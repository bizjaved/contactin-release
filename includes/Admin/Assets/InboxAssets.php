<?php
namespace ContactInbox\Admin\Assets;

use ContactInbox\Core\Config;

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
        wp_add_inline_style( $handle, $this->get_classification_modal_inline_css() );
        wp_add_inline_style( $handle, $this->get_export_modal_inline_css() );
        
        // Intent Classification CSS
        $this->register_style( 'contactin-intent-classification', 'intent-classification.css' );
        
        // Contact detail page CSS - includes modal styles
        $this->register_style( 'contactin-contact-detail', 'contact-detail.min.css' );

        // Contact detail page CSS - Tab-based layout
        $this->register_style( 'contactin-contact-detail-tabs', 'contact-detail-tabs.min.css' );

        // Unified inbox tabs CSS
        $this->register_style( 'contactin-inbox-unified', 'inbox-consolidated.min.css' );

        // Always load distribution bundle in runtime.
        $this->register_script( $handle, 'admin-inbox.min.js', [ 'jquery', 'contactin-admin-global' ] );

        // Contact deletion script (shared on contacts and detail pages)
        $this->register_script( 'contactin-contact-deletion', 'contact-deletion.js', [ 'jquery', $handle ] );

        // Contact detail tab navigation script
        $this->register_script( 'contactin-contact-detail-tabs', 'contact-detail-tabs.js', [ 'jquery' ] );

        // Enqueue Contact Edit Modal assets
        (new ContactEditAssets())->enqueue();

        // Localize script with AJAX routing and translations
        wp_localize_script( $handle, 'contactinInbox', [
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
                    'done'        => __( 'Done!',  'contactin'),
                ],
                'message_box' => [
                    'header'       => __( 'Inbox Notice',  'contactin'),
                    'footer_close' => __( 'Close',  'contactin'),
                ],
            ],
        ] );

        // Localize contact deletion script
        wp_localize_script( 'contactin-contact-deletion', 'contactinContactDeletion', [
            'ajax_url'      => admin_url( 'admin-ajax.php' ),
            'action_count'  => 'contactin_get_contact_message_count',
            'action_delete' => 'contactin_delete_contact',
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

    /**
     * Classification modal styles moved from template inline style block.
     */
    private function get_classification_modal_inline_css(): string {
        return <<<'CSS'
.cin-classification-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(120px, 1fr));
    gap: 12px;
    margin-bottom: 20px;
}

#cin-classification-modal .contactin-modal-header {
    padding: 16px 20px;
}

#cin-classification-modal .contactin-modal-body {
    padding: 24px 20px;
    flex: 1 1 auto;
    overflow-y: auto;
}

#cin-classification-modal .contactin-modal-footer {
    padding: 16px 20px;
}

.cin-classification-btn {
    padding: 12px 16px;
    border: 2px solid #c3c4c7;
    border-radius: 4px;
    background: #f6f7f7;
    color: #2c3338;
    font-weight: 500;
    cursor: pointer;
    transition: all 0.2s ease;
    text-align: center;
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 6px;
    font-size: 13px;
}

.cin-classification-btn .dashicons {
    font-size: 20px;
    width: 20px;
    height: 20px;
}

.cin-classification-btn:hover:not(.cin-current):not(.cin-classification-btn--locked) {
    border-color: #8c8f94;
    background: #fff;
    transform: translateY(-2px);
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
}

.cin-classification-btn--locked {
    opacity: 0.55;
    cursor: not-allowed;
    pointer-events: none;
}

.cin-classification-btn.cin-current {
    border-color: #2271b1;
    background: #e7f3ff;
    color: #2271b1;
    font-weight: 600;
    box-shadow: 0 2px 8px rgba(34, 113, 177, 0.2);
    transform: scale(1.02);
}

.cin-classification-btn.cin-current .dashicons {
    color: #2271b1;
}

.cin-classification-btn.cin-selected {
    border-color: #135e96;
    background: #135e96;
    color: #fff;
    font-weight: 600;
    transform: scale(1.02);
}

.cin-classification-btn.cin-selected .dashicons {
    color: #fff;
}

#cin-classification-modal.processing .cin-classification-btn:not(.cin-selected) {
    opacity: 0.5;
    cursor: not-allowed;
    pointer-events: none;
}

#cin-classification-modal.processing .cin-classification-btn.cin-selected {
    pointer-events: none;
}

.cin-category-label {
    display: block;
    font-size: 13px;
    line-height: 1.4;
    word-break: break-word;
}

.cin-classification-hint {
    color: #646970;
    margin: 10px 0 0 0;
    text-align: center;
    font-size: 12px;
}

.cin-classification-upgrade-notice {
    margin: 0 0 16px;
    padding: 10px 12px;
    border-left: 4px solid #2271b1;
    background: #f0f6fc;
}

.cin-classification-upgrade-notice p {
    margin: 0 0 8px;
}

.cin-classification-upgrade-notice p:last-child {
    margin-bottom: 0;
}

.cin-classification-upgrade-cta {
    display: flex;
    align-items: center;
    gap: 10px;
    margin-top: 8px;
}

.cin-classification-upgrade-cta p {
    margin: 0;
}

#cin-classification-modal {
    display: none !important;
    visibility: hidden !important;
    pointer-events: none !important;
    position: fixed;
    inset: 0;
    z-index: 10005;
    justify-content: center;
    align-items: flex-start;
    padding: 40px 20px 20px;
}

#cin-classification-modal.is-active {
    display: flex !important;
    visibility: visible !important;
    pointer-events: auto !important;
}

.cin-modal-title {
    font-size: 16px;
    font-weight: 600;
    color: #1d2327;
}

.cin-modal-close {
    color: #646970;
    transition: color 0.15s ease;
}

.cin-modal-close:hover {
    color: #1d2327;
}

.cin-footer-actions {
    width: 100%;
    display: flex;
    justify-content: flex-end;
    gap: 8px;
}
CSS;
    }

    /**
     * Export modal visibility rules moved from template inline style block.
     */
    private function get_export_modal_inline_css(): string {
        return <<<'CSS'
#cin-export-modal {
	display: none !important;
	align-items: center !important;
	justify-content: center !important;
	background-color: rgba(0, 0, 0, 0.5) !important;
}

#cin-export-modal.active {
	display: flex !important;
}
CSS;
    }

}
