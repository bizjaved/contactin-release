<?php
/**
 * GDPR Repository
 *
 * Handles GDPR token management and data deletion.
 * Extracted from DB class for better separation of concerns.
 *
 * Tokens are stored directly in the messages table (gdpr_token, gdpr_expires columns)
 * to match the original implementation.
 *
 * @package ContactInbox\Core\Repositories
 */

declare(strict_types=1);

namespace ContactInbox\Core\Repositories;

use ContactInbox\Core\Config;
use ContactInbox\Core\Logger;

if (!defined('ABSPATH')) exit;

final class GDPRRepository {
    
    private string $table_messages;
    private string $table_gdpr_log;
    
    public function __construct() {
        global $wpdb;
        $this->table_messages = $wpdb->prefix . Config::TABLE_MESSAGES;
        $this->table_gdpr_log = $wpdb->prefix . Config::TABLE_GDPR_DELETION_LOG;
    }

    /**
     * Build GDPR log WHERE clause and parameters.
     */
    private function build_log_filters(string $search, string $crm_status, string $deletion_status): array {
        global $wpdb;

        $where_clauses = [];
        $where_params = [];

        if ($search !== '') {
            $where_clauses[] = "(email LIKE %s OR name LIKE %s)";
            $search_term = '%' . $wpdb->esc_like($search) . '%';
            $where_params[] = $search_term;
            $where_params[] = $search_term;
        }

        if ($crm_status !== 'all') {
            $where_clauses[] = "crm_sync_status = %s";
            $where_params[] = $crm_status;
        }

        if ($deletion_status !== 'all') {
            $where_clauses[] = "deletion_status = %s";
            $where_params[] = $deletion_status;
        }

        $where_sql = !empty($where_clauses) ? 'WHERE ' . implode(' AND ', $where_clauses) : '';

        return [$where_sql, $where_params];
    }

    /**
     * Count GDPR deletion logs with optional filters.
     */
    public function count_logs(string $search, string $crm_status, string $deletion_status): int {
        global $wpdb;

        [$where_sql, $where_params] = $this->build_log_filters($search, $crm_status, $deletion_status);

        $count_sql = "SELECT COUNT(*) FROM {$this->table_gdpr_log} {$where_sql}";

        if (!empty($where_params)) {
            return (int) $wpdb->get_var($wpdb->prepare($count_sql, $where_params));
        }

        return (int) $wpdb->get_var($count_sql);
    }

    /**
     * Fetch GDPR deletion logs with pagination.
     */
    public function get_logs(string $search, string $crm_status, string $deletion_status, int $limit, int $offset): array {
        global $wpdb;

        [$where_sql, $where_params] = $this->build_log_filters($search, $crm_status, $deletion_status);

        $logs_sql = "SELECT * FROM {$this->table_gdpr_log} {$where_sql} ORDER BY deleted_at DESC LIMIT %d OFFSET %d";
        $logs_params = array_merge($where_params, [$limit, $offset]);

        return $wpdb->get_results($wpdb->prepare($logs_sql, $logs_params)) ?: [];
    }

    /**
     * Fetch GDPR logs for CSV export.
     */
    public function get_export_rows(string $search, string $crm_status, string $deletion_status, int $limit, int $offset): array {
        global $wpdb;

        [$where_sql, $where_params] = $this->build_log_filters($search, $crm_status, $deletion_status);

        $query = "SELECT email, name, crm_sync_status, deletion_status, deleted_by, deleted_at
                  FROM {$this->table_gdpr_log}
                  {$where_sql}
                  ORDER BY deleted_at DESC
                  LIMIT %d OFFSET %d";

        $query_params = array_merge($where_params, [$limit, $offset]);

        return $wpdb->get_results($wpdb->prepare($query, $query_params), ARRAY_A) ?: [];
    }

    /**
     * Count synced and completed GDPR logs.
     */
    public function count_synced_completed(): int {
        global $wpdb;

        $synced_count = $wpdb->get_var(
            "SELECT COUNT(*) FROM {$this->table_gdpr_log} WHERE crm_sync_status = 'synced' AND deletion_status = 'completed'"
        );

        return (int) $synced_count;
    }

