<?php
declare(strict_types=1);

namespace ContactInbox\Admin;

use ContactInbox\Core\Config;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Support Boxes Manager
 *
 * Handles conditional display of feedback, review, and upgrade boxes
 * based on version (free vs pro) and usage time.
 *
 * @package ContactIn\Admin
 */
final class SupportBoxesManager {
	private const REVIEW_TOP_IMPRESSIONS_META_KEY = 'contactinbox_wp_review_top_impressions';
	private const REVIEW_TOP_LAST_SHOWN_META_KEY  = 'contactinbox_wp_review_top_last_shown';
	private const REVIEW_TOP_MAX_IMPRESSIONS      = 3;
	private const REVIEW_TOP_COOLDOWN_SECONDS     = 259200; // 3 days.
	private const REVIEW_TOP_RANDOM_PERCENT       = 35;
	private const TEMP_GLOBAL_ADMIN_REVIEW_PROMPT = false;

	/**
	 * Installation date option key
	 */
	const INSTALLATION_DATE_KEY             = 'contactinbox_installation_date';
	const PRO_FEEDBACK_ACTION_PARAM         = 'contactinbox_feedback_box_action';
	const PRO_FEEDBACK_NONCE_PARAM          = '_cin_fb_nonce';
	const FEEDBACK_BOX_PARAM                = 'contactinbox_feedback_box';
	const PRO_FEEDBACK_NONCE_ACTION         = 'cin_feedback_box_action';
	const FEEDBACK_REDIRECT_PARAM           = 'contactinbox_feedback_redirect_to';
	const REVIEW_BOX_TEST_PARAM             = 'contactinbox_test_review_box';
	const REVIEW_BOX_TEST_NONCE_PARAM       = '_cin_review_test_nonce';
	const REVIEW_BOX_TEST_NONCE_ACTION      = 'cin_review_box_test_mode';
	const PRO_FEEDBACK_MIN_DAYS             = 14;
	const PRO_FEEDBACK_SNOOZE_DAYS          = 7;
	const FEEDBACK_META_HIDDEN_UNTIL_SUFFIX = '_hidden_until';
	const FEEDBACK_META_DISMISSED_AT_SUFFIX = '_dismissed_at';

	/**
	 * Check if this is the free build.
	 *
	 * @return bool True if free version, false if pro build.
	 */
	public static function is_free_version(): bool {
		if ( defined( 'CONTACTINBOX_IS_FREE' ) ) {
			return (bool) CONTACTINBOX_IS_FREE;
		}

		return true;
	}

	/**
	 * Check if pro build.
	 *
	 * @return bool True if pro build, false if free
	 */
	public static function is_pro_version(): bool {
		return ! self::is_free_version();
	}

	/**
	 * Licensing states are disabled in this build.
	 *
	 * @return bool
	 */
	private static function is_non_premium_state(): bool {
		return false;
	}

	/**
	 * Check if 14 days have passed since installation
	 *
	 * Uses a transient/option to track first installation time.
	 * Shows review box after 14 days of usage.
	 *
	 * @return bool True if 14+ days have passed since installation
	 */
	public static function should_show_review_box(): bool {
		if ( self::is_review_box_test_mode() ) {
			return true;
		}

		// Always show to pro users (they chose to pay)
		if ( self::is_pro_version() ) {
			return false; // Pro users don't see review box
		}

		$installation_date = get_option( self::INSTALLATION_DATE_KEY );

		// If no date set, set it now
		if ( false === $installation_date ) {
			update_option( self::INSTALLATION_DATE_KEY, time(), false );
			// Don't show on first day
			return false;
		}

		// Calculate days since installation
		$days_passed = ( time() - (int) $installation_date ) / DAY_IN_SECONDS;

		return $days_passed >= 14;
	}

	/**
	 * Record installation date (called on plugin activation)
	 *
	 * @return void
	 */
	public static function record_installation_date(): void {
		if ( ! get_option( self::INSTALLATION_DATE_KEY ) ) {
			update_option( self::INSTALLATION_DATE_KEY, time(), false );
		}
	}

