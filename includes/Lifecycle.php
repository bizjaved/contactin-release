<?php
namespace ContactInbox;

use ContactInbox\Core\Config;
use ContactInbox\Core\CoreBootstrap;
use ContactInbox\Core\DB;
use ContactInbox\Core\Settings;
use ContactInbox\Core\ActivationHandler;
use ContactInbox\Cron\AnalyticsAggregationJob;
use ContactInbox\Integration\FreemiusIntegration;

// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.WP.I18n.TextDomainMismatch, WordPress.PHP.DevelopmentFunctions.error_log_error_log, WordPress.WP.AlternativeFunctions.unlink_unlink, WordPress.WP.AlternativeFunctions.file_system_operations_fwrite, WordPress.WP.AlternativeFunctions.file_system_operations_is_writable, WordPress.WP.AlternativeFunctions.file_system_operations_fclose, WordPress.WP.AlternativeFunctions.rename_rename, WordPress.WP.AlternativeFunctions.file_system_operations_fopen, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber, Generic.PHP.ForbiddenFunctions.Found, PluginCheck.CodeAnalysis.DiscouragedFunctions.load_plugin_textdomainFound, PluginCheck.CodeAnalysis.Heredoc.NotAllowed, PluginCheck.Security.DirectDB.UnescapedDBParameter, Squiz.PHP.DiscouragedFunctions.Discouraged, WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.NamingConventions.PrefixAllGlobals.DynamicHooknameFound, WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound, WordPress.PHP.DevelopmentFunctions.error_log_debug_backtrace, WordPress.WP.AlternativeFunctions.file_system_operations_fsockopen, WordPress.WP.AlternativeFunctions.file_system_operations_readfile, WordPress.WP.AlternativeFunctions.file_system_operations_rmdir, WordPress.WP.EnqueuedResourceParameters.MissingVersion, WordPress.WP.EnqueuedResources.NonEnqueuedScript, WordPress.WP.I18n.MissingArgDomain, WordPress.WP.I18n.UnorderedPlaceholdersPlural, WordPress.WP.I18n.UnorderedPlaceholdersSingle
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Lifecycle {
	/**
	 * Plugin activation.
	 * - Creates DB tables
	 * - Creates performance indexes on messages table
	 * Sets default settings
	 * - Schedules cron jobs
	 *
	 * Note: Uses ActivationHandler for safe free/pro coexistence
	 */
	public static function activate(): void {
		// Use new ActivationHandler for safe activation in coexistence scenarios
		ActivationHandler::activate();

		// Ensure default settings are present
		$defaults = Settings::get_default_settings();
		$current  = get_option( Config::OPTION_SETTINGS, array() );
		$merged   = array_merge( $defaults, $current );

		// Explicitly ensure allowed_file_types is always set (whitelist approach)
		// If somehow missing, use the default business-safe list
		if ( empty( $merged['allowed_file_types'] ) ) {
			$merged['allowed_file_types'] = $defaults['allowed_file_types'];
		}

		update_option( Config::OPTION_SETTINGS, $merged );

		// Install/update intent classification patterns with robust error handling
		$pattern_install = \ContactInbox\Core\IntentClassifier::install_patterns();
		if ( ! $pattern_install['success'] ) {
			error_log( '[ContactIn] Pattern installation failed: ' . implode( ', ', $pattern_install['errors'] ) );
			// Don't block activation - patterns can be installed later
		}

		self::schedule_cron_jobs();

		// Ensure DB tables exist (compatibility with old DB class)
		try {
			DB::instance()->activate();
		} catch ( \Throwable $e ) {
			error_log( '[ContactIn] DB activation error: ' . $e->getMessage() );
		}
	}

	/**
	 * Plugin deactivation.
	 * - Clears scheduled cron jobs
	 * - Flushes rewrite rules
	 */
	public static function deactivate(): void {
		// Use ActivationHandler for consistent deactivation
		ActivationHandler::deactivate();
	}

	/**
	 * Plugin uninstall.
	 * - Deletes DB tables
	 * - Removes all plugin options
	 * - Clears scheduled cron jobs
	 * - Removes transients and cached data
	 * - Cleans up uploaded files
	 */
	public static function uninstall(): void {
		global $wpdb;

		// Clear all scheduled cron jobs FIRST (before deleting tables)
		// This prevents background cron jobs from trying to access deleted tables
		// Use @ to suppress any warnings from wp_clear_scheduled_hook
		wp_clear_scheduled_hook( Config::CRON_CLEANUP );
		wp_clear_scheduled_hook( Config::CRON_GDPR );
		wp_clear_scheduled_hook( Config::CRON_PROCESS_EMAIL );
		wp_clear_scheduled_hook( Config::CRON_PROCESS_CRM );
		wp_clear_scheduled_hook( Config::CRON_GDPR_CLEANUP );
		wp_clear_scheduled_hook( Config::CRON_RECLASSIFY_UNCLASSIFIED );
		wp_clear_scheduled_hook( 'contactin_process_queue' ); // Legacy hook

		// Unschedule analytics job with error suppression
		try {
			if ( class_exists( '\ContactInbox\Cron\AnalyticsAggregationJob' ) ) {
				AnalyticsAggregationJob::unschedule();
			}
		} catch ( \Throwable $e ) { // phpcs:ignore Generic.CodeAnalysis.EmptyStatement.DetectedCatch -- Intentional: unschedule() job may not exist.
			// Ignore errors - job doesn't need to exist
		}

		// Drop DB tables (now safe, no cron jobs running)
		try {
			DB::uninstall();
		} catch ( \Throwable $e ) {
			error_log( '[ContactIn] Error dropping tables: ' . $e->getMessage() );
			// Continue with option cleanup
		}

		// Remove core plugin options with error suppression
		delete_option( Config::OPTION_SETTINGS );
		delete_option( Config::OPTION_VERSION );
		delete_option( 'contactinbox_db_version' );

		// Remove intent classification options
		delete_option( 'contactin_intent_patterns_version' );
		delete_option( 'contactin_intent_patterns' );
		delete_option( 'contactin_intent_patterns_backup' );
		delete_option( 'contactin_intent_patterns_checksum' );
		delete_option( 'contactin_patterns_installed' );
		delete_option( 'contactin_patterns_installed_at' );

		// Remove REST API test tokens
		delete_option( 'contactin_test_tokens' );

		// Delete all plugin-specific options (circuit breaker, rate limiter, queue triggers, etc.)
		// Use suppressWarnings to continue cleanup even if queries have issues
		try {
			$contactin_like    = $wpdb->esc_like( 'contactin_' ) . '%';
			$contactinbox_like = $wpdb->esc_like( 'contactinbox_' ) . '%';
			$wpdb->query(
				$wpdb->prepare(
					"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
					$contactin_like
				)
			);
			$wpdb->query(
				$wpdb->prepare(
					"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
					$contactinbox_like
				)
			);
		} catch ( \Throwable $e ) {
			// Log but continue cleanup
			error_log( '[ContactIn] Error deleting options: ' . $e->getMessage() );
		}

		// Delete all plugin transients
		try {
			$transient_contactin            = $wpdb->esc_like( '_transient_contactin_' ) . '%';
			$transient_timeout_contactin    = $wpdb->esc_like( '_transient_timeout_contactin_' ) . '%';
			$transient_contactinbox         = $wpdb->esc_like( '_transient_contactinbox_' ) . '%';
			$transient_timeout_contactinbox = $wpdb->esc_like( '_transient_timeout_contactinbox_' ) . '%';
			$wpdb->query(
				$wpdb->prepare(
					"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
					$transient_contactin
				)
			);
			$wpdb->query(
				$wpdb->prepare(
					"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
					$transient_timeout_contactin
				)
			);
			$wpdb->query(
				$wpdb->prepare(
					"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
					$transient_contactinbox
				)
			);
			$wpdb->query(
				$wpdb->prepare(
					"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
					$transient_timeout_contactinbox
				)
			);
		} catch ( \Throwable $e ) {
			// Log but continue cleanup
			error_log( '[ContactIn] Error deleting transients: ' . $e->getMessage() );
		}

		// Delete uploaded attachment files
		try {
			if ( defined( 'CONTACTINBOX_UPLOADS_PATH' ) && is_dir( CONTACTINBOX_UPLOADS_PATH ) ) {
				$upload_path = CONTACTINBOX_UPLOADS_PATH;
				if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
					error_log( '[ContactIn] Attempting to delete upload directory: ' . $upload_path );
				}

				$result = self::delete_directory_recursive( $upload_path );

				if ( $result ) {
					if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
						error_log( '[ContactIn] Successfully deleted upload directory' );
					}
				} else {
					error_log( '[ContactIn] Failed to fully delete upload directory - some files may remain' );

					// Fallback: Try to delete as many files as possible even if directory removal fails
					if ( is_dir( $upload_path ) ) {
						$remaining_files = glob( $upload_path . '*', GLOB_MARK );
						if ( $remaining_files && defined( 'WP_DEBUG' ) && WP_DEBUG ) {
							error_log( '[ContactIn] ' . count( $remaining_files ) . ' items remaining in upload directory' );
							foreach ( $remaining_files as $file ) {
								error_log( '[ContactIn] Remaining: ' . $file . ' (writable: ' . ( is_writable( $file ) ? 'yes' : 'no' ) . ')' );
							}
						}
					}
				}
			} elseif ( defined( 'CONTACTINBOX_UPLOADS_PATH' ) ) {
				error_log( '[ContactIn] Upload directory does not exist: ' . CONTACTINBOX_UPLOADS_PATH );
			} else {
				error_log( '[ContactIn] CONTACTINBOX_UPLOADS_PATH constant not defined during uninstall' );
			}
		} catch ( \Throwable $e ) {
			// Log but continue cleanup
			error_log( '[ContactIn] Error deleting upload directory: ' . $e->getMessage() );
		}

		// Clear any cached data
		try {
			wp_cache_flush();
		} catch ( \Throwable $e ) {
			error_log( '[ContactIn] Error flushing cache: ' . $e->getMessage() );
		}
	}

	/**
	 * Recursively delete a directory and all its contents.
	 *
	 * @param string $dir Directory path to delete.
	 * @return bool True on success, false on failure.
	 */
	private static function delete_directory_recursive( string $dir ): bool {
		try {
			if ( ! is_dir( $dir ) ) {
				error_log( '[ContactIn] delete_directory_recursive: Not a directory: ' . $dir );
				return false;
			}

			if ( ! is_readable( $dir ) ) {
				error_log( '[ContactIn] delete_directory_recursive: Directory not readable: ' . $dir );
				return false;
			}

			$entries = @scandir( $dir ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- Intentional: checks $entries === false below to handle failure.
			if ( $entries === false ) {
				error_log( '[ContactIn] delete_directory_recursive: scandir failed for: ' . $dir );
				return false;
			}

			$files   = array_diff( $entries, array( '.', '..' ) );
			$success = true;

			foreach ( $files as $file ) {
				$path = $dir . DIRECTORY_SEPARATOR . $file;

				if ( is_dir( $path ) && ! is_link( $path ) ) {
					// Recursively delete subdirectory
					if ( ! self::delete_directory_recursive( $path ) ) {
						error_log( '[ContactIn] Failed to delete subdirectory: ' . $path );
						$success = false;
					}
				} else {
					// Delete file or symlink
					if ( ! @unlink( $path ) ) { // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- Intentional: failure logged below.
						error_log( '[ContactIn] Failed to unlink file: ' . $path . ' (writable: ' . ( is_writable( $path ) ? 'yes' : 'no' ) . ')' );
						$success = false;
					}
				}
			}

			// Try to remove the directory itself
			if ( ! @rmdir( $dir ) ) { // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- Intentional: failure logged below.
				error_log( '[ContactIn] Failed to remove directory: ' . $dir . ' (writable: ' . ( is_writable( $dir ) ? 'yes' : 'no' ) . ')' );
				return false;
			}

			return $success;
		} catch ( \Throwable $e ) {
			error_log( '[ContactIn] Error in delete_directory_recursive: ' . $e->getMessage() );
			return false;
		}
	}

	/**
	 * Reschedule cron jobs after interval changes.
	 *
	 * @param string[] $hooks Optional list of hooks to reschedule.
	 */
	public static function reschedule_cron_jobs( array $hooks = array(), array $delays = array() ): void {
		self::schedule_cron_jobs( true, $hooks, $delays );
	}

	/**
	 * Centralized cron scheduling.
	 *
	 * @param bool     $force Clear existing schedules before creating new ones.
	 * @param string[] $hooks Optional list of hooks to schedule.
	 */
	private static function schedule_cron_jobs( bool $force = false, array $hooks = array(), array $delays = array() ): void {
		$bootstrap = CoreBootstrap::instance();
		add_filter( 'cron_schedules', array( $bootstrap, 'register_custom_schedules' ) );

		$now          = time();
		$schedule_map = array(
			Config::CRON_CLEANUP                 => array(
				'schedule'  => 'daily',
				'timestamp' => $now,
			),
			Config::CRON_GDPR                    => array(
				'schedule'  => 'hourly',
				'timestamp' => $now,
			),
			Config::CRON_PROCESS_EMAIL           => array(
				'schedule'  => get_option( 'contactin_queue_interval', 'contactin_fifteen_minutes' ),
				'timestamp' => $now,
			),
			Config::CRON_PROCESS_CRM             => array(
				'schedule'  => get_option(
					'contactin_crm_queue_interval',
					get_option( 'contactin_queue_interval', 'contactin_fifteen_minutes' )
				),
				'timestamp' => $now,
			),
			Config::CRON_GDPR_CLEANUP            => array(
				'schedule'  => 'daily',
				'timestamp' => $now,
			),
			Config::CRON_RECLASSIFY_UNCLASSIFIED => array(
				'schedule'  => 'daily',
				'timestamp' => $now,
			),
			Config::CRON_LEARN_FROM_FEEDBACK     => array(
				'schedule'  => 'weekly',
				'timestamp' => $now,
			),
		);

		if ( ! FreemiusIntegration::can_use_premium_features() ) {
			unset(
				$schedule_map[ Config::CRON_PROCESS_CRM ],
				$schedule_map[ Config::CRON_RECLASSIFY_UNCLASSIFIED ],
				$schedule_map[ Config::CRON_LEARN_FROM_FEEDBACK ]
			);
		}

		if ( ! empty( $hooks ) ) {
			$schedule_map = array_intersect_key( $schedule_map, array_flip( $hooks ) );
		}

		foreach ( $schedule_map as $hook => $settings ) {
			if ( $force ) {
				wp_clear_scheduled_hook( $hook );
			}

			if ( ! wp_next_scheduled( $hook ) ) {
				$delay      = $delays[ $hook ] ?? 0;
				$timestamp  = $settings['timestamp'] + max( 0, (int) $delay );
				$recurrence = (string) ( $settings['schedule'] ?? 'hourly' );
				$schedules  = wp_get_schedules();

				if ( ! isset( $schedules[ $recurrence ] ) ) {
					if ( $hook === Config::CRON_PROCESS_CRM || $hook === Config::CRON_PROCESS_EMAIL ) {
						$fallback = isset( $schedules['contactin_fifteen_minutes'] ) ? 'contactin_fifteen_minutes' : 'hourly';
					} elseif ( $hook === Config::CRON_CLEANUP || $hook === Config::CRON_RECLASSIFY_UNCLASSIFIED || $hook === Config::CRON_LEARN_FROM_FEEDBACK ) {
						$fallback = 'daily';
					} else {
						$fallback = 'hourly';
					}

					error_log(
						sprintf(
							'[ContactIn] Invalid cron recurrence "%s" for %s. Falling back to "%s".',
							$recurrence,
							$hook,
							$fallback
						)
					);

					if ( $hook === Config::CRON_PROCESS_EMAIL ) {
						update_option( 'contactin_queue_interval', $fallback );
					} elseif ( $hook === Config::CRON_PROCESS_CRM ) {
						update_option( 'contactin_crm_queue_interval', $fallback );
					}

					$recurrence = $fallback;
				}

				wp_schedule_event( $timestamp, $recurrence, $hook );
			}
		}

		if ( empty( $hooks ) || in_array( AnalyticsAggregationJob::HOOK, $hooks, true ) ) {
			AnalyticsAggregationJob::instance()->schedule( $force );
		}

		remove_filter( 'cron_schedules', array( $bootstrap, 'register_custom_schedules' ) );
	}
}
