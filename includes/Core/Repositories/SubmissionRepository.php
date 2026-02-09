<?php
declare(strict_types=1);

namespace ContactInbox\Core\Repositories;

use ContactInbox\Core\Config;

/**
 * Submission Repository
 *
 * Handles all submission-related database operations including:
 * - Atomic transaction management
 * - Duplicate submission detection
 * - Submission receipt token generation
 * - Enhanced error logging
 *
 * @package ContactInbox\Core\Repositories
 */
final class SubmissionRepository {
    private string $table_messages;
    private string $table_submission_log;

    public function __construct() {
        global $wpdb;
        $this->table_messages = $wpdb->prefix . Config::TABLE_MESSAGES;
        $this->table_submission_log = $wpdb->prefix . Config::TABLE_SUBMISSION_LOG;
    }

    /**
     * Count identical submissions in a given time window (seconds)
     * Only counts PAST submissions within the window, not current one
     */
    public function countIdentical(array $data, int $windowSeconds): int {
        global $wpdb;
        $email = sanitize_email($data['email'] ?? '');
        $normalized_message = strtolower(trim($data['message'] ?? ''));
        $cutoff_time = date('Y-m-d H:i:s', strtotime(current_time('mysql') . " -{$windowSeconds} seconds"));
        
        // Only count submissions from the past (within the window), not including current time
        $query = $wpdb->prepare(
            "SELECT COUNT(*) FROM {$this->table_messages}
             WHERE email = %s
             AND LOWER(TRIM(message)) = %s
             AND submitted_at >= %s",
            $email,
            $normalized_message,
            $cutoff_time
        );
        return (int)$wpdb->get_var($query);
    }

