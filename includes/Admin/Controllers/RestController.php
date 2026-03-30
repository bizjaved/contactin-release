<?php
/**
 * Admin – Message REST Controller (Refactored for JSON + Form support)
 *
 * Thin REST layer: parses WP_REST_Request, delegates to FormService,
 * logs every call for auditing. No direct DB access.
 *
 * @package ContactIn\Admin
 * @since   1.6.1
 */

namespace ContactInbox\Admin\Controllers;

use ContactInbox\Core\FormService;
use ContactInbox\Core\Config;
use ContactInbox\Core\DB;
use ContactInbox\Core\Settings as CoreSettings;
use ContactInbox\Core\RateLimiter;
use ContactInbox\Core\Security;
use ContactInbox\Core\reCAPTCHA;
use WP_Error;
use WP_REST_Request;

// phpcs:disable WordPress.WP.I18n.NonSingularStringLiteralDomain

if (!defined('ABSPATH')) exit;

final class RestController {

    private static function render_submit_error(string $message, string $tip = ''): array {
        $failure_html = \ContactInbox\Core\TemplateLoader::render(
            CONTACTINBOX_PATH . 'templates/frontend/form-failure-message.php',
            [
                'failure_message' => $message,
                'failure_tip'     => $tip,
            ]
        );

        return [
            'success' => false,
            'action'  => 'submit',
            'message' => $message,
            'data'    => [ 'html' => $failure_html ],
            'timestamp' => time(),
        ];
    }

    private static function ensure_enabled() {
        $settings = CoreSettings::get_settings();
        if (empty($settings['restapi_enable'])) {
            return new WP_Error(
                Config::ERR_REST_DISABLED,
                __('REST API service is disabled in plugin settings.',  'contactin'),
                ['status' => 403]
            );
        }
        return true;
    }

    // -------------------------------------------------------------------------
    // Handlers (Route registration delegated to RestApiRoutes with proper permissions)
    // -------------------------------------------------------------------------
    public static function submit( \WP_REST_Request $request ) {
        // Ensure the controller is enabled
        $enabled = self::ensure_enabled();
        if ( $enabled instanceof \WP_Error ) {
            return $enabled;
        }

        // Extract incoming params and files
        $params = $request->get_json_params() ?? $request->get_body_params() ?? [];
        $files  = $request->get_file_params() ?? [];
        $settings = CoreSettings::get_settings();

        // Anti-spam parity for REST submissions
        $client_ip = Security::get_ip_address();
        $rate_limit = RateLimiter::check_rate_limit($client_ip);
        if (empty($rate_limit['allowed'])) {
            return self::render_submit_error(
                __('Too many requests from this network. Please wait and try again.',  'contactin'),
                __('Rate limit triggered for this IP. Please retry after a short delay.',  'contactin')
            );
        }

        foreach ((array) $params as $key => $value) {
            if (strpos((string) $key, 'ci_hp_') === 0 && strlen(trim((string) $value)) > 0) {
                RateLimiter::record_request($client_ip);
                return self::render_submit_error(
                    __('Spam detected. Submission blocked.',  'contactin'),
                    __('Honeypot validation failed.',  'contactin')
                );
            }
        }

        if (!empty($settings['recaptcha_enable']) && !empty($settings['recaptcha_site_key'])) {
            $token = (string) ($params['g-recaptcha-response'] ?? $params['recaptcha_token'] ?? '');
            if ($token === '') {
                RateLimiter::record_request($client_ip);
                return self::render_submit_error(
                    __('Security verification missing. Please try again.',  'contactin'),
                    __('reCAPTCHA token is required for submission.',  'contactin')
                );
            }

            $recaptcha = reCAPTCHA::verify_with_score($token);
            if (empty($recaptcha['valid'])) {
                RateLimiter::record_request($client_ip);
                return self::render_submit_error(
                    __('Security verification failed. Submission blocked.',  'contactin'),
                    __('reCAPTCHA verification failed.',  'contactin')
                );
            }
        }

        // FormService.submit() now handles EVERYTHING:
        // - Validation
        // - Duplicate detection
        // - Database save (atomic transaction)
        // - GDPR token generation
        // Returns: ['message_id' => int, 'payload' => array, 'gdpr_delete_link' => string]
        $form_result = \ContactInbox\Core\FormService::submit( $params, $files );
        if ( is_wp_error( $form_result ) ) {
            // Render failure template
            $failure_html = \ContactInbox\Core\TemplateLoader::render(
                CONTACTINBOX_PATH . 'templates/frontend/form-failure-message.php',
                [
                    'failure_message' => $form_result->get_error_message(),
                    'failure_tip'     => '',
                ]
            );
            return [
                'success' => false,
                'action'  => 'submit',
                'message' => $form_result->get_error_message(),
                'data'    => [ 'html' => $failure_html ],
                'timestamp' => time(),
            ];
        }

        // Record accepted REST submission attempt for sliding window rate-limit tracking
        RateLimiter::record_request($client_ip);

        // FormService already saved the message and generated GDPR token
        // Extract data from the result
        $message_id = $form_result['message_id'] ?? 0;
        $payload = $form_result['payload'] ?? [];
        $validated_files = $form_result['files'] ?? [];
        $delete_link = $form_result['gdpr_delete_link'] ?? '';

        // Trigger analytics and queue processing hooks
        // NOTE: This should only fire ONCE per submission
        if ( ! empty( $payload ) && $message_id > 0 ) {
            do_action( 'contactin_message_received', $message_id, $payload );
            
            // Queue async processing (email/CRM) - matches FormHandler behavior
            $contact_id = $form_result['contact_id'] ?? null;
            if ( ! wp_next_scheduled( 'contactin_post_submit_homework', [ $message_id, $contact_id ] ) ) {
                wp_schedule_single_event( time(), 'contactin_post_submit_homework', [ $message_id, $contact_id ] );
            }
            
            // Nudge WP-Cron immediately; if disabled, run the hook inline as a fallback
            $spawned = spawn_cron();
            if ( ! $spawned ) {
                do_action( 'contactin_post_submit_homework', $message_id, $contact_id );
            }
        }

        // Render success template (GDPR link already generated by FormService)
        $settings = get_option( Config::OPTION_SETTINGS, [] );
        
        $success_html = \ContactInbox\Core\TemplateLoader::render(
            CONTACTINBOX_PATH . 'templates/frontend/form-success-message.php',
            [
                'settings' => $settings,
                'delete_link' => $delete_link,
                'attachment' => $validated_files['attachment']['name'] ?? '',
                'masked_receipt' => '',
            ]
        );

        // Return success response
        return [
            'success'   => true,
            'action'    => 'submit',
            'id'        => $message_id,
            'message'   => __( 'Message received successfully',  'contactin'),
            'data'      => [ 'html' => $success_html ],
            'timestamp' => time(),
        ];
    }

