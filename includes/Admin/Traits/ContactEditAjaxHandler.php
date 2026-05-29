<?php
/**
 * Contact Edit AJAX Handler Trait
 *
 * Handles AJAX requests for contact editing operations.
 * Can be used by any admin page handler.
 */

declare(strict_types=1);

namespace ContactInbox\Admin\Traits;

use ContactInbox\Core\Config;
use ContactInbox\Core\Repositories\ContactRepository;
use ContactInbox\Core\Traits\EmailUniquenessValidator;

// phpcs:disable WordPress.WP.I18n.NonSingularStringLiteralDomain, WordPress.Security.ValidatedSanitizedInput, WordPress.Security.NonceVerification

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

trait ContactEditAjaxHandler {
	use EmailUniquenessValidator;

	/**
	 * Register AJAX handlers for contact editing
	 */
	public function register_contact_edit_ajax(): void {
		add_action( 'wp_ajax_contactin_get_contact_data', array( $this, 'handle_get_contact_data' ) );
		add_action( 'wp_ajax_contactin_update_contact', array( $this, 'handle_update_contact' ) );
		add_action( 'wp_ajax_contactin_check_email_availability', array( $this, 'handle_check_email_availability' ) );
	}

	/**
	 * Get contact data for edit modal
	 */
	public function handle_get_contact_data(): void {
		// Verify nonce
		if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'contactin_update_contact' ) ) {
			wp_send_json_error( array( 'message' => __( 'Security check failed.', 'contactin' ) ) );
		}

		// Check capabilities
		if ( ! current_user_can( Config::CAPABILITY ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'contactin' ) ) );
		}

		$contact_id = absint( wp_unslash( $_POST['contact_id'] ?? 0 ) );
		if ( ! $contact_id ) {
			wp_send_json_error( array( 'message' => __( 'Invalid contact ID.', 'contactin' ) ) );
		}

		$repo    = new ContactRepository();
		$contact = $repo->get_by_id( $contact_id );

		if ( ! $contact ) {
			wp_send_json_error( array( 'message' => __( 'Contact not found.', 'contactin' ) ) );
		}

		// Return contact data
		wp_send_json_success(
			array(
				'id'            => $contact->id,
				'salutation'    => $contact->salutation ?? '',
				'name'          => $contact->name ?? '',
				'email'         => $contact->email ?? '',
				'primary_phone' => $contact->primary_phone ?? '',
				'mobile_phone'  => $contact->mobile_phone ?? '',
				'home_phone'    => $contact->home_phone ?? '',
				'other_phone'   => $contact->other_phone ?? '',
				'source'        => $contact->source ?? '',
				'created_at'    => $contact->created_at ?? '',
				'updated_at'    => $contact->updated_at ?? '',
			)
		);
	}

	/**
	 * Update contact data
	 */
	public function handle_update_contact(): void {
		// Security checks
		if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'contactin_update_contact' ) ) {
			wp_send_json_error( array( 'message' => __( 'Security check failed.', 'contactin' ) ) );
		}

		if ( ! current_user_can( Config::CAPABILITY ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'contactin' ) ) );
		}

		$contact_id = absint( wp_unslash( $_POST['contact_id'] ?? 0 ) );
		if ( ! $contact_id ) {
			wp_send_json_error( array( 'message' => __( 'Invalid contact ID.', 'contactin' ) ) );
		}

		$repo    = new ContactRepository();
		$contact = $repo->get_by_id( $contact_id );

		if ( ! $contact ) {
			wp_send_json_error( array( 'message' => __( 'Contact not found.', 'contactin' ) ) );
		}

		// Prepare update data
		$update_data = array();

		if ( isset( $_POST['name'] ) ) {
			$update_data['name'] = sanitize_text_field( wp_unslash( $_POST['name'] ) );
		}
		if ( isset( $_POST['email'] ) ) {
			$update_data['email'] = sanitize_email( wp_unslash( $_POST['email'] ) );
		}
		if ( isset( $_POST['salutation'] ) ) {
			$update_data['salutation'] = sanitize_text_field( wp_unslash( $_POST['salutation'] ) );
		}
		if ( isset( $_POST['primary_phone'] ) ) {
			$update_data['primary_phone'] = sanitize_text_field( wp_unslash( $_POST['primary_phone'] ) );
		}
		if ( isset( $_POST['mobile_phone'] ) ) {
			$update_data['mobile_phone'] = sanitize_text_field( wp_unslash( $_POST['mobile_phone'] ) );
		}
		if ( isset( $_POST['home_phone'] ) ) {
			$update_data['home_phone'] = sanitize_text_field( wp_unslash( $_POST['home_phone'] ) );
		}
		if ( isset( $_POST['other_phone'] ) ) {
			$update_data['other_phone'] = sanitize_text_field( wp_unslash( $_POST['other_phone'] ) );
		}

		if ( empty( $update_data ) ) {
			wp_send_json_error( array( 'message' => __( 'No data to update.', 'contactin' ) ) );
		}

		// Validate email uniqueness if email is being updated
		if ( isset( $update_data['email'] ) ) {
			$new_email     = $update_data['email'];
			$current_email = $contact->email ?? '';

			$email_validation = self::validate_email_change( $contact_id, $new_email, $current_email );
			if ( ! $email_validation['valid'] ) {
				wp_send_json_error(
					array(
						'message' => $email_validation['message'],
						'field'   => 'email',
					)
				);
			}
		}

		$updated = $repo->update( $contact_id, $update_data );

		if ( $updated ) {
			wp_send_json_success(
				array(
					'message' => __( 'Contact updated successfully.', 'contactin' ),
				)
			);
		} else {
			wp_send_json_error(
				array(
					'message' => __( 'Failed to update contact.', 'contactin' ),
				)
			);
		}
	}

	/**
	 * Check if an email is available (not used by another contact)
	 */
	public function handle_check_email_availability(): void {
		// Security checks
		if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'contactin_update_contact' ) ) {
			wp_send_json_error( array( 'message' => __( 'Security check failed.', 'contactin' ) ) );
		}

		if ( ! current_user_can( Config::CAPABILITY ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'contactin' ) ) );
		}

		$email = sanitize_email( wp_unslash( $_POST['email'] ?? '' ) );
		if ( empty( $email ) ) {
			wp_send_json_success(
				array(
					'available' => true,
					'message'   => null,
				)
			);
		}

		$contact_id = absint( wp_unslash( $_POST['contact_id'] ?? 0 ) );
		$result     = self::check_email_availability( $email, $contact_id );

		if ( $result['available'] ) {
			wp_send_json_success( $result );
		} else {
			wp_send_json_error( $result );
		}
	}
}
