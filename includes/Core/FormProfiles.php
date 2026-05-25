<?php
/**
 * Core – Form Profiles
 *
 * Named form configurations stored in wp_options.
 * Each profile customises field visibility, validation rules,
 * success messages, and notification routing independently of
 * the global Settings page.
 *
 * Priority chain (highest wins):
 *   3. Per-placement block / shortcode overrides (in-editor tweaks)
 *   2. Named form profile        (central config per form type)
 *   1. Global Settings           (site-wide baseline)
 *
 * @package ContactIn\Core
 */

declare(strict_types=1);

namespace ContactInbox\Core;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// phpcs:disable WordPress.WP.I18n.TextDomainMismatch

final class FormProfiles {

	public const OPTION_KEY = 'contactin_form_profiles';

	/** In-memory cache to avoid repeated get_option() calls. */
	private static ?array $cache = null;

	// -------------------------------------------------------------------------
	// Read
	// -------------------------------------------------------------------------

	/**
	 * Return all saved profiles, keyed by slug.
	 *
	 * @return array<string, array>
	 */
	public static function all(): array {
		if ( self::$cache !== null ) {
			return self::$cache;
		}
		$saved       = get_option( self::OPTION_KEY, array() );
		self::$cache = is_array( $saved ) ? $saved : array();
		return self::$cache;
	}

	/**
	 * Return a single profile by slug, or null if not found.
	 */
	public static function get( string $id ): ?array {
		$all = self::all();
		return isset( $all[ $id ] ) ? $all[ $id ] : null;
	}

	/**
	 * Return a simplified list suitable for block/widget dropdowns.
	 * Format: [ ['slug' => 'sales', 'label' => 'Sales Enquiry'], ... ]
	 *
	 * @return list<array{slug:string,label:string}>
	 */
	public static function options_list(): array {
		$all  = self::all();
		$list = array();
		foreach ( $all as $slug => $profile ) {
			$list[] = array(
				'slug'  => $slug,
				'label' => ( ( $profile['label'] ?? '' ) !== '' ) ? $profile['label'] : $slug,
			);
		}
		// 'default' always comes first; rest sorted by label.
		usort(
			$list,
			static function ( $a, $b ) {
				if ( $a['slug'] === 'default' ) {
					return -1;
				}
				if ( $b['slug'] === 'default' ) {
					return 1;
				}
				return strcmp( (string) $a['label'], (string) $b['label'] );
			}
		);
		return $list;
	}

	// -------------------------------------------------------------------------
	// Write
	// -------------------------------------------------------------------------

	/**
	 * Save (create or update) a profile.
	 *
	 * @param string $id     Slug, e.g. 'support', 'quote-request'.
	 * @param array  $config Profile fields (will be sanitized internally).
	 */
	public static function save( string $id, array $config ): bool {
		$id = sanitize_key( $id );
		if ( $id === '' ) {
			return false;
		}
		$all         = self::all();
		$all[ $id ]  = self::sanitize_profile( $config );
		$result      = update_option( self::OPTION_KEY, $all, false );
		self::$cache = null;
		return (bool) $result;
	}

	/**
	 * Delete a named profile.
	 * The 'default' slug is protected and cannot be deleted.
	 */
	public static function delete( string $id ): bool {
		if ( $id === 'default' ) {
			return false;
		}
		$all = self::all();
		if ( ! isset( $all[ $id ] ) ) {
			return false;
		}
		unset( $all[ $id ] );
		update_option( self::OPTION_KEY, $all, false );
		self::$cache = null;
		return true;
	}

	/**
	 * Invalidate in-memory cache (call after any direct DB work).
	 */
	public static function clear_cache(): void {
		self::$cache = null;
	}

	// -------------------------------------------------------------------------
	// Resolve — the definitive getter used by rendering and validation
	// -------------------------------------------------------------------------

