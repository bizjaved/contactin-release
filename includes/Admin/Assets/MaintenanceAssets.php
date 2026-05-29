<?php
declare(strict_types=1);

namespace ContactInbox\Admin\Assets;

use ContactInbox\Core\Config;

// phpcs:disable WordPress.WP.I18n.NonSingularStringLiteralDomain, WordPress.WP.I18n.MissingTranslatorsComment

if (!defined('ABSPATH')) {
    exit;
}

final class MaintenanceAssets {
    use AssetHelpers;

    public function enqueue(): void {
        // CSS and JS for Maintenance page
        // dashicons dependency ensures the dismiss icon font renders properly
        $this->register_style('contactin-maintenance', 'maintenance.min.css', ['dashicons']);
        wp_add_inline_style( 'contactin-maintenance', $this->get_intent_learning_inline_css() );
        $this->register_script('contactin-maintenance', 'maintenance.min.js', ['jquery', 'contactin-admin-global']);

        wp_localize_script('contactin-maintenance', 'ContactINMaintenance', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'timezone' => get_option('timezone_string') ?: 'UTC',
            'timezone_offset' => get_option('gmt_offset') ?: 0,
            'progressNonce' => wp_create_nonce('contactin_maint_queue_progress'),
            'progressPollMs' => 2000,
            'upgradeUrl' => admin_url('admin.php?page=contactin-get-started'),
            'upgradeTitle' => __('Unlock Premium Features', 'contactin'),
            'upgradeMessage' => __('This maintenance action is available in ContactIn Pro.', 'contactin'),
            'upgradeCta' => __('Upgrade to Pro', 'contactin'),
            'upgradeDismiss' => __('Maybe later', 'contactin'),
            'messages' => [
                'processing'       => __('Processing...',  'contactin'),
                'ajaxError'        => __('AJAX error occurred.',  'contactin'),
                'actionCompleted'  => __('Action completed.',  'contactin'),
                'progressRunning'  => __('Processing in background. Progress will update automatically.',  'contactin'),
                'progressDone'     => __('Processing complete.',  'contactin'),
                'delayPrompt'      => __('Enter seconds until next run (default %s seconds):',  'contactin'),
                'invalidDelay'     => __('Please enter a valid number of seconds greater than zero.',  'contactin'),
            ],
            'confirm' => [
                'retryDlq'        => __('Retry all Dead Letter Queue items? They will be moved back to pending.',  'contactin'),
                'retryEmailDlq'   => __('Retry failed email notifications? Admin and user messages will move back to pending.',  'contactin'),
                'retryCrmDlq'     => __('Retry failed CRM sync attempts? They will move back to pending status.',  'contactin'),
                'resetCircuits'   => __('Reset circuit breakers for SMTP/CRM?',  'contactin'),
                'skipEmail'       => __('Skip email items because SMTP is disabled?',  'contactin'),
            ],
            'defaults' => [
                'delaySeconds' => 120,
            ],
            'reloadDelay' => 800,
        ]);

    }

    /**
     * Intent learning widget styles moved from template inline style block.
     */
    private function get_intent_learning_inline_css(): string {
        return <<<'CSS'
.contactin-learning-widget {
    grid-column: 1 / -1;
}

.contactin-learning-column-layout {
    display: grid;
    grid-template-columns: 1fr 1fr 1fr;
    gap: 20px;
    margin-top: 20px;
}

.contactin-learning-card {
    background: #fff;
    border: 1px solid #e0e0e0;
    border-radius: 4px;
    padding: 20px;
    box-shadow: 0 1px 3px rgba(0,0,0,0.05);
}

.contactin-learning-card h3 {
    margin: 0 0 15px 0;
    padding-bottom: 12px;
    border-bottom: 2px solid #ddd;
    font-size: 14px;
    font-weight: 600;
    color: #333;
}

.contactin-learning-stats table,
.contactin-learning-patterns table {
    margin-bottom: 0;
    width: 100%;
    border-collapse: collapse;
}

.contactin-learning-stats td {
    padding: 12px 0;
    border-bottom: 1px solid #f0f0f0;
}

.contactin-learning-stats tr:last-child td {
    border-bottom: none;
}

.contactin-learning-stats td:first-child {
    font-weight: 600;
    color: #333;
    width: 45%;
}

.contactin-learning-stats td:last-child {
    width: 55%;
}

.contactin-learning-value {
    font-size: 1.4em;
    color: #0073aa;
    display: block;
    font-weight: bold;
}

.contactin-learning-value.positive {
    color: #2ea94f;
}

.contactin-learning-subtitle {
    color: #666;
    font-weight: normal;
    font-size: 0.9em;
    display: block;
    margin-top: 3px;
}

.contactin-learning-insights ul {
    margin: 0;
    padding-left: 20px;
    list-style: none;
}

.contactin-learning-insights li {
    margin-bottom: 12px;
    line-height: 1.5;
    font-size: 0.95em;
}

.contactin-learning-insights-bullet {
    font-weight: bold;
    margin-right: 8px;
    display: inline-block;
}

.contactin-learning-insights-bullet.high {
    color: #d63638;
}

.contactin-learning-insights-bullet.medium {
    color: #f56e28;
}

.contactin-learning-insights-bullet.low {
    color: #82878c;
}

.contactin-learning-patterns th,
.contactin-learning-patterns td {
    padding: 10px 12px;
    text-align: left;
    border-bottom: 1px solid #f0f0f0;
}

.contactin-learning-patterns tr:last-child td {
    border-bottom: none;
}

.contactin-learning-patterns th {
    font-weight: 600;
    color: #333;
    font-size: 0.9em;
}

.contactin-learning-patterns th:last-child {
    text-align: right;
    width: 60px;
}

.contactin-learning-patterns td:last-child {
    text-align: right;
}

.contactin-learning-pattern-code {
    background-color: #f5f5f5;
    padding: 2px 6px;
    border-radius: 3px;
    font-family: 'Courier New', monospace;
    font-size: 0.85em;
    color: #0073aa;
}

.contactin-learning-ready-yes {
    color: #2ea94f;
    font-weight: bold;
    font-size: 1.1em;
}

.contactin-learning-footer {
    margin-top: 20px;
    padding-top: 20px;
    border-top: 1px solid #e0e0e0;
}

.contactin-learning-actions {
    display: flex;
    gap: 10px;
    flex-wrap: wrap;
}

.contactin-learning-actions .button {
    flex: 1;
    min-width: 160px;
}

@media (max-width: 1200px) {
    .contactin-learning-column-layout {
        gap: 15px;
    }
}
CSS;
    }
}
