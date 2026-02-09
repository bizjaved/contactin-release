<?php
/**
 * Admin Trait – Inbox Bulk Actions
 *
 * Handles bulk operations on multiple messages.
 * Responsibility: Bulk read, unread, delete.
 * All data operations go through CoreInbox.
 *
 * @package ContactInbox\Admin\Traits
 * @since   1.0.0
 */

declare(strict_types=1);

namespace ContactInbox\Admin\Traits;

use ContactInbox\Core\Config;
use ContactInbox\Core\Inbox as CoreInbox;

if (!defined('ABSPATH')) {
    exit;
}

trait InboxBulkActions {

    /**
     * AJAX handler: Perform bulk action on multiple messages.
     * Actions: read, unread, delete.
     * All modifications via CoreInbox (which uses DB class).
     */
    public function ci_bulk_action(): void {
        // Prevent PHP notices from breaking JSON output in AJAX responses
        $this->disable_error_output();

        // Security: nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], Config::INBOX_NONCE_ACTION)) {
            wp_send_json_error(['message' => __('Security check failed.', Config::TEXTDOMAIN)]);
        }

        // Security: capability
        if (!current_user_can(Config::CAPABILITY)) {
            wp_send_json_error(['message' => __('Permission denied.', Config::TEXTDOMAIN)]);
        }

        // Validate action and IDs
        $action = sanitize_key($_POST['bulk_action'] ?? '');
        $ids    = array_map('absint', (array) ($_POST['ids[]'] ?? $_POST['ids'] ?? []));

        if (empty($ids)) {
            wp_send_json_error(['message' => __('No messages selected.', Config::TEXTDOMAIN)]);
        }

        if (!in_array($action, ['read', 'unread', 'archive', 'unarchive', 'delete', 'spam', 'not_spam'], true)) {
            wp_send_json_error(['message' => __('Invalid bulk action.', Config::TEXTDOMAIN)]);
        }

        // For delete action, clean up attachments before deletion
        if ($action === 'delete') {
            $this->cleanup_attachments_for_ids($ids);
        }

        // Execute action via CoreInbox (not direct DB)
        $count = 0;

        if ($action === 'delete') {
            $count = CoreInbox::instance()->bulk_delete($ids);
        } elseif ($action === 'not_spam') {
            $count = CoreInbox::instance()->bulk_clear_spam($ids);
        } elseif ($action === 'spam') {
            $count = CoreInbox::instance()->bulk_mark_spam($ids);
        } else {
            if ($action === 'archive' || $action === 'unarchive') {
                $count = CoreInbox::instance()->bulk_update_archive($ids, $action === 'archive');
            } else {
                $new_status = $action === 'read' ? Config::STATUS_READ : Config::STATUS_UNREAD;
                $count = CoreInbox::instance()->bulk_update_status($ids, $new_status);
            }
        }

        if (!$count) {
            wp_send_json_error(['message' => __('No changes made.', Config::TEXTDOMAIN)]);
        }

        // Build user-friendly message
        $verb = $action === 'delete'
            ? __('deleted', Config::TEXTDOMAIN)
            : ($action === 'spam' ? __('marked as spam', Config::TEXTDOMAIN)
                : ($action === 'not_spam' ? __('moved to inbox', Config::TEXTDOMAIN)
                    : ($action === 'archive' ? __('archived', Config::TEXTDOMAIN) 
                        : ($action === 'unarchive' ? __('unarchived', Config::TEXTDOMAIN) : __('updated', Config::TEXTDOMAIN)))));
        $message = sprintf(
            _n(
                '%d message %s.',
                '%d messages %s.',
                $count,
                Config::TEXTDOMAIN
            ),
            $count,
            $verb
        );

        wp_send_json_success([
            'message' => $message,
            'count'   => $count,
            'action'  => $action,
        ]);
    }

    /**
     * AJAX handler: Clear all spam messages.
     */
    public function ci_clear_spam(): void {
        $this->disable_error_output();

        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], Config::INBOX_NONCE_ACTION)) {
            wp_send_json_error(['message' => __('Security check failed.', Config::TEXTDOMAIN)]);
        }

        if (!current_user_can(Config::CAPABILITY)) {
            wp_send_json_error(['message' => __('Permission denied.', Config::TEXTDOMAIN)]);
        }

        // Get spam message IDs first for attachment cleanup
        $message_repo = new \ContactInbox\Core\Repositories\MessageRepository();
        $spam_ids = $message_repo->get_spam_ids();
        
        // Clean up attachments before deletion
        if (!empty($spam_ids)) {
            $this->cleanup_attachments_for_ids($spam_ids);
        }

        $count = CoreInbox::instance()->delete_all_spam();
        if (!$count) {
            wp_send_json_error(['message' => __('No spam messages to delete.', Config::TEXTDOMAIN)]);
        }

        $message = sprintf(
            _n(
                '%d spam message deleted.',
                '%d spam messages deleted.',
                $count,
                Config::TEXTDOMAIN
            ),
            $count
        );

        wp_send_json_success([
            'message' => $message,
            'count'   => $count,
            'action'  => 'clear_spam',
        ]);
    }

    /**
     * AJAX handler: Clear all archived messages.
     */
    public function ci_clear_archives(): void {
        $this->disable_error_output();

        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], Config::INBOX_NONCE_ACTION)) {
            wp_send_json_error(['message' => __('Security check failed.', Config::TEXTDOMAIN)]);
        }

        if (!current_user_can(Config::CAPABILITY)) {
            wp_send_json_error(['message' => __('Permission denied.', Config::TEXTDOMAIN)]);
        }

        // Get archived message IDs first for attachment cleanup
        $message_repo = new \ContactInbox\Core\Repositories\MessageRepository();
        $archived_ids = $message_repo->get_archived_ids();
        
        // Clean up attachments before deletion
        if (!empty($archived_ids)) {
            $this->cleanup_attachments_for_ids($archived_ids);
        }

        $count = CoreInbox::instance()->delete_all_archived();
        if (!$count) {
            wp_send_json_error(['message' => __('No archived messages to delete.', Config::TEXTDOMAIN)]);
        }

        $message = sprintf(
            _n(
                '%d archived message deleted.',
                '%d archived messages deleted.',
                $count,
                Config::TEXTDOMAIN
            ),
            $count
        );

        wp_send_json_success([
            'message' => $message,
            'count'   => $count,
            'action'  => 'clear_archives',
        ]);
    }

    /**
     * Clean up attachment files for a list of message IDs.
     * Called before bulk delete to prevent orphaned files.
     */
    private function cleanup_attachments_for_ids(array $ids): void {
        if (empty($ids)) {
            return;
        }

        $message_repo = new \ContactInbox\Core\Repositories\MessageRepository();
        foreach ($ids as $id) {
            $message = $message_repo->get_by_id((int)$id);
            if (!$message) {
                continue;
            }

            $attachment_paths = \ContactInbox\Core\AttachmentHelper::extract_file_paths($message->attachment ?? null);
            foreach ($attachment_paths as $path) {
                if (file_exists($path)) {
                    @unlink($path);
                }
            }
        }
    }

    /**
     * Disable error display to keep AJAX JSON responses clean.
     */
    private function disable_error_output(): void {
        if (function_exists('ini_set')) {
            ini_set('display_errors', '0');
        }
    }
}
