<?php
// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
/**
 * Database Optimizer – MySQL Transaction & Performance Tuning
 *
 * Provides utilities and best practices for optimizing database transaction
 * performance, connection pooling, and query execution.
 *
 * @package ContactInbox\Core
 */

declare(strict_types=1);

namespace ContactInbox\Core;

use ContactInbox\Traits\Singleton;

if (!defined('ABSPATH')) exit;

final class DatabaseOptimizer {
    use Singleton;

    /**
     * Get MySQL configuration recommendations for optimal transaction performance
     *
     * @return array Recommended MySQL settings
     */
    public static function get_optimal_mysql_config(): array {
        return [
            // InnoDB engine settings (better transaction support than MyISAM)
            'default_storage_engine' => 'InnoDB',
            
            // Connection and thread settings
            'max_connections' => 300,
            'max_allowed_packet' => '64M',
            
            // InnoDB buffer pool (cache for data/indexes) - most important for performance
            'innodb_buffer_pool_size' => '75% of total RAM (min 1GB for production)',
            
            // Transaction isolation level - READ COMMITTED reduces locking
            'transaction_isolation' => 'READ-COMMITTED',
            
            // Lock timeout to prevent long-running locks
            'innodb_lock_wait_timeout' => 50,
            
            // Timeout for interactive connections
            'interactive_timeout' => 28800,
            'wait_timeout' => 28800,
            
            // Flush logs less frequently for better performance
            'innodb_flush_log_at_trx_commit' => 2, // Flush log once per second (less durable but faster)
            
            // Enable binary logging for replication/recovery
            'log_bin' => 'ON',
            'binlog_format' => 'MIXED',
            
            // Query cache (if MySQL 5.7 or earlier - disabled by default in 8.0+)
            'query_cache_type' => 'ON',
            'query_cache_size' => '256M',
            
            // Slow query logging for debugging
            'slow_query_log' => 'ON',
            'long_query_time' => 2,
        ];
    }

    /**
     * Verify form submission table has all performance indexes
     * 
     * Note: Since plugin is not released, all indexes are created
     * via CREATE TABLE in DB::get_table_definitions() during activation.
     * This method is kept for future maintenance and manual index verification.
     *
     * @return array Status of index verification
     */
    public static function optimize_submission_table(): array {
        global $wpdb;
        
        $table = $wpdb->prefix . Config::TABLE_MESSAGES;
        $expected_indexes = [
            'idx_email',
            'idx_receipt_token',
            'idx_status',
            'idx_submitted_at',
            'idx_form_id',
            'idx_ip_address',
            'idx_consent_status',
        ];
        
        $created = [];
        $existing = [];
        
        // Verify all expected indexes exist
        foreach ($expected_indexes as $index_name) {
            $exists = $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM information_schema.STATISTICS 
                 WHERE TABLE_SCHEMA = DATABASE() 
                 AND TABLE_NAME = %s 
                 AND INDEX_NAME = %s",
                str_replace($wpdb->prefix, '', $table),
                $index_name
            ));
            
