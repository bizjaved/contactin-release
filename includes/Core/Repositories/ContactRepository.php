<?php
// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
/**
 * Contact Repository
 *
 * Handles CRUD for contacts using wpdb (guarded, sanitized).
 */

declare(strict_types=1);

namespace ContactInbox\Core\Repositories;

use ContactInbox\Core\Contact;
use ContactInbox\Core\Config;
use ContactInbox\Core\PhoneUtils;

if (!defined('ABSPATH')) exit;

final class ContactRepository {
    private string $table_contacts;

    public function __construct() {
        global $wpdb;
        $this->table_contacts = $wpdb->prefix . Config::TABLE_CONTACTS;
    }

    public function get_by_id(int $id): ?Contact {
        global $wpdb;
        $row = $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM {$this->table_contacts} WHERE id = %d", $id)
        );
        return $row ? new Contact($row) : null;
    }

    public function find_by_email(string $email): ?Contact {
        global $wpdb;
        $email = sanitize_email($email);
        if ($email === '') {
            return null;
        }
        $row = $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM {$this->table_contacts} WHERE email = %s", $email)
        );
        return $row ? new Contact($row) : null;
    }

    public function find_by_phone(string $phone, string $default_country = '1'): ?Contact {
        global $wpdb;
        $normalized = PhoneUtils::normalize($phone, $default_country);
        if ($normalized === '') {
            return null;
        }
        $row = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$this->table_contacts} WHERE primary_phone = %s OR mobile_phone = %s OR home_phone = %s OR other_phone = %s LIMIT 1",
                $normalized,
                $normalized,
                $normalized,
                $normalized
            )
        );
        return $row ? new Contact($row) : null;
    }

    public function insert(array $data): int|false {
        global $wpdb;

        $prepared = [
            'salutation'    => isset($data['salutation']) ? sanitize_text_field($data['salutation']) : null,
            'name'          => sanitize_text_field($data['name'] ?? ''),
            'email'         => isset($data['email']) ? sanitize_email($data['email']) : null,
            'primary_phone' => isset($data['primary_phone']) ? sanitize_text_field($data['primary_phone']) : null,
            'mobile_phone'  => isset($data['mobile_phone']) ? sanitize_text_field($data['mobile_phone']) : null,
            'home_phone'    => isset($data['home_phone']) ? sanitize_text_field($data['home_phone']) : null,
            'other_phone'   => isset($data['other_phone']) ? sanitize_text_field($data['other_phone']) : null,
            'source'        => isset($data['source']) ? sanitize_text_field($data['source']) : null,
            'last_message_at' => isset($data['last_message_at']) ? sanitize_text_field($data['last_message_at']) : null,
        ];

        $format = ['%s','%s','%s','%s','%s','%s','%s','%s'];
        $result = $wpdb->insert($this->table_contacts, $prepared, $format);
        return $result ? (int) $wpdb->insert_id : false;
    }

    public function update(int $id, array $data): bool {
        global $wpdb;

        $update = [];
        if (isset($data['salutation'])) $update['salutation'] = sanitize_text_field($data['salutation']);
        if (isset($data['name'])) $update['name'] = sanitize_text_field($data['name']);
        if (isset($data['email'])) $update['email'] = sanitize_email($data['email']);
        if (isset($data['primary_phone'])) $update['primary_phone'] = sanitize_text_field($data['primary_phone']);
        if (isset($data['mobile_phone'])) $update['mobile_phone'] = sanitize_text_field($data['mobile_phone']);
        if (isset($data['home_phone'])) $update['home_phone'] = sanitize_text_field($data['home_phone']);
        if (isset($data['other_phone'])) $update['other_phone'] = sanitize_text_field($data['other_phone']);
        if (isset($data['source'])) $update['source'] = sanitize_text_field($data['source']);
        if (isset($data['crm_sync_status'])) $update['crm_sync_status'] = sanitize_text_field($data['crm_sync_status']);
        if (isset($data['last_message_at'])) $update['last_message_at'] = sanitize_text_field($data['last_message_at']);

        if (empty($update)) {
            return false;
        }

        $result = $wpdb->update(
            $this->table_contacts,
            $update,
            ['id' => $id],
            null,
            ['%d']
        );
        return $result !== false;
    }

    public function delete(int $id): bool {
        global $wpdb;
        return (bool) $wpdb->delete($this->table_contacts, ['id' => $id], ['%d']);
    }

    public function count(string $search = ''): int {
        global $wpdb;
        $where = '';
        $params = [];
        if ($search !== '') {
            $like = '%' . $wpdb->esc_like($search) . '%';
            $where = "WHERE name LIKE %s OR email LIKE %s";
            $params = [$like, $like];
        }
        $sql = "SELECT COUNT(*) FROM {$this->table_contacts} {$where}";
        return (int) ($params ? $wpdb->get_var($wpdb->prepare($sql, ...$params)) : $wpdb->get_var($sql));
    }

    public function get_paginated(int $page = 1, int $per_page = 20, string $search = '', string $orderby = 'updated_at', string $order = 'DESC'): array {
        global $wpdb;
        $offset = max(0, ($page - 1) * $per_page);
        $order = strtoupper($order) === 'ASC' ? 'ASC' : 'DESC';
        $allowed_orderby = ['name','email','last_message_at','created_at','updated_at'];
        if (!in_array($orderby, $allowed_orderby, true)) {
            $orderby = 'updated_at';
        }

        $where = '';
        $params = [];
        if ($search !== '') {
            $like = '%' . $wpdb->esc_like($search) . '%';
            $where = "WHERE name LIKE %s OR email LIKE %s";
            $params = [$like, $like];
        }

        $sql = "SELECT * FROM {$this->table_contacts} {$where} ORDER BY {$orderby} {$order} LIMIT %d OFFSET %d";
        $params[] = $per_page;
        $params[] = $offset;

        $rows = $params ? $wpdb->get_results($wpdb->prepare($sql, ...$params)) : $wpdb->get_results($sql);
        return array_map(fn($row) => new Contact($row), $rows ?: []);
    }

    public function touch_last_message(int $id, string $when): bool {
        global $wpdb;
        $result = $wpdb->update(
            $this->table_contacts,
            ['last_message_at' => $when],
            ['id' => $id],
            ['%s'],
            ['%d']
        );
        return $result !== false;
    }

    /**
     * Check if contact exists
     *
     * @param int $id Contact ID
     * @return bool True if contact exists
     */
    public function exists(int $id): bool {
        global $wpdb;
        $count = $wpdb->get_var(
            $wpdb->prepare("SELECT COUNT(*) FROM {$this->table_contacts} WHERE id = %d", $id)
        );
        return (int) $count > 0;
    }

    /**
     * Delete contact and all associated messages
     *
     * @param int $contact_id Contact ID to delete
     * @return array Returns ['contact_deleted' => bool, 'messages_deleted' => int]
     */
    public function delete_with_messages(int $contact_id): array {
        global $wpdb;
        $table_messages = $wpdb->prefix . Config::TABLE_MESSAGES;

        // Delete messages first
        $messages_result = $wpdb->delete($table_messages, ['contact_id' => $contact_id], ['%d']);
        $messages_deleted = $messages_result !== false ? (int) $messages_result : 0;

        // Delete contact
        $contact_result = $wpdb->delete($this->table_contacts, ['id' => $contact_id], ['%d']);
        $contact_deleted = $contact_result !== false;

        return [
            'contact_deleted' => $contact_deleted,
            'messages_deleted' => $messages_deleted,
        ];
    }
}
