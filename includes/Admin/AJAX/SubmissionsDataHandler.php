<?php
/**
 * Submissions Data AJAX Handler
 *
 * @package ContactIn\Admin\AJAX
 */

declare(strict_types=1);

namespace ContactInbox\Admin\AJAX;

use ContactInbox\Core\Config;

if (!defined('ABSPATH')) {
    exit;
}

class SubmissionsDataHandler extends BaseAJAXHandler {

    public function handle(): void {
        $this->verify();

        try {
            $date_range = $this->parse_date_range();
            $days = $date_range['days'];
            $start_date = $date_range['start_date'];
            $end_date = $date_range['end_date'];

            $trend_raw = $this->analytics->get_daily_submission_trend($days, $start_date, $end_date);
            $status = $this->analytics->get_submission_status_breakdown($days, $start_date, $end_date);
            // Use CRM log table for CRM sync rate and counts
            $crm_stats = $this->analytics->get_crm_sync_stats($days, $start_date, $end_date);
            $success_rates = $this->analytics->get_queue_success_rates_by_type($days, $start_date, $end_date);
            $success_rates['crm'] = $crm_stats['rate'];

            // Add CRM sync counts for dashboard cards
            $crm_counts = [
                'total' => $crm_stats['total'],
                'successful' => $crm_stats['successful'],
                'failed' => $crm_stats['failed'],
            ];

            // Format trend data for Chart.js with date labels
            $trend = $this->format_trend($trend_raw, $days, $start_date, $end_date);

            // Map repository status keys (unread, read, archived) to UI keys (completed, pending, failed)
            $status_mapped = [
                'completed' => $status['read'] ?? 0,      // Read = Completed
                'pending' => $status['unread'] ?? 0,      // Unread = Pending
                'failed' => $status['archived'] ?? 0,     // Archived = Failed
            ];

            // Get submission acceptance rate
            $acceptance_rate = $this->analytics->get_submission_acceptance_rate($days, $start_date, $end_date);

            // Get rejection reasons breakdown
            $rejection_reasons_raw = $this->analytics->get_rejection_reasons($days, $start_date, $end_date);
            $rejection_reasons = [
                'recaptcha_failed' => 0,
                'validation_failed' => 0,
                'rate_limited' => 0,
                'nonce_failed' => 0,
            ];
            $reason_map = [
                'recaptcha_missing' => 'recaptcha_failed',
                'honeypot_failed' => 'recaptcha_failed',
                'invalid_name_format' => 'validation_failed',
                'consent_required' => 'validation_failed',
                'unknown' => 'validation_failed',
            ];
            foreach ($rejection_reasons_raw as $reason) {
                $key = $reason['rejection_reason'] ?? '';
                if ($key === '') {
                    continue;
                }
                $bucket = $reason_map[$key] ?? $key;
                if (!isset($rejection_reasons[$bucket])) {
                    $bucket = 'validation_failed';
                }
                $rejection_reasons[$bucket] += (int) $reason['count'];
            }

            // Calculate total spam blocked (reCAPTCHA failures + classifier-tagged spam)
            $classifier_spam = $this->analytics->get_classifier_spam_count($days, $start_date, $end_date);
            $spam_blocked = $rejection_reasons['recaptcha_failed'] + $classifier_spam;

            $response_data = [
                'trend' => $trend,
                'status' => $status_mapped,
                'success_rates' => $success_rates,
                'crm_counts' => $crm_counts,
                'acceptance_rate' => $acceptance_rate,
                'rejection_reasons' => $rejection_reasons,
                'spam_blocked' => $spam_blocked,
                'classifier_spam' => $classifier_spam,
            ];

            wp_send_json_success($response_data);
        } catch (\Exception $e) {
            $this->handle_error($e);
        }
    }

    /**
     * Format trend data for Chart.js
     */
    private function format_trend(array $trend_raw, int $days, ?string $start_date, ?string $end_date): array {
        $trend = [];
        if ($start_date && $end_date) {
            $period = new \DatePeriod(
                new \DateTime($start_date),
                new \DateInterval('P1D'),
                (new \DateTime($end_date))->modify('+1 day')
            );
            $index = 0;
            foreach ($period as $date) {
                $trend[] = [
                    'date' => $date->format('M d'),
                    'count' => $trend_raw[$index] ?? 0,
                ];
                $index++;
            }
        } else {
            for ($i = 0; $i < count($trend_raw); $i++) {
                $date = gmdate('M d', strtotime("-" . ($days - 1 - $i) . " days"));
                $trend[] = [
                    'date' => $date,
                    'count' => $trend_raw[$i],
                ];
            }
        }
        return $trend;
    }
}
