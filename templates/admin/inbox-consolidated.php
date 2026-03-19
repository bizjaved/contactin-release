<?php
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals, WordPress.WP.I18n.MissingTranslatorsComment
/**
 * Consolidated Inbox Admin Page Template
 *
 * Unified view combining Main Inbox, Spam, and Archives in tabs
 * This page aggregates all three message folders into one interface
 *
 * @package ContactInbox\Admin
 */

if ( ! defined( 'ABSPATH' ) ) exit;

use ContactInbox\Core\Config;

// Variables passed from display_page()
$messages      = $messages ?? [];
$paged         = (int) ($paged ?? 1);
$pages         = (int) ($pages ?? 1);
$search        = $search ?? '';
$folder_input  = filter_input( INPUT_GET, 'folder', FILTER_SANITIZE_FULL_SPECIAL_CHARS );
$folder        = sanitize_key( is_string( $folder_input ) ? wp_unslash( $folder_input ) : 'main' ); // main, spam, or archived
$status        = $status ?? 'all';
$intent        = $intent ?? 'all';
$total_items   = (int) ($total_items ?? 0);
$unread_count  = (int) ($unread_count ?? 0);
$orderby       = $orderby ?? 'submitted_at';
$order         = $order ?? 'DESC';
$per_page      = (int) ($per_page ?? Config::INBOX_PER_PAGE);
$contact_id    = isset($contact_id) ? (int) $contact_id : 0;

$base_url = add_query_arg( ['page' => Config::MENU_INBOX_UNIFIED], admin_url('admin.php') );
if ( $contact_id ) {
    $base_url = add_query_arg( 'contact_id', $contact_id, $base_url );
}
if ( $folder && $folder !== 'main' ) {
    $base_url = add_query_arg( 'folder', $folder, $base_url );
}

// Safeguard: recompute total pages based on total_items and per_page
$per_page_safe = max( 1, $per_page );
$pages         = max( 1, (int) ceil( $total_items / $per_page_safe ) );

$pagination_args = [
    'base'      => add_query_arg( 'paged', '%#%', $base_url ),
    'format'    => '',
    'current'   => max( 1, $paged ),
    'total'     => max( 1, $pages ),
    'type'      => 'plain',
    'add_args'  => [
        's'        => $search,
        'folder'   => $folder,
        'status'   => $status,
        'intent'   => $intent,
        'per_page' => $per_page,
        'orderby'  => $orderby,
        'order'    => $order,
        'contact_id' => $contact_id,
    ],
];

$extra_query_args = $extra_query_args ?? [];
if ( $contact_id ) {
    $extra_query_args = array_merge( ['contact_id' => $contact_id], $extra_query_args );
}

// Get message counts for each folder
$db = \ContactInbox\Core\DB::instance();
$count_main = $db->get_total_messages( '', 'all', $contact_id );
$count_spam = $db->get_total_messages( '', Config::STATUS_SPAM, $contact_id );
$count_archived = $db->get_total_messages( '', Config::STATUS_ARCHIVED, $contact_id );

$inbox_consolidated_inline_js = <<<'JS'
(function($) {
    'use strict';

    window.cinConsolidatedInbox = {
        switchFolder: function(button) {
            const folder = $(button).data('folder');
            const currentUrl = new URL(window.location.href);
            currentUrl.searchParams.set('folder', folder);
            currentUrl.searchParams.set('paged', '1');
            window.location.href = currentUrl.toString();
        }
    };
    
    $(document).on('change', '#status-filter', function() {
        $('input[name="paged"]').val(1);
        $('#messages-filter').submit();
    });
    
    $(document).on('change', '#intent-filter', function() {
        $('input[name="intent"]').val($(this).val());
        $('input[name="paged"]').val(1);
        $('#messages-filter').submit();
    });
    
    $(document).on('change', '.cin-per-page-select', function() {
        $('input[name="paged"]').val(1);
        $('#messages-filter').submit();
    });
    
    $(document).on('click', '#search-submit', function() {
        $('input[name="paged"]').val(1);
    });

    $(document).on('focus', 'input[type="text"], input[type="search"], textarea, input[type="email"]', function() {
        if (window.keyboardShortcutsEnabled !== undefined) {
            window.keyboardShortcutsEnabled = false;
        }
    });
    
    $(document).on('blur', 'input[type="text"], input[type="search"], textarea, input[type="email"]', function() {
        if (window.keyboardShortcutsEnabled !== undefined) {
            window.keyboardShortcutsEnabled = true;
        }
    });
})(jQuery);
JS;
wp_add_inline_script('contactin-admin-inbox', $inbox_consolidated_inline_js);
?>

