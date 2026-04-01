<?php
declare(strict_types=1);

/**
 * Core – Security Utilities (Rate-limit, Input Validation, reCAPTCHA, IP)
 * Fully isolated, typed, Enterprise-Grade
 *
 * @package ContactIn
 */

namespace ContactInbox\Core;

use ContactInbox\Traits\Singleton;

// phpcs:disable WordPress.WP.I18n.TextDomainMismatch

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Security {
	use Singleton;

	private const RATE_LIMIT_MAX     = 5;
	private const RATE_LIMIT_TTL     = 2 * MINUTE_IN_SECONDS;
	private const PHONE_REGEX        = '/^\+?[0-9]{7,20}$/';
	private const MAX_EMAIL_LENGTH   = 100;
	private const MAX_NAME_LENGTH    = 100;
	private const MIN_NAME_LENGTH    = 2;
	private const MIN_MESSAGE_LENGTH = 10;
	private const MAX_MESSAGE_LENGTH = 10000;

	private function __construct() {}

	// =========================================================================
	// Rate Limiting
	// =========================================================================

	/**
	 * Check rate limit per IP & form
	 */
	public static function check_rate_limit( string $form_id = 'default' ): bool {
		$ip    = self::get_ip_address();
		$key   = "ci_rate_{$form_id}_{$ip}";
		$count = (int) get_transient( $key );

		if ( $count >= self::RATE_LIMIT_MAX ) {
			return false;
		}

		set_transient( $key, $count + 1, self::RATE_LIMIT_TTL );
		return true;
	}

	// =========================================================================
	// reCAPTCHA Verification
	// =========================================================================

	/**
	 * Verify reCAPTCHA v3 token
	 */
	public static function verify_recaptcha( string $response ): bool {
		$secret = get_option( 'ci_recaptcha_secret' );
		if ( ! $secret || empty( $response ) ) {
			return true; // disabled or empty token → trusted
		}

		$remote_ip     = self::get_ip_address();
		$response_data = wp_remote_post(
			'https://www.google.com/recaptcha/api/siteverify',
			array(
				'timeout'    => 8,
				'body'       => array(
					'secret'   => $secret,
					'response' => $response,
					'remoteip' => $remote_ip,
				),
				'user-agent' => 'ContactInbox/' . CONTACTINBOX_VERSION . ' | WordPress/' . get_bloginfo( 'version' ),
			)
		);

		if ( is_wp_error( $response_data ) ) {
			Logger::warning(
				'reCAPTCHA verification failed',
				array(
					'error' => $response_data->get_error_message(),
					'ip'    => $remote_ip,
				)
			);
			return false;
		}

		$result = json_decode( wp_remote_retrieve_body( $response_data ), true );
		return ! empty( $result['success'] ) && ( $result['score'] ?? 0 ) >= 0.5;
	}

	// =========================================================================
	// IP Detection
	// =========================================================================

	/**
	 * Get visitor IP address (safe)
	 */
	public static function get_ip_address(): string {
		return isset( $_SERVER['REMOTE_ADDR'] )
			? sanitize_text_field( wp_unslash( (string) $_SERVER['REMOTE_ADDR'] ) )
			: '';
	}

	// =========================================================================
	// Input Validation
	// =========================================================================

	/**
	 * Validate form input
	 *
	 * @param array<string,mixed> $input
	 * @return array<int,string> Errors
	 */
	public static function validate_input( array $input ): array {
		$errors = array();

		// Name
		$name = trim( $input['name'] ?? '' );
		if ( empty( $name ) || strlen( $name ) < self::MIN_NAME_LENGTH || strlen( $name ) > self::MAX_NAME_LENGTH ) {
			$errors[] = __( 'Please enter a valid name (2–100 characters).', 'contactin' );
		}

		// Email
		$email = trim( $input['email'] ?? '' );
		if ( ! self::is_valid_email( $email ) ) {
			$errors[] = __( 'Please enter a valid, real email address.', 'contactin' );
		}

		// Phone (optional)
		if ( ! empty( $input['phone'] ) && ! self::is_valid_phone( $input['phone'] ) ) {
			$errors[] = __( 'Please enter a valid phone number (optional).', 'contactin' );
		}

		// Message
		$message = trim( $input['message'] ?? '' );
		if ( empty( $message ) || strlen( $message ) < self::MIN_MESSAGE_LENGTH || strlen( $message ) > self::MAX_MESSAGE_LENGTH ) {
			$errors[] = __( 'Message must be 10–10,000 characters.', 'contactin' );
		}

		// Consent
		if ( empty( $input['consent'] ) ) {
			$errors[] = __( 'You must agree to data processing.', 'contactin' );
		}

		return $errors;
	}

	// =========================================================================
	// Email & Phone Validators
	// =========================================================================

	/**
	 * Validate email strictly
	 */
	public static function is_valid_email( string $email ): bool {
		$email = strtolower( trim( $email ) );

		if ( empty( $email ) || strlen( $email ) > self::MAX_EMAIL_LENGTH || ! is_email( $email ) ) {
			return false;
		}

		$disposable_domains = apply_filters(
			'contactin_disposable_email_domains',
			array(
				'10minutemail.com',
				'guerrillamail.com',
				'tempmail.org',
				'mailinator.com',
				'yopmail.com',
				'throwaway.email',
				'disposable-mail.com',
				'sharklasers.com',
				'getairmail.com',
				'maildrop.cc',
				'temp-mail.org',
				'armyspy.com',
			)
		);

		$domain = substr( strrchr( $email, '@' ), 1 );
		if ( in_array( $domain, $disposable_domains, true ) ) {
			return false;
		}

		return true;
	}

	/**
	 * Validate phone (optional, flexible)
	 */
	public static function is_valid_phone( string $phone ): bool {
		$phone = preg_replace( '/[^0-9\+]/', '', trim( $phone ) );
		if ( empty( $phone ) ) {
			return true; // optional
		}
		return (bool) preg_match( self::PHONE_REGEX, $phone );
	}

	/**
	 * Honeypot check – returns true if spam bot filled the hidden field.
	 */
	public static function is_honeypot_triggered(): bool {
		$nonce_value = '';
		if ( isset( $_POST['nonce'] ) ) {
			$nonce_value = sanitize_text_field( wp_unslash( (string) $_POST['nonce'] ) );
		} elseif ( isset( $_POST['_wpnonce'] ) ) {
			$nonce_value = sanitize_text_field( wp_unslash( (string) $_POST['_wpnonce'] ) );
		}

		if ( $nonce_value !== '' ) {
			$nonce_v1       = wp_verify_nonce( $nonce_value, 'contactinbox_nonce_action' );
			$nonce_v2       = wp_verify_nonce( $nonce_value, 'contactin_nonce_action' );
			$nonce_v3       = wp_verify_nonce( $nonce_value, 'contactin_submit_form' );
			$nonce_is_valid = $nonce_v1 || $nonce_v2 || $nonce_v3;

			if ( ! $nonce_is_valid ) {
				return true;
			}
		}

		foreach ( $_POST as $key => $value ) {
			$honeypot_key   = sanitize_key( wp_unslash( (string) $key ) );
			$honeypot_value = wp_unslash( (string) $value );
			if ( strpos( $honeypot_key, 'ci_hp_' ) === 0 && ! empty( $honeypot_value ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Sanitize input specifically for REST API requests.
	 *
	 * @param array<string,mixed> $params
	 * @return array<string,mixed>
	 */
	public static function rest_sanitize_input( array $params ): array {
		return array(
			'name'    => sanitize_text_field( $params['name'] ?? '' ),
			'email'   => sanitize_email( $params['email'] ?? '' ),
			'phone'   => sanitize_text_field( $params['phone'] ?? '' ),
			'message' => wp_kses_post( $params['message'] ?? '' ),
			'consent' => self::rest_normalize_consent( $params['consent'] ?? false ),
			'form_id' => sanitize_text_field( $params['form_id'] ?? 'default' ),
		);
	}

	/**
	 * Normalize consent flag for REST API.
	 *
	 * @param mixed $value
	 * @return bool
	 */
	public static function rest_normalize_consent( mixed $value ): bool {
		return in_array( $value, array( true, 'true', '1', 1 ), true );
	}
}