    public static function list_messages(WP_REST_Request $request) {
        $enabled = self::ensure_enabled();
        if ($enabled instanceof WP_Error) return $enabled;

        $page = (int) $request->get_param('page') ?: 1;
        $perPage = (int) $request->get_param('per_page') ?: Config::INBOX_PER_PAGE;

        return FormService::list_messages(['page' => $page, 'per_page' => $perPage]);
    }

    public static function read(WP_REST_Request $request) {
        $enabled = self::ensure_enabled();
        if ($enabled instanceof WP_Error) return $enabled;

        $id = (int) $request->get_param('id');
        return FormService::read($id);
    }

    public static function update_status(WP_REST_Request $request) {
        $enabled = self::ensure_enabled();
        if ($enabled instanceof WP_Error) return $enabled;

        $params = $request->get_json_params() ?? $request->get_body_params() ?? [];
        $id     = (int) ($params['id'] ?? $request->get_param('id'));
        $status = (string) ($params['status'] ?? $request->get_param('status'));

        return FormService::update_status($id, $status);
    }

    public static function search_messages(WP_REST_Request $request) {
        $enabled = self::ensure_enabled();
        if ($enabled instanceof WP_Error) return $enabled;

        $q       = (string) $request->get_param('q');
        $page    = (int) $request->get_param('page') ?: 1;
        $perPage = (int) $request->get_param('per_page') ?: Config::INBOX_PER_PAGE;

        return FormService::search_messages($q, $page, $perPage);
    }

    public static function bulk_delete(WP_REST_Request $request) {
        $enabled = self::ensure_enabled();
        if ($enabled instanceof WP_Error) return $enabled;

        $params = $request->get_json_params() ?? $request->get_body_params() ?? [];
        $ids    = (array) ($params['ids'] ?? $request->get_param('ids'));

        return FormService::bulk_delete($ids);
    }

    public static function gdpr_request(WP_REST_Request $request) {
        $enabled = self::ensure_enabled();
        if ($enabled instanceof WP_Error) return $enabled;

        $params = $request->get_json_params() ?? $request->get_body_params() ?? [];
        return FormService::gdpr_request($params);
    }

    public static function gdpr_delete(WP_REST_Request $request) {
        $enabled = self::ensure_enabled();
        if ($enabled instanceof WP_Error) return $enabled;

        $token = (string) $request->get_param('token');
        return FormService::gdpr_delete($token);
    }

}
