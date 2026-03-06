<?php
// phpcs:disable WordPress.Security.EscapeOutput.OutputNotEscaped, WordPress.WP.I18n.MissingTranslatorsComment, WordPress.WP.I18n.UnorderedPlaceholdersText, WordPress.WP.I18n.NonSingularStringLiteralText
/**
 * Admin Dashboard Widget – Queue Status & Management
 *
 * Displays queue statistics, health status, and provides quick actions
 * Shows real-time queue metrics: pending, processing, retry, completed, DLQ
 *
 * @package ContactInbox
 */

namespace ContactInbox\Admin;

use ContactInbox\Core\Config;
use ContactInbox\Core\Logger;
use ContactInbox\Core\CircuitBreaker;
use ContactInbox\Core\QueueManager;
use ContactInbox\Core\Repositories\QueueRepository;
use ContactInbox\Core\Repositories\MessageRepository;
use ContactInbox\Traits\Singleton;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class QueueDashboardWidget {
    use Singleton;

    private QueueRepository $queue_repo;
    private MessageRepository $message_repo;

    /**
     * Initialize dashboard widget
     */
    protected function __construct() {
        $this->queue_repo = new QueueRepository();
        $this->message_repo = new MessageRepository();
        add_action('wp_dashboard_setup', [$this, 'register_widget']);
        // Legacy handlers (kept for backward compatibility)
        add_action('wp_ajax_contactin_clear_completed_queue', [$this, 'handle_clear_completed']);
        add_action('wp_ajax_contactin_clear_dlq', [$this, 'handle_clear_dlq']);
        add_action('wp_ajax_contactin_retry_dlq_item', [$this, 'handle_retry_dlq_item']);
        add_action('wp_ajax_contactin_skip_email_queue', [$this, 'handle_skip_email_queue']);
        // Phase 3: New message-centric handlers
        add_action('wp_ajax_contactin_retry_failed_emails', [$this, 'handle_retry_failed_emails']);
        add_action('wp_ajax_contactin_retry_failed_crm', [$this, 'handle_retry_failed_crm']);
        add_action('wp_ajax_contactin_run_queue_now', [$this, 'handle_run_queue_now']);
        add_action('wp_ajax_contactin_reset_circuits', [$this, 'handle_reset_circuits']);
    }

    /**
     * Register dashboard widget
     */
    public function register_widget(): void {
        wp_add_dashboard_widget(
            'contactin_queue_widget',
            __('Contact Inbox Pro - Task Processing Queue', 'contact-inbox'),
            [$this, 'render_widget']
        );
    }

    /**
     * Render queue dashboard widget
     */
    public function render_widget(): void {
        // Check if user has permission to manage the plugin
        if (!current_user_can('manage_options')) {
            echo '<p>' . esc_html__('You do not have permission to view this information.', 'contact-inbox') . '</p>';
            return;
        }

        try {
            $stats = $this->message_repo->get_status_counts();
            $this->render_statistics($stats);
            $this->render_status_indicator($stats);
            $this->render_quick_actions($stats);
            $this->render_recent_items($stats);
        } catch (\Throwable $e) {
            Logger::error('Queue widget rendering error', [
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);
            echo '<div class="notice notice-error"><p>';
            echo esc_html__('Error loading queue statistics: ', 'contact-inbox');
            echo esc_html($e->getMessage());
            echo '</p></div>';
        }
    }

    /**
     * Render queue statistics table
     *
     * @param array $stats Queue statistics
     */
    private function render_statistics(array $stats): void {
        $total_pending = ($stats['admin_email_pending'] ?? 0) + ($stats['user_email_pending'] ?? 0) + ($stats['crm_pending'] ?? 0);
        $total_failed = ($stats['admin_email_failed'] ?? 0) + ($stats['user_email_failed'] ?? 0) + ($stats['crm_failed'] ?? 0);
        ?>
        <div class="contactin-queue-stats">
            <h3><?php esc_html_e('Message Processing Status', 'contact-inbox'); ?></h3>
            <p style="margin: 5px 0 15px 0; font-size: 13px; color: #666;">
                <?php esc_html_e('Real-time status of email notifications and CRM syncs', 'contact-inbox'); ?>
            </p>
            <table class="widefat striped">
                <tbody>
                    <tr>
                        <td><strong><?php esc_html_e('Admin Emails Pending', 'contact-inbox'); ?></strong></td>
                        <td style="text-align: right; font-weight: bold; color: #0073aa;">
                            <?php echo intval($stats['admin_email_pending'] ?? 0); ?>
                        </td>
                    </tr>
                    <tr>
                        <td><strong><?php esc_html_e('User Emails Pending', 'contact-inbox'); ?></strong></td>
                        <td style="text-align: right; font-weight: bold; color: #0073aa;">
                            <?php echo intval($stats['user_email_pending'] ?? 0); ?>
                        </td>
                    </tr>
                    <tr>
                        <td><strong><?php esc_html_e('CRM Syncs Pending', 'contact-inbox'); ?></strong></td>
                        <td style="text-align: right; font-weight: bold; color: #0073aa;">
                            <?php echo intval($stats['crm_pending'] ?? 0); ?>
                        </td>
                    </tr>
                    <tr style="border-top: 2px solid #ddd;">
                        <td><strong><?php esc_html_e('Total Pending', 'contact-inbox'); ?></strong></td>
                        <td style="text-align: right; font-weight: bold; color: #0073aa;">
                            <?php echo intval($total_pending); ?>
                        </td>
                    </tr>
                    <tr>
                        <td><strong><?php esc_html_e('Failed Items', 'contact-inbox'); ?></strong></td>
                        <td style="text-align: right; font-weight: bold; color: #dc3545;">
                            <?php echo intval($total_failed); ?>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
        <?php
    }

    /**
     * Render queue health status indicator
     *
     * @param array $stats Queue statistics
     */
    private function render_status_indicator(array $stats): void {
        $total_pending = ($stats['admin_email_pending'] ?? 0) + ($stats['user_email_pending'] ?? 0) + ($stats['crm_pending'] ?? 0);
        $total_failed = ($stats['admin_email_failed'] ?? 0) + ($stats['user_email_failed'] ?? 0) + ($stats['crm_failed'] ?? 0);

        // Determine health status based on failed items
        if ($total_failed > 50) {
            $status = 'critical';
            $color = '#dc3545';
            $label = __('Critical: High Failure Count', 'contact-inbox');
            $icon = '⚠️';
        } elseif ($total_failed > 10) {
            $status = 'warning';
            $color = '#ff9800';
            $label = __('Warning: Failed Items Need Attention', 'contact-inbox');
            $icon = '⚡';
        } elseif ($total_pending > 100) {
            $status = 'caution';
            $color = '#ffc107';
            $label = __('Caution: High Pending Volume', 'contact-inbox');
            $icon = '●';
        } else {
            $status = 'healthy';
            $color = '#28a745';
            $label = __('Healthy: Processing Normally', 'contact-inbox');
            $icon = '✓';
        }

        ?>
        <div style="margin-top: 15px; padding: 10px; background: #f5f5f5; border-left: 4px solid <?php echo esc_attr($color); ?>;">
            <p style="margin: 0;">
                <strong style="color: <?php echo esc_attr($color); ?>;"><?php echo esc_html($icon . ' ' . $label); ?></strong>
            </p>
            <small style="color: #666;">
                <?php
                if ($status === 'healthy') {
                    echo esc_html__('All messages are processing normally. No action required.', 'contact-inbox');
                } elseif ($status === 'caution') {
                    /* translators: %d: number of pending queue messages */
                    printf(
                        esc_html__('%d messages pending processing. Monitor performance.', 'contact-inbox'),
                        (int) $total_pending
                    );
                } elseif ($status === 'warning') {
                    /* translators: 1: number of failed queue messages, 2: inbox link */
                    printf(
                        wp_kses_post( __('%1$d messages failed. Review %2$s for details.', 'contact-inbox') ),
                        (int) $total_failed,
                        '<a href="' . esc_url(admin_url('admin.php?page=contact_inbox_pro_inbox')) . '">' . esc_html__('Inbox', 'contact-inbox') . '</a>'
                    );
                } elseif ($status === 'critical') {
                    /* translators: %d: number of failed queue messages */
                    printf(
                        esc_html__('Critical: %d messages failed. Immediate action recommended.', 'contact-inbox'),
                        (int) $total_failed
                    );
                }
                ?>
            </small>
        </div>
        <?php
    }

    /**
     * Render quick action buttons
     *
     * @param array $stats Queue statistics
     */
    private function render_quick_actions(array $stats): void {
        $completed = intval($stats['completed'] ?? 0);
        $dlq = intval($stats['dlq'] ?? 0);
        $settings = get_option(Config::OPTION_SETTINGS, []);
        $smtp_disabled = empty($settings['smtp_enable']);
        $smtp_state = CircuitBreaker::get_state('smtp');
        $can_skip_email = $smtp_disabled || $smtp_state !== CircuitBreaker::STATE_CLOSED;
        $cb_states = CircuitBreaker::get_stats();
        $has_open_cb = false;
        foreach ($cb_states as $state) {
            if (!empty($state['state']) && $state['state'] !== CircuitBreaker::STATE_CLOSED) {
                $has_open_cb = true;
                break;
            }
        }

        ?>
        <div style="margin-top: 15px;">
            <h4><?php esc_html_e('Quick Actions', 'contact-inbox'); ?></h4>
            <p>
                <?php if ($completed > 0): ?>
                    <button type="button" class="button button-small" id="contactin-clear-completed"
                            data-nonce="<?php echo esc_attr(wp_create_nonce('contactin_clear_completed')); ?>">
                        <?php printf(esc_html__('Clear Completed (%d)', 'contact-inbox'), $completed); ?>
                    </button>
                <?php endif; ?>

                <?php if ($dlq > 0): ?>
                    <button type="button" class="button button-small" id="contactin-clear-dlq"
                            data-nonce="<?php echo esc_attr(wp_create_nonce('contactin_clear_dlq')); ?>">
                        <?php printf(esc_html__('Clear DLQ (%d)', 'contact-inbox'), $dlq); ?>
                    </button>
                    <button type="button" class="button button-small" id="contactin-retry-dlq"
                            data-nonce="<?php echo esc_attr(wp_create_nonce('contactin_retry_dlq')); ?>">
                        <?php printf(esc_html__('Retry All DLQ (%d)', 'contact-inbox'), $dlq); ?>
                    </button>
                <?php endif; ?>

                <?php if ($can_skip_email): ?>
                    <button type="button" class="button button-small" id="contactin-skip-email"
                            data-nonce="<?php echo esc_attr(wp_create_nonce('contactin_skip_email_queue')); ?>">
                        <?php esc_html_e('Skip Email Items (SMTP off)', 'contact-inbox'); ?>
                    </button>
                <?php endif; ?>

                <button type="button" class="button button-small" id="contactin-run-queue-now"
                        data-nonce="<?php echo esc_attr(wp_create_nonce('contactin_run_queue_now')); ?>">
                    <?php esc_html_e('Run Queue Now', 'contact-inbox'); ?>
                </button>

                <button type="button" class="button button-small" id="contactin-reset-circuits"
                        data-nonce="<?php echo esc_attr(wp_create_nonce('contactin_reset_circuits')); ?>"
                        <?php echo $has_open_cb ? '' : 'disabled'; ?>>
                    <?php esc_html_e('Reset Circuit Breakers', 'contact-inbox'); ?>
                </button>
            </p>
            <div id="contactin-action-message" style="display: none; margin-top: 10px; padding: 10px; border-radius: 3px;"></div>
        </div>        <script type="text/javascript">
            (function($) {
                $(document).ready(function() {
                    // Clear completed queue items
                    $('#contactin-clear-completed').on('click', function() {
                        if (!confirm('<?php esc_attr_e('Clear all completed queue items? This action cannot be undone.', 'contact-inbox'); ?>')) {
                            return;
                        }
                        var btn = $(this);
                        btn.prop('disabled', true).text('<?php esc_attr_e('Processing...', 'contact-inbox'); ?>');
                        var nonce = btn.data('nonce');
                        $.ajax({
                            url: ajaxurl,
                            type: 'POST',
                            data: {
                                action: 'contactin_clear_completed_queue',
                                nonce: nonce
                            },
                            success: function(response) {
                                if (response.success) {
                                    showMessage('<?php esc_attr_e('Completed items cleared successfully.', 'contact-inbox'); ?>', 'updated');
                                    setTimeout(function() { location.reload(); }, 1000);
                                } else {
                                    showMessage('<?php esc_attr_e('Error: ', 'contact-inbox'); ?>' + response.data, 'error');
                                    btn.prop('disabled', false).text('<?php esc_attr_e('Clear Completed', 'contact-inbox'); ?>');
                                }
                            },
                            error: function() {
                                showMessage('<?php esc_attr_e('AJAX error occurred.', 'contact-inbox'); ?>', 'error');
                                btn.prop('disabled', false).text('<?php esc_attr_e('Clear Completed', 'contact-inbox'); ?>');
                            }
                        });
                    });

                    // Clear DLQ items
                    $('#contactin-clear-dlq').on('click', function() {
                        if (!confirm('<?php esc_attr_e('Clear all Dead Letter Queue items? This action cannot be undone.', 'contact-inbox'); ?>')) {
                            return;
                        }
                        var btn = $(this);
                        btn.prop('disabled', true).text('<?php esc_attr_e('Processing...', 'contact-inbox'); ?>');
                        var nonce = btn.data('nonce');
                        $.ajax({
                            url: ajaxurl,
                            type: 'POST',
                            data: {
                                action: 'contactin_clear_dlq',
                                nonce: nonce
                            },
                            success: function(response) {
                                if (response.success) {
                                    showMessage('<?php esc_attr_e('DLQ items cleared successfully.', 'contact-inbox'); ?>', 'updated');
                                    setTimeout(function() { location.reload(); }, 1000);
                                } else {
                                    showMessage('<?php esc_attr_e('Error: ', 'contact-inbox'); ?>' + response.data, 'error');
                                    btn.prop('disabled', false).text('<?php esc_attr_e('Clear DLQ', 'contact-inbox'); ?>');
                                }
                            },
                            error: function() {
                                showMessage('<?php esc_attr_e('AJAX error occurred.', 'contact-inbox'); ?>', 'error');
                                btn.prop('disabled', false).text('<?php esc_attr_e('Clear DLQ', 'contact-inbox'); ?>');
                            }
                        });
                    });

                    // Retry all DLQ items
                    $('#contactin-retry-dlq').on('click', function() {
                        if (!confirm('<?php esc_attr_e('Retry all Dead Letter Queue items? They will be moved back to pending queue.', 'contact-inbox'); ?>')) {
                            return;
                        }
                        var btn = $(this);
                        btn.prop('disabled', true).text('<?php esc_attr_e('Processing...', 'contact-inbox'); ?>');
                        var nonce = btn.data('nonce');
                        $.ajax({
                            url: ajaxurl,
                            type: 'POST',
                            data: {
                                action: 'contactin_retry_dlq_item',
                                nonce: nonce
                            },
                            success: function(response) {
                                if (response.success) {
                                    showMessage('<?php esc_attr_e('DLQ items queued for retry.', 'contact-inbox'); ?>', 'updated');
                                    setTimeout(function() { location.reload(); }, 1000);
                                } else {
                                    showMessage('<?php esc_attr_e('Error: ', 'contact-inbox'); ?>' + response.data, 'error');
                                    btn.prop('disabled', false).text('<?php esc_attr_e('Retry All DLQ', 'contact-inbox'); ?>');
                                }
                            },
                            error: function() {
                                showMessage('<?php esc_attr_e('AJAX error occurred.', 'contact-inbox'); ?>', 'error');
                                btn.prop('disabled', false).text('<?php esc_attr_e('Retry All DLQ', 'contact-inbox'); ?>');
                            }
                        });
                    });

                    // Skip/complete email items when SMTP is disabled
                    $('#contactin-skip-email').on('click', function() {
                        if (!confirm('<?php esc_attr_e('Mark all email queue items as completed because SMTP is disabled? This will also clear email DLQ items.', 'contact-inbox'); ?>')) {
                            return;
                        }
                        var btn = $(this);
                        btn.prop('disabled', true).text('<?php esc_attr_e('Processing...', 'contact-inbox'); ?>');
                        var nonce = btn.data('nonce');
                        $.ajax({
                            url: ajaxurl,
                            type: 'POST',
                            data: {
                                action: 'contactin_skip_email_queue',
                                nonce: nonce
                            },
                            success: function(response) {
                                if (response.success) {
                                    var data = response.data || {};
                                    var msg = '<?php esc_attr_e('Email queue items skipped because SMTP is disabled.', 'contact-inbox'); ?>';
                                    if (typeof data.updated !== 'undefined') {
                                        msg += ' ' + '<?php esc_attr_e('Updated:', 'contact-inbox'); ?>' + ' ' + data.updated;
                                    }
                                    if (typeof data.dlq_cleared !== 'undefined') {
                                        msg += ' ' + '<?php esc_attr_e('DLQ cleared:', 'contact-inbox'); ?>' + ' ' + data.dlq_cleared;
                                    }
                                    showMessage(msg, 'updated');
                                    setTimeout(function() { location.reload(); }, 1000);
                                } else {
                                    showMessage('<?php esc_attr_e('Error: ', 'contact-inbox'); ?>' + response.data, 'error');
                                    btn.prop('disabled', false).text('<?php esc_attr_e('Skip Email Items (SMTP off)', 'contact-inbox'); ?>');
                                }
                            },
                            error: function() {
                                showMessage('<?php esc_attr_e('AJAX error occurred.', 'contact-inbox'); ?>', 'error');
                                btn.prop('disabled', false).text('<?php esc_attr_e('Skip Email Items (SMTP off)', 'contact-inbox'); ?>');
                            }
                        });
                    });

                    // Run queue processor immediately
                    $('#contactin-run-queue-now').on('click', function() {
                        var btn = $(this);
                        btn.prop('disabled', true).text('<?php esc_attr_e('Processing...', 'contact-inbox'); ?>');
                        var nonce = btn.data('nonce');
                        $.ajax({
                            url: ajaxurl,
                            type: 'POST',
                            data: {
                                action: 'contactin_run_queue_now',
                                nonce: nonce
                            },
                            success: function(response) {
                                if (response.success) {
                                    showMessage('<?php esc_attr_e('Queue run scheduled now.', 'contact-inbox'); ?>', 'updated');
                                    setTimeout(function() { location.reload(); }, 800);
                                } else {
                                    showMessage('<?php esc_attr_e('Error: ', 'contact-inbox'); ?>' + response.data, 'error');
                                    btn.prop('disabled', false).text('<?php esc_attr_e('Run Queue Now', 'contact-inbox'); ?>');
                                }
                            },
                            error: function() {
                                showMessage('<?php esc_attr_e('AJAX error occurred.', 'contact-inbox'); ?>', 'error');
                                btn.prop('disabled', false).text('<?php esc_attr_e('Run Queue Now', 'contact-inbox'); ?>');
                            }
                        });
                    });

                    // Reset circuit breakers (smtp/crm/webhook)
                    $('#contactin-reset-circuits').on('click', function() {
                        if (!confirm('<?php esc_attr_e('Reset circuit breakers for SMTP/CRM/Webhook?', 'contact-inbox'); ?>')) {
                            return;
                        }
                        var btn = $(this);
                        btn.prop('disabled', true).text('<?php esc_attr_e('Processing...', 'contact-inbox'); ?>');
                        var nonce = btn.data('nonce');
                        $.ajax({
                            url: ajaxurl,
                            type: 'POST',
                            data: {
                                action: 'contactin_reset_circuits',
                                nonce: nonce
                            },
                            success: function(response) {
                                if (response.success) {
                                    showMessage('<?php esc_attr_e('Circuit breakers reset.', 'contact-inbox'); ?>', 'updated');
                                    setTimeout(function() { location.reload(); }, 800);
                                } else {
                                    showMessage('<?php esc_attr_e('Error: ', 'contact-inbox'); ?>' + response.data, 'error');
                                    btn.prop('disabled', false).text('<?php esc_attr_e('Reset Circuit Breakers', 'contact-inbox'); ?>');
                                }
                            },
                            error: function() {
                                showMessage('<?php esc_attr_e('AJAX error occurred.', 'contact-inbox'); ?>', 'error');
                                btn.prop('disabled', false).text('<?php esc_attr_e('Reset Circuit Breakers', 'contact-inbox'); ?>');
                            }
                        });
                    });

                    function showMessage(message, type) {
                        var messageDiv = $('#contactin-action-message');
                        messageDiv
                            .removeClass('updated error')
                            .addClass(type)
                            .html('<p>' + message + '</p>')
                            .show();
                    }
                });
            })(jQuery);
        </script>
        <?php
    }

    /**
     * Render recent queue items preview
     *
     * @param array $stats Queue statistics
     */
    private function render_recent_items(array $stats): void {
        try {
            // Get recent pending items
            $pending_items = QueueManager::get_items('pending', 5);
            $retry_items = QueueManager::get_items('retry', 3);

            if (!empty($pending_items) || !empty($retry_items)) {
                ?>
                <div style="margin-top: 15px;">
                    <h4><?php esc_html_e('Recent Activity', 'contact-inbox'); ?></h4>
                    
                    <?php if (!empty($pending_items)): ?>
                        <div style="margin-bottom: 10px;">
                            <p><strong><?php esc_html_e('Pending Items:', 'contact-inbox'); ?></strong></p>
                            <table class="widefat striped" style="font-size: 12px;">
                                <thead>
                                    <tr>
                                        <th><?php esc_html_e('Type', 'contact-inbox'); ?></th>
                                        <th><?php esc_html_e('Message ID', 'contact-inbox'); ?></th>
                                        <th><?php esc_html_e('Created', 'contact-inbox'); ?></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($pending_items as $item): ?>
                                        <tr>
                                            <td><?php echo esc_html($item['type'] ?? ''); ?></td>
                                            <td><code style="font-size: 10px;"><?php echo esc_html(substr($item['message_id'] ?? '', 0, 8)); ?>...</code></td>
                                            <td><?php echo esc_html($this->format_time($item['created_at'] ?? '')); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>

                    <?php if (!empty($retry_items)): ?>
                        <div>
                            <p><strong><?php esc_html_e('Retry Items:', 'contact-inbox'); ?></strong></p>
                            <table class="widefat striped" style="font-size: 12px;">
                                <thead>
                                    <tr>
                                        <th><?php esc_html_e('Type', 'contact-inbox'); ?></th>
                                        <th><?php esc_html_e('Attempts', 'contact-inbox'); ?></th>
                                        <th><?php esc_html_e('Next Attempt', 'contact-inbox'); ?></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($retry_items as $item): ?>
                                        <tr>
                                            <td><?php echo esc_html($item['type'] ?? ''); ?></td>
                                            <td><?php echo intval($item['retry_count'] ?? 0); ?>/4</td>
                                            <td><?php echo esc_html($this->format_time($item['next_attempt'] ?? '')); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
                <?php
            }
        } catch (\Throwable $e) {
            Logger::error('Recent items rendering error', ['error' => $e->getMessage()]);
        }
    }

    /**
     * Handle clear completed queue items AJAX request
     */
    /**
     * Handle clear completed queue items AJAX request
     */
    public function handle_clear_completed(): void {
        $this->check_ajax_permission('contactin_clear_completed');

        try {
            $cleared = QueueManager::clear_completed(0); // Clear all (days=0)
            Logger::notice('Admin cleared completed queue items', ['count' => $cleared]);
            wp_send_json_success(['count' => $cleared]);
        } catch (\Throwable $e) {
            Logger::error('Failed to clear completed items', ['error' => $e->getMessage()]);
            wp_send_json_error($e->getMessage());
        }
    }

    /**
     * Handle clear DLQ items AJAX request
     */
    public function handle_clear_dlq(): void {
        $this->check_ajax_permission('contactin_clear_dlq');

        try {
            $cleared = QueueManager::clear_dlq(0); // Clear all (days=0)
            Logger::notice('Admin cleared DLQ items', ['count' => $cleared]);
            wp_send_json_success(['count' => $cleared]);
        } catch (\Throwable $e) {
            Logger::error('Failed to clear DLQ', ['error' => $e->getMessage()]);
            wp_send_json_error($e->getMessage());
        }
    }

    /**
     * Handle retry DLQ items AJAX request
     */
    public function handle_retry_dlq_item(): void {
        $this->check_ajax_permission('contactin_retry_dlq');

        try {
            $dlq_item_ids = $this->queue_repo->get_dlq_ids_for_retry();

            $retried_count = 0;
            foreach ($dlq_item_ids as $dlq_id) {
                try {
                    QueueManager::retry_dlq_item($dlq_id);
                    $retried_count++;
                } catch (\Throwable $e) {
                    Logger::warning('Failed to retry DLQ item', [
                        'dlq_id' => $dlq_id,
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            Logger::notice('Admin retried DLQ items', ['count' => $retried_count]);
            wp_send_json_success(['count' => $retried_count]);
        } catch (\Throwable $e) {
            Logger::error('Failed to retry DLQ items', ['error' => $e->getMessage()]);
            wp_send_json_error($e->getMessage());
        }
    }

    /**
     * Format timestamp for display
     *
     * @param string $timestamp MySQL timestamp
     * @return string Formatted time (relative or absolute)
     */
    private function format_time(string $timestamp): string {
        if (empty($timestamp)) {
            return '—';
        }

        try {
            $time = strtotime($timestamp);
            $now = current_time('timestamp');
            $diff = $now - $time;

            if ($diff < 60) {
                return __('Just now', 'contact-inbox');
            } elseif ($diff < 3600) {
                $mins = ceil($diff / 60);
                /* translators: %d: number of minutes ago */
                return sprintf(_n('%d min ago', '%d mins ago', $mins, 'contact-inbox'), $mins);
            } elseif ($diff < 86400) {
                $hours = ceil($diff / 3600);
                /* translators: %d: number of hours ago */
                return sprintf(_n('%d hour ago', '%d hours ago', $hours, 'contact-inbox'), $hours);
            } else {
                return wp_date('M d, H:i', $time);
            }
        } catch (\Throwable $e) {
            return esc_html($timestamp);
        }
    }

    /**
     * Handle skipping/completing email items when SMTP is disabled
     */
    public function handle_skip_email_queue(): void {
        $this->check_ajax_permission('contactin_skip_email_queue');

        try {
            $result = QueueManager::skip_email_items_if_smtp_disabled();
            Logger::notice('Admin skipped email queue items due to SMTP disabled', $result);
            wp_send_json_success($result);
        } catch (\Throwable $e) {
            Logger::error('Failed to skip email queue items', ['error' => $e->getMessage()]);
            wp_send_json_error($e->getMessage());
        }
    }

    /**
     * Schedule immediate queue run
     */
    public function handle_run_queue_now(): void {
        $this->check_ajax_permission('contactin_run_queue_now');

        try {
            wp_schedule_single_event(time(), Config::CRON_PROCESS_EMAIL);
            wp_schedule_single_event(time(), Config::CRON_PROCESS_CRM);
            Logger::notice('Admin scheduled immediate email and CRM queue runs');
            wp_send_json_success();
        } catch (\Throwable $e) {
            Logger::error('Failed to schedule immediate queue runs', ['error' => $e->getMessage()]);
            wp_send_json_error($e->getMessage());
        }
    }

    /**
     * Reset circuit breakers (smtp/crm/webhook)
     */
    public function handle_reset_circuits(): void {
        $this->check_ajax_permission('contactin_reset_circuits');

        try {
            $services = ['smtp', 'crm', 'webhook'];
            foreach ($services as $service) {
                CircuitBreaker::reset($service);
            }
            Logger::notice('Admin reset circuit breakers', ['services' => $services]);
            wp_send_json_success(['services' => $services]);
        } catch (\Throwable $e) {
            Logger::error('Failed to reset circuit breakers', ['error' => $e->getMessage()]);
            wp_send_json_error($e->getMessage());
        }
    }

    /**
     * Retry failed email notifications (Phase 3: Message-Centric)
     */
    public function handle_retry_failed_emails(): void {
        $this->check_ajax_permission('contactin_retry_failed_emails');

        try {
            $total_reset = $this->message_repo->reset_email_failures();

            Logger::notice('Admin reset failed emails for retry', ['count' => $total_reset]);
            wp_send_json_success(['count' => $total_reset]);
        } catch (\Throwable $e) {
            Logger::error('Failed to reset failed emails', ['error' => $e->getMessage()]);
            wp_send_json_error($e->getMessage());
        }
    }

    /**
     * Retry failed CRM syncs (Phase 3: Message-Centric)
     */
    public function handle_retry_failed_crm(): void {
        $this->check_ajax_permission('contactin_retry_failed_crm');

        try {
            $total_reset = $this->message_repo->reset_crm_failures();

            Logger::notice('Admin reset failed CRM syncs for retry', ['count' => $total_reset]);
            wp_send_json_success(['count' => $total_reset]);
        } catch (\Throwable $e) {
            Logger::error('Failed to reset failed CRM syncs', ['error' => $e->getMessage()]);
            wp_send_json_error($e->getMessage());
        }
    }

    /**
     * Helper: Check AJAX permissions
     *
     * @param string $nonce_action Nonce action name
     */
    private function check_ajax_permission(string $nonce_action): void
    {
        if (!check_ajax_referer($nonce_action, 'nonce', false)) {
            wp_send_json_error(__('Nonce verification failed.', 'contact-inbox'));
        }

        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Insufficient permissions.', 'contact-inbox'));
        }
    }
}
