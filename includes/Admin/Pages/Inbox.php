<?php
/**
 * Admin Page – Inbox
 *
 * Main inbox page controller. Uses traits for separation of concerns:
 * - InboxPageRenderer: Display inbox list
 * - InboxMessageHandler: Single message operations (view, delete, toggle, download)
 * - InboxBulkActions: Bulk operations (read/unread/delete)
 * - InboxExportImport: CSV export
 * - InboxModalBuilder: Modal data structure & rendering
 *
 * @package ContactInbox\Admin\Pages
 * @since   1.0.0
 */

declare(strict_types=1);

namespace ContactInbox\Admin\Pages;

use ContactInbox\Core\Config;
use ContactInbox\Traits\Singleton;
use ContactInbox\Admin\Traits\{
    InboxPageRenderer,
    InboxMessageHandler,
    InboxBulkActions,
    InboxExportImport,
    InboxModalBuilder
};

if (!defined('ABSPATH')) {
    exit;
}

final class Inbox {
    use Singleton;
    use InboxPageRenderer;
    use InboxMessageHandler;
    use InboxBulkActions;
    use InboxExportImport;
    use InboxModalBuilder {
        InboxMessageHandler::disable_error_output insteadof InboxBulkActions;
    }

    /**
     * Constructor: Register AJAX handlers.
     * All logic is delegated to traits – this just wires up the hooks.
     */
    protected function __construct() {
        add_action('wp_ajax_ci_view_message', [$this, 'ci_view_message']);
        add_action('wp_ajax_ci_delete_message', [$this, 'ci_delete_message']);
        add_action('wp_ajax_ci_bulk_action', [$this, 'ci_bulk_action']);
        add_action('wp_ajax_ci_clear_spam', [$this, 'ci_clear_spam']);
        add_action('wp_ajax_ci_clear_archives', [$this, 'ci_clear_archives']);
        add_action('wp_ajax_ci_toggle_status', [$this, 'ci_toggle_status']);
        add_action('wp_ajax_ci_toggle_archive', [$this, 'ci_toggle_archive']);
        add_action('wp_ajax_ci_toggle_spam', [$this, 'ci_toggle_spam']);
        add_action('wp_ajax_ci_download_attachment', [$this, 'ci_download_attachment']);
        add_action('wp_ajax_ci_export_csv', [$this, 'ci_export_csv']);
        add_action('wp_ajax_ci_export_info', [$this, 'ci_export_info']);
        add_action('wp_ajax_cin_change_classification', [$this, 'cin_change_classification']);
        add_action('wp_ajax_ci_get_folder_counts', [$this, 'ci_get_folder_counts']);
    }

    /**
    * Static entry point: Render the contact inbox page.
    * Performs capability check, then delegates to display_page (ContactInboxPageRenderer trait).
     */
    public static function render(): void {
        if (!current_user_can(Config::CAPABILITY)) {
            wp_die(esc_html__('Permission denied.', 'contact-inbox'));
        }
        self::instance()->display_page();
    }
}
