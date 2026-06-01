<?php
/**
 * Analytics Dashboard Admin Page
 *
 * Main analytics hub showing comprehensive business intelligence:
 * - Submission analytics (volume, conversion, funnel)
 * - Performance metrics (latency, response times, errors)
 * - User analytics (device, geographic, source)
 * - Custom reports and exports
 *
 * @package ContactIn\Admin\Pages
 */

declare(strict_types=1);

namespace ContactInbox\Admin\Pages;

use ContactInbox\Core\Config;
use ContactInbox\Core\Repositories\AnalyticsRepository;
use ContactInbox\Admin\AJAX\AJAXDispatcher;
use ContactInbox\Traits\Singleton;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class AnalyticsDashboard {
	use Singleton;

	private AnalyticsRepository $analytics;
	private AJAXDispatcher $ajax_dispatcher;

	protected function __construct() {
		$this->analytics       = new AnalyticsRepository();
		$this->ajax_dispatcher = new AJAXDispatcher( $this->analytics );

		// Register AJAX handlers via dispatcher
		$this->ajax_dispatcher->register_handlers();
	}

	/**
	 * Render the analytics dashboard page
	 */
	public static function render(): void {
		if ( ! current_user_can( Config::CAPABILITY ) ) {
			wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'contactin' ) );
		}

		$instance = self::instance();

		// Get initial data for dashboard header cards (7-day focus)
		$submissions_7d      = array_sum( $instance->analytics->get_daily_submission_trend( 7 ) );
		$email_delivery      = $instance->analytics->get_email_delivery_rate();
		$queue_success_rates = array_intersect_key(
			$instance->analytics->get_queue_success_rates_by_type( 7 ),
			array( 'email' => true )
		);

		// Calculate overall queue processing rate
		$queue_rates           = array_filter( $queue_success_rates, fn( $rate ) => $rate !== null );
		$queue_processing_rate = ! empty( $queue_rates ) ? round( array_sum( $queue_rates ) / count( $queue_rates ), 1 ) : null;

		// Legacy data for backward compatibility
		$status_breakdown     = $instance->analytics->get_submission_status_breakdown();
		$queue_health         = $instance->analytics->get_queue_health();
		$queue_stats_by_type  = $queue_health['per_type'] ?? array();
		$queue_trends_by_type = $instance->analytics->get_queue_trends_by_type( 7 );

		// Localize sparkline trend data for JavaScript
		wp_localize_script(
			'contactin-dashboard-sparkline',
			'contactinSparklineTrends',
			array(
				'email' => $queue_trends_by_type['email'] ?? array(),
			)
		);

		// Load template
		$template = CONTACTINBOX_PATH . Config::TEMPLATE_ADMIN . 'analytics-dashboard-page.php';

		if ( file_exists( $template ) ) {
			include $template;
		} else {
			echo '<div class="notice notice-error"><p>' .
				esc_html__( 'Analytics dashboard template not found.', 'contactin' ) .
				'</p></div>';
		}
	}
}
