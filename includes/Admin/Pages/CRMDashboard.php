<?php
/**
 * Contact Inbox – CRM Dashboard Admin Page
 *
 * Enterprise-Grade – Explicit, safe, modular, future-proof.
 *
 * Provides controller for rendering CRM monitoring dashboard.
 * Handles AJAX requests for dashboard data updates.
 *
 * @package ContactInbox\Admin\Pages
 */

declare(strict_types=1);

namespace ContactInbox\Admin\Pages;

use ContactInbox\Traits\Singleton;
use ContactInbox\Core\Config;
use ContactInbox\Core\CRMMonitor;
use ContactInbox\Core\QueueManager;
use ContactInbox\Core\Logger;
use ContactInbox\Core\Repositories\QueueRepository;
use ContactInbox\Core\Repositories\MessageRepository;

if (!defined('ABSPATH')) {
    exit;
}

final class CRMDashboard
{
    use Singleton;

    private QueueRepository $queue_repo;
    private MessageRepository $message_repo;

    /**
     * Constructor – register AJAX handlers.
     */
    protected function __construct()
    {
        $this->queue_repo = new QueueRepository();
        $this->message_repo = new MessageRepository();
        add_action('wp_ajax_ci_get_crm_stats', [$this, 'ajax_get_stats']);
        add_action('wp_ajax_ci_get_crm_logs', [$this, 'ajax_get_logs']);
        add_action('wp_ajax_ci_get_crm_endpoints', [$this, 'ajax_get_endpoints']);
        add_action('wp_ajax_ci_get_crm_daily_stats', [$this, 'ajax_get_daily_stats']);
        add_action('wp_ajax_ci_retry_failed_crm', [$this, 'ajax_retry_failed_crm']);
        add_action('wp_ajax_ci_retry_crm_message', [$this, 'ajax_retry_crm_message']);
    }

    /**
     * Render the CRM Dashboard page.
     */
    public static function render(): void
    {
        if (!current_user_can(Config::CAPABILITY)) {
            wp_die(esc_html__('You do not have sufficient permissions to access this page.', Config::TEXTDOMAIN));
        }

        // Get initial data for page load
        $statistics = CRMMonitor::get_statistics();
        $health = CRMMonitor::get_health_status();
        $endpoints = CRMMonitor::get_endpoints();
        $daily_stats = CRMMonitor::get_daily_stats(7);

        // Render template
        $template = CONTACTINBOX_PATH . Config::TEMPLATE_ADMIN . 'crm-dashboard-page.php';

        if (file_exists($template)) {
            load_template($template, false, [
                'statistics' => $statistics,
                'health'     => $health,
                'endpoints'  => $endpoints,
                'daily_stats' => $daily_stats,
            ]);
        } else {
            echo '<div class="notice notice-error"><p>'
                . esc_html__('CRM dashboard template not found.', Config::TEXTDOMAIN)
                . '</p></div>';
        }
    }

    /**
     * AJAX: Get CRM statistics.
     */
    public function ajax_get_stats(): void
    {
        $this->check_ajax_permission('contactinbox_nonce_action');

        $stats = CRMMonitor::get_statistics();
        $health = CRMMonitor::get_health_status();

        wp_send_json_success([
            'statistics' => $stats,
            'health'     => $health,
        ]);
    }

    /**
     * AJAX: Get CRM logs paginated.
     */
    public function ajax_get_logs(): void
    {
        $this->check_ajax_permission('contactin_nonce_action');

        $page = $this->validate_positive_int($_POST['page'] ?? 1, 1);
        $per_page = $this->validate_range($_POST['per_page'] ?? 20, 1, 100, 20);
        $status = $this->validate_string($_POST['status'] ?? 'all');
        $operation = $this->validate_string($_POST['operation'] ?? 'all');

        $result = CRMMonitor::get_logs_paginated($per_page, $page, $status, 'timestamp', 'DESC', $operation);

        wp_send_json_success($result);
    }

    /**
     * AJAX: Get CRM endpoints.
     */
    public function ajax_get_endpoints(): void
    {
        $this->check_ajax_permission('contactin_nonce_action');

        $endpoints = CRMMonitor::get_endpoints();

        wp_send_json_success(['endpoints' => $endpoints]);
    }

    /**
     * AJAX: Get daily statistics for charts.
     */
    public function ajax_get_daily_stats(): void
    {
        $this->check_ajax_permission('contactin_nonce_action');

        $days = $this->validate_range($_POST['days'] ?? 7, 1, 90, 7);

        $stats = CRMMonitor::get_daily_stats($days);

        wp_send_json_success(['daily_stats' => $stats]);
    }

