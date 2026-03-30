<?php
if (!defined('ABSPATH')) exit;
/**
 * GitHub Review/Feedback Box Partial
 *
 * Displays GitHub repository link and feedback call-to-action.
 * Usage: include plugin_dir_path(__FILE__) . 'templates/partials/github-review-box.php';
 *
 * @package ContactIn/Admin
 */

// phpcs:disable WordPress.WP.I18n.TextDomainMismatch, WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound, WordPress.DB.PreparedSQL.NotPrepared

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

use ContactInbox\Admin\SupportBoxesManager;

$snooze_url = SupportBoxesManager::get_box_action_url( 'github-feedback', 'snooze' );
$dismiss_url = SupportBoxesManager::get_box_action_url( 'github-feedback', 'dismiss' );
?>
<div class="card github-review-box" style="border-left: 4px solid #0969da; background: linear-gradient(135deg, #f8f9fa 0%, #fff 100%);">
    <div style="display: flex; align-items: flex-start; gap: 12px;">
        <span style="font-size: 32px; line-height: 1;">🐙</span>
        <div style="flex: 1;">
            <h3 style="margin: 0 0 8px 0; color: #1d2327; font-size: 16px;">
                <?php esc_html_e( 'Share Your Feedback',  'contactin'); ?>
            </h3>
            <p style="margin: 0 0 12px 0; color: #646970; font-size: 13px; line-height: 1.6;">
                <?php esc_html_e( 'Have ideas for improvements? Found a bug? Star us on GitHub and share your feedback with the community.',  'contactin'); ?>
            </p>
            <a href="https://github.com/bizjaved/contactin-hub"
               target="_blank"
               rel="noopener noreferrer"
               class="button button-secondary"
               style="text-decoration: none; font-size: 13px;"
               aria-label="<?php esc_attr_e( 'Visit ContactIn on GitHub',  'contactin'); ?>">
                <span style="margin-right: 6px;">⭐</span>
                <?php esc_html_e( 'Visit GitHub',  'contactin'); ?>
            </a>
            <p style="margin: 10px 0 0 0; font-size: 12px; color: #646970;">
                <a href="<?php echo esc_url( $snooze_url ); ?>" style="text-decoration: none; color: #2271b1; margin-right: 10px;">
                    <?php esc_html_e( 'Remind me later',  'contactin'); ?>
                </a>
                <a href="<?php echo esc_url( $dismiss_url ); ?>" style="text-decoration: none; color: #646970;">
                    <?php esc_html_e( 'Dismiss',  'contactin'); ?>
                </a>
            </p>
        </div>
    </div>
</div>
