<?php
namespace ContactInbox\Admin\Assets;

use ContactInbox\Core\Config;
use ContactInbox\Admin\Assets\AssetsHelpers;

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
                'confirm_send'  => __( 'Send GDPR deletion link to: {email}?', Config::TEXTDOMAIN ),
                'no_data'       => __( 'No data', Config::TEXTDOMAIN ),
                'processing'    => __( 'Processing…', Config::TEXTDOMAIN ),
                'generated'     => __( 'Link generated!', Config::TEXTDOMAIN ),
                'copy_btn'      => __( 'Copy Link', Config::TEXTDOMAIN ),
                'copy_status'   => __( 'Copied to clipboard!', Config::TEXTDOMAIN ),
            ],
        ] );

        // Localize script with AJAX routing and translations
        wp_localize_script( $handle, 'cinInbox', [
            'ajax_url' => admin_url( 'admin-ajax.php' ),
            'nonce'    => wp_create_nonce( Config::INBOX_NONCE_ACTION ),
            'i18n'     => [
                'confirm' => [
                    'delete'      => __( Config::BULK_MSG_CONFIRM_DELETE, Config::TEXTDOMAIN ),
                    'bulk_delete' => __( Config::BULK_MSG_CONFIRM_DELETE, Config::TEXTDOMAIN ),
                    'clear_spam_title' => __( 'Clear all spam messages?', Config::TEXTDOMAIN ),
                    'clear_spam_body'  => __( 'This will permanently delete all spam messages. This action cannot be undone.', Config::TEXTDOMAIN ),
                    'clear_spam'       => __( 'Clear Spam', Config::TEXTDOMAIN ),
                ],
                'bulk' => [
                    'no_selection' => __( Config::BULK_MSG_NO_SELECTION, Config::TEXTDOMAIN ),
                    'no_action'    => __( Config::BULK_MSG_NO_ACTION, Config::TEXTDOMAIN ),
                    'applying'     => __( Config::BULK_MSG_APPLYING, Config::TEXTDOMAIN ),
                    'apply'        => __( Config::BULK_MSG_APPLY, Config::TEXTDOMAIN ),
                    'not_spam'     => __( 'Not spam', Config::TEXTDOMAIN ),
                ],
                'status' => [
                    'read'        => __( Config::STATUS_LABEL_READ, Config::TEXTDOMAIN ),
                    'unread'      => __( Config::STATUS_LABEL_UNREAD, Config::TEXTDOMAIN ),
                    'mark_read'   => __( Config::STATUS_ACTION_READ, Config::TEXTDOMAIN ),
                    'mark_unread' => __( Config::STATUS_ACTION_UNREAD, Config::TEXTDOMAIN ),
                ],
                'progress' => [
                    'processing'  => __( 'Processing…', Config::TEXTDOMAIN ),
                    'downloading' => __( 'Downloading…', Config::TEXTDOMAIN ),
                    'exporting'   => __( Config::EXPORT_MSG_RUNNING, Config::TEXTDOMAIN ),
                    'done'        => __( 'Done!', Config::TEXTDOMAIN ),
                    'export_csv'  => __( Config::EXPORT_MSG_DEFAULT, Config::TEXTDOMAIN ),
                ],
                'message_box' => [
                    'header'       => __( 'Inbox Notice', Config::TEXTDOMAIN ),
                    'footer_close' => __( 'Close', Config::TEXTDOMAIN ),
                ],
            ],
            'export_limit' => 1000,
        ] );
    }

}
