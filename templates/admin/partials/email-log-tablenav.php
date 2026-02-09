<?php
use ContactInbox\Core\Config;
?>
<div class="tablenav top">
    <div class="alignleft actions">
        <label for="status-filter" class="screen-reader-text">
            <?php esc_html_e( 'Filter by status', Config::TEXTDOMAIN ); ?>
        </label>
        <select id="status-filter" name="status">
            <option value="all"><?php esc_html_e( 'All Statuses', Config::TEXTDOMAIN ); ?></option>
            <option value="sent"><?php esc_html_e( 'Sent', Config::TEXTDOMAIN ); ?></option>
            <option value="failed"><?php esc_html_e( 'Failed', Config::TEXTDOMAIN ); ?></option>
            <option value="pending"><?php esc_html_e( 'Pending', Config::TEXTDOMAIN ); ?></option>
        </select>

        <button type="button" class="button button-secondary" id="contactin-prune-logs">
            <?php esc_html_e( 'Prune Old Logs', Config::TEXTDOMAIN ); ?>
        </button>

        <button type="button" class="button button-secondary cin-download-csv">
            <?php esc_html_e( 'Download CSV', Config::TEXTDOMAIN ); ?>
        </button>
    </div>

    <div class="tablenav-pages">
        <?php echo $pagination; ?>
    </div>
</div>