	/**
	 * Merge global settings → named profile → placement overrides into one
	 * flat array that is always compatible with FormService::validate_submission_payload()
	 * and form.php.
	 *
	 * @param string $form_id    Profile slug ('default' if not specified).
	 * @param array  $overrides  Per-placement overrides from Shortcode / Block.
	 *                           Recognised keys:
	 *                             enable_phone      (bool|string 'on'|'off')
	 *                             enable_salutation (bool|string 'on'|'off'|'auto')
	 *                             enable_subject    (bool|string 'on'|'off'|'auto')
	 *                             enable_consent    (bool)
	 *                             recaptcha         (string 'auto'|'on'|'off')
	 *                             confetti          (string 'auto'|'on'|'off')
	 *                             success_message   (string)
	 *                             consent_text      (string)
	 * @return array  Full settings array — superset of global settings.
	 */
	public static function resolve( string $form_id = 'default', array $overrides = array() ): array {
		$global  = Settings::get_settings();
		$profile = self::get( $form_id ) ?? array();

		// --- Layer 1: start from global settings as the baseline ---
		$resolved = $global;

		// Add keys that exist at profile level but not in global settings.
		$resolved['form_enable_phone']      = true;
		$resolved['require_phone']          = (bool) ( $global['form_require_phone'] ?? false );
		$resolved['form_require_subject']   = (bool) ( $global['form_require_subject'] ?? true );
		$resolved['form_enable_consent']    = true;
		$resolved['form_enable_attachment'] = $resolved['form_enable_attachment'] ?? false;

		// --- Layer 2: apply named profile overrides ---
		if ( $profile !== array() ) {
			if ( isset( $profile['show_subject'] ) ) {
				$resolved['form_enable_subject']  = (bool) $profile['show_subject'];
				$resolved['form_require_subject'] = isset( $profile['require_subject'] )
					? (bool) $profile['require_subject']
					: (bool) $profile['show_subject'];
			}
			if ( isset( $profile['show_salutation'] ) ) {
				$resolved['form_enable_salutation'] = (bool) $profile['show_salutation'];
			}
			if ( isset( $profile['show_consent'] ) ) {
				$resolved['form_enable_consent'] = (bool) $profile['show_consent'];
				if ( ! $resolved['form_enable_consent'] ) {
					$resolved['consent_required'] = false; // hidden field cannot be required
				}
			}
			if ( ! empty( $profile['success_message'] ) ) {
				$resolved['success_message'] = $profile['success_message'];
			}
			if ( ! empty( $profile['consent_text'] ) ) {
				$resolved['consent_text'] = $profile['consent_text'];
			}
			if ( ! empty( $profile['notify_email'] ) ) {
				$resolved['admin_email'] = $profile['notify_email'];
			}
			if ( isset( $profile['recaptcha'] ) && $profile['recaptcha'] !== 'auto' ) {
				$resolved['recaptcha_enable'] = $profile['recaptcha'] === 'on';
			}
			if ( isset( $profile['confetti'] ) && $profile['confetti'] !== 'auto' ) {
				$resolved['confetti_enable'] = $profile['confetti'] === 'on';
			}
			// These use the same if-guard pattern as the other profile fields so that
			// missing keys (e.g. old profiles pre-dating the field) fall back to the
			// already-resolved global value rather than a hardcoded literal.
			if ( isset( $profile['show_phone'] ) ) {
				$resolved['form_enable_phone'] = (bool) $profile['show_phone'];
			}
			if ( isset( $profile['require_phone'] ) ) {
				$resolved['require_phone'] = (bool) $profile['require_phone'];
			}
		}

		// --- Layer 3: per-placement block / shortcode overrides (highest priority) ---
		if ( $overrides !== array() ) {
			if ( isset( $overrides['enable_phone'] ) ) {
				$resolved['form_enable_phone'] = self::tri_bool( $overrides['enable_phone'], $resolved['form_enable_phone'] );
			}
			if ( isset( $overrides['enable_salutation'] ) ) {
				$resolved['form_enable_salutation'] = self::tri_bool( $overrides['enable_salutation'], $resolved['form_enable_salutation'] );
			}
			if ( isset( $overrides['enable_subject'] ) ) {
				$resolved['form_enable_subject'] = self::tri_bool( $overrides['enable_subject'], $resolved['form_enable_subject'] );
			}
			if ( isset( $overrides['enable_attachment'] ) ) {
				$resolved['form_enable_attachment'] = self::tri_bool( $overrides['enable_attachment'], $resolved['form_enable_attachment'] );
			}
			if ( isset( $overrides['enable_consent'] ) ) {
				$resolved['form_enable_consent'] = self::tri_bool( $overrides['enable_consent'], $resolved['form_enable_consent'] );
				if ( ! $resolved['form_enable_consent'] ) {
					$resolved['consent_required'] = false; // hidden field cannot be required
				}
			}
			if ( isset( $overrides['recaptcha'] ) && $overrides['recaptcha'] !== 'auto' ) {
				$resolved['recaptcha_enable'] = $overrides['recaptcha'] === 'on';
			}
			if ( isset( $overrides['confetti'] ) && $overrides['confetti'] !== 'auto' ) {
				$resolved['confetti_enable'] = $overrides['confetti'] === 'on';
			}
			if ( ! empty( $overrides['success_message'] ) ) {
				$resolved['success_message'] = $overrides['success_message'];
			}
			if ( ! empty( $overrides['consent_text'] ) ) {
				$resolved['consent_text'] = $overrides['consent_text'];
			}
		}

		return $resolved;
	}

	// -------------------------------------------------------------------------
	// Internal helpers
	// -------------------------------------------------------------------------

	/**
	 * Resolve 'auto' / 'on' / 'off' / true / false / 1 / 0 to bool.
	 * 'auto' or null → returns $default (follow higher-priority layer).
	 */
	private static function tri_bool( $value, bool $default ): bool {
		if ( $value === 'on' || $value === true || $value === 1 ) {
			return true;
		}
		if ( $value === 'off' || $value === false || $value === 0 ) {
			return false;
		}
		return $default; // 'auto', null, or unknown
	}

	/**
	 * Sanitize raw profile data before persisting.
	 */
	private static function sanitize_profile( array $raw ): array {
		return array(
			'label'           => sanitize_text_field( $raw['label'] ?? '' ),
			'show_phone'      => (bool) ( $raw['show_phone'] ?? true ),
			'show_salutation' => (bool) ( $raw['show_salutation'] ?? false ),
			'show_subject'    => (bool) ( $raw['show_subject'] ?? false ),
			'show_consent'    => (bool) ( $raw['show_consent'] ?? true ),
			'require_phone'   => (bool) ( $raw['require_phone'] ?? false ),
			'require_subject' => (bool) ( $raw['require_subject'] ?? false ),
			'success_message' => wp_kses_post( $raw['success_message'] ?? '' ),
			'consent_text'    => wp_kses_post( $raw['consent_text'] ?? '' ),
			'recaptcha'       => in_array( $raw['recaptcha'] ?? 'auto', array( 'auto', 'on', 'off' ), true )
				? $raw['recaptcha'] : 'auto',
			'confetti'        => in_array( $raw['confetti'] ?? 'auto', array( 'auto', 'on', 'off' ), true )
				? $raw['confetti'] : 'auto',
			'notify_email'    => sanitize_email( $raw['notify_email'] ?? '' ),
		);
	}
}
