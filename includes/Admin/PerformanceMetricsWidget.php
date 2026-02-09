<?php
/**
 * Performance Metrics Dashboard Widget
 *
 * Displays system performance metrics on WordPress dashboard.
 * Uses AnalyticsRepository for all data queries.
 * Renders via template with no inline styles or logic.
 *
 * @package ContactInbox\Admin
 */

declare(strict_types=1);

namespace ContactInbox\Admin;

use ContactInbox\Core\Config;
use ContactInbox\Core\Repositories\AnalyticsRepository;
use ContactInbox\Traits\Singleton;

if (!defined('ABSPATH')) {
    exit;
}

final class PerformanceMetricsWidget {
    use Singleton;

    protected function __construct() {
        if (is_admin()) {
            add_action('wp_dashboard_setup', [$this, 'register_widget'], 12);
        }
    }

    /**
     * Register the dashboard widget
     */
    public function register_widget(): void {
        if (!is_admin() || !current_user_can('manage_options')) {
            return;
        }

        wp_add_dashboard_widget(
            'contactin_performance_metrics',
            __('Contact Inbox Pro - System Performance', Config::TEXTDOMAIN),
            [$this, 'render_widget']
        );
    }

    /**
     * Render the widget
     */
    public function render_widget(): void {
        $analytics = new AnalyticsRepository();

        // Get all data via repository
        $queue = $analytics->get_queue_health();
        $email = $analytics->get_email_delivery_rate();
        $api = $analytics->get_api_stats(1);
        $crm = $analytics->get_crm_sync_rate();
        $system_status = $analytics->get_system_status();
        $analytics_url = add_query_arg(['page' => 'contactin-analytics', 'tab' => 'performance'], admin_url('admin.php'));

        // Load template
        $template = CONTACTINBOX_PATH . Config::TEMPLATE_ADMIN . 'widgets/performance-metrics-widget.php';

        if (file_exists($template)) {
            include $template;
        } else {
            echo '<div class="notice notice-error"><p>' .
                esc_html__('Performance metrics widget template not found.', Config::TEXTDOMAIN) .
                '</p></div>';
        }
    }
}
