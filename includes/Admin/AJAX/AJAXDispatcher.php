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
use ContactInbox\Admin\AJAX\LearningHandler;

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

		// CRM data
		add_action( 'wp_ajax_contactin_get_crm_data', array( $this, 'handle_crm_data' ) );
		add_action( 'wp_ajax_ci_get_crm_logs', array( $this, 'handle_crm_logs' ) );

		// Health metrics
		add_action( 'wp_ajax_contactin_get_health_metrics', array( $this, 'handle_health_metrics' ) );

		// Cron status
		add_action( 'wp_ajax_contactin_get_cron_status', array( $this, 'handle_cron_status' ) );

		// Dashboard widgets (real-time monitoring)
		add_action( 'wp_ajax_contactin_get_dashboard_widgets', array( $this, 'handle_dashboard_widgets' ) );

		// Reports export
		add_action( 'wp_ajax_contactin_export_report', array( $this, 'handle_export_report' ) );

		// Pro: Intent Classifier Self-Learning (Pro feature)
		add_action( 'wp_ajax_contactin_learning_report', array( $this, 'handle_learning_report' ) );
		add_action( 'wp_ajax_contactin_apply_recommendation', array( $this, 'handle_apply_recommendation' ) );
		add_action( 'wp_ajax_contactin_export_learning_data', array( $this, 'handle_export_learning_data' ) );
		add_action( 'wp_ajax_contactin_get_message_corrections', array( $this, 'handle_get_message_corrections' ) );
		add_action( 'wp_ajax_contactin_learning_stats', array( $this, 'handle_learning_stats' ) );
		add_action( 'wp_ajax_contactin_trigger_learning', array( $this, 'handle_trigger_learning' ) );
	}

	public function handle_submissions_data(): void {
		$handler = new SubmissionsDataHandler( $this->analytics );
		$handler->handle();
	}

	public function handle_performance_data(): void {
		$handler = new PerformanceDataHandler( $this->analytics );
		$handler->handle_performance();
	}

	public function handle_queue_stats(): void {
		$handler = new PerformanceDataHandler( $this->analytics );
		$handler->handle_queue_stats();
	}

	public function handle_users_data(): void {
		$handler = new UsersDataHandler( $this->analytics );
		$handler->handle();
	}

	public function handle_crm_data(): void {
		// PREMIUM FEATURE: CRM data only in Pro
		if ( ! \ContactInbox\Integration\FreemiusIntegration::can_use_premium_features() ) {
			wp_send_json_error( esc_html__( 'CRM integration is only available in ContactIn Pro.', 'contactin' ) );
		}
		$handler = new CRMDataHandler( $this->analytics );
		$handler->handle();
	}

	public function handle_crm_logs(): void {
		// PREMIUM FEATURE: CRM logs only in Pro
		if ( ! \ContactInbox\Integration\FreemiusIntegration::can_use_premium_features() ) {
			wp_send_json_error( esc_html__( 'CRM integration is only available in ContactIn Pro.', 'contactin' ) );
		}
		$handler = new CRMDataHandler( $this->analytics );
		$handler->handle_logs();
	}

	public function handle_health_metrics(): void {
		$handler = new HealthMetricsHandler( $this->analytics );
		$handler->handle();
	}

	public function handle_cron_status(): void {
		$handler = new CronStatusHandler( $this->analytics );
		$handler->handle();
	}

	public function handle_dashboard_widgets(): void {
		$handler = new DashboardWidgetsHandler( $this->analytics );
		$handler->handle();
	}

	public function handle_export_report(): void {
		// Phase 3E: Implement CSV/PDF export
		check_ajax_referer( 'contactin_nonce_action', 'nonce' );
		wp_send_json_success(
			array(
				'message' => 'Export functionality coming in Phase 3E',
			)
		);
	}

	// ==================== Pro: Intent Learning Handlers ====================

	public function handle_learning_report(): void {
		// PREMIUM FEATURE: Intent Classification/Learning only in Pro
		if ( ! \ContactInbox\Integration\FreemiusIntegration::can_use_premium_features() ) {
			wp_send_json_error( esc_html__( 'Intent Classification is only available in ContactIn Pro.', 'contactin' ) );
		}
		$handler = new LearningHandler( $this->analytics );
		$handler->handle_learning_report();
	}

	public function handle_apply_recommendation(): void {
		// PREMIUM FEATURE: Intent Classification/Learning only in Pro
		if ( ! \ContactInbox\Integration\FreemiusIntegration::can_use_premium_features() ) {
			wp_send_json_error( esc_html__( 'Intent Classification is only available in ContactIn Pro.', 'contactin' ) );
		}
		$handler = new LearningHandler( $this->analytics );
		$handler->handle_apply_recommendation();
	}

	public function handle_export_learning_data(): void {
		// PREMIUM FEATURE: Intent Classification/Learning only in Pro
		if ( ! \ContactInbox\Integration\FreemiusIntegration::can_use_premium_features() ) {
			wp_send_json_error( esc_html__( 'Intent Classification is only available in ContactIn Pro.', 'contactin' ) );
		}
		$handler = new LearningHandler( $this->analytics );
		$handler->handle_export_learning_data();
	}

	public function handle_get_message_corrections(): void {
		// PREMIUM FEATURE: Intent Classification/Learning only in Pro
		if ( ! \ContactInbox\Integration\FreemiusIntegration::can_use_premium_features() ) {
			wp_send_json_error( esc_html__( 'Intent Classification is only available in ContactIn Pro.', 'contactin' ) );
		}
		$handler = new LearningHandler( $this->analytics );
		$handler->handle_get_message_corrections();
	}

	public function handle_learning_stats(): void {
		// PREMIUM FEATURE: Intent Classification/Learning only in Pro
		if ( ! \ContactInbox\Integration\FreemiusIntegration::can_use_premium_features() ) {
			wp_send_json_error( esc_html__( 'Intent Classification is only available in ContactIn Pro.', 'contactin' ) );
		}
		$handler = new LearningHandler( $this->analytics );
		$handler->handle_learning_stats();
	}

	public function handle_trigger_learning(): void {
		// PREMIUM FEATURE: Intent Classification/Learning only in Pro
		if ( ! \ContactInbox\Integration\FreemiusIntegration::can_use_premium_features() ) {
			wp_send_json_error( esc_html__( 'Intent Classification is only available in ContactIn Pro.', 'contactin' ) );
		}
		$handler = new LearningHandler( $this->analytics );
		$handler->handle_trigger_learning();
	}
}
