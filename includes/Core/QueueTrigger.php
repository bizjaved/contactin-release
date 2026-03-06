<?php
/**
 * Queue Trigger – Intelligent Queue Processing Initiator
 *
 * Determines whether queue processing should be started based on:
 * - Feature enablement (SMTP, CRM)
 * - Current processing state (lock held?)
 * - Pending work existence (any items to process?)
 *
 * Called immediately after form submission to trigger async processing.
 * Replaces blind cron-based polling with event-driven architecture.
 *
 * @package ContactInbox\Core
 * @since   2.0.0
 */

declare(strict_types=1);

namespace ContactInbox\Core;

use ContactInbox\Traits\Singleton;

if (!defined('ABSPATH')) exit;

final class QueueTrigger {
    use Singleton;

    /**
     * Get queue interval option with migration support for legacy keys
     *
     * @param string $option_key Current option key (e.g., 'contactin_queue_interval')
     * @param string $fallback_key Optional fallback key for migration
     * @param string $default Default schedule slug
     * @return string Schedule slug
     */
    private static function get_queue_interval(
        string $option_key,
        ?string $fallback_key = null,
        string $default = 'contactin_fifteen_minutes'
    ): string {
        $value = get_option($option_key);
        
        if ($value) {
            return (string)$value;
        }

        // Try fallback for migration (e.g., contactinbox_queue_interval -> contactin_queue_interval)
        if ($fallback_key) {
            $fallback_value = get_option($fallback_key);
            if ($fallback_value) {
                // Migrate legacy value to new key
                update_option($option_key, $fallback_value);
                delete_option($fallback_key);
                return (string)$fallback_value;
            }
        }

        return $default;
    }

    /**
     * Maybe trigger email queue processor
     *
     * Checks if:
     * 1. Email processing is enabled (SMTP + notifications)
     * 2. No email processor is currently running
     * 3. There are pending emails to process
     *
     * If all conditions met, schedules immediate processing.
     *
     * @return bool True if processor was triggered, false if skipped
     */
    public static function maybe_trigger_email_processor(): bool {
        // Check 1: Is email processing enabled?
        if (!self::is_email_processing_enabled()) {
            Logger::debug('Email processing not enabled, skipping trigger');
            return false;
        }

        // Check 2: Is a processor already running?
        if (ProcessLock::is_locked('email')) {
            Logger::debug('Email processor already running, skipping trigger');
            return false;
        }

        // Check 3: Are there pending emails to process?
        if (!self::has_pending_emails()) {
            Logger::debug('No pending emails found, skipping trigger');
            return false;
        }

        // All checks passed: trigger processing (fast-lane, throttled)
        $fast_lane_scheduled = self::schedule_fast_lane(
            Config::CRON_PROCESS_EMAIL,
            'contactin_last_fastlane_email',
            'contactin_queue_interval',
            'contactin_fifteen_minutes'
        );

        Logger::info(
            'Email processor triggered by form submission',
            ['fast_lane_scheduled' => $fast_lane_scheduled]
        );

        return true;
    }

    /**
     * Maybe trigger CRM queue processor
     *
     * Checks if:
     * 1. CRM sync is enabled
     * 2. No CRM processor is currently running
     * 3. There are pending CRM items to process
     *
     * If all conditions met, schedules immediate processing.
     *
     * @return bool True if processor was triggered, false if skipped
     */
    public static function maybe_trigger_crm_processor(): bool {
        // Check 1: Is CRM processing enabled?
        if (!self::is_crm_processing_enabled()) {
            Logger::debug('CRM processing not enabled, skipping trigger');
            return false;
        }

        // Check 2: Is a processor already running?
        if (ProcessLock::is_locked('crm')) {
            Logger::debug('CRM processor already running, skipping trigger');
            return false;
        }

        // Check 3: Are there pending CRM items to process?
        if (!self::has_pending_crm_syncs()) {
            Logger::debug('No pending CRM syncs found, skipping trigger');
            return false;
        }

        // All checks passed: trigger processing (fast-lane, throttled)
        $fast_lane_scheduled = self::schedule_fast_lane(
            Config::CRON_PROCESS_CRM,
            'contactin_last_fastlane_crm',
            'contactin_crm_queue_interval',
            'contactin_fifteen_minutes',
            'contactin_queue_interval'
        );

        Logger::info(
            'CRM processor triggered by form submission',
            ['fast_lane_scheduled' => $fast_lane_scheduled]
        );

        return true;
    }

