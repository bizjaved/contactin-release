<?php
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals
/**
 * Unified Review Box Partial
 *
 * Shows contextual review CTA based on edition:
 * - Free: WordPress.org review prompt
 * - Pro: GitHub feedback prompt
 *
 * @package ContactInbox/Admin
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$is_free = defined( 'CONTACTINBOX_IS_FREE' ) && CONTACTINBOX_IS_FREE;

$icon = $is_free ? '⭐' : '🐙';
$title = $is_free
    ? __( 'Enjoying Contact Inbox?', 'contact-inbox' )
    : __( 'Share Your Feedback', 'contact-inbox' );
$description = $is_free
    ? __( 'Help other WordPress users discover this plugin by leaving a 5-star review on WordPress.org. Your feedback means a lot to us!', 'contact-inbox' )
    : __( 'Have ideas for improvements? Found a bug? Star us on GitHub and share your feedback with the community.', 'contact-inbox' );
$url = $is_free
    ? 'https://wordpress.org/support/plugin/contact-inbox/reviews/'
    : 'https://contactinbox.app/';
$button_class = $is_free ? 'button button-primary' : 'button button-secondary';
$button_text = $is_free
    ? __( 'Leave a Review', 'contact-inbox' )
    : __( 'Visit Website', 'contact-inbox' );
$button_emoji = $is_free ? '✍️' : '⭐';
$aria_label = $is_free
    ? __( 'Leave a review for Contact Inbox on WordPress.org', 'contact-inbox' )
    : __( 'Visit Contact Inbox Pro website', 'contact-inbox' );
$border_color = $is_free ? '#3582c4' : '#0969da';
$issue_url = $is_free
    ? 'https://github.com/bizjaved/contact-inbox/issues/new/choose'
    : 'https://github.com/bizjaved/contact-inbox-pro/issues/new/choose';
?>
<div class="card cin-review-box" style="border-left: 4px solid <?php echo esc_attr( $border_color ); ?>; background: linear-gradient(135deg, #f8f9fa 0%, #fff 100%);">
    <div style="display: flex; align-items: flex-start; gap: 12px;">
        <span style="font-size: 32px; line-height: 1;"><?php echo esc_html( $icon ); ?></span>
        <div style="flex: 1;">
            <h3 style="margin: 0 0 8px 0; color: #1d2327; font-size: 16px;">
                <?php echo esc_html( $title ); ?>
            </h3>
            <p style="margin: 0 0 12px 0; color: #646970; font-size: 13px; line-height: 1.6;">
                <?php echo esc_html( $description ); ?>
            </p>
            <a href="<?php echo esc_url( $url ); ?>"
               target="_blank"
               rel="noopener noreferrer"
               class="<?php echo esc_attr( $button_class ); ?>"
               style="text-decoration: none; font-size: 13px;"
               aria-label="<?php echo esc_attr( $aria_label ); ?>">
                <span style="margin-right: 6px;"><?php echo esc_html( $button_emoji ); ?></span>
                <?php echo esc_html( $button_text ); ?>
            </a>
            <p style="margin: 10px 0 0 0; font-size: 12px; color: #646970;">
                <?php esc_html_e( 'Found a bug?', 'contact-inbox' ); ?>
                <a href="<?php echo esc_url( $issue_url ); ?>" target="_blank" rel="noopener noreferrer">
                    <?php esc_html_e( 'Report an issue', 'contact-inbox' ); ?>
                </a>
            </p>
        </div>
    </div>
</div>
