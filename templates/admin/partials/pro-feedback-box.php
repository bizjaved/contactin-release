<?php
if (!defined('ABSPATH')) exit;
/**
 * Pro Feedback Box - Licensed/Pro Version
 *
 * Displays lighter feedback appreciation box in pro version.
 * Encourages feature requests and feedback from paid users.
 *
 * @package ContactIn/Admin
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

use ContactInbox\Core\Config;
use ContactInbox\Admin\SupportBoxesManager;

// phpcs:disable WordPress.WP.I18n.NonSingularStringLiteralDomain, WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound

$snooze_url = SupportBoxesManager::get_box_action_url( 'pro-feedback', 'snooze' );
$dismiss_url = SupportBoxesManager::get_box_action_url( 'pro-feedback', 'dismiss' );
?>

<div class="card pro-feedback-box" style="border-left: 4px solid #7928ca; background: linear-gradient(135deg, #faf5ff 0%, #fff 100%);">
    <div style="display: flex; align-items: flex-start; gap: 12px;">
        <span style="font-size: 32px; line-height: 1;">💬</span>
        <div style="flex: 1;">
            <h3 style="margin: 0 0 8px 0; color: #1d2327; font-size: 16px;">
                <?php esc_html_e( 'Support & Product Feedback',  'contactin'); ?>
            </h3>
            <p style="margin: 0 0 12px 0; color: #646970; font-size: 13px; line-height: 1.6;">
                <?php esc_html_e( 'Need help or have an idea? Send support requests and feature feedback through our Contact Us page. We use intent routing to triage each submission quickly.',  'contactin'); ?>
            </p>
            <a href="https://contactinbox.app/contactin-pro-contact-us/"
               target="_blank"
               rel="noopener noreferrer"
               class="button button-secondary"
               style="text-decoration: none; font-size: 13px; border-color: #7928ca; color: #7928ca;"
               aria-label="<?php esc_attr_e( 'Send product feedback via ContactIn website',  'contactin'); ?>">
                <span style="margin-right: 6px;">💡</span>
                <?php esc_html_e( 'Share Feedback',  'contactin'); ?>
            </a>
            <a href="https://contactinbox.app/contactin-pro-contact-us/"
               target="_blank"
               rel="noopener noreferrer"
               class="button button-secondary"
               style="text-decoration: none; font-size: 13px; margin-left: 8px;"
               aria-label="<?php esc_attr_e( 'Get support via ContactIn website',  'contactin'); ?>">
                <span style="margin-right: 6px;">🛟</span>
                <?php esc_html_e( 'Get Support',  'contactin'); ?>
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