    /**
     * Save submission with atomic transaction
     * Returns submission receipt token on success
     */
    public function save_atomic(array $data): array|\WP_Error {
        global $wpdb;
        try {
            $wpdb->query('START TRANSACTION');
            $receipt_token = $this->generate_receipt_token();
            if (!is_string($receipt_token) || $receipt_token === '') {
                $receipt_token = wp_generate_password(32, false);
            }
            if (empty($data['name']) || empty($data['email']) || empty($data['message'])) {
                $wpdb->query('ROLLBACK');
                return new \WP_Error('missing_required_fields', __('Missing required form fields.', Config::TEXTDOMAIN));
            }
            // NOTE: Duplicate check is done in FormHandler BEFORE calling save_atomic()
            // Removing duplicate check here to prevent false positives
            
            // DEBUG: Log what attachment data we received
            if (class_exists('\ContactInbox\Core\Logger')) {
                \ContactInbox\Core\Logger::info('SubmissionRepository: received data', [
                    'has_attachment_key' => isset($data['attachment']),
                    'attachment_value' => isset($data['attachment']) ? substr($data['attachment'], 0, 100) : 'not set',
                    'attachment_empty' => empty($data['attachment'])
                ]);
            }

            // Validate attachment data format before storage
            if (!empty($data['attachment'])) {
                $attachment = $data['attachment'];
                
                // Check if it looks like JSON
                if (is_string($attachment) && strlen($attachment) > 0 && ($attachment[0] === '{' || $attachment[0] === '[')) {
                    $decoded = json_decode($attachment, true);
                    if (json_last_error() !== JSON_ERROR_NONE) {
                        $wpdb->query('ROLLBACK');
                        \ContactInbox\Core\Logger::error('Invalid JSON in attachment field', [
                            'attachment_preview' => substr($attachment, 0, 100),
                            'json_error' => json_last_error_msg(),
                            'email' => $data['email'] ?? 'unknown'
                        ]);
                        return new \WP_Error('invalid_attachment', __('Attachment data is malformed. Please try again.', Config::TEXTDOMAIN));
                    }
                    
                    // If it's a JSON object (not array), ensure path key exists
                    if (is_array($decoded) && isset($decoded['name']) && !isset($decoded['path'])) {
                        $wpdb->query('ROLLBACK');
                        \ContactInbox\Core\Logger::error('Attachment JSON missing path key', [
                            'keys_present' => array_keys($decoded),
                            'email' => $data['email'] ?? 'unknown'
                        ]);
                        return new \WP_Error('invalid_attachment', __('Attachment data is incomplete. Please try again.', Config::TEXTDOMAIN));
                    }
                }
            }
            

            $prepared = [
                'form_id'           => sanitize_text_field($data['form_id'] ?? 'default'),
                'contact_id'        => isset($data['contact_id']) ? (int) $data['contact_id'] : null,
                'salutation'        => isset($data['salutation']) ? sanitize_text_field($data['salutation']) : null,
                'name'              => sanitize_text_field($data['name']),
                'email'             => sanitize_email($data['email']),
                'phone'             => isset($data['phone']) ? sanitize_text_field($data['phone']) : null,
                'mobile_phone'      => isset($data['mobile_phone']) ? sanitize_text_field($data['mobile_phone']) : null,
                'home_phone'        => isset($data['home_phone']) ? sanitize_text_field($data['home_phone']) : null,
                'other_phone'       => isset($data['other_phone']) ? sanitize_text_field($data['other_phone']) : null,
                'subject'           => isset($data['subject']) ? sanitize_text_field($data['subject']) : '',
                'message'           => wp_kses_post($data['message']),
                'attachment'        => isset($data['attachment']) ? $data['attachment'] : null,
                'consent'           => isset($data['consent']) ? (int)$data['consent'] : 0,
                'ip_address'        => $data['ip_address'] ?? $this->get_client_ip(),
                'user_agent'        => $data['user_agent'] ?? ($_SERVER['HTTP_USER_AGENT'] ?? ''),
                'recaptcha_score'   => isset($data['recaptcha_score']) ? (float)$data['recaptcha_score'] : null,
                'receipt_token'     => $receipt_token,
                'status'            => Config::STATUS_UNREAD,
                'submitted_at'      => current_time('mysql'),
            ];
            // Format must match $prepared array order exactly
            // form_id, contact_id, salutation, name, email, phone, mobile_phone, home_phone, other_phone, subject, message, attachment, consent, ip_address, user_agent, recaptcha_score, receipt_token, status, submitted_at
            $format = ['%s', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%s', '%s', '%f', '%s', '%s', '%s'];
            $result = false;
            for ($attempt = 0; $attempt < 3; $attempt++) {
                if ($attempt > 0) {
                    $prepared['receipt_token'] = $this->generate_receipt_token();
                    if (!is_string($prepared['receipt_token']) || $prepared['receipt_token'] === '') {
                        $prepared['receipt_token'] = wp_generate_password(32, false);
                    }
                }
                $result = $wpdb->insert($this->table_messages, $prepared, $format);
                if ($result) {
                    break;
                }
                $error_msg = $wpdb->last_error;
                $error_code = $this->detect_database_error($error_msg);
                if ($error_code !== 'unknown') {
                    $wpdb->query('ROLLBACK');
                    if ($error_code === 'database_locked' || $error_code === 'database_deadlock') {
                        return new \WP_Error($error_code, __('Database is busy. Please wait 30 seconds and try again.', Config::TEXTDOMAIN));
                    }
                }
                if (strpos($error_msg, 'receipt_token') === false) {
                    break;
                }
            }
            if (!$result) {
                $wpdb->query('ROLLBACK');
                $error_code = $this->detect_database_error($wpdb->last_error);
                if ($error_code === 'database_locked' || $error_code === 'database_deadlock') {
                    return new \WP_Error($error_code, __('Database is busy. Please wait 30 seconds and try again.', Config::TEXTDOMAIN));
                }
                return new \WP_Error('database_error', __('Failed to save submission to database.', Config::TEXTDOMAIN));
            }
            $message_id = $wpdb->insert_id;
            $this->log_submission_attempt($message_id, $data['email'], $prepared['ip_address']);
            $wpdb->query('COMMIT');

            return [
                'message_id'    => $message_id,
                'receipt_token' => $receipt_token,
            ];
        } catch (\Throwable $e) {
            $wpdb->query('ROLLBACK');
            $error_code = $this->detect_database_error($e->getMessage());
            if ($error_code === 'database_locked' || $error_code === 'database_deadlock') {
                return new \WP_Error($error_code, __('Database is busy. Please wait 30 seconds and try again.', Config::TEXTDOMAIN));
            }
            return new \WP_Error('transaction_error', __('An error occurred while saving your submission.', Config::TEXTDOMAIN));
        }
    }

    /**
     * Check if this is a duplicate submission (same email + message within time window)
     * Only checks PAST submissions, not the current one being processed
     */
    private function is_duplicate(array $data, int $seconds = 5): bool {
        global $wpdb;
        $email = sanitize_email($data['email'] ?? '');
        $normalized_message = strtolower(trim($data['message'] ?? ''));
        $cutoff_time = date('Y-m-d H:i:s', strtotime(current_time('mysql') . " -{$seconds} seconds"));
        
        // Query only looks back in time, never includes current submission
        $query = $wpdb->prepare(
            "SELECT COUNT(*) FROM {$this->table_messages}
             WHERE email = %s
             AND LOWER(TRIM(message)) = %s
             AND submitted_at >= %s",
            $email,
            $normalized_message,
            $cutoff_time
        );
        $count = (int)$wpdb->get_var($query);
        return $count > 0;
    }

    /**
     * Log submission attempt for deduplication tracking
     */
    private function log_submission_attempt(int $message_id, string $email, string $ip_address): bool {
        global $wpdb;
        $result = $wpdb->insert(
            $this->table_submission_log,
            [
                'message_id'     => $message_id,
                'email'          => $email,
                'ip_address'     => $ip_address,
                'attempted_at'   => current_time('mysql'),
                'status'         => 'completed',
            ],
            ['%d', '%s', '%s', '%s', '%s']
        );
        return (bool)$result;
    }

    /**
     * Generate unique receipt token for submission
     */
    private function generate_receipt_token(): string {
        return bin2hex(random_bytes(32));
    }

    /**
     * Get receipt info for tracking
     */
    public function get_receipt_info(string $receipt_token): ?array {
        global $wpdb;
        $query = $wpdb->prepare(
            "SELECT id, email, name, subject, submitted_at FROM {$this->table_messages} WHERE receipt_token = %s",
            $receipt_token
        );
        return $wpdb->get_row($query, ARRAY_A) ?: null;
    }

    /**
     * Get client IP address (with fallback options)
     */
    private function get_client_ip(): string {
        if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
            return sanitize_text_field($_SERVER['HTTP_CLIENT_IP']);
        } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $ips = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
            return sanitize_text_field(trim($ips[0]));
        } elseif (!empty($_SERVER['REMOTE_ADDR'])) {
            return sanitize_text_field($_SERVER['REMOTE_ADDR']);
        }
        return 'unknown';
    }

    /**
     * Detect database error type for user-friendly error messages
     */
    private function detect_database_error(string $error_message): string {
        $error_lower = strtolower($error_message);
        if (strpos($error_lower, 'database is locked') !== false || strpos($error_lower, 'general error: 2006') !== false || strpos($error_lower, 'mysql has gone away') !== false) {
            return 'database_locked';
        }
        if (strpos($error_lower, 'deadlock') !== false || strpos($error_lower, 'error 1213') !== false) {
            return 'database_deadlock';
        }
        if (strpos($error_lower, 'lock wait timeout exceeded') !== false || strpos($error_lower, 'error 1205') !== false) {
            return 'database_locked';
        }
        if (strpos($error_lower, 'table is locked') !== false) {
            return 'database_locked';
        }
        return 'unknown';
    }

    /**
     * Get the count of submissions made today.
     */
    public function get_submission_count_today(): int {
        global $wpdb;
        $today = current_time('Y-m-d');
        $query = $wpdb->prepare(
            "SELECT COUNT(*) FROM {$this->table_submission_log} WHERE DATE(attempted_at) = %s",
            $today
        );
        return (int)$wpdb->get_var($query);
    }

    /**
     * Get the count of successful submissions made today.
     */
    public function get_successful_submission_count_today(): int {
        global $wpdb;
        $today = current_time('Y-m-d');
        $query = $wpdb->prepare(
            "SELECT COUNT(*) FROM {$this->table_submission_log} WHERE DATE(completed_at) = %s",
            $today
        );
        return (int)$wpdb->get_var($query);
    }
}
