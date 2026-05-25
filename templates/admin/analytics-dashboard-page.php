<?php
/**
 * Analytics Dashboard Page Template
 *
 * Main analytics hub with 5 tabs:
 * - Submissions: Volume, trends, conversion funnel
 * - Performance: Queue health, latency, error rates
 * - Users: Device distribution, geographic, source
 * - CRM: Integration health, sync rates, endpoints
 * - Reports: Custom reports, exports, scheduling
 *
 * @package ContactIn
 */

use ContactInbox\Core\Config;

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals, WordPress.Security.EscapeOutput, WordPress.WP.I18n.NonSingularStringLiteralDomain, WordPress.WP.I18n.MissingTranslatorsComment

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Preload queue stats for per-type cards
$queue_stats_by_type  = $queue_stats_by_type ?? array();
$queue_trends_by_type = $queue_trends_by_type ?? array();
$queue_health         = $queue_health ?? array( 'pending' => 0 );

?>

<div class="wrap contactin-dashboard-analytics">

	<div class="contactin-analytics-header-wrapper">
		<h1 class="contactin-analytics-title"><?php esc_html_e( 'Dashboard', 'contactin' ); ?></h1>
		<button type="button" class="button button-secondary contactin-help-button"
			data-cin-help-open="cin-analytics-help-modal"
			aria-haspopup="dialog"
			aria-controls="cin-analytics-help-modal">
			<span class="contactin-analytics-button-icon-text">ℹ️</span><?php _e( 'Help', 'contactin' ); ?>
		</button>
	</div>

	<div class="contactin-analytics-header">
		<div class="analytics-summary">
			<div class="summary-card" data-summary="submissions">
				<div class="summary-label"><?php esc_html_e( 'Submissions', 'contactin' ); ?></div>
				<div class="summary-value" id="summary-submissions-7d" data-summary-value="submissions">
					<?php echo esc_html( number_format_i18n( (int) ( $submissions_7d ?? 0 ) ) ); ?>
				</div>
			</div>
			<div class="summary-card" data-health="email" data-summary="email">
				<div class="summary-label"><?php esc_html_e( 'Email Delivery', 'contactin' ); ?></div>
				<div class="summary-value" id="summary-email-rate" data-summary-value="email">
					<?php echo esc_html( number_format_i18n( (float) ( $email_delivery['rate'] ?? 0 ), 1 ) ); ?>%
				</div>
			</div>
			<div class="summary-card" data-health="crm" data-summary="crm">
				<div class="summary-label">
					<?php esc_html_e( 'CRM Success Rate', 'contactin' ); ?>
				</div>
				<div class="summary-value" id="summary-crm-rate" data-summary-value="crm">
					<?php echo esc_html( number_format_i18n( (float) ( $crm_rate['rate'] ?? 0 ), 1 ) ) . '%'; ?>
				</div>
				<small style="color: #666; font-size: 11px;"><?php esc_html_e( 'sync + delete', 'contactin' ); ?></small>
			</div>
			<div class="summary-card" data-health="acceptance" data-summary="acceptance">
				<div class="summary-label"><?php esc_html_e( 'Acceptance Rate', 'contactin' ); ?></div>
				<div class="summary-value" id="summary-acceptance-rate" data-summary-value="acceptance">
					<?php echo esc_html( number_format_i18n( (float) ( $queue_processing_rate ?? 0 ), 1 ) ); ?>%
				</div>
			</div>
		</div>

		<div class="analytics-filters">
			<label for="date-range"><?php esc_html_e( 'Filter:', 'contactin' ); ?></label>
			<select id="date-range" class="contactin-date-range">
				<option value="today"><?php esc_html_e( 'Today', 'contactin' ); ?></option>
				<option value="yesterday"><?php esc_html_e( 'Yesterday', 'contactin' ); ?></option>
				<option value="this_week"><?php esc_html_e( 'This Week (to date)', 'contactin' ); ?></option>
				<option value="this_month" selected><?php esc_html_e( 'This Month (to date)', 'contactin' ); ?></option>
				<option value="last_month"><?php esc_html_e( 'Last Month', 'contactin' ); ?></option>
				<option value="last_3_months"><?php esc_html_e( 'Last 3 Months', 'contactin' ); ?></option>
				<option value="last_6_months"><?php esc_html_e( 'Last 6 Months', 'contactin' ); ?></option>
				<option value="custom"><?php esc_html_e( 'Custom Range', 'contactin' ); ?></option>
			</select>
			<div class="custom-date-range" id="custom-date-range">
				<label for="start-date" class="screen-reader-text"><?php esc_html_e( 'Start Date', 'contactin' ); ?></label>
				<input type="date" id="start-date" name="start-date" />
				<label for="end-date" class="screen-reader-text"><?php esc_html_e( 'End Date', 'contactin' ); ?></label>
				<input type="date" id="end-date" name="end-date" />
				<button type="button" class="button" id="apply-date-range"><?php esc_html_e( 'Apply', 'contactin' ); ?></button>
			</div>
		</div>
	</div>

	<!-- Tab Navigation -->
	<nav class="contactin-analytics-tabs">
		<button class="tab-button active" data-tab="submissions" id="tab-submissions">
			<?php esc_html_e( 'Submissions', 'contactin' ); ?>
		</button>
		<button class="tab-button" data-tab="performance" id="tab-performance">
			<?php esc_html_e( 'System Performance', 'contactin' ); ?>
		</button>
		<button class="tab-button" data-tab="users" id="tab-users">
			<?php esc_html_e( 'Users', 'contactin' ); ?>
		</button>
		<button class="tab-button" data-tab="crm" id="tab-crm">
			<?php esc_html_e( 'Salesforce CRM', 'contactin' ); ?>
		</button>
		<button class="tab-button" data-tab="cron" id="tab-cron">
			<?php esc_html_e( 'Background Jobs', 'contactin' ); ?>
		</button>
	</nav>

	<!-- Tab Content -->
	<div class="contactin-analytics-content">

		<!-- SUBMISSIONS TAB -->
		<div class="tab-pane active" id="submissions-pane" data-tab="submissions">
			<div class="analytics-section">
				<h2><?php esc_html_e( 'Submissions Analytics', 'contactin' ); ?></h2>

				<div class="analytics-grid">
					<!-- Submission Trend Chart -->
					<div class="analytics-card">
						<h3><?php esc_html_e( 'Submission Volume Trend', 'contactin' ); ?></h3>
						<div class="card-date-label submissions-date-label"></div>
						<div class="chart-container">
							<canvas id="chart-submissions-trend"></canvas>
						</div>
					</div>

					<!-- Spam Blocked -->
					<div class="analytics-card">
						<h3><?php esc_html_e( 'Spam Blocked', 'contactin' ); ?></h3>
						<div class="card-date-label submissions-date-label"></div>
						<p class="card-description"><?php esc_html_e( 'Total spam blocked by security checks and classifier detection.', 'contactin' ); ?></p>
						<div class="conversion-metric">
							<div class="metric-value metric-spam" id="metric-spam-blocked">...</div>
							<div class="metric-label"><?php esc_html_e( 'Spam Attempts', 'contactin' ); ?></div>
						</div>
					</div>

					<!-- Rejection Reasons -->
					<div class="analytics-card">
						<h3><?php esc_html_e( 'Rejection Reasons', 'contactin' ); ?></h3>
						<div class="card-date-label submissions-date-label"></div>
						<p class="card-description"><?php esc_html_e( 'Breakdown of why form submissions were rejected during the selected period.', 'contactin' ); ?></p>
						<div class="status-breakdown">
							<div class="status-item rejection-recaptcha">
								<span class="status-icon">🤖</span>
								<div class="status-info">
									<span class="status-label"><?php esc_html_e( 'reCAPTCHA Failed', 'contactin' ); ?></span>
									<span class="status-value" id="rejection-recaptcha">...</span>
								</div>
							</div>
							<div class="status-item rejection-validation">
								<span class="status-icon">❌</span>
								<div class="status-info">
									<span class="status-label"><?php esc_html_e( 'Validation Failed', 'contactin' ); ?></span>
									<span class="status-value" id="rejection-validation">...</span>
								</div>
							</div>
							<div class="status-item rejection-ratelimit">
								<span class="status-icon">⏱️</span>
								<div class="status-info">
									<span class="status-label"><?php esc_html_e( 'Rate Limited', 'contactin' ); ?></span>
									<span class="status-value" id="rejection-ratelimit">...</span>
								</div>
							</div>
							<div class="status-item rejection-nonce">
								<span class="status-icon">🔒</span>
								<div class="status-info">
									<span class="status-label"><?php esc_html_e( 'Nonce Failed', 'contactin' ); ?></span>
									<span class="status-value" id="rejection-nonce">...</span>
								</div>
							</div>
						</div>
					</div>

					<!-- Status Breakdown -->
					<div class="analytics-card">
						<h3><?php esc_html_e( 'Inbox Review Status', 'contactin' ); ?></h3>
						<div class="card-date-label submissions-date-label"></div>
						<p class="card-description"><?php esc_html_e( 'Breakdown of messages by read status and archive state.', 'contactin' ); ?></p>
						<div class="status-breakdown">
							<div class="status-item status-read">
								<span class="status-icon">✓</span>
								<div class="status-info">
									<span class="status-label"><?php esc_html_e( 'Read', 'contactin' ); ?></span>
									<span class="status-value" id="status-completed">...</span>
								</div>
							</div>
							<div class="status-item status-unread">
								<span class="status-icon">●</span>
								<div class="status-info">
									<span class="status-label"><?php esc_html_e( 'Unread', 'contactin' ); ?></span>
									<span class="status-value" id="status-pending">...</span>
								</div>
							</div>
							<div class="status-item status-archived">
								<span class="status-icon">📁</span>
								<div class="status-info">
									<span class="status-label"><?php esc_html_e( 'Archived', 'contactin' ); ?></span>
									<span class="status-value" id="status-failed">...</span>
								</div>
							</div>
						</div>
					</div>

					<!-- Conversion Rate -->
					<div class="analytics-card">
						<h3><?php esc_html_e( 'Message Processing Rates', 'contactin' ); ?></h3>
						<div class="card-date-label submissions-date-label"></div>
						<p class="card-description"><?php esc_html_e( 'Success rate breakdown by processing type over the selected period.', 'contactin' ); ?></p>
						<div class="status-breakdown">
							<div class="status-item rate-email">
								<span class="status-icon">📧</span>
								<div class="status-info">
									<span class="status-label"><?php esc_html_e( 'Email', 'contactin' ); ?></span>
									<span class="status-value" id="rate-email">...</span>
								</div>
							</div>
							<div class="status-item rate-crm">
								<span class="status-icon">🔗</span>
								<div class="status-info">
									<span class="status-label"><?php esc_html_e( 'CRM', 'contactin' ); ?></span>
									<span class="status-value" id="rate-crm">...</span>
								</div>
							</div>
							<!-- Removed: Webhook status item -->
						</div>
					</div>
				</div>
			</div>
		</div>

		<!-- PERFORMANCE TAB -->
		<div class="tab-pane" id="performance-pane" data-tab="performance">
			<div class="analytics-section">
				<h2><?php esc_html_e( 'System Performance & Health', 'contactin' ); ?></h2>

				<div class="performance-kpi-grid">
					<!-- Email Delivery Health -->
					<div class="analytics-card performance-kpi" data-health="email">
						<div class="card-heading">
							<h3><?php esc_html_e( 'Email Delivery', 'contactin' ); ?></h3>
							<span class="date-range-label" id="performance-email-date-label"></span>
						</div>
						<p class="card-description"><?php esc_html_e( 'Delivery success rate from email logs for the selected window.', 'contactin' ); ?></p>
						<div class="kpi-primary">
							<div class="kpi-value" id="email-delivery-rate">
								<span class="percentage-value">—</span>
							</div>
							<div class="kpi-metrics">
								<div class="stat-mini">
									<span class="stat-label"><?php esc_html_e( 'Sent', 'contactin' ); ?></span>
									<span class="stat-value" id="email-sent">0</span>
								</div>
								<div class="stat-mini">
									<span class="stat-label"><?php esc_html_e( 'Failed', 'contactin' ); ?></span>
									<span class="stat-value" id="email-failed">0</span>
								</div>
								<div class="stat-mini">
									<span class="stat-label"><?php esc_html_e( 'Total', 'contactin' ); ?></span>
									<span class="stat-value" id="email-total">0</span>
								</div>
							</div>
						</div>
						<div class="failure-reasons compact">
							<p class="failure-header"><?php esc_html_e( 'Top Failures', 'contactin' ); ?></p>
							<ul class="failure-list" id="email-failures">
								<li><?php esc_html_e( 'Loading...', 'contactin' ); ?></li>
							</ul>
						</div>
					</div>

					<!-- Spam Intelligence -->
					<div class="analytics-card performance-kpi">
						<div class="card-heading">
							<h3><?php esc_html_e( 'Spam Intelligence', 'contactin' ); ?></h3>
							<span class="date-range-label" id="performance-spam-date-label"></span>
						</div>
						<p class="card-description"><?php esc_html_e( 'reCAPTCHA scoring and suspicious activity spotted in the same period.', 'contactin' ); ?></p>
						<div class="kpi-primary">
							<div class="kpi-value" id="spam-avg-score">—</div>
							<div class="kpi-metrics">
								<div class="stat-mini">
									<span class="stat-label"><?php esc_html_e( 'Total', 'contactin' ); ?></span>
									<span class="stat-value" id="spam-total">0</span>
								</div>
								<div class="stat-mini">
									<span class="stat-label"><?php esc_html_e( 'Flagged', 'contactin' ); ?></span>
									<span class="stat-value" id="spam-flagged">0</span>
								</div>
								<div class="stat-mini">
									<span class="stat-label"><?php esc_html_e( 'Spam %', 'contactin' ); ?></span>
									<span class="stat-value" id="spam-percentage">—%</span>
								</div>
							</div>
						</div>
						<div class="score-info">
							<p class="score-info-text">
								<span class="score-badge good">🟢</span> <?php esc_html_e( 'Good: ≥0.75', 'contactin' ); ?> |
								<span class="score-badge medium">🟡</span> <?php esc_html_e( 'Medium: 0.5-0.75', 'contactin' ); ?> |
								<span class="score-badge bad">🔴</span> <?php esc_html_e( 'Suspicious: <0.5', 'contactin' ); ?>
							</p>
						</div>
					</div>
				</div>

				<div class="performance-system-grid">
					<!-- API Health -->
					<div class="analytics-card performance-system" data-health="api">
						<div class="card-heading">
							<h3><?php esc_html_e( 'API Availability', 'contactin' ); ?></h3>
							<span class="date-range-label" id="performance-api-date-label"></span>
						</div>
						<p class="card-description"><?php esc_html_e( 'REST API performance and error rates for the chosen filter.', 'contactin' ); ?></p>
						<div class="health-indicator" id="health-api">
							<div class="status-dot pending"></div>
							<span class="status-text"><?php esc_html_e( 'Loading...', 'contactin' ); ?></span>
						</div>
						<div class="health-stats-inline">
							<div class="stat-mini">
								<span class="stat-label"><?php esc_html_e( 'Requests', 'contactin' ); ?></span>
								<span class="stat-value" id="api-requests">0</span>
							</div>
							<div class="stat-mini">
								<span class="stat-label"><?php esc_html_e( 'Errors', 'contactin' ); ?></span>
								<span class="stat-value" id="api-errors">0</span>
							</div>
						</div>
					</div>

					<!-- Processing Queue Health -->
					<div class="analytics-card performance-system" data-health="queue">
						<div class="card-heading">
							<h3><?php esc_html_e( 'Processing Queue', 'contactin' ); ?></h3>
							<span class="current-state-label"><?php esc_html_e( 'Live snapshot', 'contactin' ); ?></span>
						</div>
						<p class="card-description"><?php esc_html_e( 'Real-time queue state (not affected by date filters).', 'contactin' ); ?></p>
						<div class="health-indicator" id="health-queue">
							<div class="status-dot pending"></div>
							<span class="status-text"><?php esc_html_e( 'Loading...', 'contactin' ); ?></span>
						</div>
						<div class="health-stats-inline queue-stat-text">
							<p class="queue-stat-line queue-stat-line-failed">
								<span class="queue-stat-number" id="queue-failed">0</span>
								<?php esc_html_e( 'messages have failed processing', 'contactin' ); ?>
							</p>
							<p class="queue-stat-line queue-stat-line-pending">
								<span class="queue-stat-number" id="queue-pending">0</span>
								<?php esc_html_e( 'messages are pending', 'contactin' ); ?>
							</p>
						</div>
					</div>

					<!-- Overall System Health -->
					<div class="analytics-card performance-system" data-health="system">
						<div class="card-heading">
							<h3><?php esc_html_e( 'System Alerts', 'contactin' ); ?></h3>
							<span class="date-range-label" id="performance-system-date-label"></span>
						</div>
						<p class="card-description"><?php esc_html_e( 'Consolidated health checks across queue, email, API, and CRM.', 'contactin' ); ?></p>
						<div class="health-indicator" id="system-status">
							<div class="status-dot pending"></div>
							<span class="status-text"><?php esc_html_e( 'Analyzing...', 'contactin' ); ?></span>
						</div>
						<div class="system-status-details" id="system-status-details">
							<ul class="status-issues-list"></ul>
						</div>
					</div>
				</div>

				<?php
				$queue_stats_by_type = $queue_stats_by_type ?? array();

				// Combine admin_email and user_email into 'email' totals
				$email_stats = array(
					'pending'    => 0,
					'processing' => 0,
					'retry'      => 0,
					'dlq'        => 0,
					'sent'       => 0,
				);

				if ( isset( $queue_stats_by_type['admin_email'] ) ) {
					$email_stats['pending']    += $queue_stats_by_type['admin_email']['pending'] ?? 0;
					$email_stats['processing'] += $queue_stats_by_type['admin_email']['processing'] ?? 0;
					$email_stats['retry']      += $queue_stats_by_type['admin_email']['retry'] ?? 0;
					$email_stats['dlq']        += $queue_stats_by_type['admin_email']['dlq'] ?? 0;
					$email_stats['sent']       += $queue_stats_by_type['admin_email']['sent'] ?? 0;
				}

				if ( isset( $queue_stats_by_type['user_email'] ) ) {
					$email_stats['pending']    += $queue_stats_by_type['user_email']['pending'] ?? 0;
					$email_stats['processing'] += $queue_stats_by_type['user_email']['processing'] ?? 0;
					$email_stats['retry']      += $queue_stats_by_type['user_email']['retry'] ?? 0;
					$email_stats['dlq']        += $queue_stats_by_type['user_email']['dlq'] ?? 0;
					$email_stats['sent']       += $queue_stats_by_type['user_email']['sent'] ?? 0;
				}

				$queues = array(
					'email' => array(
						'label'  => __( 'Email Processing', 'contactin' ),
						'counts' => $email_stats,
					),
					'crm'   => array(
						'label'  => __( 'CRM Processing', 'contactin' ),
						'counts' => $queue_stats_by_type['crm'] ?? array(
							'pending'    => 0,
							'processing' => 0,
							'retry'      => 0,
							'dlq'        => 0,
							'sent'       => 0,
						),
					),
					// Removed: webhook queue definition
				);

				foreach ( $queues as $key => $queue_data ) {
					$counts       = $queue_data['counts'];
					$has_dlq      = ( $counts['dlq'] ?? 0 ) > 0;
					$has_retry    = ( $counts['retry'] ?? 0 ) > 0;
					$high_pending = ( $counts['pending'] ?? 0 ) > 5;

					$state       = 'good';
					$state_label = __( 'Stable', 'contactin' );
					if ( $has_dlq || $has_retry ) {
						$state       = 'critical';
						$state_label = __( 'Attention', 'contactin' );
					} elseif ( $high_pending ) {
						$state       = 'warning';
						$state_label = __( 'Busy', 'contactin' );
					}

					$queues[ $key ]['state']       = $state;
					$queues[ $key ]['state_label'] = $state_label;
				}

				$dlq_total = intval( ( $queues['email']['counts']['dlq'] ?? 0 ) + ( $queues['crm']['counts']['dlq'] ?? 0 ) );
				$dlq_state = $dlq_total > 0 ? 'critical' : 'good';
				$dlq_label = $dlq_total > 0 ? __( 'Needs review', 'contactin' ) : __( 'Clear', 'contactin' );
				?>

				<div class="queue-status-header">
					<h3><?php esc_html_e( 'Message Processing Status', 'contactin' ); ?> <span class="date-range-label" id="queue-status-date-label"></span></h3>
					<p class="queue-description"><?php esc_html_e( 'Email and CRM throughput for the selected period.', 'contactin' ); ?></p>
				</div>

				<div class="queue-status-grid">
					<?php foreach ( $queues as $key => $queue_data ) : ?>
						<div class="analytics-card queue-card queue-<?php echo esc_attr( $key ); ?>">
							<div class="queue-card-head">
								<div class="queue-card-title">
									<span class="queue-name"><?php echo esc_html( $queue_data['label'] ); ?></span>
									<span class="queue-chip state-<?php echo esc_attr( $queue_data['state'] ); ?>"><?php echo esc_html( $queue_data['state_label'] ); ?></span>
								</div>
								<div class="queue-total">
									<span class="queue-total-label"><?php esc_html_e( 'Pending', 'contactin' ); ?></span>
									<span class="queue-total-value"><?php echo intval( $queue_data['counts']['pending'] ); ?></span>
								</div>
							</div>
							<div class="queue-counts">
								<div class="queue-stat">
									<span class="stat-label"><?php esc_html_e( 'Sent', 'contactin' ); ?></span>
									<span class="stat-value"><?php echo intval( $queue_data['counts']['sent'] ?? 0 ); ?></span>
								</div>
								<div class="queue-stat">
									<span class="stat-label"><?php esc_html_e( 'Failed', 'contactin' ); ?></span>
									<span class="stat-value"><?php echo intval( $queue_data['counts']['dlq'] ); ?></span>
								</div>
							</div>
						</div>
					<?php endforeach; ?>

					<div class="analytics-card queue-card queue-dlq">
						<div class="queue-card-head">
							<div class="queue-card-title">
								<span class="queue-name"><?php esc_html_e( 'Failed Messages (All Types)', 'contactin' ); ?></span>
								<span class="queue-chip state-<?php echo esc_attr( $dlq_state ); ?>"><?php echo esc_html( $dlq_label ); ?></span>
							</div>
							<div class="queue-total">
								<span class="queue-total-label"><?php esc_html_e( 'Total', 'contactin' ); ?></span>
								<span class="queue-total-value"><?php echo $dlq_total; ?></span>
							</div>
						</div>
						<p class="queue-help-text"><?php esc_html_e( 'Messages that failed email or CRM processing.', 'contactin' ); ?></p>
					</div>
				</div>
			</div>
		</div>

		<!-- SALESFORCE CRM TAB -->
		<div class="tab-pane" id="crm-pane" data-tab="crm">
			<div class="analytics-section">
				<h2><?php esc_html_e( 'Salesforce CRM Dashboard', 'contactin' ); ?></h2>

				<div class="analytics-grid">
					<!-- CRM Statistics -->
					<div class="analytics-card">
						<h3><?php esc_html_e( 'Sync Statistics', 'contactin' ); ?> <span class="date-range-label" id="crm-stats-date-label"></span></h3>
						<p class="card-description"><?php esc_html_e( 'CRM sync attempts for the selected period', 'contactin' ); ?></p>
						<div class="crm-stats">
							<div class="stat-item">
								<span class="stat-label"><?php esc_html_e( 'Total Synced', 'contactin' ); ?></span>
								<span class="stat-value" id="crm-total-synced">0</span>
							</div>
							<div class="stat-item">
								<span class="stat-label"><?php esc_html_e( 'Successful', 'contactin' ); ?></span>
								<span class="stat-value" id="crm-success-count">0</span>
							</div>
							<div class="stat-item">
								<span class="stat-label"><?php esc_html_e( 'Failed', 'contactin' ); ?></span>
								<span class="stat-value" id="crm-failed-count">0</span>
							</div>
							<div class="stat-item">
								<span class="stat-label"><?php esc_html_e( 'Pending', 'contactin' ); ?></span>
								<span class="stat-value" id="crm-pending-count">0</span>
							</div>
							<div class="stat-item">
								<span class="stat-label"><?php esc_html_e( 'Success Rate', 'contactin' ); ?></span>
								<span class="stat-value" id="crm-success-rate">0%</span>
							</div>
						</div>
					</div>

					<!-- CRM Health Status -->
					<div class="analytics-card">
						<h3><?php esc_html_e( 'Integration Health', 'contactin' ); ?> <span class="current-state-label"><?php esc_html_e( '(Current)', 'contactin' ); ?></span></h3>
						<div class="health-indicator" id="crm-health">
							<div class="status-dot pending"></div>
							<span class="status-text"><?php esc_html_e( 'Loading...', 'contactin' ); ?></span>
						</div>
						<div class="health-details" id="crm-health-details">
							<div class="health-item">
								<span class="health-label"><?php esc_html_e( 'Authorization', 'contactin' ); ?></span>
								<span class="health-status" id="crm-auth-status">—</span>
							</div>
						</div>
					</div>

					<!-- Sync Trend Chart -->
					<div class="analytics-card">
						<h3><?php esc_html_e( 'Daily Sync Activity', 'contactin' ); ?> <span class="date-range-label" id="crm-chart-date-label"></span></h3>
						<div class="chart-container">
							<canvas id="chart-crm-daily-stats"></canvas>
						</div>
					</div>

					<!-- Active Endpoints -->
					<div class="analytics-card">
						<h3><?php esc_html_e( 'Active Endpoints', 'contactin' ); ?> <span class="date-range-label" id="crm-endpoints-date-label"></span></h3>
						<div class="endpoint-list" id="crm-endpoints">
							<p class="placeholder-text"><?php esc_html_e( 'Loading endpoint data...', 'contactin' ); ?></p>
						</div>
					</div>

					<!-- Recent Activity -->
					<div class="analytics-card full-width">
						<h3><?php esc_html_e( 'Recent Sync Activity', 'contactin' ); ?></h3>
						<div class="activity-log" id="crm-activity-log">
							<p class="placeholder-text"><?php esc_html_e( 'Loading activity...', 'contactin' ); ?></p>
						</div>
					</div>
				</div>

			</div>
		</div>

		<!-- CRON TAB -->
		<div class="tab-pane" id="cron-pane" data-tab="cron">
			<div class="analytics-section">
				<h2><?php esc_html_e( 'Background Jobs & Cron Health', 'contactin' ); ?></h2>

				<div class="cron-summary-grid">
					<div class="analytics-card">
						<h4><?php esc_html_e( 'Overall Health', 'contactin' ); ?></h4>
						<div class="cron-health-score" id="cron-health-overall">—</div>
						<div class="cron-stat-pair">
							<span><?php esc_html_e( 'Executions (24h)', 'contactin' ); ?></span>
							<span id="cron-executions-24h">0</span>
						</div>
						<div class="cron-stat-pair">
							<span><?php esc_html_e( 'Failed (24h)', 'contactin' ); ?></span>
							<span id="cron-failed-24h">0</span>
						</div>
						<div class="cron-stat-pair">
							<span><?php esc_html_e( 'Avg Duration (24h)', 'contactin' ); ?></span>
							<span id="cron-avg-duration">—</span>
						</div>
					</div>

					<div class="analytics-card">
						<h4><?php esc_html_e( 'Email Queue Processor (24h)', 'contactin' ); ?></h4>
						<div class="cron-health-row" id="cron-contactinbox_process_email_queue-status">—</div>
						<div class="cron-meta-grid" id="cron-contactinbox_process_email_queue-meta">
							<span class="meta-label"><?php esc_html_e( 'Last Run', 'contactin' ); ?></span>
							<span class="meta-value" data-field="last_run">—</span>
							<span class="meta-label"><?php esc_html_e( 'Next Run', 'contactin' ); ?></span>
							<span class="meta-value" data-field="next_run">—</span>
							<span class="meta-label"><?php esc_html_e( 'Failures', 'contactin' ); ?></span>
							<span class="meta-value" data-field="failure_count">0</span>
							<span class="meta-label"><?php esc_html_e( 'Duration', 'contactin' ); ?></span>
							<span class="meta-value" data-field="last_duration_ms">—</span>
						</div>
					</div>

					<div class="analytics-card">
						<h4><?php esc_html_e( 'CRM Queue Processor (24h)', 'contactin' ); ?></h4>
						<div class="cron-health-row" id="cron-contactinbox_process_crm_queue-status">—</div>
						<div class="cron-meta-grid" id="cron-contactinbox_process_crm_queue-meta">
							<span class="meta-label"><?php esc_html_e( 'Last Run', 'contactin' ); ?></span>
							<span class="meta-value" data-field="last_run">—</span>
							<span class="meta-label"><?php esc_html_e( 'Next Run', 'contactin' ); ?></span>
							<span class="meta-value" data-field="next_run">—</span>
							<span class="meta-label"><?php esc_html_e( 'Failures', 'contactin' ); ?></span>
							<span class="meta-value" data-field="failure_count">0</span>
							<span class="meta-label"><?php esc_html_e( 'Duration', 'contactin' ); ?></span>
							<span class="meta-value" data-field="last_duration_ms">—</span>
						</div>
					</div>

					<div class="analytics-card">
						<h4><?php esc_html_e( 'Cleanup (Maintenance, 24h)', 'contactin' ); ?></h4>
						<div class="cron-health-row" id="cron-contactinbox_cleanup_cron-status">—</div>
						<div class="cron-meta-grid" id="cron-contactinbox_cleanup_cron-meta">
							<span class="meta-label"><?php esc_html_e( 'Last Run', 'contactin' ); ?></span>
							<span class="meta-value" data-field="last_run">—</span>
							<span class="meta-label"><?php esc_html_e( 'Next Run', 'contactin' ); ?></span>
							<span class="meta-value" data-field="next_run">—</span>
							<span class="meta-label"><?php esc_html_e( 'Failures', 'contactin' ); ?></span>
							<span class="meta-value" data-field="failure_count">0</span>
							<span class="meta-label"><?php esc_html_e( 'Duration', 'contactin' ); ?></span>
							<span class="meta-value" data-field="last_duration_ms">—</span>
						</div>
					</div>

					<div class="analytics-card">
						<h4><?php esc_html_e( 'GDPR Expiry (24h)', 'contactin' ); ?></h4>
						<div class="cron-health-row" id="cron-contactinbox_gdpr_expiry_check-status">—</div>
						<div class="cron-meta-grid" id="cron-contactinbox_gdpr_expiry_check-meta">
							<span class="meta-label"><?php esc_html_e( 'Last Run', 'contactin' ); ?></span>
							<span class="meta-value" data-field="last_run">—</span>
							<span class="meta-label"><?php esc_html_e( 'Next Run', 'contactin' ); ?></span>
							<span class="meta-value" data-field="next_run">—</span>
							<span class="meta-label"><?php esc_html_e( 'Failures', 'contactin' ); ?></span>
							<span class="meta-value" data-field="failure_count">0</span>
							<span class="meta-label"><?php esc_html_e( 'Duration', 'contactin' ); ?></span>
							<span class="meta-value" data-field="last_duration_ms">—</span>
						</div>
					</div>

					<div class="analytics-card">
						<h4><?php esc_html_e( 'Analytics Aggregation (24h)', 'contactin' ); ?></h4>
						<div class="cron-health-row" id="cron-contactin_daily_analytics_aggregation-status">—</div>
						<div class="cron-meta-grid" id="cron-contactin_daily_analytics_aggregation-meta">
							<span class="meta-label"><?php esc_html_e( 'Last Run', 'contactin' ); ?></span>
							<span class="meta-value" data-field="last_run">—</span>
							<span class="meta-label"><?php esc_html_e( 'Next Run', 'contactin' ); ?></span>
							<span class="meta-value" data-field="next_run">—</span>
							<span class="meta-label"><?php esc_html_e( 'Failures', 'contactin' ); ?></span>
							<span class="meta-value" data-field="failure_count">0</span>
							<span class="meta-label"><?php esc_html_e( 'Duration', 'contactin' ); ?></span>
							<span class="meta-value" data-field="last_duration_ms">—</span>
						</div>
					</div>
				</div>

				<div class="analytics-card">
					<h4><?php esc_html_e( 'Recent Cron Events (24h)', 'contactin' ); ?></h4>
					<table class="cron-table" id="cron-events-table">
						<thead>
							<tr>
								<th><?php esc_html_e( 'Job', 'contactin' ); ?></th>
								<th><?php esc_html_e( 'Status', 'contactin' ); ?></th>
								<th><?php esc_html_e( 'Last Run', 'contactin' ); ?></th>
								<th><?php esc_html_e( 'Duration', 'contactin' ); ?></th>
								<th><?php esc_html_e( 'Next Run', 'contactin' ); ?></th>
							</tr>
						</thead>
						<tbody>
							<tr><td colspan="5"><?php esc_html_e( 'No records available for the past 24 hours.', 'contactin' ); ?></td></tr>
						</tbody>
					</table>
				</div>
			</div>
		</div>

		<!-- USERS TAB -->
		<div class="tab-pane" id="users-pane" data-tab="users">
			<div class="analytics-section">
				<h2><?php esc_html_e( 'User Analytics', 'contactin' ); ?></h2>

				<div class="analytics-grid">
					<!-- Device Distribution -->
					<div class="analytics-card">
						<h3><?php esc_html_e( 'Device Distribution', 'contactin' ); ?></h3>
						<div class="chart-container">
							<canvas id="chart-device-distribution" data-chart-type="pie"></canvas>
						</div>
						<div class="chart-legend" id="legend-device"></div>
					</div>

					<!-- Geographic Distribution -->
					<div class="analytics-card">
						<h3><?php esc_html_e( 'Geographic Distribution (Top 10)', 'contactin' ); ?></h3>
						<div class="chart-container">
							<canvas id="chart-geographic-distribution" data-chart-type="bar"></canvas>
						</div>
						<div class="chart-legend" id="legend-geographic"></div>
					</div>

					<!-- Traffic Sources -->
					<div class="analytics-card">
						<h3><?php esc_html_e( 'Traffic Sources', 'contactin' ); ?></h3>
						<div class="chart-container">
							<canvas id="chart-traffic-sources" data-chart-type="doughnut"></canvas>
						</div>
						<div class="chart-legend" id="legend-sources"></div>
					</div>

					<!-- Browser Distribution -->
					<div class="analytics-card">
						<h3><?php esc_html_e( 'Browser Distribution', 'contactin' ); ?></h3>
						<div class="chart-container">
							<canvas id="chart-browser-distribution" data-chart-type="bar"></canvas>
						</div>
						<div class="chart-legend" id="legend-browser"></div>
					</div>
				</div>
			</div>
		</div>

	</div>
