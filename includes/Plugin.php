<?php

/**
 * Contact Inbox – Main Plugin Class (Refactored)
 *
 * Enterprise-Grade – Clean, fast, secure, modular, future-proof.
 *
 * Ensures all AJAX handlers are registered on every admin request,
 * including admin-ajax.php.
 *
 * @package ContactInbox
 */

namespace ContactInbox;

use ContactInbox\Traits\Singleton;
use ContactInbox\Admin\Menu\AdminMenu;
use ContactInbox\Admin\AssetsDispatcher;
use ContactInbox\Frontend\Assets as FrontendAssets;
use ContactInbox\Core\CoreBootstrap;
use ContactInbox\Core\ProcessLock;
use ContactInbox\Integrations\IntegrationsBootstrap;
use ContactInbox\Cron\CronJobs;
use ContactInbox\Cli\CliBootstrap;
use ContactInbox\Core\Config;

// Admin Pages
use ContactInbox\Admin\Pages\Inbox;
use ContactInbox\Admin\Pages\Contacts;
use ContactInbox\Admin\Pages\Settings;
use ContactInbox\Admin\Pages\EmailLog;
use ContactInbox\Admin\Pages\AnalyticsDashboard;
use ContactInbox\Admin\Pages\Maintenance;
use ContactInbox\Admin\Pages\CRMSettingsPage;
use ContactInbox\Admin\Pages\RestApiIntegration;
use ContactInbox\Admin\Pages\GetStarted;
use ContactInbox\Core\OAuthCallbackHandler;
use ContactInbox\Admin\Helpers\UpgradeModalHelper;

