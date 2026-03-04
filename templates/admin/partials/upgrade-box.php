<?php
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
                <?php esc_html_e( 'Need more power?', 'contact-inbox-hub' ); ?>
            </h3>
            <p style="margin: 0 0 12px 0; color: #646970; font-size: 13px; line-height: 1.6;">
                <?php esc_html_e( 'Upgrade to Contact Inbox Pro to unlock CRM sync, GDPR tools, advanced automation, and priority support.', 'contact-inbox-hub' ); ?>
            </p>
                <a href="<?php echo esc_url( \ContactInbox\Core\Config::get_upgrade_url() ); ?>"
               target="_blank"
               rel="noopener noreferrer"
               class="button button-primary"
               style="text-decoration: none; font-size: 13px;"
               aria-label="<?php esc_attr_e( 'Upgrade to Contact Inbox Pro', 'contact-inbox-hub' ); ?>">
                <span style="margin-right: 6px;">⭐</span>
                <?php esc_html_e( 'Upgrade to Pro', 'contact-inbox-hub' ); ?>
            </a>
        </div>
    </div>
</div>
