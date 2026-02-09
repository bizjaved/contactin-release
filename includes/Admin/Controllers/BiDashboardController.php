<?php
/**
 * BI Dashboard AJAX Controller
 *
 * Handles AJAX requests for Business Intelligence dashboard data.
 * Retrieves analytics metrics, KPIs, and system health status.
 *
 * @package ContactInbox\Admin\Controllers
 */

declare(strict_types=1);

namespace ContactInbox\Admin\Controllers;

use ContactInbox\Traits\Singleton;
use ContactInbox\Core\Repositories\AnalyticsRepository;
use ContactInbox\Core\Config;

if (!defined('ABSPATH')) {
    exit;
}

final class BiDashboardController {
    use Singleton;

    private AnalyticsRepository $analytics;

    protected function __construct() {
        $this->analytics = new AnalyticsRepository();
        $this->register_hooks();
    }

    /**
     * Register AJAX handlers
     */
    private function register_hooks(): void {
        add_action('wp_ajax_contactin_get_kpi_metrics', [$this, 'get_kpi_metrics']);
        add_action('wp_ajax_contactin_get_daily_trends', [$this, 'get_daily_trends']);
        add_action('wp_ajax_contactin_get_system_health', [$this, 'get_system_health']);
        add_action('wp_ajax_contactin_get_email_health', [$this, 'get_email_health']);
        add_action('wp_ajax_contactin_get_crm_health', [$this, 'get_crm_health']);
    }

    /**
     * Get KPI metrics for dashboard
     * 
     * @return void Outputs JSON
     */
    public function get_kpi_metrics(): void {
        check_ajax_referer('contactin_nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Insufficient permissions', Config::TEXTDOMAIN)]);
        }

        try {
            $data = [
                'submission_count_today' => $this->analytics->get_submission_count_today(),
                'submission_status' => $this->analytics->get_submission_status_breakdown(7),
                'conversion_rate' => $this->analytics->get_conversion_rate_today(),
                'queue_health' => $this->analytics->get_queue_health(),
                'email_delivery_rate' => $this->analytics->get_email_delivery_rate(),
                'api_stats' => $this->analytics->get_api_stats(),
                'crm_sync_rate' => $this->analytics->get_crm_sync_rate(),
            ];

            wp_send_json_success($data);
        } catch (\Exception $e) {
            wp_send_json_error(['message' => __('Failed to load KPI metrics', Config::TEXTDOMAIN)]);
        }
    }

    /**
     * Get daily trend data
     * 
     * @return void Outputs JSON
     */
    public function get_daily_trends(): void {
        check_ajax_referer('contactin_nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Insufficient permissions', Config::TEXTDOMAIN)]);
        }

        try {
            $days = isset($_POST['days']) ? (int)$_POST['days'] : 7;
            
            $data = [
                'submission_trend' => $this->analytics->get_daily_submission_trend($days),
                'labels' => $this->get_date_labels($days),
            ];

            wp_send_json_success($data);
        } catch (\Exception $e) {
            wp_send_json_error(['message' => __('Failed to load trend data', Config::TEXTDOMAIN)]);
        }
    }

    /**
     * Get overall system health status
     * 
     * @return void Outputs JSON
     */
    public function get_system_health(): void {
        check_ajax_referer('contactin_nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Insufficient permissions', Config::TEXTDOMAIN)]);
        }

        try {
            $status = $this->analytics->get_system_status();

            wp_send_json_success([
                'status' => $status,
                'queue' => $this->analytics->get_queue_health(),
                'email' => $this->analytics->get_email_delivery_rate(),
                'api' => $this->analytics->get_api_stats(),
                'crm' => $this->analytics->get_crm_sync_rate(),
            ]);
        } catch (\Exception $e) {
            wp_send_json_error(['message' => __('Failed to load system health', Config::TEXTDOMAIN)]);
        }
    }

    /**
     * Get email delivery health metrics
     * 
     * @return void Outputs JSON
     */
    public function get_email_health(): void {
        check_ajax_referer('contactin_nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Insufficient permissions', Config::TEXTDOMAIN)]);
        }

        try {
            $days = isset($_POST['days']) ? (int)$_POST['days'] : 30;
            $data = $this->analytics->get_email_delivery_health($days);

            wp_send_json_success($data);
        } catch (\Exception $e) {
            wp_send_json_error(['message' => __('Failed to load email health', Config::TEXTDOMAIN)]);
        }
    }

    /**
     * Get CRM sync health metrics
     * 
     * @return void Outputs JSON
     */
    public function get_crm_health(): void {
        check_ajax_referer('contactin_nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Insufficient permissions', Config::TEXTDOMAIN)]);
        }

        try {
            $days = isset($_POST['days']) ? (int)$_POST['days'] : 30;
            $data = $this->analytics->get_crm_sync_health($days);

            wp_send_json_success($data);
        } catch (\Exception $e) {
            wp_send_json_error(['message' => __('Failed to load CRM health', Config::TEXTDOMAIN)]);
        }
    }

    /**
     * Generate date labels for chart
     *
     * @param int $days Number of days
     * @return array Array of date strings
     */
    private function get_date_labels(int $days): array {
        $labels = [];
        for ($i = $days - 1; $i >= 0; $i--) {
            $labels[] = date('M d', strtotime("-{$i} days"));
        }
        return $labels;
    }
}
