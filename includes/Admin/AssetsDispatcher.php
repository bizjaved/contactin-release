<?php
namespace ContactInbox\Admin;

use ContactInbox\Traits\Singleton;
use ContactInbox\Core\Config;
use ContactInbox\Admin\Assets\{
    InboxAssets, SettingsAssets, EmailLogAssets,
    EditorAssets, AssetHelpers,
    AnalyticsWidgetsAssets, AnalyticsDashboardAssets, MaintenanceAssets, CRMSettingsAssets
};

final class AssetsDispatcher {
    use Singleton;
    use AssetHelpers;

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
            'contactin-analytics_page_contactinbox-crm'        => CRMSettingsAssets::class,
            'contactin-analytics_page_contactin-email-log'     => EmailLogAssets::class,

            // Legacy/compatibility: Submenu pages (old parent: contactin-inbox)
            'contactin-inbox_page_contactin-inbox'           => InboxAssets::class,
            'contactin-inbox_page_contactinbox-inbox-unified' => InboxAssets::class,
            'contactin-inbox_page_contactinbox-contacts'     => InboxAssets::class,
            'contactin-inbox_page_contactin-settings'        => SettingsAssets::class,
            'contactin-inbox_page_contactin-analytics'       => AnalyticsDashboardAssets::class,
            'contactin-inbox_page_contactin-maintenance'     => MaintenanceAssets::class,
            'contactin-inbox_page_contactin-email-log'       => EmailLogAssets::class,

            // WordPress sometimes generates hooks with parent menu as 'contact-inbox' (legacy)
            'contact-inbox_page_contact-inbox-inbox'         => InboxAssets::class,
            'contact-inbox_page_contactinbox-inbox-unified'   => InboxAssets::class,
            'contact-inbox_page_contactinbox-contacts'       => InboxAssets::class,
            'contact-inbox_page_contactin-settings'      => SettingsAssets::class,
            'contact-inbox_page_contactin-maintenance'   => MaintenanceAssets::class,
            'contact-inbox_page_contactinbox-crm'        => CRMSettingsAssets::class,
            'contact-inbox_page_contactin-analytics'     => AnalyticsDashboardAssets::class,
            'contact-inbox_page_contactin-email-log'     => EmailLogAssets::class,

            // Dashboard widgets (index.php is the dashboard)
            'index.php'                                     => AnalyticsWidgetsAssets::class,
        ];

        add_action('admin_enqueue_scripts', [$this, 'dispatch']);
        add_action('elementor/editor/after_enqueue_styles', [$this, 'enqueue_elementor_editor']);
        add_action('enqueue_block_editor_assets', [$this, 'enqueue_gutenberg_editor']);
    }

    /**
     * Dispatch asset enqueue for the current admin page.
     */
    public function dispatch(string $hook): void {
        // Fallback: enqueue settings assets by page slug if hook is unexpected
        if (!isset($this->handlers[$hook])) {
            $page = isset($_GET['page']) ? sanitize_key((string) $_GET['page']) : '';
            if (in_array($page, [Config::MENU_SETTINGS, 'contactin-settings'], true)) {
                $this->enqueue_global();
                (new SettingsAssets())->enqueue();
            }
            return;
        }

        // Enqueue global admin assets (shared across plugin pages)
        $this->enqueue_global();

        // Instantiate and enqueue page‑specific assets
        $class = $this->handlers[$hook];
        (new $class())->enqueue();
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

        // JS: dist/js/admin-global.min.js
        $this->register_script($handle, 'admin-global.min.js', ['jquery'], Config::VERSION);
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