<div class="wrap cin-inbox-page">

    <!-- PAGE HEADER -->
    <div class="cin-page-header">
        <div>
            <h1><?php esc_html_e( 'Inbox', 'contact-inbox' ); ?></h1>
            <span class="cin-header-count"><?php echo esc_html( sprintf(
                __('(%s messages)', 'contact-inbox'),
                number_format_i18n( $total_database_messages ?? $total_items )
            ) ); ?></span>
        </div>
        <div>
            <button type="button" class="button button-secondary"
                    data-cin-help-open="cin-inbox-help-modal"
                    aria-haspopup="dialog"
                    aria-controls="cin-inbox-help-modal">
                <?php esc_html_e('Help', 'contact-inbox'); ?>
            </button>
            <button type="button" class="button cin-icon-button"
                    onclick="window.cinInboxKeyboardShortcuts && window.cinInboxKeyboardShortcuts()"
                    title="<?php esc_attr_e('Keyboard Shortcuts', 'contact-inbox'); ?>">
                ⌨️
            </button>
        </div>
    </div>

    <div class="cin-inbox-shell">
        <!-- TAB NAVIGATION HEADER -->
        <div class="cin-consolidated-tabs-header">
            <nav class="cin-consolidated-tabs-nav" role="tablist">
                <!-- Main Inbox Tab -->
                <button class="cin-consolidated-tab-button <?php echo $folder === 'main' ? 'cin-tab-active' : ''; ?>" 
                        role="tab" 
                        aria-selected="<?php echo $folder === 'main' ? 'true' : 'false'; ?>" 
                        aria-controls="cin-main-folder-panel"
                        data-folder="main"
                        onclick="window.cinConsolidatedInbox && window.cinConsolidatedInbox.switchFolder(this)">
                    <span class="dashicons dashicons-email-alt"></span>
                    <span><?php esc_html_e('Main', 'contact-inbox'); ?></span>
                    <span class="cin-tab-badge"><?php echo esc_html( number_format_i18n($count_main) ); ?></span>
                </button>

                <!-- Spam Tab -->
                <button class="cin-consolidated-tab-button <?php echo $folder === 'spam' ? 'cin-tab-active' : ''; ?>" 
                        role="tab" 
                        aria-selected="<?php echo $folder === 'spam' ? 'true' : 'false'; ?>" 
                        aria-controls="cin-spam-folder-panel"
                        data-folder="spam"
                        onclick="window.cinConsolidatedInbox && window.cinConsolidatedInbox.switchFolder(this)">
                    <span class="dashicons dashicons-shield-alt"></span>
                    <span><?php esc_html_e('Spam', 'contact-inbox'); ?></span>
                    <span class="cin-tab-badge"><?php echo esc_html( number_format_i18n($count_spam) ); ?></span>
                </button>

                <!-- Archives Tab -->
                <button class="cin-consolidated-tab-button <?php echo $folder === 'archived' ? 'cin-tab-active' : ''; ?>" 
                        role="tab" 
                        aria-selected="<?php echo $folder === 'archived' ? 'true' : 'false'; ?>" 
                        aria-controls="cin-archived-folder-panel"
                        data-folder="archived"
                        onclick="window.cinConsolidatedInbox && window.cinConsolidatedInbox.switchFolder(this)">
                    <span class="dashicons dashicons-archive"></span>
                    <span><?php esc_html_e('Archives', 'contact-inbox'); ?></span>
                    <span class="cin-tab-badge"><?php echo esc_html( number_format_i18n($count_archived) ); ?></span>
                </button>
            </nav>
        </div>

        <!-- MESSAGES TAB PANEL -->
        <div class="cin-messages-container">
            <!-- MAIN FORM -->
        <form method="get" id="messages-filter" class="cin-inbox-form">
                <input type="hidden" name="page" value="<?php echo esc_attr( Config::MENU_INBOX_UNIFIED ); ?>">
                <input type="hidden" name="folder" value="<?php echo esc_attr($folder); ?>">
                <?php if ( $contact_id ) : ?>
                    <input type="hidden" name="contact_id" value="<?php echo esc_attr( $contact_id ); ?>">
                <?php endif; ?>
                <input type="hidden" name="paged" value="<?php echo esc_attr( $paged ); ?>">
                <input type="hidden" name="orderby" value="<?php echo esc_attr( $orderby ); ?>">
                <input type="hidden" name="order" value="<?php echo esc_attr( $order ); ?>">
                <input type="hidden" name="intent" value="<?php echo esc_attr( $intent ); ?>">

                <!-- SECTION 1: Search & Export -->
                <?php
                load_template(
                    CONTACTINBOX_PATH . Config::TEMPLATE_ADMIN_PART . 'inbox-search-filter.php',
                    false,
                    [
                        'search'         => $search,
                        'current_status' => $folder === 'spam'
                            ? Config::STATUS_SPAM
                            : ( $folder === 'archived' ? Config::STATUS_ARCHIVED : $status ),
                        'base_url'       => $base_url,
                        'contact_id'     => $contact_id,
                    ]
                );
                ?>

                <!-- SECTION 2: Table Navigation (filters + bulk + pagination in one line) -->
                <div class="tablenav top cin-inbox-tablenav">
                    <div class="alignleft actions">
                        <!-- Status filter -->
                        <?php if ( $folder === 'main' ) : ?>
                            <label for="status-filter" class="screen-reader-text"><?php esc_html_e( 'Filter by status', 'contact-inbox' ); ?></label>
                            <select id="status-filter" name="status" class="cin-status-filter">
                                <option value="all" <?php selected( $status, 'all' ); ?>><?php esc_html_e( 'All', 'contact-inbox' ); ?></option>
                                <option value="read" <?php selected( $status, 'read' ); ?>><?php esc_html_e( 'Read', 'contact-inbox' ); ?></option>
                                <option value="unread" <?php selected( $status, 'unread' ); ?>><?php esc_html_e( 'Unread', 'contact-inbox' ); ?></option>
                            </select>
                        <?php else : ?>
                            <input type="hidden" name="status" value="<?php echo esc_attr( $status ); ?>">
                            <span class="cin-status-pill">
                                <?php echo $folder === 'spam' ? esc_html__( 'Spam', 'contact-inbox' ) : esc_html__( 'Archived', 'contact-inbox' ); ?>
                            </span>
                        <?php endif; ?>

                        <!-- Intent filter (hidden on spam and archive tabs) -->
                        <?php
                        $settings = \ContactInbox\Core\Settings::get_settings();
                        if (!empty($settings['intent_enable']) && $folder === 'main'):
                            $intent_categories = \ContactInbox\Core\IntentClassifier::get_categories();
                        ?>
                        <label for="intent-filter" class="screen-reader-text"><?php esc_html_e( 'Filter by intent', 'contact-inbox' ); ?></label>
                        <select id="intent-filter" name="intent" class="cin-intent-filter">
                            <option value="all" <?php selected( $intent, 'all' ); ?>><?php esc_html_e( 'All Intents', 'contact-inbox' ); ?></option>
                            <?php foreach ($intent_categories as $cat_key => $cat_label): 
                                // Skip spam category - use spam tab for spam handling
                                if ($cat_key === 'spam') {
                                    continue;
                                }
                            ?>
                                <option value="<?php echo esc_attr($cat_key); ?>" <?php selected( $intent, $cat_key ); ?>>
                                    <?php echo esc_html($cat_label); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <?php endif; ?>

                        <div id="filter-loading-indicator" class="cin-loading-indicator"></div>

                        <!-- Bulk Actions -->
                        <label for="bulk-action-selector-top" class="screen-reader-text"><?php esc_html_e( 'Bulk actions', 'contact-inbox' ); ?></label>
                        <select name="action" id="bulk-action-selector-top" class="cin-bulk-action">
                            <option value="-1"><?php esc_html_e( 'Bulk actions', 'contact-inbox' ); ?></option>
                            <?php
                            // Determine current status from folder parameter
                            $bulk_folder_input = filter_input( INPUT_GET, 'folder', FILTER_SANITIZE_FULL_SPECIAL_CHARS );
                            $bulk_folder = sanitize_key( is_string( $bulk_folder_input ) ? wp_unslash( $bulk_folder_input ) : 'main' );
                            if ($bulk_folder === 'spam') {
                                $bulk_status = Config::STATUS_SPAM;
                            } elseif ($bulk_folder === 'archived') {
                                $bulk_status = Config::STATUS_ARCHIVED;
                            } else {
                                $bulk_status = 'all';
                            }
                            $bulk_actions = \ContactInbox\Admin\Helpers\InboxActionHelper::get_bulk_actions($bulk_status);
                            foreach ($bulk_actions as $value => $label) :
                            ?>
                                <option value="<?php echo esc_attr($value); ?>"><?php echo esc_html($label); ?></option>
                            <?php endforeach; ?>
                        </select>
                        <div id="bulk-loading-indicator" class="cin-loading-indicator"></div>

                        <!-- Unread Count Badge -->
                        <div class="cin-unread-count">
                            <span class="cin-count-label"><?php esc_html_e( 'Unread:', 'contact-inbox' ); ?></span>
                            <span class="cin-count-value"><?php echo esc_html( number_format_i18n( $unread_count ) ); ?></span>
                        </div>
                    </div>

                    <div class="tablenav-pages">
                        <span class="displaying-num"><?php echo esc_html( number_format_i18n( $total_items ) ); ?> <?php esc_html_e( 'items', 'contact-inbox' ); ?></span>

                        <!-- Per page selector -->
                        <label for="per-page" class="cin-per-page-label"><?php esc_html_e( 'Rows per page', 'contact-inbox' ); ?></label>
                        <select id="per-page" name="per_page" class="cin-per-page-select">
                            <option value="20" <?php selected( $per_page, 20 ); ?>>20</option>
                            <option value="50" <?php selected( $per_page, 50 ); ?>>50</option>
                            <option value="100" <?php selected( $per_page, 100 ); ?>>100</option>
                        </select>

                        <?php
                        $pagination_top = $pagination_args;
                        $pagination_top['prev_text'] = __( 'Prev', 'contact-inbox' );
                        $pagination_top['next_text'] = __( 'Next', 'contact-inbox' );
                        echo wp_kses_post( paginate_links( $pagination_top ) );
                        ?>
                    </div>
                </div>

                <!-- THE TABLE -->
                <div class="wp-list-table-container">
                    <?php
                    load_template(
                        CONTACTINBOX_PATH . Config::TEMPLATE_ADMIN_PART . 'inbox-table.php',
                        false,
                        [
                            'messages' => $messages,
                            'paged'    => $paged,
                            'pages'    => $pages,
                            'search'   => $search,
                            'status'   => $status,
                            'orderby'  => $orderby,
                            'order'    => $order,
                            'per_page' => $per_page,
                            'extra_query_args' => $extra_query_args,
                        ]
                    );
                    ?>
                </div>

                <!-- Bottom pagination intentionally hidden to match log pages -->

        </form><!-- #messages-filter -->
        </div><!-- .cin-messages-container -->
    </div><!-- .cin-inbox-shell -->

    <!-- Export Modal -->
    <?php load_template( CONTACTINBOX_PATH . Config::TEMPLATE_ADMIN_PART . 'export-modal.php' ); ?>

    <!-- Classification Change Modal -->
    <?php load_template( CONTACTINBOX_PATH . Config::TEMPLATE_ADMIN_PART . 'classification-modal.php' ); ?>

    <!-- Support Boxes -->
    <div class="cin-support-boxes">
        <div class="cin-support-box">
            <?php load_template( CONTACTINBOX_PATH . Config::TEMPLATE_ADMIN_PART . 'upgrade-box.php' ); ?>
        </div>
        <div class="cin-support-box">
            <?php load_template( CONTACTINBOX_PATH . Config::TEMPLATE_ADMIN_PART . 'review-box.php' ); ?>
        </div>
    </div>

    <?php load_template( CONTACTINBOX_PATH . Config::TEMPLATE_ADMIN_PART . 'inbox-help-modal.php' ); ?>

</div><!-- .wrap.cin-inbox-page -->
            window.keyboardShortcutsEnabled = true;
