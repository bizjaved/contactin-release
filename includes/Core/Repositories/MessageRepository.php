<?php
/**
 * Message Repository
 *
 * Handles all message-related database operations.
 * Extracted from DB class for better separation of concerns.
 *
 * @package ContactIn\Core\Repositories
 */

declare(strict_types=1);

namespace ContactInbox\Core\Repositories;

use ContactInbox\Core\Message;
use ContactInbox\Core\Config;

// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.Security.ValidatedSanitizedInput.MissingUnslash, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized, WordPress.WP.AlternativeFunctions.unlink_unlink, WordPress.WP.AlternativeFunctions.file_system_operations_fwrite, WordPress.WP.AlternativeFunctions.file_system_operations_is_writable, WordPress.WP.AlternativeFunctions.file_system_operations_fclose, WordPress.WP.AlternativeFunctions.rename_rename, WordPress.WP.AlternativeFunctions.file_system_operations_fopen, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber

if (!defined('ABSPATH')) exit;

final class MessageRepository {
    
    private string $table_messages;
    
    public function __construct() {
        global $wpdb;
        $this->table_messages = $wpdb->prefix . Config::TABLE_MESSAGES;
    }

    /**
     * Insert a new message
     */
    public function insert(array $data): int|false {
        global $wpdb;

        // Validate required fields
        $required = ['name', 'email', 'message'];
        foreach ($required as $field) {
            if (empty($data[$field])) {
                return false;
            }
        }

        // Prepare data
        $prepared = [
            'form_id'    => sanitize_text_field($data['form_id'] ?? 'default'),
            'contact_id' => isset($data['contact_id']) ? (int) $data['contact_id'] : null,
            'salutation' => isset($data['salutation']) ? sanitize_text_field($data['salutation']) : null,
            'name'       => sanitize_text_field($data['name']),
            'email'      => sanitize_email($data['email']),
            'phone'      => isset($data['phone']) ? sanitize_text_field($data['phone']) : null,
            'mobile_phone' => isset($data['mobile_phone']) ? sanitize_text_field($data['mobile_phone']) : null,
            'home_phone'   => isset($data['home_phone']) ? sanitize_text_field($data['home_phone']) : null,
            'other_phone'  => isset($data['other_phone']) ? sanitize_text_field($data['other_phone']) : null,
            'subject'    => isset($data['subject']) ? sanitize_text_field($data['subject']) : '',
            'message'    => wp_kses_post($data['message']),
            'attachment' => isset($data['attachment']) ? sanitize_text_field($data['attachment']) : null,
            'consent'    => isset($data['consent']) ? (int)$data['consent'] : 0,
            'ip_address' => $data['ip_address'] ?? $this->get_client_ip(),
            'user_agent' => $data['user_agent'] ?? ($_SERVER['HTTP_USER_AGENT'] ?? ''),
            'recaptcha_score' => isset($data['recaptcha_score']) ? (float)$data['recaptcha_score'] : null,
            'processing_time_ms' => isset($data['processing_time_ms']) ? (int)$data['processing_time_ms'] : null,
        ];

    $format = ['%s', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%s', '%s', '%f', '%d'];

        $result = $wpdb->insert($this->table_messages, $prepared, $format);

        if (!$result) {
            return false;
        }

        $insert_id = (int) $wpdb->insert_id;

        // Keep the contact's last activity fresh when we know the contact link.
        if (!empty($prepared['contact_id'])) {
            $contacts = new ContactRepository();
            $contacts->touch_last_message((int) $prepared['contact_id'], current_time('mysql'));
        }

        return $insert_id;
    }

    /**
     * Get messages with pagination and filtering
     */
    public function get_paginated(
        int $page = 1,
        int $per_page = 25,
        string $search = '',
        string $status = 'all',
        string $orderby = 'submitted_at',
        string $order = 'DESC',
        ?int $contact_id = null,
        ?string $intent = null
    ): array {
        global $wpdb;

        $offset = max(0, ($page - 1) * $per_page);
        $orderby = $this->validate_column_name($orderby);
        $order = strtoupper($order) === 'ASC' ? 'ASC' : 'DESC';
        $contact_id = $contact_id ? (int) $contact_id : null;

        $where_clauses = [];
        $where_values = [];

        // Status filter (including virtual spam)
        if ($status === Config::STATUS_SPAM) {
            $where_clauses[] = "recaptcha_score IS NOT NULL AND recaptcha_score < %f";
            $where_values[] = Config::SPAM_SCORE_THRESHOLD;
        } elseif ($status === Config::STATUS_ARCHIVED) {
            // Show only archived messages
            $where_clauses[] = "is_archived = %d";
            $where_values[] = 1;
        } elseif ($status !== 'all' && in_array($status, [Config::STATUS_UNREAD, Config::STATUS_READ], true)) {
            $where_clauses[] = "status = %s";
            $where_values[] = $status;
            // Exclude spam and archived from regular status views
            $where_clauses[] = "(recaptcha_score IS NULL OR recaptcha_score >= %f)";
            $where_values[] = Config::SPAM_SCORE_THRESHOLD;
            $where_clauses[] = "is_archived = %d";
            $where_values[] = 0;
        } elseif ($status === 'all') {
            // Exclude spam and archived from 'all' view
            $where_clauses[] = "(recaptcha_score IS NULL OR recaptcha_score >= %f)";
            $where_values[] = Config::SPAM_SCORE_THRESHOLD;
            $where_clauses[] = "is_archived = %d";
            $where_values[] = 0;
        }

        // Intent filter
        if (!empty($intent) && $intent !== 'all') {
            $where_clauses[] = "intent_category = %s";
            $where_values[] = $intent;
        }

        if ($contact_id) {
            $where_clauses[] = "contact_id = %d";
            $where_values[] = $contact_id;
        }

        // Search filter
        if (!empty($search)) {
            $search = '%' . $wpdb->esc_like($search) . '%';
            $where_clauses[] = "(name LIKE %s OR email LIKE %s OR message LIKE %s)";
            $where_values[] = $search;
            $where_values[] = $search;
            $where_values[] = $search;
        }

        $where = !empty($where_clauses) ? 'WHERE ' . implode(' AND ', $where_clauses) : '';

        $query = "SELECT * FROM {$this->table_messages} $where ORDER BY $orderby $order LIMIT %d OFFSET %d";
        $query_values = array_merge($where_values, [$per_page, $offset]);

        $results = $wpdb->get_results($wpdb->prepare($query, ...$query_values), OBJECT);

        if (empty($results)) {
            return [];
        }

        $messages = array_map(fn($row) => new Message($row), $results);

        if ($status === Config::STATUS_SPAM) {
            foreach ($messages as $message) {
                $message->intent_category = \ContactInbox\Core\IntentClassifier::CATEGORY_SPAM;
                if ($message->intent_confidence === null) {
                    $message->intent_confidence = 1.0;
                }
            }
        }

        return $messages;
    }

    public function count_by_contact(int $contact_id): int {
        global $wpdb;
        return (int) $wpdb->get_var(
            $wpdb->prepare("SELECT COUNT(*) FROM {$this->table_messages} WHERE contact_id = %d", $contact_id)
        );
    }

    public function get_by_contact(
        int $contact_id,
        int $page = 1,
        int $per_page = 20,
        string $orderby = 'submitted_at',
        string $order = 'DESC'
    ): array {
        global $wpdb;

        $offset = max(0, ($page - 1) * $per_page);
        $order = strtoupper($order) === 'ASC' ? 'ASC' : 'DESC';
        $allowed_orderby = ['submitted_at', 'status', 'subject'];
        if (!in_array($orderby, $allowed_orderby, true)) {
            $orderby = 'submitted_at';
        }

        $query = $wpdb->prepare(
            "SELECT * FROM {$this->table_messages} WHERE contact_id = %d ORDER BY {$orderby} {$order} LIMIT %d OFFSET %d",
            $contact_id,
            $per_page,
            $offset
        );

        $rows = $wpdb->get_results($query, OBJECT);
        return empty($rows) ? [] : array_map(fn($row) => new Message($row), $rows);
    }

    /**
     * Get the latest message ID for a contact
     */
    public function get_latest_message_id_by_contact(int $contact_id): ?int {
        global $wpdb;

        $contact_id = (int) $contact_id;
        if ($contact_id <= 0) {
            return null;
        }

        $id = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT id FROM {$this->table_messages} WHERE contact_id = %d ORDER BY submitted_at DESC LIMIT 1",
                $contact_id
            )
        );

