<?php
declare(strict_types=1);

namespace ContactInbox\Admin\Assets;

use ContactInbox\Core\Config;

if (!defined('ABSPATH')) {
    exit;
}

final class MaintenanceAssets {
    use AssetHelpers;

    public function enqueue(): void {
        // CSS and JS for Maintenance page
        $this->register_style('contactin-maintenance', 'maintenance.min.css');
        $this->register_script('contactin-maintenance', 'maintenance.min.js', ['jquery', 'contactin-admin-global']);

        wp_localize_script('contactin-maintenance', 'ContactINMaintenance', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'timezone' => get_option('timezone_string') ?: 'UTC',
            'timezone_offset' => get_option('gmt_offset') ?: 0,
            'progressNonce' => wp_create_nonce('contactin_maint_queue_progress'),
            'progressPollMs' => 2000,
            'messages' => [
                'processing'       => __('Processing...', Config::TEXTDOMAIN),
                'ajaxError'        => __('AJAX error occurred.', Config::TEXTDOMAIN),
                'actionCompleted'  => __('Action completed.', Config::TEXTDOMAIN),
                'progressRunning'  => __('Processing in background. Progress will update automatically.', Config::TEXTDOMAIN),
                'progressDone'     => __('Processing complete.', Config::TEXTDOMAIN),
                'delayPrompt'      => __('Enter seconds until next run (default %s seconds):', Config::TEXTDOMAIN),
                'invalidDelay'     => __('Please enter a valid number of seconds greater than zero.', Config::TEXTDOMAIN),
            ],
            'confirm' => [
                'retryDlq'        => __('Retry all Dead Letter Queue items? They will be moved back to pending.', Config::TEXTDOMAIN),
                'retryEmailDlq'   => __('Retry failed email notifications? Admin and user messages will move back to pending.', Config::TEXTDOMAIN),
                'retryCrmDlq'     => __('Retry failed CRM sync attempts? They will move back to pending status.', Config::TEXTDOMAIN),
                'resetCircuits'   => __('Reset circuit breakers for SMTP/CRM?', Config::TEXTDOMAIN),
                'skipEmail'       => __('Skip email items because SMTP is disabled?', Config::TEXTDOMAIN),
            ],
            'defaults' => [
                'delaySeconds' => 120,
            ],
            'reloadDelay' => 800,
        ]);

        // Enqueue Attachment Cleanup assets for the card
        if (class_exists('ContactInbox\Admin\Assets\AttachmentCleanupAssets')) {
            (new \ContactInbox\Admin\Assets\AttachmentCleanupAssets())->enqueue();
        }
    }
}
