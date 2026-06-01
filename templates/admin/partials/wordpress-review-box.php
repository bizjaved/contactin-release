<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
/**
 * WordPress Review Box Partial
 *
 * Displays WordPress.org plugin review call-to-action for the free version.
 * Shows only to free version users (not pro/licensed).
 * Links to WordPress.org plugin page where free version is distributed via Freemius.
 *
 * @package ContactIn/Admin
 */

use ContactInbox\Core\Config;
use ContactInbox\Admin\SupportBoxesManager;

// phpcs:disable WordPress.WP.I18n.NonSingularStringLiteralDomain, WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound

$snooze_url  = SupportBoxesManager::get_box_action_url( 'wordpress-review', 'snooze' );
$dismiss_url = SupportBoxesManager::get_box_action_url( 'wordpress-review', 'dismiss' );
?>
<div class="card wordpress-review-box" style="border-left: 4px solid #3582c4; background: linear-gradient(135deg, #f8f9fa 0%, #fff 100%);">
	<div style="display: flex; align-items: flex-start; gap: 12px;">
		<span style="font-size: 32px; line-height: 1;">⭐</span>
		<div style="flex: 1;">
			<h3 style="margin: 0 0 8px 0; color: #1d2327; font-size: 16px;">
				<?php esc_html_e( 'Enjoying ContactIn?', 'contactin' ); ?>
			</h3>
			<p style="margin: 0 0 12px 0; color: #646970; font-size: 13px; line-height: 1.6;">
				<?php esc_html_e( 'If ContactIn is helping your team, please consider leaving an honest review on WordPress.org. Your feedback helps us improve and helps other users evaluate the plugin.', 'contactin' ); ?>
			</p>
			<a href="https://wordpress.org/support/plugin/contactin/reviews/"
				target="_blank"
				rel="noopener noreferrer"
				class="button button-primary"
				style="text-decoration: none; font-size: 13px; background-color: #3582c4; border-color: #3582c4;"
				aria-label="<?php esc_attr_e( 'Leave a review for ContactIn on WordPress.org', 'contactin' ); ?>">
				<span style="margin-right: 6px;">✍️</span>
				<?php esc_html_e( 'Leave a Review on WordPress.org', 'contactin' ); ?>
			</a>
			<p style="margin: 10px 0 0 0; font-size: 12px; color: #646970;">
				<a href="<?php echo esc_url( $snooze_url ); ?>" style="text-decoration: none; color: #2271b1; margin-right: 10px;">
					<?php esc_html_e( 'Remind me later', 'contactin' ); ?>
				</a>
				<a href="<?php echo esc_url( $dismiss_url ); ?>" data-cin-support-action="dismiss" style="text-decoration: none; color: #646970;">
					<?php esc_html_e( 'Dismiss', 'contactin' ); ?>
				</a>
			</p>
		</div>
	</div>
</div>
