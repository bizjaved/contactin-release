<?php
/**
 * Core – Form Service (Refactored & Consistent)
 *
 * Encapsulates validation and persistence rules for message lifecycle.
 *
 * @package ContactIn\Core
 * @since   1.6.1
 */

namespace ContactInbox\Core;

use WP_Error;
use ContactInbox\Core\Repositories\SubmissionRepository;
use ContactInbox\Core\Security;
use ContactInbox\Core\ContactResolver;
use ContactInbox\Core\Logger;

// phpcs:disable WordPress.WP.I18n.NonSingularStringLiteralDomain, WordPress.WP.I18n.MissingTranslatorsComment, WordPress.Security.ValidatedSanitizedInput.MissingUnslash, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized, WordPress.Security.NonceVerification.Recommended, WordPress.Security.NonceVerification.Missing, WordPress.WP.I18n.NonSingularStringLiteralText, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.WP.AlternativeFunctions.unlink_unlink, WordPress.WP.AlternativeFunctions.file_system_operations_fwrite, WordPress.WP.AlternativeFunctions.file_system_operations_is_writable, WordPress.WP.AlternativeFunctions.file_system_operations_fclose, WordPress.WP.AlternativeFunctions.rename_rename, WordPress.WP.AlternativeFunctions.file_system_operations_fopen, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class FormService {

	// -------------------------------------------------------------------------
	// Form Submission
	// -------------------------------------------------------------------------
	public static function submit( array $post, array $files = array() ) {
		$form_id  = sanitize_key( $post['form_id'] ?? 'default' );
		$settings = FormProfiles::resolve( $form_id );

		// Honeypot: any ci_hp_* non-empty => spam
		foreach ( $post as $k => $v ) {
			if ( strpos( $k, 'ci_hp_' ) === 0 && strlen( trim( (string) $v ) ) > 0 ) {
				return new \WP_Error( 'honeypot', __( 'Spam detected', 'contactin' ), array( 'status' => 400 ) );
			}
		}

		// Build sanitized payload
		$payload = array(
			'salutation'   => sanitize_text_field( $post['salutation'] ?? '' ),
			'name'         => sanitize_text_field( $post['name'] ?? '' ),
			'email'        => sanitize_email( $post['email'] ?? '' ),
			'phone'        => sanitize_text_field( $post['phone'] ?? '' ),
			'mobile_phone' => sanitize_text_field( $post['mobile_phone'] ?? '' ),
			'home_phone'   => sanitize_text_field( $post['home_phone'] ?? '' ),
			'other_phone'  => sanitize_text_field( $post['other_phone'] ?? '' ),
			'message'      => sanitize_textarea_field( $post['message'] ?? '' ),
			'consent'      => ! empty( $post['consent'] ) ? 1 : 0,
		);

		// Apply phone intelligence: normalize, classify, deduplicate
		$bucketed = PhoneUtils::bucket( $payload, '' );
		foreach ( array( 'phone', 'mobile_phone', 'home_phone', 'other_phone' ) as $pkey ) {
			$payload[ $pkey ] = $bucketed[ $pkey ] ?? '';
		}

		if ( isset( $settings['form_enable_subject'] ) && (bool) $settings['form_enable_subject'] ) {
			$payload['subject'] = sanitize_text_field( $post['subject'] ?? '' );
		}

		$validation_result = self::validate_submission_payload( $payload, $settings );
		if ( $validation_result instanceof WP_Error ) {
			return $validation_result;
		}

		// reCAPTCHA verification is handled by the frontend/AJAX submission flow.
		// No additional verification step is needed here.

		// Use atomic save with proper transaction handling
		$submission_repo = new Repositories\SubmissionRepository();
		$submission_data = array(
			'salutation'      => $payload['salutation'],
			'name'            => $payload['name'],
			'email'           => $payload['email'],
			'message'         => $payload['message'],
			'consent'         => $payload['consent'] ?? 0,
			'ip_address'      => Security::get_ip_address(),
			'user_agent'      => $_SERVER['HTTP_USER_AGENT'] ?? '',
			'status'          => Config::STATUS_UNREAD,
			'form_id'         => $form_id,
			'recaptcha_score' => isset( $payload['recaptcha_score'] ) ? (float) $payload['recaptcha_score'] : null,
		);

		// Add optional fields (already normalized & classified)
		if ( ! empty( $payload['phone'] ) ) {
			$submission_data['phone'] = $payload['phone'];
		}
		if ( ! empty( $payload['mobile_phone'] ) ) {
			$submission_data['mobile_phone'] = $payload['mobile_phone'];
		}
		if ( ! empty( $payload['home_phone'] ) ) {
			$submission_data['home_phone'] = $payload['home_phone'];
		}
		if ( ! empty( $payload['other_phone'] ) ) {
			$submission_data['other_phone'] = $payload['other_phone'];
		}
		if ( ! empty( $payload['subject'] ) ) {
			$submission_data['subject'] = $payload['subject'];
		}
		if ( ! empty( $payload['salutation'] ) ) {
			$submission_data['salutation'] = $payload['salutation'];
		}
		Logger::debug( 'FormService: attachments are disabled', array() );

		// Central duplicate detection: Check for identical submission within 5 seconds
		$duplicate_window = 5; // seconds
		$duplicate_count  = $submission_repo->countIdentical( $submission_data, $duplicate_window );
		if ( $duplicate_count > 0 ) {
			return new \WP_Error(
				'duplicate_submission',
				__( 'This submission appears to be a duplicate. Please wait a moment before submitting again.', 'contactin' ),
				array( 'status' => 409 )
			);
		}

		// Resolve/create contact and normalize phones
		if ( ! class_exists( ContactResolver::class ) ) {
			require_once CONTACTINBOX_PATH . 'includes/Core/ContactResolver.php';
		}
		$contact_resolution = ContactResolver::resolve( $payload, '' );
		$contact_id         = $contact_resolution['contact_id'];
		$normalized_phones  = $contact_resolution['phones'];

		if ( ! empty( $normalized_phones['phone'] ) ) {
			$submission_data['phone'] = $normalized_phones['phone'];
		}
		if ( ! empty( $normalized_phones['mobile_phone'] ) ) {
			$submission_data['mobile_phone'] = $normalized_phones['mobile_phone'];
		}
		if ( ! empty( $normalized_phones['home_phone'] ) ) {
			$submission_data['home_phone'] = $normalized_phones['home_phone'];
		}
		if ( ! empty( $normalized_phones['other_phone'] ) ) {
			$submission_data['other_phone'] = $normalized_phones['other_phone'];
		}
		if ( ! empty( $contact_id ) ) {
			$submission_data['contact_id'] = $contact_id;
		}

		// Preserve salutation for contact resolution.
		if ( ! empty( $payload['salutation'] ) ) {
			$submission_data['salutation'] = $payload['salutation'];
		}

		// Save using atomic transaction
		$save_result = $submission_repo->save_atomic( $submission_data );
		if ( $save_result instanceof \WP_Error ) {
			return $save_result;
		}

		$message_id = $save_result['message_id'];

		// Set initial message statuses consistently for all entry points.
		self::set_initial_message_statuses( $message_id, $settings );

		// Intent Classification: Classify message intent automatically.
		// Runs regardless of license status; only learning is premium-gated.
		if ( ! empty( $settings['intent_enable'] ) ) {
			$classifier = IntentClassifier::instance();
			$intent     = $classifier->classify(
				$submission_data['subject'] ?? '',
				$submission_data['message'] ?? ''
			);
			DB::instance()->update_message_intent( $message_id, $intent );
		}

		// Return submission result payload for AJAX/REST handlers.
		return array(
			'success'          => true,
			'action'           => 'submit',
			'message_id'       => $message_id,
			'contact_id'       => $contact_id,
			'payload'          => $payload,
			'files'            => $files,
			'timestamp'        => time(),
		);
	}

	/**
	 * Validate a sanitized submission payload against the configured field rules.
	 *
	 * @param array      $payload Sanitized submission payload.
	 * @param array|null $settings Optional settings override.
	 * @return true|WP_Error
	 */
	public static function validate_submission_payload( array $payload, ?array $settings = null ) {
		$settings = is_array( $settings ) ? wp_parse_args( $settings, Settings::get_default_settings() ) : Settings::get_settings();

		$missing         = array();
		$required_fields = array( 'name', 'email', 'message' );

		if ( ! empty( $settings['form_enable_subject'] ) ) {
			// Only require subject when form_require_subject is not explicitly false
			if ( ! isset( $settings['form_require_subject'] ) || $settings['form_require_subject'] ) {
				$required_fields[] = 'subject';
			}
		}
		if ( ! empty( $settings['require_phone'] ) ) {
			$required_fields[] = 'phone';
		}

		foreach ( $required_fields as $field ) {
			$value = trim( (string) ( $payload[ $field ] ?? '' ) );

			if ( $field === 'email' ) {
				if ( $value === '' || ! is_email( $value ) ) {
					$missing[] = $field;
				}
				continue;
			}

			if ( $value === '' ) {
				$missing[] = $field;
			}
		}

		if ( ! empty( $missing ) ) {
			return new WP_Error(
				'validation_failed',
				__( 'Missing or invalid required fields', 'contactin' ),
				array(
					'fields' => $missing,
					'status' => 422,
				)
			);
		}

		if ( ! empty( $settings['consent_required'] ) && empty( $payload['consent'] ) ) {
			return new WP_Error( 'consent_required', __( 'Consent is required', 'contactin' ), array( 'status' => 422 ) );
		}

		$field_rules = array(
			'name'    => array(
				'max_chars' => absint( $settings['max_name_chars'] ?? 0 ),
				'min_words' => absint( $settings['min_name_words'] ?? 0 ),
			),
			'message' => array(
				'max_chars' => absint( $settings['max_message_chars'] ?? 0 ),
				'min_words' => absint( $settings['min_message_words'] ?? 0 ),
			),
		);

		if ( ! empty( $settings['form_enable_subject'] ) ) {
			$field_rules['subject'] = array(
				'max_chars' => absint( $settings['max_subject_chars'] ?? 0 ),
				'min_words' => absint( $settings['min_subject_words'] ?? 0 ),
			);
		}

		foreach ( $field_rules as $field => $rules ) {
			$value = trim( (string) ( $payload[ $field ] ?? '' ) );
			if ( $value === '' ) {
				continue;
			}

			$max_chars = (int) ( $rules['max_chars'] ?? 0 );
			if ( $max_chars > 0 && self::string_length( $value ) > $max_chars ) {
				return new WP_Error(
					'validation_failed',
					sprintf(
						__( '%1$s must be %2$d characters or fewer.', 'contactin' ),
						self::get_field_label( $field ),
						$max_chars
					),
					array(
						'field'      => $field,
						'validation' => 'max_chars',
						'status'     => 422,
					)
				);
			}

			$min_words = (int) ( $rules['min_words'] ?? 0 );
			if ( $min_words > 0 && self::count_words( $value ) < $min_words ) {
				return new WP_Error(
					'validation_failed',
					sprintf(
						__( '%1$s must contain at least %2$d words.', 'contactin' ),
						self::get_field_label( $field ),
						$min_words
					),
					array(
						'field'      => $field,
						'validation' => 'min_words',
						'status'     => 422,
					)
				);
			}
		}

		return true;
	}

	// -------------------------------------------------------------------------
	// Message Management
	// -------------------------------------------------------------------------
	public static function read( int $id ): array|WP_Error {
		$msg = DB::instance()->get_message_by_id( $id );
		if ( ! $msg ) {
			return new WP_Error( 'not_found', __( 'Message not found.', 'contactin' ), array( 'status' => 404 ) );
		}

		return array(
			'success'   => true,
			'action'    => 'read',
			'id'        => (int) ( $msg->id ?? 0 ),
			'data'      => array(
				'name'               => (string) ( $msg->name ?? '' ),
				'email'              => (string) ( $msg->email ?? '' ),
				'phone'              => (string) ( $msg->phone ?? '' ),
				'subject'            => (string) ( $msg->subject ?? '' ),
				'message'            => (string) ( $msg->message ?? '' ),
				'status'             => (string) ( $msg->status ?? '' ),
				'date'               => (string) ( $msg->submitted_at ?? '' ),
				'attachment'         => (string) ( $msg->attachment ?? '' ),
				'ip_address'         => (string) ( $msg->ip_address ?? '' ),
				'admin_email_status' => (string) ( $msg->admin_email_status ?? 'pending' ),
				'user_email_status'  => (string) ( $msg->user_email_status ?? 'pending' ),
				'crm_status'         => (string) ( $msg->crm_status ?? 'pending' ),
			),
			'timestamp' => time(),
		);
	}

	public static function update_status( int $id, string $status ): array|WP_Error {
		$status  = strtolower( trim( $status ) );
		$allowed = array( Config::STATUS_UNREAD, Config::STATUS_READ, Config::STATUS_ARCHIVED );
		if ( ! in_array( $status, $allowed, true ) ) {
			return new WP_Error(
				'invalid_status',
				__( 'Invalid status value.', 'contactin' ),
				array( 'status' => 400 )
			);
		}

		// Use the existing toggle_status() function
		$newStatus = DB::instance()->toggle_status( $id );

		if ( $newStatus === false ) {
			return new WP_Error(
				'update_failed',
				__( 'Failed to update status.', 'contactin' ),
				array( 'status' => 500 )
			);
		}

		return array(
			'success'   => true,
			'action'    => 'update_status',
			'id'        => $id,
			'status'    => $newStatus,
			'timestamp' => time(),
		);
	}

	public static function list_messages( array $args = array() ): array|WP_Error {
		try {
			$page     = (int) ( $args['page'] ?? 1 );
			$search   = (string) ( $args['search'] ?? '' );
			$status   = (string) ( $args['status'] ?? 'all' );
			$per_page = (int) ( $args['per_page'] ?? Config::INBOX_PER_PAGE );

			$messages = DB::instance()->get_messages( $page, $search, $status, $per_page );

			return array(
				'success'   => true,
				'action'    => 'list_messages',
				'page'      => $page,
				'per_page'  => $per_page,
				'items'     => array_map( fn( $msg ) => method_exists( $msg, 'to_array' ) ? $msg->to_array() : (array) $msg, $messages ),
				'timestamp' => time(),
			);
		} catch ( \Throwable $e ) {
			return new WP_Error(
				'ci_db_error',
				__( 'Failed to fetch messages.', 'contactin' ),
				array(
					'status' => 500,
					'detail' => $e->getMessage(),
				)
			);
		}
	}

	public static function search_messages( string $query, int $page = 1, int $per_page = Config::INBOX_PER_PAGE ): array|WP_Error {
		$page     = max( 1, $page );
		$per_page = max( 1, min( $per_page, 100 ) );

		$query = trim( $query );
		if ( $query === '' ) {
			return new WP_Error( 'empty_query', __( 'Search query cannot be empty.', 'contactin' ), array( 'status' => 400 ) );
		}
		if ( mb_strlen( $query ) > 256 ) {
			$query = mb_substr( $query, 0, 256 );
		}

		$results = DB::instance()->search_messages( $query, $page, $per_page );

		return array(
			'success'   => true,
			'action'    => 'search_messages',
			'query'     => $query,
			'page'      => $page,
			'per_page'  => $per_page,
			'items'     => $results,
			'timestamp' => time(),
		);
	}

	public static function bulk_delete( array $ids ): array|WP_Error {
		$ids = array_values( array_unique( array_map( 'absint', $ids ) ) );
		if ( empty( $ids ) ) {
			return new WP_Error( 'no_ids', __( 'No valid IDs provided.', 'contactin' ), array( 'status' => 400 ) );
		}

		$deleted = DB::instance()->bulk_delete( $ids );
		if ( $deleted === 0 ) {
			return new WP_Error( 'delete_failed', __( 'No records deleted.', 'contactin' ), array( 'status' => 404 ) );
		}

		return array(
			'success'   => true,
			'action'    => 'bulk_delete',
			'deleted'   => $deleted,
			'ids'       => $ids,
			'timestamp' => time(),
		);
	}

	public static function get_settings(): array {
		return Settings::get_settings();
	}

	public static function is_subject_enabled(): bool {
		$s = self::get_settings();
		return isset( $s['form_enable_subject'] ) ? (bool) $s['form_enable_subject'] : false;
	}

	/**
	 * Set initial message processing statuses.
	 *
	 * Called immediately after save so AJAX and REST share the same defaults.
	 * CRM status is explicitly marked as skipped in this build.
	 *
	 * @param int   $message_id Message ID
	 * @param array $settings Plugin settings
	 */
	private static function set_initial_message_statuses( int $message_id, array $settings ): void {
		if ( $message_id <= 0 ) {
			return;
		}

		$db           = DB::instance();
		$smtp_enabled = ! empty( $settings['smtp_enable'] );

		$admin_status = ( $smtp_enabled && ! empty( $settings['send_admin_notification'] ) )
			? Config::EMAIL_PENDING
			: Config::EMAIL_SKIPPED;

		$user_status = ( $smtp_enabled && ! empty( $settings['send_user_copy'] ) )
			? Config::EMAIL_PENDING
			: Config::EMAIL_SKIPPED;

		$crm_status = Config::CRM_SKIPPED;

		$admin_updated = $db->update_message_status( $message_id, 'admin_email', $admin_status );
		$user_updated  = $db->update_message_status( $message_id, 'user_email', $user_status );
		$crm_updated   = $db->update_message_status( $message_id, 'crm', $crm_status );

		if ( ! $admin_updated || ! $user_updated || ! $crm_updated ) {
			Logger::error(
				'Failed to set initial message statuses',
				array(
					'message_id'  => $message_id,
					'admin_email' => $admin_status,
					'user_email'  => $user_status,
					'crm'         => $crm_status,
				)
			);
			return;
		}

		Logger::info(
			'Initial message statuses set',
			array(
				'message_id'  => $message_id,
				'admin_email' => $admin_status,
				'user_email'  => $user_status,
				'crm'         => $crm_status,
			)
		);
	}

	/**
	 * Count words in a text string.
	 */
	private static function count_words( string $value ): int {
		$words = preg_split( '/\s+/', trim( wp_strip_all_tags( $value ) ) ) ?: array();
		$words = array_filter(
			$words,
			static function ( $word ) {
				return $word !== '';
			}
		);

		return count( $words );
	}

	/**
	 * Return string length with multibyte support when available.
	 */
	private static function string_length( string $value ): int {
		if ( function_exists( 'mb_strlen' ) ) {
			return (int) mb_strlen( $value );
		}

		return strlen( $value );
	}

	/**
	 * Return a translated label for known submission fields.
	 */
	private static function get_field_label( string $field ): string {
		switch ( $field ) {
			case 'name':
				return __( 'Name', 'contactin' );
			case 'subject':
				return __( 'Subject', 'contactin' );
			case 'message':
				return __( 'Message', 'contactin' );
			case 'email':
				return __( 'Email', 'contactin' );
			default:
				return ucfirst( str_replace( '_', ' ', $field ) );
		}
	}
}
