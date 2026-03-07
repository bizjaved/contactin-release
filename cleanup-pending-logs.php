<?php
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals, WordPress.Security.EscapeOutput.OutputNotEscaped, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared
/**
 * Cleanup Script: Remove Orphaned Pending CRM Logs
 * 
 * This script removes "pending" CRM log entries that were created before
 * the fix was implemented. These are duplicate entries that were never
 * updated to their final status (delivered/rejected/server_error).
 * 
 * Usage:
 *   wp eval-file cleanup-pending-logs.php
 * 
 * Or from CLI:
 *   cd /var/www/html/wpdev/wp-content/plugins/contact-inbox-pro
 *   wp eval-file cleanup-pending-logs.php
 */

if (!defined('ABSPATH')) {
    // Running via WP-CLI
    if (!defined('WP_CLI') || !WP_CLI) {
        die("This script must be run via WP-CLI: wp eval-file cleanup-pending-logs.php\n");
    }
}

global $wpdb;

$table = $wpdb->prefix . 'contactinbox_crm_log';

// Check if table exists
$table_exists = $wpdb->get_var(
    $wpdb->prepare('SHOW TABLES LIKE %s', $table)
);
if (!$table_exists) {
    WP_CLI::error("Table $table does not exist.");
    exit(1);
}

WP_CLI::log("Cleaning up orphaned 'pending' CRM log entries...");
WP_CLI::log("");

// Get count of pending entries
$pending_count = $wpdb->get_var(
    $wpdb->prepare("SELECT COUNT(*) FROM %i WHERE status = %s", $table, 'pending')
);
WP_CLI::log("Found $pending_count 'pending' entries.");

if ($pending_count == 0) {
    WP_CLI::success("No pending entries to clean up!");
    exit(0);
}

// Show sample of pending entries before deletion
WP_CLI::log("");
WP_CLI::log("Sample of entries to be deleted:");
$samples = $wpdb->get_results(
    $wpdb->prepare(
        "SELECT id, message_id, operation, created_at 
         FROM %i 
         WHERE status = %s 
         ORDER BY created_at DESC 
         LIMIT 10",
        $table,
        'pending'
    ),
    ARRAY_A
);

foreach ($samples as $sample) {
    WP_CLI::log(sprintf(
        "  - ID %d | Message %d | Operation: %s | Created: %s",
        $sample['id'],
        $sample['message_id'],
        $sample['operation'],
        $sample['created_at']
    ));
}

if ($pending_count > 10) {
    WP_CLI::log("  ... and " . ($pending_count - 10) . " more");
}

WP_CLI::log("");
WP_CLI::confirm("Delete these orphaned 'pending' entries?", ['default' => 'Y']);

// Delete pending entries
    $deleted = $wpdb->query(
        $wpdb->prepare('DELETE FROM %i WHERE status = %s', $table, 'pending')
    );

if ($deleted === false) {
    WP_CLI::error("Failed to delete pending entries: " . $wpdb->last_error);
    exit(1);
}

WP_CLI::success("Deleted $deleted orphaned 'pending' entries.");
WP_CLI::log("");

// Show updated stats
    $remaining = $wpdb->get_var(
        $wpdb->prepare('SELECT COUNT(*) FROM %i WHERE status = %s', $table, 'pending')
    );
WP_CLI::log("Remaining 'pending' entries: $remaining");

    $total = $wpdb->get_var(
        $wpdb->prepare('SELECT COUNT(*) FROM %i', $table)
    );
WP_CLI::log("Total CRM log entries: $total");

WP_CLI::success("Cleanup complete!");
