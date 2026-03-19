<?php
// phpcs:disable WordPress.Security.EscapeOutput.OutputNotEscaped, WordPress.WP.I18n.MissingTranslatorsComment, WordPress.NamingConventions.PrefixAllGlobals
/**
 * Admin Template: REST API Log
 *
 * @package ContactInbox/Admin
 */

use ContactInbox\Core\Config;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$contactinbox_rest_page      = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : 'contactin-rest-log';
$contactinbox_http_method_in = isset( $_GET['http_method'] ) ? sanitize_text_field( wp_unslash( $_GET['http_method'] ) ) : 'all';
$contactinbox_endpoint_in    = isset( $_GET['endpoint'] ) ? sanitize_key( wp_unslash( $_GET['endpoint'] ) ) : 'all';
$contactinbox_http_code_in   = isset( $_GET['http_code'] ) ? sanitize_key( wp_unslash( $_GET['http_code'] ) ) : 'all';
$contactinbox_validated_in   = isset( $_GET['validated'] ) ? sanitize_key( wp_unslash( $_GET['validated'] ) ) : 'all';

$contactinbox_allowed_http_methods = [ 'all', 'GET', 'POST', 'PUT', 'DELETE' ];
$contactinbox_allowed_endpoints    = [ 'all', 'submit', 'upload-attachment', 'read', 'status', 'messages', 'search', 'delete', 'bulk-delete' ];
$contactinbox_allowed_http_codes   = [ 'all', '200', '400', '401', '403', '404', '500' ];
$contactinbox_allowed_validated    = [ 'all', '0', '1' ];

$contactinbox_http_method_normalized = 'all' === strtolower( $contactinbox_http_method_in ) ? 'all' : strtoupper( $contactinbox_http_method_in );
$contactinbox_http_method            = in_array( $contactinbox_http_method_normalized, $contactinbox_allowed_http_methods, true ) ? $contactinbox_http_method_normalized : 'all';
$contactinbox_endpoint               = in_array( $contactinbox_endpoint_in, $contactinbox_allowed_endpoints, true ) ? $contactinbox_endpoint_in : 'all';
$contactinbox_http_code              = in_array( $contactinbox_http_code_in, $contactinbox_allowed_http_codes, true ) ? $contactinbox_http_code_in : 'all';
$contactinbox_validated              = in_array( $contactinbox_validated_in, $contactinbox_allowed_validated, true ) ? $contactinbox_validated_in : 'all';

