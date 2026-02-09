<?php
/**
 * Admin Page – GDPR Deletion Log
 */

declare(strict_types=1);

namespace ContactInbox\Admin\Pages;

use ContactInbox\Traits\Singleton;
use ContactInbox\Admin\Traits\ExportHelper;
use ContactInbox\Core\Config;
use ContactInbox\Core\Repositories\GDPRRepository;

if (!defined('ABSPATH')) {
    exit;
}

final class GDPRLog {
    use Singleton;
    use ExportHelper;

    private GDPRRepository $repo;

    protected function __construct() {
        $this->repo = new GDPRRepository();
        add_action('admin_init', [$this, 'hooks']);
    }

    public function hooks(): void {
        // Modern naming
        add_action('wp_ajax_contactinbox_gdpr_prune_logs', [$this, 'ajax_prune_logs']);
        add_action('wp_ajax_contactinbox_gdpr_clear_logs', [$this, 'ajax_clear_logs']);
        add_action('wp_ajax_contactinbox_download_gdpr_csv', [$this, 'ajax_download_csv']);
        add_action('wp_ajax_contactinbox_gdpr_export_info', [$this, 'ajax_export_info']);
        add_action('wp_ajax_contactinbox_gdpr_get_synced_count', [$this, 'ajax_get_synced_count']);
        
        // Legacy support
        add_action('wp_ajax_ci_gdpr_prune_logs', [$this, 'ajax_prune_logs']);
        add_action('wp_ajax_ci_gdpr_clear_logs', [$this, 'ajax_clear_logs']);
    }

    public static function render(): void {
        if (!current_user_can(Config::CAPABILITY)) {
            wp_die(esc_html__('Permission denied.', Config::TEXTDOMAIN));
        }
        self::instance()->display();
    }

    private function display(): void {
        // Filters
        $search = isset($_GET['s']) ? sanitize_text_field($_GET['s']) : '';
        $crm_filter = isset($_GET['crm_status']) ? sanitize_text_field($_GET['crm_status']) : 'all';
        $status_filter = isset($_GET['deletion_status']) ? sanitize_text_field($_GET['deletion_status']) : 'all';
        $paged = max(1, absint($_GET['paged'] ?? 1));
        $per_page = max(10, min(100, absint($_GET['per_page'] ?? 20)));

        // Get total count
        $total_items = $this->repo->count_logs($search, $crm_filter, $status_filter);

        // Calculate pagination
        $pages = max(1, (int) ceil($total_items / $per_page));
        $paged = max(1, min($paged, $pages));
        $offset = ($paged - 1) * $per_page;

        // Get logs
        $logs = $this->repo->get_logs($search, $crm_filter, $status_filter, $per_page, $offset);

        // Count by CRM status
        $synced_count = $this->repo->count_synced_completed();

        $display_start = $total_items ? (($paged - 1) * $per_page) + 1 : 0;
        $display_end = $total_items ? min($display_start + $per_page - 1, $total_items) : 0;

        $crm_filter_options = [
            'all'              => __( 'All CRM statuses', Config::TEXTDOMAIN ),
            'synced'           => __( 'Synced', Config::TEXTDOMAIN ),
            'not_synced'       => __( 'Not synced', Config::TEXTDOMAIN ),
            'unsynced'         => __( 'Unsynced', Config::TEXTDOMAIN ),
            'partially_synced' => __( 'Partially synced', Config::TEXTDOMAIN ),
            'unknown'          => __( 'Unknown', Config::TEXTDOMAIN ),
        ];

        $deletion_status_options = [
            'all'         => __( 'All deletion statuses', Config::TEXTDOMAIN ),
            'pending'     => __( 'Pending', Config::TEXTDOMAIN ),
            'in_progress' => __( 'In progress', Config::TEXTDOMAIN ),
            'completed'   => __( 'Completed', Config::TEXTDOMAIN ),
            'failed'      => __( 'Failed', Config::TEXTDOMAIN ),
        ];

        $status_labels = [
            'pending'     => __( 'Pending', Config::TEXTDOMAIN ),
            'in_progress' => __( 'In progress', Config::TEXTDOMAIN ),
            'completed'   => __( 'Completed', Config::TEXTDOMAIN ),
            'failed'      => __( 'Failed', Config::TEXTDOMAIN ),
        ];

        $crm_labels = [
            'synced'           => __( 'Synced', Config::TEXTDOMAIN ),
            'not_synced'       => __( 'Not synced', Config::TEXTDOMAIN ),
            'unsynced'         => __( 'Unsynced', Config::TEXTDOMAIN ),
            'partially_synced' => __( 'Partially synced', Config::TEXTDOMAIN ),
            'unknown'          => __( 'Unknown', Config::TEXTDOMAIN ),
        ];

        $range_summary_label = '';
        if ($total_items) {
            $range_summary_label = sprintf(
                __( 'Showing %1$s–%2$s of %3$s records', Config::TEXTDOMAIN ),
                number_format_i18n($display_start),
                number_format_i18n($display_end),
                number_format_i18n($total_items)
            );
        }

        // Load template
        $template = CONTACTINBOX_PATH . 'templates/admin/gdpr-log-page.php';
        if (!file_exists($template)) {
            wp_die(__('GDPR log template missing.', Config::TEXTDOMAIN));
        }

        // Pass variables to template
        include $template;
    }

