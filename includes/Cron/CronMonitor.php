<?php
// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DateTime.RestrictedFunctions.date_date
declare(strict_types=1);

namespace ContactInbox\Cron;

use ContactInbox\Core\Config;
use ContactInbox\Traits\Singleton;
use ContactInbox\Core\Logger;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Cron Job Monitoring and Logging
 * 
 * Tracks execution of all cron jobs, records failures, durations, and provides
 * dashboard visibility into cron health and issues.
 */
final class CronMonitor {
    use Singleton;

    /**
     * Track currently running cron jobs to prevent duplicate execution
     */
    private static array $running_jobs = [];

    /**
     * Record cron job start
     * 
     * @param string $cron_hook The WordPress cron hook name
     * @return int|null Record ID or null if already running
     */
    public static function start_job(string $cron_hook): ?int {
        global $wpdb;

        // Prevent duplicate execution
        if (isset(self::$running_jobs[$cron_hook])) {
            Logger::warning("Cron job '{$cron_hook}' is already running, skipping duplicate");
            return null;
        }

        // Get last execution record
        $last_record = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}" . Config::TABLE_CRON_LOG . " 
                WHERE cron_hook = %s ORDER BY start_time DESC LIMIT 1",
                $cron_hook
            )
        );

        // If last job is still running after 30 minutes, mark as timeout
        if ($last_record && $last_record->status === 'running') {
            $last_start = strtotime($last_record->start_time);
            $current_time = time();
            $duration = $current_time - $last_start;

            if ($duration > 1800) { // 30 minutes
                self::fail_job(
                    (int)$last_record->id,
                    'timeout',
                    "Cron job exceeded 30 minute timeout"
                );
                Logger::warning("Previous execution of '{$cron_hook}' marked as timeout");
            }
        }

        // Insert new execution record
        $inserted = $wpdb->insert(
            $wpdb->prefix . Config::TABLE_CRON_LOG,
            [
                'cron_hook' => $cron_hook,
                'status' => 'running',
                'start_time' => current_time('mysql', true),
            ],
            ['%s', '%s', '%s']
        );

        if (!$inserted) {
            Logger::error("Failed to log cron start for '{$cron_hook}'");
            return null;
        }

        $record_id = (int)$wpdb->insert_id;
        self::$running_jobs[$cron_hook] = $record_id;

        Logger::debug("Cron job '{$cron_hook}' started (record_id: {$record_id})");

        return $record_id;
    }

    /**
     * Record cron job success
     * 
     * @param int $record_id The cron log record ID
     * @param int $items_processed Optional count of items processed
     * @param array $metadata Additional metadata to store
     */
    public static function success_job(int $record_id, int $items_processed = 0, array $metadata = []): void {
        global $wpdb;

        $end_time = current_time('mysql', true);
        $start = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT start_time FROM {$wpdb->prefix}" . Config::TABLE_CRON_LOG . " WHERE id = %d",
                $record_id
            )
        );

        $duration_ms = 0;
        if ($start) {
            $duration_seconds = strtotime($end_time) - strtotime($start);
            $duration_ms = max(0, intval($duration_seconds * 1000));
        }

        $wpdb->update(
            $wpdb->prefix . Config::TABLE_CRON_LOG,
            [
                'status' => 'success',
                'end_time' => $end_time,
                'duration_ms' => $duration_ms,
                'items_processed' => $items_processed,
                'failure_count' => 0,
            ],
            ['id' => $record_id],
            ['%s', '%s', '%d', '%d', '%d'],
            ['%d']
        );

        // Clear the running flag
        self::clear_running_flag($record_id);

        Logger::debug(
            "Cron job completed successfully (record_id: {$record_id}, duration: {$duration_ms}ms, items: {$items_processed})"
        );
    }

    /**
     * Record cron job failure
     * 
     * @param int $record_id The cron log record ID
     * @param string $error_code Error code/category
     * @param string $error_message Error description
     */
    public static function fail_job(int $record_id, string $error_code = 'error', string $error_message = ''): void {
        global $wpdb;

        $end_time = current_time('mysql', true);

        // Get previous failure count
        $prev_count = (int)$wpdb->get_var(
            $wpdb->prepare(
                "SELECT failure_count FROM {$wpdb->prefix}" . Config::TABLE_CRON_LOG . " WHERE id = %d",
                $record_id
            )
        );

        $start = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT start_time FROM {$wpdb->prefix}" . Config::TABLE_CRON_LOG . " WHERE id = %d",
                $record_id
            )
        );

        $duration_ms = 0;
        if ($start) {
            $duration_seconds = strtotime($end_time) - strtotime($start);
            $duration_ms = max(0, intval($duration_seconds * 1000));
        }

        $wpdb->update(
            $wpdb->prefix . Config::TABLE_CRON_LOG,
            [
                'status' => 'failed',
                'end_time' => $end_time,
                'duration_ms' => $duration_ms,
                'error_code' => $error_code,
                'error_message' => substr($error_message, 0, 500),
                'last_failure_time' => $end_time,
                'failure_count' => $prev_count + 1,
            ],
            ['id' => $record_id],
            ['%s', '%s', '%d', '%s', '%s', '%s', '%d'],
            ['%d']
        );

        // Clear the running flag
        self::clear_running_flag($record_id);

        Logger::error(
            "Cron job failed (record_id: {$record_id}, code: {$error_code})",
            ['error_message' => $error_message, 'duration_ms' => $duration_ms]
        );
    }

    /**
     * Get the currently running record ID for a cron hook
     * 
     * @param string $cron_hook The WordPress cron hook name
     * @return int|null
     */
    public static function get_running_id(string $cron_hook): ?int {
        return self::$running_jobs[$cron_hook] ?? null;
    }

    /**
     * Clear the running flag for a job
     * 
     * @param int $record_id
     */
    private static function clear_running_flag(int $record_id): void {
        foreach (self::$running_jobs as $hook => $id) {
            if ($id === $record_id) {
                unset(self::$running_jobs[$hook]);
                break;
            }
        }
    }

    /**
     * Get cron job status by hook name
     * 
     * @param string $cron_hook
     * @return array|null
     */
    public static function get_cron_status(string $cron_hook): ?array {
        global $wpdb;

        $record = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}" . Config::TABLE_CRON_LOG . " 
                WHERE cron_hook = %s ORDER BY start_time DESC LIMIT 1",
                $cron_hook
            ),
            ARRAY_A
        );

        if (!$record) {
            return null;
        }

        // Check if cron is scheduled
        $scheduled = wp_next_scheduled($cron_hook);

        return [
            'hook' => $cron_hook,
            'last_run' => $record['start_time'] ?? null,
            'last_duration_ms' => (int)($record['duration_ms'] ?? 0),
            'status' => $record['status'] ?? 'unknown',
            'error_code' => $record['error_code'] ?? null,
            'error_message' => $record['error_message'] ?? null,
            'failure_count' => (int)($record['failure_count'] ?? 0),
            'items_processed' => (int)($record['items_processed'] ?? 0),
            'is_scheduled' => $scheduled !== false,
            'next_run' => $scheduled ? date('Y-m-d H:i:s', $scheduled) : null,
            'health' => self::calculate_health($record),
        ];
    }

    /**
     * Get all cron job statuses
     * 
     * @return array
     */
    public static function get_all_cron_status(): array {
        $cron_hooks = [
            Config::CRON_CLEANUP,
            Config::CRON_GDPR,
            Config::CRON_PROCESS_EMAIL,
            Config::CRON_PROCESS_CRM,
            'contactin_daily_analytics_aggregation',
        ];

        $status = [];
        foreach ($cron_hooks as $hook) {
            $cron_status = self::get_cron_status($hook);
            if ($cron_status) {
                $status[$hook] = $cron_status;
                continue;
            }

            // If we have no log entries yet, still return a stub so the UI can show schedule info
            $next_run = wp_next_scheduled($hook);
            $status[$hook] = [
                'hook' => $hook,
                'last_run' => null,
                'last_duration_ms' => null,
                'status' => 'never_run',
                'error_code' => null,
                'error_message' => null,
                'failure_count' => 0,
                'items_processed' => 0,
                'is_scheduled' => $next_run !== false,
                'next_run' => $next_run ? date('Y-m-d H:i:s', $next_run) : null,
                // No history yet; treat as warning so the UI does not show "loading" forever
                'health' => 'warning',
            ];
        }

        return $status;
    }

    /**
     * Calculate health score for a cron job
     * 
     * @param array $record
     * @return string 'healthy', 'warning', or 'critical'
     */
    private static function calculate_health(array $record): string {
        $status = $record['status'] ?? 'unknown';
        $failure_count = (int)($record['failure_count'] ?? 0);
        $last_failure = $record['last_failure_time'] ?? null;

        // If currently running, check if timeout
        if ($status === 'running') {
            $start = strtotime($record['start_time'] ?? 'now');
            if (time() - $start > 1800) { // 30 minutes
                return 'critical';
            }
            return 'warning';
        }

        // If last run failed
        if ($status === 'failed') {
            return 'critical';
        }

        // If recently had failures (more than 3 in last 24 hours)
        if ($failure_count >= 3) {
            return 'warning';
        }

        // If last run was successful
        if ($status === 'success') {
            return 'healthy';
        }

        return 'warning';
    }

    /**
     * Prune old cron log entries (older than 30 days)
     * 
     * @param int $days_to_keep
     * @return int Number of records deleted
     */
    public static function prune_old_logs(int $days_to_keep = 30): int {
        global $wpdb;

        $cutoff_date = gmdate('Y-m-d H:i:s', time() - ($days_to_keep * DAY_IN_SECONDS));

        $deleted = $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM {$wpdb->prefix}" . Config::TABLE_CRON_LOG . " 
                WHERE start_time < %s",
                $cutoff_date
            )
        );

        Logger::info("Pruned {$deleted} old cron log entries");

        return $deleted;
    }

    /**
     * Get cron job statistics
     * 
     * @return array
     */
    public static function get_statistics(): array {
        global $wpdb;

        $total = (int)$wpdb->get_var(
            "SELECT COUNT(*) FROM {$wpdb->prefix}" . Config::TABLE_CRON_LOG
        );

        $failed_24h = (int)$wpdb->get_var(
            "SELECT COUNT(*) FROM {$wpdb->prefix}" . Config::TABLE_CRON_LOG . " 
            WHERE status = 'failed' AND start_time > DATE_SUB(NOW(), INTERVAL 24 HOUR)"
        );

        $executions_24h = (int)$wpdb->get_var(
            "SELECT COUNT(*) FROM {$wpdb->prefix}" . Config::TABLE_CRON_LOG . " 
            WHERE start_time > DATE_SUB(NOW(), INTERVAL 24 HOUR)"
        );

        // Average duration over the last 24 hours for completed runs
        $avg_duration = (int)$wpdb->get_var(
            "SELECT AVG(duration_ms) FROM {$wpdb->prefix}" . Config::TABLE_CRON_LOG . " 
            WHERE status IN ('success', 'failed')
              AND start_time > DATE_SUB(NOW(), INTERVAL 24 HOUR)"
        );

        $total_items_processed = (int)$wpdb->get_var(
            "SELECT SUM(items_processed) FROM {$wpdb->prefix}" . Config::TABLE_CRON_LOG . " 
            WHERE status = 'success'"
        );

        $critical_count = (int)$wpdb->get_var(
            "SELECT COUNT(DISTINCT cron_hook) FROM {$wpdb->prefix}" . Config::TABLE_CRON_LOG . " 
            WHERE status = 'failed' AND start_time > DATE_SUB(NOW(), INTERVAL 24 HOUR)"
        );

        return [
            'total_executions' => $total,
            'executions_24h' => $executions_24h,
            'failed_in_24h' => $failed_24h,
            'avg_duration_ms' => $avg_duration,
            'total_items_processed' => $total_items_processed,
            'critical_jobs' => $critical_count,
            'health' => self::calculate_overall_health($failed_24h, $total),
        ];
    }

    /**
     * Calculate overall health percentage
     * 
     * @param int $failed_count
     * @param int $total_count
     * @return int Percentage (0-100)
     */
    private static function calculate_overall_health(int $failed_count, int $total_count): int {
        if ($total_count === 0) {
            return 100;
        }

        $success_rate = (($total_count - $failed_count) / $total_count) * 100;
        return max(0, intval($success_rate));
    }
}
