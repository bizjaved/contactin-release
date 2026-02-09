<?php
declare(strict_types=1);

namespace ContactInbox\Core\Repositories;

use ContactInbox\Core\Config;
use ContactInbox\Core\CRMStatus;
use ContactInbox\Core\Logger;
if (!defined('ABSPATH')) exit;

final class CRMRepository {
    private string $table_crm_log;

    public function __construct() {
        global $wpdb;
        $this->table_crm_log = $wpdb->prefix . Config::TABLE_CRM_LOG;
    }

    /**
     * Clear all CRM logs
     * @return int Number of rows deleted
     */
    public function clear_all(): int {
        global $wpdb;
        $table = esc_sql($this->table_crm_log);
        
        $table_exists = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $this->table_crm_log));
        if (!$table_exists) {
            return 0;
        }
        
        $wpdb->query("TRUNCATE TABLE {$table}");
        return $wpdb->rows_affected;
    }

    /**
     * Prune CRM logs older than X days
     * @param int $days
     * @return int Number of rows deleted
     */
    public function prune_old_logs(int $days = 30): int {
        global $wpdb;
        $table = esc_sql($this->table_crm_log);
        $table_exists = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $this->table_crm_log));
        if (!$table_exists) {
            return 0;
        }
        $sql = $wpdb->prepare(
            "DELETE FROM {$table} WHERE created_at < DATE_SUB(NOW(), INTERVAL %d DAY)",
            $days
        );
        $wpdb->query($sql);
        return $wpdb->rows_affected;
    }

    /**
     * Insert CRM operation log
     */
    public function insert_log(array $data): int {
        global $wpdb;

        $prepared = [
            'message_id'      => (int)($data['message_id'] ?? 0),
            'crm_system'      => sanitize_text_field($data['crm_system'] ?? ''),
            'operation'       => sanitize_text_field($data['operation'] ?? 'sync'),
            'crm_id'          => isset($data['crm_id']) ? sanitize_text_field($data['crm_id']) : null,
            'status'          => CRMStatus::normalize($data['status'] ?? CRMStatus::PENDING),
            'response'        => isset($data['response']) ? wp_json_encode($data['response']) : '{}',
            'error_message'   => isset($data['error_message']) ? sanitize_textarea_field($data['error_message']) : null,
        ];

        $format = ['%d', '%s', '%s', '%s', '%s', '%s', '%s'];
        $wpdb->insert($this->table_crm_log, $prepared, $format);

        return $wpdb->insert_id;
    }

    /**
     * Insert CRM operation log and update contact CRM sync status (with transaction)
     * Only updates contact status on FIRST sync (when status is currently NULL)
     * 
     * @param array $data Log data (message_id, crm_system, operation, crm_id, status, response, error_message)
     * @param int $contact_id Contact ID to update sync status for
     * @return int Log ID on success, 0 on failure
     */
    public function insert_log_with_contact_sync(array $data, int $contact_id): int {
        global $wpdb;

        try {
            // Start transaction
            $wpdb->query('START TRANSACTION');

            // Insert CRM log
            $prepared = [
                'message_id'      => (int)($data['message_id'] ?? 0),
                'crm_system'      => sanitize_text_field($data['crm_system'] ?? ''),
                'operation'       => sanitize_text_field($data['operation'] ?? 'sync'),
                'crm_id'          => isset($data['crm_id']) ? sanitize_text_field($data['crm_id']) : null,
                'status'          => CRMStatus::normalize($data['status'] ?? CRMStatus::PENDING),
                'response'        => isset($data['response']) ? wp_json_encode($data['response']) : '{}',
                'error_message'   => isset($data['error_message']) ? sanitize_textarea_field($data['error_message']) : null,
            ];

            $format = ['%d', '%s', '%s', '%s', '%s', '%s', '%s'];
            $result = $wpdb->insert($this->table_crm_log, $prepared, $format);
            
            if ($result === false) {
                throw new \Exception('Failed to insert CRM log: ' . $wpdb->last_error);
            }

            $log_id = $wpdb->insert_id;

            // Update contact CRM sync status ONLY on first sync (when status is NULL)
            if ($contact_id > 0 && isset($data['status']) && $data['status'] === 'sent') {
                $contacts_table = $wpdb->prefix . Config::TABLE_CONTACTS;
                $current_status = $wpdb->get_var($wpdb->prepare(
                    "SELECT crm_sync_status FROM {$contacts_table} WHERE id = %d",
                    $contact_id
                ));

                // Only update if status is NULL (first-time sync)
                if ($current_status === null) {
                    $contact_repo = new ContactRepository();
                    $update_result = $contact_repo->update($contact_id, ['crm_sync_status' => 'sent']);
                    
                    if (!$update_result) {
                        throw new \Exception('Failed to update contact CRM sync status');
                    }
                }
            }

            // Commit transaction
            $wpdb->query('COMMIT');

            return $log_id;
        } catch (\Exception $e) {
            // Rollback on error
            $wpdb->query('ROLLBACK');
            
            Logger::log(
                Logger::ERROR,
                'CRM log with contact sync failed: ' . $e->getMessage(),
                ['contact_id' => $contact_id, 'data' => $data, 'error' => $e->getMessage()]
            );

            return 0;
        }
    }

    /**
     * Update existing CRM log entry
     * 
     * @param int $log_id The log ID to update
     * @param array $data Updated data (status, crm_id, response, error_message)
     * @return bool True on success, false on failure
     */
    public function update_log(int $log_id, array $data): bool {
        global $wpdb;

        if ($log_id <= 0) {
            return false;
        }

        $update = [];
        $format = [];

        if (isset($data['status'])) {
            $update['status'] = CRMStatus::normalize($data['status']);
            $format[] = '%s';
        }

        if (isset($data['crm_id'])) {
            $update['crm_id'] = $data['crm_id'] ? sanitize_text_field($data['crm_id']) : null;
            $format[] = '%s';
        }

        if (isset($data['response'])) {
            $update['response'] = wp_json_encode($data['response']);
            $format[] = '%s';
        }

        if (isset($data['error_message'])) {
            $update['error_message'] = $data['error_message'] ? sanitize_textarea_field($data['error_message']) : null;
            $format[] = '%s';
        }

        if (empty($update)) {
            return false;
        }

        $result = $wpdb->update(
            $this->table_crm_log,
            $update,
            ['id' => $log_id],
            $format,
            ['%d']
        );

        return $result !== false;
    }

    /**
     * Determine if a message already has a successful sync log
     */
    public function has_successful_sync(int $message_id): bool {
        global $wpdb;

        if ($message_id <= 0) {
            return false;
        }

        $table = esc_sql($this->table_crm_log);

        $table_exists = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $this->table_crm_log));
        if (!$table_exists) {
            return false;
        }

        $success_statuses = CRMStatus::expand_filter(CRMStatus::DELIVERED);
        if (empty($success_statuses)) {
            $success_statuses = [CRMStatus::DELIVERED];
        }

        $placeholders = implode(',', array_fill(0, count($success_statuses), '%s'));
        $sql = $wpdb->prepare(
            "SELECT id FROM {$table} WHERE message_id = %d AND operation = %s AND status IN ({$placeholders}) ORDER BY id DESC LIMIT 1",
            array_merge([$message_id, 'sync'], $success_statuses)
        );

        return (bool) $wpdb->get_var($sql);
    }

    /**
     * Get CRM logs with limit/offset and filtering
     */
    public function get_logs(
        int $per_page = 20,
        int $offset = 0,
        string $orderby = 'created_at',
        string $order = 'DESC',
        string $status = 'all',
        ?string $operation = null,
        ?int $days = null,
        ?string $start_date = null,
        ?string $end_date = null
    ): array {
        global $wpdb;

        $table = esc_sql($this->table_crm_log);

        // Check if table exists before querying
        $table_exists = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $this->table_crm_log));
        if (!$table_exists) {
            return [];
        }

        $allowed = ['id', 'created_at', 'message_id', 'crm_system', 'operation', 'crm_id', 'status'];
        $orderby = in_array($orderby, $allowed, true) ? $orderby : 'created_at';
        $order   = in_array(strtoupper($order), ['ASC', 'DESC'], true) ? strtoupper($order) : 'DESC';

        $where  = [];
        $params = [];

        if (!empty($status) && $status !== 'all') {
            $filter_values = CRMStatus::expand_filter($status);
            if (!empty($filter_values)) {
                $placeholders = implode(',', array_fill(0, count($filter_values), '%s'));
                $where[] = "status IN ($placeholders)";
                $params = array_merge($params, $filter_values);
            }
        }

        if (!empty($operation) && $operation !== 'all') {
            $where[] = 'operation = %s';
            $params[] = sanitize_text_field($operation);
        }

        if ($start_date && $end_date) {
            $where[] = 'DATE(created_at) >= %s AND DATE(created_at) <= %s';
            $params[] = $start_date;
            $params[] = $end_date;
        } elseif ($days && $days > 0) {
            $where[] = 'DATE(created_at) >= DATE_SUB(CURDATE(), INTERVAL %d DAY)';
            $params[] = $days;
        }

        $sql = "SELECT * FROM {$table}";
        if ($where) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }
        $sql .= " ORDER BY {$orderby} {$order} LIMIT %d OFFSET %d";
        $params[] = $per_page;
        $params[] = $offset;

        $results = (array)$wpdb->get_results($wpdb->prepare($sql, ...$params), ARRAY_A);
        foreach ($results as &$row) {
            $row['status'] = CRMStatus::normalize($row['status'] ?? '');
        }
        unset($row);
        return $results;
    }

    /**
     * Count CRM logs with optional filtering
     */
    public function count_logs(string $status = 'all', ?string $operation = null): int {
        global $wpdb;

        // Check if table exists before querying
        $table_exists = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $this->table_crm_log));
        
        if (!$table_exists) {
            return 0;
        }

        $where = [];
        $params = [];

        if ($status !== 'all') {
            $filter_values = CRMStatus::expand_filter($status);
            if (!empty($filter_values)) {
                $placeholders = implode(',', array_fill(0, count($filter_values), '%s'));
                $where[] = "status IN ($placeholders)";
                $params = array_merge($params, $filter_values);
            }
        }

        if (!empty($operation) && $operation !== 'all') {
            $where[] = 'operation = %s';
            $params[] = sanitize_text_field($operation);
        }

        $where_clause = $where ? ('WHERE ' . implode(' AND ', $where)) : '';
        $sql = "SELECT COUNT(*) FROM {$this->table_crm_log} {$where_clause}";

        return $params
            ? (int)$wpdb->get_var($wpdb->prepare($sql, ...$params))
            : (int)$wpdb->get_var($sql);
    }

    /**
     * Get distinct CRM operations for filter dropdowns
     */
    public function get_operations(): array {
        global $wpdb;
        $table_exists = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $this->table_crm_log));
        if (!$table_exists) {
            return [];
        }

        $operations = (array)$wpdb->get_col(
            "SELECT DISTINCT operation FROM {$this->table_crm_log} WHERE operation IS NOT NULL AND operation <> '' ORDER BY operation ASC"
        );

        return array_values(array_filter(array_map('sanitize_text_field', $operations)));
    }

    /**
     * Get CRM statistics
     */
    public function get_stats(?int $days = null, ?string $start_date = null, ?string $end_date = null, ?string $operation = null): array {
        global $wpdb;
        $table = esc_sql($this->table_crm_log);
        $where = [];
        $params = [];


        // Date filtering (use DATE(created_at) for compatibility with dashboard filters)
        if ($start_date && $end_date) {
            $where[] = 'DATE(created_at) >= %s AND DATE(created_at) <= %s';
            $params[] = $start_date;
            $params[] = $end_date;
        } elseif ($days && $days > 0) {
            $where[] = 'DATE(created_at) >= DATE_SUB(CURDATE(), INTERVAL %d DAY)';
            $params[] = $days;
        }

        if (!empty($operation) && $operation !== 'all') {
            $where[] = 'operation = %s';
            $params[] = sanitize_text_field($operation);
        }

        $where_clause = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

        // Total syncs
        $sql_total = "SELECT COUNT(*) FROM {$table} {$where_clause}";
        $total = (int)($params ? $wpdb->get_var($wpdb->prepare($sql_total, ...$params)) : $wpdb->get_var($sql_total));

        // Successful syncs (delivered + legacy aliases)
        $success_values = CRMStatus::expand_filter(CRMStatus::DELIVERED);
        $success_placeholders = implode(',', array_fill(0, count($success_values), '%s'));
        $success_prefix = $where_clause ? "$where_clause AND " : 'WHERE ';
        $sql_success = "SELECT COUNT(*) FROM {$table} {$success_prefix}status IN ($success_placeholders)";
        $success_params = array_merge($params, $success_values);
        $success = (int)$wpdb->get_var($wpdb->prepare($sql_success, ...$success_params));

        // Failed syncs (failed/rejected/server errors...)
        $failure_values = array_unique(array_merge(
            CRMStatus::get_failure_statuses(),
            CRMStatus::expand_filter(CRMStatus::FAILED)
        ));
        $failure_placeholders = implode(',', array_fill(0, count($failure_values), '%s'));
        $failure_prefix = $where_clause ? "$where_clause AND " : 'WHERE ';
        $sql_failed = "SELECT COUNT(*) FROM {$table} {$failure_prefix}status IN ($failure_placeholders)";
        $failed_params = array_merge($params, $failure_values);
        $failed = (int)$wpdb->get_var($wpdb->prepare($sql_failed, ...$failed_params));

        // Success rate should ignore pending/unknown states and only consider attempted syncs
        $attempted = $success + $failed;
        $success_rate = $attempted > 0 ? round(($success / $attempted) * 100, 1) : 0;
        $pending = max($total - $attempted, 0);

        // Recent activity (24h) - only if no date filter was applied
        if (empty($start_date) && empty($days)) {
            $activity_24h = (int)$wpdb->get_var(
                "SELECT COUNT(*) FROM {$table} WHERE created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)"
            );
        } else {
            $activity_24h = $total; // If filtered, the total IS the activity for that period
        }

        return [
            'total_syncs'     => $total,
            'successful'      => $success,
            'failed'          => $failed,
            'success_rate'    => $success_rate,
            'pending'         => $pending,
            'activity_24h'    => $activity_24h,
        ];
    }

    /**
     * Get single CRM log
     */
    public function get_log(int $id): ?array {
        global $wpdb;

        // Check if table exists before querying
        $table_exists = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $this->table_crm_log));
        
        if (!$table_exists) {
            return null;
        }

        $row = $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM {$this->table_crm_log} WHERE id = %d", $id),
            ARRAY_A
        );

        if ($row) {
            $row['status'] = CRMStatus::normalize($row['status'] ?? '');
        }

        return $row;
    }

    /**
     * Get table name
     */
    public function get_table_name(): string {
        return $this->table_crm_log;
    }
}
