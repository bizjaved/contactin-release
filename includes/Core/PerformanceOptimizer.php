<?php
declare(strict_types=1);

namespace ContactInbox\Core;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Performance Optimizer Service
 *
 * Manages optimization strategies:
 * - Database index creation and validation
 * - Query result caching
 * - Batch processing optimizations
 * - Database maintenance
 *
 * @package ContactInbox\Core
 */
final class PerformanceOptimizer {

    /**
     * Index definitions with their detection queries
     * Format: [index_name => [table => 'table_name', columns => ['col1', 'col2'], type => 'KEY|UNIQUE']]
     * 
     * NOTE: As of v1.5.0, all critical indexes are created automatically in DB::get_table_definitions()
     * during plugin activation. This constant is maintained for manual index verification and
     * repair on existing installations.
     */
    private const CRITICAL_INDEXES = [
        // Queue table composite indexes
        'idx_queue_type_message_status' => [
            'table' => 'contactin_queue',
            'columns' => ['type', 'message_id', 'status'],
            'type' => 'KEY',
            'purpose' => 'Optimize find_recent_duplicate() query',
        ],
        'idx_queue_priority_created' => [
            'table' => 'contactin_queue',
            'columns' => ['priority', 'created_at'],
            'type' => 'KEY',
            'purpose' => 'Optimize priority-based queue sorting',
        ],
        // DLQ indexes
        'idx_dlq_type_created' => [
            'table' => 'contactin_dead_letter',
            'columns' => ['type', 'failed_at'],
            'type' => 'KEY',
            'purpose' => 'Optimize DLQ queries by type',
        ],
        // Messages table indexes
        'idx_messages_admin_email' => [
            'table' => 'contactin_messages',
            'columns' => ['admin_email_status', 'submitted_at'],
            'type' => 'KEY',
            'purpose' => 'Optimize admin email processing queries',
        ],
        'idx_messages_user_email' => [
            'table' => 'contactin_messages',
            'columns' => ['user_email_status', 'submitted_at'],
            'type' => 'KEY',
            'purpose' => 'Optimize user email processing queries',
        ],
        'idx_messages_crm' => [
            'table' => 'contactin_messages',
            'columns' => ['crm_status', 'submitted_at'],
            'type' => 'KEY',
            'purpose' => 'Optimize CRM processing queries',
        ],
    ];

    /**
     * Create all missing critical indexes
     * 
     * NOTE: As of v1.5.0, all critical indexes are created automatically during plugin activation
     * via CREATE TABLE statements. This method is maintained for:
     * - Manual index verification and repair
     * - Recovering from index corruption or accidental deletion
     * - Adding indexes to existing installations pre-v1.5.0
     *
     * @return array Status report with created/existing/failed indexes
     */
    public static function create_critical_indexes(): array {
        global $wpdb;
        
        $report = [
            'created' => [],
            'existing' => [],
            'failed' => [],
            'total' => count(self::CRITICAL_INDEXES),
        ];

        foreach (self::CRITICAL_INDEXES as $index_name => $definition) {
            $table = $wpdb->prefix . $definition['table'];
            
            // Check if index already exists
            if (self::index_exists($table, $index_name)) {
                $report['existing'][] = $index_name;
                continue;
            }

            // Create the index
            $columns = implode('`, `', $definition['columns']);
            $type_keyword = $definition['type'] === 'UNIQUE' ? 'UNIQUE KEY' : 'KEY';
            
            $sql = "ALTER TABLE {$table} ADD {$type_keyword} `{$index_name}` (`{$columns}`)";

            $result = $wpdb->query($sql);

            if ($result === false) {
                $report['failed'][] = [
                    'index' => $index_name,
                    'error' => $wpdb->last_error,
                ];
            } else {
                $report['created'][] = $index_name;
            }
        }

        return $report;
    }

