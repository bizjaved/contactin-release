<?php
namespace ContactInbox\Admin\Pages;

use ContactInbox\Traits\Singleton;
use ContactInbox\Core\Config;
use ContactInbox\Core\DB;
use ContactInbox\Core\Settings;

if (!defined('ABSPATH')) exit;
// phpcs:disable WordPress.WP.I18n.NonSingularStringLiteralDomain, WordPress.WP.I18n.MissingTranslatorsComment, WordPress.Security.ValidatedSanitizedInput.MissingUnslash, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized, WordPress.Security.NonceVerification.Recommended, WordPress.Security.NonceVerification.Missing, WordPress.WP.I18n.UnorderedPlaceholdersText

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * REST API Integration page for managing API keys, tokens, and monitoring.
 *
 * Displays:
 * - Active API tokens/keys with management options
 * - Base endpoint URL and documentation
 * - Health status and connection indicators
 * - Usage statistics and metrics
 * - Links to related pages (test, logs)
 */
final class RestApiIntegration {
    use Singleton;

    /**
     * Constructor – wires up AJAX handlers.
     */
    protected function __construct() {
        add_action( 'wp_ajax_contactinbox_generate_rest_token', [ $this, 'ajax_generate_token' ] );
        add_action( 'wp_ajax_contactinbox_revoke_rest_token', [ $this, 'ajax_revoke_token' ] );
        add_action( 'wp_ajax_contactinbox_get_rest_health', [ $this, 'ajax_get_health' ] );
        add_action( 'wp_ajax_contactinbox_test_api_connection', [ $this, 'ajax_test_connection' ] );
        add_action( 'wp_ajax_contactinbox_toggle_restapi_service', [ $this, 'ajax_toggle_restapi_service' ] );
        add_action( 'wp_ajax_contactinbox_save_rate_limits', [ $this, 'ajax_save_rate_limits' ] );
        
        // Legacy action hooks (contactin_ prefix)
        add_action( 'wp_ajax_contactin_generate_rest_token', [ $this, 'ajax_generate_token' ] );
        add_action( 'wp_ajax_contactin_revoke_rest_token', [ $this, 'ajax_revoke_token' ] );
        add_action( 'wp_ajax_contactin_get_rest_health', [ $this, 'ajax_get_health' ] );
        add_action( 'wp_ajax_contactin_test_api_connection', [ $this, 'ajax_test_connection' ] );
        add_action( 'wp_ajax_contactin_toggle_restapi_service', [ $this, 'ajax_toggle_restapi_service' ] );
        add_action( 'wp_ajax_contactin_save_rate_limits', [ $this, 'ajax_save_rate_limits' ] );
    }

    /**
     * AJAX: Save REST API rate limit settings via WordPress Settings.
     * 
     * This updates the REST API security throttle limits only.
     * Not to be confused with any other rate limiting in the system.
     * Uses Settings class (which calls update_option) - no direct DB transactions.
     */
    public function ajax_save_rate_limits(): void {
        check_ajax_referer( Config::NONCE_ACTION, 'nonce' );
        if ( ! current_user_can( Config::CAPABILITY ) ) {
            wp_send_json_error( [ 'message' => __( 'Permission denied.',  'contactin') ] );
        }

        $value = absint( $_POST['rate_limit_value'] ?? 60 );
        $unit  = sanitize_text_field( $_POST['rate_limit_unit'] ?? 'minute' );

        // Validate unit and set the appropriate rate limit
        if ( ! in_array( $unit, [ 'minute', 'hour', 'day' ], true ) ) {
            wp_send_json_error( [ 'message' => __( 'Invalid rate limit unit.',  'contactin') ] );
        }

        // Ensure value is at least 1
        $value = max( 1, $value );

        // Get current settings and update only the selected unit's limit
        $settings = Settings::get_settings();
        
        if ( $unit === 'minute' ) {
            $current = (int) ( $settings['rate_limit_per_minute'] ?? 0 );
            $settings['rate_limit_per_minute'] = $value;
            $label = __( 'Per Minute',  'contactin');
        } elseif ( $unit === 'hour' ) {
            $current = (int) ( $settings['rate_limit_per_hour'] ?? 0 );
            $settings['rate_limit_per_hour'] = $value;
            $label = __( 'Per Hour',  'contactin');
        } else {
            $current = (int) ( $settings['rate_limit_per_day'] ?? 0 );
            $settings['rate_limit_per_day'] = $value;
            $label = __( 'Per Day',  'contactin');
        }

        // Update via Settings class (uses WordPress update_option, not direct DB)
        $unchanged = ( $current === $value );
        $updated = $unchanged ? true : Settings::update_settings( $settings );

        if ( $updated ) {
            wp_send_json_success([
                'message' => $unchanged
                    ? sprintf( __( 'Rate limit %s unchanged (%d).',  'contactin'), $label, $value )
                    : sprintf( __( 'Rate limit %s updated to %d.',  'contactin'), $label, $value ),
                'unit' => $unit,
                'value' => $value,
            ]);
        } else {
            wp_send_json_error([
                'message' => __( 'Failed to save rate limit.',  'contactin'),
            ]);
        }
    }

