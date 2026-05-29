<?php
/**
 * Admin – Form Profiles Page
 *
 * Registers wp_ajax_* handlers for the Form Profiles CRUD UI
 * rendered on the Settings → Forms tab.
 *
 * @package ContactIn\Admin\Pages
 */

declare(strict_types=1);

namespace ContactInbox\Admin\Pages;

use ContactInbox\Core\Config;
use ContactInbox\Core\FormProfiles;
use ContactInbox\Traits\Singleton;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// phpcs:disable WordPress.WP.I18n.TextDomainMismatch, WordPress.Security.NonceVerification.Missing -- Nonce verified via check_access() which calls check_ajax_referer()

final class FormProfilesPage {
	use Singleton;

	private function __construct() {
		add_action( 'wp_ajax_contactin_get_form_profiles', array( $this, 'ajax_get_profiles' ) );
		add_action( 'wp_ajax_contactin_save_form_profile', array( $this, 'ajax_save_profile' ) );
		add_action( 'wp_ajax_contactin_delete_form_profile', array( $this, 'ajax_delete_profile' ) );
	}

	// -------------------------------------------------------------------------
	// AJAX handlers
	// -------------------------------------------------------------------------

	/**
	 * GET  all profiles — fills the management table on page load or after save.
	 */
	public function ajax_get_profiles(): void {
		$this->check_access();
		wp_send_json_success(
			array(
				'profiles'     => FormProfiles::all(),
				'options_list' => FormProfiles::options_list(),
			)
		);
	}

	/**
	 * POST  create or update a single profile.
	 *
	 * Required POST fields: slug, label
	 * Optional fields: show_phone, show_salutation, show_subject,
	 *                  show_consent, require_phone,
	 *                  require_subject, success_message, consent_text,
	 *                  recaptcha, confetti, notify_email
	 */
	public function ajax_save_profile(): void {
		$this->check_access();

		$slug = sanitize_key( wp_unslash( $_POST['slug'] ?? '' ) );
		if ( $slug === '' ) {
			wp_send_json_error( array( 'message' => __( 'Profile slug is required.', 'contactin' ) ), 400 );
		}

		$submitted_email = sanitize_email( wp_unslash( $_POST['notify_email'] ?? '' ) );

		$config = array(
			'label'           => sanitize_text_field( wp_unslash( $_POST['label'] ?? '' ) ),
			'show_phone'      => ! empty( $_POST['show_phone'] ),
			'show_salutation' => ! empty( $_POST['show_salutation'] ),
			'show_subject'    => ! empty( $_POST['show_subject'] ),
			'show_consent'    => ! empty( $_POST['show_consent'] ),
			'require_phone'   => ! empty( $_POST['require_phone'] ),
			'require_subject' => ! empty( $_POST['require_subject'] ),
			'success_message' => wp_kses_post( wp_unslash( $_POST['success_message'] ?? '' ) ),
			'consent_text'    => wp_kses_post( wp_unslash( $_POST['consent_text'] ?? '' ) ),
			'recaptcha'       => sanitize_key( wp_unslash( $_POST['recaptcha'] ?? 'auto' ) ),
			'confetti'        => sanitize_key( wp_unslash( $_POST['confetti'] ?? 'auto' ) ),
			'notify_email'    => $submitted_email,
		);

		$saved = FormProfiles::save( $slug, $config );

		if ( ! $saved ) {
			wp_send_json_error( array( 'message' => __( 'Could not save profile.', 'contactin' ) ), 500 );
		}

		$response = array(
			'message'      => __( 'Form profile saved.', 'contactin' ),
			'profiles'     => FormProfiles::all(),
			'options_list' => FormProfiles::options_list(),
		);

		wp_send_json_success( $response );
	}

	/**
	 * POST  delete a named profile (slug = 'default' is rejected).
	 */
	public function ajax_delete_profile(): void {
		$this->check_access();

		$slug = sanitize_key( wp_unslash( $_POST['slug'] ?? '' ) );

		if ( $slug === 'default' ) {
			wp_send_json_error( array( 'message' => __( 'The default profile cannot be deleted.', 'contactin' ) ), 403 );
		}

		$deleted = FormProfiles::delete( $slug );

		if ( ! $deleted ) {
			wp_send_json_error( array( 'message' => __( 'Profile not found or could not be deleted.', 'contactin' ) ), 404 );
		}

		wp_send_json_success(
			array(
				'message'      => __( 'Form profile deleted.', 'contactin' ),
				'profiles'     => FormProfiles::all(),
				'options_list' => FormProfiles::options_list(),
			)
		);
	}

	// -------------------------------------------------------------------------
	// Internal
	// -------------------------------------------------------------------------

	private function check_access(): void {
		check_ajax_referer( Config::SETTINGS_NONCE_ACTION, 'nonce' );
		if ( ! current_user_can( Config::CAPABILITY ) ) {
			wp_send_json_error( array( 'message' => __( 'Unauthorized.', 'contactin' ) ), 403 );
		}
	}
}
