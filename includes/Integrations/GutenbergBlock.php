<?php
/**
 * Gutenberg Block – ContactIn Form
 *
 * Enterprise-Grade: Full dynamic block, live preview, server-side render, accessible.
 *
 * @package ContactIn\Integrations
 */

namespace ContactInbox\Integrations;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
// phpcs:disable WordPress.WP.I18n.TextDomainMismatch

final class GutenbergBlock {

	public static function init(): void {
		add_action( 'init', array( __CLASS__, 'register_block' ) );
	}

	public static function register_block(): void {
		if ( ! function_exists( 'register_block_type' ) ) {
			return;
		}

		$script_deps = array( 'wp-blocks', 'wp-block-editor', 'wp-element', 'wp-i18n', 'wp-components', 'wp-data', 'wp-server-side-render' );

		if ( ! wp_script_is( 'contactin-profile-core', 'registered' ) ) {
			\ContactInbox\Admin\ProfileManagerCore::register_script();
		}

		if ( wp_script_is( 'contactin-profile-core', 'registered' ) ) {
			$script_deps[] = 'contactin-profile-core';
		}

		// Register editor script
		wp_register_script(
			'contactin-gutenberg-block',
			CONTACTINBOX_URL . 'dist/js/gutenberg-block.min.js',
			$script_deps,
			CONTACTINBOX_VERSION,
			true
		);

		// Register editor style
		wp_register_style(
			'contactin-gutenberg-editor',
			CONTACTINBOX_URL . 'dist/css/gutenberg-editor.min.css',
			array( 'wp-edit-blocks' ),
			CONTACTINBOX_VERSION
		);

		// Frontend style (shared with shortcode renderer)
		wp_register_style(
			'contactin-gutenberg-frontend',
			CONTACTINBOX_URL . 'dist/css/frontend.min.css',
			array(),
			CONTACTINBOX_VERSION
		);

		// NOTE: Do NOT register a separate 'contactin-block-view' handle for frontend.min.js.
		// Assets::register_assets() already registers it under 'contactin-frontend'.
		// Using two different handles for the same file causes frontend.min.js to load
		// TWICE on Gutenberg block pages, creating duplicate form submit listeners
		// and therefore duplicate database records per submission.
		// The block's view_script is intentionally omitted here; Assets::enqueue_frontend_assets()
		// detects the block via has_form_on_page() and enqueues 'contactin-frontend' correctly.

		register_block_type(
			'contactin/contact-form',
			array(
				'api_version'     => 3,
				'editor_script'   => 'contactin-gutenberg-block',
				'editor_style'    => 'contactin-gutenberg-editor',
				'style'           => 'contactin-gutenberg-frontend',
				// view_script intentionally omitted — see note above.
				'render_callback' => array( __CLASS__, 'render_server_side' ),
				'attributes'      => array(
					'formId' => array(
						'type'    => 'string',
						'default' => 'default',
					),
				),
			)
		);

		// Block metadata only — data/nonce/ajaxurl are provided by contactinProfileCore (ProfileManagerCore).
		wp_localize_script(
			'contactin-gutenberg-block',
			'contactinBlock',
			array(
				'title'       => __( 'ContactIn Form', 'contactin' ),
				'description' => __( 'Secure, GDPR-compliant contact form with reCAPTCHA v3, attachments, and confetti.', 'contactin' ),
				'icon'        => 'email',
				'category'    => 'widgets',
				'keywords'    => array( 'contact', 'form', 'secure', 'gdpr', 'recaptcha' ),
			)
		);
	}

	/**
	 * Server-side render via template
	 */
	public static function render_server_side( array $attributes ): string {
		$form_id = sanitize_key( $attributes['formId'] ?? 'default' );

		// Ensure the main frontend script is enqueued. Assets::enqueue_frontend_assets()
		// handles the normal case; this covers popups / template parts where the block
		// renders after wp_enqueue_scripts has already fired.
		if ( ! wp_script_is( 'contactin-frontend', 'enqueued' ) ) {
			wp_enqueue_script( 'contactin-frontend' );
		}

		// Inline-print the contactin config object so the JS always has ajaxurl +
		// nonce regardless of whether Assets::enqueue_scripts() has been called.
		if ( ! wp_script_is( 'contactin-frontend', 'done' ) ) {
			$resolved = \ContactInbox\Core\FormProfiles::resolve( $form_id );
			$config   = wp_json_encode(
				array(
					'ajaxurl'          => admin_url( 'admin-ajax.php' ),
					'nonce'            => wp_create_nonce( \ContactInbox\Core\Config::FORM_SUBMIT_NONCE ),
					'recaptcha'        => array(
						'enabled'  => ! empty( $resolved['recaptcha_enable'] ),
						'site_key' => \ContactInbox\Core\reCAPTCHA::get_site_key(),
					),
					'confetti_enabled' => ! empty( $resolved['confetti_enable'] ),
					'success_message'  => $resolved['success_message'] ?? '',
					'message_timeout'  => absint( $resolved['message_timeout_ms'] ?? 10000 ),
					'allowedFileTypes' => $resolved['allowed_file_types'] ?? '',
					'maxFileSize'      => absint( $resolved['max_file_size'] ?? 0 ),
				)
			);
			wp_add_inline_script(
				'contactin-frontend',
				'window.contactin = window.contactin || ' . $config . ';',
				'before'
			);
		}

		ob_start();
		$atts = array( 'formId' => $form_id );
		include CONTACTINBOX_PATH . 'templates/block-contact-form.php';
		return ob_get_clean();
	}
}

add_action( 'plugins_loaded', array( GutenbergBlock::class, 'init' ) );
