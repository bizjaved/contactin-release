<?php
/**
 * Admin Trait – Inbox Message Handler
 *
 * Handles single-message operations via AJAX.
 * Responsibility: Message view, delete, toggle status, download attachments.
 * All data operations go through CoreInbox (which uses DB class).
 *
 * @package ContactInbox\Admin\Traits
 * @since   1.0.0
 */

declare(strict_types=1);

namespace ContactInbox\Admin\Traits;

use ContactInbox\Core\Config;
use ContactInbox\Core\Inbox as CoreInbox;
use ContactInbox\Core\Repositories\EmailLogRepository;

if (!defined('ABSPATH')) {
    exit;
}

trait InboxMessageHandler {

    /**
     * Get MessageRepository instance (lazy initialization) - specific to message handler
     */
    private function get_handler_message_repo(): \ContactInbox\Core\Repositories\MessageRepository {
        static $repo = null;
        if ($repo === null) {
            $repo = new \ContactInbox\Core\Repositories\MessageRepository();
        }
        return $repo;
    }

    /**
     * Get EmailLogRepository instance (lazy initialization)
     */
    private function get_email_log_repo(): \ContactInbox\Core\Repositories\EmailLogRepository {
        static $repo = null;
        if ($repo === null) {
            $repo = new \ContactInbox\Core\Repositories\EmailLogRepository();
        }
        return $repo;
    }

    /**
     * AJAX handler: View single message with navigation.
     * Fetches message, builds modal data, passes to ModalBuilder trait.
     */
    public function ci_view_message(): void {
        // Prevent PHP notices from breaking JSON output in AJAX responses
        $this->disable_error_output();

        $nonce = isset($_POST['nonce']) ? sanitize_text_field(wp_unslash($_POST['nonce'])) : '';

        // Security: nonce
        if ('' === $nonce || !wp_verify_nonce($nonce, Config::INBOX_NONCE_ACTION)) {
            wp_send_json_error(['message' => __('Security check failed.', 'contact-inbox')]);
        }

        // Security: capability
        if (!current_user_can(Config::CAPABILITY)) {
            wp_send_json_error(['message' => __('Permission denied.', 'contact-inbox')]);
        }

        // Validate input
        $id        = isset($_POST['id']) ? absint(wp_unslash($_POST['id'])) : 0;
        $direction = isset($_POST['direction']) ? sanitize_key(wp_unslash($_POST['direction'])) : '';

        if (!$id) {
            wp_send_json_error(['message' => __('Invalid message ID.', 'contact-inbox')]);
        }

        // Validate direction if provided
        if (!empty($direction) && !in_array($direction, ['next', 'prev'], true)) {
            wp_send_json_error(['message' => __('Invalid direction.', 'contact-inbox')]);
        }

        // Sanitize filters for navigation context
        $search = isset($_POST['s'])
            ? sanitize_text_field(wp_unslash($_POST['s']))
            : (isset($_GET['s']) ? sanitize_text_field(wp_unslash($_GET['s'])) : '');
        $status = isset($_POST['status'])
            ? sanitize_key(wp_unslash($_POST['status']))
            : (isset($_GET['status']) ? sanitize_key(wp_unslash($_GET['status'])) : 'all');

        // Validate status against allowed values
        $allowed_statuses = ['all', Config::STATUS_READ, Config::STATUS_UNREAD, Config::STATUS_SPAM, Config::STATUS_ARCHIVED];
        if (!in_array($status, $allowed_statuses, true)) {
            $status = 'all';
        }

        // Fetch all message IDs for navigation (via CoreInbox, not direct DB)
        $all_ids = CoreInbox::instance()->get_all_message_ids($search, $status);
        $all_ids = array_values(array_map('intval', (array) $all_ids));

        // Fallback: single message if no list
        if (empty($all_ids)) {
            $message = CoreInbox::instance()->get_message_by_id($id);
            if (!$message) {
                wp_send_json_error(['message' => __('Message not found.', 'contact-inbox')]);
            }
            $data = $this->build_message_modal_data($message, 0, 1, false, false, $search, $status);
            $html = $this->render_modal_html($data);
            wp_send_json_success($this->build_modal_response($data, $html));
        }

        // Find current index
        $current_index = array_search($id, $all_ids, true);
        $total         = count($all_ids);

        if ($current_index === false) {
            $message = CoreInbox::instance()->get_message_by_id($id);
            if (!$message) {
                wp_send_json_error(['message' => __('Message not found.', 'contact-inbox')]);
            }
            $data = $this->build_message_modal_data($message, 0, 1, false, false, $search, $status);
            $html = $this->render_modal_html($data);
            wp_send_json_success($this->build_modal_response($data, $html));
        }

        // Navigate based on direction
        if ($direction === 'next' && $current_index < $total - 1) {
            $current_index++;
        } elseif ($direction === 'prev' && $current_index > 0) {
            $current_index--;
        }

        $has_prev = ($current_index > 0);
        $has_next = ($current_index < ($total - 1));

        // Fetch target message
        $target_id = (int) $all_ids[$current_index];
        $message   = CoreInbox::instance()->get_message_by_id($target_id);

        if (!$message) {
            wp_send_json_error(['message' => __('Message not found.', 'contact-inbox')]);
        }

        $this->attach_statuses($message);

        // Build response
        $data = $this->build_message_modal_data(
            $message,
            $current_index,
            $total,
            $has_prev,
            $has_next,
            $search,
            $status
        );
        $html = $this->render_modal_html($data);

        wp_send_json_success($this->build_modal_response($data, $html));
    }

