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
use ContactInbox\Admin\Pages\GetStarted;
use ContactInbox\Admin\ProfileManagerCore;
use ContactInbox\Admin\Pages\FormProfilesPage;

// Dashboard
use ContactInbox\Admin\DashboardWidget;
use ContactInbox\Admin\SubmissionMetricsWidget;
use ContactInbox\Admin\PerformanceMetricsWidget;
use ContactInbox\Admin\TodaySnapshotWidget;
use ContactInbox\Admin\QueueDashboardWidget;
use ContactInbox\Cron\AnalyticsAggregationJob;
use ContactInbox\Cron\QueueHealthMonitor;
use ContactInbox\Core\AnalyticsHooks;
use ContactInbox\Core\ServerHealthChecker;
use ContactInbox\Admin\SupportBoxesManager;

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
		// 0a) Server health check
		add_action( 'admin_init', array( ServerHealthChecker::class, 'check_server_health' ) );
		add_action( 'admin_notices', array( SupportBoxesManager::class, 'maybe_render_temporary_admin_review_notice' ) );

		// Register lock cleanup on shutdown
		ProcessLock::register_cleanup();

		// Note: Conflict detection handled via activated_plugin hook in main plugin file

		// Plugin action links
		add_filter( 'plugin_action_links_' . CONTACTINBOX_BASENAME, array( $this, 'add_action_links' ) );
		add_filter( 'plugin_row_meta', array( $this, 'add_plugin_row_meta' ), 20, 2 );

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
		Contacts::instance();
		GetStarted::instance();
		// Note: PluginInfo is not instantiated here - plugin API is handled at top-level in contactin.php

		// 8) Other admin pages (instantiate if they register hooks)
		AnalyticsDashboard::instance();
		Maintenance::instance();

		// 9) Dashboard widgets
		SubmissionMetricsWidget::instance();
		QueueDashboardWidget::instance();

		// 11) Global hook – fire after everything is ready
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

		$action_links['compare-pro'] = sprintf(
			'<a href="%s" target="_blank" rel="noopener noreferrer" aria-label="%s">%s</a>',
			esc_url( $this->get_pro_upgrade_url( 'plugin_action_links' ) ),
			esc_attr__( 'Compare ContactIn Free and Pro plans (opens in a new tab)', 'contactin' ),
			esc_html__( 'Compare Free vs Pro', 'contactin' )
		);

		return array_merge( $action_links, $links );
	}

	/**
	 * Add a lightweight, policy-safe upgrade link to plugin row meta.
	 *
	 * @param array  $links Existing plugin row links.
	 * @param string $file  Plugin basename.
	 * @return array
	 */
	public function add_plugin_row_meta( array $links, string $file ): array {
		if ( $file !== CONTACTINBOX_BASENAME ) {
			return $links;
		}

		$links['contactin-upgrade'] = sprintf(
			'<a href="%s" target="_blank" rel="noopener noreferrer" aria-label="%s">%s</a>',
			esc_url( $this->get_pro_upgrade_url( 'plugin_row_meta' ) ),
			esc_attr__( 'Learn about ContactIn Pro features (opens in a new tab)', 'contactin' ),
			esc_html__( 'Upgrade to Pro', 'contactin' )
		);

		return $links;
	}

	/**
	 * Build the canonical upgrade URL with attribution parameters.
	 *
	 * @param string $source Source identifier for analytics.
	 * @return string
	 */
	private function get_pro_upgrade_url( string $source ): string {
		return add_query_arg(
			array(
				'utm_source'   => 'wp_admin',
				'utm_medium'   => 'plugin_ui',
				'utm_campaign' => 'contactin_free_to_pro',
				'utm_content'  => sanitize_key( $source ),
			),
			'https://contactinbox.app/'
		);
	}
}
