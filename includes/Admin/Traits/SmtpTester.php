<?php
declare(strict_types=1);

namespace ContactInbox\Admin\Traits;

use ContactInbox\Core\Config;
use ContactInbox\Core\SMTP;
use ContactInbox\Core\Settings;

// phpcs:disable WordPress.WP.I18n.NonSingularStringLiteralDomain, WordPress.Security.ValidatedSanitizedInput, WordPress.Security.NonceVerification

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

trait SmtpTester {
	public function ajax_test_smtp(): void {
		check_ajax_referer( Config::SMTP_TEST_NONCE_ACTION, 'nonce' );

		if ( ! current_user_can( Config::CAPABILITY ) ) {
			wp_send_json_error(
				array(
					'code'    => 'permission_denied',
					'message' => __( 'Permission denied.', 'contactin' ),
				)
			);
		}

		$to = sanitize_email( $_POST['to'] ?? $_POST['admin_email'] ?? get_option( 'admin_email' ) );
		if ( ! is_email( $to ) ) {
			wp_send_json_error(
				array(
					'code'    => 'invalid_email',
					'message' => __( 'Invalid test email address.', 'contactin' ),
				)
			);
		}

		$enc_input  = $_POST['smtp_encryption'] ?? $_POST['enc'] ?? '';
		$enc        = in_array( $enc_input, array( 'ssl', 'tls', 'none', 'auto' ), true ) ? $enc_input : 'none';
		$port_input = $_POST['smtp_port'] ?? $_POST['port'] ?? 587;
		$port       = absint( $port_input );
		if ( $port < 1 || $port > 65535 ) {
			wp_send_json_error(
				array(
					'code'    => 'invalid_port',
					'message' => __( 'Invalid SMTP port.', 'contactin' ),
				)
			);
		}

		$existing_settings = get_option( Config::OPTION_SETTINGS, array() );
		if ( ! is_array( $existing_settings ) ) {
			$existing_settings = array();
		}

		$override = array(
			'smtp_host'       => sanitize_text_field( $_POST['smtp_host'] ?? $_POST['host'] ?? '' ),
			'smtp_port'       => $port,
			'smtp_user'       => sanitize_text_field( $_POST['smtp_user'] ?? $_POST['username'] ?? '' ),
			'smtp_pass'       => (string) ( $_POST['smtp_pass'] ?? $_POST['password'] ?? '' ),
			'smtp_encryption' => $enc,
			'smtp_from_email' => sanitize_email( $_POST['smtp_from_email'] ?? $_POST['from_email'] ?? $to ),
			'smtp_from_name'  => sanitize_text_field( $_POST['smtp_from_name'] ?? $_POST['from_name'] ?? get_bloginfo( 'name' ) ),
		);

		if ( $override['smtp_pass'] === '' && ! empty( $existing_settings['smtp_pass'] ) ) {
			$override['smtp_pass'] = (string) $existing_settings['smtp_pass'];
		}

		$result = SMTP::test_smtp( $to, $override, true );

		if ( $result['success'] ) {
			$merged                = array_merge( $existing_settings, $override );
			$merged['smtp_enable'] = 1;

			$sanitized = Settings::sanitize_settings( $merged );
			Settings::update_settings( $sanitized );

			wp_send_json_success(
				array(
					'code'    => 'smtp_success',
					'message' => $result['message'],
				)
			);
		}

		wp_send_json_error(
			array(
				'code'    => 'smtp_failed',
				'message' => $result['message'],
			)
		);
	}

	public function ajax_check_smtp_result(): void {
		$key = sanitize_text_field( $_POST['key'] ?? '' );
		if ( $key === '' ) {
			wp_send_json_error(
				array(
					'code'    => 'missing_key',
					'message' => __( 'Missing key.', 'contactin' ),
				)
			);
		}

		if ( ! apply_filters( 'contactinbox_allow_smtp_poll', true, $key ) ) {
			wp_send_json_error(
				array(
					'code'    => 'poll_limited',
					'message' => __( 'Polling limited. Please wait a moment.', 'contactin' ),
				)
			);
		}

		$result = get_transient( $key );
		if ( $result ) {
			do_action( 'contactinbox_smtp_result_checked', $key, $result );
			wp_send_json_success(
				array(
					'code'    => 'smtp_result_ready',
					'message' => __( 'SMTP result available.', 'contactin' ),
					'result'  => $result,
				)
			);
		}

		wp_send_json_error(
			array(
				'code'    => 'smtp_result_pending',
				'message' => __( 'No result yet.', 'contactin' ),
			)
		);
	}
}
