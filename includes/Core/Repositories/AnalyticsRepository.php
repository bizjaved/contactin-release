<?php declare(strict_types=1);
/**
 * Analytics Repository
 *
 * Centralized data layer for analytics queries across the plugin.
 * Uses existing repositories (MessageRepository, SubmissionRepository, etc.)
 * to gather analytics data without direct database calls.
 *
 * @package ContactIn\Core\Repositories
 */

namespace ContactInbox\Core\Repositories;

use ContactInbox\Core\Config;
use ContactInbox\Core\CRMMonitor;
use ContactInbox\Core\IntentClassifier;
use ContactInbox\Core\Logger;
use ContactInbox\Core\QueueManager;
use ContactInbox\Core\Repositories\QueueRepository;

// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.WP.I18n.NonSingularStringLiteralDomain, WordPress.WP.I18n.MissingTranslatorsComment, WordPress.Security.ValidatedSanitizedInput.MissingUnslash, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized, WordPress.Security.NonceVerification.Recommended, WordPress.WP.AlternativeFunctions.unlink_unlink, WordPress.WP.AlternativeFunctions.file_system_operations_fwrite, WordPress.WP.AlternativeFunctions.file_system_operations_is_writable, WordPress.WP.AlternativeFunctions.file_system_operations_fclose, WordPress.WP.AlternativeFunctions.rename_rename, WordPress.WP.AlternativeFunctions.file_system_operations_fopen, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class AnalyticsRepository {

	/**
	 * Get CRM sync stats for dashboard (rate, total, successful, failed)
	 */
	public function get_crm_sync_stats( ?int $days = null, ?string $start_date = null, ?string $end_date = null ): array {
		// Get ALL CRM operations (sync + delete) for comprehensive stats
		$stats      = \ContactInbox\Core\CRMMonitor::get_statistics( $days, $start_date, $end_date, null );
		$total      = $stats['total_syncs'] ?? 0;
		$successful = $stats['successful'] ?? 0;
		$failed     = $stats['failed'] ?? 0;
		$rate       = $total > 0 ? round( ( $successful / $total ) * 100, 1 ) : 0.0;
		return array(
			'rate'       => $rate,
			'total'      => $total,
			'successful' => $successful,
			'failed'     => $failed,
		);
	}

	private MessageRepository $message_repo;
	private EmailLogRepository $email_repo;
	private RestLogRepository $rest_repo;
	private SubmissionRepository $submission_repo;
	private QueueRepository $queue_repo;
	private SubmissionAttemptsRepository $attempts_repo;

	public function __construct() {
		$this->message_repo    = new MessageRepository();
		$this->email_repo      = new EmailLogRepository();
		$this->rest_repo       = new RestLogRepository();
		$this->submission_repo = new SubmissionRepository();
		$this->queue_repo      = new QueueRepository();
		$this->attempts_repo   = new SubmissionAttemptsRepository();
	}

	/**
	 * Get today's submission count
	 */
	public function get_submission_count_today(): int {
		$today  = current_time( 'Y-m-d' );
		$counts = $this->message_repo->get_inbox_status_breakdown( $today, $today );

		return (int) ( $counts['unread'] ?? 0 )
			+ (int) ( $counts['read'] ?? 0 )
			+ (int) ( $counts['archived'] ?? 0 );
	}

	/**
	 * Get submission status breakdown for last 7 days
	 *
	 * @return array ['unread' => int, 'read' => int, 'archived' => int, 'completed' => int, 'failed' => int]
	 */
	public function get_submission_status_breakdown( int $days = 7, ?string $startDate = null, ?string $endDate = null ): array {
		$range_start = $startDate;
		$range_end   = $endDate;
		if ( ! $range_start || ! $range_end ) {
			$range_start = gmdate( 'Y-m-d', time() - ( $days * DAY_IN_SECONDS ) );
			$range_end   = gmdate( 'Y-m-d' );
		}

		$counts = $this->message_repo->get_inbox_status_breakdown( $range_start, $range_end );

		// Get submission completion data from submission repository
		global $wpdb;
		$table = $wpdb->prefix . Config::TABLE_SUBMISSION_LOG;

		$submission_counts = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT 
                COUNT(*) as total,
                SUM(CASE WHEN completed_at IS NOT NULL THEN 1 ELSE 0 END) as successful
            FROM {$table}
            WHERE DATE(attempted_at) >= %s AND DATE(attempted_at) <= %s",
				$range_start,
				$range_end
			)
		);

		$total_submissions      = (int) ( $submission_counts->total ?? 0 );
		$successful_submissions = (int) ( $submission_counts->successful ?? 0 );
		$failed_submissions     = $total_submissions - $successful_submissions;

		return array(
			'unread'    => $counts['unread'] ?? 0,
			'read'      => $counts['read'] ?? 0,
			'archived'  => $counts['archived'] ?? 0,
			// Completion metrics based on actual submission attempts
			'completed' => $successful_submissions,
			'failed'    => $failed_submissions,
		);
	}

	/**
	 * Calculate conversion rate for last 7 days
	 *
	 * @return float Percentage 0-100
	 */
	public function get_conversion_rate( int $days = 7 ): float {
		global $wpdb;
		$table = $wpdb->prefix . Config::TABLE_QUEUE;

		// Count all queue entries created in window
		$total = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$table} WHERE DATE(created_at) >= DATE_SUB(NOW(), INTERVAL %d DAY)",
				$days
			)
		);

		if ( $total === 0 ) {
			return 0.0;
		}

		// Count completed queue items in window
		$completed = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$table} WHERE DATE(created_at) >= DATE_SUB(NOW(), INTERVAL %d DAY) AND status = 'completed'",
				$days
			)
		);

		return round( ( $completed / $total ) * 100, 1 );
	}

	/**
	 * Get processing success rates by type from message status columns
	 *
	 * @param int $days Number of days to analyze
	 * @return array ['email' => float|null, 'crm' => float|null]
	 */
	public function get_queue_success_rates_by_type( int $days = 7, ?string $startDate = null, ?string $endDate = null ): array {
		try {
			$range_start = $startDate;
			$range_end   = $endDate;
			if ( ! $range_start || ! $range_end ) {
				$range_start = gmdate( 'Y-m-d', time() - ( $days * DAY_IN_SECONDS ) );
				$range_end   = gmdate( 'Y-m-d' );
			}

			$counts = $this->message_repo->get_status_counts( $range_start, $range_end );

			$rates = array();

			// Email rate (admin + user combined)
			$email_sent      = $counts['admin_email_sent'] + $counts['user_email_sent'];
			$email_failed    = $counts['admin_email_failed'] + $counts['user_email_failed'];
			$email_attempted = $email_sent + $email_failed;
			$rates['email']  = $email_attempted > 0 ? round( ( $email_sent / $email_attempted ) * 100, 1 ) : null;

			// CRM rate
			$crm_sent      = $counts['crm_sent'];
			$crm_failed    = $counts['crm_failed'];
			$crm_attempted = $crm_sent + $crm_failed;
			$rates['crm']  = $crm_attempted > 0 ? round( ( $crm_sent / $crm_attempted ) * 100, 1 ) : null;

			return $rates;
		} catch ( \Throwable $e ) {
			return array(
				'email' => null,
				'crm'   => null,
			);
		}
	}

	/**
	 * Get today's conversion rate as a percentage.
	 *
	 * @return float Conversion rate (0-100).
	 */
	public function get_conversion_rate_today(): float {
		// Fetch today's total submissions and successful submissions
		$total_submissions      = $this->submission_repo->get_submission_count_today();
		$successful_submissions = $this->submission_repo->get_successful_submission_count_today();

		// Avoid division by zero
		if ( $total_submissions === 0 ) {
			return 0.0;
		}

		// Calculate conversion rate
		return ( $successful_submissions / $total_submissions ) * 100;
	}

	/**
	 * Get today's email sent count
	 *
	 * @return int Number of emails successfully sent today
	 */
	public function get_emails_sent_today(): int {
		global $wpdb;
		$table = $wpdb->prefix . Config::TABLE_EMAIL_LOG;
		$today = current_time( 'Y-m-d' );

		$count = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$table} WHERE status = 'sent' AND DATE(created_at) = %s",
				$today
			)
		);

		return (int) ( $count ?? 0 );
	}

	/**
	 * Get today's CRM synced count
	 *
	 * @return int Number of contacts successfully synced to CRM today
	 */
	public function get_crm_synced_today(): int {
		global $wpdb;
		$table = $wpdb->prefix . Config::TABLE_CRM_LOG;
		$today = current_time( 'Y-m-d' );

		$count = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(DISTINCT message_id) FROM {$table} WHERE status = 'success' AND DATE(created_at) = %s",
				$today
			)
		);

		return (int) ( $count ?? 0 );
	}

	/**
	 * Get submission acceptance rate
	 *
	 * Percentage of form submission attempts that successfully passed all checks
	 * (nonce, rate limits, CAPTCHA, validation) and were saved to database.
	 *
	 * @param int $days Number of days to analyze
	 * @return float|null Acceptance rate (0-100) or null if no attempts
	 */
	public function get_submission_acceptance_rate( int $days = 7, ?string $startDate = null, ?string $endDate = null ): ?float {
		$stats = $this->attempts_repo->get_success_rate( $days, $startDate, $endDate );
		return $stats['rate'];
	}

	/**
	 * Get rejection reasons breakdown
	 *
	 * @param int $days Number of days to analyze
	 * @return array Array of ['reason' => string, 'count' => int]
	 */
	public function get_rejection_reasons( int $days = 7, ?string $startDate = null, ?string $endDate = null ): array {
		return $this->attempts_repo->get_rejection_reasons( $days, $startDate, $endDate );
	}

	/**
	 * Count classifier-tagged spam messages for the date window.
	 */
	public function get_classifier_spam_count( int $days = 7, ?string $startDate = null, ?string $endDate = null ): int {
		return $this->message_repo->count_by_intent_range(
			IntentClassifier::CATEGORY_SPAM,
			$days,
			$startDate,
			$endDate
		);
	}

	/**
	 * Get daily trend data for last N days
	 *
	 * @param int $days Number of days to retrieve
	 * @return array Array of daily submission counts
	 */
	public function get_daily_submission_trend( int $days = 7, ?string $startDate = null, ?string $endDate = null ): array {
		global $wpdb;

		$table = $wpdb->prefix . Config::TABLE_MESSAGES;
		$days  = max( 1, $days );

		if ( ! $startDate || ! $endDate ) {
			$endDate   = current_time( 'Y-m-d' );
			$startDate = gmdate( 'Y-m-d', time() - ( ( $days - 1 ) * DAY_IN_SECONDS ) );
		}

		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT DATE(submitted_at) as date, COUNT(*) as count
                 FROM {$table}
                 WHERE (recaptcha_score IS NULL OR recaptcha_score >= %f)
                   AND DATE(submitted_at) BETWEEN %s AND %s
                 GROUP BY DATE(submitted_at)
                 ORDER BY date ASC",
				Config::SPAM_SCORE_THRESHOLD,
				$startDate,
				$endDate
			),
			ARRAY_A
		);

		$countsByDate = array();
		foreach ( $rows as $row ) {
			$countsByDate[ $row['date'] ] = (int) $row['count'];
		}

		$period = new \DatePeriod(
			new \DateTime( $startDate ),
			new \DateInterval( 'P1D' ),
			( new \DateTime( $endDate ) )->modify( '+1 day' )
		);

		$data = array();
		foreach ( $period as $date ) {
			$key    = $date->format( 'Y-m-d' );
			$data[] = $countsByDate[ $key ] ?? 0;
		}

		return $data;
	}

	/**
	 * Get queue health metrics
	 *
	 * @return array ['pending' => int, 'status' => string, 'message' => string]
	 */
	public function get_queue_health( ?int $days = null, ?string $start_date = null, ?string $end_date = null ): array {
		try {
			$db     = \ContactInbox\Core\DB::instance();
			$counts = $db->get_message_status_counts( $start_date, $end_date );

			// Calculate totals by processing type
			$admin_email_pending = $counts['admin_email_pending'];
			$user_email_pending  = $counts['user_email_pending'];
			$crm_pending         = $counts['crm_pending'];

			$admin_email_failed = $counts['admin_email_failed'];
			$user_email_failed  = $counts['user_email_failed'];
			$crm_failed         = $counts['crm_failed'];

			// Add queue-based CRM operations (crm_delete) from queue table
			$queue_stats       = $this->queue_repo->get_stats_by_type( $days, $start_date, $end_date );
			$crm_queue_pending = (int) ( ( $queue_stats['crm']['pending'] ?? 0 ) + ( $queue_stats['crm_delete']['pending'] ?? 0 ) );
			$crm_queue_retry   = (int) ( ( $queue_stats['crm']['retry'] ?? 0 ) + ( $queue_stats['crm_delete']['retry'] ?? 0 ) );
			$crm_queue_failed  = (int) ( ( $queue_stats['crm']['dlq'] ?? 0 ) + ( $queue_stats['crm_delete']['dlq'] ?? 0 ) );

			$total_email_pending = $admin_email_pending + $user_email_pending;
			$total_crm_pending   = $crm_pending + $crm_queue_pending + $crm_queue_retry;
			$total_pending       = $total_email_pending + $total_crm_pending;
			$total_failed        = $admin_email_failed + $user_email_failed + $crm_failed + $crm_queue_failed;

			// Build per_type breakdown (for compatibility with existing template)
			$per_type = array(
				'admin_email' => array(
					'pending'    => $admin_email_pending,
					'processing' => 0, // No longer tracked separately
					'retry'      => 0, // Handled differently now
					'dlq'        => $admin_email_failed, // Failed = DLQ equivalent
					'sent'       => $counts['admin_email_sent'],
				),
				'user_email'  => array(
					'pending'    => $user_email_pending,
					'processing' => 0,
					'retry'      => 0,
					'dlq'        => $user_email_failed,
					'sent'       => $counts['user_email_sent'],
				),
				'email'       => array(
					'pending'    => $total_email_pending,
					'processing' => 0,
					'retry'      => 0,
					'dlq'        => $admin_email_failed + $user_email_failed,
					'sent'       => $counts['admin_email_sent'] + $counts['user_email_sent'],
				),
				'crm'         => array(
					'pending'    => $crm_pending,
					'processing' => 0,
					'retry'      => 0,
					'dlq'        => $crm_failed,
					'sent'       => $counts['crm_sent'],
				),
			);

			// Derive overall status
			$status  = 'good';
			$message = __( 'Message processing healthy', 'contactin' );

			if ( $total_failed > 0 ) {
				$status  = 'critical';
				$message = sprintf( __( '%d messages have failed processing', 'contactin' ), $total_failed );
			} elseif ( $total_pending >= 10 ) {
				$status  = 'warning';
				$message = sprintf( __( '%d messages pending processing', 'contactin' ), $total_pending );
			}

			return array(
				'pending'    => $total_pending,
				'processing' => 0, // No longer using processing state
				'retry'      => 0, // Retries tracked differently now
				'dlq'        => $total_failed, // Failed = DLQ equivalent
				'status'     => $status,
				'message'    => $message,
				'per_type'   => $per_type,
			);
		} catch ( \Throwable $e ) {
			return array(
				'pending'    => 0,
				'processing' => 0,
				'retry'      => 0,
				'dlq'        => 0,
				'status'     => 'error',
				'message'    => __( 'Processing status unavailable', 'contactin' ),
				'per_type'   => array(),
			);
		}
	}

	/**
	 * Get message processing trend data per type for sparklines
	 */
	public function get_queue_trends_by_type( int $days = 7 ): array {
		try {
			$db     = \ContactInbox\Core\DB::instance();
			$trends = $db->get_message_status_trends( $days );

			// Transform to expected format: ['email' => [['date' => Y-m-d, 'count' => int], ...], ...]
			$email_trends = array();
			$crm_trends   = array();

			foreach ( $trends as $row ) {
				$email_pending = (int) ( $row->admin_pending ?? 0 ) + (int) ( $row->user_pending ?? 0 );
				$crm_pending   = (int) ( $row->crm_pending ?? 0 );

				$email_trends[] = array(
					'date'  => $row->date ?? '',
					'count' => $email_pending,
				);

				$crm_trends[] = array(
					'date'  => $row->date ?? '',
					'count' => $crm_pending,
				);
			}

			return array(
				'email' => $email_trends,
				'crm'   => $crm_trends,
			);
		} catch ( \Throwable $e ) {
			return array();
		}
	}

	/**
	 * Get email delivery rate from message status columns
	 *
	 * @return array ['rate' => float, 'status' => string, 'message' => string]
	 */
	public function get_email_delivery_rate( ?int $days = null, ?string $start_date = null, ?string $end_date = null ): array {
		try {
			// Reuse the same source and window as the health metrics so the performance card does not flicker
			$stats = $this->get_email_delivery_health( $days ?? 30, $start_date, $end_date );

			$rate  = (float) ( $stats['success_rate'] ?? 0 );
			$total = (int) ( $stats['total'] ?? 0 );

			if ( $total === 0 ) {
				return array(
					'rate'    => 0,
					'status'  => 'neutral',
					'message' => __( 'No emails sent', 'contactin' ),
				);
			}

			$status = $rate >= 90 ? 'good' : ( $rate >= 75 ? 'warning' : 'error' );

			return array(
				'rate'    => $rate,
				'status'  => $status,
				'message' => sprintf( __( '%s%% delivered', 'contactin' ), $rate ),
				'meta'    => array(
					'sent'   => (int) ( $stats['sent'] ?? 0 ),
					'failed' => (int) ( $stats['failed'] ?? 0 ),
					'total'  => (int) ( $stats['total'] ?? 0 ),
				),
			);
		} catch ( \Throwable $e ) {
			return array(
				'rate'    => 0,
				'status'  => 'error',
				'message' => __( 'Email stats unavailable', 'contactin' ),
			);
		}
	}

	/**
	 * Get API response statistics (last 24 hours)
	 *
	 * @return array ['time_ms' => int, 'status' => string, 'message' => string]
	 */
	public function get_api_stats( ?int $days = null, ?string $start_date = null, ?string $end_date = null ): array {
		try {
			global $wpdb;
			$table = $wpdb->prefix . Config::TABLE_REST_LOG;

			$where  = array();
			$params = array();

			if ( $start_date && $end_date ) {
				$where[]  = 'timestamp >= %s AND timestamp < DATE_ADD(%s, INTERVAL 1 DAY)';
				$params[] = $start_date;
				$params[] = $end_date;
			} elseif ( $days && $days > 0 ) {
				$where[]  = 'timestamp >= DATE_SUB(NOW(), INTERVAL %d DAY)';
				$params[] = $days;
			} else {
				$where[]  = 'timestamp >= DATE_SUB(NOW(), INTERVAL %d DAY)';
				$params[] = 30;
			}

			$where_clause = $where ? 'WHERE ' . implode( ' AND ', $where ) : '';

			$total = (int) $wpdb->get_var(
				$wpdb->prepare(
					"SELECT COUNT(*) FROM {$table} {$where_clause}",
					...$params
				)
			);

			if ( $total === 0 ) {
				return array(
					'total_requests' => 0,
					'total_errors'   => 0,
					'error_rate'     => 0,
					'status'         => 'neutral',
					'message'        => __( 'No API activity', 'contactin' ),
				);
			}

			$error_codes = (int) $wpdb->get_var(
				$wpdb->prepare(
					"SELECT COUNT(*) FROM {$table} {$where_clause} AND (response_code < 200 OR response_code >= 300)",
					...$params
				)
			);

			$error_rate = $total > 0 ? round( ( $error_codes / $total ) * 100, 1 ) : 0.0;

			$status = $error_rate < 5 ? 'good' : ( $error_rate < 10 ? 'warning' : 'error' );

			return array(
				'total_requests' => $total,
				'total_errors'   => $error_codes,
				'error_rate'     => $error_rate,
				'status'         => $status,
				'message'        => sprintf( __( '%s%% error rate', 'contactin' ), $error_rate ),
			);
		} catch ( \Throwable $e ) {
			return array(
				'total_requests' => 0,
				'total_errors'   => 0,
				'error_rate'     => 0,
				'status'         => 'error',
				'message'        => __( 'API stats unavailable', 'contactin' ),
			);
		}
	}

	/**
	 * Get CRM sync success rate from message crm_status column
	 *
	 * @return array ['rate' => float, 'status' => string, 'message' => string]
	 */
	public function get_crm_sync_rate( ?int $days = null, ?string $start_date = null, ?string $end_date = null ): array {
		try {
			$stats      = $this->get_crm_sync_stats( $days, $start_date, $end_date );
			$total      = (int) ( $stats['total'] ?? 0 );
			$successful = (int) ( $stats['successful'] ?? 0 );
			$failed     = (int) ( $stats['failed'] ?? 0 );
			$pending    = max( $total - $successful - $failed, 0 );
			$attempted  = $successful + $failed;
			$rate       = $attempted > 0 ? round( ( $successful / $attempted ) * 100, 1 ) : 0;

			if ( $total === 0 ) {
				return array(
					'rate'       => 0,
					'status'     => 'neutral',
					'message'    => __( 'No CRM activity', 'contactin' ),
					'successful' => 0,
					'failed'     => 0,
					'pending'    => 0,
					'total'      => 0,
					'attempted'  => 0,
				);
			}

			$status = $rate >= 90 ? 'good' : ( $rate >= 75 ? 'warning' : 'error' );

			return array(
				'rate'       => $rate,
				'status'     => $status,
				'message'    => sprintf( __( '%s%% successful', 'contactin' ), $rate ),
				'successful' => $successful,
				'failed'     => $failed,
				'pending'    => $pending,
				'total'      => $total,
				'attempted'  => $attempted,
				'operations' => __( 'sync + delete', 'contactin' ), // Clarify what's included
			);
		} catch ( \Throwable $e ) {
			return array(
				'rate'       => 0,
				'status'     => 'error',
				'message'    => __( 'CRM stats unavailable', 'contactin' ),
				'successful' => 0,
				'failed'     => 0,
				'pending'    => 0,
				'total'      => 0,
				'attempted'  => 0,
			);
		}
	}

	/**
	 * Get overall system status based on all metrics
	 *
	 * @return string 'good', 'warning', or 'error'
	 */
	public function get_system_status( ?int $days = null, ?string $start_date = null, ?string $end_date = null ): string {
		// Respect provided date range so system status matches dashboard filters
		$queue = $this->get_queue_health( $days, $start_date, $end_date );
		$email = $this->get_email_delivery_rate( $days, $start_date, $end_date );
		$api   = $this->get_api_stats( $days, $start_date, $end_date );
		$crm   = $this->get_crm_sync_rate( $days, $start_date, $end_date );

		$statuses = array(
			$queue['status'] ?? 'neutral',
			$email['status'] ?? 'neutral',
			$api['status'] ?? 'neutral',
			$crm['status'] ?? 'neutral',
		);

		// Count errors and warnings
		$error_count   = count( array_filter( $statuses, fn( $s ) => $s === 'error' ) );
		$warning_count = count( array_filter( $statuses, fn( $s ) => $s === 'warning' ) );

		// Only show error if 2+ components are failing (critical)
		if ( $error_count >= 2 ) {
			return 'error';
		}

		// Show warning if any errors or multiple warnings
		if ( $error_count >= 1 || $warning_count >= 2 ) {
			return 'warning';
		}

		return 'good';
	}

	/**
	 * Get aggregated daily metric from analytics_daily table
	 * (Faster than calculating on-the-fly after aggregation runs)
	 *
	 * @param string      $date Date in Y-m-d format
	 * @param string      $metric_type Type of metric
	 * @param string|null $form_id Optional form_id filter
	 * @return float|null The metric value or null if not found
	 */
	public function get_daily_metric( string $date, string $metric_type, ?string $form_id = null ): ?float {
		global $wpdb;
		$table = $wpdb->prefix . Config::TABLE_ANALYTICS_DAILY;

		$query = $wpdb->prepare(
			"SELECT value FROM {$table} WHERE date = %s AND metric_type = %s",
			$date,
			$metric_type
		);

		if ( $form_id ) {
			$query = $wpdb->prepare(
				"SELECT value FROM {$table} WHERE date = %s AND metric_type = %s AND form_id = %s",
				$date,
				$metric_type,
				$form_id
			);
		}

		$result = $wpdb->get_var( $query );
		return $result ? (float) $result : null;
	}

	/**
	 * Get daily metric trends for a date range
	 * (Uses pre-aggregated data for better performance)
	 *
	 * @param string      $metric_type Type of metric
	 * @param int         $days Number of days to retrieve
	 * @param string|null $form_id Optional form_id filter
	 * @return array Array of ['date' => 'Y-m-d', 'value' => float]
	 */
	public function get_daily_trends(
		string $metric_type,
		int $days = 7,
		?string $form_id = null
	): array {
		global $wpdb;
		$table = $wpdb->prefix . Config::TABLE_ANALYTICS_DAILY;

		$start_date = gmdate( 'Y-m-d', time() - ( $days * DAY_IN_SECONDS ) );
		$end_date   = gmdate( 'Y-m-d' );

		$query = $wpdb->prepare(
			"SELECT date, value FROM {$table} 
             WHERE metric_type = %s AND date BETWEEN %s AND %s",
			$metric_type,
			$start_date,
			$end_date
		);

		if ( $form_id ) {
			$query = $wpdb->prepare(
				"SELECT date, value FROM {$table} 
                 WHERE metric_type = %s AND form_id = %s AND date BETWEEN %s AND %s",
				$metric_type,
				$form_id,
				$start_date,
				$end_date
			);
		}

		$results = $wpdb->get_results( $query, ARRAY_A );

		return array_map(
			function ( $row ) {
				return array(
					'date'  => $row['date'],
					'value' => (float) $row['value'],
				);
			},
			$results ?? array()
		);
	}

	/**
	 * Get metric by date range (with optional aggregation)
	 *
	 * @param string $metric_type Type of metric
	 * @param string $start_date Start date in Y-m-d format
	 * @param string $end_date End date in Y-m-d format
	 * @param string $aggregate 'sum', 'avg', 'max', 'min'
	 * @return float|null Aggregated value or null
	 */
	public function get_metric_by_date_range(
		string $metric_type,
		string $start_date,
		string $end_date,
		string $aggregate = 'sum'
	): ?float {
		global $wpdb;
		$table = $wpdb->prefix . Config::TABLE_ANALYTICS_DAILY;

		$allowed_aggregates = array( 'sum', 'avg', 'max', 'min' );
		$aggregate          = in_array( strtolower( $aggregate ), $allowed_aggregates, true )
			? strtoupper( $aggregate )
			: 'SUM';

		$result = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT {$aggregate}(value) FROM {$table} 
             WHERE metric_type = %s AND date BETWEEN %s AND %s",
				$metric_type,
				$start_date,
				$end_date
			)
		);

		return $result ? (float) $result : null;
	}

	/**
	 * Get device distribution from analytics_events
	 *
	 * @param int $days Number of days to analyze
	 * @return array Device type => count
	 */
	public function get_device_distribution( ?int $days = null, ?string $start_date = null, ?string $end_date = null ): array {
		global $wpdb;
		$table = $wpdb->prefix . Config::TABLE_ANALYTICS_EVENTS;

		$where_parts  = array( 'device_type IS NOT NULL', 'submission_id IS NOT NULL' );
		$where_values = array();

		if ( $start_date && $end_date ) {
			$where_parts[]  = 'created_at >= %s AND created_at < DATE_ADD(%s, INTERVAL 1 DAY)';
			$where_values[] = $start_date;
			$where_values[] = $end_date;
		} elseif ( $days && $days > 0 ) {
			$where_parts[]  = 'created_at >= DATE_SUB(NOW(), INTERVAL %d DAY)';
			$where_values[] = $days;
		} else {
			// Default to 7 days if no parameters provided
			$where_parts[]  = 'created_at >= DATE_SUB(NOW(), INTERVAL %d DAY)';
			$where_values[] = 7;
		}

		$where_clause = 'WHERE ' . implode( ' AND ', $where_parts );
		$query        = "SELECT device_type, COUNT(*) as count FROM {$table} {$where_clause} GROUP BY device_type";

		Logger::debug(
			'Analytics device distribution query',
			array(
				'query'  => $query,
				'params' => $where_values,
			)
		);

		$results = ! empty( $where_values )
			? $wpdb->get_results( $wpdb->prepare( $query, ...$where_values ), ARRAY_A )
			: $wpdb->get_results( $query, ARRAY_A );

		Logger::debug(
			'Analytics device distribution results',
			array(
				'count' => $results ? count( $results ) : 0,
				'data'  => $results,
			)
		);

		$distribution = array();
		if ( ! empty( $results ) ) {
			foreach ( $results as $row ) {
				$distribution[ $row['device_type'] ] = (int) $row['count'];
			}
		}

		return $distribution;
	}

	/**
	 * Get geographic distribution from analytics_events
	 *
	 * @param int|null    $days Number of days to analyze
	 * @param string|null $start_date Start gmdate(YYYY-MM-DD)
	 * @param string|null $end_date End gmdate(YYYY-MM-DD)
	 * @return array Country code => count
	 */
	public function get_geographic_distribution( ?int $days = null, ?string $start_date = null, ?string $end_date = null ): array {
		global $wpdb;
		$table = $wpdb->prefix . Config::TABLE_ANALYTICS_EVENTS;

		$where_parts  = array( 'country IS NOT NULL', 'submission_id IS NOT NULL' );
		$where_values = array();

		if ( $start_date && $end_date ) {
			$where_parts[]  = 'created_at >= %s AND created_at < DATE_ADD(%s, INTERVAL 1 DAY)';
			$where_values[] = $start_date;
			$where_values[] = $end_date;
		} elseif ( $days && $days > 0 ) {
			$where_parts[]  = 'created_at >= DATE_SUB(NOW(), INTERVAL %d DAY)';
			$where_values[] = $days;
		} else {
			// Default to 7 days if no parameters provided
			$where_parts[]  = 'created_at >= DATE_SUB(NOW(), INTERVAL %d DAY)';
			$where_values[] = 7;
		}

		$where_clause = 'WHERE ' . implode( ' AND ', $where_parts );
		$query        = "SELECT country, COUNT(*) as count FROM {$table} {$where_clause} GROUP BY country ORDER BY count DESC LIMIT 20";

		$results = ! empty( $where_values )
			? $wpdb->get_results( $wpdb->prepare( $query, ...$where_values ), ARRAY_A )
			: $wpdb->get_results( $query, ARRAY_A );

		$distribution = array();
		if ( ! empty( $results ) ) {
			foreach ( $results as $row ) {
				$distribution[ $row['country'] ?? 'Unknown' ] = (int) $row['count'];
			}
		}

		return $distribution;
	}

	/**
	 * Get traffic sources (utm_source breakdown)
	 *
	 * @param int|null    $days Number of days to analyze
	 * @param string|null $start_date Start gmdate(YYYY-MM-DD)
	 * @param string|null $end_date End gmdate(YYYY-MM-DD)
	 * @return array Source => count
	 */
	public function get_traffic_sources( ?int $days = null, ?string $start_date = null, ?string $end_date = null ): array {
		global $wpdb;
		$table = $wpdb->prefix . Config::TABLE_ANALYTICS_EVENTS;

		$where_parts  = array( 'submission_id IS NOT NULL' );
		$where_values = array();

		if ( $start_date && $end_date ) {
			$where_parts[]  = 'created_at >= %s AND created_at < DATE_ADD(%s, INTERVAL 1 DAY)';
			$where_values[] = $start_date;
			$where_values[] = $end_date;
		} elseif ( $days && $days > 0 ) {
			$where_parts[]  = 'created_at >= DATE_SUB(NOW(), INTERVAL %d DAY)';
			$where_values[] = $days;
		} else {
			// Default to 7 days if no parameters provided
			$where_parts[]  = 'created_at >= DATE_SUB(NOW(), INTERVAL %d DAY)';
			$where_values[] = 7;
		}

		$where_clause = $where_parts ? 'WHERE ' . implode( ' AND ', $where_parts ) : '';
		$query        = "SELECT COALESCE(NULLIF(utm_source, ''), 'direct') as source, COUNT(*) as count FROM {$table} {$where_clause} GROUP BY source ORDER BY count DESC";

		$results = ! empty( $where_values )
			? $wpdb->get_results( $wpdb->prepare( $query, ...$where_values ), ARRAY_A )
			: $wpdb->get_results( $query, ARRAY_A );

		$sources = array();
		if ( ! empty( $results ) ) {
			foreach ( $results as $row ) {
				$sources[ $row['source'] ] = (int) $row['count'];
			}
		}

		return $sources;
	}

	/**
	 * Get browser distribution from analytics_events
	 *
	 * @param int|null    $days Number of days to analyze
	 * @param string|null $start_date Start gmdate(YYYY-MM-DD)
	 * @param string|null $end_date End gmdate(YYYY-MM-DD)
	 * @return array Browser name => count
	 */
	public function get_browser_distribution( ?int $days = null, ?string $start_date = null, ?string $end_date = null ): array {
		global $wpdb;
		$table = $wpdb->prefix . Config::TABLE_ANALYTICS_EVENTS;

		$where_parts  = array( 'browser IS NOT NULL', 'submission_id IS NOT NULL' );
		$where_values = array();

		if ( $start_date && $end_date ) {
			$where_parts[]  = 'created_at >= %s AND created_at < DATE_ADD(%s, INTERVAL 1 DAY)';
			$where_values[] = $start_date;
			$where_values[] = $end_date;
		} elseif ( $days && $days > 0 ) {
			$where_parts[]  = 'created_at >= DATE_SUB(NOW(), INTERVAL %d DAY)';
			$where_values[] = $days;
		} else {
			// Default to 7 days if no parameters provided
			$where_parts[]  = 'created_at >= DATE_SUB(NOW(), INTERVAL %d DAY)';
			$where_values[] = 7;
		}

		$where_clause = 'WHERE ' . implode( ' AND ', $where_parts );
		$query        = "SELECT browser, COUNT(*) as count FROM {$table} {$where_clause} GROUP BY browser ORDER BY count DESC";

		$results = ! empty( $where_values )
			? $wpdb->get_results( $wpdb->prepare( $query, ...$where_values ), ARRAY_A )
			: $wpdb->get_results( $query, ARRAY_A );

		$distribution = array();
		if ( ! empty( $results ) ) {
			foreach ( $results as $row ) {
				$distribution[ $row['browser'] ] = (int) $row['count'];
			}
		}

		return $distribution;
	}

	/**
	 * Get event count for a specific type
	 *
	 * @param string $event_type Type of event (e.g., 'form_view', 'form_submission')
	 * @param int    $days Days to analyze
	 * @return int Event count
	 */
	public function get_event_count( string $event_type, int $days = 7 ): int {
		global $wpdb;
		$table      = $wpdb->prefix . Config::TABLE_ANALYTICS_EVENTS;
		$start_date = gmdate( 'Y-m-d H:i:s', time() - ( $days * DAY_IN_SECONDS ) );

		$count = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$table}
             WHERE event_type = %s AND created_at >= %s",
				$event_type,
				$start_date
			)
		);

		return (int) ( $count ?? 0 );
	}

	/**
	 * Get email delivery health metrics
	 *
	 * @param int|null    $days Number of days to analyze (ignored when explicit dates are provided)
	 * @param string|null $start_date Optional start gmdate(YYYY-MM-DD)
	 * @param string|null $end_date Optional end gmdate(YYYY-MM-DD)
	 * @return array ['success_rate' => float, 'total' => int, 'sent' => int, 'failed' => int, 'top_failures' => array]
	 */
	public function get_email_delivery_health( ?int $days = 30, ?string $start_date = null, ?string $end_date = null ): array {
		global $wpdb;
		$table = $wpdb->prefix . Config::TABLE_EMAIL_LOG;
		$start = null;
		$end   = null;

		if ( $start_date && $end_date ) {
			$start = $start_date;
			$end   = $end_date;
		} elseif ( $days !== null && $days > 0 ) {
			$start = gmdate( 'Y-m-d', time() - ( $days * DAY_IN_SECONDS ) );
			$end   = gmdate( 'Y-m-d' );
		} else {
			$start = gmdate( 'Y-m-d', time() - ( 30 * DAY_IN_SECONDS ) );
			$end   = gmdate( 'Y-m-d' );
		}

		$date_clause = 'DATE(created_at) BETWEEN %s AND %s';

		// Get total emails in period
		$total = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$table} WHERE {$date_clause}",
				$start,
				$end
			)
		);

		// Optionally blend with message status counts to avoid understating deliveries when logs are sparse
		$sent_log   = 0;
		$failed_log = 0;

		if ( $total > 0 ) {
			$sent_log = (int) $wpdb->get_var(
				$wpdb->prepare(
					"SELECT COUNT(*) FROM {$table} WHERE {$date_clause} AND status = %s",
					$start,
					$end,
					'sent'
				)
			);

			$failed_log = (int) $wpdb->get_var(
				$wpdb->prepare(
					"SELECT COUNT(*) FROM {$table} WHERE {$date_clause} AND status = %s",
					$start,
					$end,
					'failed'
				)
			);
		}

		$sent_status   = 0;
		$failed_status = 0;
		try {
			$db_counts     = \ContactInbox\Core\DB::instance()->get_message_status_counts();
			$sent_status   = (int) ( $db_counts['admin_email_sent'] + $db_counts['user_email_sent'] );
			$failed_status = (int) ( $db_counts['admin_email_failed'] + $db_counts['user_email_failed'] );
		} catch ( \Throwable $e ) { // phpcs:ignore Generic.CodeAnalysis.EmptyStatement.DetectedCatch -- Intentional: status fallback unavailable; non-critical analytics.
			// ignore; status fallback unavailable
		}

		// Decide which source to use: prefer status counts when they show more attempted emails than the log (common after log cleanup)
		$attempted_log    = $sent_log + $failed_log;
		$attempted_status = $sent_status + $failed_status;

		if ( $attempted_status > $attempted_log ) {
			$sent   = $sent_status;
			$failed = $failed_status;
		} else {
			$sent   = $attempted_log > 0 ? $sent_log : $sent_status;
			$failed = $attempted_log > 0 ? $failed_log : $failed_status;
		}

		// Use the largest attempted count across sources to avoid dividing by a tiny log total
		$attempted_blend = max( $sent + $failed, $attempted_log, $attempted_status );

		if ( $attempted_blend === 0 ) {
			return array(
				'success_rate' => 0,
				'total'        => 0,
				'sent'         => 0,
				'failed'       => 0,
				'top_failures' => array(),
			);
		}

		// If we are using log data (attempted_log > 0), also surface top failures from the log window
		$failures = array();
		if ( $attempted_log > 0 ) {
			$failures = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT 
                    COALESCE(NULLIF(TRIM(error_message), ''), 'Unknown error') as error_message,
                    COUNT(*) as count 
                 FROM {$table}
                 WHERE {$date_clause} AND status = %s
                 GROUP BY error_message
                 ORDER BY count DESC
                 LIMIT 3",
					$start,
					$end,
					'failed'
				),
				ARRAY_A
			);
		}

		$top_failures = array();
		if ( ! empty( $failures ) ) {
			foreach ( $failures as $failure ) {
				$reason = $failure['error_message'] ?? __( 'Unknown error', 'contactin' );
				// Clean up the error message
				$reason = trim( $reason );
				if ( empty( $reason ) ) {
					$reason = __( 'Unknown error', 'contactin' );
				}
				// Truncate long messages
				if ( strlen( $reason ) > 150 ) {
					$reason = substr( $reason, 0, 147 ) . '...';
				}
				$top_failures[] = array(
					'reason' => $reason,
					'count'  => (int) $failure['count'],
				);
			}
		}

		$success_rate = $total > 0 ? round( ( $sent / $total ) * 100, 1 ) : 0;

		// Compute rate from blended attempts and reflect the same total in the card
		$success_rate = $attempted_blend > 0 ? round( ( $sent / $attempted_blend ) * 100, 1 ) : 0;

		return array(
			'success_rate' => $success_rate,
			'total'        => $attempted_blend,
			'sent'         => $sent,
			'failed'       => $failed,
			'top_failures' => $top_failures,
		);
	}

	/**
	 * Get CRM sync health metrics
	 *
	 * @param int $days Number of days to analyze
	 * @return array ['success_rate' => float, 'total' => int, 'successful' => int, 'failed' => int, 'top_failures' => array]
	 */
	public function get_crm_sync_health( int $days = 30 ): array {
		global $wpdb;
		$table      = $wpdb->prefix . Config::TABLE_REST_LOG;
		$start_date = gmdate( 'Y-m-d', time() - ( $days * DAY_IN_SECONDS ) );

		// Get total CRM sync attempts
		$total = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$table} 
             WHERE DATE(timestamp) >= %s AND endpoint LIKE %s",
				$start_date,
				'%crm%'
			)
		);

		if ( $total === 0 ) {
			return array(
				'success_rate' => 0,
				'total'        => 0,
				'successful'   => 0,
				'failed'       => 0,
				'top_failures' => array(),
			);
		}

		// Count successful syncs (2xx response codes)
		$successful = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$table}
             WHERE DATE(timestamp) >= %s AND endpoint LIKE %s AND response_code >= 200 AND response_code < 300",
				$start_date,
				'%crm%'
			)
		);

		// Count failed syncs
		$failed = $total - $successful;

		// Get top 3 failure reasons (error messages)
		$failures = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT 
                COALESCE(NULLIF(TRIM(response_body), ''), 'Unknown CRM error') as response_body,
                COUNT(*) as count 
             FROM {$table}
             WHERE DATE(timestamp) >= %s AND endpoint LIKE %s AND (response_code < 200 OR response_code >= 300)
             GROUP BY response_body
             ORDER BY count DESC
             LIMIT 3",
				$start_date,
				'%crm%'
			),
			ARRAY_A
		);

		$top_failures = array();
		if ( ! empty( $failures ) ) {
			foreach ( $failures as $failure ) {
				$reason = $failure['response_body'] ?? __( 'Unknown error', 'contactin' );
				// Clean up the error message
				$reason = trim( $reason );
				if ( empty( $reason ) ) {
					$reason = __( 'Unknown CRM error', 'contactin' );
				}
				// Try to parse JSON error messages
				if ( substr( $reason, 0, 1 ) === '{' || substr( $reason, 0, 1 ) === '[' ) {
					$decoded = json_decode( $reason, true );
					if ( isset( $decoded['message'] ) ) {
						$reason = $decoded['message'];
					} elseif ( isset( $decoded['error'] ) ) {
						$reason = is_array( $decoded['error'] ) ? ( $decoded['error']['message'] ?? 'CRM error' ) : $decoded['error'];
					}
				}
				// Truncate long error messages
				if ( strlen( $reason ) > 150 ) {
					$reason = substr( $reason, 0, 147 ) . '...';
				}
				$top_failures[] = array(
					'reason' => $reason,
					'count'  => (int) $failure['count'],
				);
			}
		}

		$success_rate = round( ( $successful / $total ) * 100, 1 );

		return array(
			'success_rate' => $success_rate,
			'total'        => $total,
			'successful'   => $successful,
			'failed'       => $failed,
			'top_failures' => $top_failures,
		);
	}

	/**
	 * Get spam intelligence metrics (reCAPTCHA score analysis)
	 *
	 * @param int $days Number of days to analyze
	 * @return array ['avg_score' => float, 'spam_percentage' => float, 'total' => int, 'spam_count' => int]
	 */
	public function get_spam_intelligence( int $days = 30 ): array {
		global $wpdb;
		$table      = $wpdb->prefix . Config::TABLE_MESSAGES;
		$start_date = gmdate( 'Y-m-d', time() - ( $days * DAY_IN_SECONDS ) );

		// Get total messages with recaptcha scores
		$total = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$table}
             WHERE DATE(submitted_at) >= %s AND recaptcha_score IS NOT NULL",
				$start_date
			)
		);

		if ( $total === 0 ) {
			return array(
				'avg_score'       => 0,
				'spam_percentage' => 0,
				'total'           => 0,
				'spam_count'      => 0,
			);
		}

		// Get average recaptcha score
		$avg_score_result = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT AVG(recaptcha_score) FROM {$table}
             WHERE DATE(submitted_at) >= %s AND recaptcha_score IS NOT NULL",
				$start_date
			)
		);
		$avg_score        = $avg_score_result ? (float) $avg_score_result : 0;

		// Count spam submissions (score < 0.5)
		$spam_count = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$table}
             WHERE DATE(submitted_at) >= %s AND recaptcha_score IS NOT NULL AND recaptcha_score < 0.5",
				$start_date
			)
		);

		$spam_percentage = round( ( $spam_count / $total ) * 100, 1 );

		return array(
			'avg_score'       => round( $avg_score, 2 ),
			'spam_percentage' => $spam_percentage,
			'total'           => $total,
			'spam_count'      => $spam_count,
		);
	}
}
