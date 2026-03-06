<?php
/**
 * Form Failure Message Template
 *
 * Displays a user-friendly error modal when form submission fails.
 * This message appears in a modal overlay, allowing users to close it and retry without losing form data.
 *
 * @package ContactInbox/Frontend
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

use ContactInbox\Core\Config;

$failure_message = $failure_message ?? __( 'Sorry, your message could not be sent. Please try again later.', 'contact-inbox' );
$failure_tip     = ! empty( $failure_tip ) ? $failure_tip : __( 'Tip: If you see a security or token error, please refresh the page and resubmit.', 'contact-inbox' );
?>

<div class="contactin-failure-modal" role="alertdialog" aria-modal="true" id="cin-error-modal-title">
    <div class="contactin-failure-icon">
        <div class="contactin-failure-icon-bg">⚠</div>
    </div>

    <div class="contactin-failure-content">
        <h2 class="contactin-failure-title">
            <?php esc_html_e( 'Submission Could Not Be Sent', 'contact-inbox' ); ?>
        </h2>

        <p class="contactin-failure-message">
            <?php echo esc_html( $failure_message ); ?>
        </p>

        <?php if ( ! empty( $failure_tip ) ) : ?>
            <div class="contactin-failure-tip-box">
                <p class="contactin-failure-tip">
                    <strong><?php esc_html_e( 'Troubleshooting:', 'contact-inbox' ); ?></strong>
                    <?php echo esc_html( $failure_tip ); ?>
                </p>
            </div>
        <?php endif; ?>

        <div class="contactin-failure-actions">
            <button
                type="button"
                class="contactin-retry-btn"
                data-action="close-error-modal"
                aria-label="<?php esc_attr_e( 'Close error and retry', 'contact-inbox' ); ?>"
            >
                <?php esc_html_e( 'Try Again', 'contact-inbox' ); ?>
            </button>
        </div>
    </div>
</div>
