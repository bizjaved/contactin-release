<?php
/**
 * Intent Learning Widget Partial
 *
 * Displays classifier self-learning progress.
 * Shows corrections logged, insights found, and improvements pending review.
 *
 * @package ContactIn\Templates
 */

use ContactInbox\Core\Config;

// phpcs:disable WordPress.WP.I18n.NonSingularStringLiteralDomain, WordPress.NamingConventions.PrefixAllGlobals

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// PRO-locked on maintenance page: keep static UI and avoid learning analysis work.
$stats = array(
	'total_corrections'              => 'Nil',
	'this_week'                      => 'Nil',
	'avg_original_confidence'        => 'Nil',
	'estimated_accuracy_improvement' => 'Nil',
	'ready_for_training'             => false,
);
?>

<style>
.contactin-learning-widget {
	grid-column: 1 / -1;
}

.contactin-learning-column-layout {
	display: grid;
	grid-template-columns: 1fr 1fr 1fr;
	gap: 20px;
	margin-top: 20px;
}

.contactin-learning-card {
	background: #fff;
	border: 1px solid #e0e0e0;
	border-radius: 4px;
	padding: 20px;
	box-shadow: 0 1px 3px rgba(0,0,0,0.05);
}

.contactin-learning-card h3 {
	margin: 0 0 15px 0;
	padding-bottom: 12px;
	border-bottom: 2px solid #ddd;
	font-size: 14px;
	font-weight: 600;
	color: #333;
}

.contactin-learning-stats table,
.contactin-learning-patterns table {
	margin-bottom: 0;
	width: 100%;
	border-collapse: collapse;
}

.contactin-learning-stats td {
	padding: 12px 0;
	border-bottom: 1px solid #f0f0f0;
}

.contactin-learning-stats tr:last-child td {
	border-bottom: none;
}

.contactin-learning-stats td:first-child {
	font-weight: 600;
	color: #333;
	width: 45%;
}

.contactin-learning-stats td:last-child {
	width: 55%;
}

.contactin-learning-value {
	font-size: 1.4em;
	color: #0073aa;
	display: block;
	font-weight: bold;
}

.contactin-learning-value.positive {
	color: #2ea94f;
}

.contactin-learning-subtitle {
	color: #666;
	font-weight: normal;
	font-size: 0.9em;
	display: block;
	margin-top: 3px;
}

.contactin-learning-insights ul {
	margin: 0;
	padding-left: 20px;
	list-style: none;
}

.contactin-learning-insights li {
	margin-bottom: 12px;
	line-height: 1.5;
	font-size: 0.95em;
}

.contactin-learning-insights-bullet {
	font-weight: bold;
	margin-right: 8px;
	display: inline-block;
}

.contactin-learning-insights-bullet.high {
	color: #d63638;
}

.contactin-learning-insights-bullet.medium {
	color: #f56e28;
}

.contactin-learning-insights-bullet.low {
	color: #82878c;
}

.contactin-learning-patterns th,
.contactin-learning-patterns td {
	padding: 10px 12px;
	text-align: left;
	border-bottom: 1px solid #f0f0f0;
}

.contactin-learning-patterns tr:last-child td {
	border-bottom: none;
}

.contactin-learning-patterns th {
	font-weight: 600;
	color: #333;
	font-size: 0.9em;
}

.contactin-learning-patterns th:last-child {
	text-align: right;
	width: 60px;
}

.contactin-learning-patterns td:last-child {
	text-align: right;
}

.contactin-learning-pattern-code {
	background-color: #f5f5f5;
	padding: 2px 6px;
	border-radius: 3px;
	font-family: 'Courier New', monospace;
	font-size: 0.85em;
	color: #0073aa;
}

.contactin-learning-ready-yes {
	color: #2ea94f;
	font-weight: bold;
	font-size: 1.1em;
}

.contactin-learning-footer {
	margin-top: 20px;
	padding-top: 20px;
	border-top: 1px solid #e0e0e0;
}

.contactin-learning-actions {
	display: flex;
	gap: 10px;
	flex-wrap: wrap;
}

.contactin-learning-actions .button {
	flex: 1;
	min-width: 160px;
}

@media (max-width: 1200px) {
	.contactin-learning-column-layout {
		gap: 15px;
	}
}
</style>

<div class="contactin-card contactin-learning-widget">
	<h2><?php esc_html_e( 'Classifier Self-Learning', 'contactin' ); ?> <button type="button" class="button button-small disabled" data-upgrade-only="1" aria-disabled="true"><span class="cin-pro-badge cin-pro-badge--button"><?php esc_html_e( 'PRO', 'contactin' ); ?></span></button></h2>
	<p><?php esc_html_e( 'Learn from your corrections to improve categorization accuracy.', 'contactin' ); ?></p>
	
	<div class="contactin-learning-column-layout">
		<!-- Card 1: Statistics -->
		<div class="contactin-learning-card contactin-learning-stats-card">
			<h3><?php esc_html_e( 'Statistics', 'contactin' ); ?></h3>
			<table class="widefat">
				<tbody>
					<tr>
						<td>
							<strong><?php esc_html_e( 'Corrections Logged', 'contactin' ); ?></strong>
						</td>
						<td>
							<span class="contactin-learning-value">
								<?php echo esc_html( $stats['total_corrections'] ); ?>
							</span>
							<span class="contactin-learning-subtitle">
								(<?php echo esc_html( $stats['this_week'] ); ?> this week)
							</span>
						</td>
					</tr>
					<tr>
						<td>
							<strong><?php esc_html_e( 'Avg. Original Confidence', 'contactin' ); ?></strong>
						</td>
						<td>
							<span class="contactin-learning-value">
								<?php echo esc_html( $stats['avg_original_confidence'] ); ?>
							</span>
							<span class="contactin-learning-subtitle">
								(lower = more room to improve)
							</span>
						</td>
					</tr>
					<tr>
						<td>
							<strong><?php esc_html_e( 'Est. Accuracy Improvement', 'contactin' ); ?></strong>
						</td>
						<td>
							<span class="contactin-learning-value positive">
								<?php echo esc_html( $stats['estimated_accuracy_improvement'] ); ?>
							</span>
							<span class="contactin-learning-subtitle">
								potential from feedback
							</span>
						</td>
					</tr>
					<tr>
						<td>
							<strong><?php esc_html_e( 'Ready for Analysis', 'contactin' ); ?></strong>
						</td>
						<td>
							<span class="contactin-learning-subtitle">Nil</span>
						</td>
					</tr>
				</tbody>
			</table>
		</div>

		<!-- Card 2: Latest Insights -->
		<div class="contactin-learning-card contactin-learning-insights-card">
			<h3><?php esc_html_e( 'Latest Insights', 'contactin' ); ?></h3>
			<p style="color: #999; margin: 0; font-size: 0.95em;">Nil</p>
		</div>

		<!-- Card 3: Top Correction Patterns -->
		<div class="contactin-learning-card contactin-learning-patterns-card">
			<h3><?php esc_html_e( 'Top Correction Patterns', 'contactin' ); ?></h3>
			<p style="color: #999; margin: 0; font-size: 0.95em;">Nil</p>
		</div>
	</div>
</div>
