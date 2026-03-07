
<?php
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals
use ContactInbox\Core\Config;
use ContactInbox\Admin\Helpers\UpgradeModalHelper;
/**
 * Maintenance / Operations Template
 */
if (!defined('ABSPATH')) {
    exit;
}

?>
<?php
// Enqueue admin global CSS
$css_path = CONTACTINBOX_PATH . ContactInbox\Core\Config::DIST_CSS . 'admin-global.min.css';
$css_url  = ContactInbox\Core\Config::URL . ContactInbox\Core\Config::DIST_CSS . 'admin-global.min.css';
if (file_exists($css_path)) {
    wp_enqueue_style('contactin-admin-global', $css_url, [], filemtime($css_path));
}

// Enqueue maintenance-specific CSS
$maint_css_path = CONTACTINBOX_PATH . ContactInbox\Core\Config::DIST_CSS . 'maintenance.min.css';
$maint_css_url  = ContactInbox\Core\Config::URL . ContactInbox\Core\Config::DIST_CSS . 'maintenance.min.css';
if (file_exists($maint_css_path)) {
    wp_enqueue_style('contactin-maintenance', $maint_css_url, ['contactin-admin-global'], filemtime($maint_css_path));
}

$nonce_run_email       = wp_create_nonce('contactin_maint_run_queue_email');
$nonce_run_crm         = wp_create_nonce('contactin_maint_run_queue_crm');
$nonce_retry_email     = wp_create_nonce('contactin_maint_retry_email_dlq');
$nonce_retry_crm       = wp_create_nonce('contactin_maint_retry_crm_dlq');
$nonce_reset_cb        = wp_create_nonce('contactin_maint_reset_circuits');
$nonce_skip_email      = wp_create_nonce('contactin_maint_skip_email');
$nonce_resched_email   = wp_create_nonce('contactin_maint_reschedule_email_queue');
$nonce_resched_crm     = wp_create_nonce('contactin_maint_reschedule_crm_queue');
$nonce_gdpr_queue_delete = wp_create_nonce('contactin_maint_gdpr_queue_delete');
$nonce_gdpr_immediate_delete = wp_create_nonce('contactin_maint_gdpr_immediate_delete');
$nonce_reclassify_intent = wp_create_nonce('contactin_maint_reclassify_intent');

$pending    = intval($queue_stats['pending'] ?? 0);
$processing = intval($queue_stats['processing'] ?? 0);
$retry      = intval($queue_stats['retry'] ?? 0);
$completed  = intval($queue_stats['completed'] ?? 0);
$email_reschedule_default = intval($email_reschedule_default ?? 120);
$crm_reschedule_default = intval($crm_reschedule_default ?? $email_reschedule_default);

// Get breakdown by processing type for detailed display
$admin_email_pending = intval($queue_stats_by_type['admin_email']['pending'] ?? 0);
$admin_email_sent = intval($queue_stats_by_type['admin_email']['sent'] ?? 0);
$admin_email_failed = intval($queue_stats_by_type['admin_email']['failed'] ?? 0);

$user_email_pending = intval($queue_stats_by_type['user_email']['pending'] ?? 0);
$user_email_sent = intval($queue_stats_by_type['user_email']['sent'] ?? 0);
$user_email_failed = intval($queue_stats_by_type['user_email']['failed'] ?? 0);

$crm_pending = intval($queue_stats_by_type['crm']['pending'] ?? 0);
$crm_sent = intval($queue_stats_by_type['crm']['sent'] ?? 0);
$crm_failed = intval($queue_stats_by_type['crm']['failed'] ?? 0);

$crm_queue_pending = intval($crm_queue_pending ?? 0);
$crm_queue_processing = intval($crm_queue_processing ?? 0);
$crm_queue_retry = intval($crm_queue_retry ?? 0);
$crm_queue_dlq = intval($crm_queue_dlq ?? 0);
$crm_pending_total = intval($crm_pending_total ?? $crm_pending);

$email_queue_pending = intval($email_queue_pending ?? 0);
$email_queue_processing = intval($email_queue_processing ?? 0);
$email_pending_total = intval($email_pending_total ?? ($admin_email_pending + $user_email_pending));

// Attachment and deletion stats are provided by Maintenance::render()
$file_pending = intval($file_pending ?? 0);
$file_synced = intval($file_synced ?? 0);
$file_failed = intval($file_failed ?? 0);
$attachment_retry_pending = intval($attachment_retry_pending ?? 0);
$attachment_retry_retry = intval($attachment_retry_retry ?? 0);
$attachment_retry_dlq = intval($attachment_retry_dlq ?? 0);
$email_retry = intval($email_retry ?? 0);
$email_dlq = intval($email_dlq ?? 0);
$delete_pending = intval($delete_pending ?? 0);
$delete_retry = intval($delete_retry ?? 0);
$delete_completed = intval($delete_completed ?? 0);
$delete_dlq = intval($delete_dlq ?? 0);
$crm_deleted = intval($crm_deleted ?? 0);