// Dashboard
use ContactInbox\Admin\DashboardWidget;
use ContactInbox\Admin\SubmissionMetricsWidget;
use ContactInbox\Admin\IntegrationStatusWidget;
use ContactInbox\Admin\PerformanceMetricsWidget;
use ContactInbox\Admin\TodaySnapshotWidget;
use ContactInbox\Admin\QueueDashboardWidget;
use ContactInbox\Cron\AnalyticsAggregationJob;
use ContactInbox\Core\AnalyticsHooks;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class Plugin {
    use Singleton;

    /**
     * Keep constructor empty – lifecycle handled by init().
     */
    private function __construct() {}

    /**
     * Main initialization – runs on plugins_loaded.
     */
    public function init(): void {
        // Register lock cleanup on shutdown
        ProcessLock::register_cleanup();

        // Note: Conflict detection handled via activated_plugin hook in main plugin file

        // Plugin action links
        add_filter('plugin_action_links_' . CONTACTINBOX_BASENAME, [$this, 'add_action_links']);
        
        // Plugin row meta (View details, etc.)
        // Use high priority so we normalize legacy details links after other plugins/SDK filters.
        add_filter('plugin_row_meta', [$this, 'add_row_meta'], 999, 2);

        // 1) Admin menu
        AdminMenu::instance()->register();

        // 2) Assets
        AssetsDispatcher::instance();
        FrontendAssets::init();

    // 2b) Upgrade modal for free version
    UpgradeModalHelper::init();

        // 3) Core
        CoreBootstrap::instance()->boot();

        // 4) Integrations
        IntegrationsBootstrap::instance()->boot();

        // 5) Cron
        CronJobs::instance()->register();
        AnalyticsAggregationJob::instance();

        // 5b) Analytics hooks
        AnalyticsHooks::instance();

        // 6) CLI
        CliBootstrap::instance()->register();

        // 7) Admin pages with AJAX handlers
        Inbox::instance();
        Settings::instance();
        EmailLog::instance();
        Contacts::instance();
        CRMSettingsPage::instance();
        RestApiIntegration::instance();
        GetStarted::instance();

        // 8) Other admin pages (instantiate if they register hooks)
        AnalyticsDashboard::instance();
        Maintenance::instance();

        // 10) OAuth callback handler (if class exists)
        if (class_exists('ContactInbox\Core\OAuthCallbackHandler')) {
            OAuthCallbackHandler::init();
        }

        // 11) Dashboard widgets
        SubmissionMetricsWidget::instance();
        IntegrationStatusWidget::instance();
        QueueDashboardWidget::instance();

    // 12) Global hook – fire after everything is ready
        do_action( 'contactin_loaded', $this );
    }

    /**
     * Add "Get Started" link to plugin action links.
     * Inspired by Starter Templates plugin pattern.
     */
    public function add_action_links(array $links): array {
        $action_links = array();
        
        // Get Started link (primary action)
        $action_links['get-started'] = sprintf(
            '<a href="%s" aria-label="%s">%s</a>',
            esc_url(admin_url('admin.php?page=contactin-get-started')),
            esc_attr__('Get Started with Contact Inbox', 'contact-inbox'),
            esc_html__('Get Started', 'contact-inbox')
        );
        
        // Show Pro link only if Pro version is not active
        if ( ! is_plugin_active( 'contact-inbox-pro/contact-inbox.php' ) ) {
            $action_links['go-pro'] = sprintf(
                '<a href="%s" target="_blank" rel="noreferrer" style="color: #dd4f93; font-weight: 600;">%s</a>',
                esc_url('https://contactinbox.app/'),
                esc_html__('Get Pro', 'contact-inbox')
            );
        }
        
        return array_merge($action_links, $links);
    }

    /**
     * Add row meta links (View details, Documentation, etc.)
     *
     * @param array  $links Array of plugin meta links.
     * @param string $file  Plugin file path.
     * @return array Modified links array.
     */
    public function add_row_meta(array $links, string $file): array {
        if (CONTACTINBOX_BASENAME !== $file) {
            return $links;
        }

        $plugin_details_url = admin_url(
            'plugin-install.php?fs_allow_updater_and_dialog=true&tab=plugin-information&plugin=contact-inbox&TB_iframe=true&width=772&height=591'
        );

        foreach ($links as $index => $link) {
            if (!is_string($link)) {
                continue;
            }

            if (
                strpos($link, 'admin-ajax.php?action=contactin_free_plugin_details') !== false
                || strpos($link, 'admin-ajax.php?action=contactin_pro_plugin_details') !== false
                || strpos($link, 'admin-ajax.php?action=contactin_plugin_details') !== false
            ) {
                $links[$index] = preg_replace(
                    '#https?://[^"\']*/wp-admin/admin-ajax\.php\?action=contactin(?:_free|_pro)?_plugin_details(?:&amp;|&)[^"\']*#i',
                    esc_url($plugin_details_url),
                    $link
                );
            }
        }

        $row_meta = array(
            'docs' => sprintf(
                '<a href="%s" target="_blank" rel="noopener noreferrer" aria-label="%s">%s</a>',
                esc_url('https://github.com/bizjaved/contact-inbox#readme'),
                esc_attr__('View Contact Inbox documentation', 'contact-inbox'),
                esc_html__('Documentation', 'contact-inbox')
            ),
            'support' => sprintf(
                '<a href="%s" target="_blank" rel="noopener noreferrer" aria-label="%s">%s</a>',
                esc_url('https://github.com/bizjaved/contact-inbox/issues'),
                esc_attr__('Get support for Contact Inbox', 'contact-inbox'),
                esc_html__('Support', 'contact-inbox')
            ),
        );

        // Add Pro link for free version
        if ( ! is_plugin_active( 'contact-inbox-pro/contact-inbox.php' ) ) {
            $row_meta['upgrade'] = sprintf(
                '<a href="%s" target="_blank" rel="noopener noreferrer" aria-label="%s" style="color: #dd4f93; font-weight: 600;">%s</a>',
                esc_url('https://contactinbox.app/'),
                esc_attr__('Upgrade to Contact Inbox Pro', 'contact-inbox'),
                esc_html__('Upgrade to Pro', 'contact-inbox')
            );
        }

        return array_merge($links, $row_meta);
    }
}

