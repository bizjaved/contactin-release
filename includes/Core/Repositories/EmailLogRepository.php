<?php
/**
 * Email Log Repository
 *
 * Handles all email log database operations.
 * Extracted from DB class for better separation of concerns.
 *
 * @package ContactInbox\Core\Repositories
 */

declare(strict_types=1);

namespace ContactInbox\Core\Repositories;

use ContactInbox\Core\Config;

// Repository layer centralizes direct SQL access and dynamic table-name usage.
// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter

if (!defined('ABSPATH')) exit;

final class EmailLogRepository {
    
    private string $table_email_log;
    
    public function __construct() {
        global $wpdb;
        $this->table_email_log = $wpdb->prefix . Config::TABLE_EMAIL_LOG;
    }

    /**
     * Insert email log entry
     */
    public function insert(array $data): int {
        global $wpdb;

        $recipient = $data['recipient'] ?? '';
        if (is_array($recipient)) {
            $recipient = array_map(static function ($address): string {
                $address = trim((string) $address);
                if ($address === '') {
                    return '';
                }
                $sanitized = sanitize_email($address);
                return $sanitized !== '' ? $sanitized : sanitize_text_field($address);
            }, $recipient);
            $recipient = array_filter($recipient, static fn($value) => $value !== '');
            $recipient = implode(', ', $recipient);
        } else {
            $recipient = trim((string) $recipient);
        }

        $prepared = [
            'recipient'      => sanitize_text_field($recipient),
            'subject'        => sanitize_text_field($data['subject'] ?? ''),
            'type'           => sanitize_text_field($data['type'] ?? 'contact_form'),
            'status'         => sanitize_text_field($data['status'] ?? 'pending'),
            'error_message'  => isset($data['error_message']) ? sanitize_textarea_field($data['error_message']) : null,
        ];

        $format = ['%s', '%s', '%s', '%s', '%s'];
        $wpdb->insert($this->table_email_log, $prepared, $format);

        return $wpdb->insert_id;
    }

    /**
     * Get email logs with pagination
     */
    public function get_paginated(
        int $page = 1,
        int $per_page = 25,
        string $status = '',
        string $orderby = 'created_at',
        string $order = 'DESC'
    ): array {
        global $wpdb;

        $offset = max(0, ($page - 1) * $per_page);

        // Validate orderby column
        $allowed_columns = ['id', 'created_at', 'recipient', 'subject', 'status', 'error_message'];
        $orderby = in_array($orderby, $allowed_columns, true) ? $orderby : 'created_at';
        $order = strtoupper($order) === 'ASC' ? 'ASC' : 'DESC';

        $where = '';
        $where_values = [];

        if (!empty($status)) {
            $where = "WHERE status = %s";
            $where_values = [$status];
        }

        $query = "SELECT * FROM {$this->table_email_log} $where ORDER BY $orderby $order LIMIT %d OFFSET %d";
        $query_values = array_merge($where_values, [$per_page, $offset]);

        return $wpdb->get_results($wpdb->prepare($query, ...$query_values), ARRAY_A);
    }

    /**
     * Get email logs with limit and offset (direct parameters, not page-based)
     */
    public function get_with_limit_offset(
        int $limit = 100,
        int $offset = 0,
        string $status = '',
        string $orderby = 'created_at',
        string $order = 'DESC'
    ): array {
        global $wpdb;

        // Validate orderby column
        $allowed_columns = ['id', 'created_at', 'recipient', 'subject', 'status', 'error_message'];
        $orderby = in_array($orderby, $allowed_columns, true) ? $orderby : 'created_at';
        $order = strtoupper($order) === 'ASC' ? 'ASC' : 'DESC';

        $sql = "SELECT * FROM {$this->table_email_log}";
        $args = [];

        if ($status && $status !== 'all') {
            $sql .= " WHERE status = %s";
            $args[] = $status;
        }

        $sql .= " ORDER BY $orderby $order LIMIT %d OFFSET %d";
        $args[] = $limit;
        $args[] = $offset;

        return (array) $wpdb->get_results($wpdb->prepare($sql, ...$args), ARRAY_A);
    }
    public function bulk_delete(array $ids): int {
        global $wpdb;

        $ids = array_map('intval', $ids);
        if (empty($ids)) {
            return 0;
        }

        $deleted = 0;
        foreach ($ids as $id) {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
            $result = $wpdb->delete(
                $this->table_email_log,
                ['id' => $id],
                ['%d']
            );
            if ($result) {
                $deleted += (int) $result;
            }
        }

        return $deleted;
    }

