<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
/**
 * Template: CRM Dashboard Page
 *
 * @var array $statistics CRM statistics data
 * @var array $health CRM health status
 * @var array $endpoints CRM endpoints in use
 * @var array $daily_stats Daily statistics for last 7 days
 */

use ContactInbox\Core\Config;
use ContactInbox\Core\CRMStatus;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// phpcs:disable WordPress.WP.I18n.NonSingularStringLiteralDomain, WordPress.WP.I18n.MissingTranslatorsComment, WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound, WordPress.Security.EscapeOutput.OutputNotEscaped

$nonce        = wp_create_nonce( 'contactin_nonce_action' );
$health_class = 'notice-info';
if ( 'critical' === $health['status'] ) {
	$health_class = 'notice-error';
} elseif ( 'warning' === $health['status'] ) {
	$health_class = 'notice-warning';
} else {
	$health_class = 'notice-success';
}
?>

<div class="wrap">
	<h1><?php esc_html_e( 'CRM Integration Dashboard', 'contactin' ); ?></h1>

	<!-- Health Status Alert -->
	<div class="notice <?php echo esc_attr( $health_class ); ?> inline">
		<p>
			<strong><?php esc_html_e( 'Status:', 'contactin' ); ?></strong>
			<?php echo esc_html( $health['message'] ); ?>

			<?php if ( $statistics['failed'] > 0 ) : ?>
				<button type="button" id="cin-retry-failed-crm" class="button button-primary cin-ml-lg">
					<span class="dashicons dashicons-update-alt cin-icon-with-text"></span>
					<?php
					printf(
						esc_html__( 'Retry %d Failed Sync(s)', 'contactin' ),
						$statistics['failed']
					);
					?>
				</button>
			<?php endif; ?>
		</p>
	</div>

	<!-- Statistics Cards -->
	<div class="contactin-stats-grid">
		<div class="stat-card">
			<div class="stat-label"><?php esc_html_e( 'Total Sends', 'contactin' ); ?></div>
			<div class="stat-value"><?php echo esc_html( $statistics['total_sends'] ); ?></div>
		</div>

		<div class="stat-card">
			<div class="stat-label"><?php esc_html_e( 'Successful', 'contactin' ); ?></div>
			<div class="stat-value success"><?php echo esc_html( $statistics['successful'] ); ?></div>
		</div>

		<div class="stat-card">
			<div class="stat-label"><?php esc_html_e( 'Failed', 'contactin' ); ?></div>
			<div class="stat-value error"><?php echo esc_html( $statistics['failed'] ); ?></div>
		</div>

		<div class="stat-card">
			<div class="stat-label"><?php esc_html_e( 'Pending', 'contactin' ); ?></div>
			<div class="stat-value pending"><?php echo esc_html( $statistics['pending'] ); ?></div>
		</div>

		<div class="stat-card">
			<div class="stat-label"><?php esc_html_e( 'Success Rate', 'contactin' ); ?></div>
			<div class="stat-value"><?php echo esc_html( $statistics['success_rate'] ); ?>%</div>
		</div>

		<div class="stat-card">
			<div class="stat-label"><?php esc_html_e( 'Avg Response (ms)', 'contactin' ); ?></div>
			<div class="stat-value"><?php echo esc_html( $statistics['avg_response_time_ms'] ); ?></div>
		</div>
	</div>

	<!-- Daily Stats Chart -->
	<div class="postbox">
		<h2 class="hndle"><?php esc_html_e( '7-Day Trend', 'contactin' ); ?></h2>
		<div class="inside">
			<table class="widefat striped">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Date', 'contactin' ); ?></th>
						<th><?php esc_html_e( 'Total', 'contactin' ); ?></th>
						<th><?php esc_html_e( 'Successful', 'contactin' ); ?></th>
						<th><?php esc_html_e( 'Failed', 'contactin' ); ?></th>
						<th><?php esc_html_e( 'Pending', 'contactin' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php if ( ! empty( $daily_stats ) ) : ?>
						<?php foreach ( $daily_stats as $day ) : ?>
							<tr>
								<td><?php echo esc_html( $day['date'] ); ?></td>
								<td><?php echo esc_html( $day['total'] ); ?></td>
								<td><span class="cin-color-success"><?php echo esc_html( $day['successful'] ); ?></span></td>
								<td><span class="cin-color-error"><?php echo esc_html( $day['failed'] ); ?></span></td>
								<td><span class="cin-color-warning"><?php echo esc_html( $day['pending'] ); ?></span></td>
							</tr>
						<?php endforeach; ?>
					<?php else : ?>
						<tr>
							<td colspan="5"><?php esc_html_e( 'No data available.', 'contactin' ); ?></td>
						</tr>
					<?php endif; ?>
				</tbody>
			</table>
		</div>
	</div>

	<!-- Endpoints Table -->
	<div class="postbox">
		<h2 class="hndle"><?php esc_html_e( 'Active Endpoints', 'contactin' ); ?></h2>
		<div class="inside">
			<?php if ( ! empty( $endpoints ) ) : ?>
				<table class="widefat striped">
					<thead>
						<tr>
							<th><?php esc_html_e( 'Endpoint URL', 'contactin' ); ?></th>
							<th><?php esc_html_e( 'Total Sends', 'contactin' ); ?></th>
							<th><?php esc_html_e( 'Successful', 'contactin' ); ?></th>
							<th><?php esc_html_e( 'Success Rate', 'contactin' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $endpoints as $endpoint ) : ?>
							<tr>
								<td><code><?php echo esc_html( substr( $endpoint['endpoint'], 0, 50 ) ); ?>...</code></td>
								<td><?php echo esc_html( $endpoint['total'] ); ?></td>
								<td><?php echo esc_html( $endpoint['successful'] ); ?></td>
								<td>
									<span class="<?php echo ( $endpoint['success_rate'] >= 80 ) ? 'cin-color-success' : 'cin-color-error'; ?>">
										<?php echo esc_html( $endpoint['success_rate'] ); ?>%
									</span>
								</td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			<?php else : ?>
				<p><?php esc_html_e( 'No endpoints recorded yet.', 'contactin' ); ?></p>
			<?php endif; ?>
		</div>
	</div>

	<!-- Recent Logs Table -->
	<div class="postbox">
		<h2 class="hndle"><?php esc_html_e( 'Recent CRM Logs', 'contactin' ); ?></h2>
		<div class="inside">
			<div class="crm-filter-row">
				<label for="crm_log_status"><?php esc_html_e( 'Filter by Status:', 'contactin' ); ?></label>
				<select id="crm_log_status">
					<?php foreach ( CRMStatus::get_filter_options() as $option ) : ?>
						<option value="<?php echo esc_attr( $option['value'] ); ?>"><?php echo esc_html( $option['label'] ); ?></option>
					<?php endforeach; ?>
				</select>
			</div>
			<table class="widefat striped" id="crm_logs_table">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Timestamp', 'contactin' ); ?></th>
						<th><?php esc_html_e( 'Status', 'contactin' ); ?></th>
						<th><?php esc_html_e( 'Response Time (ms)', 'contactin' ); ?></th>
						<th><?php esc_html_e( 'Files', 'contactin' ); ?></th>
						<th><?php esc_html_e( 'Endpoint', 'contactin' ); ?></th>
						<th><?php esc_html_e( 'Error', 'contactin' ); ?></th>
					</tr>
				</thead>
				<tbody id="crm_logs_body">
					<tr>
						<td colspan="5" class="cin-text-center cin-p-xl">
							<em><?php esc_html_e( 'Loading...', 'contactin' ); ?></em>
						</td>
					</tr>
				</tbody>
			</table>
			<div id="crm_logs_pagination" class="cin-text-center cin-mt-lg"></div>
		</div>
	</div>

	<style>
		.contactin-crm-dashboard {
			margin-top: 20px;
		}

		.contactin-stats-grid {
			display: grid;
			grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
			gap: 15px;
			margin: 20px 0;
		}

		.stat-card {
			background: #fff;
			border: 1px solid #ccc;
			border-radius: 4px;
			padding: 20px;
			text-align: center;
			box-shadow: 0 1px 1px rgba(0, 0, 0, 0.04);
			transition: box-shadow 0.2s ease;
		}

		.stat-card:hover {
			box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
		}

		.stat-label {
			color: #666;
			font-size: 12px;
			text-transform: uppercase;
			margin-bottom: 10px;
			letter-spacing: 0.5px;
		}

		.stat-value {
			font-size: 32px;
			font-weight: bold;
			color: #0073aa;
			line-height: 1.2;
		}

		.stat-value.success {
			color: #46b450;
		}

		.stat-value.error {
			color: #dc3545;
		}

		.stat-value.pending {
			color: #ffc107;
		}

		.postbox {
			margin: 20px 0;
			background: #fff;
			border: 1px solid #ccc;
			border-radius: 4px;
		}

		.postbox .hndle {
			background: #f5f5f5;
			border-bottom: 1px solid #ccc;
			padding: 12px;
			margin: 0;
			font-size: 14px;
			font-weight: 600;
			cursor: pointer;
		}

		.postbox .inside {
			padding: 15px;
		}

		.postbox table th {
			background: #f9f9f9;
			padding: 10px;
			text-align: left;
			font-weight: 600;
		}

		.postbox table td {
			padding: 10px;
			vertical-align: middle;
		}

		.postbox table tr:nth-child(even) {
			background: #f9f9f9;
		}

		.crm_logs_page_btn {
			padding: 8px 12px;
			margin: 0 3px;
			min-width: 36px;
			text-align: center;
		}

		.crm_logs_page_btn:disabled {
			background: #0073aa;
			color: white;
			cursor: default;
		}

		#crm_log_status {
			padding: 6px 10px;
			min-width: 150px;
			border: 1px solid #ccc;
			border-radius: 3px;
		}

		.crm-filter-row {
			margin-bottom: 15px;
			display: flex;
			align-items: center;
			gap: 10px;
		}

		.crm-filter-row label {
			margin: 0;
			font-weight: 600;
		}
	</style>

	<script type="text/javascript">
		(function() {
			const nonce = '<?php echo esc_js( $nonce ); ?>';
			const statusSelect = document.getElementById('crm_log_status');
			const logsBody = document.getElementById('crm_logs_body');
			const paginationDiv = document.getElementById('crm_logs_pagination');

			/**
			 * Load CRM logs via AJAX
			 */
			function loadLogs(page = 1, status = 'all') {
				if (!page || page < 1) page = 1;

				fetch(ajaxurl, {
					method: 'POST',
					headers: {
						'Content-Type': 'application/x-www-form-urlencoded',
					},
					body: new URLSearchParams({
						action: 'ci_get_crm_logs',
						nonce: nonce,
						page: page,
						per_page: 20,
						status: status,
					}),
				})
					.then(response => {
						if (!response.ok) {
							throw new Error('Network response was not ok');
						}
						return response.json();
					})
					.then(data => {
						if (data.success && data.data) {
							renderLogs(data.data.logs);
							renderPagination(data.data.total_pages, page, status);
						} else {
							showError('<?php esc_html_e( 'Error loading logs', 'contactin' ); ?>');
						}
					})
					.catch(error => {
						console.error('AJAX error:', error);
						showError('<?php esc_html_e( 'Error loading logs', 'contactin' ); ?>');
					});
			}

			/**
			 * Render logs table rows
			 */
			function renderLogs(logs) {
				if (!logs || logs.length === 0) {
					logsBody.innerHTML = '<tr><td colspan="6" class="cin-text-center cin-p-xl"><?php esc_html_e( 'No logs found', 'contactin' ); ?></td></tr>';
					return;
				}

				const parseResponse = (response) => {
					if (!response) return {};
					if (typeof response === 'object') return response;
					try {
						return JSON.parse(response);
					} catch (err) {
						return {};
					}
				};

				const getFilesSummary = (log, response) => {
					const operation = log.operation || '';
					if (operation === 'sync') {
						// Use actual database columns for file tracking
						const filesQueued = Number(log.files_queued || 0);
						const queuedFilenamesText = (log.queued_filenames || '').trim();
						const names = queuedFilenamesText ? queuedFilenamesText.split(', ') : [];
						const preview = names.slice(0, 3);
						const extra = names.length > preview.length ? names.length - preview.length : 0;
						let namesText = preview.length ? preview.join(', ') : '<?php echo esc_js( __( 'None', 'contactin' ) ); ?>';
						if (extra > 0) {
							namesText += ` (+${extra} more)`;
						}
						return `${filesQueued} (${namesText})`;
					}

					if (operation === 'file_sync') {
						const filename = response.filename || '';
						const caseId = response.case_id || '';
						if (filename && caseId) {
							return `${filename} (Case ${caseId})`;
						}
						if (filename) {
							return filename;
						}
						if (caseId) {
							return `Case ${caseId}`;
						}
					}

					return '-';
				};

				logsBody.innerHTML = logs.map(log => `
					<tr>
						<td>${escapeHtml(log.created_at || log.timestamp || '-')}</td>
						<td>
							<span style="
								background-color: ${getStatusColor(log.status || log.crm_send_status)};
								color: white;
								padding: 4px 8px;
								border-radius: 3px;
								font-size: 11px;
								font-weight: 500;
								display: inline-block;
							">
								${escapeHtml(log.status || log.crm_send_status || '-')}
							</span>
						</td>
						${(() => {
							const response = parseResponse(log.response);
							const responseTime = response.response_time_ms || log.crm_response_time || '-';
							const endpoint = response.endpoint || log.crm_endpoint || '-';
							const filesSummary = getFilesSummary(log, response);
							return `
								<td class="cin-text-right">${responseTime}</td>
								<td class="cin-color-muted cin-p-sm">${escapeHtml(filesSummary)}</td>
								<td><code class="cin-code-inline">${escapeHtml(endpoint ? String(endpoint).substring(0, 40) : '-')}</code></td>
								<td class="cin-color-muted cin-p-sm">${escapeHtml(log.error_message ? log.error_message.substring(0, 60) : '-')}</td>
							`;
						})()}
					</tr>
				`).join('');
			}

			/**
			 * Render pagination buttons
			 */
			function renderPagination(totalPages, currentPage, status) {
				if (!totalPages || totalPages <= 1) {
					paginationDiv.innerHTML = '';
					return;
				}

				let html = '';
				for (let i = 1; i <= totalPages; i++) {
					if (i === currentPage) {
						html += `<button type="button" class="button crm_logs_page_btn" disabled>${i}</button>`;
					} else {
						html += `<button type="button" class="button crm_logs_page_btn" onclick="window.cinCRMLoadLogs(${i}, '${escapeHtml(status)}')">${i}</button>`;
					}
				}
				paginationDiv.innerHTML = html;
			}

			/**
			 * Get status badge color
			 */
			function getStatusColor(status) {
				const colors = JSON.parse('<?php echo wp_json_encode( CRMStatus::get_colors() ); ?>');
				return colors[status] || '#999';
			}

			/**
			 * Escape HTML entities for safe display
			 */
			function escapeHtml(text) {
				if (!text) return '';
				const map = {
					'&': '&amp;',
					'<': '&lt;',
					'>': '&gt;',
					'"': '&quot;',
					"'": '&#039;'
				};
				return String(text).replace(/[&<>"']/g, m => map[m]);
			}

			/**
			 * Show error message
			 */
			function showError(message) {
				logsBody.innerHTML = `<tr><td colspan="5" class="cin-text-center cin-color-error cin-p-xl">${escapeHtml(message)}</td></tr>`;
			}

			/**
			 * Global function for pagination buttons
			 */
			window.cinCRMLoadLogs = function(page, status) {
				loadLogs(page, status);
			};

			/**
			 * Initialize: Load initial logs
			 */
			function init() {
				loadLogs(1, 'all');

				// Status filter event listener
				if (statusSelect) {
					statusSelect.addEventListener('change', function() {
						loadLogs(1, this.value);
					});
				}

				// Retry failed CRM syncs button
				const retryBtn = document.getElementById('cin-retry-failed-crm');
				if (retryBtn) {
					retryBtn.addEventListener('click', function() {
						if (!confirm('<?php echo esc_js( __( 'Retry all failed CRM syncs? They will be re-queued for processing.', 'contactin' ) ); ?>')) {
							return;
						}

						retryBtn.disabled = true;
								retryBtn.innerHTML = '<span class="dashicons dashicons-update-alt spinner is-active cin-icon-with-text"></span> <?php echo esc_js( __( 'Processing...', 'contactin' ) ); ?>';

						fetch(ajaxurl, {
							method: 'POST',
							headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
							body: new URLSearchParams({
								action: 'ci_retry_failed_crm',
								nonce: '<?php echo esc_js( $nonce ); ?>'
							})
						})
						.then(res => res.json())
						.then(data => {
							if (data.success) {
								alert(data.data.message);
								// Reload page to reflect updated counts
								window.location.reload();
							} else {
								alert('<?php echo esc_js( __( 'Error:', 'contactin' ) ); ?> ' + (data.data?.message || '<?php echo esc_js( __( 'Unknown error', 'contactin' ) ); ?>'));
								retryBtn.disabled = false;
								retryBtn.innerHTML = '<span class=\"dashicons dashicons-update-alt cin-icon-with-text\"></span> <?php echo esc_js( __( 'Retry Failed Syncs', 'contactin' ) ); ?>';
							}
						})
						.catch(err => {
							console.error('[CRM Retry] Error:', err);
							alert('<?php echo esc_js( __( 'Network error. Please try again.', 'contactin' ) ); ?>');
							retryBtn.disabled = false;
							retryBtn.innerHTML = '<span class=\"dashicons dashicons-update-alt cin-icon-with-text\"></span> <?php echo esc_js( __( 'Retry Failed Syncs', 'contactin' ) ); ?>';
						});
					});
				}
			}

			// Start when DOM is ready
			if (document.readyState === 'loading') {
				document.addEventListener('DOMContentLoaded', init);
			} else {
				init();
			}
		})();
	</script>
</div>
