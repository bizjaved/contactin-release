<?php
namespace ContactInbox\Core;

use ContactInbox\Core\Config;
use ContactInbox\Core\DB;
use WP_List_Table;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class RestLogTable extends WP_List_Table {

    protected $_column_headers = [];

    private function query_text(string $key, string $default = ''): string {
        $value = filter_input(INPUT_GET, $key, FILTER_UNSAFE_RAW);
        if (null === $value || false === $value) {
            return $default;
        }
        return sanitize_text_field(wp_unslash((string) $value));
    }

    private function query_key(string $key, string $default = ''): string {
        $value = filter_input(INPUT_GET, $key, FILTER_UNSAFE_RAW);
        if (null === $value || false === $value) {
            return $default;
        }
        return sanitize_key(wp_unslash((string) $value));
    }

    private function query_int(string $key, int $default = 0): int {
        $value = filter_input(INPUT_GET, $key, FILTER_UNSAFE_RAW);
        if (null === $value || false === $value || '' === $value) {
            return $default;
        }
        return absint(wp_unslash((string) $value));
    }

    public function __construct() {
        parent::__construct([
            'singular' => 'rest_log',
            'plural'   => 'rest_logs',
            'ajax'     => false,
        ]);
    }

    public function get_columns(): array {
        return [
            'timestamp'     => __( 'Timestamp', 'contact-inbox' ),
            'ip_address'    => __( 'IP', 'contact-inbox' ),
            'http_method'   => __( 'Method', 'contact-inbox' ),
            'endpoint'      => __( 'Endpoint', 'contact-inbox' ),
            'response_code' => __( 'HTTP Code', 'contact-inbox' ),
            'validated'     => __( 'Valid', 'contact-inbox' ),
            'token_valid'   => __( 'Token', 'contact-inbox' ),
            'error_message' => __( 'Error', 'contact-inbox' ),
            'actions'       => __( 'Actions', 'contact-inbox' ),
        ];
    }

    protected function get_sortable_columns(): array {
        return [
            'timestamp'     => [ 'timestamp', true, null, null, 'desc' ],
            'http_method'   => [ 'http_method', false ],
            'endpoint'      => [ 'endpoint', false ],
            'response_code' => [ 'response_code', false ],
        ];
    }

    public function prepare_items(): void {
        // Support dynamic per_page from GET parameter (20, 50, 100)
        $per_page_options = [20, 50, 100];
        $per_page = $this->query_int('per_page', 20);
        if (!in_array($per_page, $per_page_options)) {
            $per_page = 20;
        }
        
        $current_page = $this->get_pagenum();
        $offset       = ($current_page - 1) * $per_page;

        $orderby = $this->query_key('orderby', 'timestamp');
        if ('' === $orderby) {
            $orderby = 'timestamp';
        }
        $requested_order = $this->query_text('order');
        $order   = strtoupper($requested_order) === 'ASC' ? 'ASC' : 'DESC';

        $method_raw    = $this->query_text('http_method', 'all');
        $endpoint_raw  = $this->query_text('endpoint', 'all');
        $http_code_raw = $this->query_text('http_code', 'all');
        $validated_raw = $this->query_text('validated', 'all');

        $method    = 'all' !== strtolower($method_raw) ? $method_raw : null;
        $endpoint  = 'all' !== strtolower($endpoint_raw) ? $endpoint_raw : null;
        $http_code = 'all' !== strtolower($http_code_raw) ? intval($http_code_raw) : null;
        $validated = 'all' !== strtolower($validated_raw) ? intval($validated_raw) : null;

        $total_items = DB::instance()->count_rest_logs($method, $endpoint, $http_code, $validated);

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

        $this->_column_headers = [ $this->get_columns(), [], $this->get_sortable_columns() ];

        $this->set_pagination_args([
            'total_items' => $total_items,
            'per_page'    => $per_page,
            'total_pages' => max(1, ceil($total_items / $per_page)),
        ]);
    }

    public function column_default($item, $column_name) {
        switch ($column_name) {
            case 'timestamp':
                return esc_html($item['timestamp']);
            case 'ip_address':
                return esc_html($item['ip_address'] ?? '');
            case 'http_method':
                return esc_html($item['http_method'] ?? '');
            case 'endpoint':
                return esc_html($item['endpoint'] ?? '');
            case 'response_code':
                return isset($item['response_code']) ? (int) $item['response_code'] : '';
            case 'validated':
                return ! empty($item['validated']) ? '✔' : '✖';
            case 'token_valid':
                return ! empty($item['token_valid']) ? '✔' : '✖';
            case 'error_message':
                if (empty($item['error_message'])) {
                    return '<span class="cin-error-none">—</span>';
                }
                return sprintf('<details class="cin-error-details"><summary>%s</summary><pre class="cin-error-text">%s</pre></details>', esc_html__('Error', 'contact-inbox'), esc_html($item['error_message']));
            case 'actions':
                $id    = (int) $item['id'];
                $nonce = wp_create_nonce(Config::SETTINGS_NONCE_ACTION);
                return sprintf(
                    '<button type="button" class="button button-small contactin-view-log" data-id="%d" data-nonce="%s">%s</button>',
                    $id,
                    esc_attr($nonce),
                    esc_html__('View details', 'contact-inbox')
                );
            default:
                return '';
        }
    }

    protected function display_tablenav($which) {
        // Suppress tablenav - we're rendering everything in the template
        // This prevents duplicate filters/pagination
    }

    private function capture_bulk_actions($which) {
        ob_start();
        $this->bulk_actions($which);
        return ob_get_clean();
    }

    private function capture_pagination_links() {
        ob_start();
        $this->pagination_links();
        return ob_get_clean();
    }
}
