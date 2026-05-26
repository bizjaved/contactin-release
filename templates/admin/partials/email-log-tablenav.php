<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use ContactInbox\Core\Config;

// phpcs:disable WordPress.WP.I18n.NonSingularStringLiteralDomain, WordPress.Security.EscapeOutput.OutputNotEscaped
?>
<div class="tablenav top">
	<div class="alignleft actions">
		<label for="status-filter" class="screen-reader-text">
			<?php esc_html_e( 'Filter by status', 'contactin' ); ?>
		</label>
		<select id="status-filter" name="status">
			<option value="all"><?php esc_html_e( 'All Statuses', 'contactin' ); ?></option>
			<option value="sent"><?php esc_html_e( 'Sent', 'contactin' ); ?></option>
			<option value="failed"><?php esc_html_e( 'Failed', 'contactin' ); ?></option>
			<option value="pending"><?php esc_html_e( 'Pending', 'contactin' ); ?></option>
		</select>

		<button type="button" class="button button-secondary" id="contactin-prune-logs">
			<?php esc_html_e( 'Prune Old Logs', 'contactin' ); ?>
		</button>
	</div>

	<div class="tablenav-pages">
		<?php echo wp_kses_post( $pagination ); ?>
	</div>
</div>