    /**
     * 
     * Prevents disabling REST API if file attachment is enabled in settings,
     * since file attachment requires the REST API service.
     */
    public function ajax_toggle_restapi_service(): void {
        check_ajax_referer( Config::NONCE_ACTION, 'nonce' );
        if ( ! current_user_can( Config::CAPABILITY ) ) {
            wp_send_json_error( [ 'message' => __( 'Permission denied.',  'contactin') ] );
        }

        // Accept both 'enable' and 'enabled' parameter names for compatibility
        $enable = isset( $_POST['enable'] ) ? (int) $_POST['enable'] : ( isset( $_POST['enabled'] ) ? (int) $_POST['enabled'] : 0 );
        $enable = (bool) ( $enable === 1 );
        
        // Get all settings
        $settings = Settings::get_settings();
        
        // Check if file attachment is enabled
        $attachment_enabled = ! empty( $settings['form_enable_attachment'] );
        $force_disable = isset( $_POST['force_disable'] ) && $_POST['force_disable'] === '1';
        
        // Always show confirmation modal when disabling REST API (to warn about file upload dependency)
        if ( ! $enable && ! $force_disable ) {
            wp_send_json_error([
                'message' => $attachment_enabled 
                    ? __( 'File attachment is currently enabled and requires the REST API service.',  'contactin')
                    : __( 'The REST API service is required for file attachment uploads. Disabling it will prevent the file upload feature from working.',  'contactin'),
                'code'    => 'attachment_requires_restapi',
                'enabled' => true, // Keep the service enabled
                'requires_confirmation' => true,
            ]);
        }
        
        // If force disable, also disable file attachment if it's enabled
        $attachment_was_disabled = false;
        if ( ! $enable && $attachment_enabled && $force_disable ) {
            $settings['form_enable_attachment'] = false;
            $attachment_was_disabled = true;
        }
        
        // Update the REST API setting
        $settings['restapi_enable'] = $enable;
        
        // Update settings
        $updated = Settings::update_settings( $settings );
        
        // Clear the cache to ensure fresh read
        Settings::clear_cache();

        $button = $enable
            ? __( 'Disable REST API Service',  'contactin')
            : __( 'Enable REST API Service',  'contactin');
        $label = $enable ? __( 'Service Enabled',  'contactin') : __( 'Service Disabled',  'contactin');
        
        // Custom success message based on what was disabled
        $message = $enable 
            ? __( 'REST API service enabled successfully',  'contactin')
            : ( $attachment_was_disabled 
                ? __( 'REST API service and file attachment feature disabled successfully',  'contactin')
                : __( 'REST API service disabled successfully',  'contactin')
            );

        wp_send_json_success([
            'enabled' => $enable,
            'button'  => $button,
            'label'   => $label,
            'message' => $message,
        ]);
    }

    /**
     * Render the REST API Integration page.
     * Instantiates the Singleton first to register AJAX handlers.
     */
    public static function render(): void {
        if ( ! current_user_can( Config::CAPABILITY ) ) {
            wp_die( esc_html__( 'Permission denied.',  'contactin') );
        }
        // Handle REST API enable/disable toggle
        if ( isset( $_POST['contactinbox_toggle_restapi'], $_POST['contactinbox_restapi_toggle'] ) ) {
            $settings = \ContactInbox\Core\Settings::get_settings();
            $settings['restapi_enable'] = ( $_POST['contactinbox_restapi_toggle'] === 'enable' );
            \ContactInbox\Core\Settings::update_settings( $settings );
            // Redirect to avoid resubmission and refresh status
            wp_safe_redirect( add_query_arg( [ 'page' => $_GET['page'] ?? 'contactin-restapi' ], admin_url( 'admin.php' ) ) );
            exit;
        }
        self::instance()->display_page();
    }

    /**
     * Display the REST API Integration page content.
     */
    private function display_page(): void {
        $this->enqueue_assets();
        include CONTACTINBOX_PATH . Config::TEMPLATE_ADMIN . 'restapi-integration-page.php';
    }

    /**
     * Enqueue CSS and JS for this page.
     */
    private function enqueue_assets(): void {
        $asset_class = new \ContactInbox\Admin\Assets\RestApiIntegrationAssets();
        $asset_class->enqueue();
    }

