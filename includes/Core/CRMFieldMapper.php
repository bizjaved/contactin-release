<?php
namespace ContactInbox\Core;

use ContactInbox\Core\Logger;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Maps plugin fields to CRM fields safely.
 *
 * Supports regional naming conventions through configurable name field ordering.
 */
final class CRMFieldMapper {

	/**
	 * Split a 2+ word name into FirstName and LastName.
	 *
	 * Respects regional name ordering configuration:
	 * - 'first_last' (default): "John Doe" → FirstName="John", LastName="Doe"
	 * - 'last_first': "Doe John" → FirstName="John", LastName="Doe"
	 *
	 * @param string $fullName Name to split (must have 2+ words)
	 * @param string $order 'first_last' or 'last_first' (default: 'first_last')
	 * @return array { first => 'John', last => 'Doe', valid => true/false }
	 */
	public static function split_name( string $fullName, string $order = 'first_last' ): array {
		$parts = preg_split( '/\s+/', trim( $fullName ) );
		$count = count( $parts );

		// Must have at least 2 words for CRM integration
		if ( $count < 2 ) {
			return array(
				'first' => '',
				'last'  => '',
				'valid' => false,
			);
		}

		// Handle based on regional naming convention
		if ( $order === 'last_first' ) {
			// Last name comes first: "Doe John" → FirstName="John", LastName="Doe"
			$last  = $parts[0];
			$first = implode( ' ', array_slice( $parts, 1 ) );
		} else {
			// Default - First name comes first: "John Doe" → FirstName="John", LastName="Doe"
			$first = $parts[0];
			$last  = implode( ' ', array_slice( $parts, 1 ) );
		}

		return array(
			'first' => $first,
			'last'  => $last,
			'valid' => true,
		);
	}

	/**
	 * Validate that name has at least 2 words.
	 *
	 * @param string $name
	 * @return bool
	 */
	public static function is_valid_name( string $name ): bool {
		$parts = preg_split( '/\s+/', trim( $name ) );
		return count( $parts ) >= 2;
	}

	/**
	 * Apply admin-defined field mapping to payload (Legacy method - kept for backwards compatibility)
	 *
	 * @param array $params Plugin form params (name, email, message, etc.)
	 * @param array $mapping Admin-defined mapping from settings
	 * @param array $crm_settings CRM settings including name field order configuration
	 * @return array CRM-ready payload
	 */
	public static function apply_mapping( array $params, array $mapping, array $crm_settings = array() ): array {
		$payload = array();

		// Get name field ordering preference (default: first_last)
		$name_order = $crm_settings['name_field_order'] ?? 'first_last';

		foreach ( $mapping as $pluginField => $crmField ) {
			if ( ! isset( $params[ $pluginField ] ) || empty( $crmField ) ) {
				continue;
			}

			// Special handling for "name" field - split into FirstName and LastName
			if ( $pluginField === 'name' && ! empty( $params['name'] ) ) {
				$nameParts = self::split_name( $params['name'], $name_order );

				// If name is invalid (< 2 words), throw error for logging
				if ( ! $nameParts['valid'] ) {
					// Return error indicator that will be caught during sync
					return array(
						'_error'         => true,
						'_error_message' => 'Name must have at least 2 words (e.g., "John Doe"). Single-word names cannot be synced to CRM.',
					);
				}

				// Support formats: "FirstName,LastName" or "FirstName LastName"
				$crm_fields = preg_split( '/[\s,]+/', $crmField );
				$crm_fields = array_filter( array_map( 'trim', $crm_fields ) );

				foreach ( $crm_fields as $cf ) {
					if ( empty( $cf ) ) {
						continue;
					}

					if ( stripos( $cf, 'FirstName' ) !== false ) {
						$payload[ $cf ] = $nameParts['first'];
					} elseif ( stripos( $cf, 'LastName' ) !== false ) {
						$payload[ $cf ] = $nameParts['last'];
					} else {
						// If mapping is generic, use full name
						$payload[ $cf ] = $params['name'];
					}
				}
			} else {
				$payload[ $crmField ] = $params[ $pluginField ];
			}
		}

		return $payload;
	}

