<?php
declare(strict_types=1);

namespace ContactInbox;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Detects and prevents conflicts when both free and premium versions are active.
 *
 * This class ensures a smooth upgrade path by:
 * - Detecting when free version is active
 * - Auto-deactivating free version when premium is activated
 * - Showing admin notices about the transition
 * - Preserving all data during the transition
 */
final class PluginConflictDetector {

	/**
	 * Plugin slugs for detection
	 */
	private const FREE_PLUGIN    = 'contactin/contactin.php';
	private const PREMIUM_PLUGIN = 'contactin-pro/contactin.php';

	/**
	 * Initialize conflict detection hooks
	 */
	public static function init(): void {
		// Check for conflicts on admin pages
		add_action( 'admin_init', array( self::class, 'detect_conflict' ) );

		// Show admin notices
		add_action( 'admin_notices', array( self::class, 'show_conflict_notice' ) );

		// Handle premium version activation - deactivate free if it's active
		if ( ! ( defined( 'CONTACTINBOX_IS_FREE' ) && CONTACTINBOX_IS_FREE ) ) {
			// This fires when the PREMIUM version is activated
			add_action( 'activated_plugin', array( self::class, 'on_premium_activated' ), 5, 2 );
		}
	}

	/**
	 * Detect if both free and premium versions are active
	 */
	public static function detect_conflict(): void {
		if ( ! function_exists( 'is_plugin_active' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		$is_free = defined( 'CONTACTINBOX_IS_FREE' ) && CONTACTINBOX_IS_FREE;

		// Premium version: if free is active, deactivate it
		if ( ! $is_free && is_plugin_active( self::FREE_PLUGIN ) ) {
			deactivate_plugins( self::FREE_PLUGIN, true );

			// Set transient to show notice
			set_transient( 'contactinbox_free_auto_deactivated', true, 60 );
		}
	}

	/**
	 * Show admin notice when conflict is detected or auto-deactivation occurs
	 */
	public static function show_conflict_notice(): void {
		// Check if we just auto-deactivated the free version
		if ( get_transient( 'contactinbox_free_auto_deactivated' ) ) {
			delete_transient( 'contactinbox_free_auto_deactivated' );

			$is_free = defined( 'CONTACTINBOX_IS_FREE' ) && CONTACTINBOX_IS_FREE;

			if ( ! $is_free ) {
				// Premium version talking to user
				?>
				<div class="notice notice-success is-dismissible">
					<p>
						<strong>ContactIn activated!</strong> 
						The free version has been automatically deactivated to prevent conflicts. 
						All your data, settings, and messages have been preserved.
					</p>
				</div>
				<?php
			}
		}
	}

	/**
	 * Handle when premium plugin is activated while free is active
	 *
	 * @param string $plugin Path to the plugin file relative to the plugins directory
	 * @param bool   $network_wide Whether to enable the plugin for all sites in the network
	 */
	public static function on_premium_activated( string $plugin, bool $network_wide ): void {
		// Check if the PREMIUM version was just activated
		if ( $plugin === self::PREMIUM_PLUGIN ) {
			// Deactivate the free version if it's active
			if ( ! function_exists( 'is_plugin_active' ) ) {
				require_once ABSPATH . 'wp-admin/includes/plugin.php';
			}

			if ( is_plugin_active( self::FREE_PLUGIN ) ) {
				deactivate_plugins( self::FREE_PLUGIN, true );
				set_transient( 'contactinbox_free_auto_deactivated', true, 60 );
			}
		}
	}

	/**
	 * Check if the other version is active
	 *
	 * @return bool True if conflict exists
	 */
	public static function has_conflict(): bool {
		if ( ! function_exists( 'is_plugin_active' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		$is_free = defined( 'CONTACTINBOX_IS_FREE' ) && CONTACTINBOX_IS_FREE;

		if ( $is_free ) {
			return is_plugin_active( self::PREMIUM_PLUGIN );
		}

		return is_plugin_active( self::FREE_PLUGIN );
	}

	/**
	 * Get the name of the conflicting plugin
	 *
	 * @return string|null Plugin name or null if no conflict
	 */
	public static function get_conflicting_plugin(): ?string {
		if ( ! self::has_conflict() ) {
			return null;
		}

		$is_free = defined( 'CONTACTINBOX_IS_FREE' ) && CONTACTINBOX_IS_FREE;

		return $is_free ? 'ContactIn (Free)' : 'ContactIn Pro';
	}
}
