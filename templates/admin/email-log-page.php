<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
/**
 * Admin Template: Email Log
 *
 * @package ContactIn
 */

use ContactInbox\Core\Config;

// phpcs:disable WordPress.WP.I18n.NonSingularStringLiteralDomain, WordPress.WP.I18n.MissingTranslatorsComment, WordPress.Security.ValidatedSanitizedInput, WordPress.Security.NonceVerification, WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound, WordPress.Security.EscapeOutput.OutputNotEscaped

$page_param   = \ContactInbox\Admin\Helpers\AdminRequest::get_plugin_page_slug() ?: 'contactin-email-log';
$status_param = \ContactInbox\Admin\Helpers\AdminRequest::is_query_authorized( array( 'status' ), array( Config::EMAIL_LOG_ACTION ), Config::EMAIL_LOG_NONCE )
	? \ContactInbox\Admin\Helpers\AdminRequest::get_request_key( 'status', 'all' )
	: 'all';
?>
<div class="wrap cin-email-log-page">
	<div class="cin-page-header">
		<div>
			<h1><?php esc_html_e( 'Email Log', 'contactin' ); ?></h1>
			<span class="cin-header-count">
				<?php
				printf(
					_n( '(%s email log)', '(%s email logs)', $total_items, 'contactin' ),
					number_format_i18n( $total_items )
				);
				?>
			</span>
		</div>
	</div>

	<div id="contactin-email-notice" class="notice cin-hidden"></div>

	<form id="contactin-email-log-form" method="get">
		<input type="hidden" name="page" value="<?php echo esc_attr( $page_param ); ?>" />
		<?php wp_nonce_field( Config::EMAIL_LOG_ACTION, Config::EMAIL_LOG_NONCE ); ?>

		<!-- Filters and action buttons - using inbox/contacts layout -->
		<div class="tablenav top cin-log-tablenav cin-email-log-tablenav">
			<div class="alignleft actions">
				<!-- Status filter -->
				<label for="status-filter" class="screen-reader-text">
					<?php esc_html_e( 'Filter by status', 'contactin' ); ?>
				</label>
				<select id="status-filter" name="status" class="cin-status-filter-select">
					<option value="all" <?php selected( $status_param, 'all' ); ?>><?php esc_html_e( 'All Statuses', 'contactin' ); ?></option>
					<option value="sent" <?php selected( $status_param, 'sent' ); ?>><?php esc_html_e( 'Sent', 'contactin' ); ?></option>
					<option value="failed" <?php selected( $status_param, 'failed' ); ?>><?php esc_html_e( 'Failed', 'contactin' ); ?></option>
					<option value="pending" <?php selected( $status_param, 'pending' ); ?>><?php esc_html_e( 'Pending', 'contactin' ); ?></option>
				</select>

				<!-- Prune button (handled by admin-email-log.min.js) -->
				<button type="button" class="button button-secondary" id="contactin-prune-email-btn" data-nonce="<?php echo esc_attr( wp_create_nonce( Config::NONCE_ACTION ) ); ?>" <?php disabled( $total_items === 0 ); ?>>
					<?php esc_html_e( 'Prune Old Logs', 'contactin' ); ?>
				</button>

				<!-- Clear All Logs button (handled by admin-email-log.min.js) -->
				<button type="button" class="button button-secondary" id="contactin-clear-email-logs" data-nonce="<?php echo esc_attr( wp_create_nonce( 'contactinbox_email_clear_all_logs' ) ); ?>" <?php disabled( $total_items === 0 ); ?>>
					<?php esc_html_e( 'Clear All Logs', 'contactin' ); ?>
				</button>
			</div>
		</div>

		<div class="tablenav top cin-log-tablenav-pages cin-email-log-tablenav-pages">
			<div class="tablenav-pages">
				<span class="displaying-num"><?php echo esc_html( number_format_i18n( $total_items ) ); ?> <?php esc_html_e( 'items', 'contactin' ); ?></span>

				<!-- Per page filter -->
				<label for="per-page-filter-email" class="cin-per-page-label"><?php esc_html_e( 'Rows per page', 'contactin' ); ?></label>
				<select id="per-page-filter-email" name="per_page" class="cin-per-page-select">
					<option value="20" <?php selected( $per_page, 20 ); ?>>20</option>
					<option value="50" <?php selected( $per_page, 50 ); ?>>50</option>
					<option value="100" <?php selected( $per_page, 100 ); ?>>100</option>
				</select>
				
				<?php
				$pagination_args = array(
					'base'      => add_query_arg(
						array(
							'paged'    => '%#%',
							'per_page' => $per_page,
						)
					),
					'format'    => '',
					'current'   => $current_page,
					'total'     => (int) $table->get_pagination_arg( 'total_pages' ),
					'prev_text' => __( 'Prev', 'contactin' ),
					'next_text' => __( 'Next', 'contactin' ),
					'type'      => 'plain',
				);
				echo wp_kses_post( paginate_links( $pagination_args ) );
				?>
			</div>
		</div>

		<div class="wp-list-table-container">
			<?php
			// Defensive check
			if ( empty( $table->_column_headers ) ) {
				$table->_column_headers = array( $table->get_columns(), array(), $table->get_sortable_columns() );
			}
			// Render the table (includes top and bottom tablenav automatically)
			$table->display();
			?>
		</div>
	</form>

	<!-- Load shared warning modal -->
	<?php load_template( CONTACTINBOX_PATH . Config::TEMPLATE_ADMIN_PART . 'warning-modal.php' ); ?>

	<!-- Email Log Page JS Override -->
	<?php
	ob_start();
	?>
	jQuery(document).ready(function($) {
		'use strict';
		
		// Prune button handler with warning modal
		$('#contactin-prune-email-btn').off('click').on('click', function(e) {
			e.preventDefault();
			const title = '⚠️ Prune Old Email Logs';
			const message = 'This will permanently delete all email logs older than 30 days. This action cannot be undone.';
			
			window.cinShowLogWarningModal({
				title: title,
				message: message,
				logType: 'email logs',
				confirmText: '<?php esc_html_e( 'Prune Logs', 'contactin' ); ?>',
				onConfirm: function() {
					const $btn = $('#contactin-prune-email-btn');
					const nonce = $btn.data('nonce');
					if (!nonce) {
						if (window.cinShowMessage) window.cinShowMessage('Missing security token.', 'error');
						return;
					}
					
					$btn.addClass('is-busy').prop('disabled', true);
					$.post(window.ajaxurl || '', {
						action: 'contactin_prune_email_logs',
						_ajax_nonce: nonce
					}, function(response) {
						if (response.success) {
							if (window.cinShowMessage) window.cinShowMessage(response.data?.message || '<?php esc_html_e( 'Pruned old logs.', 'contactin' ); ?>', 'success');
							setTimeout(() => location.reload(), 1500);
						} else {
							if (window.cinShowMessage) window.cinShowMessage(response.data?.message || '<?php esc_html_e( 'Failed to prune logs.', 'contactin' ); ?>', 'error');
							$btn.removeClass('is-busy').prop('disabled', false);
						}
					}, 'json').fail(() => {
						if (window.cinShowMessage) window.cinShowMessage('<?php esc_html_e( 'Network error.', 'contactin' ); ?>', 'error');
						$btn.removeClass('is-busy').prop('disabled', false);
					});
				}
			});
		});
		
		// Clear button handler with warning modal
		$('#contactin-clear-email-logs').off('click').on('click', function(e) {
			e.preventDefault();
			const title = '⚠️ Clear All Email Logs';
			const message = 'This will PERMANENTLY DELETE ALL email logs. This action cannot be undone. Do you want to continue?';
			
			window.cinShowLogWarningModal({
				title: title,
				message: message,
				logType: 'email logs',
				confirmText: '<?php esc_html_e( 'Delete All', 'contactin' ); ?>',
				confirmStyle: 'danger',
				onConfirm: function() {
					const $btn = $('#contactin-clear-email-logs');
					const nonce = $btn.data('nonce');
					if (!nonce) {
						if (window.cinShowMessage) window.cinShowMessage('Missing security token.', 'error');
						return;
					}
					
					$btn.addClass('is-busy').prop('disabled', true);
					$.post(window.ajaxurl || '', {
						action: 'contactin_email_clear_all_logs',
						_ajax_nonce: nonce
					}, function(response) {
						if (response.success) {
							if (window.cinShowMessage) window.cinShowMessage(response.data?.message || '<?php esc_html_e( 'All logs cleared.', 'contactin' ); ?>', 'success');
							setTimeout(() => location.reload(), 1500);
						} else {
							if (window.cinShowMessage) window.cinShowMessage(response.data?.message || '<?php esc_html_e( 'Failed to clear logs.', 'contactin' ); ?>', 'error');
							$btn.removeClass('is-busy').prop('disabled', false);
						}
					}, 'json').fail(() => {
						if (window.cinShowMessage) window.cinShowMessage('<?php esc_html_e( 'Network error.', 'contactin' ); ?>', 'error');
						$btn.removeClass('is-busy').prop('disabled', false);
					});
				}
			});
		});
		
		// Auto-submit form when status dropdown changes
		$(document).on('change', '#status-filter', function() {
			$('#contactin-email-log-form').submit();
		});
		
		// Auto-submit form when per-page dropdown changes
		$(document).on('change', '#per-page-filter-email', function() {
			$('#contactin-email-log-form').submit();
		});
	});
	<?php
	$email_log_page_script = trim( (string) ob_get_clean() );
	wp_add_inline_script( 'contactin-admin-email-log', $email_log_page_script, 'after' );
	?>
