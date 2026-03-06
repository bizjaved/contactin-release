<?php
use ContactInbox\Core\Config;

$crm_successful = intval($crm['successful'] ?? 0);
$crm_failed = intval($crm['failed'] ?? 0);
$crm_pending = intval($crm['pending'] ?? max(($crm['total'] ?? 0) - $crm_successful - $crm_failed, 0));
$crm_total = intval($crm['total'] ?? ($crm_successful + $crm_failed + $crm_pending));
$crm_rate = floatval($crm['rate'] ?? 0);
$api_requests = intval($api['total_requests'] ?? ($api['time_ms'] ?? 0));
?>
<div class="contactin-performance-metrics">
    <ul class="performance-list">
                <!-- CRM Sync Rate -->
                <li class="performance-item">
                    <div class="performance-label">
                        <?php esc_html_e('CRM Sync Rate', 'contact-inbox'); ?>
                    </div>
                    <div class="performance-value">
                        <div>
                            <div class="performance-stat" id="summary-crm-rate" data-cin-perf="crm-rate"><?php echo esc_html(number_format_i18n($crm_rate, 1)); ?>%</div>
                            <div class="performance-message" data-cin-perf="crm-message"><?php echo esc_html($crm['message']); ?></div>
                            <div class="crm-metrics-row">
                                <span>
                                    <?php esc_html_e('Successful', 'contact-inbox'); ?>
                                    <strong data-cin-perf="crm-successful"><?php echo esc_html(number_format_i18n($crm_successful)); ?></strong>
                                </span>
                                <span>
                                    <?php esc_html_e('Failed', 'contact-inbox'); ?>
                                    <strong data-cin-perf="crm-failed"><?php echo esc_html(number_format_i18n($crm_failed)); ?></strong>
                                </span>
                                <span>
                                    <?php esc_html_e('Pending', 'contact-inbox'); ?>
                                    <strong data-cin-perf="crm-pending"><?php echo esc_html(number_format_i18n($crm_pending)); ?></strong>
                                </span>
                                <span>
                                    <?php esc_html_e('Total', 'contact-inbox'); ?>
                                    <strong data-cin-perf="crm-total"><?php echo esc_html(number_format_i18n($crm_total)); ?></strong>
                                </span>
                            </div>
                        </div>
                        <div class="status-indicator status-<?php echo esc_attr($crm['status']); ?>" data-cin-perf-status="crm"></div>
                    </div>
                </li>
        <!-- Queue Health -->
        <li class="performance-item">
            <div class="performance-label">
                <?php esc_html_e('Queue Status', 'contact-inbox'); ?>
            </div>
            <div class="performance-value">
                <div>
                    <div class="performance-stat" data-cin-perf="queue-pending"><?php echo intval($queue['pending']); ?></div>
                    <div class="performance-message" data-cin-perf="queue-message"><?php echo esc_html($queue['message']); ?></div>
                </div>
                <div class="status-indicator status-<?php echo esc_attr($queue['status']); ?>" data-cin-perf-status="queue"></div>
            </div>
        </li>

        <!-- Email Delivery Rate -->
        <li class="performance-item">
            <div class="performance-label">
                <?php esc_html_e('Email Delivery', 'contact-inbox'); ?>
            </div>
            <div class="performance-value">
                <div>
                    <div class="performance-stat" data-cin-perf="email-rate"><?php echo floatval($email['rate']); ?>%</div>
                    <div class="performance-message" data-cin-perf="email-message"><?php echo esc_html($email['message']); ?></div>
                </div>
                <div class="status-indicator status-<?php echo esc_attr($email['status']); ?>" data-cin-perf-status="email"></div>
            </div>
        </li>

        <!-- API Response Time -->
        <li class="performance-item">
            <div class="performance-label">
                <?php esc_html_e('API Health', 'contact-inbox'); ?>
            </div>
            <div class="performance-value">
                <div>
                    <div class="performance-stat" data-cin-perf="api-time"><?php echo esc_html(number_format_i18n($api_requests)); ?></div>
                    <div class="performance-message" data-cin-perf="api-message"><?php echo esc_html($api['message']); ?></div>
                </div>
                <div class="status-indicator status-<?php echo esc_attr($api['status']); ?>" data-cin-perf-status="api"></div>
            </div>
        </li>


    </ul>

    <!-- System Status Summary -->
    <div class="system-status <?php echo esc_attr($system_status); ?>" data-cin-perf="system-status">
        <div class="system-status-text">
            <div class="status-indicator status-<?php echo esc_attr($system_status); ?>" data-cin-perf-status="system" style="margin: 0;"></div>
            <span data-cin-perf="system-status-label">
                <?php
                switch ($system_status) {
                    case 'good':
                        esc_html_e('System Healthy', 'contact-inbox');
                        break;
                    case 'warning':
                        esc_html_e('System Warning', 'contact-inbox');
                        break;
                    case 'error':
                        esc_html_e('System Issues', 'contact-inbox');
                        break;
                }
                ?>
            </span>
        </div>
    </div>

    <div style="text-align: center; border-top: 1px solid #e0e0e0; padding-top: 12px; margin-top: 12px;">
        <a href="<?php echo esc_url($analytics_url); ?>" class="cin-widget-btn primary">
            <?php esc_html_e('View Analytics Dashboard', 'contact-inbox'); ?>
        </a>
    </div>
</div>
