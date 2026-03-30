<?php
if (!defined('ABSPATH')) exit;
/**
 * GDPR Deletion Confirmation Template
 *
 * @package ContactIn\Frontend
 */

use ContactInbox\Core\Config;

// phpcs:disable WordPress.WP.I18n.NonSingularStringLiteralDomain, WordPress.WP.I18n.MissingTranslatorsComment, WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound, WordPress.Security.EscapeOutput.OutputNotEscaped, WordPress.WP.I18n.NonSingularStringLiteralText

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
            <h1><?php esc_html_e( 'Your data deletion request has been processed successfully.',  'contactin'); ?></h1>
            <p>
                <?php
                printf(
                    esc_html__( 'Your data associated with %s has been permanently deleted.',  'contactin'),
                    '<strong>' . esc_html( $email ) . '</strong>'
                );
                ?>
            </p>
        <?php else : ?>
            <h1><?php esc_html_e( 'Invalid or expired deletion link.',  'contactin'); ?></h1>
            <p><?php echo esc_html( $message ); ?></p>
        <?php endif; ?>

    </div>
</div>
