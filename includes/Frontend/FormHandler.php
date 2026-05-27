<?php
/**
 * Frontend – AJAX Form Handler
 *
 * Handles AJAX form submission securely with:
 * - Nonce, honeypot, rate limiting, ReCAPTCHA
 * - Validation via Core\FormService
 * - Database persistence
 * - Email notifications via SMTP
 *
 * @package ContactIn
 */

namespace ContactInbox\Frontend;

use ContactInbox\Core\Config;
use ContactInbox\Core\FormService;
use ContactInbox\Core\Security;

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
	}


	/**
	 * Main AJAX handler – validates, saves, sends emails
	 *
	 * CRITICAL: Data is saved to database BEFORE success response
	 * Ensures no data loss even if async operations fail
	 */
	public function handle() {
		$start_time    = microtime( true );
		$form_id       = sanitize_key( wp_unslash( $_POST['form_id'] ?? 'default' ) );
		$settings      = \ContactInbox\Core\FormProfiles::resolve( (string) $form_id );
		$attempts_repo = new SubmissionAttemptsRepository();

		// Get client info for attempt logging
		$client_ip  = Security::get_ip_address();
		$user_agent = isset( $_SERVER['HTTP_USER_AGENT'] )
			? sanitize_text_field( wp_unslash( (string) $_SERVER['HTTP_USER_AGENT'] ) )
			: '';

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
					'email'              => sanitize_email( wp_unslash( $_POST['email'] ?? '' ) ),
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
						'email'              => sanitize_email( wp_unslash( $_POST['email'] ?? '' ) ),
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

		// Get form data (unslashed once before per-field sanitization).
		$form_data = wp_unslash( $_POST );

		// Apply render-time form configuration overrides (HMAC-signed hidden fields).
		$_cin_vf_raw = isset( $form_data['cin_vf'] ) ? (string) $form_data['cin_vf'] : '';
		$_cin_vf_mac = isset( $form_data['cin_vf_mac'] ) ? (string) $form_data['cin_vf_mac'] : '';
		if ( $_cin_vf_raw && $_cin_vf_mac ) {
			$expected_mac = hash_hmac( 'sha256', $_cin_vf_raw, wp_salt( 'auth' ) );
			if ( hash_equals( $expected_mac, $_cin_vf_mac ) ) {
				$vf = json_decode( $_cin_vf_raw, true );
				if ( is_array( $vf ) ) {
					if ( isset( $vf['es'] ) ) {
						$settings['form_enable_subject'] = (bool) $vf['es'];
					}
					if ( isset( $vf['rs'] ) ) {
						$settings['form_require_subject'] = (bool) $vf['rs'];
					}
					if ( isset( $vf['rp'] ) ) {
						$settings['require_phone'] = (bool) $vf['rp'];
					}
					if ( isset( $vf['cr'] ) ) {
						$settings['consent_required'] = (bool) $vf['cr'];
					}
				}
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
			'form_id'    => sanitize_key( $form_data['form_id'] ?? 'default' ),
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

		// Extract reCAPTCHA score (Phase 1: Gold Standard Logging)
		// Score is captured from reCAPTCHA v3 response for spam detection
		$recaptcha_score = null;
		if ( ! empty( $settings['recaptcha_enable'] ) && ! empty( $settings['recaptcha_site_key'] ) ) {
			$recaptcha_token = sanitize_text_field( $form_data['g-recaptcha-response'] ?? $form_data['recaptcha_token'] ?? '' );
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
				// Delegate to FormService for validation + atomic save.
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

				// Call FormService for validation + atomic save
				$form_result = FormService::submit( $form_data, array(), $settings );

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
					$submission_data  = $form_result['payload'] ?? array();

					// SUCCESS: Data is now safely in database.
					Logger::info(
						'Submission saved with receipt token',
						array(
							'receipt_token' => substr( $receipt_token, 0, 8 ) . '...',
							'email'         => $payload['email'],
						)
					);

					// Trigger AnalyticsHooks for webhook queueing
					do_action( 'contactin_message_received', $message_id, $submission_data );

					// Defer post-submit processing to async hook to keep response fast.
					$contact_id = $form_result['contact_id'] ?? null;
					if ( ! wp_next_scheduled( 'contactin_post_submit_homework', array( $message_id, $contact_id ) ) ) {
						wp_schedule_single_event( time(), 'contactin_post_submit_homework', array( $message_id, $contact_id ) );
					}

					// Nudge WP-Cron immediately; if disabled, run the hook inline as a fallback.
					// IMPORTANT: unschedule first so the event does not also fire via cron later.
					$spawned = spawn_cron();
					if ( ! $spawned ) {
						wp_unschedule_event( time(), 'contactin_post_submit_homework', array( $message_id, $contact_id ) );
						do_action( 'contactin_post_submit_homework', $message_id, $contact_id );
					}

					Logger::debug(
						'Form submission completed',
						array(
							'message_id' => $message_id,
							'email'      => $payload['email'],
						)
					);

					// Initial message statuses are set in FormService::submit() for consistency.
					// No need to set them here anymore

					$handler_result = $this->render_success( $receipt_token, $settings );
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
	 * Render success template and exit
	 *
	 * CRITICAL: This is called AFTER database save
	 * Data is guaranteed to be persisted regardless of async processing state.
	 * Receipt token provided for user tracking and support requests
	 */
	private function render_success( string $receipt_token, array $settings ) {
		ob_start();
		$failure_message = null; // Not used in success template
		$delete_link     = '';
		$masked_receipt  = ReceiptTokenService::mask_token( $receipt_token, 4 ); // Show partial token for display
		include CONTACTINBOX_PATH . Config::TEMPLATE_FRONTEND . 'form-success-message.php';
		$html = ob_get_clean();

		// GOLD STANDARD: Log successful submission with all tracking data
		Logger::info(
			'Form submission completed successfully',
			array(
				'receipt_token'    => substr( $receipt_token, 0, 8 ) . '***',
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
}
