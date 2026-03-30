<?php
namespace ContactInbox\Admin\Assets;

use ContactInbox\Core\Config;

if (!defined('ABSPATH')) exit;
/**
 * Handles enqueueing of Attachment Cleanup card assets (CSS/JS) and localization.
 */
class AttachmentCleanupAssets {
    public function enqueue(): void {
        // CSS
        wp_enqueue_style(
            'contactin-attachment-cleanup',
            Config::URL . Config::DIST_CSS . 'attachment-cleanup.min.css',
            [],
            Config::VERSION
        );
        // JS
        wp_enqueue_script(
            'contactin-attachment-cleanup',
            Config::URL . Config::DIST_JS . 'attachment-cleanup.min.js',
            ['jquery'],
            Config::VERSION,
            true
        );
        // Localize
        wp_localize_script('contactin-attachment-cleanup', 'cinAttachmentCleanup', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('contactin_maint_cleanup_orphaned_attachments'),
            'nonce_clean_stale' => wp_create_nonce('contactin_maint_clean_stale_db_entries'),
            'i18n' => [
                'scanConfirm' => __('Scan for orphaned files?',  'contactin'),
                'deleteConfirm' => __('Delete all orphaned files? This cannot be undone.',  'contactin'),
                'cleanStaleConfirm' => __('Remove 4 stale database entries? Files referencing non-existent attachments will be updated.',  'contactin'),
                'scheduleSaved' => __('Schedule updated.',  'contactin'),
                'error' => __('An error occurred.',  'contactin'),
            ]
        ]);
    }
}
