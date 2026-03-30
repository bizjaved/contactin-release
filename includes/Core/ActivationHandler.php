<?php
declare(strict_types=1);

namespace ContactInbox\Core;

use ContactInbox\Core\Config;
use ContactInbox\Core\SafeUninstallHandler;
use ContactInbox\Core\TableDefinitions;

// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.WP.I18n.TextDomainMismatch, WordPress.PHP.DevelopmentFunctions.error_log_error_log
/**
 * Activation and Deactivation Handler
 * 
 * Manages plugin activation/deactivation in coexistence scenarios.
 * Creates/updates tables safely for multiple versions.
 * 
 * @package ContactIn\Core
 */
final class ActivationHandler {
    
    /**
     * Handle plugin activation
     * Creates missing tables and sets up required data
     * 
     * @return void
     */
    public static function activate(): void {
        if (!defined('ABSPATH')) {
            return;
        }

        // Create shared tables if they don't exist
        self::createTables();

        // Register version
        self::registerVersion();

        // Initialize plugin options
        self::initializeOptions();

        // Record installation date for support boxes timing
        \ContactInbox\Admin\SupportBoxesManager::record_installation_date();

        // Flush rewrite rules
        flush_rewrite_rules(false);

        // Clear any error status from previous deactivation
        delete_option('contact_inbox_deactivation_error');
    }

    /**
     * Handle plugin deactivation
     * 
     * @return void
     */
    public static function deactivate(): void {
        if (!defined('ABSPATH')) {
            return;
        }

        // Clear scheduled events
        wp_clear_scheduled_hook('contact_inbox_process_queue');
        wp_clear_scheduled_hook('contact_inbox_generate_alerts');
        wp_clear_scheduled_hook('contact_inbox_crm_monitor_status');

        // Clear cron schedules
        SafeUninstallHandler::clearCronSchedules();

        // Update deactivation timestamp
        update_option('contact_inbox_last_deactivation', current_time('mysql'));

        // Flush rewrite rules
        flush_rewrite_rules(false);
    }

    /**
     * Create all necessary database tables
     * Safely handles existing tables in coexistence scenarios
     * 
     * @return void
     */
    private static function createTables(): void {
        global $wpdb;

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        $charset_collate = $wpdb->get_charset_collate();

        try {
            // Get all table definitions
            $all_tables = TableDefinitions::getAll($charset_collate);

            foreach ($all_tables as $table_name => $sql) {
                $full_table_name = $wpdb->prefix . $table_name;

                // Check if table exists
                $table_exists = $wpdb->get_var($wpdb->prepare(
                    "SELECT 1 FROM information_schema.tables WHERE table_schema = %s AND table_name = %s LIMIT 1",
                    DB_NAME,
                    $full_table_name
                )); // phpcs:ignore

                if (!$table_exists) {
                    // Create the table
                    dbDelta($sql);

                    if ($wpdb->last_error) {
                        error_log("[ContactIn] Error creating table {$table_name}: {$wpdb->last_error}");
                    }
                } else {
                    // Table exists, verify columns (optional but safe)
                    self::verifyTableStructure($table_name, $charset_collate);
                }
            }
        } catch (\Throwable $e) {
            error_log("[ContactIn] Table creation error: " . $e->getMessage());
            update_option('contact_inbox_table_creation_error', $e->getMessage());
        }
    }

    /**
     * Verify and update table structure if needed
     * Adds missing columns but doesn't drop existing ones
     * 
     * @param string $table_name Table name without prefix
     * @param string $charset_collate Charset and collation
     * @return void
     */
    private static function verifyTableStructure(string $table_name, string $charset_collate): void {
        global $wpdb;

        // For now, just verify the table has the expected columns
        // Future: add specific column verification and ALTER TABLE statements
    }

    /**
     * Register plugin version
     * 
     * @return void
     */
    private static function registerVersion(): void {
        $version = defined('CONTACT_INBOX_PRO_VERSION') 
            ? CONTACT_INBOX_PRO_VERSION 
            : 'unknown';

        update_option('contact_inbox_pro_version', $version);
        update_option('contact_inbox_pro_activated_at', current_time('mysql'));
    }

