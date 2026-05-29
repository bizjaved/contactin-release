<?php
namespace ContactInbox\Admin;

use ContactInbox\Admin\Helpers\AdminRequest;
use ContactInbox\Traits\Singleton;
use ContactInbox\Core\Config;
use ContactInbox\Admin\Assets\{
	InboxAssets, SettingsAssets, EmailLogAssets,
	EditorAssets, AssetHelpers,
	AnalyticsWidgetsAssets, AnalyticsDashboardAssets, MaintenanceAssets,
	ContactDeletionAssets
};

// phpcs:disable WordPress.WP.I18n.NonSingularStringLiteralDomain, WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound, WordPress.Security.NonceVerification.Recommended

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class AssetsDispatcher {
	use Singleton;
	use AssetHelpers;

	/**
	 * Map of admin page hooks to asset handler classes.
	 * We store class names and instantiate lazily.
	 */
	private array $handlers      = array();
	private array $page_handlers = array();

	private function __construct() {
		$this->handlers = array(
			// Dashboard widgets (index.php is the dashboard)
			'index.php' => AnalyticsWidgetsAssets::class,
		);

		$this->page_handlers = array(
			'contactin-analytics'           => AnalyticsDashboardAssets::class,
			Config::MENU_INBOX              => InboxAssets::class,
			'contactin-inbox'               => InboxAssets::class,
			Config::MENU_INBOX_UNIFIED      => InboxAssets::class,
			Config::MENU_CONTACTS           => InboxAssets::class,
			Config::MENU_SETTINGS           => SettingsAssets::class,
			'contactin-settings'            => SettingsAssets::class,
			Config::MENU_MAINTENANCE        => MaintenanceAssets::class,
			'contactin-maintenance'         => MaintenanceAssets::class,
			Config::MENU_EMAIL_LOG          => EmailLogAssets::class,
			'contactin-email-log'           => EmailLogAssets::class,
		);

		add_action( 'admin_enqueue_scripts', array( $this, 'dispatch' ) );
		add_action( 'in_admin_header', array( $this, 'render_admin_branding' ) );
		add_action( 'elementor/editor/after_enqueue_styles', array( $this, 'enqueue_elementor_editor' ) );
		add_action( 'elementor/editor/after_enqueue_scripts', array( $this, 'enqueue_elementor_editor' ) ); // belt-and-suspenders for JS
		add_action( 'enqueue_block_editor_assets', array( $this, 'enqueue_gutenberg_editor' ) );
	}

	/**
	 * Dispatch asset enqueue for the current admin page.
	 */
	public function dispatch( string $hook ): void {
		$page           = $this->get_current_page_slug();
		$handler_class  = $this->resolve_handler_class( $hook, $page );
		$is_plugin_page = $this->is_contactin_admin_page( $hook, $page );

		if ( ! $is_plugin_page && null === $handler_class ) {
			return;
		}

		// Always enqueue shared admin assets on recognized plugin pages, even if
		// WordPress/Freemius generates an unexpected hook suffix.
		$this->enqueue_global();

		if ( null !== $handler_class ) {
			( new $handler_class() )->enqueue();
		}

		// Enqueue contact deletion assets for contacts page
		if ( in_array( $page, array( Config::MENU_CONTACTS, 'contactinbox-contacts' ), true ) ) {
			( new ContactDeletionAssets() )->enqueue();
		}
	}

	private function get_current_page_slug(): string {
		return AdminRequest::get_plugin_page_slug();
	}

	private function resolve_handler_class( string $hook, string $page ): ?string {
		if ( isset( $this->handlers[ $hook ] ) ) {
			return $this->handlers[ $hook ];
		}

		if ( $page !== '' && isset( $this->page_handlers[ $page ] ) ) {
			return $this->page_handlers[ $page ];
		}

		if ( $hook !== '' ) {
			foreach ( $this->page_handlers as $slug => $class ) {
				if ( strpos( $hook, $slug ) !== false ) {
					return $class;
				}
			}
		}

		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( $screen && ! empty( $screen->id ) ) {
			$screen_id = (string) $screen->id;

			foreach ( $this->page_handlers as $slug => $class ) {
				if ( strpos( $screen_id, $slug ) !== false ) {
					return $class;
				}
			}
		}

		return null;
	}

	/**
	 * Enqueue global admin assets shared across plugin admin pages.
	 */
	private function enqueue_global(): void {
		$handle = 'contactin-admin-global';

		// CSS: dist/css/admin-global.min.css
		$this->register_style( $handle, 'admin-global.min.css', array(), Config::VERSION );

		if ( $this->is_contactin_admin_page() ) {
			$logo_mark_path = CONTACTINBOX_PATH . 'assets/logo-512.png';
			$logo_mark_url  = CONTACTINBOX_URL . 'assets/logo-512.png';

			if ( ! file_exists( $logo_mark_path ) ) {
				$logo_mark_path = CONTACTINBOX_PATH . 'assets/logo-256.png';
				$logo_mark_url  = CONTACTINBOX_URL . 'assets/logo-256.png';
			}

			if ( file_exists( $logo_mark_path ) ) {
				$logo_mark_url = add_query_arg( 'ver', (string) filemtime( $logo_mark_path ), $logo_mark_url );
			}

			$logo_mark_url = esc_url( $logo_mark_url );

			wp_add_inline_style(
				$handle,
				'.contactin-admin-branding{display:flex;align-items:center;gap:10px;margin:10px 0 12px;padding:0 0 8px;border-bottom:1px solid ' . Config::COLOR_BORDER_SUBTLE . '}.contactin-admin-branding__mark{width:26px;height:26px;display:block;flex:0 0 26px}.contactin-admin-branding__title{font-size:15px;font-weight:600;color:' . Config::COLOR_TEXT_STRONG . ';line-height:1;margin:0}.contactin-admin-branding__subtitle{font-size:12px;color:' . Config::COLOR_TEXT_MUTED . ";line-height:1;margin-top:4px}.contactin-admin-branding__meta{display:flex;flex-direction:column}.contactin-admin-branding + .wrap h1,.contactin-admin-branding + .wrap .wp-heading-inline{margin-top:0}.contactin-admin-branding__mark{background:url('{$logo_mark_url}') center/contain no-repeat}"
			);
		}

		// JS: dist/js/admin-global.min.js
		$this->register_script( $handle, 'admin-global.min.js', array( 'jquery' ), Config::VERSION );
	}

	private function is_contactin_admin_page( string $hook = '', string $page = '' ): bool {
		if ( ! is_admin() ) {
			return false;
		}

		if ( $page === '' ) {
			$page = $this->get_current_page_slug();
		}

		if ( $page !== '' ) {
			return str_starts_with( $page, 'contactin' )
				|| str_starts_with( $page, 'contactinbox' )
				|| str_starts_with( $page, 'contactin' );
		}

		if ( $hook !== '' ) {
			return strpos( $hook, 'contactin' ) !== false
				|| strpos( $hook, 'contactinbox' ) !== false
				|| strpos( $hook, 'contactin' ) !== false;
		}

		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( ! $screen || empty( $screen->id ) ) {
			return false;
		}

		$screen_id = (string) $screen->id;
		return strpos( $screen_id, 'contactin' ) !== false || strpos( $screen_id, 'contactin' ) !== false;
	}

	public function render_admin_branding(): void {
		if ( ! $this->is_contactin_admin_page() ) {
			return;
		}

		echo '<div class="contactin-admin-branding">';
		echo '<span class="contactin-admin-branding__mark" aria-hidden="true"></span>';
		echo '<div class="contactin-admin-branding__meta">';
		echo '<p class="contactin-admin-branding__title">' . esc_html__( 'ContactIn', 'contactin' ) . '</p>';
		echo '<p class="contactin-admin-branding__subtitle">' . esc_html__( 'Never miss a message. Never lose a lead.', 'contactin' ) . '</p>';
		echo '</div>';
		echo '</div>';
	}

	/**
	 * Enqueue Elementor editor assets.
	 */
	public function enqueue_elementor_editor(): void {
		( new EditorAssets() )->enqueue_elementor();
	}

	/**
	 * Enqueue Gutenberg editor assets.
	 */
	public function enqueue_gutenberg_editor(): void {
		( new EditorAssets() )->enqueue_gutenberg();
	}
}
