<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
/**
 * Upgrade to Pro Box - Free Version Only
 *
 * Displays prominent upgrade call-to-action box in free version.
 * Links to premium features and pricing details.
 *
 * @package ContactIn/Admin
 */

use ContactInbox\Core\Config;
?>

<div class="card upgrade-pro-box" style="border-left: 4px solid #0073aa; background: linear-gradient(135deg, #f0f6fc 0%, #fff 100%);">
	<div style="display: flex; align-items: flex-start; gap: 12px;">
		<span style="font-size: 32px; line-height: 1;">⭐</span>
		<div style="flex: 1;">
			<h3 style="margin: 0 0 8px 0; color: #1d2327; font-size: 16px;">
				<?php esc_html_e( 'Unlock Pro Features', 'contactin' ); ?>
			</h3>
			<p style="margin: 0 0 12px 0; color: #646970; font-size: 13px; line-height: 1.6;">
				<?php esc_html_e( 'Upgrade to ContactIn Pro for advanced analytics, automation controls, and priority support.', 'contactin' ); ?>
			</p>
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=contactin-get-started' ) ); ?>"
				class="button button-primary"
				style="text-decoration: none; font-size: 13px; background-color: #0073aa; border-color: #0073aa;"
				aria-label="<?php esc_attr_e( 'Upgrade to ContactIn Pro', 'contactin' ); ?>">
				<span style="margin-right: 6px;">🚀</span>
				<?php esc_html_e( 'Upgrade Now', 'contactin' ); ?>
			</a>
		</div>
	</div>
</div>
