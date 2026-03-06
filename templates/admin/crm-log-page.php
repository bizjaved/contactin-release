<?php
/**
 * Admin Template: CRM Log
 *
 * @package ContactInbox
 */

use ContactInbox\Core\Config;
use ContactInbox\Core\CRMStatus;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
?>
<div class="wrap cin-crm-log-page">
    <div class="cin-page-header">
        <div>
            <h1><?php esc_html_e( 'CRM Log', 'contact-inbox' ); ?></h1>
            <span class="cin-header-count">
                <?php printf(
                    _n( '(%s sync operation)', '(%s sync operations)', $total_items, 'contact-inbox' ),
                    number_format_i18n( $total_items )
                ); ?>
            </span>
        </div>
    </div>

    <div id="contactin-crm-notice" class="notice cin-rest-message-box cin-hidden"></div>

    <form id="contactin-crm-log-form" method="get">
        <?php
        $crm_page_input = filter_input( INPUT_GET, 'page', FILTER_SANITIZE_FULL_SPECIAL_CHARS );
        $crm_page = is_string( $crm_page_input ) ? sanitize_text_field( wp_unslash( $crm_page_input ) ) : 'contactin-crm-log';
        ?>
        <input type="hidden" name="page" value="<?php echo esc_attr( $crm_page ); ?>" />
        <?php wp_nonce_field( 'contactin_crm_log_action', 'contactin_crm_log_nonce' ); ?>

        <!-- Filters and action buttons - using inbox/contacts layout -->
        <div class="tablenav top cin-log-tablenav cin-crm-log-tablenav">
            <div class="alignleft actions">
                <!-- Status filter -->
                <label for="status-filter-crm" class="screen-reader-text">
                    <?php esc_html_e('Filter by status', 'contact-inbox'); ?>
                </label>
                <select id="status-filter-crm" name="status" class="cin-status-filter-select">
                    <?php foreach (CRMStatus::get_filter_options() as $option) : ?>
                        <option value="<?php echo esc_attr($option['value']); ?>" <?php selected($current_status, $option['value']); ?>>
                            <?php echo esc_html($option['label']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>

                <!-- Operation filter -->
                <label for="operation-filter-crm" class="screen-reader-text">
                    <?php esc_html_e('Filter by operation', 'contact-inbox'); ?>
                </label>
                <select id="operation-filter-crm" name="operation" class="cin-operation-filter">
                    <option value="all" <?php selected($current_operation, 'all'); ?>><?php esc_html_e('All Operations', 'contact-inbox'); ?></option>
                    <?php foreach ($operation_options as $operation_option) : ?>
                        <option value="<?php echo esc_attr($operation_option); ?>" <?php selected($current_operation, $operation_option); ?>>
                            <?php echo esc_html(ucwords(str_replace('_', ' ', $operation_option))); ?>
                        </option>
                    <?php endforeach; ?>
                </select>

                <!-- Use a single, always-fresh nonce for both buttons -->
                <?php $crm_logs_nonce = wp_create_nonce('contactin_crm_clear_all_logs'); ?>
                <button type="button" class="button button-secondary" id="contactin-prune-crm-btn" data-nonce="<?php echo esc_attr($crm_logs_nonce); ?>" <?php disabled( $total_items === 0 ); ?>>
                    <?php esc_html_e('Prune Old Logs', 'contact-inbox'); ?>
                </button>

                <!-- Clear All Logs button -->
                <button type="button" class="button button-secondary" id="contactin-clear-crm-logs" data-nonce="<?php echo esc_attr($crm_logs_nonce); ?>" <?php disabled( $total_items === 0 ); ?>>
                    <?php esc_html_e('Clear All Logs', 'contact-inbox'); ?>
                </button>

                <span class="cin-log-export">
                    <button type="button" class="button button-primary cin-download-csv" id="contactin-download-crm-csv"
                        data-status="<?php echo esc_attr($current_status); ?>"
                        data-operation="<?php echo esc_attr($current_operation); ?>"
                        data-nonce="<?php echo esc_attr($crm_logs_nonce); ?>"
                        data-export-info-action="contactinbox_crm_export_info"
                        data-ajax-action="contactinbox_download_crm_csv"
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

        <div class="tablenav top cin-log-tablenav-pages cin-crm-log-tablenav-pages">
            <div class="tablenav-pages">
                <span class="displaying-num"><?php echo esc_html( number_format_i18n( $total_items ) ); ?> <?php esc_html_e( 'items', 'contact-inbox' ); ?></span>

                <!-- Per page filter -->
                <label for="per-page-filter-crm" class="cin-per-page-label">
                    <?php esc_html_e( 'Rows per page', 'contact-inbox' ); ?>
                </label>
                <select id="per-page-filter-crm" name="per_page" class="cin-per-page-select">
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

</div>
