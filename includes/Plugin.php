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

        // Conflict detection (must run early to prevent dual activation)
        PluginConflictDetector::init();

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

        // 8) Other admin pages (instantiate if they register hooks)
        AnalyticsDashboard::instance();
        Maintenance::instance();

    // 10) OAuth callback handler
        OAuthCallbackHandler::init();

    // 11) Dashboard widgets
        SubmissionMetricsWidget::instance();
        IntegrationStatusWidget::instance();
        QueueDashboardWidget::instance();

    // 12) Global hook – fire after everything is ready
        do_action( 'contactin_loaded', $this );
    }
}
