<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
/**
 * Change Classification Modal
 *
 * Modal for changing message classification/intent
 * Uses global contactin-modal CSS classes from admin-inbox.min.css
 *
 * @package ContactIn\Admin
 */

use ContactInbox\Core\Config;

// phpcs:disable WordPress.WP.I18n.NonSingularStringLiteralDomain, WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound

$nonce    = wp_create_nonce( Config::INBOX_NONCE_ACTION );
$settings = \ContactInbox\Core\Settings::get_settings();
$is_non_premium_state = false;
?>

<?php if ( ! empty( $settings['intent_enable'] ) ) : ?>
<!-- Classification Modal - Uses global contactin-modal classes -->
<div id="cin-classification-modal" class="contactin-modal">
	<div class="contactin-modal-backdrop"></div>
	<div class="contactin-modal-content">
		<!-- Header -->
		<div class="contactin-modal-header">
			<div style="display: flex; justify-content: space-between; align-items: center; width: 100%;">
				<h2 class="cin-modal-title" style="margin: 0; flex: 1; font-size: 16px;"><?php esc_html_e( 'Change Classification', 'contactin' ); ?></h2>
				<button type="button" class="cin-modal-close" aria-label="<?php esc_attr_e( 'Close', 'contactin' ); ?>" style="background: none; border: none; cursor: pointer; padding: 0; display: flex; align-items: center; justify-content: center;">
					<span class="dashicons dashicons-no" style="font-size: 20px; width: 20px; height: 20px;"></span>
				</button>
			</div>
		</div>

		<!-- Body -->
		<div class="contactin-modal-body">
			<input type="hidden" id="cin-classification-message-id" value="">
			<input type="hidden" id="cin-classification-nonce" value="<?php echo esc_attr( $nonce ); ?>">
			<input type="hidden" id="cin-classification-locked" value="<?php echo $is_non_premium_state ? '1' : '0'; ?>">

			<div class="cin-classification-grid">
				<?php
				$categories = \ContactInbox\Core\IntentClassifier::get_categories();
				foreach ( $categories as $cat_key => $cat_label ) :
					// Skip spam category - use spam tab for spam handling
					if ( $cat_key === 'spam' ) {
						continue;
					}
					$cat_color = \ContactInbox\Core\IntentClassifier::get_category_color( $cat_key );
					?>
					<button type="button" class="cin-classification-btn cin-intent-<?php echo esc_attr( $cat_color ); ?><?php echo $is_non_premium_state ? ' cin-classification-btn--locked' : ''; ?>" 
							data-category="<?php echo esc_attr( $cat_key ); ?>"
							data-color="<?php echo esc_attr( $cat_color ); ?>"
							data-locked="<?php echo $is_non_premium_state ? '1' : '0'; ?>"
							title="<?php echo esc_attr( $cat_label ); ?>">
						<span class="dashicons dashicons-tag"></span>
						<span class="cin-category-label"><?php echo esc_html( $cat_label ); ?></span>
					</button>
				<?php endforeach; ?>
			</div>

			<p class="cin-classification-hint">
				<small><?php esc_html_e( 'Choose the category that best matches this message.', 'contactin' ); ?></small>
			</p>
		</div>

		<!-- Footer -->
		<div class="contactin-modal-footer">
			<div class="cin-footer-actions">
				<button type="button" class="button button-secondary cin-modal-cancel">
					<?php esc_html_e( 'Cancel', 'contactin' ); ?>
				</button>
			</div>
		</div>
	</div>
</div>

<?php endif; ?>
