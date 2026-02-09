<?php
/**
 * CRM Data AJAX Handler
 *
 * @package ContactInbox\Admin\AJAX
 */

declare(strict_types=1);

namespace ContactInbox\Admin\AJAX;

use ContactInbox\Core\CRMMonitor;

if (!defined('ABSPATH')) {
    exit;
}

class CRMDataHandler extends BaseAJAXHandler {

    public function handle(): void {
        $this->verify();

        try {
            $date_range = $this->parse_date_range();
            $days = $date_range['days'];
            $start_date = $date_range['start_date'];
            $end_date = $date_range['end_date'];

            $operation = 'sync';

            // Get CRM data from CRMMonitor with date filters
            $statistics_raw = CRMMonitor::get_statistics($days, $start_date, $end_date, $operation);

            $health = CRMMonitor::get_health_status($days, $start_date, $end_date, $operation);
            $endpoints = CRMMonitor::get_endpoints($days, $start_date, $end_date, $operation);
            $daily_stats = CRMMonitor::get_daily_stats($days, $start_date, $end_date, $operation);

            // Map statistics keys to what the frontend expects while keeping originals
            $statistics = $statistics_raw;
            $statistics['total_requests'] = $statistics_raw['total_syncs'] ?? 0;
            $statistics['successful_requests'] = $statistics_raw['successful'] ?? 0;
            $statistics['failed_requests'] = $statistics_raw['failed'] ?? 0;
            $statistics['pending_requests'] = $statistics_raw['pending'] ?? max(
                0,
                ($statistics['total_requests'] ?? 0) - ($statistics['successful_requests'] ?? 0) - ($statistics['failed_requests'] ?? 0)
            );
            $statistics['success_rate'] = $statistics_raw['success_rate'] ?? 0;
            $statistics['pending'] = $statistics['pending_requests'];

            // Format daily stats for Chart.js
            $daily_formatted = [];
            if (is_array($daily_stats)) {
                foreach ($daily_stats as $item) {
                    $daily_formatted[] = [
                        'date' => isset($item['date']) ? date('M d', strtotime($item['date'])) : $item['date'] ?? '',
                        'count' => isset($item['successful']) ? $item['successful'] : 0,
                        'failed' => isset($item['failed']) ? $item['failed'] : 0,
                    ];
                }
            }

            $response_data = [
                'statistics' => $statistics,
                'health' => $health,
                'endpoints' => $endpoints,
                'daily_stats' => $daily_formatted,
            ];

            wp_send_json_success($response_data);
        } catch (\Exception $e) {
            $this->handle_error($e);
        }
    }

    public function handle_logs(): void {
        $this->verify_crm_nonce();

        try {
            $page = isset($_POST['page']) ? max(1, absint($_POST['page'])) : 1;
            $per_page = isset($_POST['per_page']) ? max(1, absint($_POST['per_page'])) : 5;
            $per_page = min($per_page, 50);
            $status = isset($_POST['status']) ? sanitize_text_field($_POST['status']) : 'all';
            $orderby = isset($_POST['orderby']) ? sanitize_text_field($_POST['orderby']) : 'timestamp';
            $order = isset($_POST['order']) ? sanitize_text_field($_POST['order']) : 'DESC';
            $operation = isset($_POST['operation']) ? sanitize_text_field($_POST['operation']) : null;

            $logs = CRMMonitor::get_logs_paginated(
                $per_page,
                $page,
                $status,
                $orderby,
                $order,
                $operation
            );

            wp_send_json_success($logs);
        } catch (\Exception $e) {
            $this->handle_error($e);
        }
    }

    private function verify_crm_nonce(): void {
        $valid = check_ajax_referer('contactinbox_nonce_action', 'nonce', false);
        if (!$valid) {
            $valid = check_ajax_referer('contactin_nonce_action', 'nonce', false);
        }

        if (!$valid) {
            wp_send_json_error(['message' => __('Invalid nonce.', 'contact-inbox')]);
            exit;
        }

        if (!current_user_can(\ContactInbox\Core\Config::CAPABILITY)) {
            wp_send_json_error(['message' => __('Permission denied.', 'contact-inbox')]);
            exit;
        }
    }
}
