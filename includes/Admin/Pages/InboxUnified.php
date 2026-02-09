<?php
/**
 * Admin Page – Unified Inbox
 *
 * Aggregated inbox page with tabs for Main, Spam, and Archives.
 * Keeps individual inbox pages intact.
 *
 * @package ContactInbox\Admin\Pages
 * @since   1.0.0
 */

declare(strict_types=1);

namespace ContactInbox\Admin\Pages;

use ContactInbox\Core\Config;
use ContactInbox\Core\Repositories\MessageRepository;
use ContactInbox\Traits\Singleton;

if (!defined('ABSPATH')) {
    exit;
}

final class InboxUnified {
    use Singleton;

    private MessageRepository $message_repo;

    private function __construct() {
        $this->message_repo = new MessageRepository();
    }

    /**
     * Static entry point: Render the unified inbox page.
     */
    public static function render(): void {
        if (!current_user_can(Config::CAPABILITY)) {
            wp_die(esc_html__('Permission denied.', Config::TEXTDOMAIN));
        }
        self::instance()->display_page();
    }

    /**
     * Display the unified inbox page.
     */
    public function display_page(): void {
        // Sanitize and validate filters
        $filters = $this->sanitize_inbox_filters();

        // Enforce allowed per_page values server-side
        $per_page_options = [20, 50, 100];
        if (!in_array($filters['per_page'], $per_page_options, true)) {
            $filters['per_page'] = Config::INBOX_PER_PAGE;
        }

        // Get total messages in database (unfiltered)
        $total_database_messages = $this->message_repo->count('', 'all', 0);

        // Get totals for pagination (based on current folder/status/intent)
        $total_items = $this->message_repo->count($filters['search'], $filters['status'], $filters['contact_id'], $filters['intent']);

        // Get unread count for current folder (use 'unread' status for main folder, or the folder status for spam/archived)
        $unread_status = ($filters['folder'] === 'main') ? 'unread' : $filters['status'];
        $unread_count = $this->message_repo->count('', $unread_status, $filters['contact_id'], 'all');

        // Fetch paginated data
        $messages = $this->message_repo->get_paginated(
            $filters['paged'],
            $filters['per_page'],
            $filters['search'],
            $filters['status'],
            $filters['orderby'],
            $filters['order'],
            $filters['contact_id'],
            $filters['intent']
        );

        // Hook: before render
        do_action('contact_inbox_pro_inbox_before_render');

        // Load template with data
        $template = CONTACTINBOX_PATH . Config::TEMPLATE_ADMIN . 'inbox-consolidated.php';
        if (file_exists($template)) {
            // Calculate total pages based on total items and per_page
            $total_pages = max(1, (int) ceil($total_items / $filters['per_page']));
            // Clamp current page to valid range
            $filters['paged'] = max(1, min($filters['paged'], $total_pages));

            $args = [
                'messages'     => $messages,
                'paged'        => $filters['paged'],
                'pages'        => $total_pages,
                'search'       => $filters['search'],
                'status'       => $filters['status'],
                'folder'       => $filters['folder'],
                'intent'       => $filters['intent'],
                'contact_id'   => $filters['contact_id'],
                'total_items'  => $total_items,
                'total_database_messages' => $total_database_messages,
                'unread_count' => $unread_count,
                'orderby'      => $filters['orderby'],
                'order'        => $filters['order'],
                'per_page'     => $filters['per_page'],
            ];

            // Include template with arguments extracted into scope
            if (is_array($args)) {
                // phpcs:ignore WordPress.PHP.DontExtract.extract_extract
                extract($args, EXTR_SKIP);
            }
            include $template;
        } else {
            wp_die(esc_html__('Unified inbox template not found.', Config::TEXTDOMAIN));
        }

        // Hook: after render
        do_action('contact_inbox_pro_inbox_after_render');
    }

    /**
     * Sanitize and validate inbox filter parameters for unified view.
     */
    private function sanitize_inbox_filters(): array {
        $search     = sanitize_text_field($_GET['s'] ?? '');
        $folder_raw = sanitize_key($_GET['folder'] ?? 'main');
        $folder     = in_array($folder_raw, ['main', 'spam', 'archived'], true) ? $folder_raw : 'main';
        $paged      = max(1, absint($_GET['paged'] ?? 1));
        $orderby    = sanitize_key($_GET['orderby'] ?? 'submitted_at');
        $order      = strtoupper(sanitize_key($_GET['order'] ?? 'DESC')) === 'ASC' ? 'ASC' : 'DESC';
        $contact_id = absint($_GET['contact_id'] ?? 0);
        $intent     = sanitize_key($_GET['intent'] ?? 'all');

        // Validate per_page
        $per_page_options = [20, 50, 100];
        $per_page = absint($_GET['per_page'] ?? Config::INBOX_PER_PAGE);
        if (!in_array($per_page, $per_page_options, true)) {
            $per_page = Config::INBOX_PER_PAGE;
        }

        // Map folder to status filter
        $status = sanitize_key($_GET['status'] ?? 'all');
        if ($folder === 'spam') {
            $status = Config::STATUS_SPAM;
        } elseif ($folder === 'archived') {
            $status = Config::STATUS_ARCHIVED;
        } else {
            // Main folder: allow all/read/unread only
            if (!in_array($status, ['all', Config::STATUS_READ, Config::STATUS_UNREAD], true)) {
                $status = 'all';
            }
        }

        return [
            'paged'      => $paged,
            'search'     => $search,
            'status'     => $status,
            'folder'     => $folder,
            'per_page'   => $per_page,
            'orderby'    => $orderby,
            'order'      => $order,
            'contact_id' => $contact_id,
            'intent'     => $intent,
        ];
    }
}
