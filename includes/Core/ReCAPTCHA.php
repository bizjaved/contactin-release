<?php
declare(strict_types=1);

/**
 * Core – Google reCAPTCHA v3 Verification
 * Secure, fast, cached, typed, fully isolated, Enterprise-Grade
 *
 * @package ContactIn
 */

namespace ContactInbox\Core;

use ContactInbox\Traits\Singleton;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class reCAPTCHA {
	use Singleton;

	private const MIN_SCORE = 0.5;
	private const CACHE_TTL = 120; // 2 minutes (reCAPTCHA tokens are short-lived)

	private function __construct() {}

	// =========================================================================
	// Public Verification
	// =========================================================================

	/**
	 * Verify reCAPTCHA v3 token and return score
	 *
	 * @param string $token  reCAPTCHA response token
	 * @param string $action Expected action (optional)
	 *
	 * @return array Array with 'valid' (bool) and 'score' (float|null)
	 */
	public static function verify_with_score( string $token, string $action = 'submit_contact' ): array {
		$settings = get_option( Config::OPTION_SETTINGS, array() );

		if ( empty( $settings['recaptcha_enable'] ) ||
			empty( $settings['recaptcha_site_key'] ) ||
			empty( $settings['recaptcha_secret_key'] )
		) {
			// Disabled in settings → trusted
			return array(
				'valid' => true,
				'score' => null,
			);
		}

		if ( empty( $token ) || strlen( $token ) < 50 ) {
			return array(
				'valid' => false,
				'score' => null,
			);
		}

		$cache_key = 'ci_recaptcha_' . md5( $token );
		$cached    = wp_cache_get( $cache_key, 'contactin' );
		if ( $cached !== false ) {
			return $cached;
		}

		$secret = $settings['recaptcha_secret_key'];
		$ip     = Security::get_ip_address();

		$response = wp_remote_post(
			'https://www.google.com/recaptcha/api/siteverify',
			array(
				'timeout'    => 8,
				'body'       => array(
					'secret'   => $secret,
					'response' => $token,
					'remoteip' => $ip,
				),
				'user-agent' => 'ContactInbox/' . CONTACTINBOX_VERSION . ' | WordPress/' . get_bloginfo( 'version' ),
			)
		);

		if ( is_wp_error( $response ) ) {
			Logger::warning(
				'reCAPTCHA verification failed',
				array(
					'error' => $response->get_error_message(),
					'ip'    => $ip,
				)
			);
			$result = array(
				'valid' => false,
				'score' => null,
			);
			wp_cache_set( $cache_key, $result, 'contactin', self::CACHE_TTL );
			return $result;
		}

		$body  = json_decode( wp_remote_retrieve_body( $response ), true );
		$score = is_array( $body ) ? (float) ( $body['score'] ?? 0 ) : null;

		$valid = is_array( $body ) &&
				! empty( $body['success'] ) &&
				( $score ?? 0 ) >= self::MIN_SCORE &&
				( empty( $action ) || ( $body['action'] ?? '' ) === $action ) &&
				( empty( $body['hostname'] ) || $body['hostname'] === wp_parse_url( home_url(), PHP_URL_HOST ) );

		$result = array(
			'valid' => $valid,
			'score' => $score,
		);
		wp_cache_set( $cache_key, $result, 'contactin', self::CACHE_TTL );

		return $result;
	}

	/**
	 * Verify reCAPTCHA v3 token
	 *
	 * @param string $token  reCAPTCHA response token
	 * @param string $action Expected action (optional)
	 *
	 * @return bool True if valid, false otherwise
	 */
	public static function verify( string $token, string $action = 'submit_contact' ): bool {
		$result = self::verify_with_score( $token, $action );
		return (bool) $result['valid'];
	}

	// =========================================================================
	// Helpers
	// =========================================================================

	/**
	 * Get the public site key for frontend
	 */
	public static function get_site_key(): string {
		$settings = get_option( Config::OPTION_SETTINGS, array() );
		return $settings['recaptcha_site_key'] ?? '';
	}

	/**
	 * Check if reCAPTCHA is fully enabled and configured
	 */
	public static function is_enabled(): bool {
		$settings = get_option( Config::OPTION_SETTINGS, array() );
		return ! empty( $settings['recaptcha_enable'] ) &&
				! empty( $settings['recaptcha_site_key'] ) &&
				! empty( $settings['recaptcha_secret_key'] );
	}
}
