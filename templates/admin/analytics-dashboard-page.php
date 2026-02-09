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
        <h1 class="contactin-analytics-title"><?php esc_html_e('Dashboard', Config::TEXTDOMAIN); ?></h1>
        <button type="button" class="button button-secondary contactin-help-button"
            data-cin-help-open="cin-analytics-help-modal"
            aria-haspopup="dialog"
            aria-controls="cin-analytics-help-modal">
            <span class="contactin-analytics-button-icon-text">ℹ️</span><?php _e('Help', Config::TEXTDOMAIN); ?>
        </button>
    </div>

    <div class="contactin-analytics-header">
        <div class="analytics-summary">
            <div class="summary-card" data-summary="submissions">
                <div class="summary-label"><?php esc_html_e('Submissions', Config::TEXTDOMAIN); ?></div>
                <div class="summary-value" id="summary-submissions-7d" data-summary-value="submissions">
                    <?php echo esc_html(number_format_i18n((int)($submissions_7d ?? 0))); ?>
                </div>
            </div>
            <div class="summary-card" data-health="email" data-summary="email">
                <div class="summary-label"><?php esc_html_e('Email Delivery', Config::TEXTDOMAIN); ?></div>
                <div class="summary-value" id="summary-email-rate" data-summary-value="email">
                    <?php echo esc_html(number_format_i18n((float)($email_delivery['rate'] ?? 0), 1)); ?>%
                </div>
            </div>
            <div class="summary-card <?php echo (defined('CONTACTINBOX_IS_FREE') && CONTACTINBOX_IS_FREE) ? 'contactinbox-show-upgrade-modal' : ''; ?>" data-health="crm" data-summary="crm" <?php echo (defined('CONTACTINBOX_IS_FREE') && CONTACTINBOX_IS_FREE) ? 'title="' . esc_attr__('CRM Sync is available in Contact Inbox Pro', Config::TEXTDOMAIN) . '"' : ''; ?>>
                <div class="summary-label">
                    <?php esc_html_e('CRM Sync Rate', Config::TEXTDOMAIN); ?>
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
                <div class="summary-label"><?php esc_html_e('Acceptance Rate', Config::TEXTDOMAIN); ?></div>
                <div class="summary-value" id="summary-acceptance-rate" data-summary-value="acceptance">
                    <?php echo esc_html(number_format_i18n((float)($queue_processing_rate ?? 0), 1)); ?>%
                </div>
            </div>
        </div>

        <div class="analytics-filters">
            <label for="date-range"><?php esc_html_e('Filter:', Config::TEXTDOMAIN); ?></label>
            <select id="date-range" class="contactin-date-range">
                <option value="today"><?php esc_html_e('Today', Config::TEXTDOMAIN); ?></option>
                <option value="yesterday"><?php esc_html_e('Yesterday', Config::TEXTDOMAIN); ?></option>
                <option value="this_week"><?php esc_html_e('This Week (to date)', Config::TEXTDOMAIN); ?></option>
                <option value="this_month" selected><?php esc_html_e('This Month (to date)', Config::TEXTDOMAIN); ?></option>
                <option value="last_month"><?php esc_html_e('Last Month', Config::TEXTDOMAIN); ?></option>
                <option value="last_3_months"><?php esc_html_e('Last 3 Months', Config::TEXTDOMAIN); ?></option>
                <option value="last_6_months"><?php esc_html_e('Last 6 Months', Config::TEXTDOMAIN); ?></option>
                <option value="custom"><?php esc_html_e('Custom Range', Config::TEXTDOMAIN); ?></option>
            </select>
            <div class="custom-date-range" id="custom-date-range">
                <label for="start-date" class="screen-reader-text"><?php esc_html_e('Start Date', Config::TEXTDOMAIN); ?></label>
                <input type="date" id="start-date" name="start-date" />
                <label for="end-date" class="screen-reader-text"><?php esc_html_e('End Date', Config::TEXTDOMAIN); ?></label>
                <input type="date" id="end-date" name="end-date" />
                <button type="button" class="button" id="apply-date-range"><?php esc_html_e('Apply', Config::TEXTDOMAIN); ?></button>
            </div>
        </div>
    </div>

    <!-- Tab Navigation -->
    <nav class="contactin-analytics-tabs">
        <button class="tab-button active" data-tab="submissions" id="tab-submissions">
            <?php esc_html_e('Submissions', Config::TEXTDOMAIN); ?>
        </button>
        <button class="tab-button" data-tab="performance" id="tab-performance">
            <?php esc_html_e('System Performance', Config::TEXTDOMAIN); ?>
            <?php if ( defined('CONTACTINBOX_IS_FREE') && CONTACTINBOX_IS_FREE ) : ?>
                <span style="margin-left: 6px; background: #dc3545; color: white; padding: 2px 6px; border-radius: 3px; font-size: 10px; font-weight: bold; vertical-align: middle;">PRO</span>
            <?php endif; ?>
        </button>
        <button class="tab-button" data-tab="users" id="tab-users">
            <?php esc_html_e('Users', Config::TEXTDOMAIN); ?>
            <?php if ( defined('CONTACTINBOX_IS_FREE') && CONTACTINBOX_IS_FREE ) : ?>
                <span style="margin-left: 6px; background: #dc3545; color: white; padding: 2px 6px; border-radius: 3px; font-size: 10px; font-weight: bold; vertical-align: middle;">PRO</span>
            <?php endif; ?>
        </button>
        <button class="tab-button" data-tab="crm" id="tab-crm">
            <?php esc_html_e('Salesforce CRM', Config::TEXTDOMAIN); ?>
            <?php if ( defined('CONTACTINBOX_IS_FREE') && CONTACTINBOX_IS_FREE ) : ?>
                <span style="margin-left: 6px; background: #dc3545; color: white; padding: 2px 6px; border-radius: 3px; font-size: 10px; font-weight: bold; vertical-align: middle;">PRO</span>
            <?php endif; ?>
        </button>
        <button class="tab-button" data-tab="cron" id="tab-cron">
            <?php esc_html_e('Background Jobs', Config::TEXTDOMAIN); ?>
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
                <h2><?php esc_html_e('Submissions Analytics', Config::TEXTDOMAIN); ?></h2>

                <div class="analytics-grid">
                    <!-- Submission Trend Chart -->
                    <div class="analytics-card">
                        <h3><?php esc_html_e('Submission Volume Trend', Config::TEXTDOMAIN); ?></h3>
                        <div class="card-date-label submissions-date-label"></div>
                        <div class="chart-container">
                            <canvas id="chart-submissions-trend"></canvas>
                        </div>
                    </div>

                    <!-- Spam Blocked -->
                    <div class="analytics-card">
                        <h3><?php esc_html_e('Spam Blocked', Config::TEXTDOMAIN); ?></h3>
                        <div class="card-date-label submissions-date-label"></div>
                        <p class="card-description"><?php esc_html_e('Total spam blocked by security checks and classifier detection.', Config::TEXTDOMAIN); ?></p>
                        <div class="conversion-metric">
                            <div class="metric-value metric-spam" id="metric-spam-blocked">...</div>
                            <div class="metric-label"><?php esc_html_e('Spam Attempts', Config::TEXTDOMAIN); ?></div>
                        </div>
                    </div>

                    <!-- Rejection Reasons -->
                    <div class="analytics-card">
                        <h3><?php esc_html_e('Rejection Reasons', Config::TEXTDOMAIN); ?></h3>
                        <div class="card-date-label submissions-date-label"></div>
                        <p class="card-description"><?php esc_html_e('Breakdown of why form submissions were rejected during the selected period.', Config::TEXTDOMAIN); ?></p>
                        <div class="status-breakdown">
                            <div class="status-item rejection-recaptcha">
                                <span class="status-icon">🤖</span>
                                <div class="status-info">
                                    <span class="status-label"><?php esc_html_e('reCAPTCHA Failed', Config::TEXTDOMAIN); ?></span>
                                    <span class="status-value" id="rejection-recaptcha">...</span>
                                </div>
                            </div>
                            <div class="status-item rejection-validation">
                                <span class="status-icon">❌</span>
                                <div class="status-info">
                                    <span class="status-label"><?php esc_html_e('Validation Failed', Config::TEXTDOMAIN); ?></span>
                                    <span class="status-value" id="rejection-validation">...</span>
                                </div>
                            </div>
                            <div class="status-item rejection-ratelimit">
                                <span class="status-icon">⏱️</span>
                                <div class="status-info">
                                    <span class="status-label"><?php esc_html_e('Rate Limited', Config::TEXTDOMAIN); ?></span>
                                    <span class="status-value" id="rejection-ratelimit">...</span>
                                </div>
                            </div>
                            <div class="status-item rejection-nonce">
                                <span class="status-icon">🔒</span>
                                <div class="status-info">
                                    <span class="status-label"><?php esc_html_e('Nonce Failed', Config::TEXTDOMAIN); ?></span>
                                    <span class="status-value" id="rejection-nonce">...</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Status Breakdown -->
                    <div class="analytics-card">
                        <h3><?php esc_html_e('Inbox Review Status', Config::TEXTDOMAIN); ?></h3>
                        <div class="card-date-label submissions-date-label"></div>
                        <p class="card-description"><?php esc_html_e('Breakdown of messages by read status and archive state.', Config::TEXTDOMAIN); ?></p>
                        <div class="status-breakdown">
                            <div class="status-item status-read">
                                <span class="status-icon">✓</span>
                                <div class="status-info">
                                    <span class="status-label"><?php esc_html_e('Read', Config::TEXTDOMAIN); ?></span>
                                    <span class="status-value" id="status-completed">...</span>
                                </div>
                            </div>
                            <div class="status-item status-unread">
                                <span class="status-icon">●</span>
                                <div class="status-info">
                                    <span class="status-label"><?php esc_html_e('Unread', Config::TEXTDOMAIN); ?></span>
                                    <span class="status-value" id="status-pending">...</span>
                                </div>
                            </div>
                            <div class="status-item status-archived">
                                <span class="status-icon">📁</span>
                                <div class="status-info">
                                    <span class="status-label"><?php esc_html_e('Archived', Config::TEXTDOMAIN); ?></span>
                                    <span class="status-value" id="status-failed">...</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Conversion Rate -->
                    <div class="analytics-card">
                        <h3><?php esc_html_e('Message Processing Rates', Config::TEXTDOMAIN); ?></h3>
                        <div class="card-date-label submissions-date-label"></div>
                        <p class="card-description"><?php esc_html_e('Success rate breakdown by processing type over the selected period.', Config::TEXTDOMAIN); ?></p>
                        <div class="status-breakdown">
                            <div class="status-item rate-email">
                                <span class="status-icon">📧</span>
                                <div class="status-info">
                                    <span class="status-label"><?php esc_html_e('Email', Config::TEXTDOMAIN); ?></span>
                                    <span class="status-value" id="rate-email">...</span>
                                </div>
                            </div>
                            <div class="status-item rate-crm">
                                <span class="status-icon">🔗</span>
                                <div class="status-info">
                                    <span class="status-label"><?php esc_html_e('CRM', Config::TEXTDOMAIN); ?></span>
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
                <h2><?php esc_html_e('System Performance & Health', Config::TEXTDOMAIN); ?></h2>

                <div class="performance-kpi-grid">
                    <!-- Email Delivery Health -->
                    <div class="analytics-card performance-kpi" data-health="email">
                        <div class="card-heading">
                            <h3><?php esc_html_e('Email Delivery', Config::TEXTDOMAIN); ?></h3>
                            <span class="date-range-label" id="performance-email-date-label"></span>
                        </div>
                        <p class="card-description"><?php esc_html_e('Delivery success rate from email logs for the selected window.', Config::TEXTDOMAIN); ?></p>
                        <div class="kpi-primary">
                            <div class="kpi-value" id="email-delivery-rate">
                                <span class="percentage-value">—</span>
                            </div>
                            <div class="kpi-metrics">
                                <div class="stat-mini">
                                    <span class="stat-label"><?php esc_html_e('Sent', Config::TEXTDOMAIN); ?></span>
                                    <span class="stat-value" id="email-sent">0</span>
                                </div>
                                <div class="stat-mini">
                                    <span class="stat-label"><?php esc_html_e('Failed', Config::TEXTDOMAIN); ?></span>
                                    <span class="stat-value" id="email-failed">0</span>
                                </div>
                                <div class="stat-mini">
                                    <span class="stat-label"><?php esc_html_e('Total', Config::TEXTDOMAIN); ?></span>
                                    <span class="stat-value" id="email-total">0</span>
                                </div>
                            </div>
                        </div>
                        <div class="failure-reasons compact">
                            <p class="failure-header"><?php esc_html_e('Top Failures', Config::TEXTDOMAIN); ?></p>
                            <ul class="failure-list" id="email-failures">
                                <li><?php esc_html_e('Loading...', Config::TEXTDOMAIN); ?></li>
                            </ul>
                        </div>
                    </div>

                    <!-- Spam Intelligence -->
                    <div class="analytics-card performance-kpi">
                        <div class="card-heading">
                            <h3><?php esc_html_e('Spam Intelligence', Config::TEXTDOMAIN); ?></h3>
                            <span class="date-range-label" id="performance-spam-date-label"></span>
                        </div>
                        <p class="card-description"><?php esc_html_e('reCAPTCHA scoring and suspicious activity spotted in the same period.', Config::TEXTDOMAIN); ?></p>
                        <div class="kpi-primary">
                            <div class="kpi-value" id="spam-avg-score">—</div>
                            <div class="kpi-metrics">
                                <div class="stat-mini">
                                    <span class="stat-label"><?php esc_html_e('Total', Config::TEXTDOMAIN); ?></span>
                                    <span class="stat-value" id="spam-total">0</span>
                                </div>
                                <div class="stat-mini">
                                    <span class="stat-label"><?php esc_html_e('Flagged', Config::TEXTDOMAIN); ?></span>
                                    <span class="stat-value" id="spam-flagged">0</span>
                                </div>
                                <div class="stat-mini">
                                    <span class="stat-label"><?php esc_html_e('Spam %', Config::TEXTDOMAIN); ?></span>
                                    <span class="stat-value" id="spam-percentage">—%</span>
                                </div>
                            </div>
                        </div>
                        <div class="score-info">
                            <p class="score-info-text">
                                <span class="score-badge good">🟢</span> <?php esc_html_e('Good: ≥0.75', Config::TEXTDOMAIN); ?> |
                                <span class="score-badge medium">🟡</span> <?php esc_html_e('Medium: 0.5-0.75', Config::TEXTDOMAIN); ?> |
                                <span class="score-badge bad">🔴</span> <?php esc_html_e('Suspicious: <0.5', Config::TEXTDOMAIN); ?>
                            </p>
                        </div>
                    </div>
                </div>

                <div class="performance-system-grid">
                    <!-- API Health -->
                    <div class="analytics-card performance-system" data-health="api">
                        <div class="card-heading">
                            <h3><?php esc_html_e('API Availability', Config::TEXTDOMAIN); ?></h3>
                            <span class="date-range-label" id="performance-api-date-label"></span>
                        </div>
                        <p class="card-description"><?php esc_html_e('REST API performance and error rates for the chosen filter.', Config::TEXTDOMAIN); ?></p>
                        <div class="health-indicator" id="health-api">
                            <div class="status-dot pending"></div>
                            <span class="status-text"><?php esc_html_e('Loading...', Config::TEXTDOMAIN); ?></span>
                        </div>
                        <div class="health-stats-inline">
                            <div class="stat-mini">
                                <span class="stat-label"><?php esc_html_e('Requests', Config::TEXTDOMAIN); ?></span>
                                <span class="stat-value" id="api-requests">0</span>
                            </div>
                            <div class="stat-mini">
                                <span class="stat-label"><?php esc_html_e('Errors', Config::TEXTDOMAIN); ?></span>
                                <span class="stat-value" id="api-errors">0</span>
                            </div>
                        </div>
                    </div>

                    <!-- Processing Queue Health -->
                    <div class="analytics-card performance-system" data-health="queue">
                        <div class="card-heading">
                            <h3><?php esc_html_e('Processing Queue', Config::TEXTDOMAIN); ?></h3>
                            <span class="current-state-label"><?php esc_html_e('Live snapshot', Config::TEXTDOMAIN); ?></span>
                        </div>
                        <p class="card-description"><?php esc_html_e('Real-time queue state (not affected by date filters).', Config::TEXTDOMAIN); ?></p>
                        <div class="health-indicator" id="health-queue">
                            <div class="status-dot pending"></div>
                            <span class="status-text"><?php esc_html_e('Loading...', Config::TEXTDOMAIN); ?></span>
                        </div>
                        <div class="health-stats-inline queue-stat-text">
                            <p class="queue-stat-line queue-stat-line-failed">
                                <span class="queue-stat-number" id="queue-failed">0</span>
                                <?php esc_html_e('messages have failed processing', Config::TEXTDOMAIN); ?>
                            </p>
                            <p class="queue-stat-line queue-stat-line-pending">
                                <span class="queue-stat-number" id="queue-pending">0</span>
                                <?php esc_html_e('messages are pending', Config::TEXTDOMAIN); ?>
                            </p>
                        </div>
                    </div>

                    <!-- Overall System Health -->
                    <div class="analytics-card performance-system" data-health="system">
                        <div class="card-heading">
                            <h3><?php esc_html_e('System Alerts', Config::TEXTDOMAIN); ?></h3>
                            <span class="date-range-label" id="performance-system-date-label"></span>
                        </div>
                        <p class="card-description"><?php esc_html_e('Consolidated health checks across queue, email, API, and CRM.', Config::TEXTDOMAIN); ?></p>
                        <div class="health-indicator" id="system-status">
                            <div class="status-dot pending"></div>
                            <span class="status-text"><?php esc_html_e('Analyzing...', Config::TEXTDOMAIN); ?></span>
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
                        'label'   => __('Email Processing', Config::TEXTDOMAIN),
                        'counts'  => $email_stats,
                    ],
                    'crm' => [
                        'label'   => __('CRM Processing', Config::TEXTDOMAIN),
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
                    $state_label = __('Stable', Config::TEXTDOMAIN);
                    if ($has_dlq || $has_retry) {
                        $state = 'critical';
                        $state_label = __('Attention', Config::TEXTDOMAIN);
                    } elseif ($high_pending) {
                        $state = 'warning';
                        $state_label = __('Busy', Config::TEXTDOMAIN);
                    }

                    $queues[$key]['state'] = $state;
                    $queues[$key]['state_label'] = $state_label;
                }

                $dlq_total = intval(($queues['email']['counts']['dlq'] ?? 0) + ($queues['crm']['counts']['dlq'] ?? 0));
                $dlq_state = $dlq_total > 0 ? 'critical' : 'good';
                $dlq_label = $dlq_total > 0 ? __('Needs review', Config::TEXTDOMAIN) : __('Clear', Config::TEXTDOMAIN);
                ?>

                <div class="queue-status-header">
                    <h3><?php esc_html_e('Message Processing Status', Config::TEXTDOMAIN); ?> <span class="date-range-label" id="queue-status-date-label"></span></h3>
                    <p class="queue-description"><?php esc_html_e('Email and CRM throughput for the selected period.', Config::TEXTDOMAIN); ?></p>
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
                                    <span class="queue-total-label"><?php esc_html_e('Pending', Config::TEXTDOMAIN); ?></span>
                                    <span class="queue-total-value"><?php echo intval($queue_data['counts']['pending']); ?></span>
                                </div>
                            </div>
                            <div class="queue-counts">
                                <div class="queue-stat">
                                    <span class="stat-label"><?php esc_html_e('Sent', Config::TEXTDOMAIN); ?></span>
                                    <span class="stat-value"><?php echo intval($queue_data['counts']['sent'] ?? 0); ?></span>
                                </div>
                                <div class="queue-stat">
                                    <span class="stat-label"><?php esc_html_e('Failed', Config::TEXTDOMAIN); ?></span>
                                    <span class="stat-value"><?php echo intval($queue_data['counts']['dlq']); ?></span>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>

                    <div class="analytics-card queue-card queue-dlq">
                        <div class="queue-card-head">
                            <div class="queue-card-title">
                                <span class="queue-name"><?php esc_html_e('Failed Messages (All Types)', Config::TEXTDOMAIN); ?></span>
                                <span class="queue-chip state-<?php echo esc_attr($dlq_state); ?>"><?php echo esc_html($dlq_label); ?></span>
                            </div>
                            <div class="queue-total">
                                <span class="queue-total-label"><?php esc_html_e('Total', Config::TEXTDOMAIN); ?></span>
                                <span class="queue-total-value"><?php echo $dlq_total; ?></span>
                            </div>
                        </div>
                        <p class="queue-help-text"><?php esc_html_e('Messages that failed email or CRM processing.', Config::TEXTDOMAIN); ?></p>
                    </div>
                </div>
            </div>
        </div>

        <!-- SALESFORCE CRM TAB -->
        <div class="tab-pane" id="crm-pane" data-tab="crm">
            <div class="analytics-section">
                <h2><?php esc_html_e('Salesforce CRM Dashboard', Config::TEXTDOMAIN); ?></h2>

                <div class="analytics-grid">
                    <!-- CRM Statistics -->
                    <div class="analytics-card">
                        <h3><?php esc_html_e('Sync Statistics', Config::TEXTDOMAIN); ?> <span class="date-range-label" id="crm-stats-date-label"></span></h3>
                        <p class="card-description"><?php esc_html_e('CRM sync attempts for the selected period', Config::TEXTDOMAIN); ?></p>
                        <div class="crm-stats">
                            <div class="stat-item">
                                <span class="stat-label"><?php esc_html_e('Total Synced', Config::TEXTDOMAIN); ?></span>
                                <span class="stat-value" id="crm-total-synced">0</span>
                            </div>
                            <div class="stat-item">
                                <span class="stat-label"><?php esc_html_e('Successful', Config::TEXTDOMAIN); ?></span>
                                <span class="stat-value" id="crm-success-count">0</span>
                            </div>
                            <div class="stat-item">
                                <span class="stat-label"><?php esc_html_e('Failed', Config::TEXTDOMAIN); ?></span>
                                <span class="stat-value" id="crm-failed-count">0</span>
                            </div>
                            <div class="stat-item">
                                <span class="stat-label"><?php esc_html_e('Pending', Config::TEXTDOMAIN); ?></span>
                                <span class="stat-value" id="crm-pending-count">0</span>
                            </div>
                            <div class="stat-item">
                                <span class="stat-label"><?php esc_html_e('Success Rate', Config::TEXTDOMAIN); ?></span>
                                <span class="stat-value" id="crm-success-rate">0%</span>
                            </div>
                        </div>
                    </div>

                    <!-- CRM Health Status -->
                    <div class="analytics-card">
                        <h3><?php esc_html_e('Integration Health', Config::TEXTDOMAIN); ?> <span class="current-state-label"><?php esc_html_e('(Current)', Config::TEXTDOMAIN); ?></span></h3>
                        <div class="health-indicator" id="crm-health">
                            <div class="status-dot pending"></div>
                            <span class="status-text"><?php esc_html_e('Loading...', Config::TEXTDOMAIN); ?></span>
                        </div>
                        <div class="health-details" id="crm-health-details">
                            <div class="health-item">
                                <span class="health-label"><?php esc_html_e('Authorization', Config::TEXTDOMAIN); ?></span>
                                <span class="health-status" id="crm-auth-status">—</span>
                            </div>
                        </div>
                    </div>

                    <!-- Sync Trend Chart -->
                    <div class="analytics-card">
                        <h3><?php esc_html_e('Daily Sync Activity', Config::TEXTDOMAIN); ?> <span class="date-range-label" id="crm-chart-date-label"></span></h3>
                        <div class="chart-container">
                            <canvas id="chart-crm-daily-stats"></canvas>
                        </div>
                    </div>

                    <!-- Active Endpoints -->
                    <div class="analytics-card">
                        <h3><?php esc_html_e('Active Endpoints', Config::TEXTDOMAIN); ?> <span class="date-range-label" id="crm-endpoints-date-label"></span></h3>
                        <div class="endpoint-list" id="crm-endpoints">
                            <p class="placeholder-text"><?php esc_html_e('Loading endpoint data...', Config::TEXTDOMAIN); ?></p>
                        </div>
                    </div>

                    <!-- Recent Activity -->
                    <div class="analytics-card full-width">
                        <h3><?php esc_html_e('Recent Sync Activity', Config::TEXTDOMAIN); ?></h3>
                        <div class="activity-log" id="crm-activity-log">
                            <p class="placeholder-text"><?php esc_html_e('Loading activity...', Config::TEXTDOMAIN); ?></p>
                        </div>
                    </div>
                </div>

            </div>
        </div>

        <!-- CRON TAB -->
        <div class="tab-pane" id="cron-pane" data-tab="cron">
            <div class="analytics-section">
                <h2><?php esc_html_e('Background Jobs & Cron Health', Config::TEXTDOMAIN); ?></h2>

                <div class="cron-summary-grid">
                    <div class="analytics-card">
                        <h4><?php esc_html_e('Overall Health', Config::TEXTDOMAIN); ?></h4>
                        <div class="cron-health-score" id="cron-health-overall">—</div>
                        <div class="cron-stat-pair">
                            <span><?php esc_html_e('Executions (24h)', Config::TEXTDOMAIN); ?></span>
                            <span id="cron-executions-24h">0</span>
                        </div>
                        <div class="cron-stat-pair">
                            <span><?php esc_html_e('Failed (24h)', Config::TEXTDOMAIN); ?></span>
                            <span id="cron-failed-24h">0</span>
                        </div>
                        <div class="cron-stat-pair">
                            <span><?php esc_html_e('Avg Duration (24h)', Config::TEXTDOMAIN); ?></span>
                            <span id="cron-avg-duration">—</span>
                        </div>
                    </div>

                    <div class="analytics-card">
                        <h4><?php esc_html_e('Email Queue Processor (24h)', Config::TEXTDOMAIN); ?></h4>
                        <div class="cron-health-row" id="cron-contactinbox_process_email_queue-status">—</div>
                        <div class="cron-meta-grid" id="cron-contactinbox_process_email_queue-meta">
                            <span class="meta-label"><?php esc_html_e('Last Run', Config::TEXTDOMAIN); ?></span>
                            <span class="meta-value" data-field="last_run">—</span>
                            <span class="meta-label"><?php esc_html_e('Next Run', Config::TEXTDOMAIN); ?></span>
                            <span class="meta-value" data-field="next_run">—</span>
                            <span class="meta-label"><?php esc_html_e('Failures', Config::TEXTDOMAIN); ?></span>
                            <span class="meta-value" data-field="failure_count">0</span>
                            <span class="meta-label"><?php esc_html_e('Duration', Config::TEXTDOMAIN); ?></span>
                            <span class="meta-value" data-field="last_duration_ms">—</span>
                        </div>
                    </div>

                    <div class="analytics-card">
                        <h4><?php esc_html_e('CRM Queue Processor (24h)', Config::TEXTDOMAIN); ?></h4>
                        <div class="cron-health-row" id="cron-contactinbox_process_crm_queue-status">—</div>
                        <div class="cron-meta-grid" id="cron-contactinbox_process_crm_queue-meta">
                            <span class="meta-label"><?php esc_html_e('Last Run', Config::TEXTDOMAIN); ?></span>
                            <span class="meta-value" data-field="last_run">—</span>
                            <span class="meta-label"><?php esc_html_e('Next Run', Config::TEXTDOMAIN); ?></span>
                            <span class="meta-value" data-field="next_run">—</span>
                            <span class="meta-label"><?php esc_html_e('Failures', Config::TEXTDOMAIN); ?></span>
                            <span class="meta-value" data-field="failure_count">0</span>
                            <span class="meta-label"><?php esc_html_e('Duration', Config::TEXTDOMAIN); ?></span>
                            <span class="meta-value" data-field="last_duration_ms">—</span>
                        </div>
                    </div>

                    <div class="analytics-card">
                        <h4><?php esc_html_e('Cleanup (Maintenance, 24h)', Config::TEXTDOMAIN); ?></h4>
                        <div class="cron-health-row" id="cron-contactinbox_cleanup_cron-status">—</div>
                        <div class="cron-meta-grid" id="cron-contactinbox_cleanup_cron-meta">
                            <span class="meta-label"><?php esc_html_e('Last Run', Config::TEXTDOMAIN); ?></span>
                            <span class="meta-value" data-field="last_run">—</span>
                            <span class="meta-label"><?php esc_html_e('Next Run', Config::TEXTDOMAIN); ?></span>
                            <span class="meta-value" data-field="next_run">—</span>
                            <span class="meta-label"><?php esc_html_e('Failures', Config::TEXTDOMAIN); ?></span>
                            <span class="meta-value" data-field="failure_count">0</span>
                            <span class="meta-label"><?php esc_html_e('Duration', Config::TEXTDOMAIN); ?></span>
                            <span class="meta-value" data-field="last_duration_ms">—</span>
                        </div>
                    </div>

                    <div class="analytics-card">
                        <h4><?php esc_html_e('GDPR Expiry (24h)', Config::TEXTDOMAIN); ?></h4>
                        <div class="cron-health-row" id="cron-contactinbox_gdpr_expiry_check-status">—</div>
                        <div class="cron-meta-grid" id="cron-contactinbox_gdpr_expiry_check-meta">
                            <span class="meta-label"><?php esc_html_e('Last Run', Config::TEXTDOMAIN); ?></span>
                            <span class="meta-value" data-field="last_run">—</span>
                            <span class="meta-label"><?php esc_html_e('Next Run', Config::TEXTDOMAIN); ?></span>
                            <span class="meta-value" data-field="next_run">—</span>
                            <span class="meta-label"><?php esc_html_e('Failures', Config::TEXTDOMAIN); ?></span>
                            <span class="meta-value" data-field="failure_count">0</span>
                            <span class="meta-label"><?php esc_html_e('Duration', Config::TEXTDOMAIN); ?></span>
                            <span class="meta-value" data-field="last_duration_ms">—</span>
                        </div>
                    </div>

                    <div class="analytics-card">
                        <h4><?php esc_html_e('Analytics Aggregation (24h)', Config::TEXTDOMAIN); ?></h4>
                        <div class="cron-health-row" id="cron-contactin_daily_analytics_aggregation-status">—</div>
                        <div class="cron-meta-grid" id="cron-contactin_daily_analytics_aggregation-meta">
                            <span class="meta-label"><?php esc_html_e('Last Run', Config::TEXTDOMAIN); ?></span>
                            <span class="meta-value" data-field="last_run">—</span>
                            <span class="meta-label"><?php esc_html_e('Next Run', Config::TEXTDOMAIN); ?></span>
                            <span class="meta-value" data-field="next_run">—</span>
                            <span class="meta-label"><?php esc_html_e('Failures', Config::TEXTDOMAIN); ?></span>
                            <span class="meta-value" data-field="failure_count">0</span>
                            <span class="meta-label"><?php esc_html_e('Duration', Config::TEXTDOMAIN); ?></span>
                            <span class="meta-value" data-field="last_duration_ms">—</span>
                        </div>
                    </div>
                </div>

                <div class="analytics-card">
                    <h4><?php esc_html_e('Recent Cron Events (24h)', Config::TEXTDOMAIN); ?></h4>
                    <table class="cron-table" id="cron-events-table">
                        <thead>
                            <tr>
                                <th><?php esc_html_e('Job', Config::TEXTDOMAIN); ?></th>
                                <th><?php esc_html_e('Status', Config::TEXTDOMAIN); ?></th>
                                <th><?php esc_html_e('Last Run', Config::TEXTDOMAIN); ?></th>
                                <th><?php esc_html_e('Duration', Config::TEXTDOMAIN); ?></th>
                                <th><?php esc_html_e('Next Run', Config::TEXTDOMAIN); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr><td colspan="5"><?php esc_html_e('No records available for the past 24 hours.', Config::TEXTDOMAIN); ?></td></tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- USERS TAB -->
        <div class="tab-pane" id="users-pane" data-tab="users">
            <div class="analytics-section">
                <h2><?php esc_html_e('User Analytics', Config::TEXTDOMAIN); ?></h2>

                <div class="analytics-grid">
                    <!-- Device Distribution -->
                    <div class="analytics-card">
                        <h3><?php esc_html_e('Device Distribution', Config::TEXTDOMAIN); ?></h3>
                        <div class="chart-container">
                            <canvas id="chart-device-distribution" data-chart-type="pie"></canvas>
                        </div>
                        <div class="chart-legend" id="legend-device"></div>
                    </div>

                    <!-- Geographic Distribution -->
                    <div class="analytics-card">
                        <h3><?php esc_html_e('Geographic Distribution (Top 10)', Config::TEXTDOMAIN); ?></h3>
                        <div class="chart-container">
                            <canvas id="chart-geographic-distribution" data-chart-type="bar"></canvas>
                        </div>
                        <div class="chart-legend" id="legend-geographic"></div>
                    </div>

                    <!-- Traffic Sources -->
                    <div class="analytics-card">
                        <h3><?php esc_html_e('Traffic Sources', Config::TEXTDOMAIN); ?></h3>
                        <div class="chart-container">
                            <canvas id="chart-traffic-sources" data-chart-type="doughnut"></canvas>
                        </div>
                        <div class="chart-legend" id="legend-sources"></div>
                    </div>

                    <!-- Browser Distribution -->
                    <div class="analytics-card">
                        <h3><?php esc_html_e('Browser Distribution', Config::TEXTDOMAIN); ?></h3>
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
            <h2><?php esc_html_e('Dashboard', Config::TEXTDOMAIN); ?></h2>
            <button type="button" class="cin-modal-close" aria-label="<?php esc_attr_e('Close', Config::TEXTDOMAIN); ?>">
                <span aria-hidden="true">&times;</span>
            </button>
        </div>
        <div class="cin-modal-body">
            <!-- Quick Navigation -->
            <div class="cin-help-nav">
                <h3><?php esc_html_e('Quick Navigation', Config::TEXTDOMAIN); ?></h3>
                <ul>
                    <li><a href="#analytics-help-overview" class="cin-help-link"><?php esc_html_e('Overview', Config::TEXTDOMAIN); ?></a></li>
                    <li><a href="#analytics-help-submissions" class="cin-help-link"><?php esc_html_e('Submissions', Config::TEXTDOMAIN); ?></a></li>
                    <li><a href="#analytics-help-performance" class="cin-help-link"><?php esc_html_e('Performance', Config::TEXTDOMAIN); ?></a></li>
                    <li><a href="#analytics-help-crm" class="cin-help-link"><?php esc_html_e('CRM Integration', Config::TEXTDOMAIN); ?></a></li>
                    <li><a href="#analytics-help-users" class="cin-help-link"><?php esc_html_e('User Analytics', Config::TEXTDOMAIN); ?></a></li>
                    <li><a href="#analytics-help-cron" class="cin-help-link"><?php esc_html_e('Background Jobs', Config::TEXTDOMAIN); ?></a></li>
                    <li><a href="#analytics-help-troubleshoot" class="cin-help-link"><?php esc_html_e('Troubleshooting', Config::TEXTDOMAIN); ?></a></li>
                </ul>
            </div>

            <!-- Overview -->
            <div id="analytics-help-overview" class="cin-help-section">
                <h3><?php esc_html_e('📊 Overview', Config::TEXTDOMAIN); ?></h3>
                <div class="cin-help-item">
                    <p><?php esc_html_e('Welcome to the Analytics Dashboard! This comprehensive guide explains each section, metric, and how to interpret the business intelligence (BI) data to get the most value from your contact form analytics.', Config::TEXTDOMAIN); ?></p>
                    <ul>
                        <li><?php esc_html_e('Track submission trends and conversion rates', Config::TEXTDOMAIN); ?></li>
                        <li><?php esc_html_e('Monitor email delivery and CRM sync health', Config::TEXTDOMAIN); ?></li>
                        <li><?php esc_html_e('Understand spam patterns and reCAPTCHA effectiveness', Config::TEXTDOMAIN); ?></li>
                        <li><?php esc_html_e('Diagnose issues quickly with actionable insights', Config::TEXTDOMAIN); ?></li>
                    </ul>
                </div>
            </div>

            <!-- Submissions Analytics -->
            <div id="analytics-help-submissions" class="cin-help-section">
                <h3><?php esc_html_e('📧 Submissions Analytics', Config::TEXTDOMAIN); ?></h3>
                <div class="cin-help-item">
                    <h4><?php esc_html_e('Submission Volume Trend', Config::TEXTDOMAIN); ?></h4>
                    <p><?php esc_html_e('Chart showing daily submission counts over time. Use filters to zoom in on specific date ranges.', Config::TEXTDOMAIN); ?></p>
                </div>
                <div class="cin-help-item">
                    <h4><?php esc_html_e('Submission Status Breakdown', Config::TEXTDOMAIN); ?></h4>
                    <ul>
                        <li><strong><?php esc_html_e('Read', Config::TEXTDOMAIN); ?>:</strong> <?php esc_html_e('Messages that have been opened and reviewed.', Config::TEXTDOMAIN); ?></li>
                        <li><strong><?php esc_html_e('Unread', Config::TEXTDOMAIN); ?>:</strong> <?php esc_html_e('New submissions awaiting review.', Config::TEXTDOMAIN); ?></li>
                        <li><strong><?php esc_html_e('Archived', Config::TEXTDOMAIN); ?>:</strong> <?php esc_html_e('Messages moved to archive for record-keeping.', Config::TEXTDOMAIN); ?></li>
                    </ul>
                </div>
                <div class="cin-help-item">
                    <h4><?php esc_html_e('Spam Detection', Config::TEXTDOMAIN); ?></h4>
                    <p><?php esc_html_e('Monitors spam attempts blocked by reCAPTCHA and validation rules. Watch for spikes which may indicate targeted attacks.', Config::TEXTDOMAIN); ?></p>
                </div>
                <div class="cin-help-item">
                    <h4><?php esc_html_e('Queue Success Rates', Config::TEXTDOMAIN); ?></h4>
                    <ul>
                        <li><strong><?php esc_html_e('Email Queue', Config::TEXTDOMAIN); ?>:</strong> <?php esc_html_e('Percentage of emails successfully delivered.', Config::TEXTDOMAIN); ?></li>
                        <li><strong><?php esc_html_e('CRM Queue', Config::TEXTDOMAIN); ?>:</strong> <?php esc_html_e('Percentage of CRM sync attempts that succeeded.', Config::TEXTDOMAIN); ?></li>
                    </ul>
                </div>
            </div>

            <!-- Performance -->
            <div id="analytics-help-performance" class="cin-help-section">
                <h3><?php esc_html_e('⚡ System Performance & Health', Config::TEXTDOMAIN); ?></h3>
                <div class="cin-help-item">
                    <h4><?php esc_html_e('Email Delivery Health', Config::TEXTDOMAIN); ?></h4>
                    <p><?php esc_html_e('Shows the success rate of email delivery. A healthy rate is 95% or higher. Failures may indicate SMTP configuration issues, ISP rate limits, or temporary network problems.', Config::TEXTDOMAIN); ?></p>
                </div>
                <div class="cin-help-item">
                    <h4><?php esc_html_e('Queue Health', Config::TEXTDOMAIN); ?></h4>
                    <ul>
                        <li><strong><?php esc_html_e('Pending', Config::TEXTDOMAIN); ?>:</strong> <?php esc_html_e('Messages waiting to be processed.', Config::TEXTDOMAIN); ?></li>
                        <li><strong><?php esc_html_e('Processing', Config::TEXTDOMAIN); ?>:</strong> <?php esc_html_e('Items currently being handled.', Config::TEXTDOMAIN); ?></li>
                        <li><strong><?php esc_html_e('Retry', Config::TEXTDOMAIN); ?>:</strong> <?php esc_html_e('Failed items queued for retry.', Config::TEXTDOMAIN); ?></li>
                        <li><strong><?php esc_html_e('DLQ', Config::TEXTDOMAIN); ?>:</strong> <?php esc_html_e('Dead Letter Queue - items that failed after max retries.', Config::TEXTDOMAIN); ?></li>
                    </ul>
                </div>
                <div class="cin-help-item">
                    <h4><?php esc_html_e('API Health', Config::TEXTDOMAIN); ?></h4>
                    <p><?php esc_html_e('Tracks requests to REST API endpoints and error rates. A healthy API shows low error rates (< 1%).', Config::TEXTDOMAIN); ?></p>
                </div>
                <div class="cin-help-item">
                    <h4><?php esc_html_e('Spam Intelligence', Config::TEXTDOMAIN); ?></h4>
                    <p><?php esc_html_e('Displays average reCAPTCHA scores: 🟢 Good (≥0.75), 🟡 Medium (0.5-0.75), 🔴 Suspicious (<0.5). Monitor trends to detect abuse patterns.', Config::TEXTDOMAIN); ?></p>
                </div>
            </div>

            <!-- CRM Integration -->
            <div id="analytics-help-crm" class="cin-help-section">
                <h3><?php esc_html_e('🔗 Salesforce CRM Dashboard', Config::TEXTDOMAIN); ?></h3>
                <div class="cin-help-item">
                    <h4><?php esc_html_e('Sync Statistics', Config::TEXTDOMAIN); ?></h4>
                    <ul>
                        <li><strong><?php esc_html_e('Total Synced', Config::TEXTDOMAIN); ?>:</strong> <?php esc_html_e('All records sent to CRM.', Config::TEXTDOMAIN); ?></li>
                        <li><strong><?php esc_html_e('Successful', Config::TEXTDOMAIN); ?>:</strong> <?php esc_html_e('Records that reached CRM without errors.', Config::TEXTDOMAIN); ?></li>
                        <li><strong><?php esc_html_e('Failed', Config::TEXTDOMAIN); ?>:</strong> <?php esc_html_e('Sync attempts that encountered errors.', Config::TEXTDOMAIN); ?></li>
                        <li><strong><?php esc_html_e('Pending', Config::TEXTDOMAIN); ?>:</strong> <?php esc_html_e('Records queued but not yet processed.', Config::TEXTDOMAIN); ?></li>
                    </ul>
                </div>
                <div class="cin-help-item">
                    <h4><?php esc_html_e('Integration Health', Config::TEXTDOMAIN); ?></h4>
                    <p><?php esc_html_e('Shows current authorization status and connection health. If unhealthy, check your OAuth token, API limits, or network connectivity.', Config::TEXTDOMAIN); ?></p>
                </div>
                <div class="cin-help-item">
                    <h4><?php esc_html_e('Daily Sync Activity', Config::TEXTDOMAIN); ?></h4>
                    <p><?php esc_html_e('Chart showing successful vs failed syncs per day. Sudden drops may indicate authentication or schema issues.', Config::TEXTDOMAIN); ?></p>
                </div>
                <div class="cin-help-item">
                    <h4><?php esc_html_e('Active Endpoints', Config::TEXTDOMAIN); ?></h4>
                    <p><?php esc_html_e('Lists Salesforce endpoints being targeted by this site. Each shows success rate and error details.', Config::TEXTDOMAIN); ?></p>
                </div>
            </div>

            <!-- User Analytics -->
            <div id="analytics-help-users" class="cin-help-section">
                <h3><?php esc_html_e('👥 User Analytics', Config::TEXTDOMAIN); ?></h3>
                <div class="cin-help-item">
                    <h4><?php esc_html_e('Device Distribution', Config::TEXTDOMAIN); ?></h4>
                    <p><?php esc_html_e('Shows submissions by device type (mobile, tablet, desktop). Helps identify if your forms are mobile-friendly or need optimization.', Config::TEXTDOMAIN); ?></p>
                </div>
                <div class="cin-help-item">
                    <h4><?php esc_html_e('Geographic Distribution', Config::TEXTDOMAIN); ?></h4>
                    <p><?php esc_html_e('Top 10 countries/regions submitting forms. Useful for identifying regional trends and potential localization needs.', Config::TEXTDOMAIN); ?></p>
                </div>
                <div class="cin-help-item">
                    <h4><?php esc_html_e('Traffic Sources', Config::TEXTDOMAIN); ?></h4>
                    <p><?php esc_html_e('Breakdown of where submissions originate (organic, direct, referral, etc.). Guides marketing and content strategy.', Config::TEXTDOMAIN); ?></p>
                </div>
                <div class="cin-help-item">
                    <h4><?php esc_html_e('Browser Distribution', Config::TEXTDOMAIN); ?></h4>
                    <p><?php esc_html_e('Shows which browsers users employ. Important for testing and identifying browser-specific issues.', Config::TEXTDOMAIN); ?></p>
                </div>
            </div>

            <!-- Background Jobs -->
            <div id="analytics-help-cron" class="cin-help-section">
                <h3><?php esc_html_e('⚙️ Background Jobs & Cron Health', Config::TEXTDOMAIN); ?></h3>
                <div class="cin-help-item">
                    <h4><?php esc_html_e('Overall Health Score', Config::TEXTDOMAIN); ?></h4>
                    <p><?php esc_html_e('Composite score based on job execution success, latency, and error rates. Healthy systems show 95%+ success.', Config::TEXTDOMAIN); ?></p>
                </div>
                <div class="cin-help-item">
                    <h4><?php esc_html_e('Core Background Jobs', Config::TEXTDOMAIN); ?></h4>
                    <ul>
                        <li><strong><?php esc_html_e('Email Queue Processor', Config::TEXTDOMAIN); ?>:</strong> <?php esc_html_e('Handles email deliveries every few minutes.', Config::TEXTDOMAIN); ?></li>
                        <li><strong><?php esc_html_e('CRM Queue Processor', Config::TEXTDOMAIN); ?>:</strong> <?php esc_html_e('Handles CRM record syncs and attachment uploads every few minutes.', Config::TEXTDOMAIN); ?></li>
                        <li><strong><?php esc_html_e('Cleanup/Maintenance', Config::TEXTDOMAIN); ?>:</strong> <?php esc_html_e('Runs daily to purge old logs and temp files.', Config::TEXTDOMAIN); ?></li>
                        <li><strong><?php esc_html_e('GDPR Expiry Check', Config::TEXTDOMAIN); ?>:</strong> <?php esc_html_e('Automated data retention enforcement.', Config::TEXTDOMAIN); ?></li>
                        <li><strong><?php esc_html_e('Analytics Aggregation', Config::TEXTDOMAIN); ?>:</strong> <?php esc_html_e('Rolls up daily stats for reporting.', Config::TEXTDOMAIN); ?></li>
                    </ul>
                </div>
                <div class="cin-help-item">
                    <h4><?php esc_html_e('Cron Scheduling Architecture', Config::TEXTDOMAIN); ?></h4>
                    <ul>
                        <li><?php esc_html_e('All recurring cron schedules are created once during plugin activation.', Config::TEXTDOMAIN); ?></li>
                        <li><?php esc_html_e('No runtime scheduling occurs to prevent duplicate schedules.', Config::TEXTDOMAIN); ?></li>
                        <li><?php esc_html_e('Manual processing operations trigger single-event execution without affecting recurring schedules.', Config::TEXTDOMAIN); ?></li>
                        <li><?php esc_html_e('Use the Maintenance page to reschedule or manually trigger processing as needed.', Config::TEXTDOMAIN); ?></li>
                    </ul>
                </div>
                <div class="cin-help-item">
                    <h4><?php esc_html_e('Monitoring Job Runs', Config::TEXTDOMAIN); ?></h4>
                    <ul>
                        <li><strong><?php esc_html_e('Last Run', Config::TEXTDOMAIN); ?>:</strong> <?php esc_html_e('Timestamp of the most recent execution.', Config::TEXTDOMAIN); ?></li>
                        <li><strong><?php esc_html_e('Next Run', Config::TEXTDOMAIN); ?>:</strong> <?php esc_html_e('Scheduled time for the next execution.', Config::TEXTDOMAIN); ?></li>
                        <li><strong><?php esc_html_e('Failures (24h)', Config::TEXTDOMAIN); ?>:</strong> <?php esc_html_e('Count of failed attempts in the past day.', Config::TEXTDOMAIN); ?></li>
                        <li><strong><?php esc_html_e('Duration', Config::TEXTDOMAIN); ?>:</strong> <?php esc_html_e('How long the last execution took.', Config::TEXTDOMAIN); ?></li>
                    </ul>
                </div>
            </div>

            <!-- Troubleshooting -->
            <div id="analytics-help-troubleshoot" class="cin-help-section">
                <h3><?php esc_html_e('🧯 Troubleshooting Tips', Config::TEXTDOMAIN); ?></h3>
                <div class="cin-help-item">
                    <h4><?php esc_html_e('High Email Failure Rate', Config::TEXTDOMAIN); ?></h4>
                    <ul>
                        <li><?php esc_html_e('Check SMTP settings and credentials.', Config::TEXTDOMAIN); ?></li>
                        <li><?php esc_html_e('Verify domain SPF/DKIM records are configured.', Config::TEXTDOMAIN); ?></li>
                        <li><?php esc_html_e('Check ISP rate limits and bounce feedback loops.', Config::TEXTDOMAIN); ?></li>
                    </ul>
                </div>
                <div class="cin-help-item">
                    <h4><?php esc_html_e('CRM Sync Failures', Config::TEXTDOMAIN); ?></h4>
                    <ul>
                        <li><?php esc_html_e('Verify OAuth token is still valid and not revoked.', Config::TEXTDOMAIN); ?></li>
                        <li><?php esc_html_e('Check for API rate limits in Salesforce.', Config::TEXTDOMAIN); ?></li>
                        <li><?php esc_html_e('Ensure field mappings match current Salesforce schema.', Config::TEXTDOMAIN); ?></li>
                    </ul>
                </div>
                <div class="cin-help-item">
                    <h4><?php esc_html_e('Queue Processing Delays', Config::TEXTDOMAIN); ?></h4>
                    <ul>
                        <li><?php esc_html_e('Check if background jobs are running (cron enabled).', Config::TEXTDOMAIN); ?></li>
                        <li><?php esc_html_e('Monitor DLQ for stuck items that need manual intervention.', Config::TEXTDOMAIN); ?></li>
                        <li><?php esc_html_e('Review job duration trends for performance issues.', Config::TEXTDOMAIN); ?></li>
                    </ul>
                </div>
                <div class="cin-help-item">
                    <h4><?php esc_html_e('Spam Score Anomalies', Config::TEXTDOMAIN); ?></h4>
                    <ul>
                        <li><?php esc_html_e('Check reCAPTCHA v3 sensitivity and score thresholds.', Config::TEXTDOMAIN); ?></li>
                        <li><?php esc_html_e('Review failed validation rules and adjust if too strict.', Config::TEXTDOMAIN); ?></li>
                        <li><?php esc_html_e('Verify that bots are not gaming validation patterns.', Config::TEXTDOMAIN); ?></li>
                    </ul>
                </div>
            </div>
        </div>
        <div class="cin-modal-footer">
            <p><?php esc_html_e('For further assistance, consult the full documentation or contact support.', Config::TEXTDOMAIN); ?></p>
        </div>
    </div>
</div>