        return $id ? (int) $id : null;
    }

    /**
     * Get messages by email with pagination
     */
    public function get_by_email(string $email, int $page = 1, int $per_page = 50): array {
        global $wpdb;

        $email = sanitize_email($email);
        if ($email === '') {
            return [];
        }

        $offset = max(0, ($page - 1) * $per_page);

        $query = $wpdb->prepare(
            "SELECT * FROM {$this->table_messages} WHERE email = %s ORDER BY submitted_at DESC LIMIT %d OFFSET %d",
            $email,
            $per_page,
            $offset
        );

        $rows = $wpdb->get_results($query, OBJECT);
        return empty($rows) ? [] : array_map(fn($row) => new Message($row), $rows);
    }

    /**
     * Get message IDs, attachments, and contact links by email (for GDPR deletion)
     */
    public function get_ids_and_attachments_by_email(string $email): array {
        global $wpdb;

        $email = sanitize_email($email);
        if ($email === '') {
            return [];
        }

        $query = $wpdb->prepare(
            "SELECT id, contact_id, attachment, crm_status FROM {$this->table_messages} WHERE email = %s",
            $email
        );

        return $wpdb->get_results($query, OBJECT) ?: [];
    }

    /**
     * Get total count of messages
     */
    public function count(string $search = '', string $status = 'all', ?int $contact_id = null, ?string $intent = null): int {
        global $wpdb;

        $contact_id = $contact_id ? (int) $contact_id : null;

        $where_clauses = [];
        $where_values = [];

        if ($intent && $intent !== 'all') {
            $where_clauses[] = "intent_category = %s";
            $where_values[] = $intent;
        }

        if ($status === Config::STATUS_SPAM) {
            $where_clauses[] = "recaptcha_score IS NOT NULL AND recaptcha_score < %f";
            $where_values[] = Config::SPAM_SCORE_THRESHOLD;
        } elseif ($status === Config::STATUS_ARCHIVED) {
            // Show only archived messages
            $where_clauses[] = "is_archived = %d";
            $where_values[] = 1;
        } elseif ($status !== 'all' && in_array($status, [Config::STATUS_UNREAD, Config::STATUS_READ], true)) {
            $where_clauses[] = "status = %s";
            $where_values[] = $status;
            // Exclude spam from regular status views
            $where_clauses[] = "(recaptcha_score IS NULL OR recaptcha_score >= %f)";
            $where_values[] = Config::SPAM_SCORE_THRESHOLD;
            $where_clauses[] = "is_archived = %d";
            $where_values[] = 0;
        } elseif ($status === 'all') {
            // Exclude spam from 'all' view
            $where_clauses[] = "(recaptcha_score IS NULL OR recaptcha_score >= %f)";
            $where_values[] = Config::SPAM_SCORE_THRESHOLD;
            $where_clauses[] = "is_archived = %d";
            $where_values[] = 0;
        }

        if ($contact_id) {
            $where_clauses[] = "contact_id = %d";
            $where_values[] = $contact_id;
        }

        if (!empty($search)) {
            $search = '%' . $wpdb->esc_like($search) . '%';
            $where_clauses[] = "(name LIKE %s OR email LIKE %s OR message LIKE %s)";
            $where_values[] = $search;
            $where_values[] = $search;
            $where_values[] = $search;
        }

        $where = !empty($where_clauses) ? 'WHERE ' . implode(' AND ', $where_clauses) : '';
        $query = "SELECT COUNT(*) FROM {$this->table_messages} $where";

        if (!empty($where_values)) {
            return (int)$wpdb->get_var($wpdb->prepare($query, ...$where_values));
        }
        return (int)$wpdb->get_var($query);
    }

    /**
     * Get message by ID
     */
    public function get_by_id(int $id): ?Message {
        global $wpdb;

        $row = $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM {$this->table_messages} WHERE id = %d", $id),
            OBJECT
        );

        if (!$row) {
            return null;
        }

        return new Message($row);
    }

    /**
     * Get all message IDs with optional search/filter
     */
    public function get_all_ids(string $search = '', string $status = 'all', ?int $contact_id = null): array {
        global $wpdb;

        $contact_id = $contact_id ? (int) $contact_id : null;

        $where_clauses = [];
        $where_values = [];

        if ($status === Config::STATUS_SPAM) {
            $where_clauses[] = "recaptcha_score IS NOT NULL AND recaptcha_score < %f";
            $where_values[] = Config::SPAM_SCORE_THRESHOLD;
        } elseif ($status === Config::STATUS_ARCHIVED) {
            // Show only archived messages
            $where_clauses[] = "is_archived = %d";
            $where_values[] = 1;
        } elseif ($status !== 'all' && in_array($status, [Config::STATUS_UNREAD, Config::STATUS_READ], true)) {
            $where_clauses[] = "status = %s";
            $where_values[] = $status;
            // Exclude spam from regular status views
            $where_clauses[] = "(recaptcha_score IS NULL OR recaptcha_score >= %f)";
            $where_values[] = Config::SPAM_SCORE_THRESHOLD;
            $where_clauses[] = "is_archived = %d";
            $where_values[] = 0;
        } elseif ($status === 'all') {
            // Exclude spam from 'all' view
            $where_clauses[] = "(recaptcha_score IS NULL OR recaptcha_score >= %f)";
            $where_values[] = Config::SPAM_SCORE_THRESHOLD;
            $where_clauses[] = "is_archived = %d";
            $where_values[] = 0;
        }

        if ($contact_id) {
            $where_clauses[] = "contact_id = %d";
            $where_values[] = $contact_id;
        }

        if (!empty($search)) {
            $search = '%' . $wpdb->esc_like($search) . '%';
            $where_clauses[] = "(name LIKE %s OR email LIKE %s OR message LIKE %s)";
            $where_values[] = $search;
            $where_values[] = $search;
            $where_values[] = $search;
        }

        $where = !empty($where_clauses) ? 'WHERE ' . implode(' AND ', $where_clauses) : '';
        $query = "SELECT id FROM {$this->table_messages} $where";

        if (!empty($where_values)) {
            $results = $wpdb->get_results($wpdb->prepare($query, ...$where_values), ARRAY_A);
        } else {
            $results = $wpdb->get_results($query, ARRAY_A);
        }

        return array_map(fn($row) => (int)$row['id'], $results);
    }

    /**
     * Delete message by ID
     */
    public function delete(int $id): bool {
        global $wpdb;
        // Note: Individual message deletion does NOT trigger CRM Contact deletion
        // Only Contact deletion (delete_with_messages) triggers CRM deletion
        return (bool)$wpdb->delete($this->table_messages, ['id' => $id], ['%d']);
    }

    /**
     * Toggle message status (unread ↔ read)
     */
    public function toggle_status(int $id): string|false {
        global $wpdb;

        $current = $wpdb->get_var(
            $wpdb->prepare("SELECT status FROM {$this->table_messages} WHERE id = %d", $id)
        );

        $status_map = [
            Config::STATUS_UNREAD => Config::STATUS_READ,
            Config::STATUS_READ   => Config::STATUS_UNREAD,
        ];

        $new_status = $status_map[$current] ?? Config::STATUS_READ;

        $result = $wpdb->update(
            $this->table_messages,
            ['status' => $new_status],
            ['id' => $id],
            ['%s'],
            ['%d']
        );

        return $result !== false ? $new_status : false;
    }

    /**
     * Bulk delete messages
     * 
     * Note: Individual message deletion does NOT trigger CRM Contact deletion.
     * Only Contact deletion (delete_with_messages) triggers CRM deletion.
     */
    public function bulk_delete(array $ids): int {
        global $wpdb;

        if (empty($ids)) {
            return 0;
        }

        $ids = array_map('intval', $ids);
        $placeholders = implode(',', array_fill(0, count($ids), '%d'));
        
        return (int)$wpdb->query($wpdb->prepare(
            "DELETE FROM {$this->table_messages} WHERE id IN ($placeholders)",
            $ids
        ));
    }

    /**
     * Bulk update status
     */
    public function bulk_update_status(array $ids, string $status): int {
        global $wpdb;

        if (!in_array($status, [Config::STATUS_UNREAD, Config::STATUS_READ], true)) {
            return 0;
        }

        $ids = array_map('intval', $ids);
        $placeholders = implode(',', array_fill(0, count($ids), '%d'));

        return (int)$wpdb->query($wpdb->prepare(
            "UPDATE {$this->table_messages} SET status = %s WHERE id IN ($placeholders)",
            array_merge([$status], $ids)
        ));
    }

    /**
     * Bulk update archive flag
     */
    public function bulk_update_archive(array $ids, bool $archived): int {
        global $wpdb;

        $ids = array_map('intval', $ids);
        if (empty($ids)) {
            return 0;
        }

        $placeholders = implode(',', array_fill(0, count($ids), '%d'));

        // When archiving, also clear spam flag (messages can't be both)
        // When unarchiving, keep spam status as is
        if ($archived) {
            return (int)$wpdb->query($wpdb->prepare(
                "UPDATE {$this->table_messages} SET is_archived = 1, recaptcha_score = NULL WHERE id IN ($placeholders)",
                ...$ids
            ));
        } else {
            return (int)$wpdb->query($wpdb->prepare(
                "UPDATE {$this->table_messages} SET is_archived = 0 WHERE id IN ($placeholders)",
                ...$ids
            ));
        }
    }

    /**
     * Bulk clear spam flag (recaptcha_score) so items move out of spam view
     */
    public function bulk_clear_spam(array $ids): int {
        $ids = array_map('intval', $ids);
        if (empty($ids)) {
            return 0;
        }

        $count = 0;
        foreach ($ids as $id) {
            if ($this->mark_message_as_not_spam($id)) {
                $count++;
            }
        }
        return $count;
    }

    /**
     * Bulk mark messages as spam (set recaptcha_score below threshold)
     */
    public function bulk_mark_spam(array $ids): int {
        $ids = array_map('intval', $ids);
        if (empty($ids)) {
            return 0;
        }

        $count = 0;
        foreach ($ids as $id) {
            if ($this->mark_message_as_spam($id)) {
                $count++;
            }
        }
        return $count;
    }

    /**
     * Delete all spam-flagged messages
     */
    /**
     * Get all spam message IDs for cleanup purposes
     */
    public function get_spam_ids(): array {
        global $wpdb;

        $results = $wpdb->get_col($wpdb->prepare(
            "SELECT id FROM {$this->table_messages} WHERE recaptcha_score IS NOT NULL AND recaptcha_score < %f",
            Config::SPAM_SCORE_THRESHOLD
        ));

        return array_map('intval', (array) $results);
    }

    /**
     * Get all archived message IDs for cleanup purposes
     */
    public function get_archived_ids(): array {
        global $wpdb;

        $results = $wpdb->get_col($wpdb->prepare(
            "SELECT id FROM {$this->table_messages} WHERE is_archived = %d",
            1
        ));

        return array_map('intval', (array) $results);
    }

    public function delete_all_spam(): int {
        global $wpdb;
        // Note: Individual message deletion does NOT trigger CRM Contact deletion
        // Only Contact deletion (delete_with_messages) triggers CRM deletion
        return (int)$wpdb->query($wpdb->prepare(
            "DELETE FROM {$this->table_messages} WHERE recaptcha_score IS NOT NULL AND recaptcha_score < %f",
            Config::SPAM_SCORE_THRESHOLD
        ));
    }

    /**
     * Delete all archived messages
     */
    public function delete_all_archived(): int {
        global $wpdb;
        // Note: Individual message deletion does NOT trigger CRM Contact deletion
        // Only Contact deletion (delete_with_messages) triggers CRM deletion
        return (int)$wpdb->query($wpdb->prepare(
            "DELETE FROM {$this->table_messages} WHERE is_archived = %d",
            1
        ));
    }

    /**
     * Get messages for export
     */
    public function get_for_export(string $search = '', string $status = 'all', int $limit = Config::EXPORT_LIMIT, int $offset = 0, ?int $contact_id = null, ?string $intent = null): array {
        global $wpdb;

        $where_clauses = [];
        $where_values = [];

        $contact_id = $contact_id ? (int) $contact_id : null;

        $limit  = max(1, min($limit, Config::EXPORT_LIMIT));
        $offset = max(0, $offset);

        if ($status === Config::STATUS_SPAM) {
            $where_clauses[] = "recaptcha_score IS NOT NULL AND recaptcha_score < %f";
            $where_values[] = Config::SPAM_SCORE_THRESHOLD;
        } elseif ($status === Config::STATUS_ARCHIVED) {
            // Show only archived messages
            $where_clauses[] = "is_archived = %d";
            $where_values[] = 1;
        } elseif ($status !== 'all' && in_array($status, [Config::STATUS_UNREAD, Config::STATUS_READ], true)) {
            $where_clauses[] = "status = %s";
            $where_values[] = $status;
            // Exclude spam from regular status views
            $where_clauses[] = "(recaptcha_score IS NULL OR recaptcha_score >= %f)";
            $where_values[] = Config::SPAM_SCORE_THRESHOLD;
            $where_clauses[] = "is_archived = %d";
            $where_values[] = 0;
        } elseif ($status === 'all') {
            // Exclude spam from 'all' view
            $where_clauses[] = "(recaptcha_score IS NULL OR recaptcha_score >= %f)";
            $where_values[] = Config::SPAM_SCORE_THRESHOLD;
            $where_clauses[] = "is_archived = %d";
            $where_values[] = 0;
        }

        // Intent filter
        if (!empty($intent) && $intent !== 'all') {
            $where_clauses[] = "intent_category = %s";
            $where_values[] = $intent;
        }

        if ($contact_id) {
            $where_clauses[] = "contact_id = %d";
            $where_values[] = $contact_id;
        }

        if (!empty($search)) {
            $search = '%' . $wpdb->esc_like($search) . '%';
            $where_clauses[] = "(name LIKE %s OR email LIKE %s OR message LIKE %s)";
            $where_values[] = $search;
            $where_values[] = $search;
            $where_values[] = $search;
        }

        $where = !empty($where_clauses) ? 'WHERE ' . implode(' AND ', $where_clauses) : '';
        $sql   = "SELECT * FROM {$this->table_messages} $where ORDER BY submitted_at DESC LIMIT %d OFFSET %d";

        $params = $where_values;
        $params[] = $limit;
        $params[] = $offset;

        return $wpdb->get_results(
            $wpdb->prepare($sql, ...$params),
            OBJECT
        );
    }

    /**
     * Search messages with pagination
     */
    public function search(string $query, int $page = 1, int $per_page = 25): array {
        return $this->get_paginated($page, $per_page, $query);
    }

    /**
     * Reset admin/user email failure statuses back to pending
     */
    public function reset_email_failures(): int {
        global $wpdb;

        $table = $this->table_messages;

        $admin_reset = $wpdb->query(
            $wpdb->prepare(
                "UPDATE {$table}
                 SET admin_email_status = %s, admin_email_retries = 0, admin_email_error = NULL
                 WHERE admin_email_status = %s",
                Config::EMAIL_PENDING,
                Config::EMAIL_FAILED
            )
        );

        $user_reset = $wpdb->query(
            $wpdb->prepare(
                "UPDATE {$table}
                 SET user_email_status = %s, user_email_retries = 0, user_email_error = NULL
                 WHERE user_email_status = %s",
                Config::EMAIL_PENDING,
                Config::EMAIL_FAILED
            )
        );

        return ($admin_reset !== false ? (int) $admin_reset : 0) + ($user_reset !== false ? (int) $user_reset : 0);
    }

    /**
     * Reset CRM failures back to pending
     */
    public function reset_crm_failures(): int {
        global $wpdb;

        $table = $this->table_messages;

        $crm_reset = $wpdb->query(
            $wpdb->prepare(
                "UPDATE {$table}
                 SET crm_status = %s, crm_retries = 0, crm_error = NULL
                 WHERE crm_status = %s",
                Config::CRM_PENDING,
                Config::CRM_FAILED
            )
        );

        return $crm_reset !== false ? (int) $crm_reset : 0;
    }

    /**
     * Mark CRM sync as delivered for a message.
     */
    public function mark_crm_sent(int $message_id): bool {
        global $wpdb;

        $result = $wpdb->update(
            $this->table_messages,
            [
                'crm_status' => Config::CRM_SENT,
                'crm_synced_at' => current_time('mysql'),
                'crm_error' => null,
            ],
            ['id' => $message_id],
            ['%s', '%s', '%s'],
            ['%d']
        );

        return $result !== false;
    }

    /**
     * Mark CRM sync as failed for a message and increment retry count.
     */
    public function mark_crm_failed(int $message_id, string $error_message): bool {
        global $wpdb;

        $truncated_error = substr($error_message, 0, 1000);

        $result = $wpdb->query(
            $wpdb->prepare(
                "UPDATE {$this->table_messages}
                 SET crm_status = %s,
                     crm_error = %s,
                     crm_retries = crm_retries + 1
                 WHERE id = %d",
                Config::CRM_FAILED,
                $truncated_error,
                $message_id
            )
        );

        return $result !== false;
    }

    /**
     * Count messages for a relative period
     */
    public function count_by_period(string $period): int {
        global $wpdb;

        $now   = current_time('mysql');
        $today = current_time('Y-m-d');

        switch ($period) {
            case 'today':
                $where = $wpdb->prepare('DATE(submitted_at) = %s', $today);
                break;
            case 'week':
                $where = $wpdb->prepare('submitted_at >= DATE_SUB(%s, INTERVAL 7 DAY)', $now);
                break;
            case 'month':
                $where = $wpdb->prepare('MONTH(submitted_at) = MONTH(%s) AND YEAR(submitted_at) = YEAR(%s)', $now, $now);
                break;
            case 'year':
                $where = $wpdb->prepare('YEAR(submitted_at) = YEAR(%s)', $now);
                break;
            default:
                return 0;
        }

        $sql = "SELECT COUNT(*) FROM {$this->table_messages} WHERE {$where}";
        return (int) $wpdb->get_var($sql);
    }

    /**
     * Get message trend for the last N days (default 7)
     *
     * @param int $days Number of days to look back
     * @return array<int, array{label:string,count:int}>
     */
    public function get_trend(int $days = 7): array {
        global $wpdb;

        $data = [];
        $days = max(1, $days);

        for ($i = $days - 1; $i >= 0; $i--) {
            $date  = gmdate('Y-m-d', strtotime("-{$i} days"));
            $label = gmdate('M d', strtotime("-{$i} days"));

            $sql   = $wpdb->prepare(
                "SELECT COUNT(*) FROM {$this->table_messages} WHERE DATE(submitted_at) = %s",
                $date
            );

            $data[] = [
                'label' => $label,
                'count' => (int) $wpdb->get_var($sql),
            ];
        }

        return $data;
    }

    /**
     * Get daily submission counts for a date window.
     *
     * @return int[] Daily counts in ascending date order
     */
    public function get_daily_counts(int $days = 7, ?string $startDate = null, ?string $endDate = null): array {
        global $wpdb;

        if ($startDate && $endDate) {
            $rows = $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT DATE(submitted_at) as date, COUNT(*) as count
                     FROM {$this->table_messages}
                     WHERE DATE(submitted_at) BETWEEN %s AND %s
                     GROUP BY DATE(submitted_at)
                     ORDER BY date ASC",
                    $startDate,
                    $endDate
                ),
                ARRAY_A
            );

            $countsByDate = [];
            foreach ($rows as $row) {
                $countsByDate[$row['date']] = (int) $row['count'];
            }

            $period = new \DatePeriod(
                new \DateTime($startDate),
                new \DateInterval('P1D'),
                (new \DateTime($endDate))->modify('+1 day')
            );

            $data = [];
            foreach ($period as $date) {
                $key = $date->format('Y-m-d');
                $data[] = $countsByDate[$key] ?? 0;
            }

            return $data;
        }

        $data = [];
        $days = max(1, $days);

        for ($i = $days - 1; $i >= 0; $i--) {
            $date = gmdate('Y-m-d', strtotime("-{$i} days"));

            $count = (int) $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$this->table_messages} WHERE DATE(submitted_at) = %s",
                $date
            ));

            $data[] = $count;
        }

        return $data;
    }

    /**
     * Update message archive status
     *
     * @param int  $message_id ID of the message
     * @param bool $archived   True to archive, false to restore
     * @return bool True on success, false on failure
     */
    public function update_archive(int $message_id, bool $archived): bool {
        global $wpdb;

        if ($message_id <= 0) {
            return false;
        }

        $result = $wpdb->update(
            $this->table_messages,
            ['is_archived' => (int) $archived],
            ['id' => $message_id],
            ['%d'],
            ['%d']
        );

        return $result !== false;
    }

    /**
     * Get table name
     */
    public function get_table_name(): string {
        return $this->table_messages;
    }

    /**
     * Validate column name to prevent SQL injection
     */
    private function validate_column_name(string $column): string {
        $allowed = ['id', 'name', 'email', 'subject', 'message', 'status', 'submitted_at'];
        return in_array($column, $allowed) ? $column : 'submitted_at';
    }

    /**
     * Clean stale database entries (files that no longer exist on disk)
     * @param string $uploads_dir Directory where attachments are stored
     * @return int Number of records cleaned
     */
    public function clean_stale_attachments(string $uploads_dir): int {
        global $wpdb;
        
        // Get all messages with attachments
        $results = $wpdb->get_results("SELECT id, attachment FROM {$this->table_messages} WHERE attachment IS NOT NULL AND attachment != ''");
        
        $cleaned = 0;
        foreach ($results as $row) {
            $attachment = $row->attachment;
            $data = is_string($attachment) && ($attachment[0] === '{' || $attachment[0] === '[') 
                ? json_decode($attachment, true)
                : null;
            
            if (!$data) continue;
            
            $has_stale = false;
            
            // Handle single attachment
            if (isset($data['path'])) {
                $path = $uploads_dir . basename($data['path']);
                if (!is_file($path)) {
                    $has_stale = true;
                    $data = null; // Clear the entire attachment
                }
            }
            // Handle multiple attachments - check each one
            elseif (is_array($data)) {
                $filtered = [];
                foreach ($data as $item) {
                    if (is_array($item) && isset($item['path'])) {
                        $path = $uploads_dir . basename($item['path']);
                        if (is_file($path)) {
                            // Keep valid attachment
                            $filtered[] = $item;
                        } else {
                            // Mark as stale - don't include it
                            $has_stale = true;
                        }
                    } else {
                        // Keep non-file items
                        $filtered[] = $item;
                    }
                }
                // Update data to only include valid attachments
                if ($has_stale) {
                    $data = empty($filtered) ? null : $filtered;
                }
            }
            
            // Update DB if stale entries found and removed
            if ($has_stale) {
                if ($data === null) {
                    // No valid attachments left, clear the field
                    $wpdb->update(
                        $this->table_messages,
                        ['attachment' => null],
                        ['id' => $row->id],
                        ['%s'],
                        ['%d']
                    );
                } else {
                    // Some attachments are valid, keep only those
                    $wpdb->update(
                        $this->table_messages,
                        ['attachment' => wp_json_encode($data)],
                        ['id' => $row->id],
                        ['%s'],
                        ['%d']
                    );
                }
                $cleaned++;
            }
        }
        
        return $cleaned;
    }

    /**
     * Get client IP address
     */
    private function get_client_ip(): string {
        if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
            return sanitize_text_field($_SERVER['HTTP_CLIENT_IP']);
        } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            return sanitize_text_field(explode(',', $_SERVER['HTTP_X_FORWARDED_FOR'])[0]);
        } else {
            return sanitize_text_field($_SERVER['REMOTE_ADDR'] ?? '');
        }
    }

    // ==================== INTENT CLASSIFICATION METHODS ====================

    /**
     * Mark a single message as spam
     * Sets recaptcha_score below threshold, clears archive flag,
     * and aligns intent classification to spam.
     * Unified method called by: bulk_mark_spam, update_intent (when category=spam), AJAX handlers
     *
     * @param int $message_id Message ID
     * @return bool Success
     */
    public function mark_message_as_spam(int $message_id): bool {
        global $wpdb;
        
        $spam_score = max(0.0, Config::SPAM_SCORE_THRESHOLD - 0.01);
        
        $result = $wpdb->update(
            $this->table_messages,
            [
                'recaptcha_score' => $spam_score,
                'is_archived' => 0, // Can't be both archived and spam
                'intent_category' => \ContactInbox\Core\IntentClassifier::CATEGORY_SPAM,
                'intent_confidence' => 1.0,
                'intent_keywords' => wp_json_encode(['manual']),
                'intent_classified_at' => current_time('mysql'),
            ],
            ['id' => $message_id],
            ['%f', '%d', '%s', '%f', '%s', '%s'],
            ['%d']
        );
        
        return $result !== false;
    }

    /**
     * Mark a single message as not spam (move to inbox)
     * Clears recaptcha_score, then re-runs classifier detection.
     * Unified method called by: bulk_clear_spam, AJAX handlers
     *
     * @param int $message_id Message ID
     * @return bool Success
     */
    public function mark_message_as_not_spam(int $message_id): bool {
        global $wpdb;
        
        $result = $wpdb->update(
            $this->table_messages,
            [
                'recaptcha_score' => null,
            ],
            ['id' => $message_id],
            ['%f'],
            ['%d']
        );

        if ($result === false) {
            return false;
        }

        return $this->reclassify_message_by_content($message_id);
    }

    /**
     * Re-run classifier on message content and persist detected intent.
     */
    private function reclassify_message_by_content(int $message_id): bool {
        global $wpdb;

        $message = $this->get_by_id($message_id);
        if (!$message) {
            return false;
        }

        $intent = \ContactInbox\Core\IntentClassifier::instance()->classify(
            (string) ($message->subject ?? ''),
            (string) ($message->message ?? '')
        );

        $result = $wpdb->update(
            $this->table_messages,
            [
                'intent_category' => $intent['category'] ?? 'unclassified',
                'intent_confidence' => isset($intent['confidence']) ? (float) $intent['confidence'] : null,
                'intent_keywords' => isset($intent['keywords']) ? wp_json_encode($intent['keywords']) : null,
                'intent_classified_at' => $intent['classified_at'] ?? current_time('mysql'),
            ],
            ['id' => $message_id],
            ['%s', '%f', '%s', '%s'],
            ['%d']
        );

        return $result !== false;
    }

    /**
     * Update message intent classification
     *
     * @param int $message_id Message ID
     * @param array $intent Intent data (category, confidence, keywords, classified_at)
     * @return bool Success
     */
    public function update_intent(int $message_id, array $intent): bool {
        global $wpdb;

        $category = $intent['category'] ?? 'unclassified';
        
        $data = [
            'intent_category' => $category,
            'intent_confidence' => isset($intent['confidence']) ? (float) $intent['confidence'] : null,
            'intent_keywords' => isset($intent['keywords']) ? wp_json_encode($intent['keywords']) : null,
            'intent_classified_at' => $intent['classified_at'] ?? current_time('mysql'),
        ];

        // Update intent fields
        $format = ['%s', '%f', '%s', '%s'];
        $result = $wpdb->update(
            $this->table_messages,
            $data,
            ['id' => $message_id],
            $format,
            ['%d']
        );

        // Check for actual database error
        if ($result === false) {
            return false;
        }

        // If classifying as spam, also apply spam marking via unified method
        if ($category === 'spam') {
            return $this->mark_message_as_spam($message_id);
        }

        return true;
    }

    /**
     * Get intent statistics
     *
     * @return array Category => count
     */
    public function get_intent_stats(): array {
        global $wpdb;

        $results = $wpdb->get_results(
            "SELECT intent_category, COUNT(*) as count 
             FROM {$this->table_messages} 
             GROUP BY intent_category 
             ORDER BY count DESC",
            ARRAY_A
        );

        $stats = [];
        foreach ($results as $row) {
            $stats[$row['intent_category']] = (int) $row['count'];
        }

        return $stats;
    }

    /**
     * Get intent distribution for a date range
     *
     * @param int $days Number of days to look back
     * @return array Date => [category => count]
     */
    public function get_intent_trend(int $days = 7): array {
        global $wpdb;

        $data = [];
        $days = max(1, $days);

        for ($i = $days - 1; $i >= 0; $i--) {
            $date = gmdate('Y-m-d', strtotime("-{$i} days"));
            $label = gmdate('M d', strtotime("-{$i} days"));

            $results = $wpdb->get_results($wpdb->prepare(
                "SELECT intent_category, COUNT(*) as count 
                 FROM {$this->table_messages} 
                 WHERE DATE(submitted_at) = %s 
                 GROUP BY intent_category",
                $date
            ), ARRAY_A);

            $day_data = ['date' => $label];
            foreach ($results as $row) {
                $day_data[$row['intent_category']] = (int) $row['count'];
            }

            $data[] = $day_data;
        }

        return $data;
    }

    /**
     * Get messages by intent category
     *
     * @param string $category Intent category
     * @param int $page Page number
     * @param int $per_page Results per page
     * @return array Messages
     */
    public function get_by_intent(string $category, int $page = 1, int $per_page = 20): array {
        global $wpdb;

        $offset = max(0, ($page - 1) * $per_page);
        
        $query = $wpdb->prepare(
            "SELECT * FROM {$this->table_messages} 
             WHERE intent_category = %s 
             ORDER BY submitted_at DESC 
             LIMIT %d OFFSET %d",
            $category,
            $per_page,
            $offset
        );

        $rows = $wpdb->get_results($query, OBJECT);
        return empty($rows) ? [] : array_map(fn($row) => new Message($row), $rows);
    }

    /**
     * Count messages by intent category
     *
     * @param string $category Intent category
     * @return int Count
     */
    public function count_by_intent(string $category): int {
        global $wpdb;

        return (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$this->table_messages} WHERE intent_category = %s",
            $category
        ));
    }

    /**
     * Count messages by intent category within a date window.
     */
    public function count_by_intent_range(
        string $category,
        int $days = 7,
        ?string $startDate = null,
        ?string $endDate = null
    ): int {
        global $wpdb;

        if ($startDate && $endDate) {
            return (int) $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$this->table_messages}
                 WHERE intent_category = %s
                 AND submitted_at >= %s
                 AND submitted_at <= %s",
                $category,
                $startDate . ' 00:00:00',
                $endDate . ' 23:59:59'
            ));
        }

        return (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$this->table_messages}
             WHERE intent_category = %s
             AND DATE(submitted_at) >= DATE_SUB(NOW(), INTERVAL %d DAY)",
            $category,
            $days
        ));
    }

    /**
     * Count unclassified messages (for intent reclassification bulk operations)
     *
     * @return int Count of unclassified, non-archived, non-spam messages
     */
    public function count_unclassified(): int {
        global $wpdb;

        return (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$this->table_messages}
             WHERE intent_category = %s
             AND (recaptcha_score IS NULL OR recaptcha_score >= %f)
             AND is_archived = 0",
            'unclassified',
            Config::SPAM_SCORE_THRESHOLD
        ));
    }

    /**
     * Get batch of unclassified messages for reclassification
     *
     * @param int $limit Maximum messages to retrieve
     * @param int $offset Starting position
     * @return array Array of messages with id, subject, message fields
     */
    public function get_unclassified_batch(int $limit = 100, int $offset = 0): array {
        global $wpdb;

        $limit = max(1, min($limit, 500));
        $offset = max(0, $offset);

        $query = $wpdb->prepare(
            "SELECT id, subject, message FROM {$this->table_messages}
             WHERE intent_category = %s
             AND (recaptcha_score IS NULL OR recaptcha_score >= %f)
             AND is_archived = 0
             ORDER BY id ASC
             LIMIT %d OFFSET %d",
            'unclassified',
            Config::SPAM_SCORE_THRESHOLD,
            $limit,
            $offset
        );

        return $wpdb->get_results($query) ?? [];
    }

    /**
     * Get message status counts across admin email, user email, and CRM channels
     *
     * @param string|null $start_date Optional start date filter (Y-m-d format)
     * @param string|null $end_date Optional end date filter (Y-m-d format)
     * @return array Associative array with status counts for each channel
     */
    public function get_status_counts(?string $start_date = null, ?string $end_date = null): array {
        global $wpdb;

        $where_clause = '';
        if ($start_date && $end_date) {
            $where_clause = $wpdb->prepare(
                " WHERE submitted_at >= %s AND submitted_at <= %s",
                $start_date . ' 00:00:00',
                $end_date . ' 23:59:59'
            );
        }

        $query = "SELECT
            COALESCE(SUM(CASE WHEN admin_email_status IS NULL OR admin_email_status IN (%s, %s) THEN 1 ELSE 0 END), 0) AS admin_email_pending,
            COALESCE(SUM(CASE WHEN admin_email_status = %s THEN 1 ELSE 0 END), 0) AS admin_email_sent,
            COALESCE(SUM(CASE WHEN admin_email_status = %s THEN 1 ELSE 0 END), 0) AS admin_email_failed,
            COALESCE(SUM(CASE WHEN admin_email_status = %s THEN 1 ELSE 0 END), 0) AS admin_email_skipped,
            COALESCE(SUM(CASE WHEN user_email_status IS NULL OR user_email_status IN (%s, %s) THEN 1 ELSE 0 END), 0) AS user_email_pending,
            COALESCE(SUM(CASE WHEN user_email_status = %s THEN 1 ELSE 0 END), 0) AS user_email_sent,
            COALESCE(SUM(CASE WHEN user_email_status = %s THEN 1 ELSE 0 END), 0) AS user_email_failed,
            COALESCE(SUM(CASE WHEN user_email_status = %s THEN 1 ELSE 0 END), 0) AS user_email_skipped,
            COALESCE(SUM(CASE WHEN crm_status IS NULL OR crm_status IN (%s, %s) THEN 1 ELSE 0 END), 0) AS crm_pending,
            COALESCE(SUM(CASE WHEN crm_status = %s THEN 1 ELSE 0 END), 0) AS crm_sent,
            COALESCE(SUM(CASE WHEN crm_status = %s THEN 1 ELSE 0 END), 0) AS crm_failed,
            COALESCE(SUM(CASE WHEN crm_status = %s THEN 1 ELSE 0 END), 0) AS crm_skipped
        FROM {$this->table_messages}{$where_clause}";

        $result = $wpdb->get_row($wpdb->prepare(
            $query,
            Config::EMAIL_PENDING,
            Config::EMAIL_PROCESSING,
            Config::EMAIL_SENT,
            Config::EMAIL_FAILED,
            Config::EMAIL_SKIPPED,
            Config::EMAIL_PENDING,
            Config::EMAIL_PROCESSING,
            Config::EMAIL_SENT,
            Config::EMAIL_FAILED,
            Config::EMAIL_SKIPPED,
            Config::CRM_PENDING,
            Config::CRM_PROCESSING,
            Config::CRM_SENT,
            Config::CRM_FAILED,
            Config::CRM_SKIPPED
        ));

        return [
            'admin_email_pending' => (int) ($result->admin_email_pending ?? 0),
            'admin_email_sent' => (int) ($result->admin_email_sent ?? 0),
            'admin_email_failed' => (int) ($result->admin_email_failed ?? 0),
            'admin_email_skipped' => (int) ($result->admin_email_skipped ?? 0),
            'user_email_pending' => (int) ($result->user_email_pending ?? 0),
            'user_email_sent' => (int) ($result->user_email_sent ?? 0),
            'user_email_failed' => (int) ($result->user_email_failed ?? 0),
            'user_email_skipped' => (int) ($result->user_email_skipped ?? 0),
            'crm_pending' => (int) ($result->crm_pending ?? 0),
            'crm_sent' => (int) ($result->crm_sent ?? 0),
            'crm_failed' => (int) ($result->crm_failed ?? 0),
            'crm_skipped' => (int) ($result->crm_skipped ?? 0),
        ];
    }

    /**
     * Get inbox review status counts (read/unread/archived), excluding spam.
     */
    public function get_inbox_status_breakdown(?string $start_date = null, ?string $end_date = null): array {
        global $wpdb;

        $where_clauses = ["(recaptcha_score IS NULL OR recaptcha_score >= %f)"];
        $where_params = [Config::SPAM_SCORE_THRESHOLD];

        if ($start_date && $end_date) {
            $where_clauses[] = "submitted_at >= %s AND submitted_at <= %s";
            $where_params[] = $start_date . ' 00:00:00';
            $where_params[] = $end_date . ' 23:59:59';
        }

        $where_sql = 'WHERE ' . implode(' AND ', $where_clauses);

        // Build params array: status constants first, then where params
        $params = array_merge(
            [Config::STATUS_UNREAD, Config::STATUS_READ],
            $where_params
        );

        $query = "SELECT
            COALESCE(SUM(CASE WHEN status = %s AND is_archived = 0 THEN 1 ELSE 0 END), 0) AS `unread`,
            COALESCE(SUM(CASE WHEN status = %s AND is_archived = 0 THEN 1 ELSE 0 END), 0) AS `read`,
            COALESCE(SUM(CASE WHEN is_archived = 1 THEN 1 ELSE 0 END), 0) AS `archived`
        FROM {$this->table_messages} {$where_sql}";

        $prepared = $wpdb->prepare($query, $params);
        $result = $wpdb->get_row($prepared);

        return [
            'unread' => (int) ($result->unread ?? 0),
            'read' => (int) ($result->read ?? 0),
            'archived' => (int) ($result->archived ?? 0),
        ];
    }

    /**
     * Get failed messages that need retry
     * Returns messages where any processing channel has failed status
     *
     * @param int $limit Maximum number of messages to return
     * @return array Array of message objects with failed status
     */
    public function get_failed(int $limit = 100): array {
        global $wpdb;

        return $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$this->table_messages}
                WHERE admin_email_status = %s 
                   OR user_email_status = %s 
                   OR crm_status = %s
                ORDER BY submitted_at DESC
                LIMIT %d",
                Config::EMAIL_FAILED,
                Config::EMAIL_FAILED,
                Config::CRM_FAILED,
                $limit
            )
        ) ?? [];
    }

    /**
     * Update a specific processing channel status for a message
     * Safely resets timestamps, error notes, and retry counters when appropriate
     *
     * @param int $message_id Message ID
     * @param string $channel Processing channel: 'admin_email', 'user_email', or 'crm'
     * @param string $status New status value
     * @param string|null $error_message Optional error message for failed status
     * @return bool Success
     */
    public function update_channel_status(
        int $message_id,
        string $channel,
        string $status,
        ?string $error_message = null
    ): bool {
        global $wpdb;

        $map = [
            'admin_email' => [
                'status'    => 'admin_email_status',
                'error'     => 'admin_email_error',
                'timestamp' => 'admin_email_sent_at',
                'retries'   => 'admin_email_retries',
            ],
            'user_email' => [
                'status'    => 'user_email_status',
                'error'     => 'user_email_error',
                'timestamp' => 'user_email_sent_at',
                'retries'   => 'user_email_retries',
            ],
            'crm' => [
                'status'    => 'crm_status',
                'error'     => 'crm_error',
                'timestamp' => 'crm_synced_at',
                'retries'   => 'crm_retries',
            ],
        ];

        if (!isset($map[$channel])) {
            return false;
        }

        $allowed_statuses = match ($channel) {
            'crm' => [Config::CRM_PENDING, Config::CRM_PROCESSING, Config::CRM_FAILED, Config::CRM_SENT, Config::CRM_SKIPPED],
            default => [Config::EMAIL_PENDING, Config::EMAIL_PROCESSING, Config::EMAIL_FAILED, Config::EMAIL_SENT, Config::EMAIL_SKIPPED],
        };

        if (!in_array($status, $allowed_statuses, true)) {
            return false;
        }

        $pending_status = $channel === 'crm' ? Config::CRM_PENDING : Config::EMAIL_PENDING;
        $processing_status = $channel === 'crm' ? Config::CRM_PROCESSING : Config::EMAIL_PROCESSING;
        $sent_status = $channel === 'crm' ? Config::CRM_SENT : Config::EMAIL_SENT;
        $skipped_status = $channel === 'crm' ? Config::CRM_SKIPPED : Config::EMAIL_SKIPPED;

        $columns = $map[$channel];

        if ($status === $processing_status) {
            $sql = "UPDATE {$this->table_messages} SET {$columns['status']} = %s WHERE id = %d";
            $prepared = $wpdb->prepare($sql, $status, $message_id);
        } elseif ($status === $pending_status) {
            $sql = "UPDATE {$this->table_messages} SET {$columns['status']} = %s, {$columns['error']} = NULL, {$columns['timestamp']} = NULL, {$columns['retries']} = 0 WHERE id = %d";
            $prepared = $wpdb->prepare($sql, $status, $message_id);
        } elseif ($status === $sent_status) {
            $sql = "UPDATE {$this->table_messages} SET {$columns['status']} = %s, {$columns['error']} = NULL, {$columns['timestamp']} = %s WHERE id = %d";
            $prepared = $wpdb->prepare($sql, $status, current_time('mysql'), $message_id);
        } elseif ($status === $skipped_status) {
            $sql = "UPDATE {$this->table_messages} SET {$columns['status']} = %s, {$columns['error']} = NULL, {$columns['timestamp']} = NULL, {$columns['retries']} = 0 WHERE id = %d";
            $prepared = $wpdb->prepare($sql, $status, $message_id);
        } else { // failed
            if ($error_message !== null && $error_message !== '') {
                $truncated_error = substr($error_message, 0, 1000);
                $sql = "UPDATE {$this->table_messages} SET {$columns['status']} = %s, {$columns['error']} = %s WHERE id = %d";
                $prepared = $wpdb->prepare($sql, $status, $truncated_error, $message_id);
            } else {
                $sql = "UPDATE {$this->table_messages} SET {$columns['status']} = %s, {$columns['error']} = NULL WHERE id = %d";
                $prepared = $wpdb->prepare($sql, $status, $message_id);
            }
        }

        if (!$prepared) {
            return false;
        }

        $updated = $wpdb->query($prepared);
        return $updated !== false;
    }
}
