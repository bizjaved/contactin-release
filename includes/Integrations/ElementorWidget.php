<?php
/**
 * Elementor Widget – ContactIn Form
 *
 * Enterprise-Grade: Fully customizable, live preview, accessible, performant.
 *
 * @package ContactIn\Integrations
 */

namespace ContactInbox\Integrations;

use Elementor\Widget_Base;
use Elementor\Controls_Manager;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
// phpcs:disable WordPress.WP.I18n.TextDomainMismatch

if ( ! class_exists( '\Elementor\Plugin' ) ) {
	return;
}

final class ElementorWidget extends Widget_Base {

	public function get_name(): string {
		return 'contactin_contact_form';
	}

	public function get_title(): string {
		return __( 'ContactIn Form', 'contactin' );
	}

	public function get_icon(): string {
		return 'eicon-form-horizontal';
	}

	public function get_categories(): array {
		return array( 'basic', 'general' );
	}

	public function get_keywords(): array {
		return array( 'contact', 'form', 'secure', 'gdpr', 'recaptcha', 'spam', 'attachment' );
	}

	/**
	 * Tell Elementor which stylesheets this widget needs.
	 * Elementor will enqueue them automatically on any page that contains this widget,
	 * including template-library inserts and popups.
	 */
	public function get_style_depends(): array {
		return array( 'contactin-frontend', 'contactin-error-modal' );
	}

	/**
	 * Tell Elementor which scripts this widget needs.
	 */
	public function get_script_depends(): array {
		return array( 'contactin-frontend' );
	}

	protected function register_controls(): void {
		$this->start_controls_section(
			'section_form_settings',
			array(
				'label' => __( 'Form Profile', 'contactin' ),
				'tab'   => Controls_Manager::TAB_CONTENT,
			)
		);

		// Build options list from saved profiles.
		$profile_options = array();
		foreach ( \ContactInbox\Core\FormProfiles::options_list() as $p ) {
			$profile_options[ $p['slug'] ] = $p['label'];
		}
		// Always ensure 'default' is present even on a fresh install.
		if ( empty( $profile_options ) ) {
			$profile_options['default'] = __( 'Default', 'contactin' );
		}

		$this->add_control(
					'contactin_profile_info',
			array(
				'type' => Controls_Manager::RAW_HTML,
				'raw'  => sprintf(
					'<p style="font-size:11px;color:#6b7280;margin:0 0 10px;line-height:1.5;">%s</p>'
					. '<a href="%s" target="_blank" rel="noopener" style="font-size:11px;text-decoration:none;">%s ↗</a>',
					esc_html__( 'Profiles control which fields appear, notification routing, and submission behaviour. All profiles share the same inbox.', 'contactin' ),
					esc_url( admin_url( 'admin.php?page=' . \ContactInbox\Core\Config::MENU_SETTINGS . '#cin-tab-forms' ) ),
					esc_html__( 'Manage all profiles', 'contactin' )
				),
			)
		);

		$this->add_control(
			'form_id',
			array(
				'label'       => __( 'Active Profile', 'contactin' ),
				'type'        => Controls_Manager::SELECT,
				'default'     => 'default',
				'options'     => $profile_options,
				// 'none' prevents automatic AJAX re-render on every keystroke;
				// we trigger renderRemoteServer() manually from JS after profile saves.
				'render_type' => 'none',
			)
		);

		$this->add_control(
					'contactin_profile_actions',
			array(
				'type'            => Controls_Manager::RAW_HTML,
				'content_classes' => 'cin-profile-actions',
				'raw'             => sprintf(
					'<div class="cin-profile-action-bar" style="display:flex;gap:6px;flex-wrap:wrap;margin-top:4px;">'
					. '<button type="button" class="elementor-button elementor-button-default cin-edit-current-profile" style="font-size:11px;padding:4px 10px;flex:1;">%s</button>'
					. '<button type="button" class="elementor-button elementor-button-default cin-create-new-profile" style="font-size:11px;padding:4px 10px;flex:1;">%s</button>'
					. '</div>',
					esc_html__( '✏ Edit Profile', 'contactin' ),
					esc_html__( '+ New Profile', 'contactin' )
				),
			)
		);

		$this->end_controls_section();
	}

	/**
	 * Render widget output on the frontend.
	 */
	protected function render(): void {
		if ( ! wp_style_is( 'contactin-frontend', 'registered' ) || ! wp_script_is( 'contactin-frontend', 'registered' ) ) {
			\ContactInbox\Frontend\Assets::register_assets();
		}

		// Late-enqueue fallback: covers Elementor popups, template-library parts and
		// any context where wp_enqueue_scripts has already fired without our assets.
		if ( ! wp_style_is( 'contactin-frontend', 'enqueued' ) ) {
			wp_enqueue_style( 'contactin-frontend' );
		}
		if ( ! wp_style_is( 'contactin-error-modal', 'enqueued' ) ) {
			if ( ! wp_style_is( 'contactin-error-modal', 'registered' ) ) {
				wp_register_style(
					'contactin-error-modal',
					CONTACTINBOX_URL . 'dist/css/form-error-modal.css',
					array(),
					CONTACTINBOX_VERSION
				);
			}
			wp_enqueue_style( 'contactin-error-modal' );
		}
		if ( ! wp_script_is( 'contactin-frontend', 'enqueued' ) ) {
			wp_enqueue_script( 'contactin-frontend' );
		}

		$settings = $this->get_settings_for_display();
		$form_id  = sanitize_key( $settings['form_id'] ?? 'default' );

		echo do_shortcode( '[contactin_form form_id="' . esc_attr( $form_id ) . '"]' );
	}

	/**
	 * Use server-side (remote) rendering in the editor preview.
	 * This tells Elementor to call PHP render() via AJAX for the preview iframe
	 * instead of running the client-side content_template() Underscore template.
	 */
	protected function get_template_type(): string {
		return 'remote';
	}

	/**
	 * Disable output caching so Elementor always calls render() fresh.
	 */
	public function is_dynamic_content(): bool {
		return true;
	}

	/**
	 * JS template fallback — not used when get_template_type() returns 'remote',
	 * but required by Elementor's abstract Widget_Base contract.
	 */
	protected function content_template(): void {}
}

// Editor CSS/JS are enqueued by AssetsDispatcher via elementor/editor/after_enqueue_styles
// and elementor/editor/after_enqueue_scripts — no duplicate hook needed here.
