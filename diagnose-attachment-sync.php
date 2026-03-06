<?php
/**
 * Attachment Sync Diagnostic Script
 * 
 * Traces attachment through the full pipeline:
 * Database save → CRM payload → Queue processing → CRM connector
 */

// This can be run from WP-CLI or WordPress shell
if (!function_exists('get_option')) {
    define('WP_USE_THEMES', false);
    require_once(dirname(__FILE__) . '/../../../wp-load.php');
}

use ContactInbox\Core\Logger;
use ContactInbox\Core\AttachmentHelper;
use ContactInbox\Core\CRMQueuePayloadBuilder;

echo "\n=== ATTACHMENT SYNC DIAGNOSTIC ===\n\n";

// Find the most recent message with attachment
global $wpdb;
$table_messages = $wpdb->prefix . 'cibox_messages';

$recent_msg = $wpdb->get_row(
    "SELECT * FROM {$table_messages} WHERE attachment IS NOT NULL AND attachment != '' ORDER BY submitted_at DESC LIMIT 1"
);

if (!$recent_msg) {
    echo "❌ NO MESSAGES WITH ATTACHMENTS FOUND\n";
    echo "   Create a form submission with a file attachment first.\n";
    exit(1);
}

echo "✓ Found message with attachment\n";
echo "  Message ID: {$recent_msg->id}\n";
echo "  Email: {$recent_msg->email}\n";
echo "  Submitted: {$recent_msg->submitted_at}\n\n";

// STEP 1: Check database storage
echo "STEP 1: Database Storage\n";
echo "─────────────────────────\n";
echo "Attachment field length: " . strlen($recent_msg->attachment) . " bytes\n";
echo "First 200 chars: " . substr($recent_msg->attachment, 0, 200) . "\n";

// Try to parse as JSON
$parsed = json_decode($recent_msg->attachment, true);
if (json_last_error() === JSON_ERROR_NONE) {
    echo "✓ Valid JSON\n";
    echo "  Keys: " . implode(', ', array_keys($parsed)) . "\n";
    if (isset($parsed['path'])) {
        echo "  Path: {$parsed['path']}\n";
    }
    if (isset($parsed['name'])) {
        echo "  Name: {$parsed['name']}\n";
    }
} else {
    echo "❌ NOT valid JSON: " . json_last_error_msg() . "\n";
    echo "   Attachment: " . $recent_msg->attachment . "\n";
}
echo "\n";

// STEP 2: Test AttachmentHelper extraction
echo "STEP 2: Attachment Extraction\n";
echo "─────────────────────────────\n";
$extracted = AttachmentHelper::extract_file_paths($recent_msg->attachment);
echo "Extracted paths count: " . count($extracted) . "\n";

