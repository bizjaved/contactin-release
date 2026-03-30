<?php
/**
 * Admin Trait – Inbox Bulk Actions
 *
 * Handles bulk operations on multiple messages.
 * Responsibility: Bulk read, unread, delete.
 * All data operations go through CoreInbox.
 *
 * @package ContactIn\Admin\Traits
 * @since   1.0.0
 */

declare(strict_types=1);

namespace ContactInbox\Admin\Traits;

use ContactInbox\Core\Config;
use ContactInbox\Core\Inbox as CoreInbox;

// phpcs:disable WordPress.WP.I18n.NonSingularStringLiteralDomain, WordPress.Security.ValidatedSanitizedInput, WordPress.Security.NonceVerification, WordPress.WP.I18n.MissingTranslatorsComment, Generic.PHP.ForbiddenFunctions.Found, PluginCheck.CodeAnalysis.DiscouragedFunctions.load_plugin_textdomainFound, PluginCheck.CodeAnalysis.Heredoc.NotAllowed, PluginCheck.Security.DirectDB.UnescapedDBParameter, Squiz.PHP.DiscouragedFunctions.Discouraged, WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.NamingConventions.PrefixAllGlobals.DynamicHooknameFound, WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound, WordPress.PHP.DevelopmentFunctions.error_log_debug_backtrace, WordPress.WP.AlternativeFunctions.file_system_operations_fsockopen, WordPress.WP.AlternativeFunctions.file_system_operations_readfile, WordPress.WP.AlternativeFunctions.file_system_operations_rmdir, WordPress.WP.EnqueuedResourceParameters.MissingVersion, WordPress.WP.EnqueuedResources.NonEnqueuedScript, WordPress.WP.I18n.MissingArgDomain, WordPress.WP.I18n.UnorderedPlaceholdersPlural, WordPress.WP.I18n.UnorderedPlaceholdersSingle

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
            wp_send_json_error(['message' => __('Security check failed.',  'contactin')]);
        }

        // Security: capability
        if (!current_user_can(Config::CAPABILITY)) {
            wp_send_json_error(['message' => __('Permission denied.',  'contactin')]);
        }

        // Validate action and IDs
        $action = sanitize_key($_POST['bulk_action'] ?? '');
        $ids    = array_map('absint', (array) ($_POST['ids[]'] ?? $_POST['ids'] ?? []));

        if (empty($ids)) {
            wp_send_json_error(['message' => __('No messages selected.',  'contactin')]);
        }

        if (!in_array($action, ['read', 'unread', 'archive', 'unarchive', 'delete', 'spam', 'not_spam'], true)) {
            wp_send_json_error(['message' => __('Invalid bulk action.',  'contactin')]);
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
            wp_send_json_error(['message' => __('No changes made.',  'contactin')]);
        }

        // Build user-friendly message
        $verb = $action === 'delete'
            ? __('deleted',  'contactin')
            : ($action === 'spam' ? __('marked as spam',  'contactin')
                : ($action === 'not_spam' ? __('moved to inbox',  'contactin')
                    : ($action === 'archive' ? __('archived',  'contactin') 
                        : ($action === 'unarchive' ? __('unarchived',  'contactin') : __('updated',  'contactin')))));
        $message = sprintf(
            _n(
                '%d message %s.',
                '%d messages %s.',
                $count,
                'contactin'
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
            wp_send_json_error(['message' => __('Security check failed.',  'contactin')]);
        }

        if (!current_user_can(Config::CAPABILITY)) {
            wp_send_json_error(['message' => __('Permission denied.',  'contactin')]);
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
            wp_send_json_error(['message' => __('No spam messages to delete.',  'contactin')]);
        }

        $message = sprintf(
            _n(
                '%d spam message deleted.',
                '%d spam messages deleted.',
                $count,
                'contactin'
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
            wp_send_json_error(['message' => __('Security check failed.',  'contactin')]);
        }

        if (!current_user_can(Config::CAPABILITY)) {
            wp_send_json_error(['message' => __('Permission denied.',  'contactin')]);
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
            wp_send_json_error(['message' => __('No archived messages to delete.',  'contactin')]);
        }

        $message = sprintf(
            _n(
                '%d archived message deleted.',
                '%d archived messages deleted.',
                $count,
                'contactin'
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
     * Uses robust deletion with retry logic.
     */
    private function cleanup_attachments_for_ids(array $ids): array {
        if (empty($ids)) {
            return ['deleted' => 0, 'failed' => 0];
        }

        $message_repo = new \ContactInbox\Core\Repositories\MessageRepository();
        $all_paths = [];
        
        foreach ($ids as $id) {
            $message = $message_repo->get_by_id((int)$id);
            if (!$message) {
                continue;
            }

            $attachment_paths = \ContactInbox\Core\AttachmentHelper::extract_file_paths($message->attachment ?? null);
            $all_paths = array_merge($all_paths, $attachment_paths);
        }

        if (empty($all_paths)) {
            return ['deleted' => 0, 'failed' => 0];
        }

        // Use robust deletion with retry logic
        $deletion_results = \ContactInbox\Core\AttachmentHelper::delete_files_safely($all_paths, 3);
        
        if (!empty($deletion_results['failed'])) {
            \ContactInbox\Core\Logger::warning('Some attachment files could not be deleted during bulk deletion', [
                'message_ids' => $ids,
                'total_files' => count($all_paths),
                'failed_count' => count($deletion_results['failed']),
                'failed' => $deletion_results['failed'],
            ]);
        }

        return [
            'deleted' => count($deletion_results['deleted']),
            'failed' => count($deletion_results['failed']),
        ];
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