    /**
     * AJAX handler: Delete single message.
     * All deletion via CoreInbox (which manages file cleanup + DB).
     */
    public function ci_delete_message(): void {
        // Prevent PHP notices from breaking JSON output in AJAX responses
        $this->disable_error_output();

        $nonce = isset($_POST['nonce']) ? sanitize_text_field(wp_unslash($_POST['nonce'])) : '';

        // Security: nonce
        if ('' === $nonce || !wp_verify_nonce($nonce, Config::INBOX_NONCE_ACTION)) {
            wp_send_json_error(['message' => __('Security check failed.', 'contact-inbox')]);
        }

        // Security: capability
        if (!current_user_can(Config::CAPABILITY)) {
            wp_send_json_error(['message' => __('Permission denied.', 'contact-inbox')]);
        }

        // Validate input
        $id = isset($_POST['id']) ? absint(wp_unslash($_POST['id'])) : 0;
        if (!$id) {
            wp_send_json_error(['message' => __('Invalid message ID.', 'contact-inbox')]);
        }

        // Fetch message
        $message = CoreInbox::instance()->get_message_by_id($id);
        if (!$message) {
            wp_send_json_error(['message' => __('Message not found.', 'contact-inbox')]);
        }

        $this->attach_statuses($message);

        // Clean up attachment file if exists (before DB deletion)
        $attachment_paths = \ContactInbox\Core\AttachmentHelper::extract_file_paths($message->attachment ?? null);
        foreach ($attachment_paths as $path) {
            if (file_exists($path)) {
                wp_delete_file($path);
            }
        }

        // Delete via CoreInbox (all DB operations here)
        $deleted = CoreInbox::instance()->delete_message($id);
        if (!$deleted) {
            wp_send_json_error(['message' => __('Failed to delete message.', 'contact-inbox')]);
        }

        wp_send_json_success(['message' => __('Message deleted permanently.', 'contact-inbox')]);
    }