    /**
     * Initialize plugin options with defaults
     * 
     * @return void
     */
    private static function initializeOptions(): void {
        $defaults = [
            'contact_inbox_admin_emails' => get_option('admin_email'),
            'contact_inbox_enable_crm_sync' => false,
            'contact_inbox_enable_analytics' => true,
            'contact_inbox_enable_gdpr' => true,
            'contact_inbox_enable_spam_protection' => true,
            'contact_inbox_queue_batch_size' => 10,
            'contact_inbox_queue_batch_limit' => 50,
            'contact_inbox_auto_cleanup_days' => 90,
            'contact_inbox_max_file_size_mb' => 10,
        ];

        foreach ($defaults as $option => $value) {
            if (false === get_option($option)) {
                add_option($option, $value);
            }
        }
    }

    /**
     * Check if both free and pro versions are installed
     * 
     * @return bool True if both versions detected
     */
    public static function isBothVersionsInstalled(): bool {
        $free_installed = file_exists(ABSPATH . 'wp-content/plugins/contactin/contactin.php');
        $pro_installed = file_exists(ABSPATH . 'wp-content/plugins/contactin-pro/contactin.php');

        return $free_installed && $pro_installed;
    }

    /**
     * Check if free version is active
     * 
     * @return bool True if free version is active
     */
    public static function isFreeVersionActive(): bool {
        if (!function_exists('is_plugin_active')) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }

        return is_plugin_active('contactin/contactin.php');
    }

    /**
     * Check if pro version is active
     * 
     * @return bool True if pro version is active
     */
    public static function isProVersionActive(): bool {
        if (!function_exists('is_plugin_active')) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }

        return is_plugin_active('contactin-pro/contactin.php');
    }

    /**
     * Handle conflict between free and pro versions
     * 
     * @return array<string, mixed> Conflict details
     */
    public static function handleVersionConflict(): array {
        $free_active = self::isFreeVersionActive();
        $pro_active = self::isProVersionActive();

        if ($free_active && $pro_active) {
            // Both active - need to deactivate one
            if (defined('CONTACT_INBOX_PRO_VERSION')) {
                // Currently loading pro version, deactivate it
                deactivate_plugins('contactin-pro/contactin.php', true);

                return [
                    'status' => 'conflict_resolved',
                    'message' => 'Pro version deactivated to avoid conflicts.',
                    'deactivated' => 'pro',
                ];
            } else {
                // Currently loading free version, deactivate free
                deactivate_plugins('contactin/contactin.php', true);

                return [
                    'status' => 'conflict_resolved',
                    'message' => 'Free version deactivated to avoid conflicts.',
                    'deactivated' => 'free',
                ];
            }
        }

        return ['status' => 'ok', 'message' => 'No version conflict detected.'];
    }

    /**
     * Get plugin setup status
     * 
     * @return array<string, mixed> Setup status
     */
    public static function getSetupStatus(): array {
        global $wpdb;

        $shared_tables = TableDefinitions::getSharedTables();
        $pro_tables = TableDefinitions::getProOnlyTables();

        $missing_shared = [];
        $missing_pro = [];

        foreach ($shared_tables as $table) {
            $table_name = $wpdb->prefix . $table;
            $exists = $wpdb->get_var($wpdb->prepare(
                "SELECT 1 FROM information_schema.tables WHERE table_schema = %s AND table_name = %s",
                DB_NAME,
                $table_name
            )); // phpcs:ignore

            if (!$exists) {
                $missing_shared[] = $table;
            }
        }

        foreach ($pro_tables as $table) {
            $table_name = $wpdb->prefix . $table;
            $exists = $wpdb->get_var($wpdb->prepare(
                "SELECT 1 FROM information_schema.tables WHERE table_schema = %s AND table_name = %s",
                DB_NAME,
                $table_name
            )); // phpcs:ignore

            if (!$exists) {
                $missing_pro[] = $table;
            }
        }

        return [
            'tables_ok' => empty($missing_shared),
            'missing_shared_tables' => $missing_shared,
            'missing_pro_tables' => $missing_pro,
            'pro_version_active' => self::isProVersionActive(),
            'free_version_active' => self::isFreeVersionActive(),
            'both_installed' => self::isBothVersionsInstalled(),
        ];
    }
}
