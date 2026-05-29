<?php
/**
 * AJAX Dispatcher
 *
 * Registers and dispatches all AJAX handlers.
 *
 * @package ContactIn\Admin\AJAX
 */

declare(strict_types=1);

namespace ContactInbox\Admin\AJAX;

use ContactInbox\Core\Repositories\AnalyticsRepository;
use ContactInbox\Core\Config;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class AJAXDispatcher {

	private AnalyticsRepository $analytics;

	public function __construct( AnalyticsRepository $analytics = null ) {
		$this->analytics = $analytics ?? new AnalyticsRepository();
	}

	/**
	 * Register all AJAX handlers
	 */
	public function register_handlers(): void {
		// Submissions data
		add_action( 'wp_ajax_contactin_get_submissions_data', array( $this, 'handle_submissions_data' ) );

		// Performance and queue data
		add_action( 'wp_ajax_contactin_get_performance_data', array( $this, 'handle_performance_data' ) );
		add_action( 'wp_ajax_contactin_get_queue_stats', array( $this, 'handle_queue_stats' ) );

		// Users and device data
		add_action( 'wp_ajax_contactin_get_users_data', array( $this, 'handle_users_data' ) );

		// Health metrics
		add_action( 'wp_ajax_contactin_get_health_metrics', array( $this, 'handle_health_metrics' ) );

		// Cron status
		add_action( 'wp_ajax_contactin_get_cron_status', array( $this, 'handle_cron_status' ) );

		// Dashboard widgets (real-time monitoring)
		add_action( 'wp_ajax_contactin_get_dashboard_widgets', array( $this, 'handle_dashboard_widgets' ) );

		// Reports export
		add_action( 'wp_ajax_contactin_export_report', array( $this, 'handle_export_report' ) );

	}

	public function handle_submissions_data(): void {
		$this->verify_dispatch_request();
		$handler = new SubmissionsDataHandler( $this->analytics );
		$handler->handle();
	}

	public function handle_performance_data(): void {
		$this->verify_dispatch_request();
		$handler = new PerformanceDataHandler( $this->analytics );
		$handler->handle_performance();
	}

	public function handle_queue_stats(): void {
		$this->verify_dispatch_request();
		$handler = new PerformanceDataHandler( $this->analytics );
		$handler->handle_queue_stats();
	}

	public function handle_users_data(): void {
		$this->verify_dispatch_request();
		$handler = new UsersDataHandler( $this->analytics );
		$handler->handle();
	}

	public function handle_health_metrics(): void {
		$this->verify_dispatch_request();
		$handler = new HealthMetricsHandler( $this->analytics );
		$handler->handle();
	}

	public function handle_cron_status(): void {
		$this->verify_dispatch_request();
		$handler = new CronStatusHandler( $this->analytics );
		$handler->handle();
	}

	public function handle_dashboard_widgets(): void {
		$this->verify_dispatch_request();
		$handler = new DashboardWidgetsHandler( $this->analytics );
		$handler->handle();
	}

	public function handle_export_report(): void {
		$this->verify_dispatch_request();
		// Phase 3E: Implement CSV/PDF export
		wp_send_json_success(
			array(
				'message' => 'Export functionality coming in Phase 3E',
			)
		);
	}

	/**
	 * Verify admin AJAX requests across dashboard endpoints.
	 *
	 * Accepts legacy and current nonce actions for backward compatibility.
	 */
	private function verify_dispatch_request(): void {
		$nonce = isset( $_POST['nonce'] ) ? sanitize_text_field( wp_unslash( (string) $_POST['nonce'] ) ) : '';

		if ( '' === $nonce ) {
			wp_send_json_error( array( 'message' => __( 'Invalid nonce.', 'contactin' ) ), 403 );
			exit;
		}

		$valid = false;
		foreach ( array( 'contactinbox_nonce_action', 'contactin_nonce_action', Config::NONCE_ACTION, Config::SETTINGS_NONCE_ACTION, Config::INBOX_NONCE_ACTION ) as $action ) {
			if ( wp_verify_nonce( $nonce, $action ) ) {
				$valid = true;
				break;
			}
		}

		if ( ! $valid ) {
			wp_send_json_error( array( 'message' => __( 'Invalid nonce.', 'contactin' ) ), 403 );
			exit;
		}

		if ( ! current_user_can( Config::CAPABILITY ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'contactin' ) ), 403 );
			exit;
		}
	}
}
