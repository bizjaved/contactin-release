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

    private function deletion_post_int(string $key, int $default = 0): int {
        $value = filter_input(INPUT_POST, $key, FILTER_SANITIZE_NUMBER_INT);
        return is_scalar($value) ? absint((string) $value) : $default;
    }

    private function deletion_post_bool(string $key, bool $default = false): bool {
        $value = filter_input(INPUT_POST, $key, FILTER_SANITIZE_FULL_SPECIAL_CHARS);
        if (!is_string($value)) {
            return $default;
        }

        $normalized = strtolower(sanitize_text_field(wp_unslash($value)));
        return in_array($normalized, ['1', 'true', 'yes', 'on'], true);
    }

    /**
     * AJAX handler: Get contact message count before deletion
     */
    public function ci_get_contact_message_count(): void {
        $this->disable_error_output();

        // Security: nonce
        if (!check_ajax_referer('ci_contact_deletion', 'nonce', false)) {
            wp_send_json_error(['message' => __('Security check failed.', 'contact-inbox')]);
        }

        // Security: capability
        if (!current_user_can(Config::CAPABILITY)) {
            wp_send_json_error(['message' => __('Permission denied.', 'contact-inbox')]);
        }

        // Validate input
        $contact_id = $this->deletion_post_int('contact_id');
        if (!$contact_id) {
            wp_send_json_error(['message' => __('Invalid contact ID.', 'contact-inbox')]);
        }

        // Check if contact exists
        $contact_repo = new ContactRepository();
        if (!$contact_repo->exists($contact_id)) {
            wp_send_json_error(['message' => __('Contact not found.', 'contact-inbox')]);
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
        if (!check_ajax_referer('ci_contact_deletion', 'nonce', false)) {
            wp_send_json_error(['message' => __('Security check failed.', 'contact-inbox')]);
        }

        // Security: capability
        if (!current_user_can(Config::CAPABILITY)) {
            wp_send_json_error(['message' => __('Permission denied.', 'contact-inbox')]);
        }

        // Validate input
        $contact_id = $this->deletion_post_int('contact_id');
        $delete_messages = $this->deletion_post_bool('delete_messages');

        if (!$contact_id) {
            wp_send_json_error(['message' => __('Invalid contact ID.', 'contact-inbox')]);
        }

        // Check if contact exists
        $contact_repo = new ContactRepository();
        $contact = $contact_repo->get_by_id($contact_id);
        if (!$contact) {
            wp_send_json_error(['message' => __('Contact not found.', 'contact-inbox')]);
        }

        try {
            if ($delete_messages) {
                // Delete contact with all associated messages
                $result = $contact_repo->delete_with_messages($contact_id);
                
                if (!$result['contact_deleted']) {
                    wp_send_json_error([
                        'message' => __('Failed to delete contact.', 'contact-inbox'),
                    ]);
                }

                wp_send_json_success([
                    'message' => sprintf(
                        /* translators: %d: number of deleted messages. */
                        __('Contact and %d message(s) deleted permanently.', 'contact-inbox'),
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
                        'message' => __('Failed to delete contact.', 'contact-inbox'),
                    ]);
                }

                wp_send_json_success([
                    'message' => __('Contact deleted. Associated messages were preserved.', 'contact-inbox'),
                    'contact_id' => $contact_id,
                    'messages_deleted' => 0,
                ]);
            }
        } catch (\Exception $e) {
            do_action('contactinbox_error_log', 'Contact deletion error: ' . $e->getMessage());
            wp_send_json_error([
                'message' => __('An error occurred while deleting the contact.', 'contact-inbox'),
            ]);
        }
    }

    /**
     * Disable error output to avoid breaking JSON responses
     */
    private function disable_error_output(): void {
        return;
    }
}
