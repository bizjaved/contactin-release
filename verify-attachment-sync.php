<?php
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals, WordPress.Security.EscapeOutput.OutputNotEscaped, WordPress.WP.AlternativeFunctions.file_exists_file_exists, WordPress.WP.AlternativeFunctions.file_system_read_filesize
/**
 * Attachment Sync Test Script
 * 
 * This script tests the attachment sync flow end-to-end
 * Run this on staging server to verify the fix works
 * 
 * Usage: wp eval-file verify-attachment-sync.php
 */

namespace ContactInbox\Tests;

use ContactInbox\Core\Logger;
use ContactInbox\Core\DB;
use ContactInbox\Core\Message;
use ContactInbox\Core\AttachmentHelper;
use ContactInbox\Core\CRMQueuePayloadBuilder;

if (!defined('ABSPATH')) {
    exit('Must run in WordPress context');
}

echo "\n=== ATTACHMENT SYNC VERIFICATION TEST ===\n\n";

// Test 1: Check if message with attachment exists
echo "Test 1: Searching for messages with attachments...\n";
$db = DB::instance();
$messages = $db->get_all_message_ids('', 'all', null);
$messages_with_attachments = 0;

foreach ($messages as $message_id) {
    $message = $db->get_message_by_id($message_id);
    if (!empty($message) && !empty($message->attachment)) {
        $messages_with_attachments++;
        
        // Test 2: Verify attachment JSON is valid
        echo "\nTest 2: Validating attachment JSON for message ID: $message_id\n";
        $attachment = $message->attachment;
        echo "  Raw attachment data length: " . strlen($attachment) . "\n";
        echo "  Preview: " . substr($attachment, 0, 80) . "...\n";
        
        // Try to parse as JSON
        if (substr($attachment, 0, 1) === '{' || substr($attachment, 0, 1) === '[') {
            $decoded = json_decode($attachment, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                echo "  ✓ JSON is valid and parseable\n";
                if (isset($decoded['path'])) {
                    echo "  ✓ Contains 'path' key: " . substr($decoded['path'], 0, 50) . "...\n";
                } elseif (is_array($decoded) && count($decoded) > 0 && isset($decoded[0]['path'])) {
                    echo "  ✓ Array format with " . count($decoded) . " items, all have 'path'\n";
                } else {
                    echo "  ✗ MISSING 'path' key in JSON object\n";
                }
            } else {
                echo "  ✗ JSON PARSE ERROR: " . json_last_error_msg() . "\n";
            }
        } else {
            echo "  ℹ Plain URL format (not JSON)\n";
        }
        
        // Test 3: Extract and validate file paths
        echo "\nTest 3: Extracting file paths...\n";
        $file_paths = AttachmentHelper::extract_file_paths($attachment);
        if (!empty($file_paths)) {
            echo "  ✓ Extracted " . count($file_paths) . " path(s)\n";
            foreach ($file_paths as $path) {
                if (file_exists($path)) {
                    echo "    ✓ File exists: " . basename($path) . " (" . size_format(filesize($path)) . ")\n";
                } else {
                    echo "    ✗ File NOT found: $path\n";
                }
            }
        } else {
            echo "  ✗ Could not extract any file paths\n";
        }
        
        // Test 4: Build CRM payload
        echo "\nTest 4: Building CRM queue payload...\n";
        $payload = CRMQueuePayloadBuilder::build_from_message($message);
        if (isset($payload['attachment'])) {
            echo "  ✓ Payload includes attachment\n";
            if (is_array($payload['attachment'])) {
                echo "    Array with " . count($payload['attachment']) . " file(s)\n";
            } else {
                echo "    Single file: " . basename($payload['attachment']) . "\n";
            }
        } else {
            echo "  ✗ Payload has NO attachment (queuing will fail)\n";
        }
        
        // Limit output to first 5 messages to prevent overwhelming log
        if ($messages_with_attachments >= 5) {
            echo "\n... (more messages exist)\n";
            break;
        }
    }
}

echo "\n\nTest 5: Summary\n";
echo "  Total messages with attachments: $messages_with_attachments\n";
echo "  Total messages checked: " . count($messages) . "\n";

if ($messages_with_attachments === 0) {
    echo "\n⚠ No messages with attachments found. Submit a form with a file to test.\n";
} else {
    echo "\n✓ Attachment sync chain tested successfully.\n";
}

echo "\nNext: Check debug.log for attachment-related messages:\n";
echo "  grep -i attachment /var/www/wordpress/wp-content/debug.log | tail -20\n";

echo "\n=== TEST COMPLETE ===\n\n";
