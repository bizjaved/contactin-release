<?php
/**
 * Elementor Widget – Contact Inbox Form
 *
 * Enterprise-Grade: Fully customizable, live preview, accessible, performant.
 *
 * @package ContactInbox\Integrations
 */

namespace ContactInbox\Integrations;

use Elementor\Widget_Base;
use Elementor\Controls_Manager;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! class_exists( '\Elementor\Plugin' ) ) {
    return;
}

final class ElementorWidget extends Widget_Base {

    public function get_name(): string {
        return 'contactin_contact_form';
    }

    public function get_title(): string {
        return __( 'Contact Inbox Form', 'contact-inbox-hub' );
    }

    public function get_icon(): string {
        return 'eicon-form-horizontal';
    }

    public function get_categories(): array {
        return [ 'basic', 'general' ];
    }

    public function get_keywords(): array {
        return [ 'contact', 'form', 'secure', 'gdpr', 'recaptcha', 'spam', 'attachment' ];
    }

    protected function register_controls(): void {
        $this->start_controls_section(
            'section_form_settings',
            [
                'label' => __( 'Form Settings', 'contact-inbox-hub' ),
                'tab'   => Controls_Manager::TAB_CONTENT,
            ]
        );

        $this->add_control(
            'form_id',
            [
                'label'       => __( 'Form ID', 'contact-inbox-hub' ),
                'type'        => Controls_Manager::TEXT,
                'default'     => 'default',
                'placeholder' => 'contact',
                'description' => __( 'Unique identifier for styling or tracking.', 'contact-inbox-hub' ),
            ]
        );

        $this->add_control(
            'success_message',
            [
                'label'       => __( 'Custom Success Message', 'contact-inbox-hub' ),
                'type'        => Controls_Manager::WYSIWYG,
                'placeholder' => __( 'Thank you! Your message has been sent.', 'contact-inbox-hub' ),
                'description' => __( 'Leave empty to use global setting.', 'contact-inbox-hub' ),
            ]
        );

        $this->add_control(
            'recaptcha',
            [
                'label'   => __( 'reCAPTCHA v3', 'contact-inbox-hub' ),
                'type'    => Controls_Manager::SELECT,
                'default' => 'auto',
                'options' => [
                    'auto' => __( 'Use Global Setting', 'contact-inbox-hub' ),
                    'on'   => __( 'Force Enable', 'contact-inbox-hub' ),
                    'off'  => __( 'Force Disable', 'contact-inbox-hub' ),
                ],
            ]
        );

        $this->add_control(
            'confetti',
            [
                'label'   => __( 'Confetti Celebration', 'contact-inbox-hub' ),
                'type'    => Controls_Manager::SELECT,
                'default' => 'auto',
                'options' => [
                    'auto' => __( 'Use Global Setting', 'contact-inbox-hub' ),
                    'on'   => __( 'Always Show', 'contact-inbox-hub' ),
                    'off'  => __( 'Never Show', 'contact-inbox-hub' ),
                ],
            ]
        );

        $this->add_control(
            'attachment',
            [
                'label'        => __( 'File Upload', 'contact-inbox-hub' ),
                'type'         => Controls_Manager::SWITCHER,
                'label_on'     => __( 'Show', 'contact-inbox-hub' ),
                'label_off'    => __( 'Hide', 'contact-inbox-hub' ),
                'return_value' => 'on',
                'default'      => 'on',
            ]
        );

        $this->add_control(
            'consent',
            [
                'label'        => __( 'Privacy Consent', 'contact-inbox-hub' ),
                'type'         => Controls_Manager::SWITCHER,
                'label_on'     => __( 'Show', 'contact-inbox-hub' ),
                'label_off'    => __( 'Hide', 'contact-inbox-hub' ),
                'return_value' => 'on',
                'default'      => 'on',
            ]
        );

        $this->end_controls_section();
    }

    /**
     * Render widget output on the frontend.
     */
    protected function render(): void {
        $settings = $this->get_settings_for_display();

        $atts = [
            'form_id'    => sanitize_key( $settings['form_id'] ?? 'default' ),
            'success'    => wp_kses_post( $settings['success_message'] ?? '' ),
            'recaptcha'  => sanitize_text_field( $settings['recaptcha'] ?? 'auto' ),
            'confetti'   => sanitize_text_field( $settings['confetti'] ?? 'auto' ),
            'attachment' => $settings['attachment'] === 'on' ? 'on' : 'off',
            'consent'    => $settings['consent'] === 'on' ? 'on' : 'off',
        ];

        $shortcode = '[contactin_contact_form';
        foreach ( $atts as $key => $value ) {
            if ( $value !== '' && $value !== 'auto' && $value !== 'default' ) {
                $shortcode .= sprintf( ' %s="%s"', $key, esc_attr( $value ) );
            } elseif ( $key === 'form_id' ) {
                $shortcode .= sprintf( ' form_id="%s"', esc_attr( $value ) );
            }
        }
        $shortcode .= ']';

        echo do_shortcode( $shortcode );
    }

    /**
     * Render widget output in the editor (live preview).
     */
    protected function content_template(): void {
        $template = plugin_dir_path(__FILE__) . '../templates/elementor-placeholder.php';
        if ( file_exists( $template ) ) {
            include $template;
        }
    }
}

// Enqueue editor-specific CSS for Elementor preview
add_action( 'elementor/editor/after_enqueue_styles', function() {
    wp_enqueue_style(
        'contactin-elementor-editor',
        plugins_url( 'dist/css/elementor-editor.min.css', dirname(__FILE__) ),
        [],
        defined('CONTACTINBOX_VERSION') ? CONTACTINBOX_VERSION : '1.0.0'
    );
} );
