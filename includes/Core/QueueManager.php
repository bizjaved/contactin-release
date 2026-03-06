<?php
/**
 * Queue Manager – Manages async operation queue with retry logic
 *
 * Handles queuing of async operations (email, CRM, webhooks) with:
 * - Exponential backoff retry strategy
 * - Dead letter queue for permanently failed items
 * - Status tracking and monitoring
 *
 * @package ContactInbox\Core
 */

declare(strict_types=1);

namespace ContactInbox\Core;

use ContactInbox\Traits\Singleton;
use ContactInbox\Core\Repositories\QueueRepository;
use ContactInbox\Core\Config;
use WP_Error;

if (!defined('ABSPATH')) exit;

final class QueueManager {
    use Singleton;

    private QueueRepository $queue_repo;

    // Retry configuration
    private const MAX_RETRIES = 4;

    private function __construct() {
        $this->queue_repo = new QueueRepository();
    }

    /**
     * Push item to queue with intelligent deduplication
     *
     * Prevents duplicate queue entries for the same message_id + type combination
     * within a configurable time window (default 10 seconds)
     *
     * @param string $type Queue item type (email, crm, webhook)
     * @param array $data Data payload
     * @param string $message_id Associated message ID
     * @param int $priority Priority level (1=high, 5=low, default=3)
     * @return int|WP_Error Queue ID or error
     */
    public static function push(
        string $type,
        array $data,
        string $message_id = '',
        int $priority = 3
    ): int|WP_Error {
        $instance = self::instance();
        
        // Deduplication: Check for existing identical queue item
        // Only check if message_id is provided (webhooks might not have it)
        if (!empty($message_id)) {
            $duplicate = $instance->queue_repo->find_recent_duplicate(
                $type,
                $message_id,
                10 // seconds - prevent duplicates within this window
            );
            
            if ($duplicate) {
                Logger::warning('Duplicate queue item prevented', [
                    'type' => $type,
                    'message_id' => $message_id,
                    'existing_queue_id' => $duplicate['id'],
                    'existing_status' => $duplicate['status'],
                ]);
                
                // Return the existing queue ID instead of creating duplicate
                return (int)$duplicate['id'];
            }
        }
        
        return $instance->queue_repo->insert(
            [
                'type'        => $type,
                'data'        => wp_json_encode($data),
                'message_id'  => $message_id,
                'priority'    => $priority,
                'status'      => 'pending',
                'retry_count' => 0,
                'last_error'  => null,
                'created_at'  => current_time('mysql'),
            ]
        );
    }

    /**
     * Get next item to process from queue
     *
     * Retrieves highest priority pending/retry item
     *
     * @param  array|null $types Optional array of queue types to filter by
     * @return array|null Queue item or null if empty
     */
    public static function get_next_item(?array $types = null): ?array {
        $instance = self::instance();
        return $instance->queue_repo->get_next_pending($types);
    }

    /**
     * Check for pending queue items of a specific type.
     */
    public static function has_pending_type(string $type): bool {
        $instance = self::instance();
        return $instance->queue_repo->has_pending_type($type);
    }

    /**
     * Mark item as processing
     *
     * @param int $queue_id Queue item ID
     * @return bool Success
     */
    public static function mark_processing(int $queue_id): bool {
        $instance = self::instance();
        return $instance->queue_repo->update_status($queue_id, 'processing');
    }

    /**
     * Mark item as completed
     *
     * @param int $queue_id Queue item ID
     * @param array $result Result data
     * @return bool Success
     */
    public static function mark_completed(int $queue_id, array $result = []): bool {
        $instance = self::instance();
        
        // Update queue status
        $success = $instance->queue_repo->update_status($queue_id, 'completed');
        
        if ($success) {
            // Log completion
            $instance->queue_repo->log_execution(
                $queue_id,
                'completed',
                'Successfully processed',
                $result
            );
        }

        return $success;
    }

    /**
     * Mark item as failed and decide retry/dlq
     *
     * @param int $queue_id Queue item ID
     * @param string $error Error message
     * @param string $error_type Optional error classification
     * @return bool Success
     */
    public static function mark_failed(int $queue_id, string $error = '', string $error_type = ''): bool {
        $instance = self::instance();
        $item = $instance->queue_repo->get_by_id($queue_id);

        if (!$item) {
            return false;
        }

        $retry_count = (int)$item['retry_count'];

        // Check if we should retry
        if ($retry_count < self::MAX_RETRIES) {
            // Calculate next retry time (adaptive backoff with jitter)
            $delay_seconds = RetryStrategy::get_delay($retry_count, $error_type);
            $next_attempt = current_time('timestamp') + $delay_seconds;
            
            return $instance->queue_repo->update(
                $queue_id,
                [
                    'status'       => 'retry',
                    'retry_count'  => $retry_count + 1,
                    'last_error'   => $error,
                    'next_attempt' => date('Y-m-d H:i:s', $next_attempt),
                ]
            ) && $instance->queue_repo->log_execution(
                $queue_id,
                'retry',
                "Scheduled retry #" . ($retry_count + 1) . " in {$delay_seconds}s. Error: {$error}",
                []
            );
        }

        // Max retries exceeded - move to dead letter queue
        return self::move_to_dlq($queue_id, $error);
    }

