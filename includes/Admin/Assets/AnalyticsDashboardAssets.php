<?php
/**
 * Admin – Analytics Dashboard Assets Dispatcher
 *
 * Manages CSS, JavaScript, and localization for the analytics dashboard.
 * Follows the same pattern as InboxAssets and AnalyticsWidgetsAssets.
 *
 * @package ContactInbox\Admin\Assets
 * @since   1.7.0
 */

declare(strict_types=1);

namespace ContactInbox\Admin\Assets;

use ContactInbox\Core\Config;

if (!defined('ABSPATH')) {
    exit;
}

final class AnalyticsDashboardAssets {
    use AssetHelpers;

    /**
     * Enqueue assets for analytics dashboard page
     */
    public function enqueue(): void {
        if (!is_admin()) {
            return;
        }

        // Assets are enqueued by AssetsDispatcher based on hook match
        // No need to check screen again

        // Enqueue dashboard-specific CSS using helper
        $this->register_style('contactin-dashboard-analytics', 'dashboard-analytics.min.css');

        // Enqueue Chart.js for visualizations
        wp_enqueue_script(
            'chart-js',
            Config::URL . 'includes/Admin/Assets/js/vendor/chart.min.js',
            [],
            '4.4.0',
            true
        );

        // Enqueue Select2 for dropdown filters
        wp_enqueue_script(
            'select2',
            Config::URL . 'includes/Admin/Assets/js/vendor/select2.min.js',
            ['jquery'],
            '4.1.0',
            true
        );

        wp_enqueue_style(
            'select2',
            Config::URL . 'includes/Admin/Assets/css/vendor/select2.min.css',
            [],
            '4.1.0'
        );

        // Enqueue dashboard utility scripts (loaded before main script)
        $this->register_script(
            'contactin-dashboard-date-utils',
            'dashboard-date-utils.min.js',
            []
        );

        $this->register_script(
            'contactin-dashboard-chart-renderer',
            'dashboard-chart-renderer.min.js',
            ['chart-js']
        );

        $this->register_script(
            'contactin-dashboard-render-helpers',
            'dashboard-render-helpers.min.js',
            ['jquery']
        );

        // Enqueue tab manager
        $this->register_script(
            'contactin-dashboard-tabs',
            'dashboard-tabs.min.js',
            []
        );

        // Enqueue main dashboard JavaScript using helper (depends on utilities)
        $this->register_script(
            'contactin-dashboard-analytics',
            'dashboard-analytics.min.js',
            ['jquery', 'contactin-dashboard-date-utils', 'contactin-dashboard-chart-renderer', 'contactin-dashboard-render-helpers', 'contactin-dashboard-tabs', 'select2']
        );

        // Localize script with AJAX data and i18n strings
        wp_localize_script('contactin-dashboard-analytics', 'contactinAnalytics', [
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('contactinbox_nonce_action'),
            'crm_nonce' => wp_create_nonce('contactin_nonce_action'),
            'is_free' => defined('CONTACTINBOX_IS_FREE') && CONTACTINBOX_IS_FREE,
            'page_title' => __('Dashboard', Config::TEXTDOMAIN),
            'timezone' => get_option('timezone_string') ?: 'UTC',
            'timezone_offset' => get_option('gmt_offset') ?: 0,
            'i18n' => [
                'submissions_tab' => __('Submissions', Config::TEXTDOMAIN),
                'performance_tab' => __('Performance', Config::TEXTDOMAIN),
                'users_tab' => __('Users', Config::TEXTDOMAIN),
                'crm_tab' => __('CRM Integration', Config::TEXTDOMAIN),
                'cron_tab' => __('Cron Jobs', Config::TEXTDOMAIN),
                'reports_tab' => __('Reports', Config::TEXTDOMAIN),
                'loading' => __('Loading...', Config::TEXTDOMAIN),
                'error' => __('An error occurred. Please try again.', Config::TEXTDOMAIN),
                'export_csv' => __('Export as CSV', Config::TEXTDOMAIN),
                'export_pdf' => __('Export as PDF', Config::TEXTDOMAIN),
            ],
        ]);
    }
}
