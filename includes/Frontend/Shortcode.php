<?php
/**
 * Frontend – Shortcode & Form Renderer
 *
 * Supports:
 * [contactin_form]
 * [contactin_form form_id="support" success="Thank you!" recaptcha="on"]
 *
 * Also auto-detects Gutenberg block usage.
 *
 * @package ContactIn\Frontend
 * @since   1.0.0
 */

namespace ContactInbox\Frontend;

use ContactInbox\Core\Config;
use ContactInbox\Core\reCAPTCHA;
use ContactInbox\Core\Settings as CoreSettings;
use ContactInbox\Core\FormProfiles;
use ContactInbox\Traits\Singleton;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
// phpcs:disable WordPress.WP.I18n.TextDomainMismatch, WordPress.PHP.DevelopmentFunctions.error_log_error_log, Generic.PHP.ForbiddenFunctions.Found, PluginCheck.CodeAnalysis.DiscouragedFunctions.load_plugin_textdomainFound, PluginCheck.CodeAnalysis.Heredoc.NotAllowed, PluginCheck.Security.DirectDB.UnescapedDBParameter, Squiz.PHP.DiscouragedFunctions.Discouraged, WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.NamingConventions.PrefixAllGlobals.DynamicHooknameFound, WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound, WordPress.PHP.DevelopmentFunctions.error_log_debug_backtrace, WordPress.WP.AlternativeFunctions.file_system_operations_fsockopen, WordPress.WP.AlternativeFunctions.file_system_operations_readfile, WordPress.WP.AlternativeFunctions.file_system_operations_rmdir, WordPress.WP.EnqueuedResourceParameters.MissingVersion, WordPress.WP.EnqueuedResources.NonEnqueuedScript, WordPress.WP.I18n.MissingArgDomain, WordPress.WP.I18n.UnorderedPlaceholdersPlural, WordPress.WP.I18n.UnorderedPlaceholdersSingle

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Shortcode {
	use Singleton;

	protected function __construct() {
		add_shortcode( 'contactin_form', array( $this, 'render' ) );
		// Backward-compat alias – existing posts using [contact_inbox_form] keep working.
		add_shortcode( 'contact_inbox_form', array( $this, 'render' ) );

		// Smart asset loading – only when form is present
		add_action( 'wp_enqueue_scripts', array( $this, 'conditional_assets' ), 20 );
	}

	/**
	 * Render the contact form
	 */
	public function render( array $atts ): string {
		try {
			$atts = shortcode_atts(
				array(
					'form_id'      => 'default',
					'success'      => '',
					'recaptcha'    => 'auto', // auto | on | off
					'confetti'     => 'auto', // auto | on | off
					'attachment'   => 'auto', // auto | on | off — follow profile; global is the ceiling
					'consent'      => 'auto', // auto | on | off — follow profile by default
					'phone'        => 'auto', // auto | on | off — follow profile by default
					'salutation'   => 'auto',  // auto | on | off — salutation dropdown
					'subject'      => 'auto',  // auto | on | off — subject field
					'consent_text' => '',      // custom consent label; empty = use global
				),
				$atts,
				'contactin_form'
			);

			// Sanitize attributes
			$form_id         = sanitize_key( $atts['form_id'] );
			$success_message = ! empty( $atts['success'] )
				? wp_kses_post( $atts['success'] )
				: $this->get_setting( 'success_message', __( 'Thank you! We’ll get back to you soon.', 'contactin' ) );

			// Build per-placement overrides for the 3-level resolution chain.
			$overrides = array(
				'enable_phone'      => $atts['phone'],
				'enable_salutation' => $atts['salutation'],
				'enable_subject'    => $atts['subject'],
				'enable_attachment' => $atts['attachment'], // resolve() clamps this to the global ceiling
				'enable_consent'    => $atts['consent'],    // 'auto'|'on'|'off'
				'recaptcha'         => $atts['recaptcha'],
				'confetti'          => $atts['confetti'],
				'success_message'   => ! empty( $atts['success'] ) ? wp_kses_post( $atts['success'] ) : '',
				'consent_text'      => $atts['consent_text'],
			);
			$resolved  = FormProfiles::resolve( $form_id, $overrides );

			$enable_recaptcha  = ! empty( $resolved['recaptcha_enable'] ) && ! empty( $resolved['recaptcha_site_key'] );
			$enable_confetti   = ! empty( $resolved['confetti_enable'] );
			$enable_attachment = false;
			$enable_consent    = isset( $resolved['form_enable_consent'] ) ? (bool) $resolved['form_enable_consent'] : true;
			$enable_phone      = isset( $resolved['form_enable_phone'] ) ? (bool) $resolved['form_enable_phone'] : true;
			$enable_salutation = ! empty( $resolved['form_enable_salutation'] );
			$enable_subject    = ! empty( $resolved['form_enable_subject'] );

			$args = array(
				'form_id'            => $form_id,
				'success_message'    => ! empty( $resolved['success_message'] ) ? $resolved['success_message'] : $success_message,
				'enable_recaptcha'   => $enable_recaptcha,
				'enable_confetti'    => $enable_confetti,
				'enable_attachment'  => $enable_attachment,
				'enable_consent'     => $enable_consent,
				'enable_phone'       => $enable_phone,
				'require_phone'      => isset( $resolved['require_phone'] ) ? (bool) $resolved['require_phone'] : false,
				'enable_salutation'  => $enable_salutation,
				'enable_subject'     => $enable_subject,
				'recaptcha_site_key' => $enable_recaptcha ? reCAPTCHA::get_site_key() : '',
				'consent_text'       => ! empty( $resolved['consent_text'] )
					? $resolved['consent_text']
					: $this->get_setting( 'consent_text', __( 'I consent to my data being used to respond to this message.', 'contactin' ) ),
				'privacy_url'        => $this->get_setting( 'privacy_url', get_privacy_policy_url() ),
				'settings'           => $resolved,
			);

			// Fire hook: Track form view
			do_action( 'contactin_form_rendered', $form_id );

			ob_start();
			load_template( CONTACTINBOX_PATH . 'templates/frontend/form.php', false, $args );
			$output = ob_get_clean();
			return $output;
		} catch ( \Throwable $e ) {
			error_log( '[ContactInbox ERROR] Shortcode render() exception: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine() );
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
				array(),
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
		if ( has_shortcode( $post->post_content, 'contactin_form' ) ) {
			return true;
		}
		// Backward-compat: posts still using old [contact_inbox_form] tag
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
