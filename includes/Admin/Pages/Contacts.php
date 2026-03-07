<?php
// phpcs:disable WordPress.Security.EscapeOutput.OutputNotEscaped
/**
 * Admin Page – Contacts
 */

declare(strict_types=1);

namespace ContactInbox\Admin\Pages;

use ContactInbox\Traits\Singleton;
use ContactInbox\Admin\Traits\ExportHelper;
use ContactInbox\Admin\Traits\ContactEditAjaxHandler;
use ContactInbox\Admin\Traits\ContactDeletionHandler;
use ContactInbox\Core\Config;
use ContactInbox\Core\Repositories\ContactRepository;
use ContactInbox\Core\Repositories\MessageRepository;
use ContactInbox\Core\PhoneUtils;

if (!defined('ABSPATH')) {
    exit;
}

final class Contacts {
    use Singleton;
    use ExportHelper;
    use ContactEditAjaxHandler;
    use ContactDeletionHandler;

    private ContactRepository $contact_repo;
    private MessageRepository $message_repo;

    protected function __construct() {
        $this->contact_repo = new ContactRepository();
        $this->message_repo = new MessageRepository();
        add_action('wp_ajax_contactinbox_contacts_export', [$this, 'export_csv']);
        add_action('wp_ajax_contactinbox_contacts_export_info', [$this, 'export_info']);
        add_action('wp_ajax_ci_get_contact_message_count', [$this, 'ci_get_contact_message_count']);
        add_action('wp_ajax_ci_delete_contact', [$this, 'ci_delete_contact']);
        $this->register_contact_edit_ajax();
    }

    private function query_arg_text(string $key, string $default = ''): string {
        $value = filter_input(INPUT_GET, $key, FILTER_UNSAFE_RAW);
        if (null === $value || false === $value) {
            return $default;
        }
        return sanitize_text_field(wp_unslash((string) $value));
    }

    private function query_arg_key(string $key, string $default = ''): string {
        $value = filter_input(INPUT_GET, $key, FILTER_UNSAFE_RAW);
        if (null === $value || false === $value) {
            return $default;
        }
        return sanitize_key(wp_unslash((string) $value));
    }

    private function query_arg_int(string $key, int $default = 0): int {
        $value = filter_input(INPUT_GET, $key, FILTER_UNSAFE_RAW);
        if (null === $value || false === $value || '' === $value) {
            return $default;
        }
        return absint(wp_unslash((string) $value));
    }

    private function post_arg_text(string $key, string $default = ''): string {
        $value = filter_input(INPUT_POST, $key, FILTER_UNSAFE_RAW);
        if (null === $value || false === $value) {
            return $default;
        }
        return sanitize_text_field(wp_unslash((string) $value));
    }

    public static function render(): void {
        if (!current_user_can(Config::CAPABILITY)) {
            wp_die(esc_html__('Permission denied.', 'contact-inbox'));
        }
        self::instance()->display();
    }

    private function display(): void {
        $contact_id = $this->query_arg_int('contact_id');
        if ($contact_id > 0) {
            $this->display_contact_detail($contact_id);
            return;
        }

        $this->display_contact_list();
    }

    private function display_contact_list(): void {
        $filters = $this->sanitize_filters();

        $total = $this->contact_repo->count($filters['search']);
        $contacts = $this->contact_repo->get_paginated(
            $filters['paged'],
            $filters['per_page'],
            $filters['search'],
            $filters['orderby'],
            $filters['order']
        );

        foreach ($contacts as $contact) {
            $contact->last_message_id = $this->message_repo->get_latest_message_id_by_contact($contact->id);
        }

        $template = CONTACTINBOX_PATH . Config::TEMPLATE_ADMIN . 'contacts-page.php';
        if (!file_exists($template)) {
            wp_die(esc_html__('Contacts template not found.', 'contact-inbox'));
        }

        $pages = max(1, (int) ceil($total / $filters['per_page']));
        $filters['paged'] = max(1, min($filters['paged'], $pages));

        // Make available in template scope
        $search = $filters['search'];
        $paged = $filters['paged'];
        $per_page = $filters['per_page'];
        $orderby = $filters['orderby'];
        $order = $filters['order'];
        $total_items = $total;
        $contacts_list = $contacts;
        $pages_count = $pages;

        include $template;
    }

