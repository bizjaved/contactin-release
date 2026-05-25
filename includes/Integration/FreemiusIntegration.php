<?php
declare(strict_types=1);

namespace ContactInbox\Integration;

// phpcs:disable WordPress.WP.I18n.TextDomainMismatch, WordPress.PHP.DevelopmentFunctions.error_log_error_log
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Freemius Integration Handler
 *
 * Manages all Freemius-related functionality for plugin licensing,
 * updates, and analytics.
 *
 * @package ContactIn\Integration
 */
final class FreemiusIntegration {

	public const LICENSE_STATE_PREMIUM = 'premium';
	public const LICENSE_STATE_EXPIRED = 'expired';
	public const LICENSE_STATE_FREE    = 'free';

	/**
	 * Freemius SDK instance
	 *
	 * @var object|null
	 */
	private static $freemius = null;

	/**
	 * Whether Freemius hooks have been registered.
	 *
	 * @var bool
	 */
	private static bool $hooks_registered = false;

	/**
	 * Initialize Freemius integration
	 * Called from main plugin file before anything else
	 *
	 * @return void
	 */
	public static function initialize(): void {
		self::init_freemius();
	}

	/**
	 * Check if Freemius SDK is available
	 *
	 * @return bool
	 */
	public static function is_sdk_available(): bool {
		if ( function_exists( 'contactin_fs' ) ) {
			return true;
		}

		return file_exists( CONTACTINBOX_PATH . 'vendor/autoload.php' );
	}

	/**
	 * Load Freemius SDK
	 *
	 * @return void
	 */
	private static function load_sdk(): void {
		if ( function_exists( 'contactin_fs' ) ) {
			return;
		}

		if ( ! class_exists( 'Freemius' ) ) {
			require_once CONTACTINBOX_PATH . 'vendor/autoload.php';
		}
	}

	/**
	 * Initialize Freemius with plugin configuration
	 *
	 * @return object|null The Freemius instance
	 */
	private static function init_freemius(): ?object {
		if ( null !== self::$freemius ) {
			return self::$freemius;
		}

		try {
			if ( function_exists( 'contactin_fs' ) ) {
				$instance = contactin_fs();

				if ( is_object( $instance ) ) {
					self::register_hooks();
					self::$freemius = $instance;

					return self::$freemius;
				}
			}

			// Only initialize directly when helper is unavailable.
			if ( ! self::is_sdk_available() ) {
				return null;
			}

			// Load Freemius SDK
			self::load_sdk();

			// Freemius configuration — mirrors the canonical fs_dynamic_init in contactin.php.
			$freemius_config = array(
				'id'                              => self::get_freemius_id(),
				'slug'                            => 'contactin',
				'premium_slug'                    => 'contactin-pro',
				'type'                            => 'plugin',
				'public_key'                      => self::get_freemius_public_key(),
				'is_premium'                      => false,
				'is_live'                         => self::is_live_environment(),
				'has_premium_version'             => true,
				'has_addons'                      => false,
				'has_paid_plans'                  => true,
				'is_org_compliant'                => true,
				'trial'                           => array(
					'days'               => 30,
					'is_require_payment' => false,
				),
				'menu'                            => array(
					'slug'    => 'contactin-settings',
					'contact' => false,
					'support' => false,
					'parent'  => array(
						'slug' => 'contactin-analytics',
					),
				),
				'is_after_install_set_up_on_init' => false,
			);

			// Register Freemius hooks before initialization
			self::register_hooks();

			// Initialize Freemius
			self::$freemius = fs_dynamic_init( $freemius_config );

			return self::$freemius;
		} catch ( \Throwable $e ) {
			error_log( '[ContactIn] Freemius initialization error: ' . $e->getMessage() );
			return null;
		}
	}