    /**
     * Get active REST API tokens from RestApiRoutes.
     *
     * @return array List of active tokens with metadata (keyed by token ID).
     */
    public static function get_active_tokens(): array {
        $tokens = get_option( 'contactin_test_tokens', [] );

        if ( ! is_array( $tokens ) ) {
            return [];
        }

        // Filter to active (non-revoked and non-expired) tokens only
        $active = [];
        foreach ( $tokens as $token_id => $token_meta ) {
            $is_revoked = ! empty( $token_meta['revoked'] );
            $is_expired = ! empty( $token_meta['expires_at'] ) && $token_meta['expires_at'] < time();
            if ( ! $is_revoked && ! $is_expired ) {
                // Add the ID to the metadata for easier template access
                $token_meta['id'] = $token_id;
                $active[ $token_id ] = $token_meta;
            }
        }
        return $active;
    }

    /**
     * Get REST API health/usage statistics.
     *
     * @return array Health metrics including last call, success rate, etc.
     */
    public static function get_health_stats(): array {
        $db = DB::instance();

        // Get last successful REST call
        $recent_logs = $db->get_rest_logs( 1, 0, null, null, null, null, 'timestamp', 'DESC' );
        $last_call = ! empty( $recent_logs ) ? reset( $recent_logs ) : null;

        // Get call statistics for last 24 hours (past 1 day)
        $calls_24h = $db->count_rest_logs( null, null, null, null, 1 );
        $success_24h = $db->count_rest_logs( null, null, 200, null, 1 );

        // Get call statistics for last 7 days (past 7 days)
        $calls_7d = $db->count_rest_logs( null, null, null, null, 7 );
        $success_7d = $db->count_rest_logs( null, null, 200, null, 7 );

        return [
            'is_enabled'      => ! empty( Settings::get_settings()['restapi_enable'] ),
            'last_call_time'  => $last_call['timestamp'] ?? null,
            'last_endpoint'   => $last_call['endpoint'] ?? null,
            'calls_24h'       => $calls_24h,
            'success_24h'     => $success_24h,
            'success_rate_24h' => $calls_24h > 0 ? round( ( $success_24h / $calls_24h ) * 100 ) : 0,
            'calls_7d'        => $calls_7d,
            'success_7d'      => $success_7d,
            'success_rate_7d' => $calls_7d > 0 ? round( ( $success_7d / $calls_7d ) * 100 ) : 0,
        ];
    }

    /**
     * AJAX: Generate a new REST API token.
     * 
     * Delegates to RestApiRoutes for token generation to maintain
     * a single source of truth for token management.
     */
    public function ajax_generate_token(): void {
        check_ajax_referer( Config::NONCE_ACTION, 'nonce' );
        if ( ! current_user_can( Config::CAPABILITY ) ) {
            wp_send_json_error( [ 'message' => __( 'Permission denied.',  'contactin') ] );
        }

        $scopes = isset( $_POST['routes'] ) ? array_map( 'sanitize_text_field', (array) $_POST['routes'] ) : [];
        $ttl = isset( $_POST['ttl'] ) ? absint( $_POST['ttl'] ) : 3600; // default 1 hour

        // Default scopes if none specified
        if ( empty( $scopes ) ) {
            $scopes = [ '/contactin/v1/submit' ];
        }

        try {
            $token_info = \ContactInbox\Integrations\RestApiRoutes::issue_test_token( $scopes, $ttl );
            
            wp_send_json_success( [
                'message' => __( 'Token generated successfully. Copy it now – you won\'t see it again!',  'contactin'),
                'token'   => $token_info['token'],
                'token_id' => $token_info['id'],
                'expires_at' => $token_info['expires_at'],
            ] );
        } catch ( Exception $e ) {
            wp_send_json_error( [ 'message' => $e->getMessage() ] );
        }
    }

    /**
     * AJAX: Revoke a REST API token.
     * 
     * Delegates to RestApiRoutes for token revocation to maintain
     * a single source of truth for token management.
     */
    public function ajax_revoke_token(): void {
        check_ajax_referer( Config::NONCE_ACTION, 'nonce' );
        if ( ! current_user_can( Config::CAPABILITY ) ) {
            wp_send_json_error( [ 'message' => __( 'Permission denied.',  'contactin') ] );
        }

        $token_id = sanitize_text_field( $_POST['token_id'] ?? '' );

        if ( empty( $token_id ) ) {
            wp_send_json_error( [ 'message' => __( 'Invalid token ID.',  'contactin') ] );
        }

        try {
            $revoked = \ContactInbox\Integrations\RestApiRoutes::revoke_test_token( $token_id );
            
            if ( $revoked ) {
                wp_send_json_success( [ 'message' => __( 'Token revoked successfully.',  'contactin') ] );
            } else {
                wp_send_json_error( [ 'message' => __( 'Token not found or already revoked.',  'contactin') ] );
            }
        } catch ( Exception $e ) {
            wp_send_json_error( [ 'message' => $e->getMessage() ] );
        }
    }

