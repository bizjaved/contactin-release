<?php
use ContactInbox\Core\Config;
$api_requests = intval($api['total_requests'] ?? ($api['time_ms'] ?? 0));
$crm_health_status = strtolower($crm_health['status'] ?? ($crm['status'] ?? 'unknown'));
$crm_status_text = ucfirst($crm_health_status);
$crm_message_raw = $crm_health['message'] ?? '';
$crm_message = preg_replace('/^(critical|warning|healthy|unknown):\s*/i', '', $crm_message_raw);
?>
<div class="contactin-integration-status">
    <div class="integration-list">
        <!-- Salesforce CRM -->
        <div class="integration-item">
            <div class="integration-header">
                <div class="integration-name">
                    <span class="status-badge status-<?php echo esc_attr($crm['status']); ?>" data-cin-integration="crm-status">
                        <?php echo esc_html($crm_status); ?>
                    </span>
                    <?php esc_html_e('Salesforce CRM', 'contact-inbox'); ?>
                </div>
                     <a href="<?php echo esc_url(add_query_arg(['page' => 'contactin-crm'], admin_url('admin.php'))); ?>" 
                   class="integration-config" title="<?php esc_attr_e('Configure CRM', 'contact-inbox'); ?>">
                    ⚙
                </a>
            </div>
            <div class="integration-metrics">
                <div class="metric-row">
                    <span class="metric-label">
                        <?php esc_html_e('Sync Rate', 'contact-inbox'); ?>
                        <span class="help-icon" title="<?php esc_attr_e('Percentage of form submissions successfully sent to Salesforce. Green = healthy, Yellow = investigate, Red = fix immediately', 'contact-inbox'); ?>">?</span>
                    </span>
                    <span class="metric-value" data-cin-integration="crm-rate">
                        <?php echo esc_html($crm['rate']); ?>%
                        <span class="trend-arrow"><?php echo $crm['rate'] >= 90 ? '↑' : ($crm['rate'] >= 75 ? '→' : '↓'); ?></span>
                    </span>
                </div>
                <div class="metric-row">
                    <span class="metric-label"><?php esc_html_e('Status', 'contact-inbox'); ?></span>
                    <span class="metric-value"><strong><?php echo esc_html($crm_status_text ?: __('Unknown', 'contact-inbox')); ?></strong></span>
                </div>
                <div class="metric-row">
                    <span class="metric-label" data-cin-integration="crm-message"><?php echo esc_html($crm_message ?: $crm_message_raw); ?></span>
                    <span class="metric-value"></span>
                </div>
            </div>
        </div>

        <!-- Email Service -->
        <div class="integration-item">
            <div class="integration-header">
                <div class="integration-name">
                    <span class="status-badge status-<?php echo esc_attr($email['status']); ?>" data-cin-integration="email-status">
                        <?php echo esc_html($email_status); ?>
                    </span>
                    <?php esc_html_e('Email Service', 'contact-inbox'); ?>
                </div>
                <a href="<?php echo esc_url(add_query_arg(['page' => 'contactin-settings'], admin_url('admin.php'))); ?>" 
                   class="integration-config" title="<?php esc_attr_e('Configure Email', 'contact-inbox'); ?>">
                    ⚙
                </a>
            </div>
            <div class="integration-metrics">
                <div class="metric-row">
                    <span class="metric-label">
                        <?php esc_html_e('Delivery Rate', 'contact-inbox'); ?>
                        <span class="help-icon" title="<?php esc_attr_e('Percentage of confirmation emails reaching user inboxes. Spam filters or server issues can lower this.', 'contact-inbox'); ?>">?</span>
                    </span>
                    <span class="metric-value" data-cin-integration="email-rate">
                        <?php echo esc_html($email['rate']); ?>%
                        <span class="trend-arrow"><?php echo $email['rate'] >= 95 ? '↑' : ($email['rate'] >= 85 ? '→' : '↓'); ?></span>
                    </span>
                </div>
                <div class="metric-row">
                    <span class="metric-label"><?php esc_html_e('Status', 'contact-inbox'); ?></span>
                    <span class="metric-value" data-cin-integration="email-message"><?php echo esc_html($email['message']); ?></span>
                </div>
            </div>
        </div>

        <!-- REST API -->
        <div class="integration-item">
            <div class="integration-header">
                <div class="integration-name">
                    <span class="status-badge status-<?php echo esc_attr($api['status']); ?>" data-cin-integration="api-status">
                        <?php echo esc_html($api_status); ?>
                    </span>
                    <?php esc_html_e('REST API', 'contact-inbox'); ?>
                </div>
                <a href="<?php echo esc_url(add_query_arg(['page' => 'contactin-restapi-integration'], admin_url('admin.php'))); ?>" 
                   class="integration-config" title="<?php esc_attr_e('Configure API', 'contact-inbox'); ?>">
                    ⚙
                </a>
            </div>
            <div class="integration-metrics">
                <div class="metric-row">
                    <span class="metric-label">
                        <?php esc_html_e('Health', 'contact-inbox'); ?>
                        <span class="help-icon" title="<?php esc_attr_e('Overall API responsiveness. Good = responding quickly to requests.', 'contact-inbox'); ?>">?</span>
                    </span>
                    <span class="metric-value" data-cin-integration="api-health">
                        <?php echo ucfirst(esc_html($api['status'])); ?>
                    </span>
                </div>
                <div class="metric-row">
                    <span class="metric-label">
                        <?php esc_html_e('Requests (24h)', 'contact-inbox'); ?>
                    </span>
                    <span class="metric-value" data-cin-integration="api-requests">
                        <?php echo esc_html(number_format_i18n($api_requests)); ?>
                    </span>
                </div>
            </div>
        </div>
    </div>

    <div class="integration-actions">
        <a href="<?php echo esc_url($analytics_url); ?>" class="cin-widget-btn primary">
            <?php esc_html_e('View Analytics Dashboard', 'contact-inbox'); ?>
        </a>
    </div>
</div>
