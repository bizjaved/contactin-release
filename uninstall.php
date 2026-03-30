<?php
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals, WordPress.PHP.DevelopmentFunctions.error_log_error_log
/**
 * ContactIn – Uninstall
 * Full cleanup, GDPR-safe
 *
 * This file is executed by WordPress when the plugin is uninstalled.
 * Uses Lifecycle class to ensure consistent cleanup across all removal paths.
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
    exit;
}

// Define plugin constants required by Config class
$plugin_file = __DIR__ . '/contactin.php';
if ( ! file_exists( $plugin_file ) ) {
    $plugin_file = __DIR__ . '/contact-inbox.php';
}
if ( ! defined( 'CONTACTINBOX_FILE' ) ) {
    define( 'CONTACTINBOX_FILE', $plugin_file );
}
if ( ! defined( 'CONTACTINBOX_PATH' ) ) {
    define( 'CONTACTINBOX_PATH', plugin_dir_path( $plugin_file ) );
}
if ( ! defined( 'CONTACTINBOX_URL' ) ) {
    define( 'CONTACTINBOX_URL', plugin_dir_url( $plugin_file ) );
}
if ( ! defined( 'CONTACTINBOX_VERSION' ) ) {
    define( 'CONTACTINBOX_VERSION', '1.0' );
}
if ( ! defined( 'CONTACTINBOX_UPLOADS_PATH' ) && function_exists( 'wp_upload_dir' ) ) {
    $upload_dir = wp_upload_dir();
    define( 'CONTACTINBOX_UPLOADS_PATH', $upload_dir['basedir'] . '/contactin-attachments/' );
}
if ( ! defined( 'CONTACTINBOX_UPLOADS_URL' ) && function_exists( 'wp_upload_dir' ) ) {
    $upload_dir = wp_upload_dir();
    define( 'CONTACTINBOX_UPLOADS_URL', $upload_dir['baseurl'] . '/contactin-attachments/' );
}

// Load autoloader and dependencies
require_once __DIR__ . '/includes/Core/Autoloader.php';
require_once __DIR__ . '/includes/Lifecycle.php';

// Call the unified uninstall method with comprehensive error handling
try {
    \ContactInbox\Lifecycle::uninstall();
} catch ( \Throwable $e ) {
    // Log the error but don't let it prevent uninstallation
    error_log( '[ContactInbox] Fatal error during uninstall: ' . $e->getMessage() );
    error_log( '[ContactInbox] Some files may remain. See CLEANUP-README.md for manual cleanup instructions.' );
    
    // Still attempt to clear options manually as fallback
    global $wpdb;
    if ( function_exists( 'delete_option' ) ) {
        @delete_option( 'contactin_settings' );
        @delete_option( 'contactinbox_settings' );
        @delete_option( 'contactinbox_db_version' );
        @delete_option( 'contactin_intent_patterns' );
        @delete_option( 'contactin_test_tokens' );
    }
    
    // Attempt to delete upload directory even if other cleanup failed
    if ( defined( 'CONTACTINBOX_UPLOADS_PATH' ) && is_dir( CONTACTINBOX_UPLOADS_PATH ) ) {
        try {
            if ( ! function_exists( 'WP_Filesystem' ) ) {
                require_once ABSPATH . 'wp-admin/includes/file.php';
            }
            WP_Filesystem();
            global $wp_filesystem;

            // Try basic recursive deletion
            $iterator = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator( CONTACTINBOX_UPLOADS_PATH, \RecursiveDirectoryIterator::SKIP_DOTS ),
                \RecursiveIteratorIterator::CHILD_FIRST
            );
            foreach ( $iterator as $file ) {
                if ( $file->isDir() ) {
                    if ( is_object( $wp_filesystem ) && method_exists( $wp_filesystem, 'rmdir' ) ) {
                        $wp_filesystem->rmdir( $file->getPathname(), false );
                    }
                } else {
                    wp_delete_file( $file->getPathname() );
                }
            }
            if ( is_object( $wp_filesystem ) && method_exists( $wp_filesystem, 'rmdir' ) ) {
                $wp_filesystem->rmdir( CONTACTINBOX_UPLOADS_PATH, false );
            }
        } catch ( \Throwable $cleanup_error ) {
            error_log( '[ContactInbox] Fallback cleanup also failed: ' . $cleanup_error->getMessage() );
            error_log( '[ContactInbox] Run manual-cleanup.php to remove orphaned files.' );
        }
    }
}
