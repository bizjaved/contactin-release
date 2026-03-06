<?php
// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
/**
 * REST Log Repository
 *
 * Handles all REST API call log database operations.
 * Extracted from DB class for better separation of concerns.
 *
 * @package ContactInbox\Core\Repositories
 */

declare(strict_types=1);

namespace ContactInbox\Core\Repositories;

use ContactInbox\Core\Config;

if (!defined('ABSPATH')) exit;

final class RestLogRepository {
    
    private string $table_rest_log;
    
    public function __construct() {
        global $wpdb;
        $this->table_rest_log = $wpdb->prefix . Config::TABLE_REST_LOG;
    }

    /**
     * Insert REST API log entry
     */
    public function insert(array $data): int {
        global $wpdb;

        $prepared = [
            'ip_address'        => sanitize_text_field($data['ip_address'] ?? $this->get_client_ip()),
            'user_agent'        => isset($data['user_agent']) ? sanitize_text_field($data['user_agent']) : '',
            'user_id'           => isset($data['user_id']) ? (int)$data['user_id'] : null,
            'http_method'       => sanitize_text_field($data['http_method'] ?? 'POST'),
            'endpoint'          => sanitize_text_field($data['endpoint'] ?? ''),
            'request_headers'   => isset($data['request_headers']) ? wp_json_encode($data['request_headers']) : '{}',
            'request_payload'   => isset($data['request_payload']) ? wp_json_encode($data['request_payload']) : '{}',
            'response_code'     => (int)($data['response_code'] ?? 0),
            'response_body'     => isset($data['response_body']) ? wp_json_encode($data['response_body']) : '{}',
            'validated'         => (int)($data['validated'] ?? 0),
            'token_valid'       => isset($data['token_valid']) ? (int)$data['token_valid'] : null,
            'error_code'        => isset($data['error_code']) ? sanitize_text_field($data['error_code']) : null,
            'error_message'     => isset($data['error_message']) ? sanitize_textarea_field($data['error_message']) : null,
            'validation_errors' => isset($data['validation_errors']) ? wp_json_encode($data['validation_errors']) : null,
        ];

        $format = ['%s', '%s', '%d', '%s', '%s', '%s', '%s', '%d', '%s', '%d', '%d', '%s', '%s', '%s'];
        $wpdb->insert($this->table_rest_log, $prepared, $format);

        return $wpdb->insert_id;
    }

