<?php
/**
 * Frontend – Shortcode & Form Renderer
 *
 * Supports:
 * [contact_inbox_form]
 * [contact_inbox_form form_id="support" success="Thank you!" recaptcha="on"]
 *
 * Also auto-detects Gutenberg block usage.
 *
 * @package ContactInbox\Frontend
 * @since   1.0.0
 */

namespace ContactInbox\Frontend;

use ContactInbox\Core\Config;
use ContactInbox\Core\reCAPTCHA;
use ContactInbox\Core\Settings as CoreSettings;
use ContactInbox\Traits\Singleton;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class Shortcode {
    use Singleton;

    protected function __construct() {
        add_shortcode( 'contact_inbox_form', [ $this, 'render' ] );

        // Smart asset loading – only when form is present
        add_action( 'wp_enqueue_scripts', [ $this, 'conditional_assets' ], 20 );
    }

    /**
     * Render the contact form
     */
    public function render( array $atts ): string {
        try {
            $atts = shortcode_atts( [
                'form_id'    => 'default',
                'success'    => '',
                'recaptcha'  => 'auto', // auto | on | off
                'confetti'   => 'auto', // auto | on | off
                'attachment' => 'on',   // on | off
                'consent'    => 'on',   // on | off
            ], $atts, 'contact_inbox_form' );

            // Sanitize attributes
            $form_id          = sanitize_key( $atts['form_id'] );
            $success_message  = ! empty( $atts['success'] )
                ? wp_kses_post( $atts['success'] )
                : $this->get_setting( 'success_message', __( 'Thank you! We’ll get back to you soon.', 'contact-inbox' ) );

            $enable_recaptcha  = $this->resolve_bool( $atts['recaptcha'], reCAPTCHA::is_enabled() );
            $enable_confetti   = $this->resolve_bool( $atts['confetti'], (bool) $this->get_setting( 'confetti_enable' ) );
            $enable_attachment = $atts['attachment'] === 'on';
            $enable_consent    = $atts['consent'] === 'on';

            $args = [
                'form_id'            => $form_id,
                'success_message'    => $success_message,
                'enable_recaptcha'   => $enable_recaptcha,
                'enable_confetti'    => $enable_confetti,
                'enable_attachment'  => $enable_attachment,
                'enable_consent'     => $enable_consent,
                'recaptcha_site_key' => $enable_recaptcha ? reCAPTCHA::get_site_key() : '',
                'consent_text'       => $this->get_setting( 'consent_text', __( 'I consent to my data being used to respond to this message.', 'contact-inbox' ) ),
                'privacy_url'        => $this->get_setting( 'privacy_url', get_privacy_policy_url() ),
            ];

            // Fire hook: Track form view
            do_action( 'contactin_form_rendered', $form_id );

            ob_start();
            load_template( CONTACTINBOX_PATH . 'templates/frontend/form.php', false, $args );
            $output = ob_get_clean();
            return $output;
        } catch (\Throwable $e) {
            error_log('[ContactInbox ERROR] Shortcode render() exception: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
            return '<div class="cin-error">An error occurred while rendering the contact form.</div>';
        }
    }

    /**
     * Conditionally enqueue assets only when form is present
     */
    public function conditional_assets(): void {
        if ( ! $this->is_form_on_page() ) {
            return;
        }

        // reCAPTCHA script (only if enabled globally)
        if ( reCAPTCHA::is_enabled() ) {
            wp_enqueue_script(
                'google-recaptcha',
                'https://www.google.com/recaptcha/api.js?render=' . rawurlencode( reCAPTCHA::get_site_key() ),
                [],
                null,
                true
            );

            wp_add_inline_script(
                'contactin-frontend',
                sprintf( 'window.cinRecaptcha = { enabled: true, siteKey: "%s" };', esc_js( reCAPTCHA::get_site_key() ) ),
                'before'
            );
        }
    }

    /**
     * Universal form detection (shortcode + Gutenberg block + reusable blocks)
     */
    private function is_form_on_page(): bool {
        global $post;

        if ( ! is_a( $post, 'WP_Post' ) ) {
            return false;
        }

        // 1. Classic shortcode
        if ( has_shortcode( $post->post_content, 'contact_inbox_form' ) ) {
            return true;
        }

        // 2. Gutenberg block (including reusable blocks)
        if ( has_block( 'contactin/contact-form', $post ) ) {
            return true;
        }

        // 3. Deep scan for reusable blocks
        $blocks = parse_blocks( $post->post_content );
        foreach ( $blocks as $block ) {
            if ( isset( $block['blockName'] ) && $block['blockName'] === 'contactin/contact-form' ) {
                return true;
            }
            if ( ! empty( $block['innerBlocks'] ) ) {
                foreach ( $block['innerBlocks'] as $inner ) {
                    if ( isset( $inner['blockName'] ) && $inner['blockName'] === 'contactin/contact-form' ) {
                        return true;
                    }
                }
            }
        }

        return false;
    }

    /**
     * Resolve 'auto' → use global setting
     */
    private function resolve_bool( string $value, bool $global_setting ): bool {
        if ( $value === 'on' ) {
            return true;
        }
        if ( $value === 'off' ) {
            return false;
        }
        return $global_setting; // 'auto'
    }

    /**
     * Safe helper to get settings from Core\Settings
     */
    private function get_setting( string $key, $default = false ) {
        return CoreSettings::get_settings()[ $key ] ?? $default;
    }
}
