<?php
namespace ContactInbox\Cron;

use ContactInbox\Traits\Singleton;
use ContactInbox\Core\Config;
use ContactInbox\Core\DB;
use ContactInbox\Core\Settings;
use ContactInbox\Core\Logger;
use ContactInbox\Core\QueueManager;
use ContactInbox\Core\QueueMonitor;
use ContactInbox\Core\AlertSystem;
use ContactInbox\Core\CircuitBreaker;
use ContactInbox\Core\RateLimiter;
use ContactInbox\Core\GDPR;
use ContactInbox\Core\ProcessLock;
use ContactInbox\Core\NameFormatter;
use ContactInbox\Core\IntentClassifier;
use ContactInbox\Core\ErrorClassifier;
use ContactInbox\Core\AlertGenerator;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter, Generic.PHP.ForbiddenFunctions.Found, PluginCheck.CodeAnalysis.DiscouragedFunctions.load_plugin_textdomainFound, PluginCheck.CodeAnalysis.Heredoc.NotAllowed, Squiz.PHP.DiscouragedFunctions.Discouraged, WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.NamingConventions.PrefixAllGlobals.DynamicHooknameFound, WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound, WordPress.PHP.DevelopmentFunctions.error_log_debug_backtrace, WordPress.WP.AlternativeFunctions.file_system_operations_fsockopen, WordPress.WP.AlternativeFunctions.file_system_operations_readfile, WordPress.WP.AlternativeFunctions.file_system_operations_rmdir, WordPress.WP.EnqueuedResourceParameters.MissingVersion, WordPress.WP.EnqueuedResources.NonEnqueuedScript, WordPress.WP.I18n.MissingArgDomain, WordPress.WP.I18n.UnorderedPlaceholdersPlural, WordPress.WP.I18n.UnorderedPlaceholdersSingle

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class CronJobs {
	use Singleton;

	/**
	 * Register cron job hooks.
	 */
	public function register(): void {
		add_action( Config::CRON_CLEANUP, array( $this, 'run_cleanup' ) );
		// Email processing cron.
		add_action( Config::CRON_PROCESS_EMAIL, array( $this, 'process_email_queue' ) );
		// Intent classification maintenance
		add_action( Config::CRON_RECLASSIFY_UNCLASSIFIED, array( $this, 'run_reclassify_unclassified' ) );

		// Throttled recovery for missing cron schedules.
		// This runs at most once per hour to avoid noisy duplicate scheduling
		// while still healing broken/missing schedules in long-running sites.
		if ( get_transient( 'contactin_cron_health_check_throttle' ) === false ) {
			self::ensure_cron_health();
			set_transient( 'contactin_cron_health_check_throttle', 1, HOUR_IN_SECONDS );
		}
	}

	/**
	 * Ensure cron jobs are scheduled and healthy
	 *
	 * Detects missing schedules and creates them (throttled to once per hour).
	 * This is a recovery mechanism for when schedules get accidentally deleted.
	 */
	private static function ensure_cron_health(): void {
		$email_interval = get_option( 'contactin_queue_interval', 'contactin_fifteen_minutes' );
		$cron           = get_option( 'cron', array() );

		$schedules = wp_get_schedules();
		if ( ! isset( $schedules[ $email_interval ] ) ) {
			$fallback = 'contactin_fifteen_minutes';
			if ( ! isset( $schedules[ $fallback ] ) ) {
				$fallback = 'hourly';
			}
			Logger::warning(
				'Invalid email queue interval slug detected, falling back',
				array(
					'invalid_interval'  => $email_interval,
					'fallback_interval' => $fallback,
				)
			);
			$email_interval = $fallback;
			update_option( 'contactin_queue_interval', $fallback );
		}

		$email_interval_seconds = $schedules[ $email_interval ]['interval'] ?? 900;

		// Email queue processor - only schedule if truly missing
		$email_next = wp_next_scheduled( Config::CRON_PROCESS_EMAIL );
		if ( ! $email_next ) {
			// Double-check by looking at the cron array directly to avoid wp_next_scheduled issues
			$has_email_cron = false;
			foreach ( $cron as $timestamp => $hooks ) {
				if ( isset( $hooks[ Config::CRON_PROCESS_EMAIL ] ) ) {
					$has_email_cron = true;
					break;
				}
			}

			if ( ! $has_email_cron ) {
				wp_schedule_event( time() + $email_interval_seconds, $email_interval, Config::CRON_PROCESS_EMAIL );
				Logger::info(
					'Email queue cron recovered: was missing, rescheduled',
					array(
						'interval'         => $email_interval,
						'interval_seconds' => $email_interval_seconds,
					)
				);
			}
		}

		// Intent reclassify cron: reschedule if missing.
		$reclassify_next = wp_next_scheduled( Config::CRON_RECLASSIFY_UNCLASSIFIED );
		if ( ! $reclassify_next ) {
			$has_reclassify_cron = false;
			foreach ( $cron as $timestamp => $hooks ) {
				if ( isset( $hooks[ Config::CRON_RECLASSIFY_UNCLASSIFIED ] ) ) {
					$has_reclassify_cron = true;
					break;
				}
			}

			if ( ! $has_reclassify_cron ) {
				wp_schedule_event( time() + DAY_IN_SECONDS, 'daily', Config::CRON_RECLASSIFY_UNCLASSIFIED );
				Logger::info( 'Intent reclassification cron recovered: was missing, rescheduled' );
			}
		}

	}

	/**
	 * Daily cleanup job – prune logs, clear caches.
	 */
	public function run_cleanup(): void {
		$record_id = CronMonitor::start_job( Config::CRON_CLEANUP );
		if ( ! $record_id ) {
			return;
		}

		try {
			$items_deleted = 0;
			$settings      = Settings::get_settings();

			// Clean up old completed queue items (older than 7 days)
			$completed_deleted = QueueManager::clear_completed( 7 );
			if ( $completed_deleted > 0 ) {
				Logger::info(
					'Cleaned up old completed queue items',
					array(
						'deleted'        => $completed_deleted,
						'retention_days' => 7,
					)
				);
			}

			// Clean up old DLQ items (older than 30 days)
			$dlq_deleted = QueueManager::clear_dlq( 30 );
			if ( $dlq_deleted > 0 ) {
				Logger::info(
					'Cleaned up old DLQ items',
					array(
						'deleted'        => $dlq_deleted,
						'retention_days' => 30,
					)
				);
			}

			$items_deleted = $completed_deleted + $dlq_deleted;

			// Orphaned attachment analytics (daily scan, no deletion)
			$stats = \ContactInbox\Core\AttachmentCleanupService::instance()->get_orphaned_analytics();
			update_option( 'contactin_orphaned_attachments_stats', $stats );

			// Clean up old temporary files (older than 24 hours)
			$temp_cleanup = \ContactInbox\Core\AttachmentCleanupService::instance()->delete_old_temp_files_cleanup();

			Logger::info(
				'Daily cleanup executed',
				array(
					'queue_completed_deleted'   => $completed_deleted,
					'queue_dlq_deleted'         => $dlq_deleted,
					'total_queue_items_deleted' => $items_deleted,
					'orphaned_attachments'      => $stats,
					'temp_files_deleted'        => $temp_cleanup,
				)
			);

			CronMonitor::success_job( $record_id, $items_deleted );
		} catch ( \Throwable $e ) {
			CronMonitor::fail_job( $record_id, 'exception', $e->getMessage() );
			Logger::error(
				'Cleanup cron error: ' . $e->getMessage(),
				array(
					'file' => $e->getFile(),
					'line' => $e->getLine(),
				)
			);
		}
	}

	/**
	 * Hourly GDPR expiry check – delete expired GDPR records.
	 */
	public function run_gdpr_expiry(): void {
		Logger::notice( 'GDPR expiry cron execution blocked: feature disabled in this build' );
		return;

		$record_id = false;
		if ( ! $record_id ) {
			return;
		}

		try {
			$deleted = DB::instance()->delete_expired_gdpr();

			Logger::info(
				'GDPR expiry check completed',
				array( 'deleted' => $deleted )
			);

			CronMonitor::success_job( $record_id, $deleted );
		} catch ( \Throwable $e ) {
			CronMonitor::fail_job( $record_id, 'exception', $e->getMessage() );
			Logger::error(
				'GDPR expiry check error: ' . $e->getMessage(),
				array(
					'file' => $e->getFile(),
					'line' => $e->getLine(),
				)
			);
		}
	}

	/**
	 * Process email queue with distributed locking
	 *
	 * Processes pending admin and user emails using ProcessLock to ensure
	 * only one processor runs at a time. Fails gracefully if another process
	 * is already running (lock is held).
	 *
	 * Lock TTL: 5 minutes (auto-expires if process crashes)
	 * Runs on scheduled interval (default: 15 minutes) or triggered by form submission
	 */
	public function process_email_queue(): void {
		// Attempt to acquire lock
		if ( ! ProcessLock::acquire( 'email', 300 ) ) {
			Logger::debug( 'Could not acquire email processor lock, another process is running' );
			return;
		}

		$record_id = CronMonitor::start_job( Config::CRON_PROCESS_EMAIL );
		if ( ! $record_id ) {
			ProcessLock::release( 'email' );
			return;
		}

		$max_iterations = 50;
		$processed      = 0;

		Logger::info( 'Email queue processor started with lock acquired' );

		try {
			global $wpdb;
			$table    = $wpdb->prefix . Config::TABLE_MESSAGES;
			$settings = get_option( Config::OPTION_SETTINGS, array() );

			// PHASE 1: Process queue table items (new unified queue system)
			// This processes queued items (email, attachment_retry types) with automatic retry logic
			while ( $processed < $max_iterations ) {
				$queue_item = QueueManager::get_next_item();
				if ( ! $queue_item ) {
					break; // No more queue items
				}

				try {
					// Attempt to mark item as processing - use nested try-catch to prevent
					// outer catch from calling mark_failed if this fails
					try {
						if ( ! QueueManager::mark_processing( $queue_item['id'] ) ) {
							Logger::error(
								'Could not mark email queue item as processing (database update failed)',
								array(
									'queue_id' => $queue_item['id'],
									'type'     => $queue_item['type'],
								)
							);
							continue; // Skip to next item without incrementing processed
						}
					} catch ( \Throwable $mark_error ) {
						// mark_processing threw an exception (e.g., database connection failure)
						Logger::error(
							'Exception while marking email queue item as processing',
							array(
								'queue_id' => $queue_item['id'],
								'type'     => $queue_item['type'],
								'error'    => $mark_error->getMessage(),
							)
						);
						continue; // Skip item - don't try to mark_failed since mark_processing failed
					}

					$queue_data = json_decode( $queue_item['data'], true ) ?: array();

					// Dispatch by queue type
					switch ( $queue_item['type'] ) {
						case 'email':
							// Email processing delegated to legacy message table (Phase 2)
							// Skip here - emails are processed with exponential backoff retry logic
							// in process_pending_admin_emails() and process_pending_user_emails()
							QueueManager::mark_completed( $queue_item['id'], array( 'processed_as' => 'skipped_legacy_phase' ) );
							break;

						case 'attachment_retry':
							self::process_attachment_retry( (int) $queue_item['id'], $queue_data );
							break;

						case 'webhook':
							self::process_webhook( (int) $queue_item['id'], $queue_data );
							break;

						default:
							Logger::warning(
								'Unknown queue type in email processor',
								array(
									'queue_id' => $queue_item['id'],
									'type'     => $queue_item['type'],
								)
							);
							QueueManager::mark_failed( $queue_item['id'], "Unknown queue type: {$queue_item['type']}" );
							break;
					}

					++$processed;
				} catch ( \Throwable $e ) {
					// This catch handles errors during actual queue item PROCESSING,
					// NOT errors from mark_processing (those are handled above)
					Logger::error(
						'Error processing email queue item',
						array(
							'queue_id' => $queue_item['id'],
							'type'     => $queue_item['type'],
							'error'    => $e->getMessage(),
						)
					);
					QueueManager::mark_failed( $queue_item['id'], $e->getMessage() );
					++$processed;
				}
			}

			// PHASE 2: Process legacy message table items (for backward compatibility)
			// If we still have iterations left, process messages from the old system
			if ( $processed < $max_iterations ) {
				// Process admin emails
				$admin_count = self::process_pending_admin_emails( $table, $settings, $max_iterations - $processed );
				$processed  += $admin_count;
			}

			// Process user emails
			if ( $processed < $max_iterations ) {
				$user_count = self::process_pending_user_emails( $table, $settings, $max_iterations - $processed );
				$processed += $user_count;
			}

			Logger::info( "Email queue processor completed. Processed {$processed} items" );

			// Record success with items processed
			CronMonitor::success_job( $record_id, $processed );

		} catch ( \Throwable $e ) {
			CronMonitor::fail_job( $record_id, 'exception', $e->getMessage() );
			Logger::critical(
				'Email queue processor fatal error: ' . $e->getMessage(),
				array(
					'file' => $e->getFile(),
					'line' => $e->getLine(),
				)
			);
		} finally {
			// DISABLED: This was creating duplicate schedules in a feedback loop
			// Schedules are created during plugin activation
			// self::ensure_recurring_schedule(
			// Config::CRON_PROCESS_EMAIL,
			// 'contactin_queue_interval',
			// 'contactin_fifteen_minutes'
			// );

			// Always release lock, even if exception occurred
			ProcessLock::release( 'email' );
		}
	}

	/**
	 * Process CRM queue with distributed locking
	 *
	 * Processes pending CRM syncs using ProcessLock to ensure only one
	 * processor runs at a time. Fails gracefully if another process is
	 * already running (lock is held).
	 *
	 * Lock TTL: 5 minutes (auto-expires if process crashes)
	 * Max Duration: 4 minutes (safety margin to release before TTL expires)
	 * Runs on scheduled interval (default: 15 minutes) or triggered by form submission
	 */
	public function process_crm_queue(): void {
		Logger::notice( 'CRM queue processor execution blocked: feature disabled in this build' );
		return;

		// Attempt to acquire lock
		if ( ! ProcessLock::acquire( 'crm', 300 ) ) {
			Logger::debug( 'Could not acquire CRM processor lock, another process is running' );
			return;
		}

		$record_id = false;
		if ( ! $record_id ) {
			ProcessLock::release( 'crm' );
			return;
		}

		$max_iterations       = 50;
		$max_duration_seconds = 240;  // 4 minutes (leaves 1 min safety margin before TTL)
		$start_time           = microtime( true );
		$processed            = 0;

		Logger::info( 'CRM queue processor started with lock acquired' );

		try {
			global $wpdb;
			$table        = $wpdb->prefix . Config::TABLE_MESSAGES;
			$crm_settings = \ContactInbox\Core\CRMSettings::get_settings();

			// PHASE 0: Detect and clean up stale processing items
			// This prevents items from being stuck in processing state forever
			$queue_repo  = new \ContactInbox\Core\Repositories\QueueRepository();
			$stale_moved = $queue_repo->move_stale_to_dlq( 30 );  // 30 minutes
			if ( $stale_moved > 0 ) {
				Logger::warning(
					'Cleaned up stale processing items',
					array(
						'count'  => $stale_moved,
						'reason' => 'Stuck in processing for > 30 minutes',
					)
				);
			}

			$normalized_pending = $queue_repo->normalize_pending_next_attempt( 'crm_delete' );
			if ( $normalized_pending > 0 ) {
				Logger::info(
					'Normalized CRM delete pending next_attempt values',
					array(
						'count' => $normalized_pending,
					)
				);
			}

			// PHASE 1: Process queue table items (new unified queue system)
			// This processes queued items (crm, attachment_retry types) with automatic retry logic
			while ( $processed < $max_iterations ) {
				// Safety check: exit if we've been running too long
				$elapsed = microtime( true ) - $start_time;
				if ( $elapsed > $max_duration_seconds ) {
					Logger::warning(
						'CRM processor max duration reached, safely exiting',
						array(
							'processed'            => $processed,
							'elapsed_seconds'      => round( $elapsed, 2 ),
							'max_duration_seconds' => $max_duration_seconds,
						)
					);
					break;
				}

				$queue_item = QueueManager::get_next_item( array( 'crm', 'attachment_retry', 'crm_delete' ) );
				if ( ! $queue_item ) {
					$queue_stats      = $queue_repo->get_stats_by_type();
					$crm_delete_stats = $queue_stats['crm_delete'] ?? array();
					$crm_stats        = $queue_stats['crm'] ?? array();
					$attachment_stats = $queue_stats['attachment_retry'] ?? array();
					$pending_total    = (int) ( $crm_delete_stats['pending'] ?? 0 )
						+ (int) ( $crm_delete_stats['retry'] ?? 0 )
						+ (int) ( $crm_stats['pending'] ?? 0 )
						+ (int) ( $crm_stats['retry'] ?? 0 )
						+ (int) ( $attachment_stats['pending'] ?? 0 )
						+ (int) ( $attachment_stats['retry'] ?? 0 );

					if ( $pending_total > 0 ) {
						Logger::warning(
							'CRM queue has items but none are eligible for processing',
							array(
								'crm_delete'       => $crm_delete_stats,
								'crm'              => $crm_stats,
								'attachment_retry' => $attachment_stats,
							)
						);
					}
					break; // No more queue items
				}

				try {
					// Attempt to mark item as processing - use nested try-catch to prevent
					// outer catch from calling mark_failed if this fails
					try {
						if ( ! QueueManager::mark_processing( $queue_item['id'] ) ) {
							Logger::error(
								'Could not mark CRM queue item as processing (database update failed)',
								array(
									'queue_id' => $queue_item['id'],
									'type'     => $queue_item['type'],
								)
							);
							continue; // Skip to next item without incrementing processed
						}
					} catch ( \Throwable $mark_error ) {
						// mark_processing threw an exception (e.g., database connection failure)
						Logger::error(
							'Exception while marking CRM queue item as processing',
							array(
								'queue_id' => $queue_item['id'],
								'type'     => $queue_item['type'],
								'error'    => $mark_error->getMessage(),
							)
						);
						continue; // Skip item - don't try to mark_failed since mark_processing failed
					}

					$queue_data = json_decode( $queue_item['data'], true ) ?: array();

					// Dispatch by queue type
					switch ( $queue_item['type'] ) {
						case 'crm':
							self::process_crm_from_queue( (int) $queue_item['id'], $queue_data );
							break;

						case 'attachment_retry':
							self::process_attachment_retry( (int) $queue_item['id'], $queue_data );
							break;

						case 'crm_delete':
							self::process_crm_delete( (int) $queue_item['id'], $queue_data );
							break;

						default:
							Logger::warning(
								'Unknown queue type in CRM processor',
								array(
									'queue_id' => $queue_item['id'],
									'type'     => $queue_item['type'],
								)
							);
							QueueManager::mark_failed( $queue_item['id'], "Unknown queue type: {$queue_item['type']}" );
							break;
					}

					++$processed;
				} catch ( \Throwable $e ) {
					// This catch handles errors during actual queue item PROCESSING,
					// NOT errors from mark_processing (those are handled above)
					Logger::error(
						'Error processing CRM queue item',
						array(
							'queue_id' => $queue_item['id'],
							'type'     => $queue_item['type'],
							'error'    => $e->getMessage(),
						)
					);
					QueueManager::mark_failed( $queue_item['id'], $e->getMessage() );
					++$processed;
				}
			}

			// PHASE 2: Process legacy message table items (for backward compatibility)
			// If we still have iterations left, process messages from the old system
			if ( $processed < $max_iterations ) {
				$crm_count  = self::process_pending_crm_syncs( $table, $crm_settings, $max_iterations - $processed );
				$processed += $crm_count;
			}

			Logger::info( "CRM queue processor completed. Processed {$processed} items" );

			// Check for alerts
			AlertSystem::check_and_alert();

			// Record success with items processed
			CronMonitor::success_job( $record_id, $processed );

		} catch ( \Throwable $e ) {
			CronMonitor::fail_job( $record_id, 'exception', $e->getMessage() );
			Logger::critical(
				'CRM queue processor fatal error: ' . $e->getMessage(),
				array(
					'file' => $e->getFile(),
					'line' => $e->getLine(),
				)
			);
		} finally {
			// DISABLED: This was creating duplicate schedules in a feedback loop
			// Schedules are created during plugin activation
			// self::ensure_recurring_schedule(
			// 'disabled_crm_hook',
			// 'disabled_crm_interval',
			// 'contactin_fifteen_minutes',
			// 'contactin_queue_interval'
			// );

			// Always release lock, even if exception occurred
			ProcessLock::release( 'crm' );
		}
	}

	/**
	 * Process email item from queue table
	 *
	 * Handles email queue items with data payload from QueueManager.
	 * Minimal processing: email queue items are typically handled by legacy message table processing.
	 *
	 * @param int   $queue_id Queue item ID
	 * @param array $data Queue item payload
	 * @throws \Exception If processing fails
	 */
	private static function process_email_from_queue( int $queue_id, array $data ): void {
		// Email queue items would contain email-specific data
		// For now, minimal processing since email processing is handled by messages table
		// This method should not call mark_completed/mark_failed - let the dispatcher handle status

		Logger::debug(
			'Email queue item dispatch (processing handled by message table)',
			array(
				'queue_id' => $queue_id,
			)
		);

		// If any processing was needed here, it would go above
		// No explicit completion needed - dispatcher will handle it
	}

	/**
	 * Process CRM item from queue table
	 *
	 * Handles CRM queue items with data payload from QueueManager
	 *
	 * @param int   $queue_id Queue item ID
	 * @param array $data Queue item payload containing CRM sync data
	 */
	private static function process_crm_from_queue( int $queue_id, array $data ): void {
		Logger::notice(
			'CRM queue item skipped: CRM background sync disabled',
			array(
				'queue_id' => $queue_id,
			)
		);
		QueueManager::mark_completed(
			$queue_id,
			array(
				'crm_status' => 'disabled',
			)
		);
	}

	/**
	 * Process attachment retry item from queue table
	 *
	 * Retrieves failed attachment data from queue and attempts re-upload to Salesforce
	 * with automatic retry logic handled by QueueManager.
	 *
	 * @param int $queue_id Queue item ID
	 * @param array $data Queue item payload containing attachment metadata
	 */
	/**
	 * Process CRM contact deletion (GDPR cleanup)
	 */
	private static function process_crm_delete( int $queue_id, array $data ): void {
		try {
			$crm_id        = (string) ( $data['crm_id'] ?? '' );
			$contact_id    = $crm_id !== '' ? $crm_id : (string) ( $data['contact_id'] ?? '' );
			$email         = (string) ( $data['email'] ?? '' );
			$name          = (string) ( $data['name'] ?? '' );
			$gdpr_log_id   = (int) ( $data['gdpr_log_id'] ?? 0 );
			$http_code     = null;
			$response_body = null;
			$error_code    = null;
			$error_fields  = null;
			$child_cleanup = null;

			$write_contact_delete_log = function ( string $status, array $response, ?string $error_message = null ) use ( $queue_id, $data, $crm_id ): void {
				$crm_repo = new \ContactInbox\Core\Repositories\CRMRepository();

				$updated = $crm_repo->finalize_contact_delete_pending_log(
					$queue_id,
					$data,
					$status,
					$response,
					$error_message
				);

				if ( $updated ) {
					return;
				}

				$crm_repo->insert_log(
					array(
						'message_id'    => 0,
						'crm_system'    => 'salesforce',
						'operation'     => 'contact_delete',
						'crm_id'        => $response['crm_id'] ?? ( $crm_id ?: null ),
						'status'        => $status,
						'response'      => $response,
						'error_message' => $error_message,
					)
				);
			};

			// Get CRM settings
			$settings = \ContactInbox\Core\CRMSettings::get_settings();
			if ( empty( $settings['crm_enabled'] ) ) {
				Logger::info( 'CRM disabled, skipping delete', array( 'queue_id' => $queue_id ) );
				QueueManager::mark_completed( $queue_id );
				return;
			}

			if ( empty( $settings['crm_delete_sync'] ) ) {
				if ( $gdpr_log_id > 0 ) {
					global $wpdb;
					$table = $wpdb->prefix . Config::TABLE_GDPR_DELETION_LOG;
					$wpdb->update(
						$table,
						array(
							'crm_sync_status' => 'manual_required',
							'deletion_status' => 'completed',
							'error_message'   => 'CRM deletion sync disabled; delete in Salesforce manually.',
						),
						array( 'id' => $gdpr_log_id ),
						array( '%s', '%s', '%s' ),
						array( '%d' )
					);
				}

				$write_contact_delete_log(
					'skipped',
					array(
						'contact_id'  => $contact_id,
						'crm_id'      => $crm_id ?: null,
						'email'       => $email,
						'name'        => $name,
						'note'        => 'CRM deletion sync disabled',
						'gdpr_log_id' => $gdpr_log_id,
					)
				);

				QueueManager::mark_completed( $queue_id );
				return;
			}

			if ( $crm_id === '' && $gdpr_log_id > 0 ) {
				global $wpdb;
				$table_gdpr_log = $wpdb->prefix . Config::TABLE_GDPR_DELETION_LOG;
				$stored_crm_id  = $wpdb->get_var(
					$wpdb->prepare(
						"SELECT crm_id FROM {$table_gdpr_log} WHERE id = %d",
						$gdpr_log_id
					)
				);
				if ( ! empty( $stored_crm_id ) ) {
					$crm_id     = (string) $stored_crm_id;
					$contact_id = $crm_id;
				}
			}

			// If contact_id is not a Salesforce ID, try to fetch from contacts table first
			// This is faster than a Salesforce query if we already have the ID stored
			if ( ! empty( $contact_id ) && ! self::is_salesforce_id( $contact_id ) ) {
				global $wpdb;
				$table_contacts = $wpdb->prefix . Config::TABLE_CONTACTS;

				// Try to get the Salesforce ID from the contacts table
				$stored_sf_id = $wpdb->get_var(
					$wpdb->prepare(
						"SELECT crm_id FROM {$table_contacts} WHERE id = %d OR email = %s LIMIT 1",
						(int) $contact_id,
						$email
					)
				);

				if ( $stored_sf_id ) {
					$contact_id = $stored_sf_id;
					$crm_id     = $stored_sf_id;
				}
			}

			if ( $gdpr_log_id > 0 ) {
				global $wpdb;
				$table = $wpdb->prefix . Config::TABLE_GDPR_DELETION_LOG;
				$wpdb->update(
					$table,
					array(
						'crm_sync_status' => 'manual_required',
						'deletion_status' => 'completed',
						'error_message'   => 'CRM deletion sync disabled; delete in Salesforce manually.',
					),
					array( 'id' => $gdpr_log_id ),
					array( '%s', '%s', '%s' ),
					array( '%d' )
				);
			}

			$write_contact_delete_log(
				'skipped',
				array(
					'contact_id'  => $contact_id,
					'crm_id'      => $crm_id ?: null,
					'email'       => $email,
					'name'        => $name,
					'note'        => 'CRM deletion sync disabled',
					'gdpr_log_id' => $gdpr_log_id,
				)
			);

			QueueManager::mark_completed( $queue_id );
			return;

			$resolved_contact_id = $contact_id;
			if ( ! self::is_salesforce_id( $resolved_contact_id ) ) {
				if ( empty( $email ) ) {
					throw new \Exception( 'CRM contact ID missing and email not provided for lookup' );
				}
				try {
					$resolved_contact_id = self::fetch_contact_id_by_email(
						$email,
						$instance_url,
						$api_version,
						$access_token,
						$settings
					);
				} catch ( \Throwable $lookup_error ) {
					// If contact lookup fails (not found), treat as already deleted
					if ( stripos( $lookup_error->getMessage(), 'no results' ) !== false ||
						stripos( $lookup_error->getMessage(), 'not found' ) !== false ) {

						Logger::info(
							'CRM contact not found during lookup - already deleted',
							array(
								'queue_id'   => $queue_id,
								'email'      => $email,
								'contact_id' => $contact_id,
							)
						);

						if ( $gdpr_log_id > 0 ) {
							global $wpdb;
							$table = $wpdb->prefix . Config::TABLE_GDPR_DELETION_LOG;
							$wpdb->update(
								$table,
								array(
									'crm_sync_status' => 'deleted',
									'deletion_status' => 'completed',
									'error_message'   => null,
								),
								array( 'id' => $gdpr_log_id ),
								array( '%s', '%s', '%s' ),
								array( '%d' )
							);
						}

						$write_contact_delete_log(
							'delivered',
							array(
								'contact_id'   => $contact_id,
								'crm_id'       => $contact_id,
								'email'        => $email,
								'name'         => $name,
								'note'         => 'Contact not found in Salesforce - already deleted',
								'gdpr_log_id'  => $gdpr_log_id,
								'lookup_error' => $lookup_error->getMessage(),
							)
						);

						QueueManager::mark_completed( $queue_id );
						return;
					}
					// Other lookup errors (auth, network, etc.) should be retried
					throw $lookup_error;
				}
			}

			if ( $resolved_contact_id ) {
				$crm_id = $resolved_contact_id;
			}

			if ( empty( $resolved_contact_id ) ) {
				// If we still don't have an ID after lookup, contact doesn't exist - treat as success
				Logger::info(
					'CRM contact ID could not be resolved - already deleted',
					array(
						'queue_id'   => $queue_id,
						'email'      => $email,
						'contact_id' => $contact_id,
					)
				);

				if ( $gdpr_log_id > 0 ) {
					global $wpdb;
					$table = $wpdb->prefix . Config::TABLE_GDPR_DELETION_LOG;
					$wpdb->update(
						$table,
						array(
							'crm_sync_status' => 'deleted',
							'deletion_status' => 'completed',
							'error_message'   => null,
						),
						array( 'id' => $gdpr_log_id ),
						array( '%s', '%s', '%s' ),
						array( '%d' )
					);
				}

				$write_contact_delete_log(
					'delivered',
					array(
						'contact_id'  => $contact_id,
						'crm_id'      => $contact_id ?: null,
						'email'       => $email,
						'name'        => $name,
						'note'        => 'Contact ID could not be resolved - already deleted',
						'gdpr_log_id' => $gdpr_log_id,
					)
				);

				QueueManager::mark_completed( $queue_id );
				return;
			}

			// Best-effort pre-cleanup of restricted child records.
			// Do not block contact deletion when describe/query cleanup fails;
			// rely on Salesforce DELETE response as the source of truth.
			try {
				$child_cleanup = self::delete_restricted_child_records(
					$resolved_contact_id,
					$instance_url,
					$api_version,
					$access_token,
					$settings
				);

				if ( ! empty( $child_cleanup['errors'] ) ) {
					Logger::warning(
						'CRM child cleanup had errors; attempting contact delete anyway',
						array(
							'queue_id'     => $queue_id,
							'contact_id'   => $resolved_contact_id,
							'errors_count' => count( $child_cleanup['errors'] ),
						)
					);
				}
			} catch ( \Throwable $child_cleanup_error ) {
				$child_cleanup = array(
					'checked'   => 0,
					'deleted'   => 0,
					'errors'    => array(
						array(
							'object'        => null,
							'id'            => null,
							'message'       => $child_cleanup_error->getMessage(),
							'http_code'     => null,
							'response_body' => null,
						),
					),
					'by_object' => array(),
					'warning'   => 'child_cleanup_failed_continue_delete',
				);

				Logger::warning(
					'CRM child cleanup unavailable; continuing with direct contact delete',
					array(
						'queue_id'   => $queue_id,
						'contact_id' => $resolved_contact_id,
						'error'      => $child_cleanup_error->getMessage(),
					)
				);
			}

			// Salesforce DELETE API endpoint
			$endpoint = "{$instance_url}/services/data/{$api_version}/sobjects/Contact/{$resolved_contact_id}";

			$response = self::request_salesforce(
				'DELETE',
				$endpoint,
				array(
					'headers' => array(
						'Authorization' => 'Bearer ' . $access_token,
						'Content-Type'  => 'application/json',
					),
				),
				$settings
			);

			if ( is_wp_error( $response ) ) {
				throw new \Exception( 'CRM delete request failed: ' . $response->get_error_message() );
			}

			$http_code     = wp_remote_retrieve_response_code( $response );
			$body          = wp_remote_retrieve_body( $response );
			$response_body = $body;

			// 204 No Content is success for DELETE
			if ( $http_code === 204 || $http_code === 200 ) {
				try {
					// Success path: update logs and complete
					// Note: GDPR log update is intentionally after queue completion (best-effort)
					// so that queue item is marked complete even if GDPR update fails

					Logger::info(
						'CRM contact deleted successfully',
						array(
							'queue_id'    => $queue_id,
							'contact_id'  => $resolved_contact_id,
							'email'       => $email,
							'gdpr_log_id' => $gdpr_log_id,
						)
					);

					// Log to CRM log table for audit trail (must not throw)
					try {
						$write_contact_delete_log(
							'delivered',
							array(
								'contact_id'    => $resolved_contact_id,
								'crm_id'        => $resolved_contact_id,
								'email'         => $email,
								'name'          => $name,
								'http_code'     => $http_code,
								'gdpr_log_id'   => $gdpr_log_id,
								'child_cleanup' => $child_cleanup,
							)
						);
					} catch ( \Throwable $crm_log_error ) {
						// Log CRM log insert failure but don't block queue completion
						Logger::warning(
							'CRM log insert failed but queue marked complete',
							array(
								'queue_id' => $queue_id,
								'error'    => $crm_log_error->getMessage(),
							)
						);
					}

					// Mark queue as completed (critical - must not fail)
					if ( ! QueueManager::mark_completed( $queue_id ) ) {
						throw new \Exception( 'Failed to mark queue item as completed' );
					}

					// Update GDPR log AFTER queue is completed (best-effort, won't block completion)
					if ( $gdpr_log_id > 0 ) {
						try {
							global $wpdb;
							$table = $wpdb->prefix . Config::TABLE_GDPR_DELETION_LOG;
							$wpdb->update(
								$table,
								array(
									'crm_sync_status' => 'deleted',
									'deletion_status' => 'completed',
									'error_message'   => null,
								),
								array( 'id' => $gdpr_log_id ),
								array( '%s', '%s', '%s' ),
								array( '%d' )
							);
						} catch ( \Throwable $gdpr_update_error ) {
							// GDPR log update failure is now non-critical
							// CRM log has the authoritative record and cron can sync later
							Logger::warning(
								'GDPR log update failed (will be synced by cron)',
								array(
									'queue_id'    => $queue_id,
									'gdpr_log_id' => $gdpr_log_id,
									'error'       => $gdpr_update_error->getMessage(),
								)
							);
						}
					}
				} catch ( \Throwable $success_error ) {
					// If anything in the success path fails, treat as retriable error
					Logger::error(
						'Error during CRM delete success path',
						array(
							'queue_id'   => $queue_id,
							'contact_id' => $resolved_contact_id,
							'error'      => $success_error->getMessage(),
						)
					);
					// Rethrow to be caught by outer catch
					throw $success_error;
				}
			} else {
				// Handle specific error cases
				$error_data    = json_decode( $body, true );
				$error_code    = $error_data[0]['errorCode'] ?? null;
				$error_fields  = $error_data[0]['fields'] ?? null;
				$error_message = $error_data[0]['message'] ?? "HTTP {$http_code}: {$body}";

				// Check if this is an "already deleted" scenario - treat as success
				$is_already_deleted     = false;
				$already_deleted_reason = '';

				if ( $http_code === 404 ) {
					$is_already_deleted     = true;
					$already_deleted_reason = '404 Not Found';
				}

				if ( $error_code === 'ENTITY_IS_DELETED' ) {
					$is_already_deleted     = true;
					$already_deleted_reason = 'ENTITY_IS_DELETED error code';
				}

				if ( stripos( $error_message, 'entity is deleted' ) !== false ) {
					$is_already_deleted     = true;
					$already_deleted_reason = 'entity is deleted in message';
				}

				if ( $is_already_deleted ) {
					try {
						// Already deleted success path: update logs and complete
						// GDPR log update is intentionally after queue completion (best-effort)

						Logger::info(
							'CRM contact already deleted',
							array(
								'queue_id'    => $queue_id,
								'contact_id'  => $contact_id,
								'resolved_id' => $resolved_contact_id,
								'email'       => $email,
								'http_code'   => $http_code,
								'error_code'  => $error_code,
								'reason'      => $already_deleted_reason,
							)
						);

						// Log to CRM log table even for already deleted (must not throw)
						try {
							$write_contact_delete_log(
								'delivered',
								array(
									'contact_id'    => $resolved_contact_id,
									'crm_id'        => $resolved_contact_id,
									'email'         => $email,
									'name'          => $name,
									'http_code'     => $http_code,
									'error_code'    => $error_code,
									'note'          => 'Contact already deleted (' . $already_deleted_reason . ')',
									'gdpr_log_id'   => $gdpr_log_id,
									'response_body' => $response_body,
									'child_cleanup' => $child_cleanup,
								)
							);
						} catch ( \Throwable $crm_log_error ) {
							Logger::warning(
								'CRM log insert failed but queue marked complete',
								array(
									'queue_id' => $queue_id,
									'error'    => $crm_log_error->getMessage(),
								)
							);
						}

						// Mark queue as completed (critical)
						if ( ! QueueManager::mark_completed( $queue_id ) ) {
							throw new \Exception( 'Failed to mark queue item as completed' );
						}

						// Update GDPR log AFTER queue is completed (best-effort)
						if ( $gdpr_log_id > 0 ) {
							try {
								global $wpdb;
								$table = $wpdb->prefix . Config::TABLE_GDPR_DELETION_LOG;
								$wpdb->update(
									$table,
									array(
										'crm_sync_status' => 'deleted',
										'deletion_status' => 'completed',
										'error_message'   => null,
									),
									array( 'id' => $gdpr_log_id ),
									array( '%s', '%s', '%s' ),
									array( '%d' )
								);
							} catch ( \Throwable $gdpr_update_error ) {
								Logger::warning(
									'GDPR log update failed (will be synced by cron)',
									array(
										'queue_id'    => $queue_id,
										'gdpr_log_id' => $gdpr_log_id,
										'error'       => $gdpr_update_error->getMessage(),
									)
								);
							}
						}
						return;
					} catch ( \Throwable $success_error ) {
						// If anything in the already-deleted success path fails, treat as retriable error
						Logger::error(
							'Error during CRM delete already-deleted success path',
							array(
								'queue_id'   => $queue_id,
								'contact_id' => $resolved_contact_id,
								'error'      => $success_error->getMessage(),
							)
						);
						// Rethrow to be caught by outer catch
						throw $success_error;
					}
				}

				throw new \Exception( $error_message );
			}
		} catch ( \Throwable $e ) {
			// Classify the error to determine retriability
			$error_type   = ErrorClassifier::classify( $e );
			$is_retriable = ErrorClassifier::is_retriable( $error_type );

			Logger::error(
				'CRM delete failed',
				array(
					'queue_id'     => $queue_id,
					'error'        => $e->getMessage(),
					'contact_id'   => $data['contact_id'] ?? null,
					'email'        => $data['email'] ?? null,
					'error_type'   => $error_type,
					'is_retriable' => $is_retriable,
				)
			);

			// Log failure to CRM log table
			try {
				$write_contact_delete_log(
					'failed',
					array(
						'contact_id'    => $resolved_contact_id ?? ( $data['contact_id'] ?? null ),
						'crm_id'        => $resolved_contact_id ?? ( $data['crm_id'] ?? null ),
						'email'         => $data['email'] ?? null,
						'name'          => $data['name'] ?? null,
						'gdpr_log_id'   => $data['gdpr_log_id'] ?? null,
						'error_type'    => $error_type,
						'retriable'     => $is_retriable,
						'http_code'     => $http_code,
						'error_code'    => $error_code,
						'error_fields'  => $error_fields,
						'response_body' => $response_body,
						'child_cleanup' => $child_cleanup,
					),
					$e->getMessage()
				);
			} catch ( \Throwable $log_error ) {
				Logger::error(
					'Failed to log CRM delete error',
					array(
						'queue_id'       => $queue_id,
						'log_error'      => $log_error->getMessage(),
						'original_error' => $e->getMessage(),
					)
				);
			}

			// Update GDPR log with error
			if ( ! empty( $data['gdpr_log_id'] ) ) {
				global $wpdb;
				$table = $wpdb->prefix . Config::TABLE_GDPR_DELETION_LOG;
				$wpdb->update(
					$table,
					array(
						'deletion_status' => 'failed',
						'error_message'   => substr( $e->getMessage(), 0, 500 ),
					),
					array( 'id' => (int) $data['gdpr_log_id'] ),
					array( '%s', '%s' ),
					array( '%d' )
				);
			}

			// Emit alert for critical errors
			AlertGenerator::alert_crm_failure(
				$error_type,
				$e->getMessage(),
				0,
				array(
					'queue_id'   => $queue_id,
					'operation'  => 'contact_delete',
					'contact_id' => $data['contact_id'] ?? null,
					'email'      => $data['email'] ?? null,
					'retriable'  => $is_retriable,
				)
			);

			// Handle retriable vs non-retriable errors (same pattern as CRM sync)
			try {
				if ( $is_retriable ) {
					// Retriable error - use adaptive retry logic with jitter
					if ( ! QueueManager::mark_failed( $queue_id, $e->getMessage(), $error_type ) ) {
						Logger::critical(
							'Failed to mark CRM delete item as failed - stuck in processing',
							array(
								'queue_id'       => $queue_id,
								'original_error' => $e->getMessage(),
							)
						);
					}
				} else {
					// Non-retriable error - move directly to DLQ without retrying
					if ( ! QueueManager::mark_non_retryable( $queue_id, $error_type, $e->getMessage() ) ) {
						Logger::critical(
							'Failed to move CRM delete item to DLQ - stuck in processing',
							array(
								'queue_id'       => $queue_id,
								'error_type'     => $error_type,
								'original_error' => $e->getMessage(),
							)
						);
					}
					// Emit DLQ alert for permanent failures
					AlertGenerator::alert_dlq_item( 'crm_delete', $e->getMessage(), 0, "[{$error_type}]" );
				}
			} catch ( \Throwable $mark_error ) {
				// If even marking the failure fails, log critical error and move to DLQ directly
				Logger::critical(
					'Exception while updating queue status for CRM delete - attempting DLQ move',
					array(
						'queue_id'       => $queue_id,
						'original_error' => $e->getMessage(),
						'mark_error'     => $mark_error->getMessage(),
					)
				);
				// Try to move to DLQ as last resort
				try {
					QueueManager::move_to_dlq( $queue_id, 'Failed to update queue status: ' . $mark_error->getMessage() );
				} catch ( \Throwable $dlq_error ) {
					Logger::critical(
						'Item completely stuck - could not move to DLQ',
						array(
							'queue_id'  => $queue_id,
							'dlq_error' => $dlq_error->getMessage(),
						)
					);
				}
			}
		}
	}

	private static function is_salesforce_id( string $contact_id ): bool {
		return (bool) preg_match( '/^[a-zA-Z0-9]{15,18}$/', $contact_id );
	}

	/**
	 * Resolve outbound Salesforce timeout (seconds).
	 */
	private static function get_crm_request_timeout_seconds( array $settings = array() ): int {
		$configured = (int) ( $settings['request_timeout'] ?? 30 );
		$filtered   = (int) apply_filters( 'contactin_crm_request_timeout', $configured, $settings );
		return max( 15, min( 90, $filtered ) );
	}

	/**
	 * Resolve timeout retry attempts for transport-level timeout failures.
	 */
	private static function get_crm_timeout_retry_attempts( array $settings = array() ): int {
		$configured = (int) ( $settings['request_timeout_retries'] ?? 1 );
		$filtered   = (int) apply_filters( 'contactin_crm_timeout_retries', $configured, $settings );
		return max( 0, min( 3, $filtered ) );
	}

	/**
	 * Determine whether a transport error is timeout-related.
	 */
	private static function is_timeout_transport_error( $response ): bool {
		if ( ! is_wp_error( $response ) ) {
			return false;
		}

		$message = strtolower( (string) $response->get_error_message() );
		return strpos( $message, 'curl error 28' ) !== false
			|| strpos( $message, 'timed out' ) !== false
			|| strpos( $message, 'operation timed out' ) !== false
			|| strpos( $message, 'timeout' ) !== false;
	}

	/**
	 * Execute Salesforce request with timeout-aware retry for transport timeouts.
	 */
	private static function request_salesforce( string $method, string $endpoint, array $args, array $settings = array() ) {
		$timeout     = self::get_crm_request_timeout_seconds( $settings );
		$max_retries = self::get_crm_timeout_retry_attempts( $settings );

		$request_args = array_merge(
			$args,
			array(
				'method'  => strtoupper( $method ),
				'timeout' => $timeout,
			)
		);

		$attempt = 0;
		do {
			$response = wp_remote_request( $endpoint, $request_args );
			if ( ! self::is_timeout_transport_error( $response ) ) {
				return $response;
			}

			if ( $attempt >= $max_retries ) {
				return $response;
			}

			$wait_us = 250000 * ( $attempt + 1 );
			usleep( $wait_us );
			++$attempt;
		} while ( true );
	}

	/**
	 * Detect Salesforce authentication failures that should trigger a token refresh retry.
	 */
	private static function is_salesforce_auth_failure( $response ): bool {
		if ( is_wp_error( $response ) ) {
			return false;
		}

		$code = (int) wp_remote_retrieve_response_code( $response );
		if ( $code !== 401 ) {
			return false;
		}

		$raw = (string) wp_remote_retrieve_body( $response );
		if ( $raw === '' ) {
			return true;
		}

		$decoded = json_decode( $raw, true );
		if ( ! is_array( $decoded ) ) {
			return true;
		}

		$entry      = isset( $decoded[0] ) && is_array( $decoded[0] ) ? $decoded[0] : $decoded;
		$error_code = strtoupper( (string) ( $entry['errorCode'] ?? $entry['error'] ?? '' ) );

		return in_array( $error_code, array( 'INVALID_AUTH_HEADER', 'INVALID_SESSION_ID', 'INVALID_SESSION' ), true )
			|| $error_code === '';
	}

	/**
	 * Check whether request args include an Authorization Bearer token header.
	 */
	private static function has_bearer_auth_header( array $request_args ): bool {
		if ( empty( $request_args['headers'] ) || ! is_array( $request_args['headers'] ) ) {
			return false;
		}

		foreach ( $request_args['headers'] as $header_name => $header_value ) {
			if ( strtolower( (string) $header_name ) === 'authorization' && stripos( (string) $header_value, 'Bearer ' ) === 0 ) {
				return true;
			}
		}

		return false;
	}

	private static function fetch_contact_id_by_email(
		string $email,
		string $instance_url,
		string $api_version,
		string $access_token,
		array $settings = array()
	): ?string {
		$escaped_email = str_replace( "'", "\\'", $email );
		$query         = rawurlencode( "SELECT Id FROM Contact WHERE Email = '{$escaped_email}' LIMIT 1" );
		$query_url     = "{$instance_url}/services/data/{$api_version}/query?q={$query}";

		$response = self::request_salesforce(
			'GET',
			$query_url,
			array(
				'headers' => array(
					'Authorization' => 'Bearer ' . $access_token,
					'Content-Type'  => 'application/json',
				),
			),
			$settings
		);

		if ( is_wp_error( $response ) ) {
			throw new \Exception( 'CRM contact lookup failed due request error' );
		}

		$code = wp_remote_retrieve_response_code( $response );
		$body = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( $code !== 200 || empty( $body['records'][0]['Id'] ) ) {
			throw new \Exception( 'CRM contact lookup failed due invalid response' );
		}

		return $body['records'][0]['Id'];
	}

	private static function delete_restricted_child_records(
		string $contact_id,
		string $instance_url,
		string $api_version,
		string $access_token,
		array $settings = array()
	): array {
		$relationships = self::fetch_restricted_child_relationships(
			$instance_url,
			$api_version,
			$access_token,
			$settings
		);

		$summary = array(
			'checked'   => count( $relationships ),
			'deleted'   => 0,
			'errors'    => array(),
			'by_object' => array(),
		);

		foreach ( $relationships as $relationship ) {
			$child_object = $relationship['childSObject'];
			$field        = $relationship['field'];

			$ids = self::fetch_child_record_ids(
				$child_object,
				$field,
				$contact_id,
				$instance_url,
				$api_version,
				$access_token,
				$settings
			);

			if ( empty( $ids ) ) {
				continue;
			}

			if ( ! isset( $summary['by_object'][ $child_object ] ) ) {
				$summary['by_object'][ $child_object ] = array(
					'field'   => $field,
					'found'   => 0,
					'deleted' => 0,
					'errors'  => 0,
				);
			}

			$summary['by_object'][ $child_object ]['found'] += count( $ids );

			foreach ( $ids as $child_id ) {
				$delete_result = self::delete_child_record(
					$child_object,
					$child_id,
					$instance_url,
					$api_version,
					$access_token,
					$settings
				);

				if ( $delete_result['success'] ) {
					++$summary['deleted'];
					++$summary['by_object'][ $child_object ]['deleted'];
				} else {
					$summary['errors'][] = array(
						'object'        => $child_object,
						'id'            => $child_id,
						'message'       => $delete_result['message'],
						'http_code'     => $delete_result['http_code'],
						'response_body' => $delete_result['response_body'],
					);
					++$summary['by_object'][ $child_object ]['errors'];
				}
			}
		}

		return $summary;
	}

	private static function fetch_restricted_child_relationships(
		string $instance_url,
		string $api_version,
		string $access_token,
		array $settings = array()
	): array {
		$endpoint = "{$instance_url}/services/data/{$api_version}/sobjects/Contact/describe";

		$response = self::request_salesforce(
			'GET',
			$endpoint,
			array(
				'headers' => array(
					'Authorization' => 'Bearer ' . $access_token,
					'Content-Type'  => 'application/json',
				),
			),
			$settings
		);

		if ( is_wp_error( $response ) ) {
			throw new \Exception( 'CRM describe failed due request error' );
		}

		$code = wp_remote_retrieve_response_code( $response );
		$body = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( $code !== 200 || empty( $body['childRelationships'] ) ) {
			throw new \Exception( 'CRM describe failed: unable to read child relationships' );
		}

		$relationships = array();
		foreach ( $body['childRelationships'] as $relationship ) {
			$restricted   = (bool) ( $relationship['restrictedDelete'] ?? false );
			$child_object = $relationship['childSObject'] ?? '';
			$field        = $relationship['field'] ?? '';
			$deprecated   = (bool) ( $relationship['deprecatedAndHidden'] ?? false );

			if ( ! $restricted || $deprecated || $child_object === '' || $field === '' ) {
				continue;
			}

			$key                   = $child_object . ':' . $field;
			$relationships[ $key ] = array(
				'childSObject' => $child_object,
				'field'        => $field,
			);
		}

		return array_values( $relationships );
	}

	private static function fetch_child_record_ids(
		string $child_object,
		string $field,
		string $contact_id,
		string $instance_url,
		string $api_version,
		string $access_token,
		array $settings = array()
	): array {
		$records    = array();
		$escaped_id = str_replace( "'", "\\'", $contact_id );
		$query      = rawurlencode( "SELECT Id FROM {$child_object} WHERE {$field} = '{$escaped_id}'" );
		$query_url  = "{$instance_url}/services/data/{$api_version}/query?q={$query}";

		while ( $query_url ) {
			$response = self::request_salesforce(
				'GET',
				$query_url,
				array(
					'headers' => array(
						'Authorization' => 'Bearer ' . $access_token,
						'Content-Type'  => 'application/json',
					),
				),
				$settings
			);

			if ( is_wp_error( $response ) ) {
				throw new \Exception( 'CRM query failed due request error' );
			}

			$code = wp_remote_retrieve_response_code( $response );
			$body = json_decode( wp_remote_retrieve_body( $response ), true );

			if ( $code !== 200 || ! isset( $body['records'] ) ) {
				throw new \Exception( 'CRM query failed due invalid response' );
			}

			foreach ( $body['records'] as $record ) {
				if ( ! empty( $record['Id'] ) ) {
					$records[] = $record['Id'];
				}
			}

			if ( ! empty( $body['nextRecordsUrl'] ) ) {
				$query_url = $instance_url . $body['nextRecordsUrl'];
			} else {
				$query_url = '';
			}
		}

		return $records;
	}

	private static function delete_child_record(
		string $child_object,
		string $child_id,
		string $instance_url,
		string $api_version,
		string $access_token,
		array $settings = array()
	): array {
		$endpoint = "{$instance_url}/services/data/{$api_version}/sobjects/{$child_object}/{$child_id}";

		$response = self::request_salesforce(
			'DELETE',
			$endpoint,
			array(
				'headers' => array(
					'Authorization' => 'Bearer ' . $access_token,
					'Content-Type'  => 'application/json',
				),
			),
			$settings
		);

		if ( is_wp_error( $response ) ) {
			return array(
				'success'       => false,
				'message'       => $response->get_error_message(),
				'http_code'     => null,
				'response_body' => null,
			);
		}

		$code = wp_remote_retrieve_response_code( $response );
		$body = wp_remote_retrieve_body( $response );

		if ( $code === 204 || $code === 200 || $code === 404 ) {
			return array(
				'success'       => true,
				'message'       => 'Deleted',
				'http_code'     => $code,
				'response_body' => $body,
			);
		}

		return array(
			'success'       => false,
			'message'       => "HTTP {$code}: {$body}",
			'http_code'     => $code,
			'response_body' => $body,
		);
	}

	private static function process_attachment_retry( int $queue_id, array $data ): void {
		Logger::notice(
			'Attachment retry skipped: CRM attachment sync disabled',
			array(
				'queue_id' => $queue_id,
			)
		);
		QueueManager::mark_completed(
			$queue_id,
			array(
				'attachment_sync' => 'disabled',
			)
		);
	}

	/**
	 * Process pending messages with retry logic (Phase 2: Message-Centric Processing)
	 *
	 * Scans messages table for pending/failed statuses and processes them.
	 * Runs every 2 minutes. Uses exponential backoff for retries.
	 *
	 * @deprecated Use process_email_queue() and process_crm_queue() instead
	 */
	public function process_pending_messages(): void {
		$record_id = CronMonitor::start_job( 'contactin_process_queue' );
		if ( ! $record_id ) {
			return;
		}

		$max_iterations = 50;
		$processed      = 0;

		Logger::info( 'Message processor started' );

		try {
			global $wpdb;
			$table        = $wpdb->prefix . Config::TABLE_MESSAGES;
			$settings     = get_option( Config::OPTION_SETTINGS, array() );
			$crm_settings = \ContactInbox\Core\CRMSettings::get_settings();

			// Process admin emails
			$admin_count = self::process_pending_admin_emails( $table, $settings, $max_iterations - $processed );
			$processed  += $admin_count;

			// Process user emails
			if ( $processed < $max_iterations ) {
				$user_count = self::process_pending_user_emails( $table, $settings, $max_iterations - $processed );
				$processed += $user_count;
			}

			// Process CRM syncs
			if ( $processed < $max_iterations ) {
				$crm_count  = self::process_pending_crm_syncs( $table, $crm_settings, $max_iterations - $processed );
				$processed += $crm_count;
			}

			Logger::info( "Message processor completed. Processed {$processed} items" );

			// Check for alerts
			AlertSystem::check_and_alert();

			// Record success with items processed
			CronMonitor::success_job( $record_id, $processed );

		} catch ( \Throwable $e ) {
			CronMonitor::fail_job( $record_id, 'exception', $e->getMessage() );
			Logger::critical(
				'Message processor fatal error: ' . $e->getMessage(),
				array(
					'file' => $e->getFile(),
					'line' => $e->getLine(),
				)
			);
		}
	}

	/**
	 * Process pending admin email notifications
	 */
	private static function process_pending_admin_emails( string $table, array $settings, int $limit ): int {
		global $wpdb;
		$processed = 0;
		$smtp_enabled = ! empty( $settings['smtp_enable'] );

		// Skip if admin notifications are disabled
		if ( empty( $settings['send_admin_notification'] ) ) {
			$wpdb->query(
				$wpdb->prepare(
					"UPDATE {$table}
                     SET admin_email_status = %s,
                         admin_email_error = NULL,
                         admin_email_sent_at = NULL,
                         admin_email_retries = 0
                     WHERE admin_email_status IS NULL
                        OR admin_email_status IN (%s, %s, %s)",
					Config::EMAIL_SKIPPED,
					Config::EMAIL_PENDING,
					Config::EMAIL_FAILED,
					Config::EMAIL_PROCESSING
				)
			);

			Logger::debug( 'Admin email processing skipped: Admin notifications disabled' );
			return 0;
		}

		// Check SMTP circuit only when SMTP transport is active.
		if ( $smtp_enabled && ! CircuitBreaker::is_available( 'smtp' ) ) {
			Logger::warning(
				'Admin email processing skipped: SMTP circuit breaker open',
				array(
					'state' => CircuitBreaker::get_state( 'smtp' ),
				)
			);
			return 0;
		}

		// Get pending or failed admin emails with exponential backoff
		$messages = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$table}
            WHERE (admin_email_status IS NULL OR admin_email_status = %s OR admin_email_status = %s)
            AND admin_email_retries < 5
            ORDER BY submitted_at ASC
            LIMIT %d",
				Config::EMAIL_PENDING,
				Config::EMAIL_FAILED,
				$limit
			)
		);

		if ( empty( $messages ) ) {
			return 0;
		}

		foreach ( $messages as $message ) {
			$message_id  = (int) $message->id;
			$retry_count = (int) ( $message->admin_email_retries ?? 0 );

			$claimed = $wpdb->query(
				$wpdb->prepare(
					"UPDATE {$table}
                     SET admin_email_status = %s
                     WHERE id = %d
                       AND (admin_email_status IS NULL OR admin_email_status = %s OR admin_email_status = %s)",
					Config::EMAIL_PROCESSING,
					$message_id,
					Config::EMAIL_PENDING,
					Config::EMAIL_FAILED
				)
			);

			if ( ! $claimed ) {
				Logger::debug(
					'Admin email already claimed by another worker',
					array(
						'message_id' => $message_id,
						'status'     => $message->admin_email_status ?? 'unknown',
					)
				);
				continue;
			}

			// Exponential backoff: skip if not enough time has passed since last attempt
			if ( $retry_count > 0 && ! empty( $message->submitted_at ) ) {
				$backoff_seconds = pow( 2, $retry_count ) * 60; // 2min, 4min, 8min, 16min, 32min
				$next_attempt    = strtotime( $message->submitted_at ) + $backoff_seconds;
				if ( time() < $next_attempt ) {
					$wpdb->query(
						$wpdb->prepare(
							"UPDATE {$table} SET admin_email_status = %s WHERE id = %d",
							Config::EMAIL_PENDING,
							$message_id
						)
					);
					Logger::debug(
						"Skipping message #{$message_id}: backoff not expired",
						array(
							'message_id'   => $message_id,
							'retry_count'  => $retry_count,
							'next_attempt' => gmdate( 'Y-m-d H:i:s', $next_attempt ),
						)
					);
					continue;
				}
			}

			$start_time = microtime( true );

			try {
				$delete_link = self::resolve_delete_link( $message );

				// Build email data
				$display_name = NameFormatter::display( $message->salutation ?? '', $message->name ?? '' );
				$email_data   = array(
					'message_id'   => $message_id,
					'name'         => $display_name,
					'salutation'   => $message->salutation ?? '',
					'email'        => $message->email,
					'subject'      => $message->subject,
					'message'      => $message->message,
					'phone'        => $message->phone ?? '',
					'ip_address'   => $message->ip_address ?? '',
					'ip'           => $message->ip_address ?? '',
					'submitted_at' => $message->submitted_at,
					'date'         => $message->submitted_at,
					'attachment'   => $message->attachment ?? '',
					'delete_link'  => $delete_link,
					'inbox_link'   => \admin_url( sprintf( 'admin.php?page=%1$s', Config::MENU_INBOX ) ),
				);

				// Send admin notification
				$result = \ContactInbox\Core\SMTP::send_admin_notification( $email_data );

				if ( $result ) {
					// Success: mark as sent
					$wpdb->update(
						$table,
						array(
							'admin_email_status'  => Config::EMAIL_SENT,
							'admin_email_sent_at' => current_time( 'mysql' ),
							'admin_email_error'   => null,
						),
						array( 'id' => $message_id ),
						array( '%s', '%s', '%s' ),
						array( '%d' )
					);

					if ( $smtp_enabled ) {
						CircuitBreaker::record_success( 'smtp' );
					}
					$duration_ms = intval( ( microtime( true ) - $start_time ) * 1000 );
					QueueMonitor::record_operation( 'admin_email', true, $duration_ms );

					Logger::info( "Admin email sent for message #{$message_id}" );
					++$processed;
				} else {
					$error = \ContactInbox\Core\SMTP::get_last_error();
					if ( $error === '' ) {
						$error = 'Admin notification send returned false';
					}
					throw new \Exception( $error );
				}
			} catch ( \Throwable $e ) {
				// Failure: classify error and emit alert
				$error_type   = ErrorClassifier::classify( $e );
				$is_retriable = ErrorClassifier::is_retriable( $error_type );

				// Mark as failed with alert
				$wpdb->update(
					$table,
					array(
						'admin_email_status'  => Config::EMAIL_FAILED,
						'admin_email_error'   => substr( $e->getMessage(), 0, 1000 ),
						'admin_email_retries' => $retry_count + 1,
					),
					array( 'id' => $message_id ),
					array( '%s', '%s', '%d' ),
					array( '%d' )
				);

				if ( $smtp_enabled ) {
					CircuitBreaker::record_failure( 'smtp', $e->getMessage() );
				}
				$duration_ms = intval( ( microtime( true ) - $start_time ) * 1000 );
				QueueMonitor::record_operation( 'admin_email', false, $duration_ms, $e->getMessage() );

				// Emit alert for email failures
				AlertGenerator::alert_email_failure(
					$e->getMessage(),
					$message_id,
					$message->email ?? ''
				);

				Logger::error(
					"Admin email failed for message #{$message_id}: " . $e->getMessage(),
					array(
						'message_id'  => $message_id,
						'retry_count' => $retry_count + 1,
						'error_type'  => $error_type,
						'retriable'   => $is_retriable,
					)
				);

				// Schedule one-off retry processor if retriable and not at max retries
				if ( $is_retriable && ( $retry_count + 1 ) < 5 ) {
					$submitted_timestamp = strtotime( $message->submitted_at );
					self::schedule_email_retry( $retry_count + 1, $submitted_timestamp );
				}
			}
		}

		return $processed;
	}

	/**
	 * Schedule a one-off cron event for email retry processing.
	 * Ensures retries execute at the calculated next_attempt time instead of waiting
	 * for the next recurring 15-minute cron tick.
	 *
	 * @param int $retry_count Current retry count (after increment)
	 * @param int $submitted_timestamp Unix timestamp of original submission
	 */
	private static function schedule_email_retry( int $retry_count, int $submitted_timestamp ): void {
		// Calculate next attempt using same backoff formula
		$backoff_seconds = pow( 2, $retry_count ) * 60; // 2min, 4min, 8min, 16min, 32min
		$next_attempt    = $submitted_timestamp + $backoff_seconds;

		// Don't schedule in the past
		if ( $next_attempt <= time() ) {
			$next_attempt = time() + 1;
		}

		// Schedule one-off event at next_attempt
		$scheduled = wp_schedule_single_event( $next_attempt, Config::CRON_PROCESS_EMAIL );

		if ( $scheduled !== false ) {
			Logger::debug(
				'Scheduled one-off email retry processor',
				array(
					'retry_count'     => $retry_count,
					'backoff_seconds' => $backoff_seconds,
					'next_attempt'    => gmdate( 'Y-m-d H:i:s', $next_attempt ),
				)
			);

			// Spawn cron to trigger immediate execution (with fallback if disabled)
			if ( function_exists( 'spawn_cron' ) ) {
				spawn_cron( $next_attempt );
			}
		} else {
			Logger::warning(
				'Failed to schedule one-off email retry processor',
				array(
					'retry_count'  => $retry_count,
					'next_attempt' => gmdate( 'Y-m-d H:i:s', $next_attempt ),
				)
			);
		}
	}

	/**
	 * Process pending user email confirmations
	 */
	private static function process_pending_user_emails( string $table, array $settings, int $limit ): int {
		global $wpdb;
		$processed = 0;
		$smtp_enabled = ! empty( $settings['smtp_enable'] );

		// Skip if user confirmations are disabled
		if ( empty( $settings['send_user_copy'] ) ) {
			$wpdb->query(
				$wpdb->prepare(
					"UPDATE {$table}
                     SET user_email_status = %s,
                         user_email_error = NULL,
                         user_email_sent_at = NULL,
                         user_email_retries = 0
                     WHERE user_email_status IS NULL
                        OR user_email_status IN (%s, %s, %s)",
					Config::EMAIL_SKIPPED,
					Config::EMAIL_PENDING,
					Config::EMAIL_FAILED,
					Config::EMAIL_PROCESSING
				)
			);

			return 0;
		}

		// Check SMTP circuit only when SMTP transport is active.
		if ( $smtp_enabled && ! CircuitBreaker::is_available( 'smtp' ) ) {
			return 0;
		}

		// Get pending or failed user emails with exponential backoff
		$messages = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$table}
            WHERE (user_email_status IS NULL OR user_email_status = %s OR user_email_status = %s)
            AND user_email_retries < 5
            ORDER BY submitted_at ASC
            LIMIT %d",
				Config::EMAIL_PENDING,
				Config::EMAIL_FAILED,
				$limit
			)
		);

		if ( empty( $messages ) ) {
			return 0;
		}

		foreach ( $messages as $message ) {
			$message_id  = (int) $message->id;
			$retry_count = (int) ( $message->user_email_retries ?? 0 );

			$claimed = $wpdb->query(
				$wpdb->prepare(
					"UPDATE {$table}
                     SET user_email_status = %s
                     WHERE id = %d
                       AND (user_email_status IS NULL OR user_email_status = %s OR user_email_status = %s)",
					Config::EMAIL_PROCESSING,
					$message_id,
					Config::EMAIL_PENDING,
					Config::EMAIL_FAILED
				)
			);

			if ( ! $claimed ) {
				Logger::debug(
					'User email already claimed by another worker',
					array(
						'message_id' => $message_id,
						'status'     => $message->user_email_status ?? 'unknown',
					)
				);
				continue;
			}

			// Exponential backoff
			if ( $retry_count > 0 && ! empty( $message->submitted_at ) ) {
				$backoff_seconds = pow( 2, $retry_count ) * 60;
				$next_attempt    = strtotime( $message->submitted_at ) + $backoff_seconds;
				if ( time() < $next_attempt ) {
					$wpdb->query(
						$wpdb->prepare(
							"UPDATE {$table} SET user_email_status = %s WHERE id = %d",
							Config::EMAIL_PENDING,
							$message_id
						)
					);
					Logger::debug(
						"Skipping user email {$message_id}: backoff not expired",
						array(
							'message_id'   => $message_id,
							'retry_count'  => $retry_count,
							'next_attempt' => gmdate( 'Y-m-d H:i:s', $next_attempt ),
						)
					);
					continue;
				}
			}

			$start_time = microtime( true );

			try {
				// Build email data
				$display_name = NameFormatter::display( $message->salutation ?? '', $message->name ?? '' );
				$email_data   = array(
					'message_id'    => $message_id,
					'name'          => $display_name,
					'salutation'    => $message->salutation ?? '',
					'email'         => $message->email,
					'subject'       => $message->subject,
					'message'       => $message->message,
					'phone'         => $message->phone ?? '',
					'receipt_token' => $message->receipt_token ?? '',
					'submitted_at'  => $message->submitted_at ?? '',
				);

				// Send user confirmation
				$result = \ContactInbox\Core\SMTP::send_user_confirmation( $email_data );

				if ( $result ) {
					// Success
					$wpdb->update(
						$table,
						array(
							'user_email_status'  => Config::EMAIL_SENT,
							'user_email_sent_at' => current_time( 'mysql' ),
							'user_email_error'   => null,
						),
						array( 'id' => $message_id ),
						array( '%s', '%s', '%s' ),
						array( '%d' )
					);

					if ( $smtp_enabled ) {
						CircuitBreaker::record_success( 'smtp' );
					}
					$duration_ms = intval( ( microtime( true ) - $start_time ) * 1000 );
					QueueMonitor::record_operation( 'user_email', true, $duration_ms );

					Logger::info( "User confirmation sent for message #{$message_id}" );
					++$processed;
				} else {
					$error = \ContactInbox\Core\SMTP::get_last_error();
					if ( $error === '' ) {
						$error = 'User confirmation send returned false';
					}
					throw new \Exception( $error );
				}
			} catch ( \Throwable $e ) {
				// Failure: classify error and emit alert
				$error_type   = ErrorClassifier::classify( $e );
				$is_retriable = ErrorClassifier::is_retriable( $error_type );

				// Mark as failed with alert
				$wpdb->update(
					$table,
					array(
						'user_email_status'  => Config::EMAIL_FAILED,
						'user_email_error'   => substr( $e->getMessage(), 0, 1000 ),
						'user_email_retries' => $retry_count + 1,
					),
					array( 'id' => $message_id ),
					array( '%s', '%s', '%d' ),
					array( '%d' )
				);

				if ( $smtp_enabled ) {
					CircuitBreaker::record_failure( 'smtp', $e->getMessage() );
				}
				$duration_ms = intval( ( microtime( true ) - $start_time ) * 1000 );
				QueueMonitor::record_operation( 'user_email', false, $duration_ms, $e->getMessage() );

				// Emit alert for email failures
				AlertGenerator::alert_email_failure(
					$e->getMessage(),
					$message_id,
					$message->email ?? ''
				);

				Logger::error(
					"User confirmation failed for message #{$message_id}: " . $e->getMessage(),
					array(
						'message_id'  => $message_id,
						'retry_count' => $retry_count + 1,
						'error_type'  => $error_type,
						'retriable'   => $is_retriable,
					)
				);

				// Schedule one-off retry processor if retriable and not at max retries
				if ( $is_retriable && ( $retry_count + 1 ) < 5 ) {
					$submitted_timestamp = strtotime( $message->submitted_at );
					self::schedule_email_retry( $retry_count + 1, $submitted_timestamp );
				}
			}
		}

		return $processed;
	}

	/**
	 * Process pending CRM syncs
	 */
	private static function process_pending_crm_syncs( string $table, array $crm_settings, int $limit ): int {
		if ( empty( $crm_settings['crm_enabled'] ) ) {
			return 0;
		}

		Logger::notice(
			'Legacy CRM sync processor skipped: CRM background sync disabled',
			array(
				'limit' => $limit,
			)
		);

		return 0;
	}

	private static function resolve_delete_link( object $message ): string {
		$email = sanitize_email( $message->email ?? '' );
		if ( $email === '' || ! is_email( $email ) ) {
			return '';
		}

		$token   = '';
		$expires = isset( $message->gdpr_expires ) ? (int) $message->gdpr_expires : 0;
		if ( ! empty( $message->gdpr_token ) && $expires > time() ) {
			$token = (string) $message->gdpr_token;
		} else {
			$generated = GDPR::generate_token( (int) ( $message->id ?? 0 ) );
			if ( ! empty( $generated ) ) {
				$token = $generated;
			}
		}

		return $token !== '' ? GDPR::build_delete_link( $token, $email ) : '';
	}

	/**
	 * Legacy process_email method - kept for backward compatibility with old queue items
	 *
	 * @deprecated Will be removed after queue table migration is complete
	 */
	private static function process_email( int $queue_id, array $data ): void {
		// Extract email_data and settings from queue data
		$email_data = $data['email_data'] ?? array();
		$settings   = $data['settings'] ?? array();

		$display_name = NameFormatter::display( $email_data['salutation'] ?? '', $email_data['name'] ?? '' );
		if ( $display_name !== '' ) {
			$email_data['name'] = $display_name;
		}

		$current_settings = get_option( Config::OPTION_SETTINGS, array() );
		$smtp_enabled     = ! empty( $current_settings['smtp_enable'] );

		if ( empty( $email_data['email'] ) || empty( $email_data['name'] ) ) {
			throw new \Exception( 'Invalid email data: missing email or name' );
		}

		$result = array();
		$errors = array();

		// Check SMTP circuit only when SMTP transport is active.
		if ( $smtp_enabled && ! CircuitBreaker::is_available( 'smtp' ) ) {
			$errors[] = 'SMTP service unavailable (circuit breaker open)';
			Logger::warning(
				'SMTP operation skipped',
				array(
					'queue_id' => $queue_id,
					'reason'   => 'Circuit breaker open',
					'state'    => CircuitBreaker::get_state( 'smtp' ),
				)
			);
		} else {
			// Send admin notification if enabled
			if ( ! empty( $settings['send_admin_notification'] ) ) {
				try {
					$admin_result = \ContactInbox\Core\SMTP::send_admin_notification( $email_data );
					if ( $admin_result ) {
						$result['admin_notification'] = 'sent';
						if ( $smtp_enabled ) {
							CircuitBreaker::record_success( 'smtp' );
						}
					} else {
						$admin_error = \ContactInbox\Core\SMTP::get_last_error();
						$admin_error = $admin_error !== '' ? $admin_error : 'Admin notification returned false';
						$errors[]    = 'Admin notification failed: ' . $admin_error;
						if ( $smtp_enabled ) {
							CircuitBreaker::record_failure( 'smtp', $admin_error );
						}
					}
				} catch ( \Throwable $e ) {
					$errors[] = 'Admin notification error: ' . $e->getMessage();
					if ( $smtp_enabled ) {
						CircuitBreaker::record_failure( 'smtp', $e->getMessage() );
					}
				}
			}

			// Send user confirmation if enabled
			if ( ! empty( $settings['send_user_copy'] ) ) {
				try {
					$user_result = \ContactInbox\Core\SMTP::send_user_confirmation( $email_data );
					if ( $user_result ) {
						$result['user_confirmation'] = 'sent';
						if ( $smtp_enabled ) {
							CircuitBreaker::record_success( 'smtp' );
						}
					} else {
						$user_error = \ContactInbox\Core\SMTP::get_last_error();
						$user_error = $user_error !== '' ? $user_error : 'User confirmation returned false';
						$errors[]   = 'User confirmation failed: ' . $user_error;
						if ( $smtp_enabled ) {
							CircuitBreaker::record_failure( 'smtp', $user_error );
						}
					}
				} catch ( \Throwable $e ) {
					$errors[] = 'User confirmation error: ' . $e->getMessage();
					if ( $smtp_enabled ) {
						CircuitBreaker::record_failure( 'smtp', $e->getMessage() );
					}
				}
			}
		}

		// Fail queue item only if all operations failed and are not optional
		if ( ! empty( $errors ) && empty( $result ) ) {
			throw new \Exception( 'Email operations failed' );
		}

		QueueManager::mark_completed( $queue_id, $result );

		Logger::info(
			"Email operations completed for {$email_data['email']}",
			array(
				'queue_id' => $queue_id,
				'email'    => $email_data['email'],
				'result'   => $result,
				'errors'   => $errors,
			)
		);
	}

	/**
	 * Process CRM queue item with graceful degradation
	 */
	private static function process_crm( int $queue_id, array $data ): void {
		Logger::notice(
			'Legacy CRM queue processor skipped: CRM background sync disabled',
			array(
				'queue_id' => $queue_id,
			)
		);
		QueueManager::mark_completed(
			$queue_id,
			array(
				'crm_status' => 'disabled',
			)
		);
	}

	/**
	 * Process webhook queue item
	 */
	private static function process_webhook( int $queue_id, array $data ): void {
		QueueManager::mark_non_retryable( $queue_id, ErrorClassifier::VALIDATION, 'Webhook delivery is not available in this build' );
		Logger::notice(
			'Webhook delivery skipped: feature not available in this build',
			array(
				'queue_id' => $queue_id,
			)
		);
	}

	/**
	 * Ensure a recurring schedule exists and is spaced after immediate runs
	 */
	private static function ensure_recurring_schedule(
		string $hook,
		string $interval_option,
		string $default_interval,
		?string $fallback_interval_option = null
	): void {
		$schedules = wp_get_schedules();

		$interval_slug = get_option( $interval_option, $default_interval );
		if ( $fallback_interval_option && empty( $interval_slug ) ) {
			$interval_slug = get_option( $fallback_interval_option, $default_interval );
		}

		$interval_seconds = $schedules[ $interval_slug ]['interval'] ?? 900;
		$next_run         = wp_next_scheduled( $hook );

		// If missing or too close, reschedule to now + interval
		if ( ! $next_run || $next_run <= time() ) {
			if ( $next_run ) {
				wp_unschedule_event( $next_run, $hook );
			}
			wp_schedule_event( time() + $interval_seconds, $interval_slug, $hook );
			return;
		}

		// If a fast-lane just ran, ensure the next recurring run is spaced out
		if ( ( $next_run - time() ) < ( $interval_seconds / 2 ) ) {
			wp_unschedule_event( $next_run, $hook );
			wp_schedule_event( time() + $interval_seconds, $interval_slug, $hook );
		}
	}

	/**
	 * GDPR Deletion Cleanup Job
	 * Retries failed GDPR deletions once every 24 hours
	 */
	public function run_gdpr_deletion_cleanup(): void {
		Logger::notice( 'GDPR deletion cleanup execution blocked: feature disabled in this build' );
		return;

		$start_time = microtime( true );

		// Log cron start
		$log_id = 0;

		try {
			$gdpr_repo    = new \ContactInbox\Core\Repositories\GDPRRepository();
			$contact_repo = new \ContactInbox\Core\Repositories\ContactRepository();

			// Get failed or pending deletions
			$failed_deletions = $gdpr_repo->get_failed_pending_deletions( 10, 1 );

			$processed = 0;
			$success   = 0;
			$failed    = 0;

			foreach ( $failed_deletions as $log ) {
				++$processed;

				// Check if contact still exists
				if ( ! $contact_repo->exists( (int) $log->contact_id ) ) {
					// Contact already deleted, mark as completed
					$gdpr_repo->update_deletion_status( (int) $log->id, 'completed', null );
					++$success;
					continue;
				}

				// Retry deletion
				try {
					// Delete contact and messages
					$result = $contact_repo->delete_with_messages( (int) $log->contact_id );

					if ( $result['contact_deleted'] ) {
						$gdpr_repo->update_deletion_status(
							(int) $log->id,
							'completed',
							null,
							$result['messages_deleted']
						);
						++$success;
					} else {
						$gdpr_repo->update_deletion_status(
							(int) $log->id,
							'failed',
							'Failed to delete contact record'
						);
						++$failed;
					}
				} catch ( \Throwable $e ) {
					$gdpr_repo->update_deletion_status(
						(int) $log->id,
						'failed',
						$e->getMessage()
					);
					++$failed;
				}
			}

			// Log completion
			$duration_ms = (int) ( ( microtime( true ) - $start_time ) * 1000 );
			$this->log_cron_end( $log_id, 'success', $duration_ms, $processed );

			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				Logger::notice(
					'GDPR deletion cleanup completed',
					array(
						'processed'   => $processed,
						'success'     => $success,
						'failed'      => $failed,
						'duration_ms' => $duration_ms,
					)
				);
			}
		} catch ( \Throwable $e ) {
			$duration_ms = (int) ( ( microtime( true ) - $start_time ) * 1000 );
			$this->log_cron_end( $log_id, 'failed', $duration_ms, 0, $e->getMessage() );

			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				Logger::error(
					'GDPR deletion cleanup failed',
					array(
						'error' => $e->getMessage(),
					)
				);
			}
		}
	}

	/**
	 * Fallback maintenance job - reclassify unclassified messages.
	 *
	 * This runs daily to catch any messages that failed initial classification,
	 * enabling a second chance at intent detection using current patterns.
	 * Processes in batches to avoid performance issues.
	 */
	public function run_reclassify_unclassified(): void {
		// Classification runs regardless of license — only learning is premium-gated.
		$record_id = CronMonitor::start_job( Config::CRON_RECLASSIFY_UNCLASSIFIED );
		if ( ! $record_id ) {
			return;
		}

		$start_time = microtime( true );

		try {
			$classifier         = IntentClassifier::instance();
			$batch_size         = 100;
			$processed          = 0;
			$success            = 0;
			$failed             = 0;
			$total_unclassified = 0;

			// Get total count of unclassified messages
			$total_unclassified = $classifier->count_unclassified_messages();

			if ( $total_unclassified === 0 ) {
				Logger::info( 'Intent reclassification: no unclassified messages found' );
				$this->log_cron_end( $record_id, 'success', 0, 0 );
				return;
			}

			Logger::info(
				'Intent reclassification started',
				array(
					'total_unclassified' => $total_unclassified,
					'batch_size'         => $batch_size,
				)
			);

			// Process in batches
			$batches = ceil( $total_unclassified / $batch_size );
			for ( $batch = 0; $batch < $batches; $batch++ ) {
				try {
					$offset = $batch * $batch_size;
					$result = $classifier->bulk_classify_unclassified( $batch_size, $offset );

					if ( is_array( $result ) ) {
						$processed += $result['processed'] ?? 0;
						$success   += $result['success'] ?? 0;
						$failed    += $result['failed'] ?? 0;

						if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
							Logger::debug(
								'Intent reclassification batch processed',
								array(
									'batch'           => $batch + 1,
									'of'              => $batches,
									'batch_processed' => $result['processed'] ?? 0,
									'batch_success'   => $result['success'] ?? 0,
									'batch_failed'    => $result['failed'] ?? 0,
								)
							);
						}
					}
				} catch ( \Throwable $e ) {
					Logger::error(
						'Intent reclassification batch failed',
						array(
							'batch' => $batch + 1,
							'error' => $e->getMessage(),
						)
					);
					$failed += $batch_size;
				}

				// Small delay between batches to reduce database load
				usleep( 50000 ); // 50ms
			}

			// Log completion
			$duration_ms = (int) ( ( microtime( true ) - $start_time ) * 1000 );
			$this->log_cron_end( $record_id, 'success', $duration_ms, $processed );

			Logger::info(
				'Intent reclassification completed',
				array(
					'total_unclassified_found' => $total_unclassified,
					'total_processed'          => $processed,
					'success'                  => $success,
					'failed'                   => $failed,
					'duration_ms'              => $duration_ms,
				)
			);

		} catch ( \Throwable $e ) {
			$duration_ms = (int) ( ( microtime( true ) - $start_time ) * 1000 );
			$this->log_cron_end( $record_id, 'failed', $duration_ms, 0, $e->getMessage() );

			Logger::error(
				'Intent reclassification cron job failed',
				array(
					'error' => $e->getMessage(),
					'file'  => $e->getFile(),
					'line'  => $e->getLine(),
				)
			);
		}
	}

	/**
	 * Helper method to log cron job start
	 *
	 * @param string $cron_hook The cron hook name
	 * @return int|null The record ID or null if already running
	 */
	private function log_cron_start( string $cron_hook ): ?int {
		return CronMonitor::start_job( $cron_hook );
	}

	/**
	 * Helper method to log cron job end status
	 *
	 * @param int    $record_id The cron log record ID
	 * @param string $status 'success' or 'failed'
	 * @param int    $duration_ms Duration in milliseconds
	 * @param int    $processed Number of items processed
	 * @param string $error_message Error message if failed
	 */
	private function log_cron_end( int $record_id, string $status, int $duration_ms, int $processed, string $error_message = '' ): void {
		if ( $status === 'success' ) {
			CronMonitor::success_job( $record_id, $processed );
		} else {
			CronMonitor::fail_job( $record_id, $status, $error_message );
		}
	}
}
