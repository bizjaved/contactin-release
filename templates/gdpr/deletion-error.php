<?php
/**
 * GDPR Deletion Error Template
 *
 * @package ContactInbox
 */
if ( ! defined( 'ABSPATH' ) ) exit;

use ContactInbox\Core\Config;

$contactin_home_url = home_url( '/' );
$contactin_message  = get_query_var('gdpr_error_message');
?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
    <meta charset="<?php bloginfo( 'charset' ); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?php esc_html_e( 'Deletion Error', 'contact-inbox' ); ?> - <?php bloginfo( 'name' ); ?></title>
    <?php wp_head(); ?>
</head>
<body class="cin-gdpr-page cin-gdpr-error">
<div class="cin-response error">
    <h2><?php esc_html_e( 'Deletion Failed', 'contact-inbox' ); ?></h2>
    <p><?php echo esc_html( $contactin_message ); ?></p>
    <p class="contactin-gdpr-delete">
        <a href="<?php echo esc_url( $contactin_home_url ); ?>">
            <?php esc_html_e( 'Back to Homepage', 'contact-inbox' ); ?>
        </a>
    </p>
</div>
<?php wp_footer(); ?>
</body>
</html>
<?php exit; ?>
