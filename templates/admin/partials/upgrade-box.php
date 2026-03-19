<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

/**
 * Upgrade Box Partial
 *
 * Displays upgrade-to-pro call-to-action box in admin pages.
 * Usage: include plugin_dir_path(__FILE__) . 'templates/partials/upgrade-box.php';
 *
 * @package ContactInbox/Admin
 */
?>
<div class="card upgrade-box" style="border-left: 4px solid #46b450; background: linear-gradient(135deg, #f8f9fa 0%, #fff 100%);">
    <div style="display: flex; align-items: flex-start; gap: 12px;">
        <span style="font-size: 32px; line-height: 1;">🚀</span>
        <div style="flex: 1;">
            <h3 style="margin: 0 0 8px 0; color: #1d2327; font-size: 16px;">
                <?php esc_html_e( 'Need more power?', 'contact-inbox' ); ?>
            </h3>
            <p style="margin: 0 0 12px 0; color: #646970; font-size: 13px; line-height: 1.6;">
                <?php esc_html_e( 'Start a free 30-day trial of ContactIn Pro to unlock CRM sync, GDPR tools, advanced automation, and priority support.', 'contact-inbox' ); ?>
            </p>
                <a href="<?php echo esc_url( \ContactInbox\Core\Config::get_trial_url() ); ?>"
               target="_blank"
               rel="noopener noreferrer"
               class="button button-primary"
               style="text-decoration: none; font-size: 13px;"
               aria-label="<?php esc_attr_e( 'Start free 30-day trial of ContactIn Pro', 'contact-inbox' ); ?>">
                <span style="margin-right: 6px;">⭐</span>
                <?php esc_html_e( 'Start Free 30-Day Trial', 'contact-inbox' ); ?>
            </a>
        </div>
    </div>
</div>
