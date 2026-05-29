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
        wp_add_inline_style( $handle, $this->get_intent_stats_inline_css() );

        // Chart.js for submission trend sparkline
        wp_enqueue_script(
            'chart-js',
            Config::URL . 'dist/js/vendor/chart.min.js',
            [],
            '4.5.1',
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

    /**
     * Intent stats widget styles moved from inline output.
     */
    private function get_intent_stats_inline_css(): string {
        return <<<'CSS'
.contactin-intent-stats-widget {
    padding: 16px;
}

.intent-distribution {
    margin: 16px 0;
}

.intent-stat-row {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 8px 0;
    border-bottom: 1px solid #eee;
}

.intent-stat-label {
    min-width: 100px;
}

.intent-stat-bar {
    flex: 1;
    height: 24px;
    background: #f0f0f0;
    border-radius: 4px;
    overflow: hidden;
}

.intent-bar-fill {
    height: 100%;
    min-width: 2px;
    transition: width 0.3s ease;
    opacity: 0.8;
}

.intent-bar-fill.intent-primary {
    background: #0073aa;
}

.intent-bar-fill.intent-warning {
    background: #f0b849;
}

.intent-bar-fill.intent-info {
    background: #00a0d2;
}

.intent-bar-fill.intent-danger {
    background: #dc3232;
}

.intent-bar-fill.intent-secondary {
    background: #72aee6;
}

.intent-bar-fill.intent-dark {
    background: #2c3338;
}

.intent-bar-fill.intent-muted {
    background: #999;
}

.intent-stat-numbers {
    min-width: 80px;
    text-align: right;
}

.intent-count {
    font-weight: 600;
    margin-right: 8px;
}

.intent-percentage {
    color: #666;
    font-size: 12px;
}

.intent-trend-table {
    margin-top: 12px;
    overflow-x: auto;
}

.intent-trend-table table {
    width: 100%;
    border-collapse: collapse;
    font-size: 12px;
}

.intent-trend-table th,
.intent-trend-table td {
    padding: 6px 8px;
    text-align: center;
    border: 1px solid #eee;
}

.intent-trend-table th {
    background: #f5f5f5;
    font-weight: 600;
}

.intent-trend-table tr:hover {
    background: #fafafa;
}
CSS;
    }
}
