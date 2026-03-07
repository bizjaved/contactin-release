<?php
/**
 * Cron Status AJAX Handler
 *
 * @package ContactInbox\Admin\AJAX
 */

declare(strict_types=1);

namespace ContactInbox\Admin\AJAX;

use ContactInbox\Cron\CronMonitor;
use ContactInbox\Core\Config;

if (!defined('ABSPATH')) {
    exit;
}

class CronStatusHandler extends BaseAJAXHandler {

    public function handle(): void {
        $this->verify();

        try {
            $status = CronMonitor::get_all_cron_status() ?: [];
            $stats = CronMonitor::get_statistics() ?: [];

            // Ensure every known cron hook has an entry so UI never shows blanks
            $default_hooks = [
                Config::CRON_PROCESS_EMAIL,
                Config::CRON_PROCESS_CRM,
                Config::CRON_CLEANUP,
                Config::CRON_GDPR,
                'contactin_daily_analytics_aggregation',
            ];

            foreach ($default_hooks as $hook) {
                if (!isset($status[$hook])) {
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
                        'next_run' => $next_run ? wp_date('Y-m-d H:i:s', (int) $next_run) : null,
                        'health' => 'warning',
                    ];
                }
            }

            // Default stats when table empty or unavailable
            $stats = array_merge(
                [
                    'total_executions' => 0,
                    'executions_24h' => 0,
                    'failed_in_24h' => 0,
                    'avg_duration_ms' => 0,
                    'total_items_processed' => 0,
                    'critical_jobs' => 0,
                    'health' => 100,
                ],
                $stats
            );

            wp_send_json_success([
                'status' => $status,
                'statistics' => $stats,
            ]);
        } catch (\Exception $e) {
            $this->handle_error($e);
        }
    }
}
