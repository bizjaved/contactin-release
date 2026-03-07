<?php
declare(strict_types=1);

namespace ContactInbox\Admin\Traits;

use ContactInbox\Core\Config;
use ContactInbox\Core\Logger;
use ContactInbox\Lifecycle;

if (!defined('ABSPATH')) {
    exit;
}

trait CronManager
{
    private function cron_post_text(string $key, string $default = ''): string
    {
        $value = filter_input(INPUT_POST, $key, FILTER_SANITIZE_FULL_SPECIAL_CHARS);
        return is_string($value) ? sanitize_text_field(wp_unslash($value)) : $default;
    }

    /**
     * Run a cron job manually via AJAX
     */
    public function ajax_run_cron_now(): void
    {
        check_ajax_referer('ci_cron_action', 'nonce');

        if (!current_user_can(Config::CAPABILITY)) {
            wp_send_json_error(['message' => __('Permission denied.', 'contact-inbox')]);
        }

        $event = $this->cron_post_text('event');

        if (empty($event)) {
            wp_send_json_error([
                'message' => 'Event name is required'
            ]);
        }

        try {
            Logger::info("Manually triggering cron event: {$event}");

            // Execute the cron event immediately
            // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.DynamicHooknameFound
            do_action($event);

            Logger::info("Cron event executed successfully: {$event}");

            wp_send_json_success([
                'message' => 'Job executed successfully',
                'event' => $event
            ]);
        } catch (\Throwable $e) {
            Logger::error("Failed to run cron event {$event}: " . $e->getMessage());
            wp_send_json_error([
                'message' => 'Failed to execute job: ' . $e->getMessage()
            ]);
        }
    }

    /**
     * Update cron job interval via AJAX
     */
    public function ajax_update_cron_interval(): void
    {
        check_ajax_referer('ci_cron_action', 'nonce');

        if (!current_user_can(Config::CAPABILITY)) {
            wp_send_json_error(['message' => __('Permission denied.', 'contact-inbox')]);
        }

        $event = $this->cron_post_text('event');
        $new_interval = $this->cron_post_text('interval');

        if (empty($event) || empty($new_interval)) {
            wp_send_json_error([
                'message' => 'Event name and interval are required'
            ]);
        }

        // Validate interval exists
        $schedules = wp_get_schedules();
        if (!isset($schedules[$new_interval])) {
            wp_send_json_error([
                'message' => 'Invalid interval specified'
            ]);
        }

        $interval_seconds = (int)($schedules[$new_interval]['interval'] ?? 0);
        $warning = null;
        if ($interval_seconds > 0 && $interval_seconds < 900) {
            $warning = 'Running more often than every 15 minutes can add load. Form submissions already trigger immediate one-off runs to keep notifications fresh.';
        }

        try {
            Logger::info("Updating cron interval for {$event} to {$new_interval}");

            if ($event === Config::CRON_PROCESS_EMAIL) {
                update_option('contactin_queue_interval', $new_interval);
            } elseif ($event === Config::CRON_PROCESS_CRM) {
                update_option('contactin_crm_queue_interval', $new_interval);
            }

            Lifecycle::reschedule_cron_jobs([$event]);
            $next_run = wp_next_scheduled($event);
            if (!$next_run) {
                throw new \Exception('Failed to reschedule event');
            }

            Logger::info("Cron interval updated successfully: {$event} -> {$new_interval}");

            wp_send_json_success([
                'message' => 'Schedule updated successfully',
                'event' => $event,
                'interval' => $new_interval,
                'next_run' => $next_run,
                'warning' => $warning,
            ]);
        } catch (\Throwable $e) {
            Logger::error("Failed to update cron interval for {$event}: " . $e->getMessage());
            wp_send_json_error([
                'message' => 'Failed to update schedule: ' . $e->getMessage()
            ]);
        }
    }
}