	/**
	 * Get which boxes to display (returns array of box names)
	 *
	 * @return array List of box identifiers to show
	 */
	public static function get_boxes_to_display(): array {
		$boxes = array();

		// WordPress.org packages must not advertise locked or premium-only
		// functionality from the installed build.
		if ( self::is_non_premium_state() ) {
			return $boxes;
		}

		if ( self::is_free_version() ) {
			if ( self::should_show_review_box() && self::should_show_box_for_current_user( 'wordpress-review' ) ) {
				$boxes[] = 'wordpress-review'; // Show after 14 days
			}
		} else {
			// Pro version boxes
			if ( self::should_show_pro_feedback_box() ) {
				$boxes[] = 'pro-feedback'; // Website contact-us based support & feedback
			}
		}

		return $boxes;
	}

	/**
	 * Render support boxes for a page
	 *
	 * @param string $context Context where boxes are being rendered (e.g., 'inbox', 'settings')
	 * @return void
	 */
	public static function render_support_boxes( string $context = '' ): bool {
		static $dismiss_script_printed = false;

		self::maybe_handle_feedback_box_action();

		$boxes = self::get_boxes_to_display();

		if ( empty( $boxes ) ) {
			return false;
		}

		if ( 'settings-top' === $context && ! self::should_show_settings_boxes_at_top( $boxes ) ) {
			return false;
		}

		$template_path = CONTACTINBOX_PATH . Config::TEMPLATE_ADMIN_PART;

		/**
		 * Filter: Allow modifying boxes to display
		 *
		 * @param array  $boxes List of box identifiers
		 * @param string $context Where boxes are being rendered
		 */
		$boxes = apply_filters( 'contactinbox_support_boxes', $boxes, $context );
		$boxes = array_values( array_diff( $boxes, array( 'expired-license' ) ) );

		if ( empty( $boxes ) ) {
			return false;
		}

		$rendered_any = false;

		?>
		<div class="cin-support-boxes" style="width: 100%; margin: 12px 0;">
			<?php foreach ( $boxes as $box_id ) : ?>
				<?php
				if ( ! self::should_render_box_in_context( $box_id, $context ) ) {
					continue;
				}
				?>
				<div class="cin-support-box" style="max-width: 60%; margin: 0 auto 12px auto;">
					<?php $rendered_any = true; ?>
					<?php
					switch ( $box_id ) {
						case 'upgrade-pro':
							load_template( $template_path . 'upgrade-pro-box.php' );
							break;
						case 'wordpress-review':
							load_template( $template_path . 'wordpress-review-box.php' );
							break;
						case 'github-feedback':
							load_template( $template_path . 'github-review-box.php' );
							break;
						case 'pro-feedback':
							load_template( $template_path . 'pro-feedback-box.php' );
							break;
					}
					?>
				</div>
			<?php endforeach; ?>
		</div>
		<?php

		if ( $rendered_any && 'settings-top' === $context ) {
			self::record_settings_top_impression();
		}

		if ( ! $dismiss_script_printed ) {
			$dismiss_script_printed = true;
			?>
			<script>
			(function() {
				if (window.__cinSupportDismissBound) {
					return;
				}
				window.__cinSupportDismissBound = true;

				document.addEventListener('click', function(event) {
					var target = event.target;
					if (!target) {
						return;
					}

					var dismissLink = target.closest('a[data-cin-support-action="dismiss"]');
					if (!dismissLink) {
						return;
					}

					event.preventDefault();

					var wrapper = dismissLink.closest('.cin-support-box');
					var requestUrl = dismissLink.getAttribute('href');
					if (!requestUrl) {
						if (wrapper) {
							wrapper.remove();
						}
						return;
					}

					fetch(requestUrl, {
						method: 'GET',
						credentials: 'same-origin',
						cache: 'no-store',
						redirect: 'follow'
					}).finally(function() {
						if (wrapper) {
							wrapper.remove();
						}
					});
				});
			})();
			</script>
			<?php
		}

		return $rendered_any;
	}

