<?php
namespace ContactInbox\Admin\Pages;

use ContactInbox\Traits\Singleton;
use ContactInbox\Core\Config;
use ContactInbox\Core\CRMLogTable;
use ContactInbox\Core\Repositories\CRMRepository;
use ContactInbox\Admin\Traits\ExportHelper;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class CRMLog {
    use Singleton;
    use ExportHelper;

    private CRMRepository $repo;

    protected function __construct() {
        $this->repo = new CRMRepository();
        add_action( 'admin_init', [ $this, 'hooks' ] );
        
        // Modern naming
        add_action('wp_ajax_contactinbox_crm_clear_all_logs', [ $this, 'ajax_clear_all_logs' ] );
        add_action('wp_ajax_contactinbox_crm_prune_old_logs', [ $this, 'ajax_prune_old_logs' ] );
        add_action('wp_ajax_contactinbox_download_crm_csv', [ $this, 'ajax_download_csv' ] );
        add_action('wp_ajax_contactinbox_crm_export_info', [ $this, 'ajax_export_info' ] );
        
        // Legacy support (JavaScript uses these)
        add_action('wp_ajax_contactin_crm_clear_all_logs', [ $this, 'ajax_clear_all_logs' ] );
        add_action('wp_ajax_contactin_crm_prune_old_logs', [ $this, 'ajax_prune_old_logs' ] );
    }

    /**
     * AJAX: Download CRM logs as CSV (with batching support).
     */
    public function ajax_download_csv(): void {
        // Accept either _ajax_nonce (WP default) or nonce (legacy) and validate against both old/new actions
        $nonce_key   = isset($_REQUEST['_ajax_nonce']) ? '_ajax_nonce' : 'nonce';
        $nonce_value = $_REQUEST[$nonce_key] ?? '';
        $valid_nonce = wp_verify_nonce($nonce_value, 'contactin_crm_clear_all_logs')
            || wp_verify_nonce($nonce_value, Config::CRM_LOG_ACTION)
            || wp_verify_nonce($nonce_value, Config::CRM_LOG_NONCE);

        if (!$valid_nonce) {
            wp_send_json_error(['message' => __('Security check failed.', Config::TEXTDOMAIN)], 403);
        }
        if ( ! current_user_can( Config::CAPABILITY ) ) {
            wp_send_json_error([ 'message' => __( 'Permission denied.', Config::TEXTDOMAIN ) ]);
        }
        
        // Batching/chunking support
        $limit  = isset($_GET['limit']) ? max(1, min(absint($_GET['limit']), Config::EXPORT_LIMIT)) : Config::EXPORT_LIMIT;
        $batch  = isset($_GET['batch']) ? max(1, absint($_GET['batch'])) : 1;
        $offset = ($batch - 1) * $limit;

        // Get logs with pagination
        $rows = $this->repo->get_logs($limit, $offset, 'created_at', 'DESC', 'all');

        if (empty($rows)) {
            wp_send_json_error([ 'message' => __( 'No logs to export.', Config::TEXTDOMAIN ) ]);
        }

        // Build CSV
        $csv = $this->build_csv_data($rows);
        if (!$csv) {
            wp_send_json_error(['message' => __('Failed to generate CSV data.', Config::TEXTDOMAIN)]);
        }

        $total_batches = absint($_GET['total_batches'] ?? 0);
        $filename = $this->get_export_filename('crm-log', $batch, $total_batches);

        // Send CSV file download
        $this->send_csv_download($csv, $filename);
    }

    /**
     * AJAX: Export info (total, batches, limit) for client-side orchestration.
     */
    public function ajax_export_info(): void {
        // Accept either _ajax_nonce (WP default) or nonce (legacy) and validate against both old/new actions
        $nonce_key   = isset($_REQUEST['_ajax_nonce']) ? '_ajax_nonce' : 'nonce';
        $nonce_value = $_REQUEST[$nonce_key] ?? '';
        $valid_nonce = wp_verify_nonce($nonce_value, 'contactin_crm_clear_all_logs')
            || wp_verify_nonce($nonce_value, Config::CRM_LOG_ACTION)
            || wp_verify_nonce($nonce_value, Config::CRM_LOG_NONCE);

        if (!$valid_nonce) {
            wp_send_json_error(['message' => __('Security check failed.', Config::TEXTDOMAIN)], 403);
        }
        if ( ! current_user_can( Config::CAPABILITY ) ) {
            wp_send_json_error([ 'message' => __( 'Permission denied.', Config::TEXTDOMAIN ) ]);
        }

        $total   = $this->repo->count_logs('all');
        $limit   = Config::EXPORT_LIMIT;
        $batches = (int) ceil(max(0, $total) / $limit);

        wp_send_json_success([
            'total'   => $total,
            'limit'   => $limit,
            'batches' => $batches,
            'message' => sprintf(__('Found %d CRM logs. Export limit: %d per file.', Config::TEXTDOMAIN), $total, $limit),
        ]);
    }

    /**
     * AJAX: Prune old CRM logs (older than 30 days)
     */
    public function ajax_prune_old_logs(): void {
        check_ajax_referer('contactin_crm_clear_all_logs'); // reuse nonce for simplicity
        $days = 30; // Prune logs older than 30 days
        $count = $this->repo->prune_old_logs($days);
        if ($count > 0) {
            wp_send_json_success(['message' => sprintf(__('Pruned %d old logs.', \ContactInbox\Core\Config::TEXTDOMAIN), $count)]);
        } else {
            wp_send_json_success(['message' => __('No old logs to prune.', \ContactInbox\Core\Config::TEXTDOMAIN)]);
        }
    }

    public function ajax_clear_all_logs(): void {
        check_ajax_referer('contactin_crm_clear_all_logs');
        $count = $this->repo->clear_all();
        if ($count > 0 || $count === 0) { // 0 is also valid (table was empty or truncated)
            wp_send_json_success(['message' => __('All CRM logs cleared.', Config::TEXTDOMAIN)]);
        } else {
            wp_send_json_error(['message' => __('Failed to clear logs.', \ContactInbox\Core\Config::TEXTDOMAIN)]);
        }
    }

    public static function render(): void {
        if ( ! current_user_can( Config::CAPABILITY ) ) {
            wp_die(
                esc_html__( 'You do not have sufficient permissions to access this page.', Config::TEXTDOMAIN )
            );
        }
        
        $instance = self::instance();
        
        // Instantiate and prepare the table
        $table = new CRMLogTable();
        $table->prepare_items();
        
        // Get pagination and filter variables from table
        $total_items = (int) $table->get_pagination_arg('total_items');
        $per_page = (int) $table->get_pagination_arg('per_page');
        $current_page = (int) $table->get_pagination_arg('page');
        $current_status = sanitize_text_field($_REQUEST['status'] ?? 'all');
        $current_operation = sanitize_text_field($_REQUEST['operation'] ?? 'all');
        
        // Get operation options from repository
        $operation_options = $instance->repo->get_operations();
        
        // Calculate pagination display values
        $start = ($current_page - 1) * $per_page + 1;
        $end = min($start + $per_page - 1, $total_items);
        
        $template = CONTACTINBOX_PATH . Config::TEMPLATE_ADMIN . 'crm-log-page.php';
        if ( file_exists( $template ) ) {
            include $template;
        } else {
            echo '<div class="notice notice-error"><p>'
                . esc_html__( 'CRM log template not found.', Config::TEXTDOMAIN )
                . '</p></div>';
        }
    }

    public function hooks(): void {
        // Placeholder for notices/assets
    }
}