	/**
	 * Register Freemius hooks
	 *
	 * @return void
	 */
	private static function register_hooks(): void {
		if ( self::$hooks_registered ) {
			return;
		}

		// Hook after Freemius init — pro build slug.
		add_action(
			'fs_after_init_contactin-pro',
			array( self::class, 'on_freemius_init' )
		);

		// Hook after Freemius init — free build slug.
		// NOTE: Do NOT share on_freemius_init here because the free-build instance
		// would overwrite the premium instance stored in self::$freemius.
		// Instead handle free-build state separately via the same callback but
		// the instance guard inside on_freemius_init prevents overwrite.
		add_action(
			'fs_after_init_contactin',
			array( self::class, 'on_freemius_init' )
		);

		// Hook after plugin activation
		add_action(
			'fs_after_install_contactin-pro',
			array( self::class, 'on_freemius_install' )
		);

		// Hook for license change on pro slug (renewal, cancellation, new activation).
		add_action(
			'fs_after_license_change_contactin-pro',
			array( self::class, 'on_license_change' )
		);

		// Hook for license change on free slug (covers: subscription cancelled/resumed
		// when the free version is the one connected to Freemius account).
		add_action(
			'fs_after_license_change_contactin',
			array( self::class, 'on_license_change' )
		);

		// Hook for first-time premium version activation (fresh pro-build install).
		add_action(
			'fs_after_premium_version_activation_contactin-pro',
			array( self::class, 'on_premium_activation' )
		);

		// Self-healing: re-schedule premium crons if the license is active but crons are missing.
		// Catches the case where a license was renewed while an older plugin version was active
		// (before the lifecycle hooks were correctly registered), leaving crons unscheduled.
		add_action( 'admin_init', array( self::class, 'maybe_heal_premium_workloads' ) );

		self::$hooks_registered = true;
	}

	/**
	 * Callback when Freemius is initialized
	 *
	 * @param \FS_Site|null $freemius Freemius instance
	 * @return void
	 */
	public static function on_freemius_init( $freemius ): void {
		if ( null === $freemius ) {
			return;
		}

		// Only store the instance if it is the premium-capable one, or if we
		// don't have any instance yet. This prevents the free-build slug hook
		// (fs_after_init_contactin) from overwriting a valid premium instance
		// with a non-premium one and making all subsequent license checks fail.
		$is_premium_instance = method_exists( $freemius, 'is_premium' ) && (bool) $freemius->is_premium();
		if ( null === self::$freemius || $is_premium_instance ) {
			self::$freemius = $freemius;
		}

		// Set plugin icon/banner if available
		self::set_plugin_branding( $freemius );

		// If the Freemius instance itself confirms this is the premium build,
		// proactively clear any stale "non-premium restrictions applied" transient
		// so the notice disappears and enforcement does not block features on this
		// and subsequent page loads — without waiting for admin_init or the heal
		// throttle to expire.
		if ( $is_premium_instance ) {
			delete_transient( 'contactin_non_premium_restrictions_applied' );
		}

		// Do NOT call enforce_non_premium_restrictions() here.
		// At Freemius init time the license state may not yet be fully resolved,
		// causing crons to be cleared on sites that DO have an active premium license.
		// Enforcement is handled reliably by:
		// 1. on_license_change() — fires when license actually changes
		// 2. maybe_heal_premium_workloads() — admin_init self-heal (runs eagerly when crons are missing)
	}

	/**
	 * Callback when plugin is installed via Freemius
	 *
	 * @param \FS_Site|null $freemius Freemius instance
	 * @return void
	 */
	public static function on_freemius_install( $freemius ): void {
		// Track installation event
		do_action( 'contact_inbox_plugin_installed', $freemius );

		// Set up default license options
		update_option( 'contact_inbox_freemius_installed', current_time( 'mysql' ) );
	}

	/**
	 * Callback when the premium version is activated for the first time.
	 *
	 * Freemius fires fs_after_premium_version_activation_{slug} on fresh
	 * pro-build installs where fs_after_license_change may not fire.
	 *
	 * @param object|null $freemius Freemius instance.
	 * @return void
	 */
	public static function on_premium_activation( $freemius ): void {
		if ( is_object( $freemius ) ) {
			self::$freemius = $freemius;
		}

		update_option( 'contact_inbox_license_changed', current_time( 'mysql' ) );
		delete_transient( 'contact_inbox_license_status' );
		delete_transient( 'contactin_cron_health_check_throttle' );

		if ( self::are_all_features_enabled() ) {
			self::restore_premium_workloads();
		}
	}

	/**
	 * Callback when license changes
	 *
	 * @param \FS_Site|null $freemius Freemius instance
	 * @return void
	 */
	public static function on_license_change( $freemius ): void {
		if ( null === $freemius ) {
			return;
		}

		if ( is_object( $freemius ) ) {
			self::$freemius = $freemius;
		}

		// Update activation record
		update_option( 'contact_inbox_license_changed', current_time( 'mysql' ) );

		// Clear any license-related transients
		delete_transient( 'contact_inbox_license_status' );
		delete_transient( 'contactin_cron_health_check_throttle' );

		if ( ! self::are_all_features_enabled() ) {
			// Clear the transient so enforcement runs immediately after this change.
			delete_transient( 'contactin_non_premium_restrictions_applied' );
			self::disable_premium_workloads();
		} else {
			// License renewed/reactivated — re-enable everything that was shut down.
			self::restore_premium_workloads();
		}
	}

