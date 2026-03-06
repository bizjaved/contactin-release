<?php
/**
 * Health Metrics AJAX Handler
 *
 * @package ContactInbox\Admin\AJAX
 */

declare(strict_types=1);

namespace ContactInbox\Admin\AJAX;

if (!defined('ABSPATH')) {
    exit;
}

class HealthMetricsHandler extends BaseAJAXHandler {

    public function handle(): void {
        $this->verify();

        try {
            $days_input = filter_input(INPUT_POST, 'days', FILTER_SANITIZE_NUMBER_INT);
            $days = is_scalar($days_input) ? absint((string) $days_input) : 30;

            $email_health = $this->analytics->get_email_delivery_health($days);
            $crm_health = $this->analytics->get_crm_sync_health($days);
            $spam_intelligence = $this->analytics->get_spam_intelligence($days);

            wp_send_json_success([
                'email_health' => $email_health,
                'crm_health' => $crm_health,
                'spam_intelligence' => $spam_intelligence,
            ]);
        } catch (\Exception $e) {
            $this->handle_error($e);
        }
    }
}
