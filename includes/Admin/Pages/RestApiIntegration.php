<?php
namespace ContactInbox\Admin\Pages;

use ContactInbox\Traits\Singleton;
use ContactInbox\Core\Config;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
// phpcs:disable WordPress.WP.I18n.NonSingularStringLiteralDomain, WordPress.WP.I18n.MissingTranslatorsComment, WordPress.Security.ValidatedSanitizedInput.MissingUnslash, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized, WordPress.Security.NonceVerification.Recommended, WordPress.Security.NonceVerification.Missing, WordPress.WP.I18n.UnorderedPlaceholdersText

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * REST API Integration page for managing API keys, tokens, and monitoring.
 *
 * Displays:
 * - Active API tokens/keys with management options
 * - Base endpoint URL and documentation
 * - Health status and connection indicators
 * - Usage statistics and metrics
 * - Links to related pages (test, logs)
 */
final class RestApiIntegration {
	use Singleton;

	/**
	 * Constructor intentionally left empty.
	 *
	 * The REST API Integration screen is UI-only in this build.
	 */
	protected function __construct() {
		// No-op: backend interactions are disabled on this page.
	}


	/**
	 * Render the REST API Integration page.
	 * Instantiates the Singleton first to register AJAX handlers.
	 */
	public static function render(): void {
		if ( ! current_user_can( Config::CAPABILITY ) ) {
			wp_die( esc_html__( 'Permission denied.', 'contactin' ) );
		}
		self::render_frontend_only_page();
	}

	/**
	 * Display the REST API Integration page content.
	 */
	private static function render_frontend_only_page(): void {
		self::enqueue_assets();
		include CONTACTINBOX_PATH . Config::TEMPLATE_ADMIN . 'restapi-integration-page.php';
	}

	/**
	 * Enqueue CSS and JS for this page.
	 */
	private static function enqueue_assets(): void {
		$asset_class = new \ContactInbox\Admin\Assets\RestApiIntegrationAssets();
		$asset_class->enqueue();
	}

	/**
	 * Get active REST API tokens from RestApiRoutes.
	 *
	 * @return array List of active tokens with metadata (keyed by token ID).
	 */
	public static function get_active_tokens(): array {
		return array();
	}

	/**
	 * Get REST API health/usage statistics.
	 *
	 * @return array Health metrics including last call, success rate, etc.
	 */
	public static function get_health_stats(): array {
		return array(
			'is_enabled'       => false,
			'last_call_time'   => null,
			'last_endpoint'    => null,
			'calls_24h'        => 0,
			'success_24h'      => 0,
			'success_rate_24h' => 0,
			'calls_7d'         => 0,
			'success_7d'       => 0,
			'success_rate_7d'  => 0,
		);
	}

}