    /**
     * AJAX: Prune old logs using retention period from settings
     */
    public function ajax_prune_logs(): void {
        check_ajax_referer(Config::NONCE_ACTION, 'nonce');
        
        if (!current_user_can(Config::CAPABILITY)) {
            wp_send_json_error(['message' => __('Permission denied.', Config::TEXTDOMAIN)]);
        }

        // Get retention days from settings
        $settings = get_option(Config::OPTION_SETTINGS, []);
        $retention_days = absint($settings['gdpr_log_retention_days'] ?? 90);

        $deleted = $this->repo->prune_old_logs($retention_days);

        if ($deleted === false) {
            wp_send_json_error(['message' => __('Failed to prune GDPR logs.', Config::TEXTDOMAIN)]);
        }

        wp_send_json_success([
            'message' => sprintf(
                __('Pruned %d GDPR log entries older than %d days.', Config::TEXTDOMAIN),
                (int) $deleted,
                $retention_days
            ),
            'deleted' => (int) $deleted,
            'days'    => $retention_days,
        ]);
    }

    /**
     * AJAX: Clear all non-synced logs
     */
    public function ajax_clear_logs(): void {
        check_ajax_referer(Config::NONCE_ACTION, 'nonce');
        
        if (!current_user_can(Config::CAPABILITY)) {
            wp_send_json_error(['message' => __('Permission denied.', Config::TEXTDOMAIN)]);
        }

        $deleted = $this->repo->clear_non_synced_logs();

        if ($deleted === false) {
            wp_send_json_error(['message' => __('Failed to clear GDPR logs.', Config::TEXTDOMAIN)]);
        }

        wp_send_json_success([
            'message' => sprintf(
                __('Cleared %d log entries.', Config::TEXTDOMAIN),
                (int) $deleted
            ),
            'deleted' => (int) $deleted,
        ]);
    }