    private function display_contact_detail(int $contact_id): void {
        $contact = $this->contact_repo->get_by_id($contact_id);

        if (!$contact) {
            wp_die(esc_html__('Contact not found.', 'contact-inbox'));
        }

        $filters = $this->sanitize_detail_filters();

        $total_messages = $this->message_repo->count(
            $filters['search'],
            $filters['status'],
            $contact_id
        );
        $pages = max(1, (int) ceil($total_messages / $filters['per_page']));
        $filters['paged'] = max(1, min($filters['paged'], $pages));

        $messages = $this->message_repo->get_paginated(
            $filters['paged'],
            $filters['per_page'],
            $filters['search'],
            $filters['status'],
            $filters['orderby'],
            $filters['order'],
            $contact_id
        );

        // Get unread message count for this contact
        $unread_count = $this->message_repo->count('', 'unread', $contact_id);

        $template = CONTACTINBOX_PATH . Config::TEMPLATE_ADMIN . 'contact-detail.php';
        if (!file_exists($template)) {
            wp_die(esc_html__('Contact detail template not found.', 'contact-inbox'));
        }

        $contact_item   = $contact;
        $messages_list  = $messages;
        $paged          = $filters['paged'];
        $per_page       = $filters['per_page'];
        $pages_count    = $pages;
        $total_items    = $total_messages;
        $search         = $filters['search'];
        $status         = $filters['status'];
        $orderby        = $filters['orderby'];
        $order          = $filters['order'];

        include $template;
    }

    private function sanitize_filters(): array {
        $search = $this->query_arg_text('s');
        $paged = max(1, $this->query_arg_int('paged', 1));
        $per_page = $this->query_arg_int('per_page', 20);
        $per_page = in_array($per_page, [20,50,100], true) ? $per_page : 20;
        $orderby = $this->query_arg_key('orderby', 'updated_at');
        $order = strtoupper($this->query_arg_key('order', 'DESC')) === 'ASC' ? 'ASC' : 'DESC';
        return compact('search','paged','per_page','orderby','order');
    }

    private function sanitize_detail_filters(): array {
        $search = $this->query_arg_text('s');
        $status = $this->query_arg_key('status', 'all');
        $paged = max(1, $this->query_arg_int('paged', 1));
        $per_page = $this->query_arg_int('per_page', 20);
        $per_page = in_array($per_page, [10,20,50], true) ? $per_page : 20;
        $orderby = $this->query_arg_key('orderby', 'submitted_at');
        $order = strtoupper($this->query_arg_key('order', 'DESC')) === 'ASC' ? 'ASC' : 'DESC';
        return compact('search','status','paged','per_page','orderby','order');
    }

