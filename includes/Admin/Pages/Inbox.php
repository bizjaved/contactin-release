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
 * @package ContactIn\Admin\Pages
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

if ( ! defined( 'ABSPATH' ) ) {
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
		add_action( 'wp_ajax_ci_view_message', array( $this, 'ci_view_message' ) );
		add_action( 'wp_ajax_ci_delete_message', array( $this, 'ci_delete_message' ) );
		add_action( 'wp_ajax_ci_bulk_action', array( $this, 'ci_bulk_action' ) );
		add_action( 'wp_ajax_ci_clear_spam', array( $this, 'ci_clear_spam' ) );
		add_action( 'wp_ajax_ci_clear_archives', array( $this, 'ci_clear_archives' ) );
		add_action( 'wp_ajax_ci_toggle_status', array( $this, 'ci_toggle_status' ) );
		add_action( 'wp_ajax_ci_toggle_archive', array( $this, 'ci_toggle_archive' ) );
		add_action( 'wp_ajax_ci_toggle_spam', array( $this, 'ci_toggle_spam' ) );
		add_action( 'wp_ajax_ci_download_attachment', array( $this, 'ci_download_attachment' ) );
		add_action( 'wp_ajax_ci_export_csv', array( $this, 'ci_export_csv' ) );
		add_action( 'wp_ajax_ci_export_info', array( $this, 'ci_export_info' ) );
		add_action( 'wp_ajax_cin_change_classification', array( $this, 'cin_change_classification' ) );
		add_action( 'wp_ajax_ci_get_folder_counts', array( $this, 'ci_get_folder_counts' ) );
	}

	/**
	 * Static entry point: Render the contact inbox page.
	 * Performs capability check, then delegates to display_page (ContactInboxPageRenderer trait).
	 */
	public static function render(): void {
		if ( ! current_user_can( Config::CAPABILITY ) ) {
			wp_die( esc_html__( 'Permission denied.', 'contactin' ) );
		}
		self::instance()->display_page();
	}
}
