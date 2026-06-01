<?php
/**
 * Template: Gutenberg Contact Form Block
 *
 * @var array $atts Attributes from block
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

?>

<?php
echo do_shortcode( '[contactin_form form_id="' . esc_attr( $atts['formId'] ) . '"]' );
