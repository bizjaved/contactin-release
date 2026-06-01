<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// phpcs:disable WordPress.WP.I18n.NonSingularStringLiteralDomain, WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound

// Ensure variables are set with defaults.
if ( ! isset( $today_count ) ) {
	$today_count = 0;
}
if ( ! isset( $emails_sent ) ) {
	$emails_sent = 0;
}
if ( ! isset( $conversion_rate ) ) {
	$conversion_rate = 0;
}
if ( ! isset( $trend_labels ) ) {
	$trend_labels = array();
}
if ( ! isset( $trend_values ) ) {
	$trend_values = array();
}
if ( ! isset( $analytics_url ) ) {
	$analytics_url = admin_url( 'admin.php?page=contactin-analytics' );
}
?>
<div class="contactin-submission-metrics">
	<div class="cin-widget-metrics">
		<div class="cin-metric-card">
			<div class="cin-metric-label"><?php esc_html_e( 'Today\'s Submissions', 'contactin' ); ?></div>
			<div class="cin-metric-value" data-cin-submissions="today-count"><?php echo esc_html( (string) intval( $today_count ) ); ?></div>
		</div>

		<div class="cin-metric-card">
			<div class="cin-metric-label">
				<?php esc_html_e( 'Emails Sent', 'contactin' ); ?>
				<div style="font-size: 11px; color: #999; font-weight: normal; margin-top: 2px;">
					<?php esc_html_e( 'Successfully delivered', 'contactin' ); ?>
				</div>
			</div>
			<div class="cin-metric-value status-completed" data-cin-submissions="emails-sent"><?php echo esc_html( (string) intval( $emails_sent ) ); ?></div>
		</div>

		<div class="cin-metric-card">
			<div class="cin-metric-label">
				<?php esc_html_e( 'Conversion Rate', 'contactin' ); ?>
			</div>
			<div class="cin-metric-value status-completed" data-cin-submissions="conversion-rate"><?php echo esc_html( number_format_i18n( (float) $conversion_rate, 1 ) ); ?>%</div>
		</div>
	</div>

	<div class="cin-widget-chart">
		<h4><?php esc_html_e( '7-Day Trend', 'contactin' ); ?></h4>
		<canvas id="contactin-submission-sparkline" height="80"></canvas>
	</div>

	<div style="text-align: center; border-top: 1px solid #e0e0e0; padding-top: 12px; margin-top: 12px;">
		<a href="<?php echo esc_url( $analytics_url ); ?>" class="cin-widget-btn primary">
			<?php esc_html_e( 'View Analytics Dashboard', 'contactin' ); ?>
		</a>
	</div>
</div>
