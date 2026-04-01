<?php
namespace ContactInbox\Core;

use ContactInbox\Traits\Singleton;
use ContactInbox\Core\{DB, SMTP, ReCAPTCHA, GDPR};
use ContactInbox\Frontend\{Shortcode, FormHandler};
use ContactInbox\Admin\Pages\Settings;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// phpcs:disable WordPress.WP.I18n.TextDomainMismatch, WordPress.PHP.DevelopmentFunctions.error_log_error_log

final class CoreBootstrap {
	use Singleton;

	public function boot(): void {
		// Register custom cron schedules (must run early)
		add_filter( 'cron_schedules', array( $this, 'register_custom_schedules' ) );

		DB::instance();
		FormHandler::instance();
		SMTP::instance();
		ReCAPTCHA::instance();
		Shortcode::instance();
		Settings::instance();

		add_action( 'init', array( GDPR::class, 'instance' ) );

		// Run lightweight post-submit homework asynchronously (after user sees success)
		add_action( 'contactin_post_submit_homework', array( QueueTrigger::class, 'run_post_submit_homework' ), 10, 2 );

		// Check and update intent patterns on every page load (caches comparison)
		$this->check_intent_patterns_version();

		// Note: CronJobs::register() is intentionally called in Plugin::init(),
		// not here, to avoid double-registering all cron action hooks.
	}

	/**
	 * Check if intent patterns need updating (on version upgrades)
	 * Uses transient to avoid constant checks
	 */
	private function check_intent_patterns_version(): void {
		// Check only once per 24 hours with transient
		if ( get_transient( 'contactin_patterns_check_done' ) ) {
			return;
		}

		$plugin_version   = Config::VERSION;
		$current_version  = get_option( 'contactin_plugin_version', '0' );
		$patterns_version = get_option( 'contactin_intent_patterns_version', '0' );
		$is_first_install = get_option( 'contactin_patterns_installed' ) === false;

		// Update patterns if:
		// 1. Plugin version changed (upgrade scenario)
		// 2. Patterns not properly installed
		// 3. Pattern verification fails
		if ( $current_version !== $plugin_version || $is_first_install ) {
			$result = IntentClassifier::install_patterns();

			if ( ! $result['success'] ) {
				error_log( '[ContactIn] Pattern update check failed: ' . implode( ', ', $result['errors'] ) );
			} elseif ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				error_log( '[ContactIn] Pattern update check passed: ' . $result['message'] );
			}

			update_option( 'contactin_plugin_version', $plugin_version );
		}

		// Verify pattern integrity
		$health = IntentClassifier::health_check();
		if ( ! $health['status']['verified'] ) {
			error_log( '[ContactIn] Pattern integrity check failed: ' . wp_json_encode( $health['verification']['errors'] ) );

			// Attempt recovery from backup
			$recovery = IntentClassifier::restore_from_backup();
			if ( ! $recovery['success'] ) {
				// Last resort: reinstall from scratch
				IntentClassifier::reset_to_defaults();
				error_log( '[ContactIn] Patterns restored from backup, then reset to defaults' );
			}
		}

		// Set transient for 24 hours to avoid repeated checks
		set_transient( 'contactin_patterns_check_done', true, 24 * HOUR_IN_SECONDS );
	}

	/**
	 * Register custom cron schedules.
	 * Must be registered on every load for WordPress to recognize them.
	 */
	public function register_custom_schedules( $schedules ): array {
		$schedules['contactin_one_minute'] = array(
			// phpcs:ignore WordPress.WP.CronInterval.CronSchedulesInterval -- Intentional: queue/post-submit homework needs near-real-time processing for reliable async follow-up.
			'interval' => 60,
			'display'  => esc_html__( 'Every 1 minute', 'contactin' ),
		);

		$schedules['contactin_two_minutes'] = array(
			'interval' => 120,
			'display'  => esc_html__( 'Every 2 minutes', 'contactin' ),
		);

		$schedules['contactin_five_minutes'] = array(
			'interval' => 300,
			'display'  => esc_html__( 'Every 5 minutes', 'contactin' ),
		);

		$schedules['contactin_fifteen_minutes'] = array(
			'interval' => 900,
			'display'  => esc_html__( 'Every 15 minutes', 'contactin' ),
		);

		return $schedules;
	}
}
