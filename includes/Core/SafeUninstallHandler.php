<?php
declare(strict_types=1);

namespace ContactInbox\Core;

use ContactInbox\Core\Config;
use ContactInbox\Core\TableDefinitions;

// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.WP.I18n.TextDomainMismatch, WordPress.PHP.DevelopmentFunctions.error_log_error_log, WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound, WordPress.DB.PreparedSQL.NotPrepared, Generic.PHP.ForbiddenFunctions.Found, PluginCheck.CodeAnalysis.DiscouragedFunctions.load_plugin_textdomainFound, PluginCheck.CodeAnalysis.Heredoc.NotAllowed, PluginCheck.Security.DirectDB.UnescapedDBParameter, Squiz.PHP.DiscouragedFunctions.Discouraged, WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.NamingConventions.PrefixAllGlobals.DynamicHooknameFound, WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound, WordPress.PHP.DevelopmentFunctions.error_log_debug_backtrace, WordPress.WP.AlternativeFunctions.file_system_operations_fsockopen, WordPress.WP.AlternativeFunctions.file_system_operations_readfile, WordPress.WP.AlternativeFunctions.file_system_operations_rmdir, WordPress.WP.EnqueuedResourceParameters.MissingVersion, WordPress.WP.EnqueuedResources.NonEnqueuedScript, WordPress.WP.I18n.MissingArgDomain, WordPress.WP.I18n.UnorderedPlaceholdersPlural, WordPress.WP.I18n.UnorderedPlaceholdersSingle
/**
 * Safe Uninstall Handler
 * 
 * Handles uninstallation safely in free/pro coexistence scenarios.
 * Preserves shared data when uninstalling one version.
 * 
 * @package ContactIn\Core
 */
final class SafeUninstallHandler {
    
    /**
     * Version of this handler for safe upgrades
     */
    private const HANDLER_VERSION = '1.0.0';
    
    /**
     * Safely uninstall the plugin
     * 
     * - Preserves shared tables when both versions are installed
     * - Drops pro-only tables only if free version exists
     * - Preserves all data if other version is active
     * 
     * @param bool $is_pro Whether this is pro version uninstalling
     * @return void
     */
    public static function uninstall(bool $is_pro = false): void {
        if (!defined('ABSPATH')) {
            return;
        }

        global $wpdb;

        // Check if other version is installed
        $other_version_exists = self::checkOtherVersionInstalled($is_pro);

        if ($other_version_exists) {
            // If other version is installed, do minimal cleanup only
            self::minimalCleanup($is_pro);
        } else {
            // Both versions being removed, clean everything
            self::fullCleanup();
        }

        // Clean up the version indicator
        self::cleanupVersionIndicators();
    }

    /**
     * Check if the other version (free if uninstalling pro, pro if uninstalling free) is installed
     * 
     * @param bool $is_pro Current version being uninstalled
     * @return bool True if other version is installed
     */
    private static function checkOtherVersionInstalled(bool $is_pro): bool {
        if ($is_pro) {
            // Check if free version is installed
            return file_exists(ABSPATH . 'wp-content/plugins/contactin/contactin.php');
        } else {
            // Check if pro version is installed
            return file_exists(ABSPATH . 'wp-content/plugins/contactin-pro/contactin.php');
        }
    }

    /**
     * Minimal cleanup when other version is present
     * Only removes version-specific data and options
     * 
     * @param bool $is_pro Whether this is pro version
     * @return void
     */
    private static function minimalCleanup(bool $is_pro): void {
        global $wpdb;

        // Remove version-specific options
        $version_option = $is_pro ? 'contact_inbox_pro_version' : 'contact_inbox_free_version';
        delete_option($version_option);

        // Remove version-specific tables only if they don't exist in other version
        if ($is_pro) {
            // Drop pro-only table if it exists
            $pro_only = TableDefinitions::getProOnlyTables();
            foreach ($pro_only as $table) {
                $table_name = $wpdb->prefix . $table;
                // @phpstan-ignore-next-line
                $wpdb->query($wpdb->prepare(
                    "DROP TABLE IF EXISTS `" . $table_name . "`"
                )); // phpcs:ignore

                // Check for errors but don't fail
                if ($wpdb->last_error) {
                    // Log the error but continue
                    error_log("[ContactIn] Failed to drop table {$table_name}: {$wpdb->last_error}");
                    $wpdb->show_errors(false);
                }
            }
        }

        // Clear cron schedules only if they're ours
        self::clearCronSchedules();
    }

