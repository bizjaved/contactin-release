<?php
/**
 * Email Uniqueness Validator Trait
 *
 * Enforces that each contact has a unique email address.
 * This trait provides validation methods for contact email uniqueness.
 *
 * @package ContactIn\Core\Traits
 */

declare(strict_types=1);

namespace ContactInbox\Core\Traits;

use ContactInbox\Core\Config;
use ContactInbox\Core\Repositories\ContactRepository;

// phpcs:disable WordPress.WP.I18n.NonSingularStringLiteralDomain, WordPress.WP.I18n.MissingTranslatorsComment, WordPress.WP.I18n.UnorderedPlaceholdersText

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

trait EmailUniquenessValidator {
	/**
	 * Check if an email is already used by another contact.
	 *
	 * @param string $email The email to check
	 * @param int    $exclude_contact_id Contact ID to exclude from check (for updates)
	 * @return array{valid: bool, conflict_id: int|null, conflict_name: string|null}
	 */
	public static function validate_email_uniqueness( string $email, int $exclude_contact_id = 0 ): array {
		$email = sanitize_email( $email );

		if ( empty( $email ) ) {
			return array(
				'valid'         => true,
				'conflict_id'   => null,
				'conflict_name' => null,
			);
		}

		$repo     = new ContactRepository();
		$existing = $repo->find_by_email( $email );

		if ( ! $existing ) {
			return array(
				'valid'         => true,
				'conflict_id'   => null,
				'conflict_name' => null,
			);
		}

		// If it's the same contact, it's valid
		if ( $exclude_contact_id > 0 && $existing->id === $exclude_contact_id ) {
			return array(
				'valid'         => true,
				'conflict_id'   => null,
				'conflict_name' => null,
			);
		}

		// Email is already used by another contact
		return array(
			'valid'         => false,
			'conflict_id'   => $existing->id,
			'conflict_name' => $existing->name,
		);
	}

	/**
	 * Validate email when updating a contact.
	 *
	 * @param int    $contact_id Contact ID being updated
	 * @param string $new_email New email address
	 * @param string $current_email Current email address
	 * @return array{valid: bool, message: string|null}
	 */
	public static function validate_email_change( int $contact_id, string $new_email, string $current_email ): array {
		$new_email     = sanitize_email( $new_email );
		$current_email = sanitize_email( $current_email );

		// If email didn't change, it's valid
		if ( $new_email === $current_email ) {
			return array(
				'valid'   => true,
				'message' => null,
			);
		}

		// Empty email is allowed
		if ( empty( $new_email ) ) {
			return array(
				'valid'   => true,
				'message' => null,
			);
		}

		$validation = self::validate_email_uniqueness( $new_email, $contact_id );

		if ( ! $validation['valid'] ) {
			$message = sprintf(
				__( 'Email "%s" is already used by contact "%s" (ID: %d). Each contact must have a unique email address.', 'contactin' ),
				esc_html( $new_email ),
				esc_html( $validation['conflict_name'] ?? __( 'Unknown', 'contactin' ) ),
				$validation['conflict_id']
			);
			return array(
				'valid'   => false,
				'message' => $message,
			);
		}

		return array(
			'valid'   => true,
			'message' => null,
		);
	}

	/**
	 * Check email availability via AJAX.
	 *
	 * @return array{available: bool, message: string|null}
	 */
	public static function check_email_availability( string $email, int $exclude_contact_id = 0 ): array {
		$validation = self::validate_email_uniqueness( $email, $exclude_contact_id );

		if ( $validation['valid'] ) {
			return array(
				'available' => true,
				'message'   => null,
			);
		}

		$message = sprintf(
			__( 'Email already in use by %s', 'contactin' ),
			esc_html( $validation['conflict_name'] ?? __( 'another contact', 'contactin' ) )
		);

		return array(
			'available' => false,
			'message'   => $message,
		);
	}
}
