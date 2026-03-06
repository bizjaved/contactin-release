<?php
use ContactInbox\Core\Config;
?>
<div class="tablenav top">
    <div class="alignleft actions">
        <label for="status-filter" class="screen-reader-text">
            <?php esc_html_e( 'Filter by status', 'contact-inbox' ); ?>
        </label>
        <select id="status-filter" name="status">
            <option value="all"><?php esc_html_e( 'All Statuses', 'contact-inbox' ); ?></option>
            <option value="sent"><?php esc_html_e( 'Sent', 'contact-inbox' ); ?></option>
            <option value="failed"><?php esc_html_e( 'Failed', 'contact-inbox' ); ?></option>
            <option value="pending"><?php esc_html_e( 'Pending', 'contact-inbox' ); ?></option>
        </select>

        <button type="button" class="button button-secondary" id="contactin-prune-logs">
            <?php esc_html_e( 'Prune Old Logs', 'contact-inbox' ); ?>
        </button>

        <button type="button" class="button button-secondary cin-download-csv">
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