    /**
     * Prune old GDPR logs (excluding synced records).
     */
    public function prune_old_logs(int $retention_days) {
        global $wpdb;

        return $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM {$this->table_gdpr_log}
                 WHERE deleted_at < DATE_SUB(NOW(), INTERVAL %d DAY)
                 AND crm_sync_status != 'synced'",
                $retention_days
            )
        );
    }

    /**
     * Clear all non-synced GDPR logs.
     */
    public function clear_non_synced_logs() {
        global $wpdb;

        return $wpdb->query(
            "DELETE FROM {$this->table_gdpr_log} WHERE crm_sync_status != 'synced'"
        );
    }

    /**
     * Generate GDPR token
     */
    public function generate_token(int $message_id, int $expires): ?string {
        $token = wp_generate_uuid4();
        return $this->save_token($message_id, $token, $expires) ? $token : null;
    }

    /**
     * Save GDPR token to messages table
     */
    public function save_token(int $message_id, string $token, int $expires): bool {
        global $wpdb;

        $updated = $wpdb->update(
            $this->table_messages,
            [
                'gdpr_token'   => $token,
                'gdpr_expires' => $expires,
            ],
            ['id' => $message_id],
            ['%s', '%d'],
            ['%d']
        );

        if ($updated === false) {
            // Always log error, not just in WP_DEBUG
            Logger::error(
                'GDPR token save failed',
                [
                    'message_id' => $message_id,
                    'token'      => $token,
                    'expires'    => $expires,
                    'query'      => $wpdb->last_query ?? '',
                    'error'      => $wpdb->last_error,
                    'result'     => $updated,
                ]
            );
        } else {
            Logger::debug('GDPR token save success', [
                'message_id' => $message_id,
                'token'      => $token,
                'expires'    => $expires,
                'result'     => $updated,
            ]);
        }

        return $updated !== false;
    }

    /**
     * Validate GDPR token and get message ID
     */
    public function validate_token_get_id(string $token, ?string $email = null): ?int {
        global $wpdb;

        $conditions = ['gdpr_token = %s'];
        $params     = [$token];

        if ($email !== null) {
            $conditions[] = 'email = %s';
            $params[]     = $email;
        }

        $where = implode(' AND ', $conditions);

        $row = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT id, gdpr_expires FROM {$this->table_messages} WHERE {$where} LIMIT 1",
                $params
            )
        );

        if (!$row) {
            return null;
        }

        return ((int) $row->gdpr_expires > time()) ? (int) $row->id : null;
    }

    /**
     * Clear GDPR token
     */
    public function clear_token(int $message_id): bool {
        global $wpdb;

        $updated = $wpdb->update(
            $this->table_messages,
            [
                'gdpr_token'   => null,
                'gdpr_expires' => null,
            ],
            ['id' => $message_id],
            [null, null],
            ['%d']
        );

        if ($updated === false && WP_DEBUG) {
            Logger::error(
                'GDPR token clear failed',
                [
                    'message_id' => $message_id,
                    'error'      => $wpdb->last_error,
                ]
            );
        }

        return $updated !== false;
    }

    /**
     * Delete expired GDPR data and messages
     */
    public function delete_expired(): int {
        global $wpdb;

        $now = time();

        // Get all messages with expired GDPR data
        $expired_ids = $wpdb->get_col(
            $wpdb->prepare(
                "SELECT id FROM {$this->table_messages} WHERE gdpr_expires IS NOT NULL AND gdpr_expires < %d",
                $now
            )
        );

        if (empty($expired_ids)) {
            return 0;
        }

        $deleted = 0;
        foreach ($expired_ids as $id) {
            $id = (int) $id;
            
            // Delete associated email logs
            $wpdb->delete(
                $wpdb->prefix . Config::TABLE_EMAIL_LOG,
                ['message_id' => $id],
                ['%d']
            );

            // Delete associated webhook logs
            $wpdb->delete(
                $wpdb->prefix . Config::TABLE_WEBHOOK_LOG,
                ['message_id' => $id],
                ['%d']
            );

            // Delete the message
            if ($wpdb->delete($this->table_messages, ['id' => $id], ['%d'])) {
                $deleted++;

                if (WP_DEBUG) {
                    Logger::notice(
                        'GDPR expired message deleted',
                        [ 'message_id' => $id ]
                    );
                }
            }
        }

        return $deleted;
    }

    /**
     * Get GDPR deletion stats.
     */
    public function get_deletion_stats(): array {
        global $wpdb;

        $stats = $wpdb->get_row(
            "SELECT
                COUNT(*) as total_deletions,
                SUM(CASE WHEN crm_sync_status = 'deleted' THEN 1 ELSE 0 END) as crm_deleted,
                SUM(CASE WHEN crm_sync_status = 'synced' AND deletion_status = 'completed' THEN 1 ELSE 0 END) as ready_for_deletion
            FROM {$this->table_gdpr_log}",
            ARRAY_A
        );

        return is_array($stats) ? $stats : [];
    }

    /**
     * Check if GDPR deletion log table exists
     */
    public function table_exists(): bool {
        global $wpdb;
        
        $exists = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = %s AND table_name = %s",
                DB_NAME,
                Config::TABLE_GDPR_DELETION_LOG
            )
        );
        
        return (bool) $exists;
    }

    /**
     * Log GDPR deletion
     */
    public function log_deletion(array $log_data): bool {
        global $wpdb;

        // Ensure all required fields are present, use empty string for NULL values in string columns
        $insert_data = [
            'contact_id' => !empty($log_data['contact_id']) ? (int)$log_data['contact_id'] : null,
            'email' => $log_data['email'] ?? '',
            'name' => $log_data['name'] ?? '',
            'crm_sync_status' => $log_data['crm_sync_status'] ?? 'unknown',
            'messages_deleted' => !empty($log_data['messages_deleted']) ? (int)$log_data['messages_deleted'] : 0,
            'messages_synced' => !empty($log_data['messages_synced']) ? (int)$log_data['messages_synced'] : 0,
            'messages_unsynced' => !empty($log_data['messages_unsynced']) ? (int)$log_data['messages_unsynced'] : 0,
            'files_deleted' => !empty($log_data['files_deleted']) ? (int)$log_data['files_deleted'] : 0,
            'deletion_status' => $log_data['deletion_status'] ?? 'completed',
            'error_message' => !empty($log_data['error_message']) ? $log_data['error_message'] : null,
            'deleted_by' => !empty($log_data['deleted_by']) ? (int)$log_data['deleted_by'] : 0,
            'deleted_at' => $log_data['deleted_at'] ?? current_time('mysql'),
        ];

        $format = ['%d', '%s', '%s', '%s', '%d', '%d', '%d', '%d', '%s', '%s', '%d', '%s'];
        
        $result = $wpdb->insert($this->table_gdpr_log, $insert_data, $format);
        
        if ($result === false) {
            $error_msg = $wpdb->last_error ?: 'Unknown database error';
            Logger::log(Logger::ERROR, 'GDPR deletion log insert failed: ' . $error_msg, [
                'log_data' => $log_data,
                'insert_data' => $insert_data,
                'error' => $error_msg,
                'table' => $this->table_gdpr_log,
                'last_query' => $wpdb->last_query,
            ]);
            return false;
        }
        
        return true;
    }

    /**
     * Get deletion logs ready for CRM sync (completed and synced)
     *
     * @param int $limit Maximum number of records to retrieve
     * @return array Array of deletion log records
     */
    public function get_ready_for_crm_deletion(int $limit = 100): array {
        global $wpdb;

        $results = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT id, email, name, contact_id FROM {$this->table_gdpr_log} 
                 WHERE crm_sync_status = %s 
                 AND deletion_status = %s
                 AND crm_deletion_queued_at IS NULL
                 ORDER BY deleted_at DESC
                 LIMIT %d",
                'synced',
                'completed',
                $limit
            )
        );

        return $results ?: [];
    }

    /**
     * Update GDPR log when deletion is queued
     *
     * @param int $log_id GDPR log ID
     * @param string $timestamp Timestamp to set (defaults to current time)
     * @return bool Success
     */
    public function mark_deletion_queued(int $log_id, ?string $timestamp = null): bool {
        global $wpdb;

        $timestamp = $timestamp ?? current_time('mysql');

        $result = $wpdb->update(
            $this->table_gdpr_log,
            [
                'crm_deletion_queued_at' => $timestamp,
                'deletion_status' => 'in_progress',
            ],
            ['id' => $log_id],
            ['%s', '%s'],
            ['%d']
        );

        return $result !== false;
    }

    /**
     * Check if a contact has already been queued for CRM deletion
     *
     * @param int $log_id GDPR log ID
     * @return bool True if already queued
     */
    public function is_deletion_queued(int $log_id): bool {
        global $wpdb;

        $result = $wpdb->get_var($wpdb->prepare(
            "SELECT crm_deletion_queued_at FROM {$this->table_gdpr_log}
             WHERE id = %d AND crm_deletion_queued_at IS NOT NULL",
            $log_id
        ));

        return $result !== null;
    }

    /**
     * Start transaction
     */
    public function start_transaction(): void {
        global $wpdb;
        $wpdb->query('START TRANSACTION');
    }

    /**
     * Commit transaction
     */
    public function commit_transaction(): void {
        global $wpdb;
        $wpdb->query('COMMIT');
    }

    /**
     * Rollback transaction
     */
    public function rollback_transaction(): void {
        global $wpdb;
        $wpdb->query('ROLLBACK');
    }

    /**
     * Get failed or pending deletions older than specified hours
     *
     * @param int $limit Maximum number of records to retrieve
     * @param int $hours_old Minimum age in hours
     * @return array Array of deletion log records
     */
    public function get_failed_pending_deletions(int $limit = 10, int $hours_old = 1): array {
        global $wpdb;

        $results = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$this->table_gdpr_log} 
                 WHERE deletion_status IN ('failed', 'pending') 
                 AND deleted_at < DATE_SUB(NOW(), INTERVAL %d HOUR)
                 LIMIT %d",
                $hours_old,
                $limit
            )
        );

        return is_array($results) ? $results : [];
    }

    /**
     * Update deletion status for a log entry
     *
     * @param int $log_id GDPR log ID
     * @param string $status New status (completed, failed, pending)
     * @param string|null $error Error message if failed
     * @param int|null $messages_deleted Number of messages deleted
     * @return bool Success status
     */
    public function update_deletion_status(int $log_id, string $status, ?string $error = null, ?int $messages_deleted = null): bool {
        global $wpdb;

        $update_data = [
            'deletion_status' => $status,
            'error_message' => $error,
        ];
        $format = ['%s', '%s'];

        if ($messages_deleted !== null) {
            $update_data['messages_deleted'] = $messages_deleted;
            $format[] = '%d';
        }

        $result = $wpdb->update(
            $this->table_gdpr_log,
            $update_data,
            ['id' => $log_id],
            $format,
            ['%d']
        );

        return $result !== false;
    }
}
