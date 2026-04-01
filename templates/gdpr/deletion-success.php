<?php
/**
 * GDPR Deletion Success Template
 *
 * @package ContactIn
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use ContactInbox\Core\Config;

// phpcs:disable WordPress.WP.I18n.NonSingularStringLiteralDomain, WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound, WordPress.WP.I18n.NonSingularStringLiteralText

$home_url = home_url( '/' );
?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<title><?php esc_html_e( 'Data Deleted', 'contactin' ); ?> - <?php bloginfo( 'name' ); ?></title>
	<?php wp_head(); ?>
</head>
<body class="cin-gdpr-page cin-gdpr-success">
<div class="cin-response success">
	<h2><?php esc_html_e( 'Your Data Has Been Deleted', 'contactin' ); ?></h2>
	<p><?php esc_html_e( 'Your data deletion request has been processed successfully.', 'contactin' ); ?></p>
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
