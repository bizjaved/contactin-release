<?php
// phpcs:disable WordPress.Security.EscapeOutput.OutputNotEscaped
/**
 * Admin Trait – Inbox Export/Import
 *
 * Handles message export to CSV.
 * Responsibility: CSV generation and download.
 * All data fetching via CoreInbox.
 *
 * @package ContactInbox\Admin\Traits
 * @since   1.0.0
 */

declare(strict_types=1);

namespace ContactInbox\Admin\Traits;

use ContactInbox\Core\Config;
use ContactInbox\Core\Inbox as CoreInbox;

if (!defined('ABSPATH')) {
    exit;
}

trait InboxExportImport {

    private function request_text(string $key, string $default = ''): string {
        $value = filter_input(INPUT_GET, $key, FILTER_UNSAFE_RAW);
        if (null === $value || false === $value) {
            $value = filter_input(INPUT_POST, $key, FILTER_UNSAFE_RAW);
        }
        if (null === $value || false === $value) {
            return $default;
        }
        return sanitize_text_field(wp_unslash((string) $value));
    }

    private function request_key(string $key, string $default = ''): string {
        $value = filter_input(INPUT_GET, $key, FILTER_UNSAFE_RAW);
        if (null === $value || false === $value) {
            $value = filter_input(INPUT_POST, $key, FILTER_UNSAFE_RAW);
        }
        if (null === $value || false === $value) {
            return $default;
        }
        return sanitize_key(wp_unslash((string) $value));
    }

    private function request_int(string $key, int $default = 0): int {
        $value = filter_input(INPUT_GET, $key, FILTER_UNSAFE_RAW);
        if (null === $value || false === $value) {
            $value = filter_input(INPUT_POST, $key, FILTER_UNSAFE_RAW);
        }
        if (null === $value || false === $value || '' === $value) {
            return $default;
        }
        return absint(wp_unslash((string) $value));
    }

    /**
     * AJAX handler: Export messages to CSV.
     * Applies filters (search, status, intent) to export only relevant messages.
     */
    public function ci_export_csv(): void {
        // Security: nonce (wp_nonce_url adds _wpnonce parameter)
        $nonce = $this->request_text('_wpnonce');
        if ('' === $nonce || !wp_verify_nonce($nonce, Config::INBOX_NONCE_ACTION)) {
            wp_die(__('Security check failed.', 'contact-inbox'), '', 403);
        }

        // Security: capability
        if (!current_user_can(Config::CAPABILITY)) {
            wp_die(__('Permission denied.', 'contact-inbox'), '', 403);
        }

        // Sanitize and validate filters
        $search = $this->request_text('s');
        $status = $this->request_key('status', 'all');
        $intent = $this->request_key('intent', 'all');
        $contact_id = $this->request_int('contact_id', 0);
        $limit  = max(1, min($this->request_int('limit', Config::EXPORT_LIMIT), Config::EXPORT_LIMIT));
        $batch  = max(1, $this->request_int('batch', 1));
        $offset = ($batch - 1) * $limit;

        // Validate status against allowed values
        $allowed_statuses = ['all', Config::STATUS_READ, Config::STATUS_UNREAD, Config::STATUS_SPAM, Config::STATUS_ARCHIVED];
        if (!in_array($status, $allowed_statuses, true)) {
            $status = 'all';
        }

        // Validate intent against allowed categories
        $allowed_intents = ['all', 'sales', 'support', 'feedback', 'complaint', 'question', 'spam', 'unclassified'];
        if (!in_array($intent, $allowed_intents, true)) {
            $intent = 'all';
        }

        // Fetch messages via CoreInbox (not direct DB)
        $messages = CoreInbox::instance()->get_messages_for_export($search, $status, $limit, $offset, $contact_id ?: null, $intent !== 'all' ? $intent : null);

        if (empty($messages)) {
            wp_die(__('No messages to export.', 'contact-inbox'));
        }

        // Build CSV data
        $csv_data = $this->build_csv_data($messages);

        if (!$csv_data) {
            wp_die(__('Failed to generate CSV data.', 'contact-inbox'));
        }

        // Generate filename with timestamp and batch info
        $filename = 'messages-export-' . gmdate('Y-m-d-His');
        $total_batches = $this->request_int('total_batches', 0);
        if ($batch > 1 || $total_batches > 0) {
            $filename .= '-b' . $batch . ($total_batches ? '-of' . $total_batches : '');
        }
        $filename .= '.csv';

        // Set headers for file download
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Cache-Control: no-cache, no-store, must-revalidate');
        header('Pragma: no-cache');
        header('Expires: 0');

        // Output CSV data
        echo $csv_data;
        exit;
    }