	/**
	 * Temporary admin-wide top notice for occasional WordPress review prompts.
	 *
	 * @return void
	 */
	public static function maybe_render_temporary_admin_review_notice(): void {
		if ( ! self::is_temporary_global_review_notice_enabled() ) {
			return;
		}

		if ( ! is_admin() || ! current_user_can( Config::CAPABILITY ) ) {
			return;
		}

		if ( function_exists( 'wp_doing_ajax' ) && wp_doing_ajax() ) {
			return;
		}

		if ( function_exists( 'wp_doing_cron' ) && wp_doing_cron() ) {
			return;
		}

		$boxes = self::get_boxes_to_display();
		if ( empty( $boxes ) || ! self::should_show_settings_boxes_at_top( $boxes ) ) {
			return;
		}

		self::render_support_boxes( 'admin-global-top' );
	}

	/**
	 * Temporary feature switch for global admin review prompt testing.
	 *
	 * @return bool
	 */
	public static function is_temporary_global_review_notice_enabled(): bool {
		return self::TEMP_GLOBAL_ADMIN_REVIEW_PROMPT;
	}

	/**
	 * Determine whether the Pro feedback box should be shown to the current user.
	 *
	 * @return bool
	 */
	private static function should_show_pro_feedback_box(): bool {
		if ( ! self::is_pro_version() ) {
			return false;
		}

		$installation_date = get_option( self::INSTALLATION_DATE_KEY );

		if ( false === $installation_date ) {
			update_option( self::INSTALLATION_DATE_KEY, time(), false );
			return false;
		}

		$days_passed = ( time() - (int) $installation_date ) / DAY_IN_SECONDS;
		if ( $days_passed < self::PRO_FEEDBACK_MIN_DAYS ) {
			return false;
		}

		return self::should_show_box_for_current_user( 'pro-feedback' );
	}

	/**
	 * Determine whether a support box should be shown for the current user.
	 *
	 * @param string $box_id
	 * @return bool
	 */
	private static function should_show_box_for_current_user( string $box_id ): bool {
		if ( 'wordpress-review' === $box_id && self::is_review_box_test_mode() ) {
			return true;
		}

		if ( ! self::is_feedback_box_id_allowed( $box_id ) ) {
			return true;
		}

		$user_id = get_current_user_id();
		if ( $user_id <= 0 ) {
			return true;
		}

		$dismissed_at = (int) get_user_meta( $user_id, self::get_box_dismissed_meta_key( $box_id ), true );
		if ( $dismissed_at > 0 ) {
			return false;
		}

		$hidden_until = (int) get_user_meta( $user_id, self::get_box_hidden_until_meta_key( $box_id ), true );
		if ( $hidden_until > time() ) {
			return false;
		}

		return true;
	}

