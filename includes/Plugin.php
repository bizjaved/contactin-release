<?php

/**
 * ContactIn – Main Plugin Class (Refactored)
 *
 * Enterprise-Grade – Clean, fast, secure, modular, future-proof.
 *
 * Ensures all AJAX handlers are registered on every admin request,
 * including admin-ajax.php.
 *
 * @package ContactIn
 */

namespace ContactInbox;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

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
use ContactInbox\Admin\Pages\RestLog;
use ContactInbox\Admin\Pages\RestApiIntegration;
use ContactInbox\Admin\Pages\CRMSettingsPage;
use ContactInbox\Admin\Pages\CRMDashboard;
use ContactInbox\Admin\Pages\AnalyticsDashboard;
use ContactInbox\Admin\Pages\Maintenance;
use ContactInbox\Admin\Pages\GetStarted;
use ContactInbox\Admin\GDPRHandler;
use ContactInbox\Admin\ProfileManagerCore;
use ContactInbox\Core\OAuthCallbackHandler;
use ContactInbox\Admin\Pages\FormProfilesPage;

// Dashboard
use ContactInbox\Admin\DashboardWidget;
use ContactInbox\Admin\SubmissionMetricsWidget;
use ContactInbox\Admin\IntegrationStatusWidget;
use ContactInbox\Admin\PerformanceMetricsWidget;
use ContactInbox\Admin\TodaySnapshotWidget;
use ContactInbox\Admin\QueueDashboardWidget;
use ContactInbox\Cron\AnalyticsAggregationJob;
use ContactInbox\Cron\QueueHealthMonitor;
use ContactInbox\Core\AnalyticsHooks;
use ContactInbox\Core\ServerHealthChecker;
use ContactInbox\Integration\FreemiusIntegration;

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
		// 0a) Bootstrap Freemius integration FIRST so all license-lifecycle hooks
		// (fs_after_license_change, fs_after_premium_version_activation, etc.)
		// are registered before WordPress fires them on 'init' / 'admin_init'.
		FreemiusIntegration::initialize();

		// 0b) Server health check
		add_action( 'admin_init', array( ServerHealthChecker::class, 'check_server_health' ) );

		// Register lock cleanup on shutdown
		ProcessLock::register_cleanup();

		// Note: Conflict detection handled via activated_plugin hook in main plugin file

		// Plugin action links
		add_filter( 'plugin_action_links_' . CONTACTINBOX_BASENAME, array( $this, 'add_action_links' ) );

		// Note: Plugin row meta is registered early in contactin.php (main plugin file)
		// so it works even when the plugin is deactivated - similar to Elementor approach

		// 1) Admin menu
		AdminMenu::instance()->register();

		// 2) Assets
		ProfileManagerCore::register_hooks();
		AssetsDispatcher::instance();
		FrontendAssets::init();

		// 3) Core
		CoreBootstrap::instance()->boot();

		// 4) Integrations
		IntegrationsBootstrap::instance()->boot();

		// 5) Cron
		CronJobs::instance()->register();
		AnalyticsAggregationJob::instance();
		QueueHealthMonitor::instance();  // NEW: Prevents queue stalls and stuck locks

		// 5b) Analytics hooks
		AnalyticsHooks::instance();

		// 6) CLI
		CliBootstrap::instance()->register();

		// 7) Admin pages with AJAX handlers
		Inbox::instance();
		Settings::instance();
		FormProfilesPage::instance();
		EmailLog::instance();
		RestLog::instance();
		\ContactInbox\Admin\Pages\GDPRLog::instance();
		// PREMIUM FEATURE: GDPR delete functionality only in Pro
		if ( FreemiusIntegration::can_use_premium_features() ) {
			GDPRHandler::instance();
		}
		Contacts::instance();
		GetStarted::instance();
		// Note: PluginInfo is not instantiated here - plugin API is handled at top-level in contactin.php

		// 8) Integration pages - PREMIUM FEATURES
		if ( FreemiusIntegration::can_use_premium_features() ) {
			RestApiIntegration::instance();
		}

		// 9) Other admin pages (instantiate if they register hooks)
		// PREMIUM FEATURE: CRM Integration
		if ( FreemiusIntegration::can_use_premium_features() ) {
			CRMSettingsPage::instance();
			CRMDashboard::instance();
			\ContactInbox\Admin\Pages\CRMLog::instance();
		}
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

	/**
	 * Add "Get Started" link to plugin action links.
	 * Inspired by Starter Templates plugin pattern.
	 */
	public function add_action_links( array $links ): array {
		$action_links = array();

		// Get Started link (primary action)
		$action_links['get-started'] = sprintf(
			'<a href="%s" aria-label="%s" style="color: #667eea; font-weight: 600;">%s</a>',
			esc_url( admin_url( 'admin.php?page=contactin-get-started' ) ),
			esc_attr__( 'Get Started with ContactIn', 'contactin' ),
			esc_html__( 'Get Started', 'contactin' )
		);

		return array_merge( $action_links, $links );
	}
}
