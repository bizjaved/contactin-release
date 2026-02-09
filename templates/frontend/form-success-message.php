<?php
/**
 * Form Success Message Template
 *
 * Displays a confirmation message when a form submission succeeds,
 * including optional GDPR deletion options.
 *
 * @package ContactInbox/Frontend
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

use ContactInbox\Core\Config;

$delete_link    = $delete_link ?? $gdpr_delete_link ?? '';
$settings       = $settings ?? [];
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
            <?php esc_html_e( 'Message Sent Successfully', Config::TEXTDOMAIN ); ?>
        </h2>

        <p class="contactin-success-message" style="margin: 0 0 24px 0; font-size: 16px; color: #6b7280; line-height: 1.6;">
            <?php
            $success_message = $settings['success_message'] ?? __( 'Thank you! Your message has been received. We\'ll review it shortly and get back to you soon.', Config::TEXTDOMAIN );
            echo wp_kses_post( $success_message );
            ?>
        </p>

    </div>
</div>
