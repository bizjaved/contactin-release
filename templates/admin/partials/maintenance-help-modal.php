<?php
/**
 * Template: Maintenance Help Modal
 * File: templates/admin/partials/maintenance-help-modal.php
 * Description: Comprehensive help guide for maintenance/operations
 */

if (!defined('ABSPATH')) exit;
use ContactInbox\Core\Config;
?>

<div id="contactin-maint-help-modal" class="cin-modal cin-modal-hidden" data-cin-help-modal="true">
    <div class="cin-modal-overlay"></div>
    <div class="cin-modal-content">
        <div class="cin-modal-header">
            <h2><?php esc_html_e('ContactIn - Maintenance Guide', 'contact-inbox'); ?></h2>
            <button type="button" class="cin-modal-close" aria-label="<?php esc_html_e('Close', 'contact-inbox'); ?>">
                <span aria-hidden="true">&times;</span>
            </button>
        </div>
        <div class="cin-modal-body">
            <div class="cin-help-nav">
                <h3><?php esc_html_e('Quick Navigation', 'contact-inbox'); ?></h3>
                <ul>
                    <li><a href="#maint-overview" class="cin-help-link"><?php esc_html_e('Overview', 'contact-inbox'); ?></a></li>
                    <li><a href="#maint-queue" class="cin-help-link"><?php esc_html_e('Queue Recovery', 'contact-inbox'); ?></a></li>
                    <li><a href="#maint-dlq" class="cin-help-link"><?php esc_html_e('Dead Letter Queue', 'contact-inbox'); ?></a></li>
                    <li><a href="#maint-completed" class="cin-help-link"><?php esc_html_e('Completed & Stuck', 'contact-inbox'); ?></a></li>
                    <li><a href="#maint-circuits" class="cin-help-link"><?php esc_html_e('Circuits & Email', 'contact-inbox'); ?></a></li>
                    <li><a href="#maint-attachments" class="cin-help-link"><?php esc_html_e('Attachment Cleanup', 'contact-inbox'); ?></a></li>
                    <li><a href="#maint-badges" class="cin-help-link"><?php esc_html_e('Badges & Status', 'contact-inbox'); ?></a></li>
                    <li><a href="#maint-troubleshoot" class="cin-help-link"><?php esc_html_e('Troubleshooting', 'contact-inbox'); ?></a></li>
                </ul>
            </div>

            <div id="maint-overview" class="cin-help-section">
                <h3><?php esc_html_e('🛠️ Maintenance Overview', 'contact-inbox'); ?></h3>
                <div class="cin-help-item">
                    <h4><?php esc_html_e('Why this center exists', 'contact-inbox'); ?></h4>
                    <p><?php esc_html_e('Every background job (email, CRM, webhooks, attachments) moves through the ContactIn queue. The Maintenance tab lets operators inspect those flows, retrigger stuck work, and keep storage lean without touching the database.', 'contact-inbox'); ?></p>
                    <ul>
                        <li><?php esc_html_e('Use it after outages, credential changes, or deployments that paused cron.', 'contact-inbox'); ?></li>
                        <li><?php esc_html_e('All actions require the Manage Options capability, so everyday editors cannot run them accidentally.', 'contact-inbox'); ?></li>
                        <li><?php esc_html_e('Manual processing operations run asynchronously with real-time progress indication—no page blocking.', 'contact-inbox'); ?></li>
                        <li><?php esc_html_e('Status badges update in real time—refresh the page after taking action to confirm counts drop.', 'contact-inbox'); ?></li>
                        <li><?php esc_html_e('All recurring cron schedules are created during plugin activation only, preventing duplicate schedules.', 'contact-inbox'); ?></li>
                    </ul>
                </div>
            </div>

            <div id="maint-queue" class="cin-help-section">
                <h3><?php esc_html_e('⚡ Queue Recovery', 'contact-inbox'); ?></h3>
                <div class="cin-help-item">
                    <h4><?php esc_html_e('Process Email/CRM Pending Now', 'contact-inbox'); ?></h4>
                    <p><?php esc_html_e('Triggers asynchronous processing of pending email or CRM items without waiting for WP-Cron. Great for clearing a surge of pending emails or CRM syncs right after you fix credentials.', 'contact-inbox'); ?></p>
                    <ul>
                        <li><?php esc_html_e('Processing runs in the background—you can continue working while it completes.', 'contact-inbox'); ?></li>
                        <li><?php esc_html_e('A progress bar appears showing real-time status with pending counts updating every 2 seconds.', 'contact-inbox'); ?></li>
                        <li><?php esc_html_e('Progress bar auto-hides 3 seconds after completion.', 'contact-inbox'); ?></li>
                        <li><?php esc_html_e('CRM processing includes both record creation and attachment uploads in the progress count.', 'contact-inbox'); ?></li>
                        <li><?php esc_html_e('If counts remain high after completion, move to Completed & Stuck → Reset Processing.', 'contact-inbox'); ?></li>
                    </ul>
                </div>
                <div class="cin-help-item">
                    <h4><?php esc_html_e('Reschedule Queue', 'contact-inbox'); ?></h4>
                    <p><?php esc_html_e('Adjusts the next run time for email or CRM queue processors. Enter the number of seconds until the next run (e.g., 120 for 2 minutes).', 'contact-inbox'); ?></p>
                    <ul>
                        <li><?php esc_html_e('All recurring cron schedules are created during plugin activation only—no runtime scheduling occurs.', 'contact-inbox'); ?></li>
                        <li><?php esc_html_e('This reschedule operation updates the existing recurring schedule without creating duplicates.', 'contact-inbox'); ?></li>
                        <li><?php esc_html_e('Manual processing triggers single-event execution and does not affect recurring schedules.', 'contact-inbox'); ?></li>
                        <li><?php esc_html_e('Next run time displays in the card status line so you can confirm the change.', 'contact-inbox'); ?></li>
                    </ul>
                </div>
            </div>

            <div id="maint-dlq" class="cin-help-section">
                <h3><?php esc_html_e('🧰 Dead Letter Queue', 'contact-inbox'); ?></h3>
                <div class="cin-help-item">
                    <h4><?php esc_html_e('Retry All DLQ', 'contact-inbox'); ?></h4>
                    <p><?php esc_html_e('Moves every failed attempt back to Pending so the queue can reprocess them with the latest credentials and circuit state.', 'contact-inbox'); ?></p>
                    <ul>
                        <li><?php esc_html_e('Run this after fixing SMTP credentials, re-authorizing CRM, or re-enabling a webhook endpoint.', 'contact-inbox'); ?></li>
                        <li><?php esc_html_e('If items fail again, check the CRM/Email logs for the exact HTTP or SMTP error.', 'contact-inbox'); ?></li>
                    </ul>
                </div>
                <div class="cin-help-item">
                    <h4><?php esc_html_e('Clear DLQ', 'contact-inbox'); ?></h4>
                    <p><?php esc_html_e('Permanently deletes failed jobs older than the number of days you specify (0 deletes everything). Helpful when bad data is no longer relevant.', 'contact-inbox'); ?></p>
                    <ul>
                        <li><?php esc_html_e('Record the DLQ count before clearing so auditors know what was removed.', 'contact-inbox'); ?></li>
                        <li><?php esc_html_e('Use a smaller window (e.g., 30 days) in regulated environments to keep forensic history.', 'contact-inbox'); ?></li>
                    </ul>
                </div>
            </div>

            <div id="maint-completed" class="cin-help-section">
                <h3><?php esc_html_e('📦 Completed & Stuck Items', 'contact-inbox'); ?></h3>
                <div class="cin-help-item">
                    <h4><?php esc_html_e('Clear Completed', 'contact-inbox'); ?></h4>
                    <p><?php esc_html_e('Removes successfully processed jobs older than the threshold you set. This keeps the queue tables slim and improves dashboard performance.', 'contact-inbox'); ?></p>
                    <ul>
                        <li><?php esc_html_e('Start with 7–14 days in production; expand temporarily when debugging.', 'contact-inbox'); ?></li>
                        <li><?php esc_html_e('Completed count in the card status line tells you whether a cleanup is overdue.', 'contact-inbox'); ?></li>
                    </ul>
                </div>
                <div class="cin-help-item">
                    <h4><?php esc_html_e('Reset Processing → Pending', 'contact-inbox'); ?></h4>
                    <p><?php esc_html_e('Detects messages stuck in the “processing” state beyond the minutes you enter and moves them back to Pending so the worker can retry.', 'contact-inbox'); ?></p>
                    <ul>
                        <li><?php esc_html_e('Use 5–10 minutes for most hosts; increase if you routinely send large attachments.', 'contact-inbox'); ?></li>
                        <li><?php esc_html_e('If the same items keep returning to Processing, investigate server CPU limits or third-party rate limits.', 'contact-inbox'); ?></li>
                    </ul>
                </div>
            </div>

            <div id="maint-circuits" class="cin-help-section">
                <h3><?php esc_html_e('🚦 Circuits & Email', 'contact-inbox'); ?></h3>
                <div class="cin-help-item">
                    <h4><?php esc_html_e('Reset Circuit Breakers', 'contact-inbox'); ?></h4>
                    <p><?php esc_html_e('Closes any open circuit breaker (SMTP, CRM, Webhook) after you have fixed upstream issues. Until you reset, the queue will pause delivery to avoid repeated failures.', 'contact-inbox'); ?></p>
                    <ul>
                        <li><?php esc_html_e('Run this after updating SMTP passwords, Salesforce tokens, or webhook endpoints.', 'contact-inbox'); ?></li>
                        <li><?php esc_html_e('Check the circuit badges at the top—Closed = healthy, Open = tripped.', 'contact-inbox'); ?></li>
                    </ul>
                </div>
                <div class="cin-help-item">
                    <h4><?php esc_html_e('Skip Email Items (SMTP Off)', 'contact-inbox'); ?></h4>
                    <p><?php esc_html_e('Marks all queued email notifications as delivered so the queue can move on while SMTP remains intentionally disabled.', 'contact-inbox'); ?></p>
                    <ul>
                        <li><?php esc_html_e('Use during maintenance windows when SMTP is blocked by your host.', 'contact-inbox'); ?></li>
                        <li><?php esc_html_e('Communicate with stakeholders first—skipped notifications are not resent later.', 'contact-inbox'); ?></li>
                    </ul>
                </div>
            </div>

            <div id="maint-attachments" class="cin-help-section">
                <h3><?php esc_html_e('🧹 Attachment Cleanup', 'contact-inbox'); ?></h3>
                <div class="cin-help-item">
                    <h4><?php esc_html_e('When to run a scan', 'contact-inbox'); ?></h4>
                    <p><?php esc_html_e('Scans the uploads directory for orphaned files—typically created when submissions are deleted or truncated by storage quotas.', 'contact-inbox'); ?></p>
                    <ul>
                        <li><?php esc_html_e('Trigger a scan monthly on busy sites or after bulk inbox cleanup.', 'contact-inbox'); ?></li>
                        <li><?php esc_html_e('Watch the Last Scan timestamp and size summary to estimate impact before deleting.', 'contact-inbox'); ?></li>
                    </ul>
                </div>
                <div class="cin-help-item">
                    <h4><?php esc_html_e('Cleaning up safely', 'contact-inbox'); ?></h4>
                    <p><?php esc_html_e('Use “Clean Up Orphaned Files” once you have reviewed the counts. The tool only deletes files no longer referenced in the database.', 'contact-inbox'); ?></p>
                    <ul>
                        <li><?php esc_html_e('If the button is disabled, no orphaned files were detected in the last scan.', 'contact-inbox'); ?></li>
                        <li><?php esc_html_e('Download a server backup before deleting large batches in environments without snapshots.', 'contact-inbox'); ?></li>
                    </ul>
                </div>
            </div>

            <div id="maint-badges" class="cin-help-section">
                <h3><?php esc_html_e('📊 Badges & Status Lines', 'contact-inbox'); ?></h3>
                <div class="cin-help-item">
                    <ul>
                        <li><?php esc_html_e('Top badges mirror live queue counts so you never guess which control to use.', 'contact-inbox'); ?></li>
                        <li><?php esc_html_e('Circuit badges list each integration (SMTP, CRM, Webhooks). Closed = delivering, Open = paused.', 'contact-inbox'); ?></li>
                        <li><?php esc_html_e('Each card surfaces the metric that action affects (e.g., DLQ count, next run time, completed totals).', 'contact-inbox'); ?></li>
                    </ul>
                </div>
            </div>

            <div id="maint-troubleshoot" class="cin-help-section">
                <h3><?php esc_html_e('🩺 Troubleshooting', 'contact-inbox'); ?></h3>
                <div class="cin-help-item">
                    <h4><?php esc_html_e('Queue never processes', 'contact-inbox'); ?></h4>
                    <ul>
                        <li><?php esc_html_e('Verify WP-Cron is enabled or use a server-side cron to hit wp-cron.php.', 'contact-inbox'); ?></li>
                        <li><?php esc_html_e('Click “Run Queue Now” and watch the Pending badge—if it drops, cron scheduling is the issue.', 'contact-inbox'); ?></li>
                    </ul>
                </div>
                <div class="cin-help-item">
                    <h4><?php esc_html_e('DLQ keeps growing', 'contact-inbox'); ?></h4>
                    <ul>
                        <li><?php esc_html_e('Inspect CRM/Email logs for the specific HTTP or SMTP status being returned.', 'contact-inbox'); ?></li>
                        <li><?php esc_html_e('Reset the related circuit breaker after fixing the upstream system, then Retry DLQ.', 'contact-inbox'); ?></li>
                    </ul>
                </div>
                <div class="cin-help-item cin-help-highlight">
                    <h4><?php esc_html_e('Cron overlaps or timeouts', 'contact-inbox'); ?></h4>
                    <ul>
                        <li><?php esc_html_e('Use “Reset Processing → Pending” with a low threshold (5 min) to free stuck jobs.', 'contact-inbox'); ?></li>
                        <li><?php esc_html_e('Reduce batch sizes via plugin filters or ensure the server PHP max execution time is at least 120 seconds.', 'contact-inbox'); ?></li>
                    </ul>
                </div>
            </div>
        </div>

    </div>
</div>