</div>

<div id="cin-analytics-help-modal" class="cin-modal cin-modal-hidden" data-cin-help-modal="true">
	<div class="cin-modal-overlay"></div>
	<div class="cin-modal-content" role="dialog" aria-modal="true">
		<div class="cin-modal-header">
			<h2><?php esc_html_e( 'Dashboard', 'contactin' ); ?></h2>
			<button type="button" class="cin-modal-close" aria-label="<?php esc_attr_e( 'Close', 'contactin' ); ?>">
				<span aria-hidden="true">&times;</span>
			</button>
		</div>
		<div class="cin-modal-body">
			<!-- Quick Navigation -->
			<div class="cin-help-nav">
				<h3><?php esc_html_e( 'Quick Navigation', 'contactin' ); ?></h3>
				<ul>
					<li><a href="#analytics-help-overview" class="cin-help-link"><?php esc_html_e( 'Overview', 'contactin' ); ?></a></li>
					<li><a href="#analytics-help-submissions" class="cin-help-link"><?php esc_html_e( 'Submissions', 'contactin' ); ?></a></li>
					<li><a href="#analytics-help-performance" class="cin-help-link"><?php esc_html_e( 'Performance', 'contactin' ); ?></a></li>
					<li><a href="#analytics-help-crm" class="cin-help-link"><?php esc_html_e( 'CRM Integration', 'contactin' ); ?></a></li>
					<li><a href="#analytics-help-users" class="cin-help-link"><?php esc_html_e( 'User Analytics', 'contactin' ); ?></a></li>
					<li><a href="#analytics-help-cron" class="cin-help-link"><?php esc_html_e( 'Background Jobs', 'contactin' ); ?></a></li>
					<li><a href="#analytics-help-troubleshoot" class="cin-help-link"><?php esc_html_e( 'Troubleshooting', 'contactin' ); ?></a></li>
				</ul>
			</div>

			<!-- Overview -->
			<div id="analytics-help-overview" class="cin-help-section">
				<h3><?php esc_html_e( '📊 Overview', 'contactin' ); ?></h3>
				<div class="cin-help-item">
					<p><?php esc_html_e( 'Welcome to the Analytics Dashboard! This comprehensive guide explains each section, metric, and how to interpret the business intelligence (BI) data to get the most value from your contact form analytics.', 'contactin' ); ?></p>
					<ul>
						<li><?php esc_html_e( 'Track submission trends and conversion rates', 'contactin' ); ?></li>
						<li><?php esc_html_e( 'Monitor email delivery and CRM sync health', 'contactin' ); ?></li>
						<li><?php esc_html_e( 'Understand spam patterns and reCAPTCHA effectiveness', 'contactin' ); ?></li>
						<li><?php esc_html_e( 'Diagnose issues quickly with actionable insights', 'contactin' ); ?></li>
					</ul>
				</div>
			</div>

			<!-- Submissions Analytics -->
			<div id="analytics-help-submissions" class="cin-help-section">
				<h3><?php esc_html_e( '📧 Submissions Analytics', 'contactin' ); ?></h3>
				<div class="cin-help-item">
					<h4><?php esc_html_e( 'Submission Volume Trend', 'contactin' ); ?></h4>
					<p><?php esc_html_e( 'Chart showing daily submission counts over time. Use filters to zoom in on specific date ranges.', 'contactin' ); ?></p>
				</div>
				<div class="cin-help-item">
					<h4><?php esc_html_e( 'Submission Status Breakdown', 'contactin' ); ?></h4>
					<ul>
						<li><strong><?php esc_html_e( 'Read', 'contactin' ); ?>:</strong> <?php esc_html_e( 'Messages that have been opened and reviewed.', 'contactin' ); ?></li>
						<li><strong><?php esc_html_e( 'Unread', 'contactin' ); ?>:</strong> <?php esc_html_e( 'New submissions awaiting review.', 'contactin' ); ?></li>
						<li><strong><?php esc_html_e( 'Archived', 'contactin' ); ?>:</strong> <?php esc_html_e( 'Messages moved to archive for record-keeping.', 'contactin' ); ?></li>
					</ul>
				</div>
				<div class="cin-help-item">
					<h4><?php esc_html_e( 'Spam Detection', 'contactin' ); ?></h4>
					<p><?php esc_html_e( 'Monitors spam attempts blocked by reCAPTCHA and validation rules. Watch for spikes which may indicate targeted attacks.', 'contactin' ); ?></p>
				</div>
				<div class="cin-help-item">
					<h4><?php esc_html_e( 'Queue Success Rates', 'contactin' ); ?></h4>
					<ul>
						<li><strong><?php esc_html_e( 'Email Queue', 'contactin' ); ?>:</strong> <?php esc_html_e( 'Percentage of emails successfully delivered.', 'contactin' ); ?></li>
						<li><strong><?php esc_html_e( 'CRM Queue', 'contactin' ); ?>:</strong> <?php esc_html_e( 'Percentage of CRM sync attempts that succeeded.', 'contactin' ); ?></li>
					</ul>
				</div>
			</div>

			<!-- Performance -->
			<div id="analytics-help-performance" class="cin-help-section">
				<h3><?php esc_html_e( '⚡ System Performance & Health', 'contactin' ); ?></h3>
				<div class="cin-help-item">
					<h4><?php esc_html_e( 'Email Delivery Health', 'contactin' ); ?></h4>
					<p><?php esc_html_e( 'Shows the success rate of email delivery. A healthy rate is 95% or higher. Failures may indicate SMTP configuration issues, ISP rate limits, or temporary network problems.', 'contactin' ); ?></p>
				</div>
				<div class="cin-help-item">
					<h4><?php esc_html_e( 'Queue Health', 'contactin' ); ?></h4>
					<ul>
						<li><strong><?php esc_html_e( 'Pending', 'contactin' ); ?>:</strong> <?php esc_html_e( 'Messages waiting to be processed.', 'contactin' ); ?></li>
						<li><strong><?php esc_html_e( 'Processing', 'contactin' ); ?>:</strong> <?php esc_html_e( 'Items currently being handled.', 'contactin' ); ?></li>
						<li><strong><?php esc_html_e( 'Retry', 'contactin' ); ?>:</strong> <?php esc_html_e( 'Failed items queued for retry.', 'contactin' ); ?></li>
						<li><strong><?php esc_html_e( 'DLQ', 'contactin' ); ?>:</strong> <?php esc_html_e( 'Dead Letter Queue - items that failed after max retries.', 'contactin' ); ?></li>
					</ul>
				</div>
				<div class="cin-help-item">
					<h4><?php esc_html_e( 'API Health', 'contactin' ); ?></h4>
					<p><?php esc_html_e( 'Tracks requests to REST API endpoints and error rates. A healthy API shows low error rates (< 1%).', 'contactin' ); ?></p>
				</div>
				<div class="cin-help-item">
					<h4><?php esc_html_e( 'Spam Intelligence', 'contactin' ); ?></h4>
					<p><?php esc_html_e( 'Displays average reCAPTCHA scores: 🟢 Good (≥0.75), 🟡 Medium (0.5-0.75), 🔴 Suspicious (<0.5). Monitor trends to detect abuse patterns.', 'contactin' ); ?></p>
				</div>
			</div>

			<!-- CRM Integration -->
			<div id="analytics-help-crm" class="cin-help-section">
				<h3><?php esc_html_e( '🔗 Salesforce CRM Dashboard', 'contactin' ); ?></h3>
				<div class="cin-help-item">
					<h4><?php esc_html_e( 'Sync Statistics', 'contactin' ); ?></h4>
					<ul>
						<li><strong><?php esc_html_e( 'Total Synced', 'contactin' ); ?>:</strong> <?php esc_html_e( 'All records sent to CRM.', 'contactin' ); ?></li>
						<li><strong><?php esc_html_e( 'Successful', 'contactin' ); ?>:</strong> <?php esc_html_e( 'Records that reached CRM without errors.', 'contactin' ); ?></li>
						<li><strong><?php esc_html_e( 'Failed', 'contactin' ); ?>:</strong> <?php esc_html_e( 'Sync attempts that encountered errors.', 'contactin' ); ?></li>
						<li><strong><?php esc_html_e( 'Pending', 'contactin' ); ?>:</strong> <?php esc_html_e( 'Records queued but not yet processed.', 'contactin' ); ?></li>
					</ul>
				</div>
				<div class="cin-help-item">
					<h4><?php esc_html_e( 'Integration Health', 'contactin' ); ?></h4>
					<p><?php esc_html_e( 'Shows current authorization status and connection health. If unhealthy, check your OAuth token, API limits, or network connectivity.', 'contactin' ); ?></p>
				</div>
				<div class="cin-help-item">
					<h4><?php esc_html_e( 'Daily Sync Activity', 'contactin' ); ?></h4>
					<p><?php esc_html_e( 'Chart showing successful vs failed syncs per day. Sudden drops may indicate authentication or schema issues.', 'contactin' ); ?></p>
				</div>
				<div class="cin-help-item">
					<h4><?php esc_html_e( 'Active Endpoints', 'contactin' ); ?></h4>
					<p><?php esc_html_e( 'Lists Salesforce endpoints being targeted by this site. Each shows success rate and error details.', 'contactin' ); ?></p>
				</div>
			</div>

			<!-- User Analytics -->
			<div id="analytics-help-users" class="cin-help-section">
				<h3><?php esc_html_e( '👥 User Analytics', 'contactin' ); ?></h3>
				<div class="cin-help-item">
					<h4><?php esc_html_e( 'Device Distribution', 'contactin' ); ?></h4>
					<p><?php esc_html_e( 'Shows submissions by device type (mobile, tablet, desktop). Helps identify if your forms are mobile-friendly or need optimization.', 'contactin' ); ?></p>
				</div>
				<div class="cin-help-item">
					<h4><?php esc_html_e( 'Geographic Distribution', 'contactin' ); ?></h4>
					<p><?php esc_html_e( 'Top 10 countries/regions submitting forms. Useful for identifying regional trends and potential localization needs.', 'contactin' ); ?></p>
				</div>
				<div class="cin-help-item">
					<h4><?php esc_html_e( 'Traffic Sources', 'contactin' ); ?></h4>
					<p><?php esc_html_e( 'Breakdown of where submissions originate (organic, direct, referral, etc.). Guides marketing and content strategy.', 'contactin' ); ?></p>
				</div>
				<div class="cin-help-item">
					<h4><?php esc_html_e( 'Browser Distribution', 'contactin' ); ?></h4>
					<p><?php esc_html_e( 'Shows which browsers users employ. Important for testing and identifying browser-specific issues.', 'contactin' ); ?></p>
				</div>
			</div>

			<!-- Background Jobs -->
			<div id="analytics-help-cron" class="cin-help-section">
				<h3><?php esc_html_e( '⚙️ Background Jobs & Cron Health', 'contactin' ); ?></h3>
				<div class="cin-help-item">
					<h4><?php esc_html_e( 'Overall Health Score', 'contactin' ); ?></h4>
					<p><?php esc_html_e( 'Composite score based on job execution success, latency, and error rates. Healthy systems show 95%+ success.', 'contactin' ); ?></p>
				</div>
				<div class="cin-help-item">
					<h4><?php esc_html_e( 'Core Background Jobs', 'contactin' ); ?></h4>
					<ul>
						<li><strong><?php esc_html_e( 'Email Queue Processor', 'contactin' ); ?>:</strong> <?php esc_html_e( 'Handles email deliveries every few minutes.', 'contactin' ); ?></li>
						<li><strong><?php esc_html_e( 'CRM Queue Processor', 'contactin' ); ?>:</strong> <?php esc_html_e( 'Handles CRM record syncs and attachment uploads every few minutes.', 'contactin' ); ?></li>
						<li><strong><?php esc_html_e( 'Cleanup/Maintenance', 'contactin' ); ?>:</strong> <?php esc_html_e( 'Runs daily to purge old logs and temp files.', 'contactin' ); ?></li>
						<li><strong><?php esc_html_e( 'GDPR Expiry Check', 'contactin' ); ?>:</strong> <?php esc_html_e( 'Automated data retention enforcement.', 'contactin' ); ?></li>
						<li><strong><?php esc_html_e( 'Analytics Aggregation', 'contactin' ); ?>:</strong> <?php esc_html_e( 'Rolls up daily stats for reporting.', 'contactin' ); ?></li>
					</ul>
				</div>
				<div class="cin-help-item">
					<h4><?php esc_html_e( 'Cron Scheduling Architecture', 'contactin' ); ?></h4>
					<ul>
						<li><?php esc_html_e( 'All recurring cron schedules are created once during plugin activation.', 'contactin' ); ?></li>
						<li><?php esc_html_e( 'No runtime scheduling occurs to prevent duplicate schedules.', 'contactin' ); ?></li>
						<li><?php esc_html_e( 'Manual processing operations trigger single-event execution without affecting recurring schedules.', 'contactin' ); ?></li>
						<li><?php esc_html_e( 'Use the Maintenance page to reschedule or manually trigger processing as needed.', 'contactin' ); ?></li>
					</ul>
				</div>
				<div class="cin-help-item">
					<h4><?php esc_html_e( 'Monitoring Job Runs', 'contactin' ); ?></h4>
					<ul>
						<li><strong><?php esc_html_e( 'Last Run', 'contactin' ); ?>:</strong> <?php esc_html_e( 'Timestamp of the most recent execution.', 'contactin' ); ?></li>
						<li><strong><?php esc_html_e( 'Next Run', 'contactin' ); ?>:</strong> <?php esc_html_e( 'Scheduled time for the next execution.', 'contactin' ); ?></li>
						<li><strong><?php esc_html_e( 'Failures (24h)', 'contactin' ); ?>:</strong> <?php esc_html_e( 'Count of failed attempts in the past day.', 'contactin' ); ?></li>
						<li><strong><?php esc_html_e( 'Duration', 'contactin' ); ?>:</strong> <?php esc_html_e( 'How long the last execution took.', 'contactin' ); ?></li>
					</ul>
				</div>
			</div>

			<!-- Troubleshooting -->
			<div id="analytics-help-troubleshoot" class="cin-help-section">
				<h3><?php esc_html_e( '🧯 Troubleshooting Tips', 'contactin' ); ?></h3>
				<div class="cin-help-item">
					<h4><?php esc_html_e( 'High Email Failure Rate', 'contactin' ); ?></h4>
					<ul>
						<li><?php esc_html_e( 'Check SMTP settings and credentials.', 'contactin' ); ?></li>
						<li><?php esc_html_e( 'Verify domain SPF/DKIM records are configured.', 'contactin' ); ?></li>
						<li><?php esc_html_e( 'Check ISP rate limits and bounce feedback loops.', 'contactin' ); ?></li>
					</ul>
				</div>
				<div class="cin-help-item">
					<h4><?php esc_html_e( 'CRM Sync Failures', 'contactin' ); ?></h4>
					<ul>
						<li><?php esc_html_e( 'Verify OAuth token is still valid and not revoked.', 'contactin' ); ?></li>
						<li><?php esc_html_e( 'Check for API rate limits in Salesforce.', 'contactin' ); ?></li>
						<li><?php esc_html_e( 'Ensure field mappings match current Salesforce schema.', 'contactin' ); ?></li>
					</ul>
				</div>
				<div class="cin-help-item">
					<h4><?php esc_html_e( 'Queue Processing Delays', 'contactin' ); ?></h4>
					<ul>
						<li><?php esc_html_e( 'Check if background jobs are running (cron enabled).', 'contactin' ); ?></li>
						<li><?php esc_html_e( 'Monitor DLQ for stuck items that need manual intervention.', 'contactin' ); ?></li>
						<li><?php esc_html_e( 'Review job duration trends for performance issues.', 'contactin' ); ?></li>
					</ul>
				</div>
				<div class="cin-help-item">
					<h4><?php esc_html_e( 'Spam Score Anomalies', 'contactin' ); ?></h4>
					<ul>
						<li><?php esc_html_e( 'Check reCAPTCHA v3 sensitivity and score thresholds.', 'contactin' ); ?></li>
						<li><?php esc_html_e( 'Review failed validation rules and adjust if too strict.', 'contactin' ); ?></li>
						<li><?php esc_html_e( 'Verify that bots are not gaming validation patterns.', 'contactin' ); ?></li>
					</ul>
				</div>
			</div>
		</div>
		<div class="cin-modal-footer">
			<p><?php esc_html_e( 'For further assistance, consult the full documentation or contact support.', 'contactin' ); ?></p>
		</div>
	</div>
</div>
