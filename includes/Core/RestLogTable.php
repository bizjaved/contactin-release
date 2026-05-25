<?php
namespace ContactInbox\Core;

use ContactInbox\Core\Config;
use ContactInbox\Core\DB;
use WP_List_Table;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
// phpcs:disable WordPress.WP.I18n.NonSingularStringLiteralDomain, WordPress.Security.ValidatedSanitizedInput, WordPress.Security.NonceVerification

class RestLogTable extends WP_List_Table {

	protected $_column_headers = array();

	public function __construct() {
		parent::__construct(
			array(
				'singular' => 'rest_log',
				'plural'   => 'rest_logs',
				'ajax'     => false,
			)
		);
	}

	public function get_columns(): array {
		return array(
			'timestamp'     => __( 'Timestamp', 'contactin' ),
			'ip_address'    => __( 'IP', 'contactin' ),
			'http_method'   => __( 'Method', 'contactin' ),
			'endpoint'      => __( 'Endpoint', 'contactin' ),
			'response_code' => __( 'HTTP Code', 'contactin' ),
			'validated'     => __( 'Valid', 'contactin' ),
			'token_valid'   => __( 'Token', 'contactin' ),
			'error_message' => __( 'Error', 'contactin' ),
			'actions'       => __( 'Actions', 'contactin' ),
		);
	}

	protected function get_sortable_columns(): array {
		return array(
			'timestamp'     => array( 'timestamp', true, null, null, 'desc' ),
			'http_method'   => array( 'http_method', false ),
			'endpoint'      => array( 'endpoint', false ),
			'response_code' => array( 'response_code', false ),
		);
	}

	public function prepare_items(): void {
		// Support dynamic per_page from GET parameter (20, 50, 100)
		$per_page_options = array( 20, 50, 100 );
		$per_page         = absint( $_REQUEST['per_page'] ?? 20 );
		if ( ! in_array( $per_page, $per_page_options, true ) ) {
			$per_page = 20;
		}

		$current_page = $this->get_pagenum();
		$offset       = ( $current_page - 1 ) * $per_page;

		$orderby         = sanitize_key( $_REQUEST['orderby'] ?? '' ) ?: 'timestamp';
		$requested_order = sanitize_text_field( $_REQUEST['order'] ?? '' );
		$order           = strtoupper( $requested_order ) === 'ASC' ? 'ASC' : 'DESC';
		if ( empty( $_REQUEST['orderby'] ) ) {
			$_REQUEST['orderby'] = 'timestamp';
		}
		if ( empty( $requested_order ) || ! in_array( $requested_order, array( 'ASC', 'DESC', 'asc', 'desc' ), true ) ) {
			$_REQUEST['order'] = 'DESC';
		}

		$method    = isset( $_REQUEST['http_method'] ) && $_REQUEST['http_method'] !== 'all' ? sanitize_text_field( $_REQUEST['http_method'] ) : null;
		$endpoint  = isset( $_REQUEST['endpoint'] ) && $_REQUEST['endpoint'] !== 'all' ? sanitize_text_field( $_REQUEST['endpoint'] ) : null;
		$http_code = isset( $_REQUEST['http_code'] ) && $_REQUEST['http_code'] !== 'all' ? intval( $_REQUEST['http_code'] ) : null;
		$validated = isset( $_REQUEST['validated'] ) && $_REQUEST['validated'] !== 'all' ? intval( $_REQUEST['validated'] ) : null;

		$total_items = DB::instance()->count_rest_logs( $method, $endpoint, $http_code, $validated );

		$rows = DB::instance()->get_rest_logs(
			$per_page,
			$offset,
			$method,
			$endpoint,
			$http_code,
			$validated,
			$orderby,
			$order
		);

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
			case 'timestamp':
				return esc_html( $item['timestamp'] );
			case 'ip_address':
				return esc_html( $item['ip_address'] ?? '' );
			case 'http_method':
				return esc_html( $item['http_method'] ?? '' );
			case 'endpoint':
				return esc_html( $item['endpoint'] ?? '' );
			case 'response_code':
				return isset( $item['response_code'] ) ? (int) $item['response_code'] : '';
			case 'validated':
				return ! empty( $item['validated'] ) ? '✔' : '✖';
			case 'token_valid':
				return ! empty( $item['token_valid'] ) ? '✔' : '✖';
			case 'error_message':
				if ( empty( $item['error_message'] ) ) {
					return '<span class="cin-error-none">—</span>';
				}
				return sprintf( '<details class="cin-error-details"><summary>%s</summary><pre class="cin-error-text">%s</pre></details>', esc_html__( 'Error', 'contactin' ), esc_html( $item['error_message'] ) );
			case 'actions':
				$id    = (int) $item['id'];
				$nonce = wp_create_nonce( Config::SETTINGS_NONCE_ACTION );
				return sprintf(
					'<button type="button" class="button button-small contactin-view-log" data-id="%d" data-nonce="%s">%s</button>',
					$id,
					esc_attr( $nonce ),
					esc_html__( 'View details', 'contactin' )
				);
			default:
				return '';
		}
	}

	protected function display_tablenav( $which ) {
		// Suppress tablenav - we're rendering everything in the template
		// This prevents duplicate filters/pagination
	}
}
