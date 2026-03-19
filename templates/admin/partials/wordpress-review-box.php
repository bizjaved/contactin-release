<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

/**
 * WordPress Review Box Partial
 *
 * Displays WordPress.org plugin review call-to-action.
 * Usage: include plugin_dir_path(__FILE__) . 'templates/partials/wordpress-review-box.php';
 *
 * @package ContactInbox/Admin
 */
?>
<div class="card wordpress-review-box" style="border-left: 4px solid #3582c4; background: linear-gradient(135deg, #f8f9fa 0%, #fff 100%);">
    <div style="display: flex; align-items: flex-start; gap: 12px;">
        <span style="font-size: 32px; line-height: 1;">⭐</span>
        <div style="flex: 1;">
            <h3 style="margin: 0 0 8px 0; color: #1d2327; font-size: 16px;">
                <?php esc_html_e( 'Enjoying ContactIn?', 'contact-inbox' ); ?>
            </h3>
            <p style="margin: 0 0 12px 0; color: #646970; font-size: 13px; line-height: 1.6;">
                <?php esc_html_e( 'Help other WordPress users discover this plugin by leaving a 5-star review on WordPress.org. Your feedback means a lot to us!', 'contact-inbox' ); ?>
            </p>
            <a href="https://wordpress.org/support/plugin/contact-inbox-hub/reviews/"
               target="_blank"
               rel="noopener noreferrer"
               class="button button-primary"
               style="text-decoration: none; font-size: 13px; background-color: #3582c4; border-color: #3582c4;"
               aria-label="<?php esc_attr_e( 'Leave a review for ContactIn on WordPress.org', 'contact-inbox' ); ?>">
                <span style="margin-right: 6px;">✍️</span>
                <?php esc_html_e( 'Leave a Review', 'contact-inbox' ); ?>
            </a>
        </div>
    </div>
</div>
