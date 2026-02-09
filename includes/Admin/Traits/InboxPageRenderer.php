<?php
/**
 * Admin Trait – Inbox Page Renderer
 *
 * Handles inbox list page display.
 * Responsibility: Sanitize filters, fetch data, pass to template.
 * NO HTML generation in this trait – all rendering in templates.
 *
 * @package ContactInbox\Admin\Traits
 * @since   1.0.0
 */

declare(strict_types=1);

namespace ContactInbox\Admin\Traits;

use ContactInbox\Core\Config;
use ContactInbox\Core\Repositories\MessageRepository;

if (!defined('ABSPATH')) {
    exit;
}

trait InboxPageRenderer {

    /**
     * Get MessageRepository instance (lazy initialization)
     */
    private function get_message_repo(): MessageRepository {
        static $repo = null;
        if ($repo === null) {
            $repo = new MessageRepository();
        }
        return $repo;
    }

    /**
    * Display the Inbox admin page.
     * Sanitizes filters, fetches data via DB, passes to template.
     */
    public function display_page(): void {
        // Capability check
        if (!current_user_can(Config::CAPABILITY)) {
            wp_die(esc_html__('You do not have sufficient permissions to access this page.', Config::TEXTDOMAIN));
        }

        // Sanitize and validate filters
        $filters = $this->sanitize_inbox_filters();

        // Enforce allowed per_page values server-side
        $per_page_options = [20, 50, 100];
        if ( ! in_array( $filters['per_page'], $per_page_options, true ) ) {
            $filters['per_page'] = Config::INBOX_PER_PAGE;
        }

        // Get totals for pagination
        $message_repo = $this->get_message_repo();
        $total_items  = $message_repo->count( $filters['search'], $filters['status'], $filters['contact_id'], $filters['intent'] );
        $unread_count = $message_repo->count( $filters['search'], 'unread', $filters['contact_id'], $filters['intent'] );

        // Fetch paginated data
        $messages = $message_repo->get_paginated(
            $filters['paged'],
            $filters['per_page'],
            $filters['search'],
            $filters['status'],
            $filters['orderby'],
            $filters['order'],
            $filters['contact_id'],
            $filters['intent']
        );
        $messages = $this->enrich_message_statuses($messages);

        // Hook: before render
        do_action('contact_inbox_pro_inbox_before_render');

        // Load template with data
        $template = CONTACTINBOX_PATH . Config::TEMPLATE_ADMIN . 'inbox-page.php';
        if (file_exists($template)) {
            // Calculate total pages based on total items and per_page
            $total_pages = max( 1, (int) ceil( $total_items / $filters['per_page'] ) );
            // Clamp current page to valid range
            $filters['paged'] = max( 1, min( $filters['paged'], $total_pages ) );

            $args = [
                'messages'     => $messages,
                'paged'        => $filters['paged'],
                'pages'        => $total_pages,
                'search'       => $filters['search'],
                'status'       => $filters['status'],
                'intent'       => $filters['intent'],
                'contact_id'   => $filters['contact_id'],
                'total_items'  => $total_items,
                'unread_count' => $unread_count,
                'orderby'      => $filters['orderby'],
                'order'        => $filters['order'],
                'per_page'     => $filters['per_page'],
            ];

            // Include template with arguments extracted into scope (WordPress load_template does not extract $args)
            if ( is_array( $args ) ) {
                // phpcs:ignore WordPress.PHP.DontExtract.extract_extract
                extract( $args, EXTR_SKIP );
            }
            include $template;
        } else {
            // Template error – let wp_die handle this
            wp_die(esc_html__('Inbox template not found.', Config::TEXTDOMAIN));
        }

        // Hook: after render
        do_action('contact_inbox_pro_inbox_after_render');
    }

    /**
     * Sanitize and validate inbox filter parameters.
     *
     * @return array {
     *     @type int    $paged       Current page number (1-based)
     *     @type string $search      Search term
     *     @type string $status      Filter by status (all, read, unread, spam, archived)
     *     @type string $intent      Filter by intent category
     *     @type int    $per_page    Items per page (20, 50, 100)
     *     @type string $orderby     Sort column
     *     @type string $order       Sort order (ASC, DESC)
     * }
     */
    private function sanitize_inbox_filters(): array {
        $search   = sanitize_text_field($_GET['s'] ?? '');
        $status   = sanitize_key($_GET['status'] ?? 'all');
        $intent   = sanitize_key($_GET['intent'] ?? 'all');
        $paged    = max(1, absint($_GET['paged'] ?? 1));
        $orderby  = sanitize_key($_GET['orderby'] ?? 'submitted_at');
        $order    = strtoupper(sanitize_key($_GET['order'] ?? 'DESC')) === 'ASC' ? 'ASC' : 'DESC';
        $contact_id = absint($_GET['contact_id'] ?? 0);

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

        // Validate orderby against allowed columns
        $allowed_columns = ['id', 'name', 'email', 'subject', 'message', 'status', 'submitted_at'];
        if (!in_array($orderby, $allowed_columns, true)) {
            $orderby = 'submitted_at';
        }

        // Validate per_page
        $per_page_options = [20, 50, 100];
        $per_page = absint($_GET['per_page'] ?? Config::INBOX_PER_PAGE);
        if (!in_array($per_page, $per_page_options, true)) {
            $per_page = Config::INBOX_PER_PAGE;
        }

        return [
            'paged'   => $paged,
            'search'  => $search,
            'status'  => $status,
            'intent'  => $intent,
            'per_page' => $per_page,
            'orderby' => $orderby,
            'order'   => $order,
            'contact_id' => $contact_id,
        ];
    }

    /**
     * Attach email and CRM status summaries to message entities for display.
     * Phase 1: Queue Redesign - Messages now have status directly from database.
     */
    private function enrich_message_statuses(array $messages): array {
        if (empty($messages)) {
            return $messages;
        }

        // Messages already have admin_email_status, user_email_status, crm_status
        // loaded from database in the Message entity constructor.
        // No additional enrichment needed - data is already available.
        // This method kept for backward compatibility.

        return $messages;
    }
}
