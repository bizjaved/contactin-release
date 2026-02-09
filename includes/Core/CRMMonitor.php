<?php
/**
 * Contact Inbox – CRM Monitoring & Statistics
 *
 * Enterprise-Grade – Explicit, safe, modular, future-proof.
 *
 * Provides comprehensive monitoring and statistics for CRM integration.
 * Queries CRM log table for CRM-specific metrics and trends.
 *
 * @package ContactInbox\Core
 */

declare(strict_types=1);

namespace ContactInbox\Core;

if (!defined('ABSPATH')) {
    exit;
}

final class CRMMonitor
{
    /**
     * Get overall CRM statistics
     *
     * @return array Statistics with totals, rates, and averages
     */
    public static function get_statistics(?int $days = null, ?string $start_date = null, ?string $end_date = null, ?string $operation = null): array
    {
        return DB::instance()->get_crm_stats($days, $start_date, $end_date, $operation);
    }

    /**
     * Get CRM logs paginated
     *
     * @param int    $per_page Items per page (default 20)
     * @param int    $page Current page number (default 1)
     * @param string $status Filter by status (all, delivered, rejected, etc)
     * @param string $orderby Sort field (id, timestamp, crm_send_status)
     * @param string $order Sort direction (ASC, DESC)
     * @return array Logs array with pagination info
     */
    public static function get_logs_paginated(
        int $per_page = 20,
        int $page = 1,
        string $status = 'all',
        string $orderby = 'timestamp',
        string $order = 'DESC',
        ?string $operation = null
    ): array {
        $offset = ($page - 1) * $per_page;
        
        $logs = DB::instance()->get_crm_logs(
            $per_page,
            $offset,
            $orderby,
            $order,
            $status,
            $operation
        );

        $total = DB::instance()->count_crm_logs($status, $operation);
        $total_pages = ceil($total / $per_page);

        return [
            'logs'         => $logs,
            'total'        => $total,
            'total_pages'  => $total_pages,
            'current_page' => $page,
            'per_page'     => $per_page,
        ];
    }

    /**
     * Get CRM status distribution
     *
     * @return array Breakdown of each CRM status
     */
    public static function get_status_distribution(): array
    {
        $logs = DB::instance()->get_crm_logs(10000, 0, 'status', 'DESC', 'all');
        $distribution = [];
        foreach ($logs as $log) {
            $status = CRMStatus::normalize($log['status'] ?? 'unknown');
            if (!isset($distribution[$status])) {
                $distribution[$status] = 0;
            }
            $distribution[$status]++;
        }
        arsort($distribution);
        return $distribution;
    }

    /**
     * Get average response time by status
     *
     * @return array Average response times for each status
     */
    public static function get_avg_response_times(): array
    {
        $logs = DB::instance()->get_crm_logs(10000, 0, 'created_at', 'DESC', 'all');
        $times = [];
        foreach ($logs as $log) {
            $status = CRMStatus::normalize($log['status'] ?? 'unknown');
            $response = json_decode($log['response'] ?? '{}', true);
            $response_time = $response['response_time_ms'] ?? null;
            if ($response_time) {
                if (!isset($times[$status])) {
                    $times[$status] = ['sum' => 0, 'max' => 0, 'count' => 0];
                }
                $times[$status]['sum'] += $response_time;
                $times[$status]['count']++;
                if ($response_time > $times[$status]['max']) {
                    $times[$status]['max'] = $response_time;
                }
            }
        }
        $result = [];
        foreach ($times as $status => $data) {
            $result[$status] = [
                'avg' => $data['count'] ? (int)round($data['sum'] / $data['count']) : 0,
                'max' => (int)$data['max'],
            ];
        }
        return $result;
    }

    /**
     * Get CRM endpoints in use
     *
     * @param int|null $days Number of days to look back
     * @param string|null $start_date Start date for range
     * @param string|null $end_date End date for range
     * @return array Unique endpoints with success rates
     */
    public static function get_endpoints(?int $days = null, ?string $start_date = null, ?string $end_date = null, ?string $operation = null): array
    {
        $logs = DB::instance()->get_crm_logs(10000, 0, 'created_at', 'DESC', 'all', $operation, $days, $start_date, $end_date);
        $endpoints = [];
        foreach ($logs as $log) {
            $response = json_decode($log['response'] ?? '{}', true);
            $endpoint = $response['endpoint'] ?? null;
            if ($endpoint) {
                if (!isset($endpoints[$endpoint])) {
                    $endpoints[$endpoint] = [
                        'endpoint' => $endpoint,
                        'display_name' => self::format_endpoint_display($endpoint),
                        'total' => 0,
                        'successful' => 0,
                        'failed' => 0,
                    ];
                }
                $endpoints[$endpoint]['total']++;
                if (CRMStatus::is_success($log['status'] ?? '')) {
                    $endpoints[$endpoint]['successful']++;
                } elseif (CRMStatus::is_failure($log['status'] ?? '')) {
                    $endpoints[$endpoint]['failed']++;
                }
            }
        }
        // Calculate success rate
        foreach ($endpoints as &$ep) {
            $attempted = $ep['successful'] + $ep['failed'];
            $ep['success_rate'] = $attempted > 0 ? round(($ep['successful'] / $attempted) * 100, 1) : 0;
        }
        usort($endpoints, function($a, $b) { return $b['total'] <=> $a['total']; });

        if (empty($endpoints) && !empty($operation)) {
            return self::get_endpoints($days, $start_date, $end_date, null);
        }

        return array_values($endpoints);
    }

