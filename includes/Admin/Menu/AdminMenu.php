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
        $icon_svg = CONTACTINBOX_URL . 'dist/branding/icon-20x20.svg';

        // Main Plugin Menu - Dashboard as default
        add_menu_page(
            __( 'Contact Inbox', Config::TEXTDOMAIN ),
            __( 'Contact Inbox', Config::TEXTDOMAIN ),
            Config::CAPABILITY,
            'contactin-analytics',
            [ \ContactInbox\Admin\Pages\AnalyticsDashboard::class, 'render' ],
            $icon_svg,
            26
        );

        // Dashboard (duplicates main menu for first item, WordPress convention)
        add_submenu_page( 'contactin-analytics', __( 'Dashboard', Config::TEXTDOMAIN ), __( 'Dashboard', Config::TEXTDOMAIN ),
            Config::CAPABILITY, 'contactin-analytics', [ \ContactInbox\Admin\Pages\AnalyticsDashboard::class, 'render' ] );

        // Unified inbox (tabs for Main, Spam, Archives)
        add_submenu_page( 'contactin-analytics', __( 'Inbox', Config::TEXTDOMAIN ), __( 'Inbox', Config::TEXTDOMAIN ),
            Config::CAPABILITY, Config::MENU_INBOX_UNIFIED, [ \ContactInbox\Admin\Pages\InboxUnified::class, 'render' ] );
        add_submenu_page(
            'contactin-analytics',
            __( 'Contacts', Config::TEXTDOMAIN),
            __( 'Contacts', Config::TEXTDOMAIN),
            Config::CAPABILITY,
            Config::MENU_CONTACTS,
            [Contacts::class, 'render']
        );

        add_submenu_page( 'contactin-analytics', __( 'Settings', Config::TEXTDOMAIN ), __( 'Settings', Config::TEXTDOMAIN ),
            Config::CAPABILITY, Config::MENU_SETTINGS, [ \ContactInbox\Admin\Pages\Settings::instance(), 'display_page' ]);

        // CRM Integration
        add_submenu_page( 'contactin-analytics', __( 'CRM Integration', Config::TEXTDOMAIN ), __( 'CRM Integration', Config::TEXTDOMAIN ),
            Config::CAPABILITY, Config::MENU_CRM, [ \ContactInbox\Admin\Pages\CRMSettingsPage::class, 'render' ] );

        // REST API Integration
        add_submenu_page( 'contactin-analytics', __( 'REST API', Config::TEXTDOMAIN ), __( 'REST API', Config::TEXTDOMAIN ),
            Config::CAPABILITY, Config::MENU_REST_API_TEST, [ \ContactInbox\Admin\Pages\RestApiIntegration::class, 'render' ] );

        // Maintenance / Operations
        add_submenu_page( 'contactin-analytics', __( 'Maintenance', Config::TEXTDOMAIN ), __( 'Maintenance', Config::TEXTDOMAIN ),
            Config::CAPABILITY, Config::MENU_MAINTENANCE, [ \ContactInbox\Admin\Pages\Maintenance::class, 'render' ] );

        // Email Log
        add_submenu_page( 'contactin-analytics', __( 'Email Log', Config::TEXTDOMAIN ), __( 'Email Log', Config::TEXTDOMAIN ),
            Config::CAPABILITY, Config::MENU_EMAIL_LOG, [ \ContactInbox\Admin\Pages\EmailLog::class, 'render' ] );

        // Get Started (Hidden page - accessed via plugin action link)
        add_submenu_page( null, __( 'Get Started', Config::TEXTDOMAIN ), __( 'Get Started', Config::TEXTDOMAIN ),
            Config::CAPABILITY, 'contactin-get-started', [ \ContactInbox\Admin\Pages\GetStarted::class, 'render' ] );
    }
}
