<?php
/**
 * GDPR Deletion Error Template
 *
 * @package ContactIn
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use ContactInbox\Core\Config;

// phpcs:disable WordPress.WP.I18n.NonSingularStringLiteralDomain, WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound

$home_url = home_url( '/' );
$message  = get_query_var( 'gdpr_error_message' );
?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<title><?php esc_html_e( 'Deletion Error', 'contactin' ); ?> - <?php bloginfo( 'name' ); ?></title>
	<?php wp_head(); ?>
</head>
<body class="cin-gdpr-page cin-gdpr-error">
<div class="cin-response error">
	<h2><?php esc_html_e( 'Deletion Failed', 'contactin' ); ?></h2>
	<p><?php echo esc_html( $message ); ?></p>
	<p class="contactin-gdpr-delete">
		<a href="<?php echo esc_url( $home_url ); ?>">
			<?php esc_html_e( 'Back to Homepage', 'contactin' ); ?>
		</a>
	</p>
</div>
<?php wp_footer(); ?>
</body>
</html>
<?php exit; ?>
