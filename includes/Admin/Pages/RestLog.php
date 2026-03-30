<?php
namespace ContactInbox\Admin\Pages;

use ContactInbox\Traits\Singleton;
use ContactInbox\Admin\Traits\ExportHelper;
use ContactInbox\Core\Config;
use ContactInbox\Core\DB;
use ContactInbox\Core\RestLogTable;
use ContactInbox\Core\Repositories\RestLogRepository;

if (!defined('ABSPATH')) exit;
// phpcs:disable WordPress.WP.I18n.NonSingularStringLiteralDomain, WordPress.Security.ValidatedSanitizedInput, WordPress.Security.NonceVerification, WordPress.WP.I18n.UnorderedPlaceholdersText, WordPress.WP.I18n.MissingTranslatorsComment

if ( ! defined( 'ABSPATH' ) ) exit;

final class RestLog {
    use Singleton;
    use ExportHelper;

    private RestLogRepository $repo;

    private function __construct() {
        $this->repo = new RestLogRepository();
        // Modern naming
        add_action( 'wp_ajax_contactinbox_view_rest_log', [ $this, 'ajax_view' ] );
        add_action( 'wp_ajax_contactinbox_get_adjacent_rest_log', [ $this, 'ajax_get_adjacent_rest_log' ] );
        add_action( 'wp_ajax_contactinbox_prune_rest', [ $this, 'ajax_prune' ] );
        add_action( 'wp_ajax_contactinbox_clear_rest_logs', [ $this, 'ajax_clear_all' ] );
        add_action( 'wp_ajax_contactinbox_download_rest_csv', [ $this, 'ajax_download_csv' ] );
        add_action( 'wp_ajax_contactinbox_rest_export_info', [ $this, 'ajax_export_info' ] );
        
        // Legacy support (JavaScript uses these)
        add_action( 'wp_ajax_contactin_view_rest_log', [ $this, 'ajax_view' ] );
        add_action( 'wp_ajax_contactin_get_adjacent_rest_log', [ $this, 'ajax_get_adjacent_rest_log' ] );
        add_action( 'wp_ajax_contactin_prune_rest', [ $this, 'ajax_prune' ] );
        add_action( 'wp_ajax_contactin_clear_rest_logs', [ $this, 'ajax_clear_all' ] );
        add_action( 'wp_ajax_contactin_download_rest_csv', [ $this, 'ajax_download_csv' ] );
    }

    public static function render(): void {
        if ( ! current_user_can( Config::CAPABILITY ) ) {
            wp_die(
                esc_html__( 'You do not have sufficient permissions to access this page.',  'contactin')
            );
        }
        
        $instance = self::instance();
        
        // Instantiate and prepare the table
        $table = new RestLogTable();
        $table->prepare_items();
        
        // Get pagination variables from table
        $total_items = (int) $table->get_pagination_arg('total_items');
        $per_page = (int) $table->get_pagination_arg('per_page');
        $current_page = (int) $table->get_pagination_arg('page');
        
        // Calculate pagination display values
        $start = ($current_page - 1) * $per_page + 1;
        $end = min($start + $per_page - 1, $total_items);
        
        $template = CONTACTINBOX_PATH . Config::TEMPLATE_ADMIN . 'rest-log-page.php';
        if ( file_exists( $template ) ) {
            include $template;
        } else {
            echo '<div class="notice notice-error"><p>'
                . esc_html__( 'REST API log template not found.',  'contactin')
                . '</p></div>';
        }
    }