	/**
	 * Handle per-user support box actions (snooze/dismiss).
	 *
	 * @return void
	 */
	private static function maybe_handle_feedback_box_action(): void {
		if ( ! is_admin() || ! current_user_can( Config::CAPABILITY ) ) {
			return;
		}

		$action = isset( $_GET[ self::PRO_FEEDBACK_ACTION_PARAM ] )
			? sanitize_key( wp_unslash( (string) $_GET[ self::PRO_FEEDBACK_ACTION_PARAM ] ) )
			: '';

		$box_id = isset( $_GET[ self::FEEDBACK_BOX_PARAM ] )
			? sanitize_key( wp_unslash( (string) $_GET[ self::FEEDBACK_BOX_PARAM ] ) )
			: '';

		if ( ! in_array( $action, array( 'snooze', 'dismiss' ), true ) || ! self::is_feedback_box_id_allowed( $box_id ) ) {
			return;
		}

		$nonce = isset( $_GET[ self::PRO_FEEDBACK_NONCE_PARAM ] )
			? sanitize_text_field( wp_unslash( (string) $_GET[ self::PRO_FEEDBACK_NONCE_PARAM ] ) )
			: '';

		if ( ! wp_verify_nonce( $nonce, self::PRO_FEEDBACK_NONCE_ACTION ) ) {
			return;
		}

		$requested_redirect = isset( $_GET[ self::FEEDBACK_REDIRECT_PARAM ] )
			? sanitize_text_field( wp_unslash( (string) $_GET[ self::FEEDBACK_REDIRECT_PARAM ] ) )
			: '';

		$user_id = get_current_user_id();
		if ( $user_id <= 0 ) {
			return;
		}

		if ( 'snooze' === $action ) {
			update_user_meta( $user_id, self::get_box_hidden_until_meta_key( $box_id ), time() + ( self::PRO_FEEDBACK_SNOOZE_DAYS * DAY_IN_SECONDS ) );
		}

		if ( 'dismiss' === $action ) {
			update_user_meta( $user_id, self::get_box_dismissed_meta_key( $box_id ), time() );
			delete_user_meta( $user_id, self::get_box_hidden_until_meta_key( $box_id ) );
		}

		$redirect_url = self::get_requested_redirect_url( $requested_redirect );

		$redirect_url = remove_query_arg(
			array( self::PRO_FEEDBACK_ACTION_PARAM, self::PRO_FEEDBACK_NONCE_PARAM, self::FEEDBACK_BOX_PARAM ),
			$redirect_url
		);
		$redirect_url = wp_validate_redirect( $redirect_url, admin_url( 'admin.php?page=' . Config::MENU_SETTINGS ) );

		wp_safe_redirect( $redirect_url );
		exit;
	}

	/**
	 * Build a nonce-protected action URL for a specific support box.
	 *
	 * @param string $box_id
	 * @param string $action snooze|dismiss
	 * @return string
	 */
	public static function get_box_action_url( string $box_id, string $action ): string {
		if ( ! self::is_feedback_box_id_allowed( $box_id ) || ! in_array( $action, array( 'snooze', 'dismiss' ), true ) ) {
			return '';
		}

		$current_url = self::get_current_admin_request_url();

		$action_url = add_query_arg(
			array(
				self::PRO_FEEDBACK_ACTION_PARAM => $action,
				self::FEEDBACK_BOX_PARAM        => $box_id,
				self::FEEDBACK_REDIRECT_PARAM   => rawurlencode( $current_url ),
			),
			$current_url
		);

		return wp_nonce_url( $action_url, self::PRO_FEEDBACK_NONCE_ACTION, self::PRO_FEEDBACK_NONCE_PARAM );
	}

	/**
	 * @param string $box_id
	 * @return bool
	 */
	private static function is_feedback_box_id_allowed( string $box_id ): bool {
		return in_array( $box_id, array( 'pro-feedback', 'wordpress-review', 'github-feedback' ), true );
	}

	/**
	 * Restrict where each support box can render to avoid nagging on workflow pages.
	 *
	 * @param string $box_id
	 * @param string $context
	 * @return bool
	 */
	private static function should_render_box_in_context( string $box_id, string $context ): bool {
		if ( in_array( $box_id, array( 'wordpress-review', 'github-feedback', 'pro-feedback' ), true ) ) {
			if ( 'admin-global-top' === $context ) {
				return $box_id === 'wordpress-review';
			}

			return in_array( $context, array( 'settings', 'settings-top' ), true );
		}

		return true;
	}

