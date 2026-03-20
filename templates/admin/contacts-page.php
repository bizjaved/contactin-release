<?php
// phpcs:disable WordPress.Security.EscapeOutput.OutputNotEscaped, WordPress.NamingConventions.PrefixAllGlobals
/**
 * Contacts Admin Page
 */

use ContactInbox\Core\Config;
use ContactInbox\Core\PhoneUtils;

if (!defined('ABSPATH')) exit;

$base_url = admin_url('admin.php?page=' . Config::MENU_CONTACTS);
?>
<div class="wrap cin-contacts-page">
    <!-- PAGE HEADER -->
    <div class="cin-page-header">
        <div>
            <h1><?php esc_html_e('Contacts', 'contact-inbox'); ?></h1>
            <span class="cin-header-count"><?php printf(
                /* translators: %s: number of contacts. */
                esc_html__('(%s contacts)', 'contact-inbox'),
                number_format_i18n( $total_items ?? 0 )
            ); ?></span>
        </div>
        <div>
            <!-- Optional: Help or action buttons can be added here -->
        </div>
    </div>

    <div class="cin-contacts-shell">
        <!-- MAIN FORM -->
        <form method="get" id="contacts-filter" class="cin-contacts-form">
            <input type="hidden" name="page" value="<?php echo esc_attr(Config::MENU_CONTACTS); ?>" />
            <input type="hidden" name="paged" value="<?php echo esc_attr($paged); ?>" />
            <input type="hidden" name="orderby" value="<?php echo esc_attr($orderby); ?>" />
            <input type="hidden" name="order" value="<?php echo esc_attr($order); ?>" />
            <input type="hidden" name="ci_contact_deletion_nonce" value="<?php echo esc_attr(wp_create_nonce('ci_contact_deletion')); ?>" />

            <!-- SECTION 1: Search & Export -->
            <?php if ( ! empty( $search ) ) : ?>
                <div class="cin-active-filters">
                    <span class="cin-filters-label"><?php esc_html_e('Filters:', 'contact-inbox'); ?></span>
                    <span class="cin-filter-badge">
                        <span>🔍</span>
                        <span><?php echo esc_html($search); ?></span>
                        <a href="<?php echo esc_url(remove_query_arg(['s', 'paged'], $base_url)); ?>" title="<?php esc_attr_e('Remove search', 'contact-inbox'); ?>">&times;</a>
                    </span>
                </div>
            <?php endif; ?>

            <div class="cin-search-export-row">
                <div class="cin-search-controls">
                    <label class="screen-reader-text" for="contacts-search-input"><?php esc_html_e('Search Contacts', 'contact-inbox'); ?></label>
                    <input type="search" id="contacts-search-input" name="s" value="<?php echo esc_attr($search); ?>" placeholder="<?php esc_attr_e('Search contacts by name, email, or phone...', 'contact-inbox'); ?>" />
                    <input type="submit" id="search-submit" class="button" value="<?php esc_attr_e('Search', 'contact-inbox'); ?>">
                    <?php if ( ! empty( $search ) ) : ?>
                        <a href="<?php echo esc_url(remove_query_arg(['s', 'paged'], $base_url)); ?>" class="button"><?php esc_html_e('Clear', 'contact-inbox'); ?></a>
                    <?php endif; ?>
                </div>

                <div class="cin-export-controls">
                    <?php $export_url = wp_nonce_url(add_query_arg(['action' => 'contactinbox_contacts_export', 's' => $search, 'orderby' => $orderby, 'order' => $order], admin_url('admin-ajax.php')), 'contactinbox_contacts_export'); ?>
                    <span style="display: inline-flex; align-items: center;">
                        <button type="button" class="button button-primary cin-contacts-export-btn" data-url="<?php echo esc_url($export_url); ?>" data-search="<?php echo esc_attr($search); ?>" data-nonce="<?php echo esc_attr(wp_create_nonce('contactinbox_contacts_export')); ?>" <?php disabled($total_items === 0); ?>>
                            <span class="dashicons dashicons-download"></span>
                            <?php esc_html_e('Export CSV', 'contact-inbox'); ?>
                        </button>
                        <?php if ( defined('CONTACTINBOX_IS_FREE') && CONTACTINBOX_IS_FREE ) : ?>
                            <?php \ContactInbox\Admin\Helpers\UpgradeModalHelper::render_badge( 'margin-left: 4px; padding: 1px 4px; border-radius: 2px; font-size: 9px;' ); ?>
                        <?php endif; ?>
                    </span>
                </div>
            </div>

            <!-- SECTION 2: Table Navigation (pagination) -->
            <div class="tablenav top">
                <div class="tablenav-pages">
                    <span class="displaying-num"><?php echo esc_html(number_format_i18n($total_items)); ?> <?php esc_html_e('items', 'contact-inbox'); ?></span>
                    <label for="contacts-per-page" class="cin-per-page-label"><?php esc_html_e('Rows per page', 'contact-inbox'); ?></label>
                    <select name="per_page" id="contacts-per-page" class="cin-per-page-select" onchange="document.getElementById('contacts-filter').submit();">
                        <option value="20" <?php selected($per_page, 20); ?>>20</option>
                        <option value="50" <?php selected($per_page, 50); ?>>50</option>
                        <option value="100" <?php selected($per_page, 100); ?>>100</option>
                    </select>
                    <?php if ($pages > 1) : ?>
                        <?php $pagination_args = ['base' => add_query_arg('paged', '%#%', $base_url), 'format' => '', 'current' => max(1, $paged), 'total' => max(1, $pages), 'type' => 'plain', 'prev_text' => esc_html__('Prev', 'contact-inbox'), 'next_text' => esc_html__('Next', 'contact-inbox'), 'add_args' => ['s' => $search, 'per_page' => $per_page, 'orderby' => $orderby, 'order' => $order]]; ?>
                        <?php echo paginate_links($pagination_args); ?>
                    <?php endif; ?>
                </div>
            </div>

            <div class="wp-list-table-container">
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th class="sortable <?php echo $orderby === 'name' ? 'sorted' : ''; ?> <?php echo $orderby === 'name' ? strtolower($order) : 'desc'; ?>">
                                <a href="<?php echo esc_url(add_query_arg(['orderby' => 'name', 'order' => ($orderby === 'name' && $order === 'ASC' ? 'DESC' : 'ASC'), 's' => $search, 'per_page' => $per_page, 'paged' => 1], $base_url)); ?>">
                                    <span><?php esc_html_e('Name', 'contact-inbox'); ?></span>
                                    <span class="sorting-indicator"></span>
                                </a>
                            </th>
                            <th><?php esc_html_e('Email', 'contact-inbox'); ?></th>
                            <th><?php esc_html_e('Phones', 'contact-inbox'); ?></th>
                            <th class="sortable <?php echo $orderby === 'last_message_at' ? 'sorted' : ''; ?> <?php echo $orderby === 'last_message_at' ? strtolower($order) : 'desc'; ?>">
                                <a href="<?php echo esc_url(add_query_arg(['orderby' => 'last_message_at', 'order' => ($orderby === 'last_message_at' && $order === 'ASC' ? 'DESC' : 'ASC'), 's' => $search, 'per_page' => $per_page, 'paged' => 1], $base_url)); ?>">
                                    <span><?php esc_html_e('Last Activity', 'contact-inbox'); ?></span>
                                    <span class="sorting-indicator"></span>
                                </a>
                            </th>
                            <th class="table-col-actions"><?php esc_html_e('Actions', 'contact-inbox'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($contacts_list)) : ?>
                            <tr class="no-items"><td colspan="5"><?php esc_html_e('No contacts found.', 'contact-inbox'); ?></td></tr>
                        <?php else : ?>
                            <?php foreach ($contacts_list as $contact) : ?>
                                <?php $detail_url = add_query_arg('contact_id', $contact->id, $base_url); ?>
                                <tr id="contactin-row-<?php echo esc_attr($contact->id); ?>">
                                    <td><a href="<?php echo esc_url($detail_url); ?>" class="cin-contact-link"><?php echo esc_html($contact->name); ?></a></td>
                                    <td><?php echo $contact->email ? '<a href="mailto:' . esc_attr($contact->email) . '">' . esc_html($contact->email) . '</a>' : '&mdash;'; ?></td>
                                    <td>
                                        <?php
                                        $phones = array_filter([
                                            $contact->mobile_phone,
                                            $contact->primary_phone,
                                            $contact->home_phone,
                                            $contact->other_phone,
                                        ]);
                                        if (empty($phones)) {
                                            echo '&mdash;';
                                        } else {
                                            $formatted = array_map(fn($p) => esc_html(PhoneUtils::format($p, 'international')), $phones);
                                            echo implode('<br>', $formatted);
                                        }
                                        ?>
                                    </td>
                                    <td><?php echo $contact->last_message_at ? esc_html(get_date_from_gmt($contact->last_message_at, 'M j, Y g:i A')) : '&mdash;'; ?></td>
                                    <td class="column-actions actions" data-label="<?php esc_attr_e('Actions', 'contact-inbox'); ?>">
                                        <div class="cin-row-actions">
                                            <button type="button" class="cin-btn cin-btn-icon cin-btn-primary cin-edit-contact-btn" 
                                                    data-contact-id="<?php echo esc_attr($contact->id); ?>"
                                                    data-nonce="<?php echo esc_attr(wp_create_nonce('ci_update_contact')); ?>"
                                                    title="<?php esc_attr_e('Edit Contact', 'contact-inbox'); ?>">
                                                <span class="dashicons dashicons-edit"></span>
                                            </button>
                                            <button type="button" class="cin-btn cin-btn-icon cin-btn-primary cin-action-view" data-href="<?php echo esc_url($detail_url); ?>" title="<?php esc_attr_e('View Contact', 'contact-inbox'); ?>">
                                                <span class="dashicons dashicons-visibility"></span>
                                            </button>
                                            <?php if (!empty($contact->last_message_id)) : ?>
                                                <button type="button" class="cin-btn cin-btn-icon cin-btn-primary contactin-gdpr"
                                                        data-id="<?php echo esc_attr($contact->last_message_id); ?>"
                                                        data-email="<?php echo esc_attr($contact->email); ?>"
                                                        data-nonce="<?php echo esc_attr(wp_create_nonce(Config::GDPR_NONCE_ACTION)); ?>"
                                                        title="<?php esc_attr_e('GDPR Delete Link', 'contact-inbox'); ?>">
                                                    <span class="dashicons dashicons-privacy"></span>
                                                </button>
                                            <?php else : ?>
                                                <button type="button" class="cin-btn cin-btn-icon" disabled
                                                        title="<?php esc_attr_e('GDPR link unavailable (no messages)', 'contact-inbox'); ?>">
                                                    <span class="dashicons dashicons-privacy"></span>
                                                </button>
                                            <?php endif; ?>
                                            <button type="button" class="cin-btn cin-btn-icon cin-btn-danger cin-delete-contact-btn"
                                                    data-contact-id="<?php echo esc_attr($contact->id); ?>"
                                                    title="<?php esc_attr_e('Delete this contact', 'contact-inbox'); ?>">
                                                <span class="dashicons dashicons-trash"></span>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </form>
    </div>

    <!-- Export Modal -->
    <div id="cin-export-modal" class="cin-modal-overlay" role="dialog" aria-modal="true" aria-labelledby="cin-export-modal-title">
        <div class="cin-confirm-modal">
            <h3 id="cin-export-modal-title"><?php esc_html_e('Export Contacts', 'contact-inbox'); ?></h3>
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

    <?php
    // Include Contact Edit Modal
    load_template(
        CONTACTINBOX_PATH . Config::TEMPLATE_ADMIN_PART . 'contact-edit-modal.php',
        false
    );
    ?>

    <?php
    // Include GDPR link generation modal
    load_template(
        CONTACTINBOX_PATH . Config::TEMPLATE_ADMIN_PART . 'gdpr-modal.php',
        false
    );
    ?>
</div>
<?php
ob_start();
?>
jQuery(document).ready(function($) {
    $(document).on('click', '.cin-action-view', function() {
        const href = $(this).data('href');
        if (href) {
            window.location.href = href;
        }
    });
});
<?php
$contacts_inline_js = trim((string) ob_get_clean());
wp_add_inline_script('contactin-admin-inbox', $contacts_inline_js);
?>
