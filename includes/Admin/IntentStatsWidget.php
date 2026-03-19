<?php
/**
 * Intent Statistics Widget
 *
 * Displays message intent distribution and trends on the dashboard
 *
 * @package ContactInbox\Admin
 */

declare(strict_types=1);

namespace ContactInbox\Admin;

use ContactInbox\Core\Config;
use ContactInbox\Core\IntentClassifier;
use ContactInbox\Core\DB;

if (!defined('ABSPATH')) {
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
        if (empty($settings['intent_enable'])) {
            echo '<p>' . esc_html__('Intent classification is disabled.', 'contact-inbox') . '</p>';
            return;
        }

        // Get stats
        $db = DB::instance();
        $stats = $db->get_intent_stats();
        $trend = $db->get_intent_trend(7);
        $categories = IntentClassifier::get_categories();

        if (empty($stats)) {
            echo '<p>' . esc_html__('No classified messages yet.', 'contact-inbox') . '</p>';
            return;
        }

        $total = array_sum($stats);

        $intent_stats_widget_css = <<<'CSS'
            .contactin-intent-stats-widget {
                padding: 16px;
            }

            .intent-distribution {
                margin: 16px 0;
            }

            .intent-stat-row {
                display: flex;
                align-items: center;
                gap: 12px;
                padding: 8px 0;
                border-bottom: 1px solid #eee;
            }

            .intent-stat-label {
                min-width: 100px;
            }

            .intent-stat-bar {
                flex: 1;
                height: 24px;
                background: #f0f0f0;
                border-radius: 4px;
                overflow: hidden;
            }

            .intent-bar-fill {
                height: 100%;
                min-width: 2px;
                transition: width 0.3s ease;
                opacity: 0.8;
            }

            .intent-bar-fill.intent-primary {
                background: #0073aa;
            }

            .intent-bar-fill.intent-warning {
                background: #f0b849;
            }

            .intent-bar-fill.intent-info {
                background: #00a0d2;
            }

            .intent-bar-fill.intent-danger {
                background: #dc3232;
            }

            .intent-bar-fill.intent-secondary {
                background: #72aee6;
            }

            .intent-bar-fill.intent-dark {
                background: #2c3338;
            }

            .intent-bar-fill.intent-muted {
                background: #999;
            }

            .intent-stat-numbers {
                min-width: 80px;
                text-align: right;
            }

            .intent-count {
                font-weight: 600;
                margin-right: 8px;
            }

            .intent-percentage {
                color: #666;
                font-size: 12px;
            }

            .intent-trend-table {
                margin-top: 12px;
                overflow-x: auto;
            }

            .intent-trend-table table {
                width: 100%;
                border-collapse: collapse;
                font-size: 12px;
            }

            .intent-trend-table th,
            .intent-trend-table td {
                padding: 6px 8px;
                text-align: center;
                border: 1px solid #eee;
            }

            .intent-trend-table th {
                background: #f5f5f5;
                font-weight: 600;
            }

            .intent-trend-table tr:hover {
                background: #fafafa;
            }
CSS;
        wp_add_inline_style('contactin-dashboard-widgets', $intent_stats_widget_css);

        ?>
        <div class="contactin-intent-stats-widget">
            <h3><?php esc_html_e('Message Intent Distribution', 'contact-inbox'); ?></h3>
            
            <div class="intent-distribution">
                <?php foreach ($stats as $category => $count):
                    $label = $categories[$category] ?? ucfirst($category);
                    $color = IntentClassifier::get_category_color($category);
                    $percentage = $total > 0 ? round(($count / $total) * 100, 1) : 0;
                ?>
                    <div class="intent-stat-row">
                        <div class="intent-stat-label">
                            <span class="cin-intent-badge cin-intent-<?php echo esc_attr($color); ?>">
                                <?php echo esc_html($label); ?>
                            </span>
                        </div>
                        <div class="intent-stat-bar">
                            <div class="intent-bar-fill intent-<?php echo esc_attr($color); ?>" 
                                 style="width: <?php echo esc_attr($percentage); ?>%">
                            </div>
                        </div>
                        <div class="intent-stat-numbers">
                            <span class="intent-count"><?php echo esc_html($count); ?></span>
                            <span class="intent-percentage"><?php echo esc_html($percentage); ?>%</span>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <h4 style="margin-top: 20px;"><?php esc_html_e('7-Day Trend', 'contact-inbox'); ?></h4>
            <div class="intent-trend-table">
                <table>
                    <thead>
                        <tr>
                            <th><?php esc_html_e('Date', 'contact-inbox'); ?></th>
                            <?php foreach (array_keys($categories) as $cat): ?>
                                <th title="<?php echo esc_attr($categories[$cat]); ?>">
                                    <?php echo esc_html(substr($categories[$cat], 0, 3)); ?>
                                </th>
                            <?php endforeach; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($trend as $day):
                            $date = $day['date'] ?? '';
                        ?>
                            <tr>
                                <td><?php echo esc_html($date); ?></td>
                                <?php foreach (array_keys($categories) as $cat):
                                    $count = $day[$cat] ?? 0;
                                ?>
                                    <td><?php echo esc_html($count); ?></td>
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