    /**
     * Create a human-friendly endpoint label for UI display.
     */
    private static function format_endpoint_display(string $endpoint): string
    {
        if (stripos($endpoint, '/sobjects/Contact/Email/') !== false) {
            return __('Contact Upsert (Email)', 'contact-inbox');
        }

        if (stripos($endpoint, '/sobjects/Case') !== false) {
            return __('Case Create', 'contact-inbox');
        }

        // Fallback: use path tail for readability
        $parsed = wp_parse_url($endpoint);
        if (!empty($parsed['path'])) {
            $segments = array_values(array_filter(explode('/', $parsed['path'])));
            if (!empty($segments)) {
                return ucfirst(str_replace('-', ' ', end($segments)));
            }
        }

        return $endpoint;
    }

    /**
     * Get CRM errors from recent logs
     *
     * @param int $limit Number of recent errors to retrieve
     * @return array Recent error logs
     */
    public static function get_recent_errors(int $limit = 10, ?int $days = null, ?string $start_date = null, ?string $end_date = null, ?string $operation = null): array
    {
        $logs = DB::instance()->get_crm_logs(10000, 0, 'created_at', 'DESC', 'all', $operation, $days, $start_date, $end_date);
        $errors = [];
        foreach ($logs as $log) {
            if (CRMStatus::is_failure($log['status'] ?? '')) {
                $errors[] = $log;
                if (count($errors) >= $limit) break;
            }
        }
        return $errors;
    }

    /**
     * Get CRM daily statistics for charts
     *
     * @param int|null $days Number of days to retrieve
     * @param string|null $start_date Start date for range
     * @param string|null $end_date End date for range
     * @return array Daily stats with timestamps and metrics
     */
    public static function get_daily_stats(?int $days = null, ?string $start_date = null, ?string $end_date = null, ?string $operation = null): array
    {
        $logs = DB::instance()->get_crm_logs(10000, 0, 'created_at', 'ASC', 'all', $operation, $days, $start_date, $end_date);
        $daily = [];
        foreach ($logs as $log) {
            $date = substr($log['created_at'], 0, 10);
            if (!isset($daily[$date])) {
                $daily[$date] = ['date' => $date, 'total' => 0, 'successful' => 0, 'failed' => 0, 'pending' => 0];
            }
            $daily[$date]['total']++;
            $status = $log['status'] ?? '';
            if (CRMStatus::is_success($status)) {
                $daily[$date]['successful']++;
            } elseif (CRMStatus::is_failure($status)) {
                $daily[$date]['failed']++;
            } else {
                $daily[$date]['pending']++;
            }
        }
        $stats = array_values($daily);
        usort($stats, function($a, $b) { return strcmp($a['date'], $b['date']); });
        return $stats;
    }

    /**
     * Get health status of CRM integration
     *
     * @return array Health metrics (status, success rate, avg response time, etc)
     */
    public static function get_health_status(?int $days = null, ?string $start_date = null, ?string $end_date = null, ?string $operation = null): array
    {
        $stats = self::get_statistics($days, $start_date, $end_date, $operation);
        $recent_errors = self::get_recent_errors(5, $days, $start_date, $end_date, $operation);
        $endpoints = self::get_endpoints($days, $start_date, $end_date, $operation);

        $total = $stats['total_syncs'] ?? ($stats['total_sends'] ?? 0);
        $success_rate = $stats['success_rate'] ?? 0;

        // Determine health status based on success rate, but treat no-traffic as unknown
        if ($total === 0) {
            $health_status = 'unknown';
        } elseif ($success_rate < 50) {
            $health_status = 'critical';
        } elseif ($success_rate < 80) {
            $health_status = 'warning';
        } else {
            $health_status = 'healthy';
        }

        // Basic auth heuristic: enabled + either OAuth token/refresh token or oauth_enabled flag
        $settings = CRMSettings::get_settings();
        $is_authorized = !empty($settings['crm_enabled']) && (
            !empty($settings['auth_token']) ||
            !empty($settings['refresh_token']) ||
            !empty($settings['oauth_enabled'])
        );

        return [
            'status'             => $health_status,
            'success_rate'       => $success_rate,
            'avg_response_time'  => $stats['avg_response_time_ms'] ?? 0,
            'total_sends'        => $total,
            'recent_error_count' => count($recent_errors),
            'endpoint_count'     => count($endpoints),
            'is_authorized'      => $is_authorized,
            'message'            => self::get_health_message($health_status, $success_rate, $total),
        ];
    }

    /**
     * Get human-readable health status message
     *
     * @param string $status Health status (healthy, warning, critical)
     * @param float  $success_rate Success rate percentage
     * @return string Human-readable message
     */
    private static function get_health_message(string $status, float $success_rate, int $total): string
    {
        if ($total === 0) {
            return __('No CRM activity recorded yet.', 'contact-inbox');
        }

        switch ($status) {
            case 'critical':
                return sprintf(
                    __('Critical: Only %.1f%% of CRM sends are succeeding. Check endpoints and authentication.', 'contact-inbox'),
                    $success_rate
                );
            case 'warning':
                return sprintf(
                    __('Warning: CRM success rate is %.1f%%. Monitor for issues.', 'contact-inbox'),
                    $success_rate
                );
            case 'healthy':
                return sprintf(
                    __('Healthy: CRM integration operating normally (%.1f%% success rate)', 'contact-inbox'),
                    $success_rate
                );
            default:
                return __('CRM status is unknown. Awaiting activity.', 'contact-inbox');
        }
    }
}
