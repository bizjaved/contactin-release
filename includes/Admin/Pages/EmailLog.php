<?php
namespace ContactInbox\Admin\Pages;

use ContactInbox\Traits\Singleton;
use ContactInbox\Core\Config;
use ContactInbox\Core\Repositories\EmailLogRepository;
use ContactInbox\Core\Settings;
use ContactInbox\Admin\Traits\ExportHelper;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class EmailLog {
    use Singleton;
    use ExportHelper;

    private EmailLogRepository $repo;

    protected function __construct() {
        $this->repo = new EmailLogRepository();
        add_action( 'admin_init', [ $this, 'hooks' ] );

        // AJAX handlers - support both naming conventions
        add_action( 'wp_ajax_contactinbox_get_email_log', [ $this, 'ajax_get_log' ] );
        add_action( 'wp_ajax_contactinbox_get_adjacent_email_log', [ $this, 'ajax_get_adjacent' ] );
        add_action( 'wp_ajax_contactinbox_prune_email_logs', [ $this, 'ajax_prune' ] );
        add_action( 'wp_ajax_contactin_prune_email_logs', [ $this, 'ajax_prune' ] ); // Legacy support
        add_action( 'wp_ajax_contactinbox_email_clear_all_logs', [ $this, 'ajax_clear_all_logs' ] );
        add_action( 'wp_ajax_contactin_email_clear_all_logs', [ $this, 'ajax_clear_all_logs' ] ); // Legacy support
        add_action( 'wp_ajax_contactinbox_download_email_csv', [ $this, 'ajax_download_csv' ] );
        add_action( 'wp_ajax_contactinbox_get_email_logs', [ $this, 'ajax_get_logs' ] );
        add_action( 'wp_ajax_contactinbox_email_export_info', [ $this, 'ajax_export_info' ] );
    }

    public static function render(): void {
        if ( ! current_user_can( Config::CAPABILITY ) ) {
            wp_die(
                esc_html__( 'You do not have sufficient permissions to access this page.', Config::TEXTDOMAIN )
            );
        }

        // Instantiate and prepare table in controller, not template
        $table = new \ContactInbox\Core\EmailLogTable();
        $table->prepare_items();

        $total_items   = (int) $table->get_pagination_arg( 'total_items' );
        $per_page      = (int) $table->get_pagination_arg( 'per_page' );
        $current_page  = (int) $table->get_pagination_arg( 'page' );
        $total_pages   = (int) $table->get_pagination_arg( 'total_pages' );

        $start = ( $current_page - 1 ) * $per_page + 1;
        $end   = min( $start + $per_page - 1, $total_items );

        // Build absolute path to template
        $template = CONTACTINBOX_PATH . Config::TEMPLATE_ADMIN . 'email-log-page.php';

        if ( file_exists( $template ) ) {
            include $template;
        } else {
            echo '<div class="notice notice-error"><p>'
                . esc_html__( 'Email log template not found.', Config::TEXTDOMAIN )
                . '</p></div>';
        }
    }

    public function hooks(): void {
        // Placeholder for notices/assets
    }

    /**
     * AJAX: Download CSV of email logs (with batching support).
     */
    public function ajax_download_csv(): void {
        // Security: nonce (AJAX parameter)
        if (!isset($_REQUEST['_wpnonce']) || !wp_verify_nonce($_REQUEST['_wpnonce'], Config::NONCE_ACTION)) {
            wp_send_json_error(['message' => __('Security check failed.', Config::TEXTDOMAIN)]);
        }

        // Security: capability
        if (!current_user_can(Config::CAPABILITY)) {
            wp_send_json_error(['message' => __('Permission denied.', Config::TEXTDOMAIN)]);
        }

        // Sanitize filters
        $status = isset($_POST['status']) && $_POST['status'] !== 'all'
            ? sanitize_text_field($_POST['status'])
            : '';
        $orderby = sanitize_key($_POST['orderby'] ?? 'created_at');
        $order   = strtoupper(sanitize_text_field($_POST['order'] ?? 'DESC'));

        // Batching/chunking support
        $limit  = isset($_GET['limit']) ? max(1, min(absint($_GET['limit']), Config::EXPORT_LIMIT)) : Config::EXPORT_LIMIT;
        $batch  = isset($_GET['batch']) ? max(1, absint($_GET['batch'])) : 1;
        $offset = ($batch - 1) * $limit;

        // Fetch rows via repository
        $rows = $this->repo->get_with_limit_offset($limit, $offset, $status, $orderby, $order);

        if (empty($rows)) {
            wp_send_json_error(['message' => __('No logs to export.', Config::TEXTDOMAIN)]);
        }

        // Build CSV
        $csv = $this->build_csv_data($rows);
        if (!$csv) {
            wp_send_json_error(['message' => __('Failed to generate CSV data.', Config::TEXTDOMAIN)]);
        }

        // Prepare response
        $total_batches = absint($_GET['total_batches'] ?? 0);
        $filename = $this->get_export_filename('email-log', $batch, $total_batches);

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

        $status = isset($_POST['status']) && $_POST['status'] !== 'all'
            ? sanitize_text_field($_POST['status'])
            : '';

        $total   = $this->repo->count($status);
        $limit   = Config::EXPORT_LIMIT;
        $batches = (int) ceil(max(0, $total) / $limit);

        wp_send_json_success([
            'total'   => $total,
            'limit'   => $limit,
            'batches' => $batches,
            'message' => sprintf(__('Found %d email logs. Export limit: %d per file.', Config::TEXTDOMAIN), $total, $limit),
        ]);
    }

    public function ajax_get_logs(): void {
        check_ajax_referer( Config::NONCE_ACTION, 'nonce' );
        if ( ! current_user_can( Config::CAPABILITY ) ) {
            wp_send_json_error([ 'message' => __( 'Permission denied.', Config::TEXTDOMAIN ) ]);
        }

        $status  = isset($_POST['status']) && $_POST['status'] !== 'all'
            ? sanitize_text_field($_POST['status'])
            : '';
        $orderby = sanitize_key($_POST['orderby'] ?? 'created_at');
        $order   = strtoupper(sanitize_text_field($_POST['order'] ?? 'DESC'));
        $limit   = absint($_POST['limit'] ?? 20);
        $offset  = absint($_POST['offset'] ?? 0);

        $rows = $this->repo->get_with_limit_offset($limit, $offset, $status, $orderby, $order);

        if (empty($rows)) {
            wp_send_json_success([
                'rows'    => [],
                'message' => __( 'No email logs found.', Config::TEXTDOMAIN ),
            ]);
        }

        wp_send_json_success([
            'rows'    => $rows,
            'count'   => count($rows),
            'message' => sprintf(
                __( 'Loaded %d email logs.', Config::TEXTDOMAIN ),
                count($rows)
            ),
        ]);
    }

    /**
     * AJAX: Get single email log
     */
    public function ajax_get_log(): void {
        check_ajax_referer( Config::NONCE_ACTION, 'nonce' );
        if ( ! current_user_can( Config::CAPABILITY ) ) {
            wp_send_json_error([ 'message' => __( 'Permission denied.', Config::TEXTDOMAIN ) ]);
        }

        $id     = absint( $_POST['id'] ?? 0 );
        $status = sanitize_text_field( $_POST['status'] ?? 'all' );

        if ( ! $id ) {
            wp_send_json_error([ 'message' => __( 'Invalid log ID.', Config::TEXTDOMAIN ) ]);
        }

        $row = $this->repo->get_by_id( $id );
        if ( ! $row ) {
            wp_send_json_error([ 'message' => __( 'Log not found.', Config::TEXTDOMAIN ) ]);
        }

        // Always compute navigation flags with current filters
        $hasPrev = (bool) $this->repo->get_adjacent( $row['id'], 'prev', $status );
        $hasNext = (bool) $this->repo->get_adjacent( $row['id'], 'next', $status );

        wp_send_json_success([
            'id'        => (int) $row['id'],
            'timestamp' => $row['created_at'],
            'recipient' => $row['recipient'],
            'subject'   => $row['subject'],
            'status'    => $row['status'],
            'error'     => $row['error_message'] ?? '',
            'headers'   => $row['headers'] ?? '',
            'body'      => $row['body'] ?? '',
            'hasPrev'   => $hasPrev,
            'hasNext'   => $hasNext,
        ]);
    }

    /**
     * AJAX: Get adjacent email log (Prev/Next)
     */
    public function ajax_get_adjacent(): void {
        check_ajax_referer( Config::NONCE_ACTION, 'nonce' );
        if ( ! current_user_can( Config::CAPABILITY ) ) {
            wp_send_json_error([ 'message' => __( 'Permission denied.', Config::TEXTDOMAIN ) ]);
        }

        $direction  = sanitize_text_field( $_POST['direction'] ?? '' );
        $current_id = absint( $_POST['current_id'] ?? 0 );
        $status     = sanitize_text_field( $_POST['status'] ?? 'all' );

        if ( ! $current_id || ! in_array( $direction, [ 'prev', 'next' ], true ) ) {
            wp_send_json_error([ 'message' => __( 'Invalid request.', Config::TEXTDOMAIN ) ]);
        }

        $row = $this->repo->get_adjacent( $current_id, $direction, $status );
        if ( ! $row ) {
            // Return error response with navigation flags for button state management
            $hasPrev = (bool) $this->repo->get_adjacent( $current_id, 'prev', $status );
            $hasNext = (bool) $this->repo->get_adjacent( $current_id, 'next', $status );
            
            wp_send_json_error([
                'message' => __( 'No more logs in this direction.', Config::TEXTDOMAIN ),
                'hasPrev' => $hasPrev,
                'hasNext' => $hasNext,
            ]);
        }

        $hasPrev = (bool) $this->repo->get_adjacent( $row['id'], 'prev', $status );
        $hasNext = (bool) $this->repo->get_adjacent( $row['id'], 'next', $status );

        wp_send_json_success([
            'id'        => (int) $row['id'],
            'timestamp' => $row['created_at'],
            'recipient' => $row['recipient'],
            'subject'   => $row['subject'],
            'status'    => $row['status'],
            'error'     => $row['error_message'] ?? '',
            'headers'   => $row['headers'] ?? '',
            'body'      => $row['body'] ?? '',
            'hasPrev'   => $hasPrev,
            'hasNext'   => $hasNext,
        ]);
    }

    /**
     * AJAX: Prune old email logs
     */
    public function ajax_prune(): void {
        check_ajax_referer( Config::NONCE_ACTION, 'nonce' );
        if ( ! current_user_can( Config::CAPABILITY ) ) {
            wp_send_json_error([ 'message' => __( 'Permission denied.', Config::TEXTDOMAIN ) ]);
        }

        $settings = Settings::get_settings();
        $days     = absint( $settings['email_log_retention_days'] ?? 90 );
        $deleted  = $this->repo->prune( $days );

        wp_send_json_success([
            'message' => sprintf(
                __( 'Pruned %d email logs older than %d days.', Config::TEXTDOMAIN ),
                (int) $deleted,
                $days
            ),
            'deleted' => (int) $deleted,
            'days'    => $days,
        ]);
    }

    /**
     * AJAX: Clear all email logs
     */
    public function ajax_clear_all_logs(): void {
        check_ajax_referer( 'contactinbox_email_clear_all_logs', '_ajax_nonce' );
        if ( ! current_user_can( Config::CAPABILITY ) ) {
            wp_send_json_error([ 'message' => __( 'Permission denied.', Config::TEXTDOMAIN ) ]);
        }

        $deleted = $this->repo->clear_all();

        if ( $deleted > 0 ) {
            wp_send_json_success([
                'message' => sprintf(
                    __( 'Successfully cleared %d email logs.', Config::TEXTDOMAIN ),
                    $deleted
                ),
                'deleted' => $deleted,
            ]);
        } else {
            wp_send_json_success([
                'message' => __( 'No email logs to clear.', Config::TEXTDOMAIN ),
                'deleted' => 0,
            ]);
        }
    }
}
