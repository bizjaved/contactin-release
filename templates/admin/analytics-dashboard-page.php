<?php
/**
 * Analytics Dashboard Page Template
 *
 * Main analytics hub with 5 tabs:
 * - Submissions: Volume, trends, conversion funnel
 * - Performance: Queue health, latency, error rates
 * - Users: Device distribution, geographic, source
 * - CRM: Integration health, sync rates, endpoints
 * - Reports: Custom reports, exports, scheduling
 *
 * @package ContactInbox
 */

use ContactInbox\Core\Config;

if (!defined('ABSPATH')) {
    exit;
}

// Preload queue stats for per-type cards
$queue_stats_by_type = $queue_stats_by_type ?? [];
$queue_trends_by_type = $queue_trends_by_type ?? [];
$queue_health = $queue_health ?? ['pending' => 0];
$is_free = defined('CONTACTINBOX_IS_FREE') && CONTACTINBOX_IS_FREE;
?>

<div class="wrap contactin-dashboard-analytics">

    <?php if ( $is_free ) : ?>
        <style>
            .rate-crm,
            .crm-metric {
                display: none !important;
            }
            
            /* Make CRM summary card appear disabled and clickable */
            .summary-card[data-summary="crm"] {
                opacity: 0.7;
                cursor: pointer;
                position: relative;
            }
            
            .summary-card[data-summary="crm"]:hover {
                opacity: 0.85;
            }
            
            /* Disabled appearance for CRM pane in free version */
            #crm-pane {
                opacity: 0.6;
                pointer-events: none;
                position: relative;
            }
            
            /* Disabled appearance for Background Jobs pane in free version */
            #cron-pane {
                opacity: 0.6;
                pointer-events: none;
                position: relative;
            }
            
            /* Disabled appearance for Performance pane in free version */
            #performance-pane {
                opacity: 0.6;
                pointer-events: none;
                position: relative;
            }
            
            /* Disabled appearance for Users pane in free version */
            #users-pane {
                opacity: 0.6;
                pointer-events: none;
                position: relative;
            }
        </style>
    <?php endif; ?>

    <div class="contactin-analytics-header-wrapper">
        <h1 class="contactin-analytics-title"><?php esc_html_e('Dashboard', 'contact-inbox'); ?></h1>
        <button type="button" class="button button-secondary contactin-help-button"
            data-cin-help-open="cin-analytics-help-modal"
            aria-haspopup="dialog"
            aria-controls="cin-analytics-help-modal">
            <span class="contactin-analytics-button-icon-text">ℹ️</span><?php esc_html_e('Help', 'contact-inbox'); ?>
        </button>
    </div>

    <div class="contactin-analytics-header">
        <div class="analytics-summary">
            <div class="summary-card" data-summary="submissions">
                <div class="summary-label"><?php esc_html_e('Submissions', 'contact-inbox'); ?></div>
                <div class="summary-value" id="summary-submissions-7d" data-summary-value="submissions">
                    <?php echo esc_html(number_format_i18n((int)($submissions_7d ?? 0))); ?>
                </div>
            </div>
            <div class="summary-card" data-health="email" data-summary="email">
                <div class="summary-label"><?php esc_html_e('Email Delivery', 'contact-inbox'); ?></div>
                <div class="summary-value" id="summary-email-rate" data-summary-value="email">
                    <?php echo esc_html(number_format_i18n((float)($email_delivery['rate'] ?? 0), 1)); ?>%
                </div>
            </div>
            <div class="summary-card <?php echo (defined('CONTACTINBOX_IS_FREE') && CONTACTINBOX_IS_FREE) ? 'contactinbox-show-upgrade-modal' : ''; ?>" data-health="crm" data-summary="crm" <?php echo (defined('CONTACTINBOX_IS_FREE') && CONTACTINBOX_IS_FREE) ? 'title="' . esc_attr__('CRM Sync is available in Contact Inbox Pro', 'contact-inbox') . '"' : ''; ?>>
                <div class="summary-label">
                    <?php esc_html_e('CRM Sync Rate', 'contact-inbox'); ?>
                    <?php if ( defined('CONTACTINBOX_IS_FREE') && CONTACTINBOX_IS_FREE ) : ?>
                        <span style="margin-left: 6px; background: #dc3545; color: white; padding: 2px 5px; border-radius: 3px; font-size: 9px; font-weight: bold; vertical-align: middle;">PRO</span>
                    <?php endif; ?>
                </div>
                <div class="summary-value" id="summary-crm-rate" data-summary-value="crm">
                    <?php 
                    if ( defined('CONTACTINBOX_IS_FREE') && CONTACTINBOX_IS_FREE ) {
                        echo '—';
                    } else {
                        echo esc_html(number_format_i18n((float)($crm_rate['rate'] ?? 0), 1)) . '%';
                    }
                    ?>
                </div>
            </div>
            <div class="summary-card" data-health="acceptance" data-summary="acceptance">
                <div class="summary-label"><?php esc_html_e('Acceptance Rate', 'contact-inbox'); ?></div>
                <div class="summary-value" id="summary-acceptance-rate" data-summary-value="acceptance">
                    <?php echo esc_html(number_format_i18n((float)($queue_processing_rate ?? 0), 1)); ?>%
                </div>
            </div>
        </div>

        <div class="analytics-filters">
            <label for="date-range"><?php esc_html_e('Filter:', 'contact-inbox'); ?></label>
            <select id="date-range" class="contactin-date-range">
                <option value="today"><?php esc_html_e('Today', 'contact-inbox'); ?></option>
                <option value="yesterday"><?php esc_html_e('Yesterday', 'contact-inbox'); ?></option>
                <option value="this_week"><?php esc_html_e('This Week (to date)', 'contact-inbox'); ?></option>
                <option value="this_month" selected><?php esc_html_e('This Month (to date)', 'contact-inbox'); ?></option>
                <option value="last_month"><?php esc_html_e('Last Month', 'contact-inbox'); ?></option>
                <option value="last_3_months"><?php esc_html_e('Last 3 Months', 'contact-inbox'); ?></option>
                <option value="last_6_months"><?php esc_html_e('Last 6 Months', 'contact-inbox'); ?></option>
                <option value="custom"><?php esc_html_e('Custom Range', 'contact-inbox'); ?></option>
            </select>
            <div class="custom-date-range" id="custom-date-range">
                <label for="start-date" class="screen-reader-text"><?php esc_html_e('Start Date', 'contact-inbox'); ?></label>
                <input type="date" id="start-date" name="start-date" />
                <label for="end-date" class="screen-reader-text"><?php esc_html_e('End Date', 'contact-inbox'); ?></label>
                <input type="date" id="end-date" name="end-date" />
                <button type="button" class="button" id="apply-date-range"><?php esc_html_e('Apply', 'contact-inbox'); ?></button>
            </div>
        </div>
    </div>

    <!-- Tab Navigation -->
    <nav class="contactin-analytics-tabs">
        <button class="tab-button active" data-tab="submissions" id="tab-submissions">
            <?php esc_html_e('Submissions', 'contact-inbox'); ?>
        </button>
        <button class="tab-button" data-tab="performance" id="tab-performance">
            <?php esc_html_e('System Performance', 'contact-inbox'); ?>
            <?php if ( defined('CONTACTINBOX_IS_FREE') && CONTACTINBOX_IS_FREE ) : ?>
                <span style="margin-left: 6px; background: #dc3545; color: white; padding: 2px 6px; border-radius: 3px; font-size: 10px; font-weight: bold; vertical-align: middle;">PRO</span>
            <?php endif; ?>
        </button>
        <button class="tab-button" data-tab="users" id="tab-users">
            <?php esc_html_e('Users', 'contact-inbox'); ?>
            <?php if ( defined('CONTACTINBOX_IS_FREE') && CONTACTINBOX_IS_FREE ) : ?>
                <span style="margin-left: 6px; background: #dc3545; color: white; padding: 2px 6px; border-radius: 3px; font-size: 10px; font-weight: bold; vertical-align: middle;">PRO</span>
            <?php endif; ?>
        </button>
        <button class="tab-button" data-tab="crm" id="tab-crm">
            <?php esc_html_e('Salesforce CRM', 'contact-inbox'); ?>
            <?php if ( defined('CONTACTINBOX_IS_FREE') && CONTACTINBOX_IS_FREE ) : ?>
                <span style="margin-left: 6px; background: #dc3545; color: white; padding: 2px 6px; border-radius: 3px; font-size: 10px; font-weight: bold; vertical-align: middle;">PRO</span>
            <?php endif; ?>
        </button>
        <button class="tab-button" data-tab="cron" id="tab-cron">
            <?php esc_html_e('Background Jobs', 'contact-inbox'); ?>
            <?php if ( defined('CONTACTINBOX_IS_FREE') && CONTACTINBOX_IS_FREE ) : ?>
                <span style="margin-left: 6px; background: #dc3545; color: white; padding: 2px 6px; border-radius: 3px; font-size: 10px; font-weight: bold; vertical-align: middle;">PRO</span>
            <?php endif; ?>
        </button>
    </nav>

    <!-- Tab Content -->
    <div class="contactin-analytics-content">

        <!-- SUBMISSIONS TAB -->
        <div class="tab-pane active" id="submissions-pane" data-tab="submissions">
            <div class="analytics-section">
                <h2><?php esc_html_e('Submissions Analytics', 'contact-inbox'); ?></h2>

                <div class="analytics-grid">
                    <!-- Submission Trend Chart -->
                    <div class="analytics-card">
                        <h3><?php esc_html_e('Submission Volume Trend', 'contact-inbox'); ?></h3>
                        <div class="card-date-label submissions-date-label"></div>
                        <div class="chart-container">
                            <canvas id="chart-submissions-trend"></canvas>
                        </div>
                    </div>

                    <!-- Spam Blocked -->
                    <div class="analytics-card">
                        <h3><?php esc_html_e('Spam Blocked', 'contact-inbox'); ?></h3>
                        <div class="card-date-label submissions-date-label"></div>
                        <p class="card-description"><?php esc_html_e('Total spam blocked by security checks and classifier detection.', 'contact-inbox'); ?></p>
                        <div class="conversion-metric">
                            <div class="metric-value metric-spam" id="metric-spam-blocked">...</div>
                            <div class="metric-label"><?php esc_html_e('Spam Attempts', 'contact-inbox'); ?></div>
                        </div>
                    </div>

                    <!-- Rejection Reasons -->
                    <div class="analytics-card">
                        <h3><?php esc_html_e('Rejection Reasons', 'contact-inbox'); ?></h3>
                        <div class="card-date-label submissions-date-label"></div>
                        <p class="card-description"><?php esc_html_e('Breakdown of why form submissions were rejected during the selected period.', 'contact-inbox'); ?></p>
                        <div class="status-breakdown">
                            <div class="status-item rejection-recaptcha">
                                <span class="status-icon">🤖</span>
                                <div class="status-info">
                                    <span class="status-label"><?php esc_html_e('reCAPTCHA Failed', 'contact-inbox'); ?></span>
                                    <span class="status-value" id="rejection-recaptcha">...</span>
                                </div>
                            </div>
                            <div class="status-item rejection-validation">
                                <span class="status-icon">❌</span>
                                <div class="status-info">
                                    <span class="status-label"><?php esc_html_e('Validation Failed', 'contact-inbox'); ?></span>
                                    <span class="status-value" id="rejection-validation">...</span>
                                </div>
                            </div>
                            <div class="status-item rejection-ratelimit">
                                <span class="status-icon">⏱️</span>
                                <div class="status-info">
                                    <span class="status-label"><?php esc_html_e('Rate Limited', 'contact-inbox'); ?></span>
                                    <span class="status-value" id="rejection-ratelimit">...</span>
                                </div>
                            </div>
                            <div class="status-item rejection-nonce">
                                <span class="status-icon">🔒</span>
                                <div class="status-info">
                                    <span class="status-label"><?php esc_html_e('Nonce Failed', 'contact-inbox'); ?></span>
                                    <span class="status-value" id="rejection-nonce">...</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Status Breakdown -->
                    <div class="analytics-card">
                        <h3><?php esc_html_e('Inbox Review Status', 'contact-inbox'); ?></h3>
                        <div class="card-date-label submissions-date-label"></div>
                        <p class="card-description"><?php esc_html_e('Breakdown of messages by read status and archive state.', 'contact-inbox'); ?></p>
                        <div class="status-breakdown">
                            <div class="status-item status-read">
                                <span class="status-icon">✓</span>
                                <div class="status-info">
                                    <span class="status-label"><?php esc_html_e('Read', 'contact-inbox'); ?></span>
                                    <span class="status-value" id="status-completed">...</span>
                                </div>
                            </div>
                            <div class="status-item status-unread">
                                <span class="status-icon">●</span>
                                <div class="status-info">
                                    <span class="status-label"><?php esc_html_e('Unread', 'contact-inbox'); ?></span>
                                    <span class="status-value" id="status-pending">...</span>
                                </div>
                            </div>
                            <div class="status-item status-archived">
                                <span class="status-icon">📁</span>
                                <div class="status-info">
                                    <span class="status-label"><?php esc_html_e('Archived', 'contact-inbox'); ?></span>
                                    <span class="status-value" id="status-failed">...</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Conversion Rate -->
                    <div class="analytics-card">
                        <h3><?php esc_html_e('Message Processing Rates', 'contact-inbox'); ?></h3>
                        <div class="card-date-label submissions-date-label"></div>
                        <p class="card-description"><?php esc_html_e('Success rate breakdown by processing type over the selected period.', 'contact-inbox'); ?></p>
                        <div class="status-breakdown">
                            <div class="status-item rate-email">
                                <span class="status-icon">📧</span>
                                <div class="status-info">
                                    <span class="status-label"><?php esc_html_e('Email', 'contact-inbox'); ?></span>
                                    <span class="status-value" id="rate-email">...</span>
                                </div>
                            </div>
                            <div class="status-item rate-crm">
                                <span class="status-icon">🔗</span>
                                <div class="status-info">
                                    <span class="status-label"><?php esc_html_e('CRM', 'contact-inbox'); ?></span>
                                    <span class="status-value" id="rate-crm">...</span>
                                </div>
                            </div>
                            <!-- Removed: Webhook status item -->
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- PERFORMANCE TAB -->
        <div class="tab-pane" id="performance-pane" data-tab="performance">
            <div class="analytics-section">
                <h2><?php esc_html_e('System Performance & Health', 'contact-inbox'); ?></h2>

                <div class="performance-kpi-grid">
                    <!-- Email Delivery Health -->
                    <div class="analytics-card performance-kpi" data-health="email">
                        <div class="card-heading">
                            <h3><?php esc_html_e('Email Delivery', 'contact-inbox'); ?></h3>
                            <span class="date-range-label" id="performance-email-date-label"></span>
                        </div>
                        <p class="card-description"><?php esc_html_e('Delivery success rate from email logs for the selected window.', 'contact-inbox'); ?></p>
                        <div class="kpi-primary">
                            <div class="kpi-value" id="email-delivery-rate">
                                <span class="percentage-value">—</span>
                            </div>
                            <div class="kpi-metrics">
                                <div class="stat-mini">
                                    <span class="stat-label"><?php esc_html_e('Sent', 'contact-inbox'); ?></span>
                                    <span class="stat-value" id="email-sent">0</span>
                                </div>
                                <div class="stat-mini">
                                    <span class="stat-label"><?php esc_html_e('Failed', 'contact-inbox'); ?></span>
                                    <span class="stat-value" id="email-failed">0</span>
                                </div>
                                <div class="stat-mini">
                                    <span class="stat-label"><?php esc_html_e('Total', 'contact-inbox'); ?></span>
                                    <span class="stat-value" id="email-total">0</span>
                                </div>
                            </div>
                        </div>
                        <div class="failure-reasons compact">
                            <p class="failure-header"><?php esc_html_e('Top Failures', 'contact-inbox'); ?></p>
                            <ul class="failure-list" id="email-failures">
                                <li><?php esc_html_e('Loading...', 'contact-inbox'); ?></li>
                            </ul>
                        </div>
                    </div>

                    <!-- Spam Intelligence -->
                    <div class="analytics-card performance-kpi">
                        <div class="card-heading">
                            <h3><?php esc_html_e('Spam Intelligence', 'contact-inbox'); ?></h3>
                            <span class="date-range-label" id="performance-spam-date-label"></span>
                        </div>
                        <p class="card-description"><?php esc_html_e('reCAPTCHA scoring and suspicious activity spotted in the same period.', 'contact-inbox'); ?></p>
                        <div class="kpi-primary">
                            <div class="kpi-value" id="spam-avg-score">—</div>
                            <div class="kpi-metrics">
                                <div class="stat-mini">
                                    <span class="stat-label"><?php esc_html_e('Total', 'contact-inbox'); ?></span>
                                    <span class="stat-value" id="spam-total">0</span>
                                </div>
                                <div class="stat-mini">
                                    <span class="stat-label"><?php esc_html_e('Flagged', 'contact-inbox'); ?></span>
                                    <span class="stat-value" id="spam-flagged">0</span>
                                </div>
                                <div class="stat-mini">
                                    <span class="stat-label"><?php esc_html_e('Spam %', 'contact-inbox'); ?></span>
                                    <span class="stat-value" id="spam-percentage">—%</span>
                                </div>
                            </div>
                        </div>
                        <div class="score-info">
                            <p class="score-info-text">
                                <span class="score-badge good">🟢</span> <?php esc_html_e('Good: ≥0.75', 'contact-inbox'); ?> |
                                <span class="score-badge medium">🟡</span> <?php esc_html_e('Medium: 0.5-0.75', 'contact-inbox'); ?> |
                                <span class="score-badge bad">🔴</span> <?php esc_html_e('Suspicious: <0.5', 'contact-inbox'); ?>
                            </p>
                        </div>
                    </div>
                </div>

                <div class="performance-system-grid">
                    <!-- API Health -->
                    <div class="analytics-card performance-system" data-health="api">
                        <div class="card-heading">
                            <h3><?php esc_html_e('API Availability', 'contact-inbox'); ?></h3>
                            <span class="date-range-label" id="performance-api-date-label"></span>
                        </div>
                        <p class="card-description"><?php esc_html_e('REST API performance and error rates for the chosen filter.', 'contact-inbox'); ?></p>
                        <div class="health-indicator" id="health-api">
                            <div class="status-dot pending"></div>
                            <span class="status-text"><?php esc_html_e('Loading...', 'contact-inbox'); ?></span>
                        </div>
                        <div class="health-stats-inline">
                            <div class="stat-mini">
                                <span class="stat-label"><?php esc_html_e('Requests', 'contact-inbox'); ?></span>
                                <span class="stat-value" id="api-requests">0</span>
                            </div>
                            <div class="stat-mini">
                                <span class="stat-label"><?php esc_html_e('Errors', 'contact-inbox'); ?></span>
                                <span class="stat-value" id="api-errors">0</span>
                            </div>
                        </div>
                    </div>

                    <!-- Processing Queue Health -->
                    <div class="analytics-card performance-system" data-health="queue">
                        <div class="card-heading">
                            <h3><?php esc_html_e('Processing Queue', 'contact-inbox'); ?></h3>
                            <span class="current-state-label"><?php esc_html_e('Live snapshot', 'contact-inbox'); ?></span>
                        </div>
                        <p class="card-description"><?php esc_html_e('Real-time queue state (not affected by date filters).', 'contact-inbox'); ?></p>
                        <div class="health-indicator" id="health-queue">
                            <div class="status-dot pending"></div>
                            <span class="status-text"><?php esc_html_e('Loading...', 'contact-inbox'); ?></span>
                        </div>
                        <div class="health-stats-inline queue-stat-text">
                            <p class="queue-stat-line queue-stat-line-failed">
                                <span class="queue-stat-number" id="queue-failed">0</span>
                                <?php esc_html_e('messages have failed processing', 'contact-inbox'); ?>
                            </p>
                            <p class="queue-stat-line queue-stat-line-pending">
                                <span class="queue-stat-number" id="queue-pending">0</span>
                                <?php esc_html_e('messages are pending', 'contact-inbox'); ?>
                            </p>
                        </div>
                    </div>

                    <!-- Overall System Health -->
                    <div class="analytics-card performance-system" data-health="system">
                        <div class="card-heading">
                            <h3><?php esc_html_e('System Alerts', 'contact-inbox'); ?></h3>
                            <span class="date-range-label" id="performance-system-date-label"></span>
                        </div>
                        <p class="card-description"><?php esc_html_e('Consolidated health checks across queue, email, API, and CRM.', 'contact-inbox'); ?></p>
                        <div class="health-indicator" id="system-status">
                            <div class="status-dot pending"></div>
                            <span class="status-text"><?php esc_html_e('Analyzing...', 'contact-inbox'); ?></span>
                        </div>
                        <div class="system-status-details" id="system-status-details">
                            <ul class="status-issues-list"></ul>
                        </div>
                    </div>
                </div>

                <?php
                $queue_stats_by_type = $queue_stats_by_type ?? [];

                // Combine admin_email and user_email into 'email' totals
                $email_stats = [
                    'pending' => 0,
                    'processing' => 0,
                    'retry' => 0,
                    'dlq' => 0,
                    'sent' => 0,
                ];

                if (isset($queue_stats_by_type['admin_email'])) {
                    $email_stats['pending'] += $queue_stats_by_type['admin_email']['pending'] ?? 0;
                    $email_stats['processing'] += $queue_stats_by_type['admin_email']['processing'] ?? 0;
                    $email_stats['retry'] += $queue_stats_by_type['admin_email']['retry'] ?? 0;
                    $email_stats['dlq'] += $queue_stats_by_type['admin_email']['dlq'] ?? 0;
                    $email_stats['sent'] += $queue_stats_by_type['admin_email']['sent'] ?? 0;
                }

                if (isset($queue_stats_by_type['user_email'])) {
                    $email_stats['pending'] += $queue_stats_by_type['user_email']['pending'] ?? 0;
                    $email_stats['processing'] += $queue_stats_by_type['user_email']['processing'] ?? 0;
                    $email_stats['retry'] += $queue_stats_by_type['user_email']['retry'] ?? 0;
                    $email_stats['dlq'] += $queue_stats_by_type['user_email']['dlq'] ?? 0;
                    $email_stats['sent'] += $queue_stats_by_type['user_email']['sent'] ?? 0;
                }

                $queues = [
                    'email' => [
                        'label'   => __('Email Processing', 'contact-inbox'),
                        'counts'  => $email_stats,
                    ],
                    'crm' => [
                        'label'   => __('CRM Processing', 'contact-inbox'),
                        'counts'  => $queue_stats_by_type['crm'] ?? ['pending' => 0, 'processing' => 0, 'retry' => 0, 'dlq' => 0, 'sent' => 0],
                    ],
                    // Removed: webhook queue definition
                ];

                foreach ($queues as $key => $queue_data) {
                    $counts = $queue_data['counts'];
                    $has_dlq = ($counts['dlq'] ?? 0) > 0;
                    $has_retry = ($counts['retry'] ?? 0) > 0;
                    $high_pending = ($counts['pending'] ?? 0) > 5;

                    $state = 'good';
                    $state_label = __('Stable', 'contact-inbox');
                    if ($has_dlq || $has_retry) {
                        $state = 'critical';
                        $state_label = __('Attention', 'contact-inbox');
                    } elseif ($high_pending) {
                        $state = 'warning';
                        $state_label = __('Busy', 'contact-inbox');
                    }

                    $queues[$key]['state'] = $state;
                    $queues[$key]['state_label'] = $state_label;
                }

                $dlq_total = intval(($queues['email']['counts']['dlq'] ?? 0) + ($queues['crm']['counts']['dlq'] ?? 0));
                $dlq_state = $dlq_total > 0 ? 'critical' : 'good';
                $dlq_label = $dlq_total > 0 ? __('Needs review', 'contact-inbox') : __('Clear', 'contact-inbox');
                ?>

                <div class="queue-status-header">
                    <h3><?php esc_html_e('Message Processing Status', 'contact-inbox'); ?> <span class="date-range-label" id="queue-status-date-label"></span></h3>
                    <p class="queue-description"><?php esc_html_e('Email and CRM throughput for the selected period.', 'contact-inbox'); ?></p>
                </div>

                <div class="queue-status-grid">
                    <?php foreach ($queues as $key => $queue_data) : ?>
                        <div class="analytics-card queue-card queue-<?php echo esc_attr($key); ?>">
                            <div class="queue-card-head">
                                <div class="queue-card-title">
                                    <span class="queue-name"><?php echo esc_html($queue_data['label']); ?></span>
                                    <span class="queue-chip state-<?php echo esc_attr($queue_data['state']); ?>"><?php echo esc_html($queue_data['state_label']); ?></span>
                                </div>
                                <div class="queue-total">
                                    <span class="queue-total-label"><?php esc_html_e('Pending', 'contact-inbox'); ?></span>
                                    <span class="queue-total-value"><?php echo intval($queue_data['counts']['pending']); ?></span>
                                </div>
                            </div>
                            <div class="queue-counts">
                                <div class="queue-stat">
                                    <span class="stat-label"><?php esc_html_e('Sent', 'contact-inbox'); ?></span>
                                    <span class="stat-value"><?php echo intval($queue_data['counts']['sent'] ?? 0); ?></span>
                                </div>
                                <div class="queue-stat">
                                    <span class="stat-label"><?php esc_html_e('Failed', 'contact-inbox'); ?></span>
                                    <span class="stat-value"><?php echo intval($queue_data['counts']['dlq']); ?></span>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>

                    <div class="analytics-card queue-card queue-dlq">
                        <div class="queue-card-head">
                            <div class="queue-card-title">
                                <span class="queue-name"><?php esc_html_e('Failed Messages (All Types)', 'contact-inbox'); ?></span>
                                <span class="queue-chip state-<?php echo esc_attr($dlq_state); ?>"><?php echo esc_html($dlq_label); ?></span>
                            </div>
                            <div class="queue-total">
                                <span class="queue-total-label"><?php esc_html_e('Total', 'contact-inbox'); ?></span>
                                <span class="queue-total-value"><?php echo $dlq_total; ?></span>
                            </div>
                        </div>
                        <p class="queue-help-text"><?php esc_html_e('Messages that failed email or CRM processing.', 'contact-inbox'); ?></p>
                    </div>
                </div>
            </div>
        </div>

        <!-- SALESFORCE CRM TAB -->
        <div class="tab-pane" id="crm-pane" data-tab="crm">
            <div class="analytics-section">
                <h2><?php esc_html_e('Salesforce CRM Dashboard', 'contact-inbox'); ?></h2>

                <div class="analytics-grid">
                    <!-- CRM Statistics -->
                    <div class="analytics-card">
                        <h3><?php esc_html_e('Sync Statistics', 'contact-inbox'); ?> <span class="date-range-label" id="crm-stats-date-label"></span></h3>
                        <p class="card-description"><?php esc_html_e('CRM sync attempts for the selected period', 'contact-inbox'); ?></p>
                        <div class="crm-stats">
                            <div class="stat-item">
                                <span class="stat-label"><?php esc_html_e('Total Synced', 'contact-inbox'); ?></span>
                                <span class="stat-value" id="crm-total-synced">0</span>
                            </div>
                            <div class="stat-item">
                                <span class="stat-label"><?php esc_html_e('Successful', 'contact-inbox'); ?></span>
                                <span class="stat-value" id="crm-success-count">0</span>
                            </div>
                            <div class="stat-item">
                                <span class="stat-label"><?php esc_html_e('Failed', 'contact-inbox'); ?></span>
                                <span class="stat-value" id="crm-failed-count">0</span>
                            </div>
                            <div class="stat-item">
                                <span class="stat-label"><?php esc_html_e('Pending', 'contact-inbox'); ?></span>
                                <span class="stat-value" id="crm-pending-count">0</span>
                            </div>
                            <div class="stat-item">
                                <span class="stat-label"><?php esc_html_e('Success Rate', 'contact-inbox'); ?></span>
                                <span class="stat-value" id="crm-success-rate">0%</span>
                            </div>
                        </div>
                    </div>

                    <!-- CRM Health Status -->
                    <div class="analytics-card">
                        <h3><?php esc_html_e('Integration Health', 'contact-inbox'); ?> <span class="current-state-label"><?php esc_html_e('(Current)', 'contact-inbox'); ?></span></h3>
                        <div class="health-indicator" id="crm-health">
                            <div class="status-dot pending"></div>
                            <span class="status-text"><?php esc_html_e('Loading...', 'contact-inbox'); ?></span>
                        </div>
                        <div class="health-details" id="crm-health-details">
                            <div class="health-item">
                                <span class="health-label"><?php esc_html_e('Authorization', 'contact-inbox'); ?></span>
                                <span class="health-status" id="crm-auth-status">—</span>
                            </div>
                        </div>
                    </div>

                    <!-- Sync Trend Chart -->
                    <div class="analytics-card">
                        <h3><?php esc_html_e('Daily Sync Activity', 'contact-inbox'); ?> <span class="date-range-label" id="crm-chart-date-label"></span></h3>
                        <div class="chart-container">
                            <canvas id="chart-crm-daily-stats"></canvas>
                        </div>
                    </div>

                    <!-- Active Endpoints -->
                    <div class="analytics-card">
                        <h3><?php esc_html_e('Active Endpoints', 'contact-inbox'); ?> <span class="date-range-label" id="crm-endpoints-date-label"></span></h3>
                        <div class="endpoint-list" id="crm-endpoints">
                            <p class="placeholder-text"><?php esc_html_e('Loading endpoint data...', 'contact-inbox'); ?></p>
                        </div>
                    </div>

                    <!-- Recent Activity -->
                    <div class="analytics-card full-width">
                        <h3><?php esc_html_e('Recent Sync Activity', 'contact-inbox'); ?></h3>
                        <div class="activity-log" id="crm-activity-log">
                            <p class="placeholder-text"><?php esc_html_e('Loading activity...', 'contact-inbox'); ?></p>
                        </div>
                    </div>
                </div>

            </div>
        </div>

        <!-- CRON TAB -->
        <div class="tab-pane" id="cron-pane" data-tab="cron">
            <div class="analytics-section">
                <h2><?php esc_html_e('Background Jobs & Cron Health', 'contact-inbox'); ?></h2>

                <div class="cron-summary-grid">
                    <div class="analytics-card">
                        <h4><?php esc_html_e('Overall Health', 'contact-inbox'); ?></h4>
                        <div class="cron-health-score" id="cron-health-overall">—</div>
                        <div class="cron-stat-pair">
                            <span><?php esc_html_e('Executions (24h)', 'contact-inbox'); ?></span>
                            <span id="cron-executions-24h">0</span>
                        </div>
                        <div class="cron-stat-pair">
                            <span><?php esc_html_e('Failed (24h)', 'contact-inbox'); ?></span>
                            <span id="cron-failed-24h">0</span>
                        </div>
                        <div class="cron-stat-pair">
                            <span><?php esc_html_e('Avg Duration (24h)', 'contact-inbox'); ?></span>
                            <span id="cron-avg-duration">—</span>
                        </div>
                    </div>

                    <div class="analytics-card">
                        <h4><?php esc_html_e('Email Queue Processor (24h)', 'contact-inbox'); ?></h4>
                        <div class="cron-health-row" id="cron-contactinbox_process_email_queue-status">—</div>
                        <div class="cron-meta-grid" id="cron-contactinbox_process_email_queue-meta">
                            <span class="meta-label"><?php esc_html_e('Last Run', 'contact-inbox'); ?></span>
                            <span class="meta-value" data-field="last_run">—</span>
                            <span class="meta-label"><?php esc_html_e('Next Run', 'contact-inbox'); ?></span>
                            <span class="meta-value" data-field="next_run">—</span>
                            <span class="meta-label"><?php esc_html_e('Failures', 'contact-inbox'); ?></span>
                            <span class="meta-value" data-field="failure_count">0</span>
                            <span class="meta-label"><?php esc_html_e('Duration', 'contact-inbox'); ?></span>
                            <span class="meta-value" data-field="last_duration_ms">—</span>
                        </div>
                    </div>

                    <div class="analytics-card">
                        <h4><?php esc_html_e('CRM Queue Processor (24h)', 'contact-inbox'); ?></h4>
                        <div class="cron-health-row" id="cron-contactinbox_process_crm_queue-status">—</div>
                        <div class="cron-meta-grid" id="cron-contactinbox_process_crm_queue-meta">
                            <span class="meta-label"><?php esc_html_e('Last Run', 'contact-inbox'); ?></span>
                            <span class="meta-value" data-field="last_run">—</span>
                            <span class="meta-label"><?php esc_html_e('Next Run', 'contact-inbox'); ?></span>
                            <span class="meta-value" data-field="next_run">—</span>
                            <span class="meta-label"><?php esc_html_e('Failures', 'contact-inbox'); ?></span>
                            <span class="meta-value" data-field="failure_count">0</span>
                            <span class="meta-label"><?php esc_html_e('Duration', 'contact-inbox'); ?></span>
                            <span class="meta-value" data-field="last_duration_ms">—</span>
                        </div>
                    </div>

                    <div class="analytics-card">
                        <h4><?php esc_html_e('Cleanup (Maintenance, 24h)', 'contact-inbox'); ?></h4>
                        <div class="cron-health-row" id="cron-contactinbox_cleanup_cron-status">—</div>
                        <div class="cron-meta-grid" id="cron-contactinbox_cleanup_cron-meta">
                            <span class="meta-label"><?php esc_html_e('Last Run', 'contact-inbox'); ?></span>
                            <span class="meta-value" data-field="last_run">—</span>
                            <span class="meta-label"><?php esc_html_e('Next Run', 'contact-inbox'); ?></span>
                            <span class="meta-value" data-field="next_run">—</span>
                            <span class="meta-label"><?php esc_html_e('Failures', 'contact-inbox'); ?></span>
                            <span class="meta-value" data-field="failure_count">0</span>
                            <span class="meta-label"><?php esc_html_e('Duration', 'contact-inbox'); ?></span>
                            <span class="meta-value" data-field="last_duration_ms">—</span>
                        </div>
                    </div>

                    <div class="analytics-card">
                        <h4><?php esc_html_e('GDPR Expiry (24h)', 'contact-inbox'); ?></h4>
                        <div class="cron-health-row" id="cron-contactinbox_gdpr_expiry_check-status">—</div>
                        <div class="cron-meta-grid" id="cron-contactinbox_gdpr_expiry_check-meta">
                            <span class="meta-label"><?php esc_html_e('Last Run', 'contact-inbox'); ?></span>
                            <span class="meta-value" data-field="last_run">—</span>
                            <span class="meta-label"><?php esc_html_e('Next Run', 'contact-inbox'); ?></span>
                            <span class="meta-value" data-field="next_run">—</span>
                            <span class="meta-label"><?php esc_html_e('Failures', 'contact-inbox'); ?></span>
                            <span class="meta-value" data-field="failure_count">0</span>
                            <span class="meta-label"><?php esc_html_e('Duration', 'contact-inbox'); ?></span>
                            <span class="meta-value" data-field="last_duration_ms">—</span>
                        </div>
                    </div>

                    <div class="analytics-card">
                        <h4><?php esc_html_e('Analytics Aggregation (24h)', 'contact-inbox'); ?></h4>
                        <div class="cron-health-row" id="cron-contactin_daily_analytics_aggregation-status">—</div>
                        <div class="cron-meta-grid" id="cron-contactin_daily_analytics_aggregation-meta">
                            <span class="meta-label"><?php esc_html_e('Last Run', 'contact-inbox'); ?></span>
                            <span class="meta-value" data-field="last_run">—</span>
                            <span class="meta-label"><?php esc_html_e('Next Run', 'contact-inbox'); ?></span>
                            <span class="meta-value" data-field="next_run">—</span>
                            <span class="meta-label"><?php esc_html_e('Failures', 'contact-inbox'); ?></span>
                            <span class="meta-value" data-field="failure_count">0</span>
                            <span class="meta-label"><?php esc_html_e('Duration', 'contact-inbox'); ?></span>
                            <span class="meta-value" data-field="last_duration_ms">—</span>
                        </div>
                    </div>
                </div>

                <div class="analytics-card">
                    <h4><?php esc_html_e('Recent Cron Events (24h)', 'contact-inbox'); ?></h4>
                    <table class="cron-table" id="cron-events-table">
                        <thead>
                            <tr>
                                <th><?php esc_html_e('Job', 'contact-inbox'); ?></th>
                                <th><?php esc_html_e('Status', 'contact-inbox'); ?></th>
                                <th><?php esc_html_e('Last Run', 'contact-inbox'); ?></th>
                                <th><?php esc_html_e('Duration', 'contact-inbox'); ?></th>
                                <th><?php esc_html_e('Next Run', 'contact-inbox'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr><td colspan="5"><?php esc_html_e('No records available for the past 24 hours.', 'contact-inbox'); ?></td></tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- USERS TAB -->
        <div class="tab-pane" id="users-pane" data-tab="users">
            <div class="analytics-section">
                <h2><?php esc_html_e('User Analytics', 'contact-inbox'); ?></h2>

                <div class="analytics-grid">
                    <!-- Device Distribution -->
                    <div class="analytics-card">
                        <h3><?php esc_html_e('Device Distribution', 'contact-inbox'); ?></h3>
                        <div class="chart-container">
                            <canvas id="chart-device-distribution" data-chart-type="pie"></canvas>
                        </div>
                        <div class="chart-legend" id="legend-device"></div>
                    </div>

                    <!-- Geographic Distribution -->
                    <div class="analytics-card">
                        <h3><?php esc_html_e('Geographic Distribution (Top 10)', 'contact-inbox'); ?></h3>
                        <div class="chart-container">
                            <canvas id="chart-geographic-distribution" data-chart-type="bar"></canvas>
                        </div>
                        <div class="chart-legend" id="legend-geographic"></div>
                    </div>

                    <!-- Traffic Sources -->
                    <div class="analytics-card">
                        <h3><?php esc_html_e('Traffic Sources', 'contact-inbox'); ?></h3>
                        <div class="chart-container">
                            <canvas id="chart-traffic-sources" data-chart-type="doughnut"></canvas>
                        </div>
                        <div class="chart-legend" id="legend-sources"></div>
                    </div>

                    <!-- Browser Distribution -->
                    <div class="analytics-card">
                        <h3><?php esc_html_e('Browser Distribution', 'contact-inbox'); ?></h3>
                        <div class="chart-container">
                            <canvas id="chart-browser-distribution" data-chart-type="bar"></canvas>
                        </div>
                        <div class="chart-legend" id="legend-browser"></div>
                    </div>
                </div>
            </div>
        </div>

    </div>
</div>

<div id="cin-analytics-help-modal" class="cin-modal cin-modal-hidden" data-cin-help-modal="true">
    <div class="cin-modal-overlay"></div>
    <div class="cin-modal-content" role="dialog" aria-modal="true">
        <div class="cin-modal-header">
            <h2><?php esc_html_e('Dashboard', 'contact-inbox'); ?></h2>
            <button type="button" class="cin-modal-close" aria-label="<?php esc_attr_e('Close', 'contact-inbox'); ?>">
                <span aria-hidden="true">&times;</span>
            </button>
        </div>
        <div class="cin-modal-body">
            <!-- Quick Navigation -->
            <div class="cin-help-nav">
                <h3><?php esc_html_e('Quick Navigation', 'contact-inbox'); ?></h3>
                <ul>
                    <li><a href="#analytics-help-overview" class="cin-help-link"><?php esc_html_e('Overview', 'contact-inbox'); ?></a></li>
                    <li><a href="#analytics-help-submissions" class="cin-help-link"><?php esc_html_e('Submissions', 'contact-inbox'); ?></a></li>
                    <li><a href="#analytics-help-performance" class="cin-help-link"><?php esc_html_e('Performance', 'contact-inbox'); ?></a></li>
                    <li><a href="#analytics-help-crm" class="cin-help-link"><?php esc_html_e('CRM Integration', 'contact-inbox'); ?></a></li>
                    <li><a href="#analytics-help-users" class="cin-help-link"><?php esc_html_e('User Analytics', 'contact-inbox'); ?></a></li>
                    <li><a href="#analytics-help-cron" class="cin-help-link"><?php esc_html_e('Background Jobs', 'contact-inbox'); ?></a></li>
                    <li><a href="#analytics-help-troubleshoot" class="cin-help-link"><?php esc_html_e('Troubleshooting', 'contact-inbox'); ?></a></li>
                </ul>
            </div>

            <!-- Overview -->
            <div id="analytics-help-overview" class="cin-help-section">
                <h3><?php esc_html_e('📊 Overview', 'contact-inbox'); ?></h3>
                <div class="cin-help-item">
                    <p><?php esc_html_e('Welcome to the Analytics Dashboard! This comprehensive guide explains each section, metric, and how to interpret the business intelligence (BI) data to get the most value from your contact form analytics.', 'contact-inbox'); ?></p>
                    <ul>
                        <li><?php esc_html_e('Track submission trends and conversion rates', 'contact-inbox'); ?></li>
                        <li><?php esc_html_e('Monitor email delivery and CRM sync health', 'contact-inbox'); ?></li>
                        <li><?php esc_html_e('Understand spam patterns and reCAPTCHA effectiveness', 'contact-inbox'); ?></li>
                        <li><?php esc_html_e('Diagnose issues quickly with actionable insights', 'contact-inbox'); ?></li>
                    </ul>
                </div>
            </div>

            <!-- Submissions Analytics -->
            <div id="analytics-help-submissions" class="cin-help-section">
                <h3><?php esc_html_e('📧 Submissions Analytics', 'contact-inbox'); ?></h3>
                <div class="cin-help-item">
                    <h4><?php esc_html_e('Submission Volume Trend', 'contact-inbox'); ?></h4>
                    <p><?php esc_html_e('Chart showing daily submission counts over time. Use filters to zoom in on specific date ranges.', 'contact-inbox'); ?></p>
                </div>
                <div class="cin-help-item">
                    <h4><?php esc_html_e('Submission Status Breakdown', 'contact-inbox'); ?></h4>
                    <ul>
                        <li><strong><?php esc_html_e('Read', 'contact-inbox'); ?>:</strong> <?php esc_html_e('Messages that have been opened and reviewed.', 'contact-inbox'); ?></li>
                        <li><strong><?php esc_html_e('Unread', 'contact-inbox'); ?>:</strong> <?php esc_html_e('New submissions awaiting review.', 'contact-inbox'); ?></li>
                        <li><strong><?php esc_html_e('Archived', 'contact-inbox'); ?>:</strong> <?php esc_html_e('Messages moved to archive for record-keeping.', 'contact-inbox'); ?></li>
                    </ul>
                </div>
                <div class="cin-help-item">
                    <h4><?php esc_html_e('Spam Detection', 'contact-inbox'); ?></h4>
                    <p><?php esc_html_e('Monitors spam attempts blocked by reCAPTCHA and validation rules. Watch for spikes which may indicate targeted attacks.', 'contact-inbox'); ?></p>
                </div>
                <div class="cin-help-item">
                    <h4><?php esc_html_e('Queue Success Rates', 'contact-inbox'); ?></h4>
                    <ul>
                        <li><strong><?php esc_html_e('Email Queue', 'contact-inbox'); ?>:</strong> <?php esc_html_e('Percentage of emails successfully delivered.', 'contact-inbox'); ?></li>
                        <li><strong><?php esc_html_e('CRM Queue', 'contact-inbox'); ?>:</strong> <?php esc_html_e('Percentage of CRM sync attempts that succeeded.', 'contact-inbox'); ?></li>
                    </ul>
                </div>
            </div>

            <!-- Performance -->
            <div id="analytics-help-performance" class="cin-help-section">
                <h3><?php esc_html_e('⚡ System Performance & Health', 'contact-inbox'); ?></h3>
                <div class="cin-help-item">
                    <h4><?php esc_html_e('Email Delivery Health', 'contact-inbox'); ?></h4>
                    <p><?php esc_html_e('Shows the success rate of email delivery. A healthy rate is 95% or higher. Failures may indicate SMTP configuration issues, ISP rate limits, or temporary network problems.', 'contact-inbox'); ?></p>
                </div>
                <div class="cin-help-item">
                    <h4><?php esc_html_e('Queue Health', 'contact-inbox'); ?></h4>
                    <ul>
                        <li><strong><?php esc_html_e('Pending', 'contact-inbox'); ?>:</strong> <?php esc_html_e('Messages waiting to be processed.', 'contact-inbox'); ?></li>
                        <li><strong><?php esc_html_e('Processing', 'contact-inbox'); ?>:</strong> <?php esc_html_e('Items currently being handled.', 'contact-inbox'); ?></li>
                        <li><strong><?php esc_html_e('Retry', 'contact-inbox'); ?>:</strong> <?php esc_html_e('Failed items queued for retry.', 'contact-inbox'); ?></li>
                        <li><strong><?php esc_html_e('DLQ', 'contact-inbox'); ?>:</strong> <?php esc_html_e('Dead Letter Queue - items that failed after max retries.', 'contact-inbox'); ?></li>
                    </ul>
                </div>
                <div class="cin-help-item">
                    <h4><?php esc_html_e('API Health', 'contact-inbox'); ?></h4>
                    <p><?php esc_html_e('Tracks requests to REST API endpoints and error rates. A healthy API shows low error rates (< 1%).', 'contact-inbox'); ?></p>
                </div>
                <div class="cin-help-item">
                    <h4><?php esc_html_e('Spam Intelligence', 'contact-inbox'); ?></h4>
                    <p><?php esc_html_e('Displays average reCAPTCHA scores: 🟢 Good (≥0.75), 🟡 Medium (0.5-0.75), 🔴 Suspicious (<0.5). Monitor trends to detect abuse patterns.', 'contact-inbox'); ?></p>
                </div>
            </div>

            <!-- CRM Integration -->
            <div id="analytics-help-crm" class="cin-help-section">
                <h3><?php esc_html_e('🔗 Salesforce CRM Dashboard', 'contact-inbox'); ?></h3>
                <div class="cin-help-item">
                    <h4><?php esc_html_e('Sync Statistics', 'contact-inbox'); ?></h4>
                    <ul>
                        <li><strong><?php esc_html_e('Total Synced', 'contact-inbox'); ?>:</strong> <?php esc_html_e('All records sent to CRM.', 'contact-inbox'); ?></li>
                        <li><strong><?php esc_html_e('Successful', 'contact-inbox'); ?>:</strong> <?php esc_html_e('Records that reached CRM without errors.', 'contact-inbox'); ?></li>
                        <li><strong><?php esc_html_e('Failed', 'contact-inbox'); ?>:</strong> <?php esc_html_e('Sync attempts that encountered errors.', 'contact-inbox'); ?></li>
                        <li><strong><?php esc_html_e('Pending', 'contact-inbox'); ?>:</strong> <?php esc_html_e('Records queued but not yet processed.', 'contact-inbox'); ?></li>
                    </ul>
                </div>
                <div class="cin-help-item">
                    <h4><?php esc_html_e('Integration Health', 'contact-inbox'); ?></h4>
                    <p><?php esc_html_e('Shows current authorization status and connection health. If unhealthy, check your OAuth token, API limits, or network connectivity.', 'contact-inbox'); ?></p>
                </div>
                <div class="cin-help-item">
                    <h4><?php esc_html_e('Daily Sync Activity', 'contact-inbox'); ?></h4>
                    <p><?php esc_html_e('Chart showing successful vs failed syncs per day. Sudden drops may indicate authentication or schema issues.', 'contact-inbox'); ?></p>
                </div>
                <div class="cin-help-item">
                    <h4><?php esc_html_e('Active Endpoints', 'contact-inbox'); ?></h4>
                    <p><?php esc_html_e('Lists Salesforce endpoints being targeted by this site. Each shows success rate and error details.', 'contact-inbox'); ?></p>
                </div>
            </div>

            <!-- User Analytics -->
            <div id="analytics-help-users" class="cin-help-section">
                <h3><?php esc_html_e('👥 User Analytics', 'contact-inbox'); ?></h3>
                <div class="cin-help-item">
                    <h4><?php esc_html_e('Device Distribution', 'contact-inbox'); ?></h4>
                    <p><?php esc_html_e('Shows submissions by device type (mobile, tablet, desktop). Helps identify if your forms are mobile-friendly or need optimization.', 'contact-inbox'); ?></p>
                </div>
                <div class="cin-help-item">
                    <h4><?php esc_html_e('Geographic Distribution', 'contact-inbox'); ?></h4>
                    <p><?php esc_html_e('Top 10 countries/regions submitting forms. Useful for identifying regional trends and potential localization needs.', 'contact-inbox'); ?></p>
                </div>
                <div class="cin-help-item">
                    <h4><?php esc_html_e('Traffic Sources', 'contact-inbox'); ?></h4>
                    <p><?php esc_html_e('Breakdown of where submissions originate (organic, direct, referral, etc.). Guides marketing and content strategy.', 'contact-inbox'); ?></p>
                </div>
                <div class="cin-help-item">
                    <h4><?php esc_html_e('Browser Distribution', 'contact-inbox'); ?></h4>
                    <p><?php esc_html_e('Shows which browsers users employ. Important for testing and identifying browser-specific issues.', 'contact-inbox'); ?></p>
                </div>
            </div>

            <!-- Background Jobs -->
            <div id="analytics-help-cron" class="cin-help-section">
                <h3><?php esc_html_e('⚙️ Background Jobs & Cron Health', 'contact-inbox'); ?></h3>
                <div class="cin-help-item">
                    <h4><?php esc_html_e('Overall Health Score', 'contact-inbox'); ?></h4>
                    <p><?php esc_html_e('Composite score based on job execution success, latency, and error rates. Healthy systems show 95%+ success.', 'contact-inbox'); ?></p>
                </div>
                <div class="cin-help-item">
                    <h4><?php esc_html_e('Core Background Jobs', 'contact-inbox'); ?></h4>
                    <ul>
                        <li><strong><?php esc_html_e('Email Queue Processor', 'contact-inbox'); ?>:</strong> <?php esc_html_e('Handles email deliveries every few minutes.', 'contact-inbox'); ?></li>
                        <li><strong><?php esc_html_e('CRM Queue Processor', 'contact-inbox'); ?>:</strong> <?php esc_html_e('Handles CRM record syncs and attachment uploads every few minutes.', 'contact-inbox'); ?></li>
                        <li><strong><?php esc_html_e('Cleanup/Maintenance', 'contact-inbox'); ?>:</strong> <?php esc_html_e('Runs daily to purge old logs and temp files.', 'contact-inbox'); ?></li>
                        <li><strong><?php esc_html_e('GDPR Expiry Check', 'contact-inbox'); ?>:</strong> <?php esc_html_e('Automated data retention enforcement.', 'contact-inbox'); ?></li>
                        <li><strong><?php esc_html_e('Analytics Aggregation', 'contact-inbox'); ?>:</strong> <?php esc_html_e('Rolls up daily stats for reporting.', 'contact-inbox'); ?></li>
                    </ul>
                </div>
                <div class="cin-help-item">
                    <h4><?php esc_html_e('Cron Scheduling Architecture', 'contact-inbox'); ?></h4>
                    <ul>
                        <li><?php esc_html_e('All recurring cron schedules are created once during plugin activation.', 'contact-inbox'); ?></li>
                        <li><?php esc_html_e('No runtime scheduling occurs to prevent duplicate schedules.', 'contact-inbox'); ?></li>
                        <li><?php esc_html_e('Manual processing operations trigger single-event execution without affecting recurring schedules.', 'contact-inbox'); ?></li>
                        <li><?php esc_html_e('Use the Maintenance page to reschedule or manually trigger processing as needed.', 'contact-inbox'); ?></li>
                    </ul>
                </div>
                <div class="cin-help-item">
                    <h4><?php esc_html_e('Monitoring Job Runs', 'contact-inbox'); ?></h4>
                    <ul>
                        <li><strong><?php esc_html_e('Last Run', 'contact-inbox'); ?>:</strong> <?php esc_html_e('Timestamp of the most recent execution.', 'contact-inbox'); ?></li>
                        <li><strong><?php esc_html_e('Next Run', 'contact-inbox'); ?>:</strong> <?php esc_html_e('Scheduled time for the next execution.', 'contact-inbox'); ?></li>
                        <li><strong><?php esc_html_e('Failures (24h)', 'contact-inbox'); ?>:</strong> <?php esc_html_e('Count of failed attempts in the past day.', 'contact-inbox'); ?></li>
                        <li><strong><?php esc_html_e('Duration', 'contact-inbox'); ?>:</strong> <?php esc_html_e('How long the last execution took.', 'contact-inbox'); ?></li>
                    </ul>
                </div>
            </div>

            <!-- Troubleshooting -->
            <div id="analytics-help-troubleshoot" class="cin-help-section">
                <h3><?php esc_html_e('🧯 Troubleshooting Tips', 'contact-inbox'); ?></h3>
                <div class="cin-help-item">
                    <h4><?php esc_html_e('High Email Failure Rate', 'contact-inbox'); ?></h4>
                    <ul>
                        <li><?php esc_html_e('Check SMTP settings and credentials.', 'contact-inbox'); ?></li>
                        <li><?php esc_html_e('Verify domain SPF/DKIM records are configured.', 'contact-inbox'); ?></li>
                        <li><?php esc_html_e('Check ISP rate limits and bounce feedback loops.', 'contact-inbox'); ?></li>
                    </ul>
                </div>
                <div class="cin-help-item">
                    <h4><?php esc_html_e('CRM Sync Failures', 'contact-inbox'); ?></h4>
                    <ul>
                        <li><?php esc_html_e('Verify OAuth token is still valid and not revoked.', 'contact-inbox'); ?></li>
                        <li><?php esc_html_e('Check for API rate limits in Salesforce.', 'contact-inbox'); ?></li>
                        <li><?php esc_html_e('Ensure field mappings match current Salesforce schema.', 'contact-inbox'); ?></li>
                    </ul>
                </div>
                <div class="cin-help-item">
                    <h4><?php esc_html_e('Queue Processing Delays', 'contact-inbox'); ?></h4>
                    <ul>
                        <li><?php esc_html_e('Check if background jobs are running (cron enabled).', 'contact-inbox'); ?></li>
                        <li><?php esc_html_e('Monitor DLQ for stuck items that need manual intervention.', 'contact-inbox'); ?></li>
                        <li><?php esc_html_e('Review job duration trends for performance issues.', 'contact-inbox'); ?></li>
                    </ul>
                </div>
                <div class="cin-help-item">
                    <h4><?php esc_html_e('Spam Score Anomalies', 'contact-inbox'); ?></h4>
                    <ul>
                        <li><?php esc_html_e('Check reCAPTCHA v3 sensitivity and score thresholds.', 'contact-inbox'); ?></li>
                        <li><?php esc_html_e('Review failed validation rules and adjust if too strict.', 'contact-inbox'); ?></li>
                        <li><?php esc_html_e('Verify that bots are not gaming validation patterns.', 'contact-inbox'); ?></li>
                    </ul>
                </div>
            </div>
        </div>
        <div class="cin-modal-footer">
            <p><?php esc_html_e('For further assistance, consult the full documentation or contact support.', 'contact-inbox'); ?></p>
        </div>
    </div>
</div>
