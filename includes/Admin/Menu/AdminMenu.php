<?php
namespace ContactInbox\Admin\Menu;

use ContactInbox\Traits\Singleton;
use ContactInbox\Core\Config;
use ContactInbox\Admin\Pages\Contacts;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// phpcs:disable WordPress.WP.I18n.NonSingularStringLiteralDomain

final class AdminMenu {
	use Singleton;

	public function register(): void {
		add_action( 'admin_menu', array( $this, 'add_menu_pages' ) );
	}

	public function add_menu_pages(): void {
								$icon_svg = 'data:image/svg+xml;base64,' . base64_encode(
									'
<svg width="20" height="20" viewBox="0 0 20 20" xmlns="http://www.w3.org/2000/svg" aria-hidden="true" focusable="false">
        <path fill="#000" d="M3.5 4.2h13c.72 0 1.3.58 1.3 1.3v9c0 .72-.58 1.3-1.3 1.3h-13c-.72 0-1.3-.58-1.3-1.3v-9c0-.72.58-1.3 1.3-1.3Zm.9 1.9v.62l5.6 3.56 5.6-3.56V6.1L10 9.72 4.4 6.1Zm0 2.18v5.62h11.2V8.28l-4.63 2.95a1.8 1.8 0 0 1-1.94 0L4.4 8.28Z"/>
</svg>
'
								);

		// Main Plugin Menu - Dashboard as default
		add_menu_page(
			__( 'ContactIn', 'contactin' ),
			__( 'ContactIn', 'contactin' ),
			Config::CAPABILITY,
			'contactin-analytics',
			array( \ContactInbox\Admin\Pages\AnalyticsDashboard::class, 'render' ),
			$icon_svg,
			26
		);

		// Dashboard (duplicates main menu for first item, WordPress convention)
		add_submenu_page(
			'contactin-analytics',
			__( 'Dashboard', 'contactin' ),
			__( 'Dashboard', 'contactin' ),
			Config::CAPABILITY,
			'contactin-analytics',
			array( \ContactInbox\Admin\Pages\AnalyticsDashboard::class, 'render' )
		);

		// Unified inbox (tabs for Main, Spam, Archives)
		add_submenu_page(
			'contactin-analytics',
			__( 'Inbox', 'contactin' ),
			__( 'Inbox', 'contactin' ),
			Config::CAPABILITY,
			Config::MENU_INBOX_UNIFIED,
			array( \ContactInbox\Admin\Pages\InboxUnified::class, 'render' )
		);
		add_submenu_page(
			'contactin-analytics',
			__( 'Contacts', 'contactin' ),
			__( 'Contacts', 'contactin' ),
			Config::CAPABILITY,
			Config::MENU_CONTACTS,
			array( Contacts::class, 'render' )
		);

		add_submenu_page(
			'contactin-analytics',
			__( 'Settings', 'contactin' ),
			__( 'Settings', 'contactin' ),
			Config::CAPABILITY,
			Config::MENU_SETTINGS,
			array( \ContactInbox\Admin\Pages\Settings::instance(), 'display_page' )
		);

		// Maintenance / Operations
		add_submenu_page(
			'contactin-analytics',
			__( 'Maintenance', 'contactin' ),
			__( 'Maintenance', 'contactin' ),
			Config::CAPABILITY,
			Config::MENU_MAINTENANCE,
			array( \ContactInbox\Admin\Pages\Maintenance::class, 'render' )
		);

		// Email Log
		add_submenu_page(
			'contactin-analytics',
			__( 'Email Log', 'contactin' ),
			__( 'Email Log', 'contactin' ),
			Config::CAPABILITY,
			Config::MENU_EMAIL_LOG,
			array( \ContactInbox\Admin\Pages\EmailLog::class, 'render' )
		);

		// Get Started (Hidden page - accessed via plugin action link)
		add_submenu_page(
			null,
			__( 'Get Started', 'contactin' ),
			__( 'Get Started', 'contactin' ),
			Config::CAPABILITY,
			'contactin-get-started',
			array( \ContactInbox\Admin\Pages\GetStarted::class, 'render' )
		);

	}
}
