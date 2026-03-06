<?php
/**
 * Contact Deletion Assets
 *
 * Enqueues CSS and JS files for contact deletion functionality.
 *
 * @package ContactInbox\Admin\Assets
 */

declare(strict_types=1);

namespace ContactInbox\Admin\Assets;

use ContactInbox\Core\Config;

if (!defined('ABSPATH')) {
    exit;
}

final class ContactDeletionAssets {
    use AssetHelpers;

    /**
     * Enqueue contact deletion assets (localization only)
     * JS is now consolidated in admin-inbox.min.js
     * CSS is now consolidated in contact-detail.min.css
     */
    public function enqueue(): void {
        // Ensure 'contactin-admin-inbox' script is enqueued before localizing
        // (it should be from InboxAssets, but we check just to be safe)
        if (!wp_script_is('contactin-admin-inbox', 'enqueued')) {
            // If not enqueued by InboxAssets, enqueue it now
            wp_enqueue_script('contactin-admin-inbox');
        }

        // Localize for the admin-inbox script which now contains contact deletion JS
        wp_localize_script('contactin-admin-inbox', 'cinContactDeletion', [
            'ajax_url'           => admin_url('admin-ajax.php'),
            'action_count'       => 'ci_get_contact_message_count',
            'action_delete'      => 'ci_delete_contact',
            'nonce_action'       => 'ci_contact_deletion',
            'strings'            => [
                'confirm_delete'          => __('Are you sure you want to delete this contact?', 'contact-inbox'),
                'has_messages'            => __('This contact has %d associated message(s). These need to be deleted before deleting the contact.', 'contact-inbox'),
                'delete_contact_messages' => __('Delete Contact and Messages', 'contact-inbox'),
                'cancel'                  => __('Cancel', 'contact-inbox'),
                'deleting'                => __('Deleting...', 'contact-inbox'),
                'error'                   => __('An error occurred. Please try again.', 'contact-inbox'),
                'redirect_message'        => __('Contact deleted. Redirecting...', 'contact-inbox'),
            ],
        ]);
    }
}