    /**
     * AJAX handler: Toggle message status (read ↔ unread).
     */
    public function ci_toggle_status(): void {
        // Prevent PHP notices from breaking JSON output in AJAX responses
        $this->disable_error_output();

        $nonce = isset($_POST['nonce']) ? sanitize_text_field(wp_unslash($_POST['nonce'])) : '';

        // Security: nonce
        if ('' === $nonce || !wp_verify_nonce($nonce, Config::INBOX_NONCE_ACTION)) {
            wp_send_json_error(['message' => __('Security check failed.', 'contact-inbox')]);
        }

        // Security: capability
        if (!current_user_can(Config::CAPABILITY)) {
            wp_send_json_error(['message' => __('Permission denied.', 'contact-inbox')]);
        }

        // Validate input
        $id = isset($_POST['id']) ? absint(wp_unslash($_POST['id'])) : 0;
        if (!$id) {
            wp_send_json_error(['message' => __('Invalid message ID.', 'contact-inbox')]);
        }

        // Toggle via CoreInbox
        $new_status = CoreInbox::instance()->toggle_status($id);
        if (!$new_status) {
            wp_send_json_error(['message' => __('Failed to update status.', 'contact-inbox')]);
        }

        wp_send_json_success(['new_status' => $new_status]);
    }

    /**
     * AJAX handler: Toggle archive status of a message.
     */
    public function ci_toggle_archive(): void {
        // Prevent PHP notices from breaking JSON output in AJAX responses
        $this->disable_error_output();

        $nonce = isset($_POST['nonce']) ? sanitize_text_field(wp_unslash($_POST['nonce'])) : '';

        // Security: nonce
        if ('' === $nonce || !wp_verify_nonce($nonce, Config::INBOX_NONCE_ACTION)) {
            wp_send_json_error(['message' => __('Security check failed.', 'contact-inbox')]);
        }

        // Security: capability
        if (!current_user_can(Config::CAPABILITY)) {
            wp_send_json_error(['message' => __('Permission denied.', 'contact-inbox')]);
        }

        // Validate input
        $id = isset($_POST['id']) ? absint(wp_unslash($_POST['id'])) : 0;
        $archive_action = isset($_POST['archive_action']) ? sanitize_key(wp_unslash($_POST['archive_action'])) : 'archive';
        
        if (!$id) {
            wp_send_json_error(['message' => __('Invalid message ID.', 'contact-inbox')]);
        }

        // Validate archive action
        if (!in_array($archive_action, ['archive', 'unarchive'], true)) {
            wp_send_json_error(['message' => __('Invalid action.', 'contact-inbox')]);
        }

        // Archive status: true for archive, false to restore
        $should_archive = ($archive_action === 'archive');
        
        // Update via CoreInbox (all DB operations must use CoreInbox)
        $updated = CoreInbox::instance()->toggle_archive($id, $should_archive);
        
        if (!$updated) {
            wp_send_json_error(['message' => __('Failed to update message.', 'contact-inbox')]);
        }

        wp_send_json_success([
            'archived' => $should_archive,
            'message' => $should_archive ? __('Message archived', 'contact-inbox') : __('Message restored', 'contact-inbox')
        ]);
    }

    public function ci_toggle_spam(): void {
        // Prevent PHP notices from breaking JSON output in AJAX responses
        $this->disable_error_output();

        $nonce = isset($_POST['nonce']) ? sanitize_text_field(wp_unslash($_POST['nonce'])) : '';

        // Security: nonce
        if ('' === $nonce || !wp_verify_nonce($nonce, Config::INBOX_NONCE_ACTION)) {
            wp_send_json_error(['message' => __('Security check failed.', 'contact-inbox')]);
        }

        // Security: capability
        if (!current_user_can(Config::CAPABILITY)) {
            wp_send_json_error(['message' => __('Permission denied.', 'contact-inbox')]);
        }

        // Validate input
        $id = isset($_POST['id']) ? absint(wp_unslash($_POST['id'])) : 0;
        $spam_action = isset($_POST['spam_action']) ? sanitize_key(wp_unslash($_POST['spam_action'])) : 'spam';

        if (!$id) {
            wp_send_json_error(['message' => __('Invalid message ID.', 'contact-inbox')]);
        }

        if (!in_array($spam_action, ['spam', 'not_spam'], true)) {
            wp_send_json_error(['message' => __('Invalid action.', 'contact-inbox')]);
        }

        // Handle spam or not_spam action
        $ids = [$id];
        if ($spam_action === 'not_spam') {
            $updated = CoreInbox::instance()->bulk_clear_spam($ids);
            if (!$updated) {
                wp_send_json_error(['message' => __('Failed to move message to inbox.', 'contact-inbox')]);
            }
            wp_send_json_success([
                'marked_spam' => false,
                'message' => __('Message moved to inbox', 'contact-inbox')
            ]);
        } else {
            $updated = CoreInbox::instance()->bulk_mark_spam($ids);
            if (!$updated) {
                wp_send_json_error(['message' => __('Failed to mark as spam.', 'contact-inbox')]);
            }
            wp_send_json_success([
                'marked_spam' => true,
                'message' => __('Message marked as spam', 'contact-inbox')
            ]);
        }
    }

