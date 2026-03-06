<?php
/**
 * Analytics Aggregation Cron Job
 *
 * Schedules and executes daily analytics metric aggregation via WP-Cron.
 * Runs once daily at configurable time (default: 2 AM).
 *
 * @package ContactInbox\Cron
 * @since   1.7.0
 */

declare(strict_types=1);

namespace ContactInbox\Cron;

use ContactInbox\Traits\Singleton;
use ContactInbox\Core\Analytics\AnalyticsAggregator;
use ContactInbox\Core\Logger;
use ContactInbox\Cron\CronMonitor;

if (!defined('ABSPATH')) {
    exit;
}

final class AnalyticsAggregationJob {
    use Singleton;

    const HOOK = 'contactin_daily_analytics_aggregation';
    const RECURRENCE = 'daily';

    protected function __construct() {
        // Scheduling is centralized in Lifecycle::schedule_cron_jobs()

        // Register cron callback
        add_action(self::HOOK, [$this, 'execute']);
    }

    /**
     * Schedule the cron job if not already scheduled
     */
    public function schedule(bool $force = false): void {
        if ($force) {
            wp_clear_scheduled_hook(self::HOOK);
        }

        // Double-check by looking at the cron array directly - wp_next_scheduled is unreliable
        $cron = get_option('cron', []);
        foreach ($cron as $timestamp => $hooks) {
            if (isset($hooks[self::HOOK])) {
                return;
            }
        }

        // Schedule to run daily at 2:00 AM
        $time = strtotime('tomorrow 2:00 AM');
        wp_schedule_event($time, self::RECURRENCE, self::HOOK);

        Logger::info('Analytics aggregation job scheduled');
    }

    /**
     * Execute the aggregation job
     */
    public function execute(): void {
        $record_id = CronMonitor::start_job(self::HOOK);
        if (!$record_id) {
            return;
        }

        try {
            // Aggregate metrics for yesterday
            $yesterday = gmdate('Y-m-d', time() - DAY_IN_SECONDS);
            $result = AnalyticsAggregator::aggregate_daily_metrics($yesterday);

            if ($result) {
                Logger::info('Analytics aggregation job completed successfully');
            } else {
                Logger::warning('Analytics aggregation job completed with errors');
            }

            // Prune records older than 90 days (optional, can be made configurable)
            $pruned = AnalyticsAggregator::prune_old_records(90);
            if ($pruned > 0) {
                Logger::info("Pruned {$pruned} old analytics records");
            }

            $items_processed = is_numeric($result) ? (int)$result : 0;
            CronMonitor::success_job($record_id, $items_processed);

        } catch ( \Exception $e ) {
            CronMonitor::fail_job($record_id, 'exception', $e->getMessage());
            Logger::error('Analytics aggregation job failed: ' . $e->getMessage());
        }
    }

    /**
     * Unschedule the cron job (called on plugin deactivation)
     */
    public static function unschedule(): void {
        $timestamp = wp_next_scheduled(self::HOOK);
        if ($timestamp) {
            wp_unschedule_event($timestamp, self::HOOK);
            Logger::info('Analytics aggregation job unscheduled');
        }
    }
}