	/**
	 * Map Contact fields (person information) for two-object architecture
	 *
	 * @param array $params Plugin form params
	 * @param array $crm_settings CRM settings
	 * @param bool  $is_new_contact Whether this is a new contact (optional, defaults to true for safety)
	 * @return array Contact payload
	 */
	public static function map_contact_fields( array $params, array $crm_settings = array(), bool $is_new_contact = true ): array {
		$payload         = array();
		$name_order      = $crm_settings['name_field_order'] ?? 'first_last';
		$contact_mapping = $crm_settings['contact_mapping'] ?? array();
		$phone_strategy  = $crm_settings['phone_handling_strategy'] ?? 'secondary';

		// Default Contact field mappings if not customized
		$defaults = array(
			'email' => 'Email',
			'phone' => 'Phone',
		);

		// Map Name (required for Contact)
		if ( ! empty( $params['name'] ) ) {
			$nameParts = self::split_name( $params['name'], $name_order );

			if ( ! $nameParts['valid'] ) {
				return array(
					'_error'         => true,
					'_error_message' => 'Contact name must have at least 2 words (e.g., "John Doe").',
				);
			}

			$payload['FirstName'] = $nameParts['first'];
			$payload['LastName']  = $nameParts['last'];
		}

		// Map Salutation to Salesforce standard field
		if ( ! empty( $params['salutation'] ) ) {
			$payload['Salutation'] = $params['salutation'];
		}

		// Standard source indicator
		$payload['LeadSource'] = 'Web';

		// Map Email (required - used as external ID for upsert)
		if ( ! empty( $params['email'] ) ) {
			$email_field             = $contact_mapping['email'] ?? $defaults['email'];
			$payload[ $email_field ] = $params['email'];
		}

		// Map Phone (optional) with configurable strategy and validation
		if ( ! empty( $params['phone'] ) ) {
			// Validate phone if configured
			$phone_validation = $crm_settings['phone_validation'] ?? 'none';
			if ( $phone_validation === 'basic' && ! self::is_valid_phone( $params['phone'] ) ) {
				// Phone failed validation, skip it
				Logger::warning(
					'Phone validation failed, skipping phone field',
					array(
						'phone'           => substr( $params['phone'], 0, 5 ) . '***', // Log partial for privacy
						'validation_mode' => $phone_validation,
					)
				);
			} else {
				self::apply_phone_strategy( $payload, $params['phone'], $contact_mapping, $phone_strategy, $is_new_contact );
			}
		}

		return $payload;
	}

	/**
	 * Validate phone number format.
	 *
	 * Basic validation checks:
	 * - Not whitespace only
	 * - Contains at least 10 digits/chars after stripping formatting
	 * - Starts with digit or + (international format)
	 *
	 * @param string $phone Phone number to validate
	 * @return bool True if phone passes basic validation
	 */
	public static function is_valid_phone( string $phone ): bool {
		// Trim whitespace
		$phone = trim( $phone );

		// Empty or whitespace only
		if ( empty( $phone ) ) {
			return false;
		}

		// Remove formatting characters to count actual digits
		$cleaned = preg_replace( '/[^\d+x]/', '', $phone );

		// Check minimum length (most phone numbers 10+ digits)
		if ( strlen( $cleaned ) < 10 ) {
			return false;
		}

		// Check starts with + (international) or digit
		if ( ! preg_match( '/^[\d+]/', $phone ) ) {
			return false;
		}

		return true;
	}

	/**
	 * Apply phone handling strategy to contact payload.
	 *
	 * Strategies:
	 * - 'overwrite': Store in primary Phone field (default, backwards compatible)
	 * - 'secondary': Store in OtherPhone field to preserve existing phone
	 * - 'skip_if_exists': Add marker flag; let CRM determine if field already populated
	 *
	 * Special handling:
	 * - For 'secondary' strategy with NEW CONTACTS: Uses Phone field, not OtherPhone
	 *   (OtherPhone is only used for existing contacts to preserve their primary phone)
	 * - Empty phone values are silently skipped (no null/empty fields sent)
	 * - Whitespace-only phones are treated as empty
	 *
	 * @param array  &$payload Contact payload (modified in place)
	 * @param string $phone Phone number to map
	 * @param array  $contact_mapping Contact field mappings from settings
	 * @param string $strategy Phone handling strategy
	 * @param bool   $is_new_contact Whether this is a new contact (no prior record)
	 */
	private static function apply_phone_strategy(
		array &$payload,
		string $phone,
		array $contact_mapping,
		string $strategy,
		bool $is_new_contact = true
	): void {
		// Trim whitespace and skip if empty
		$phone = trim( $phone );
		if ( empty( $phone ) ) {
			return;
		}

		$phone_field = $contact_mapping['phone'] ?? 'Phone';

		switch ( $strategy ) {
			case 'secondary':
				// For new contacts, always use primary Phone field
				// For existing contacts, use OtherPhone to preserve their primary phone
				if ( $is_new_contact ) {
					$payload[ $phone_field ] = $phone;
				} else {
					// Store in OtherPhone to preserve existing Phone field
					$payload['OtherPhone'] = $phone;
				}
				break;
			case 'skip_if_exists':
				// Add marker for conditional update in Salesforce
				$payload[ $phone_field ]  = $phone;
				$payload['_phone_action'] = 'only_if_empty';
				break;
			case 'overwrite':
			default:
				// Standard behavior: overwrite Phone field with new value
				$payload[ $phone_field ] = $phone;
				break;
		}
	}