    public function ajax_view(): void {
        check_ajax_referer( Config::NONCE_ACTION, 'nonce' );
        if ( ! current_user_can( Config::CAPABILITY ) ) {
            wp_send_json_error([ 'message' => __( 'Permission denied.',  'contactin') ]);
        }

        $id             = absint( $_POST['id'] ?? 0 );
        $http_method    = sanitize_text_field( $_POST['http_method'] ?? 'all' );
        $endpoint       = sanitize_text_field( $_POST['endpoint'] ?? 'all' );
        $http_code      = sanitize_text_field( $_POST['http_code'] ?? 'all' );
        $validated      = sanitize_text_field( $_POST['validated'] ?? 'all' );

        if ( ! $id ) {
            wp_send_json_error([ 'message' => __( 'Invalid log ID.',  'contactin') ]);
        }

        $row = DB::instance()->get_rest_log( $id );
        if ( ! $row ) {
            wp_send_json_error([ 'message' => __( 'Log entry not found.',  'contactin') ]);
        }

        // Pretty‑print JSON fields
        $headers_pretty  = $this->format_json_field( $row['request_headers'] ?? '' );
        $payload_pretty  = $this->format_json_field( $row['request_payload'] ?? '' );
        $response_pretty = $this->format_json_field( $row['response_body'] ?? '' );

        // Always compute navigation flags with current filters
        $hasPrev = (bool) DB::instance()->get_adjacent_rest_log( $row['id'], 'prev', $http_method, $endpoint, $http_code, $validated );
        $hasNext = (bool) DB::instance()->get_adjacent_rest_log( $row['id'], 'next', $http_method, $endpoint, $http_code, $validated );

        wp_send_json_success([
            'id'              => (int) $row['id'],
            'timestamp'       => $row['timestamp'],
            'ip_address'      => $row['ip_address'] ?? '',
            'user_agent'      => $row['user_agent'] ?? '',
            'user_id'         => isset( $row['user_id'] ) ? (int) $row['user_id'] : null,
            'method'          => $row['http_method'] ?? '',
            'endpoint'        => $row['endpoint'] ?? '',
            'code'            => isset( $row['response_code'] ) ? (int) $row['response_code'] : null,
            'validated'       => ! empty( $row['validated'] ),
            'token_valid'     => ! empty( $row['token_valid'] ),
            'error_code'      => $row['error_code'] ?? '',
            'error_message'   => $row['error_message'] ?? '',
            'request_headers' => $headers_pretty,
            'request_payload' => $payload_pretty,
            'response_body'   => $response_pretty,
            'hasPrev'         => $hasPrev,
            'hasNext'         => $hasNext,
        ]);
    }

    public function ajax_get_adjacent_rest_log(): void {
        check_ajax_referer( Config::NONCE_ACTION, 'nonce' );
        if ( ! current_user_can( Config::CAPABILITY ) ) {
            wp_send_json_error([ 'message' => __( 'Permission denied.',  'contactin') ]);
        }

        $direction      = sanitize_text_field( $_POST['direction'] ?? '' );
        $current_id     = absint( $_POST['current_id'] ?? 0 );
        $http_method    = sanitize_text_field( $_POST['http_method'] ?? 'all' );
        $endpoint       = sanitize_text_field( $_POST['endpoint'] ?? 'all' );
        $http_code      = sanitize_text_field( $_POST['http_code'] ?? 'all' );
        $validated      = sanitize_text_field( $_POST['validated'] ?? 'all' );

        if ( ! $current_id || ! in_array( $direction, [ 'prev', 'next' ], true ) ) {
            wp_send_json_error([ 'message' => __( 'Invalid request.',  'contactin') ]);
        }

        $row = DB::instance()->get_adjacent_rest_log( $current_id, $direction, $http_method, $endpoint, $http_code, $validated );
        if ( ! $row ) {
            // Gold standard: still return flags so UI can disable correctly
            $hasPrev = (bool) DB::instance()->get_adjacent_rest_log( $current_id, 'prev', $http_method, $endpoint, $http_code, $validated );
            $hasNext = (bool) DB::instance()->get_adjacent_rest_log( $current_id, 'next', $http_method, $endpoint, $http_code, $validated );

            wp_send_json_error([
                'message' => __( 'No more logs in this direction.',  'contactin'),
                'hasPrev' => $hasPrev,
                'hasNext' => $hasNext,
            ]);
        }

        $headers_pretty  = $this->format_json_field( $row['request_headers'] ?? '' );
        $payload_pretty  = $this->format_json_field( $row['request_payload'] ?? '' );
        $response_pretty = $this->format_json_field( $row['response_body'] ?? '' );

        // Always compute flags relative to this new row
        $hasPrev = (bool) DB::instance()->get_adjacent_rest_log( $row['id'], 'prev', $http_method, $endpoint, $http_code, $validated );
        $hasNext = (bool) DB::instance()->get_adjacent_rest_log( $row['id'], 'next', $http_method, $endpoint, $http_code, $validated );

        wp_send_json_success([
            'id'              => (int) $row['id'],
            'timestamp'       => $row['timestamp'],
            'ip_address'      => $row['ip_address'] ?? '',
            'user_agent'      => $row['user_agent'] ?? '',
            'user_id'         => isset( $row['user_id'] ) ? (int) $row['user_id'] : null,
            'method'          => $row['http_method'] ?? '',
            'endpoint'        => $row['endpoint'] ?? '',
            'code'            => isset( $row['response_code'] ) ? (int) $row['response_code'] : null,
            'validated'       => ! empty( $row['validated'] ),
            'token_valid'     => ! empty( $row['token_valid'] ),
            'error_code'      => $row['error_code'] ?? '',
            'error_message'   => $row['error_message'] ?? '',
            'request_headers' => $headers_pretty,
            'request_payload' => $payload_pretty,
            'response_body'   => $response_pretty,
            'hasPrev'         => $hasPrev,
            'hasNext'         => $hasNext,
        ]);
    }

