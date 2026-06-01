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

	private const SUBMISSION_WIDGET_SCRIPT_HANDLE = 'contactin-submission-widget-chart';

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
			$conversion_rate = $analytics->get_conversion_rate_today();

			$trend_days   = 7;
			$trend_values = $analytics->get_daily_submission_trend( $trend_days );
			$trend_labels = array();
			$now          = time();
			for ( $i = $trend_days - 1; $i >= 0; $i-- ) {
				$trend_labels[] = date_i18n( 'M j', $now - ( $i * DAY_IN_SECONDS ) );
			}
			$analytics_url = add_query_arg( array( 'page' => 'contactin-analytics' ), admin_url( 'admin.php' ) );
			$this->enqueue_chart_script( $trend_labels, $trend_values );

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

	/**
	 * Attach chart rendering script with data using enqueue APIs.
	 *
	 * @param array $labels Trend labels.
	 * @param array $values Trend values.
	 */
	private function enqueue_chart_script( array $labels, array $values ): void {
		wp_register_script(
			self::SUBMISSION_WIDGET_SCRIPT_HANDLE,
			false,
			array( 'chart-js' ),
			CONTACTINBOX_VERSION,
			true
		);
		wp_enqueue_script( self::SUBMISSION_WIDGET_SCRIPT_HANDLE );

		wp_add_inline_script(
			self::SUBMISSION_WIDGET_SCRIPT_HANDLE,
			'window.contactinSubmissionWidgetData=' . wp_json_encode(
				array(
					'labels' => $labels,
					'values' => $values,
				)
			) . ';',
			'before'
		);

		wp_add_inline_script(
			self::SUBMISSION_WIDGET_SCRIPT_HANDLE,
			"(function(){const chartData=window.contactinSubmissionWidgetData||{labels:[],values:[]};const data=Array.isArray(chartData.values)?chartData.values:[];const labels=Array.isArray(chartData.labels)?chartData.labels:[];const ctx=document.getElementById('contactin-submission-sparkline');if(ctx&&window.Chart){new Chart(ctx,{type:'line',data:{labels:labels,datasets:[{label:'Submissions',data:data,borderColor:'#0073aa',backgroundColor:'rgba(0, 115, 170, 0.1)',borderWidth:2,fill:true,tension:0.4,pointRadius:4,pointBackgroundColor:'#0073aa',pointBorderColor:'#fff',pointBorderWidth:2}]},options:{responsive:true,maintainAspectRatio:true,plugins:{legend:{display:false}},scales:{y:{beginAtZero:true,max:Math.max(...data,1)+1}}}});}})();",
			'after'
		);
	}
}
