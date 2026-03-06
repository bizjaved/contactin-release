<?php

namespace ContactInbox\Core;

use ContactInbox\Core\Config;

if ( ! class_exists( 'WP_List_Table' ) ) {
    require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

class ContactIN_Inbox_Table extends \WP_List_Table {
    /** @var array */
    public array $messages = [];
    /** @var int Total items from DB (for pagination) */
    public int $total_items_db = 0;

    public function __construct() {
        parent::__construct([
            'singular' => 'message',
            'plural'   => 'messages',
            'ajax'     => true,
        ]);
    }

    public function get_columns(): array {
        return [
            'cb'         => '<input type="checkbox" />',
            'name'       => __( 'Name', 'contact-inbox' ),
            'email'      => __( 'Email', 'contact-inbox' ),
            'phone'      => __( 'Phone', 'contact-inbox' ),
            'subject'    => __( 'Subject', 'contact-inbox' ),   // ← NEW
            'message'    => __( 'Message', 'contact-inbox' ),
            'attachment' => __( 'Attachment', 'contact-inbox' ),
            'status'     => __( 'Status', 'contact-inbox' ),
            'date'       => __( 'Date', 'contact-inbox' ),
            'actions'    => __( 'Actions', 'contact-inbox' ),
        ];
    }

    public function get_sortable_columns(): array {
        return [
            'name'    => ['name', false],
            'email'   => ['email', false],
            'subject' => ['subject', false],
            'date'    => ['submitted_at', true], // true for descending default
        ];
    }


    public function single_row( $item ) {
        $msg = $item;
        include CONTACTINBOX_PATH . 'templates/admin/partials/inbox-row.php';
    }

    public function prepare_items(): void {
        $columns  = $this->get_columns();
        $hidden   = [];
        $sortable = $this->get_sortable_columns();
        $this->_column_headers = [ $columns, $hidden, $sortable ];

        // Bridge messages → items safely
        $this->items = is_array($this->messages) ? $this->messages : [];

        // Get per_page from request, default to 20
        $per_page_options = [20, 50, 100];
        $per_page_input = filter_input(INPUT_GET, 'per_page', FILTER_SANITIZE_NUMBER_INT);
        $per_page = is_scalar($per_page_input) ? absint((string) $per_page_input) : 20;
        if (!in_array($per_page, $per_page_options, true)) {
            $per_page = 20;
        }

        // Use total from DB (set by caller), not just current page items count
        $total_items = $this->total_items_db > 0 ? $this->total_items_db : count($this->items);
        $total_pages = $per_page ? (int) ceil($total_items / $per_page) : 1;

        $this->set_pagination_args([
            'total_items' => $total_items,
            'per_page'    => $per_page,
            'total_pages' => max(1, $total_pages),
        ]);
    }

    /**
     * Set total items count from DB (for pagination calc, not just current page items)
     */
    public function set_total_items_from_db(int $total): void {
        $this->total_items_db = $total;
    }

    public function get_bulk_actions() {
        return [
            'mark_read'   => __( 'Mark as Read', 'contact-inbox' ),
            'mark_unread' => __( 'Mark as Unread', 'contact-inbox' ),
            'delete'      => __( 'Delete', 'contact-inbox' ),
        ];
    }

    protected function display_tablenav( $which ) {
        if ( $which === 'top' ) {
            // Suppress the default top nav (we render our own in the template)
            return;
        }

    }
}
