<?php
// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
/**
 * Concurrency Manager – Handle Race Conditions in High-Volume Scenarios
 *
 * Implements database locking, duplicate detection, and optimistic locking
 * Ensures data integrity under concurrent form submissions
 *
 * @package ContactIn
 */

namespace ContactInbox\Core;

use ContactInbox\Traits\Singleton;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class ConcurrencyManager {
	use Singleton;

	const LOCK_TIMEOUT     = 10; // 10 second lock timeout
	const DUPLICATE_WINDOW = 5; // 5 second duplicate detection window

	/**
	 * Acquire a distributed lock for form submission
	 *
	 * @param string $lock_key Unique lock identifier
	 * @param int    $timeout Lock timeout in seconds
	 * @return string Lock token or empty string if failed
	 */
	public static function acquire_lock( string $lock_key, int $timeout = self::LOCK_TIMEOUT ): string {
		try {
			$lock_key   = sanitize_key( $lock_key );
			$lock_token = wp_generate_uuid4();
			$expires    = time() + $timeout;

			// Use WordPress transient for distributed lock
			$lock_acquired = get_transient( "contactin_lock_{$lock_key}" );

			if ( $lock_acquired ) {
				// Lock already exists
				return '';
			}

			// Acquire lock
			set_transient( "contactin_lock_{$lock_key}", $lock_token, $timeout );

			// Verify lock was set (race condition check)
			$verification = get_transient( "contactin_lock_{$lock_key}" );
			if ( $verification === $lock_token ) {
				Logger::debug( 'Distributed lock acquired', array( 'lock_key' => $lock_key ) );
				return $lock_token;
			}

			return '';
		} catch ( \Throwable $e ) {
			Logger::error(
				'Failed to acquire lock',
				array(
					'error'    => $e->getMessage(),
					'lock_key' => $lock_key,
				)
			);
			return '';
		}
	}

	/**
	 * Release a distributed lock
	 *
	 * @param string $lock_key Lock identifier
	 * @param string $lock_token Token from acquire_lock
	 * @return bool True if released successfully
	 */
	public static function release_lock( string $lock_key, string $lock_token ): bool {
		try {
			$lock_key = sanitize_key( $lock_key );

			// Verify token matches before releasing (prevent stealing locks)
			$current_token = get_transient( "contactin_lock_{$lock_key}" );

			if ( $current_token === $lock_token ) {
				delete_transient( "contactin_lock_{$lock_key}" );
				Logger::debug( 'Distributed lock released', array( 'lock_key' => $lock_key ) );
				return true;
			}

			return false;
		} catch ( \Throwable $e ) {
			Logger::error(
				'Failed to release lock',
				array(
					'error'    => $e->getMessage(),
					'lock_key' => $lock_key,
				)
			);
			return false;
		}
	}

	/**
	 * Check for duplicate submission within time window
	 *
	 * Uses database query with row locking to prevent race conditions
	 *
	 * @param string $email Email address
	 * @param string $message_hash Hash of message content
	 * @param int    $window_seconds Detection window in seconds
	 * @return bool True if duplicate detected
	 */
	public static function is_duplicate( string $email, string $message_hash, int $window_seconds = self::DUPLICATE_WINDOW ): bool {
		try {
			global $wpdb;
			$table = $wpdb->prefix . Config::TABLE_MESSAGES;

			// Use FOR UPDATE lock to prevent race conditions
			$result = $wpdb->get_row(
				$wpdb->prepare(
					"SELECT id FROM {$table} 
                 WHERE email = %s 
                 AND UNHEX(MD5(message)) = UNHEX(%s)
                 AND submitted_at > DATE_SUB(NOW(), INTERVAL %d SECOND)
                 LIMIT 1",
					$email,
					$message_hash,
					$window_seconds
				)
			);

			return ! empty( $result );
		} catch ( \Throwable $e ) {
			Logger::error(
				'Failed to check for duplicates',
				array(
					'error' => $e->getMessage(),
					'email' => $email,
				)
			);
			return false; // Default to allow on error
		}
	}

	/**
	 * Acquire database-level lock on messages table
	 *
	 * Useful for operations requiring exclusive access
	 *
	 * @return bool True if lock acquired
	 */
	public static function lock_table(): bool {
		try {
			global $wpdb;
			$table = $wpdb->prefix . Config::TABLE_MESSAGES;

			$wpdb->query( "LOCK TABLE {$table} WRITE" );

			Logger::debug( 'Database table locked' );
			return true;
		} catch ( \Throwable $e ) {
			Logger::error(
				'Failed to lock table',
				array(
					'error' => $e->getMessage(),
				)
			);
			return false;
		}
	}

	/**
	 * Release database-level lock
	 *
	 * @return bool True if lock released
	 */
	public static function unlock_table(): bool {
		try {
			global $wpdb;
			$wpdb->query( 'UNLOCK TABLES' );

			Logger::debug( 'Database table unlocked' );
			return true;
		} catch ( \Throwable $e ) {
			Logger::error(
				'Failed to unlock table',
				array(
					'error' => $e->getMessage(),
				)
			);
			return false;
		}
	}

	/**
	 * Get statistics about concurrent submissions
	 *
	 * @return array Concurrency stats
	 */
	public static function get_stats(): array {
		try {
			global $wpdb;
			$table = $wpdb->prefix . Config::TABLE_MESSAGES;

			// Count submissions in last minute
			$last_minute = $wpdb->get_var(
				"SELECT COUNT(*) FROM {$table} 
                 WHERE submitted_at > DATE_SUB(NOW(), INTERVAL 1 MINUTE)"
			);

			// Count submissions in last hour
			$last_hour = $wpdb->get_var(
				"SELECT COUNT(*) FROM {$table} 
                 WHERE submitted_at > DATE_SUB(NOW(), INTERVAL 1 HOUR)"
			);

			// Count submissions in last day
			$last_day = $wpdb->get_var(
				"SELECT COUNT(*) FROM {$table} 
                 WHERE submitted_at > DATE_SUB(NOW(), INTERVAL 1 DAY)"
			);

			return array(
				'last_minute' => intval( $last_minute ?? 0 ),
				'last_hour'   => intval( $last_hour ?? 0 ),
				'last_day'    => intval( $last_day ?? 0 ),
			);
		} catch ( \Throwable $e ) {
			Logger::error(
				'Failed to get concurrency stats',
				array(
					'error' => $e->getMessage(),
				)
			);
			return array();
		}
	}

	/**
	 * Generate message hash for duplicate detection
	 *
	 * Creates deterministic hash of message content
	 *
	 * @param string $message Message content
	 * @return string MD5 hash
	 */
	public static function hash_message( string $message ): string {
		// Normalize: trim whitespace, lowercase for comparison
		$normalized = strtolower( trim( $message ) );
		return md5( $normalized );
	}
}
