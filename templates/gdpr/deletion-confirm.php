<?php
/**
 * GDPR Deletion Confirmation Template
 *
 * @package ContactIn
 */
if ( ! defined( 'ABSPATH' ) ) exit;

use ContactInbox\Core\Config;

// phpcs:disable WordPress.WP.I18n.NonSingularStringLiteralDomain, WordPress.WP.I18n.MissingTranslatorsComment, WordPress.Security.EscapeOutput.OutputNotEscaped, WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound, WordPress.DB.PreparedSQL.NotPrepared

$home_url = home_url( '/' );
$token = $args['token'] ?? '';
$email = $args['email'] ?? '';
$stats = $args['stats'] ?? [];
?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
    <meta charset="<?php bloginfo( 'charset' ); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?php esc_html_e( 'Confirm Data Deletion',  'contactin'); ?> - <?php bloginfo( 'name' ); ?></title>
    <?php wp_head(); ?>
</head>
<body class="cin-gdpr-page cin-gdpr-confirm">
<div class="cin-gdpr-container" data-cin-gdpr-confirm="1" data-token="<?php echo esc_attr( $token ); ?>" data-email="<?php echo esc_attr( $email ); ?>">
    <div class="cin-gdpr-header">
        <h1>⚠️ <?php esc_html_e( 'Confirm Data Deletion',  'contactin'); ?></h1>
        <p><?php echo esc_html( $email ); ?></p>
    </div>
    <div class="cin-gdpr-body">
        <div class="cin-warning">
            <div class="cin-warning-icon">🛑</div>
            <div class="cin-warning-content">
                <h3><?php esc_html_e( 'This action cannot be undone!',  'contactin'); ?></h3>
                <p><?php esc_html_e( 'Once you confirm, all your data associated with this email address will be permanently deleted from our system.',  'contactin'); ?></p>
            </div>
        </div>

        <div class="cin-deletion-list">
            <h3><?php esc_html_e( 'The following data will be deleted:',  'contactin'); ?></h3>
            
            <div class="cin-deletion-item">
                <div class="cin-deletion-icon">✉️</div>
                <div class="cin-deletion-text">
                    <strong><?php esc_html_e( 'Messages',  'contactin'); ?></strong>
                    <span><?php esc_html_e( 'All your contact form submissions',  'contactin'); ?></span>
                </div>
                <div class="cin-deletion-count"><?php echo esc_html( $stats['messages'] ?? 0 ); ?></div>
            </div>

            <div class="cin-deletion-item">
                <div class="cin-deletion-icon">📎</div>
                <div class="cin-deletion-text">
                    <strong><?php esc_html_e( 'Attachments',  'contactin'); ?></strong>
                    <span><?php esc_html_e( 'Files you uploaded with messages',  'contactin'); ?></span>
                </div>
                <div class="cin-deletion-count"><?php echo esc_html( $stats['attachments'] ?? 0 ); ?></div>
            </div>

            <div class="cin-deletion-item">
                <div class="cin-deletion-icon">👤</div>
                <div class="cin-deletion-text">
                    <strong><?php esc_html_e( 'Contact Record',  'contactin'); ?></strong>
                    <span><?php esc_html_e( 'Your name, email, phone, and activity history',  'contactin'); ?></span>
                </div>
                <div class="cin-deletion-count">1</div>
            </div>
        </div>

        <div class="cin-progress-container" id="cin-progress">
            <div class="cin-progress-bar">
                <div class="cin-progress-fill" id="cin-progress-fill">0%</div>
            </div>
            <p class="cin-progress-text" id="cin-progress-text"><?php esc_html_e( 'Initializing...',  'contactin'); ?></p>
        </div>

        <div class="cin-error-container" id="cin-error">
            <p id="cin-error-text"></p>
        </div>

        <div class="cin-actions" id="cin-actions">
            <a href="<?php echo esc_url( $home_url ); ?>" class="cin-btn cin-btn-cancel">
                <?php esc_html_e( 'Cancel',  'contactin'); ?>
            </a>
            <button type="button" class="cin-btn cin-btn-delete" id="cin-confirm-delete">
                <?php esc_html_e( 'Yes, Delete My Data',  'contactin'); ?>
            </button>
        </div>
    </div>
</div>
<?php wp_footer(); ?>
</body>
</html>
