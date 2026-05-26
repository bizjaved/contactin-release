<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// phpcs:disable WordPress.WP.I18n.NonSingularStringLiteralDomain, WordPress.WP.I18n.MissingTranslatorsComment, WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound, WordPress.Security.EscapeOutput.OutputNotEscaped
?>
<div class="contactin-performance-metrics">
	<ul class="performance-list">
		<li class="performance-item">
			<div class="performance-label">
				<?php esc_html_e( 'Queue Status', 'contactin' ); ?>
			</div>
			<div class="performance-value">
				<div>
					<div class="performance-stat" data-cin-perf="queue-pending"><?php echo intval( $queue['pending'] ?? 0 ); ?></div>
					<div class="performance-message" data-cin-perf="queue-message"><?php echo esc_html( $queue['message'] ?? '' ); ?></div>
				</div>
				<div class="status-indicator status-<?php echo esc_attr( $queue['status'] ?? 'pending' ); ?>" data-cin-perf-status="queue"></div>
			</div>
		</li>

		<li class="performance-item">
			<div class="performance-label">
				<?php esc_html_e( 'Email Delivery', 'contactin' ); ?>
			</div>
			<div class="performance-value">
				<div>
					<div class="performance-stat" data-cin-perf="email-rate"><?php echo floatval( $email['rate'] ?? 0 ); ?>%</div>
					<div class="performance-message" data-cin-perf="email-message"><?php echo esc_html( $email['message'] ?? '' ); ?></div>
				</div>
				<div class="status-indicator status-<?php echo esc_attr( $email['status'] ?? 'pending' ); ?>" data-cin-perf-status="email"></div>
			</div>
		</li>
	</ul>

	<div class="system-status <?php echo esc_attr( $system_status ); ?>" data-cin-perf="system-status">
		<div class="system-status-text">
			<div class="status-indicator status-<?php echo esc_attr( $system_status ); ?>" data-cin-perf-status="system" style="margin: 0;"></div>
			<span data-cin-perf="system-status-label">
				<?php
				switch ( $system_status ) {
					case 'good':
						esc_html_e( 'System Healthy', 'contactin' );
						break;
					case 'warning':
						esc_html_e( 'System Warning', 'contactin' );
						break;
					case 'error':
						esc_html_e( 'System Issues', 'contactin' );
						break;
				}
				?>
			</span>
		</div>
	</div>

	<div style="text-align: center; border-top: 1px solid #e0e0e0; padding-top: 12px; margin-top: 12px;">
		<a href="<?php echo esc_url( $analytics_url ); ?>" class="cin-widget-btn primary">
			<?php esc_html_e( 'View Analytics Dashboard', 'contactin' ); ?>
		</a>
	</div>
</div>