    /**
     * AJAX handler: Export contacts to CSV with batching support.
     */
    public function export_csv(): void {
        // Security: nonce
        $nonce = '';
        $request_nonce = filter_input(INPUT_GET, '_wpnonce', FILTER_UNSAFE_RAW);
        if (null === $request_nonce || false === $request_nonce) {
            $request_nonce = filter_input(INPUT_POST, '_wpnonce', FILTER_UNSAFE_RAW);
        }
        if (null !== $request_nonce && false !== $request_nonce) {
            $nonce = sanitize_text_field(wp_unslash((string) $request_nonce));
        }
        if ('' === $nonce || !wp_verify_nonce($nonce, 'contactinbox_contacts_export')) {
            wp_die(__('Security check failed.', 'contact-inbox'), '', 403);
        }

        // Security: capability
        if (!current_user_can(Config::CAPABILITY)) {
            wp_die(__('Permission denied.', 'contact-inbox'), '', 403);
        }

        // Get filters and batching parameters
        $search = $this->query_arg_text('s');
        $orderby = $this->query_arg_key('orderby', 'updated_at');
        $order = strtoupper($this->query_arg_key('order', 'DESC')) === 'ASC' ? 'ASC' : 'DESC';
        
        // Batching parameters
        $limit = max(1, min($this->query_arg_int('limit', 1000), 1000));
        $batch = max(1, $this->query_arg_int('batch', 1));
        $total_batches = $this->query_arg_int('total_batches', 0);

        // Get contacts for this batch
        $contacts = $this->contact_repo->get_paginated($batch, $limit, $search, $orderby, $order);

        if (empty($contacts)) {
            wp_die(__('No contacts to export.', 'contact-inbox'));
        }

        // Prepare CSV data
        $rows = [];
        foreach ($contacts as $contact) {
            // Format each phone type separately
            $primary_phone = $contact->primary_phone 
                ? PhoneUtils::format($contact->primary_phone, 'international') 
                : '';
            $mobile_phone = $contact->mobile_phone 
                ? PhoneUtils::format($contact->mobile_phone, 'international') 
                : '';
            $home_phone = $contact->home_phone 
                ? PhoneUtils::format($contact->home_phone, 'international') 
                : '';
            $other_phone = $contact->other_phone 
                ? PhoneUtils::format($contact->other_phone, 'international') 
                : '';

            // Convert last activity to WordPress timezone
            $last_activity = $contact->last_message_at 
                ? get_date_from_gmt($contact->last_message_at, 'Y-m-d H:i:s')
                : '';

            $rows[] = [
                $contact->id,
                $contact->salutation ?? '',
                $contact->name,
                $contact->email ?? '',
                $primary_phone,
                $mobile_phone,
                $home_phone,
                $other_phone,
                $contact->source ?? '',
                $last_activity,
                $contact->created_at ? get_date_from_gmt($contact->created_at, 'Y-m-d H:i:s') : '',
                $contact->updated_at ? get_date_from_gmt($contact->updated_at, 'Y-m-d H:i:s') : '',
            ];
        }

        // Define headers
        $headers = [
            __('ID', 'contact-inbox'),
            __('Salutation', 'contact-inbox'),
            __('Name', 'contact-inbox'),
            __('Email', 'contact-inbox'),
            __('Primary Phone', 'contact-inbox'),
            __('Mobile Phone', 'contact-inbox'),
            __('Home Phone', 'contact-inbox'),
            __('Other Phone', 'contact-inbox'),
            __('Source', 'contact-inbox'),
            __('Last Activity', 'contact-inbox'),
            __('Created', 'contact-inbox'),
            __('Updated', 'contact-inbox'),
        ];

        // Build CSV using ExportHelper trait
        $csv = $this->build_csv_data($rows, $headers);

        if (!$csv) {
            wp_die(__('Failed to generate CSV data.', 'contact-inbox'));
        }

        // Generate filename with batch info
        $filename = $this->get_export_filename('contacts', $batch, $total_batches);

        // Send CSV download
        $this->send_csv_download($csv, $filename);
    }

    /**
     * AJAX handler: Export info (total, batches, limit) for client-side orchestration.
     */
    public function export_info(): void {
        // Security: nonce
        $nonce = $this->post_arg_text('nonce');
        if ('' === $nonce || !wp_verify_nonce($nonce, 'contactinbox_contacts_export')) {
            wp_send_json_error(['message' => __('Security check failed.', 'contact-inbox')]);
        }

        // Security: capability
        if (!current_user_can(Config::CAPABILITY)) {
            wp_send_json_error(['message' => __('Permission denied.', 'contact-inbox')]);
        }

        // Get filters
        $search = $this->post_arg_text('s');

        // Get total count
        $total = $this->contact_repo->count($search);
        $limit = 1000; // Max records per batch
        $batches = (int) ceil(max(0, $total) / $limit);

        wp_send_json_success([
            'total' => $total,
            'limit' => $limit,
            'batches' => $batches,
            'message' => sprintf(
                /* translators: 1: contacts found, 2: export limit per file. */
                __('Found %1$d contacts. Export limit: %2$d per file.', 'contact-inbox'),
                $total,
                $limit
            ),
        ]);
    }
}
