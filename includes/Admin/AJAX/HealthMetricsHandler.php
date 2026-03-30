<?php
/**
 * Health Metrics AJAX Handler
 *
 * @package ContactIn\Admin\AJAX
 */

declare(strict_types=1);

namespace ContactInbox\Admin\AJAX;

if (!defined('ABSPATH')) {
    exit;
}

class HealthMetricsHandler extends BaseAJAXHandler {

    public function handle(): void {
        check_ajax_referer('contactinbox_nonce_action', 'nonce');
        $this->verify();

        try {
            $days = isset($_POST['days']) ? absint($_POST['days']) : 30;

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
