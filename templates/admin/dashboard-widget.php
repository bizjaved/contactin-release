<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
/**
 * Dashboard Widget Template
 *
 * @package ContactIn\Admin
 *
 * Variables passed:
 * @var int $today
 * @var int $week
 * @var int $month
 * @var int $year
 * @var int $unread_count
 * @var int $read_count
 * @var int $total_count
 * @var object|null $recent_message
 * @var array $trend_data
 * @var string $inbox_url
 */

use ContactInbox\Core\Config;

// phpcs:disable WordPress.WP.I18n.NonSingularStringLiteralDomain, WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! isset( $inbox_url ) ) {
	$inbox_url = admin_url( 'admin.php?page=contactin_inbox' );
}
?>

<div class="cin-dashboard-widget">

	<!-- Time-based Stats - Professional Card Grid -->
	<div class="cin-widget-metrics">
		<div class="cin-metric-card">
			<div class="cin-metric-value"><?php echo intval( $today ?? 0 ); ?></div>
			<div class="cin-metric-label"><?php esc_html_e( 'Today', 'contactin' ); ?></div>
		</div>
		<div class="cin-metric-card">
			<div class="cin-metric-value"><?php echo intval( $week ?? 0 ); ?></div>
			<div class="cin-metric-label"><?php esc_html_e( 'Week', 'contactin' ); ?></div>
		</div>
		<div class="cin-metric-card">
			<div class="cin-metric-value"><?php echo intval( $month ?? 0 ); ?></div>
			<div class="cin-metric-label"><?php esc_html_e( 'Month', 'contactin' ); ?></div>
		</div>
		<div class="cin-metric-card">
			<div class="cin-metric-value"><?php echo intval( $year ?? 0 ); ?></div>
			<div class="cin-metric-label"><?php esc_html_e( 'Year', 'contactin' ); ?></div>
		</div>
	</div>

	<!-- Status Breakdown - Card Grid -->
	<div class="cin-widget-status">
		<h4><?php esc_html_e( 'Status Breakdown', 'contactin' ); ?></h4>
		<div class="cin-status-metrics">
			<div class="cin-status-card cin-card-unread">
				<div class="cin-status-value"><?php echo intval( $unread_count ?? 0 ); ?></div>
				<div class="cin-status-label"><?php esc_html_e( 'Unread', 'contactin' ); ?></div>
			</div>
			<div class="cin-status-card cin-card-total">
				<div class="cin-status-value"><?php echo intval( $total_count ?? 0 ); ?></div>
				<div class="cin-status-label"><?php esc_html_e( 'Total', 'contactin' ); ?></div>
			</div>
		</div>
	</div>

	<!-- 7-Day Trend Chart -->
	<div class="cin-widget-chart">
		<h4><?php esc_html_e( 'Last 7 Days', 'contactin' ); ?></h4>
		<canvas id="contactin-trend-chart" height="100"></canvas>
	</div>

	<!-- Action Buttons -->
	<div class="cin-widget-actions">
		<a href="<?php echo esc_url( $inbox_url ); ?>" class="cin-widget-btn primary">
			<?php esc_html_e( 'View Inbox', 'contactin' ); ?>
		</a>
		<a href="<?php echo esc_url( add_query_arg( array( 'page' => 'contactin-analytics' ), admin_url( 'admin.php' ) ) ); ?>" class="cin-widget-btn primary">
			<?php esc_html_e( 'View Analytics', 'contactin' ); ?>
		</a>
	</div>

</div>

<!-- Chart Script -->
<script>
	document.addEventListener( 'DOMContentLoaded', function() {
		const ctx = document.getElementById( 'contactin-trend-chart' );
		if ( !ctx ) return;

		const labels = <?php echo wp_json_encode( array_keys( $trend_data ?? array() ) ); ?>;
		const values = <?php echo wp_json_encode( array_values( $trend_data ?? array() ) ); ?>;

		if ( labels.length === 0 ) {
			// No data available
			return;
		}

		new Chart( ctx, {
			type: 'line',
			data: {
				labels: labels,
				datasets: [{
					label: 'Messages',
					data: values,
					borderColor: '#0073aa',
					backgroundColor: 'rgba(0, 115, 170, 0.1)',
					borderWidth: 2,
					fill: true,
					tension: 0.4,
					pointRadius: 4,
					pointBackgroundColor: '#0073aa',
					pointBorderColor: '#fff',
					pointBorderWidth: 2
				}]
			},
			options: {
				responsive: true,
				maintainAspectRatio: true,
				plugins: {
					legend: {
						display: false
					}
				},
				scales: {
					y: {
						beginAtZero: true,
						max: Math.max( ...values, 1 ) + 1
					}
				}
			}
		} );
	} );
</script>
