<?php
/**
 * Frontend – Assets Manager
 *
 * Enterprise-Grade: Smart, fast, universal, dependency-safe.
 * Detects shortcode anywhere (blocks, widgets, reusable blocks, etc.)
 *
 * @package ContactInbox\Frontend
 */

namespace ContactInbox\Frontend;

use ContactInbox\Traits\Singleton;
use ContactInbox\Core\reCAPTCHA;
use ContactInbox\Core\Config;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class Assets {
    use Singleton;

    /**
     * Hook into WordPress enqueue system.
     */
    public static function init(): void {
        add_action( 'wp_enqueue_scripts', [ __CLASS__, 'enqueue_frontend_assets' ], 10 );
    }

    /**
     * Enqueue frontend assets only when form is present.
     */
    public static function enqueue_frontend_assets(): void {

        if ( ! self::should_enqueue() ) {
            return;
        }


        $defaults = [
            'form_enable_attachment' => false,
            'allowed_file_types' => 'jpg,png,gif,pdf,doc,docx',
            'max_file_size' => 5,
        ];
        $settings = get_option( Config::OPTION_SETTINGS, [] );
        // Merge defaults with saved settings, saved settings take precedence
        $settings = array_merge($defaults, (array)$settings);
        // Fallback: If allowed_file_types is empty, use default
        if (empty($settings['allowed_file_types'])) {
            $settings['allowed_file_types'] = $defaults['allowed_file_types'];
        }

        self::enqueue_styles();
        self::enqueue_confetti( $settings );
        self::enqueue_recaptcha();
        self::enqueue_scripts( $settings );
    }

    /**
     * Determine if assets should be enqueued.
     */
    private static function should_enqueue(): bool {
        // Require form shortcode detection anywhere on the page
        return self::has_form_on_page();
    }

    /**
     * Resolve asset version from file modification time with plugin version fallback.
     */
    private static function asset_version( string $relative_path ): string {
        $asset_path = CONTACTINBOX_PATH . ltrim( $relative_path, '/' );
        return file_exists( $asset_path ) ? (string) filemtime( $asset_path ) : CONTACTINBOX_VERSION;
    }

    /**
     * Enqueue critical frontend CSS.
     * 
     * GOLD STANDARD: CSS Priority System (Cascading Override)
     * 1. Theme Styles (activated WordPress theme) - HIGHEST PRIORITY
     * 2. WordPress Core Styles (wp-forms, wp-buttons)
     * 3. Plugin Default Styles (fallback) - LOWEST PRIORITY
     * 
     * Loading Order (Last = Highest Priority):
     * 1. Plugin styles load first (base styles)
     * 2. WordPress core styles load next (can override plugin)
     * 3. Theme styles load last via normal WordPress setup (can override all)
     * 
     * This ensures the activated theme's styles always take precedence,
     * with WordPress core styles as a middle layer, and plugin styles as fallback.
     */
    private static function enqueue_styles(): void {
        // Tier 3 (Load First): Plugin default styles as fallback base
        wp_enqueue_style(
            'contactin-frontend',
            CONTACTINBOX_URL . 'dist/css/frontend.min.css',
            [],
            self::asset_version( 'dist/css/frontend.min.css' )
        );

        // Enqueue error modal styles
        wp_enqueue_style(
            'contactin-error-modal',
            CONTACTINBOX_URL . 'dist/css/form-error-modal.css',
            [],
            self::asset_version( 'dist/css/form-error-modal.css' )
        );

        // Tier 2 (Load Second): WordPress core styles can override plugin styles
        // These are standard WordPress form and button styles
        wp_enqueue_style( 'wp-forms' );
        wp_enqueue_style( 'wp-buttons' );

        // Tier 1 (Load Last/Automatic): Theme styles
        // The activated theme's styles load automatically during normal WordPress setup.
        // They will naturally override both plugin and WordPress core styles due to
        // CSS cascade (later = higher priority). No action needed here.
    }

    /**
     * Enqueue confetti script if enabled.
     */
    private static function enqueue_confetti( array $settings ): void {
        if ( empty( $settings['confetti_enable'] ) ) {
            return;
        }

        wp_enqueue_script(
            'contactin-confetti',
            CONTACTINBOX_URL . 'dist/js/confetti.min.js',
            [],
            self::asset_version( 'dist/js/confetti.min.js' ),
            true
        );
    }

    /**
     * Enqueue Google reCAPTCHA v3 if enabled.
     */
    private static function enqueue_recaptcha(): void {
        if ( ! reCAPTCHA::is_enabled() ) {
            return;
        }

        wp_enqueue_script(
            'google-recaptcha',
            'https://www.google.com/recaptcha/api.js?render=' . urlencode( reCAPTCHA::get_site_key() ),
            [],
            null,
            true
        );
    }

    /**
     * Enqueue main frontend script and localize settings.
     */
    private static function enqueue_scripts( array $settings ): void {
        wp_enqueue_script(
            'contactin-frontend',
            CONTACTINBOX_URL . 'dist/js/frontend.min.js',
            [ 'jquery' ],
            self::asset_version( 'dist/js/frontend.min.js' ),
            true
        );

          // Enqueue file upload assets only if attachment feature is enabled
          if ( ! empty( $settings['form_enable_attachment'] ) ) {
            // Fallback: If allowed_file_types is empty, use default
            if (empty($settings['allowed_file_types'])) {
                $settings['allowed_file_types'] = 'jpg,png,gif,pdf,doc,docx';
            }
            wp_enqueue_script(
                'contactin-file-upload',
                CONTACTINBOX_URL . 'dist/js/file-upload.js',
                [],
                self::asset_version( 'dist/js/file-upload.js' ),
                true
            );

            // Enqueue file upload styles
            wp_enqueue_style(
                'contactin-file-upload',
                CONTACTINBOX_URL . 'dist/css/file-upload.css',
                [],
                self::asset_version( 'dist/css/file-upload.css' )
            );

            // Localize file upload script config only if attachments enabled
            wp_localize_script( 'contactin-file-upload', 'cinFormConfig', [
                'ajaxUrl'  => admin_url( 'admin-ajax.php' ),
                'restUrl'  => get_rest_url(),
                'nonce'    => wp_create_nonce( 'wp_rest' ),
                'maxFileSize' => absint( $settings['max_file_size'] ?? 5 ),
                'allowedFileTypes' => $settings['allowed_file_types'] ?? '',
            ] );
        }

        wp_localize_script( 'contactin-frontend', 'contactin', [
            'ajaxurl'           => admin_url( 'admin-ajax.php' ),
            'nonce'             => wp_create_nonce( Config::FORM_SUBMIT_NONCE ),
            'recaptcha'         => [
                'enabled'   => reCAPTCHA::is_enabled(),
                'site_key'  => reCAPTCHA::get_site_key(),
            ],
            'confetti_enabled'  => ! empty( $settings['confetti_enable'] ),
            'consent_text'      => $settings['consent_text'] ?? '',
            'message_timeout'   => absint( $settings['message_timeout_ms'] ?? 10000 ),
            'success_message'   => $settings['success_message'] ?? __( 'Thank you! Your message has been sent.', 'contact-inbox' ),
            'allowedFileTypes'  => $settings['allowed_file_types'] ?? '',
            'maxFileSize'       => absint( $settings['max_file_size'] ?? 0 ),
        ] );
    }

    /**
     * UNIVERSAL form detection – works everywhere.
     */
    private static function has_form_on_page(): bool {
        global $post;

        // 1. Current post/page content
        if ( $post && has_shortcode( $post->post_content, 'contact_inbox_form' ) ) {
            return true;
        }

        // 2. Direct string search in post content (for Gutenberg blocks)
        if ( $post && strpos( $post->post_content, '[contact_inbox_form' ) !== false ) {
            return true;
        }

        // 3. Any rendered content (widgets, etc.)
        $content = apply_filters( 'the_content', '' );
        if ( has_shortcode( $content, 'contact_inbox_form' ) ) {
            return true;
        }
        if ( strpos( $content, '[contact_inbox_form' ) !== false ) {
            return true;
        }

        // 4. Queried object (archives, etc.)
        $queried = get_queried_object();
        if ( $queried && isset( $queried->post_content ) && has_shortcode( $queried->post_content, 'contact_inbox_form' ) ) {
            return true;
        }
        if ( $queried && isset( $queried->post_content ) && strpos( $queried->post_content, '[contact_inbox_form' ) !== false ) {
            return true;
        }

        // 5. Output buffer check (last resort)
        if ( did_action( 'wp_body_open' ) ) {
            ob_start();
            $buffer = ob_get_contents();
            ob_end_clean();
            if ( $buffer && strpos( $buffer, '[contact_inbox_form' ) !== false ) {
                return true;
            }
        }

        return false;
    }
}
