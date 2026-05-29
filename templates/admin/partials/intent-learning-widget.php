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

<div class="contactin-card contactin-learning-widget">
	<h2><?php esc_html_e( 'Classifier Self-Learning', 'contactin' ); ?></h2>
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