    public function ajax_prune(): void {
        check_ajax_referer( Config::NONCE_ACTION, 'nonce' );
        if ( ! current_user_can( Config::CAPABILITY ) ) {
            wp_send_json_error([ 'message' => __( 'Permission denied.',  'contactin') ]);
        }

        $settings       = get_option( Config::OPTION_SETTINGS, [] );
        $retention_days = absint( $settings['rest_log_retention_days'] ?? 30 );

        $deleted = $this->repo->prune( $retention_days );

        if ( $deleted > 0 ) {
            wp_send_json_success([
                'message' => sprintf(
                    __( 'Pruned %d REST API logs older than %d days.',  'contactin'),
                    (int) $deleted,
                    $retention_days
                ),
                'deleted' => (int) $deleted,
                'days'    => $retention_days,
            ]);
        } else {
            wp_send_json_success([ 'message' => __( 'No old REST API logs to prune.',  'contactin') ]);
        }
    }

    public function ajax_clear_all(): void {
        check_ajax_referer( Config::NONCE_ACTION, 'nonce' );
        if ( ! current_user_can( Config::CAPABILITY ) ) {
            wp_send_json_error([ 'message' => __( 'Permission denied.',  'contactin') ]);
        }

        if ( $this->repo->clear_all() ) {
            wp_send_json_success([
                'message' => __( 'All REST API logs have been cleared successfully.',  'contactin'),
            ]);
        } else {
            wp_send_json_error([ 'message' => __( 'Failed to clear REST API logs.',  'contactin') ]);
        }
    }

