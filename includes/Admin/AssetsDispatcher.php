<?php

namespace ContactInbox\Admin;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

use ContactInbox\Traits\Singleton;
use ContactInbox\Core\Config;
use ContactInbox\Admin\Assets\{
    InboxAssets, SettingsAssets, EmailLogAssets,
    EditorAssets, AssetHelpers,
    AnalyticsWidgetsAssets, AnalyticsDashboardAssets, MaintenanceAssets, CRMSettingsAssets, RestApiIntegrationAssets, ContactDeletionAssets
};

final class AssetsDispatcher {
    use Singleton;
    use AssetHelpers;

    private function query_key(string $key, string $default = ''): string {
        $value = filter_input(INPUT_GET, $key, FILTER_UNSAFE_RAW);
        if (null === $value || false === $value) {
            return $default;
        }
        return sanitize_key(wp_unslash((string) $value));
    }

    /**
     * Map of admin page hooks to asset handler classes.
     * We store class names and instantiate lazily.
     */
    private array $handlers = [];

    private function __construct() {
                $this->handlers = [
                    // Actual hook for settings page (from error_log)
                    'contact-inbox-pro_page_contactinbox-settings' => SettingsAssets::class,
                       // Actual hook for Maintenance page (from error_log)
                       'contact-inbox-pro_page_contactinbox-maintenance' => MaintenanceAssets::class,
                              // Contacts page should share inbox assets (filtered inbox view)
                              'contact-inbox-pro_page_contactinbox-contacts' => InboxAssets::class,
                       // Actual hook for Email Log page (from error_log)
                       'contact-inbox-pro_page_contactinbox-email-log' => EmailLogAssets::class,
                       // Actual hook for Inbox page (from error_log)
                       'contact-inbox-pro_page_contactinbox-inbox' => InboxAssets::class,
                       // Unified inbox page
                       'contact-inbox-pro_page_contactinbox-inbox-unified' => InboxAssets::class,
            // Top-level (Dashboard)
            'toplevel_page_contactin-analytics'              => AnalyticsDashboardAssets::class,
            // Top-level (Inbox, legacy)
            'toplevel_page_contactin-inbox'                  => InboxAssets::class,

            // Submenu pages (new parent: contactin-analytics)
            'contactin-analytics_page_contactin-analytics'     => AnalyticsDashboardAssets::class,
            'contact-inbox-_page_settings' => SettingsAssets::class,
            'contactin-analytics_page_contactin-inbox'         => InboxAssets::class,
            'contactin-analytics_page_contactinbox-inbox-unified' => InboxAssets::class,
            'contactin-analytics_page_contactinbox-contacts'   => InboxAssets::class,
            'contactin-analytics_page_contactin-settings'      => SettingsAssets::class,
            'contactin-analytics_page_contactinbox-settings'   => SettingsAssets::class,
            'contactin-analytics_page_contactin-maintenance'   => MaintenanceAssets::class,
            'contactin-analytics_page_contactinbox-maintenance' => MaintenanceAssets::class,
            'contactin-analytics_page_contactinbox-crm'        => CRMSettingsAssets::class,
            'contactin-analytics_page_contactinbox-rest-api-test' => RestApiIntegrationAssets::class,
            'contactin-analytics_page_contactin-email-log'     => EmailLogAssets::class,
            'contactin-analytics_page_contactinbox-email-log'  => EmailLogAssets::class,

            // Legacy/compatibility: Submenu pages (old parent: contactin-inbox)
            'contactin-inbox_page_contactin-inbox'           => InboxAssets::class,
            'contactin-inbox_page_contactinbox-inbox-unified' => InboxAssets::class,
            'contactin-inbox_page_contactinbox-contacts'     => InboxAssets::class,
            'contactin-inbox_page_contactin-settings'        => SettingsAssets::class,
            'contactin-inbox_page_contactin-analytics'       => AnalyticsDashboardAssets::class,
            'contactin-inbox_page_contactin-maintenance'     => MaintenanceAssets::class,
            'contactin-inbox_page_contactinbox-maintenance'  => MaintenanceAssets::class,
            'contactin-inbox_page_contactinbox-rest-api-test' => RestApiIntegrationAssets::class,
            'contactin-inbox_page_contactin-email-log'       => EmailLogAssets::class,
            'contactin-inbox_page_contactinbox-email-log'    => EmailLogAssets::class,

            // WordPress sometimes generates hooks with parent menu as 'contact-inbox' (legacy)
            'contact-inbox_page_contact-inbox-inbox'         => InboxAssets::class,
            'contact-inbox_page_contactinbox-inbox-unified'   => InboxAssets::class,
            'contact-inbox_page_contactinbox-contacts'       => InboxAssets::class,
            'contact-inbox_page_contactin-settings'      => SettingsAssets::class,
            'contact-inbox_page_contactin-maintenance'   => MaintenanceAssets::class,
            'contact-inbox_page_contactinbox-maintenance' => MaintenanceAssets::class,
            'contact-inbox_page_contactinbox-crm'        => CRMSettingsAssets::class,
            'contact-inbox_page_contactinbox-rest-api-test' => RestApiIntegrationAssets::class,
            'contact-inbox_page_contactin-analytics'     => AnalyticsDashboardAssets::class,
            'contact-inbox_page_contactin-email-log'     => EmailLogAssets::class,
            'contact-inbox_page_contactinbox-email-log'  => EmailLogAssets::class,

            // Dashboard widgets (index.php is the dashboard)
            'index.php'                                     => AnalyticsWidgetsAssets::class,
        ];

        add_action('admin_enqueue_scripts', [$this, 'dispatch']);
        add_action('in_admin_header', [$this, 'render_admin_branding']);
        add_action('elementor/editor/after_enqueue_styles', [$this, 'enqueue_elementor_editor']);
        add_action('enqueue_block_editor_assets', [$this, 'enqueue_gutenberg_editor']);
    }

