<?php
/**
 * CRM Monitor Stub
 * 
 * This is a stub class to prevent fatal errors.
 * Actual CRM monitoring is a PRO feature.
 *
 * @package ContactInbox
 */

namespace ContactInbox\Core;

/**
 * CRMMonitor stub class
 */
class CRMMonitor {
    
    /**
     * Singleton instance
     *
     * @var CRMMonitor|null
     */
    private static $instance = null;
    
    /**
     * Get singleton instance
     *
     * @return CRMMonitor
     */
    public static function instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Get statistics
     *
     * @param int $days Days to query
     * @param string|null $start_date Start date
     * @param string|null $end_date End date
     * @param string $operation Operation type
     * @return array
     */
    public static function get_statistics($days = 30, $start_date = null, $end_date = null, $operation = 'sync') {
        return [
            'total' => 0,
            'success' => 0,
            'failed' => 0,
            'pending' => 0,
            'success_rate' => 0,
        ];
    }
    
    /**
     * Get health status
     *
     * @param int $days Days to query
     * @param string|null $start_date Start date
     * @param string|null $end_date End date
     * @param string $operation Operation type
     * @return array
     */
    public static function get_health_status($days = 30, $start_date = null, $end_date = null, $operation = 'sync') {
        return [
            'status' => 'unavailable',
            'message' => 'CRM monitoring is a PRO feature',
            'score' => 0,
        ];
    }
    
    /**
     * Get endpoints
     *
     * @param int $days Days to query
     * @param string|null $start_date Start date
     * @param string|null $end_date End date
     * @param string $operation Operation type
     * @return array
     */
    public static function get_endpoints($days = 30, $start_date = null, $end_date = null, $operation = 'sync') {
        return [];
    }
    
    /**
     * Get daily stats
     *
     * @param int $days Days to query
     * @param string|null $start_date Start date
     * @param string|null $end_date End date
     * @param string $operation Operation type
     * @return array
     */
    public static function get_daily_stats($days = 7, $start_date = null, $end_date = null, $operation = 'sync') {
        return [];
    }
    
    /**
     * Get logs paginated
     *
     * @param int $per_page Items per page
     * @param int $page Current page
     * @param string $status Status filter
     * @param string $order_by Order by column
     * @param string $order Order direction
     * @param string $operation Operation type
     * @return array
     */
    public static function get_logs_paginated($per_page = 20, $page = 1, $status = '', $order_by = 'timestamp', $order = 'DESC', $operation = 'sync') {
        return [
            'logs' => [],
            'total' => 0,
            'pages' => 0,
            'current_page' => $page,
        ];
    }
}
