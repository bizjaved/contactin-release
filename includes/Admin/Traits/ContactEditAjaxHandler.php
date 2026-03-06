<?php
/**
 * Contact Edit AJAX Handler Trait
 * 
 * Handles AJAX requests for contact editing operations.
 * Can be used by any admin page handler.
 */

declare(strict_types=1);

namespace ContactInbox\Admin\Traits;

use ContactInbox\Core\Config;
use ContactInbox\Core\Repositories\ContactRepository;
use ContactInbox\Core\Traits\EmailUniquenessValidator;

if (!defined('ABSPATH')) {
    exit;
}

trait ContactEditAjaxHandler {
    use EmailUniquenessValidator;

    private function post_text(string $key, string $default = ''): string {
        $value = filter_input(INPUT_POST, $key, FILTER_UNSAFE_RAW);
        if (null === $value || false === $value) {
            return $default;
        }
        return sanitize_text_field(wp_unslash((string) $value));
    }

    private function post_email(string $key, string $default = ''): string {
        $value = filter_input(INPUT_POST, $key, FILTER_UNSAFE_RAW);
        if (null === $value || false === $value) {
            return $default;
        }
        return sanitize_email(wp_unslash((string) $value));
    }

    private function post_int(string $key, int $default = 0): int {
        $value = filter_input(INPUT_POST, $key, FILTER_UNSAFE_RAW);
        if (null === $value || false === $value || '' === $value) {
            return $default;
        }
        return absint(wp_unslash((string) $value));
    }
    
    /**
     * Register AJAX handlers for contact editing
     */
    public function register_contact_edit_ajax(): void {
        add_action('wp_ajax_ci_get_contact_data', [$this, 'handle_get_contact_data']);
        add_action('wp_ajax_ci_update_contact', [$this, 'handle_update_contact']);
        add_action('wp_ajax_ci_check_email_availability', [$this, 'handle_check_email_availability']);
    }

    /**
     * Get contact data for edit modal
     */
    public function handle_get_contact_data(): void {
        $nonce = $this->post_text('nonce');

        // Verify nonce
        if ('' === $nonce || !wp_verify_nonce($nonce, 'ci_update_contact')) {
            wp_send_json_error(['message' => __('Security check failed.', 'contact-inbox')]);
        }

        // Check capabilities
        if (!current_user_can(Config::CAPABILITY)) {
            wp_send_json_error(['message' => __('Permission denied.', 'contact-inbox')]);
        }

        $contact_id = $this->post_int('contact_id');
        if (!$contact_id) {
            wp_send_json_error(['message' => __('Invalid contact ID.', 'contact-inbox')]);
        }

        $repo = new ContactRepository();
        $contact = $repo->get_by_id($contact_id);

        if (!$contact) {
            wp_send_json_error(['message' => __('Contact not found.', 'contact-inbox')]);
        }

        // Return contact data
        wp_send_json_success([
            'id'              => $contact->id,
            'salutation'      => $contact->salutation ?? '',
            'name'            => $contact->name ?? '',
            'email'           => $contact->email ?? '',
            'primary_phone'   => $contact->primary_phone ?? '',
            'mobile_phone'    => $contact->mobile_phone ?? '',
            'home_phone'      => $contact->home_phone ?? '',
            'other_phone'     => $contact->other_phone ?? '',
            'source'          => $contact->source ?? '',
            'created_at'      => $contact->created_at ?? '',
            'updated_at'      => $contact->updated_at ?? '',
        ]);
    }

    /**
     * Update contact data
     */
    public function handle_update_contact(): void {
        $nonce = $this->post_text('nonce');

        // Security checks
        if ('' === $nonce || !wp_verify_nonce($nonce, 'ci_update_contact')) {
            wp_send_json_error(['message' => __('Security check failed.', 'contact-inbox')]);
        }

        if (!current_user_can(Config::CAPABILITY)) {
            wp_send_json_error(['message' => __('Permission denied.', 'contact-inbox')]);
        }

        $contact_id = $this->post_int('contact_id');
        if (!$contact_id) {
            wp_send_json_error(['message' => __('Invalid contact ID.', 'contact-inbox')]);
        }

        $repo = new ContactRepository();
        $contact = $repo->get_by_id($contact_id);

        if (!$contact) {
            wp_send_json_error(['message' => __('Contact not found.', 'contact-inbox')]);
        }

        // Prepare update data
        $update_data = [];

        if (null !== filter_input(INPUT_POST, 'name', FILTER_UNSAFE_RAW)) {
            $update_data['name'] = $this->post_text('name');
        }
        if (null !== filter_input(INPUT_POST, 'email', FILTER_UNSAFE_RAW)) {
            $update_data['email'] = $this->post_email('email');
        }
        if (null !== filter_input(INPUT_POST, 'salutation', FILTER_UNSAFE_RAW)) {
            $update_data['salutation'] = $this->post_text('salutation');
        }
        if (null !== filter_input(INPUT_POST, 'primary_phone', FILTER_UNSAFE_RAW)) {
            $update_data['primary_phone'] = $this->post_text('primary_phone');
        }
        if (null !== filter_input(INPUT_POST, 'mobile_phone', FILTER_UNSAFE_RAW)) {
            $update_data['mobile_phone'] = $this->post_text('mobile_phone');
        }
        if (null !== filter_input(INPUT_POST, 'home_phone', FILTER_UNSAFE_RAW)) {
            $update_data['home_phone'] = $this->post_text('home_phone');
        }
        if (null !== filter_input(INPUT_POST, 'other_phone', FILTER_UNSAFE_RAW)) {
            $update_data['other_phone'] = $this->post_text('other_phone');
        }

        if (empty($update_data)) {
            wp_send_json_error(['message' => __('No data to update.', 'contact-inbox')]);
        }

        // Validate email uniqueness if email is being updated
        if (isset($update_data['email'])) {
            $new_email = $update_data['email'];
            $current_email = $contact->email ?? '';
            
            $email_validation = self::validate_email_change($contact_id, $new_email, $current_email);
            if (!$email_validation['valid']) {
                wp_send_json_error([
                    'message' => $email_validation['message'],
                    'field' => 'email',
                ]);
            }
        }

        $updated = $repo->update($contact_id, $update_data);

        if ($updated) {
            wp_send_json_success([
                'message' => __('Contact updated successfully.', 'contact-inbox'),
            ]);
        } else {
            wp_send_json_error([
                'message' => __('Failed to update contact.', 'contact-inbox'),
            ]);
        }
    }

    /**
     * Check if an email is available (not used by another contact)
     */
    public function handle_check_email_availability(): void {
        $nonce = $this->post_text('nonce');

        // Security checks
        if ('' === $nonce || !wp_verify_nonce($nonce, 'ci_update_contact')) {
            wp_send_json_error(['message' => __('Security check failed.', 'contact-inbox')]);
        }

        if (!current_user_can(Config::CAPABILITY)) {
            wp_send_json_error(['message' => __('Permission denied.', 'contact-inbox')]);
        }

        $email = $this->post_email('email');
        if (empty($email)) {
            wp_send_json_success(['available' => true, 'message' => null]);
        }

        $contact_id = $this->post_int('contact_id');
        $result = self::check_email_availability($email, $contact_id);

        if ($result['available']) {
            wp_send_json_success($result);
        } else {
            wp_send_json_error($result);
        }
    }
}
