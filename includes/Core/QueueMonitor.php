<?php
/**
 * Queue Monitor – Real-time Queue Health & Performance Tracking
 *
 * Monitors queue health, performance metrics, and triggers alerts
 * Tracks: processing times, success rates, error rates, queue depth
 *
 * @package ContactIn
 */

namespace ContactInbox\Core;

use ContactInbox\Traits\Singleton;

if (!defined('ABSPATH')) exit;
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class QueueMonitor {
    use Singleton;

    const METRIC_NAMESPACE = 'contactin_queue_metrics';
    const METRIC_PROCESSING_TIME = 'processing_time';
    const METRIC_SUCCESS_RATE = 'success_rate';
    const METRIC_ERROR_RATE = 'error_rate';
    const METRIC_DLQ_COUNT = 'dlq_count';
    const METRIC_QUEUE_DEPTH = 'queue_depth';

    /**
     * Record queue processing metrics
     *
     * @param string $operation_type Operation type (email, crm, webhook)
     * @param bool $success Whether operation succeeded
     * @param int $duration_ms Duration in milliseconds
     * @param string $error_msg Error message if failed
     */
    public static function record_operation(string $operation_type, bool $success, int $duration_ms, string $error_msg = ''): void {
        try {
            $metrics = self::get_or_create_metrics();
            
            // Update operation-specific metrics
            if (!isset($metrics['operations'][$operation_type])) {
                $metrics['operations'][$operation_type] = [
                    'total' => 0,
                    'success' => 0,
                    'failed' => 0,
                    'total_time_ms' => 0,
                    'last_checked' => current_time('mysql'),
                ];
            }

            $metrics['operations'][$operation_type]['total']++;
            $metrics['operations'][$operation_type]['total_time_ms'] += $duration_ms;

            if ($success) {
                $metrics['operations'][$operation_type]['success']++;
            } else {
                $metrics['operations'][$operation_type]['failed']++;
                $metrics['last_error'] = [
                    'operation' => $operation_type,
                    'message' => $error_msg,
                    'timestamp' => current_time('mysql'),
                ];
            }

            $metrics['last_updated'] = current_time('mysql');
            self::save_metrics($metrics);
        } catch (\Throwable $e) {
            Logger::error('Failed to record queue metrics', [
                'error' => $e->getMessage(),
                'operation' => $operation_type,
            ]);
        }
    }

    /**
     * Get current queue health status
     *
     * @return array Health status with overall rating
     */
    public static function get_health_status(): array {
        try {
            $stats = QueueManager::get_stats();
            $metrics = self::get_metrics();

            $pending = intval($stats['pending'] ?? 0);
            $processing = intval($stats['processing'] ?? 0);
            $retry = intval($stats['retry'] ?? 0);
            $dlq = intval($stats['dlq'] ?? 0);

            // Calculate health score (0-100)
            $health_score = 100;

            // Deduct for high queue depth
            if ($pending > 1000) {
                $health_score -= 30;
            } elseif ($pending > 500) {
                $health_score -= 20;
            } elseif ($pending > 100) {
                $health_score -= 10;
            }

            // Deduct for retry items
            if ($retry > 100) {
                $health_score -= 25;
            } elseif ($retry > 50) {
                $health_score -= 15;
            } elseif ($retry > 10) {
                $health_score -= 5;
            }

            // Deduct heavily for DLQ items
            if ($dlq > 100) {
                $health_score -= 50;
            } elseif ($dlq > 50) {
                $health_score -= 40;
            } elseif ($dlq > 10) {
                $health_score -= 30;
            } elseif ($dlq > 0) {
                $health_score -= 15;
            }

            // Calculate success rate
            $success_rate = self::calculate_success_rate($metrics);

            // Deduct for low success rate
            if ($success_rate < 90) {
                $health_score -= (90 - $success_rate) / 2;
            }

            $health_score = max(0, min(100, intval($health_score)));

            // Determine status
            if ($health_score >= 90) {
                $status = 'healthy';
                $level = 'info';
            } elseif ($health_score >= 70) {
                $status = 'caution';
                $level = 'warning';
            } elseif ($health_score >= 50) {
                $status = 'degraded';
                $level = 'alert';
            } else {
                $status = 'critical';
                $level = 'critical';
            }

            return [
                'status' => $status,
                'level' => $level,
                'health_score' => $health_score,
                'queue_depth' => $pending + $processing + $retry,
                'pending' => $pending,
                'processing' => $processing,
                'retry' => $retry,
                'dlq' => $dlq,
                'success_rate' => round($success_rate, 2),
                'average_processing_time_ms' => self::get_average_processing_time($metrics),
                'last_error' => $metrics['last_error'] ?? null,
                'last_checked' => current_time('mysql'),
            ];
        } catch (\Throwable $e) {
            Logger::error('Failed to get queue health status', [
                'error' => $e->getMessage(),
            ]);

            return [
                'status' => 'unknown',
                'level' => 'warning',
                'health_score' => 50,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Check if alerts should be triggered
     *
     * @return array Array of triggered alerts
     */
    public static function check_alerts(): array {
        try {
            $alerts = [];
            $health = self::get_health_status();

            // Critical alerts
            if ($health['dlq'] > 100) {
                $alerts[] = [
                    'type' => 'dlq_overflow',
                    'level' => 'critical',
                    'message' => sprintf('Dead Letter Queue has %d items', $health['dlq']),
                    'action' => 'Review failed operations in DLQ',
                ];
            }

            if ($health['pending'] > 1000) {
                $alerts[] = [
                    'type' => 'queue_overflow',
                    'level' => 'critical',
                    'message' => sprintf('Queue depth is %d items', $health['queue_depth']),
                    'action' => 'Queue processor may be overwhelmed',
                ];
            }

            // Warning alerts
            if ($health['success_rate'] < 80) {
                $alerts[] = [
                    'type' => 'low_success_rate',
                    'level' => 'warning',
                    'message' => sprintf('Success rate is only %.1f%%', $health['success_rate']),
                    'action' => 'Check last error and operation configuration',
                ];
            }

            if ($health['retry'] > 50) {
                $alerts[] = [
                    'type' => 'high_retry_count',
                    'level' => 'warning',
                    'message' => sprintf('Retry queue has %d items', $health['retry']),
                    'action' => 'Items are being retried, may indicate temporary failures',
                ];
            }

            // Info alerts
            if ($health['dlq'] > 0) {
                $alerts[] = [
                    'type' => 'dlq_present',
                    'level' => 'info',
                    'message' => sprintf('%d items in Dead Letter Queue', $health['dlq']),
                    'action' => 'Review and retry failed operations',
                ];
            }

            return $alerts;
        } catch (\Throwable $e) {
            Logger::error('Failed to check alerts', [
                'error' => $e->getMessage(),
            ]);
            return [];
        }
    }

    /**
     * Get performance metrics for dashboard
     *
     * @return array Performance data
     */
    public static function get_performance_metrics(): array {
        try {
            $metrics = self::get_metrics();

            if (empty($metrics['operations'])) {
                return [
                    'operations' => [],
                    'total_processed' => 0,
                    'total_success' => 0,
                    'total_failed' => 0,
                ];
            }

            $total_processed = 0;
            $total_success = 0;
            $total_failed = 0;
            $operations = [];

            foreach ($metrics['operations'] as $op_type => $op_data) {
                $total_processed += $op_data['total'];
                $total_success += $op_data['success'];
                $total_failed += $op_data['failed'];

                $success_rate = $op_data['total'] > 0 ? ($op_data['success'] / $op_data['total']) * 100 : 0;
                $avg_time = $op_data['total'] > 0 ? $op_data['total_time_ms'] / $op_data['total'] : 0;

                $operations[$op_type] = [
                    'total' => $op_data['total'],
                    'success' => $op_data['success'],
                    'failed' => $op_data['failed'],
                    'success_rate' => round($success_rate, 2),
                    'average_time_ms' => round($avg_time, 2),
                ];
            }

            return [
                'operations' => $operations,
                'total_processed' => $total_processed,
                'total_success' => $total_success,
                'total_failed' => $total_failed,
                'overall_success_rate' => $total_processed > 0 ? round(($total_success / $total_processed) * 100, 2) : 0,
            ];
        } catch (\Throwable $e) {
            Logger::error('Failed to get performance metrics', [
                'error' => $e->getMessage(),
            ]);
            return [];
        }
    }

    /**
     * Reset metrics (useful after alerts have been addressed)
     */
    public static function reset_metrics(): void {
        try {
            delete_transient(self::METRIC_NAMESPACE);
            Logger::notice('Queue metrics reset by admin');
        } catch (\Throwable $e) {
            Logger::error('Failed to reset metrics', ['error' => $e->getMessage()]);
        }
    }

    /**
     * Get or create metrics structure
     *
     * @return array Metrics array
     */
    private static function get_or_create_metrics(): array {
        $metrics = self::get_metrics();

        if (empty($metrics)) {
            $metrics = [
                'operations' => [],
                'last_updated' => current_time('mysql'),
            ];
        }

        return $metrics;
    }

    /**
     * Get stored metrics from transient
     *
     * @return array Metrics array
     */
    private static function get_metrics(): array {
        $metrics = get_transient(self::METRIC_NAMESPACE);
        return is_array($metrics) ? $metrics : [];
    }

    /**
     * Save metrics to transient (expires in 7 days)
     *
     * @param array $metrics Metrics data
     */
    private static function save_metrics(array $metrics): void {
        set_transient(self::METRIC_NAMESPACE, $metrics, WEEK_IN_SECONDS);
    }

    /**
     * Calculate overall success rate from metrics
     *
     * @param array $metrics Metrics array
     * @return float Success rate (0-100)
     */
    private static function calculate_success_rate(array $metrics): float {
        if (empty($metrics['operations'])) {
            return 100;
        }

        $total = 0;
        $success = 0;

        foreach ($metrics['operations'] as $op_data) {
            $total += $op_data['total'];
            $success += $op_data['success'];
        }

        return $total > 0 ? ($success / $total) * 100 : 100;
    }

    /**
     * Get average processing time from metrics
     *
     * @param array $metrics Metrics array
     * @return float Average time in milliseconds
     */
    private static function get_average_processing_time(array $metrics): float {
        if (empty($metrics['operations'])) {
            return 0;
        }

        $total_time = 0;
        $total_count = 0;

        foreach ($metrics['operations'] as $op_data) {
            $total_time += $op_data['total_time_ms'];
            $total_count += $op_data['total'];
        }

        return $total_count > 0 ? round($total_time / $total_count, 2) : 0;
    }
}
