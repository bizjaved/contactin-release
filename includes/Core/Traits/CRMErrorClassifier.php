<?php
/**
 * CRM Error Classifier Trait
 *
 * Classifies errors into types: AUTH, VALIDATION, RATE_LIMIT, TIMEOUT, SERVER_ERROR
 * Determines if errors should be retried based on error type.
 *
 * @package ContactIn\Core\Traits
 */

declare(strict_types=1);

namespace ContactInbox\Core\Traits;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

trait CRMErrorClassifier {

	/**
	 * Classify error based on HTTP code and response
	 */
	protected function classify_error( int $http_code, array $response = array() ): string {
		// Auth errors - don't retry
		if ( $http_code === 401 || $http_code === 403 ) {
			return 'AUTH';
		}

		// Rate limit - retry
		if ( $http_code === 429 ) {
			return 'RATE_LIMIT';
		}

		// Server error - retry
		if ( $http_code >= 500 ) {
			return 'SERVER_ERROR';
		}

		// Validation error - don't retry
		if ( $http_code === 400 ) {
			if ( $this->is_validation_error( $response ) ) {
				return 'VALIDATION';
			}
			return 'FIELD_MAPPING';
		}

		// Connection timeout - retry
		if ( $http_code === 0 || $http_code === 408 || $http_code === 504 ) {
			return 'TIMEOUT';
		}

		return 'UNKNOWN';
	}

	/**
	 * Determine if error is retriable
	 */
	protected function should_retry( string $error_type, int $retry_count = 0 ): bool {
		$no_retry = array( 'AUTH', 'VALIDATION', 'FIELD_MAPPING' );

		// Max 4 retries
		if ( $retry_count >= 4 ) {
			return false;
		}

		return ! in_array( $error_type, $no_retry, true );
	}

	/**
	 * Check if Salesforce returned validation error
	 */
	private function is_validation_error( array $response ): bool {
		if ( ! is_array( $response ) ) {
			return false;
		}

		if ( isset( $response['error'] ) && is_array( $response['error'] ) ) {
			$message  = strtolower( $response['error'][0]['message'] ?? '' );
			$keywords = array( 'required', 'invalid', 'missing', 'field', 'format' );

			foreach ( $keywords as $keyword ) {
				if ( strpos( $message, $keyword ) !== false ) {
					return true;
				}
			}
		}

		return false;
	}
}
