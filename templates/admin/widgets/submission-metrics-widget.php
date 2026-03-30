<?php
if (!defined('ABSPATH')) exit;
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

use ContactInbox\Core\Config;

// phpcs:disable WordPress.WP.I18n.NonSingularStringLiteralDomain, WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound

// Ensure variables are set with defaults
if (!isset($today_count)) $today_count = 0;
if (!isset($emails_sent)) $emails_sent = 0;
if (!isset($crm_synced)) $crm_synced = 0;
if (!isset($conversion_rate)) $conversion_rate = 0;
if (!isset($trend_labels)) $trend_labels = [];
if (!isset($trend_values)) $trend_values = [];
if (!isset($analytics_url)) $analytics_url = admin_url('admin.php?page=contactin-analytics');
?>
<div class="contactin-submission-metrics">
    <!-- Metrics Grid -->
    <div class="cin-widget-metrics">
        <div class="cin-metric-card">
            <div class="cin-metric-label"><?php esc_html_e('Today\'s Submissions',  'contactin'); ?></div>
            <div class="cin-metric-value" data-cin-submissions="today-count"><?php echo intval($today_count); ?></div>
        </div>

        <div class="cin-metric-card">
            <div class="cin-metric-label">
                <?php esc_html_e('Emails Sent',  'contactin'); ?>
                <div style="font-size: 11px; color: #999; font-weight: normal; margin-top: 2px;">
                    <?php esc_html_e('Successfully delivered',  'contactin'); ?>
                </div>
            </div>
            <div class="cin-metric-value status-completed" data-cin-submissions="emails-sent"><?php echo intval($emails_sent); ?></div>
        </div>

        <div class="cin-metric-card">
            <div class="cin-metric-label">
                <?php esc_html_e('CRM Operations',  'contactin'); ?>
                <div style="font-size: 11px; color: #999; font-weight: normal; margin-top: 2px;">
                    <?php esc_html_e('Synced + Deleted',  'contactin'); ?>
                </div>
            </div>
            <div class="cin-metric-value status-completed" data-cin-submissions="crm-synced"><?php echo intval($crm_synced); ?></div>
        </div>
    </div>

    <!-- 7-Day Trend -->
    <div class="cin-widget-chart">
        <h4><?php esc_html_e('7-Day Trend',  'contactin'); ?></h4>
        <canvas id="contactin-submission-sparkline" height="80"></canvas>
        <script>
            (function() {
                const data = <?php echo wp_json_encode($trend_values); ?>;
                const ctx = document.getElementById('contactin-submission-sparkline');
                if (ctx && window.Chart) {
                    new Chart(ctx, {
                        type: 'line',
                        data: {
                            labels: <?php echo wp_json_encode($trend_labels); ?>,
                            datasets: [{
                                label: 'Submissions',
                                data: data,
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
                                legend: { display: false }
                            },
                            scales: {
                                y: {
                                    beginAtZero: true,
                                    max: Math.max( ...data, 1 ) + 1
                                }
                            }
                        }
                    });
                }
            })();
        </script>
    </div>

    <!-- Action Button -->
    <div style="text-align: center; border-top: 1px solid #e0e0e0; padding-top: 12px; margin-top: 12px;">
        <a href="<?php echo esc_url($analytics_url); ?>" class="cin-widget-btn primary">
            <?php esc_html_e('View Analytics Dashboard',  'contactin'); ?>
        </a>
</div>
