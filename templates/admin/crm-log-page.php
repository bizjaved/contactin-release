<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
/**
 * Admin Template: CRM Log
 *
 * @package ContactIn
 */

use ContactInbox\Core\Config;
use ContactInbox\Core\CRMStatus;

// phpcs:disable WordPress.WP.I18n.NonSingularStringLiteralDomain, WordPress.Security.ValidatedSanitizedInput, WordPress.Security.NonceVerification, WordPress.NamingConventions.PrefixAllGlobals, WordPress.Security.EscapeOutput, WordPress.WP.I18n.MissingTranslatorsComment

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="wrap cin-crm-log-page">
	<div class="cin-page-header">
		<div>
			<h1><?php esc_html_e( 'CRM Log', 'contactin' ); ?></h1>
			<span class="cin-header-count">
				<?php
				printf(
					_n( '(%s sync operation)', '(%s sync operations)', $total_items, 'contactin' ),
					number_format_i18n( $total_items )
				);
				?>
			</span>
		</div>
	</div>

	<div id="contactin-crm-notice" class="notice cin-rest-message-box cin-hidden"></div>

	<form id="contactin-crm-log-form" method="get">
		<input type="hidden" name="page" value="<?php echo esc_attr( $_GET['page'] ?? 'contactin-crm-log' ); ?>" />
		<?php wp_nonce_field( 'contactin_crm_log_action', 'contactin_crm_log_nonce' ); ?>

		<!-- Filters and action buttons - using inbox/contacts layout -->
		<div class="tablenav top cin-log-tablenav cin-crm-log-tablenav">
			<div class="alignleft actions">
				<!-- Status filter -->
				<label for="status-filter-crm" class="screen-reader-text">
					<?php esc_html_e( 'Filter by status', 'contactin' ); ?>
				</label>
				<select id="status-filter-crm" name="status" class="cin-status-filter-select">
					<?php foreach ( CRMStatus::get_filter_options() as $option ) : ?>
						<option value="<?php echo esc_attr( $option['value'] ); ?>" <?php selected( $current_status, $option['value'] ); ?>>
							<?php echo esc_html( $option['label'] ); ?>
						</option>
					<?php endforeach; ?>
				</select>

				<!-- Operation filter -->
				<label for="operation-filter-crm" class="screen-reader-text">
					<?php esc_html_e( 'Filter by operation', 'contactin' ); ?>
				</label>
				<select id="operation-filter-crm" name="operation" class="cin-operation-filter">
					<option value="all" <?php selected( $current_operation, 'all' ); ?>><?php esc_html_e( 'All Operations', 'contactin' ); ?></option>
					<?php foreach ( $operation_options as $operation_option ) : ?>
						<option value="<?php echo esc_attr( $operation_option ); ?>" <?php selected( $current_operation, $operation_option ); ?>>
							<?php echo esc_html( ucwords( str_replace( '_', ' ', $operation_option ) ) ); ?>
						</option>
					<?php endforeach; ?>
				</select>

				<!-- Use a single, always-fresh nonce for both buttons -->
				<?php $crm_logs_nonce = wp_create_nonce( 'contactin_crm_clear_all_logs' ); ?>
				<button type="button" class="button button-secondary" id="contactin-prune-crm-btn" data-nonce="<?php echo esc_attr( $crm_logs_nonce ); ?>" <?php disabled( $total_items === 0 ); ?>>
					<?php esc_html_e( 'Prune Old Logs', 'contactin' ); ?>
				</button>

				<!-- Clear All Logs button -->
				<button type="button" class="button button-secondary" id="contactin-clear-crm-logs" data-nonce="<?php echo esc_attr( $crm_logs_nonce ); ?>" <?php disabled( $total_items === 0 ); ?>>
					<?php esc_html_e( 'Clear All Logs', 'contactin' ); ?>
				</button>

				<?php if ( \ContactInbox\Integration\FreemiusIntegration::can_use_premium_features() ) : ?>
				<span class="cin-log-export">
					<button type="button" class="button button-primary cin-download-csv" id="contactin-download-crm-csv"
						data-status="<?php echo esc_attr( $current_status ); ?>"
						data-operation="<?php echo esc_attr( $current_operation ); ?>"
						data-nonce="<?php echo esc_attr( $crm_logs_nonce ); ?>"
						data-export-info-action="contactinbox_crm_export_info"
						data-ajax-action="contactinbox_download_crm_csv"
						<?php disabled( $total_items === 0 ); ?>>
						<span class="dashicons dashicons-download"></span>
						<?php esc_html_e( 'Export CSV', 'contactin' ); ?>
					</button>
				</span>
				<?php endif; ?>
			</div>
		</div>

		<div class="tablenav top cin-log-tablenav-pages cin-crm-log-tablenav-pages">
			<div class="tablenav-pages">
				<span class="displaying-num"><?php echo esc_html( number_format_i18n( $total_items ) ); ?> <?php esc_html_e( 'items', 'contactin' ); ?></span>

				<!-- Per page filter -->
				<label for="per-page-filter-crm" class="cin-per-page-label">
					<?php esc_html_e( 'Rows per page', 'contactin' ); ?>
				</label>
				<select id="per-page-filter-crm" name="per_page" class="cin-per-page-select">
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
				echo paginate_links( $pagination_args );
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

	<!-- Load shared export modal -->
	<?php load_template( CONTACTINBOX_PATH . Config::TEMPLATE_ADMIN_PART . 'export-modal.php' ); ?>

	<!-- Load shared warning modal -->
	<?php load_template( CONTACTINBOX_PATH . Config::TEMPLATE_ADMIN_PART . 'warning-modal.php' ); ?>

	<!-- CRM Log Page JS Override -->
	<script>
	jQuery(document).ready(function($) {
		'use strict';
		
		// Override handleAjaxAction for CRM log page to use warning modal
		const originalHandleAjaxAction = window.handleAjaxAction;
		
		// Prune button handler with warning modal
		$('#contactin-prune-crm-btn').off('click').on('click', function(e) {
			e.preventDefault();
			const title = '⚠️ Prune Old CRM Logs';
			const message = 'This will permanently delete all CRM logs older than 30 days. This action cannot be undone.';
			
			window.showLogWarningModal({
				title: title,
				message: message,
				logType: 'CRM logs',
				confirmText: '<?php esc_html_e( 'Prune Logs', 'contactin' ); ?>',
				onConfirm: function() {
					const $btn = $('#contactin-prune-crm-btn');
					const nonce = $btn.data('nonce');
					if (!nonce) {
						if (window.cinShowMessage) window.cinShowMessage('Missing security token.', 'error');
						return;
					}
					
					$btn.addClass('is-busy').prop('disabled', true);
					$.post(window.ajaxurl || (window.contactinCrmLog?.ajaxUrl || ''), {
						action: 'contactin_crm_prune_old_logs',
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
		$('#contactin-clear-crm-logs').off('click').on('click', function(e) {
			e.preventDefault();
			const title = '⚠️ Clear All CRM Logs';
			const message = 'This will PERMANENTLY DELETE ALL CRM logs. This action cannot be undone. Do you want to continue?';
			
			window.showLogWarningModal({
				title: title,
				message: message,
				logType: 'CRM logs',
				confirmText: '<?php esc_html_e( 'Delete All', 'contactin' ); ?>',
				confirmStyle: 'danger',
				onConfirm: function() {
					const $btn = $('#contactin-clear-crm-logs');
					const nonce = $btn.data('nonce');
					if (!nonce) {
						if (window.cinShowMessage) window.cinShowMessage('Missing security token.', 'error');
						return;
					}
					
					$btn.addClass('is-busy').prop('disabled', true);
					$.post(window.ajaxurl || (window.contactinCrmLog?.ajaxUrl || ''), {
						action: 'contactin_crm_clear_all_logs',
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
	});
	</script>