    /**
     * Check if email processing is enabled
     *
     * Requires BOTH:
     * - SMTP enabled
     * - At least one of: admin notifications OR user copy enabled
     *
     * @return bool
     */
    private static function is_email_processing_enabled(): bool {
        $settings = Settings::get_settings();

        // SMTP must be enabled
        if (empty($settings['smtp_enable'])) {
            return false;
        }

        // At least one email type must be enabled
        $send_admin = !empty($settings['send_admin_notification']);
        $send_user = !empty($settings['send_user_copy']);

        return $send_admin || $send_user;
    }

    /**
     * Check if CRM processing is enabled
     *
     * @return bool
     */
    private static function is_crm_processing_enabled(): bool {
        $settings = CRMSettings::get_settings();
        return !empty($settings['crm_enabled']);
    }

    /**
     * Check if there are pending emails to process
     *
     * Scans messages table for:
     * - admin_email_status: NULL or 'pending' or 'failed' (with retries < 5)
     * - user_email_status: NULL or 'pending' or 'failed' (with retries < 5)
     *
     * Returns immediately on first match (LIMIT 1) for efficiency.
     *
     * @return bool True if at least one pending email exists
     */
    private static function has_pending_emails(): bool {
        global $wpdb;
        $table = $wpdb->prefix . Config::TABLE_MESSAGES;

        // Look for messages with pending admin or user emails
        $count = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$table}
             WHERE (
               (admin_email_status IS NULL OR admin_email_status = %s OR admin_email_status = %s)
               AND admin_email_retries < %d
             ) OR (
               (user_email_status IS NULL OR user_email_status = %s OR user_email_status = %s)
               AND user_email_retries < %d
             )
             LIMIT 1",
            Config::EMAIL_PENDING,
            Config::EMAIL_FAILED,
            5,
            Config::EMAIL_PENDING,
            Config::EMAIL_FAILED,
            5
        ));

        return ($count ?? 0) > 0;
    }

    /**
     * Check if there are pending CRM syncs to process
     *
     * Scans messages table for:
     * - crm_status: NULL (legacy messages) or 'pending' or 'failed' (with retries < 5)
     * - Excludes: 'skipped' (CRM disabled), 'sent' (completed), 'synced' (completed)
     *
     * Returns immediately on first match (LIMIT 1) for efficiency.
     *
     * @return bool True if at least one pending CRM sync exists
     */
    private static function has_pending_crm_syncs(): bool {
        global $wpdb;
        $table = $wpdb->prefix . Config::TABLE_MESSAGES;

        // Look for messages with pending CRM syncs
        // Note: NULL check is for backward compatibility with old messages
        // New messages will have explicit 'pending' or 'skipped' status
        $count = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$table}
             WHERE (crm_status IS NULL OR crm_status = %s OR crm_status = %s)
             AND crm_retries < %d
             LIMIT 1",
            Config::CRM_PENDING,
            Config::CRM_FAILED,
            5
        ));

        return ($count ?? 0) > 0;
    }

    /**
     * Get count of pending emails (for diagnostics)
     *
     * @internal For admin dashboard / debugging only
     * @return int Count of messages with pending emails
     */
    public static function get_pending_email_count(): int {
        global $wpdb;
        $table = $wpdb->prefix . Config::TABLE_MESSAGES;

        $count = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$table}
             WHERE (
               (admin_email_status IS NULL OR admin_email_status = %s OR admin_email_status = %s)
               AND admin_email_retries < %d
             ) OR (
               (user_email_status IS NULL OR user_email_status = %s OR user_email_status = %s)
               AND user_email_retries < %d
             )",
            Config::EMAIL_PENDING,
            Config::EMAIL_FAILED,
            5,
            Config::EMAIL_PENDING,
            Config::EMAIL_FAILED,
            5
        ));

        return intval($count ?? 0);
    }

    /**
     * Get count of pending CRM syncs (for diagnostics)
     *
     * @internal For admin dashboard / debugging only
     * @return int Count of messages with pending CRM syncs
     */
    public static function get_pending_crm_count(): int {
        global $wpdb;
        $table = $wpdb->prefix . Config::TABLE_MESSAGES;

        $count = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$table}
             WHERE (crm_status IS NULL OR crm_status = %s OR crm_status = %s)
             AND crm_retries < %d",
            Config::CRM_PENDING,
            Config::CRM_FAILED,
            5
        ));

        return intval($count ?? 0);
    }

    /**
     * Get email processor status (for admin dashboard)
     *
     * @internal For admin dashboard / debugging
     * @return array Status information
     */
    public static function get_email_processor_status(): array {
        $is_locked = ProcessLock::is_locked('email');
        $lock_duration = ProcessLock::get_lock_duration('email');
        $pending_count = self::get_pending_email_count();

        return [
            'enabled' => self::is_email_processing_enabled(),
            'running' => $is_locked,
            'lock_duration_seconds' => $lock_duration,
            'pending_count' => $pending_count,
            'status_text' => self::format_status($is_locked, $lock_duration, $pending_count),
        ];
    }

    /**
     * Get CRM processor status (for admin dashboard)
     *
     * @internal For admin dashboard / debugging
     * @return array Status information
     */
    public static function get_crm_processor_status(): array {
        $is_locked = ProcessLock::is_locked('crm');
        $lock_duration = ProcessLock::get_lock_duration('crm');
        $pending_count = self::get_pending_crm_count();

        return [
            'enabled' => self::is_crm_processing_enabled(),
            'running' => $is_locked,
            'lock_duration_seconds' => $lock_duration,
            'pending_count' => $pending_count,
            'status_text' => self::format_status($is_locked, $lock_duration, $pending_count),
        ];
    }

    /**
     * Format status text for display
     *
     * @internal
     */
    private static function format_status(bool $running, int $lock_duration, int $pending): string {
        if (!$running && $pending === 0) {
            return 'Idle (no pending items)';
        }

        if ($running) {
            $duration_text = self::format_duration($lock_duration);
            return "Processing ({$duration_text})";
        }

        return "Idle ({$pending} pending)";
    }

    /**
     * Format duration as human-readable string
     *
     * @internal
     */
    private static function format_duration(int $seconds): string {
        if ($seconds < 60) {
            return "{$seconds}s";
        }

        $minutes = (int)($seconds / 60);
        $remaining = $seconds % 60;

        if ($minutes < 60) {
            return $remaining > 0 ? "{$minutes}m {$remaining}s" : "{$minutes}m";
        }

        $hours = (int)($minutes / 60);
        $remaining_min = $minutes % 60;

        return "{$hours}h {$remaining_min}m";
    }

    /**
     * Schedule a one-off fast-lane run while keeping normal cadence
     *
     * - Throttles repeated scheduling via an option timestamp
     * - Skips if the next recurring run is soon (within 3 minutes)
     * - Respects the configured interval defaulting to 15 minutes
     * - Logs diagnostics to detect duplicate scheduling
     */
    private static function schedule_fast_lane(
        string $hook,
        string $last_option,
        string $interval_option,
        string $default_interval,
        ?string $fallback_interval_option = null
    ): bool {
        $now = time();
        $last = (int) get_option($last_option, 0);

        // Throttle to avoid over-scheduling when multiple submissions arrive at once
        if (($now - $last) < 120) {
            Logger::debug('Fast-lane scheduling throttled (within 2 minutes)', [
                'hook' => $hook,
                'seconds_since_last' => $now - $last,
            ]);
            return false;
        }

        $next = wp_next_scheduled($hook);
        if ($next && ($next - $now) <= Config::FAST_LANE_THRESHOLD) {
            // A run is already imminent; no need for fast-lane
            Logger::debug('Imminent run exists, skipping fast-lane', [
                'hook' => $hook,
                'next_run_in_seconds' => $next - $now,
                'threshold_seconds' => Config::FAST_LANE_THRESHOLD,
            ]);
            return false;
        }
        // Always schedule immediate single event for freshness
        wp_schedule_single_event($now, $hook);
        update_option($last_option, $now, false);

        // Trigger WP-Cron immediately to process the scheduled event
        $spawned = spawn_cron();

        // Fallback: if WP-Cron is disabled or spawn_cron() failed, invoke the hook directly
        if (!$spawned) {
            Logger::warning('Fast-lane spawn_cron failed, running hook directly', [
                'hook' => $hook,
                'disable_wp_cron' => defined('DISABLE_WP_CRON') && DISABLE_WP_CRON,
            ]);
            do_action($hook);
        }

        Logger::info('Fast-lane triggered', [
            'hook' => $hook,
            'next_scheduled_run' => $next ? date('Y-m-d H:i:s', $next) : null,
        ]);

        return true;
    }

    /**
     * Post-submit homework: runs after user sees success response.
     * Triggers email/CRM processors without delaying the request.
     */
    public static function run_post_submit_homework(int $message_id = 0, ?int $contact_id = null): void {
        // Keep lean: just trigger processors; avoid expensive work here.
        $is_free = defined('CONTACTINBOX_IS_FREE') && CONTACTINBOX_IS_FREE;
        if (!$is_free && $message_id > 0) {
            CRMQueueService::queue_message_sync($message_id, 3, false);
        }
        self::maybe_trigger_email_processor();
        if (!$is_free) {
            self::maybe_trigger_crm_processor();
        }

        Logger::info('Post-submit homework triggered', [
            'message_id' => $message_id,
            'contact_id' => $contact_id,
        ]);
    }
}
