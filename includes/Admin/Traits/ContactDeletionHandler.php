<?php
/**
 * Contact Deletion Handler Trait
 *
 * Handles contact deletion via AJAX with confirmation for associated messages.
 *
 * @package ContactInbox\Admin\Traits
 */

declare(strict_types=1);

namespace ContactInbox\Admin\Traits;

use ContactInbox\Core\Config;
use ContactInbox\Core\Repositories\ContactRepository;
use ContactInbox\Core\Repositories\MessageRepository;

if (!defined('ABSPATH')) {
    exit;
}

trait ContactDeletionHandler {

    /**
     * AJAX handler: Get contact message count before deletion
     */
    public function ci_get_contact_message_count(): void {
        $this->disable_error_output();

        // Security: nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'ci_contact_deletion')) {
            wp_send_json_error(['message' => __('Security check failed.', Config::TEXTDOMAIN)]);
        }

        // Security: capability
        if (!current_user_can(Config::CAPABILITY)) {
            wp_send_json_error(['message' => __('Permission denied.', Config::TEXTDOMAIN)]);
        }

        // Validate input
        $contact_id = absint($_POST['contact_id'] ?? 0);
        if (!$contact_id) {
            wp_send_json_error(['message' => __('Invalid contact ID.', Config::TEXTDOMAIN)]);
        }

        // Check if contact exists
        $contact_repo = new ContactRepository();
        if (!$contact_repo->exists($contact_id)) {
            wp_send_json_error(['message' => __('Contact not found.', Config::TEXTDOMAIN)]);
        }

        // Count associated messages
        $message_repo = new MessageRepository();
        $message_count = $message_repo->count_by_contact($contact_id);

        wp_send_json_success([
            'contact_id' => $contact_id,
            'message_count' => $message_count,
        ]);
    }

    /**
     * AJAX handler: Delete contact (with optional messages)
     */
    public function ci_delete_contact(): void {
        $this->disable_error_output();

        // Security: nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'ci_contact_deletion')) {
            wp_send_json_error(['message' => __('Security check failed.', Config::TEXTDOMAIN)]);
        }

        // Security: capability
        if (!current_user_can(Config::CAPABILITY)) {
            wp_send_json_error(['message' => __('Permission denied.', Config::TEXTDOMAIN)]);
        }

        // Validate input
        $contact_id = absint($_POST['contact_id'] ?? 0);
        $delete_messages = isset($_POST['delete_messages']) ? (bool) $_POST['delete_messages'] : false;

        if (!$contact_id) {
            wp_send_json_error(['message' => __('Invalid contact ID.', Config::TEXTDOMAIN)]);
        }

        // Check if contact exists
        $contact_repo = new ContactRepository();
        $contact = $contact_repo->get_by_id($contact_id);
        if (!$contact) {
            wp_send_json_error(['message' => __('Contact not found.', Config::TEXTDOMAIN)]);
        }

        try {
            if ($delete_messages) {
                // Delete contact with all associated messages
                $result = $contact_repo->delete_with_messages($contact_id);
                
                if (!$result['contact_deleted']) {
                    wp_send_json_error([
                        'message' => __('Failed to delete contact.', Config::TEXTDOMAIN),
                    ]);
                }

                wp_send_json_success([
                    'message' => sprintf(
                        __('Contact and %d message(s) deleted permanently.', Config::TEXTDOMAIN),
                        $result['messages_deleted']
                    ),
                    'contact_id' => $contact_id,
                    'messages_deleted' => $result['messages_deleted'],
                ]);
            } else {
                // Delete only the contact, keep messages
                $deleted = $contact_repo->delete($contact_id);
                
                if (!$deleted) {
                    wp_send_json_error([
                        'message' => __('Failed to delete contact.', Config::TEXTDOMAIN),
                    ]);
                }

                wp_send_json_success([
                    'message' => __('Contact deleted. Associated messages were preserved.', Config::TEXTDOMAIN),
                    'contact_id' => $contact_id,
                    'messages_deleted' => 0,
                ]);
            }
        } catch (\Exception $e) {
            do_action('contactinbox_error_log', 'Contact deletion error: ' . $e->getMessage());
            wp_send_json_error([
                'message' => __('An error occurred while deleting the contact.', Config::TEXTDOMAIN),
            ]);
        }
    }

    /**
     * Disable error output to avoid breaking JSON responses
     */
    private function disable_error_output(): void {
        if (defined('WP_DEBUG') && WP_DEBUG) {
            ini_set('display_errors', '0');
        }
    }
}
