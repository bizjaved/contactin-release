<?php
// phpcs:disable WordPress.Security.EscapeOutput.OutputNotEscaped, WordPress.WP.I18n.MissingTranslatorsComment, WordPress.NamingConventions.PrefixAllGlobals
/**
 * Admin Template: Email Log
 *
 * @package ContactInbox
 */

use ContactInbox\Core\Config;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$contactinbox_email_log_page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : 'contactin-email-log';
$contactinbox_status_input   = isset( $_GET['status'] ) ? sanitize_key( wp_unslash( $_GET['status'] ) ) : 'all';
$contactinbox_status         = in_array( $contactinbox_status_input, [ 'all', 'sent', 'failed', 'pending' ], true ) ? $contactinbox_status_input : 'all';

$contactinbox_is_filter_request = isset( $_GET['status'] );
if ( $contactinbox_is_filter_request ) {
    $contactinbox_email_nonce = isset( $_GET['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ) : '';
    if ( '' === $contactinbox_email_nonce || ! wp_verify_nonce( $contactinbox_email_nonce, 'contactinbox_email_log_filter' ) ) {
        $contactinbox_status = 'all';
    }
}

$email_log_page_inline_js = <<<'JS'
(function($) {
    'use strict';
    
    $(document).on('change', '#status-filter', function() {
        $('#contactin-email-log-form').submit();
    });
    
    $(document).on('change', '#per-page-filter-email', function() {
        $('#contactin-email-log-form').submit();
    });
})(jQuery);
JS;
wp_add_inline_script('contactin-admin-email-log', $email_log_page_inline_js);
?>
<div class="wrap cin-email-log-page">
    <div class="cin-page-header">
        <div>
            <h1><?php esc_html_e( 'Email Log', 'contact-inbox' ); ?></h1>
            <span class="cin-header-count">
                <?php printf(
                    _n( '(%s email log)', '(%s email logs)', $total_items, 'contact-inbox' ),
                    number_format_i18n( $total_items )
                ); ?>
            </span>
        </div>
    </div>

    <div id="contactin-email-notice" class="notice cin-hidden"></div>

    <form id="contactin-email-log-form" method="get">
        <input type="hidden" name="page" value="<?php echo esc_attr( $contactinbox_email_log_page ); ?>" />
        <?php wp_nonce_field( 'contactinbox_email_log_filter' ); ?>
        <?php wp_nonce_field( Config::EMAIL_LOG_ACTION, Config::EMAIL_LOG_NONCE ); ?>

        <!-- Filters and action buttons - using inbox/contacts layout -->
        <div class="tablenav top cin-log-tablenav cin-email-log-tablenav">
            <div class="alignleft actions">
                <!-- Status filter -->
                <label for="status-filter" class="screen-reader-text">
                    <?php esc_html_e('Filter by status', 'contact-inbox'); ?>
                </label>
                <select id="status-filter" name="status" class="cin-status-filter-select">
                    <option value="all" <?php selected( $contactinbox_status, 'all' ); ?>><?php esc_html_e('All Statuses', 'contact-inbox'); ?></option>
                    <option value="sent" <?php selected( $contactinbox_status, 'sent' ); ?>><?php esc_html_e('Sent', 'contact-inbox'); ?></option>
                    <option value="failed" <?php selected( $contactinbox_status, 'failed' ); ?>><?php esc_html_e('Failed', 'contact-inbox'); ?></option>
                    <option value="pending" <?php selected( $contactinbox_status, 'pending' ); ?>><?php esc_html_e('Pending', 'contact-inbox'); ?></option>
                </select>

                <!-- Prune button (handled by admin-email-log.min.js) -->
                <button type="button" class="button button-secondary" id="contactin-prune-email-btn" <?php disabled( $total_items === 0 ); ?>>
                    <?php esc_html_e('Prune Old Logs', 'contact-inbox'); ?>
                </button>

                <!-- Clear All Logs button (handled by admin-email-log.min.js) -->
                <button type="button" class="button button-secondary" id="contactin-clear-email-logs" <?php disabled( $total_items === 0 ); ?>>
                    <?php esc_html_e('Clear All Logs', 'contact-inbox'); ?>
                </button>

                <span class="cin-log-export">
                    <button type="button" class="button button-primary cin-download-csv" 
                        data-status="<?php echo esc_attr( $contactinbox_status ); ?>"
                        data-export-info-action="contactinbox_email_export_info"
                        data-ajax-action="contactinbox_download_email_csv"
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

        <div class="tablenav top cin-log-tablenav-pages cin-email-log-tablenav-pages">
            <div class="tablenav-pages">
                <span class="displaying-num"><?php echo esc_html( number_format_i18n( $total_items ) ); ?> <?php esc_html_e( 'items', 'contact-inbox' ); ?></span>

                <!-- Per page filter -->
                <label for="per-page-filter-email" class="cin-per-page-label"><?php esc_html_e( 'Rows per page', 'contact-inbox' ); ?></label>
                <select id="per-page-filter-email" name="per_page" class="cin-per-page-select">
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

        <div class="wp-list-table-container">
            <?php
            // Defensive check
            if ( empty( $table->_column_headers ) ) {
                $table->_column_headers = [ $table->get_columns(), [], $table->get_sortable_columns() ];
            }
            // Render the table (includes top and bottom tablenav automatically)
            $table->display();
            ?>
        </div>
    </form>

    <!-- Export Modal -->
    <div id="cin-export-modal" class="cin-modal-overlay" role="dialog" aria-modal="true" aria-labelledby="cin-export-modal-title">
        <div class="cin-confirm-modal">
            <h3 id="cin-export-modal-title"><?php esc_html_e('Export Records', 'contact-inbox'); ?></h3>
            <p class="cin-export-meta">
                <?php esc_html_e('Total:', 'contact-inbox'); ?> <strong id="cin-export-total">0</strong> · 
                <?php esc_html_e('Max per file:', 'contact-inbox'); ?> <strong id="cin-export-max">1000</strong>
            </p>
            <div class="cin-export-row">
                <label for="cin-export-chunk"><?php esc_html_e('Chunk size:', 'contact-inbox'); ?></label>
                <input id="cin-export-chunk" type="number" min="1" max="1000" value="500" class="cin-export-chunk">
                <span class="cin-export-hint"><?php esc_html_e('(Max 1000)', 'contact-inbox'); ?></span>
            </div>
            <div id="cin-export-links" class="cin-export-links"></div>
            <div class="cin-export-footer">
                <button type="button" class="button" id="cin-export-close"><?php esc_html_e('Close', 'contact-inbox'); ?></button>
            </div>
        </div>
    </div>

</div>