if (!empty($extracted)) {
    foreach ($extracted as $idx => $path) {
        echo "  Path {$idx}: {$path}\n";
        echo "    Exists: " . (file_exists($path) ? 'YES' : 'NO') . "\n";
        echo "    Readable: " . (is_readable($path) ? 'YES' : 'NO') . "\n";
        echo "    Size: " . (file_exists($path) ? filesize($path) : '0') . " bytes\n";
    }
} else {
    echo "❌ NO PATHS EXTRACTED\n";
    
    // Debug the parse_attachment_data function
    echo "\n  Debugging parse_attachment_data():\n";
    $debug_parsed = AttachmentHelper::parse_attachment_data($recent_msg->attachment);
    echo "  Parsed result:\n";
    echo "  " . json_encode($debug_parsed, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
    
    if (isset($debug_parsed['path'])) {
        echo "  Trying url_to_path() on: {$debug_parsed['path']}\n";
        $converted = AttachmentHelper::url_to_path($debug_parsed['path']);
        echo "  Result: " . ($converted ?: 'NULL') . "\n";
        if ($converted) {
            echo "  File exists at converted path: " . (file_exists($converted) ? 'YES' : 'NO') . "\n";
        }
    }
}
echo "\n";

// STEP 3: Test CRM payload building
echo "STEP 3: CRM Payload Building\n";
echo "─────────────────────────────\n";
try {
    $payload = CRMQueuePayloadBuilder::build_from_message_id($recent_msg->id);
    
    if (!$payload) {
        echo "❌ Payload build returned NULL\n";
    } else {
        echo "✓ Payload built successfully\n";
        if (isset($payload['attachment'])) {
            echo "  Has attachment: YES\n";
            if (is_array($payload['attachment'])) {
                echo "  Attachment type: ARRAY (" . count($payload['attachment']) . " files)\n";
                foreach ($payload['attachment'] as $idx => $att) {
                    echo "    {$idx}: {$att}\n";
                }
            } else {
                echo "  Attachment type: STRING\n";
                echo "  Value: {$payload['attachment']}\n";
            }
        } else {
            echo "❌ Payload has NO attachment field\n";
        }
    }
} catch (Exception $e) {
    echo "❌ Error building payload: " . $e->getMessage() . "\n";
}
echo "\n";

// STEP 4: Check queue status
echo "STEP 4: Queue Status\n";
echo "───────────────────\n";
$table_queue = $wpdb->prefix . 'cibox_queue';
$queue_items = $wpdb->get_results(
    $wpdb->prepare(
        "SELECT * FROM {$table_queue} WHERE object_id = %d ORDER BY created_at DESC LIMIT 10",
        $recent_msg->id
    )
);

if (empty($queue_items)) {
    echo "❌ NO QUEUE ITEMS FOR THIS MESSAGE\n";
} else {
    echo "✓ Found " . count($queue_items) . " queue items\n";
    foreach ($queue_items as $item) {
        echo "\n  Queue ID: {$item->id}\n";
        echo "  Type: {$item->object_type}\n";
        echo "  Status: {$item->status}\n";
        echo "  Created: {$item->created_at}\n";
        
        if (!empty($item->error_message)) {
            echo "  ❌ Error: {$item->error_message}\n";
        }
        
        // Show a snippet of the payload
        if (!empty($item->payload_data)) {
            $payload_data = json_decode($item->payload_data, true);
            if (isset($payload_data['attachment'])) {
                echo "  Has attachment in payload: YES\n";
            } else {
                echo "  ❌ NO attachment in payload\n";
            }
        }
    }
}
echo "\n";

// STEP 5: Check CRM log
echo "STEP 5: CRM Sync Log\n";
echo "────────────────────\n";
$table_crm_log = $wpdb->prefix . 'cibox_crm_log';
$crm_logs = $wpdb->get_results(
    $wpdb->prepare(
        "SELECT * FROM {$table_crm_log} WHERE message_id = %d ORDER BY created_at DESC LIMIT 5",
        $recent_msg->id
    )
);

if (empty($crm_logs)) {
    echo "ℹ NO CRM LOG ENTRIES (Message may not have been synced yet)\n";
} else {
    echo "✓ Found " . count($crm_logs) . " CRM log entries\n";
    foreach ($crm_logs as $log) {
        echo "\n  Log ID: {$log->id}\n";
        echo "  Status: {$log->status}\n";
        echo "  Created: {$log->created_at}\n";
        
        if (!empty($log->error_message)) {
            echo "  ❌ Error: {$log->error_message}\n";
        }
        
        if (!empty($log->response_data)) {
            $response = json_decode($log->response_data, true);
            if (isset($response['files_queued'])) {
                echo "  Files queued: {$response['files_queued']}\n";
            }
            if (isset($response['queued_filenames'])) {
                echo "  Filenames: " . implode(', ', (array)$response['queued_filenames']) . "\n";
            }
        }
    }
}
echo "\n";

// STEP 6: Summary and recommendations
echo "SUMMARY & RECOMMENDATIONS\n";
echo "─────────────────────────\n";

$issues = [];

if (!$parsed || json_last_error() !== JSON_ERROR_NONE) {
    $issues[] = "❌ Attachment not stored as valid JSON in database";
}

if (empty($extracted)) {
    $issues[] = "❌ Attachment extraction failed - paths could not be extracted";
}

if ($payload && !isset($payload['attachment'])) {
    $issues[] = "❌ CRM payload does not include attachment field";
}

if (empty($queue_items)) {
    $issues[] = "❌ No queue items found for message";
}

if (empty($issues)) {
    echo "✓ All checks passed! Attachment sync should be working.\n";
    echo "  Check Salesforce to verify attachment was uploaded.\n";
} else {
    echo "Issues found:\n";
    foreach ($issues as $issue) {
        echo "  {$issue}\n";
    }
    echo "\n";
    echo "Troubleshooting:\n";
    echo "  1. Check wp-content/debug.log for warnings\n";
    echo "  2. Verify attachment file exists in " . CONTACTINBOX_UPLOADS_PATH . "\n";
    echo "  3. Review ATTACHMENT_SYNC_BUG_FIX.md for detailed analysis\n";
}
echo "\n";
?>
