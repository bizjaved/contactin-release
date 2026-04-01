<?php
/**
 * Circuit Breaker – Resilience Pattern for External Service Failures
 *
 * Prevents cascading failures by temporarily disabling failing services
 * Implements: CLOSED → OPEN → HALF_OPEN → CLOSED states
 *
 * @package ContactIn
 */

namespace ContactInbox\Core;

use ContactInbox\Traits\Singleton;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CircuitBreaker {
	use Singleton;

	const STATE_CLOSED    = 'closed';      // Normal operation
	const STATE_OPEN      = 'open';          // Failing, skip requests
	const STATE_HALF_OPEN = 'half_open'; // Testing if service recovered

	const FAILURE_THRESHOLD = 5;        // Open after 5 failures
	const SUCCESS_THRESHOLD = 2;        // Close after 2 successes in HALF_OPEN
	const RESET_TIMEOUT     = 300;          // 5 minutes before trying again

	const OPTION_PREFIX = 'contactin_cb_';

	/**
	 * Record successful operation for a service
	 *
	 * @param string $service Service name (smtp, crm, webhook, etc.)
	 */
	public static function record_success( string $service ): void {
		try {
			$service = sanitize_key( $service );
			$key     = self::OPTION_PREFIX . $service;
			$data    = self::get_state_data( $service );

			if ( $data['state'] === self::STATE_HALF_OPEN ) {
				++$data['success_count'];
				if ( $data['success_count'] >= self::SUCCESS_THRESHOLD ) {
					// Service recovered, reset circuit
					$data['state']         = self::STATE_CLOSED;
					$data['failure_count'] = 0;
					$data['success_count'] = 0;
					$data['last_failure']  = null;
					Logger::info( "Circuit breaker closed for {$service} (service recovered)" );
				}
			} elseif ( $data['state'] === self::STATE_CLOSED ) {
				// Reset failure count on success
				$data['failure_count'] = max( 0, $data['failure_count'] - 1 );
				++$data['success_count'];
			}

			$data['last_success'] = current_time( 'mysql' );
			update_option( $key, $data, 'no' );
		} catch ( \Throwable $e ) {
			Logger::error(
				'Failed to record circuit breaker success',
				array(
					'service' => $service,
					'error'   => $e->getMessage(),
				)
			);
		}
	}

	/**
	 * Record failed operation for a service
	 *
	 * @param string $service Service name
	 * @param string $error Error message
	 */
	public static function record_failure( string $service, string $error = '' ): void {
		try {
			$service = sanitize_key( $service );
			$key     = self::OPTION_PREFIX . $service;
			$data    = self::get_state_data( $service );

			++$data['failure_count'];
			$data['last_failure'] = current_time( 'mysql' );
			$data['last_error']   = $error;

			if ( $data['state'] === self::STATE_CLOSED && $data['failure_count'] >= self::FAILURE_THRESHOLD ) {
				// Too many failures, open circuit
				$data['state']     = self::STATE_OPEN;
				$data['opened_at'] = current_time( 'mysql' );
				Logger::warning(
					"Circuit breaker opened for {$service}",
					array(
						'failures'   => $data['failure_count'],
						'last_error' => $error,
					)
				);
			} elseif ( $data['state'] === self::STATE_HALF_OPEN ) {
				// Failed during recovery attempt, reopen circuit
				$data['state']     = self::STATE_OPEN;
				$data['opened_at'] = current_time( 'mysql' );
				Logger::warning( "Circuit breaker reopened for {$service} (recovery failed)" );
			}

			update_option( $key, $data, 'no' );
		} catch ( \Throwable $e ) {
			Logger::error(
				'Failed to record circuit breaker failure',
				array(
					'service' => $service,
					'error'   => $e->getMessage(),
				)
			);
		}
	}

	/**
	 * Check if service is available (circuit allows requests)
	 *
	 * @param string $service Service name
	 * @return bool True if service is available
	 */
	public static function is_available( string $service ): bool {
		try {
			$service = sanitize_key( $service );
			$data    = self::get_state_data( $service );

			if ( $data['state'] === self::STATE_CLOSED ) {
				return true;
			}

			if ( $data['state'] === self::STATE_OPEN ) {
				// Check if reset timeout has passed
				if ( ! empty( $data['opened_at'] ) ) {
					$opened_time = strtotime( $data['opened_at'] );
					$now         = time();
					if ( $now - $opened_time >= self::RESET_TIMEOUT ) {
						// Try recovery
						$data['state']         = self::STATE_HALF_OPEN;
						$data['success_count'] = 0;
						update_option( self::OPTION_PREFIX . $service, $data, 'no' );
						Logger::notice( "Circuit breaker half-open for {$service} (testing recovery)" );
						return true; // Allow one request to test
					}
				}
				return false;
			}

			if ( $data['state'] === self::STATE_HALF_OPEN ) {
				return true; // Allow test requests
			}

			return true; // Default to allow
		} catch ( \Throwable $e ) {
			Logger::error(
				'Failed to check circuit breaker availability',
				array(
					'service' => $service,
					'error'   => $e->getMessage(),
				)
			);
			return true; // Default to allow on error
		}
	}

	/**
	 * Get current state of service
	 *
	 * @param string $service Service name
	 * @return string State (closed, open, half_open)
	 */
	public static function get_state( string $service ): string {
		try {
			$data = self::get_state_data( $service );
			return $data['state'];
		} catch ( \Throwable $e ) {
			return self::STATE_CLOSED;
		}
	}

	/**
	 * Get circuit breaker statistics for all services
	 *
	 * @return array Statistics per service
	 */
	public static function get_stats(): array {
		try {
			$services = array( 'smtp', 'crm' );
			$stats    = array();

			foreach ( $services as $service ) {
				$data              = self::get_state_data( $service );
				$stats[ $service ] = array(
					'state'         => $data['state'],
					'failure_count' => $data['failure_count'],
					'success_count' => $data['success_count'],
					'last_failure'  => $data['last_failure'],
					'last_error'    => $data['last_error'],
					'last_success'  => $data['last_success'],
				);
			}

			return $stats;
		} catch ( \Throwable $e ) {
			Logger::error(
				'Failed to get circuit breaker stats',
				array(
					'error' => $e->getMessage(),
				)
			);
			return array();
		}
	}

	/**
	 * Reset circuit breaker for a service (admin action)
	 *
	 * @param string $service Service name
	 */
	public static function reset( string $service ): void {
		try {
			$service = sanitize_key( $service );
			delete_option( self::OPTION_PREFIX . $service );
			Logger::notice( "Circuit breaker reset for {$service}" );
		} catch ( \Throwable $e ) {
			Logger::error(
				'Failed to reset circuit breaker',
				array(
					'service' => $service,
					'error'   => $e->getMessage(),
				)
			);
		}
	}

	/**
	 * Get or create state data for a service
	 *
	 * @param string $service Service name
	 * @return array State data
	 */
	private static function get_state_data( string $service ): array {
		$service = sanitize_key( $service );
		$key     = self::OPTION_PREFIX . $service;
		$data    = get_option( $key );

		if ( ! is_array( $data ) ) {
			$data = array(
				'state'         => self::STATE_CLOSED,
				'failure_count' => 0,
				'success_count' => 0,
				'last_failure'  => null,
				'last_error'    => null,
				'last_success'  => null,
				'opened_at'     => null,
			);
		}

		return $data;
	}
}