    /**
     * AJAX: Retry all failed CRM syncs (bulk retry)
     * 
     * Gold Standard Implementation:
     * - Validates permissions and nonce
     * - Finds all failed CRM queue items
     * - Re-queues them with reset retry count
     * - Returns actionable feedback to user
     */
    public function ajax_retry_failed_crm(): void
    {
        $this->check_ajax_permission('contactin_nonce_action');

        $retried = $this->queue_repo->bulk_reset_to_pending('crm', ['dlq', 'retry']);

        if ($retried === 0) {
            wp_send_json_success([
                'message' => __('No failed CRM syncs found to retry.', Config::TEXTDOMAIN),
                'count' => 0
            ]);
            return;
        }

        Logger::info('Bulk CRM retry initiated', [
            'retried' => $retried,
            'user_id' => get_current_user_id()
        ]);

        wp_send_json_success([
            'message' => sprintf(
                _n(
                    '%d CRM sync queued for retry.',
                    '%d CRM syncs queued for retry.',
                    $retried,
                    Config::TEXTDOMAIN
                ),
                $retried
            ),
            'count' => $retried
        ]);
    }

    /**
     * AJAX: Retry CRM sync for a specific message
     * 
     * Gold Standard Implementation:
     * - Message-level granular retry control
     * - Validates message exists
     * - Creates new queue entry or resets existing
     * - Provides clear feedback
     */
    public function ajax_retry_crm_message(): void
    {
        $this->check_ajax_permission('contactin_nonce_action');

        $message_id = $this->validate_positive_int($_POST['message_id'] ?? 0);
        if (!$message_id) {
            wp_send_json_error(['message' => __('Invalid message ID.', Config::TEXTDOMAIN)]);
            return;
        }

        // Verify message exists using repository
        $message = $this->message_repo->get_by_id($message_id);

        if (!$message) {
            wp_send_json_error(['message' => __('Message not found.', Config::TEXTDOMAIN)]);
            return;
        }

        // Check if there's an existing queue item for this message
        $existing_queue = $this->queue_repo->get_latest_by_type_and_message('crm', (string)$message_id);

        if ($existing_queue) {
            // Reset existing queue item
            $updated = $this->queue_repo->reset_item_for_retry((int) $existing_queue['id']);
        } else {
            // Create new queue entry
            $queue_data = [
                'name' => $message->name,
                'email' => $message->email,
                'phone' => $message->phone ?? '',
                'message' => $message->message,
                'message_id' => $message_id
            ];

            if (!empty($message->attachment)) {
                $file_paths = \ContactInbox\Core\AttachmentHelper::extract_file_paths($message->attachment);
                $valid_paths = [];

                foreach ($file_paths as $file_path) {
                    if ($file_path && \ContactInbox\Core\AttachmentHelper::is_valid_file($file_path)) {
                        $valid_paths[] = $file_path;
                    }
                }

                if (!empty($valid_paths)) {
                    $queue_data['attachment'] = count($valid_paths) === 1 ? $valid_paths[0] : $valid_paths;
                }
            }

            $queue_id = QueueManager::push('crm', $queue_data, (string)$message_id, 2); // Priority 2 for retries
            
            if (is_wp_error($queue_id)) {
                wp_send_json_error(['message' => $queue_id->get_error_message()]);
                return;
            }
        }

        Logger::info('CRM retry initiated for message', [
            'message_id' => $message_id,
            'user_id' => get_current_user_id()
        ]);

        wp_send_json_success([
            'message' => __('CRM sync queued for retry. Check back in a few minutes.', Config::TEXTDOMAIN)
        ]);
    }

    /**
     * Helper: Check AJAX permissions
     *
     * @param string $nonce_action Nonce action name
     */
    private function check_ajax_permission(string $nonce_action): void
    {
        check_ajax_referer($nonce_action, 'nonce');
        if (!current_user_can(Config::CAPABILITY)) {
            wp_send_json_error(['message' => __('Permission denied.', Config::TEXTDOMAIN)]);
        }
    }

    /**
     * Helper: Validate positive integer
     *
     * @param mixed $value Input value
     * @param int $min Minimum allowed value (default 1)
     * @return int Validated integer or 0 if invalid
     */
    private function validate_positive_int($value, int $min = 1): int
    {
        $int = absint($value);
        return $int >= $min ? $int : 0;
    }

    /**
     * Helper: Validate integer within range
     *
     * @param mixed $value Input value
     * @param int $min Minimum allowed value
     * @param int $max Maximum allowed value
     * @param int $default Default value if out of range
     * @return int Validated integer
     */
    private function validate_range($value, int $min, int $max, int $default): int
    {
        $int = absint($value);
        if ($int < $min || $int > $max) {
            return $default;
        }
        return $int;
    }

    /**
     * Helper: Validate and sanitize string
     *
     * @param mixed $value Input value
     * @param int $max_length Maximum string length
     * @return string Sanitized string
     */
    private function validate_string($value, int $max_length = 100): string
    {
        $str = sanitize_text_field((string) $value);
        return substr($str, 0, $max_length);
    }

}
