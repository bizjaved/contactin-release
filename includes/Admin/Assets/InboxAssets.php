<?php
namespace ContactInbox\Admin\Assets;

use ContactInbox\Core\Config;
use ContactInbox\Admin\Assets\AssetsHelpers;
use ContactInbox\Integration\FreemiusIntegration;

if (!defined('ABSPATH')) exit;
// phpcs:disable WordPress.WP.I18n.NonSingularStringLiteralDomain, WordPress.WP.I18n.UnorderedPlaceholdersText, WordPress.WP.I18n.MissingTranslatorsComment, WordPress.WP.I18n.NonSingularStringLiteralText

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Handles enqueueing of Inbox page assets (CSS/JS) and localization.
 */
final class InboxAssets {
    use AssetHelpers;

    /**
     * Enqueue Inbox page assets.
     */
    public function enqueue(): void {
        $handle = 'contactin-admin-inbox';

        // Enqueue CSS
        $this->register_style( $handle, 'admin-inbox.min.css' );
        
        // Intent Classification CSS
        $this->register_style( 'contactin-intent-classification', 'intent-classification.css' );
        
        // Contact detail page CSS - includes modal styles
        $this->register_style( 'contactin-contact-detail', 'contact-detail.min.css' );

        // Contact detail page CSS - Tab-based layout
        $this->register_style( 'contactin-contact-detail-tabs', 'contact-detail-tabs.min.css' );

        // Unified inbox tabs CSS
        $this->register_style( 'contactin-inbox-unified', 'inbox-consolidated.min.css' );

        // Enqueue JS on both inbox and contacts pages, depend on global for helpers
        $this->register_script( $handle, 'admin-inbox.min.js', [ 'jquery', 'contactin-admin-global' ] );

        // Contact deletion script (shared on contacts and detail pages)
        $this->register_script( 'contactin-contact-deletion', 'contact-deletion.js', [ 'jquery', $handle ] );

        // Contact detail tab navigation script
        $this->register_script( 'contactin-contact-detail-tabs', 'contact-detail-tabs.js', [ 'jquery' ] );

        // Enqueue Contact Edit Modal assets
        (new ContactEditAssets())->enqueue();

        // Localize script with AJAX routing and translations
        wp_localize_script( $handle, 'cinInbox', [
            'ajax_url' => admin_url( 'admin-ajax.php' ),
            'nonce'    => wp_create_nonce( Config::INBOX_NONCE_ACTION ),
            'i18n'     => [
                'confirm' => [
                    'delete'      => __( 'Delete permanently?',  'contactin'),
                    'bulk_delete' => __( 'Delete permanently?',  'contactin'),
                    'clear_spam_title' => __( 'Clear all spam messages?',  'contactin'),
                    'clear_spam_body'  => __( 'This will permanently delete all spam messages. This action cannot be undone.',  'contactin'),
                    'clear_spam'       => __( 'Clear Spam',  'contactin'),
                ],
                'bulk' => [
                    'no_selection' => __( 'Please select messages.',  'contactin'),
                    'no_action'    => __( 'Please choose an action.',  'contactin'),
                    'applying'     => __( 'Applying...',  'contactin'),
                    'apply'        => __( 'Apply',  'contactin'),
                    'not_spam'     => __( 'Not spam',  'contactin'),
                ],
                'status' => [
                    'read'        => __( 'Read',  'contactin'),
                    'unread'      => __( 'Unread',  'contactin'),
                    'mark_read'   => __( 'Read',  'contactin'),
                    'mark_unread' => __( 'Unread',  'contactin'),
                ],
                'progress' => [
                    'processing'  => __( 'Processing…',  'contactin'),
                    'downloading' => __( 'Downloading…',  'contactin'),
                    'exporting'   => __( 'Exporting...',  'contactin'),
                    'done'        => __( 'Done!',  'contactin'),
                    'export_csv'  => __( 'Export CSV',  'contactin'),
                ],
                'upgrade_export' => [
                    'title'       => __( 'Unlock Premium Features', 'contactin' ),
                    'message'     => __( 'CSV export is available in ContactIn Pro.', 'contactin' ),
                    'features_title' => __( 'With ContactIn Pro you get:', 'contactin' ),
                    'features'    => [
                        __( 'Attachment uploads and premium CSV exports', 'contactin' ),
                        __( 'AI classifier automation and learning tools', 'contactin' ),
                        __( 'Advanced CRM and REST integration workflows', 'contactin' ),
                    ],
                    'upgrade_cta' => __( 'Upgrade to Pro', 'contactin' ),
                    'dismiss'     => __( 'Maybe later', 'contactin' ),
                ],
                'message_box' => [
                    'header'       => __( 'Inbox Notice',  'contactin'),
                    'footer_close' => __( 'Close',  'contactin'),
                ],
            ],
            'export_limit' => 1000,
            'upgrade_url' => FreemiusIntegration::get_upgrade_url( 'admin-inbox' ),
        ] );

        // Localize contact deletion script
        wp_localize_script( 'contactin-contact-deletion', 'cinContactDeletion', [
            'ajax_url'      => admin_url( 'admin-ajax.php' ),
            'action_count'  => 'ci_get_contact_message_count',
            'action_delete' => 'ci_delete_contact',
            'strings'       => [
                'confirm_delete'          => __( 'Are you sure you want to delete this contact?',  'contactin'),
                'has_messages'            => __( 'This contact has %d associated message(s). These need to be deleted before deleting the contact.',  'contactin'),
                'delete_contact_messages' => __( 'Delete Contact and Messages',  'contactin'),
                'cancel'                  => __( 'Cancel',  'contactin'),
                'deleting'                => __( 'Deleting...',  'contactin'),
                'error'                   => __( 'An error occurred. Please try again.',  'contactin'),
            ],
        ] );
    }

}
