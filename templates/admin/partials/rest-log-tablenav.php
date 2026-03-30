<?php
if (!defined('ABSPATH')) exit;
/**
 * Admin Partial: REST Log Tablenav
 *
 * @package ContactIn\Admin
 */

use ContactInbox\Core\Config;
use ContactInbox\Core\DB;

// phpcs:disable WordPress.WP.I18n.NonSingularStringLiteralDomain, WordPress.WP.I18n.MissingTranslatorsComment, WordPress.Security.ValidatedSanitizedInput, WordPress.Security.NonceVerification, WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound, WordPress.Security.EscapeOutput.OutputNotEscaped

if ( ! defined( 'ABSPATH' ) ) exit;

// Capture current filter values (from POST or GET)
$current_method    = $_REQUEST['method']    ?? 'all';
$current_endpoint  = $_REQUEST['endpoint']  ?? 'all';
$current_http_code = $_REQUEST['http_code'] ?? 'all';
$current_validated = $_REQUEST['validated'] ?? 'all';
?>
<div class="tablenav <?php echo esc_attr( $which ); ?>">
    <div class="alignleft actions">
        <label for="method-filter" class="screen-reader-text">
            <?php esc_html_e( 'Filter by method',  'contactin'); ?>
        </label>
        <select id="method-filter" name="method">
            <option value="all" <?php selected( $current_method, 'all' ); ?>><?php esc_html_e( 'All Methods',  'contactin'); ?></option>
            <option value="GET" <?php selected( $current_method, 'GET' ); ?>>GET</option>
            <option value="POST" <?php selected( $current_method, 'POST' ); ?>>POST</option>
            <option value="PUT" <?php selected( $current_method, 'PUT' ); ?>>PUT</option>
            <option value="DELETE" <?php selected( $current_method, 'DELETE' ); ?>>DELETE</option>
        </select>

        <label for="endpoint-filter" class="screen-reader-text">
            <?php esc_html_e( 'Filter by endpoint',  'contactin'); ?>
        </label>
        <?php $endpoints = DB::instance()->get_distinct_endpoints(); ?>
        <select id="endpoint-filter" name="endpoint">
            <option value="all" <?php selected( $current_endpoint, 'all' ); ?>><?php esc_html_e( 'All Endpoints',  'contactin'); ?></option>
            <?php if ( ! empty( $endpoints ) ) : ?>
                <?php foreach ( $endpoints as $ep ): ?>
                    <option value="<?php echo esc_attr( $ep ); ?>" <?php selected( $current_endpoint, $ep ); ?>>
                        <?php echo esc_html( $ep ); ?>
                    </option>
                <?php endforeach; ?>
            <?php endif; ?>
        </select>

        <label for="http-filter" class="screen-reader-text">
            <?php esc_html_e( 'Filter by HTTP Code',  'contactin'); ?>
        </label>
        <select id="http-filter" name="http_code">
            <option value="all" <?php selected( $current_http_code, 'all' ); ?>><?php esc_html_e( 'All Codes',  'contactin'); ?></option>
            <option value="200" <?php selected( $current_http_code, '200' ); ?>>200</option>
            <option value="400" <?php selected( $current_http_code, '400' ); ?>>400</option>
            <option value="401" <?php selected( $current_http_code, '401' ); ?>>401</option>
            <option value="403" <?php selected( $current_http_code, '403' ); ?>>403</option>
            <option value="500" <?php selected( $current_http_code, '500' ); ?>>500</option>
        </select>

        <label for="validated-filter" class="screen-reader-text">
            <?php esc_html_e( 'Filter by Validated',  'contactin'); ?>
        </label>
        <select id="validated-filter" name="validated">
            <option value="all" <?php selected( $current_validated, 'all' ); ?>><?php esc_html_e( 'All',  'contactin'); ?></option>
            <option value="1" <?php selected( $current_validated, '1' ); ?>><?php esc_html_e( '✔ Valid',  'contactin'); ?></option>
            <option value="0" <?php selected( $current_validated, '0' ); ?>><?php esc_html_e( '✖ Invalid',  'contactin'); ?></option>
        </select>


        <!-- Prune button -->
        <button type="button" class="button button-secondary" id="contactin-prune-btn">
            <?php esc_html_e( 'Prune Old Logs',  'contactin'); ?>
        </button>

        <!-- Download CSV button -->
        <button type="button" class="button button-secondary" id="contactin-download-csv">
            <?php esc_html_e( 'Download CSV',  'contactin'); ?>
        </button>
    </div>

    <div class="tablenav-pages">
        <?php echo $pagination; ?>
    </div>
</div>
