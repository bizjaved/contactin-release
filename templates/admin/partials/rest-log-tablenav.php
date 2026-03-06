<?php
/**
 * Admin Partial: REST Log Tablenav
 *
 * @package ContactInbox\Admin
 */

use ContactInbox\Core\Config;
use ContactInbox\Core\DB;

if ( ! defined( 'ABSPATH' ) ) exit;

// Capture current filter values (from POST or GET)
$contactinbox_request_method = filter_input(INPUT_GET, 'method', FILTER_UNSAFE_RAW);
if (null === $contactinbox_request_method || false === $contactinbox_request_method) {
    $contactinbox_request_method = filter_input(INPUT_POST, 'method', FILTER_UNSAFE_RAW);
}
$contactinbox_request_endpoint = filter_input(INPUT_GET, 'endpoint', FILTER_UNSAFE_RAW);
if (null === $contactinbox_request_endpoint || false === $contactinbox_request_endpoint) {
    $contactinbox_request_endpoint = filter_input(INPUT_POST, 'endpoint', FILTER_UNSAFE_RAW);
}
$contactinbox_request_http_code = filter_input(INPUT_GET, 'http_code', FILTER_UNSAFE_RAW);
if (null === $contactinbox_request_http_code || false === $contactinbox_request_http_code) {
    $contactinbox_request_http_code = filter_input(INPUT_POST, 'http_code', FILTER_UNSAFE_RAW);
}
$contactinbox_request_validated = filter_input(INPUT_GET, 'validated', FILTER_UNSAFE_RAW);
if (null === $contactinbox_request_validated || false === $contactinbox_request_validated) {
    $contactinbox_request_validated = filter_input(INPUT_POST, 'validated', FILTER_UNSAFE_RAW);
}

$current_method    = sanitize_text_field(wp_unslash((string) ($contactinbox_request_method ?? 'all')));
$current_endpoint  = sanitize_text_field(wp_unslash((string) ($contactinbox_request_endpoint ?? 'all')));
$current_http_code = sanitize_text_field(wp_unslash((string) ($contactinbox_request_http_code ?? 'all')));
$current_validated = sanitize_text_field(wp_unslash((string) ($contactinbox_request_validated ?? 'all')));

if (!in_array($current_method, ['all', 'GET', 'POST', 'PUT', 'DELETE'], true)) {
    $current_method = 'all';
}
if (!in_array($current_http_code, ['all', '200', '400', '401', '403', '500'], true)) {
    $current_http_code = 'all';
}
if (!in_array($current_validated, ['all', '0', '1'], true)) {
    $current_validated = 'all';
}
?>
<div class="tablenav <?php echo esc_attr( $which ); ?>">
    <?php wp_nonce_field('contactinbox_rest_log_filter'); ?>
    <div class="alignleft actions">
        <label for="method-filter" class="screen-reader-text">
            <?php esc_html_e( 'Filter by method', 'contact-inbox' ); ?>
        </label>
        <select id="method-filter" name="method">
            <option value="all" <?php selected( $current_method, 'all' ); ?>><?php esc_html_e( 'All Methods', 'contact-inbox' ); ?></option>
            <option value="GET" <?php selected( $current_method, 'GET' ); ?>>GET</option>
            <option value="POST" <?php selected( $current_method, 'POST' ); ?>>POST</option>
            <option value="PUT" <?php selected( $current_method, 'PUT' ); ?>>PUT</option>
            <option value="DELETE" <?php selected( $current_method, 'DELETE' ); ?>>DELETE</option>
        </select>

        <label for="endpoint-filter" class="screen-reader-text">
            <?php esc_html_e( 'Filter by endpoint', 'contact-inbox' ); ?>
        </label>
        <?php $endpoints = DB::instance()->get_distinct_endpoints(); ?>
        <select id="endpoint-filter" name="endpoint">
            <option value="all" <?php selected( $current_endpoint, 'all' ); ?>><?php esc_html_e( 'All Endpoints', 'contact-inbox' ); ?></option>
            <?php if ( ! empty( $endpoints ) ) : ?>
                <?php foreach ( $endpoints as $ep ): ?>
                    <option value="<?php echo esc_attr( $ep ); ?>" <?php selected( $current_endpoint, $ep ); ?>>
                        <?php echo esc_html( $ep ); ?>
                    </option>
                <?php endforeach; ?>
            <?php endif; ?>
        </select>

        <label for="http-filter" class="screen-reader-text">
            <?php esc_html_e( 'Filter by HTTP Code', 'contact-inbox' ); ?>
        </label>
        <select id="http-filter" name="http_code">
            <option value="all" <?php selected( $current_http_code, 'all' ); ?>><?php esc_html_e( 'All Codes', 'contact-inbox' ); ?></option>
            <option value="200" <?php selected( $current_http_code, '200' ); ?>>200</option>
            <option value="400" <?php selected( $current_http_code, '400' ); ?>>400</option>
            <option value="401" <?php selected( $current_http_code, '401' ); ?>>401</option>
            <option value="403" <?php selected( $current_http_code, '403' ); ?>>403</option>
            <option value="500" <?php selected( $current_http_code, '500' ); ?>>500</option>
        </select>

        <label for="validated-filter" class="screen-reader-text">
            <?php esc_html_e( 'Filter by Validated', 'contact-inbox' ); ?>
        </label>
        <select id="validated-filter" name="validated">
            <option value="all" <?php selected( $current_validated, 'all' ); ?>><?php esc_html_e( 'All', 'contact-inbox' ); ?></option>
            <option value="1" <?php selected( $current_validated, '1' ); ?>><?php esc_html_e( '✔ Valid', 'contact-inbox' ); ?></option>
            <option value="0" <?php selected( $current_validated, '0' ); ?>><?php esc_html_e( '✖ Invalid', 'contact-inbox' ); ?></option>
        </select>


        <!-- Prune button -->
        <button type="button" class="button button-secondary" id="contactin-prune-btn">
            <?php esc_html_e( 'Prune Old Logs', 'contact-inbox' ); ?>
        </button>

        <!-- Download CSV button -->
        <button type="button" class="button button-secondary" id="contactin-download-csv">
            <?php esc_html_e( 'Download CSV', 'contact-inbox' ); ?>
            <?php if ( defined('CONTACTINBOX_IS_FREE') && CONTACTINBOX_IS_FREE ) : ?>
                <span style="margin-left: 4px; background: #dc3545; color: white; padding: 1px 4px; border-radius: 2px; font-size: 9px; font-weight: bold;">PRO</span>
            <?php endif; ?>
        </button>
    </div>

    <div class="tablenav-pages">
        <?php echo $pagination; ?>
    </div>
</div>
