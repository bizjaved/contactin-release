<?php
/**
 * Integration Status Center Dashboard Widget
 *
 * Displays health status of all integrations (Salesforce, Email, REST API).
 * Shows sync rates, health indicators, and last check times.
 * Follows gold-standard BI widget patterns with status indicators.
 *
 * @package ContactIn\Admin
 */

declare(strict_types=1);

namespace ContactInbox\Admin;

use ContactInbox\Core\Config;
use ContactInbox\Core\Repositories\AnalyticsRepository;
use ContactInbox\Core\CRMMonitor;
use ContactInbox\Traits\Singleton;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class IntegrationStatusWidget {
	use Singleton;

	protected function __construct() {
		if ( is_admin() ) {
			add_action( 'wp_dashboard_setup', array( $this, 'register_widget' ), 12 );
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
			'contactin_integration_status',
			__( 'ContactIn - Integration Status', 'contactin' ),
			array( $this, 'render_widget' )
		);
	}

	/**
	 * Render the widget
	 */
	public function render_widget(): void {
		$analytics = new AnalyticsRepository();

		// Get integration data
		$email = $analytics->get_email_delivery_rate();
		$api   = $analytics->get_api_stats( 1 );
		$crm   = $analytics->get_crm_sync_rate();

		// Get CRM details from monitor
		$crm_stats  = CRMMonitor::get_statistics();
		$crm_health = CRMMonitor::get_health_status();

		// Calculate status indicators
		$email_status = $this->get_status_badge( $email['status'] );
		$api_status   = $this->get_status_badge( $api['status'] );
		$crm_status   = $this->get_status_badge( $crm['status'] );

		// Analytics URL for drill-down
		$analytics_url = add_query_arg( array( 'page' => 'contactin-analytics' ), admin_url( 'admin.php' ) );

		// Load template
		$template = CONTACTINBOX_PATH . Config::TEMPLATE_ADMIN . 'widgets/integration-status-widget.php';

		if ( file_exists( $template ) ) {
			include $template;
		} else {
			echo '<div class="notice notice-error"><p>' .
				esc_html__( 'Integration status widget template not found.', 'contactin' ) .
				'</p></div>';
		}
	}

	/**
	 * Convert status string to badge HTML
	 */
	private function get_status_badge( string $status ): string {
		switch ( strtolower( $status ) ) {
			case 'good':
				return '✓';
			case 'warning':
				return '⚠';
			case 'error':
				return '✗';
			case 'healthy':
				return '✓';
			case 'critical':
				return '✗';
			default:
				return '–';
		}
	}
}
