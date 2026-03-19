<?php
/**
 * Today's Snapshot Dashboard Widget
 *
 * Quick health check showing today's form submissions breakdown.
 * Displays completed vs failed with progress visualization.
 * Shows overall system health status and alerts.
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

final class TodaySnapshotWidget {
    use Singleton;

    protected function __construct() {
        if (is_admin()) {
            add_action('wp_dashboard_setup', [$this, 'register_widget'], 13);
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
            'contactin_today_snapshot',
            __('ContactIn - Today\'s Snapshot', 'contact-inbox'),
            [$this, 'render_widget']
        );
    }

    /**
     * Render the widget
     */
    public function render_widget(): void {
        $analytics = new AnalyticsRepository();

        // Get today's data
        $today = current_time('Y-m-d');
        $today_count = $analytics->get_submission_count_today();
        $status_breakdown = $analytics->get_submission_status_breakdown(1, $today, $today);
        $system_status = $analytics->get_system_status();

        $completed = (int) ($status_breakdown['completed'] ?? 0);
        $failed = (int) ($status_breakdown['failed'] ?? 0);
        $completion_total = $completed + $failed;
        $percent_base = $completion_total > 0 ? $completion_total : 1;

        $total_submissions = $today_count;
        $completed_percentage = $completed > 0 ? round(($completed / $percent_base) * 100) : 0;
        $failed_percentage = $failed > 0 ? round(($failed / $percent_base) * 100) : 0;
        $system_health = $system_status;
        $alerts = [];

        // Determine if there are alerts
        $has_alerts = $system_status === 'error' || 
                  $system_status === 'warning';

        // Analytics URL for drill-down
        $analytics_url = add_query_arg(['page' => 'contactin-analytics'], admin_url('admin.php'));

        // Load template
        $template = CONTACTINBOX_PATH . Config::TEMPLATE_ADMIN . 'widgets/today-snapshot-widget.php';

        if (file_exists($template)) {
            include $template;
        } else {
            echo '<div class="notice notice-error"><p>' .
                esc_html__('Today snapshot widget template not found.', 'contact-inbox') .
                '</p></div>';
        }
    }
}
