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
}
