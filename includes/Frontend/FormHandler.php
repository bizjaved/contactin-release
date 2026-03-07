<?php
// phpcs:disable WordPress.WP.I18n.MissingTranslatorsComment, WordPress.WP.I18n.UnorderedPlaceholdersText, WordPress.WP.I18n.NonSingularStringLiteralText
/**
 * Frontend – AJAX Form Handler
 *
 * Handles AJAX form submission securely with:
 * - Nonce, honeypot, rate limiting, ReCAPTCHA
 * - Validation via Core\FormService
 * - Database persistence, GDPR token generation
 * - Email notifications via SMTP
 *
 * @package ContactInbox
 */

namespace ContactInbox\Frontend;

use ContactInbox\Core\Config;
use ContactInbox\Core\FormService;
use ContactInbox\Core\DB;
use ContactInbox\Core\Security;
use ContactInbox\Core\CRMConnector;
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
    use Singleton, SubmissionRateLimiterTrait;

    /**
     * Initialize AJAX hooks.
     */
    protected function __construct() {
        add_action('wp_ajax_contactin_submit', [ $this, 'handle' ]);
        add_action('wp_ajax_nopriv_contactin_submit', [ $this, 'handle' ]);
    }


    /**
     * Main AJAX handler – validates, saves, sends emails
     * 
     * CRITICAL: Data is saved to database BEFORE success response
     * Ensures no data loss even if async operations fail
     */
    public function handle() {
        $post_data = isset($_POST) && is_array($_POST) ? wp_unslash($_POST) : [];
        $file_id_for_log = isset($post_data['file_id']) ? sanitize_text_field((string) $post_data['file_id']) : '';

        \ContactInbox\Core\Logger::info('===FormHandler::handle() CALLED===', [
            'has_file_id_post' => '' !== $file_id_for_log,
            'file_id_value' => $file_id_for_log !== '' ? $file_id_for_log : 'NOT SET'
        ]);
        \ContactInbox\Core\Logger::debug('FormHandler handle() entered', [
            'file' => __FILE__,
            'line' => __LINE__,
            'request' => $post_data
        ]);
        $start_time = microtime(true);
        $settings = get_option(Config::OPTION_SETTINGS, []);
        $attempts_repo = new SubmissionAttemptsRepository();
        
        // Get client info for attempt logging
        $client_ip = Security::get_ip_address();
        $user_agent = isset($_SERVER['HTTP_USER_AGENT']) ? sanitize_text_field(wp_unslash($_SERVER['HTTP_USER_AGENT'])) : '';
        $form_id = isset($post_data['form_id']) ? sanitize_text_field((string) $post_data['form_id']) : 'default';
        $request_email = isset($post_data['email']) ? sanitize_email((string) $post_data['email']) : null;

        // Nonce check (prevents CSRF/cache replay)
        if (!check_ajax_referer(Config::FORM_SUBMIT_NONCE, 'nonce', false)) {
            $processing_time = (int)((microtime(true) - $start_time) * 1000);
            $attempts_repo->log_attempt([
                'form_id' => $form_id,
                'ip_address' => $client_ip,
                'user_agent' => $user_agent,
                'rejection_reason' => 'nonce_failed',
                'processing_time_ms' => $processing_time,
            ]);
            
            return $this->render_error(
                __('Security check failed. Please refresh the page and submit again (token expired).', 'contact-inbox'),
                __('Refresh the page to get a new security token, then submit once.', 'contact-inbox')
            );
        }

        // Rate limiting check (Phase 2D: Concurrent Safety)
        $rate_limit = RateLimiter::check_rate_limit($client_ip);

        if (!$rate_limit['allowed']) {
            $processing_time = (int)((microtime(true) - $start_time) * 1000);
            $attempts_repo->log_attempt([
                'form_id' => $form_id,
                'email' => $request_email,
                'ip_address' => $client_ip,
                'user_agent' => $user_agent,
                'rejection_reason' => 'rate_limited',
                'processing_time_ms' => $processing_time,
            ]);
            
            Logger::warning('Rate limit exceeded', [
                'ip' => $client_ip,
                'reason' => $rate_limit['reason'],
                'window' => $rate_limit['window'] ?? 'unknown',
            ]);

            $retry_after = $rate_limit['retry_after'] ?? 60;
            $error_msg = sprintf(
                __('Too many requests from this network. Please wait %d seconds and try again.', 'contact-inbox'),
                $retry_after
            );
            $tip = sprintf(
                __('Wait about %d seconds, then submit once. If it keeps happening, contact us with your approximate time and email.', 'contact-inbox'),
                $retry_after
            );
            return $this->render_error($error_msg, $tip);
        }

        // Get form data (attachments disabled in free version)
        $form_data = $post_data;
        $attachment = '';
        $attachment_info = [];

        // PHASE 1: Tiered duplicate/rate-limit checks (AFTER file processing)
        if ($this->isRapidRepeat($form_data)) {
            return $this->render_error(
                __('You just submitted this message. Please wait before resubmitting.', 'contact-inbox'),
                __('Rapid repeat detected. Please wait at least 30 seconds before submitting again.', 'contact-inbox')
            );
        }
        if ($this->isShortTermRepeat($form_data, 2)) {
            return $this->render_error(
                __('You have submitted this message multiple times in a short period.', 'contact-inbox'),
                __('Please wait a few minutes before submitting again, or contact support if you need urgent help.', 'contact-inbox')
            );
        }
        if ($this->isLongTermRepeat($form_data, 5)) {
            return $this->render_error(
                __('You have reached the daily limit for this message.', 'contact-inbox'),
                __('Please wait 24 hours before submitting this message again.', 'contact-inbox')
            );
        }
        if ($this->isAbsoluteRepeat($form_data, 10)) {
            return $this->render_error(
                __('You have reached the weekly limit for this message.', 'contact-inbox'),
                __('Please contact support if you need to submit this message again.', 'contact-inbox')
            );
        }

        // Validate form data inline (without saving to database)
        // Extract and sanitize payload
        $payload = [
            'salutation' => sanitize_text_field( $form_data['salutation'] ?? '' ),
            'name'    => sanitize_text_field( $form_data['name'] ?? '' ),
            'email'   => sanitize_email( $form_data['email'] ?? '' ),
            'phone'   => sanitize_text_field( $form_data['phone'] ?? '' ),
            'message' => sanitize_textarea_field( $form_data['message'] ?? '' ),
            'consent' => ! empty( $form_data['consent'] ) ? 1 : 0,
            'form_id' => sanitize_text_field( $form_data['form_id'] ?? 'default' ),
        ];

        if ( isset( $settings['form_enable_subject'] ) && (bool) $settings['form_enable_subject'] ) {
            $payload['subject'] = sanitize_text_field( $form_data['subject'] ?? '' );
        }

        // Basic validation
        $missing = [];
        if ( $payload['name'] === '' ) {
            $missing[] = 'name';
        }
        if ( $payload['email'] === '' || ! is_email( $payload['email'] ) ) {
            $missing[] = 'email';
        }
        if ( $payload['message'] === '' ) {
            $missing[] = 'message';
        }
        if ( ! empty( $missing ) ) {
            $processing_time = (int)((microtime(true) - $start_time) * 1000);
            $attempts_repo->log_attempt([
                'form_id' => $form_id,
                'email' => $form_data['email'] ?? null,
                'ip_address' => $client_ip,
                'user_agent' => $user_agent,
                'rejection_reason' => 'validation_failed',
                'processing_time_ms' => $processing_time,
            ]);
            
            return $this->render_error(
                __( 'Missing or invalid required fields', 'contact-inbox' ),
                __('Please check required fields and try again.', 'contact-inbox')
            );
        }

        // Consent validation
        if ( ! empty( $settings['consent_required'] ) && empty( $payload['consent'] ) ) {
            $processing_time = (int)((microtime(true) - $start_time) * 1000);
            $attempts_repo->log_attempt([
                'form_id' => $form_id,
                'email' => $payload['email'],
                'ip_address' => $client_ip,
                'user_agent' => $user_agent,
                'rejection_reason' => 'consent_required',
                'processing_time_ms' => $processing_time,
            ]);
            
            return $this->render_error(
                __( 'Consent is required', 'contact-inbox' ),
                __('Please accept the consent checkbox and try again.', 'contact-inbox')
            );
        }

        // Extract reCAPTCHA score (Phase 1: Gold Standard Logging)
        // Score is captured from reCAPTCHA v3 response for spam detection
        $recaptcha_score = null;
        if (!empty($settings['recaptcha_enable']) && !empty($settings['recaptcha_site_key'])) {
            $recaptcha_token = $form_data['g-recaptcha-response'] ?? $form_data['recaptcha_token'] ?? '';
            if (!empty($recaptcha_token)) {
                $recaptcha_result = reCAPTCHA::verify_with_score($recaptcha_token);
                
                // Log if reCAPTCHA verification failed
                if (!$recaptcha_result['valid']) {
                    $processing_time = (int)((microtime(true) - $start_time) * 1000);
                    $attempts_repo->log_attempt([
                        'form_id' => $form_id,
                        'email' => $payload['email'],
                        'ip_address' => $client_ip,
                        'user_agent' => $user_agent,
                        'rejection_reason' => 'recaptcha_failed',
                        'recaptcha_score' => $recaptcha_result['score'] ?? null,
                        'processing_time_ms' => $processing_time,
                    ]);
                }
                
                $recaptcha_score = ($recaptcha_result['valid'] && isset($recaptcha_result['score'])) ? (float)$recaptcha_result['score'] : null;
                Logger::debug('reCAPTCHA score captured', [
                    'score' => $recaptcha_score,
                    'email' => $payload['email'],
                ]);
            }
        }

        // Concurrency control: Acquire distributed lock (Phase 2D)
        $lock_key = 'form_submit_' . md5($client_ip . $payload['email']);
        $lock_token = ConcurrencyManager::acquire_lock($lock_key, 10);

        if (empty($lock_token)) {
            Logger::warning('Failed to acquire concurrency lock', [
                'lock_key' => $lock_key,
                'email' => $payload['email'],
            ]);
            return $this->render_error(
                __('Request already in progress. Please wait.', 'contact-inbox'),
                __('We are finishing your previous request. Please wait a few seconds and avoid double-clicking submit.', 'contact-inbox')
            );
        }

        // Wrap remaining logic in try-finally to ensure lock is released
        $handler_result = null;
        try {
            // Duplicate detection (Phase 2D) - DO NOT SAVE if duplicate
            $message_hash = ConcurrencyManager::hash_message($payload['message']);
            if (ConcurrencyManager::is_duplicate($payload['email'], $message_hash, ConcurrencyManager::DUPLICATE_WINDOW)) {
                Logger::warning('Duplicate submission detected', [
                    'email' => $payload['email'],
                    'hash' => $message_hash,
                ]);
                // Do not process/save, just set error result
                $handler_result = $this->render_error(
                    __('Duplicate submission detected. Please wait before submitting again.', 'contact-inbox'),
                    sprintf(
                        /* translators: %d: number of seconds for duplicate detection window */
                        __('We received an identical message within the last %d seconds. Please wait a moment or adjust your message before resubmitting.', 'contact-inbox'),
                        ConcurrencyManager::DUPLICATE_WINDOW
                    )
                );
            } else {
                // Delegate to FormService for atomic save with GDPR link generation
                $form_data = [
                    'salutation' => $payload['salutation'],
                    'name'    => $payload['name'],
                    'email'   => $payload['email'],
                    'message' => $payload['message'],
                    'consent' => $payload['consent'],
                    'recaptcha_score' => $recaptcha_score,
                ];

                if (!empty($payload['subject'])) {
                    $form_data['subject'] = $payload['subject'];
                }

                // Multi-phone logic: use PhoneUtils to classify and assign
                $phone_raw = $payload['phone'] ?? '';
                $form_data['phone'] = $phone_raw;
                $form_data['mobile_phone'] = null;
                $form_data['home_phone'] = null;
                $form_data['other_phone'] = null;

                if (!empty($phone_raw)) {
                    if (!class_exists('ContactInbox\\Core\\PhoneUtils')) {
                        require_once dirname(__DIR__, 2) . '/Core/PhoneUtils.php';
                    }
                    $type = \ContactInbox\Core\PhoneUtils::detect_type($phone_raw);
                    $normalized = \ContactInbox\Core\PhoneUtils::normalize($phone_raw);
                    if ($type === \ContactInbox\Core\PhoneUtils::TYPE_MOBILE) {
                        $form_data['mobile_phone'] = $normalized;
                    } elseif ($type === \ContactInbox\Core\PhoneUtils::TYPE_HOME) {
                        $form_data['home_phone'] = $normalized;
                    } elseif ($type === \ContactInbox\Core\PhoneUtils::TYPE_OTHER) {
                        $form_data['other_phone'] = $normalized;
                    } else {
                        // Unknown: assign to phone (legacy/main field)
                        $form_data['phone'] = $normalized;
                    }
                }
                
                // Include attachment info if file was uploaded
                Logger::info('Before FormService: checking attachment', [
                    'attachment_empty' => empty($attachment),
                    'attachment_value' => $attachment ?: 'empty',
                    'attachment_info_empty' => empty($attachment_info),
                    'attachment_info_value' => $attachment_info ?: []
                ]);
                
                if (!empty($attachment) && !empty($attachment_info)) {
                    $form_data['attachment'] = wp_json_encode($attachment_info + ['path' => $attachment]);
                    Logger::debug('Attachment being saved', [
                        'attachment_json' => $form_data['attachment'],
                        'attachment_url' => $attachment,
                        'attachment_info' => $attachment_info
                    ]);
                } elseif (!empty($attachment)) {
                    $form_data['attachment'] = $attachment;
                    Logger::debug('Attachment URL only (no info)', ['attachment_url' => $attachment]);
                }

                // Call FormService which handles atomic save + GDPR token generation
                $form_result = FormService::submit($form_data, []);

                if ($form_result instanceof WP_Error) {
                    $error_code = $form_result->get_error_code();
                    $tip = $this->map_failure_tip($error_code);

                    Logger::error(
                        'Form service submission failed',
                        [
                            'error' => $form_result->get_error_message(),
                            'code' => $error_code,
                            'email' => $payload['email'],
                        ]
                    );
                    $handler_result = $this->render_error($form_result->get_error_message(), $tip);
                } else {
                    // Extract data from FormService response
                    $message_id = $form_result['message_id'];
                    $receipt_token = $form_result['receipt_token'] ?? wp_generate_password(32, false);
                    $gdpr_delete_link = $form_result['gdpr_delete_link'] ?? '';
                    $submission_data = $form_result['payload'] ?? [];

                    // SUCCESS: Data is now safely in database with GDPR link
                    Logger::info(
                        'Submission saved with receipt token',
                        [
                            'receipt_token' => substr($receipt_token, 0, 8) . '...',
                            'email'         => $payload['email'],
                            'attachment'    => $attachment ?: 'none',
                            'has_gdpr_link'  => !empty($gdpr_delete_link),
                        ]
                    );

                    // Trigger AnalyticsHooks for webhook queueing
                    do_action('contactin_message_received', $message_id, $submission_data);

                    // Defer homework (email/CRM processing) to async hook to keep response fast
                    $contact_id = $form_result['contact_id'] ?? null;
                    if (!wp_next_scheduled('contactin_post_submit_homework', [$message_id, $contact_id])) {
                        wp_schedule_single_event(time(), 'contactin_post_submit_homework', [$message_id, $contact_id]);
                    }

                    // Nudge WP-Cron immediately; if disabled, run the hook inline as a fallback.
                    $spawned = spawn_cron();
                    if (!$spawned) {
                        do_action('contactin_post_submit_homework', $message_id, $contact_id);
                    }

                    // Verify file actually exists if attachment was provided
                    // Double-check that file wasn't deleted between finalize and save
                    if (!empty($attachment)) {
                        $full_path = WP_CONTENT_DIR . '/uploads/' . str_replace(WP_CONTENT_URL . '/uploads/', '', $attachment);
                        if (!file_exists($full_path)) {
                            Logger::warning(
                                'Attachment file verification failed',
                                [
                                    'path'  => $full_path,
                                    'email' => $payload['email'],
                                ]
                            );
                        }
                    }
                    
                    Logger::debug('Form submission completed with GDPR link', [
                        'message_id'      => $message_id,
                        'email'           => $payload['email'],
                        'has_gdpr_link'   => !empty($gdpr_delete_link),
                    ]);

                    // NOTE: Initial message statuses (email & CRM) are now set by FormService.submit()
                    // This ensures consistent status initialization across all entry points (AJAX, REST API, etc.)
                    // No need to set them here anymore

                    // Pass deletion link to success template
                    $handler_result = $this->render_success($receipt_token, $gdpr_delete_link, $settings, $attachment);
                }
            }

        } finally {
            // Always release the concurrency lock (Phase 2D)
            ConcurrencyManager::release_lock($lock_key, $lock_token);
            Logger::debug('Concurrency lock released', ['lock_key' => $lock_key]);

            // Record rate limit request for sliding window calculation (Phase 2D)
            RateLimiter::record_request($client_ip);
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
    private static function set_initial_message_statuses(array $email_data, array $settings, array $crm_settings): void {
        global $wpdb;
        $is_free = defined('CONTACTINBOX_IS_FREE') && CONTACTINBOX_IS_FREE;
        
        // Validate critical data before attempting anything
        if (empty($email_data['email']) || empty($email_data['name']) || empty($email_data['message_id'])) {
            Logger::warning('Invalid email data provided to set_initial_message_statuses', [
                'has_email' => !empty($email_data['email']),
                'has_name' => !empty($email_data['name']),
                'has_message_id' => !empty($email_data['message_id']),
            ]);
            return;
        }

        $message_id = $email_data['message_id'];
        $table = $wpdb->prefix . Config::TABLE_MESSAGES;
        $update_data = [];
        $update_format = [];
        $operations_pending = [];
        $operations_skipped = [];
        
        // Check if SMTP is enabled - if not, skip all email operations
        $smtp_enabled = !empty($settings['smtp_enable']);

        // Set admin email status based on SMTP and notification preference
        if ($smtp_enabled && !empty($settings['send_admin_notification'])) {
            $update_data['admin_email_status'] = Config::EMAIL_PENDING;
            $operations_pending[] = 'admin_email';
        } else {
            $update_data['admin_email_status'] = Config::EMAIL_SKIPPED;
            $operations_skipped[] = 'admin_email';
            if (!$smtp_enabled) {
                Logger::info('Admin email skipped - SMTP disabled');
            }
        }
        $update_format[] = '%s';

        // Set user email status based on SMTP and user copy preference
        if ($smtp_enabled && !empty($settings['send_user_copy'])) {
            $update_data['user_email_status'] = Config::EMAIL_PENDING;
            $operations_pending[] = 'user_email';
        } else {
            $update_data['user_email_status'] = Config::EMAIL_SKIPPED;
            $operations_skipped[] = 'user_email';
            if (!$smtp_enabled) {
                Logger::info('User email skipped - SMTP disabled');
            }
        }
        $update_format[] = '%s';

        // Set CRM status based on integration toggle
        if (!$is_free && !empty($crm_settings['crm_enabled'])) {
            $update_data['crm_status'] = Config::EMAIL_PENDING;
            $operations_pending[] = 'crm';
        } else {
            $update_data['crm_status'] = Config::EMAIL_SKIPPED;
            $operations_skipped[] = 'crm';
        }
        $update_format[] = '%s';

        // Update message statuses in database
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
        $result = $wpdb->update(
            $table,
            $update_data,
            ['id' => $message_id],
            $update_format,
            ['%d']
        );

        if ($result === false) {
            Logger::error('Failed to set initial message statuses', [
                'message_id' => $message_id,
                'error' => $wpdb->last_error,
            ]);
        } else {
            Logger::info('Initial message statuses initialized', [
                'message_id' => $message_id,
                'pending_operations' => $operations_pending,
                'skipped_operations' => $operations_skipped,
            ]);
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
    private function finalize_uploaded_file($temp_file_path, $file_id, $file_ext) {
        if (!is_dir(CONTACTINBOX_UPLOADS_PATH)) {
            wp_mkdir_p(CONTACTINBOX_UPLOADS_PATH);
        }

        $final_filename = $file_id . '.' . $file_ext;
        $final_path = CONTACTINBOX_UPLOADS_PATH . $final_filename;

        // Move file from temp to final location
        if (!$this->move_file($temp_file_path, $final_path)) {
            return new WP_Error(
                'file_move_failed',
                __('Failed to finalize file upload.', 'contact-inbox')
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
    private function handle_file_upload(array $file) {
        if (!function_exists('wp_handle_upload')) {
            require_once ABSPATH . 'wp-admin/includes/file.php';
        }

        // Get allowed file types from settings to ensure wp_handle_upload respects the admin configuration
        $settings = get_option(Config::OPTION_SETTINGS, []);
        $allowed_types = !empty($settings['allowed_file_types']) 
            ? array_map('trim', explode(',', strtolower($settings['allowed_file_types'])))
            : ['jpg', 'jpeg', 'png', 'pdf', 'doc', 'docx'];

        // Build MIME types map from allowed extensions
        // Important: WordPress may group multiple extensions in one MIME entry (e.g., "jpg|jpeg|jpe")
        // We need to split these so that only individually allowed extensions are accepted
        $wp_mimes = get_allowed_mime_types();
        $mimes_override = [];
        
        foreach ($wp_mimes as $ext_group => $mime_type) {
            // MIME types can have multiple extensions separated by |, e.g., "jpg|jpeg|jpe"
            $extensions = explode('|', $ext_group);
            $allowed_extensions_in_group = [];
            
            // Check which extensions in this group are allowed
            foreach ($extensions as $ext) {
                if (in_array(strtolower($ext), $allowed_types, true)) {
                    $allowed_extensions_in_group[] = $ext;
                }
            }
            
            // If this group has allowed extensions, add them individually to the override
            // This ensures that if only "jpg" is allowed but the group is "jpg|jpeg",
            // we don't accidentally allow "jpeg"
            if (!empty($allowed_extensions_in_group)) {
                // If all extensions in the group are allowed, keep the group as-is
                if (count($allowed_extensions_in_group) === count($extensions)) {
                    $mimes_override[$ext_group] = $mime_type;
                } else {
                    // Otherwise, create individual entries for only the allowed extensions
                    foreach ($allowed_extensions_in_group as $ext) {
                        $mimes_override[$ext] = $mime_type;
                    }
                }
            }
        }

        $overrides = [
            'test_form' => false,
            'mimes'     => !empty($mimes_override) ? $mimes_override : [
                'jpg|jpeg' => 'image/jpeg',
                'png'      => 'image/png',
                'pdf'      => 'application/pdf',
                'doc|docx' => 'application/msword',
            ],
        ];

        // Create a temporary $_FILES entry for wp_handle_upload
        $temp_file = [
            'name'     => $file['name'],
            'type'     => $file['type'],
            'tmp_name' => $file['tmp_name'],
            'error'    => 0,
            'size'     => $file['size'],
        ];

        $uploaded = $this->handle_upload($temp_file, $overrides);

        if (isset($uploaded['error'])) {
            return new WP_Error('upload_error', $uploaded['error']);
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
    private function get_attachment_info(string $attachment_path): array {
        if (empty($attachment_path)) {
            Logger::debug('Empty attachment path provided to get_attachment_info');
            return [];
        }

        // Use centralized helper for URL to path conversion and file info
        $file_info = \ContactInbox\Core\AttachmentHelper::get_file_info($attachment_path);
        
        if (!$file_info['exists']) {
            Logger::warning('Attachment file missing during success response', [ 
                'original_path' => $attachment_path,
                'resolved_path' => $file_info['path'],
                'reason' => 'File does not exist at expected location'
            ]);
            return [];
        }

        return [
            'name'       => $file_info['name'],
            'size'       => $file_info['size'],
            'size_bytes' => $file_info['size_bytes'],
            'exists'     => true,
        ];
    }

    /**
     * Format bytes into human-readable size (B, KB, MB, GB)
     *
     * @param int $bytes File size in bytes
     * @return string Formatted file size
     */
    private function format_file_size(int $bytes): string {
        $units = ['B', 'KB', 'MB', 'GB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        $bytes /= 1024 ** $pow;

        return round($bytes, 2) . ' ' . $units[$pow];
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
        $delete_link = $gdpr_delete_link; // Make available to template
        $masked_receipt = ReceiptTokenService::mask_token($receipt_token, 4); // Show partial token for display
        include CONTACTINBOX_PATH . Config::TEMPLATE_FRONTEND . 'form-success-message.php';
        $html = ob_get_clean();
        
        // GOLD STANDARD: Log successful submission with all tracking data
        Logger::info('Form submission completed successfully', [
            'receipt_token' => substr($receipt_token, 0, 8) . '***',
            'has_gdpr_link' => !empty($gdpr_delete_link),
            'has_attachment' => !empty($attachment),
            'confetti_enabled' => !empty($settings['confetti_enable']),
        ]);
        
        // Return success with data_saved flag and receipt token for user tracking
        wp_send_json_success([
            'html'           => $html,
            'data_saved'     => true, // Confirm data was saved to database
            'receipt_token'  => $receipt_token, // Full token for admin/later use
            'confetti'       => !empty($settings['confetti_enable']), // Add confetti flag for frontend
        ]);
    }

    /**
     * Render error template and exit
     */
    private function render_error(string $message, string $tip = '') {
        ob_start();
        $failure_message = $message;
        $failure_tip = $tip;
        include CONTACTINBOX_PATH . Config::TEMPLATE_FRONTEND . 'form-failure-message.php';
        $html = ob_get_clean();
        
        wp_send_json_error(['html' => $html]);
    }

    /**
     * Provide user-facing tips tailored to known error codes
     */
    private function map_failure_tip(string $error_code): string {
        switch ($error_code) {
            case 'duplicate_submission':
                return __('We detected this is the same message you just submitted. To prevent duplicate submissions, we\'ve rejected it. Please wait at least 30 seconds before submitting a different message, or modify this one before resubmitting.', 'contact-inbox');
            case 'missing_required_fields':
                return __('Please complete the required fields and submit again.', 'contact-inbox');
            case 'database_error':
            case 'transaction_error':
                return __('The database is currently busy. Please wait 30 seconds and try again. If this continues to happen, contact us with your email and the time you submitted.', 'contact-inbox');
            case 'database_locked':
                return __('Database is temporarily locked due to high traffic. Please wait about 30 seconds and try again. This usually resolves quickly.', 'contact-inbox');
            case 'database_deadlock':
                return __('We encountered a temporary database conflict. Please wait 30 seconds and try again. Your submission will not be duplicated.', 'contact-inbox');
            default:
                return __('Please try again. If this keeps happening, contact us and include your email and the time you submitted.', 'contact-inbox');
        }
    }

    /**
     * Async: Send admin notification email
     * Runs via loopback request after form submission completes
     */
    public static function async_send_admin_notification(array $email_data) {
        \ContactInbox\Core\SMTP::send_admin_notification($email_data);
    }

    /**
     * Async: Send user confirmation email
     * Runs via loopback request after form submission completes
     */
    public static function async_send_user_confirmation(array $email_data) {
        \ContactInbox\Core\SMTP::send_user_confirmation($email_data);
    }

    /**
     * Async: Send form data to CRM
     * Runs via loopback request after form submission completes
     */
    public static function async_send_crm(array $crm_data) {
        if ( defined('CONTACTINBOX_IS_FREE') && CONTACTINBOX_IS_FREE ) {
            return;
        }

        $response = CRMConnector::send($crm_data);
        
        if (WP_DEBUG) {
            if (is_wp_error($response)) {
                Logger::debug(
                    'CRM async dispatch failed',
                    [ 'error' => $response->get_error_message() ]
                );
            } else {
                Logger::debug(
                    'CRM async dispatch succeeded',
                    [ 'status' => $response['crm_status'] ?? 'unknown' ]
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
        if ( defined('CONTACTINBOX_IS_FREE') && CONTACTINBOX_IS_FREE ) {
            wp_send_json_error([
                'message' => __('File attachments are available in Contact Inbox Pro.', 'contact-inbox')
            ], 403);
        }

        // Check nonce for security
        $ajax_nonce = isset($_REQUEST['nonce']) ? sanitize_text_field(wp_unslash($_REQUEST['nonce'])) : '';
        if ('' === $ajax_nonce || !wp_verify_nonce($ajax_nonce, 'wp_rest')) {
            wp_send_json_error([
                'message' => __('Security check failed', 'contact-inbox')
            ], 403);
        }

        // Verify file was uploaded
        if (empty($_FILES['file']) || !is_array($_FILES['file'])) {
            wp_send_json_error([
                'message' => __('No file provided', 'contact-inbox')
            ], 400);
        }

        $file_name = isset($_FILES['file']['name']) ? sanitize_file_name(wp_unslash($_FILES['file']['name'])) : '';
        $file_type = isset($_FILES['file']['type']) ? sanitize_text_field(wp_unslash($_FILES['file']['type'])) : '';
        $file_tmp_name = isset($_FILES['file']['tmp_name']) ? sanitize_text_field(wp_unslash($_FILES['file']['tmp_name'])) : '';
        $file_error = isset($_FILES['file']['error']) ? absint($_FILES['file']['error']) : 1;
        $file_size = isset($_FILES['file']['size']) ? absint($_FILES['file']['size']) : 0;

        if ('' === $file_name || '' === $file_tmp_name || 0 !== $file_error) {
            wp_send_json_error([
                'message' => __('Invalid file upload payload', 'contact-inbox')
            ], 400);
        }

        $file = [
            'name'     => $file_name,
            'type'     => $file_type,
            'tmp_name' => $file_tmp_name,
            'error'    => $file_error,
            'size'     => $file_size,
        ];
        $settings = get_option(Config::OPTION_SETTINGS, []);
        
        // Check if attachments are enabled in form settings
        if (empty($settings['form_enable_attachment'])) {
            wp_send_json_error([
                'message' => __('File attachments are disabled', 'contact-inbox')
            ], 403);
        }

        // Rate limit check (per IP, doesn't block all uploads)
        $client_ip = Security::get_ip_address();
        $rate_limit = RateLimiter::check_rate_limit($client_ip);
        if (!$rate_limit['allowed']) {
            wp_send_json_error([
                'message' => __('Too many requests. Please wait and try again.', 'contact-inbox')
            ], 429);
        }

        // Validate file size
        $max_mb = absint($settings['max_file_size'] ?? 5);
        $max_bytes = max(1, $max_mb) * 1024 * 1024;
        
        if ($file['size'] > $max_bytes) {
            wp_send_json_error([
                'message' => sprintf(
                    __('File exceeds maximum size of %d MB', 'contact-inbox'),
                    $max_mb
                )
            ], 422);
        }

        // Validate file type against allowed extensions
        $allowed_types = array_map('trim', explode(',', strtolower($settings['allowed_file_types'] ?? 'jpg,png,pdf,doc,docx')));
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        
        if (!$ext || !in_array($ext, $allowed_types, true)) {
            wp_send_json_error([
                'message' => sprintf(
                    __('File type .%s is not allowed', 'contact-inbox'),
                    $ext ?: 'unknown'
                )
            ], 422);
        }

        // Create temp directory if it doesn't exist
        $upload_dir = wp_upload_dir();
        $temp_dir = $upload_dir['basedir'] . '/contactin-temp-uploads';

        if (!is_dir($temp_dir) && !wp_mkdir_p($temp_dir)) {
            wp_send_json_error([
                'message' => __('Failed to create upload directory', 'contact-inbox')
            ], 500);
        }

        // Generate unique filename with UUID (same as REST API controller)
        $original_name = sanitize_file_name($file['name']);
        $unique_id = wp_generate_uuid4();
        $new_filename = $unique_id . '.' . $ext;
        $file_path = $temp_dir . '/' . $new_filename;

        // Move file to temp directory
        if (!$this->move_file($file['tmp_name'], $file_path)) {
            Logger::error('File upload failed', [
                'temp_file' => $file['tmp_name'],
                'target_path' => $file_path,
                'temp_exists' => file_exists($file['tmp_name']) ? 'yes' : 'no',
            ]);
            
            wp_send_json_error([
                'message' => __('Failed to save uploaded file', 'contact-inbox')
            ], 500);
        }

        Logger::info('File uploaded successfully via AJAX', [
            'file_id' => $unique_id,
            'filename' => $original_name,
            'size' => filesize($file_path),
        ]);

        // Return success with file info (same format as REST API for compatibility)
        wp_send_json_success([
            'file_id' => $unique_id,
            'filename' => $original_name,
            'ext' => $ext,
        ], 200);
    }
    private function move_file(string $source, string $destination): bool {
        if (!file_exists($source)) {
            return false;
        }

        if (!function_exists('WP_Filesystem')) {
            require_once ABSPATH . 'wp-admin/includes/file.php';
        }

        WP_Filesystem();
        global $wp_filesystem;

        if (is_object($wp_filesystem) && method_exists($wp_filesystem, 'move')) {
            return (bool) $wp_filesystem->move($source, $destination, true);
        }

        return false;
    }
}
