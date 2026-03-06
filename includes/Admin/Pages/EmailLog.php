<?php
// phpcs:disable WordPress.WP.I18n.MissingTranslatorsComment, WordPress.WP.I18n.UnorderedPlaceholdersText, WordPress.WP.I18n.NonSingularStringLiteralText
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

    private function request_text(string $key, string $default = ''): string {
        $value = filter_input(INPUT_POST, $key, FILTER_UNSAFE_RAW);
        if (null === $value || false === $value) {
            $value = filter_input(INPUT_GET, $key, FILTER_UNSAFE_RAW);
        }
        if (null === $value || false === $value) {
            return $default;
        }
        return sanitize_text_field(wp_unslash((string) $value));
    }

    private function request_key(string $key, string $default = ''): string {
        $value = filter_input(INPUT_POST, $key, FILTER_UNSAFE_RAW);
        if (null === $value || false === $value) {
            $value = filter_input(INPUT_GET, $key, FILTER_UNSAFE_RAW);
        }
        if (null === $value || false === $value) {
            return $default;
        }
        return sanitize_key(wp_unslash((string) $value));
    }

    private function request_int(string $key, int $default = 0): int {
        $value = filter_input(INPUT_POST, $key, FILTER_UNSAFE_RAW);
        if (null === $value || false === $value) {
            $value = filter_input(INPUT_GET, $key, FILTER_UNSAFE_RAW);
        }
        if (null === $value || false === $value || '' === $value) {
            return $default;
        }
        return absint(wp_unslash((string) $value));
    }

    public static function render(): void {
        if ( ! current_user_can( Config::CAPABILITY ) ) {
            wp_die(
                esc_html__( 'You do not have sufficient permissions to access this page.', 'contact-inbox' )
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
                . esc_html__( 'Email log template not found.', 'contact-inbox' )
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
        $nonce = $this->request_text('_wpnonce');
        if ('' === $nonce || !wp_verify_nonce($nonce, Config::NONCE_ACTION)) {
            wp_send_json_error(['message' => __('Security check failed.', 'contact-inbox')]);
        }

        // Security: capability
        if (!current_user_can(Config::CAPABILITY)) {
            wp_send_json_error(['message' => __('Permission denied.', 'contact-inbox')]);
        }

        // Sanitize filters
        $status_value = $this->request_text('status', 'all');
        $status = 'all' !== strtolower($status_value)
            ? $status_value
            : '';
        $orderby = $this->request_key('orderby', 'created_at');
        $order   = strtoupper($this->request_text('order', 'DESC'));

        // Batching/chunking support
        $limit  = max(1, min($this->request_int('limit', Config::EXPORT_LIMIT), Config::EXPORT_LIMIT));
        $batch  = max(1, $this->request_int('batch', 1));
        $offset = ($batch - 1) * $limit;

        // Fetch rows via repository
        $rows = $this->repo->get_with_limit_offset($limit, $offset, $status, $orderby, $order);

        if (empty($rows)) {
            wp_send_json_error(['message' => __('No logs to export.', 'contact-inbox')]);
        }

        // Build CSV
        $csv = $this->build_csv_data($rows);
        if (!$csv) {
            wp_send_json_error(['message' => __('Failed to generate CSV data.', 'contact-inbox')]);
        }

        // Prepare response
        $total_batches = $this->request_int('total_batches', 0);
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
            wp_send_json_error(['message' => __('Permission denied.', 'contact-inbox')]);
        }

        $status_value = $this->request_text('status', 'all');
        $status = 'all' !== strtolower($status_value)
            ? $status_value
            : '';

        $total   = $this->repo->count($status);
        $limit   = Config::EXPORT_LIMIT;
        $batches = (int) ceil(max(0, $total) / $limit);

        wp_send_json_success([
            'total'   => $total,
            'limit'   => $limit,
            'batches' => $batches,
            'message' => sprintf(__('Found %d email logs. Export limit: %d per file.', 'contact-inbox'), $total, $limit),
        ]);
    }

    public function ajax_get_logs(): void {
        check_ajax_referer( Config::NONCE_ACTION, 'nonce' );
        if ( ! current_user_can( Config::CAPABILITY ) ) {
            wp_send_json_error([ 'message' => __( 'Permission denied.', 'contact-inbox' ) ]);
        }

        $status_value = $this->request_text('status', 'all');
        $status  = 'all' !== strtolower($status_value)
            ? $status_value
            : '';
        $orderby = $this->request_key('orderby', 'created_at');
        $order   = strtoupper($this->request_text('order', 'DESC'));
        $limit   = $this->request_int('limit', 20);
        $offset  = $this->request_int('offset', 0);

        $rows = $this->repo->get_with_limit_offset($limit, $offset, $status, $orderby, $order);

        if (empty($rows)) {
            wp_send_json_success([
                'rows'    => [],
                'message' => __( 'No email logs found.', 'contact-inbox' ),
            ]);
        }

        wp_send_json_success([
            'rows'    => $rows,
            'count'   => count($rows),
            'message' => sprintf(
                __( 'Loaded %d email logs.', 'contact-inbox' ),
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
            wp_send_json_error([ 'message' => __( 'Permission denied.', 'contact-inbox' ) ]);
        }

        $id     = $this->request_int('id', 0);
        $status = $this->request_text('status', 'all');

        if ( ! $id ) {
            wp_send_json_error([ 'message' => __( 'Invalid log ID.', 'contact-inbox' ) ]);
        }

        $row = $this->repo->get_by_id( $id );
        if ( ! $row ) {
            wp_send_json_error([ 'message' => __( 'Log not found.', 'contact-inbox' ) ]);
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
            wp_send_json_error([ 'message' => __( 'Permission denied.', 'contact-inbox' ) ]);
        }

        $direction  = $this->request_text('direction');
        $current_id = $this->request_int('current_id', 0);
        $status     = $this->request_text('status', 'all');

        if ( ! $current_id || ! in_array( $direction, [ 'prev', 'next' ], true ) ) {
            wp_send_json_error([ 'message' => __( 'Invalid request.', 'contact-inbox' ) ]);
        }

        $row = $this->repo->get_adjacent( $current_id, $direction, $status );
        if ( ! $row ) {
            // Return error response with navigation flags for button state management
            $hasPrev = (bool) $this->repo->get_adjacent( $current_id, 'prev', $status );
            $hasNext = (bool) $this->repo->get_adjacent( $current_id, 'next', $status );
            
            wp_send_json_error([
                'message' => __( 'No more logs in this direction.', 'contact-inbox' ),
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
            wp_send_json_error([ 'message' => __( 'Permission denied.', 'contact-inbox' ) ]);
        }

        $settings = Settings::get_settings();
        $days     = absint( $settings['email_log_retention_days'] ?? 90 );
        $deleted  = $this->repo->prune( $days );

        wp_send_json_success([
            'message' => sprintf(
                __( 'Pruned %d email logs older than %d days.', 'contact-inbox' ),
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
            wp_send_json_error([ 'message' => __( 'Permission denied.', 'contact-inbox' ) ]);
        }

        $deleted = $this->repo->clear_all();

        if ( $deleted > 0 ) {
            wp_send_json_success([
                'message' => sprintf(
                    __( 'Successfully cleared %d email logs.', 'contact-inbox' ),
                    $deleted
                ),
                'deleted' => $deleted,
            ]);
        } else {
            wp_send_json_success([
                'message' => __( 'No email logs to clear.', 'contact-inbox' ),
                'deleted' => 0,
            ]);
        }
    }
}
