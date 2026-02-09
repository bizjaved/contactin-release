<?php
/**
 * CRM Error Repository
 * 
 * Handles all CRM error logging to crm_errors table
 *
 * @package ContactInbox\Core\Repositories
 */

declare(strict_types=1);

namespace ContactInbox\Core\Repositories;

use ContactInbox\Core\Config;

if (!defined('ABSPATH')) exit;

final class CRMErrorRepository {
    
    private string $table;
    
    public function __construct() {
        global $wpdb;
        $this->table = $wpdb->prefix . 'contactin_crm_errors';
    }
    
    /**
     * Log a CRM error
     */
    public function log_error(
        int $message_id,
        string $error_type,
        int $http_code,
        string $error_message,
        ?int $webhook_log_id = null,
        ?string $endpoint = null
    ): int {
        global $wpdb;
        
        $wpdb->insert($this->table, [
            'message_id' => $message_id,
            'error_type' => sanitize_text_field($error_type),
            'http_code' => $http_code,
            'error_message' => sanitize_textarea_field($error_message),
            'webhook_log_id' => $webhook_log_id,
            'endpoint' => $endpoint ? sanitize_text_field($endpoint) : '',
        ], ['%d', '%s', '%d', '%s', '%d', '%s']);
        
        return $wpdb->insert_id;
    }
    
    /**
     * Get errors by type for a date range
     */
    public function get_errors_by_type(string $start_date, string $end_date): array {
        global $wpdb;
        
        return $wpdb->get_results($wpdb->prepare(
            "SELECT error_type, COUNT(*) as count, http_code
             FROM {$this->table}
             WHERE DATE(created_at) BETWEEN %s AND %s
             GROUP BY error_type, http_code
             ORDER BY count DESC",
            $start_date,
            $end_date
        ), ARRAY_A) ?: [];
    }
    
    /**
     * Get recent errors
     */
    public function get_recent_errors(int $limit = 10): array {
        global $wpdb;
        
        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$this->table}
             ORDER BY created_at DESC
             LIMIT %d",
            $limit
        ), ARRAY_A) ?: [];
    }
    
    /**
     * Get error count by type for today
     */
    public function count_by_type_today(): array {
        global $wpdb;
        $today = date('Y-m-d');
        
        return $wpdb->get_results($wpdb->prepare(
            "SELECT error_type, COUNT(*) as count
             FROM {$this->table}
             WHERE DATE(created_at) = %s
             GROUP BY error_type",
            $today
        ), ARRAY_A) ?: [];
    }
}
