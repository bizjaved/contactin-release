<?php
/**
 * Integrations – WebhookController Routes
 *
 * Adds HMAC verification and Application Passwords support.
 *
 * @package ContactIn\Integrations
 */

namespace ContactInbox\Integrations;

use WP_Error;
use WP_REST_Request;
use ContactInbox\Admin\Controllers\WebhookController;
use ContactInbox\Core\Config;
use ContactInbox\Core\Settings as CoreSettings;
use ContactInbox\Core\WebhookSignature;

if (!defined('ABSPATH')) exit;
// phpcs:disable WordPress.WP.I18n.NonSingularStringLiteralDomain

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class WebhookRoutes {

    /**
     * Initialize webhook routes and logging middleware.
     */
    public static function init(): void {
        add_action( 'rest_api_init', [ __CLASS__, 'register_routes' ] );
        // Add centralized webhook request/response logging
        add_filter( 'rest_post_dispatch', [ __CLASS__, 'log_request' ], 10, 3 );
    }

    /**
     * Register REST routes only if WebhookController is enabled.
     */
    public static function register_routes(): void {
        $settings = CoreSettings::get_settings();
        if ( empty( $settings['webhooks_enable'] ) ) {
            return; // Webhooks disabled in settings
        }

        $namespace = 'contactin/v1';

        // Generic webhook endpoint (submit new message)
        register_rest_route( $namespace, Config::WEBHOOK_ENDPOINT_SUBMIT, [
            'methods'             => 'POST',
            'callback'            => [ WebhookController::class, 'submit' ],
            'permission_callback' => [ __CLASS__, 'permission_public' ],
            'show_in_index'       => false,
        ] );

        // Status update endpoint
        register_rest_route( $namespace, Config::WEBHOOK_ENDPOINT_STATUS, [
            'methods'             => 'POST',
            'callback'            => [ WebhookController::class, 'update_status' ],
            'permission_callback' => [ __CLASS__, 'permission_public' ],
            'show_in_index'       => false,
        ] );

        // Optional: get_status parity
        register_rest_route( $namespace, Config::WEBHOOK_ENDPOINT_STATUS . '/get', [
            'methods'             => 'POST',
            'callback'            => [ WebhookController::class, 'get_status' ],
            'permission_callback' => [ __CLASS__, 'permission_public' ],
            'show_in_index'       => false,
        ] );
    }

    /**
     * Permission callback for webhook endpoints.
     *
     * Allows:
     * 1. Unauthenticated public access (for external webhooks)
     * 2. Authenticated admin access with nonce verification
     * 3. HMAC-signed webhook requests
     */
    public static function permission_public( WP_REST_Request $request ): bool|WP_Error {
        $settings = CoreSettings::get_settings();
        if ( empty( $settings['webhooks_enable'] ) ) {
            return new WP_Error(
                'webhooks_disabled',
                __( 'Webhooks service is disabled in plugin settings.',  'contactin'),
                [ 'status' => 403 ]
            );
        }

        // 1) WP-authenticated user (cookie or Application Passwords)
        if ( is_user_logged_in() && current_user_can( Config::CAPABILITY ) ) {
            // Verify nonce for admin users
            $nonce = $request->get_header( 'X-WP-Nonce' );
            if ( $nonce && wp_verify_nonce( $nonce, Config::NONCE_ACTION ) ) {
                return true;
            }
            // Admin without valid nonce gets 403
            return new WP_Error(
                'rest_cookie_invalid_nonce',
                __( 'Nonce verification failed',  'contactin'),
                [ 'status' => 403 ]
            );
        }

        $secret = self::get_webhook_secret();
        $signature = $request->get_header( 'X-ContactIN-Signature' ) ?: $request->get_header( 'X-Securech-Signature' );
        $timestamp = self::get_timestamp_header( $request );

        // 2) If a secret is configured, require a valid signature
        if ( $secret !== '' ) {
            if ( empty( $signature ) ) {
                return new WP_Error(
                    'webhook_signature_missing',
                    __( 'Missing webhook signature.',  'contactin'),
                    [ 'status' => 401 ]
                );
            }

            if ( ! self::validate_webhook_hmac( $request, $signature, $secret, $timestamp ) ) {
                return new WP_Error(
                    'webhook_signature_invalid',
                    __( 'Webhook signature validation failed.',  'contactin'),
                    [ 'status' => 401 ]
                );
            }

            if ( $timestamp && WebhookSignature::is_replay( $signature, $timestamp ) ) {
                return new WP_Error(
                    'webhook_replay_detected',
                    __( 'Webhook replay detected.',  'contactin'),
                    [ 'status' => 401 ]
                );
            }

            return true;
        }

        // 3) HMAC webhook verification when signature is present but secret is optional
        if ( $signature && self::validate_webhook_hmac( $request, $signature, $secret, $timestamp ) ) {
            return true;
        }

        // 4) Allow unauthenticated public access when no secret is configured
        return true;
    }

    /**
     * Validate webhook HMAC signature
     *
     * - Reads secret from option 'contactin_webhook_secret'
     * - Expects signature to be hex-encoded HMAC-SHA256 of the raw request body
     * - Uses hash_equals to avoid timing attacks
     */
    private static function validate_webhook_hmac( WP_REST_Request $request, string $signature, string $secret, ?int $timestamp = null ): bool {
        if ( $secret === '' ) {
            return false;
        }

        $body = $request->get_body();
        if ( $body === null ) {
            return false;
        }

        // If a timestamp is provided, require freshness
        if ( $timestamp && ! WebhookSignature::is_fresh( $timestamp ) ) {
            return false;
        }

        return WebhookSignature::verify( $body, $secret, $signature, $timestamp );
    }

    /**
     * Get configured webhook secret.
     */
    private static function get_webhook_secret(): string {
        return (string) get_option( 'contactin_webhook_secret', '' );
    }

    /**
     * Get timestamp header if present.
     */
    private static function get_timestamp_header( WP_REST_Request $request ): ?int {
        $raw = $request->get_header( 'X-ContactIN-Timestamp' ) ?: $request->get_header( 'X-Securech-Timestamp' );
        if ( $raw === null || $raw === '' ) {
            return null;
        }
        $timestamp = (int) $raw;
        return $timestamp > 0 ? $timestamp : null;
    }

    /**
     * Centralized webhook logging middleware.
     * Logs all webhook requests and responses for ContactIN endpoints.
     *
     * @param WP_REST_Response|WP_Error $response The response object.
     * @param WP_REST_Server            $server   The REST server.
     * @param WP_REST_Request           $request  The request object.
     * @return WP_REST_Response|WP_Error The unmodified response.
     */
    public static function log_request( $response, $server, $request ) {
        // Only log our ContactIN webhook routes
        $route = $request->get_route();
        if ( strpos( $route, '/contactin/v1/webhook' ) === false ) {
            return $response;
        }

        // Extract request details
        $method           = $request->get_method();
        $endpoint         = $request->get_route();
        $request_headers  = $request->get_headers();
        $request_payload  = $request->get_json_params() ?? $request->get_body_params() ?? [];
        
        // Extract response details
        $response_code = 200;
        $response_body = [];
        
        if ( $response instanceof \WP_REST_Response ) {
            $response_code = $response->get_status();
            $response_body = $response->get_data();
        } elseif ( $response instanceof \WP_Error ) {
            $response_code = $response->get_error_data()['status'] ?? 400;
            $response_body = [
                'code'    => $response->get_error_code(),
                'message' => $response->get_error_message(),
            ];
        }

        // Log the webhook request through the centralized DB class
        \ContactInbox\Core\DB::instance()->insert_webhook_log([
            'ip_address'      => isset($_SERVER['REMOTE_ADDR'])
                ? sanitize_text_field(wp_unslash((string) $_SERVER['REMOTE_ADDR']))
                : '',
            'headers'         => $request_headers,
            'payload'         => $request_payload,
            'validated'       => ( $response_code >= 200 && $response_code < 300 ) ? 1 : 0,
            'http_code'       => $response_code,
            'error_message'   => is_wp_error( $response ) ? $response->get_error_message() : null,
            'integration_type' => 'webhook',
        ]);

        return $response;
    }
}


