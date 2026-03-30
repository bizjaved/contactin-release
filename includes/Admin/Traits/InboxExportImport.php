<?php
/**
 * Admin Trait – Inbox Export/Import
 *
 * Handles message export to CSV.
 * Responsibility: CSV generation and download.
 * All data fetching via CoreInbox.
 *
 * @package ContactIn\Admin\Traits
 * @since   1.0.0
 */

declare(strict_types=1);

namespace ContactInbox\Admin\Traits;

use ContactInbox\Core\Config;
use ContactInbox\Core\Inbox as CoreInbox;

// phpcs:disable WordPress.WP.I18n.NonSingularStringLiteralDomain, WordPress.WP.I18n.MissingTranslatorsComment, WordPress.Security.ValidatedSanitizedInput, WordPress.Security.NonceVerification, WordPress.Security.EscapeOutput.OutputNotEscaped, WordPress.WP.I18n.UnorderedPlaceholdersText, WordPress.WP.AlternativeFunctions.unlink_unlink, WordPress.WP.AlternativeFunctions.file_system_operations_fwrite, WordPress.WP.AlternativeFunctions.file_system_operations_is_writable, WordPress.WP.AlternativeFunctions.file_system_operations_fclose, WordPress.WP.AlternativeFunctions.rename_rename, WordPress.WP.AlternativeFunctions.file_system_operations_fopen, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber

if (!defined('ABSPATH')) {
    exit;
}

trait InboxExportImport {

    /**
     * AJAX handler: Export messages to CSV.
     * Applies filters (search, status, intent) to export only relevant messages.
     */
    public function ci_export_csv(): void {
        // Feature gating: CSV export is a premium feature
        if ( ! \ContactInbox\Integration\FreemiusIntegration::can_use_premium_features() ) {
            wp_die( esc_html__( 'This feature requires a Pro license.',  'contactin'), '', 403 );
        }

        // Security: nonce (wp_nonce_url adds _wpnonce parameter)
        if (!isset($_REQUEST['_wpnonce']) || !wp_verify_nonce($_REQUEST['_wpnonce'], Config::INBOX_NONCE_ACTION)) {
            wp_die(esc_html__('Security check failed.',  'contactin'), '', 403);
        }

        // Security: capability
        if (!current_user_can(Config::CAPABILITY)) {
            wp_die(esc_html__('Permission denied.',  'contactin'), '', 403);
        }

        // Sanitize and validate filters
        $search = sanitize_text_field($_GET['s'] ?? '');
        $status = sanitize_key($_GET['status'] ?? 'all');
        $intent = sanitize_key($_GET['intent'] ?? 'all');
        $contact_id = absint($_GET['contact_id'] ?? 0);
        $limit  = isset($_GET['limit']) ? max(1, min(absint($_GET['limit']), Config::EXPORT_LIMIT)) : Config::EXPORT_LIMIT;
        $batch  = isset($_GET['batch']) ? max(1, absint($_GET['batch'])) : 1;
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
            wp_die(esc_html__('No messages to export.',  'contactin'));
        }

        // Build CSV data
        $csv_data = $this->build_csv_data($messages);

        if (!$csv_data) {
            wp_die(esc_html__('Failed to generate CSV data.',  'contactin'));
        }

        // Generate filename with timestamp and batch info
        $filename = 'messages-export-' . gmdate('Y-m-d-His');
        if ($batch > 1 || !empty($_GET['total_batches'])) {
            $total_batches = absint($_GET['total_batches'] ?? 0);
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
        // Feature gating: CSV export is a premium feature
        if ( ! \ContactInbox\Integration\FreemiusIntegration::can_use_premium_features() ) {
            wp_send_json_error( [ 'message' => __( 'This feature requires a Pro license.',  'contactin') ] );
        }

        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], Config::INBOX_NONCE_ACTION)) {
            wp_send_json_error(['message' => __('Security check failed.',  'contactin')]);
        }

        if (!current_user_can(Config::CAPABILITY)) {
            wp_send_json_error(['message' => __('Permission denied.',  'contactin')]);
        }

        // Sanitize and validate filters
        $search = sanitize_text_field($_POST['s'] ?? '');
        $status = sanitize_key($_POST['status'] ?? 'all');
        $intent = sanitize_key($_POST['intent'] ?? 'all');
        $contact_id = absint($_POST['contact_id'] ?? 0);

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
            'message' => sprintf(__('Found %d messages. Export limit: %d per file.',  'contactin'), $total, $limit),
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
                !empty($msg->attachment) ? __('Yes',  'contactin') : __('No',  'contactin'),
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
            __('ID',  'contactin'),
            __('Name',  'contactin'),
            __('Email',  'contactin'),
            __('Message',  'contactin'),
            __('Status',  'contactin'),
            __('Submitted At',  'contactin'),
            __('IP Address',  'contactin'),
            __('Has Attachment',  'contactin'),
            __('Classification',  'contactin'),
            __('Admin Email Status',  'contactin'),
            __('User Email Status',  'contactin'),
            __('CRM Status',  'contactin'),
        ];
    }
}
