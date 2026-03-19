<?php
/**
 * Gutenberg Block – ContactIn Form
 *
 * Enterprise-Grade: Full dynamic block, live preview, server-side render, accessible.
 *
 * @package ContactInbox\Integrations
 */

namespace ContactInbox\Integrations;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class GutenbergBlock {

    public static function init(): void {
        add_action( 'init', [ __CLASS__, 'register_block' ] );
    }

    public static function register_block(): void {
        if ( ! function_exists( 'register_block_type' ) ) {
            return;
        }

        $script_path = CONTACTINBOX_PATH . 'dist/js/gutenberg-block.min.js';
        $editor_style_path = CONTACTINBOX_PATH . 'dist/css/gutenberg-editor.min.css';
        $frontend_style_path = CONTACTINBOX_PATH . 'dist/css/frontend.min.css';

        $script_version = file_exists( $script_path ) ? (string) filemtime( $script_path ) : CONTACTINBOX_VERSION;
        $editor_style_version = file_exists( $editor_style_path ) ? (string) filemtime( $editor_style_path ) : CONTACTINBOX_VERSION;
        $frontend_style_version = file_exists( $frontend_style_path ) ? (string) filemtime( $frontend_style_path ) : CONTACTINBOX_VERSION;

        // Register editor script
        wp_register_script(
            'contactin-gutenberg-block',
            CONTACTINBOX_URL . 'dist/js/gutenberg-block.min.js',
            [ 'wp-blocks','wp-block-editor','wp-element','wp-i18n','wp-components','wp-data' ],
            $script_version,
            true
        );

        // Register styles
        wp_register_style(
            'contactin-gutenberg-editor',
            CONTACTINBOX_URL . 'dist/css/gutenberg-editor.min.css',
            [ 'wp-edit-blocks' ],
            $editor_style_version
        );

        wp_register_style(
            'contactin-gutenberg-frontend',
            CONTACTINBOX_URL . 'dist/css/frontend.min.css',
            [],
            $frontend_style_version
        );

        register_block_type( 'contactin/contact-form', [
            'api_version'     => 3,
            'editor_script'   => 'contactin-gutenberg-block',
            'editor_style'    => 'contactin-gutenberg-editor',
            'style'           => 'contactin-gutenberg-frontend',
            'render_callback' => [ __CLASS__, 'render_server_side' ],
            'attributes'      => [
                'formId'         => [ 'type' => 'string',  'default' => 'default' ],
                'successMessage' => [ 'type' => 'string',  'default' => '' ],
                'showPhone'      => [ 'type' => 'boolean', 'default' => true ],
                'recaptcha'      => [ 'type' => 'string',  'default' => 'auto', 'enum' => [ 'auto','on','off' ] ],
                'confetti'       => [ 'type' => 'string',  'default' => 'auto', 'enum' => [ 'auto','on','off' ] ],
                'attachment'     => [ 'type' => 'string',  'default' => 'on',  'enum' => [ 'on','off' ] ],
                'consent'        => [ 'type' => 'string',  'default' => 'on',  'enum' => [ 'on','off' ] ],
            ],
        ] );

        wp_localize_script( 'contactin-gutenberg-block', 'contactinBlock', [
            'title'       => __( 'ContactIn Form', 'contact-inbox' ),
            'description' => __( 'Secure, GDPR-compliant contact form with reCAPTCHA v3, attachments, and confetti.', 'contact-inbox' ),
            'icon'        => 'email',
            'category'    => 'widgets',
            'keywords'    => [ 'contact','form','secure','gdpr','recaptcha' ],
        ] );
    }

    /**
     * Server-side render via template
     */
    public static function render_server_side( array $attributes ): string {
        $atts = shortcode_atts( [
            'formId'         => 'default',
            'successMessage' => '',
            'recaptcha'      => 'auto',
            'confetti'       => 'auto',
            'attachment'     => 'on',
            'consent'        => 'on',
            'showPhone'      => true,
        ], $attributes, 'contactin_contact_form' );

        ob_start();
        include CONTACTINBOX_PATH . 'templates/block-contact-form.php';
        return ob_get_clean();
    }
}

add_action( 'plugins_loaded', [ GutenbergBlock::class, 'init' ] );
