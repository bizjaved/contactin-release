<?php
/**
 * Donate Box Partial
 *
 * Displays donation call-to-action box in admin pages.
 * Usage: include plugin_dir_path(__FILE__) . 'templates/partials/donate-box.php';
 *
 * @package ContactInbox/Admin
 */
?>
<div class="card donate-box" style="border-left: 4px solid #46b450; background: linear-gradient(135deg, #f8f9fa 0%, #fff 100%);">
    <div style="display: flex; align-items: flex-start; gap: 12px;">
        <span style="font-size: 32px; line-height: 1;">💝</span>
        <div style="flex: 1;">
            <h3 style="margin: 0 0 8px 0; color: #1d2327; font-size: 16px;">
                <?php esc_html_e( 'Love this plugin?', 'contact-inbox-hub' ); ?>
            </h3>
            <p style="margin: 0 0 12px 0; color: #646970; font-size: 13px; line-height: 1.6;">
                <?php esc_html_e( 'It\'s 100% free and built with passion. Your support helps us continue developing amazing features.', 'contact-inbox-hub' ); ?>
            </p>
            <a href="https://www.paypal.com/ncp/payment/TGXL9QCSX3FSG"
               target="_blank"
               rel="noopener noreferrer"
               class="button button-primary"
               style="text-decoration: none; font-size: 13px;"
               aria-label="<?php esc_attr_e( 'Donate via PayPal to support Contact Inbox', 'contact-inbox-hub' ); ?>">
                <span style="margin-right: 6px;">☕</span>
                <?php esc_html_e( 'Buy Us Coffee', 'contact-inbox-hub' ); ?>
            </a>
        </div>
    </div>
</div>
