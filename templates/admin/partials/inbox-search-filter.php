<?php
if (!defined('ABSPATH')) exit;
/**
 * Inbox Search Bar + CSV Export
 *
 * @package ContactIn\Admin
 */
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

use ContactInbox\Core\Config;

// phpcs:disable WordPress.WP.I18n.NonSingularStringLiteralDomain, WordPress.NamingConventions.PrefixAllGlobals, WordPress.Security.ValidatedSanitizedInput, WordPress.Security.NonceVerification, WordPress.WP.I18n.NonSingularStringLiteralText

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
            <span style="font-size:12px;font-weight:600;text-transform:uppercase;color:#646970;"><?php esc_html_e( 'Filters:',  'contactin'); ?></span>
            <?php if ( ! empty( $search ) ) : ?>
                <span style="display:inline-flex;align-items:center;gap:6px;background:#f0f6fc;border:1px solid #0073aa;border-radius:3px;padding:4px 8px;font-size:12px;">
                    <span>🔍</span>
                    <span><?php echo esc_html( $search ); ?></span>
                    <a href="<?php echo esc_url( add_query_arg( array_merge( $base_args, ['s' => ''] ), $base_url ) ); ?>" title="<?php esc_attr_e( 'Remove search',  'contactin'); ?>" style="text-decoration:none;font-weight:bold;">&times;</a>
                </span>
            <?php endif; ?>

        </div>
    <?php endif; ?>

    <!-- Search + CSV Layout -->
    <div style="display:flex;justify-content:space-between;align-items:center;margin:0 0 0 0;padding:0;gap:1rem;min-height:36px;flex-wrap:nowrap;">
        <!-- Left: Search Controls -->
        <div style="display:flex;gap:8px;align-items:center;flex-shrink:0;">
            <label class="screen-reader-text" for="contactin-search-input">
                <?php esc_html_e( 'Search Messages',  'contactin'); ?>
            </label>
            <input type="search"
                   id="contactin-search-input"
                   name="s"
                   value="<?php echo esc_attr( $search ); ?>"
                   placeholder="<?php esc_attr_e( 'Search name, email, subject or message...',  'contactin'); ?>"
                   data-search-term="<?php echo esc_attr( $search ); ?>"
                   style="width:350px;padding:6px 10px;border:1px solid #ddd;border-radius:4px;" />
            <input type="submit" id="search-submit" class="button" value="<?php esc_attr_e( 'Search',  'contactin'); ?>">

            <?php if ( ! empty( $search ) || $current_status !== 'all' ) : ?>
                <a href="<?php echo esc_url( add_query_arg( array_merge( $base_args, ['s' => '', 'paged' => ''] ), $base_url ) ); ?>" class="button">
                    <?php esc_html_e( 'Clear',  'contactin'); ?>
                </a>
            <?php endif; ?>
        </div>

        <!-- Right: CSV Export -->
        <?php if ( \ContactInbox\Integration\FreemiusIntegration::can_use_premium_features() ) : ?>
        <div style="display:flex;align-items:center;margin-left:auto;flex-shrink:0;">
            <?php
                $export_url = wp_nonce_url(
                    admin_url(
                        'admin-ajax.php?action=ci_export_csv&s=' . urlencode($search) . '&status=' . urlencode($current_status) . ( $contact_id ? '&contact_id=' . (int) $contact_id : '' )
                    ),
                    Config::INBOX_NONCE_ACTION
                );
            ?>
            <button type="button"
               class="button button-primary cin-download-csv"
               data-ajax-action="ci_export_csv"
               data-export-info-action="ci_export_info"
               data-url="<?php echo esc_url( $export_url ); ?>"
               data-search="<?php echo esc_attr( $search ); ?>"
               data-status="<?php echo esc_attr( $current_status ); ?>"
               data-contact-id="<?php echo esc_attr( $contact_id ); ?>"
               data-nonce="<?php echo esc_attr( wp_create_nonce( Config::INBOX_NONCE_ACTION ) ); ?>">
                <span class="dashicons dashicons-download"></span>
                <?php esc_html_e( 'Export CSV',  'contactin'); ?>
            </button>
        </div>
        <?php endif; ?>
    </div>

</div><!-- .cin-inbox-filter -->