    /**
     * Check if index exists on table
     *
     * @param string $table Full table name with prefix
     * @param string $index_name Index name to check
     * @return bool True if index exists
     */
    private static function index_exists(string $table, string $index_name): bool {
        global $wpdb;

        $result = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS 
                 WHERE TABLE_SCHEMA = %s AND TABLE_NAME = %s AND INDEX_NAME = %s",
                DB_NAME,
                str_replace($wpdb->prefix, '', $table),
                $index_name
            )
        );

        return ((int)($result ?? 0)) > 0;
    }

    /**
     * Get optimization recommendations based on current database state
     *
     * @return array List of recommendations
     */
    public static function get_recommendations(): array {
        $recommendations = [];

        // Check for missing indexes
        $index_report = self::diagnose_indexes();
        if (!empty($index_report['missing'])) {
            $recommendations[] = [
                'type' => 'index',
                'severity' => 'high',
                'message' => sprintf(
                    'Create %d missing indexes to improve query performance',
                    count($index_report['missing'])
                ),
                'action' => 'Run PerformanceOptimizer::create_critical_indexes()',
            ];
        }

        // Check for table bloat
        $bloat_report = self::diagnose_table_bloat();
        if (!empty($bloat_report['large_tables'])) {
            $recommendations[] = [
                'type' => 'maintenance',
                'severity' => 'medium',
                'message' => sprintf(
                    '%d tables could benefit from OPTIMIZE',
                    count($bloat_report['large_tables'])
                ),
                'action' => 'Run PerformanceOptimizer::optimize_tables()',
            ];
        }

        return $recommendations;
    }

    /**
     * Diagnose missing indexes
     *
     * @return array Report of missing/existing indexes
     */
    private static function diagnose_indexes(): array {
        $report = ['missing' => [], 'existing' => []];

        foreach (self::CRITICAL_INDEXES as $index_name => $definition) {
            global $wpdb;
            $table = $wpdb->prefix . $definition['table'];
            
            if (self::index_exists($table, $index_name)) {
                $report['existing'][] = $index_name;
            } else {
                $report['missing'][] = [
                    'name' => $index_name,
                    'purpose' => $definition['purpose'],
                ];
            }
        }

        return $report;
    }

    /**
     * Diagnose table bloat and fragmentation
     *
     * @return array Tables needing optimization
     */
    private static function diagnose_table_bloat(): array {
        global $wpdb;

        $tables = [
            $wpdb->prefix . Config::TABLE_QUEUE,
            $wpdb->prefix . Config::TABLE_MESSAGES,
            $wpdb->prefix . Config::TABLE_DEAD_LETTER,
        ];

        $report = ['large_tables' => []];

        foreach ($tables as $table) {
            $status = $wpdb->get_row(
                $wpdb->prepare(
                    "SELECT data_free, data_length FROM INFORMATION_SCHEMA.TABLES 
                     WHERE TABLE_SCHEMA = %s AND TABLE_NAME = %s",
                    DB_NAME,
                    str_replace($wpdb->prefix, '', $table)
                )
            );

            if ($status && ((int)($status->data_free ?? 0)) > 0) {
                $fragmentation = (int)($status->data_free) / ((int)($status->data_length) + (int)($status->data_free));
                
                if ($fragmentation > 0.1) { // More than 10% fragmented
                    $report['large_tables'][] = [
                        'table' => $table,
                        'fragmentation_percent' => round($fragmentation * 100, 2),
                    ];
                }
            }
        }

        return $report;
    }

    /**
     * Optimize tables by defragmenting
     *
     * @return array Optimization report
     */
    public static function optimize_tables(): array {
        global $wpdb;

        $tables = [
            $wpdb->prefix . Config::TABLE_QUEUE,
            $wpdb->prefix . Config::TABLE_MESSAGES,
            $wpdb->prefix . Config::TABLE_DEAD_LETTER,
        ];

        $report = ['optimized' => [], 'failed' => []];

        foreach ($tables as $table) {
            $result = $wpdb->query("OPTIMIZE TABLE {$table}");
            
            if ($result === false) {
                $report['failed'][] = [
                    'table' => $table,
                    'error' => $wpdb->last_error,
                ];
            } else {
                $report['optimized'][] = $table;
            }
        }

        Logger::info('Table optimization completed', $report);

        return $report;
    }

    /**
     * Calculate estimated memory usage for caching strategies
     *
     * @return array Memory usage estimates
     */
    public static function estimate_cache_impact(): array {
        global $wpdb;

        // Estimate queue size
        $queue_rows = (int)$wpdb->get_var(
            "SELECT COUNT(*) FROM {$wpdb->prefix}" . Config::TABLE_QUEUE
        );

        // Estimate per-row size (rough average)
        $queue_row_size = 1500; // bytes per row with data field
        $queue_total = $queue_rows * $queue_row_size;

        // Estimate settings cache impact
        $caching_opcodes = 3; // CRMSettings, AlertPreferences, PluginConfig
        $settings_cache_size = 50 * 1024; // 50KB per cached object

        return [
            'queue_rows' => $queue_rows,
            'queue_memory_estimate_mb' => round($queue_total / 1024 / 1024, 2),
            'settings_cache_memory_estimate_mb' => round(($caching_opcodes * $settings_cache_size) / 1024 / 1024, 2),
            'total_estimate_mb' => round(($queue_total + ($caching_opcodes * $settings_cache_size)) / 1024 / 1024, 2),
        ];
    }
}
