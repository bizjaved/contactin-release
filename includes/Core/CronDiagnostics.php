<?php
/**
 * Cron Job Diagnostics
 *
 * Provides detailed analysis of cron scheduling state, detects anomalies,
 * and suggests recovery actions.
 *
 * @package ContactInbox\Core
 */

declare(strict_types=1);

namespace ContactInbox\Core;

use ContactInbox\Traits\Singleton;

if (!defined('ABSPATH')) {
    exit;
}

final class CronDiagnostics {
    use Singleton;

    /**
     * Run full diagnostics on all queue crons
     *
     * @return array Diagnostic report with issues and recommendations
     */
    public static function run_diagnostics(): array {
        $report = [
            'timestamp' => current_time('mysql'),
            'wp_cron_disabled' => defined('DISABLE_WP_CRON') && DISABLE_WP_CRON,
            'crons' => [],
            'issues' => [],
            'recommendations' => [],
        ];

        // Check each cron
        foreach ([Config::CRON_PROCESS_EMAIL, Config::CRON_PROCESS_CRM] as $hook) {
            $cron_report = self::check_cron($hook);
            $report['crons'][$hook] = $cron_report;

            // Collect issues
            if (!empty($cron_report['issues'])) {
                $report['issues'] = array_merge($report['issues'], $cron_report['issues']);
            }
        }

        // Add general recommendations
        if ($report['wp_cron_disabled']) {
            $report['recommendations'][] = 'DISABLE_WP_CRON is set to true. Ensure your system cron is configured to call wp-cron.php.';
        }

        if (!empty($report['issues'])) {
            $report['health'] = 'warning';
        } else {
            $report['health'] = 'healthy';
        }

        return $report;
    }

