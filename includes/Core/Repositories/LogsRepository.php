<?php
// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound, WordPress.DB.PreparedSQL.NotPrepared, Generic.PHP.ForbiddenFunctions.Found, PluginCheck.CodeAnalysis.DiscouragedFunctions.load_plugin_textdomainFound, PluginCheck.CodeAnalysis.Heredoc.NotAllowed, PluginCheck.Security.DirectDB.UnescapedDBParameter, Squiz.PHP.DiscouragedFunctions.Discouraged, WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.NamingConventions.PrefixAllGlobals.DynamicHooknameFound, WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound, WordPress.PHP.DevelopmentFunctions.error_log_debug_backtrace, WordPress.WP.AlternativeFunctions.file_system_operations_fsockopen, WordPress.WP.AlternativeFunctions.file_system_operations_readfile, WordPress.WP.AlternativeFunctions.file_system_operations_rmdir, WordPress.WP.EnqueuedResourceParameters.MissingVersion, WordPress.WP.EnqueuedResources.NonEnqueuedScript, WordPress.WP.I18n.MissingArgDomain, WordPress.WP.I18n.UnorderedPlaceholdersPlural, WordPress.WP.I18n.UnorderedPlaceholdersSingle
/**
 * Logs Repository
 * 
 * All database operations for application logs
 *
 * @package ContactIn\Core\Repositories
 */

declare(strict_types=1);

namespace ContactInbox\Core\Repositories;

use ContactInbox\Core\Config;

if (!defined('ABSPATH')) exit;

final class LogsRepository {
    
    private string $table;
    
    public function __construct() {
        global $wpdb;
        $this->table = $wpdb->prefix . 'contactin_logs';
    }
    
    /**
     * Write log entry to database
     */
    public function log(string $level, string $message, array $context = []): int {
        global $wpdb;
        
        $wpdb->insert($this->table, [
            'level' => sanitize_text_field($level),
            'message' => sanitize_textarea_field($message),
            'context' => wp_json_encode($context),
            'timestamp' => current_time('mysql'),
        ], ['%s', '%s', '%s', '%s']);
        
        return $wpdb->insert_id;
    }
    
    /**
     * Get recent logs, optionally filtered by level
     */
    public function get_recent(int $limit = 100, ?string $level = null): array {
        global $wpdb;
        
        $query = "SELECT * FROM {$this->table}";
        $params = [];
        
        if ($level) {
            $query .= " WHERE level = %s";
            $params[] = $level;
        }
        
        $query .= " ORDER BY timestamp DESC LIMIT %d";
        $params[] = $limit;
        
        return $wpdb->get_results(
            $wpdb->prepare($query, ...$params),
            ARRAY_A
        ) ?: [];
    }
    
    /**
     * Cleanup old logs (for cron job)
     */
    public function cleanup_old_logs(int $days = 30): int {
        global $wpdb;
        
        $date = gmdate('Y-m-d H:i:s', strtotime("-$days days"));
        
        return $wpdb->query($wpdb->prepare(
            "DELETE FROM {$this->table} WHERE timestamp < %s",
            $date
        ));
    }
}
