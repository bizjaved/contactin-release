<?php
/**
 * Performance Data AJAX Handler
 *
 * Handles both performance metrics and queue stats.
 *
 * @package ContactInbox\Admin\AJAX
 */

declare(strict_types=1);

namespace ContactInbox\Admin\AJAX;

use ContactInbox\Core\Config;

if (!defined('ABSPATH')) {
    exit;
}

class PerformanceDataHandler extends BaseAJAXHandler {

    public function handle_performance(): void {
        $this->verify();

        try {
            $date_range = $this->parse_date_range();
            $days = $date_range['days'];
            $start_date = $date_range['start_date'];
            $end_date = $date_range['end_date'];

            // Queue metrics now respect date filters
            $queue = $this->analytics->get_queue_health($days, $start_date, $end_date);
            // Date-based metrics respect the selected date range
            $email = $this->analytics->get_email_delivery_rate($days, $start_date, $end_date);
            $api = $this->analytics->get_api_stats($days, $start_date, $end_date);
            $crm = $this->analytics->get_crm_sync_rate($days, $start_date, $end_date);
            $system = $this->analytics->get_system_status($days, $start_date, $end_date);

            $response_data = [
                'queue' => $queue,
                'email_delivery' => $email,
                'api_stats' => $api,
                'crm_sync' => $crm,
                'system_status' => $system,
            ];
            wp_send_json_success($response_data);
        } catch (\Exception $e) {
            $this->handle_error($e);
        }
    }

    public function handle_queue_stats(): void {
        $this->verify();

        try {
            $date_range = $this->parse_date_range();
            $days = $date_range['days'];
            $start_date = $date_range['start_date'];
            $end_date = $date_range['end_date'];

            // Queue metrics now respect date filters
            $queue_health = $this->analytics->get_queue_health($days, $start_date, $end_date);
            $queue_stats_by_type = $queue_health['per_type'] ?? [];

            // Format queue data for frontend
            $queues = [];
            foreach (['email', 'crm'] as $type) {
                $counts = $queue_stats_by_type[$type] ?? ['pending' => 0, 'processing' => 0, 'retry' => 0, 'dlq' => 0];
                
                $has_dlq = ($counts['dlq'] ?? 0) > 0;
                $has_retry = ($counts['retry'] ?? 0) > 0;
                $high_pending = ($counts['pending'] ?? 0) > 5;

                $state = 'good';
                $state_label = __('Stable', Config::TEXTDOMAIN);
                if ($has_dlq || $has_retry) {
                    $state = 'critical';
                    $state_label = __('Attention', Config::TEXTDOMAIN);
                } elseif ($high_pending) {
                    $state = 'warning';
                    $state_label = __('Busy', Config::TEXTDOMAIN);
                }

                $queues[$type] = [
                    'label' => ucfirst($type) . ' Queue',
                    'counts' => $counts,
                    'state' => $state,
                    'state_label' => $state_label,
                ];
            }

            $dlq_total = intval(
                ($queue_stats_by_type['email']['dlq'] ?? 0) +
                ($queue_stats_by_type['crm']['dlq'] ?? 0)
            );

            wp_send_json_success([
                'queues' => $queues,
                'dlq_total' => $dlq_total,
                'dlq_state' => $dlq_total > 0 ? 'critical' : 'good',
                'dlq_label' => $dlq_total > 0 ? __('Needs review', Config::TEXTDOMAIN) : __('Clear', Config::TEXTDOMAIN),
            ]);
        } catch (\Exception $e) {
            $this->handle_error($e);
        }
    }
}