    /**
     * Dispatch asset enqueue for the current admin page.
     */
    public function dispatch(string $hook): void {
        // Fallback: enqueue settings assets by page slug if hook is unexpected
        if (!isset($this->handlers[$hook])) {
            if ($this->is_contactin_admin_page()) {
                $this->enqueue_global();
            }

            $page = $this->query_key('page');
            $page_handlers = [
                Config::MENU_SETTINGS => SettingsAssets::class,
                'contactin-settings' => SettingsAssets::class,
                Config::MENU_INBOX_UNIFIED => InboxAssets::class,
                'contactin-inbox' => InboxAssets::class,
                'contactinbox-inbox' => InboxAssets::class,
                Config::MENU_CONTACTS => InboxAssets::class,
                'contactinbox-contacts' => InboxAssets::class,
                Config::MENU_MAINTENANCE => MaintenanceAssets::class,
                'contactin-maintenance' => MaintenanceAssets::class,
                'contactinbox-maintenance' => MaintenanceAssets::class,
                Config::MENU_CRM => CRMSettingsAssets::class,
                'contactinbox-crm' => CRMSettingsAssets::class,
                Config::MENU_REST_API_TEST => RestApiIntegrationAssets::class,
                'contactinbox-rest-api-test' => RestApiIntegrationAssets::class,
                Config::MENU_EMAIL_LOG => EmailLogAssets::class,
                'contactin-email-log' => EmailLogAssets::class,
                'contactinbox-email-log' => EmailLogAssets::class,
                'contactin-analytics' => AnalyticsDashboardAssets::class,
            ];

            if (isset($page_handlers[$page])) {
                $class = $page_handlers[$page];
                (new $class())->enqueue();
            }

            if (in_array($page, [Config::MENU_CONTACTS, 'contactinbox-contacts'], true)) {
                (new ContactDeletionAssets())->enqueue();
            }
            return;
        }

        // Enqueue global admin assets (shared across plugin pages)
        $this->enqueue_global();

        // Instantiate and enqueue page‑specific assets
        $class = $this->handlers[$hook];
        (new $class())->enqueue();

        // Enqueue contact deletion assets for contacts page
        $page = $this->query_key('page');
        if (in_array($page, [Config::MENU_CONTACTS, 'contactinbox-contacts'], true)) {
            (new ContactDeletionAssets())->enqueue();
        }
    }

