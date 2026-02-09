<?php
/**
 * Salesforce Attachment Repository
 * 
 * Handles logging of attachment uploads to Salesforce.
 * All database operations go through this class.
 *
 * @package ContactInbox\Core\Repositories
 */

declare(strict_types=1);

namespace ContactInbox\Core\Repositories;

use ContactInbox\Core\Config;

if (!defined('ABSPATH')) {
    exit;
}

final class SalesforceAttachmentRepository {
    
    private string $table;
    
    public function __construct() {
        global $wpdb;
        $this->table = $wpdb->prefix . Config::TABLE_SF_ATTACHMENTS;
    }

    /**
     * Log attachment upload attempt/result.
     *
     * @return int Insert ID
     */
    public function log_attachment(
        int $message_id,
        string $filename,
        int $file_size,
        string $case_id,
        ?string $content_version_id = null,
        ?string $content_document_id = null,
        string $status = 'pending',
        ?string $error_message = null
    ): int {
        global $wpdb;

        $wpdb->insert(
            $this->table,
            [
                'message_id' => $message_id,
                'filename' => sanitize_file_name($filename),
                'file_size' => $file_size,
                'case_id' => sanitize_text_field($case_id),
                'content_version_id' => $content_version_id ? sanitize_text_field($content_version_id) : null,
                'content_document_id' => $content_document_id ? sanitize_text_field($content_document_id) : null,
                'status' => sanitize_text_field($status),
                'error_message' => $error_message ? sanitize_textarea_field($error_message) : null,
            ],
            [
                '%d',
                '%s',
                '%d',
                '%s',
                '%s',
                '%s',
                '%s',
                '%s',
            ]
        );

        return $wpdb->insert_id;
    }

    /**
     * Update attachment log status.
     */
    public function update_status(
        int $log_id,
        string $status,
        ?string $content_version_id = null,
        ?string $content_document_id = null,
        ?string $error_message = null
    ): bool {
        global $wpdb;

        $result = $wpdb->update(
            $this->table,
            [
                'status' => sanitize_text_field($status),
                'content_version_id' => $content_version_id ? sanitize_text_field($content_version_id) : null,
                'content_document_id' => $content_document_id ? sanitize_text_field($content_document_id) : null,
                'error_message' => $error_message ? sanitize_textarea_field($error_message) : null,
                'updated_at' => current_time('mysql'),
            ],
            ['id' => $log_id],
            [
                '%s',
                '%s',
                '%s',
                '%s',
                '%s',
            ],
            ['%d']
        );

        return $result !== false;
    }

    /**
     * Get attachment by ID.
     *
     * @return array|null Attachment record or null if not found
     */
    public function get_by_id(int $id): ?array {
        global $wpdb;

        $result = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$this->table} WHERE id = %d",
                $id
            ),
            ARRAY_A
        );

        return $result ?: null;
    }

    /**
     * Get attachment by message and filename (for duplicate detection).
     *
     * @return array|null Attachment record or null if not found
     */
    public function get_by_message_and_filename(int $message_id, string $filename): ?array {
        global $wpdb;

        $result = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$this->table} WHERE message_id = %d AND filename = %s ORDER BY created_at DESC LIMIT 1",
                $message_id,
                sanitize_file_name($filename)
            ),
            ARRAY_A
        );

        return $result ?: null;
    }

    /**
     * Get attachment logs by message ID.
     *
     * @return array Array of attachment logs
     */
    public function get_by_message(int $message_id): array {
        global $wpdb;

        return $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$this->table} WHERE message_id = %d ORDER BY created_at DESC",
                $message_id
            ),
            ARRAY_A
        ) ?: [];
    }

    /**
     * Get attachment logs by case ID.
     *
     * @return array Array of attachment logs
     */
    public function get_by_case(string $case_id): array {
        global $wpdb;

        return $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$this->table} WHERE case_id = %s ORDER BY created_at DESC",
                $case_id
            ),
            ARRAY_A
        ) ?: [];
    }

    /**
     * Get attachment statistics.
     */
    public function get_stats(string $start_date = '', string $end_date = ''): array {
        global $wpdb;

        $where = '';
        if (!empty($start_date) && !empty($end_date)) {
            $where = $wpdb->prepare(
                " WHERE DATE(created_at) BETWEEN %s AND %s",
                $start_date,
                $end_date
            );
        }

        return $wpdb->get_row(
            "SELECT 
                COUNT(*) as total_uploads,
                SUM(CASE WHEN status = 'delivered' THEN 1 ELSE 0 END) as successful_uploads,
                SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) as failed_uploads,
                SUM(file_size) as total_size
            FROM {$this->table}
            {$where}",
            ARRAY_A
        ) ?: [];
    }

    /**
     * Get attachment status counts.
     */
    public function get_status_counts(): array {
        global $wpdb;

        $stats = $wpdb->get_row(
            "SELECT
                COUNT(*) as total,
                SUM(CASE WHEN status = 'delivered' THEN 1 ELSE 0 END) as delivered,
                SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) as failed,
                SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
                SUM(CASE WHEN status = 'queued' THEN 1 ELSE 0 END) as queued,
                SUM(CASE WHEN status = 'uploading' THEN 1 ELSE 0 END) as uploading
            FROM {$this->table}",
            ARRAY_A
        );

        return is_array($stats) ? $stats : [];
    }

    /**
     * Get recent failed uploads.
     */
    public function get_recent_failures(int $limit = 10): array {
        global $wpdb;

        return $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$this->table}
                WHERE status IN ('failed', 'pending')
                ORDER BY created_at DESC
                LIMIT %d",
                $limit
            ),
            ARRAY_A
        ) ?: [];
    }

    /**
     * Delete old attachment logs (cleanup).
     *
     * @param int $days_old Delete logs older than this many days
     * @return int Number of rows deleted
     */
    public function cleanup_old_logs(int $days_old = 90): int {
        global $wpdb;

        $result = $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM {$this->table}
                WHERE created_at < DATE_SUB(NOW(), INTERVAL %d DAY)
                AND status IN ('delivered', 'failed')",
                $days_old
            )
        );

        return $result ?: 0;
    }
}
