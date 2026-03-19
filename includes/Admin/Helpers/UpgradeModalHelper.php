<?php
/**
 * Upgrade Modal Helper
 *
 * Provides a centralized modal for promoting premium features.
 *
 * @package ContactInbox\Admin\Helpers
 */

namespace ContactInbox\Admin\Helpers;

use ContactInbox\Core\Config;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class UpgradeModalHelper
 *
 * Handles the premium upgrade modal display and functionality.
 */
class UpgradeModalHelper {

	/**
	 * Determine whether upgrade modal popup should be disabled for current screen.
	 *
	 * @param object $screen Current admin screen object.
	 * @return bool
	 */
	private static function should_disable_modal_for_screen( $screen ) {
		if ( ! $screen || empty( $screen->id ) ) {
			return false;
		}

		$screen_id = (string) $screen->id;

		if (
			false !== strpos( $screen_id, 'contactin-analytics' )
			|| false !== strpos( $screen_id, 'contactin-maintenance' )
			|| false !== strpos( $screen_id, 'contactinbox-maintenance' )
		) {
			return true;
		}

		return false;
	}

	/**
	 * Initialize the upgrade modal.
	 *
	 * @return void
	 */
	public static function init() {
		if ( ! defined( 'CONTACTINBOX_IS_FREE' ) || ! CONTACTINBOX_IS_FREE ) {
			return;
		}

		add_action( 'admin_footer', array( __CLASS__, 'render_modal' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_assets' ) );
	}

	/**
	 * Enqueue modal assets.
	 *
	 * @return void
	 */
	public static function enqueue_assets() {
		$screen = get_current_screen();
		if ( ! $screen || ( strpos( $screen->id, 'contactin' ) === false && strpos( $screen->id, 'contact-inbox' ) === false ) ) {
			return;
		}

		if ( self::should_disable_modal_for_screen( $screen ) ) {
			return;
		}

		$style_path = CONTACTINBOX_PATH . 'dist/css/upgrade-modal.css';
		$style_version = file_exists( $style_path ) ? (string) filemtime( $style_path ) : CONTACTINBOX_VERSION;

		$script_path = CONTACTINBOX_PATH . 'dist/js/upgrade-modal.js';
		$script_version = file_exists( $script_path ) ? (string) filemtime( $script_path ) : CONTACTINBOX_VERSION;

		wp_enqueue_style(
			'contactinbox-upgrade-modal',
			CONTACTINBOX_URL . 'dist/css/upgrade-modal.css',
			array(),
			$style_version
		);

		wp_enqueue_script(
			'contactinbox-upgrade-modal',
			CONTACTINBOX_URL . 'dist/js/upgrade-modal.js',
			array( 'jquery' ),
			$script_version,
			true
		);

		wp_localize_script(
			'contactinbox-upgrade-modal',
			'contactinboxUpgrade',
			array(
				'proUrl' => Config::get_trial_url(),
			)
		);
	}

	/**
	 * Render the upgrade modal HTML.
	 *
	 * @return void
	 */
	public static function render_modal() {
		$screen = get_current_screen();
		if ( ! $screen || ( strpos( $screen->id, 'contactin' ) === false && strpos( $screen->id, 'contact-inbox' ) === false ) ) {
			return;
		}

		if ( self::should_disable_modal_for_screen( $screen ) ) {
			return;
		}
		?>
		<div id="contactinbox-upgrade-modal" class="contactinbox-modal" style="display: none;">
			<div class="contactinbox-modal-overlay"></div>
			<div class="contactinbox-modal-content">
				<button class="contactinbox-modal-close" aria-label="<?php esc_attr_e( 'Close', 'contact-inbox' ); ?>">
					<span class="dashicons dashicons-no-alt"></span>
				</button>
				<div class="contactinbox-modal-header">
					<span class="contactinbox-modal-icon dashicons dashicons-star-filled"></span>
					<h2><?php esc_html_e( 'Premium Feature', 'contact-inbox' ); ?></h2>
				</div>
				<div class="contactinbox-modal-body">
					<h3 class="contactinbox-feature-title"><?php esc_html_e( 'Unlock This Feature', 'contact-inbox' ); ?></h3>
					<p class="contactinbox-feature-description">
						<?php esc_html_e( 'This feature is available in ContactIn Pro. Start your 30-day free trial (no credit card) to access:', 'contact-inbox' ); ?>
					</p>
					<ul class="contactinbox-features-list">
						<li><span class="dashicons dashicons-yes"></span> <?php esc_html_e( 'GDPR Compliance & Data Management', 'contact-inbox' ); ?></li>
						<li><span class="dashicons dashicons-yes"></span> <?php esc_html_e( 'Salesforce CRM Integration', 'contact-inbox' ); ?></li>
						<li><span class="dashicons dashicons-yes"></span> <?php esc_html_e( 'File Attachment Support', 'contact-inbox' ); ?></li>
						<li><span class="dashicons dashicons-yes"></span> <?php esc_html_e( 'REST API Access', 'contact-inbox' ); ?></li>
						<li><span class="dashicons dashicons-yes"></span> <?php esc_html_e( 'CSV Export Functionality', 'contact-inbox' ); ?></li>
						<li><span class="dashicons dashicons-yes"></span> <?php esc_html_e( 'Advanced Queue Processing', 'contact-inbox' ); ?></li>
						<li><span class="dashicons dashicons-yes"></span> <?php esc_html_e( 'Extended Analytics & Reporting', 'contact-inbox' ); ?></li>
						<li><span class="dashicons dashicons-yes"></span> <?php esc_html_e( 'Priority Support', 'contact-inbox' ); ?></li>
					</ul>
				</div>
				<div class="contactinbox-modal-footer">
					<a href="#" class="button button-primary button-large contactinbox-upgrade-btn" target="_blank">
						<span class="dashicons dashicons-cart"></span>
						<?php esc_html_e( 'Start Free 30-Day Trial', 'contact-inbox' ); ?>
					</a>
					<a href="<?php echo esc_url( Config::get_trial_url() ); ?>" class="contactinbox-learn-more" target="_blank">
						<?php esc_html_e( 'Learn More', 'contact-inbox' ); ?>
					</a>
				</div>
			</div>
		</div>
		<?php
	}

	/**
	 * Check if a feature is premium.
	 *
	 * @param string $feature Feature identifier.
	 * @return bool
	 */
	public static function is_premium_feature( $feature ) {
		if ( ! defined( 'CONTACTINBOX_IS_FREE' ) || ! CONTACTINBOX_IS_FREE ) {
			return false;
		}

		$premium_features = array(
			'gdpr',
			'crm',
			'crm_sync',
			'attachments',
			'rest_api',
			'csv_export',
			'manual_queue',
			'scheduling',
			'log_retention',
		);

		return in_array( $feature, $premium_features, true );
	}

	/**
	 * Render a premium badge.
	 *
	 * @return void
	 */
	public static function render_badge() {
		?>
		<span class="contactinbox-pro-badge" title="<?php esc_attr_e( 'Premium Feature', 'contact-inbox' ); ?>">
			<span class="dashicons dashicons-lock"></span>
			<?php esc_html_e( 'PRO', 'contact-inbox' ); ?>
		</span>
		<?php
	}

	/**
	 * Render a disabled field wrapper for premium features.
	 *
	 * @param string $feature Feature identifier.
	 * @param string $content Field content to wrap.
	 * @return string
	 */
	public static function wrap_premium_field( $feature, $content ) {
		if ( ! self::is_premium_feature( $feature ) ) {
			return $content;
		}

		ob_start();
		?>
		<div class="contactinbox-premium-field" data-feature="<?php echo esc_attr( $feature ); ?>">
			<div class="contactinbox-premium-overlay">
				<button type="button" class="button contactinbox-show-upgrade-modal">
					<span class="dashicons dashicons-lock"></span>
					<?php esc_html_e( 'Upgrade to Pro', 'contact-inbox' ); ?>
				</button>
			</div>
			<div class="contactinbox-premium-content">
				<?php echo $content; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
			</div>
		</div>
		<?php
		return ob_get_clean();
	}
}
