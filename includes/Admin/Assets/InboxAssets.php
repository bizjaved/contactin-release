<?php
namespace ContactInbox\Admin\Assets;

use ContactInbox\Core\Config;
use ContactInbox\Admin\Assets\AssetsHelpers;

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

        // Enqueue GDPR script
        $this->register_script( 'contactin-gdpr', 'gdpr.js', [ 'jquery' ] );

        // Enqueue Contact Edit Modal assets
        (new ContactEditAssets())->enqueue();

        // Localize GDPR script
        wp_localize_script( 'contactin-gdpr', 'cinGDPR', [
            'ajax_url'    => admin_url( 'admin-ajax.php' ),
            'nonce'       => wp_create_nonce( Config::GDPR_NONCE_ACTION ),
            'expiry_days' => \ContactInbox\Core\GDPR::EXPIRATION_DAYS,
            'i18n'        => [
                'confirm_send'  => __( 'Send GDPR deletion link to: {email}?',  'contactin'),
                'no_data'       => __( 'No data',  'contactin'),
                'processing'    => __( 'Processing…',  'contactin'),
                'generated'     => __( 'Link generated!',  'contactin'),
                'copy_btn'      => __( 'Copy Link',  'contactin'),
                'copy_status'   => __( 'Copied to clipboard!',  'contactin'),
            ],
        ] );

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
                'message_box' => [
                    'header'       => __( 'Inbox Notice',  'contactin'),
                    'footer_close' => __( 'Close',  'contactin'),
                ],
            ],
            'export_limit' => 1000,
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
