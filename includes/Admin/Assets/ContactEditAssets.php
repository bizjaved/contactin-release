<?php
/**
 * Contact Edit Modal Assets
 *
 * Handles enqueueing and localization of contact edit modal assets.
 * Used on both Contacts page and Contact Detail page.
 */

declare(strict_types=1);

namespace ContactInbox\Admin\Assets;

use ContactInbox\Core\Config;

// phpcs:disable WordPress.WP.I18n.NonSingularStringLiteralDomain

if (!defined('ABSPATH')) {
    exit;
}

final class ContactEditAssets {
    use AssetHelpers;

    /**
     * Enqueue Contact Edit Modal assets
     */
    public function enqueue(): void {
        $handle = 'contactin-contact-edit';

        // CSS - Contact edit modal drawer
        $this->register_style(
            $handle,
            'contact-edit-modal.min.css',
            ['contactin-admin-global']
        );

        // JS - Contact edit modal handler with validation
        $this->register_script(
            $handle,
            'contact-edit-modal.js',
            ['jquery', 'contactin-admin-global']
        );

        // Localize for JavaScript
        wp_localize_script($handle, 'cinContactEdit', [
            'ajax_url'      => admin_url('admin-ajax.php'),
            'action'        => 'ci_update_contact',
            'nonce_action'  => 'ci_update_contact',
            'check_email_action' => 'ci_check_email_availability',
            'strings'       => [
                'confirm_save'       => __('Are you sure you want to save these changes?',  'contactin'),
                'save_error'         => __('Failed to save contact. Please try again.',  'contactin'),
                'save_success'       => __('Contact saved successfully!',  'contactin'),
                'validation_error'   => __('Please fix the errors below:',  'contactin'),
                'invalid_email'      => __('Please enter a valid email address.',  'contactin'),
                'invalid_phone'      => __('Please enter a valid phone number.',  'contactin'),
                'name_required'      => __('Name is required.',  'contactin'),
                'unsaved_changes'    => __('You have unsaved changes. Do you want to leave without saving?',  'contactin'),
                'email_in_use'       => __('This email is already used by another contact.',  'contactin'),
                'email_check_error'  => __('Error checking email availability.',  'contactin'),
            ],
        ]);
    }
}
