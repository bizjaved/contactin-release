<?php
/**
 * Admin Template: GDPR Deletion Log
 *
 * @package ContactInbox/Admin
 */

use ContactInbox\Core\Config;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
?>
<div class="wrap cin-gdpr-log-page">
    <div class="cin-page-header">
        <div>
            <h1><?php esc_html_e( 'GDPR Deletion Log', Config::TEXTDOMAIN ); ?></h1>
            <span class="cin-header-count">
                <?php printf(
                    _n( '(%s deletion)', '(%s deletions)', $total_items, Config::TEXTDOMAIN ),
                    number_format_i18n( $total_items )
                ); ?>
            </span>
        </div>
    </div>

    <div id="contactin-gdpr-notice" class="notice cin-rest-message-box cin-hidden"></div>

    <form id="contactin-gdpr-log-form" method="get">
        <input type="hidden" name="page" value="<?php echo esc_attr( $_GET['page'] ?? Config::MENU_GDPR_LOG ); ?>" />
        <div class="tablenav top cin-log-tablenav cin-gdpr-log-tablenav">
            <div class="alignleft actions">
                <p class="search-box">
                    <label class="screen-reader-text" for="gdpr-log-search-input">
                        <?php esc_html_e( 'Search GDPR logs', Config::TEXTDOMAIN ); ?>
                    </label>
                    <input type="search" id="gdpr-log-search-input" name="s" value="<?php echo esc_attr( $search ); ?>" placeholder="<?php esc_attr_e( 'Search by email or name', Config::TEXTDOMAIN ); ?>" />
                    <input type="submit" class="button" value="<?php esc_attr_e( 'Search', Config::TEXTDOMAIN ); ?>" />
                </p>

                <label for="crm-status-filter" class="screen-reader-text">
                    <?php esc_html_e( 'Filter by CRM sync status', Config::TEXTDOMAIN ); ?>
                </label>
                <select id="crm-status-filter" name="crm_status">
                    <?php foreach ( $crm_filter_options as $value => $label ) : ?>
                        <option value="<?php echo esc_attr( $value ); ?>" <?php selected( $crm_filter, $value ); ?>>
                            <?php echo esc_html( $label ); ?>
                        </option>
                    <?php endforeach; ?>
                </select>

                <label for="status-filter-gdpr" class="screen-reader-text">
                    <?php esc_html_e( 'Filter by deletion status', Config::TEXTDOMAIN ); ?>
                </label>
                <select id="status-filter-gdpr" name="deletion_status">
                    <?php foreach ( $deletion_status_options as $value => $label ) : ?>
                        <option value="<?php echo esc_attr( $value ); ?>" <?php selected( $status_filter, $value ); ?>>
                            <?php echo esc_html( $label ); ?>
                        </option>
                    <?php endforeach; ?>
                </select>

                <button type="button" class="button button-secondary" id="contactin-prune-gdpr-btn" <?php disabled( $total_items === 0 ); ?>>
                    <?php esc_html_e( 'Prune Old Logs', Config::TEXTDOMAIN ); ?>
                </button>

                <button type="button" class="button button-secondary" id="contactin-clear-gdpr-logs" <?php disabled( $total_items === 0 ); ?>>
                    <?php esc_html_e( 'Clear All Logs', Config::TEXTDOMAIN ); ?>
                </button>

                <?php if ( $synced_count > 0 ) : ?>
                    <a href="<?php echo esc_url( admin_url( 'admin.php?page=' . Config::MENU_MAINTENANCE ) ); ?>" class="button">
                        <?php printf( esc_html__( 'Manage %d Synced →', Config::TEXTDOMAIN ), $synced_count ); ?>
                    </a>
                <?php endif; ?>
            </div>
            <div class="alignright actions">
                <span class="cin-log-export">
                    <button type="button" class="button button-primary cin-download-csv"
                        data-export-info-action="contactinbox_gdpr_export_info"
                        data-ajax-action="contactinbox_download_gdpr_csv"
                        data-nonce="<?php echo esc_attr(wp_create_nonce(Config::NONCE_ACTION)); ?>"
                        <?php disabled( $total_items === 0 ); ?>>
                        <span class="dashicons dashicons-download"></span>
                        <?php esc_html_e( 'Export CSV', Config::TEXTDOMAIN ); ?>
                        <?php if ( defined('CONTACTINBOX_IS_FREE') && CONTACTINBOX_IS_FREE ) : ?>
                            <span style="margin-left: 4px; background: #dc3545; color: white; padding: 1px 4px; border-radius: 2px; font-size: 9px; font-weight: bold;">PRO</span>
                        <?php endif; ?>
                    </button>
                </span>
            </div>
        </div>

        <div class="tablenav top cin-log-tablenav-pages">
            <div class="tablenav-pages">
                <span class="displaying-num">
                    <?php echo esc_html( number_format_i18n( $total_items ) ); ?> <?php esc_html_e( 'items', Config::TEXTDOMAIN ); ?>
                </span>

                <label for="gdpr-log-per-page" class="cin-per-page-label">
                    <?php esc_html_e( 'Rows per page', Config::TEXTDOMAIN ); ?>
                </label>
                <select id="gdpr-log-per-page" name="per_page" class="cin-per-page-select">
                    <option value="20" <?php selected( $per_page, 20 ); ?>>20</option>
                    <option value="50" <?php selected( $per_page, 50 ); ?>>50</option>
                    <option value="100" <?php selected( $per_page, 100 ); ?>>100</option>
                </select>

                <?php
                $pagination_links = paginate_links([
                    'base'      => add_query_arg([
                        'paged'      => '%#%',
                        's'          => $search,
                        'crm_status' => $crm_filter,
                        'deletion_status' => $status_filter,
                        'per_page'   => $per_page,
                    ]),
                    'format'    => '',
                    'current'   => $paged,
                    'total'     => max( 1, $pages ),
                    'prev_text' => __( 'Prev', Config::TEXTDOMAIN ),
                    'next_text' => __( 'Next', Config::TEXTDOMAIN ),
                    'type'      => 'plain',
                ]);
                echo wp_kses_post( $pagination_links );
                ?>
            </div>
        </div>

        <div class="wp-list-table-container">
            <table class="wp-list-table widefat fixed striped table-view-list cin-log-table cin-gdpr-log-table">
                <thead>
                <tr>
                    <th scope="col" class="column-primary"><?php esc_html_e( 'Contact', Config::TEXTDOMAIN ); ?></th>
                    <th scope="col"><?php esc_html_e( 'Email', Config::TEXTDOMAIN ); ?></th>
                    <th scope="col"><?php esc_html_e( 'CRM Sync', Config::TEXTDOMAIN ); ?></th>
                    <th scope="col"><?php esc_html_e( 'Messages', Config::TEXTDOMAIN ); ?></th>
                    <th scope="col"><?php esc_html_e( 'Files', Config::TEXTDOMAIN ); ?></th>
                    <th scope="col"><?php esc_html_e( 'Deletion Status', Config::TEXTDOMAIN ); ?></th>
                    <th scope="col"><?php esc_html_e( 'Deleted By', Config::TEXTDOMAIN ); ?></th>
                    <th scope="col"><?php esc_html_e( 'Deleted At', Config::TEXTDOMAIN ); ?></th>
                    <th scope="col"><?php esc_html_e( 'Error', Config::TEXTDOMAIN ); ?></th>
                </tr>
                </thead>
                <tbody>
                <?php if ( ! empty( $logs ) ) : ?>
                    <?php foreach ( $logs as $log ) :
                        $contact_name = trim( (string) ( $log->name ?? '' ) );
                        $email        = $log->email ?? '';
                        $contact_id   = isset( $log->contact_id ) ? (int) $log->contact_id : null;
                        $crm_status   = $log->crm_sync_status ?? 'unknown';
                        $deletion_status = $log->deletion_status ?? 'pending';
                        $messages_deleted = isset( $log->messages_deleted ) ? (int) $log->messages_deleted : 0;
                        $messages_synced  = isset( $log->messages_synced ) ? (int) $log->messages_synced : 0;
                        $messages_unsynced = isset( $log->messages_unsynced ) ? (int) $log->messages_unsynced : 0;
                        $files_deleted = isset( $log->files_deleted ) ? (int) $log->files_deleted : 0;
                        $deleted_by    = isset( $log->deleted_by ) ? (int) $log->deleted_by : 0;
                        $deleted_at    = $log->deleted_at ?? '';

                        $deleted_by_user  = $deleted_by ? get_userdata( $deleted_by ) : null;
                        $deleted_by_label = $deleted_by_user
                            ? $deleted_by_user->display_name
                            : ( $deleted_by ? sprintf( __( 'User #%d', Config::TEXTDOMAIN ), $deleted_by ) : __( 'System', Config::TEXTDOMAIN ) );
                        $deleted_at_label = $deleted_at ? mysql2date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $deleted_at, false ) : '—';

                        $messages_label = number_format_i18n( $messages_deleted );
                        if ( $messages_synced || $messages_unsynced ) {
                            $messages_label .= sprintf(
                                ' (%s/%s)',
                                number_format_i18n( $messages_synced ),
                                number_format_i18n( $messages_unsynced )
                            );
                        }

                        $files_label = number_format_i18n( $files_deleted );

                        $error_markup = empty( $log->error_message )
                            ? '<span class="cin-error-none">—</span>'
                            : sprintf(
                                '<details class="cin-error-details"><summary>%s</summary><pre class="cin-error-text">%s</pre></details>',
                                esc_html__( 'View', Config::TEXTDOMAIN ),
                                esc_html( $log->error_message )
                            );
                    ?>
                    <tr>
                        <td class="column-primary">
                            <strong><?php echo esc_html( $contact_name ?: ( $email ?: __( 'Unknown contact', Config::TEXTDOMAIN ) ) ); ?></strong>
                            <?php if ( $contact_id ) : ?>
                                <span class="description"><?php printf( esc_html__( 'ID #%d', Config::TEXTDOMAIN ), $contact_id ); ?></span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <a href="mailto:<?php echo esc_attr( $email ); ?>">
                                <?php echo esc_html( $email ); ?>
                            </a>
                        </td>
                        <td>
                            <span class="cin-status-badge cin-status-<?php echo esc_attr( sanitize_html_class( $crm_status ) ); ?>">
                                <?php echo esc_html( $crm_labels[ $crm_status ] ?? ucfirst( $crm_status ) ); ?>
                            </span>
                        </td>
                        <td><?php echo esc_html( $messages_label ); ?></td>
                        <td><?php echo esc_html( $files_label ); ?></td>
                        <td>
                            <span class="cin-status-badge cin-status-<?php echo esc_attr( sanitize_html_class( $deletion_status ) ); ?>">
                                <?php echo esc_html( $status_labels[ $deletion_status ] ?? ucfirst( $deletion_status ) ); ?>
                            </span>
                        </td>
                        <td><?php echo esc_html( $deleted_by_label ); ?></td>
                        <td><?php echo esc_html( $deleted_at_label ); ?></td>
                        <td><?php echo wp_kses_post( $error_markup ); ?></td>
                    </tr>
                    <?php endforeach; ?>
                <?php else : ?>
                    <tr class="no-items">
                        <td class="colspanchange" colspan="9">
                            <?php esc_html_e( 'No items found.', Config::TEXTDOMAIN ); ?>
                        </td>
                    </tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </form>

    <div id="cin-export-modal" class="cin-modal-overlay" role="dialog" aria-modal="true" aria-labelledby="cin-export-modal-title">
        <div class="cin-confirm-modal">
            <h3 id="cin-export-modal-title"><?php esc_html_e( 'Export GDPR Records', Config::TEXTDOMAIN ); ?></h3>
            <p class="cin-export-meta">
                <?php esc_html_e( 'Total:', Config::TEXTDOMAIN ); ?> <strong id="cin-export-total">0</strong> ·
                <?php esc_html_e( 'Max per file:', Config::TEXTDOMAIN ); ?> <strong id="cin-export-max">1000</strong>
            </p>
            <div class="cin-export-row">
                <label for="cin-export-chunk"><?php esc_html_e( 'Chunk size:', Config::TEXTDOMAIN ); ?></label>
                <input id="cin-export-chunk" type="number" min="1" max="1000" value="500" class="cin-export-chunk">
                <span class="cin-export-hint"><?php esc_html_e( '(Max 1000)', Config::TEXTDOMAIN ); ?></span>
            </div>
            <div id="cin-export-links" class="cin-export-links"></div>
            <div class="cin-export-footer">
                <button type="button" class="button" id="cin-export-close"><?php esc_html_e( 'Close', Config::TEXTDOMAIN ); ?></button>
            </div>
        </div>
    </div>
</div>