    /**
     * AJAX: Get REST API health statistics.
     */
    public function ajax_get_health(): void {
        check_ajax_referer( Config::NONCE_ACTION, 'nonce' );
        if ( ! current_user_can( Config::CAPABILITY ) ) {
            wp_send_json_error( [ 'message' => __( 'Permission denied.',  'contactin') ] );
        }

        $health = self::get_health_stats();
        wp_send_json_success( $health );
    }

    /**
     * AJAX: Test API connection with provided data and token.
     */
    public function ajax_test_connection(): void {
        check_ajax_referer( Config::NONCE_ACTION, 'nonce' );
        if ( ! current_user_can( Config::CAPABILITY ) ) {
            wp_send_json_error( [ 'message' => __( 'Permission denied.',  'contactin') ] );
        }

        $name       = sanitize_text_field( $_POST['name'] ?? '' );
        $email      = sanitize_email( $_POST['email'] ?? '' );
        $message    = sanitize_textarea_field( $_POST['message'] ?? '' );
        $salutation = sanitize_text_field( $_POST['salutation'] ?? '' );
        $subject    = sanitize_text_field( $_POST['subject'] ?? '' );

        if ( empty( $name ) || empty( $email ) || empty( $message ) ) {
            wp_send_json_error( [ 'message' => __( 'All fields are required.',  'contactin') ] );
        }

        if ( ! is_email( $email ) ) {
            wp_send_json_error( [ 'message' => __( 'Invalid email address.',  'contactin') ] );
        }

        // Prepare test payload
        $payload = [
            'name'    => $name,
            'email'   => $email,
            'message' => $message,
            'form_id' => 'api_test',
        ];

        if ( ! empty( $salutation ) ) {
            $payload['salutation'] = $salutation;
        }

        if ( ! empty( $subject ) ) {
            $payload['subject'] = $subject;
        }

        // Make request to the REST endpoint
        $endpoint = rest_url( 'contactin/v1/submit' );
        $has_attachment = ! empty( $_FILES['attachment'] ) && ! empty( $_FILES['attachment']['tmp_name'] );

        if ( $has_attachment ) {
            if ( ! empty( $_FILES['attachment']['error'] ) ) {
                wp_send_json_error( [
                    'message' => __( 'Attachment upload failed.',  'contactin'),
                    'error'   => 'upload_error',
                ] );
            }

            // Upload file to temp storage using REST API (goes through proper REST stack with logging)
            $upload_request = new \WP_REST_Request( 'POST', '/contactin/v1/upload-attachment' );
            $upload_request->set_file_params( [ 'file' => $_FILES['attachment'] ] );
            
            // Use rest_do_request to go through the full REST API stack
            // This ensures proper request/response logging and validation
            $upload_response = rest_do_request( $upload_request );
            
            if ( is_wp_error( $upload_response ) ) {
                wp_send_json_error( [
                    'message' => $upload_response->get_error_message(),
                    'error'   => $upload_response->get_error_code(),
                ] );
            }

            $upload_data = ( $upload_response instanceof \WP_REST_Response ) ? $upload_response->get_data() : [];
            if ( empty( $upload_data['temp_path'] ) ) {
                wp_send_json_error( [
                    'message' => __( 'Attachment upload failed.',  'contactin'),
                    'error'   => 'upload_failed',
                ] );
            }

            $payload['attachment'] = $upload_data['temp_path'];
        }

        $response = wp_remote_post( $endpoint, [
            'method'  => 'POST',
            'headers' => [
                'Content-Type' => 'application/json',
            ],
            'body'    => wp_json_encode( $payload ),
            'timeout' => 15,
        ] );

        if ( is_wp_error( $response ) ) {
            wp_send_json_error( [
                'message' => sprintf(
                    __( 'Connection failed: %s',  'contactin'),
                    $response->get_error_message()
                ),
                'error'   => $response->get_error_code(),
            ] );
        }

        $status_code = wp_remote_retrieve_response_code( $response );
        $body        = wp_remote_retrieve_body( $response );
        $data        = json_decode( $body, true );

        if ( $status_code >= 200 && $status_code < 300 ) {
            wp_send_json_success( [
                'message'     => __( 'Test request successful!',  'contactin'),
                'status_code' => $status_code,
                'response'    => $data,
                'message_id'  => $data['data']['id'] ?? null,
            ] );
        } else {
            wp_send_json_error( [
                'message'     => __( 'Test request failed with status code: ',  'contactin') . $status_code,
                'status_code' => $status_code,
                'response'    => $data,
                'error'       => $data['message'] ?? __( 'Unknown error',  'contactin'),
            ] );
        }
    }
}
