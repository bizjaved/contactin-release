<?php
declare(strict_types=1);

namespace ContactInbox;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Detects coexistence between free and premium versions.
 *
 * WordPress.org packages must not automatically deactivate other plugins,
 * so this class only reports the conflict and leaves activation decisions to
 * the site administrator.
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
	}

	/**
	 * Detect if both free and premium versions are active.
	 */
	public static function detect_conflict(): void {
		if ( ! function_exists( 'is_plugin_active' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		if ( self::has_conflict() ) {
			set_transient( 'contactinbox_plugin_conflict_detected', true, 60 );
		}
	}

	/**
	 * Show admin notice when both plugin variants are active.
	 */
	public static function show_conflict_notice(): void {
		$conflict_detected = get_transient( 'contactinbox_plugin_conflict_detected' );

		if ( ! $conflict_detected && ! self::has_conflict() ) {
			return;
		}

		delete_transient( 'contactinbox_plugin_conflict_detected' );

		?>
		<div class="notice notice-warning is-dismissible">
			<p>
				<strong><?php esc_html_e( 'ContactIn plugin conflict detected.', 'contactin' ); ?></strong>
				<?php esc_html_e( 'Both free and premium variants appear to be active. To comply with WordPress plugin rules, this plugin will not deactivate the other variant automatically. Please deactivate the version you do not want to use.', 'contactin' ); ?>
			</p>
		</div>
		<?php
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
