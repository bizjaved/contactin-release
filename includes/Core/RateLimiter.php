<?php
// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
/**
 * Rate Limiter – Prevent Abuse and Ensure Fairness Under High Load
 *
 * Implements sliding window rate limiting per IP address
 * Supports customizable limits: requests per minute, hour, day
 *
 * @package ContactIn
 */

namespace ContactInbox\Core;

use ContactInbox\Traits\Singleton;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class RateLimiter {
	use Singleton;

	const OPTION_PREFIX = 'contactin_rate_limit_';

	/**
	 * Check if IP is rate limited
	 *
	 * @param string $ip_address IP address to check
	 * @return array Rate limit status
	 */
	public static function check_rate_limit( string $ip_address ): array {
		try {
			$ip_address = self::sanitize_ip( $ip_address );

			if ( empty( $ip_address ) ) {
				return array(
					'allowed' => false,
					'reason'  => 'Invalid IP address',
				);
			}

			// Get custom limits from settings
			$settings = Settings::get_settings();

			// Optional IP controls
			if ( self::is_ip_blocked( $ip_address, $settings ) ) {
				return array(
					'allowed'     => false,
					'reason'      => 'IP blocked by policy',
					'window'      => 'policy',
					'retry_after' => 86400,
				);
			}

			if ( self::is_allowlist_enabled( $settings ) && ! self::is_ip_allowlisted( $ip_address, $settings ) ) {
				return array(
					'allowed'     => false,
					'reason'      => 'IP not in allowlist',
					'window'      => 'policy',
					'retry_after' => 86400,
				);
			}

			$limit_minute = intval( $settings['rate_limit_per_minute'] ?? Config::RATE_LIMIT_PER_MINUTE );
			$limit_hour   = intval( $settings['rate_limit_per_hour'] ?? Config::RATE_LIMIT_PER_HOUR );
			$limit_day    = intval( $settings['rate_limit_per_day'] ?? Config::RATE_LIMIT_PER_DAY );

			// Check per-minute limit (most strict)
			$minute_count = self::get_request_count( $ip_address, 60 );
			if ( $minute_count >= $limit_minute ) {
				return array(
					'allowed'     => false,
					'reason'      => 'Too many requests per minute',
					'limit'       => $limit_minute,
					'current'     => $minute_count,
					'window'      => 'minute',
					'retry_after' => 60,
				);
			}

			// Check per-hour limit
			$hour_count = self::get_request_count( $ip_address, 3600 );
			if ( $hour_count >= $limit_hour ) {
				return array(
					'allowed'     => false,
					'reason'      => 'Too many requests per hour',
					'limit'       => $limit_hour,
					'current'     => $hour_count,
					'window'      => 'hour',
					'retry_after' => 3600,
				);
			}

			// Check per-day limit
			$day_count = self::get_request_count( $ip_address, 86400 );
			if ( $day_count >= $limit_day ) {
				return array(
					'allowed'     => false,
					'reason'      => 'Too many requests per day',
					'limit'       => $limit_day,
					'current'     => $day_count,
					'window'      => 'day',
					'retry_after' => 86400,
				);
			}

			return array(
				'allowed'          => true,
				'minute_remaining' => $limit_minute - $minute_count,
				'hour_remaining'   => $limit_hour - $hour_count,
				'day_remaining'    => $limit_day - $day_count,
			);
		} catch ( \Throwable $e ) {
			Logger::error(
				'Rate limiter check failed',
				array(
					'error' => $e->getMessage(),
					'ip'    => $ip_address,
				)
			);
			return array( 'allowed' => true ); // Default to allow on error
		}
	}

	/**
	 * Record a request from an IP address
	 *
	 * @param string $ip_address IP address making request
	 */
	public static function record_request( string $ip_address ): void {
		try {
			$ip_address = self::sanitize_ip( $ip_address );

			if ( empty( $ip_address ) ) {
				return;
			}

			$key      = self::OPTION_PREFIX . $ip_address;
			$requests = self::get_requests( $ip_address );

			// Add new request with current timestamp
			$requests[] = time();

			// Store last 100 requests (sliding window)
			$requests = array_slice( $requests, -100 );
			update_option( $key, $requests, 'no' );

			Logger::debug(
				'Rate limit request recorded',
				array(
					'ip'    => $ip_address,
					'count' => count( $requests ),
				)
			);
		} catch ( \Throwable $e ) {
			Logger::error(
				'Failed to record rate limit request',
				array(
					'error' => $e->getMessage(),
					'ip'    => $ip_address,
				)
			);
		}
	}

	/**
	 * Get request count within a time window
	 *
	 * @param string $ip_address IP address
	 * @param int    $window_seconds Time window in seconds (60, 3600, 86400)
	 * @return int Number of requests in window
	 */
	private static function get_request_count( string $ip_address, int $window_seconds ): int {
		try {
			$requests = self::get_requests( $ip_address );
			$now      = time();
			$cutoff   = $now - $window_seconds;

			// Count requests within window
			$count = 0;
			foreach ( $requests as $request_time ) {
				if ( $request_time >= $cutoff ) {
					++$count;
				}
			}

			return $count;
		} catch ( \Throwable $e ) {
			Logger::error(
				'Failed to get request count',
				array(
					'error' => $e->getMessage(),
					'ip'    => $ip_address,
				)
			);
			return 0;
		}
	}

	/**
	 * Get all recorded requests for an IP
	 *
	 * @param string $ip_address IP address
	 * @return array Request timestamps
	 */
	private static function get_requests( string $ip_address ): array {
		$key      = self::OPTION_PREFIX . $ip_address;
		$requests = get_option( $key );

		if ( ! is_array( $requests ) ) {
			return array();
		}

		// Ensure all values are timestamps
		return array_filter(
			$requests,
			function ( $item ) {
				return is_int( $item ) || ( is_numeric( $item ) && intval( $item ) > 0 );
			}
		);
	}

	/**
	 * Clean up old rate limit records (older than 24 hours)
	 */
	public static function cleanup_old_records(): void {
		try {
			global $wpdb;
			$prefix      = self::OPTION_PREFIX;
			$cutoff_time = time() - 86400; // 24 hours ago

			// Get all rate limit options
			$options = $wpdb->get_col(
				"SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE '{$prefix}%'"
			);

			if ( empty( $options ) ) {
				return;
			}

			$deleted = 0;
			foreach ( $options as $option_name ) {
				$requests = get_option( $option_name );

				if ( is_array( $requests ) ) {
					// Filter out old requests
					$filtered = array_filter(
						$requests,
						function ( $time ) use ( $cutoff_time ) {
							return $time >= $cutoff_time;
						}
					);

					if ( empty( $filtered ) ) {
						// No requests left, delete option
						delete_option( $option_name );
						++$deleted;
					} elseif ( count( $filtered ) < count( $requests ) ) {
						// Some requests removed, update
						update_option( $option_name, array_values( $filtered ), 'no' );
					}
				}
			}

			if ( $deleted > 0 ) {
				Logger::notice( "Rate limit cleanup: deleted {$deleted} old records" );
			}
		} catch ( \Throwable $e ) {
			Logger::error(
				'Failed to cleanup rate limit records',
				array(
					'error' => $e->getMessage(),
				)
			);
		}
	}

	/**
	 * Reset rate limit for specific IP (admin action)
	 *
	 * @param string $ip_address IP to reset
	 */
	public static function reset( string $ip_address ): void {
		try {
			$ip_address = self::sanitize_ip( $ip_address );
			if ( ! empty( $ip_address ) ) {
				delete_option( self::OPTION_PREFIX . $ip_address );
				Logger::notice( "Rate limit reset for IP: {$ip_address}" );
			}
		} catch ( \Throwable $e ) {
			Logger::error(
				'Failed to reset rate limit',
				array(
					'error' => $e->getMessage(),
					'ip'    => $ip_address,
				)
			);
		}
	}

	/**
	 * Sanitize and validate IP address
	 *
	 * @param string $ip IP address
	 * @return string Sanitized IP or empty string if invalid
	 */
	private static function sanitize_ip( string $ip ): string {
		// Use WordPress sanitization
		$ip = sanitize_text_field( $ip );

		// Validate IP format (IPv4 or IPv6)
		if ( filter_var( $ip, FILTER_VALIDATE_IP ) ) {
			return $ip;
		}

		return '';
	}

	/**
	 * Parse newline/comma separated IP list settings.
	 */
	private static function parse_ip_list( string $raw ): array {
		$parts = preg_split( '/[\s,]+/', strtolower( trim( $raw ) ) ) ?: array();
		$parts = array_map( 'trim', $parts );
		return array_values(
			array_filter(
				$parts,
				static function ( $value ) {
					return $value !== '';
				}
			)
		);
	}

	private static function is_allowlist_enabled( array $settings ): bool {
		$enabled = $settings['ip_allowlist_enable'] ?? false;
		return ! empty( $enabled );
	}

	private static function is_ip_allowlisted( string $ip_address, array $settings ): bool {
		$list = self::parse_ip_list( (string) ( $settings['ip_allowlist'] ?? '' ) );
		if ( empty( $list ) ) {
			return false;
		}
		return in_array( strtolower( $ip_address ), $list, true );
	}

	private static function is_ip_blocked( string $ip_address, array $settings ): bool {
		$list = self::parse_ip_list( (string) ( $settings['ip_blacklist'] ?? '' ) );
		if ( empty( $list ) ) {
			return false;
		}
		return in_array( strtolower( $ip_address ), $list, true );
	}
}