$email_failed_total = $admin_email_failed + $user_email_failed;

$email_failed_summary = sprintf(
    /* translators: 1: legacy failures, 2: admin failures, 3: user failures, 4: queue retry, 5: queue dlq */
    __('Email failures: %1$d (admin: %2$d, user: %3$d) · Queue retry: %4$d · Queue DLQ: %5$d', 'contact-inbox'),
    $email_failed_total,
    $admin_email_failed,
    $user_email_failed,
    $email_retry,
    $email_dlq
);

$crm_failed_summary = sprintf(
    /* translators: 1: record failures, 2: file failures */
    __('Record failures: %1$d, File failures: %2$d', 'contact-inbox'),
    $crm_failed,
    $file_failed
);

$email_completed_total = intval($email_completed_total ?? ($admin_email_sent + $user_email_sent));
$crm_completed_total = intval($crm_completed_total ?? $crm_sent);
$crm_delete_completed = intval($crm_delete_completed ?? 0);

$completed_summary = sprintf(
    /* translators: 1: email sent count, 2: record synced count, 3: file synced count, 4: CRM delete completed count, 5: CRM deleted count */
    __('Email Sent: %1$d, Records Synced: %2$d, Files Synced: %3$d, CRM Deletes Completed: %4$d, CRM Deleted: %5$d', 'contact-inbox'),
    $email_completed_total,
    $crm_completed_total,
    $file_synced,
    $crm_delete_completed,
    $crm_deleted
);

$email_processing_status = sprintf(
    /* translators: 1: pending emails, 2: legacy pending, 3: queue pending, 4: queue processing, 5: sent emails, 6: retry emails, 7: dlq emails, 8: failed emails */
    __('Pending: %1$d (legacy: %2$d, queue: %3$d, processing: %4$d) · Sent: %5$d · Retry: %6$d · DLQ: %7$d · Failed: %8$d', 'contact-inbox'),
    $email_pending_total,
    $admin_email_pending + $user_email_pending,
    $email_queue_pending,
    $email_queue_processing,
    $admin_email_sent + $user_email_sent,
    $email_retry,
    $email_dlq,
    $email_failed_total
);

$crm_processing_status = sprintf(
    /* translators: 1: record pending total, 2: legacy pending, 3: queue pending, 4: queue processing, 5: record synced, 6: record failed, 7: queue retry, 8: queue dlq, 9: file pending, 10: file synced, 11: file failed, 12: delete pending, 13: delete retry, 14: delete dlq, 15: deleted */
    __('Records - Pending: %1$d (legacy: %2$d, queue: %3$d, processing: %4$d) · Synced: %5$d · Failed: %6$d · Queue Retry: %7$d · Queue DLQ: %8$d | Files - Pending: %9$d · Synced: %10$d · Failed: %11$d | Deletions - Pending: %12$d · Retry: %13$d · DLQ: %14$d · Deleted: %15$d', 'contact-inbox'),
    $crm_pending_total,
    $crm_pending,
    $crm_queue_pending,
    $crm_queue_processing,
    $crm_sent,
    $crm_failed,
    $crm_queue_retry,
    $crm_queue_dlq,
    $file_pending,
    $file_synced,
    $file_failed,
    $delete_pending,
    $delete_retry,
    $delete_dlq,
    $crm_deleted
);

?>

