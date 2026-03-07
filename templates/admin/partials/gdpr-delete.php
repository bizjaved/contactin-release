<?php
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals, WordPress.WP.I18n.NonSingularStringLiteralText
/**
 * GDPR Deletion Confirmation Template
 *
 * @package ContactInbox\Frontend
 */

use ContactInbox\Core\Config;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Variables passed in from maybe_handle_gdpr_link()
$link_valid  = $args['link_valid'] ?? false;
$email       = $args['email'] ?? '';
$message     = $args['message'] ?? '';
?>

<div class="wrap cin-gdpr-page">
    <div class="cin-gdpr-wrap <?php echo $link_valid ? 'cin-gdpr-success' : 'cin-gdpr-error'; ?>">
        <div class="cin-gdpr-icon">
            <?php echo $link_valid ? '✅' : '⚠️'; ?>
        </div>

        <?php if ( $link_valid ) : ?>
            <h1><?php echo esc_html( Config::GDPR_SUCCESS_DEFAULT ); ?></h1>
            <p>
                <?php
                /* translators: %s: email address for deleted data owner. */
                $gdpr_deleted_text = esc_html__( 'Your data associated with %s has been permanently deleted.', 'contact-inbox' );
                printf(
                    esc_html( $gdpr_deleted_text ),
                    '<strong>' . esc_html( $email ) . '</strong>'
                );
                ?>
            </p>
        <?php else : ?>
            <h1><?php echo esc_html( Config::GDPR_MSG_INVALID ); ?></h1>
            <p><?php echo esc_html( $message ); ?></p>
        <?php endif; ?>

    </div>
</div>
