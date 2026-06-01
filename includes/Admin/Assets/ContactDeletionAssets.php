<?php
/**
 * Contact Deletion Assets
 *
 * Enqueues CSS and JS files for contact deletion functionality.
 *
 * @package ContactIn\Admin\Assets
 */

declare(strict_types=1);

namespace ContactInbox\Admin\Assets;

use ContactInbox\Core\Config;

// phpcs:disable WordPress.WP.I18n.NonSingularStringLiteralDomain, WordPress.WP.I18n.UnorderedPlaceholdersText, WordPress.WP.I18n.MissingTranslatorsComment

if (!defined('ABSPATH')) {
    exit;
}

final class ContactDeletionAssets {
    use AssetHelpers;

    /**
     * Enqueue contact deletion assets (JS + localization)
     */
    public function enqueue(): void {
        // Ensure core inbox script is available for shared helpers
        $deps = ['jquery', 'contactin-admin-inbox'];

        // Contact deletion interaction script
        $this->register_script('contactin-contact-deletion', 'contact-deletion.js', $deps);

        // Ensure modal styles are available
        wp_enqueue_style('contactin-contact-detail');

        // Localize script
        wp_localize_script('contactin-contact-deletion', 'contactinContactDeletion', [
            'ajax_url'      => admin_url('admin-ajax.php'),
            'action_count'  => 'contactin_get_contact_message_count',
            'action_delete' => 'contactin_delete_contact',
            'nonce_action'  => 'contactin_contact_deletion',
            'strings'       => [
                'confirm_delete'          => __('Are you sure you want to delete this contact?',  'contactin'),
                'has_messages'            => __('This contact has %d message(s). What would you like to do?',  'contactin'),
                'delete_contact_messages' => __('Delete Contact & Messages',  'contactin'),
                'cancel'                  => __('Cancel',  'contactin'),
                'deleting'                => __('Deleting...',  'contactin'),
                'error'                   => __('An error occurred. Please try again.',  'contactin'),
                'success'                 => __('Contact deleted successfully!',  'contactin'),
            ],
        ]);
    }
}
