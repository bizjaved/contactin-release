<?php
// phpcs:disable Generic.PHP.ForbiddenFunctions.Found, PluginCheck.CodeAnalysis.DiscouragedFunctions.load_plugin_textdomainFound, PluginCheck.CodeAnalysis.Heredoc.NotAllowed, PluginCheck.Security.DirectDB.UnescapedDBParameter, Squiz.PHP.DiscouragedFunctions.Discouraged, WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.NamingConventions.PrefixAllGlobals.DynamicHooknameFound, WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound, WordPress.PHP.DevelopmentFunctions.error_log_debug_backtrace, WordPress.WP.AlternativeFunctions.file_system_operations_fsockopen, WordPress.WP.AlternativeFunctions.file_system_operations_readfile, WordPress.WP.AlternativeFunctions.file_system_operations_rmdir, WordPress.WP.EnqueuedResourceParameters.MissingVersion, WordPress.WP.EnqueuedResources.NonEnqueuedScript, WordPress.WP.I18n.MissingArgDomain, WordPress.WP.I18n.UnorderedPlaceholdersPlural, WordPress.WP.I18n.UnorderedPlaceholdersSingle
/**
 * Frontend – Assets Manager
 *
 * Enterprise-Grade: Smart, fast, universal, dependency-safe.
 * Detects shortcode anywhere (blocks, widgets, reusable blocks, etc.)
 *
 * @package ContactIn\Frontend
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
		// Always register handles so get_style_depends() / get_script_depends() in
		// the Elementor widget can reference them even before conditional enqueue runs.
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'register_assets' ), 5 );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'enqueue_frontend_assets' ), 10 );
	}

	/**
	 * Register (but do NOT enqueue) all frontend handles unconditionally.
	 * This lets Elementor's get_style_depends() / get_script_depends() find the handles.
	 */
	public static function register_assets(): void {
		wp_register_style(
			'contactin-frontend',
			CONTACTINBOX_URL . 'dist/css/frontend.min.css',
			array(),
			CONTACTINBOX_VERSION
		);
		wp_register_style(
			'contactin-error-modal',
			CONTACTINBOX_URL . 'dist/css/form-error-modal.css',
			array(),
			CONTACTINBOX_VERSION
		);
		wp_register_script(
			'contactin-frontend',
			CONTACTINBOX_URL . 'dist/js/frontend.min.js',
			array( 'jquery' ),
			CONTACTINBOX_VERSION,
			true
		);
	}

	/**
	 * Enqueue frontend assets only when form is present.
	 */
	public static function enqueue_frontend_assets(): void {

		if ( ! self::should_enqueue() ) {
			return;
		}

		$defaults = array(
			'form_enable_attachment' => false,
			// NOTE: allowed_file_types intentionally not in defaults
			// Whitelist approach: no files allowed unless explicitly configured in settings
			'max_file_size'          => 5,
		);
		$settings = get_option( Config::OPTION_SETTINGS, array() );
		// Merge defaults with saved settings, saved settings take precedence
		$settings = array_merge( $defaults, (array) $settings );

		// Do NOT fall back to defaults if allowed_file_types is empty
		// Empty means no files are allowed (whitelist approach)
		if ( empty( $settings['allowed_file_types'] ) ) {
			$settings['allowed_file_types'] = '';
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
		// Handles are already registered by register_assets() – just enqueue them.
		wp_enqueue_style( 'contactin-frontend' );
		wp_enqueue_style( 'contactin-error-modal' );

		// Tier 2: WordPress core styles can override plugin styles
		wp_enqueue_style( 'wp-forms' );
		wp_enqueue_style( 'wp-buttons' );
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
			array(),
			'1.0',
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
			array(),
			null,
			true
		);
	}

	/**
	 * Enqueue main frontend script and localize settings.
	 */
	private static function enqueue_scripts( array $settings ): void {
		// Handle already registered by register_assets() – just enqueue.
		wp_enqueue_script( 'contactin-frontend' );

		wp_localize_script(
			'contactin-frontend',
			'contactin',
			array(
				'ajaxurl'          => admin_url( 'admin-ajax.php' ),
				'nonce'            => wp_create_nonce( Config::FORM_SUBMIT_NONCE ),
				'recaptcha'        => array(
					'enabled'  => reCAPTCHA::is_enabled(),
					'site_key' => reCAPTCHA::get_site_key(),
				),
				'confetti_enabled' => ! empty( $settings['confetti_enable'] ),
				'consent_text'     => $settings['consent_text'] ?? '',
				'message_timeout'  => absint( $settings['message_timeout_ms'] ?? 10000 ),
				'success_message'  => $settings['success_message'] ?? __( 'Thank you! Your message has been sent.', 'contactin' ),
				'allowedFileTypes' => $settings['allowed_file_types'] ?? '',
				'maxFileSize'      => absint( $settings['max_file_size'] ?? 0 ),
			)
		);
	}

	/**
	 * UNIVERSAL form detection – works everywhere.
	 */
	private static function has_form_on_page(): bool {
		global $post;

		// 1. Shortcode in post content
		if ( $post && has_shortcode( $post->post_content, 'contactin_form' ) ) {
			return true;
		}
		if ( $post && has_shortcode( $post->post_content, 'contact_inbox_form' ) ) {
			return true;
		}

		// 2. Gutenberg block – stored as an HTML comment, never matched by has_shortcode()
		if ( $post && function_exists( 'has_block' ) && has_block( 'contactin/contact-form', $post ) ) {
			return true;
		}
		// Also matches raw comment string (reusable blocks load via separate post)
		if ( $post && strpos( $post->post_content, 'wp:contactin/contact-form' ) !== false ) {
			return true;
		}

		// 3. Direct shortcode string search (widgets, classic editor)
		if ( $post && strpos( $post->post_content, '[contactin_form' ) !== false ) {
			return true;
		}

		// 3. Any rendered content (widgets, etc.)
		$content = apply_filters( 'the_content', '' );
		if ( has_shortcode( $content, 'contactin_form' ) ) {
			return true;
		}
		if ( strpos( $content, '[contactin_form' ) !== false ) {
			return true;
		}

		// 4. Queried object (archives, etc.)
		$queried = get_queried_object();
		if ( $queried && isset( $queried->post_content ) && has_shortcode( $queried->post_content, 'contactin_form' ) ) {
			return true;
		}
		if ( $queried && isset( $queried->post_content ) && strpos( $queried->post_content, '[contactin_form' ) !== false ) {
			return true;
		}

		// 5. Elementor widget detection – data is stored in _elementor_data post meta as JSON,
		// never in post_content, so shortcode checks above always miss it.
		if ( $post ) {
			$elementor_data = get_post_meta( $post->ID, '_elementor_data', true );
			if ( $elementor_data && strpos( $elementor_data, 'contactin_contact_form' ) !== false ) {
				return true;
			}
			// Elementor Pro global widgets / theme templates stored separately.
			$elementor_pro_data = get_post_meta( $post->ID, '_elementor_page_settings', true );
			if ( $elementor_pro_data && strpos( (string) $elementor_pro_data, 'contactin_contact_form' ) !== false ) {
				return true;
			}
		}

		// 6. Output buffer check (last resort)
		if ( did_action( 'wp_body_open' ) ) {
			ob_start();
			$buffer = ob_get_contents();
			ob_end_clean();
			if ( $buffer && strpos( $buffer, '[contactin_form' ) !== false ) {
				return true;
			}
		}

		return false;
	}
}
