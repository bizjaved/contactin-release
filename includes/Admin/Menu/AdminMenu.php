<?php
namespace ContactInbox\Admin\Menu;

use ContactInbox\Traits\Singleton;
use ContactInbox\Core\Config;
use ContactInbox\Admin\Pages\Contacts;

final class AdminMenu {
    use Singleton;

    public function register(): void {
        add_action( 'admin_menu', [ $this, 'add_menu_pages' ] );
    }

    public function add_menu_pages(): void {
        $icon_svg = 'data:image/svg+xml;base64,' . base64_encode('
    <svg width="20" height="20" viewBox="0 0 20 20" xmlns="http://www.w3.org/2000/svg" aria-hidden="true" focusable="false">
        <path fill="#000" d="M3.5 4.2h13c.72 0 1.3.58 1.3 1.3v9c0 .72-.58 1.3-1.3 1.3h-13c-.72 0-1.3-.58-1.3-1.3v-9c0-.72.58-1.3 1.3-1.3Zm.9 1.9v.62l5.6 3.56 5.6-3.56V6.1L10 9.72 4.4 6.1Zm0 2.18v5.62h11.2V8.28l-4.63 2.95a1.8 1.8 0 0 1-1.94 0L4.4 8.28Z"/>
    </svg>
    ');

        // Main Plugin Menu - Dashboard as default
        add_menu_page(
            __( 'Contact Inbox', 'contact-inbox' ),
            __( 'Contact Inbox', 'contact-inbox' ),
            Config::CAPABILITY,
            'contactin-analytics',
            [ \ContactInbox\Admin\Pages\AnalyticsDashboard::class, 'render' ],
            $icon_svg,
            26
        );

        // Dashboard (duplicates main menu for first item, WordPress convention)
        add_submenu_page( 'contactin-analytics', __( 'Dashboard', 'contact-inbox' ), __( 'Dashboard', 'contact-inbox' ),
            Config::CAPABILITY, 'contactin-analytics', [ \ContactInbox\Admin\Pages\AnalyticsDashboard::class, 'render' ] );

        // Unified inbox (tabs for Main, Spam, Archives)
        add_submenu_page( 'contactin-analytics', __( 'Inbox', 'contact-inbox' ), __( 'Inbox', 'contact-inbox' ),
            Config::CAPABILITY, Config::MENU_INBOX_UNIFIED, [ \ContactInbox\Admin\Pages\InboxUnified::class, 'render' ] );
        add_submenu_page(
            'contactin-analytics',
            __( 'Contacts', 'contact-inbox'),
            __( 'Contacts', 'contact-inbox'),
            Config::CAPABILITY,
            Config::MENU_CONTACTS,
            [Contacts::class, 'render']
        );

        add_submenu_page( 'contactin-analytics', __( 'Settings', 'contact-inbox' ), __( 'Settings', 'contact-inbox' ),
            Config::CAPABILITY, Config::MENU_SETTINGS, [ \ContactInbox\Admin\Pages\Settings::instance(), 'display_page' ]);

        // CRM Integration
        add_submenu_page( 'contactin-analytics', __( 'CRM Integration', 'contact-inbox' ), __( 'CRM Integration', 'contact-inbox' ),
            Config::CAPABILITY, Config::MENU_CRM, [ \ContactInbox\Admin\Pages\CRMSettingsPage::class, 'render' ] );

        // REST API Integration
        add_submenu_page( 'contactin-analytics', __( 'REST API', 'contact-inbox' ), __( 'REST API', 'contact-inbox' ),
            Config::CAPABILITY, Config::MENU_REST_API_TEST, [ \ContactInbox\Admin\Pages\RestApiIntegration::class, 'render' ] );

        // Maintenance / Operations
        add_submenu_page( 'contactin-analytics', __( 'Maintenance', 'contact-inbox' ), __( 'Maintenance', 'contact-inbox' ),
            Config::CAPABILITY, Config::MENU_MAINTENANCE, [ \ContactInbox\Admin\Pages\Maintenance::class, 'render' ] );

        // Email Log
        add_submenu_page( 'contactin-analytics', __( 'Email Log', 'contact-inbox' ), __( 'Email Log', 'contact-inbox' ),
            Config::CAPABILITY, Config::MENU_EMAIL_LOG, [ \ContactInbox\Admin\Pages\EmailLog::class, 'render' ] );

        // Get Started (Hidden page - accessed via plugin action link)
        add_submenu_page( null, __( 'Get Started', 'contact-inbox' ), __( 'Get Started', 'contact-inbox' ),
            Config::CAPABILITY, 'contactin-get-started', [ \ContactInbox\Admin\Pages\GetStarted::class, 'render' ] );
    }
}