    /**
     * Full cleanup when all versions are being removed
     * Removes all plugin data including shared tables
     * 
     * @return void
     */
    private static function fullCleanup(): void {
        global $wpdb;

        // Get all tables (both shared and pro-only)
        $all_tables = array_merge(
            TableDefinitions::getSharedTables(),
            TableDefinitions::getProOnlyTables()
        );

        // Drop all tables
        foreach ($all_tables as $table) {
            $table_name = $wpdb->prefix . $table;
            // @phpstan-ignore-next-line
            $wpdb->query("DROP TABLE IF EXISTS `{$table_name}`"); // phpcs:ignore

            if ($wpdb->last_error) {
                error_log("[ContactIn] Failed to drop table {$table_name}: {$wpdb->last_error}");
                $wpdb->show_errors(false);
            }
        }

        // Remove all options
        self::deleteAllOptions();

        // Clear cron schedules
        self::clearCronSchedules();
    }

    /**
     * Delete all plugin options
     * 
     * @return void
     */
    private static function deleteAllOptions(): void {
        global $wpdb;

        $option_patterns = [
            'contact_inbox%',
            'ci_%',
            'ci_crm_%',
        ];

        foreach ($option_patterns as $pattern) {
            // @phpstan-ignore-next-line
            $wpdb->query($wpdb->prepare(
                "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
                $pattern
            )); // phpcs:ignore
        }
    }

    /**
     * Clear all plugin cron schedules
     * 
     * @return void
     */
    public static function clearCronSchedules(): void {
        $cron_hooks = [
            'contact_inbox_process_queue',
            'contact_inbox_generate_alerts',
            'contact_inbox_crm_monitor_status',
            'contact_inbox_cleanup_cron',
            'contact_inbox_analytics_cron',
            'contact_inbox_verify_attachments_cron',
            'contact_inbox_optimize_database_cron',
            'contact_inbox_process_attachments_cron',
            'contact_inbox_sync_phone_fields_cron',
            'contact_inbox_cleanup_submission_attempts_cron',
            'contact_inbox_crm_sync_cron',
            'contact_inbox_classify_intents_cron',
            'contact_inbox_detect_plugin_conflicts_cron',
        ];

        foreach ($cron_hooks as $hook) {
            wp_clear_scheduled_hook($hook);
        }
    }

    /**
     * Clean up version indicators
     * 
     * @return void
     */
    private static function cleanupVersionIndicators(): void {
        delete_option('contact_inbox_free_version');
        delete_option('contact_inbox_pro_version');
        delete_option('contact_inbox_uninstall_handler_version');
    }

    /**
     * Mark that a safe uninstall occurred
      * Called before uninstall cleanup runs
     * 
     * @param bool $is_pro Current version being uninstalled
     * @return void
     */
    public static function beforeUninstall(bool $is_pro = false): void {
        if (!defined('ABSPATH')) {
            return;
        }

        // Update marker option indicating safe uninstall handler is in use
        update_option('contact_inbox_uninstall_handler_version', self::HANDLER_VERSION);

        // Verify other version before uninstall
        if (!self::checkOtherVersionInstalled($is_pro)) {
            // No other version, schedule full cleanup
            update_option('contact_inbox_scheduled_full_cleanup', time());
        } else {
            // Other version exists, schedule minimal cleanup
            update_option('contact_inbox_scheduled_minimal_cleanup', time());
        }
    }

    /**
     * Verify database integrity after uninstall
     * 
     * @return array<string, mixed> Status of verification
     */
    public static function verifyIntegrity(): array {
        global $wpdb;

        $missing_tables = [];
        $unexpected_tables = [];
        $shared_tables = TableDefinitions::getSharedTables();

        foreach ($shared_tables as $table) {
            $table_name = $wpdb->prefix . $table;
            $exists = $wpdb->get_var($wpdb->prepare(
                "SELECT 1 FROM information_schema.tables WHERE table_schema = %s AND table_name = %s",
                DB_NAME,
                $table_name
            )); // phpcs:ignore

            if (!$exists) {
                $missing_tables[] = $table;
            }
        }

        return [
            'status' => empty($missing_tables) ? 'ok' : 'warning',
            'missing_tables' => $missing_tables,
            'handler_version' => self::HANDLER_VERSION,
        ];
    }
}
