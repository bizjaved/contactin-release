<?php
namespace ContactInbox\Admin\Assets;

// phpcs:disable WordPress.WP.I18n.TextDomainMismatch, WordPress.PHP.DevelopmentFunctions.error_log_error_log
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class EditorAssets {
    use AssetHelpers;

    private static bool $elementor_loaded = false;
    private static bool $gutenberg_loaded = false;

    /**
     * Enqueue Elementor editor assets.
     * The inline profile manager (create / edit profiles) is rendered inside
     * the widget panel by elementor-editor.min.js, backed by cin-profile-core.js.
     *
     * IMPORTANT: Elementor's Editor::enqueue_scripts() resets the global $wp_scripts
     * to a brand-new \WP_Scripts() instance before firing elementor/editor/after_enqueue_scripts.
     * This wipes every handle registered during 'init' (including cin-profile-core).
     * We must therefore call ProfileManagerCore::register_script() again here to add
     * the handle — with its URL and localized data — to the fresh script queue.
     */
    public function enqueue_elementor(): void {
        if ( self::$elementor_loaded ) {
            return;
        }

        $this->register_style( 'contactin-elementor-editor', 'elementor-editor.min.css' );

        // Re-register cin-profile-core in the fresh $wp_scripts that Elementor created.
        \ContactInbox\Admin\ProfileManagerCore::register_script();
        $deps = [ 'jquery' ];
        if ( wp_script_is( 'cin-profile-core', 'registered' ) ) {
            wp_enqueue_script( 'cin-profile-core' );
            $deps[] = 'cin-profile-core';
        }

        $this->register_script( 'contactin-elementor-editor', 'elementor-editor.min.js', $deps );
		self::$elementor_loaded = true;
    }

    /**
     * Enqueue Gutenberg editor assets.
     */
    public function enqueue_gutenberg(): void {
        if ( self::$gutenberg_loaded ) {
            return;
        }

        // Explicitly enqueue the shared core so cinProfileCore is available
        // before gutenberg-block.min.js runs.
        if ( ! wp_script_is( 'cin-profile-core', 'registered' ) ) {
            \ContactInbox\Admin\ProfileManagerCore::register_script();
        }
        if ( wp_script_is( 'cin-profile-core', 'registered' ) ) {
            wp_enqueue_script( 'cin-profile-core' );
        }

        $this->register_style( 'contactin-gutenberg-editor', 'gutenberg-editor.min.css' );

        wp_localize_script( 'contactin-gutenberg-editor', 'ContactINGutenberg', [
            'i18n' => [
                'form_block' => __( 'ContactIn Form', 'contactin' ),
                'loading'    => __( 'Loading form…', 'contactin' ),
                'error'      => __( 'Failed to load form.', 'contactin' ),
            ],
        ] );

		self::$gutenberg_loaded = true;
    }
}