    /**
     * AJAX handler: Export info (total, batches, limits) for client-side orchestration.
     */
    public function ci_export_info(): void {
        $nonce = $this->request_text('nonce');
        if ('' === $nonce || !wp_verify_nonce($nonce, Config::INBOX_NONCE_ACTION)) {
            wp_send_json_error(['message' => __('Security check failed.', 'contact-inbox')]);
        }

        if (!current_user_can(Config::CAPABILITY)) {
            wp_send_json_error(['message' => __('Permission denied.', 'contact-inbox')]);
        }

        // Sanitize and validate filters
        $search = $this->request_text('s');
        $status = $this->request_key('status', 'all');
        $intent = $this->request_key('intent', 'all');
        $contact_id = $this->request_int('contact_id', 0);

        // Validate status against allowed values
        $allowed_statuses = ['all', Config::STATUS_READ, Config::STATUS_UNREAD, Config::STATUS_SPAM, Config::STATUS_ARCHIVED];
        if (!in_array($status, $allowed_statuses, true)) {
            $status = 'all';
        }

        // Validate intent against allowed categories
        $allowed_intents = ['all', 'sales', 'support', 'feedback', 'complaint', 'question', 'spam', 'unclassified'];
        if (!in_array($intent, $allowed_intents, true)) {
            $intent = 'all';
        }

        $total   = CoreInbox::instance()->get_total_messages($search, $status, $contact_id ?: null, $intent !== 'all' ? $intent : null);
        $limit   = Config::EXPORT_LIMIT;
        $batches = (int) ceil(max(0, $total) / $limit);

        wp_send_json_success([
            'total'   => $total,
            'limit'   => $limit,
            'batches' => $batches,
            'message' => sprintf(__('Found %d messages. Export limit: %d per file.', 'contact-inbox'), $total, $limit),
        ]);
    }

    /**
     * Build CSV data from messages.
     * Returns CSV as string (can be written to file or sent as download).
     *
     * @param array $messages Message objects to export.
     * @return string CSV-formatted data, or empty string on error.
     */
    private function build_csv_data(array $messages): string {
        // Open temporary file handle for in-memory CSV
        $fh = fopen('php://temp', 'r+');
        if ($fh === false) {
            return '';
        }

        // Write headers
        $headers = $this->get_csv_headers();
        if (fputcsv($fh, $headers) === false) {
            fclose($fh);
            return '';
        }

        // Write each message row
        foreach ($messages as $msg) {
            // Admin email status
            $admin_email_status = $msg->admin_email_status ?? 'pending';
            
            // User email status
            $user_email_status = $msg->user_email_status ?? 'pending';
            
            // CRM status
            $crm_status = $msg->crm_status ?? 'pending';
            $crm_display = $crm_status === 'sent' ? 'synced' : $crm_status;
            
            // Intent Classification
            $intent_category = $msg->intent_category ?? 'unclassified';
            $intent_display = \ContactInbox\Core\IntentClassifier::get_category_label($intent_category);
            
            $row = [
                $msg->id,
                $msg->name,
                $msg->email,
                $msg->message,
                ucfirst($msg->status),
                $msg->submitted_at,
                $msg->ip_address ?? '',
                !empty($msg->attachment) ? __('Yes', 'contact-inbox') : __('No', 'contact-inbox'),
                $intent_display,
                ucfirst($admin_email_status),
                ucfirst($user_email_status),
                ucfirst($crm_display),
            ];

            if (fputcsv($fh, $row) === false) {
                fclose($fh);
                return '';
            }
        }

        // Read CSV from temp file
        rewind($fh);
        $csv = stream_get_contents($fh);
        fclose($fh);

        return $csv !== false ? $csv : '';
    }

    /**
     * Get CSV column headers.
     *
     * @return array Column headers for CSV export.
     */
    private function get_csv_headers(): array {
        return [
            __('ID', 'contact-inbox'),
            __('Name', 'contact-inbox'),
            __('Email', 'contact-inbox'),
            __('Message', 'contact-inbox'),
            __('Status', 'contact-inbox'),
            __('Submitted At', 'contact-inbox'),
            __('IP Address', 'contact-inbox'),
            __('Has Attachment', 'contact-inbox'),
            __('Classification', 'contact-inbox'),
            __('Admin Email Status', 'contact-inbox'),
            __('User Email Status', 'contact-inbox'),
            __('CRM Status', 'contact-inbox'),
        ];
    }
}