    /**
     * Mark a failed queue item for retry
     *
     * Resets the status of a failed queue item back to 'pending' so it can be retried.
     * Clears error messages and retry count.
     *
     * @param int $queue_id Queue item ID
     * @return bool Success
     */
    public static function mark_retry(int $queue_id): bool {
        $instance = self::instance();
        return $instance->queue_repo->reset_item_for_retry($queue_id);
    }

    /**
     * Mark item as non-retryable and move directly to DLQ
     *
     * For permanent failures (auth errors, validation errors, bad data) that should
     * not be retried. Immediately moves to dead letter queue bypassing retry logic.
     *
     * @param int $queue_id Queue item ID
     * @param string $error_type Error classification (AUTH, VALIDATION, FIELD_MAPPING, etc)
     * @param string $error_message Detailed error message
     * @return bool Success
     */
    public static function mark_non_retryable(int $queue_id, string $error_type, string $error_message): bool {
        $instance = self::instance();
        $item = $instance->queue_repo->get_by_id($queue_id);

        if (!$item) {
            return false;
        }

        $dlq_reason = "[{$error_type}] {$error_message}";

        // Directly move to DLQ, bypassing retry logic
        $dlq_success = self::move_to_dlq($queue_id, $dlq_reason);

        if ($dlq_success) {
            Logger::info('Non-retryable error detected, moved to DLQ', [
                'queue_id' => $queue_id,
                'type' => $item['type'],
                'message_id' => $item['message_id'],
                'error_type' => $error_type,
                'error_message' => $error_message,
            ]);
        }

        return $dlq_success;
    }

    /**
     * Move item to dead letter queue
     *
     * @param int $queue_id Queue item ID
     * @param string $reason Reason for DLQ
     * @return bool Success
     */
    public static function move_to_dlq(int $queue_id, string $reason = ''): bool {
        $instance = self::instance();
        $item = $instance->queue_repo->get_by_id($queue_id);

        if (!$item) {
            return false;
        }

        // Insert to DLQ
        $dlq_success = $instance->queue_repo->insert_dlq(
            [
                'queue_id'    => $queue_id,
                'type'        => $item['type'],
                'data'        => $item['data'],
                'message_id'  => $item['message_id'],
                'retry_count' => $item['retry_count'],
                'last_error'  => $item['last_error'],
                'dlq_reason'  => $reason,
                'created_at'  => $item['created_at'],
                'moved_at'    => current_time('mysql'),
            ]
        );

        if ($dlq_success) {
            // Update queue status to dlq
            $instance->queue_repo->update_status($queue_id, 'dlq');

            Logger::critical(
                'Queue item moved to dead letter queue',
                [
                    'queue_id' => $queue_id,
                    'type'     => $item['type'],
                    'reason'   => $reason,
                ]
            );

            return true;
        }

        return false;
    }

    /**
     * Get queue statistics
     *
     * @return array Queue stats
     */
    public static function get_stats(): array {
        $instance = self::instance();
        return $instance->queue_repo->get_stats();
    }

    /**
     * Get queue statistics grouped by type
     *
     * @return array
     */
    public static function get_stats_by_type(?int $days = null, ?string $start_date = null, ?string $end_date = null): array {
        $instance = self::instance();
        return $instance->queue_repo->get_stats_by_type($days, $start_date, $end_date);
    }

    /**
     * Get daily queue item counts grouped by type
     */
    public static function get_daily_counts_by_type(int $days = 7): array {
        $instance = self::instance();
        return $instance->queue_repo->get_daily_counts_by_type($days);
    }

    /**
     * Retry all DLQ items in batches.
     *
     * @param int $batch_size Batch size per query
     * @return array{retried:int}
     */
    public static function retry_all_dlq(int $batch_size = 200): array {
        $instance = self::instance();
        $retried = 0;
        $offset = 0;

        do {
            $ids = $instance->queue_repo->get_dlq_ids_batch($batch_size, $offset);
            if (empty($ids)) {
                break;
            }

            foreach ($ids as $dlq_id) {
                $res = self::retry_dlq_item((int) $dlq_id);
                if (!is_wp_error($res)) {
                    $retried++;
                }
            }

            $offset += $batch_size;
        } while (count($ids) === $batch_size);

        return ['retried' => $retried];
    }

    /**
     * Get queue items by status
     *
     * @param string $status Status filter
     * @param int $limit Limit results
     * @return array Queue items
     */
    public static function get_items(string $status = '', int $limit = 50): array {
        $instance = self::instance();
        return $instance->queue_repo->get_items($status, $limit);
    }

