<?php
declare(strict_types=1);

namespace ContactInbox\Core\Repositories;

use ContactInbox\Core\Config;

if (!defined('ABSPATH')) exit;

/**
 * Submission Attempts Repository
 *
 * Handles logging of all form submission attempts (both successful and rejected)
 * for analytics and success rate calculation.
 */
class SubmissionAttemptsRepository {

    private string $table;

    public function __construct() {
        global $wpdb;
        $this->table = $wpdb->prefix . Config::TABLE_SUBMISSION_ATTEMPTS;
    }

    /**
     * Log a failed submission attempt
     * 
     * Note: Only failures are logged here. Successful submissions are already
     * saved to the messages table, so we don't duplicate that data.
     *
     * @param array $data {
     *     @type string $form_id Form identifier
     *     @type string $email Email address (optional, may not be available for rejected requests)
     *     @type string $ip_address Client IP address
     *     @type string $user_agent User agent string
     *     @type string $rejection_reason Reason for rejection (required)
     *     @type float $recaptcha_score reCAPTCHA score (if available)
     *     @type int $processing_time_ms Processing time in milliseconds
     * }
     * @return int|false Insert ID or false on failure
     */
    public function log_attempt(array $data) {
        global $wpdb;

        $defaults = [
            'form_id' => 'default',
            'email' => null,
            'ip_address' => '',
            'user_agent' => null,
            'rejection_reason' => 'unknown',
            'recaptcha_score' => null,
            'processing_time_ms' => null,
        ];

        $data = wp_parse_args($data, $defaults);

        $inserted = $wpdb->insert(
            $this->table,
            [
                'form_id' => sanitize_text_field($data['form_id']),
                'email' => !empty($data['email']) ? sanitize_email($data['email']) : null,
                'ip_address' => sanitize_text_field($data['ip_address']),
                'user_agent' => !empty($data['user_agent']) ? sanitize_text_field($data['user_agent']) : null,
                'rejection_reason' => sanitize_text_field($data['rejection_reason']),
                'recaptcha_score' => is_numeric($data['recaptcha_score']) ? (float)$data['recaptcha_score'] : null,
                'processing_time_ms' => is_numeric($data['processing_time_ms']) ? (int)$data['processing_time_ms'] : null,
                'created_at' => current_time('mysql'),
            ],
            [
                '%s', // form_id
                '%s', // email
                '%s', // ip_address
                '%s', // user_agent
                '%s', // rejection_reason
                '%f', // recaptcha_score
                '%d', // processing_time_ms
                '%s', // created_at
            ]
        );

        return $inserted ? $wpdb->insert_id : false;
    }

    /**
     * Get submission success rate for a date range
     * 
     * Calculates rate from:
     * - Successful submissions: messages table (already saved)
     * - Failed attempts: submission_attempts table (only failures logged)
     *
     * @param int $days Number of days to analyze
     * @return array ['total' => int, 'successful' => int, 'rejected' => int, 'rate' => float]
     */
    public function get_success_rate(int $days = 7, ?string $startDate = null, ?string $endDate = null): array {
        global $wpdb;

        $rejectedWhere = $startDate && $endDate
            ? $wpdb->prepare("DATE(created_at) BETWEEN %s AND %s", $startDate, $endDate)
            : $wpdb->prepare("DATE(created_at) >= DATE_SUB(NOW(), INTERVAL %d DAY)", $days);

        // Count rejected attempts (only failures are logged here)
        $rejected = (int)$wpdb->get_var(
            "SELECT COUNT(*) FROM {$this->table} 
             WHERE {$rejectedWhere}"
        );

        // Count successful submissions from messages table
        $messages_table = $wpdb->prefix . Config::TABLE_MESSAGES;
        $successWhere = $startDate && $endDate
            ? $wpdb->prepare("DATE(submitted_at) BETWEEN %s AND %s", $startDate, $endDate)
            : $wpdb->prepare("DATE(submitted_at) >= DATE_SUB(NOW(), INTERVAL %d DAY)", $days);

        $successful = (int)$wpdb->get_var(
            "SELECT COUNT(*) FROM {$messages_table} 
             WHERE {$successWhere}"
        );

        $total = $successful + $rejected;

        if ($total === 0) {
            return [
                'total' => 0,
                'successful' => 0,
                'rejected' => 0,
                'rate' => null,
            ];
        }

        $rate = round(($successful / $total) * 100, 1);

        return [
            'total' => $total,
            'successful' => $successful,
            'rejected' => $rejected,
            'rate' => $rate,
        ];
    }

    /**
     * Get rejection reasons breakdown
     *
     * @param int $days Number of days to analyze
     * @return array Array of ['reason' => string, 'count' => int]
     */
    public function get_rejection_reasons(int $days = 7, ?string $startDate = null, ?string $endDate = null): array {
        global $wpdb;

        $dateClause = $startDate && $endDate
            ? $wpdb->prepare("DATE(created_at) BETWEEN %s AND %s", $startDate, $endDate)
            : $wpdb->prepare("DATE(created_at) >= DATE_SUB(NOW(), INTERVAL %d DAY)", $days);

        $results = $wpdb->get_results(
            "SELECT rejection_reason, COUNT(*) as count
                 FROM {$this->table}
                 WHERE {$dateClause}
                 GROUP BY rejection_reason
                 ORDER BY count DESC",
            ARRAY_A
        );

        return $results ?: [];
    }

    /**
     * Clean up old attempt logs (for maintenance)
     *
     * @param int $days Keep only logs from last X days
     * @return int Number of rows deleted
     */
    public function cleanup_old_attempts(int $days = 90): int {
        global $wpdb;

        $deleted = $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM {$this->table} 
                 WHERE DATE(created_at) < DATE_SUB(NOW(), INTERVAL %d DAY)",
                $days
            )
        );

        return $deleted !== false ? (int)$deleted : 0;
    }
}
