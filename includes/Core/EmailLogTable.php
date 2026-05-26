<?php
namespace ContactInbox\Core;

use WP_List_Table;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
// phpcs:disable WordPress.WP.I18n.NonSingularStringLiteralDomain, WordPress.Security.ValidatedSanitizedInput, WordPress.Security.NonceVerification

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class EmailLogTable extends WP_List_Table {

	/**
	 * Column headers property to avoid dynamic property deprecation.
	 *
	 * @var array
	 */
	protected $_column_headers = array();

	public function __construct() {
		parent::__construct(
			array(
				'singular' => 'email_log',
				'plural'   => 'email_logs',
				'ajax'     => false,
			)
		);
	}

	public function get_columns(): array {
		return array(
			'created_at'    => __( 'Timestamp', 'contactin' ),
			'recipient'     => __( 'Recipient', 'contactin' ),
			'subject'       => __( 'Subject', 'contactin' ),
			'status'        => __( 'Status', 'contactin' ),
			'error_message' => __( 'Error', 'contactin' ),
		);
	}

	protected function get_sortable_columns(): array {
		return array(
			'created_at' => array( 'created_at', true, null, null, 'desc' ),
			'recipient'  => array( 'recipient', false ),
			'subject'    => array( 'subject', false ),
			'status'     => array( 'status', false ),
		);
	}

	public function prepare_items(): void {
		// Support dynamic per_page from GET parameter (20, 50, 100)
		$per_page_options = array( 20, 50, 100 );
		$per_page         = absint( $_GET['per_page'] ?? 20 );
		if ( ! in_array( $per_page, $per_page_options, true ) ) {
			$per_page = 20;
		}

		$current_page = $this->get_pagenum();
		$offset       = ( $current_page - 1 ) * $per_page;

		$orderby         = sanitize_key( $_GET['orderby'] ?? '' ) ?: 'created_at';
		$requested_order = sanitize_text_field( $_GET['order'] ?? '' );
		$order           = strtoupper( $requested_order ) === 'ASC' ? 'ASC' : 'DESC';
		if ( empty( $_GET['orderby'] ) ) {
			$_GET['orderby'] = 'created_at';
		}
		if ( empty( $requested_order ) || ! in_array( $requested_order, array( 'ASC', 'DESC', 'asc', 'desc' ), true ) ) {
			$_GET['order'] = 'DESC';
		}
		$status = isset( $_GET['status'] ) && $_GET['status'] !== 'all' ? sanitize_text_field( $_GET['status'] ) : '';

		$total_items = EmailLog::count_logs( $status );
		$rows        = EmailLog::get_logs( $per_page, $offset, $status, $orderby, $order );

		$this->items = $rows;

		$this->_column_headers = array( $this->get_columns(), array(), $this->get_sortable_columns() );

		$this->set_pagination_args(
			array(
				'total_items' => $total_items,
				'per_page'    => $per_page,
				'total_pages' => max( 1, ceil( $total_items / $per_page ) ),
			)
		);
	}

	public function column_default( $item, $column_name ) {
		switch ( $column_name ) {
			case 'created_at':
				return esc_html( $item['created_at'] ?? '' );
			case 'recipient':
				return esc_html( $item['recipient'] ?? '' );
			case 'subject':
				return esc_html( $item['subject'] ?? '' );
			case 'status':
				return esc_html( $item['status'] ?? '' );
			case 'error_message':
				if ( empty( $item['error_message'] ) ) {
					return '<span class="cin-error-none">—</span>';
				}
				return sprintf(
					'<details class="cin-error-details"><summary>%s</summary><pre class="cin-error-text">%s</pre></details>',
					esc_html__( 'Error', 'contactin' ),
					esc_html( $item['error_message'] )
				);
			default:
				return '';
		}
	}

	/**
	 * Render custom controls in the top tablenav (filter + prune).
	 */
	protected function extra_tablenav( $which ) {
		if ( $which === 'top' ) {
			echo '<div class="alignleft actions">';

			// Status filter
			$current_status = $_GET['status'] ?? 'all';
			echo '<label for="status-filter" class="screen-reader-text">'
				. esc_html__( 'Filter by status', 'contactin' ) . '</label>';
			echo '<select id="status-filter" name="status">';
			echo '<option value="all"' . selected( $current_status, 'all', false ) . '>'
				. esc_html__( 'All Statuses', 'contactin' ) . '</option>';
			echo '<option value="sent"' . selected( $current_status, 'sent', false ) . '>'
				. esc_html__( 'Sent', 'contactin' ) . '</option>';
			echo '<option value="failed"' . selected( $current_status, 'failed', false ) . '>'
				. esc_html__( 'Failed', 'contactin' ) . '</option>';
			echo '<option value="pending"' . selected( $current_status, 'pending', false ) . '>'
				. esc_html__( 'Pending', 'contactin' ) . '</option>';
			echo '</select>';

			// Prune button
			echo '<button type="button" class="button button-secondary" id="contactin-prune-logs">'
				. esc_html__( 'Prune Old Logs', 'contactin' ) . '</button>';

			echo '</div>';
		}
	}

	/**
	 * Override tablenav: suppress default rendering
	 * We render everything in the template instead to avoid duplication
	 */
	protected function display_tablenav( $which ) {
		// Suppress tablenav - we're rendering everything in the template
		// This prevents duplicate filters/pagination
	}

	private function capture_bulk_actions( $which ) {
		ob_start();
		$this->bulk_actions( $which );
		return ob_get_clean();
	}

	private function capture_pagination_links() {
		ob_start();
		$this->pagination_links();
		return ob_get_clean();
	}
}