	/**
	 * Set plugin branding (icon, banner, etc)
	 *
	 * @param \FS_Site $freemius Freemius instance
	 * @return void
	 */
	private static function set_plugin_branding( $freemius ): void {
		$icon_path   = CONTACTINBOX_PATH . 'assets/icon.png';
		$banner_path = CONTACTINBOX_PATH . 'assets/banner.png';

		// Set icon if available
		if ( file_exists( $icon_path ) ) {
			$freemius->set_icon( $icon_path );
		}

		// Set banner if available
		if ( file_exists( $banner_path ) ) {
			$freemius->set_banner( $banner_path );
		}
	}

	/**
	 * Get Freemius plugin ID
	 *
	 * @return string
	 */
	public static function get_freemius_id(): string {
		return apply_filters( 'contact_inbox_freemius_id', '24327' );
	}

	/**
	 * Get Freemius public key
	 *
	 * @return string
	 */
	public static function get_freemius_public_key(): string {
		return apply_filters( 'contact_inbox_freemius_public_key', 'pk_dc7a7dfca50227a8404ef8029f6eb' );
	}

	/**
	 * Check if running in live environment
	 *
	 * @return bool
	 */
	public static function is_live_environment(): bool {
		// Auto-detect: treat local/dev hosts as non-live, everything else as live.
		$host     = sanitize_text_field( wp_unslash( $_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME'] ?? '' ) );
		$is_local = (
			$host === 'localhost'
			|| str_ends_with( $host, '.local' )
			|| str_ends_with( $host, '.test' )
			|| str_ends_with( $host, '.dev' )
			|| preg_match( '/^(\d{1,3}\.){3}\d{1,3}(:\d+)?$/', $host )
		);
		return apply_filters( 'contact_inbox_freemius_is_live', ! $is_local );
	}

	/**
	 * Get Freemius instance
	 *
	 * @return object|null
	 */
	public static function get_instance(): ?object {
		return self::$freemius;
	}

	/**
	 * Resolve the current Freemius instance safely.
	 *
	 * @return object|null
	 */
	private static function get_freemius_instance(): ?object {
		if ( null !== self::$freemius ) {
			return self::$freemius;
		}

		if ( function_exists( 'contactin_fs' ) ) {
			$instance = contactin_fs();

			if ( is_object( $instance ) ) {
				self::$freemius = $instance;
				return self::$freemius;
			}
		}

		return null;
	}

	/**
	 * Check if user has valid license
	 *
	 * @return bool
	 */
	public static function has_valid_license(): bool {
		$freemius = self::get_freemius_instance();

		if ( null === $freemius ) {
			return false;
		}

		try {
			if ( method_exists( $freemius, 'is_active' ) ) {
				return (bool) $freemius->is_active();
			}

			if ( method_exists( $freemius, 'get_site' ) ) {
				return null !== $freemius->get_site();
			}
		} catch ( \Throwable $e ) {
			error_log( '[ContactIn] Error checking license validity: ' . $e->getMessage() );
		}

		return false;
	}

	/**
	 * Check whether feature-gated functionality should execute.
	 *
	 * WordPress.org directory packages must not keep already-implemented
	 * functionality behind license checks. This method therefore defaults to
	 * allowing all implemented features, while still exposing a filter for
	 * integrators that need custom behavior outside wordpress.org distributions.
	 *
	 * @return bool
	 */
	public static function are_all_features_enabled(): bool {
		return (bool) apply_filters( 'contact_inbox_can_use_all_features', true );
	}

	/**
	 * Check if user has pro license
	 *
	 * Freemium model: Returns true if user has active paid plan
	 *
	 * @return bool
	 */
	public static function has_pro_license(): bool {
		return self::are_all_features_enabled();
	}

	/**
	 * Get normalized license/runtime state used across UI and workload gating.
	 *
	 * @return string One of self::LICENSE_STATE_* constants.
	 */
	public static function get_license_state(): string {
		if ( self::are_all_features_enabled() ) {
			return self::LICENSE_STATE_PREMIUM;
		}

		// Determine whether the pro build is running.
		// Use the directory-name check first; fall back to Freemius SDK's own
		// is_premium() flag (set when fs_dynamic_init is called with is_premium=>true).
		// This prevents the "Free plan active" notice from showing on pro installs
		// where the plugin folder may have a non-standard name, or where the
		// Freemius instance wasn't yet stored at the time of the check.
		$freemius              = self::get_freemius_instance();
		$fs_says_premium_build = null !== $freemius
			&& method_exists( $freemius, 'is_premium' )
			&& (bool) $freemius->is_premium();

		if ( self::is_pro_plugin_build() || $fs_says_premium_build ) {
			return self::LICENSE_STATE_EXPIRED;
		}

		return self::LICENSE_STATE_FREE;
	}

	/**
	 * Check if plugin is in expired-license state (Pro build installed, premium access unavailable).
	 *
	 * @return bool
	 */
	public static function is_expired_license_state(): bool {
		return self::LICENSE_STATE_EXPIRED === self::get_license_state();
	}

	/**
	 * Check if plugin is running in free-plan state.
	 *
	 * @return bool
	 */
	public static function is_free_plan_state(): bool {
		return self::LICENSE_STATE_FREE === self::get_license_state();
	}

	/**
	 * Check if plugin is in a non-premium state.
	 *
	 * Covers both:
	 * - Pro build with expired/inactive license
	 * - Free build without premium access
	 *
	 * @return bool
	 */
	public static function is_non_premium_state(): bool {
		return self::LICENSE_STATE_PREMIUM !== self::get_license_state();
	}

	/**
	 * Get the canonical primary CTA URL for non-premium state.
	 *
	 * Expired => renew/activate flow.
	 * Free    => upgrade flow.
	 *
	 * @return string
	 */
	public static function get_non_premium_primary_url(): string {
		return self::is_expired_license_state()
			? self::get_activate_license_url()
			: self::get_upgrade_url( 'non_premium_notice' );
	}

	/**
	 * Get canonical primary CTA label for non-premium state.
	 *
	 * @return string
	 */
	public static function get_non_premium_primary_label(): string {
		return self::is_expired_license_state()
			? __( 'Renew License', 'contactin' )
			: __( 'Upgrade to Pro', 'contactin' );
	}

	/**
	 * Check whether the currently running plugin package is the Pro build.
	 *
	 * @return bool
	 */
	private static function is_pro_plugin_build(): bool {
		// The plugin folder name varies by distribution channel:
		// - Dev/repo install : contactin-pro/
		// - Freemius zip     : contact-inbox-pro/
		// Both slugs are valid pro builds.
		if ( defined( 'CONTACTINBOX_BASENAME' ) ) {
			$basename = (string) CONTACTINBOX_BASENAME;
			return 0 === strpos( $basename, 'contactin-pro/' )
				|| 0 === strpos( $basename, 'contact-inbox-pro/' );
		}

		if ( defined( 'CONTACTINBOX_PATH' ) ) {
			$path = (string) CONTACTINBOX_PATH;
			return false !== strpos( $path, '/contactin-pro/' )
				|| false !== strpos( $path, '/contact-inbox-pro/' );
		}

		return false;
	}

	/**
	 * Enforce non-premium restrictions for both free builds and expired-license states.
	 *
	 * Uses a short-lived transient so the DB write runs once per plan-state
	 * transition rather than on every page load.
	 *
	 * @return void
	 */
	public static function enforce_non_premium_restrictions(): void {
		// Only act when premium features are genuinely unavailable.
		if ( self::are_all_features_enabled() ) {
			return;
		}

		$transient_key = 'contactin_non_premium_restrictions_applied';

		// Skip if already enforced recently (5-minute grace window).
		if ( get_transient( $transient_key ) ) {
			return;
		}

		self::disable_premium_workloads();

		// Record enforcement; expire after 5 minutes so re-checks are light.
		set_transient( $transient_key, '1', 5 * MINUTE_IN_SECONDS );
	}

	/**
	 * Disable background workloads after license downgrade/expiry or free activation.
	 *
	 * @return void
	 */
	private static function disable_premium_workloads(): void {
		$premium_crons = array(
			\ContactInbox\Core\Config::CRON_PROCESS_CRM,
			\ContactInbox\Core\Config::CRON_RECLASSIFY_UNCLASSIFIED,
			\ContactInbox\Core\Config::CRON_LEARN_FROM_FEEDBACK,
		);

		foreach ( $premium_crons as $hook ) {
			wp_clear_scheduled_hook( $hook );
		}

		$neutralized = self::neutralize_premium_queue_items();

		if ( $neutralized > 0 && defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log( '[ContactIn] Neutralized premium queue items (non-premium plan active): ' . $neutralized );
		}
	}

	/**
	 * Self-healing check: if Freemius reports premium access but premium crons are
	 * missing, restore them automatically. Runs on admin_init, throttled to once
	 * per hour so it adds no perceptible overhead.
	 *
	 * This recovers sites that renewed a license while an older plugin version
	 * (without the lifecycle hook registration fix) was active.
	 *
	 * @return void
	 */
	public static function maybe_heal_premium_workloads(): void {
		if ( ! self::are_all_features_enabled() ) {
			// Not premium — nothing to heal. Ensure the enforcement transient is
			// cleared so the next license change triggers a fresh enforcement.
			// Do NOT call disable_premium_workloads() here — that happens via
			// on_license_change() when the license actually changes.
			return;
		}

		// Premium is active.
		// 1. Always clear any stale "non-premium restrictions applied" transient so
		// enforcement logic does not block premium features on the next page load.
		delete_transient( 'contactin_non_premium_restrictions_applied' );

		$premium_crons = array(
			\ContactInbox\Core\Config::CRON_PROCESS_CRM,
			\ContactInbox\Core\Config::CRON_LEARN_FROM_FEEDBACK,
			\ContactInbox\Core\Config::CRON_RECLASSIFY_UNCLASSIFIED,
		);

		$any_missing = false;
		foreach ( $premium_crons as $hook ) {
			if ( ! wp_next_scheduled( $hook ) ) {
				$any_missing = true;
				break;
			}
		}

		if ( $any_missing ) {
			// Crons are missing — heal immediately regardless of throttle.
			// Delete stale throttle transient so it resets after the heal.
			delete_transient( 'contactin_premium_heal_check' );
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				error_log( '[ContactIn] Self-heal: premium crons missing despite active license — restoring.' );
			}
			self::restore_premium_workloads();
			// Throttle subsequent checks from this point (all healthy now).
			set_transient( 'contactin_premium_heal_check', 1, HOUR_IN_SECONDS );
			return;
		}

		// All crons present — throttle idle health checks to once per hour.
		if ( get_transient( 'contactin_premium_heal_check' ) ) {
			return;
		}
		set_transient( 'contactin_premium_heal_check', 1, HOUR_IN_SECONDS );
	}

	/**
	 * Re-enable background workloads after license renewal/reactivation.
	 *
	 * Clears blocking transients so the next request re-schedules crons and
	 * resets any queue items that were neutralized during the expired period.
	 *
	 * @return void
	 */
	private static function restore_premium_workloads(): void {
		// Allow enforcement logic to run fresh on next page load.
		delete_transient( 'contactin_non_premium_restrictions_applied' );
		// Also clear the cron health throttle so CronJobs re-checks immediately.
		delete_transient( 'contactin_cron_health_check_throttle' );

		// Resolve intervals from stored options — same source of truth as CronJobs.
		$crm_interval = get_option(
			'contactin_crm_queue_interval',
			get_option( 'contactin_queue_interval', 'contactin_fifteen_minutes' )
		);
		$schedules    = wp_get_schedules();
		if ( ! isset( $schedules[ $crm_interval ] ) ) {
			$crm_interval = isset( $schedules['contactin_fifteen_minutes'] ) ? 'contactin_fifteen_minutes' : 'hourly';
		}
		$learning_interval = isset( $schedules['weekly'] ) ? 'weekly' : 'daily';

		// Re-schedule premium crons that were cleared on expiry.
		$premium_crons = array(
			\ContactInbox\Core\Config::CRON_PROCESS_CRM => $crm_interval,
			\ContactInbox\Core\Config::CRON_LEARN_FROM_FEEDBACK => $learning_interval,
			\ContactInbox\Core\Config::CRON_RECLASSIFY_UNCLASSIFIED => 'daily',
		);

		foreach ( $premium_crons as $hook => $interval ) {
			if ( ! wp_next_scheduled( $hook ) ) {
				wp_schedule_event( time() + 60, $interval, $hook );
			}
		}

		// Restore queue items that were neutralized during the expired window.
		$restored = self::restore_premium_queue_items();

		if ( $restored > 0 && defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log( '[ContactIn] Restored ' . $restored . ' premium queue items after license renewal.' );
		}
	}

	/**
	 * Reset premium queue items that were neutralized during expiry back to pending
	 * so they are retried now that the license is active again.
	 *
	 * Only resets items whose last_error indicates they were skipped due to
	 * license expiry (not items that failed for genuine reasons).
	 *
	 * @return int Number of rows updated.
	 */
	private static function restore_premium_queue_items(): int {
		global $wpdb;

		$table_queue = $wpdb->prefix . \ContactInbox\Core\Config::TABLE_QUEUE;

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return (int) $wpdb->query(
			$wpdb->prepare(
                // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name is plugin-owned and built from trusted prefix + constant.
				"UPDATE {$table_queue}
                SET status     = %s,
                    retry_count = %d,
                    last_error  = NULL,
                    next_attempt = %s,
                    updated_at  = %s
                WHERE type IN (%s, %s, %s)
                    AND status   = %s
                    AND last_error = %s",
				'pending',
				0,
				current_time( 'mysql' ),
				current_time( 'mysql' ),
				'crm',
				'attachment_retry',
				'crm_delete',
				'completed',
				'Skipped because premium access is unavailable.'
			)
		);
	}

	/**
	 * Mark queued work as skipped so it stops retrying after expiry.
	 *
	 * @return int
	 */
	private static function neutralize_premium_queue_items(): int {
		global $wpdb;

		$table_queue = $wpdb->prefix . \ContactInbox\Core\Config::TABLE_QUEUE;

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return (int) $wpdb->query(
			$wpdb->prepare(
                // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name is plugin-owned and built from trusted prefix + constant.
				"UPDATE {$table_queue}
                SET status = %s,
                    retry_count = %d,
                    last_error = %s,
                    next_attempt = NULL,
                    updated_at = %s
                WHERE type IN (%s, %s, %s)
                    AND status IN (%s, %s, %s)",
				'completed',
				0,
				'Skipped because premium access is unavailable.',
				current_time( 'mysql' ),
				'crm',
				'attachment_retry',
				'crm_delete',
				'pending',
				'retry',
				'processing'
			)
		);
	}

	/**
	 * Get license info
	 *
	 * @return array<string, mixed>
	 */
	public static function get_license_info(): array {
		$freemius = self::get_freemius_instance();

		if ( null === $freemius ) {
			return array( 'status' => 'uninitialized' );
		}

		try {
			$user      = method_exists( $freemius, 'get_user' ) ? $freemius->get_user() : null;
			$site      = method_exists( $freemius, 'get_site' ) ? $freemius->get_site() : null;
			$plan      = method_exists( $freemius, 'get_plan' ) ? $freemius->get_plan() : null;
			$is_active = method_exists( $freemius, 'is_active' )
				? (bool) $freemius->is_active()
				: self::has_valid_license();

			return array(
				'is_active'   => $is_active,
				'license_key' => ( is_object( $site ) && isset( $site->license_key ) ) ? $site->license_key : null,
				'user_email'  => ( is_object( $user ) && isset( $user->email ) ) ? $user->email : null,
				'plan_name'   => ( is_object( $plan ) && isset( $plan->name ) ) ? $plan->name : 'free',
				'is_pro'      => self::has_pro_license(),
				'has_valid'   => self::has_valid_license(),
			);
		} catch ( \Throwable $e ) {
			error_log( '[ContactIn] Error getting license info: ' . $e->getMessage() );
			return array( 'status' => 'error' );
		}
	}

	/**
	 * Track custom event in Freemius
	 *
	 * @param string               $event_name Event identifier
	 * @param array<string, mixed> $data Event data
	 * @return void
	 */
	public static function track_event( string $event_name, array $data = array() ): void {
		if ( null === self::$freemius ) {
			return;
		}

		try {
			self::$freemius->track_event( $event_name, $data );
		} catch ( \Throwable $e ) {
			error_log( '[ContactIn] Error tracking event: ' . $e->getMessage() );
		}
	}

	/**
	 * Get upgrade URL for pro features
	 *
	 * @param string $context What triggered the upgrade prompt
	 * @return string
	 */
	public static function get_upgrade_url( string $context = '' ): string {
		if ( self::is_expired_license_state() ) {
			return self::get_activate_license_url();
		}

		if ( null === self::$freemius ) {
			return admin_url( 'admin.php?page=contactin-pro' );
		}

		try {
			$url = self::$freemius->get_upgrade_url();

			if ( ! empty( $context ) ) {
				$url = add_query_arg( 'context', urlencode( $context ), $url );
			}

			return $url;
		} catch ( \Throwable $e ) {
			return admin_url( 'admin.php?page=contactin-pro' );
		}
	}

	/**
	 * Get features available in free plan.
	 *
	 * @return array<int, string>
	 */
	public static function get_free_plan_features(): array {
		$features = array(
			'form_builder',
			'inbox',
			'contacts',
			'keyword_intent_classifier',
			'analytics_core',
			'smtp',
			'deliverability_checks',
			'queue_retries',
			'queue_deduplication',
			'gdpr_consent',
			'gdpr_retention',
			'gdpr_export',
			'recaptcha',
			'honeypot',
			'throttling_baseline',
		);

		return apply_filters( 'contact_inbox_free_features', $features );
	}

	/**
	 * Get features available only in premium plan.
	 *
	 * @return array<int, string>
	 */
	public static function get_premium_plan_features(): array {
		$features = array(
			'attachments',
			'profile_attachment_toggle',   // show_attachment toggle per form profile
			'profile_email_routing',       // notify_email per form profile
			'crm_sync',
			'crm_field_mapping',
			'crm_bidirectional_updates',
			'crm_delete_queue',
			'rest_api',
			'webhooks',
			'ml_learning',
			'ai_confidence',
			'advanced_rules',
			'advanced_rate_limits',
			'ip_allowlist',
			'ip_blocklist',
			'gdpr_delete_tokens',
			'intent_reclassification',
		);

		return apply_filters( 'contact_inbox_premium_features', $features );
	}

	/**
	 * Check if feature is available in current plan.
	 *
	 * @param string $feature Feature identifier.
	 * @return bool
	 */
	public static function is_feature_enabled( string $feature ): bool {
		$normalized_feature = sanitize_key( str_replace( array( '.', ':' ), '_', strtolower( trim( $feature ) ) ) );

		if ( '' === $normalized_feature ) {
			return false;
		}

		if ( in_array( $normalized_feature, self::get_free_plan_features(), true ) ) {
			return true;
		}

		if ( in_array( $normalized_feature, self::get_premium_plan_features(), true ) ) {
			return apply_filters( 'contact_inbox_is_feature_enabled', self::are_all_features_enabled(), $normalized_feature );
		}

		return apply_filters( 'contact_inbox_is_feature_enabled', self::are_all_features_enabled(), $normalized_feature );
	}

	/**
	 * Get Freemius admin URL
	 *
	 * @return string
	 */
	public static function get_account_url(): string {
		if ( null === self::$freemius ) {
			return admin_url( 'admin.php?page=contactin-pro-account' );
		}

		try {
			return self::$freemius->get_account_url();
		} catch ( \Throwable $e ) {
			return admin_url( 'admin.php?page=contactin-pro-account' );
		}
	}

	/**
	 * Get Freemius account URL with license activation flow enabled.
	 *
	 * @return string
	 */
	public static function get_activate_license_url(): string {
		$renewal_fallback = admin_url( 'admin.php?page=contactin-settings-account&activate_license=true' );

		if ( null === self::$freemius ) {
			return $renewal_fallback;
		}

		try {
			if ( method_exists( self::$freemius, 'get_renewal_url' ) ) {
				$url = self::$freemius->get_renewal_url();
				if ( ! empty( $url ) ) {
					return self::enforce_premium_renewal_context( $url );
				}
			}

			if ( self::is_expired_license_state()
				&& method_exists( self::$freemius, '_get_license' )
				&& method_exists( self::$freemius, 'checkout_url' )
			) {
				$license = self::$freemius->_get_license();

				if ( is_object( $license ) && isset( $license->quota ) && isset( $license->id ) ) {
					$billing_cycle = 'annually';

					if ( method_exists( $license, 'is_lifetime' ) && $license->is_lifetime() ) {
						$billing_cycle = 'lifetime';
					} elseif ( method_exists( self::$freemius, '_get_subscription' ) ) {
						$subscription = self::$freemius->_get_subscription( $license->id );
						if ( is_object( $subscription ) && isset( $subscription->billing_cycle ) ) {
							$billing_cycle = ( 1 === (int) $subscription->billing_cycle ) ? 'monthly' : 'annually';
						}
					}

					$url = self::$freemius->checkout_url(
						$billing_cycle,
						false,
						array(
							'licenses' => (int) $license->quota,
						)
					);

					if ( ! empty( $url ) ) {
						return self::enforce_premium_renewal_context( $url );
					}
				}
			}

			if ( method_exists( self::$freemius, 'get_account_url' ) ) {
				$url = self::$freemius->get_account_url( false, array( 'activate_license' => 'true' ) );
				if ( ! empty( $url ) ) {
					return self::enforce_premium_renewal_context( $url );
				}
			}

			return self::enforce_premium_renewal_context( $renewal_fallback );
		} catch ( \Throwable $e ) {
			return self::enforce_premium_renewal_context( $renewal_fallback );
		}
	}

	/**
	 * Keep expired-license recovery in renewal + premium context.
	 */
	private static function enforce_premium_renewal_context( string $url ): string {
		if ( '' === $url ) {
			return $url;
		}

		$args = array(
			'activate_license' => 'true',
			'renew'            => '1',
			'trial'            => '0',
			'is_premium'       => '1',
		);

		if ( null !== self::$freemius && method_exists( self::$freemius, 'get_plan' ) ) {
			try {
				$plan = self::$freemius->get_plan();
				if ( is_object( $plan ) && isset( $plan->id ) ) {
					$args['plan_id'] = (string) $plan->id;
				}
			} catch ( \Throwable $e ) { // phpcs:ignore Generic.CodeAnalysis.EmptyStatement.DetectedCatch -- Intentional: plan details unavailable in this context.
				// Ignore: plan details unavailable in this context.
			}
		}

		return add_query_arg( $args, $url );
	}

	/**
	 * Opt in to Freemius tracking
	 *
	 * @return void
	 */
	public static function opt_in_tracking(): void {
		if ( null === self::$freemius ) {
			return;
		}

		try {
			self::$freemius->allow_tracking();
		} catch ( \Throwable $e ) {
			error_log( '[ContactIn] Error opting in to tracking: ' . $e->getMessage() );
		}
	}

	/**
	 * Opt out from Freemius tracking
	 *
	 * @return void
	 */
	public static function opt_out_tracking(): void {
		if ( null === self::$freemius ) {
			return;
		}

		try {
			self::$freemius->deny_tracking();
		} catch ( \Throwable $e ) {
			error_log( '[ContactIn] Error opting out of tracking: ' . $e->getMessage() );
		}
	}

	// =========================================================================
	// UI Helpers — PRO badges & upgrade notices
	// =========================================================================

	/**
	 * Echo a PRO badge when the current state is non-premium.
	 *
	 * Variants:
	 *   ''         – default nav-tab / inline size  (red,   10 px)
	 *   'heading'  – section / card heading         (red,   12 px)
	 *   'button'   – inside a button label          (red,   11 px)
	 *   'field'    – beside a form-field label      (amber,  9 px)
	 *
	 * Usage in templates:
	 *   <?php FreemiusIntegration::echo_pro_badge(); ?>
	 *   <?php FreemiusIntegration::echo_pro_badge( 'heading' ); ?>
	 *   <?php FreemiusIntegration::echo_pro_badge( 'field' ); ?>
	 *
	 * @param string $variant Badge size/colour variant.
	 * @return void
	 */
	public static function echo_pro_badge( string $variant = '' ): void {
		if ( self::are_all_features_enabled() ) {
			return;
		}

		$class = 'cin-pro-badge';

		switch ( $variant ) {
			case 'heading':
				$class .= ' cin-pro-badge--heading';
				break;
			case 'button':
				$class .= ' cin-pro-badge--button';
				break;
			case 'field':
				$class .= ' cin-pro-badge--field';
				break;
		}

		echo '<span class="' . esc_attr( $class ) . '">PRO</span>';
	}

	/**
	 * Echo a standardised "Pro Feature" upgrade-notice block.
	 *
	 * Only renders when the current state is non-premium.
	 * Automatically shows "Renew License" copy for expired-license state
	 * and "Upgrade to Pro" copy for the free plan.
	 *
	 * Usage in templates:
	 *   <?php FreemiusIntegration::echo_upgrade_notice(
	 *       'Advanced Rate Limiting and IP Controls are available in Pro.',
	 *       'rate_limits'
	 *   ); ?>
	 *
	 * @param string $feature_description  Human-readable feature description.
	 * @param string $upgrade_context      Context key passed to get_upgrade_url().
	 * @return void
	 */
	public static function echo_upgrade_notice( string $feature_description, string $upgrade_context = 'pro_notice' ): void {
		if ( self::are_all_features_enabled() ) {
			return;
		}

		$url   = self::get_upgrade_url( $upgrade_context );
		$label = self::is_expired_license_state()
			? __( 'Renew License \u2192', 'contactin' )
			: __( 'Upgrade to Pro \u2192', 'contactin' );

		echo '<div class="notice notice-info inline cin-upgrade-notice">';
		echo '<p>';
		echo '<strong>' . esc_html__( 'Pro Feature', 'contactin' ) . '</strong> ';
		echo esc_html( $feature_description ) . ' ';
		echo '<a href="' . esc_url( $url ) . '" target="_blank" rel="noopener">' . esc_html( $label ) . '</a>';
		echo '</p>';
		echo '</div>';
	}
}