    /**
     * Get dead letter queue items
     *
     * @param int $limit Limit results
     * @return array DLQ items
     */
    public static function get_dlq_items(int $limit = 50): array {
        $instance = self::instance();
        return $instance->queue_repo->get_dlq_items($limit);
    }

    /**
     * Retry DLQ item
     *
     * @param int $dlq_id Dead letter queue ID
     * @return int|WP_Error New queue ID or error
     */
    public static function retry_dlq_item(int $dlq_id): int|WP_Error {
        $instance = self::instance();
        $item = $instance->queue_repo->get_dlq_by_id($dlq_id);

        if (!$item) {
            return new WP_Error(
                'dlq_item_not_found',
                __('DLQ item not found.', 'contact-inbox')
            );
        }

        // Create new queue entry with reset retry count
        $queue_id = self::push(
            $item['type'],
            json_decode($item['data'], true) ?: [],
            (int)$item['message_id'],
            3 // Reset to normal priority
        );

        if (is_int($queue_id)) {
            // Mark DLQ item as retried in dead letter table
            $instance->queue_repo->update_dlq_status($dlq_id, 'retried');

            // Reset original queue record if still present
            if (!empty($item['queue_id'])) {
                $instance->queue_repo->update(
                    (int)$item['queue_id'],
                    [
                        'status'       => 'pending',
                        'retry_count'  => 0,
                        'last_error'   => '',
                        'next_attempt' => current_time('mysql'),
                    ]
                );
            }

            Logger::info(
                'DLQ item retried',
                ['dlq_id' => $dlq_id, 'new_queue_id' => $queue_id]
            );
        }

        return $queue_id;
    }

    /**
     * Clear old completed items
     *
     * @param int $days Older than X days
     * @return int Items deleted
     */
    public static function clear_completed(int $days = 7): int {
        $instance = self::instance();
        return $instance->queue_repo->delete_older_than('completed', $days);
    }

    /**
     * Clear old failed items from DLQ
     *
     * @param int $days Older than X days
     * @return int Items deleted
     */
    public static function clear_dlq(int $days = 30): int {
        $instance = self::instance();
        return $instance->queue_repo->delete_dlq_older_than($days);
    }

    /**
     * Reset processing items older than the given age back to pending.
     */
    public static function reset_processing(int $age_minutes): int {
        $instance = self::instance();
        return $instance->queue_repo->reset_processing_to_pending($age_minutes);
    }

    /**
     * Get next retry time for item
     *
     * @param int $queue_id Queue ID
     * @return string|null ISO 8601 timestamp or null
     */
    public static function get_next_retry(int $queue_id): ?string {
        $instance = self::instance();
        $item = $instance->queue_repo->get_by_id($queue_id);
        return $item ? $item['next_attempt'] ?? null : null;
    }

    /**
     * Skip/complete email items when SMTP is disabled, and clear email DLQ.
     *
     * @return array{updated:int,dlq_cleared:int,message:string}
     */
    public static function skip_email_items_if_smtp_disabled(): array {
        $settings = get_option(Config::OPTION_SETTINGS, []);
        if (!empty($settings['smtp_enable'])) {
            return [
                'skipped_admin' => 0,
                'skipped_user'  => 0,
                'message'       => 'SMTP enabled; no changes applied',
            ];
        }

        global $wpdb;
        $table_messages = $wpdb->prefix . Config::TABLE_MESSAGES;

        // Skip pending or failed admin email notifications
        $admin_skipped = (int) $wpdb->query(
            $wpdb->prepare(
                "UPDATE {$table_messages}
                 SET admin_email_status = %s,
                     admin_email_error = NULL,
                     admin_email_sent_at = NULL,
                     admin_email_retries = 0
                 WHERE admin_email_status IS NULL
                    OR admin_email_status IN (%s, %s, %s)",
                Config::EMAIL_SKIPPED,
                Config::EMAIL_PENDING,
                Config::EMAIL_PROCESSING,
                Config::EMAIL_FAILED
            )
        );

        // Skip pending or failed user email copies
        $user_skipped = (int) $wpdb->query(
            $wpdb->prepare(
                "UPDATE {$table_messages}
                 SET user_email_status = %s,
                     user_email_error = NULL,
                     user_email_sent_at = NULL,
                     user_email_retries = 0
                 WHERE user_email_status IS NULL
                    OR user_email_status IN (%s, %s, %s)",
                Config::EMAIL_SKIPPED,
                Config::EMAIL_PENDING,
                Config::EMAIL_PROCESSING,
                Config::EMAIL_FAILED
            )
        );

        return [
            'skipped_admin' => $admin_skipped,
            'skipped_user'  => $user_skipped,
            'message'       => 'Email message channels marked as skipped because SMTP is disabled',
        ];
    }
}
