<?php
/**
 * Template: Gutenberg Contact Form Block
 *
 * @var array $atts Attributes from block
 */
?>

<?php echo do_shortcode( '[contactin_contact_form ' .
    'form_id="' . esc_attr( $atts['formId'] ) . '" ' .
    'success="' . esc_attr( $atts['successMessage'] ) . '" ' .
    'recaptcha="' . esc_attr( $atts['recaptcha'] ) . '" ' .
    'confetti="' . esc_attr( $atts['confetti'] ) . '" ' .
    'attachment="' . esc_attr( $atts['attachment'] ) . '" ' .
    'consent="' . esc_attr( $atts['consent'] ) . '" ' .
    ( $atts['showPhone'] ? '' : 'hide_phone="1"' ) .
']' ); ?>
