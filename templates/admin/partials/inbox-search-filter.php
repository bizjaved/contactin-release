<?php
/**
 * Inbox Search Bar + CSV Export
 *
 * @package ContactInbox\Admin
 */
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

use ContactInbox\Core\Config;

// Safe defaults
$search         = $search ?? ( $_GET['s'] ?? '' );
$current_status = $current_status ?? ( $_GET['status'] ?? 'all' );
$contact_id     = isset( $contact_id ) ? (int) $contact_id : (int) ( $_GET['contact_id'] ?? 0 );
$page_slug      = sanitize_key( $_GET['page'] ?? '' );
$folder         = sanitize_key( $_GET['folder'] ?? '' );
$base_url       = $base_url ?? admin_url( 'admin.php?page=' . ( $page_slug ?: Config::MENU_INBOX ) );
if ( $page_slug === Config::MENU_INBOX_UNIFIED ) {
    if ( $folder === 'spam' ) {
        $current_status = Config::STATUS_SPAM;
    } elseif ( $folder === 'archived' ) {
        $current_status = Config::STATUS_ARCHIVED;
    } elseif ( ! in_array( $current_status, ['all', Config::STATUS_READ, Config::STATUS_UNREAD], true ) ) {
        $current_status = 'all';
    }
}
if ( $current_status === 'all' ) {
    if ( $page_slug === Config::MENU_SPAM ) {
        $current_status = Config::STATUS_SPAM;
    } elseif ( $page_slug === Config::MENU_ARCHIVED ) {
        $current_status = Config::STATUS_ARCHIVED;
    }
}
if ( $contact_id ) {
    $base_url = add_query_arg( 'contact_id', $contact_id, $base_url );
}
if ( $current_status !== 'all' ) {
    $base_url = add_query_arg( 'status', $current_status, $base_url );
}

$base_args = [];
if ( $contact_id ) {
    $base_args['contact_id'] = $contact_id;
}
if ( $current_status !== 'all' ) {
    $base_args['status'] = $current_status;
}
?>

<div class="cin-inbox-filter">

    <!-- Active Filters Display -->
    <?php if ( ! empty( $search ) || $current_status !== 'all' ) : ?>
        <div style="display:flex;gap:8px;margin-bottom:10px;flex-wrap:wrap;align-items:center;">
            <span style="font-size:12px;font-weight:600;text-transform:uppercase;color:#646970;"><?php esc_html_e( 'Filters:', Config::TEXTDOMAIN ); ?></span>
            <?php if ( ! empty( $search ) ) : ?>
                <span style="display:inline-flex;align-items:center;gap:6px;background:#f0f6fc;border:1px solid #0073aa;border-radius:3px;padding:4px 8px;font-size:12px;">
                    <span>🔍</span>
                    <span><?php echo esc_html( $search ); ?></span>
                    <a href="<?php echo esc_url( add_query_arg( array_merge( $base_args, ['s' => ''] ), $base_url ) ); ?>" title="<?php esc_attr_e( 'Remove search', Config::TEXTDOMAIN ); ?>" style="text-decoration:none;font-weight:bold;">&times;</a>
                </span>
            <?php endif; ?>

        </div>
    <?php endif; ?>

    <!-- Search + CSV Layout -->
    <div style="display:flex;justify-content:space-between;align-items:center;margin:0 0 0 0;padding:0;gap:1rem;min-height:36px;flex-wrap:nowrap;">
        <!-- Left: Search Controls -->
        <div style="display:flex;gap:8px;align-items:center;flex-shrink:0;">
            <label class="screen-reader-text" for="contactin-search-input">
                <?php esc_html_e( 'Search Messages', Config::TEXTDOMAIN ); ?>
            </label>
            <input type="search"
                   id="contactin-search-input"
                   name="s"
                   value="<?php echo esc_attr( $search ); ?>"
                   placeholder="<?php esc_attr_e( 'Search name, email, subject or message...', Config::TEXTDOMAIN ); ?>"
                   data-search-term="<?php echo esc_attr( $search ); ?>"
                   style="width:350px;padding:6px 10px;border:1px solid #ddd;border-radius:4px;" />
            <input type="submit" id="search-submit" class="button" value="<?php esc_attr_e( 'Search', Config::TEXTDOMAIN ); ?>">

            <?php if ( ! empty( $search ) || $current_status !== 'all' ) : ?>
                <a href="<?php echo esc_url( add_query_arg( array_merge( $base_args, ['s' => '', 'paged' => ''] ), $base_url ) ); ?>" class="button">
                    <?php esc_html_e( 'Clear', Config::TEXTDOMAIN ); ?>
                </a>
            <?php endif; ?>
        </div>

        <!-- Right: CSV Export -->
        <div style="display:flex;align-items:center;margin-left:auto;flex-shrink:0;">
            <?php
                $export_url = wp_nonce_url(
                    admin_url(
                        'admin-ajax.php?action=ci_export_csv&s=' . urlencode($search) . '&status=' . urlencode($current_status) . ( $contact_id ? '&contact_id=' . (int) $contact_id : '' )
                    ),
                    Config::INBOX_NONCE_ACTION
                );
                $is_free = defined('CONTACTINBOX_IS_FREE') && CONTACTINBOX_IS_FREE;
            ?>
            <a href="<?php echo $is_free ? '#' : esc_url( $export_url ); ?>"
               class="button button-primary cin-download-csv<?php echo $is_free ? ' disabled contactinbox-show-upgrade-modal' : ''; ?>"
               data-url="<?php echo esc_url( $export_url ); ?>"
               data-search="<?php echo esc_attr( $search ); ?>"
               data-status="<?php echo esc_attr( $current_status ); ?>"
               data-contact-id="<?php echo esc_attr( $contact_id ); ?>"
               <?php echo $is_free ? 'aria-disabled="true" tabindex="-1"' : 'download'; ?> >
                <span class="dashicons dashicons-download"></span>
                <?php esc_html_e( Config::EXPORT_MSG_DEFAULT, Config::TEXTDOMAIN ); ?>
                <?php if ( $is_free ) : ?>
                    <span style="margin-left: 4px; background: #dc3545; color: white; padding: 1px 4px; border-radius: 2px; font-size: 9px; font-weight: bold;">PRO</span>
                <?php endif; ?>
            </a>
        </div>
    </div>

</div><!-- .cin-inbox-filter -->