    public function ci_download_attachment(): void {
        // Security: nonce
        if (!check_ajax_referer(Config::INBOX_NONCE_ACTION, 'nonce', false)) {
            wp_die(esc_html__('Security check failed.', 'contact-inbox'));
        }

        // Security: capability
        if (!current_user_can(Config::CAPABILITY)) {
            wp_die(esc_html__('Permission denied.', 'contact-inbox'));
        }

        // Validate ID
        $id = isset($_GET['id']) ? absint(wp_unslash($_GET['id'])) : 0;
        if (!$id) {
            wp_die(esc_html__('Invalid message ID.', 'contact-inbox'));
        }

        // Fetch message via CoreInbox
        $message = CoreInbox::instance()->get_message_by_id($id);
        if (!$message || empty($message->attachment) || !file_exists($message->attachment)) {
            wp_die(esc_html__('Attachment not found.', 'contact-inbox'));
        }

        $file_path = $message->attachment;
        $file_name = basename($file_path);

        // SECURITY: Ensure file is within uploads directory
        $upload_dir = wp_upload_dir();
        if (strpos(realpath($file_path), realpath($upload_dir['basedir'])) !== 0) {
            wp_die(esc_html__('Invalid file path.', 'contact-inbox'));
        }

        // Detect MIME type
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime  = $finfo ? finfo_file($finfo, $file_path) : 'application/octet-stream';
        if ($finfo) {
            finfo_close($finfo);
        }

        // Stream file (browser download)
        nocache_headers();
        header('Content-Description: File Transfer');
        header('Content-Type: ' . $mime);
        header('Content-Disposition: attachment; filename="' . basename($file_name) . '"');
        header('Content-Length: ' . filesize($file_path));

        if (!function_exists('WP_Filesystem')) {
            require_once ABSPATH . 'wp-admin/includes/file.php';
        }
        WP_Filesystem();
        global $wp_filesystem;

        $content = (is_object($wp_filesystem) && method_exists($wp_filesystem, 'get_contents'))
            ? $wp_filesystem->get_contents($file_path)
            : false;

        if ($content === false) {
            wp_die(esc_html__('Failed to read file.', 'contact-inbox'));
        }

        echo $content; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        exit;
    }