    /**
     * Count email logs
     */
    public function count(string $status = '', ?int $days = null, ?string $start_date = null, ?string $end_date = null): int {
        global $wpdb;

        // Build WHERE clause for date filtering
        $where_parts = [];
        $where_values = [];

        if (!empty($status)) {
            $where_parts[] = "status = %s";
            $where_values[] = $status;
        }

        if ($start_date && $end_date) {
            $where_parts[] = "created_at >= %s AND created_at < DATE_ADD(%s, INTERVAL 1 DAY)";
            $where_values[] = $start_date;
            $where_values[] = $end_date;
        } elseif ($days && $days > 0) {
            $where_parts[] = "created_at >= DATE_SUB(NOW(), INTERVAL %d DAY)";
            $where_values[] = $days;
        }

        $where = !empty($where_parts) ? " WHERE " . implode(" AND ", $where_parts) : "";
        $query = "SELECT COUNT(*) FROM {$this->table_email_log}{$where}";

        if (!empty($where_values)) {
            return (int)$wpdb->get_var($wpdb->prepare($query, ...$where_values));
        }
        
        return (int)$wpdb->get_var($query);
    }

    /**
     * Get latest email log for a recipient (contact form context).
     */
    public function get_latest_for_recipient(string $recipient): ?array {
        global $wpdb;

        $recipient = sanitize_email($recipient);
        if (empty($recipient)) {
            return null;
        }

        $sql = $wpdb->prepare(
            "SELECT status, error_message, created_at
             FROM {$this->table_email_log}
             WHERE recipient = %s AND type = %s
             ORDER BY id DESC
             LIMIT 1",
            $recipient,
            'contact_form'
        );

        $row = $wpdb->get_row($sql, ARRAY_A);
        return $row ?: null;
    }

    /**
     * Get email log by ID
     */
    public function get_by_id(int $id): ?array {
        global $wpdb;

        $row = $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM {$this->table_email_log} WHERE id = %d", $id),
            ARRAY_A
        );

        return $row ?: null;
    }

    /**
     * Get adjacent email log (prev or next)
     * 
     * @param int $current_id Current log ID
     * @param string $direction 'prev' or 'next'
     * @param string $status Filter by status (empty string for all)
     * @return array|null
     */
    public function get_adjacent(int $current_id, string $direction = 'next', string $status = ''): ?array {
        global $wpdb;

        $operator = ($direction === 'prev') ? '<' : '>';
        $order = ($direction === 'prev') ? 'DESC' : 'ASC';

        $where_parts = ["id $operator %d"];
        $where_values = [$current_id];

        if (!empty($status) && $status !== 'all') {
            $where_parts[] = 'status = %s';
            $where_values[] = $status;
        }

        $where_clause = implode(' AND ', $where_parts);

        $query = "SELECT * FROM {$this->table_email_log} WHERE $where_clause ORDER BY id $order LIMIT 1";

        $row = $wpdb->get_row(
            $wpdb->prepare($query, ...$where_values),
            ARRAY_A
        );

        return $row ?: null;
    }

    /**
     * Update email log status
     */
    public function update_status(
        int $log_id,
        string $status,
        string $error_message = ''
    ): bool {
        global $wpdb;

        $update_data = [
            'status' => $status,
            'error_message' => !empty($error_message) ? $error_message : null,
        ];

        $result = $wpdb->update(
            $this->table_email_log,
            $update_data,
            ['id' => $log_id],
            ['%s', '%s'],
            ['%d']
        );

        return $result !== false;
    }

    /**
     * Prune old email logs based on retention days
     */
    public function prune(int $retention_days = 90): int {
        global $wpdb;

        $cutoff_date = wp_date('Y-m-d H:i:s', strtotime("-$retention_days days"));

        return (int)$wpdb->query($wpdb->prepare(
            "DELETE FROM {$this->table_email_log} WHERE created_at < %s",
            $cutoff_date
        ));
    }

    /**
     * Clear all email logs (delete everything)
     */
    public function clear_all(): int {
        global $wpdb;

        return (int)$wpdb->query("DELETE FROM {$this->table_email_log}");
    }

    /**
     * Get table name
     */
    public function get_table_name(): string {
        return $this->table_email_log;
    }
}