    /**
     * Get REST log by ID
     */
    public function get_by_id(int $id): ?array {
        global $wpdb;

        return $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM {$this->table_rest_log} WHERE id = %d", $id),
            ARRAY_A
        );
    }

    /**
     * Count REST logs with optional filtering
     */
    public function count(
        ?string $http_method = null,
        ?string $endpoint = null,
        ?int $http_code = null,
        ?int $validated = null,
        ?int $days = null,
        ?string $start_date = null,
        ?string $end_date = null
    ): int {
        global $wpdb;

        $where_clauses = [];
        $where_values = [];

        if ($http_method !== null) {
            $where_clauses[] = "http_method = %s";
            $where_values[] = $http_method;
        }

        if ($endpoint !== null) {
            $where_clauses[] = "endpoint LIKE %s";
            $where_values[] = '%' . $wpdb->esc_like($endpoint) . '%';
        }

        if ($http_code !== null) {
            $where_clauses[] = "response_code = %d";
            $where_values[] = (int)$http_code;
        }

        if ($validated !== null) {
            $where_clauses[] = "validated = %d";
            $where_values[] = (int)$validated;
        }

        if ($start_date && $end_date) {
            $where_clauses[] = "timestamp >= %s AND timestamp < DATE_ADD(%s, INTERVAL 1 DAY)";
            $where_values[] = $start_date;
            $where_values[] = $end_date;
        } elseif ($days && $days > 0) {
            $where_clauses[] = "timestamp >= DATE_SUB(NOW(), INTERVAL %d DAY)";
            $where_values[] = $days;
        }

        $where = !empty($where_clauses) ? 'WHERE ' . implode(' AND ', $where_clauses) : '';
        $query = "SELECT COUNT(*) FROM {$this->table_rest_log} $where";
        if (empty($where_values)) {
            return (int)$wpdb->get_var($query);
        }

        return (int)$wpdb->get_var($wpdb->prepare($query, ...$where_values));
    }

    /**
     * Get REST logs with pagination and filtering
     */
    public function get_paginated(
        int $page = 1,
        int $per_page = 25,
        ?string $http_method = null,
        ?string $endpoint = null,
        ?int $http_code = null,
        ?int $validated = null,
        string $orderby = 'timestamp',
        string $order = 'DESC'
    ): array {
        global $wpdb;

        $offset = max(0, ($page - 1) * $per_page);

        // Validate orderby column
        $allowed_columns = ['id', 'timestamp', 'http_method', 'endpoint', 'response_code', 'validated'];
        if ($orderby === 'created_at') {
            $orderby = 'timestamp';
        }
        $orderby = in_array($orderby, $allowed_columns, true) ? $orderby : 'timestamp';
        $order = strtoupper($order) === 'ASC' ? 'ASC' : 'DESC';

        $where_clauses = [];
        $where_values = [];

        if ($http_method !== null) {
            $where_clauses[] = "http_method = %s";
            $where_values[] = $http_method;
        }

        if ($endpoint !== null) {
            $where_clauses[] = "endpoint LIKE %s";
            $where_values[] = '%' . $wpdb->esc_like($endpoint) . '%';
        }

        if ($http_code !== null) {
            $where_clauses[] = "response_code = %d";
            $where_values[] = (int)$http_code;
        }

        if ($validated !== null) {
            $where_clauses[] = "validated = %d";
            $where_values[] = (int)$validated;
        }

        $where = !empty($where_clauses) ? 'WHERE ' . implode(' AND ', $where_clauses) : '';

        $query = "SELECT * FROM {$this->table_rest_log} $where ORDER BY $orderby $order LIMIT %d OFFSET %d";
        $query_values = array_merge($where_values, [$per_page, $offset]);

        return $wpdb->get_results($wpdb->prepare($query, ...$query_values), ARRAY_A);
    }

    /**
     * Prune REST logs older than N days
     */
    public function prune(int $days): int {
        global $wpdb;

        $cutoff = gmdate('Y-m-d H:i:s', strtotime("-{$days} days"));

        return (int) $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM {$this->table_rest_log} WHERE timestamp < %s",
                $cutoff
            )
        );
    }

    /**
     * Get REST logs with direct limit and offset (not page-based)
     */
    public function get_with_limit_offset(
        int $limit,
        int $offset,
        ?string $http_method = null,
        ?string $endpoint = null,
        ?int $http_code = null,
        ?int $validated = null,
        string $orderby = 'timestamp',
        string $order = 'DESC'
    ): array {
        global $wpdb;
        $table = $this->table_rest_log;

        $allowed_order = ['id', 'timestamp', 'http_method', 'endpoint', 'response_code', 'validated'];
        if ($orderby === 'created_at') {
            $orderby = 'timestamp';
        }
        $orderby = in_array($orderby, $allowed_order, true) ? $orderby : 'timestamp';

        $where = [];
        $params = [];

        if ($http_method) {
            $where[] = 'http_method = %s';
            $params[] = $http_method;
        }

        if ($endpoint) {
            $where[] = 'endpoint LIKE %s';
            $params[] = '%' . $wpdb->esc_like($endpoint) . '%';
        }

        if ($http_code !== null) {
            $where[] = 'response_code = %d';
            $params[] = $http_code;
        }

        if ($validated !== null) {
            $where[] = 'validated = %d';
            $params[] = $validated;
        }

        $sql = "SELECT * FROM {$table}";

        if ($where) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }

        $sql .= " ORDER BY $orderby $order LIMIT %d OFFSET %d";
        $params[] = $limit;
        $params[] = $offset;

        return (array)$wpdb->get_results($wpdb->prepare($sql, ...$params), ARRAY_A);
    }
    public function update_status(
        int $id,
        int $response_code,
        int $validated,
        ?string $error_code = null,
        ?string $error_message = null
    ): bool {
        global $wpdb;

        $update_data = [
            'response_code' => $response_code,
            'validated'     => $validated,
        ];

        $format = ['%d', '%d'];

        if ($error_code !== null) {
            $update_data['error_code'] = $error_code;
            $format[] = '%s';
        }

        if ($error_message !== null) {
            $update_data['error_message'] = $error_message;
            $format[] = '%s';
        }

        $result = $wpdb->update(
            $this->table_rest_log,
            $update_data,
            ['id' => $id],
            $format,
            ['%d']
        );

        return $result !== false;
    }

    /**
     * Get adjacent REST log (for navigation)
     */
    public function get_adjacent(
        int $current_id,
        string $direction,
        string $http_method = 'all',
        string $endpoint = 'all',
        string $http_code = 'all',
        string $validated = 'all'
    ): ?array {
        global $wpdb;

        $current = $this->get_by_id($current_id);
        if (!$current) {
            return null;
        }

        $where_clauses = [];
        $where_values = [];
        $operator = $direction === 'next' ? '>' : '<';
        $order = $direction === 'next' ? 'ASC' : 'DESC';

        $where_clauses[] = "id $operator %d";
        $where_values[] = $current_id;

        if ($http_method !== 'all') {
            $where_clauses[] = "http_method = %s";
            $where_values[] = $http_method;
        }

        if ($endpoint !== 'all') {
            $where_clauses[] = "endpoint = %s";
            $where_values[] = $endpoint;
        }

        if ($http_code !== 'all') {
            $where_clauses[] = "response_code = %d";
            $where_values[] = (int)$http_code;
        }

        if ($validated === 'success') {
            $where_clauses[] = "validated = %d";
            $where_values[] = 1;
        } elseif ($validated === 'failed') {
            $where_clauses[] = "validated = %d";
            $where_values[] = 0;
        }

        $where = 'WHERE ' . implode(' AND ', $where_clauses);

        return $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM {$this->table_rest_log} $where ORDER BY id $order LIMIT 1", ...$where_values),
            ARRAY_A
        );
    }

    /**
     * Get distinct endpoints
     */
    public function get_distinct_endpoints(): array {
        global $wpdb;

        $results = $wpdb->get_results(
            "SELECT DISTINCT endpoint FROM {$this->table_rest_log} ORDER BY endpoint ASC",
            ARRAY_A
        );

        return array_map(fn($row) => $row['endpoint'], $results);
    }

    /**
     * Clear all REST logs
     */
    public function clear_all(): bool {
        global $wpdb;
        return $wpdb->query("TRUNCATE TABLE {$this->table_rest_log}") !== false;
    }

    /**
     * Get table name
     */
    public function get_table_name(): string {
        return $this->table_rest_log;
    }

    private function server_text(string $key, string $default = ''): string {
        $value = filter_input(INPUT_SERVER, $key, FILTER_SANITIZE_FULL_SPECIAL_CHARS);
        return is_string($value) ? sanitize_text_field(wp_unslash($value)) : $default;
    }

    /**
     * Get client IP address
     */
    private function get_client_ip(): string {
        $client_ip = $this->server_text('HTTP_CLIENT_IP');
        if ($client_ip !== '') {
            return $client_ip;
        }

        $forwarded_for = $this->server_text('HTTP_X_FORWARDED_FOR');
        if ($forwarded_for !== '') {
            return sanitize_text_field(explode(',', $forwarded_for)[0]);
        } else {
            return $this->server_text('REMOTE_ADDR');
        }
    }
}
