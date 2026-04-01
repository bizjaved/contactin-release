<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
/**
 * Form Success Message Template
 *
 * Displays a confirmation message when a form submission succeeds,
 * including optional GDPR deletion options.
 *
 * @package ContactIn/Frontend
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use ContactInbox\Core\Config;

// phpcs:disable WordPress.WP.I18n.NonSingularStringLiteralDomain, WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound

$delete_link    = $delete_link ?? $gdpr_delete_link ?? '';
$settings       = $settings ?? array();
$attachment     = $attachment ?? '';
$masked_receipt = $masked_receipt ?? '';
$home_url       = home_url( '/' );
?>

<div class="contactin-success" role="status" aria-live="polite" style="max-width: 600px; margin: 40px auto; padding: 32px; border: 1px solid #e5e7eb; border-radius: 8px; background: #ffffff; box-shadow: 0 2px 8px rgba(0,0,0,0.08); text-align: center;">
	<div class="contactin-success-icon" style="margin-bottom: 20px;">
		<div class="contactin-checkmark" style="display: inline-flex; align-items: center; justify-content: center; width: 64px; height: 64px; background: linear-gradient(135deg, #10b981 0%, #059669 100%); color: #ffffff; border-radius: 50%; font-size: 36px; font-weight: bold; box-shadow: 0 4px 12px rgba(16, 185, 129, 0.4);">
			✓
		</div>
	</div>

	<div class="contactin-success-content">
		<h2 class="contactin-success-title" style="margin: 0 0 16px 0; font-size: 24px; font-weight: 600; color: #1f2937; line-height: 1.3;">
			<?php esc_html_e( 'Message Sent Successfully', 'contactin' ); ?>
		</h2>

		<p class="contactin-success-message" style="margin: 0 0 24px 0; font-size: 16px; color: #6b7280; line-height: 1.6;">
			<?php
			$success_message = $settings['success_message'] ?? __( 'Thank you! Your message has been received. We\'ll review it shortly and get back to you soon.', 'contactin' );
			echo wp_kses_post( $success_message );
			?>
		</p>

		<?php if ( ! empty( $delete_link ) ) : ?>
			<div class="contactin-gdpr-section" style="margin-top: 32px; padding: 24px; background: #f9fafb; border: 1px solid #e5e7eb; border-radius: 6px; text-align: left;">
				<div class="contactin-gdpr-header" style="margin-bottom: 12px;">
					<h3 class="contactin-gdpr-title" style="margin: 0; font-size: 16px; font-weight: 600; color: #374151; display: flex; align-items: center; gap: 8px;">
						<span class="contactin-gdpr-icon" style="font-size: 20px;">🔒</span>
						<?php esc_html_e( 'Your Privacy & Data Control', 'contactin' ); ?>
					</h3>
				</div>
				<p class="contactin-gdpr-info" style="margin: 0 0 16px 0; font-size: 14px; color: #6b7280; line-height: 1.6;">
					<?php esc_html_e( 'In compliance with GDPR and data protection regulations, you have full control over your submission. You can request deletion of your data at any time.', 'contactin' ); ?>
				</p>
				<div class="contactin-gdpr-action-wrapper" style="text-align: center;">
					<a
						href="<?php echo esc_url( $delete_link ); ?>"
						class="contactin-gdpr-delete-btn"
						target="_blank"
						rel="noopener noreferrer"
						aria-label="<?php esc_attr_e( 'Delete your submission data', 'contactin' ); ?>"
						style="display: inline-block; padding: 10px 20px; background: #ef4444; color: #ffffff; text-decoration: none; border-radius: 4px; font-size: 14px; font-weight: 500; transition: background 0.2s ease;"
					>
						<?php esc_html_e( 'Request Data Deletion', 'contactin' ); ?>
					</a>
					<div class="contactin-gdpr-link-meta" style="margin-top: 8px; font-size: 12px; color: #9ca3af;">
						<?php echo esc_html( $settings['gdpr_expiry_text'] ?? __( 'Deletion link valid for 7 days', 'contactin' ) ); ?>
					</div>
				</div>
			</div>
		<?php endif; ?>
	</div>
</div>
