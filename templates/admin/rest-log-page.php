<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
/**
 * Admin Template: REST API Log
 *
 * @package ContactIn/Admin
 */

use ContactInbox\Core\Config;

// phpcs:disable WordPress.Security.EscapeOutput, WordPress.WP.I18n.NonSingularStringLiteralDomain, WordPress.WP.I18n.MissingTranslatorsComment, WordPress.Security.NonceVerification, WordPress.Security.ValidatedSanitizedInput, WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound, WordPress.DB.PreparedSQL.NotPrepared

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="wrap cin-rest-log-page">
	<div class="cin-page-header">
		<div>
			<h1><?php esc_html_e( 'REST API Log', 'contactin' ); ?></h1>
			<span class="cin-header-count">
				<?php
				printf(
					_n( '(%s API call)', '(%s API calls)', $total_items, 'contactin' ),
					number_format_i18n( $total_items )
				);
				?>
			</span>
		</div>
	</div>

	<div id="contactin-rest-notice" class="notice cin-rest-message-box cin-hidden"></div>

	<form id="contactin-rest-log-form" method="get">
		<input type="hidden" name="page" value="<?php echo esc_attr( $_GET['page'] ?? 'contactin-rest-log' ); ?>" />

		<!-- Filters and action buttons - using inbox/contacts/log layout -->
		<div class="tablenav top cin-log-tablenav cin-rest-log-tablenav">
			<div class="alignleft actions">
				<!-- HTTP Method filter -->
				<label for="method-filter" class="screen-reader-text">
					<?php esc_html_e( 'Filter by HTTP method', 'contactin' ); ?>
				</label>
				<select id="method-filter" name="http_method" class="cin-rest-method-filter">
					<option value="all" <?php selected( $_GET['http_method'] ?? 'all', 'all' ); ?>><?php esc_html_e( 'All Methods', 'contactin' ); ?></option>
					<option value="GET" <?php selected( $_GET['http_method'] ?? '', 'GET' ); ?>>GET</option>
					<option value="POST" <?php selected( $_GET['http_method'] ?? '', 'POST' ); ?>>POST</option>
					<option value="PUT" <?php selected( $_GET['http_method'] ?? '', 'PUT' ); ?>>PUT</option>
					<option value="DELETE" <?php selected( $_GET['http_method'] ?? '', 'DELETE' ); ?>>DELETE</option>
				</select>

				<!-- Endpoint filter -->
				<label for="endpoint-filter" class="screen-reader-text">
					<?php esc_html_e( 'Filter by endpoint', 'contactin' ); ?>
				</label>
				<select id="endpoint-filter" name="endpoint" class="cin-rest-endpoint-filter">
					<option value="all" <?php selected( $_GET['endpoint'] ?? 'all', 'all' ); ?>><?php esc_html_e( 'All Endpoints', 'contactin' ); ?></option>
					<option value="submit" <?php selected( $_GET['endpoint'] ?? '', 'submit' ); ?>><?php esc_html_e( 'Submit Form', 'contactin' ); ?></option>
					<option value="upload-attachment" <?php selected( $_GET['endpoint'] ?? '', 'upload-attachment' ); ?>><?php esc_html_e( 'Upload Attachment', 'contactin' ); ?></option>
					<option value="read" <?php selected( $_GET['endpoint'] ?? '', 'read' ); ?>><?php esc_html_e( 'Read Message', 'contactin' ); ?></option>
					<option value="status" <?php selected( $_GET['endpoint'] ?? '', 'status' ); ?>><?php esc_html_e( 'Update Status', 'contactin' ); ?></option>
					<option value="messages" <?php selected( $_GET['endpoint'] ?? '', 'messages' ); ?>><?php esc_html_e( 'List Messages', 'contactin' ); ?></option>
					<option value="search" <?php selected( $_GET['endpoint'] ?? '', 'search' ); ?>><?php esc_html_e( 'Search', 'contactin' ); ?></option>
					<option value="delete" <?php selected( $_GET['endpoint'] ?? '', 'delete' ); ?>><?php esc_html_e( 'Delete', 'contactin' ); ?></option>
					<option value="bulk-delete" <?php selected( $_GET['endpoint'] ?? '', 'bulk-delete' ); ?>><?php esc_html_e( 'Bulk Delete', 'contactin' ); ?></option>
				</select>

				<!-- HTTP Code filter -->
				<label for="http-code-filter" class="screen-reader-text">
					<?php esc_html_e( 'Filter by HTTP code', 'contactin' ); ?>
				</label>
				<select id="http-code-filter" name="http_code" class="cin-rest-http-code-filter">
					<option value="all" <?php selected( $_GET['http_code'] ?? 'all', 'all' ); ?>><?php esc_html_e( 'All Codes', 'contactin' ); ?></option>
					<option value="200" <?php selected( $_GET['http_code'] ?? '', '200' ); ?>>200</option>
					<option value="400" <?php selected( $_GET['http_code'] ?? '', '400' ); ?>>400</option>
					<option value="401" <?php selected( $_GET['http_code'] ?? '', '401' ); ?>>401</option>
					<option value="403" <?php selected( $_GET['http_code'] ?? '', '403' ); ?>>403</option>
					<option value="404" <?php selected( $_GET['http_code'] ?? '', '404' ); ?>>404</option>
					<option value="500" <?php selected( $_GET['http_code'] ?? '', '500' ); ?>>500</option>
				</select>

				<!-- Validated filter -->
				<label for="validated-filter" class="screen-reader-text">
					<?php esc_html_e( 'Filter by validation', 'contactin' ); ?>
				</label>
				<select id="validated-filter" name="validated" class="cin-rest-validated-filter">
					<option value="all" <?php selected( $_GET['validated'] ?? 'all', 'all' ); ?>><?php esc_html_e( 'All Validations', 'contactin' ); ?></option>
					<option value="1" <?php selected( $_GET['validated'] ?? '', '1' ); ?>><?php esc_html_e( 'Validated', 'contactin' ); ?></option>
					<option value="0" <?php selected( $_GET['validated'] ?? '', '0' ); ?>><?php esc_html_e( 'Not Validated', 'contactin' ); ?></option>
				</select>

				<!-- Prune button -->
				<button type="button" class="button button-secondary" id="contactin-prune-rest-btn" data-nonce="<?php echo esc_attr( wp_create_nonce( Config::NONCE_ACTION ) ); ?>" <?php disabled( $total_items === 0 ); ?>>
					<?php esc_html_e( 'Prune Old Logs', 'contactin' ); ?>
				</button>

				<!-- Clear All Logs button -->
				<button type="button" class="button button-secondary" id="contactin-clear-rest-logs" data-nonce="<?php echo esc_attr( wp_create_nonce( Config::NONCE_ACTION ) ); ?>" <?php disabled( $total_items === 0 ); ?>>
					<?php esc_html_e( 'Clear All Logs', 'contactin' ); ?>
				</button>

				<span class="cin-log-export">
					<button type="button" class="button button-primary cin-download-csv"
						data-http-method="<?php echo esc_attr( $_GET['http_method'] ?? 'all' ); ?>"
						data-endpoint="<?php echo esc_attr( $_GET['endpoint'] ?? 'all' ); ?>"
						data-http-code="<?php echo esc_attr( $_GET['http_code'] ?? 'all' ); ?>"
						data-validated="<?php echo esc_attr( $_GET['validated'] ?? 'all' ); ?>"
						data-export-info-action="contactinbox_rest_export_info"
						data-ajax-action="contactinbox_download_rest_csv"
						data-nonce="<?php echo esc_attr( wp_create_nonce( Config::NONCE_ACTION ) ); ?>"
						<?php disabled( $total_items === 0 ); ?>>
						<span class="dashicons dashicons-download"></span>
						<?php esc_html_e( 'Export CSV', 'contactin' ); ?>
					</button>
				</span>
			</div>
		</div>

		<div class="tablenav top cin-log-tablenav-pages cin-rest-log-tablenav-pages">
			<div class="tablenav-pages">
				<span class="displaying-num"><?php echo esc_html( number_format_i18n( $total_items ) ); ?> <?php esc_html_e( 'items', 'contactin' ); ?></span>

				<!-- Per page filter -->
				<label for="per-page-filter-rest" class="cin-per-page-label"><?php esc_html_e( 'Rows per page', 'contactin' ); ?></label>
				<select id="per-page-filter-rest" name="per_page" class="cin-per-page-select">
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

		<!-- Table -->
		<div class="wp-list-table-container">
			<?php $table->display(); ?>
		</div>
	</form>

	<!-- Load shared export modal -->
	<?php load_template( CONTACTINBOX_PATH . Config::TEMPLATE_ADMIN_PART . 'export-modal.php' ); ?>

	<!-- Load shared warning modal -->
	<?php load_template( CONTACTINBOX_PATH . Config::TEMPLATE_ADMIN_PART . 'warning-modal.php' ); ?>

	<!-- REST API Log Page JS Override -->
	<script>
	jQuery(document).ready(function($) {
		'use strict';
		
		// Prune button handler with warning modal
		$('#contactin-prune-rest-btn').off('click').on('click', function(e) {
			e.preventDefault();
			const title = '⚠️ Prune Old REST API Logs';
			const message = 'This will permanently delete all REST API logs older than 30 days. This action cannot be undone.';
			
			window.showLogWarningModal({
				title: title,
				message: message,
				logType: 'REST API logs',
				confirmText: '<?php esc_html_e( 'Prune Logs', 'contactin' ); ?>',
				onConfirm: function() {
					const $btn = $('#contactin-prune-rest-btn');
					const nonce = $btn.data('nonce') || (window.ContactINRestLog?.nonce || '');
					if (!nonce) {
						if (window.cinShowMessage) window.cinShowMessage('Missing security token.', 'error');
						return;
					}
					
					$btn.addClass('is-busy').prop('disabled', true);
					$.post(window.ContactINRestLog?.ajax_url || window.ajaxurl || '', {
						action: 'contactin_prune_rest',
						nonce: nonce
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
		$('#contactin-clear-rest-logs').off('click').on('click', function(e) {
			e.preventDefault();
			const title = '⚠️ Clear All REST API Logs';
			const message = 'This will PERMANENTLY DELETE ALL REST API logs. This action cannot be undone. Do you want to continue?';
			
			window.showLogWarningModal({
				title: title,
				message: message,
				logType: 'REST API logs',
				confirmText: '<?php esc_html_e( 'Delete All', 'contactin' ); ?>',
				confirmStyle: 'danger',
				onConfirm: function() {
					const $btn = $('#contactin-clear-rest-logs');
					const nonce = $btn.data('nonce') || (window.ContactINRestLog?.nonce || '');
					if (!nonce) {
						if (window.cinShowMessage) window.cinShowMessage('Missing security token.', 'error');
						return;
					}
					
					$btn.addClass('is-busy').prop('disabled', true);
					$.post(window.ContactINRestLog?.ajax_url || window.ajaxurl || '', {
						action: 'contactin_clear_rest_logs',
						nonce: nonce
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

	<div id="contactin-rest-modal" class="contactin-modal" role="dialog" aria-modal="true" aria-labelledby="contactin-rest-modal-title">
		<!-- Backdrop -->
		<div class="contactin-modal-backdrop"></div>

		<!-- Modal content -->
		<div class="contactin-modal-content">
			<!-- Header -->
			<div class="contactin-modal-header">
				<h2 id="contactin-rest-modal-title" class="cin-modal-title">
					<?php esc_html_e( 'Log Details', 'contactin' ); ?>
				</h2>
			</div>

			<!-- Meta section (compact badges) -->
			<div id="contactin-rest-meta" class="contactin-modal-meta">
				<div class="contactin-meta-item">
					<strong>Timestamp:</strong> <span>2025-12-09 09:43:09</span>
				</div>
				<div class="contactin-meta-item">
					<strong>IP:</strong> <span>127.0.0.1</span>
				</div>
				<div class="contactin-meta-item">
					<strong>User Agent:</strong> <span>WordPress/6.9; http://wpdev.local</span>
				</div>
				<div class="contactin-meta-item">
					<strong>HTTP Method:</strong> <span>POST</span>
				</div>
				<div class="contactin-meta-item">
					<strong>Endpoint:</strong> <span>/submit</span>
				</div>
				<div class="contactin-meta-item">
					<strong>HTTP Code:</strong> <span>200</span>
				</div>
				<div class="contactin-meta-item">
					<strong>Validated:</strong> <span>✔</span>
				</div>
				<div class="contactin-meta-item">
					<strong>Token valid:</strong> <span>✔</span>
				</div>
			</div>

			<!-- Payload (scrollable) -->
			<div class="contactin-modal-payload">
				<section id="contactin-rest-headers" class="cin-log-section">
					<h4><?php esc_html_e( 'Request Headers', 'contactin' ); ?></h4>
					<pre></pre>
				</section>

				<section id="contactin-rest-request" class="cin-log-section">
					<h4><?php esc_html_e( 'Request Payload', 'contactin' ); ?></h4>
					<pre></pre>
				</section>

				<section id="contactin-rest-response" class="cin-log-section">
					<h4><?php esc_html_e( 'Response Body', 'contactin' ); ?></h4>
					<pre></pre>
				</section>
			</div>

			<!-- Footer -->
			<div class="contactin-modal-footer">
				<button type="button" class="button button-secondary" id="contactin-prev-log" aria-label="<?php esc_attr_e( 'View previous log', 'contactin' ); ?>">
					<?php esc_html_e( 'Prev', 'contactin' ); ?>
				</button>
				<button type="button" class="button button-secondary" id="contactin-next-log" aria-label="<?php esc_attr_e( 'View next log', 'contactin' ); ?>">
					<?php esc_html_e( 'Next', 'contactin' ); ?>
				</button>
			</div>
		</div>
	</div>

</div>

<script>
(function($) {
	'use strict';
	
	// Auto-submit form when filter dropdowns change
	$(document).on('change', '#method-filter, #endpoint-filter, #http-code-filter, #validated-filter, #per-page-filter-rest', function() {
		$('#contactin-rest-log-form').submit();
	});
})(jQuery);
</script>

