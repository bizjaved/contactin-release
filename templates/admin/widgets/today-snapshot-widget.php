<?php
/**
 * Today's Snapshot Widget Template
 * 
 * @package ContactInbox\Admin
 * 
 * Variables passed:
 * @var int $total_submissions
 * @var int $completed
 * @var float $completed_percentage
 * @var int $failed
 * @var float $failed_percentage
 * @var string $system_health
 * @var array $alerts
 */

use ContactInbox\Core\Config;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
?>

<div class="contactin-today-snapshot">
    <!-- Submissions Count -->
    <div class="snapshot-submissions">
        <div class="submission-count" data-cin-snapshot="total-submissions"><?php echo intval( $total_submissions ?? 0 ); ?></div>
        <div class="submission-label"><?php esc_html_e( 'Submissions Today', 'contact-inbox' ); ?></div>
    </div>

    <!-- Submissions Breakdown -->
    <div class="snapshot-breakdown">
        <div class="breakdown-item">
            <span class="breakdown-label">
                <?php esc_html_e( 'Completed', 'contact-inbox' ); ?>
                <span class="help-icon" title="<?php esc_attr_e( 'Submissions successfully processed and sent to all configured integrations', 'contact-inbox' ); ?>">?</span>
            </span>
            <span class="breakdown-value" data-cin-snapshot="completed-count"><?php echo intval( $completed ?? 0 ); ?></span>
        </div>
        <div class="progress-bar">
            <div class="progress-fill completed" data-cin-snapshot="completed-bar" style="width: <?php echo floatval( $completed_percentage ?? 0 ); ?>%"></div>
        </div>

        <div class="breakdown-item">
            <span class="breakdown-label">
                <?php esc_html_e( 'Failed', 'contact-inbox' ); ?>
                <span class="help-icon" title="<?php esc_attr_e( 'Submissions with processing or integration errors', 'contact-inbox' ); ?>">?</span>
            </span>
            <span class="breakdown-value" data-cin-snapshot="failed-count"><?php echo intval( $failed ?? 0 ); ?></span>
        </div>
        <div class="progress-bar">
            <div class="progress-fill failed" data-cin-snapshot="failed-bar" style="width: <?php echo floatval( $failed_percentage ?? 0 ); ?>%"></div>
        </div>
    </div>

    <!-- System Health -->
    <div class="snapshot-health">
        <div class="health-badge <?php echo esc_attr( strtolower( $system_health ?? 'good' ) ); ?>" data-cin-snapshot="system-health">
            <span data-cin-snapshot="system-health-label"><?php 
                switch( strtolower( $system_health ?? 'good' ) ) {
                    case 'good':
                        echo '✓ ';
                        esc_html_e( 'System Healthy', 'contact-inbox' );
                        break;
                    case 'warning':
                        echo '⚠ ';
                        esc_html_e( 'System Warning', 'contact-inbox' );
                        break;
                    case 'error':
                        echo '✗ ';
                        esc_html_e( 'System Error', 'contact-inbox' );
                        break;
                    default:
                        esc_html_e( 'Unknown', 'contact-inbox' );
                }
            ?></span>
        </div>
    </div>

    <!-- Alerts Section (only show if there are alerts) -->
    <?php if ( ! empty( $alerts ) && is_array( $alerts ) ) : ?>
        <div class="snapshot-alerts">
            <div class="alerts-title"><?php esc_html_e( 'Alerts', 'contact-inbox' ); ?></div>
            <ul class="alerts-list">
                <?php foreach ( $alerts as $contactin_alert ) : ?>
                    <li><?php echo esc_html( $contactin_alert ); ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <!-- Action Buttons -->
    <div class="snapshot-actions">
        <a href="<?php echo esc_url( add_query_arg( ['page' => 'contactin-inbox'], admin_url( 'admin.php' ) ) ); ?>" class="cin-widget-btn primary">
            <?php esc_html_e( 'View Inbox', 'contact-inbox' ); ?>
        </a>
        <a href="<?php echo esc_url( add_query_arg( ['page' => 'contactin-analytics'], admin_url( 'admin.php' ) ) ); ?>" class="cin-widget-btn primary">
            <?php esc_html_e( 'View Analytics', 'contact-inbox' ); ?>
        </a>
    </div>
</div>
