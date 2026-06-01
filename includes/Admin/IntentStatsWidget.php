<?php
/**
 * Intent Statistics Widget
 *
 * Displays message intent distribution and trends on the dashboard
 *
 * @package ContactIn\Admin
 */

declare(strict_types=1);

namespace ContactInbox\Admin;

use ContactInbox\Core\Config;
use ContactInbox\Core\IntentClassifier;
use ContactInbox\Core\DB;

// phpcs:disable WordPress.WP.I18n.NonSingularStringLiteralDomain

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class IntentStatsWidget {

	/**
	 * Render intent statistics widget HTML
	 *
	 * @return void
	 */
	public static function render(): void {
		// Check if intent classification is enabled
		$settings = \ContactInbox\Core\Settings::get_settings();
		if ( empty( $settings['intent_enable'] ) ) {
			echo '<p>' . esc_html__( 'Intent classification is disabled.', 'contactin' ) . '</p>';
			return;
		}

		// Get stats
		$db         = DB::instance();
		$stats      = $db->get_intent_stats();
		$trend      = $db->get_intent_trend( 7 );
		$categories = IntentClassifier::get_categories();

		if ( empty( $stats ) ) {
			echo '<p>' . esc_html__( 'No classified messages yet.', 'contactin' ) . '</p>';
			return;
		}

		$total = array_sum( $stats );

		?>
		<div class="contactin-intent-stats-widget">
			<h3><?php esc_html_e( 'Message Intent Distribution', 'contactin' ); ?></h3>
			
			<div class="intent-distribution">
				<?php
				foreach ( $stats as $category => $count ) :
					$label      = $categories[ $category ] ?? ucfirst( $category );
					$color      = IntentClassifier::get_category_color( $category );
					$percentage = $total > 0 ? round( ( $count / $total ) * 100, 1 ) : 0;
					?>
					<div class="intent-stat-row">
						<div class="intent-stat-label">
							<span class="cin-intent-badge cin-intent-<?php echo esc_attr( $color ); ?>">
								<?php echo esc_html( $label ); ?>
							</span>
						</div>
						<div class="intent-stat-bar">
							<div class="intent-bar-fill intent-<?php echo esc_attr( $color ); ?>" 
								style="width: <?php echo esc_attr( $percentage ); ?>%">
							</div>
						</div>
						<div class="intent-stat-numbers">
							<span class="intent-count"><?php echo esc_html( $count ); ?></span>
							<span class="intent-percentage"><?php echo esc_html( $percentage ); ?>%</span>
						</div>
					</div>
				<?php endforeach; ?>
			</div>

			<h4 style="margin-top: 20px;"><?php esc_html_e( '7-Day Trend', 'contactin' ); ?></h4>
			<div class="intent-trend-table">
				<table>
					<thead>
						<tr>
							<th><?php esc_html_e( 'Date', 'contactin' ); ?></th>
							<?php foreach ( array_keys( $categories ) as $cat ) : ?>
								<th title="<?php echo esc_attr( $categories[ $cat ] ); ?>">
									<?php echo esc_html( substr( $categories[ $cat ], 0, 3 ) ); ?>
								</th>
							<?php endforeach; ?>
						</tr>
					</thead>
					<tbody>
						<?php
						foreach ( $trend as $day ) :
							$date = $day['date'] ?? '';
							?>
							<tr>
								<td><?php echo esc_html( $date ); ?></td>
								<?php
								foreach ( array_keys( $categories ) as $cat ) :
									$count = $day[ $cat ] ?? 0;
									?>
									<td><?php echo esc_html( $count ); ?></td>
								<?php endforeach; ?>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			</div>
		</div>

		<?php
	}
}
