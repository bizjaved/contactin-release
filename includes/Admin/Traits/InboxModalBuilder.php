<?php
/**
 * Admin Trait – Inbox Modal Builder
 *
 * Handles message modal data structure and HTML rendering.
 * Responsibility: Build modal context, render modal template.
 * NO HTML hardcoding – all rendering in templates.
 *
 * @package ContactIn\Admin\Traits
 * @since   1.0.0
 */

declare(strict_types=1);

namespace ContactInbox\Admin\Traits;

use ContactInbox\Core\Config;

if (!defined('ABSPATH')) {
    exit;
}

trait InboxModalBuilder {

    /**
     * Build message modal data structure.
     * Prepares all data needed for modal rendering.
     * NO HTML generation – just data organization.
     *
     * @param object $message      Message object from DB.
     * @param int    $index        Current message index (0-based).
     * @param int    $total        Total number of messages.
     * @param bool   $has_prev     Whether previous message exists.
     * @param bool   $has_next     Whether next message exists.
     * @param string $search       Current search term (for context).
     * @param string $status       Current status filter (for context).
     *
     * @return array Modal data array.
     */
    private function build_message_modal_data(
        object $message,
        int $index,
        int $total,
        bool $has_prev,
        bool $has_next,
        string $search = '',
        string $status = 'all'
    ): array {
        return [
            // Message entity
            'message'       => $message,

            // Navigation context
            'index'         => $index,
            'total'         => $total,
            'has_prev'      => $has_prev,
            'has_next'      => $has_next,

            // Filter context (for maintaining state)
            'search'        => $search,
            'status'        => $status,

            // Additional fields
            'subject'       => $message->subject ?? '',
        ];
    }

    /**
     * Render message modal HTML.
     * Loads template, passes data, returns rendered HTML.
     * Template handles all markup – NO HTML in this trait.
     *
     * @param array $data Modal data array (from build_message_modal_data).
     * @return string Rendered HTML.
     */
    private function render_modal_html(array $data): string {
        ob_start();

        // Extract message and navigation data
        $message   = $data['message'] ?? null;
        $index     = $data['index'] ?? 0;
        $total     = $data['total'] ?? 0;
        $has_prev  = $data['has_prev'] ?? false;
        $has_next  = $data['has_next'] ?? false;
        $search    = $data['search'] ?? '';
        $status    = $data['status'] ?? 'all';

        // Extract individual message fields for template
        if ($message) {
            $id             = (int) $message->id;
            $name           = $message->name ?: '—';
            $email          = $message->email ?: '—';
            $subject        = $message->subject ?: '—';
            $body           = $message->message ?: '—';
            $date           = $message->submitted_at
                ? date_i18n('M j, Y @ g:i A', strtotime($message->submitted_at))
                : '—';
            $message_status = $message->status ?: 'read';
            $ip_address     = $message->ip_address ?? '';
            
            // Handle attachment
            $attachment = method_exists($message, 'get_attachment') ? $message->get_attachment() : null;
        } else {
            $id = $name = $email = $subject = $body = $date = $message_status = $ip_address = '';
            $attachment = null;
        }

        // Template expects these exact variable names
        $s             = $search;
        $filter_status = $status;
        $status        = $message_status; // Override status with message status for template

        // Load template (template handles all HTML markup)
        $template = CONTACTINBOX_ADMIN_PARTIALS . 'message-view-modal.php';
        if (file_exists($template)) {
            require $template;
        }

        $html = ob_get_clean();
        return $html !== false ? $html : '';
    }

    /**
     * Build AJAX response for modal display.
     * Structures response for JavaScript consumption.
     *
     * @param array  $data Modal data (from build_message_modal_data).
     * @param string $html Rendered modal HTML (from render_modal_html).
     *
     * @return array AJAX response array.
     */
    private function build_modal_response(array $data, string $html): array {
        $message = $data['message'] ?? null;

        return [
            'id'               => $message ? (int) $message->id : 0,
            'index'            => $data['index'] ?? 0,
            'total'            => $data['total'] ?? 1,
            'has_prev'         => $data['has_prev'] ?? false,
            'has_next'         => $data['has_next'] ?? false,
            'status'           => $message ? $message->status : 'read',
            'subject'          => $message ? $message->subject : '',
            'search'           => $data['search'] ?? '',
            'status_filter'    => $data['status'] ?? 'all',
            'html'             => $html,
        ];
    }
}
