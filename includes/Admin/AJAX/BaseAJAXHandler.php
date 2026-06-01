<?php
/**
 * Base AJAX Handler
 *
 * Provides common functionality for all AJAX handlers.
 *
 * @package ContactIn\Admin\AJAX
 */

declare(strict_types=1);

namespace ContactInbox\Admin\AJAX;

use ContactInbox\Core\Config;
use ContactInbox\Core\Repositories\AnalyticsRepository;

// phpcs:disable WordPress.WP.I18n.NonSingularStringLiteralDomain, WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.MissingUnslash

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

abstract class BaseAJAXHandler {

	protected AnalyticsRepository $analytics;

	public function __construct( AnalyticsRepository $analytics = null ) {
		$this->analytics = $analytics ?? new AnalyticsRepository();
	}

	/**
	 * Verify AJAX request and permissions
	 */
	protected function verify(): void {
		$nonce = isset( $_POST['nonce'] ) ? sanitize_text_field( wp_unslash( (string) $_POST['nonce'] ) ) : '';

		if ( '' === $nonce ) {
			wp_send_json_error( array( 'message' => __( 'Invalid nonce.', 'contactin' ) ), 403 );
			exit;
		}

		if ( ! $this->is_valid_ajax_nonce( $nonce ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid nonce.', 'contactin' ) ), 403 );
			exit;
		}

		if ( ! current_user_can( Config::CAPABILITY ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'contactin' ) ), 403 );
			exit;
		}
	}

	/**
	 * Validate nonce against accepted AJAX actions.
	 */
	private function is_valid_ajax_nonce( string $nonce ): bool {
		foreach ( array( 'contactinbox_nonce_action', 'contactin_nonce_action', Config::NONCE_ACTION ) as $action ) {
			if ( wp_verify_nonce( $nonce, $action ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Parse and validate date range from POST
	 *
	 * @return array ['days' => int, 'start_date' => string|null, 'end_date' => string|null]
	 */
	protected function parse_date_range(): array {
		// Optional custom date range (preset dates)
		$start_date_raw = isset( $_POST['start_date'] ) ? sanitize_text_field( wp_unslash( $_POST['start_date'] ) ) : '';
		$end_date_raw   = isset( $_POST['end_date'] ) ? sanitize_text_field( wp_unslash( $_POST['end_date'] ) ) : '';

		$start_date = ( preg_match( '/^\d{4}-\d{2}-\d{2}$/', $start_date_raw ) ) ? $start_date_raw : null;
		$end_date   = ( preg_match( '/^\d{4}-\d{2}-\d{2}$/', $end_date_raw ) ) ? $end_date_raw : null;

		// If preset dates are provided, use them and ignore date_range
		if ( $start_date && $end_date ) {
			if ( strtotime( $start_date ) > strtotime( $end_date ) ) {
				wp_send_json_error( array( 'message' => __( 'Invalid date range: start date must be before end date.', 'contactin' ) ) );
				exit;
			}
			return array(
				'days'       => 0,
				'start_date' => $start_date,
				'end_date'   => $end_date,
			);
		}

		// Fall back to days-based range
		$date_range = isset( $_POST['date_range'] ) ? sanitize_text_field( wp_unslash( $_POST['date_range'] ) ) : '7';
		$days       = absint( $date_range ) ?: 7;

		return array(
			'days'       => $days,
			'start_date' => null,
			'end_date'   => null,
		);
	}

	/**
	 * Handle exceptions and return JSON error
	 */
	protected function handle_error( \Exception $e ): void {
		wp_send_json_error( array( 'message' => $e->getMessage() ) );
	}
}
