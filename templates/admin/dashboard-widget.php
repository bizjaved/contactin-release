<?php
/**
 * Dashboard Widget Template
 * 
 * @package ContactInbox\Admin
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
 * @var string $contactin_inbox_url
 */

use ContactInbox\Core\Config;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! isset( $contactin_inbox_url ) ) {
    $contactin_inbox_url = admin_url( 'admin.php?page=contactin_inbox' );
}
?>

<div class="cin-dashboard-widget">

    <!-- Time-based Stats - Professional Card Grid -->
    <div class="cin-widget-metrics">
        <div class="cin-metric-card">
            <div class="cin-metric-value"><?php echo intval( $today ?? 0 ); ?></div>
            <div class="cin-metric-label"><?php esc_html_e( 'Today', 'contact-inbox' ); ?></div>
        </div>
        <div class="cin-metric-card">
            <div class="cin-metric-value"><?php echo intval( $week ?? 0 ); ?></div>
            <div class="cin-metric-label"><?php esc_html_e( 'Week', 'contact-inbox' ); ?></div>
        </div>
        <div class="cin-metric-card">
            <div class="cin-metric-value"><?php echo intval( $month ?? 0 ); ?></div>
            <div class="cin-metric-label"><?php esc_html_e( 'Month', 'contact-inbox' ); ?></div>
        </div>
        <div class="cin-metric-card">
            <div class="cin-metric-value"><?php echo intval( $year ?? 0 ); ?></div>
            <div class="cin-metric-label"><?php esc_html_e( 'Year', 'contact-inbox' ); ?></div>
        </div>
    </div>

    <!-- Status Breakdown - Card Grid -->
    <div class="cin-widget-status">
        <h4><?php esc_html_e( 'Status Breakdown', 'contact-inbox' ); ?></h4>
        <div class="cin-status-metrics">
            <div class="cin-status-card cin-card-unread">
                <div class="cin-status-value"><?php echo intval( $unread_count ?? 0 ); ?></div>
                <div class="cin-status-label"><?php esc_html_e( 'Unread', 'contact-inbox' ); ?></div>
            </div>
            <div class="cin-status-card cin-card-total">
                <div class="cin-status-value"><?php echo intval( $total_count ?? 0 ); ?></div>
                <div class="cin-status-label"><?php esc_html_e( 'Total', 'contact-inbox' ); ?></div>
            </div>
        </div>
    </div>

    <!-- 7-Day Trend Chart -->
    <div class="cin-widget-chart">
        <h4><?php esc_html_e( 'Last 7 Days', 'contact-inbox' ); ?></h4>
        <canvas id="contactin-trend-chart" height="100"></canvas>
    </div>

    <!-- Action Buttons -->
    <div class="cin-widget-actions">
        <a href="<?php echo esc_url( $contactin_inbox_url ); ?>" class="cin-widget-btn primary">
            <?php esc_html_e( 'View Inbox', 'contact-inbox' ); ?>
        </a>
        <a href="<?php echo esc_url( add_query_arg( ['page' => 'contactin-analytics'], admin_url( 'admin.php' ) ) ); ?>" class="cin-widget-btn primary">
            <?php esc_html_e( 'View Analytics', 'contact-inbox' ); ?>
        </a>
    </div>

</div>

<!-- Chart Script -->
<script>
    document.addEventListener( 'DOMContentLoaded', function() {
        const ctx = document.getElementById( 'contactin-trend-chart' );
        if ( !ctx ) return;

        const labels = <?php echo wp_json_encode( array_keys( $trend_data ?? [] ) ); ?>;
        const values = <?php echo wp_json_encode( array_values( $trend_data ?? [] ) ); ?>;

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