	/**
	 * Decide whether support boxes should temporarily appear at top of settings.
	 *
	 * @param array $boxes
	 * @return bool
	 */
	private static function should_show_settings_boxes_at_top( array $boxes ): bool {
		if ( ! in_array( 'wordpress-review', $boxes, true ) ) {
			return false;
		}

		if ( self::is_review_box_test_mode() ) {
			return true;
		}

		$user_id = get_current_user_id();
		if ( $user_id <= 0 ) {
			return false;
		}

		$top_impressions = (int) get_user_meta( $user_id, self::REVIEW_TOP_IMPRESSIONS_META_KEY, true );
		if ( $top_impressions >= self::REVIEW_TOP_MAX_IMPRESSIONS ) {
			return false;
		}

		$last_shown = (int) get_user_meta( $user_id, self::REVIEW_TOP_LAST_SHOWN_META_KEY, true );
		if ( $last_shown > 0 && ( time() - $last_shown ) < self::REVIEW_TOP_COOLDOWN_SECONDS ) {
			return false;
		}

		return wp_rand( 1, 100 ) <= self::REVIEW_TOP_RANDOM_PERCENT;
	}

	/**
	 * Record a top-position impression for the current user.
	 *
	 * @return void
	 */
	private static function record_settings_top_impression(): void {
		$user_id = get_current_user_id();
		if ( $user_id <= 0 ) {
			return;
		}

		$top_impressions = (int) get_user_meta( $user_id, self::REVIEW_TOP_IMPRESSIONS_META_KEY, true );
		update_user_meta( $user_id, self::REVIEW_TOP_IMPRESSIONS_META_KEY, $top_impressions + 1 );
		update_user_meta( $user_id, self::REVIEW_TOP_LAST_SHOWN_META_KEY, time() );
	}

	/**
	 * Temporary test mode to force-show the WordPress review box.
	 *
	 * Usage: add `contactinbox_test_review_box=1` to the settings page URL.
	 *
	 * @return bool
	 */
	private static function is_review_box_test_mode(): bool {
		if ( ! is_admin() || ! current_user_can( Config::CAPABILITY ) ) {
			return false;
		}

		// Temporary: force review prompt visibility across wp-admin for testing.
		if ( self::is_temporary_global_review_notice_enabled() ) {
			return true;
		}

		$flag = isset( $_GET[ self::REVIEW_BOX_TEST_PARAM ] )
			? sanitize_text_field( wp_unslash( (string) $_GET[ self::REVIEW_BOX_TEST_PARAM ] ) )
			: '';

		if ( '1' !== $flag ) {
			return false;
		}

		$nonce = isset( $_GET[ self::REVIEW_BOX_TEST_NONCE_PARAM ] )
			? sanitize_text_field( wp_unslash( (string) $_GET[ self::REVIEW_BOX_TEST_NONCE_PARAM ] ) )
			: '';

		return wp_verify_nonce( $nonce, self::REVIEW_BOX_TEST_NONCE_ACTION );
	}

	/**
	 * Resolve current admin request URL as an absolute URL.
	 *
	 * @return string
	 */
	private static function get_current_admin_request_url(): string {
		$request_uri = isset( $_SERVER['REQUEST_URI'] )
			? sanitize_text_field( wp_unslash( (string) $_SERVER['REQUEST_URI'] ) )
			: '';
		if ( $request_uri === '' ) {
			return admin_url( 'admin.php?page=' . Config::MENU_SETTINGS );
		}

		return esc_url_raw( home_url( $request_uri ) );
	}

	/**
	 * Get redirect target from request and validate fallback.
	 *
	 * @return string
	 */
	private static function get_requested_redirect_url( string $requested ): string {
		if ( $requested !== '' ) {
			$requested = rawurldecode( $requested );
			if ( filter_var( $requested, FILTER_VALIDATE_URL ) ) {
				return esc_url_raw( $requested );
			}
		}

		return self::get_current_admin_request_url();
	}

	/**
	 * @param string $box_id
	 * @return string
	 */
	private static function get_box_hidden_until_meta_key( string $box_id ): string {
		return 'contactinbox_box_' . $box_id . self::FEEDBACK_META_HIDDEN_UNTIL_SUFFIX;
	}

	/**
	 * @param string $box_id
	 * @return string
	 */
	private static function get_box_dismissed_meta_key( string $box_id ): string {
		return 'contactinbox_box_' . $box_id . self::FEEDBACK_META_DISMISSED_AT_SUFFIX;
	}
}