            if ($exists) {
                $existing[] = $index_name;
            }
        }
        
        return [
            'existing' => $existing,
            'total_expected' => count($expected_indexes),
            'total_existing' => count($existing),
            'status' => count($existing) === count($expected_indexes) ? 'ok' : 'warning',
        ];
    }

    /**
     * Get current MySQL InnoDB status
     *
     * @return array InnoDB engine statistics
     */
    public static function get_innodb_status(): array {
        global $wpdb;
        
        try {
            $status = $wpdb->get_results(
                "SHOW ENGINE INNODB STATUS",
                ARRAY_A
            );
            
            if (!$status) {
                return ['error' => 'InnoDB engine not available'];
            }
            
            return [
                'status' => $status[0]['Status'] ?? '',
                'timestamp' => current_time('mysql'),
            ];
        } catch (\Throwable $e) {
            return ['error' => $e->getMessage()];
        }
    }

    /**
     * Get database table statistics
     *
     * @return array Table statistics
     */
    public static function get_table_stats(): array {
        global $wpdb;
        
        try {
            $messages_table = $wpdb->prefix . Config::TABLE_MESSAGES;
            
            // Get table size and row count
            $stats = $wpdb->get_row($wpdb->prepare(
                "SELECT 
                    TABLE_NAME,
                    TABLE_ROWS as row_count,
                    ROUND(((data_length + index_length) / 1024 / 1024), 2) as size_mb,
                    ROUND((data_length / 1024 / 1024), 2) as data_size_mb,
                    ROUND((index_length / 1024 / 1024), 2) as index_size_mb,
                    AUTO_INCREMENT,
                    ENGINE
                FROM information_schema.TABLES
                WHERE TABLE_SCHEMA = DATABASE()
                AND TABLE_NAME = %s",
                str_replace($wpdb->prefix, '', $messages_table)
            ), ARRAY_A);
            
            return $stats ?: [];
        } catch (\Throwable $e) {
            return ['error' => $e->getMessage()];
        }
    }

    /**
     * Optimize table (defragment and recalculate statistics)
     *
     * Run this periodically (monthly) for best performance
     */
    public static function optimize_table(): bool {
        global $wpdb;
        
        $table = $wpdb->prefix . Config::TABLE_MESSAGES;
        
        try {
            $wpdb->query("OPTIMIZE TABLE {$table}");
            Logger::info("Optimized table: {$table}");
            return true;
        } catch (\Throwable $e) {
            Logger::error('Table optimization failed', ['error' => $e->getMessage()]);
            return false;
        }
    }

    /**
     * Get transaction performance metrics
     *
     * @return array Performance metrics
     */
    public static function get_transaction_metrics(): array {
        global $wpdb;
        
        try {
            // Get slow query information
            $slow_queries = (int)$wpdb->get_var(
                "SHOW GLOBAL STATUS LIKE 'Slow_queries'"
            );
            
            // Get transaction info
            $trx_info = $wpdb->get_results(
                "SELECT * FROM information_schema.innodb_trx LIMIT 10",
                ARRAY_A
            );
            
            // Get lock info
            $locks = $wpdb->get_results(
                "SELECT * FROM information_schema.innodb_locks LIMIT 10",
                ARRAY_A
            );
            
            return [
                'slow_queries_total' => $slow_queries,
                'active_transactions' => count($trx_info),
                'transactions' => $trx_info,
                'locks' => $locks,
                'timestamp' => current_time('mysql'),
            ];
        } catch (\Throwable $e) {
            return ['error' => $e->getMessage()];
        }
    }

    /**
     * Kill long-running transactions (use with caution)
     *
     * @param int $timeout_seconds Kill transactions older than this
     * @return int Number of killed transactions
     */
    public static function kill_long_transactions(int $timeout_seconds = 300): int {
        global $wpdb;
        
        try {
            $cutoff_time = gmdate('Y-m-d H:i:s', time() - $timeout_seconds);
            
            $long_trx = $wpdb->get_results($wpdb->prepare(
                "SELECT trx_id, trx_mysql_thread_id 
                 FROM information_schema.innodb_trx 
                 WHERE trx_started < %s",
                $cutoff_time
            ));
            
            $killed = 0;
            foreach ((array)$long_trx as $trx) {
                $wpdb->query($wpdb->prepare(
                    "KILL %d",
                    $trx->trx_mysql_thread_id
                ));
                $killed++;
                Logger::warning("Killed long transaction", [
                    'trx_id' => $trx->trx_id,
                    'thread_id' => $trx->trx_mysql_thread_id,
                ]);
            }
            
            return $killed;
        } catch (\Throwable $e) {
            Logger::error('Failed to kill transactions', ['error' => $e->getMessage()]);
            return 0;
        }
    }

    /**
     * Enable transaction batching for bulk operations
     *
     * Use this pattern:
     * $batch = DatabaseOptimizer::start_batch(1000);
     * foreach ($items as $item) {
     *     // process item
     *     $batch->add_query($query);
     *     $batch->execute_if_full();
     * }
     * $batch->flush();
     */
    public static function start_batch(int $batch_size = 100): object {
        global $wpdb;
        
        return (object)[
            'batch_size' => $batch_size,
            'queries' => [],
            'executed' => 0,
            'wpdb' => $wpdb,
            
            'add_query' => function($query) use (&$batch) {
                $batch->queries[] = $query;
            },
            
            'execute_if_full' => function() use (&$batch) {
                if (count($batch->queries) >= $batch->batch_size) {
                    $batch->flush();
                }
            },
            
            'flush' => function() use (&$batch) {
                if (empty($batch->queries)) return;
                
                $batch->wpdb->query('START TRANSACTION');
                foreach ($batch->queries as $query) {
                    $batch->wpdb->query($query);
                }
                $batch->wpdb->query('COMMIT');
                
                $batch->executed += count($batch->queries);
                $batch->queries = [];
            },
        ];
    }

    /**
     * Get comprehensive MySQL performance report
     *
     * @return array Detailed performance report
     */
    public static function get_performance_report(): array {
        return [
            'recommended_config' => self::get_optimal_mysql_config(),
            'current_table_stats' => self::get_table_stats(),
            'transaction_metrics' => self::get_transaction_metrics(),
            'innodb_status' => self::get_innodb_status(),
            'recommendations' => [
                'connection_pooling' => 'Consider using ProxySQL or MariaDB MaxScale for connection pooling',
                'read_replicas' => 'For high traffic, implement read replicas with master-slave replication',
                'caching' => 'Use Redis or Memcached to reduce database queries',
                'batch_inserts' => 'Use batch inserts with START TRANSACTION for bulk operations',
                'query_optimization' => 'Review slow query log and add indexes as needed',
                'maintenance' => 'Run OPTIMIZE TABLE monthly and ANALYZE TABLE regularly',
            ],
        ];
    }
}
