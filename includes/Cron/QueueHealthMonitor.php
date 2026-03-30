<?php
// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
/**
 * Queue Health Monitor – Prevents Queue Stall Issues
 * 
 * Continuously monitors queue health and takes preventive action:
 * - Detects stuck ProcessLocks and force-releases them
 * - Alerts when queue backs up (items accumulating)
 * - Automatically retries stuck batches
 * - Ensures cron schedules stay healthy
 * 
 * @package ContactIn\Cron
 */

declare(strict_types=1);

namespace ContactInbox\Cron;

use ContactInbox\Traits\Singleton;
use ContactInbox\Core\Config;
use ContactInbox\Core\Logger;
use ContactInbox\Core\ProcessLock;
use ContactInbox\Core\AlertGenerator;

if (!defined('ABSPATH')) {
    exit;
}

final class QueueHealthMonitor {
    use Singleton;

    /**
     * Current timestamp for consistency
     */
    private int $now;

    protected function __construct() {
        $this->now = time();
        
        // Run health checks on hourly interval
        add_action('contactin_queue_health_check', [$this, 'run_health_check']);
        
        // Schedule the health check if not already scheduled
        if (!wp_next_scheduled('contactin_queue_health_check')) {
            wp_schedule_event($this->now, 'hourly', 'contactin_queue_health_check');
        }
    }