$contactinbox_is_filter_request = isset( $_GET['http_method'] ) || isset( $_GET['endpoint'] ) || isset( $_GET['http_code'] ) || isset( $_GET['validated'] );
if ( $contactinbox_is_filter_request ) {
    $contactinbox_rest_nonce = isset( $_GET['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ) : '';
    if ( '' === $contactinbox_rest_nonce || ! wp_verify_nonce( $contactinbox_rest_nonce, 'contactinbox_rest_log_filter' ) ) {
        $contactinbox_http_method = 'all';
        $contactinbox_endpoint    = 'all';
        $contactinbox_http_code   = 'all';
        $contactinbox_validated   = 'all';
    }
}

$rest_log_page_inline_js = <<<'JS'
(function($) {
    'use strict';
    
    $(document).on('change', '#method-filter, #endpoint-filter, #http-code-filter, #validated-filter, #per-page-filter-rest', function() {
        $('#contactin-rest-log-form').submit();
    });
})(jQuery);
JS;
wp_add_inline_script('contactin-admin-global', $rest_log_page_inline_js);
?>
<div class="wrap cin-rest-log-page">
    <div class="cin-page-header">
        <div>
            <h1><?php esc_html_e( 'REST API Log', 'contact-inbox' ); ?></h1>
            <span class="cin-header-count">
                <?php printf(
                    _n( '(%s API call)', '(%s API calls)', $total_items, 'contact-inbox' ),
                    number_format_i18n( $total_items )
                ); ?>
            </span>
        </div>
    </div>

    <div id="contactin-rest-notice" class="notice cin-rest-message-box cin-hidden"></div>

    <form id="contactin-rest-log-form" method="get">
        <input type="hidden" name="page" value="<?php echo esc_attr( $contactinbox_rest_page ); ?>" />
        <?php wp_nonce_field( 'contactinbox_rest_log_filter' ); ?>

        <!-- Filters and action buttons - using inbox/contacts/log layout -->
        <div class="tablenav top cin-log-tablenav cin-rest-log-tablenav">
            <div class="alignleft actions">
                <!-- HTTP Method filter -->
                <label for="method-filter" class="screen-reader-text">
                    <?php esc_html_e('Filter by HTTP method', 'contact-inbox'); ?>
                </label>
                <select id="method-filter" name="http_method" class="cin-rest-method-filter">
                    <option value="all" <?php selected( $contactinbox_http_method, 'all' ); ?>><?php esc_html_e('All Methods', 'contact-inbox'); ?></option>
                    <option value="GET" <?php selected( $contactinbox_http_method, 'GET' ); ?>>GET</option>
                    <option value="POST" <?php selected( $contactinbox_http_method, 'POST' ); ?>>POST</option>
                    <option value="PUT" <?php selected( $contactinbox_http_method, 'PUT' ); ?>>PUT</option>
                    <option value="DELETE" <?php selected( $contactinbox_http_method, 'DELETE' ); ?>>DELETE</option>
                </select>

                <!-- Endpoint filter -->
                <label for="endpoint-filter" class="screen-reader-text">
                    <?php esc_html_e('Filter by endpoint', 'contact-inbox'); ?>
                </label>
                <select id="endpoint-filter" name="endpoint" class="cin-rest-endpoint-filter">
                    <option value="all" <?php selected( $contactinbox_endpoint, 'all' ); ?>><?php esc_html_e('All Endpoints', 'contact-inbox'); ?></option>
                    <option value="submit" <?php selected( $contactinbox_endpoint, 'submit' ); ?>><?php esc_html_e('Submit Form', 'contact-inbox'); ?></option>
                    <option value="upload-attachment" <?php selected( $contactinbox_endpoint, 'upload-attachment' ); ?>><?php esc_html_e('Upload Attachment', 'contact-inbox'); ?></option>
                    <option value="read" <?php selected( $contactinbox_endpoint, 'read' ); ?>><?php esc_html_e('Read Message', 'contact-inbox'); ?></option>
                    <option value="status" <?php selected( $contactinbox_endpoint, 'status' ); ?>><?php esc_html_e('Update Status', 'contact-inbox'); ?></option>
                    <option value="messages" <?php selected( $contactinbox_endpoint, 'messages' ); ?>><?php esc_html_e('List Messages', 'contact-inbox'); ?></option>
                    <option value="search" <?php selected( $contactinbox_endpoint, 'search' ); ?>><?php esc_html_e('Search', 'contact-inbox'); ?></option>
                    <option value="delete" <?php selected( $contactinbox_endpoint, 'delete' ); ?>><?php esc_html_e('Delete', 'contact-inbox'); ?></option>
                    <option value="bulk-delete" <?php selected( $contactinbox_endpoint, 'bulk-delete' ); ?>><?php esc_html_e('Bulk Delete', 'contact-inbox'); ?></option>
                </select>

                <!-- HTTP Code filter -->
                <label for="http-code-filter" class="screen-reader-text">
                    <?php esc_html_e('Filter by HTTP code', 'contact-inbox'); ?>
                </label>
                <select id="http-code-filter" name="http_code" class="cin-rest-http-code-filter">
                    <option value="all" <?php selected( $contactinbox_http_code, 'all' ); ?>><?php esc_html_e('All Codes', 'contact-inbox'); ?></option>
                    <option value="200" <?php selected( $contactinbox_http_code, '200' ); ?>>200</option>
                    <option value="400" <?php selected( $contactinbox_http_code, '400' ); ?>>400</option>
                    <option value="401" <?php selected( $contactinbox_http_code, '401' ); ?>>401</option>
                    <option value="403" <?php selected( $contactinbox_http_code, '403' ); ?>>403</option>
                    <option value="404" <?php selected( $contactinbox_http_code, '404' ); ?>>404</option>
                    <option value="500" <?php selected( $contactinbox_http_code, '500' ); ?>>500</option>
                </select>

                <!-- Validated filter -->
                <label for="validated-filter" class="screen-reader-text">
                    <?php esc_html_e('Filter by validation', 'contact-inbox'); ?>
                </label>
                <select id="validated-filter" name="validated" class="cin-rest-validated-filter">
                    <option value="all" <?php selected( $contactinbox_validated, 'all' ); ?>><?php esc_html_e('All Validations', 'contact-inbox'); ?></option>
                    <option value="1" <?php selected( $contactinbox_validated, '1' ); ?>><?php esc_html_e('Validated', 'contact-inbox'); ?></option>
                    <option value="0" <?php selected( $contactinbox_validated, '0' ); ?>><?php esc_html_e('Not Validated', 'contact-inbox'); ?></option>
                </select>

                <!-- Prune button -->
                <button type="button" class="button button-secondary" id="contactin-prune-rest-btn" <?php disabled( $total_items === 0 ); ?>>
                    <?php esc_html_e('Prune Old Logs', 'contact-inbox'); ?>
                </button>

                <!-- Clear All Logs button -->
                <button type="button" class="button button-secondary" id="contactin-clear-rest-logs" <?php disabled( $total_items === 0 ); ?>>
                    <?php esc_html_e('Clear All Logs', 'contact-inbox'); ?>
                </button>

                <span class="cin-log-export">
                    <button type="button" class="button button-primary cin-download-csv"
                        data-http-method="<?php echo esc_attr( $contactinbox_http_method ); ?>"
                        data-endpoint="<?php echo esc_attr( $contactinbox_endpoint ); ?>"
                        data-http-code="<?php echo esc_attr( $contactinbox_http_code ); ?>"
                        data-validated="<?php echo esc_attr( $contactinbox_validated ); ?>"
                        data-export-info-action="contactinbox_rest_export_info"
                        data-ajax-action="contactinbox_download_rest_csv"
                        data-nonce="<?php echo esc_attr(wp_create_nonce(Config::NONCE_ACTION)); ?>"
                        <?php disabled( $total_items === 0 ); ?>>
                        <span class="dashicons dashicons-download"></span>
                        <?php esc_html_e('Export CSV', 'contact-inbox'); ?>
                        <?php if ( defined('CONTACTINBOX_IS_FREE') && CONTACTINBOX_IS_FREE ) : ?>
                            <span style="margin-left: 4px; background: #dc3545; color: white; padding: 1px 4px; border-radius: 2px; font-size: 9px; font-weight: bold;">PRO</span>
                        <?php endif; ?>
                    </button>
                </span>
            </div>
        </div>

        <div class="tablenav top cin-log-tablenav-pages cin-rest-log-tablenav-pages">
            <div class="tablenav-pages">
                <span class="displaying-num"><?php echo esc_html( number_format_i18n( $total_items ) ); ?> <?php esc_html_e( 'items', 'contact-inbox' ); ?></span>

                <!-- Per page filter -->
                <label for="per-page-filter-rest" class="cin-per-page-label"><?php esc_html_e( 'Rows per page', 'contact-inbox' ); ?></label>
                <select id="per-page-filter-rest" name="per_page" class="cin-per-page-select">
                    <option value="20" <?php selected( $per_page, 20 ); ?>>20</option>
                    <option value="50" <?php selected( $per_page, 50 ); ?>>50</option>
                    <option value="100" <?php selected( $per_page, 100 ); ?>>100</option>
                </select>

                <?php
                $pagination_args = [
                    'base'      => add_query_arg(['paged' => '%#%', 'per_page' => $per_page]),
                    'format'    => '',
                    'current'   => $current_page,
                    'total'     => (int) $table->get_pagination_arg( 'total_pages' ),
                    'prev_text' => __('Prev', 'contact-inbox'),
                    'next_text' => __('Next', 'contact-inbox'),
                    'type'      => 'plain',
                ];
                echo paginate_links($pagination_args);
                ?>
            </div>
        </div>

        <!-- Table -->
        <div class="wp-list-table-container">
            <?php $table->display(); ?>
        </div>
    </form>

    <!-- Load shared export modal -->
    <?php load_template( CONTACTINBOX_PATH . Config::TEMPLATE_ADMIN_PART . 'export-modal.php' ); ?>

    <div id="contactin-rest-modal" class="contactin-modal" role="dialog" aria-modal="true" aria-labelledby="contactin-rest-modal-title">
        <!-- Backdrop -->
        <div class="contactin-modal-backdrop"></div>

        <!-- Modal content -->
        <div class="contactin-modal-content">
            <!-- Header -->
            <div class="contactin-modal-header">
                <h2 id="contactin-rest-modal-title" class="cin-modal-title">
                    <?php esc_html_e( 'Log Details', 'contact-inbox' ); ?>
                </h2>
            </div>

            <!-- Meta section (compact badges) -->
            <div id="contactin-rest-meta" class="contactin-modal-meta">
                <div class="contactin-meta-item">
                    <strong>Timestamp:</strong> <span>2025-12-09 09:43:09</span>
                </div>
                <div class="contactin-meta-item">
                    <strong>IP:</strong> <span>127.0.0.1</span>
                </div>
                <div class="contactin-meta-item">
                    <strong>User Agent:</strong> <span>WordPress/6.9; http://wpdev.local</span>
                </div>
                <div class="contactin-meta-item">
                    <strong>HTTP Method:</strong> <span>POST</span>
                </div>
                <div class="contactin-meta-item">
                    <strong>Endpoint:</strong> <span>/submit</span>
                </div>
                <div class="contactin-meta-item">
                    <strong>HTTP Code:</strong> <span>200</span>
                </div>
                <div class="contactin-meta-item">
                    <strong>Validated:</strong> <span>✔</span>
                </div>
                <div class="contactin-meta-item">
                    <strong>Token valid:</strong> <span>✔</span>
                </div>
            </div>

            <!-- Payload (scrollable) -->
            <div class="contactin-modal-payload">
                <section id="contactin-rest-headers" class="cin-log-section">
                    <h4><?php esc_html_e( 'Request Headers', 'contact-inbox' ); ?></h4>
                    <pre></pre>
                </section>

                <section id="contactin-rest-request" class="cin-log-section">
                    <h4><?php esc_html_e( 'Request Payload', 'contact-inbox' ); ?></h4>
                    <pre></pre>
                </section>

                <section id="contactin-rest-response" class="cin-log-section">
                    <h4><?php esc_html_e( 'Response Body', 'contact-inbox' ); ?></h4>
                    <pre></pre>
                </section>
            </div>

            <!-- Footer -->
            <div class="contactin-modal-footer">
                <button type="button" class="button button-secondary" id="contactin-prev-log" aria-label="<?php esc_attr_e( 'View previous log', 'contact-inbox' ); ?>">
                    <?php esc_html_e( 'Prev', 'contact-inbox' ); ?>
                </button>
                <button type="button" class="button button-secondary" id="contactin-next-log" aria-label="<?php esc_attr_e( 'View next log', 'contact-inbox' ); ?>">
                    <?php esc_html_e( 'Next', 'contact-inbox' ); ?>
                </button>
            </div>
        </div>
    </div>

</div>


