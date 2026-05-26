<?php

namespace ContactInbox\Core;


if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// phpcs:disable WordPress.WP.I18n.TextDomainMismatch, WordPress.WP.I18n.NonSingularStringLiteralDomain, WordPress.Security.NonceVerification.Recommended

if ( ! class_exists( 'WP_List_Table' ) ) {
	require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

class ContactIN_Inbox_Table extends \WP_List_Table {
	/** @var array */
	public array $messages = array();
	/** @var int Total items from DB (for pagination) */
	public int $total_items_db = 0;

	public function __construct() {
		parent::__construct(
			array(
				'singular' => 'message',
				'plural'   => 'messages',
				'ajax'     => true,
			)
		);
	}

	public function get_columns(): array {
		return array(
			'cb'         => '<input type="checkbox" />',
			'name'       => __( 'Name', 'contactin' ),
			'email'      => __( 'Email', 'contactin' ),
			'phone'      => __( 'Phone', 'contactin' ),
			'subject'    => __( 'Subject', 'contactin' ),   // ← NEW
			'message'    => __( 'Message', 'contactin' ),
			'attachment' => __( 'Attachment', 'contactin' ),
			'status'     => __( 'Status', 'contactin' ),
			'date'       => __( 'Date', 'contactin' ),
			'actions'    => __( 'Actions', 'contactin' ),
		);
	}

	public function get_sortable_columns(): array {
		return array(
			'name'    => array( 'name', false ),
			'email'   => array( 'email', false ),
			'subject' => array( 'subject', false ),
			'date'    => array( 'submitted_at', true ), // true for descending default
		);
	}


	public function single_row( $item ) {
		$msg = $item;
		include CONTACTINBOX_PATH . 'templates/admin/partials/inbox-row.php';
	}

	public function prepare_items(): void {
		$columns               = $this->get_columns();
		$hidden                = array();
		$sortable              = $this->get_sortable_columns();
		$this->_column_headers = array( $columns, $hidden, $sortable );

		// Bridge messages → items safely
		$this->items = is_array( $this->messages ) ? $this->messages : array();

		// Get per_page from request, default to 20
		$per_page_options = array( 20, 50, 100 );
		$per_page         = absint( $_GET['per_page'] ?? 20 );
		if ( ! in_array( $per_page, $per_page_options, true ) ) {
			$per_page = 20;
		}

		// Use total from DB (set by caller), not just current page items count
		$total_items = $this->total_items_db > 0 ? $this->total_items_db : count( $this->items );
		$total_pages = $per_page ? (int) ceil( $total_items / $per_page ) : 1;

		$this->set_pagination_args(
			array(
				'total_items' => $total_items,
				'per_page'    => $per_page,
				'total_pages' => max( 1, $total_pages ),
			)
		);
	}

	/**
	 * Set total items count from DB (for pagination calc, not just current page items)
	 */
	public function set_total_items_from_db( int $total ): void {
		$this->total_items_db = $total;
	}

	public function get_bulk_actions() {
		return array(
			'mark_read'   => __( 'Mark as Read', 'contactin' ),
			'mark_unread' => __( 'Mark as Unread', 'contactin' ),
			'delete'      => __( 'Delete', 'contactin' ),
		);
	}

	protected function display_tablenav( $which ) {
		if ( $which === 'top' ) {
			// Suppress the default top nav (we render our own in the template)
			return;
		}
	}
}
