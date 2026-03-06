<?php
/**
 * Base AJAX Handler
 *
 * Provides common functionality for all AJAX handlers.
 *
 * @package ContactInbox\Admin\AJAX
 */

declare(strict_types=1);

namespace ContactInbox\Admin\AJAX;

use ContactInbox\Core\Config;
use ContactInbox\Core\Repositories\AnalyticsRepository;

if (!defined('ABSPATH')) {
    exit;
}

abstract class BaseAJAXHandler {

    protected AnalyticsRepository $analytics;

    public function __construct(AnalyticsRepository $analytics = null) {
        $this->analytics = $analytics ?? new AnalyticsRepository();
    }

    private function post_text(string $key, string $default = ''): string {
        $value = filter_input(INPUT_POST, $key, FILTER_UNSAFE_RAW);
        if (null === $value || false === $value) {
            return $default;
        }
        return sanitize_text_field(wp_unslash((string) $value));
    }

    /**
     * Verify AJAX request and permissions
     */
    protected function verify(): void {
        check_ajax_referer('contactinbox_nonce_action', 'nonce');
        if (!current_user_can(Config::CAPABILITY)) {
            wp_send_json_error(['message' => __('Permission denied.', 'contact-inbox')]);
            exit;
        }
    }

    /**
     * Parse and validate date range from POST
     *
     * @return array ['days' => int, 'start_date' => string|null, 'end_date' => string|null]
     */
    protected function parse_date_range(): array {
        // Optional custom date range (preset dates)
        $start_date_raw = $this->post_text('start_date');
        $end_date_raw = $this->post_text('end_date');

        $start_date = (preg_match('/^\d{4}-\d{2}-\d{2}$/', $start_date_raw)) ? $start_date_raw : null;
        $end_date = (preg_match('/^\d{4}-\d{2}-\d{2}$/', $end_date_raw)) ? $end_date_raw : null;

        // If preset dates are provided, use them and ignore date_range
        if ($start_date && $end_date) {
            if (strtotime($start_date) > strtotime($end_date)) {
                wp_send_json_error(['message' => __('Invalid date range: start date must be before end date.', 'contact-inbox')]);
                exit;
            }
            return [
                'days' => 0,
                'start_date' => $start_date,
                'end_date' => $end_date,
            ];
        }

        // Fall back to days-based range
        $date_range = $this->post_text('date_range', '7');
        $days = absint($date_range) ?: 7;

        return [
            'days' => $days,
            'start_date' => null,
            'end_date' => null,
        ];
    }

    /**
     * Handle exceptions and return JSON error
     */
    protected function handle_error(\Exception $e): void {
        wp_send_json_error(['message' => $e->getMessage()]);
    }
}
