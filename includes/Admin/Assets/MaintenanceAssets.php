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
        /* translators: %s: default delay in seconds before next maintenance run. */
        $delay_prompt = __('Enter seconds until next run (default %s seconds):', 'contact-inbox');

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
                'processing'       => __('Processing...', 'contact-inbox'),
                'ajaxError'        => __('AJAX error occurred.', 'contact-inbox'),
                'actionCompleted'  => __('Action completed.', 'contact-inbox'),
                'progressRunning'  => __('Processing in background. Progress will update automatically.', 'contact-inbox'),
                'progressDone'     => __('Processing complete.', 'contact-inbox'),
                'delayPrompt'      => $delay_prompt,
                'invalidDelay'     => __('Please enter a valid number of seconds greater than zero.', 'contact-inbox'),
            ],
            'confirm' => [
                'retryDlq'        => __('Retry all Dead Letter Queue items? They will be moved back to pending.', 'contact-inbox'),
                'retryEmailDlq'   => __('Retry failed email notifications? Admin and user messages will move back to pending.', 'contact-inbox'),
                'retryCrmDlq'     => __('Retry failed CRM sync attempts? They will move back to pending status.', 'contact-inbox'),
                'resetCircuits'   => __('Reset circuit breakers for SMTP/CRM?', 'contact-inbox'),
                'skipEmail'       => __('Skip email items because SMTP is disabled?', 'contact-inbox'),
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
