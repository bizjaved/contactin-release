<?php
namespace ContactInbox\Core;

use ContactInbox\Core\Repositories\ContactRepository;
use ContactInbox\Core\Traits\EmailUniquenessValidator;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * ContactResolver
 *
 * Safely resolve or create a contact from submission payload.
 * Enforces email uniqueness: Each contact = one email address.
 * Handles email-first matching, then phone-based matching (normalized).
 */
final class ContactResolver {

	use EmailUniquenessValidator;

	/**
	 * Resolve or create contact and return contact_id plus normalized phones.
	 *
	 * Email-based uniqueness strategy:
	 * 1) If email provided, find contact by email (email is unique identifier)
	 * 2) If no email match, check by phone (but only use if no email conflict)
	 * 3) Create new contact only if email not already taken
	 *
	 * @param array  $payload Submission payload (name, email, phone fields)
	 * @param string $default_country Default country code for phone normalization
	 * @return array{contact_id:int|null, phones:array}
	 */
	public static function resolve( array $payload, string $default_country = '1' ): array {
		$repo = new ContactRepository();

		$email      = isset( $payload['email'] ) ? sanitize_email( $payload['email'] ) : '';
		$name       = isset( $payload['name'] ) ? sanitize_text_field( $payload['name'] ) : '';
		$salutation = isset( $payload['salutation'] ) ? sanitize_text_field( $payload['salutation'] ) : '';

		// Apply the phone intelligence to normalize, classify, and deduplicate
		$phones_bucketed = PhoneUtils::bucket( $payload, $default_country );
		$phones          = array(
			'mobile_phone' => $phones_bucketed['mobile_phone'] ?? null,
			'phone'        => $phones_bucketed['phone'] ?? null,
			'home_phone'   => $phones_bucketed['home_phone'] ?? null,
			'other_phone'  => $phones_bucketed['other_phone'] ?? null,
		);

		// 1) Email match (priority: email is the unique identifier)
		if ( $email !== '' ) {
			$contact = $repo->find_by_email( $email );
			if ( $contact ) {
				self::merge_contact_phones( $contact, $phones, $repo, $default_country );
				if ( ! empty( $salutation ) && empty( $contact->salutation ) ) {
					$repo->update( $contact->id, array( 'salutation' => $salutation ) );
				}
				return array(
					'contact_id' => $contact->id,
					'phones'     => $phones,
				);
			}
		}

		// 2) Phone match (normalized)
		// ONLY use phone match if the contact doesn't have a different email
		// (prevents merging different email addresses into same contact)
		foreach ( array( 'mobile_phone', 'phone', 'home_phone', 'other_phone' ) as $key ) {
			if ( ! empty( $phones[ $key ] ) ) {
				$contact = $repo->find_by_phone( $phones[ $key ], $default_country );
				if ( $contact ) {
					// Check: if incoming email is provided and differs from contact's email, skip
					if ( $email !== '' && ! empty( $contact->email ) && $email !== $contact->email ) {
						// Different email - don't merge. Create new contact instead.
						continue;
					}

					self::merge_contact_phones( $contact, $phones, $repo, $default_country );
					if ( ! empty( $salutation ) && empty( $contact->salutation ) ) {
						$repo->update( $contact->id, array( 'salutation' => $salutation ) );
					}
					// Update email if incoming email is provided and contact has no email yet
					if ( $email !== '' && empty( $contact->email ) ) {
						$repo->update( $contact->id, array( 'email' => $email ) );
					}
					return array(
						'contact_id' => $contact->id,
						'phones'     => $phones,
					);
				}
			}
		}

		// 3) Create new contact
		// Only if email is not already taken by another contact
		if ( $email !== '' ) {
			$existing = $repo->find_by_email( $email );
			if ( $existing ) {
				// Email already in use - return the existing contact
				// (This is a safety check, should be rare due to check above)
				self::merge_contact_phones( $existing, $phones, $repo, $default_country );
				return array(
					'contact_id' => $existing->id,
					'phones'     => $phones,
				);
			}
		}

		$primary_phone = $phones['mobile_phone']
			?: $phones['phone']
			?: $phones['home_phone']
			?: $phones['other_phone'];

		$new_id = $repo->insert(
			array(
				'salutation'      => $salutation,
				'name'            => $name,
				'email'           => $email !== '' ? $email : null,
				'primary_phone'   => $primary_phone,
				'mobile_phone'    => $phones['mobile_phone'],
				'home_phone'      => $phones['home_phone'],
				'other_phone'     => $phones['other_phone'],
				'source'          => 'form',
				'last_message_at' => current_time( 'mysql' ),
			)
		);

		return array(
			'contact_id' => $new_id ?: null,
			'phones'     => $phones,
		);
	}

	/**
	 * Merge new phones into an existing contact without overwriting existing numbers.
	 */
	private static function merge_contact_phones( $contact, array $incoming, ContactRepository $repo, string $default_country ): void {
		$map = array(
			'mobile_phone' => 'mobile_phone',
			'phone'        => 'primary_phone',
			'home_phone'   => 'home_phone',
			'other_phone'  => 'other_phone',
		);

		$existing = array();
		foreach ( $map as $bucket => $column ) {
			$val = $contact->$column ?? null;
			if ( ! empty( $val ) ) {
				$existing[] = PhoneUtils::normalize( (string) $val, $default_country );
			}
		}

		$updates = array();

		// Slot preference per bucket to keep intent but allow fallbacks
		$preferences = array(
			'mobile_phone' => array( 'mobile_phone', 'primary_phone', 'home_phone', 'other_phone' ),
			'phone'        => array( 'primary_phone', 'mobile_phone', 'home_phone', 'other_phone' ),
			'home_phone'   => array( 'home_phone', 'primary_phone', 'mobile_phone', 'other_phone' ),
			'other_phone'  => array( 'other_phone', 'home_phone', 'mobile_phone', 'primary_phone' ),
		);

		foreach ( $map as $bucket => $column ) {
			$new = $incoming[ $bucket ] ?? null;
			if ( empty( $new ) ) {
				continue;
			}

			$normalized = PhoneUtils::normalize( (string) $new, $default_country );
			if ( $normalized === '' ) {
				continue;
			}

			// Deduplicate against existing and pending updates
			$is_duplicate = false;
			foreach ( $existing as $have ) {
				if ( PhoneUtils::are_equal( $have, $normalized, $default_country ) ) {
					$is_duplicate = true;
					break;
				}
			}
			if ( $is_duplicate ) {
				continue;
			}

			// Find the first available preferred slot for this bucket
			foreach ( $preferences[ $bucket ] as $slot ) {
				$current_val = $contact->$slot ?? null;
				$pending_val = $updates[ $slot ] ?? null;
				if ( empty( $current_val ) && empty( $pending_val ) ) {
					$updates[ $slot ] = $normalized;
					$existing[]       = $normalized;
					break;
				}
			}
		}

		if ( ! empty( $updates ) ) {
			$repo->update( $contact->id, $updates );
		}
	}
}
