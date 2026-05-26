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

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class RestController {

	/**
	 * Normalize and sanitize submission params for REST submissions.
	 */
	private static function sanitize_submit_params( array $params ): array {
		$sanitized = array(
			'salutation'            => sanitize_text_field( (string) ( $params['salutation'] ?? '' ) ),
			'name'                  => sanitize_text_field( (string) ( $params['name'] ?? '' ) ),
			'email'                 => sanitize_email( (string) ( $params['email'] ?? '' ) ),
			'phone'                 => sanitize_text_field( (string) ( $params['phone'] ?? '' ) ),
			'message'               => sanitize_textarea_field( (string) ( $params['message'] ?? '' ) ),
			'consent'               => ! empty( $params['consent'] ) ? 1 : 0,
			'form_id'               => sanitize_key( (string) ( $params['form_id'] ?? 'default' ) ),
			'subject'               => sanitize_text_field( (string) ( $params['subject'] ?? '' ) ),
			'g-recaptcha-response'  => sanitize_text_field( (string) ( $params['g-recaptcha-response'] ?? '' ) ),
			'recaptcha_token'       => sanitize_text_field( (string) ( $params['recaptcha_token'] ?? '' ) ),
		);

		// Preserve honeypot fields so anti-spam checks can inspect them.
		foreach ( $params as $key => $value ) {
			if ( strpos( (string) $key, 'ci_hp_' ) === 0 ) {
				$sanitized[ (string) $key ] = sanitize_text_field( (string) $value );
			}
		}

		return $sanitized;
	}

	private static function render_submit_error( string $message, string $tip = '' ): array {
		$failure_html = \ContactInbox\Core\TemplateLoader::render(
			CONTACTINBOX_PATH . 'templates/frontend/form-failure-message.php',
			array(
				'failure_message' => $message,
				'failure_tip'     => $tip,
			)
		);

		return array(
			'success'   => false,
			'action'    => 'submit',
			'message'   => $message,
			'data'      => array( 'html' => $failure_html ),
			'timestamp' => time(),
		);
	}

	private static function ensure_enabled() {
		$settings = CoreSettings::get_settings();
		if ( empty( $settings['restapi_enable'] ) ) {
			return new WP_Error(
				Config::ERR_REST_DISABLED,
				__( 'REST API service is disabled in plugin settings.', 'contactin' ),
				array( 'status' => 403 )
			);
		}
		return true;
	}

	// -------------------------------------------------------------------------
	// Handlers
	// -------------------------------------------------------------------------
	public static function submit( \WP_REST_Request $request ) {
		// Ensure the controller is enabled
		$enabled = self::ensure_enabled();
		if ( $enabled instanceof \WP_Error ) {
			return $enabled;
		}

		// Extract incoming params and files
		$params = $request->get_json_params() ?? $request->get_body_params() ?? array();
		if ( ! is_array( $params ) ) {
			$params = array();
		}
		$params   = self::sanitize_submit_params( $params );
		$files    = $request->get_file_params() ?? array();
		$form_id  = sanitize_key( (string) ( $params['form_id'] ?? 'default' ) );
		$settings = \ContactInbox\Core\FormProfiles::resolve( $form_id );

		// Anti-spam parity for REST submissions
		$client_ip  = Security::get_ip_address();
		$rate_limit = RateLimiter::check_rate_limit( $client_ip );
		if ( empty( $rate_limit['allowed'] ) ) {
			return self::render_submit_error(
				__( 'Too many requests from this network. Please wait and try again.', 'contactin' ),
				__( 'Rate limit triggered for this IP. Please retry after a short delay.', 'contactin' )
			);
		}

		foreach ( (array) $params as $key => $value ) {
			if ( strpos( (string) $key, 'ci_hp_' ) === 0 && strlen( trim( (string) $value ) ) > 0 ) {
				RateLimiter::record_request( $client_ip );
				return self::render_submit_error(
					__( 'Spam detected. Submission blocked.', 'contactin' ),
					__( 'Honeypot validation failed.', 'contactin' )
				);
			}
		}

		if ( ! empty( $settings['recaptcha_enable'] ) && ! empty( $settings['recaptcha_site_key'] ) ) {
			$token = (string) ( $params['g-recaptcha-response'] ?? $params['recaptcha_token'] ?? '' );
			if ( $token === '' ) {
				RateLimiter::record_request( $client_ip );
				return self::render_submit_error(
					__( 'Security verification missing. Please try again.', 'contactin' ),
					__( 'reCAPTCHA token is required for submission.', 'contactin' )
				);
			}

			$recaptcha = reCAPTCHA::verify_with_score( $token );
			if ( empty( $recaptcha['valid'] ) ) {
				RateLimiter::record_request( $client_ip );
				return self::render_submit_error(
					__( 'Security verification failed. Submission blocked.', 'contactin' ),
					__( 'reCAPTCHA verification failed.', 'contactin' )
				);
			}
		}

		// FormService.submit() handles validation and persistence.
		// - Validation
		// - Duplicate detection
		// - Database save (atomic transaction)
		// Returns: ['message_id' => int, 'payload' => array]
		$form_result = \ContactInbox\Core\FormService::submit( $params, $files, $settings );
		if ( is_wp_error( $form_result ) ) {
			// Render failure template
			$failure_html = \ContactInbox\Core\TemplateLoader::render(
				CONTACTINBOX_PATH . 'templates/frontend/form-failure-message.php',
				array(
					'failure_message' => $form_result->get_error_message(),
					'failure_tip'     => '',
				)
			);
			return array(
				'success'   => false,
				'action'    => 'submit',
				'message'   => $form_result->get_error_message(),
				'data'      => array( 'html' => $failure_html ),
				'timestamp' => time(),
			);
		}

		// Record accepted REST submission attempt for sliding window rate-limit tracking
		RateLimiter::record_request( $client_ip );

		// FormService already saved the message and returned validated payload.
		// Extract data from the result
		$message_id = $form_result['message_id'] ?? 0;
		$payload    = $form_result['payload'] ?? array();

		// Trigger analytics and queue processing hooks
		// NOTE: This should only fire ONCE per submission
		if ( ! empty( $payload ) && $message_id > 0 ) {
			do_action( 'contactin_message_received', $message_id, $payload );

			// Queue async post-submit processing to match FormHandler behavior.
			$contact_id = $form_result['contact_id'] ?? null;
			if ( ! wp_next_scheduled( 'contactin_post_submit_homework', array( $message_id, $contact_id ) ) ) {
				wp_schedule_single_event( time(), 'contactin_post_submit_homework', array( $message_id, $contact_id ) );
			}

			// Nudge WP-Cron immediately; if disabled, run the hook inline as a fallback
			$spawned = spawn_cron();
			if ( ! $spawned ) {
				do_action( 'contactin_post_submit_homework', $message_id, $contact_id );
			}
		}

		// Render success template
		$success_html = \ContactInbox\Core\TemplateLoader::render(
			CONTACTINBOX_PATH . 'templates/frontend/form-success-message.php',
			array(
				'settings'       => $settings,
				'delete_link'    => '',
				'attachment'     => '',
				'masked_receipt' => '',
			)
		);

		// Return success response
		return array(
			'success'   => true,
			'action'    => 'submit',
			'id'        => $message_id,
			'message'   => __( 'Message received successfully', 'contactin' ),
			'data'      => array( 'html' => $success_html ),
			'timestamp' => time(),
		);
	}

	public static function list_messages( WP_REST_Request $request ) {
		$enabled = self::ensure_enabled();
		if ( $enabled instanceof WP_Error ) {
			return $enabled;
		}

		$page    = (int) $request->get_param( 'page' ) ?: 1;
		$perPage = (int) $request->get_param( 'per_page' ) ?: Config::INBOX_PER_PAGE;

		return FormService::list_messages(
			array(
				'page'     => $page,
				'per_page' => $perPage,
			)
		);
	}

	public static function read( WP_REST_Request $request ) {
		$enabled = self::ensure_enabled();
		if ( $enabled instanceof WP_Error ) {
			return $enabled;
		}

		$id = (int) $request->get_param( 'id' );
		return FormService::read( $id );
	}

	public static function update_status( WP_REST_Request $request ) {
		$enabled = self::ensure_enabled();
		if ( $enabled instanceof WP_Error ) {
			return $enabled;
		}

		$params = $request->get_json_params() ?? $request->get_body_params() ?? array();
		$id     = (int) ( $params['id'] ?? $request->get_param( 'id' ) );
		$status = (string) ( $params['status'] ?? $request->get_param( 'status' ) );

		return FormService::update_status( $id, $status );
	}

	public static function search_messages( WP_REST_Request $request ) {
		$enabled = self::ensure_enabled();
		if ( $enabled instanceof WP_Error ) {
			return $enabled;
		}

		$q       = (string) $request->get_param( 'q' );
		$page    = (int) $request->get_param( 'page' ) ?: 1;
		$perPage = (int) $request->get_param( 'per_page' ) ?: Config::INBOX_PER_PAGE;

		return FormService::search_messages( $q, $page, $perPage );
	}

	public static function bulk_delete( WP_REST_Request $request ) {
		$enabled = self::ensure_enabled();
		if ( $enabled instanceof WP_Error ) {
			return $enabled;
		}

		$params = $request->get_json_params() ?? $request->get_body_params() ?? array();
		$ids    = (array) ( $params['ids'] ?? $request->get_param( 'ids' ) );

		return FormService::bulk_delete( $ids );
	}
}