	/**
	 * Map Inquiry fields (Case/Task) for two-object architecture
	 *
	 * @param array  $params Plugin form params
	 * @param array  $crm_settings CRM settings
	 * @param string $contact_id Salesforce Contact ID to link
	 * @param string $object_type 'Case' or 'Task'
	 * @return array Inquiry payload
	 */
	public static function map_inquiry_fields( array $params, array $crm_settings, string $contact_id, string $object_type = 'Case' ): array {
		$payload         = array();
		$inquiry_mapping = $crm_settings['inquiry_mapping'] ?? array();

		// Default inquiry field mappings
		$defaults = array(
			'subject' => 'Subject',
			'message' => 'Description',
		);

		// Link to Contact
		if ( $object_type === 'Case' ) {
			$payload['ContactId'] = $contact_id;
			$payload['Origin']    = 'Web'; // Standard Salesforce field
			$payload['Status']    = 'New'; // Standard Salesforce field
		} elseif ( $object_type === 'Task' ) {
			$payload['WhoId']    = $contact_id; // Task uses WhoId for Contact reference
			$payload['Status']   = 'Not Started';
			$payload['Priority'] = 'Normal';
		}

		// Map Subject - use message excerpt as fallback if subject is empty
		$subject_field        = $inquiry_mapping['subject'] ?? $defaults['subject'];
		$subject_value        = '';
		$has_explicit_subject = ! empty( $params['subject'] );

		if ( $has_explicit_subject ) {
			$subject_value = $params['subject'];
		} elseif ( ! empty( $params['message'] ) ) {
			// Fallback: Use first 100 characters of message as subject
			$message_excerpt = wp_strip_all_tags( $params['message'] );
			$subject_value   = mb_substr( $message_excerpt, 0, 100 );
			if ( mb_strlen( $message_excerpt ) > 100 ) {
				$subject_value .= '...';
			}
		}

		if ( ! empty( $subject_value ) ) {
			$prefixes = array();
			// Prepend intent category to subject if enabled and intent exists
			if ( ! empty( $crm_settings['prepend_intent_to_subject'] ) && ! empty( $params['intent_category'] ) ) {
				$intent_label = ucfirst( str_replace( '_', ' ', $params['intent_category'] ) );
				$prefixes[]   = $intent_label;
			}
			if ( $prefixes ) {
				$subject_value = implode( ': ', $prefixes ) . ': ' . $subject_value;
			}

			$payload[ $subject_field ] = $subject_value;
		}

		// Map Message/Description
		if ( ! empty( $params['message'] ) ) {
			$message_field             = $inquiry_mapping['message'] ?? $defaults['message'];
			$payload[ $message_field ] = $params['message'];
		}

		// Map Intent Category (optional)
		if ( ! empty( $params['intent_category'] ) ) {
			$intent_category_field = $inquiry_mapping['intent_category'] ?? '';
			if ( ! empty( $intent_category_field ) ) {
				$payload[ $intent_category_field ] = $params['intent_category'];
			}
		}

		// Map Intent Confidence (optional)
		if ( isset( $params['intent_confidence'] ) && $params['intent_confidence'] !== null && $params['intent_confidence'] !== '' ) {
			$intent_confidence_field = $inquiry_mapping['intent_confidence'] ?? '';
			if ( ! empty( $intent_confidence_field ) ) {
				$payload[ $intent_confidence_field ] = $params['intent_confidence'];
			}
		}

		return $payload;
	}
}
