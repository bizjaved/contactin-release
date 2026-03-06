<?php
/**
 * Manual Cleanup Script for Contact Inbox Pro
 * 
 * Run this if uninstall leaves orphaned files.
 * Usage: wp eval-file manual-cleanup.php --allow-root
 */

if ( ! defined( 'ABSPATH' ) ) {
    // Load WordPress if not already loaded
    require_once dirname( dirname( dirname( dirname( __FILE__ ) ) ) ) . '/wp-load.php';
}

// Verify admin/CLI access
if ( ! ( defined( 'WP_CLI' ) && WP_CLI ) && ! current_user_can( 'manage_options' ) ) {
    die( 'Access denied. This script requires administrator privileges.' );
}

echo "\n=== Contact Inbox Pro - Manual Cleanup ===\n\n";

// Define paths
$upload_dir = wp_upload_dir();
$attachments_path = $upload_dir['basedir'] . '/contactin-attachments/';

echo "Checking for orphaned files...\n";
echo "Upload directory: {$attachments_path}\n\n";

// Check if directory exists
if ( ! is_dir( $attachments_path ) ) {
    echo "✅ No attachment directory found - nothing to clean up.\n\n";
    exit( 0 );
}

// Count files
$files = glob( $attachments_path . '*' );
$file_count = count( $files );

if ( $file_count === 0 ) {
    echo "✅ Attachment directory is empty.\n";
    if ( @rmdir( $attachments_path ) ) {
        echo "✅ Successfully removed empty directory.\n\n";
    } else {
        echo "⚠️  Could not remove empty directory (may require manual deletion).\n\n";
    }
    exit( 0 );
}

echo "Found {$file_count} orphaned file(s):\n";
foreach ( $files as $file ) {
    echo "  - " . basename( $file ) . " (" . size_format( filesize( $file ) ) . ")\n";
}

// Calculate total size
$total_size = array_sum( array_map( 'filesize', array_filter( $files, 'is_file' ) ) );
echo "\nTotal size: " . size_format( $total_size ) . "\n\n";

// Confirm deletion
if ( defined( 'WP_CLI' ) && WP_CLI ) {
    echo "Proceeding with deletion...\n\n";
    $confirmed = true;
} else {
    $confirmed = isset( $_GET['confirm'] ) && $_GET['confirm'] === 'yes';
    if ( ! $confirmed ) {
        echo "⚠️  To delete these files, add ?confirm=yes to the URL\n\n";
        exit( 0 );
    }
}

if ( $confirmed ) {
    $deleted = 0;
    $failed = 0;
    
    foreach ( $files as $file ) {
        if ( is_file( $file ) ) {
            if ( @unlink( $file ) ) {
                $deleted++;
                echo "✅ Deleted: " . basename( $file ) . "\n";
            } else {
                $failed++;
                echo "❌ Failed to delete: " . basename( $file ) . " (check permissions)\n";
            }
        }
    }
    
    echo "\n";
    echo "Deleted: {$deleted} file(s)\n";
    
    if ( $failed > 0 ) {
        echo "Failed: {$failed} file(s)\n";
        echo "\n⚠️  Some files could not be deleted. You may need to:\n";
        echo "   1. Check file permissions (should be writable by web server)\n";
        echo "   2. Delete manually via SSH/FTP\n";
        echo "   3. Contact your hosting provider\n\n";
        exit( 1 );
    }
    
    // Try to remove directory
    if ( @rmdir( $attachments_path ) ) {
        echo "✅ Successfully removed attachment directory.\n\n";
        exit( 0 );
    } else {
        echo "⚠️  Could not remove directory (may still contain hidden files).\n\n";
        exit( 1 );
    }
}