    /**
     * Enqueue global admin assets shared across plugin admin pages.
     */
    private function enqueue_global(): void {
        $handle = 'contactin-admin-global';

        $modules_dir = CONTACTINBOX_PATH . Config::DIST_CSS . 'modules/';
        $output_file = CONTACTINBOX_PATH . Config::DIST_CSS . 'admin-global.min.css';

        if ( file_exists( $modules_dir ) ) {
            $modules = [
                '01-variables.min.css',
                '02-base.min.css',
                '03-layout.min.css',
                '04-header.min.css',
                '05-controls.min.css',
                '06-tables.min.css',
                '07-badges.min.css',
                '08-modals.min.css',
                '09-pages.min.css',
                '10-responsive.min.css',
            ];

            $needs_rebuild = ! file_exists( $output_file );
            $output_mtime  = $needs_rebuild ? 0 : (int) filemtime( $output_file );

            if ( ! $needs_rebuild ) {
                foreach ( $modules as $module ) {
                    $module_path = $modules_dir . $module;
                    if ( file_exists( $module_path ) && filemtime( $module_path ) > $output_mtime ) {
                        $needs_rebuild = true;
                        break;
                    }
                }
            }

            if ( $needs_rebuild && is_readable( CONTACTINBOX_PATH . Config::DIST_CSS . 'build-css.php' ) ) {
                require_once CONTACTINBOX_PATH . Config::DIST_CSS . 'build-css.php';
                if ( function_exists( 'contactinbox_build_css' ) ) {
                    contactinbox_build_css( true );
                }
            }
        }

        // CSS: dist/css/admin-global.min.css
        $this->register_style($handle, 'admin-global.min.css', [], Config::VERSION);

        if ( $this->is_contactin_admin_page() ) {
            $logo_mark_path = CONTACTINBOX_PATH . 'assets/logo-512.png';
            $logo_mark_url  = CONTACTINBOX_URL . 'assets/logo-512.png';

            if ( ! file_exists( $logo_mark_path ) ) {
                $logo_mark_path = CONTACTINBOX_PATH . 'assets/icon-256x256.png';
                $logo_mark_url  = CONTACTINBOX_URL . 'assets/icon-256x256.png';
            }

            if ( file_exists( $logo_mark_path ) ) {
                $logo_mark_url = add_query_arg( 'ver', (string) filemtime( $logo_mark_path ), $logo_mark_url );
            }

            $logo_mark_url = esc_url( $logo_mark_url );

            wp_add_inline_style(
                $handle,
                ".contactin-admin-branding{display:flex;align-items:center;gap:10px;margin:10px 0 12px;padding:0 0 8px;border-bottom:1px solid #dcdcde}.contactin-admin-branding__mark{width:26px;height:26px;display:block;flex:0 0 26px}.contactin-admin-branding__title{font-size:15px;font-weight:600;color:#1d2327;line-height:1;margin:0}.contactin-admin-branding__subtitle{font-size:12px;color:#646970;line-height:1;margin-top:4px}.contactin-admin-branding__meta{display:flex;flex-direction:column}.contactin-admin-branding + .wrap h1,.contactin-admin-branding + .wrap .wp-heading-inline{margin-top:0}.contactin-admin-branding__mark{background:url('{$logo_mark_url}') center/contain no-repeat}"
            );
        }

        // JS: dist/js/admin-global.min.js
        $this->register_script($handle, 'admin-global.min.js', ['jquery'], Config::VERSION);
    }

    private function is_contactin_admin_page(): bool {
        if ( ! is_admin() ) {
            return false;
        }

        $page = $this->query_key('page');

        if ( $page !== '' ) {
            return strpos( $page, 'contactin' ) === 0
                || strpos( $page, 'contactinbox' ) === 0
                || strpos( $page, 'contact-inbox' ) === 0;
        }

        $screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
        if ( ! $screen || empty( $screen->id ) ) {
            return false;
        }

        $screen_id = (string) $screen->id;
        return strpos( $screen_id, 'contactin' ) !== false || strpos( $screen_id, 'contact-inbox' ) !== false;
    }

    public function render_admin_branding(): void {
        if ( ! $this->is_contactin_admin_page() ) {
            return;
        }

        echo '<div class="contactin-admin-branding">';
        echo '<span class="contactin-admin-branding__mark" aria-hidden="true"></span>';
        echo '<div class="contactin-admin-branding__meta">';
        echo '<p class="contactin-admin-branding__title">' . esc_html__( 'ContactIn', 'contact-inbox' ) . '</p>';
        echo '<p class="contactin-admin-branding__subtitle">' . esc_html__( 'Never miss a message. Never lose a lead.', 'contact-inbox' ) . '</p>';
        echo '</div>';
        echo '</div>';
    }

    /**
     * Enqueue Elementor editor assets.
     */
    public function enqueue_elementor_editor(): void {
        (new EditorAssets())->enqueue_elementor();
    }

    /**
     * Enqueue Gutenberg editor assets.
     */
    public function enqueue_gutenberg_editor(): void {
        (new EditorAssets())->enqueue_gutenberg();
    }
}