    /**
     * Main health check routine
     * 
     * Called hourly by WordPress cron. Performs all health checks and
     * takes corrective action if issues are detected.
     */
    public function run_health_check(): void {
        $record_id = CronMonitor::start_job('contactin_queue_health_check');
        if (!$record_id) {
            return;  // Already running
        }

        try {
            $this->now = time();
            
            // Phase 1: Check for stuck ProcessLocks
            $unlocked = $this->check_and_release_stuck_locks();
            
            // Phase 2: Monitor queue backups
            $queue_status = $this->check_queue_backup();
            
            // Phase 3: Verify cron schedules are healthy
            $crons_fixed = $this->ensure_cron_schedules();
            
            // Phase 4: Check for stalled processing
            $recovered = $this->check_stalled_processing();
            
            // Log results
            $status = 'success';
            if ($unlocked > 0 || $queue_status['alerts'] > 0 || $crons_fixed > 0 || $recovered > 0) {
                $status = 'corrective_action';
            }
            
            Logger::info('Queue health check completed', [
                'locks_released' => $unlocked,
                'queue_alerts' => $queue_status['alerts'],
                'crons_fixed' => $crons_fixed,
                'stalled_recovered' => $recovered,
                'status' => $status,
            ]);
            
            CronMonitor::success_job($record_id, $unlocked + $crons_fixed + $recovered);
            
        } catch (\Throwable $e) {
            Logger::error('Queue health check failed', [
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);
            CronMonitor::fail_job($record_id, 'exception', $e->getMessage());
        }
    }

    /**
     * Phase 1: Check for stuck ProcessLocks and force-release them
     * 
     * Force releases locks older than 10 minutes (more aggressive than 5-min TTL)
     * to handle cases where:
     * - Process crashes without cleanup
     * - Transient store has TTL issues
     * - Lock gets renewed but progress stalls
     * 
     * @return int Number of locks released
     */
    private function check_and_release_stuck_locks(): int {
        $locks = ['email', 'crm', 'webhook', 'analytics'];
        $released = 0;
        
        foreach ($locks as $process) {
            $lock_timestamp = ProcessLock::get_lock_timestamp($process);
            
            if ($lock_timestamp === false) {
                continue;  // No lock held
            }
            
            $age_seconds = $this->now - $lock_timestamp;
            
            // Lock age: 10 minutes (600s) is our threshold for forced release
            if ($age_seconds > 600) {
                Logger::warning("Stuck ProcessLock detected - force releasing", [
                    'process' => $process,
                    'age_seconds' => $age_seconds,
                    'age_minutes' => floor($age_seconds / 60),
                ]);
                
                // Force release
                if (ProcessLock::force_release($process, 0)) {
                    $released++;
                    
                    // Alert admin about stuck lock
                    AlertGenerator::alert_queue_issue(
                        "Process lock was stuck for {$age_seconds} seconds ({$process} processor)",
                        "WARNING",
                        [
                            'process' => $process,
                            'stuck_duration' => $age_seconds,
                            'action_taken' => 'force_released',
                        ]
                    );
                }
            }
        }
        
        return $released;
    }

    /**
     * Phase 2: Monitor queue for backups/accumulation
     * 
     * Detects when:
     * - Items are accumulating faster than processing
     * - Queue is growing beyond thresholds
     * - Single type has excessive items
     * 
     * @return array Status with alert count
     */
    private function check_queue_backup(): array {
        global $wpdb;
        
        $queue_table = $wpdb->prefix . Config::TABLE_QUEUE;
        
        // Get current queue stats
        $stats = $wpdb->get_results("
            SELECT type, status, COUNT(*) as count
            FROM {$queue_table}
            GROUP BY type, status
        ");
        
        $alerts = 0;
        $queue_breakdown = [];
        
        foreach ($stats as $stat) {
            if (!isset($queue_breakdown[$stat->type])) {
                $queue_breakdown[$stat->type] = ['pending' => 0, 'processing' => 0, 'retry' => 0];
            }
            $queue_breakdown[$stat->type][$stat->status] = $stat->count;
        }
        
        // Check each queue type for issues
        foreach ($queue_breakdown as $type => $counts) {
            $pending = $counts['pending'] ?? 0;
            $processing = $counts['processing'] ?? 0;
            $retry = $counts['retry'] ?? 0;
            $total = $pending + $processing + $retry;
            
            // Alert threshold: 100+ pending items
            if ($pending > 100) {
                Logger::warning("Queue backup detected", [
                    'type' => $type,
                    'pending' => $pending,
                    'processing' => $processing,
                    'retry' => $retry,
                ]);
                
                AlertGenerator::alert_queue_issue(
                    "Queue backup: {$pending} pending {$type} items",
                    "WARNING",
                    [
                        'type' => $type,
                        'pending' => $pending,
                        'processing' => $processing,
                        'retry' => $retry,
                    ]
                );
                
                $alerts++;
            }
            
            // Alert if processing exceeds pending (likely stuck items)
            if ($processing > $pending && $pending > 0) {
                Logger::warning("Stuck processing items detected", [
                    'type' => $type,
                    'processing' => $processing,
                    'pending' => $pending,
                ]);
                $alerts++;
            }
        }
        
        return ['breakdown' => $queue_breakdown, 'alerts' => $alerts];
    }

    /**
     * Phase 3: Verify cron schedules are healthy
     * 
     * Ensures critical crons stay scheduled and are not disabled.
     * 
     * @return int Number of crons fixed
     */
    private function ensure_cron_schedules(): int {
        $critical_crons = [
            Config::CRON_PROCESS_EMAIL,
            Config::CRON_PROCESS_CRM,
            Config::CRON_CLEANUP,
            Config::CRON_GDPR,
        ];
        
        $fixed = 0;
        
        foreach ($critical_crons as $cron) {
            $next = wp_next_scheduled($cron);
            
            if (!$next) {
                // Cron is missing - reschedule it
                Logger::warning("Missing cron schedule detected - rescheduling", [
                    'cron' => $cron,
                ]);
                
                // Determine interval based on cron type
                $recurrence = $this->get_cron_recurrence($cron);
                $schedules = wp_get_schedules();
                if (!isset($schedules[$recurrence])) {
                    $fallback = ($cron === Config::CRON_PROCESS_CRM || $cron === Config::CRON_PROCESS_EMAIL)
                        ? (isset($schedules['contactin_fifteen_minutes']) ? 'contactin_fifteen_minutes' : 'hourly')
                        : (($cron === Config::CRON_CLEANUP) ? 'daily' : 'hourly');
                    Logger::warning('Invalid recurrence while rescheduling cron; using fallback', [
                        'cron' => $cron,
                        'invalid_recurrence' => $recurrence,
                        'fallback' => $fallback,
                    ]);
                    $recurrence = $fallback;
                }

                $interval = (int) ($schedules[$recurrence]['interval'] ?? 3600);

                wp_schedule_event($this->now + $interval, $recurrence, $cron);
                
                // Verify it was scheduled
                if (wp_next_scheduled($cron)) {
                    $fixed++;
                    
                    Logger::info('Cron rescheduled successfully', [
                        'cron' => $cron,
                        'interval' => $interval,
                    ]);
                }
            }
        }
        
        return $fixed;
    }

    /**
     * Phase 4: Check for stalled processing
     * 
     * Detects items that entered processing but never completed,
     * and forces them to be retried.
     * 
     * @return int Number of items recovered
     */
    private function check_stalled_processing(): int {
        global $wpdb;
        
        $queue_table = $wpdb->prefix . Config::TABLE_QUEUE;
        $recovered = 0;
        
        // Find items stuck in processing for > 15 minutes
        $stalled = $wpdb->get_results("
            SELECT id, type, data, retry_count, updated_at
            FROM {$queue_table}
            WHERE status = 'processing'
            AND updated_at < DATE_SUB(NOW(), INTERVAL 15 MINUTE)
            LIMIT 50
        ");
        
        foreach ($stalled as $item) {
            Logger::warning("Stalled processing item detected - recovering", [
                'queue_id' => $item->id,
                'type' => $item->type,
                'stuck_since' => $item->updated_at,
                'retry_count' => $item->retry_count,
            ]);
            
            // Reset to pending so it can be retried
            if ($wpdb->update(
                $queue_table,
                ['status' => 'pending', 'last_error' => 'Auto-recovered from stalled state'],
                ['id' => $item->id],
                ['%s', '%s'],
                ['%d']
            )) {
                $recovered++;
            }
        }
        
        if ($recovered > 0) {
            Logger::info('Recovered stalled items', ['count' => $recovered]);
            
            AlertGenerator::alert_queue_issue(
                "Auto-recovered {$recovered} stalled queue items",
                "INFO",
                ['recovered_count' => $recovered]
            );
        }
        
        return $recovered;
    }

    /**
     * Get interval seconds for a cron type
     */
    private function get_cron_interval(string $cron): int {
        $recurrence = $this->get_cron_recurrence($cron);
        $schedules = wp_get_schedules();
        
        return $schedules[$recurrence]['interval'] ?? 3600;
    }

    /**
     * Get recurrence type for a cron
     */
    private function get_cron_recurrence(string $cron): string {
        switch ($cron) {
            case Config::CRON_CLEANUP:
                return 'daily';

            case Config::CRON_GDPR:
                return 'hourly';
            
            case Config::CRON_PROCESS_EMAIL:
                // Use configured interval or default
                return get_option('contactin_queue_interval', 'contactin_fifteen_minutes');

            case Config::CRON_PROCESS_CRM:
                return get_option(
                    'contactin_crm_queue_interval',
                    get_option('contactin_queue_interval', 'contactin_fifteen_minutes')
                );
            
            default:
                return 'hourly';
        }
    }
}
