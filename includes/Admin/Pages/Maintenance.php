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
		add_action( 'wp_ajax_contactin_maint_retry_dlq', array( $this, 'ajax_retry_dlq' ) );
		add_action( 'wp_ajax_contactin_maint_retry_email_dlq', array( $this, 'ajax_retry_email_dlq' ) );
		add_action( 'wp_ajax_contactin_maint_reset_circuits', array( $this, 'ajax_reset_circuits' ) );
		add_action( 'wp_ajax_contactin_maint_skip_email', array( $this, 'ajax_skip_email' ) );
		add_action( 'wp_ajax_contactin_maint_reschedule_email_queue', array( $this, 'ajax_reschedule_email_queue' ) );
		// New event-driven queue actions
		add_action( 'wp_ajax_contactin_maint_get_lock_status', array( $this, 'ajax_get_lock_status' ) );
		add_action( 'wp_ajax_contactin_maint_force_release_lock', array( $this, 'ajax_force_release_lock' ) );
		add_action( 'wp_ajax_contactin_maint_trigger_email_processor', array( $this, 'ajax_trigger_email_processor' ) );
		add_action( 'wp_ajax_contactin_maint_queue_progress', array( $this, 'ajax_queue_progress' ) );
		// Diagnostics
		add_action( 'wp_ajax_contactin_maint_get_cron_diagnostics', array( $this, 'ajax_get_cron_diagnostics' ) );
	}

	public static function render(): void {
		if ( ! current_user_can( Config::CAPABILITY ) ) {
			wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'contactin' ) );
		}

		$instance = self::instance();

		$cb_states = CircuitBreaker::get_stats();

		// Build circuit status display (compute in controller, not template)
		$circuit_state_labels = array(
			'closed'    => esc_html__( 'Available', 'contactin' ),
			'open'      => esc_html__( 'Tripped', 'contactin' ),
			'half_open' => esc_html__( 'Recovering', 'contactin' ),
		);
		$circuit_summary      = array();
		$circuit_badges       = array(); // For badge display
		foreach ( $cb_states as $service => $state ) {
			$raw_state         = strtolower( (string) ( $state['state'] ?? '' ) );
			$fallback_label    = $raw_state !== '' ? ucwords( str_replace( '_', ' ', $raw_state ) ) : esc_html__( 'Unknown', 'contactin' );
			$display_label     = $circuit_state_labels[ $raw_state ] ?? $fallback_label;
			$circuit_summary[] = sprintf( '%s: %s', strtoupper( $service ), $display_label );

			// Skip webhook badges (not typically displayed separately)
			if ( strtolower( $service ) !== 'webhook' && strtolower( $service ) !== 'webhooks' ) {
				$circuit_badges[ $service ] = array(
					'state'   => $raw_state,
					'label'   => $display_label,
					'tooltip' => sprintf( esc_html__( 'Circuit state: %s', 'contactin' ), $raw_state !== '' ? strtoupper( $raw_state ) : esc_html__( 'Unknown', 'contactin' ) ),
				);
			}
		}
		$circuit_status_line = implode( ' · ', $circuit_summary );

		// Maintenance stats: backlog is current; throughput is last 7 days.
		$stats_window_days  = 7;
		$stats_window_end   = current_time( 'Y-m-d' );
		$stats_window_start = date( 'Y-m-d', time() - ( ( $stats_window_days - 1 ) * DAY_IN_SECONDS ) );
		$stats_window_label = sprintf( esc_html__( 'Last %d days', 'contactin' ), $stats_window_days );

		// Current backlog state (no date filter)
		$message_stats_current = $instance->message_repo->get_status_counts();
		// Short-term throughput window
		$message_stats_window = $instance->message_repo->get_status_counts( $stats_window_start, $stats_window_end );

		$next_run_email           = wp_next_scheduled( Config::CRON_PROCESS_EMAIL );
		$email_schedule_slug      = get_option( 'contactin_queue_interval', 'contactin_fifteen_minutes' );
		$email_reschedule_default = $instance->get_interval_seconds( $email_schedule_slug );
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

		// Attachment cleanup is Pro-gated in this build.

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
		Logger::notice( 'Maintenance CRM queue run blocked: CRM background sync disabled' );
		wp_send_json_error( esc_html__( 'CRM background sync is disabled in this build.', 'contactin' ) );
		return;
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
		Logger::notice( 'Maintenance CRM retry blocked: CRM background sync disabled' );
		wp_send_json_error( esc_html__( 'CRM background sync is disabled in this build.', 'contactin' ) );
		return;
	}

	public function ajax_reset_circuits(): void {
		$this->check_ajax( 'contactin_maint_reset_circuits' );
		try {
			$services = array( 'smtp', 'crm' );
			foreach ( $services as $service ) {
				CircuitBreaker::reset( $service );
			}
			Logger::notice( 'Maintenance: reset circuit breakers', array( 'services' => $services ) );
			wp_send_json_success( array( 'message' => sprintf( esc_html__( 'Reset circuit breakers for: %s', 'contactin' ), strtoupper( implode( ', ', $services ) ) ) ) );
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
						esc_html__( 'Skipped %1$d email items (legacy admin: %2$d, legacy user: %3$d, queue: %4$d). DLQ cleared: %5$d.', 'contactin' ),
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
					esc_html__( 'Email queue rescheduled. Next run in %d seconds (recurring every %s).', 'contactin' ),
					$delay,
					$interval_label
				);
			} else {
				// Using default interval
				$msg = sprintf(
					esc_html__( 'Email queue rescheduled to %s. Next run in %d seconds.', 'contactin' ),
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
		Logger::notice( 'Maintenance CRM queue reschedule blocked: CRM background sync disabled' );
		wp_send_json_error( esc_html__( 'CRM background sync is disabled in this build.', 'contactin' ) );
		return;
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
				wp_send_json_error( esc_html__( 'Process parameter is required.', 'contactin' ) );
				return;
			}

			$process = sanitize_text_field( wp_unslash( $_POST['process'] ) );

			// Whitelist validation
			if ( ! in_array( $process, array( 'email', 'crm' ), true ) ) {
				wp_send_json_error(
					sprintf(
						esc_html__( 'Invalid process type "%s". Must be "email" or "crm".', 'contactin' ),
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
						esc_html__( 'Lock is only %d seconds old. Wait until it\'s at least 5 minutes old before force releasing.', 'contactin' ),
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
							esc_html__( '%s lock force released (was held for %d seconds).', 'contactin' ),
							ucfirst( $process ),
							$duration
						),
					)
				);
			} else {
				wp_send_json_error( esc_html__( 'Failed to release lock.', 'contactin' ) );
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
						esc_html__( 'Email processor is already running (for %d seconds). Wait for it to complete or force release if stuck.', 'contactin' ),
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
		Logger::notice( 'Maintenance CRM processor trigger blocked: CRM background sync disabled' );
		wp_send_json_error( esc_html__( 'CRM background sync is disabled in this build.', 'contactin' ) );
		return;
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
			wp_send_json_error( esc_html__( 'Insufficient permissions.', 'contactin' ) );
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

		$delay = absint( wp_unslash( $_POST['delay_seconds'] ) );

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
		Logger::notice( 'Maintenance GDPR queue delete blocked: CRM/GDPR background processing disabled' );
		wp_send_json_error( esc_html__( 'GDPR background processing is disabled in this build.', 'contactin' ) );
		return;
	}

	/**
	 * AJAX: Immediately delete synced contacts from CRM.
	 * Queues deletions and processes them immediately.
	 */
	public function ajax_gdpr_immediate_delete(): void {
		$this->check_ajax( 'contactin_maint_gdpr_immediate_delete' );
		Logger::notice( 'Maintenance GDPR immediate delete blocked: CRM/GDPR background processing disabled' );
		wp_send_json_error( esc_html__( 'GDPR background processing is disabled in this build.', 'contactin' ) );
		return;
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

				$message .= __(
					'Options: 1) View & manually classify them from the Messages page, or 2) Use the ML self-learning feature to train the classifier on your specific messages.',
					'contactin'
				);
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
