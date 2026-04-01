<?php
/**
 * Dashboard Widgets AJAX Handler
 *
 * Provides real-time metrics for WordPress dashboard widgets.
 *
 * @package ContactIn\Admin\AJAX
 */

declare(strict_types=1);

namespace ContactInbox\Admin\AJAX;

use ContactInbox\Core\CRMMonitor;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class DashboardWidgetsHandler extends BaseAJAXHandler {

	public function handle(): void {
		$this->verify();

		try {
			$today            = current_time( 'Y-m-d' );
			$today_count      = $this->analytics->get_submission_count_today();
			$status_breakdown = $this->analytics->get_submission_status_breakdown( 1, $today, $today );
			$conversion_rate  = $this->analytics->get_conversion_rate_today();
			$trend_data       = $this->analytics->get_daily_submission_trend( 7 );

			$completed        = (int) ( $status_breakdown['completed'] ?? 0 );
			$failed           = (int) ( $status_breakdown['failed'] ?? 0 );
			$completion_total = $completed + $failed;
			$percent_base     = $completion_total > 0 ? $completion_total : 1;

			$completed_pct   = $completed > 0 ? round( ( $completed / $percent_base ) * 100 ) : 0;
			$failed_pct      = $failed > 0 ? round( ( $failed / $percent_base ) * 100 ) : 0;
			$completion_rate = $completion_total > 0 ? round( ( $completed / $completion_total ) * 100 ) : 0;

			$queue         = $this->analytics->get_queue_health();
			$email         = $this->analytics->get_email_delivery_rate();
			$api           = $this->analytics->get_api_stats( 1 );
			$crm           = $this->analytics->get_crm_sync_rate();
			$system_status = $this->analytics->get_system_status();

			$crm_health = CRMMonitor::get_health_status();

			wp_send_json_success(
				array(
					'snapshot'    => array(
						'total_submissions' => $today_count,
						'completed'         => $completed,
						'failed'            => $failed,
						'completed_pct'     => $completed_pct,
						'failed_pct'        => $failed_pct,
						'system_health'     => $system_status,
					),
					'submissions' => array(
						'today_count'     => $today_count,
						'conversion_rate' => $conversion_rate,
						'completed'       => $completed,
						'failed'          => $failed,
						'completion_rate' => $completion_rate,
						'trend'           => $trend_data,
					),
					'performance' => array(
						'queue'         => $queue,
						'email'         => $email,
						'api'           => $api,
						'crm'           => $crm,
						'system_status' => $system_status,
					),
					'integration' => array(
						'crm'        => $crm,
						'crm_health' => $crm_health,
						'email'      => $email,
						'api'        => $api,
					),
				)
			);
		} catch ( \Exception $e ) {
			$this->handle_error( $e );
		}
	}
}