    /**
     * AJAX handler: Change message classification (intent category).
     */
    public function cin_change_classification(): void {
        // Prevent PHP notices from breaking JSON output in AJAX responses
        $this->disable_error_output();

        $nonce = isset($_POST['nonce']) ? sanitize_text_field(wp_unslash($_POST['nonce'])) : '';

        // Security: nonce
        if ('' === $nonce || !wp_verify_nonce($nonce, Config::INBOX_NONCE_ACTION)) {
            wp_send_json_error(['message' => __('Security check failed.', 'contact-inbox')]);
        }

        // Security: capability
        if (!current_user_can(Config::CAPABILITY)) {
            wp_send_json_error(['message' => __('Permission denied.', 'contact-inbox')]);
        }

        // Validate input
        $message_id = isset($_POST['message_id']) ? absint(wp_unslash($_POST['message_id'])) : 0;
        $category = isset($_POST['category']) ? sanitize_key(wp_unslash($_POST['category'])) : '';

        if (!$message_id) {
            wp_send_json_error(['message' => __('Invalid message ID.', 'contact-inbox')]);
        }

        if (!$category) {
            wp_send_json_error(['message' => __('Invalid category.', 'contact-inbox')]);
        }

        // Validate category against allowed values
        $allowed_categories = ['sales', 'support', 'feedback', 'complaint', 'question', 'spam', 'unclassified'];
        if (!in_array($category, $allowed_categories, true)) {
            wp_send_json_error(['message' => __('Invalid category.', 'contact-inbox')]);
        }

        // Get the message to verify it exists
        $message = CoreInbox::instance()->get_message_by_id($message_id);
        if (!$message) {
            wp_send_json_error(['message' => __('Message not found.', 'contact-inbox')]);
        }

        // Use the repository to update the message classification
        try {
            $message_repo = $this->get_handler_message_repo();
            $updated = $message_repo->update_intent($message_id, [
                'category' => $category,
                'confidence' => 1.0, // Manual classification = 100% confidence
                'classified_at' => current_time('mysql'),
            ]);

            if (!$updated) {
                wp_send_json_error(['message' => __('Failed to update classification.', 'contact-inbox')]);
            }
        } catch (\Exception $e) {
            wp_send_json_error(['message' => __('Error updating classification: ', 'contact-inbox') . $e->getMessage()]);
        }

        // Generate the badge HTML for the response
        $intent_label = \ContactInbox\Core\IntentClassifier::get_category_label($category);
        $intent_color = \ContactInbox\Core\IntentClassifier::get_category_color($category);
        $badge_html = sprintf(
            '<span class="cin-intent-badge cin-intent-%s">%s</span>',
            esc_attr($intent_color),
            esc_html($intent_label)
        );

        // Determine success message based on classification
        $success_message = $category === \ContactInbox\Core\IntentClassifier::CATEGORY_SPAM
            ? __('Message moved to spam folder.', 'contact-inbox')
            : __('Classification updated successfully.', 'contact-inbox');

        wp_send_json_success([
            'message' => $success_message,
            'category' => $category,
            'badge_html' => $badge_html
        ]);
    }

    /**
     * Attach email/CRM statuses to a Message object for modal display.
     */
    private function attach_statuses(object $message): void {
        $email_log_repo = $this->get_email_log_repo();

        $email_status = $email_log_repo->get_latest_for_recipient($message->email ?? '');
        $message->email_status        = $email_status['status'] ?? null;
        $message->email_error_message = $email_status['error_message'] ?? null;

    }

    /**
     * Disable error display to keep AJAX JSON responses clean.
     */
    private function disable_error_output(): void {
        return;
    }

    /**
     * AJAX handler: Get folder counts for inbox tabs
     * Returns counts for main, spam, and archived folders
     */
    public function ci_get_folder_counts(): void {
        $this->disable_error_output();

        $nonce = isset($_POST['nonce']) ? sanitize_text_field(wp_unslash($_POST['nonce'])) : '';
        if ('' === $nonce || !wp_verify_nonce($nonce, Config::INBOX_NONCE_ACTION)) {
            wp_send_json_error(['message' => __('Security check failed.', 'contact-inbox')]);
        }

        if (!current_user_can(Config::CAPABILITY)) {
            wp_send_json_error(['message' => __('Permission denied.', 'contact-inbox')]);
        }

        // Get contact_id if filtering by specific contact
        $contact_id = isset($_POST['contact_id']) ? absint(wp_unslash($_POST['contact_id'])) : 0;

        // Get DB instance
        $db = \ContactInbox\Core\DB::instance();

        // Get counts for each folder
        $count_main = $db->get_total_messages('', 'all', $contact_id);
        $count_spam = $db->get_total_messages('', Config::STATUS_SPAM, $contact_id);
        $count_archived = $db->get_total_messages('', Config::STATUS_ARCHIVED, $contact_id);

        wp_send_json_success([
            'main'     => $count_main,
            'spam'     => $count_spam,
            'archived' => $count_archived,
        ]);
    }

}
