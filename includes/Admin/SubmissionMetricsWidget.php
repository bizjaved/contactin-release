<?php
/**
 * Submission Metrics Dashboard Widget
 *
 * Displays submission-focused metrics on WordPress dashboard.
 * Uses AnalyticsRepository for all data queries.
 * Renders via template with no inline styles or logic.
 *
 * @package ContactIn\Admin
 */

declare(strict_types=1);

namespace ContactInbox\Admin;

use ContactInbox\Core\Config;
use ContactInbox\Core\Repositories\AnalyticsRepository;
use ContactInbox\Traits\Singleton;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class SubmissionMetricsWidget {
	use Singleton;

	protected function __construct() {
		if ( is_admin() ) {
			add_action( 'wp_dashboard_setup', array( $this, 'register_widget' ), 11 );
		}
	}

	/**
	 * Register the dashboard widget
	 */
	public function register_widget(): void {
		if ( ! is_admin() || ! current_user_can( 'manage_options' ) ) {
			return;
		}

		wp_add_dashboard_widget(
			'contactin_submission_metrics',
			__( 'ContactIn - Submission Analytics', 'contactin' ),
			array( $this, 'render_widget' )
		);
	}

	/**
	 * Render the widget
	 */
	public function render_widget(): void {
		try {
			$analytics = new AnalyticsRepository();

			// Get all data via repository (all from TODAY for consistency)
			$today           = current_time( 'Y-m-d' );
			$today_count     = $analytics->get_submission_count_today();
			$emails_sent     = $analytics->get_emails_sent_today();
			$crm_synced      = $analytics->get_crm_synced_today();
			$conversion_rate = $analytics->get_conversion_rate_today();

			$trend_days   = 7;
			$trend_values = $analytics->get_daily_submission_trend( $trend_days );
			$trend_labels = array();
			$now          = time();
			for ( $i = $trend_days - 1; $i >= 0; $i-- ) {
				$trend_labels[] = date_i18n( 'M j', $now - ( $i * DAY_IN_SECONDS ) );
			}
			$analytics_url = add_query_arg( array( 'page' => 'contactin-analytics' ), admin_url( 'admin.php' ) );

			// Load template
			$template = CONTACTINBOX_PATH . Config::TEMPLATE_ADMIN . 'widgets/submission-metrics-widget.php';

			if ( file_exists( $template ) ) {
				include $template;
			} else {
				echo '<div class="notice notice-error"><p>' .
					esc_html__( 'Submission metrics widget template not found.', 'contactin' ) .
					'</p></div>';
			}
		} catch ( \Exception $e ) {
			echo '<div class="notice notice-error"><p>' .
				esc_html__( 'Error loading submission metrics: ', 'contactin' ) .
				esc_html( $e->getMessage() ) .
				'</p></div>';
		}
	}
}
