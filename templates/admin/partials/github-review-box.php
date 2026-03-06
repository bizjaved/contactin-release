<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

/**
 * GitHub Review/Feedback Box Partial
 *
 * Displays GitHub repository link and feedback call-to-action.
 * Usage: include plugin_dir_path(__FILE__) . 'templates/partials/github-review-box.php';
 *
 * @package ContactInbox/Admin
 */
?>
<div class="card github-review-box" style="border-left: 4px solid #0969da; background: linear-gradient(135deg, #f8f9fa 0%, #fff 100%);">
    <div style="display: flex; align-items: flex-start; gap: 12px;">
        <span style="font-size: 32px; line-height: 1;">🐙</span>
        <div style="flex: 1;">
            <h3 style="margin: 0 0 8px 0; color: #1d2327; font-size: 16px;">
                <?php esc_html_e( 'Share Your Feedback', 'contact-inbox' ); ?>
            </h3>
            <p style="margin: 0 0 12px 0; color: #646970; font-size: 13px; line-height: 1.6;">
                <?php esc_html_e( 'Have ideas for improvements? Found a bug? Star us on GitHub and share your feedback with the community.', 'contact-inbox' ); ?>
            </p>
            <a href="https://github.com/bizjaved/contact-inbox-hub"
               target="_blank"
               rel="noopener noreferrer"
               class="button button-secondary"
               style="text-decoration: none; font-size: 13px;"
               aria-label="<?php esc_attr_e( 'Visit Contact Inbox on GitHub', 'contact-inbox' ); ?>">
                <span style="margin-right: 6px;">⭐</span>
                <?php esc_html_e( 'Visit GitHub', 'contact-inbox' ); ?>
            </a>
        </div>
    </div>
</div>
