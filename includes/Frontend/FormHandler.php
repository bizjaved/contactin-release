<?php
/**
 * Frontend – AJAX Form Handler
 *
 * Handles AJAX form submission securely with:
 * - Nonce, honeypot, rate limiting, ReCAPTCHA
 * - Validation via Core\FormService
 * - Database persistence, GDPR token generation
 * - Email notifications via SMTP
 *
 * @package ContactIn
 */

namespace ContactInbox\Frontend;

use ContactInbox\Core\Config;
use ContactInbox\Core\FormService;
use ContactInbox\Core\DB;
use ContactInbox\Core\GDPR;
use ContactInbox\Core\Security;
use ContactInbox\Core\CRMConnector;
use ContactInbox\Core\CRMSettings;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
// phpcs:disable WordPress.WP.I18n.NonSingularStringLiteralDomain, WordPress.WP.I18n.MissingTranslatorsComment, WordPress.Security.ValidatedSanitizedInput.MissingUnslash, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized, WordPress.Security.NonceVerification.Recommended, WordPress.Security.NonceVerification.Missing, WordPress.Security.EscapeOutput.OutputNotEscaped, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.WP.AlternativeFunctions.unlink_unlink, WordPress.WP.AlternativeFunctions.file_system_operations_fwrite, WordPress.WP.AlternativeFunctions.file_system_operations_is_writable, WordPress.WP.AlternativeFunctions.file_system_operations_fclose, WordPress.WP.AlternativeFunctions.rename_rename, WordPress.WP.AlternativeFunctions.file_system_operations_fopen, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber, Generic.PHP.ForbiddenFunctions.Found, PluginCheck.CodeAnalysis.DiscouragedFunctions.load_plugin_textdomainFound, PluginCheck.CodeAnalysis.Heredoc.NotAllowed, PluginCheck.Security.DirectDB.UnescapedDBParameter, Squiz.PHP.DiscouragedFunctions.Discouraged, WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.NamingConventions.PrefixAllGlobals.DynamicHooknameFound, WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound, WordPress.PHP.DevelopmentFunctions.error_log_debug_backtrace, WordPress.WP.AlternativeFunctions.file_system_operations_fsockopen, WordPress.WP.AlternativeFunctions.file_system_operations_readfile, WordPress.WP.AlternativeFunctions.file_system_operations_rmdir, WordPress.WP.EnqueuedResourceParameters.MissingVersion, WordPress.WP.EnqueuedResources.NonEnqueuedScript, WordPress.WP.I18n.MissingArgDomain, WordPress.WP.I18n.UnorderedPlaceholdersPlural, WordPress.WP.I18n.UnorderedPlaceholdersSingle
use ContactInbox\Core\Logger;
use ContactInbox\Core\ReceiptTokenService;
use ContactInbox\Core\RateLimiter;
use ContactInbox\Core\ConcurrencyManager;
use ContactInbox\Core\reCAPTCHA;
use ContactInbox\Core\Repositories\SubmissionRepository;
use ContactInbox\Core\Repositories\SubmissionAttemptsRepository;
use ContactInbox\Admin\Controllers\AttachmentUploadController;
use ContactInbox\Traits\Singleton;
use ContactInbox\Core\Traits\SubmissionRateLimiterTrait;
use WP_Error;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class FormHandler {
	use Singleton;
	use SubmissionRateLimiterTrait;

	/**
	 * Initialize AJAX hooks.
	 */
	protected function __construct() {
		// Public endpoint by design: anonymous visitors must be able to submit forms.
		// Security is enforced inside handle() via nonce, honeypot, reCAPTCHA,
		// rate limiting, and strict server-side validation.
		add_action( 'wp_ajax_contactin_submit', array( $this, 'handle' ) );
		add_action( 'wp_ajax_nopriv_contactin_submit', array( $this, 'handle' ) );
		// Public upload endpoint is intentionally available for frontend users;
		// AttachmentUploadController validates nonce, file type, and size.
		add_action( 'wp_ajax_contactin_upload_attachment', array( $this, 'handle_attachment_upload_ajax' ) );
		add_action( 'wp_ajax_nopriv_contactin_upload_attachment', array( $this, 'handle_attachment_upload_ajax' ) );
	}


	/**
	 * Main AJAX handler – validates, saves, sends emails
	 *
	 * CRITICAL: Data is saved to database BEFORE success response
	 * Ensures no data loss even if async operations fail
	 */
	public function handle() {
		\ContactInbox\Core\Logger::info(
			'===FormHandler::handle() CALLED===',
			array(
				'has_file_id_post' => isset( $_POST['file_id'] ),
				'file_id_value'    => $_POST['file_id'] ?? 'NOT SET',
			)
		);
		\ContactInbox\Core\Logger::debug(
			'FormHandler handle() entered',
			array(
				'file'    => __FILE__,
				'line'    => __LINE__,
				'request' => $_POST,
			)
		);
		$start_time    = microtime( true );
		$form_id       = $_POST['form_id'] ?? 'default';
		$settings      = \ContactInbox\Core\FormProfiles::resolve( (string) $form_id );
		$attempts_repo = new SubmissionAttemptsRepository();

		// Get client info for attempt logging
		$client_ip  = Security::get_ip_address();
		$user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';

		// Nonce check (prevents CSRF/cache replay)
		if ( ! check_ajax_referer( Config::FORM_SUBMIT_NONCE, 'nonce', false ) ) {
			$processing_time = (int) ( ( microtime( true ) - $start_time ) * 1000 );
			$attempts_repo->log_attempt(
				array(
					'form_id'            => $form_id,
					'ip_address'         => $client_ip,
					'user_agent'         => $user_agent,
					'rejection_reason'   => 'nonce_failed',
					'processing_time_ms' => $processing_time,
				)
			);

			return $this->render_error(
				__( 'Security check failed. Please refresh the page and submit again (token expired).', 'contactin' ),
				__( 'Refresh the page to get a new security token, then submit once.', 'contactin' )
			);
		}

		// Rate limiting check (Phase 2D: Concurrent Safety)
		$rate_limit = RateLimiter::check_rate_limit( $client_ip );

		if ( ! $rate_limit['allowed'] ) {
			$processing_time = (int) ( ( microtime( true ) - $start_time ) * 1000 );
			$attempts_repo->log_attempt(
				array(
					'form_id'            => $form_id,
					'email'              => $_POST['email'] ?? null,
					'ip_address'         => $client_ip,
					'user_agent'         => $user_agent,
					'rejection_reason'   => 'rate_limited',
					'processing_time_ms' => $processing_time,
				)
			);

			Logger::warning(
				'Rate limit exceeded',
				array(
					'ip'     => $client_ip,
					'reason' => $rate_limit['reason'],
					'window' => $rate_limit['window'] ?? 'unknown',
				)
			);

			$retry_after = $rate_limit['retry_after'] ?? 60;
			$error_msg   = sprintf(
				__( 'Too many requests from this network. Please wait %d seconds and try again.', 'contactin' ),
				$retry_after
			);
			$tip         = sprintf(
				__( 'Wait about %d seconds, then submit once. If it keeps happening, contact us with your approximate time and email.', 'contactin' ),
				$retry_after
			);
			return $this->render_error( $error_msg, $tip );
		}

		// Honeypot check (bot trap)
		foreach ( $_POST as $key => $value ) {
			if ( strpos( (string) $key, 'ci_hp_' ) === 0 && strlen( trim( (string) $value ) ) > 0 ) {
				$processing_time = (int) ( ( microtime( true ) - $start_time ) * 1000 );
				$attempts_repo->log_attempt(
					array(
						'form_id'            => $form_id,
						'email'              => $_POST['email'] ?? null,
						'ip_address'         => $client_ip,
						'user_agent'         => $user_agent,
						'rejection_reason'   => 'honeypot_failed',
						'processing_time_ms' => $processing_time,
					)
				);

				RateLimiter::record_request( $client_ip );

				return $this->render_error(
					__( 'Spam detected. Submission blocked.', 'contactin' ),
					__( 'Please submit the form again manually if this was an error.', 'contactin' )
				);
			}
		}

		// Timing anti-bot check: form must exist for at least 3 s before submission.
		// The load time is HMAC-signed server-side so clients cannot forge a past timestamp.
		$min_submission_seconds = 3;
		$max_form_age_seconds   = 7200; // 2 hours
		$posted_load_time       = (int) sanitize_text_field( wp_unslash( $_POST['ci_form_load_time'] ?? '' ) );
		$posted_load_token      = sanitize_text_field( wp_unslash( $_POST['ci_form_load_token'] ?? '' ) );
		if ( $posted_load_time > 0 ) {
			$expected_token = hash_hmac( 'sha256', (string) $posted_load_time, wp_salt( 'auth' ) );
			$elapsed        = time() - $posted_load_time;

			if ( ! hash_equals( $expected_token, $posted_load_token ) ) {
				// Tampered / missing timestamp token
				$processing_time = (int) ( ( microtime( true ) - $start_time ) * 1000 );
				$attempts_repo->log_attempt(
					array(
						'form_id'            => $form_id,
						'ip_address'         => $client_ip,
						'user_agent'         => $user_agent,
						'rejection_reason'   => 'timing_token_invalid',
						'processing_time_ms' => $processing_time,
					)
				);
				RateLimiter::record_request( $client_ip );
				return $this->render_error(
					__( 'Security check failed. Please refresh the page and try again.', 'contactin' ),
					__( 'The form security token could not be verified. Please reload the page.', 'contactin' )
				);
			} elseif ( $elapsed < $min_submission_seconds ) {
				// Submitted too fast – likely a bot
				$processing_time = (int) ( ( microtime( true ) - $start_time ) * 1000 );
				$attempts_repo->log_attempt(
					array(
						'form_id'            => $form_id,
						'ip_address'         => $client_ip,
						'user_agent'         => $user_agent,
						'rejection_reason'   => 'submitted_too_fast',
						'processing_time_ms' => $processing_time,
					)
				);
				RateLimiter::record_request( $client_ip );
				return $this->render_error(
					__( 'Form submitted too quickly. Please take a moment to review your message.', 'contactin' ),
					__( 'Please wait a few seconds before submitting again.', 'contactin' )
				);
			} elseif ( $elapsed > $max_form_age_seconds ) {
				// Form page is stale (over 2 hours old)
				$processing_time = (int) ( ( microtime( true ) - $start_time ) * 1000 );
				$attempts_repo->log_attempt(
					array(
						'form_id'            => $form_id,
						'ip_address'         => $client_ip,
						'user_agent'         => $user_agent,
						'rejection_reason'   => 'form_expired',
						'processing_time_ms' => $processing_time,
					)
				);
				return $this->render_error(
					__( 'Your session has expired. Please refresh the page and submit again.', 'contactin' ),
					__( 'The form has been open too long. Reload the page to get a fresh form.', 'contactin' )
				);
			}
		}

		// Get form data (excluding files – they're pre-uploaded)
		$form_data          = $_POST;
		$attachment         = '';
		$attachment_info    = array();
		$file_id            = isset( $form_data['file_id'] ) ? sanitize_text_field( $form_data['file_id'] ) : '';
		$file_ext           = isset( $form_data['file_ext'] ) ? sanitize_text_field( $form_data['file_ext'] ) : '';
		$file_original_name = isset( $form_data['file_original_name'] ) ? sanitize_file_name( $form_data['file_original_name'] ) : '';

		Logger::debug(
			'Form submission received',
			array(
				'has_file_id'   => ! empty( $file_id ),
				'file_id'       => $file_id ?: 'empty',
				'has_file_ext'  => ! empty( $file_ext ),
				'file_ext'      => $file_ext ?: 'empty',
				'original_name' => $file_original_name ?: 'not provided',
			)
		);

		// PHASE 0: Process file attachment FIRST (before any duplicate checks)
		// If file was pre-uploaded, validate and move it from temp to final location
		$file_required = false; // Set to false to make file upload optional
		if ( $file_required && ( empty( $file_id ) || empty( $file_ext ) ) ) {
			Logger::error(
				'File required but not provided',
				array(
					'file_id'  => $file_id,
					'file_ext' => $file_ext,
				)
			);
			return $this->render_error(
				__( 'A file attachment is required. Your message was not saved.', 'contactin' ),
				__( 'Please upload a file before submitting the form.', 'contactin' )
			);
		}
		if ( ! empty( $file_id ) && ! empty( $file_ext ) ) {
			$temp_file_path = AttachmentUploadController::get_temp_file_path( $file_id, $file_ext );
			if ( $temp_file_path && file_exists( $temp_file_path ) ) {
				// Move file from temp to attachments directory
				$attachment = $this->finalize_uploaded_file( $temp_file_path, $file_id, $file_ext );
				if ( is_wp_error( $attachment ) ) {
					Logger::error( 'Failed to finalize upload', array( 'error' => $attachment->get_error_message() ) );
					return $this->render_error(
						__( 'File upload failed. Your message was not saved.', 'contactin' ),
						__( 'There was a problem saving your file. Please try again or contact support.', 'contactin' )
					);
				} else {
					// File successfully finalized, gather info for success message
					Logger::info( 'File finalized', array( 'attachment_url' => $attachment ) );
					$attachment_info = $this->get_attachment_info( $attachment );
					// Add original filename if provided
					if ( ! empty( $file_original_name ) ) {
						$attachment_info['name'] = $file_original_name;
					}
					Logger::info(
						'Attachment info extracted',
						array(
							'info' => $attachment_info,
							'url'  => $attachment,
						)
					);
				}
			} else {
				Logger::warning(
					'Temp file not found',
					array(
						'temp_path' => $temp_file_path,
						'file_id'   => $file_id,
						'file_ext'  => $file_ext,
					)
				);
				return $this->render_error(
					__( 'File upload failed. Your message was not saved.', 'contactin' ),
					__( 'The uploaded file could not be found. Please try again or contact support.', 'contactin' )
				);
			}
		}

		// PHASE 1: Tiered duplicate/rate-limit checks (AFTER file processing)
		if ( $this->isRapidRepeat( $form_data ) ) {
			return $this->render_error(
				__( 'You just submitted this message. Please wait before resubmitting.', 'contactin' ),
				__( 'Rapid repeat detected. Please wait at least 30 seconds before submitting again.', 'contactin' )
			);
		}
		if ( $this->isShortTermRepeat( $form_data, 2 ) ) {
			return $this->render_error(
				__( 'You have submitted this message multiple times in a short period.', 'contactin' ),
				__( 'Please wait a few minutes before submitting again, or contact support if you need urgent help.', 'contactin' )
			);
		}
		if ( $this->isLongTermRepeat( $form_data, 5 ) ) {
			return $this->render_error(
				__( 'You have reached the daily limit for this message.', 'contactin' ),
				__( 'Please wait 24 hours before submitting this message again.', 'contactin' )
			);
		}
		if ( $this->isAbsoluteRepeat( $form_data, 10 ) ) {
			return $this->render_error(
				__( 'You have reached the weekly limit for this message.', 'contactin' ),
				__( 'Please contact support if you need to submit this message again.', 'contactin' )
			);
		}

		// Validate form data inline (without saving to database)
		// Extract and sanitize payload
		$payload = array(
			'salutation' => sanitize_text_field( $form_data['salutation'] ?? '' ),
			'name'       => sanitize_text_field( $form_data['name'] ?? '' ),
			'email'      => sanitize_email( $form_data['email'] ?? '' ),
			'phone'      => sanitize_text_field( $form_data['phone'] ?? '' ),
			'message'    => sanitize_textarea_field( $form_data['message'] ?? '' ),
			'consent'    => ! empty( $form_data['consent'] ) ? 1 : 0,
			'form_id'    => sanitize_text_field( $form_data['form_id'] ?? 'default' ),
		);

		if ( isset( $settings['form_enable_subject'] ) && (bool) $settings['form_enable_subject'] ) {
			$payload['subject'] = sanitize_text_field( $form_data['subject'] ?? '' );
		}

		$validation_result = FormService::validate_submission_payload( $payload, $settings );
		if ( $validation_result instanceof WP_Error ) {
			$processing_time = (int) ( ( microtime( true ) - $start_time ) * 1000 );
			$attempts_repo->log_attempt(
				array(
					'form_id'            => $form_id,
					'email'              => $form_data['email'] ?? null,
					'ip_address'         => $client_ip,
					'user_agent'         => $user_agent,
					'rejection_reason'   => $validation_result->get_error_code() === 'consent_required' ? 'consent_required' : 'validation_failed',
					'processing_time_ms' => $processing_time,
				)
			);

			return $this->render_error(
				$validation_result->get_error_message(),
				$this->map_failure_tip( $validation_result->get_error_code() )
			);
		}

		// CRM Integration: Validate name has at least 2 words
		$crm_settings = CRMSettings::get_settings();
		if ( ! empty( $crm_settings['crm_enabled'] ) && ! empty( $crm_settings['mapping']['name'] ) ) {
			$name_parts = preg_split( '/\s+/', trim( $payload['name'] ) );
			if ( count( $name_parts ) < 2 ) {
				$processing_time = (int) ( ( microtime( true ) - $start_time ) * 1000 );
				$attempts_repo->log_attempt(
					array(
						'form_id'            => $form_id,
						'email'              => $payload['email'],
						'ip_address'         => $client_ip,
						'user_agent'         => $user_agent,
						'rejection_reason'   => 'invalid_name_format',
						'processing_time_ms' => $processing_time,
					)
				);

				return $this->render_error(
					__( 'Name must include at least first and last name (e.g., "John Doe") for CRM integration.', 'contactin' ),
					__( 'Please provide your full name with first and last name.', 'contactin' )
				);
			}
		}

		// Extract reCAPTCHA score (Phase 1: Gold Standard Logging)
		// Score is captured from reCAPTCHA v3 response for spam detection
		$recaptcha_score = null;
		if ( ! empty( $settings['recaptcha_enable'] ) && ! empty( $settings['recaptcha_site_key'] ) ) {
			$recaptcha_token = $form_data['g-recaptcha-response'] ?? $form_data['recaptcha_token'] ?? '';
			if ( empty( $recaptcha_token ) ) {
				$processing_time = (int) ( ( microtime( true ) - $start_time ) * 1000 );
				$attempts_repo->log_attempt(
					array(
						'form_id'            => $form_id,
						'email'              => $payload['email'],
						'ip_address'         => $client_ip,
						'user_agent'         => $user_agent,
						'rejection_reason'   => 'recaptcha_missing',
						'processing_time_ms' => $processing_time,
					)
				);

				RateLimiter::record_request( $client_ip );

				return $this->render_error(
					__( 'Security verification missing. Please try again.', 'contactin' ),
					__( 'reCAPTCHA token was not detected. Refresh the page and submit again.', 'contactin' )
				);
			}

			$recaptcha_result = reCAPTCHA::verify_with_score( $recaptcha_token );

			if ( ! $recaptcha_result['valid'] ) {
				$processing_time = (int) ( ( microtime( true ) - $start_time ) * 1000 );
				$attempts_repo->log_attempt(
					array(
						'form_id'            => $form_id,
						'email'              => $payload['email'],
						'ip_address'         => $client_ip,
						'user_agent'         => $user_agent,
						'rejection_reason'   => 'recaptcha_failed',
						'recaptcha_score'    => $recaptcha_result['score'] ?? null,
						'processing_time_ms' => $processing_time,
					)
				);

				RateLimiter::record_request( $client_ip );

				return $this->render_error(
					__( 'Security verification failed. Submission blocked.', 'contactin' ),
					__( 'Please refresh and try once more. If this continues, contact support.', 'contactin' )
				);
			}

			$recaptcha_score = isset( $recaptcha_result['score'] ) ? (float) $recaptcha_result['score'] : null;
			Logger::debug(
				'reCAPTCHA score captured',
				array(
					'score' => $recaptcha_score,
					'email' => $payload['email'],
				)
			);
		}

		// Concurrency control: Acquire distributed lock (Phase 2D)
		$lock_key   = 'form_submit_' . md5( $client_ip . $payload['email'] );
		$lock_token = ConcurrencyManager::acquire_lock( $lock_key, 10 );

		if ( empty( $lock_token ) ) {
			Logger::warning(
				'Failed to acquire concurrency lock',
				array(
					'lock_key' => $lock_key,
					'email'    => $payload['email'],
				)
			);
			return $this->render_error(
				__( 'Request already in progress. Please wait.', 'contactin' ),
				__( 'We are finishing your previous request. Please wait a few seconds and avoid double-clicking submit.', 'contactin' )
			);
		}

		// Wrap remaining logic in try-finally to ensure lock is released
		$handler_result = null;
		try {
			// Duplicate detection (Phase 2D) - DO NOT SAVE if duplicate
			$message_hash = ConcurrencyManager::hash_message( $payload['message'] );
			if ( ConcurrencyManager::is_duplicate( $payload['email'], $message_hash, ConcurrencyManager::DUPLICATE_WINDOW ) ) {
				Logger::warning(
					'Duplicate submission detected',
					array(
						'email' => $payload['email'],
						'hash'  => $message_hash,
					)
				);
				// Do not process/save, just set error result
				$handler_result = $this->render_error(
					__( 'Duplicate submission detected. Please wait before submitting again.', 'contactin' ),
					sprintf(
						/* translators: %d: number of seconds for duplicate detection window */
						__( 'We received an identical message within the last %d seconds. Please wait a moment or adjust your message before resubmitting.', 'contactin' ),
						ConcurrencyManager::DUPLICATE_WINDOW
					)
				);
			} else {
				// Delegate to FormService for atomic save with GDPR link generation
				$form_data = array(
					'salutation'      => $payload['salutation'],
					'name'            => $payload['name'],
					'email'           => $payload['email'],
					'message'         => $payload['message'],
					'consent'         => $payload['consent'],
					'form_id'         => $payload['form_id'],
					'recaptcha_score' => $recaptcha_score,
				);

				if ( ! empty( $payload['subject'] ) ) {
					$form_data['subject'] = $payload['subject'];
				}

				// Multi-phone logic: use PhoneUtils to classify and assign
				$phone_raw                 = $payload['phone'] ?? '';
				$form_data['phone']        = $phone_raw;
				$form_data['mobile_phone'] = null;
				$form_data['home_phone']   = null;
				$form_data['other_phone']  = null;

				if ( ! empty( $phone_raw ) ) {
					if ( ! class_exists( 'ContactInbox\\Core\\PhoneUtils' ) ) {
						require_once dirname( __DIR__, 2 ) . '/Core/PhoneUtils.php';
					}
					$type       = \ContactInbox\Core\PhoneUtils::detect_type( $phone_raw, array( 'default_country' => '' ) );
					$normalized = \ContactInbox\Core\PhoneUtils::normalize( $phone_raw, '' );
					if ( $type === \ContactInbox\Core\PhoneUtils::TYPE_MOBILE ) {
						$form_data['mobile_phone'] = $normalized;
					} elseif ( $type === \ContactInbox\Core\PhoneUtils::TYPE_HOME ) {
						$form_data['home_phone'] = $normalized;
					} elseif ( $type === \ContactInbox\Core\PhoneUtils::TYPE_OTHER ) {
						$form_data['other_phone'] = $normalized;
					} else {
						// Unknown: assign to phone (legacy/main field)
						$form_data['phone'] = $normalized;
					}
				}

				// Include attachment info if file was uploaded
				Logger::info(
					'Before FormService: checking attachment',
					array(
						'attachment_empty'      => empty( $attachment ),
						'attachment_value'      => $attachment ?: 'empty',
						'attachment_info_empty' => empty( $attachment_info ),
						'attachment_info_value' => $attachment_info ?: array(),
					)
				);

				if ( ! empty( $attachment ) && ! empty( $attachment_info ) ) {
					$form_data['attachment'] = wp_json_encode( $attachment_info + array( 'path' => $attachment ) );
					Logger::debug(
						'Attachment being saved',
						array(
							'attachment_json' => $form_data['attachment'],
							'attachment_url'  => $attachment,
							'attachment_info' => $attachment_info,
						)
					);
				} elseif ( ! empty( $attachment ) ) {
					$form_data['attachment'] = $attachment;
					Logger::debug( 'Attachment URL only (no info)', array( 'attachment_url' => $attachment ) );
				}

				// Call FormService which handles atomic save + GDPR token generation
				$form_result = FormService::submit( $form_data, array() );

				if ( $form_result instanceof WP_Error ) {
					$error_code = $form_result->get_error_code();
					$tip        = $this->map_failure_tip( $error_code );

					Logger::error(
						'Form service submission failed',
						array(
							'error' => $form_result->get_error_message(),
							'code'  => $error_code,
							'email' => $payload['email'],
						)
					);
					$handler_result = $this->render_error( $form_result->get_error_message(), $tip );
				} else {
					// Extract data from FormService response
					$message_id       = $form_result['message_id'];
					$receipt_token    = $form_result['receipt_token'] ?? wp_generate_password( 32, false );
					$gdpr_delete_link = $form_result['gdpr_delete_link'] ?? '';
					$submission_data  = $form_result['payload'] ?? array();

					// SUCCESS: Data is now safely in database with GDPR link
					Logger::info(
						'Submission saved with receipt token',
						array(
							'receipt_token' => substr( $receipt_token, 0, 8 ) . '...',
							'email'         => $payload['email'],
							'attachment'    => $attachment ?: 'none',
							'has_gdpr_link' => ! empty( $gdpr_delete_link ),
						)
					);

					// Trigger AnalyticsHooks for webhook queueing
					do_action( 'contactin_message_received', $message_id, $submission_data );

					// Defer homework (email/CRM processing) to async hook to keep response fast
					$contact_id = $form_result['contact_id'] ?? null;
					if ( ! wp_next_scheduled( 'contactin_post_submit_homework', array( $message_id, $contact_id ) ) ) {
						wp_schedule_single_event( time(), 'contactin_post_submit_homework', array( $message_id, $contact_id ) );
					}

					// Nudge WP-Cron immediately; if disabled, run the hook inline as a fallback.
					// IMPORTANT: unschedule first so the event does not also fire via cron later,
					// which would cause double email-sending / double CRM queueing.
					$spawned = spawn_cron();
					if ( ! $spawned ) {
						wp_unschedule_event( time(), 'contactin_post_submit_homework', array( $message_id, $contact_id ) );
						do_action( 'contactin_post_submit_homework', $message_id, $contact_id );
					}

					// Verify file actually exists if attachment was provided
					// Double-check that file wasn't deleted between finalize and save
					if ( ! empty( $attachment ) ) {
						$full_path = WP_CONTENT_DIR . '/uploads/' . str_replace( WP_CONTENT_URL . '/uploads/', '', $attachment );
						if ( ! file_exists( $full_path ) ) {
							Logger::warning(
								'Attachment file verification failed',
								array(
									'path'  => $full_path,
									'email' => $payload['email'],
								)
							);
						}
					}

					Logger::debug(
						'Form submission completed with GDPR link',
						array(
							'message_id'    => $message_id,
							'email'         => $payload['email'],
							'has_gdpr_link' => ! empty( $gdpr_delete_link ),
						)
					);

					// NOTE: Initial message statuses (email & CRM) are now set by FormService.submit()
					// This ensures consistent status initialization across all entry points (AJAX, REST API, etc.)
					// No need to set them here anymore

					// Pass deletion link to success template
					$handler_result = $this->render_success( $receipt_token, $gdpr_delete_link, $settings, $attachment );
				}
			}
		} finally {
			// Always release the concurrency lock (Phase 2D)
			ConcurrencyManager::release_lock( $lock_key, $lock_token );
			Logger::debug( 'Concurrency lock released', array( 'lock_key' => $lock_key ) );

			// Record rate limit request for sliding window calculation (Phase 2D)
			RateLimiter::record_request( $client_ip );
		}

		return $handler_result;
	}

	/**
	 * Set initial message processing statuses (Phase 2: Queue Redesign)
	 *
	 * Instead of queuing operations to a separate queue table, we set status columns
	 * directly in the message record. CronJobs will later scan for pending statuses.
	 *
	 * @param array $email_data Email notification data (name, email, subject, message_id, etc)
	 * @param array $settings   Settings array (send_admin_notification, send_user_copy, etc)
	 * @param array $crm_settings CRM settings array (crm_enabled, endpoint, etc)
	 */
	private static function set_initial_message_statuses( array $email_data, array $settings, array $crm_settings ): void {
		global $wpdb;

		// Validate critical data before attempting anything
		if ( empty( $email_data['email'] ) || empty( $email_data['name'] ) || empty( $email_data['message_id'] ) ) {
			Logger::warning(
				'Invalid email data provided to set_initial_message_statuses',
				array(
					'has_email'      => ! empty( $email_data['email'] ),
					'has_name'       => ! empty( $email_data['name'] ),
					'has_message_id' => ! empty( $email_data['message_id'] ),
				)
			);
			return;
		}

		$message_id         = $email_data['message_id'];
		$table              = $wpdb->prefix . Config::TABLE_MESSAGES;
		$update_data        = array();
		$update_format      = array();
		$operations_pending = array();
		$operations_skipped = array();

		// Check if SMTP is enabled - if not, skip all email operations
		$smtp_enabled = ! empty( $settings['smtp_enable'] );

		// Set admin email status based on SMTP and notification preference
		if ( $smtp_enabled && ! empty( $settings['send_admin_notification'] ) ) {
			$update_data['admin_email_status'] = Config::EMAIL_PENDING;
			$operations_pending[]              = 'admin_email';
		} else {
			$update_data['admin_email_status'] = Config::EMAIL_SKIPPED;
			$operations_skipped[]              = 'admin_email';
			if ( ! $smtp_enabled ) {
				Logger::info( 'Admin email skipped - SMTP disabled' );
			}
		}
		$update_format[] = '%s';

		// Set user email status based on SMTP and user copy preference
		if ( $smtp_enabled && ! empty( $settings['send_user_copy'] ) ) {
			$update_data['user_email_status'] = Config::EMAIL_PENDING;
			$operations_pending[]             = 'user_email';
		} else {
			$update_data['user_email_status'] = Config::EMAIL_SKIPPED;
			$operations_skipped[]             = 'user_email';
			if ( ! $smtp_enabled ) {
				Logger::info( 'User email skipped - SMTP disabled' );
			}
		}
		$update_format[] = '%s';

		// Set CRM status based on integration toggle
		if ( ! empty( $crm_settings['crm_enabled'] ) ) {
			$update_data['crm_status'] = Config::CRM_PENDING;
			$operations_pending[]      = 'crm';
		} else {
			$update_data['crm_status'] = Config::CRM_SKIPPED;
			$operations_skipped[]      = 'crm';
		}
		$update_format[] = '%s';

		// Update message statuses in database
		$result = $wpdb->update(
			$table,
			$update_data,
			array( 'id' => $message_id ),
			$update_format,
			array( '%d' )
		);

		if ( $result === false ) {
			Logger::error(
				'Failed to set initial message statuses',
				array(
					'message_id' => $message_id,
					'error'      => $wpdb->last_error,
				)
			);
		} else {
			Logger::info(
				'Initial message statuses initialized',
				array(
					'message_id'         => $message_id,
					'pending_operations' => $operations_pending,
					'skipped_operations' => $operations_skipped,
				)
			);
		}
	}



	/**
	 * Move uploaded file from temp directory to final attachments directory
	 *
	 * @param string $temp_file_path Path to temp file
	 * @param string $file_id        UUID of the file
	 * @param string $file_ext       File extension
	 * @return string|WP_Error Final file path or error
	 */
	private function finalize_uploaded_file( $temp_file_path, $file_id, $file_ext ) {
		if ( ! is_dir( CONTACTINBOX_UPLOADS_PATH ) ) {
			wp_mkdir_p( CONTACTINBOX_UPLOADS_PATH );
		}

		$final_filename = $file_id . '.' . $file_ext;
		$final_path     = CONTACTINBOX_UPLOADS_PATH . $final_filename;

		// Move file from temp to final location
		if ( ! rename( $temp_file_path, $final_path ) ) {
			return new WP_Error(
				'file_move_failed',
				__( 'Failed to finalize file upload.', 'contactin' )
			);
		}

		// Return relative path for storage
		return CONTACTINBOX_UPLOADS_URL . $final_filename;
	}

	/**
	 * Handle secure file upload
	 *
	 * @param array $file Validated file array from FormService
	 * @return string|WP_Error Uploaded file path or error
	 */
	private function handle_file_upload( array $file ) {
		if ( ! function_exists( 'wp_handle_upload' ) ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
		}

		// Get allowed file types from settings to ensure wp_handle_upload respects the admin configuration
		$settings      = get_option( Config::OPTION_SETTINGS, array() );
		$allowed_types = ! empty( $settings['allowed_file_types'] )
			? array_map( 'trim', explode( ',', strtolower( $settings['allowed_file_types'] ) ) )
			: array( 'jpg', 'jpeg', 'png', 'pdf', 'doc', 'docx' );

		// Build MIME types map from allowed extensions
		// Important: WordPress may group multiple extensions in one MIME entry (e.g., "jpg|jpeg|jpe")
		// We need to split these so that only individually allowed extensions are accepted
		$wp_mimes       = get_allowed_mime_types();
		$mimes_override = array();

		foreach ( $wp_mimes as $ext_group => $mime_type ) {
			// MIME types can have multiple extensions separated by |, e.g., "jpg|jpeg|jpe"
			$extensions                  = explode( '|', $ext_group );
			$allowed_extensions_in_group = array();

			// Check which extensions in this group are allowed
			foreach ( $extensions as $ext ) {
				if ( in_array( strtolower( $ext ), $allowed_types, true ) ) {
					$allowed_extensions_in_group[] = $ext;
				}
			}

			// If this group has allowed extensions, add them individually to the override
			// This ensures that if only "jpg" is allowed but the group is "jpg|jpeg",
			// we don't accidentally allow "jpeg"
			if ( ! empty( $allowed_extensions_in_group ) ) {
				// If all extensions in the group are allowed, keep the group as-is
				if ( count( $allowed_extensions_in_group ) === count( $extensions ) ) {
					$mimes_override[ $ext_group ] = $mime_type;
				} else {
					// Otherwise, create individual entries for only the allowed extensions
					foreach ( $allowed_extensions_in_group as $ext ) {
						$mimes_override[ $ext ] = $mime_type;
					}
				}
			}
		}

		$overrides = array(
			'test_form' => false,
			'mimes'     => ! empty( $mimes_override ) ? $mimes_override : array(
				'jpg|jpeg' => 'image/jpeg',
				'png'      => 'image/png',
				'pdf'      => 'application/pdf',
				'doc|docx' => 'application/msword',
			),
		);

		// Create a temporary $_FILES entry for wp_handle_upload
		$temp_file = array(
			'name'     => $file['name'],
			'type'     => $file['type'],
			'tmp_name' => $file['tmp_name'],
			'error'    => 0,
			'size'     => $file['size'],
		);

		$uploaded = wp_handle_upload( $temp_file, $overrides );

		if ( isset( $uploaded['error'] ) ) {
			return new WP_Error( 'upload_error', $uploaded['error'] );
		}

		return $uploaded['file'] ?? '';
	}

	/**
	 * Get attachment file information (name, size, type)
	 * Used to display attachment details in success message
	 *
	 * @param string $attachment_path URL path to attachment
	 * @return array Attachment info (name, size_formatted, size_bytes)
	 */
	private function get_attachment_info( string $attachment_path ): array {
		if ( empty( $attachment_path ) ) {
			Logger::debug( 'Empty attachment path provided to get_attachment_info' );
			return array();
		}

		// Use centralized helper for URL to path conversion and file info
		$file_info = \ContactInbox\Core\AttachmentHelper::get_file_info( $attachment_path );

		if ( ! $file_info['exists'] ) {
			Logger::warning(
				'Attachment file missing during success response',
				array(
					'original_path' => $attachment_path,
					'resolved_path' => $file_info['path'],
					'reason'        => 'File does not exist at expected location',
				)
			);
			return array();
		}

		return array(
			'name'       => $file_info['name'],
			'size'       => $file_info['size'],
			'size_bytes' => $file_info['size_bytes'],
			'exists'     => true,
		);
	}

	/**
	 * Format bytes into human-readable size (B, KB, MB, GB)
	 *
	 * @param int $bytes File size in bytes
	 * @return string Formatted file size
	 */
	private function format_file_size( int $bytes ): string {
		$units  = array( 'B', 'KB', 'MB', 'GB' );
		$bytes  = max( $bytes, 0 );
		$pow    = floor( ( $bytes ? log( $bytes ) : 0 ) / log( 1024 ) );
		$pow    = min( $pow, count( $units ) - 1 );
		$bytes /= 1024 ** $pow;

		return round( $bytes, 2 ) . ' ' . $units[ $pow ];
	}

	/**
	 * Render success template and exit
	 *
	 * CRITICAL: This is called AFTER database save
	 * Data is guaranteed to be persisted regardless of email/CRM success
	 * Attachment metadata (filename, size) is stored in database, not shown to user
	 * Receipt token provided for user tracking and support requests
	 */
	private function render_success(
		string $receipt_token,
		string $gdpr_delete_link,
		array $settings,
		string $attachment = ''
	) {
		ob_start();
		$failure_message = null; // Not used in success template
		$delete_link     = $gdpr_delete_link; // Make available to template
		$masked_receipt  = ReceiptTokenService::mask_token( $receipt_token, 4 ); // Show partial token for display
		include CONTACTINBOX_PATH . Config::TEMPLATE_FRONTEND . 'form-success-message.php';
		$html = ob_get_clean();

		// GOLD STANDARD: Log successful submission with all tracking data
		Logger::info(
			'Form submission completed successfully',
			array(
				'receipt_token'    => substr( $receipt_token, 0, 8 ) . '***',
				'has_gdpr_link'    => ! empty( $gdpr_delete_link ),
				'has_attachment'   => ! empty( $attachment ),
				'confetti_enabled' => ! empty( $settings['confetti_enable'] ),
			)
		);

		// Return success with data_saved flag and receipt token for user tracking
		wp_send_json_success(
			array(
				'html'          => $html,
				'data_saved'    => true, // Confirm data was saved to database
				'receipt_token' => $receipt_token, // Full token for admin/later use
				'confetti'      => ! empty( $settings['confetti_enable'] ), // Add confetti flag for frontend
			)
		);
	}

	/**
	 * Render error template and exit
	 */
	private function render_error( string $message, string $tip = '' ) {
		ob_start();
		$failure_message = $message;
		$failure_tip     = $tip;
		include CONTACTINBOX_PATH . Config::TEMPLATE_FRONTEND . 'form-failure-message.php';
		$html = ob_get_clean();

		wp_send_json_error( array( 'html' => $html ) );
	}

	/**
	 * Provide user-facing tips tailored to known error codes
	 */
	private function map_failure_tip( string $error_code ): string {
		switch ( $error_code ) {
			case 'duplicate_submission':
				return __( 'We detected this is the same message you just submitted. To prevent duplicate submissions, we\'ve rejected it. Please wait at least 30 seconds before submitting a different message, or modify this one before resubmitting.', 'contactin' );
			case 'validation_failed':
				return __( 'Please review the form requirements and try again. Check required fields, word counts, and character limits.', 'contactin' );
			case 'consent_required':
				return __( 'Please accept the consent checkbox and try again.', 'contactin' );
			case 'missing_required_fields':
				return __( 'Please complete the required fields and submit again.', 'contactin' );
			case 'database_error':
			case 'transaction_error':
				return __( 'The database is currently busy. Please wait 30 seconds and try again. If this continues to happen, contact us with your email and the time you submitted.', 'contactin' );
			case 'database_locked':
				return __( 'Database is temporarily locked due to high traffic. Please wait about 30 seconds and try again. This usually resolves quickly.', 'contactin' );
			case 'database_deadlock':
				return __( 'We encountered a temporary database conflict. Please wait 30 seconds and try again. Your submission will not be duplicated.', 'contactin' );
			default:
				return __( 'Please try again. If this keeps happening, contact us and include your email and the time you submitted.', 'contactin' );
		}
	}

	/**
	 * Async: Send admin notification email
	 * Runs via loopback request after form submission completes
	 */
	public static function async_send_admin_notification( array $email_data ) {
		\ContactInbox\Core\SMTP::send_admin_notification( $email_data );
	}

	/**
	 * Async: Send user confirmation email
	 * Runs via loopback request after form submission completes
	 */
	public static function async_send_user_confirmation( array $email_data ) {
		\ContactInbox\Core\SMTP::send_user_confirmation( $email_data );
	}

	/**
	 * Async: Send form data to CRM
	 * Runs via loopback request after form submission completes
	 */
	public static function async_send_crm( array $crm_data ) {
		$response = CRMConnector::send( $crm_data );

		if ( WP_DEBUG ) {
			if ( is_wp_error( $response ) ) {
				Logger::debug(
					'CRM async dispatch failed',
					array( 'error' => $response->get_error_message() )
				);
			} else {
				Logger::debug(
					'CRM async dispatch succeeded',
					array( 'status' => $response['crm_status'] ?? 'unknown' )
				);
			}
		}
	}

	/**
	 * AJAX File Upload Handler (for shortcode forms only)
	 *
	 * GOLD STANDARD APPROACH:
	 * - Works regardless of REST API setting
	 * - Only for forms embedded via shortcode
	 * - Direct REST API access still requires REST API to be enabled
	 *
	 * This ensures:
	 * 1. Shortcode forms work seamlessly (don't need REST API)
	 * 2. Direct API access still requires proper enablement
	 * 3. Clean separation of concerns
	 */
	public function handle_attachment_upload_ajax() {
		// Check nonce for security
		if ( ! isset( $_REQUEST['nonce'] ) || ! wp_verify_nonce( $_REQUEST['nonce'], 'wp_rest' ) ) {
			wp_send_json_error(
				array(
					'message' => __( 'Security check failed', 'contactin' ),
				),
				403
			);
		}

		// Verify file was uploaded
		if ( empty( $_FILES['file'] ) ) {
			wp_send_json_error(
				array(
					'message' => __( 'No file provided', 'contactin' ),
				),
				400
			);
		}

		$file     = $_FILES['file'];

		if ( ! function_exists( 'wp_handle_upload' ) ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
		}
		$settings = get_option( Config::OPTION_SETTINGS, array() );

		// Check if attachments are enabled in form settings
		if ( empty( $settings['form_enable_attachment'] ) ) {
			wp_send_json_error(
				array(
					'message' => __( 'File attachments are disabled', 'contactin' ),
				),
				403
			);
		}

		// Rate limit check (per IP, doesn't block all uploads)
		$client_ip  = Security::get_ip_address();
		$rate_limit = RateLimiter::check_rate_limit( $client_ip );
		if ( ! $rate_limit['allowed'] ) {
			wp_send_json_error(
				array(
					'message' => __( 'Too many requests. Please wait and try again.', 'contactin' ),
				),
				429
			);
		}

		// Validate file size
		$max_mb    = absint( $settings['max_file_size'] ?? 5 );
		$max_bytes = max( 1, $max_mb ) * 1024 * 1024;

		if ( $file['size'] > $max_bytes ) {
			wp_send_json_error(
				array(
					'message' => sprintf(
						__( 'File exceeds maximum size of %d MB', 'contactin' ),
						$max_mb
					),
				),
				422
			);
		}

		// Validate file type against allowed extensions
		// NOTE: No fallback default - if not configured, no files are allowed (whitelist approach)
		$allowed_types = array_map( 'trim', explode( ',', strtolower( $settings['allowed_file_types'] ?? '' ) ) );
		$ext           = strtolower( pathinfo( $file['name'], PATHINFO_EXTENSION ) );

		if ( ! $ext || ! in_array( $ext, $allowed_types, true ) ) {
			wp_send_json_error(
				array(
					'message' => sprintf(
						__( 'File type .%s is not allowed', 'contactin' ),
						$ext ?: 'unknown'
					),
				),
				422
			);
		}

		// Generate unique filename with UUID (same as REST API controller)
		$original_name = sanitize_file_name( $file['name'] );
		$unique_id     = wp_generate_uuid4();

		$upload_dir_filter = static function ( $dirs ) {
			$subdir        = '/contactin-temp-uploads';
			$dirs['subdir'] = $subdir;
			$dirs['path']   = $dirs['basedir'] . $subdir;
			$dirs['url']    = $dirs['baseurl'] . $subdir;
			return $dirs;
		};

		$overrides = array(
			'test_form'                => false,
			'unique_filename_callback' => static function ( $dir, $name, $file_ext ) use ( $unique_id ) {
				return $unique_id . $file_ext;
			},
		);

		add_filter( 'upload_dir', $upload_dir_filter );
		$uploaded = wp_handle_upload( $file, $overrides );
		remove_filter( 'upload_dir', $upload_dir_filter );

		if ( isset( $uploaded['error'] ) ) {
			Logger::error(
				'File upload failed',
				array(
					'temp_file'   => $file['tmp_name'],
					'target_path' => 'contactin-temp-uploads',
					'temp_exists' => file_exists( $file['tmp_name'] ) ? 'yes' : 'no',
					'error'       => $uploaded['error'],
				)
			);

			wp_send_json_error(
				array(
					'message' => __( 'Failed to save uploaded file', 'contactin' ),
				),
				500
			);
		}

		$file_path = $uploaded['file'];

		Logger::info(
			'File uploaded successfully via AJAX',
			array(
				'file_id'  => $unique_id,
				'filename' => $original_name,
				'size'     => filesize( $file_path ),
			)
		);

		// Return success with file info (same format as REST API for compatibility)
		wp_send_json_success(
			array(
				'file_id'  => $unique_id,
				'filename' => $original_name,
				'ext'      => $ext,
			),
			200
		);
	}
}
