<?php
namespace ContactInbox\Admin\Assets;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class EditorAssets {
    use AssetHelpers;

    /**
     * Enqueue Elementor editor assets.
     */
    public function enqueue_elementor(): void {
        // Register and enqueue CSS from dist/css
        $this->register_style( 'contactin-elementor-editor', 'elementor-editor.min.css' );

        // Register and enqueue JS from dist/js
        $path = CONTACTINBOX_PATH . \ContactInbox\Core\Config::DIST_JS . 'gutenberg-block.min.js';
        $url  = CONTACTINBOX_URL  . \ContactInbox\Core\Config::DIST_JS . 'gutenberg-block.min.js';

        if ( file_exists( $path ) ) {
            wp_enqueue_script(
                'contactin-elementor-editor',
                $url,
                [ 'wp-blocks', 'wp-element', 'wp-editor' ],
                filemtime( $path ),
                true
            );
        }

        // Localize script
        wp_localize_script( 'contactin-elementor-editor', 'ContactINEditor', [
            'i18n' => [
                'form_block' => __( 'Contact Inbox Form', 'contact-inbox' ),
                'loading'    => __( 'Loading form…', 'contact-inbox' ),
                'error'      => __( 'Failed to load form.', 'contact-inbox' ),
            ],
        ] );
    }

    /**
     * Enqueue Gutenberg editor assets.
     */
    public function enqueue_gutenberg(): void {
        $this->register_style( 'contactin-gutenberg-editor', 'gutenberg-editor.min.css' );

        wp_localize_script( 'contactin-gutenberg-editor', 'ContactINGutenberg', [
            'i18n' => [
                'form_block' => __( 'Contact Inbox Form', 'contact-inbox' ),
                'loading'    => __( 'Loading form…', 'contact-inbox' ),
                'error'      => __( 'Failed to load form.', 'contact-inbox' ),
            ],
        ] );
    }
}
