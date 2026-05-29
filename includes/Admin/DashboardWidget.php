<?php
/**
 * ContactIn – Dashboard Widget
 *
 * Displays comprehensive inbox statistics on WordPress dashboard:
 * - Time-based message counts (Today, This Week, This Month, This Year)
 * - Status breakdown (Read vs Unread)
 * - Visual chart showing message trends
 * - Recent message preview
 * - Quick link to inbox
 *
 * Uses template for HTML rendering, respects strict architectural patterns
 *
 * @package ContactIn\Admin
 */

namespace ContactInbox\Admin;

use ContactInbox\Core\Config;
use ContactInbox\Core\Repositories\MessageRepository;
use ContactInbox\Traits\Singleton;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class DashboardWidget {
	use Singleton;

	private const DASHBOARD_WIDGET_SCRIPT_HANDLE = 'contactin-dashboard-widget-chart';

	private MessageRepository $message_repo;

	protected function __construct() {
		$this->message_repo = new MessageRepository();

		// Only register hooks in admin
		if ( is_admin() ) {
			add_action( 'wp_dashboard_setup', array( $this, 'register_widget' ), 10 );
			add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		}
	}

	/**
	 * Enqueue Chart.js library for graphs
	 */
	public function enqueue_assets(): void {
		// Only load on dashboard page
		$current_screen = get_current_screen();
		if ( ! $current_screen || $current_screen->id !== 'dashboard' ) {
			return;
		}

		// Enqueue admin global CSS for dashboard widget styling
		$css_path = CONTACTINBOX_PATH . Config::DIST_CSS . 'admin-global.min.css';
		$css_url  = CONTACTINBOX_URL . Config::DIST_CSS . 'admin-global.min.css';
		if ( file_exists( $css_path ) ) {
			wp_enqueue_style(
				'contactin-admin-global',
				$css_url,
				array(),
				filemtime( $css_path )
			);
		}

		wp_enqueue_script(
			'chart-js',
			Config::URL . 'dist/js/vendor/chart.min.js',
			array(),
			'4.5.1',
			false
		);

		wp_register_script(
			self::DASHBOARD_WIDGET_SCRIPT_HANDLE,
			false,
			array( 'chart-js' ),
			CONTACTINBOX_VERSION,
			true
		);
		wp_enqueue_script( self::DASHBOARD_WIDGET_SCRIPT_HANDLE );
	}

	/**
	 * Register the dashboard widget
	 */
	public function register_widget(): void {
		// Check if we're in admin
		if ( ! is_admin() ) {
			return;
		}

		// Check capability
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		// Register the dashboard widget
		wp_add_dashboard_widget(
			Config::DASHBOARD_WIDGET_ID,
			__( 'ContactIn - Messages Status', 'contactin' ),
			array( $this, 'render_widget' )
		);
	}

	/**
	 * Get message count for a specific time period
	 */
	private function get_count_by_period( string $period ): int {
		return $this->message_repo->count_by_period( $period );
	}

	/**
	 * Get messages by status
	 */
	private function get_count_by_status( string $status ): int {
		return $this->message_repo->count( '', $status );
	}

	/**
	 * Get 7-day trend data for chart
	 */
	private function get_seven_day_trend(): array {
		// Repository returns an ordered array of label/count pairs; convert to label => count map for the widget
		$trend  = $this->message_repo->get_trend( 7 );
		$mapped = array();
		foreach ( $trend as $row ) {
			$mapped[ $row['label'] ] = $row['count'];
		}
		return $mapped;
	}

	/**
	 * Render the dashboard widget using template
	 */
	public function render_widget(): void {
		// Get time-based counts
		$today = $this->get_count_by_period( 'today' );
		$week  = $this->get_count_by_period( 'week' );
		$month = $this->get_count_by_period( 'month' );
		$year  = $this->get_count_by_period( 'year' );

		// Get status breakdown
		$unread_count = $this->get_count_by_status( 'unread' );
		$read_count   = $this->get_count_by_status( 'read' );
		$total_count  = $unread_count + $read_count;

		// Get recent message
		$recent_messages = $this->message_repo->get_paginated( 1, 1 );
		$recent_message  = ! empty( $recent_messages ) ? $recent_messages[0] : null;

		// Get 7-day trend data
		$trend_data = $this->get_seven_day_trend();
		$this->enqueue_widget_chart_script( $trend_data );

		// Build inbox URL
		$inbox_url = add_query_arg(
			array( 'page' => Config::MENU_INBOX ),
			admin_url( 'admin.php' )
		);

		// Load template with proper template path
		$template = CONTACTINBOX_PATH . Config::TEMPLATE_ADMIN . 'dashboard-widget.php';

		if ( file_exists( $template ) ) {
			// Include template with variables in parent scope
			include $template;
		} else {
			echo '<div class="notice notice-error"><p>'
				. esc_html__( 'Dashboard widget template not found.', 'contactin' )
				. '</p></div>';
		}
	}

	/**
	 * Attach chart initialization script via enqueue API.
	 *
	 * @param array $trend_data Chart label=>count map.
	 */
	private function enqueue_widget_chart_script( array $trend_data ): void {
		wp_add_inline_script(
			self::DASHBOARD_WIDGET_SCRIPT_HANDLE,
			'window.contactinDashboardWidgetData=' . wp_json_encode(
				array(
					'labels' => array_keys( $trend_data ),
					'values' => array_values( $trend_data ),
				)
			) . ';',
			'before'
		);

		wp_add_inline_script(
			self::DASHBOARD_WIDGET_SCRIPT_HANDLE,
			"document.addEventListener('DOMContentLoaded',function(){const chartData=window.contactinDashboardWidgetData||{labels:[],values:[]};const ctx=document.getElementById('contactin-trend-chart');if(!ctx||!window.Chart){return;}const labels=Array.isArray(chartData.labels)?chartData.labels:[];const values=Array.isArray(chartData.values)?chartData.values:[];if(labels.length===0){return;}new Chart(ctx,{type:'line',data:{labels:labels,datasets:[{label:'Messages',data:values,borderColor:'#0073aa',backgroundColor:'rgba(0, 115, 170, 0.1)',borderWidth:2,fill:true,tension:0.4,pointRadius:4,pointBackgroundColor:'#0073aa',pointBorderColor:'#fff',pointBorderWidth:2}]},options:{responsive:true,maintainAspectRatio:true,plugins:{legend:{display:false}},scales:{y:{beginAtZero:true,max:Math.max(...values,1)+1}}}});});",
			'after'
		);
	}
}
