<?php
namespace ContactInbox\Core;

/**
 * Suppress default WP_List_Table tablenav (pagination/filter UI)
 */
// (Method will be placed inside the class below)

use ContactInbox\Core\Config;
use ContactInbox\Core\Repositories\CRMRepository;
use WP_List_Table;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class CRMLogTable extends WP_List_Table {
        /**
         * Suppress default WP_List_Table tablenav (pagination/filter UI)
         */
        protected function display_tablenav($which) {
            // Suppress tablenav - we render all controls in the template
        }
    protected $_column_headers = [];

    public function __construct() {
        parent::__construct([
            'singular' => 'crm_log',
            'plural'   => 'crm_logs',
            'ajax'     => false,
        ]);
    }

    public function get_columns(): array {
        return [
            'created_at'    => __( 'Timestamp', Config::TEXTDOMAIN ),
            'message_id'    => __( 'Message ID', Config::TEXTDOMAIN ),
            'crm_system'    => __( 'CRM System', Config::TEXTDOMAIN ),
            'operation'     => __( 'Operation', Config::TEXTDOMAIN ),
            'crm_id'        => __( 'CRM Record ID', Config::TEXTDOMAIN ),
            'status'        => __( 'Status', Config::TEXTDOMAIN ),
            'response'      => __( 'Response', Config::TEXTDOMAIN ),
            'error_message' => __( 'Error', Config::TEXTDOMAIN ),
        ];
    }

    protected function get_sortable_columns(): array {
        return [
            'created_at' => [ 'created_at', true, null, null, 'desc' ],
            'message_id' => [ 'message_id', false ],
            'crm_system' => [ 'crm_system', false ],
            'operation'  => [ 'operation', false ],
            'status'     => [ 'status', false ],
        ];
    }

    public function prepare_items(): void {
        $per_page_options = [20, 50, 100];
        $per_page = absint($_REQUEST['per_page'] ?? 20);
        if (!in_array($per_page, $per_page_options)) {
            $per_page = 20;
        }
        $current_page = $this->get_pagenum();
        $offset       = ($current_page - 1) * $per_page;
        $orderby = sanitize_key($_REQUEST['orderby'] ?? '') ?: 'created_at';
        $requested_order = sanitize_text_field($_REQUEST['order'] ?? '');
        $order   = strtoupper($requested_order) === 'ASC' ? 'ASC' : 'DESC';
        if (empty($_REQUEST['orderby'])) {
            $_REQUEST['orderby'] = 'created_at';
        }
        if (empty($requested_order) || !in_array($requested_order, ['ASC', 'DESC', 'asc', 'desc'], true)) {
            $_REQUEST['order'] = 'DESC';
        }
        $status  = isset($_REQUEST['status']) ? sanitize_text_field($_REQUEST['status']) : 'all';
        $operation = isset($_REQUEST['operation']) ? sanitize_text_field($_REQUEST['operation']) : 'all';

        $repo = new CRMRepository();
        $total_items = $repo->count_logs($status, $operation);
        $rows        = $repo->get_logs($per_page, $offset, $orderby, $order, $status, $operation);


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
            case 'created_at':
                return esc_html($item['created_at'] ?? '');
            case 'message_id':
                return esc_html($item['message_id'] ?? '');
            case 'crm_system':
                return esc_html($item['crm_system'] ?? '');
            case 'operation':
                return esc_html($item['operation'] ?? '');
            case 'crm_id':
                return esc_html($item['crm_id'] ?? '');
            case 'status':
                $status = $item['status'] ?? '';
                $response_data = json_decode($item['response'] ?? '{}', true);
                
                // Gold Standard: Show actual API response status instead of sync/unsynced
                // Parse response for HTTP codes and success indicators
                $http_code = null;
                $api_success = null;
                
                // Check if response contains HTTP code or success flag
                if (is_array($response_data)) {
                    // Direct response body with success flag (e.g., {"id":"...","success":true})
                    if (isset($response_data['success'])) {
                        $api_success = (bool)$response_data['success'];
                    }
                    
                    // Response wrapped with response_body
                    if (isset($response_data['response_body'])) {
                        $body = is_string($response_data['response_body']) 
                            ? json_decode($response_data['response_body'], true) 
                            : $response_data['response_body'];
                        if (is_array($body) && isset($body['success'])) {
                            $api_success = (bool)$body['success'];
                        }
                    }
                    
                    // HTTP code from response metadata
                    if (isset($response_data['http_code'])) {
                        $http_code = (int)$response_data['http_code'];
                    }
                }
                
                // Determine display based on status and response analysis
                $display_class = 'cin-status-neutral';
                $display_icon = '•';
                $display_text = ucfirst($status);
                
                if ($status === 'delivered' || $api_success === true || in_array($http_code, [200, 201, 204], true)) {
                    $display_class = 'cin-status-success';
                    $display_icon = '✓';
                    $display_text = __('Success', Config::TEXTDOMAIN);
                } elseif ($status === 'failed' || $status === 'rejected' || $status === 'server_error' || $api_success === false) {
                    $display_class = 'cin-status-error';
                    $display_icon = '✗';
                    $display_text = __('Error', Config::TEXTDOMAIN);
                } elseif ($status === 'pending' || $status === 'processing') {
                    $display_class = 'cin-status-pending';
                    $display_icon = '⏳';
                    $display_text = __('Pending', Config::TEXTDOMAIN);
                } elseif ($status === 'rate_limited') {
                    $display_class = 'cin-status-warning';
                    $display_icon = '⚠';
                    $display_text = __('Rate Limited', Config::TEXTDOMAIN);
                } elseif ($status === 'invalid_token') {
                    $display_class = 'cin-status-error';
                    $display_icon = '🔒';
                    $display_text = __('Auth Failed', Config::TEXTDOMAIN);
                }
                
                // Add HTTP code if available
                $status_detail = $http_code ? " ({$http_code})" : '';
                
                return sprintf(
                    '<span class="%s">%s %s%s</span>',
                    esc_attr($display_class),
                    esc_html($display_icon),
                    esc_html($display_text),
                    esc_html($status_detail)
                );
            case 'response':
                if (empty($item['response'])) {
                    return '<span class="cin-response-none">—</span>';
                }

                $decoded = json_decode($item['response'], true);
                $summary = '';

                if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                    $operation = $item['operation'] ?? '';

                    if ($operation === 'sync') {
                        // Use actual database columns for file tracking
                        $files_queued = isset($item['files_queued']) ? (int)$item['files_queued'] : 0;
                        $queued_filenames_text = isset($item['queued_filenames']) ? trim($item['queued_filenames']) : '';
                        $queued_names = $queued_filenames_text !== '' ? explode(', ', $queued_filenames_text) : [];

                        $name_preview = $queued_names;
                        $extra_count = 0;
                        if (count($name_preview) > 3) {
                            $extra_count = count($name_preview) - 3;
                            $name_preview = array_slice($name_preview, 0, 3);
                        }

                        $names_text = $name_preview ? implode(', ', $name_preview) : __('None', Config::TEXTDOMAIN);
                        if ($extra_count > 0) {
                            $names_text .= sprintf(__(' (+%d more)', Config::TEXTDOMAIN), $extra_count);
                        }

                        $summary = sprintf(
                            '<div class="cin-response-summary">%s</div>',
                            esc_html(sprintf(__('Files queued: %d (%s)', Config::TEXTDOMAIN), $files_queued, $names_text))
                        );
                    } elseif ($operation === 'file_sync') {
                        $filename = isset($decoded['filename']) ? (string)$decoded['filename'] : '';
                        $case_id = isset($decoded['case_id']) ? (string)$decoded['case_id'] : '';
                        if ($filename !== '' || $case_id !== '') {
                            $summary_text = $filename !== '' && $case_id !== ''
                                ? sprintf(__('File: %1$s (Case: %2$s)', Config::TEXTDOMAIN), $filename, $case_id)
                                : ($filename !== ''
                                    ? sprintf(__('File: %s', Config::TEXTDOMAIN), $filename)
                                    : sprintf(__('Case: %s', Config::TEXTDOMAIN), $case_id));
                            $summary = sprintf('<div class="cin-response-summary">%s</div>', esc_html($summary_text));
                        }
                    }

                    return $summary . sprintf(
                        '<details class="cin-response-details"><summary>%s</summary><pre class="cin-response-neutral">%s</pre></details>',
                        esc_html__('Response', Config::TEXTDOMAIN),
                        esc_html(wp_json_encode($decoded, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES))
                    );
                }

                return sprintf(
                    '<details class="cin-response-details"><summary>%s</summary><pre class="cin-response-neutral">%s</pre></details>',
                    esc_html__('Response', Config::TEXTDOMAIN),
                    esc_html($item['response'])
                );
            case 'error_message':
                if (empty($item['error_message'])) {
                    return '<span class="cin-error-none">—</span>';
                }
                // Match email log page: summary is error label, pre is error text
                return sprintf(
                    '<details class="cin-error-details"><summary>%s</summary><pre class="cin-error-text">%s</pre></details>',
                    esc_html__('Error', Config::TEXTDOMAIN),
                    esc_html($item['error_message'])
                );
            default:
                return '';
        }
    }
}
