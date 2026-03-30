<?php
/**
 * Analytics Widgets Assets
 *
 * Handles enqueueing of analytics widgets CSS/JS and localization.
 * Follows the standard Asset class pattern in the plugin.
 *
 * @package ContactIn\Admin\Assets
 */

declare(strict_types=1);

namespace ContactInbox\Admin\Assets;

use ContactInbox\Core\Config;

if (!defined('ABSPATH')) {
    exit;
}

final class AnalyticsWidgetsAssets {
    use AssetHelpers;

    /**
     * Enqueue analytics widgets assets
     */
    public function enqueue(): void {
        $handle = 'contactin-dashboard-widgets';

        // Enqueue CSS for widgets (admin-global is already enqueued globally by AssetsDispatcher)
        $this->register_style($handle, 'dashboard-widgets.min.css');

        // Chart.js for submission trend sparkline
        wp_enqueue_script(
            'chart-js',
            Config::URL . 'dist/js/vendor/chart.min.js',
            [],
            '4.4.0',
            false
        );

        // Enqueue live refresh script for dashboard widgets
        $this->register_script(
            'contactin-dashboard-widgets-live',
            'dashboard-widgets-live.min.js',
            ['jquery']
        );

        wp_localize_script('contactin-dashboard-widgets-live', 'contactinDashboardWidgets', [
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('contactinbox_nonce_action'),
            'refresh_ms' => 30000,
        ]);
    }
}
