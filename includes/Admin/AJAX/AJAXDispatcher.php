<?php
/**
 * AJAX Dispatcher
 *
 * Registers and dispatches all AJAX handlers.
 *
 * @package ContactInbox\Admin\AJAX
 */

declare(strict_types=1);

namespace ContactInbox\Admin\AJAX;

use ContactInbox\Core\Repositories\AnalyticsRepository;

if (!defined('ABSPATH')) {
    exit;
}

final class AJAXDispatcher {

    private AnalyticsRepository $analytics;

    public function __construct(AnalyticsRepository $analytics = null) {
        $this->analytics = $analytics ?? new AnalyticsRepository();
    }

    /**
     * Register all AJAX handlers
     */
    public function register_handlers(): void {
        // Submissions data
        add_action('wp_ajax_contactin_get_submissions_data', [$this, 'handle_submissions_data']);

        // Performance and queue data
        add_action('wp_ajax_contactin_get_performance_data', [$this, 'handle_performance_data']);
        add_action('wp_ajax_contactin_get_queue_stats', [$this, 'handle_queue_stats']);

        // Users and device data
        add_action('wp_ajax_contactin_get_users_data', [$this, 'handle_users_data']);

        // CRM data
        add_action('wp_ajax_contactin_get_crm_data', [$this, 'handle_crm_data']);
        add_action('wp_ajax_ci_get_crm_logs', [$this, 'handle_crm_logs']);

        // Health metrics
        add_action('wp_ajax_contactin_get_health_metrics', [$this, 'handle_health_metrics']);

        // Cron status
        add_action('wp_ajax_contactin_get_cron_status', [$this, 'handle_cron_status']);

        // Dashboard widgets (real-time monitoring)
        add_action('wp_ajax_contactin_get_dashboard_widgets', [$this, 'handle_dashboard_widgets']);

        // Reports export
        add_action('wp_ajax_contactin_export_report', [$this, 'handle_export_report']);
    }

    public function handle_submissions_data(): void {
        $handler = new SubmissionsDataHandler($this->analytics);
        $handler->handle();
    }

    public function handle_performance_data(): void {
        $handler = new PerformanceDataHandler($this->analytics);
        $handler->handle_performance();
    }

    public function handle_queue_stats(): void {
        $handler = new PerformanceDataHandler($this->analytics);
        $handler->handle_queue_stats();
    }

    public function handle_users_data(): void {
        $handler = new UsersDataHandler($this->analytics);
        $handler->handle();
    }

    public function handle_crm_data(): void {
        // Pro feature - CRM is not available in free version
        wp_send_json_error(['message' => 'CRM features are available in ContactIn Pro']);
    }

    public function handle_crm_logs(): void {
        // Pro feature - CRM is not available in free version
        wp_send_json_error(['message' => 'CRM features are available in ContactIn Pro']);
    }

    public function handle_health_metrics(): void {
        $handler = new HealthMetricsHandler($this->analytics);
        $handler->handle();
    }

    public function handle_cron_status(): void {
        $handler = new CronStatusHandler($this->analytics);
        $handler->handle();
    }

    public function handle_dashboard_widgets(): void {
        $handler = new DashboardWidgetsHandler($this->analytics);
        $handler->handle();
    }

    public function handle_export_report(): void {
        // Phase 3E: Implement CSV/PDF export
        check_ajax_referer('contactin_nonce_action', 'nonce');
        wp_send_json_success([
            'message' => 'Export functionality coming in Phase 3E',
        ]);
    }
}