<div class="wrap contactin-maint-wrap">
    <div class="cin-settings-header-wrapper">
        <h1 class="cin-settings-title">
            <?php esc_html_e('Maintenance & Operations', 'contact-inbox'); ?>
        </h1>
        <button type="button" class="button button-secondary cin-settings-help-button"
                data-cin-help-open="contactin-maint-help-modal"
                aria-haspopup="dialog"
                aria-controls="contactin-maint-help-modal">
            <span class="cin-settings-help-icon">ℹ️</span><?php esc_html_e('Help', 'contact-inbox'); ?>
        </button>
    </div>
    <p class="contactin-maint-description"><?php esc_html_e('Manage background processing, DLQ, circuits, and schedule alignment from a single control panel.', 'contact-inbox'); ?></p>

    <div class="contactin-badges">
        <div class="contactin-badge"><span class="label"><?php esc_html_e('Email Pending', 'contact-inbox'); ?></span><span class="value" title="<?php echo esc_attr(__('Includes legacy message table + unified queue pending/processing.', 'contact-inbox')); ?>"><?php echo esc_html( (string) $email_pending_total ); ?></span></div>
        <div class="contactin-badge"><span class="label"><?php esc_html_e('Email Sent', 'contact-inbox'); ?></span><span class="value"><?php echo esc_html( (string) ( $admin_email_sent + $user_email_sent ) ); ?></span></div>
        <div class="contactin-badge"><span class="label"><?php esc_html_e('Email Retry', 'contact-inbox'); ?></span><span class="value"><?php echo esc_html( (string) $email_retry ); ?></span></div>
        <div class="contactin-badge"><span class="label"><?php esc_html_e('Email DLQ', 'contact-inbox'); ?></span><span class="value"><?php echo esc_html( (string) $email_dlq ); ?></span></div>
        <div class="contactin-badge"><span class="label"><?php esc_html_e('Email Failed', 'contact-inbox'); ?></span><span class="value"><?php echo esc_html( (string) ( $admin_email_failed + $user_email_failed ) ); ?></span></div>
        <div class="contactin-badge"><span class="label"><?php esc_html_e('CRM Record Pending', 'contact-inbox'); ?></span><span class="value"><?php echo esc_html( (string) $crm_pending ); ?></span></div>
        <div class="contactin-badge"><span class="label"><?php esc_html_e('CRM Record Synced', 'contact-inbox'); ?></span><span class="value"><?php echo esc_html( (string) $crm_sent ); ?></span></div>
        <div class="contactin-badge"><span class="label"><?php esc_html_e('CRM Record Failed', 'contact-inbox'); ?></span><span class="value"><?php echo esc_html( (string) $crm_failed ); ?></span></div>
        <div class="contactin-badge"><span class="label"><?php esc_html_e('CRM Delete Pending', 'contact-inbox'); ?></span><span class="value"><?php echo esc_html( (string) $delete_pending ); ?></span></div>
        <div class="contactin-badge"><span class="label"><?php esc_html_e('CRM Delete Retry', 'contact-inbox'); ?></span><span class="value"><?php echo esc_html( (string) $delete_retry ); ?></span></div>
        <div class="contactin-badge"><span class="label"><?php esc_html_e('CRM Delete DLQ', 'contact-inbox'); ?></span><span class="value"><?php echo esc_html( (string) $delete_dlq ); ?></span></div>
        <div class="contactin-badge"><span class="label"><?php esc_html_e('CRM Deleted', 'contact-inbox'); ?></span><span class="value"><?php echo esc_html( (string) $crm_deleted ); ?></span></div>
        <div class="contactin-badge"><span class="label"><?php esc_html_e('CRM File Pending', 'contact-inbox'); ?></span><span class="value"><?php echo esc_html( (string) $file_pending ); ?></span></div>
        <div class="contactin-badge"><span class="label"><?php esc_html_e('CRM File Synced', 'contact-inbox'); ?></span><span class="value"><?php echo esc_html( (string) $file_synced ); ?></span></div>
        <div class="contactin-badge"><span class="label"><?php esc_html_e('CRM File Failed', 'contact-inbox'); ?></span><span class="value"><?php echo esc_html( (string) $file_failed ); ?></span></div>
        <?php foreach ($circuit_badges as $service => $badge_data): ?>
            <div class="contactin-badge">
                <span class="label"><?php echo esc_html(strtoupper($service)); ?></span>
                <span class="value" data-state="<?php echo esc_attr($badge_data['state'] ?: 'unknown'); ?>" title="<?php echo esc_attr($badge_data['tooltip']); ?>"><?php echo esc_html($badge_data['label']); ?></span>
            </div>
        <?php endforeach; ?>
    </div>

    <div class="contactin-maint-grid">
        <div class="contactin-card">
            <h2><?php esc_html_e('Email Processing', 'contact-inbox'); ?></h2>
            <p><?php esc_html_e('Monitor email queue processing status.', 'contact-inbox'); ?></p>
            <div class="contactin-status">
                <span class="contactin-status-label"><?php esc_html_e('Status:', 'contact-inbox'); ?></span>
                <span class="contactin-status-text" title="<?php echo esc_attr(__('Legacy pending = message table status. Queue pending/processing = unified queue items.', 'contact-inbox')); ?>"><?php echo esc_html($email_processing_status); ?></span>
            </div>
            <p class="description cin-mt-sm">
                <?php echo esc_html($next_run_email_text); ?>
            </p>
            <div class="contactin-actions">
                <button class="button button-primary js-maint-action" data-action="contactin_maint_run_queue_email" data-nonce="<?php echo esc_attr($nonce_run_email); ?>">
                    <?php esc_html_e('Process Email Pending Now', 'contact-inbox'); ?>
                </button>
                <button class="button js-maint-action" data-action="contactin_maint_reschedule_email_queue" data-nonce="<?php echo esc_attr($nonce_resched_email); ?>" data-delay-default="<?php echo esc_attr($email_reschedule_default); ?>">
                    <?php esc_html_e('Reschedule Email Queue', 'contact-inbox'); ?>
                </button>
            </div>
            <div class="contactin-progress" data-progress-scope="email" aria-live="polite">
                <div class="contactin-progress-track">
                    <span class="contactin-progress-fill"></span>
                </div>
                <div class="contactin-progress-meta">
                    <span class="contactin-progress-text"><?php esc_html_e('Idle', 'contact-inbox'); ?></span>
                    <span class="contactin-progress-count" data-progress-count></span>
                </div>
            </div>
        </div>

        <div class="contactin-card">
            <h2><?php esc_html_e('Failed Email Messages', 'contact-inbox'); ?></h2>
            <p><?php esc_html_e('Retry failed email delivery attempts (legacy statuses + queue/DLQ).', 'contact-inbox'); ?></p>
            <div class="contactin-status">
                <span class="contactin-status-label"><?php esc_html_e('Status:', 'contact-inbox'); ?></span>
                <span class="contactin-status-text"><?php echo esc_html($email_failed_summary); ?></span>
            </div>
            <div class="contactin-actions">
                <button class="button button-primary js-maint-action" data-action="contactin_maint_retry_email_dlq" data-nonce="<?php echo esc_attr($nonce_retry_email); ?>">
                    <?php esc_html_e('Retry Failed Emails', 'contact-inbox'); ?>
                </button>
            </div>
        </div>

        <div class="contactin-card">
            <h2><?php esc_html_e('Processed Messages', 'contact-inbox'); ?></h2>
            <p><?php esc_html_e('Review aggregate delivery stats for reference.', 'contact-inbox'); ?></p>
            <div class="contactin-status">
                <span class="contactin-status-label"><?php esc_html_e('Status:', 'contact-inbox'); ?></span>
                <span class="contactin-status-text"><?php echo esc_html($completed_summary); ?></span>
            </div>
            <p class="description"><?php esc_html_e('Lifecycle clean-up is automatic; no manual queue maintenance is required.', 'contact-inbox'); ?></p>
        </div>

        <div class="contactin-card <?php echo (defined('CONTACTINBOX_IS_FREE') && CONTACTINBOX_IS_FREE) ? 'contactin-card-disabled' : ''; ?>">
            <h2>
                <?php esc_html_e('CRM Sync Processing', 'contact-inbox'); ?>
                <?php if ( defined('CONTACTINBOX_IS_FREE') && CONTACTINBOX_IS_FREE ) : ?>
                    <span style="margin-left: 8px; background: #dc3545; color: white; padding: 3px 8px; border-radius: 3px; font-size: 12px; font-weight: bold;">PRO</span>
                <?php endif; ?>
            </h2>
            <p><?php esc_html_e('Queue-driven CRM record syncs (Contact/Case creation) and file uploads. Records are queued immediately at form submission. Files are queued after case creation in Salesforce.', 'contact-inbox'); ?></p>
            <div class="contactin-status">
                <span class="contactin-status-label"><?php esc_html_e('Status:', 'contact-inbox'); ?></span>
                <span class="contactin-status-text" title="<?php echo esc_attr(__('Legacy pending = message table status. Queue pending/processing = unified queue items.', 'contact-inbox')); ?>"><?php echo esc_html($crm_processing_status); ?></span>
            </div>
            <p class="description cin-mt-sm">
                <?php echo esc_html($next_run_crm_text); ?>
            </p>
            <div class="contactin-actions">
                <button class="button button-primary <?php echo (defined('CONTACTINBOX_IS_FREE') && CONTACTINBOX_IS_FREE) ? 'disabled contactinbox-show-upgrade-modal' : 'js-maint-action'; ?>" <?php echo (defined('CONTACTINBOX_IS_FREE') && CONTACTINBOX_IS_FREE) ? 'disabled aria-disabled="true" tabindex="-1"' : 'data-action="contactin_maint_run_queue_crm" data-nonce="' . esc_attr($nonce_run_crm) . '"'; ?> title="<?php echo (defined('CONTACTINBOX_IS_FREE') && CONTACTINBOX_IS_FREE) ? esc_attr__('CRM Sync Processing is available in Contact Inbox Pro', 'contact-inbox') : ''; ?>">
                    <?php esc_html_e('Process CRM Pending Now', 'contact-inbox'); ?>
                    <?php if ( defined('CONTACTINBOX_IS_FREE') && CONTACTINBOX_IS_FREE ) : ?>
                        <span style="margin-left: 4px; background: #dc3545; color: white; padding: 2px 6px; border-radius: 3px; font-size: 11px; font-weight: bold;">PRO</span>
                    <?php endif; ?>
                </button>
                <button class="button <?php echo (defined('CONTACTINBOX_IS_FREE') && CONTACTINBOX_IS_FREE) ? 'disabled contactinbox-show-upgrade-modal' : 'js-maint-action'; ?>" <?php echo (defined('CONTACTINBOX_IS_FREE') && CONTACTINBOX_IS_FREE) ? 'disabled aria-disabled="true" tabindex="-1"' : 'data-action="contactin_maint_reschedule_crm_queue" data-nonce="' . esc_attr($nonce_resched_crm) . '" data-delay-default="' . esc_attr($crm_reschedule_default) . '"'; ?> title="<?php echo (defined('CONTACTINBOX_IS_FREE') && CONTACTINBOX_IS_FREE) ? esc_attr__('Reschedule CRM Queue is available in Contact Inbox Pro', 'contact-inbox') : ''; ?>">
                    <?php esc_html_e('Reschedule CRM Queue', 'contact-inbox'); ?>
                    <?php if ( defined('CONTACTINBOX_IS_FREE') && CONTACTINBOX_IS_FREE ) : ?>
                        <span style="margin-left: 4px; background: #dc3545; color: white; padding: 2px 6px; border-radius: 3px; font-size: 11px; font-weight: bold;">PRO</span>
                    <?php endif; ?>
                </button>
            </div>
            <div class="contactin-progress" data-progress-scope="crm" aria-live="polite">
                <div class="contactin-progress-track">
                    <span class="contactin-progress-fill"></span>
                </div>
                <div class="contactin-progress-meta">
                    <span class="contactin-progress-text"><?php esc_html_e('Idle', 'contact-inbox'); ?></span>
                    <span class="contactin-progress-count" data-progress-count></span>
                </div>
            </div>
        </div>
        <div class="contactin-card <?php echo (defined('CONTACTINBOX_IS_FREE') && CONTACTINBOX_IS_FREE) ? 'contactin-card-disabled' : ''; ?>">
            <h2>
                <?php esc_html_e('Failed CRM Syncs', 'contact-inbox'); ?>
                <?php if ( defined('CONTACTINBOX_IS_FREE') && CONTACTINBOX_IS_FREE ) : ?>
                    <span style="margin-left: 8px; background: #dc3545; color: white; padding: 3px 8px; border-radius: 3px; font-size: 12px; font-weight: bold;">PRO</span>
                <?php endif; ?>
            </h2>
            <p><?php esc_html_e('Queue-based retry for failed record syncs (Contact/Case creation) and attachment uploads. Records use exponential backoff via queue. Files queued after case creation succeeds.', 'contact-inbox'); ?></p>
            <div class="contactin-status">
                <span class="contactin-status-label"><?php esc_html_e('Failures:', 'contact-inbox'); ?></span>
                <span class="contactin-status-text">
                    <?php 
                    echo esc_html(sprintf(
                        /* translators: 1: legacy record failed, 2: queue retry, 3: queue dlq, 4: file failed, 5: attachment retry pending, 6: attachment retry retry, 7: attachment retry dlq */
                        __('Records - Failed: %1$d · Queue Retry: %2$d · Queue DLQ: %3$d | Files - Failed: %4$d · Retry Pending: %5$d · Retry: %6$d · DLQ: %7$d', 'contact-inbox'),
                        $crm_failed,
                        $crm_queue_retry,
                        $crm_queue_dlq,
                        $file_failed,
                        $attachment_retry_pending,
                        $attachment_retry_retry,
                        $attachment_retry_dlq
                    ));
                    ?>
                </span>
            </div>
            <div class="contactin-actions">
                <button class="button button-primary <?php echo (defined('CONTACTINBOX_IS_FREE') && CONTACTINBOX_IS_FREE) ? 'disabled contactinbox-show-upgrade-modal' : 'js-maint-action'; ?>" <?php echo (defined('CONTACTINBOX_IS_FREE') && CONTACTINBOX_IS_FREE) ? 'disabled aria-disabled="true" tabindex="-1"' : 'data-action="contactin_maint_retry_crm_dlq" data-nonce="' . esc_attr($nonce_retry_crm) . '"'; ?> title="<?php echo (defined('CONTACTINBOX_IS_FREE') && CONTACTINBOX_IS_FREE) ? esc_attr__('Retry Failed CRM Syncs is available in Contact Inbox Pro', 'contact-inbox') : ''; ?>">
                    <?php esc_html_e('Retry Failed CRM Syncs', 'contact-inbox'); ?>
                    <?php if ( defined('CONTACTINBOX_IS_FREE') && CONTACTINBOX_IS_FREE ) : ?>
                        <span style="margin-left: 4px; background: #dc3545; color: white; padding: 2px 6px; border-radius: 3px; font-size: 11px; font-weight: bold;">PRO</span>
                    <?php endif; ?>
                </button>
            </div>
            <p class="description cin-mt-sm">
                <?php esc_html_e('Note: Retries go through the unified queue system with automatic exponential backoff (1s, 4s, 16s, 64s). No pre-queueing of files before case exists.', 'contact-inbox'); ?>
            </p>
        </div>

        <div class="contactin-card">
            <h2><?php esc_html_e('Intent Classification', 'contact-inbox'); ?></h2>
            <p><?php esc_html_e('Reclassify unclassified messages using current classification patterns.', 'contact-inbox'); ?></p>
            <div class="contactin-status">
                <span class="contactin-status-label"><?php esc_html_e('Unclassified Messages:', 'contact-inbox'); ?></span>
                <span class="contactin-status-text"><?php echo esc_html($unclassified_count); ?></span>
            </div>
            <?php if (!empty($intent_stats)): ?>
                <div class="contactin-intent-stats-mini">
                    <?php foreach ($intent_stats as $category => $count): 
                        $label = $intent_categories[$category] ?? ucfirst($category);
                        $color = $intent_colors_map[$category] ?? 'muted';
                        $total = array_sum($intent_stats);
                        $percentage = $total > 0 ? round(($count / $total) * 100, 1) : 0;
                    ?>
                        <span class="intent-badge intent-<?php echo esc_attr($color); ?>">
                            <?php echo esc_html($label); ?>: <strong><?php echo esc_html($count); ?></strong> (<?php echo esc_html($percentage); ?>%)
                        </span>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
            <div class="contactin-actions">
                <button class="button button-primary" id="cin-reclassify-intent-btn" data-nonce="<?php echo esc_attr($nonce_reclassify_intent); ?>" <?php if ($unclassified_count === 0) echo 'disabled'; ?>>
                    <?php esc_html_e('Reclassify Unclassified Messages', 'contact-inbox'); ?>
                </button>
            </div>
            <p class="description cin-mt-sm" id="cin-reclassify-result"></p>
        </div>

        <div class="contactin-card">
            <h2><?php esc_html_e('Circuits & Email', 'contact-inbox'); ?></h2>
            <p><?php esc_html_e('Reset circuit breakers or skip email items while SMTP is disabled.', 'contact-inbox'); ?></p>
            <div class="contactin-status">
                <span class="contactin-status-label"><?php esc_html_e('Status:', 'contact-inbox'); ?></span>
                <span class="contactin-status-text"><?php echo esc_html($circuit_status_line); ?></span>
            </div>
            <div class="contactin-actions">
                <button class="button js-maint-action" data-action="contactin_maint_reset_circuits" data-nonce="<?php echo esc_attr($nonce_reset_cb); ?>">
                    <?php esc_html_e('Reset Circuit Breakers', 'contact-inbox'); ?>
                </button>
                <button class="button js-maint-action" data-action="contactin_maint_skip_email" data-nonce="<?php echo esc_attr($nonce_skip_email); ?>">
                    <?php esc_html_e('Skip Email Items (SMTP off)', 'contact-inbox'); ?>
                </button>
            </div>
        </div>
        
        <div class="contactin-card <?php echo (defined('CONTACTINBOX_IS_FREE') && CONTACTINBOX_IS_FREE) ? 'contactin-card-disabled' : ''; ?>">
            <h2>
                <?php esc_html_e('GDPR Compliance - CRM Cleanup', 'contact-inbox'); ?>
                <?php if ( defined('CONTACTINBOX_IS_FREE') && CONTACTINBOX_IS_FREE ) : ?>
                    <span style="margin-left: 8px; background: #dc3545; color: white; padding: 3px 8px; border-radius: 3px; font-size: 12px; font-weight: bold;">PRO</span>
                <?php endif; ?>
            </h2>
            <p><?php esc_html_e('Manage deletion of contacts synced to CRM. Queue for processing or delete immediately with full audit trail.', 'contact-inbox'); ?></p>
            <div class="contactin-status">
                <span class="contactin-status-label"><?php esc_html_e('Synced Contacts Ready:', 'contact-inbox'); ?></span>
                <span class="contactin-status-text contactin-gdpr-status-value">
                    <?php echo esc_html(number_format_i18n($synced_count)); ?>
                </span>
            </div>
            <?php if ($synced_count > 0): ?>
                <div class="contactin-gdpr-info-box">
                    <p>
                        📋 <strong><?php esc_html_e('Processing Options:', 'contact-inbox'); ?></strong>
                    </p>
                    <ul>
                        <li><?php esc_html_e('Queue: Schedules for batch processing (recommended)', 'contact-inbox'); ?></li>
                        <li><?php esc_html_e('Immediate: Processes now with full sync logic and fallbacks', 'contact-inbox'); ?></li>
                    </ul>
                </div>
            <?php endif; ?>
            <div class="contactin-actions">
                <button class="button <?php echo (defined('CONTACTINBOX_IS_FREE') && CONTACTINBOX_IS_FREE) ? 'disabled contactinbox-show-upgrade-modal' : (($synced_count === 0) ? 'disabled' : 'cin-gdpr-queue-delete-btn'); ?>" <?php echo (defined('CONTACTINBOX_IS_FREE') && CONTACTINBOX_IS_FREE) ? 'disabled aria-disabled="true" tabindex="-1"' : 'data-nonce="' . esc_attr($nonce_gdpr_queue_delete) . '"'; ?> <?php if (!defined('CONTACTINBOX_IS_FREE') || !CONTACTINBOX_IS_FREE) disabled($synced_count === 0); ?> title="<?php echo (defined('CONTACTINBOX_IS_FREE') && CONTACTINBOX_IS_FREE) ? esc_attr__('Queue for Deletion is available in Contact Inbox Pro', 'contact-inbox') : ''; ?>">
                    <?php esc_html_e('Queue for Deletion', 'contact-inbox'); ?>
                    <?php if ( defined('CONTACTINBOX_IS_FREE') && CONTACTINBOX_IS_FREE ) : ?>
                        <span style="margin-left: 4px; background: #dc3545; color: white; padding: 2px 6px; border-radius: 3px; font-size: 11px; font-weight: bold;">PRO</span>
                    <?php endif; ?>
                </button>
                <button class="button button-primary <?php echo (defined('CONTACTINBOX_IS_FREE') && CONTACTINBOX_IS_FREE) ? 'disabled contactinbox-show-upgrade-modal' : (($synced_count === 0) ? 'disabled' : 'cin-gdpr-immediate-delete-btn'); ?>" <?php echo (defined('CONTACTINBOX_IS_FREE') && CONTACTINBOX_IS_FREE) ? 'disabled aria-disabled="true" tabindex="-1"' : 'data-nonce="' . esc_attr($nonce_gdpr_immediate_delete) . '"'; ?> <?php if (!defined('CONTACTINBOX_IS_FREE') || !CONTACTINBOX_IS_FREE) disabled($synced_count === 0); ?> title="<?php echo (defined('CONTACTINBOX_IS_FREE') && CONTACTINBOX_IS_FREE) ? esc_attr__('Delete Now is available in Contact Inbox Pro', 'contact-inbox') : ''; ?>">
                    <?php esc_html_e('Delete Now', 'contact-inbox'); ?>
                    <?php if ( defined('CONTACTINBOX_IS_FREE') && CONTACTINBOX_IS_FREE ) : ?>
                        <span style="margin-left: 4px; background: #dc3545; color: white; padding: 2px 6px; border-radius: 3px; font-size: 11px; font-weight: bold;">PRO</span>
                    <?php endif; ?>
                </button>
                <a href="<?php echo (defined('CONTACTINBOX_IS_FREE') && CONTACTINBOX_IS_FREE) ? '#' : esc_url(admin_url('admin.php?page=' . Config::MENU_GDPR_LOG)); ?>" class="button <?php echo (defined('CONTACTINBOX_IS_FREE') && CONTACTINBOX_IS_FREE) ? 'disabled contactinbox-show-upgrade-modal' : ''; ?>" <?php echo (defined('CONTACTINBOX_IS_FREE') && CONTACTINBOX_IS_FREE) ? 'aria-disabled="true" tabindex="-1"' : ''; ?> title="<?php echo (defined('CONTACTINBOX_IS_FREE') && CONTACTINBOX_IS_FREE) ? esc_attr__('View GDPR Log is available in Contact Inbox Pro', 'contact-inbox') : ''; ?>">
                    <?php esc_html_e('View GDPR Log', 'contact-inbox'); ?>
                    <?php if ( defined('CONTACTINBOX_IS_FREE') && CONTACTINBOX_IS_FREE ) : ?>
                        <span style="margin-left: 4px; background: #dc3545; color: white; padding: 2px 6px; border-radius: 3px; font-size: 11px; font-weight: bold;">PRO</span>
                    <?php endif; ?>
                </a>
            </div>
        </div>
        
        <div class="contactin-card contactin-attachment-cleanup-card">
            <h2><?php esc_html_e('Attachment Cleanup', 'contact-inbox'); ?></h2>
            <p><?php esc_html_e('Scan for orphaned attachments—files left behind after their database records were deleted. Remove them to reclaim disk space.', 'contact-inbox'); ?></p>
            
            <!-- Diagnostics: Show discrepancies -->
            <?php if ($attachment_stale['stale_count'] > 0 || $temp_orphaned_count > 0): ?>
                <div class="notice notice-info is-dismissible contactin-diagnostics-notice">
                    <p>
                        <strong><?php esc_html_e('Attachment Diagnostics', 'contact-inbox'); ?></strong><br>
                        <?php if ($attachment_stale['stale_count'] > 0): ?>
                            <?php
                                /* translators: %d: number of stale attachment references in database */
                                printf(esc_html__('Database has %d attachment(s) referencing files that no longer exist on disk.', 'contact-inbox'), (int) $attachment_stale['stale_count']);
                            ?><br>
                        <?php endif; ?>
                        <?php if ($temp_orphaned_count > 0): ?>
                            <?php
                                /* translators: %d: number of old temporary files */
                                printf(esc_html__('Temp folder has %d old file(s) older than 24 hours (will be auto-cleaned daily).', 'contact-inbox'), (int) $temp_orphaned_count);
                            ?><br>
                        <?php endif; ?>
                    </p>
                </div>
            <?php endif; ?>
            
            <?php if ($orph_count > 0): ?>
                <div class="notice notice-warning is-dismissible contactin-diagnostics-notice">
                    <p>
                        <strong><?php esc_html_e('Orphaned attachment files detected!', 'contact-inbox'); ?></strong><br>
                        <?php
                            /* translators: 1: number of orphaned files, 2: disk space size */
                            printf(esc_html__('There are %1$d orphaned files taking up %2$s of disk space.', 'contact-inbox'), (int) $orph_count, esc_html( size_format($orph_size) ));
                        ?>
                    </p>
                </div>
            <?php endif; ?>
            <ul>
                <li><span class="label"><?php esc_html_e('Orphaned Files:', 'contact-inbox'); ?></span> <span class="value" id="cin-attach-orphaned"><?php echo esc_html($orph_count); ?></span></li>
                <li><span class="label"><?php esc_html_e('Orphaned Size:', 'contact-inbox'); ?></span> <span class="value" id="cin-attach-orphaned-size"><?php echo esc_html(number_format($orph_size_mb, 2)); ?> MB</span></li>
                <li><span class="label"><?php esc_html_e('Old Temp Files:', 'contact-inbox'); ?></span> <span class="value"><?php echo esc_html($temp_orphaned_count); ?> (auto-cleaned daily)</span></li>
                <li><span class="label"><?php esc_html_e('Stale DB Entries:', 'contact-inbox'); ?></span> <span class="value"><?php echo esc_html($attachment_stale['stale_count']); ?></span></li>
                <li><span class="label"><?php esc_html_e('Last Scan:', 'contact-inbox'); ?></span> <span class="value" id="cin-attach-last-scan"><?php echo esc_html($last_scan); ?></span></li>
            </ul>
            <div class="contactin-attachment-actions cin-flex-column-gap">
                <button class="button button-primary" id="cin-attach-delete-btn" <?php if ($orph_count === 0) echo 'disabled'; ?>><?php esc_html_e('Clean Up Orphaned Files', 'contact-inbox'); ?></button>
                <?php if ($attachment_stale['stale_count'] > 0): ?>
                    <button class="button" id="cin-attach-clean-stale-btn"><?php
                        /* translators: %d: number of stale DB entries */
                        printf(esc_html__('Clean Stale DB Entries (%d)', 'contact-inbox'), (int) $attachment_stale['stale_count']);
                    ?></button>
                <?php endif; ?>
            </div>
            <div class="contactin-attachment-summary" id="cin-attach-summary"></div>
        </div>
    </div>


    <?php load_template( CONTACTINBOX_PATH . \ContactInbox\Core\Config::TEMPLATE_ADMIN_PART . 'maintenance-help-modal.php' ); ?>
    <!-- Attachment Cleanup Card assets will be enqueued via Assets class. -->

    <div id="contactin-maint-message" class="notice"></div>
</div>
