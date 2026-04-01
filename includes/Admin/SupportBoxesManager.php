<?php
declare(strict_types=1);

namespace ContactInbox\Admin;

use ContactInbox\Integration\FreemiusIntegration;
use ContactInbox\Core\Config;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
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

	/**
	 * Installation date option key
	 */
	const INSTALLATION_DATE_KEY             = 'contactinbox_installation_date';
	const PRO_FEEDBACK_ACTION_PARAM         = 'contactinbox_feedback_box_action';
	const PRO_FEEDBACK_NONCE_PARAM          = '_cin_fb_nonce';
	const FEEDBACK_BOX_PARAM                = 'contactinbox_feedback_box';
	const PRO_FEEDBACK_NONCE_ACTION         = 'cin_feedback_box_action';
	const PRO_FEEDBACK_MIN_DAYS             = 14;
	const PRO_FEEDBACK_SNOOZE_DAYS          = 7;
	const FEEDBACK_META_HIDDEN_UNTIL_SUFFIX = '_hidden_until';
	const FEEDBACK_META_DISMISSED_AT_SUFFIX = '_dismissed_at';

	/**
	 * Check if this is the free version (no active Freemius license)
	 *
	 * @return bool True if free version, false if pro/licensed
	 */
	public static function is_free_version(): bool {
		return FreemiusIntegration::is_free_plan_state();
	}

	/**
	 * Check if pro/licensed version
	 *
	 * @return bool True if pro/licensed, false if free
	 */
	public static function is_pro_version(): bool {
		return FreemiusIntegration::has_pro_license();
	}

	/**
	 * Check if running in a non-premium state (free or expired).
	 *
	 * @return bool
	 */
	private static function is_non_premium_state(): bool {
		return FreemiusIntegration::is_non_premium_state();
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

		if ( self::is_non_premium_state() ) {
			$boxes[] = 'expired-license';
			return $boxes;
		}

		if ( self::is_free_version() ) {
			// Free version boxes
			$boxes[] = 'upgrade-pro';      // Main CTA for upgrade

			if ( self::should_show_review_box() && self::should_show_box_for_current_user( 'wordpress-review' ) ) {
				$boxes[] = 'wordpress-review'; // Show after 14 days
			}

			if ( self::should_show_box_for_current_user( 'github-feedback' ) ) {
				$boxes[] = 'github-feedback';   // Community engagement
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
	public static function render_support_boxes( string $context = '' ): void {
		self::maybe_handle_feedback_box_action();

		$boxes = self::get_boxes_to_display();

		if ( empty( $boxes ) ) {
			return;
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
			return;
		}

		?>
		<div class="cin-support-boxes">
			<?php foreach ( $boxes as $box_id ) : ?>
				<div class="cin-support-box">
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

		$current_url = isset( $_SERVER['REQUEST_URI'] )
			? esc_url_raw( wp_unslash( (string) $_SERVER['REQUEST_URI'] ) )
			: admin_url( 'admin.php?page=' . Config::MENU_SETTINGS );

		$redirect_url = remove_query_arg(
			array( self::PRO_FEEDBACK_ACTION_PARAM, self::PRO_FEEDBACK_NONCE_PARAM, self::FEEDBACK_BOX_PARAM ),
			$current_url
		);

		if ( empty( $redirect_url ) ) {
			$redirect_url = admin_url( 'admin.php?page=' . Config::MENU_SETTINGS );
		}

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

		$current_url = isset( $_SERVER['REQUEST_URI'] )
			? esc_url_raw( wp_unslash( (string) $_SERVER['REQUEST_URI'] ) )
			: admin_url( 'admin.php?page=' . Config::MENU_SETTINGS );

		$action_url = add_query_arg(
			array(
				self::PRO_FEEDBACK_ACTION_PARAM => $action,
				self::FEEDBACK_BOX_PARAM        => $box_id,
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