    public function ajax_download_csv(): void {
            // Feature gating: CSV export is a premium feature
            if ( ! \ContactInbox\Integration\FreemiusIntegration::can_use_premium_features() ) {
                wp_send_json_error( [ 'message' => __( 'This feature requires a Pro license.',  'contactin') ] );
            }

            check_ajax_referer( Config::NONCE_ACTION, 'nonce' );

            // Collect filters from request
            $method   = isset($_POST['method']) && $_POST['method'] !== 'all'
                ? sanitize_text_field( $_POST['method'] )
                : null;
            $endpoint = isset($_POST['endpoint']) && $_POST['endpoint'] !== 'all'
                ? sanitize_text_field( $_POST['endpoint'] )
                : null;
            $orderby  = sanitize_key($_POST['orderby'] ?? 'timestamp');
            $order    = strtoupper( sanitize_text_field( $_POST['order'] ?? 'DESC' ) );

            // Optional extra filters
            $http_code = isset($_POST['http_code']) && $_POST['http_code'] !== 'all'
                ? intval( $_POST['http_code'] )
                : null;
            $validated = isset($_POST['validated']) && $_POST['validated'] !== 'all'
                ? intval( $_POST['validated'] )
                : null;

            // Batching/chunking support
            $limit  = isset( $_GET['limit'] ) ? max( 1, min( absint( $_GET['limit'] ), Config::EXPORT_LIMIT ) ) : Config::EXPORT_LIMIT;
            $batch  = isset( $_GET['batch'] ) ? max( 1, absint( $_GET['batch'] ) ) : 1;
            $offset = ($batch - 1) * $limit;

            // Pass filters into DB query via repository
            $rows = $this->repo->get_with_limit_offset(
                $limit,
                $offset,
                $method,
                $endpoint,
                $http_code,
                $validated,
                $orderby,
                $order
            );

            if (empty($rows)) {
                wp_send_json_error([
                    'message' => __( 'No logs to export.',  'contactin'),
                ]);
            }

            // Build CSV
            $csv = $this->build_csv_data( $rows );
            if (!$csv) {
                wp_send_json_error(['message' => __('Failed to generate CSV data.',  'contactin')]);
            }

            $total_batches = absint( $_GET['total_batches'] ?? 0 );
            $filename = $this->get_export_filename( 'rest-log', $batch, $total_batches );

            // Send CSV file download
            $this->send_csv_download( $csv, $filename );
    }

    /**
     * AJAX: Export info (total, batches, limit) for client-side orchestration.
     */
    public function ajax_export_info(): void {        // Feature gating: CSV export is a premium feature
        if ( ! \ContactInbox\Integration\FreemiusIntegration::can_use_premium_features() ) {
            wp_send_json_error( [ 'message' => __( 'This feature requires a Pro license.',  'contactin') ] );
        }
            check_ajax_referer( Config::NONCE_ACTION, 'nonce' );
            if ( ! current_user_can( Config::CAPABILITY ) ) {
                wp_send_json_error([ 'message' => __( 'Permission denied.',  'contactin') ]);
            }

            // Collect filters from request
            $method   = isset($_POST['method']) && $_POST['method'] !== 'all'
                ? sanitize_text_field( $_POST['method'] )
                : null;
            $endpoint = isset($_POST['endpoint']) && $_POST['endpoint'] !== 'all'
                ? sanitize_text_field( $_POST['endpoint'] )
                : null;
            $http_code = isset($_POST['http_code']) && $_POST['http_code'] !== 'all'
                ? intval( $_POST['http_code'] )
                : null;
            $validated = isset($_POST['validated']) && $_POST['validated'] !== 'all'
                ? intval( $_POST['validated'] )
                : null;

            $total   = $this->repo->count( $method, $endpoint, $http_code, $validated );
            $limit   = Config::EXPORT_LIMIT;
            $batches = (int) ceil( max( 0, $total ) / $limit );

            wp_send_json_success([
                'total'   => $total,
                'limit'   => $limit,
                'batches' => $batches,
                'message' => sprintf( __( 'Found %d REST API logs. Export limit: %d per file.',  'contactin'), $total, $limit ),
            ]);
    }

    /**
     * Utility: Format JSON fields nicely
     */
    private function format_json_field( string $raw ): string {
        $decoded = json_decode( $raw, true );
        if ( is_array( $decoded ) ) {
            return wp_json_encode( $decoded, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES );
        }
        return $raw;
    }

    public static function record(
        string $http_method,
        string $endpoint,
        mixed $payload,
        mixed $response,
        int $response_code = 200
    ): void {
        DB::instance()->log_rest_call([
            'http_method'     => $http_method,
            'endpoint'        => $endpoint,
            'request_payload' => wp_json_encode($payload),
            'response_body'   => wp_json_encode($response),
            'response_code'   => $response_code,
            'ip_address'      => $_SERVER['REMOTE_ADDR'] ?? '',
            'user_agent'      => $_SERVER['HTTP_USER_AGENT'] ?? '',
            'user_id'         => get_current_user_id(),
        ]);
    }

}