    /**
     * AJAX: Download GDPR log as CSV
     */
    public function ajax_download_csv(): void {
        // Security: nonce validation (passed as _wpnonce in query string)
        if (!isset($_REQUEST['_wpnonce']) || !wp_verify_nonce($_REQUEST['_wpnonce'], Config::NONCE_ACTION)) {
            wp_send_json_error(['message' => __('Security check failed.', Config::TEXTDOMAIN)]);
        }
        
        if (!current_user_can(Config::CAPABILITY)) {
            wp_send_json_error(['message' => __('Permission denied.', Config::TEXTDOMAIN)]);
        }

        // Batching/chunking support
        $limit  = isset($_GET['limit']) ? max(1, min(absint($_GET['limit']), Config::EXPORT_LIMIT)) : Config::EXPORT_LIMIT;
        $batch  = isset($_GET['batch']) ? max(1, absint($_GET['batch'])) : 1;
        $offset = ($batch - 1) * $limit;

        // Get filter parameters (can come from GET or POST)
        $search = isset($_REQUEST['s']) ? sanitize_text_field($_REQUEST['s']) : '';
        $crm_status = isset($_REQUEST['crm_status']) ? sanitize_text_field($_REQUEST['crm_status']) : 'all';
        $deletion_status = isset($_REQUEST['deletion_status']) ? sanitize_text_field($_REQUEST['deletion_status']) : 'all';

        // Debug logging
        error_log('[ContactIN] GDPR Download CSV - Search: "' . $search . '", CRM: "' . $crm_status . '", Status: "' . $deletion_status . '", Batch: ' . $batch . ', Limit: ' . $limit);

        $rows = $this->repo->get_export_rows($search, $crm_status, $deletion_status, $limit, $offset);

        if (empty($rows)) {
            wp_send_json_error(['message' => __('No logs to export.', Config::TEXTDOMAIN)]);
        }

        // Build CSV with custom headers
        $headers = ['Email', 'Name', 'CRM Sync Status', 'Deletion Status', 'Deleted By', 'Deleted At'];
        $csv = $this->build_csv_data($rows, $headers);
        
        if (!$csv) {
            wp_send_json_error(['message' => __('Failed to generate CSV data.', Config::TEXTDOMAIN)]);
        }

        $total_batches = absint($_GET['total_batches'] ?? 0);
        $filename = $this->get_export_filename('gdpr-log', $batch, $total_batches);

        // Send CSV file download
        $this->send_csv_download($csv, $filename);
    }

    /**
     * AJAX: Export info (total, batches, limit) for client-side orchestration.
     */
    public function ajax_export_info(): void {
        check_ajax_referer(Config::NONCE_ACTION, 'nonce');
        
        if (!current_user_can(Config::CAPABILITY)) {
            wp_send_json_error(['message' => __('Permission denied.', Config::TEXTDOMAIN)]);
        }

        // Get filter parameters (can come from GET or POST)
        $search = isset($_REQUEST['s']) ? sanitize_text_field($_REQUEST['s']) : '';
        $crm_status = isset($_REQUEST['crm_status']) ? sanitize_text_field($_REQUEST['crm_status']) : 'all';
        $deletion_status = isset($_REQUEST['deletion_status']) ? sanitize_text_field($_REQUEST['deletion_status']) : 'all';

        // Debug logging
        error_log('[ContactIN] GDPR Export Info - Search: "' . $search . '", CRM: "' . $crm_status . '", Status: "' . $deletion_status . '"');

        $total = $this->repo->count_logs($search, $crm_status, $deletion_status);

        $limit   = Config::EXPORT_LIMIT;
        $batches = ceil($total / $limit);

        wp_send_json_success([
            'total'    => $total,
            'limit'    => $limit,
            'batches'  => $batches,
            'message' => sprintf(__('Found %d GDPR logs. Export limit: %d per file.', Config::TEXTDOMAIN), $total, $limit),
        ]);
    }

    /**
     * AJAX: Get synced contact count for analytical warnings in prune/clear modals.
     */
    public function ajax_get_synced_count(): void {
        check_ajax_referer(Config::NONCE_ACTION, 'nonce');
        
        if (!current_user_can(Config::CAPABILITY)) {
            wp_send_json_error(['message' => __('Permission denied.', Config::TEXTDOMAIN)]);
        }

        $synced_count = $this->repo->count_synced_completed();

        wp_send_json_success([
            'synced_count' => $synced_count,
        ]);
    }
}
