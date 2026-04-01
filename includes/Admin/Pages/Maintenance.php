<?php
declare(strict_types=1);
/**
 * Maintenance / Operations Page
 *
 * Provides admin-only operational controls: queue recovery, DLQ management,
 * circuit resets, schedule resync, and basic hygiene actions.
 *
 * @package ContactIn\Admin\Pages
 */

namespace ContactInbox\Admin\Pages;

use ContactInbox\Core\Config;
use ContactInbox\Core\QueueManager;
use ContactInbox\Core\QueueTrigger;
use ContactInbox\Core\ProcessLock;
use ContactInbox\Core\CircuitBreaker;
use ContactInbox\Core\Logger;
use ContactInbox\Core\IntentClassifier;
use ContactInbox\Core\Repositories\MessageRepository;
use ContactInbox\Core\Repositories\QueueRepository;
use ContactInbox\Core\Repositories\GDPRRepository;
use ContactInbox\Core\Repositories\SalesforceAttachmentRepository;
use ContactInbox\Cron\CronJobs;
use ContactInbox\Integration\FreemiusIntegration;
use ContactInbox\Lifecycle;
use ContactInbox\Traits\Singleton;

// phpcs:disable WordPress.Security.NonceVerification, WordPress.Security.ValidatedSanitizedInput, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.PHP.DevelopmentFunctions.error_log_error_log, WordPress.DateTime.RestrictedFunctions.date_date, WordPress.WP.I18n.NonSingularStringLiteralDomain, WordPress.WP.I18n.MissingTranslatorsComment, WordPress.WP.I18n.UnorderedPlaceholdersText, WordPress.WP.AlternativeFunctions.unlink_unlink, WordPress.WP.AlternativeFunctions.file_system_operations_fwrite, WordPress.WP.AlternativeFunctions.file_system_operations_is_writable, WordPress.WP.AlternativeFunctions.file_system_operations_fclose, WordPress.WP.AlternativeFunctions.rename_rename, WordPress.WP.AlternativeFunctions.file_system_operations_fopen, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Maintenance {
	use Singleton;

	private MessageRepository $message_repo;
	private QueueRepository $queue_repo;
	private GDPRRepository $gdpr_repo;

	private function __construct() {
		$this->message_repo = new MessageRepository();
		$this->queue_repo   = new QueueRepository();
		$this->gdpr_repo    = new GDPRRepository();
		add_action( 'wp_ajax_contactin_maint_run_queue_email', array( $this, 'ajax_run_queue_email' ) );
		add_action( 'wp_ajax_contactin_maint_run_queue_crm', array( $this, 'ajax_run_queue_crm' ) );
		add_action( 'wp_ajax_contactin_maint_retry_dlq', array( $this, 'ajax_retry_dlq' ) );
		add_action( 'wp_ajax_contactin_maint_retry_email_dlq', array( $this, 'ajax_retry_email_dlq' ) );
		add_action( 'wp_ajax_contactin_maint_retry_crm_dlq', array( $this, 'ajax_retry_crm_dlq' ) );
		add_action( 'wp_ajax_contactin_maint_reset_circuits', array( $this, 'ajax_reset_circuits' ) );
		add_action( 'wp_ajax_contactin_maint_skip_email', array( $this, 'ajax_skip_email' ) );
		add_action( 'wp_ajax_contactin_maint_reschedule_email_queue', array( $this, 'ajax_reschedule_email_queue' ) );
		add_action( 'wp_ajax_contactin_maint_reschedule_crm_queue', array( $this, 'ajax_reschedule_crm_queue' ) );
		add_action( 'wp_ajax_contactin_maint_cleanup_orphaned_attachments', array( $this, 'ajax_cleanup_orphaned_attachments' ) );
		add_action( 'wp_ajax_contactin_maint_clean_stale_db_entries', array( $this, 'ajax_clean_stale_db_entries' ) );
		// New event-driven queue actions
		add_action( 'wp_ajax_contactin_maint_get_lock_status', array( $this, 'ajax_get_lock_status' ) );
		add_action( 'wp_ajax_contactin_maint_force_release_lock', array( $this, 'ajax_force_release_lock' ) );
		add_action( 'wp_ajax_contactin_maint_trigger_email_processor', array( $this, 'ajax_trigger_email_processor' ) );
		add_action( 'wp_ajax_contactin_maint_trigger_crm_processor', array( $this, 'ajax_trigger_crm_processor' ) );
		add_action( 'wp_ajax_contactin_maint_queue_progress', array( $this, 'ajax_queue_progress' ) );
		// Diagnostics
		add_action( 'wp_ajax_contactin_maint_get_cron_diagnostics', array( $this, 'ajax_get_cron_diagnostics' ) );
		// GDPR CRM cleanup
		add_action( 'wp_ajax_contactin_maint_gdpr_queue_delete', array( $this, 'ajax_gdpr_queue_delete' ) );
		add_action( 'wp_ajax_contactin_maint_gdpr_immediate_delete', array( $this, 'ajax_gdpr_immediate_delete' ) );
		add_action( 'wp_ajax_contactin_maint_gdpr_sync_crm_logs', array( $this, 'ajax_gdpr_sync_crm_logs' ) );
		// Intent classification maintenance
		add_action( 'wp_ajax_contactin_maint_reclassify_intent', array( $this, 'ajax_reclassify_intent' ) );
	}

	/**
	 * AJAX: Clean up orphaned attachment files and update analytics
	 */
	public function ajax_cleanup_orphaned_attachments(): void {
		$this->check_ajax( 'contactin_maint_cleanup_orphaned_attachments' );
		try {
			$service = \ContactInbox\Core\AttachmentCleanupService::instance();
			$scan    = $service->scan_orphaned_files();

			// Check if any orphaned files were found
			if ( empty( $scan['orphaned'] ) ) {
				wp_send_json_success(
					array(
						'deleted' => array(),
						'failed'  => array(),
						'stats'   => array(
							'count'     => 0,
							'size'      => 0,
							'last_scan' => time(),
						),
						'message' => __( 'No orphaned files found to clean up.', 'contactin' ),
					)
				);
				return;
			}

			$deleted = $service->delete_orphaned_files( $scan['orphaned'] );

			$deleted_count = count( $deleted['deleted'] );
			$failed_count  = count( $deleted['failed'] );

			// If some files failed to delete, log and inform user
			if ( $failed_count > 0 ) {
				Logger::warning(
					'Orphaned file cleanup had failures',
					array(
						'deleted'      => $deleted_count,
						'failed'       => $failed_count,
						'failed_files' => $deleted['failed'],
					)
				);
			}

			// After successful deletion, also clean stale DB entries
			if ( $deleted_count > 0 ) {
				$stale_cleaned = $service->clean_stale_db_entries();
				Logger::info( "Cleaned {$stale_cleaned} stale database entries after file deletion" );
			}

			// Reset analytics after deletion
			update_option( 'contactinbox_orphaned_attachments_stats', array() );

			// Provide detailed message
			if ( $deleted_count > 0 && $failed_count === 0 ) {
				$message = sprintf( __( 'Successfully deleted %d orphaned files.', 'contactin' ), $deleted_count );
			} elseif ( $deleted_count > 0 && $failed_count > 0 ) {
				$message = sprintf( __( 'Deleted %d files, but %d files could not be deleted (permission denied).', 'contactin' ), $deleted_count, $failed_count );
			} else {
				// All files failed to delete - likely a permissions issue
				$uploads_dir = WP_CONTENT_DIR . '/uploads/contactin-attachments/';
				$is_writable = is_writable( $uploads_dir );
				$perms       = substr( sprintf( '%o', fileperms( $uploads_dir ) ), -4 );

				Logger::error(
					'Orphaned file cleanup - permission denied for all files',
					array(
						'directory'       => $uploads_dir,
						'writable'        => $is_writable,
						'permissions'     => $perms,
						'files_attempted' => count( $deleted['failed'] ),
					)
				);

				wp_send_json_error(
					array(
						'message' => sprintf(
							__( 'Could not delete %d orphaned files. Check folder permissions. Directory: %s (Perms: %s)', 'contactin' ),
							count( $deleted['failed'] ),
							$uploads_dir,
							$perms
						),
					)
				);
				return;
			}

			wp_send_json_success(
				array(
					'deleted' => $deleted['deleted'],
					'failed'  => $deleted['failed'],
					'stats'   => array(
						'count'     => 0,
						'size'      => 0,
						'last_scan' => time(),
					),
					'message' => $message,
				)
			);
		} catch ( \Throwable $e ) {
			Logger::error( 'Orphaned file cleanup error', array( 'error' => $e->getMessage() ) );
			wp_send_json_error( array( 'message' => $e->getMessage() ) );
		}
	}

	/**
	 * AJAX: Clean stale database entries (files referenced in DB but no longer on disk)
	 */
	public function ajax_clean_stale_db_entries(): void {
		$this->check_ajax( 'contactin_maint_clean_stale_db_entries' );
		try {
			$service       = \ContactInbox\Core\AttachmentCleanupService::instance();
			$stale_before  = $service->get_stale_entries();
			$cleaned_count = $service->clean_stale_db_entries();
			$stale_after   = $service->get_stale_entries();

			Logger::info(
				'Cleaned stale database entries',
				array(
					'cleaned_count' => $cleaned_count,
					'stale_before'  => $stale_before['stale_count'],
					'stale_after'   => $stale_after['stale_count'],
				)
			);

			wp_send_json_success(
				array(
					'cleaned'   => $cleaned_count,
					'remaining' => $stale_after['stale_count'],
					'message'   => sprintf(
						__( 'Cleaned %d stale database entries. %d entries still referencing non-existent files.', 'contactin' ),
						$cleaned_count,
						$stale_after['stale_count']
					),
				)
			);
		} catch ( \Throwable $e ) {
			Logger::error( 'Stale DB entry cleanup error', array( 'error' => $e->getMessage() ) );
			wp_send_json_error( array( 'message' => $e->getMessage() ) );
		}
	}

	public static function render(): void {
		if ( ! current_user_can( Config::CAPABILITY ) ) {
			wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'contactin' ) );
		}

		$instance = self::instance();

		$cb_states = CircuitBreaker::get_stats();

		// Build circuit status display (compute in controller, not template)
		$circuit_state_labels = array(
			'closed'    => __( 'Available', 'contactin' ),
			'open'      => __( 'Tripped', 'contactin' ),
			'half_open' => __( 'Recovering', 'contactin' ),
		);
		$circuit_summary      = array();
		$circuit_badges       = array(); // For badge display
		foreach ( $cb_states as $service => $state ) {
			$raw_state         = strtolower( (string) ( $state['state'] ?? '' ) );
			$fallback_label    = $raw_state !== '' ? ucwords( str_replace( '_', ' ', $raw_state ) ) : __( 'Unknown', 'contactin' );
			$display_label     = $circuit_state_labels[ $raw_state ] ?? $fallback_label;
			$circuit_summary[] = sprintf( '%s: %s', strtoupper( $service ), $display_label );

			// Skip webhook badges (not typically displayed separately)
			if ( strtolower( $service ) !== 'webhook' && strtolower( $service ) !== 'webhooks' ) {
				$circuit_badges[ $service ] = array(
					'state'   => $raw_state,
					'label'   => $display_label,
					'tooltip' => sprintf( __( 'Circuit state: %s', 'contactin' ), $raw_state !== '' ? strtoupper( $raw_state ) : __( 'Unknown', 'contactin' ) ),
				);
			}
		}
		$circuit_status_line = implode( ' · ', $circuit_summary );

		// Maintenance stats: backlog is current; throughput is last 7 days.
		$stats_window_days  = 7;
		$stats_window_end   = current_time( 'Y-m-d' );
		$stats_window_start = date( 'Y-m-d', time() - ( ( $stats_window_days - 1 ) * DAY_IN_SECONDS ) );
		$stats_window_label = sprintf( __( 'Last %d days', 'contactin' ), $stats_window_days );

		// Current backlog state (no date filter)
		$message_stats_current = $instance->message_repo->get_status_counts();
		// Short-term throughput window
		$message_stats_window = $instance->message_repo->get_status_counts( $stats_window_start, $stats_window_end );

		$next_run_email           = wp_next_scheduled( Config::CRON_PROCESS_EMAIL );
		$next_run_crm             = wp_next_scheduled( Config::CRON_PROCESS_CRM );
		$email_schedule_slug      = get_option( 'contactin_queue_interval', 'contactin_fifteen_minutes' );
		$crm_schedule_slug        = get_option( 'contactin_crm_queue_interval', $email_schedule_slug );
		$email_reschedule_default = $instance->get_interval_seconds( $email_schedule_slug );
		$crm_reschedule_default   = $instance->get_interval_seconds( $crm_schedule_slug );
		$format_status            = static function ( int|bool|null $timestamp ): string {
			if ( ! $timestamp ) {
				return __( 'Not currently scheduled', 'contactin' );
			}

			return sprintf(
				__( 'Next run %1$s (%2$s from now)', 'contactin' ),
				date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $timestamp ),
				human_time_diff( time(), $timestamp )
			);
		};

		$next_run_email_text = $format_status( $next_run_email );
		$next_run_crm_text   = $format_status( $next_run_crm );

		// Fetch unified queue stats (includes attachment_retry, crm_delete, etc.)
		$unified_queue_stats = $instance->queue_repo->get_stats_by_type();

		// Extract counts by type from unified queue
		$attachment_retry_stats = $unified_queue_stats['attachment_retry'] ?? array(
			'pending'    => 0,
			'processing' => 0,
			'retry'      => 0,
			'completed'  => 0,
			'dlq'        => 0,
		);
		$crm_delete_stats       = $unified_queue_stats['crm_delete'] ?? array(
			'pending'    => 0,
			'processing' => 0,
			'retry'      => 0,
			'completed'  => 0,
			'dlq'        => 0,
		);
		$email_stats            = $unified_queue_stats['email'] ?? array(
			'pending'    => 0,
			'processing' => 0,
			'retry'      => 0,
			'completed'  => 0,
			'dlq'        => 0,
		);
		$crm_stats              = $unified_queue_stats['crm'] ?? array(
			'pending'    => 0,
			'processing' => 0,
			'retry'      => 0,
			'completed'  => 0,
			'dlq'        => 0,
		);

		// Email queue stats (unified queue)
		$email_queue_pending    = (int) ( $email_stats['pending'] ?? 0 );
		$email_queue_processing = (int) ( $email_stats['processing'] ?? 0 );
		$email_pending_total    = (int) $message_stats_current['admin_email_pending']
			+ (int) $message_stats_current['user_email_pending']
			+ $email_queue_pending
			+ $email_queue_processing;

		// CRM queue stats (unified queue)
		$crm_queue_pending    = (int) ( $crm_stats['pending'] ?? 0 );
		$crm_queue_processing = (int) ( $crm_stats['processing'] ?? 0 );
		$crm_queue_retry      = (int) ( $crm_stats['retry'] ?? 0 );
		$crm_queue_dlq        = (int) ( $crm_stats['dlq'] ?? 0 );
		$crm_pending_total    = (int) $message_stats_current['crm_pending']
			+ $crm_queue_pending
			+ $crm_queue_processing;

		// Merge legacy message stats with unified queue stats
		$queue_stats = array(
			'pending'    => $message_stats_current['admin_email_pending'] + $message_stats_current['user_email_pending'] + $message_stats_current['crm_pending']
						+ $email_stats['pending'] + $crm_stats['pending'] + $attachment_retry_stats['pending'] + $crm_delete_stats['pending'],
			'processing' => $email_stats['processing'] + $crm_stats['processing'] + $attachment_retry_stats['processing'] + $crm_delete_stats['processing'],
			'retry'      => $email_stats['retry'] + $crm_stats['retry'] + $attachment_retry_stats['retry'] + $crm_delete_stats['retry'],
			'completed'  => $message_stats_window['admin_email_sent'] + $message_stats_window['user_email_sent'] + $message_stats_window['crm_sent']
						+ $email_stats['completed'] + $crm_stats['completed'] + $attachment_retry_stats['completed'] + $crm_delete_stats['completed'],
			'dlq'        => $message_stats_current['admin_email_failed'] + $message_stats_current['user_email_failed'] + $message_stats_current['crm_failed']
						+ $email_stats['dlq'] + $crm_stats['dlq'] + $attachment_retry_stats['dlq'] + $crm_delete_stats['dlq'],
		);

		$queue_stats_by_type = array(
			'admin_email'      => array(
				'pending' => $message_stats_current['admin_email_pending'],
				'sent'    => $message_stats_window['admin_email_sent'],
				'failed'  => $message_stats_current['admin_email_failed'],
			),
			'user_email'       => array(
				'pending' => $message_stats_current['user_email_pending'],
				'sent'    => $message_stats_window['user_email_sent'],
				'failed'  => $message_stats_current['user_email_failed'],
			),
			'crm'              => array(
				'pending' => $message_stats_current['crm_pending'],
				'sent'    => $message_stats_window['crm_sent'],
				'failed'  => $message_stats_current['crm_failed'],
			),
			'attachment_retry' => array(
				'pending'    => $attachment_retry_stats['pending'],
				'processing' => $attachment_retry_stats['processing'],
				'retry'      => $attachment_retry_stats['retry'],
				'completed'  => $attachment_retry_stats['completed'],
				'dlq'        => $attachment_retry_stats['dlq'],
			),
			'crm_delete'       => array(
				'pending'    => $crm_delete_stats['pending'],
				'processing' => $crm_delete_stats['processing'],
				'retry'      => $crm_delete_stats['retry'],
				'completed'  => $crm_delete_stats['completed'],
				'dlq'        => $crm_delete_stats['dlq'],
			),
		);

		// Attachment sync stats (SF attachment logs)
		$attachment_repo  = new SalesforceAttachmentRepository();
		$attachment_stats = $attachment_repo->get_status_counts( $stats_window_start, $stats_window_end );

		// Attachment retry queue stats (unified queue)
		$attachment_retry_pending = (int) ( $attachment_retry_stats['pending'] ?? 0 )
			+ (int) ( $attachment_retry_stats['processing'] ?? 0 );
		$attachment_retry_retry   = (int) ( $attachment_retry_stats['retry'] ?? 0 );
		$attachment_retry_dlq     = (int) ( $attachment_retry_stats['dlq'] ?? 0 );

		// File pending uses unified queue only (SF log is just for tracking/history)
		// Each attachment is queued once in unified queue when created, avoiding double counting
		$file_pending = $attachment_retry_pending + $attachment_retry_retry;
		$file_synced  = (int) ( $attachment_stats['delivered'] ?? 0 );
		$file_failed  = (int) ( $attachment_stats['failed'] ?? 0 );

		// Email queue stats (retry/DLQ from unified queue)
		$email_retry           = (int) ( $email_stats['retry'] ?? 0 );
		$email_dlq             = (int) ( $email_stats['dlq'] ?? 0 );
		$email_completed_total = (int) $message_stats_window['admin_email_sent']
			+ (int) $message_stats_window['user_email_sent']
			+ (int) ( $email_stats['completed'] ?? 0 );
		$crm_completed_total   = (int) $message_stats_window['crm_sent']
			+ (int) ( $crm_stats['completed'] ?? 0 );
		$crm_delete_completed  = (int) ( $crm_delete_stats['completed'] ?? 0 );

		// CRM deletion stats (queue + GDPR log)
		$delete_pending   = (int) ( $crm_delete_stats['pending'] ?? 0 )
			+ (int) ( $crm_delete_stats['processing'] ?? 0 );
		$delete_retry     = (int) ( $crm_delete_stats['retry'] ?? 0 );
		$delete_completed = (int) ( $crm_delete_stats['completed'] ?? 0 );
		$delete_dlq       = (int) ( $crm_delete_stats['dlq'] ?? 0 );

		$gdpr_stats_current = $instance->gdpr_repo->get_deletion_stats();
		$gdpr_stats_window  = $instance->gdpr_repo->get_deletion_stats( $stats_window_start, $stats_window_end );
		$crm_deleted        = (int) ( $gdpr_stats_window['crm_deleted'] ?? 0 );
		$synced_count       = (int) ( $gdpr_stats_current['ready_for_deletion'] ?? 0 );

		$failed_messages = $instance->message_repo->get_failed( 100 );

		// Event-driven queue status
		$email_processor_status = QueueTrigger::get_email_processor_status();
		$crm_processor_status   = QueueTrigger::get_crm_processor_status();
		$all_locks              = ProcessLock::get_all_locks();

		// Intent Classification stats (compute in controller, pass to template)
		$unclassified_count = $instance->message_repo->count_unclassified();
		$intent_stats       = $instance->message_repo->get_intent_stats();
		$intent_categories  = IntentClassifier::get_categories();

		// Pre-compute category colors for template rendering
		$intent_colors_map = array();
		foreach ( array_keys( $intent_categories ) as $category ) {
			$intent_colors_map[ $category ] = IntentClassifier::get_category_color( $category );
		}

		// Attachment cleanup stats (scan once, pass to template)
		$attachment_cleanup_service = \ContactInbox\Core\AttachmentCleanupService::instance();
		$attachment_scan            = $attachment_cleanup_service->scan_orphaned_files();
		$attachment_stale           = $attachment_cleanup_service->get_stale_entries();

		// Calculate orphaned file analytics
		$uploads_dir = WP_CONTENT_DIR . '/uploads/contactin-attachments/';
		$orph_count  = count( $attachment_scan['orphaned'] ?? array() );
		$orph_size   = 0;
		foreach ( $attachment_scan['orphaned'] ?? array() as $file ) {
			$path = $uploads_dir . $file;
			if ( is_file( $path ) ) {
				$orph_size += filesize( $path );
			}
		}

		$temp_orphaned_count = count( $attachment_scan['temp_orphaned'] ?? array() );
		$orph_size_mb        = $orph_size > 0 ? $orph_size / 1048576 : 0;
		$last_scan_timestamp = time();
		$last_scan           = date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $last_scan_timestamp );

		$disk_file_count = 0;
		if ( is_dir( $uploads_dir ) ) {
			$disk_files      = array_diff( scandir( $uploads_dir ), array( '.', '..' ) );
			$disk_file_count = count(
				array_filter(
					$disk_files,
					function ( $f ) use ( $uploads_dir ) {
						return is_file( $uploads_dir . $f );
					}
				)
			);
		}

		// Last CRM retry operation stats
		$last_crm_retry  = get_option( 'contactinbox_last_crm_retry', null );
		$last_retry_text = '';
		if ( $last_crm_retry && isset( $last_crm_retry['timestamp'] ) ) {
			$time_ago = human_time_diff( $last_crm_retry['timestamp'], time() );
			$attempts = (int) ( $last_crm_retry['attempts'] ?? 0 );
			$before   = $last_crm_retry['before'] ?? array();
			$after    = $last_crm_retry['after'] ?? array();

			if ( $attempts > 0 ) {
				$crm_resolved    = max( 0, ( $before['crm_failures'] ?? 0 ) - ( $after['crm_failures'] ?? 0 ) );
				$file_resolved   = max( 0, ( $before['file_failures'] ?? 0 ) - ( $after['file_failures'] ?? 0 ) );
				$delete_resolved = max( 0, ( $before['delete_failures'] ?? 0 ) - ( $after['delete_failures'] ?? 0 ) );
				$total_resolved  = $crm_resolved + $file_resolved + $delete_resolved;

				if ( $total_resolved > 0 ) {
					$last_retry_text = sprintf(
						__( 'Last retry: %s ago (%d resolved)', 'contactin' ),
						$time_ago,
						$total_resolved
					);
				} else {
					$last_retry_text = sprintf(
						__( 'Last retry: %s ago (items failed again)', 'contactin' ),
						$time_ago
					);
				}
			}
		}

		$template = CONTACTINBOX_PATH . Config::TEMPLATE_ADMIN . 'maintenance-page.php';
		if ( file_exists( $template ) ) {
			include $template;
		} else {
			echo '<div class="notice notice-error"><p>' . esc_html__( 'Maintenance template not found.', 'contactin' ) . '</p></div>';
		}
	}

	public function ajax_run_queue_email(): void {
		$this->check_ajax( 'contactin_maint_run_queue_email' );
		try {
			if ( ProcessLock::is_locked( 'email' ) ) {
				$duration = ProcessLock::get_lock_duration( 'email' );
				wp_send_json_error(
					sprintf(
						__( 'Email processor is already running (for %d seconds). Wait for it to complete or force release if stuck.', 'contactin' ),
						$duration
					)
				);
			}

			$before             = $this->message_repo->get_status_counts();
			$before_queue       = $this->queue_repo->get_stats_by_type();
			$before_email_stats = $before_queue['email'] ?? array(
				'pending'    => 0,
				'processing' => 0,
				'retry'      => 0,
				'completed'  => 0,
				'dlq'        => 0,
			);

			$pending_before = (int) ( $before['admin_email_pending'] ?? 0 )
				+ (int) ( $before['user_email_pending'] ?? 0 )
				+ (int) ( $before_email_stats['pending'] ?? 0 )
				+ (int) ( $before_email_stats['processing'] ?? 0 );

			if ( $pending_before === 0 ) {
				wp_send_json_success(
					array(
						'message'           => __( 'No pending email items to process.', 'contactin' ),
						'processed'         => 0,
						'pending_remaining' => 0,
					)
				);
			}

			// Reset stuck processing items (older than 10 minutes) back to pending so they can be reprocessed
			$reset_count = \ContactInbox\Core\QueueManager::reset_processing( 10 );
			if ( $reset_count > 0 ) {
				Logger::notice(
					'Maintenance: Reset stuck email processing items',
					array(
						'items_reset' => $reset_count,
					)
				);
			}

			// Process immediately
			CronJobs::instance()->process_email_queue();

			// Get stats after processing
			$after             = $this->message_repo->get_status_counts();
			$after_queue       = $this->queue_repo->get_stats_by_type();
			$after_email_stats = $after_queue['email'] ?? array(
				'pending'    => 0,
				'processing' => 0,
				'retry'      => 0,
				'completed'  => 0,
				'dlq'        => 0,
			);

			$pending_after = (int) ( $after['admin_email_pending'] ?? 0 )
				+ (int) ( $after['user_email_pending'] ?? 0 )
				+ (int) ( $after_email_stats['pending'] ?? 0 )
				+ (int) ( $after_email_stats['processing'] ?? 0 );

			$processed = $pending_before - $pending_after;

			Logger::notice(
				'Maintenance: manual email queue run completed',
				array(
					'pending_before' => $pending_before,
					'pending_after'  => $pending_after,
					'processed'      => $processed,
				)
			);

			wp_send_json_success(
				array(
					'message'        => sprintf(
						__( 'Processed %1$d email(s). %2$d remaining.', 'contactin' ),
						$processed,
						$pending_after
					),
					'processed'      => $processed,
					'pending_before' => $pending_before,
					'pending_after'  => $pending_after,
				)
			);
		} catch ( \Throwable $e ) {
			Logger::error( 'Maintenance run queue failed', array( 'error' => $e->getMessage() ) );
			wp_send_json_error( $e->getMessage() );
		}
	}

	public function ajax_run_queue_crm(): void {
		$this->check_ajax( 'contactin_maint_run_queue_crm' );
		try {
			if ( ProcessLock::is_locked( 'crm' ) ) {
				$duration = ProcessLock::get_lock_duration( 'crm' );
				wp_send_json_error(
					sprintf(
						__( 'CRM processor is already running (for %d seconds). Wait for it to complete or force release if stuck.', 'contactin' ),
						$duration
					)
				);
			}

			$before                  = $this->message_repo->get_status_counts();
			$queue_before            = $this->queue_repo->get_stats_by_type();
			$crm_before_stats        = $queue_before['crm'] ?? array(
				'pending'    => 0,
				'processing' => 0,
				'retry'      => 0,
				'completed'  => 0,
				'dlq'        => 0,
			);
			$crm_delete_before_stats = $queue_before['crm_delete'] ?? array(
				'pending'    => 0,
				'processing' => 0,
				'retry'      => 0,
				'completed'  => 0,
				'dlq'        => 0,
			);
			$attachment_before_stats = $queue_before['attachment_retry'] ?? array(
				'pending'    => 0,
				'processing' => 0,
				'retry'      => 0,
				'completed'  => 0,
				'dlq'        => 0,
			);

			$normalized_pending = $this->queue_repo->normalize_pending_next_attempt( 'crm_delete' );
			if ( $normalized_pending > 0 ) {
				Logger::info(
					'Maintenance: normalized CRM delete pending next_attempt values',
					array(
						'count' => $normalized_pending,
					)
				);
			}

			$message_before = (int) ( $before['crm_pending'] ?? 0 )
				+ (int) ( $crm_before_stats['pending'] ?? 0 )
				+ (int) ( $crm_before_stats['processing'] ?? 0 )
				+ (int) ( $crm_before_stats['retry'] ?? 0 );

			$delete_before = (int) ( $crm_delete_before_stats['pending'] ?? 0 )
				+ (int) ( $crm_delete_before_stats['processing'] ?? 0 )
				+ (int) ( $crm_delete_before_stats['retry'] ?? 0 );

			$attachment_before = (int) ( $attachment_before_stats['pending'] ?? 0 )
				+ (int) ( $attachment_before_stats['processing'] ?? 0 )
				+ (int) ( $attachment_before_stats['retry'] ?? 0 );

			$pending_before = $message_before + $delete_before;

			$queue_has_pending = QueueManager::has_pending_type( 'crm' )
				|| QueueManager::has_pending_type( 'crm_delete' )
				|| QueueManager::has_pending_type( 'attachment_retry' );

			if ( $pending_before === 0 && $attachment_before === 0 && ! $queue_has_pending ) {
				wp_send_json_success(
					array(
						'message'               => __( 'No pending CRM items to process.', 'contactin' ),
						'records_processed'     => 0,
						'attachments_processed' => 0,
					)
				);
			}

			// Reset stuck processing items (older than 10 minutes) back to pending so they can be reprocessed
			$reset_count = \ContactInbox\Core\QueueManager::reset_processing( 10 );
			if ( $reset_count > 0 ) {
				Logger::notice(
					'Maintenance: Reset stuck CRM processing items',
					array(
						'items_reset' => $reset_count,
					)
				);
			}

			// Process immediately
			CronJobs::instance()->process_crm_queue();

			// Get stats after processing
			$after                  = $this->message_repo->get_status_counts();
			$queue_after            = $this->queue_repo->get_stats_by_type();
			$crm_after_stats        = $queue_after['crm'] ?? array(
				'pending'    => 0,
				'processing' => 0,
				'retry'      => 0,
				'completed'  => 0,
				'dlq'        => 0,
			);
			$crm_delete_after_stats = $queue_after['crm_delete'] ?? array(
				'pending'    => 0,
				'processing' => 0,
				'retry'      => 0,
				'completed'  => 0,
				'dlq'        => 0,
			);
			$attachment_after_stats = $queue_after['attachment_retry'] ?? array(
				'pending'    => 0,
				'processing' => 0,
				'retry'      => 0,
				'completed'  => 0,
				'dlq'        => 0,
			);

			$message_after = (int) ( $after['crm_pending'] ?? 0 )
				+ (int) ( $crm_after_stats['pending'] ?? 0 )
				+ (int) ( $crm_after_stats['processing'] ?? 0 )
				+ (int) ( $crm_after_stats['retry'] ?? 0 );

			$delete_after = (int) ( $crm_delete_after_stats['pending'] ?? 0 )
				+ (int) ( $crm_delete_after_stats['processing'] ?? 0 )
				+ (int) ( $crm_delete_after_stats['retry'] ?? 0 );

			$attachment_after = (int) ( $attachment_after_stats['pending'] ?? 0 )
				+ (int) ( $attachment_after_stats['processing'] ?? 0 )
				+ (int) ( $attachment_after_stats['retry'] ?? 0 );

			$pending_after = $message_after + $delete_after;

			$messages_processed = $message_before - $message_after;
			$deletes_processed  = $delete_before - $delete_after;
			$files_processed    = $attachment_before - $attachment_after;

			$records_processed     = $messages_processed + $deletes_processed;
			$attachments_processed = $files_processed;

			Logger::notice(
				'Maintenance: manual CRM queue run completed',
				array(
					'records_before'        => $pending_before,
					'records_after'         => $pending_after,
					'records_processed'     => $records_processed,
					'attachments_before'    => $attachment_before,
					'attachments_after'     => $attachment_after,
					'attachments_processed' => $attachments_processed,
					'messages_before'       => $message_before,
					'messages_after'        => $message_after,
					'messages_processed'    => $messages_processed,
					'deletes_before'        => $delete_before,
					'deletes_after'         => $delete_after,
					'deletes_processed'     => $deletes_processed,
					'files_before'          => $attachment_before,
					'files_after'           => $attachment_after,
					'files_processed'       => $files_processed,
				)
			);

			wp_send_json_success(
				array(
					'message'               => sprintf(
						__( 'Processed — message: %1$d, file: %2$d, delete: %3$d. Remaining — message: %4$d, file: %5$d, delete: %6$d.', 'contactin' ),
						$messages_processed,
						$files_processed,
						$deletes_processed,
						$message_after,
						$attachment_after,
						$delete_after
					),
					'records_processed'     => $records_processed,
					'attachments_processed' => $attachments_processed,
					'messages_processed'    => $messages_processed,
					'files_processed'       => $files_processed,
					'deletes_processed'     => $deletes_processed,
					'records_before'        => $pending_before,
					'records_after'         => $pending_after,
					'attachments_before'    => $attachment_before,
					'attachments_after'     => $attachment_after,
					'messages_before'       => $message_before,
					'messages_after'        => $message_after,
					'files_before'          => $attachment_before,
					'files_after'           => $attachment_after,
					'deletes_before'        => $delete_before,
					'deletes_after'         => $delete_after,
				)
			);
		} catch ( Exception $e ) {
			Logger::alert( 'Maintenance: Manual CRM queue run failed', array( 'error' => $e->getMessage() ) );
			wp_send_json_error( array( 'message' => $e->getMessage() ) );
		}
	}

	public function ajax_retry_email_dlq(): void {
		$this->check_ajax( 'contactin_maint_retry_email_dlq' );
		try {
			// Requeue failed items
			$retries       = $this->retry_failed_channels( array( 'admin_email', 'user_email' ), 200 );
			$admin_retried = $retries['admin_email'] ?? 0;
			$user_retried  = $retries['user_email'] ?? 0;
			$total         = $admin_retried + $user_retried;

			$queue_failed  = $this->queue_repo->get_failed_by_type( 'email', 200 );
			$queue_retried = 0;
			foreach ( $queue_failed as $item ) {
				if ( QueueManager::mark_retry( (int) $item['id'] ) ) {
					++$queue_retried;
				}
			}

			$dlq_ids     = $this->queue_repo->get_dlq_ids_by_type( 'email', 200 );
			$dlq_retried = 0;
			foreach ( $dlq_ids as $dlq_id ) {
				$result = QueueManager::retry_dlq_item( (int) $dlq_id );
				if ( ! is_wp_error( $result ) ) {
					++$dlq_retried;
				}
			}

			$total_retried = $total + $queue_retried + $dlq_retried;

			Logger::notice(
				'Maintenance: retried failed email notifications',
				array(
					'admin'         => $admin_retried,
					'user'          => $user_retried,
					'queue_retried' => $queue_retried,
					'dlq_retried'   => $dlq_retried,
					'total_retried' => $total_retried,
				)
			);

			// Trigger processing immediately if items were retried
			if ( $total_retried > 0 ) {
				CronJobs::instance()->process_email_queue();
			}

			wp_send_json_success(
				array(
					'message' => sprintf(
						__( 'Retried %1$d failed email notification(s) (legacy: %2$d, queue: %3$d, dlq: %4$d). Now processing...', 'contactin' ),
						$total_retried,
						$total,
						$queue_retried,
						$dlq_retried
					),
					'retried' => $total_retried,
				)
			);
		} catch ( \Throwable $e ) {
			Logger::error( 'Maintenance retry email DLQ failed', array( 'error' => $e->getMessage() ) );
			wp_send_json_error( $e->getMessage() );
		}
	}

	public function ajax_retry_crm_dlq(): void {
		$this->check_ajax( 'contactin_maint_retry_crm_dlq' );
		try {
			// Capture stats BEFORE retry
			$stats_before      = $this->queue_repo->get_stats_by_type();
			$crm_before        = $stats_before['crm'] ?? array();
			$attachment_before = $stats_before['attachment_retry'] ?? array();
			$delete_before     = $stats_before['crm_delete'] ?? array();

			// Requeue failed items
			$failed               = $this->message_repo->get_failed( 200 );
			$crm_retried          = 0;
			$file_retried         = 0;
			$queue_crm_retried    = 0;
			$queue_file_retried   = 0;
			$queue_delete_retried = 0;
			$dlq_crm_retried      = 0;
			$dlq_file_retried     = 0;
			$dlq_delete_retried   = 0;

			// Retry failed CRM record syncs - push them into the queue for proper processing
			foreach ( $failed as $message ) {
				$message_id = (int) ( $message->id ?? 0 );
				if ( $message_id <= 0 ) {
					continue;
				}

				if ( ( $message->crm_status ?? null ) === Config::CRM_FAILED ) {
					$queued = \ContactInbox\Core\CRMQueueService::queue_message_sync( $message_id, 2, true );
					if ( $queued ) {
						++$crm_retried;
					}
				}
			}

			// Retry failed attachment uploads from queue
			$failed_attachments = $this->queue_repo->get_failed_by_type( 'attachment_retry', 100 );

			foreach ( $failed_attachments as $item ) {
				if ( QueueManager::mark_retry( (int) $item['id'] ) ) {
					++$queue_file_retried;
				}
			}

			// Retry failed CRM queue items
			$failed_crm_queue = $this->queue_repo->get_failed_by_type( 'crm', 100 );
			foreach ( $failed_crm_queue as $item ) {
				if ( QueueManager::mark_retry( (int) $item['id'] ) ) {
					++$queue_crm_retried;
				}
			}

			// Retry failed CRM deletion queue items
			$failed_delete_queue = $this->queue_repo->get_failed_by_type( 'crm_delete', 100 );
			foreach ( $failed_delete_queue as $item ) {
				if ( QueueManager::mark_retry( (int) $item['id'] ) ) {
					++$queue_delete_retried;
				}
			}

			// Retry DLQ items for CRM and attachment retries
			$crm_dlq_ids = $this->queue_repo->get_dlq_ids_by_type( 'crm', 100 );
			foreach ( $crm_dlq_ids as $dlq_id ) {
				$result = QueueManager::retry_dlq_item( (int) $dlq_id );
				if ( ! is_wp_error( $result ) ) {
					++$dlq_crm_retried;
				}
			}

			$attachment_dlq_ids = $this->queue_repo->get_dlq_ids_by_type( 'attachment_retry', 100 );
			foreach ( $attachment_dlq_ids as $dlq_id ) {
				$result = QueueManager::retry_dlq_item( (int) $dlq_id );
				if ( ! is_wp_error( $result ) ) {
					++$dlq_file_retried;
				}
			}

			$delete_dlq_ids = $this->queue_repo->get_dlq_ids_by_type( 'crm_delete', 100 );
			foreach ( $delete_dlq_ids as $dlq_id ) {
				$result = QueueManager::retry_dlq_item( (int) $dlq_id );
				if ( ! is_wp_error( $result ) ) {
					++$dlq_delete_retried;
				}
			}

			$total_records_retried = $crm_retried + $queue_crm_retried + $dlq_crm_retried;
			$total_files_retried   = $queue_file_retried + $dlq_file_retried;
			$total_deletes_retried = $queue_delete_retried + $dlq_delete_retried;
			$total_retried         = $total_records_retried + $total_files_retried + $total_deletes_retried;

			Logger::notice(
				'Maintenance: retried failed CRM syncs',
				array(
					'records'       => $crm_retried,
					'queue_records' => $queue_crm_retried,
					'queue_files'   => $queue_file_retried,
					'queue_deletes' => $queue_delete_retried,
					'dlq_records'   => $dlq_crm_retried,
					'dlq_files'     => $dlq_file_retried,
					'dlq_deletes'   => $dlq_delete_retried,
					'total_retried' => $total_retried,
				)
			);

			// Trigger processing immediately if items were retried
			if ( $total_retried > 0 ) {
				CronJobs::instance()->process_crm_queue();
			}

			// Capture stats AFTER processing
			$stats_after      = $this->queue_repo->get_stats_by_type();
			$crm_after        = $stats_after['crm'] ?? array();
			$attachment_after = $stats_after['attachment_retry'] ?? array();
			$delete_after     = $stats_after['crm_delete'] ?? array();

			// Compute current failures (retry + dlq)
			$crm_failures_after    = ( $crm_after['retry'] ?? 0 ) + ( $crm_after['dlq'] ?? 0 );
			$file_failures_after   = ( $attachment_after['retry'] ?? 0 ) + ( $attachment_after['dlq'] ?? 0 );
			$delete_failures_after = ( $delete_after['retry'] ?? 0 ) + ( $delete_after['dlq'] ?? 0 );

			$crm_failures_before    = ( $crm_before['retry'] ?? 0 ) + ( $crm_before['dlq'] ?? 0 );
			$file_failures_before   = ( $attachment_before['retry'] ?? 0 ) + ( $attachment_before['dlq'] ?? 0 );
			$delete_failures_before = ( $delete_before['retry'] ?? 0 ) + ( $delete_before['dlq'] ?? 0 );

			// Store last retry timestamp
			update_option(
				'contactinbox_last_crm_retry',
				array(
					'timestamp' => time(),
					'attempts'  => $total_retried,
					'before'    => array(
						'crm_failures'    => $crm_failures_before,
						'file_failures'   => $file_failures_before,
						'delete_failures' => $delete_failures_before,
					),
					'after'     => array(
						'crm_failures'    => $crm_failures_after,
						'file_failures'   => $file_failures_after,
						'delete_failures' => $delete_failures_after,
					),
				)
			);

			$message = sprintf(
				__( 'Retried %1$d failed item(s): %2$d record(s), %3$d file(s), %4$d deletion(s).', 'contactin' ),
				$total_retried,
				$total_records_retried,
				$total_files_retried,
				$total_deletes_retried
			);

			// Add before/after comparison
			if ( $total_retried > 0 ) {
				$crm_change    = $crm_failures_before - $crm_failures_after;
				$file_change   = $file_failures_before - $file_failures_after;
				$delete_change = $delete_failures_before - $delete_failures_after;

				$message .= sprintf(
					__( ' Status: Records %1$s, Files %2$s, Deletions %3$s.', 'contactin' ),
					$crm_failures_after === 0 ? '✓ Cleared' : ( $crm_change > 0 ? '↓' . $crm_change : ( $crm_change < 0 ? '↑' . abs( $crm_change ) : 'No change' ) ),
					$file_failures_after === 0 ? '✓ Cleared' : ( $file_change > 0 ? '↓' . $file_change : ( $file_change < 0 ? '↑' . abs( $file_change ) : 'No change' ) ),
					$delete_failures_after === 0 ? '✓ Cleared' : ( $delete_change > 0 ? '↓' . $delete_change : ( $delete_change < 0 ? '↑' . abs( $delete_change ) : 'No change' ) )
				);
			}

			wp_send_json_success(
				array(
					'message'         => $message,
					'retried'         => $total_retried,
					'records_retried' => $total_records_retried,
					'files_retried'   => $total_files_retried,
					'deletes_retried' => $total_deletes_retried,
					'stats'           => array(
						'before' => array(
							'crm'       => $crm_failures_before,
							'files'     => $file_failures_before,
							'deletions' => $delete_failures_before,
						),
						'after'  => array(
							'crm'       => $crm_failures_after,
							'files'     => $file_failures_after,
							'deletions' => $delete_failures_after,
						),
					),
				)
			);
		} catch ( \Throwable $e ) {
			Logger::error( 'Maintenance retry CRM DLQ failed', array( 'error' => $e->getMessage() ) );
			wp_send_json_error( $e->getMessage() );
		}
	}

	public function ajax_reset_circuits(): void {
		$this->check_ajax( 'contactin_maint_reset_circuits' );
		try {
			$services = array( 'smtp', 'crm' );
			foreach ( $services as $service ) {
				CircuitBreaker::reset( $service );
			}
			Logger::notice( 'Maintenance: reset circuit breakers', array( 'services' => $services ) );
			wp_send_json_success( array( 'message' => sprintf( __( 'Reset circuit breakers for: %s', 'contactin' ), strtoupper( implode( ', ', $services ) ) ) ) );
		} catch ( \Throwable $e ) {
			Logger::error( 'Maintenance reset circuits failed', array( 'error' => $e->getMessage() ) );
			wp_send_json_error( $e->getMessage() );
		}
	}

	public function ajax_skip_email(): void {
		$this->check_ajax( 'contactin_maint_skip_email' );
		try {
			$result = QueueManager::skip_email_items_if_smtp_disabled();
			Logger::notice( 'Maintenance: skipped email items (SMTP disabled)', $result );
			$legacy_skipped = ( $result['skipped_admin'] ?? 0 ) + ( $result['skipped_user'] ?? 0 );
			$queue_skipped  = (int) ( $result['skipped_queue'] ?? 0 );
			$dlq_cleared    = (int) ( $result['dlq_cleared'] ?? 0 );
			$total_skipped  = $legacy_skipped + $queue_skipped;

			wp_send_json_success(
				array(
					'message'     => sprintf(
						__( 'Skipped %1$d email items (legacy admin: %2$d, legacy user: %3$d, queue: %4$d). DLQ cleared: %5$d.', 'contactin' ),
						$total_skipped,
						$result['skipped_admin'] ?? 0,
						$result['skipped_user'] ?? 0,
						$queue_skipped,
						$dlq_cleared
					),
					'updated'     => $result['updated'] ?? $total_skipped,
					'dlq_cleared' => $dlq_cleared,
				)
			);
		} catch ( \Throwable $e ) {
			Logger::error( 'Maintenance skip email failed', array( 'error' => $e->getMessage() ) );
			wp_send_json_error( $e->getMessage() );
		}
	}

	public function ajax_reschedule_email_queue(): void {
		$this->check_ajax( 'contactin_maint_reschedule_email_queue' );
		try {
			// Get stored interval configuration
			$interval      = get_option( 'contactin_queue_interval', 'contactin_fifteen_minutes' );
			$default_delay = $this->get_interval_seconds( $interval );

			// Check if user provided custom delay
			$custom_delay_provided = isset( $_POST['delay_seconds'] ) && ! empty( $_POST['delay_seconds'] );
			$delay                 = $this->validate_delay_seconds( $default_delay, 60, 3600 );

			Lifecycle::reschedule_cron_jobs(
				array( Config::CRON_PROCESS_EMAIL ),
				array( Config::CRON_PROCESS_EMAIL => $delay )
			);

			Logger::notice(
				'Maintenance: rescheduled email queue',
				array(
					'interval'                 => $interval,
					'default_interval_seconds' => $default_delay,
					'actual_delay'             => $delay,
					'custom_delay_provided'    => $custom_delay_provided,
				)
			);

			// Build clear message about what was rescheduled
			$interval_label = $this->format_interval_label( $interval );
			if ( $custom_delay_provided && $delay !== $default_delay ) {
				// User provided custom delay
				$msg = sprintf(
					__( 'Email queue rescheduled. Next run in %d seconds (recurring every %s).', 'contactin' ),
					$delay,
					$interval_label
				);
			} else {
				// Using default interval
				$msg = sprintf(
					__( 'Email queue rescheduled to %s. Next run in %d seconds.', 'contactin' ),
					$interval_label,
					$default_delay
				);
			}

			wp_send_json_success(
				array(
					'message'       => $msg,
					'delay_seconds' => $delay,
					'interval'      => $interval,
				)
			);
		} catch ( \Throwable $e ) {
			Logger::error( 'Maintenance reschedule email queue failed', array( 'error' => $e->getMessage() ) );
			wp_send_json_error( $e->getMessage() );
		}
	}

	public function ajax_reschedule_crm_queue(): void {
		$this->check_ajax( 'contactin_maint_reschedule_crm_queue' );
		try {
			// Get stored interval configuration
			$interval      = get_option( 'contactin_crm_queue_interval', get_option( 'contactin_queue_interval', 'contactin_fifteen_minutes' ) );
			$default_delay = $this->get_interval_seconds( $interval );

			// Check if user provided custom delay
			$custom_delay_provided = isset( $_POST['delay_seconds'] ) && ! empty( $_POST['delay_seconds'] );
			$delay                 = $this->validate_delay_seconds( $default_delay, 60, 3600 );

			Lifecycle::reschedule_cron_jobs(
				array( Config::CRON_PROCESS_CRM ),
				array( Config::CRON_PROCESS_CRM => $delay )
			);

			Logger::notice(
				'Maintenance: rescheduled CRM queue',
				array(
					'interval'                 => $interval,
					'default_interval_seconds' => $default_delay,
					'actual_delay'             => $delay,
					'custom_delay_provided'    => $custom_delay_provided,
				)
			);

			// Build clear message about what was rescheduled
			$interval_label = $this->format_interval_label( $interval );
			if ( $custom_delay_provided && $delay !== $default_delay ) {
				// User provided custom delay
				$msg = sprintf(
					__( 'CRM queue rescheduled. Next run in %d seconds (recurring every %s).', 'contactin' ),
					$delay,
					$interval_label
				);
			} else {
				// Using default interval
				$msg = sprintf(
					__( 'CRM queue rescheduled to %s. Next run in %d seconds.', 'contactin' ),
					$interval_label,
					$default_delay
				);
			}

			wp_send_json_success(
				array(
					'message'       => $msg,
					'delay_seconds' => $delay,
					'interval'      => $interval,
				)
			);
		} catch ( \Throwable $e ) {
			Logger::error( 'Maintenance reschedule CRM queue failed', array( 'error' => $e->getMessage() ) );
			wp_send_json_error( $e->getMessage() );
		}
	}

	private function get_interval_seconds( string $schedule_slug ): int {
		$schedules = wp_get_schedules();
		if ( isset( $schedules[ $schedule_slug ]['interval'] ) ) {
			return (int) $schedules[ $schedule_slug ]['interval'];
		}

		return 120;
	}

	/**
	 * Format interval label from schedule slug.
	 * Converts 'contactin_fifteen_minutes' to 'Fifteen Minutes'
	 *
	 * @param string $schedule_slug The schedule slug to format
	 * @return string Formatted label
	 */
	private function format_interval_label( string $schedule_slug ): string {
		$label = str_replace( array( 'contactin_', '_' ), array( '', ' ' ), $schedule_slug );
		return ucwords( $label );
	}

	/**
	 * AJAX: Get current lock status
	 */
	public function ajax_get_lock_status(): void {
		$this->check_ajax( 'contactin_maint_get_lock_status' );

		try {
			$email_status = QueueTrigger::get_email_processor_status();
			$crm_status   = QueueTrigger::get_crm_processor_status();
			$all_locks    = ProcessLock::get_all_locks();

			wp_send_json_success(
				array(
					'email' => $email_status,
					'crm'   => $crm_status,
					'locks' => $all_locks,
				)
			);
		} catch ( \Throwable $e ) {
			Logger::error( 'Maintenance get lock status failed', array( 'error' => $e->getMessage() ) );
			wp_send_json_error( $e->getMessage() );
		}
	}

	/**
	 * AJAX: Force release a process lock
	 */
	public function ajax_force_release_lock(): void {
		$this->check_ajax( 'contactin_maint_force_release_lock' );

		try {
			// Validate and sanitize process parameter
			if ( ! isset( $_POST['process'] ) || empty( $_POST['process'] ) ) {
				wp_send_json_error( __( 'Process parameter is required.', 'contactin' ) );
				return;
			}

			$process = sanitize_text_field( wp_unslash( $_POST['process'] ) );

			// Whitelist validation
			if ( ! in_array( $process, array( 'email', 'crm' ), true ) ) {
				wp_send_json_error(
					sprintf(
						__( 'Invalid process type "%s". Must be "email" or "crm".', 'contactin' ),
						esc_html( $process )
					)
				);
				return;
			}

			$duration = ProcessLock::get_lock_duration( $process );

			// Safety check: only force release if lock is > 5 minutes old
			if ( $duration < 300 ) {
				wp_send_json_error(
					sprintf(
						__( 'Lock is only %d seconds old. Wait until it\'s at least 5 minutes old before force releasing.', 'contactin' ),
						$duration
					)
				);
				return;
			}

			$success = ProcessLock::force_release( $process, 300 );

			if ( $success ) {
				Logger::warning( "Maintenance: Force released {$process} lock", array( 'duration' => $duration ) );
				wp_send_json_success(
					array(
						'message' => sprintf(
							__( '%s lock force released (was held for %d seconds).', 'contactin' ),
							ucfirst( $process ),
							$duration
						),
					)
				);
			} else {
				wp_send_json_error( __( 'Failed to release lock.', 'contactin' ) );
			}
		} catch ( \Throwable $e ) {
			Logger::error( 'Maintenance force release lock failed', array( 'error' => $e->getMessage() ) );
			wp_send_json_error( $e->getMessage() );
		}
	}

	/**
	 * AJAX: Manually trigger email processor
	 */
	public function ajax_trigger_email_processor(): void {
		$this->check_ajax( 'contactin_maint_trigger_email_processor' );

		try {
			// Check if processor is already running
			if ( ProcessLock::is_locked( 'email' ) ) {
				$duration = ProcessLock::get_lock_duration( 'email' );
				wp_send_json_error(
					sprintf(
						__( 'Email processor is already running (for %d seconds). Wait for it to complete or force release if stuck.', 'contactin' ),
						$duration
					)
				);
				return;
			}

			// Check if there are pending items
			$pending_count = QueueTrigger::get_pending_email_count();
			if ( $pending_count === 0 ) {
				wp_send_json_success(
					array(
						'message'   => __( 'No pending emails to process.', 'contactin' ),
						'processed' => 0,
					)
				);
				return;
			}

			// Trigger processing
			$triggered = QueueTrigger::maybe_trigger_email_processor();

			if ( $triggered ) {
				Logger::notice( 'Maintenance: Manually triggered email processor', array( 'pending' => $pending_count ) );
				wp_send_json_success(
					array(
						'message'   => sprintf(
							__( 'Email processor triggered. %d pending items will be processed.', 'contactin' ),
							$pending_count
						),
						'pending'   => $pending_count,
						'triggered' => true,
					)
				);
			} else {
				wp_send_json_error(
					__( 'Failed to trigger email processor. Check that SMTP is enabled and configured.', 'contactin' )
				);
			}
		} catch ( \Throwable $e ) {
			Logger::error( 'Maintenance trigger email processor failed', array( 'error' => $e->getMessage() ) );
			wp_send_json_error( $e->getMessage() );
		}
	}

	/**
	 * AJAX: Manually trigger CRM processor
	 */
	public function ajax_trigger_crm_processor(): void {
		$this->check_ajax( 'contactin_maint_trigger_crm_processor' );

		try {
			// Check if processor is already running
			if ( ProcessLock::is_locked( 'crm' ) ) {
				$duration = ProcessLock::get_lock_duration( 'crm' );
				wp_send_json_error(
					sprintf(
						__( 'CRM processor is already running (for %d seconds). Wait for it to complete or force release if stuck.', 'contactin' ),
						$duration
					)
				);
				return;
			}

			// Check if there are pending items (message table + queue types)
			$pending_count     = QueueTrigger::get_pending_crm_count();
			$queue_has_pending = QueueManager::has_pending_type( 'crm' )
				|| QueueManager::has_pending_type( 'crm_delete' )
				|| QueueManager::has_pending_type( 'attachment_retry' );

			if ( $pending_count === 0 && ! $queue_has_pending ) {
				wp_send_json_success(
					array(
						'message'   => __( 'No pending CRM syncs to process.', 'contactin' ),
						'processed' => 0,
					)
				);
				return;
			}

			// Trigger processing
			$triggered = QueueTrigger::maybe_trigger_crm_processor();

			if ( $triggered ) {
				$message = $pending_count > 0
					? sprintf(
						__( 'CRM processor triggered. %d pending syncs will be processed.', 'contactin' ),
						$pending_count
					)
					: __( 'CRM processor triggered. Queue items will be processed shortly.', 'contactin' );

				Logger::notice( 'Maintenance: Manually triggered CRM processor', array( 'pending' => $pending_count ) );
				wp_send_json_success(
					array(
						'message'   => $message,
						'pending'   => $pending_count,
						'triggered' => true,
					)
				);
			} else {
				wp_send_json_error(
					__( 'Failed to trigger CRM processor. Check that CRM integration is enabled and configured.', 'contactin' )
				);
			}
		} catch ( \Throwable $e ) {
			Logger::error( 'Maintenance trigger CRM processor failed', array( 'error' => $e->getMessage() ) );
			wp_send_json_error( $e->getMessage() );
		}
	}

	/**
	 * AJAX: Get comprehensive cron diagnostics
	 */
	public function ajax_queue_progress(): void {
		$this->check_ajax( 'contactin_maint_queue_progress' );

		try {
			$status_counts = $this->message_repo->get_status_counts();
			$queue_stats   = $this->queue_repo->get_stats_by_type();

			$email_queue      = $queue_stats['email'] ?? array(
				'pending'    => 0,
				'processing' => 0,
				'retry'      => 0,
				'completed'  => 0,
				'dlq'        => 0,
				'total'      => 0,
			);
			$crm_queue        = $queue_stats['crm'] ?? array(
				'pending'    => 0,
				'processing' => 0,
				'retry'      => 0,
				'completed'  => 0,
				'dlq'        => 0,
				'total'      => 0,
			);
			$attachment_queue = $queue_stats['attachment_retry'] ?? array(
				'pending'    => 0,
				'processing' => 0,
				'retry'      => 0,
				'completed'  => 0,
				'dlq'        => 0,
				'total'      => 0,
			);

			$email_pending_total = (int) ( $status_counts['admin_email_pending'] ?? 0 )
				+ (int) ( $status_counts['user_email_pending'] ?? 0 )
				+ (int) ( $email_queue['pending'] ?? 0 )
				+ (int) ( $email_queue['processing'] ?? 0 )
				+ (int) ( $email_queue['retry'] ?? 0 );

			$crm_pending_total = (int) ( $status_counts['crm_pending'] ?? 0 )
				+ (int) ( $crm_queue['pending'] ?? 0 )
				+ (int) ( $crm_queue['processing'] ?? 0 )
				+ (int) ( $crm_queue['retry'] ?? 0 );

			$attachment_pending_total = (int) ( $attachment_queue['pending'] ?? 0 )
				+ (int) ( $attachment_queue['processing'] ?? 0 )
				+ (int) ( $attachment_queue['retry'] ?? 0 );

			wp_send_json_success(
				array(
					'email'       => array(
						'pending_total'  => $email_pending_total,
						'queue'          => $email_queue,
						'legacy_pending' => array(
							'admin' => (int) ( $status_counts['admin_email_pending'] ?? 0 ),
							'user'  => (int) ( $status_counts['user_email_pending'] ?? 0 ),
						),
						'in_progress'    => ProcessLock::is_locked( 'email' ),
					),
					'crm'         => array(
						'pending_total'  => $crm_pending_total,
						'queue'          => $crm_queue,
						'legacy_pending' => (int) ( $status_counts['crm_pending'] ?? 0 ),
						'in_progress'    => ProcessLock::is_locked( 'crm' ),
					),
					'attachments' => array(
						'pending_total' => $attachment_pending_total,
						'queue'         => $attachment_queue,
					),
				)
			);
		} catch ( \Throwable $e ) {
			Logger::error( 'Maintenance queue progress failed', array( 'error' => $e->getMessage() ) );
			wp_send_json_error( $e->getMessage() );
		}
	}

	public function ajax_get_cron_diagnostics(): void {
		$this->check_ajax( 'contactin_maint_get_cron_diagnostics' );

		try {
			$diagnostics = \ContactInbox\Core\CronDiagnostics::run_diagnostics();
			$duplicates  = \ContactInbox\Core\CronDiagnostics::check_for_duplicate_singles();

			wp_send_json_success(
				array(
					'diagnostics' => $diagnostics,
					'duplicates'  => $duplicates,
					'text_report' => \ContactInbox\Core\CronDiagnostics::get_diagnostic_text(),
				)
			);
		} catch ( \Throwable $e ) {
			Logger::error( 'Maintenance get cron diagnostics failed', array( 'error' => $e->getMessage() ) );
			wp_send_json_error( $e->getMessage() );
		}
	}

	private function check_ajax( string $action ): void {
		check_ajax_referer( $action, 'nonce' );
		if ( ! current_user_can( Config::CAPABILITY ) ) {
			wp_send_json_error( __( 'Insufficient permissions.', 'contactin' ) );
		}
	}

	/**
	 * Helper method to retry failed messages for specified channels
	 *
	 * @param array<string> $channels Channels to retry: 'admin_email', 'user_email', 'crm'
	 * @param int           $limit Maximum number of messages to attempt
	 * @return array<string, int> Count of retries per channel
	 */
	private function retry_failed_channels( array $channels, int $limit = 200 ): array {
		$failed  = $this->message_repo->get_failed( $limit );
		$retries = array_fill_keys( $channels, 0 );

		foreach ( $failed as $message ) {
			$message_id = (int) ( $message->id ?? 0 );
			if ( $message_id <= 0 ) {
				continue;
			}

			if ( in_array( 'admin_email', $channels, true ) && ( $message->admin_email_status ?? null ) === Config::EMAIL_FAILED ) {
				if ( $this->message_repo->update_channel_status( $message_id, 'admin_email', Config::EMAIL_PENDING ) ) {
					++$retries['admin_email'];
				}
			}

			if ( in_array( 'user_email', $channels, true ) && ( $message->user_email_status ?? null ) === Config::EMAIL_FAILED ) {
				if ( $this->message_repo->update_channel_status( $message_id, 'user_email', Config::EMAIL_PENDING ) ) {
					++$retries['user_email'];
				}
			}

			if ( in_array( 'crm', $channels, true ) && ( $message->crm_status ?? null ) === Config::CRM_FAILED ) {
				if ( $this->message_repo->update_channel_status( $message_id, 'crm', Config::CRM_PENDING ) ) {
					++$retries['crm'];
				}
			}
		}

		return $retries;
	}

	/**
	 * Validate and sanitize delay_seconds input
	 *
	 * @param int $default Default value if not provided or invalid
	 * @param int $min Minimum allowed value
	 * @param int $max Maximum allowed value
	 * @return int Validated delay in seconds
	 */
	private function validate_delay_seconds( int $default, int $min = 60, int $max = 3600 ): int {
		if ( ! isset( $_POST['delay_seconds'] ) ) {
			return $default;
		}

		$delay = absint( $_POST['delay_seconds'] );

		if ( $delay <= 0 ) {
			return $default;
		}

		// Enforce reasonable limits
		return max( $min, min( $delay, $max ) );
	}

	/**
	 * AJAX: Queue synced GDPR deletions for CRM cleanup.
	 * This schedules deletions for background processing using existing sync infrastructure.
	 */
	public function ajax_gdpr_queue_delete(): void {
		$this->check_ajax( 'contactin_maint_gdpr_queue_delete' );

		$crm_settings = \ContactInbox\Core\CRMSettings::get_settings();
		if ( empty( $crm_settings['crm_delete_sync'] ) ) {
			wp_send_json_error(
				array(
					'message' => __( 'CRM deletion sync is disabled. Enable it in CRM settings to queue deletions.', 'contactin' ),
				)
			);
		}

		try {
			// First, show what's pending in GDPR deletion queue
			$deletion_queue_count = $this->queue_repo->count_by_type( 'crm_delete' );

			$logs = $this->gdpr_repo->get_ready_for_crm_deletion( 200 );

			if ( empty( $logs ) ) {
				wp_send_json_success(
					array(
						'message'          => sprintf(
							__( 'No contacts ready for deletion. %d item(s) already pending in deletion queue.', 'contactin' ),
							$deletion_queue_count
						),
						'queued_count'     => 0,
						'pending_in_queue' => $deletion_queue_count,
					)
				);
				return;
			}

			// Queue each contact for CRM deletion using QueueManager
			$queued_count   = 0;
			$already_queued = 0;

			foreach ( $logs as $log ) {
				// Check if already queued to prevent duplicates
				if ( $this->gdpr_repo->is_deletion_queued( (int) $log->id ) ) {
					++$already_queued;
					continue;
				}

				// Prepare payload for CRM deletion
				$payload = array(
					'contact_id'  => $log->contact_id,
					'crm_id'      => $log->crm_id ?? null,
					'email'       => $log->email,
					'name'        => $log->name,
					'gdpr_log_id' => $log->id,
					'operation'   => 'crm_delete',
				);

				// Use QueueManager::push() static method
				$queue_id = QueueManager::push(
					'crm_delete',
					$payload,
					(string) $log->id,
					2 // High priority
				);

				if ( ! is_wp_error( $queue_id ) ) {
					// Mark deletion as queued in GDPR log using repository
					$this->gdpr_repo->mark_deletion_queued( (int) $log->id );
					++$queued_count;
				}
			}

			// Get updated queue count
			$updated_queue_count = $this->queue_repo->count_by_type( 'crm_delete' );

			Logger::info(
				'GDPR CRM deletions queued',
				array(
					'count'          => $queued_count,
					'total_logs'     => count( $logs ),
					'already_queued' => $already_queued,
					'total_in_queue' => $updated_queue_count,
				)
			);

			$message = sprintf(
				__( 'Queued %d contact(s) for CRM deletion. %d item(s) now pending in deletion queue.', 'contactin' ),
				$queued_count,
				$updated_queue_count
			);

			if ( $already_queued > 0 ) {
				$message .= ' ' . sprintf(
					__( '%d contact(s) were already queued.', 'contactin' ),
					$already_queued
				);
			}

			wp_send_json_success(
				array(
					'message'          => $message,
					'queued_count'     => $queued_count,
					'already_queued'   => $already_queued,
					'pending_in_queue' => $updated_queue_count,
				)
			);
		} catch ( \Throwable $e ) {
			Logger::error( 'GDPR queue delete failed', array( 'error' => $e->getMessage() ) );
			wp_send_json_error(
				array(
					'message' => __( 'Failed to queue deletions.', 'contactin' ) . ' ' . $e->getMessage(),
				)
			);
		}
	}

	/**
	 * AJAX: Immediately delete synced contacts from CRM.
	 * Queues deletions and processes them immediately.
	 */
	public function ajax_gdpr_immediate_delete(): void {
		$this->check_ajax( 'contactin_maint_gdpr_immediate_delete' );

		$crm_settings = \ContactInbox\Core\CRMSettings::get_settings();
		if ( empty( $crm_settings['crm_delete_sync'] ) ) {
			wp_send_json_error(
				array(
					'message' => __( 'CRM deletion sync is disabled. Enable it in CRM settings to process deletions.', 'contactin' ),
				)
			);
		}

		try {
			// Get deletion queue status before processing
			$queue_before = $this->queue_repo->count_by_type( 'crm_delete' );

			$logs = $this->gdpr_repo->get_ready_for_crm_deletion( 50 );

			if ( empty( $logs ) ) {
				wp_send_json_success(
					array(
						'message'          => sprintf(
							__( 'No contacts ready for deletion. %d item(s) currently in deletion queue.', 'contactin' ),
							$queue_before
						),
						'deleted_count'    => 0,
						'pending_in_queue' => $queue_before,
					)
				);
				return;
			}

			$deleted_count  = 0;
			$already_queued = 0;
			$errors         = array();

			foreach ( $logs as $log ) {
				try {
					// Check if already queued to prevent duplicates
					if ( $this->gdpr_repo->is_deletion_queued( (int) $log->id ) ) {
						++$already_queued;
						continue;
					}

					// Queue for deletion with highest priority for immediate
					$payload = array(
						'contact_id'  => $log->contact_id,
						'crm_id'      => $log->crm_id ?? null,
						'email'       => $log->email,
						'name'        => $log->name,
						'gdpr_log_id' => $log->id,
						'operation'   => 'crm_delete',
						'immediate'   => true,
					);

					$queue_id = QueueManager::push(
						'crm_delete',
						$payload,
						(string) $log->id,
						1 // Highest priority for immediate
					);

					if ( ! is_wp_error( $queue_id ) ) {
						++$deleted_count;

						// Update GDPR log to mark queued for CRM deletion using repository
						$this->gdpr_repo->mark_deletion_queued( (int) $log->id );

						Logger::info(
							'GDPR CRM deletion queued (immediate)',
							array(
								'email'    => $log->email,
								'log_id'   => $log->id,
								'queue_id' => $queue_id,
							)
						);
					} else {
						$errors[] = sprintf(
							__( 'Failed to queue %s: %s', 'contactin' ),
							$log->email,
							$queue_id->get_error_message()
						);
					}
				} catch ( \Throwable $e ) {
					$errors[] = sprintf(
						__( 'Error queuing %s: %s', 'contactin' ),
						$log->email,
						$e->getMessage()
					);
					Logger::error(
						'GDPR immediate delete queue error',
						array(
							'email' => $log->email,
							'error' => $e->getMessage(),
						)
					);
				}
			}

			// Trigger immediate processing if items were queued
			if ( $deleted_count > 0 ) {
				CronJobs::instance()->process_crm_queue();
			}

			// Get deletion queue status after processing
			$queue_after      = $this->queue_repo->count_by_type( 'crm_delete' );
			$actually_deleted = $queue_before - $queue_after;

			$message = sprintf(
				__( 'Processed %d contact(s) for CRM deletion. Deleted %d, pending %d.', 'contactin' ),
				$deleted_count,
				max( 0, $actually_deleted ),
				$queue_after
			);

			if ( $already_queued > 0 ) {
				$message .= ' ' . sprintf(
					__( '%d contact(s) were already queued.', 'contactin' ),
					$already_queued
				);
			}

			if ( ! empty( $errors ) ) {
				$message .= ' ' . sprintf(
					__( '%d error(s) occurred. Check logs for details.', 'contactin' ),
					count( $errors )
				);
			}

			Logger::info(
				'GDPR immediate delete processed',
				array(
					'queued'           => $deleted_count,
					'actually_deleted' => max( 0, $actually_deleted ),
					'remaining'        => $queue_after,
					'errors'           => count( $errors ),
				)
			);

			wp_send_json_success(
				array(
					'message'          => $message,
					'queued_count'     => $deleted_count,
					'deleted_count'    => max( 0, $actually_deleted ),
					'pending_in_queue' => $queue_after,
					'already_queued'   => $already_queued,
					'error_count'      => count( $errors ),
					'errors'           => array_slice( $errors, 0, 5 ), // First 5 errors only
				)
			);
		} catch ( \Throwable $e ) {
			Logger::error( 'GDPR immediate delete failed', array( 'error' => $e->getMessage() ) );
			wp_send_json_error(
				array(
					'message' => __( 'Failed to delete contacts from CRM.', 'contactin' ) . ' ' . $e->getMessage(),
				)
			);
		}
	}

	/**
	 * AJAX: Synchronize stuck GDPR logs with successful CRM deletion logs.
	 *
	 * When CRM deletion succeeds but GDPR log update fails (lock/timeout),
	 * this repair function uses CRM logs as source of truth to update stuck GDPR entries.
	 */
	public function ajax_gdpr_sync_crm_logs(): void {
		$this->check_ajax( 'contactin_maint_gdpr_sync_crm_logs' );

		try {
			$synced = $this->gdpr_repo->sync_with_crm_logs();

			Logger::info(
				'GDPR CRM log sync completed',
				array(
					'synced_count' => $synced,
				)
			);

			if ( $synced === 0 ) {
				wp_send_json_success(
					array(
						'message'      => __( 'No stuck GDPR logs found. All logs are in sync with CRM deletion records.', 'contactin' ),
						'synced_count' => 0,
					)
				);
			} else {
				wp_send_json_success(
					array(
						'message'      => sprintf(
							_n(
								'%d GDPR log synchronized with successful CRM deletion record.',
								'%d GDPR logs synchronized with successful CRM deletion records.',
								$synced,
								'contactin'
							),
							$synced
						),
						'synced_count' => $synced,
					)
				);
			}
		} catch ( \Throwable $e ) {
			Logger::error( 'GDPR CRM log sync failed', array( 'error' => $e->getMessage() ) );
			wp_send_json_error(
				array(
					'message' => __( 'Failed to synchronize GDPR logs with CRM records.', 'contactin' ) . ' ' . $e->getMessage(),
				)
			);
		}
	}

	/**
	 * AJAX: Reclassify unclassified messages using current intent patterns
	 */
	public function ajax_reclassify_intent(): void {
		$this->check_ajax( 'contactin_maint_reclassify_intent' );

		try {
			$classifier = IntentClassifier::instance();

			// Get count of unclassified messages
			$total_unclassified = $classifier->count_unclassified_messages();

			if ( $total_unclassified === 0 ) {
				wp_send_json_success(
					array(
						'message'            => __( 'No unclassified messages found.', 'contactin' ),
						'total_unclassified' => 0,
						'processed'          => 0,
						'success'            => 0,
						'failed'             => 0,
					)
				);
				return;
			}

			// Process first batch (100 messages)
			$batch_size = 100;
			$result     = $classifier->bulk_classify_unclassified( $batch_size, 0 );

			if ( ! is_array( $result ) ) {
				throw new \Exception( 'Invalid reclassification result' );
			}

			$remaining = max( 0, $total_unclassified - ( $result['processed'] ?? 0 ) );
			$processed = $result['processed'] ?? 0;
			$success   = $result['success'] ?? 0;
			$failed    = $result['failed'] ?? 0;

			// Build breakdown message with category labels and percentages
			$breakdown      = $result['breakdown'] ?? array();
			$categories     = IntentClassifier::get_categories();
			$breakdownParts = array();

			if ( ! empty( $breakdown ) && $success > 0 ) {
				foreach ( $breakdown as $category => $count ) {
					if ( $count > 0 ) {
						$percentage       = ( $count / $success ) * 100;
						$label            = $categories[ $category ] ?? ucfirst( $category );
						$breakdownParts[] = sprintf( '%s: %d (%.1f%%)', $label, $count, $percentage );
					}
				}
			}

			// Build user-friendly message based on success rate
			$message      = '';
			$success_rate = $processed > 0 ? ( $success / $processed ) * 100 : 0;

			if ( $success > 0 ) {
				// Show positive results first
				if ( ! empty( $breakdownParts ) ) {
					$message = sprintf(
						__( 'Successfully classified %d message(s): ', 'contactin' ),
						$success
					) . implode( ', ', $breakdownParts ) . '. ';
				} else {
					$message = sprintf(
						__( 'Successfully classified %d out of %d message(s). ', 'contactin' ),
						$success,
						$processed
					);
				}

				if ( $failed > 0 ) {
					$message .= sprintf(
						__( '%d message(s) could not be automatically classified.', 'contactin' ),
						$failed
					);
				}
			} else {
				// No messages were classified - provide helpful guidance
				$message = sprintf(
					__( 'Processed %d message(s), but automatic classification was not confident enough.', 'contactin' ),
					$processed
				) . ' ';

				$message .= sprintf(
					__(
						'Messages are matched against intent patterns (high/medium/low priority keywords). These %d message(s) likely don\'t contain keywords matching existing patterns.',
						'contactin'
					),
					$processed
				) . ' ';

				// Check if user has Pro license
				if ( FreemiusIntegration::has_pro_license() ) {
					$message .= __(
						'Options: 1) View & manually classify them from the Messages page, or 2) Use the ML self-learning feature to train the classifier on your specific messages.',
						'contactin'
					);
				} else {
					$message .= __(
						'Options: 1) View & manually classify them from the Messages page, or 2) Upgrade to the Pro version to use ML self-learning feature to train the classifier on your specific messages.',
						'contactin'
					);
				}
			}

			if ( $remaining > 0 ) {
				$message .= ' ' . sprintf(
					__( '%d unclassified message(s) remaining for next batch.', 'contactin' ),
					$remaining
				);
			}

			Logger::info(
				'Maintenance: Manual intent reclassification triggered',
				array(
					'batch_size'         => $batch_size,
					'processed'          => $processed,
					'success'            => $success,
					'failed'             => $failed,
					'success_rate'       => round( $success_rate, 2 ) . '%',
					'breakdown'          => $breakdown,
					'total_unclassified' => $total_unclassified,
					'remaining'          => $remaining,
				)
			);

			wp_send_json_success(
				array(
					'message'            => $message,
					'breakdown'          => $breakdownParts,
					'total_unclassified' => $total_unclassified,
					'processed'          => $processed,
					'success'            => $success,
					'failed'             => $failed,
					'remaining'          => $remaining,
					'success_rate'       => round( $success_rate, 2 ),
				)
			);

		} catch ( \Throwable $e ) {
			Logger::error(
				'Maintenance: Intent reclassification failed',
				array(
					'error' => $e->getMessage(),
					'file'  => $e->getFile(),
					'line'  => $e->getLine(),
				)
			);

			wp_send_json_error(
				array(
					'message' => __( 'Reclassification failed: ', 'contactin' ) . $e->getMessage(),
				)
			);
		}
	}
}