    /**
     * Check a single cron hook for issues
     *
     * @param string $hook Cron hook name
     * @return array Diagnostic data for this hook
     */
    private static function check_cron(string $hook): array {
        $now = current_time('timestamp');
        $next = wp_next_scheduled($hook);
        $delay = $next ? ($next - $now) : null;

        // Get last execution from cron monitor
        global $wpdb;
        $cron_log_table = $wpdb->prefix . Config::TABLE_CRON_LOG;
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
        $last_exec = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM %i
                 WHERE cron_hook = %s ORDER BY start_time DESC LIMIT 1",
                $cron_log_table,
                $hook
            )
        );

        $issues = [];
        $stale_threshold = 172800; // 48 hours
        $missing_threshold = 86400; // 24 hours

        // Issue: Missing schedule
        if (!$next) {
            $issues[] = "Cron '{$hook}' is not scheduled";
        }

        // Issue: Scheduled too far in future
        if ($next && $delay > 86400) {
            $issues[] = "Cron '{$hook}' next run is more than 24 hours away (" . self::format_duration($delay) . ")";
        }

        // Issue: Never run before
        if (!$last_exec) {
            $issues[] = "Cron '{$hook}' has never run";
        }

        // Issue: Stale (hasn't run in 2× the interval)
        if ($last_exec) {
            $last_run_ts = strtotime($last_exec->start_time);
            $last_run_age = $now - $last_run_ts;

            // Infer interval from schedule
            $interval_option = ($hook === Config::CRON_PROCESS_EMAIL) 
                ? 'contactin_queue_interval' 
                : 'contactin_crm_queue_interval';
            $interval_slug = get_option($interval_option, 'contactin_fifteen_minutes');
            $schedules = wp_get_schedules();
            $interval_seconds = $schedules[$interval_slug]['interval'] ?? 900;
            $expected_max_age = $interval_seconds * 2;

            if ($last_run_age > $expected_max_age) {
                $issues[] = "Cron '{$hook}' is stale (last run " . self::format_duration($last_run_age) . " ago, expected < " . self::format_duration($expected_max_age) . ")";
            }

            // Issue: Repeated failures
            if ($last_exec->status === 'failed' && (int)$last_exec->failure_count > 2) {
                $issues[] = "Cron '{$hook}' failed " . (int)$last_exec->failure_count . " times (last: " . ($last_exec->error_message ?? 'unknown') . ")";
            }
        }

        return [
            'hook' => $hook,
            'is_scheduled' => $next !== false,
            'next_run' => $next ? date_i18n('Y-m-d H:i:s', $next) : null,
            'next_run_in' => $delay ? self::format_duration($delay) : null,
            'last_run' => $last_exec ? $last_exec->start_time : null,
            'last_status' => $last_exec ? $last_exec->status : null,
            'issues' => $issues,
        ];
    }

    /**
     * Get all single-event schedules (potential duplicates)
     *
     * @return array List of single-event schedules that may indicate problematic duplication
     */
    public static function check_for_duplicate_singles(): array {
        $duplicates = [];
        $cron_array = _get_cron_array();

        foreach ([Config::CRON_PROCESS_EMAIL, Config::CRON_PROCESS_CRM] as $hook) {
            $singles_count = 0;
            $last_times = [];

            foreach ($cron_array as $timestamp => $cron) {
                if (!isset($cron[$hook])) {
                    continue;
                }

                foreach ($cron[$hook] as $data) {
                    // Single events have no 'schedule' key
                    if (empty($data['schedule'])) {
                        $singles_count++;
                        $last_times[] = $timestamp;
                    }
                }
            }

            if ($singles_count > 1) {
                $duplicates[$hook] = [
                    'count' => $singles_count,
                    'times' => $last_times,
                    'issue' => "Multiple single-event schedules detected (expected 0-1)",
                ];
            }
        }

        return $duplicates;
    }

    /**
     * Format duration in human-readable form
     *
     * @param int $seconds Duration in seconds
     * @return string Formatted duration
     */
    private static function format_duration(int $seconds): string {
        if ($seconds < 60) {
            return "{$seconds}s";
        }

        $minutes = (int)($seconds / 60);
        if ($minutes < 60) {
            return "{$minutes}m";
        }

        $hours = (int)($minutes / 60);
        $remaining_min = $minutes % 60;
        if ($hours < 24) {
            return "{$hours}h {$remaining_min}m";
        }

        $days = (int)($hours / 24);
        $remaining_hours = $hours % 24;
        return "{$days}d {$remaining_hours}h";
    }

    /**
     * Get diagnostic report as human-readable text
     *
     * @return string Formatted diagnostic text
     */
    public static function get_diagnostic_text(): string {
        $report = self::run_diagnostics();
        $duplicates = self::check_for_duplicate_singles();

        $text = "=== Cron Queue Diagnostics ===\n";
        $text .= "Report Time: {$report['timestamp']}\n";
        $text .= "Health: {$report['health']}\n";
        $text .= "WP-Cron Disabled: " . ($report['wp_cron_disabled'] ? 'YES' : 'NO') . "\n\n";

        foreach ($report['crons'] as $hook => $cron) {
            $text .= "### {$hook}\n";
            $text .= "  Status: " . ($cron['is_scheduled'] ? 'SCHEDULED' : 'NOT SCHEDULED') . "\n";
            if ($cron['next_run']) {
                $text .= "  Next Run: {$cron['next_run']} ({$cron['next_run_in']})\n";
            }
            if ($cron['last_run']) {
                $text .= "  Last Run: {$cron['last_run']} (Status: {$cron['last_status']})\n";
            }
            if (!empty($cron['issues'])) {
                $text .= "  Issues:\n";
                foreach ($cron['issues'] as $issue) {
                    $text .= "    - {$issue}\n";
                }
            }
            $text .= "\n";
        }

        if (!empty($duplicates)) {
            $text .= "### Potential Duplicate Schedules\n";
            foreach ($duplicates as $hook => $dup) {
                $text .= "  {$hook}: {$dup['count']} single events found\n";
            }
            $text .= "\n";
        }

        if (!empty($report['recommendations'])) {
            $text .= "### Recommendations\n";
            foreach ($report['recommendations'] as $rec) {
                $text .= "  - {$rec}\n";
            }
            $text .= "\n";
        }

        return $text;
    }
}
