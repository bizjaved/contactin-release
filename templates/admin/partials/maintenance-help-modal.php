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
            <h2><?php _e('Contact Inbox - Maintenance Guide', Config::TEXTDOMAIN); ?></h2>
            <button type="button" class="cin-modal-close" aria-label="<?php _e('Close', Config::TEXTDOMAIN); ?>">
                <span aria-hidden="true">&times;</span>
            </button>
        </div>
        <div class="cin-modal-body">
            <div class="cin-help-nav">
                <h3><?php _e('Quick Navigation', Config::TEXTDOMAIN); ?></h3>
                <ul>
                    <li><a href="#maint-overview" class="cin-help-link"><?php _e('Overview', Config::TEXTDOMAIN); ?></a></li>
                    <li><a href="#maint-queue" class="cin-help-link"><?php _e('Queue Recovery', Config::TEXTDOMAIN); ?></a></li>
                    <li><a href="#maint-dlq" class="cin-help-link"><?php _e('Dead Letter Queue', Config::TEXTDOMAIN); ?></a></li>
                    <li><a href="#maint-completed" class="cin-help-link"><?php _e('Completed & Stuck', Config::TEXTDOMAIN); ?></a></li>
                    <li><a href="#maint-circuits" class="cin-help-link"><?php _e('Circuits & Email', Config::TEXTDOMAIN); ?></a></li>
                    <li><a href="#maint-attachments" class="cin-help-link"><?php _e('Attachment Cleanup', Config::TEXTDOMAIN); ?></a></li>
                    <li><a href="#maint-badges" class="cin-help-link"><?php _e('Badges & Status', Config::TEXTDOMAIN); ?></a></li>
                    <li><a href="#maint-troubleshoot" class="cin-help-link"><?php _e('Troubleshooting', Config::TEXTDOMAIN); ?></a></li>
                </ul>
            </div>

            <div id="maint-overview" class="cin-help-section">
                <h3><?php _e('🛠️ Maintenance Overview', Config::TEXTDOMAIN); ?></h3>
                <div class="cin-help-item">
                    <h4><?php _e('Why this center exists', Config::TEXTDOMAIN); ?></h4>
                    <p><?php _e('Every background job (email, CRM, webhooks, attachments) moves through the Contact Inbox queue. The Maintenance tab lets operators inspect those flows, retrigger stuck work, and keep storage lean without touching the database.', Config::TEXTDOMAIN); ?></p>
                    <ul>
                        <li><?php _e('Use it after outages, credential changes, or deployments that paused cron.', Config::TEXTDOMAIN); ?></li>
                        <li><?php _e('All actions require the Manage Options capability, so everyday editors cannot run them accidentally.', Config::TEXTDOMAIN); ?></li>
                        <li><?php _e('Manual processing operations run asynchronously with real-time progress indication—no page blocking.', Config::TEXTDOMAIN); ?></li>
                        <li><?php _e('Status badges update in real time—refresh the page after taking action to confirm counts drop.', Config::TEXTDOMAIN); ?></li>
                        <li><?php _e('All recurring cron schedules are created during plugin activation only, preventing duplicate schedules.', Config::TEXTDOMAIN); ?></li>
                    </ul>
                </div>
            </div>

            <div id="maint-queue" class="cin-help-section">
                <h3><?php _e('⚡ Queue Recovery', Config::TEXTDOMAIN); ?></h3>
                <div class="cin-help-item">
                    <h4><?php _e('Process Email/CRM Pending Now', Config::TEXTDOMAIN); ?></h4>
                    <p><?php _e('Triggers asynchronous processing of pending email or CRM items without waiting for WP-Cron. Great for clearing a surge of pending emails or CRM syncs right after you fix credentials.', Config::TEXTDOMAIN); ?></p>
                    <ul>
                        <li><?php _e('Processing runs in the background—you can continue working while it completes.', Config::TEXTDOMAIN); ?></li>
                        <li><?php _e('A progress bar appears showing real-time status with pending counts updating every 2 seconds.', Config::TEXTDOMAIN); ?></li>
                        <li><?php _e('Progress bar auto-hides 3 seconds after completion.', Config::TEXTDOMAIN); ?></li>
                        <li><?php _e('CRM processing includes both record creation and attachment uploads in the progress count.', Config::TEXTDOMAIN); ?></li>
                        <li><?php _e('If counts remain high after completion, move to Completed & Stuck → Reset Processing.', Config::TEXTDOMAIN); ?></li>
                    </ul>
                </div>
                <div class="cin-help-item">
                    <h4><?php _e('Reschedule Queue', Config::TEXTDOMAIN); ?></h4>
                    <p><?php _e('Adjusts the next run time for email or CRM queue processors. Enter the number of seconds until the next run (e.g., 120 for 2 minutes).', Config::TEXTDOMAIN); ?></p>
                    <ul>
                        <li><?php _e('All recurring cron schedules are created during plugin activation only—no runtime scheduling occurs.', Config::TEXTDOMAIN); ?></li>
                        <li><?php _e('This reschedule operation updates the existing recurring schedule without creating duplicates.', Config::TEXTDOMAIN); ?></li>
                        <li><?php _e('Manual processing triggers single-event execution and does not affect recurring schedules.', Config::TEXTDOMAIN); ?></li>
                        <li><?php _e('Next run time displays in the card status line so you can confirm the change.', Config::TEXTDOMAIN); ?></li>
                    </ul>
                </div>
            </div>

            <div id="maint-dlq" class="cin-help-section">
                <h3><?php _e('🧰 Dead Letter Queue', Config::TEXTDOMAIN); ?></h3>
                <div class="cin-help-item">
                    <h4><?php _e('Retry All DLQ', Config::TEXTDOMAIN); ?></h4>
                    <p><?php _e('Moves every failed attempt back to Pending so the queue can reprocess them with the latest credentials and circuit state.', Config::TEXTDOMAIN); ?></p>
                    <ul>
                        <li><?php _e('Run this after fixing SMTP credentials, re-authorizing CRM, or re-enabling a webhook endpoint.', Config::TEXTDOMAIN); ?></li>
                        <li><?php _e('If items fail again, check the CRM/Email logs for the exact HTTP or SMTP error.', Config::TEXTDOMAIN); ?></li>
                    </ul>
                </div>
                <div class="cin-help-item">
                    <h4><?php _e('Clear DLQ', Config::TEXTDOMAIN); ?></h4>
                    <p><?php _e('Permanently deletes failed jobs older than the number of days you specify (0 deletes everything). Helpful when bad data is no longer relevant.', Config::TEXTDOMAIN); ?></p>
                    <ul>
                        <li><?php _e('Record the DLQ count before clearing so auditors know what was removed.', Config::TEXTDOMAIN); ?></li>
                        <li><?php _e('Use a smaller window (e.g., 30 days) in regulated environments to keep forensic history.', Config::TEXTDOMAIN); ?></li>
                    </ul>
                </div>
            </div>

            <div id="maint-completed" class="cin-help-section">
                <h3><?php _e('📦 Completed & Stuck Items', Config::TEXTDOMAIN); ?></h3>
                <div class="cin-help-item">
                    <h4><?php _e('Clear Completed', Config::TEXTDOMAIN); ?></h4>
                    <p><?php _e('Removes successfully processed jobs older than the threshold you set. This keeps the queue tables slim and improves dashboard performance.', Config::TEXTDOMAIN); ?></p>
                    <ul>
                        <li><?php _e('Start with 7–14 days in production; expand temporarily when debugging.', Config::TEXTDOMAIN); ?></li>
                        <li><?php _e('Completed count in the card status line tells you whether a cleanup is overdue.', Config::TEXTDOMAIN); ?></li>
                    </ul>
                </div>
                <div class="cin-help-item">
                    <h4><?php _e('Reset Processing → Pending', Config::TEXTDOMAIN); ?></h4>
                    <p><?php _e('Detects messages stuck in the “processing” state beyond the minutes you enter and moves them back to Pending so the worker can retry.', Config::TEXTDOMAIN); ?></p>
                    <ul>
                        <li><?php _e('Use 5–10 minutes for most hosts; increase if you routinely send large attachments.', Config::TEXTDOMAIN); ?></li>
                        <li><?php _e('If the same items keep returning to Processing, investigate server CPU limits or third-party rate limits.', Config::TEXTDOMAIN); ?></li>
                    </ul>
                </div>
            </div>

            <div id="maint-circuits" class="cin-help-section">
                <h3><?php _e('🚦 Circuits & Email', Config::TEXTDOMAIN); ?></h3>
                <div class="cin-help-item">
                    <h4><?php _e('Reset Circuit Breakers', Config::TEXTDOMAIN); ?></h4>
                    <p><?php _e('Closes any open circuit breaker (SMTP, CRM, Webhook) after you have fixed upstream issues. Until you reset, the queue will pause delivery to avoid repeated failures.', Config::TEXTDOMAIN); ?></p>
                    <ul>
                        <li><?php _e('Run this after updating SMTP passwords, Salesforce tokens, or webhook endpoints.', Config::TEXTDOMAIN); ?></li>
                        <li><?php _e('Check the circuit badges at the top—Closed = healthy, Open = tripped.', Config::TEXTDOMAIN); ?></li>
                    </ul>
                </div>
                <div class="cin-help-item">
                    <h4><?php _e('Skip Email Items (SMTP Off)', Config::TEXTDOMAIN); ?></h4>
                    <p><?php _e('Marks all queued email notifications as delivered so the queue can move on while SMTP remains intentionally disabled.', Config::TEXTDOMAIN); ?></p>
                    <ul>
                        <li><?php _e('Use during maintenance windows when SMTP is blocked by your host.', Config::TEXTDOMAIN); ?></li>
                        <li><?php _e('Communicate with stakeholders first—skipped notifications are not resent later.', Config::TEXTDOMAIN); ?></li>
                    </ul>
                </div>
            </div>

            <div id="maint-attachments" class="cin-help-section">
                <h3><?php _e('🧹 Attachment Cleanup', Config::TEXTDOMAIN); ?></h3>
                <div class="cin-help-item">
                    <h4><?php _e('When to run a scan', Config::TEXTDOMAIN); ?></h4>
                    <p><?php _e('Scans the uploads directory for orphaned files—typically created when submissions are deleted or truncated by storage quotas.', Config::TEXTDOMAIN); ?></p>
                    <ul>
                        <li><?php _e('Trigger a scan monthly on busy sites or after bulk inbox cleanup.', Config::TEXTDOMAIN); ?></li>
                        <li><?php _e('Watch the Last Scan timestamp and size summary to estimate impact before deleting.', Config::TEXTDOMAIN); ?></li>
                    </ul>
                </div>
                <div class="cin-help-item">
                    <h4><?php _e('Cleaning up safely', Config::TEXTDOMAIN); ?></h4>
                    <p><?php _e('Use “Clean Up Orphaned Files” once you have reviewed the counts. The tool only deletes files no longer referenced in the database.', Config::TEXTDOMAIN); ?></p>
                    <ul>
                        <li><?php _e('If the button is disabled, no orphaned files were detected in the last scan.', Config::TEXTDOMAIN); ?></li>
                        <li><?php _e('Download a server backup before deleting large batches in environments without snapshots.', Config::TEXTDOMAIN); ?></li>
                    </ul>
                </div>
            </div>

            <div id="maint-badges" class="cin-help-section">
                <h3><?php _e('📊 Badges & Status Lines', Config::TEXTDOMAIN); ?></h3>
                <div class="cin-help-item">
                    <ul>
                        <li><?php _e('Top badges mirror live queue counts so you never guess which control to use.', Config::TEXTDOMAIN); ?></li>
                        <li><?php _e('Circuit badges list each integration (SMTP, CRM, Webhooks). Closed = delivering, Open = paused.', Config::TEXTDOMAIN); ?></li>
                        <li><?php _e('Each card surfaces the metric that action affects (e.g., DLQ count, next run time, completed totals).', Config::TEXTDOMAIN); ?></li>
                    </ul>
                </div>
            </div>

            <div id="maint-troubleshoot" class="cin-help-section">
                <h3><?php _e('🩺 Troubleshooting', Config::TEXTDOMAIN); ?></h3>
                <div class="cin-help-item">
                    <h4><?php _e('Queue never processes', Config::TEXTDOMAIN); ?></h4>
                    <ul>
                        <li><?php _e('Verify WP-Cron is enabled or use a server-side cron to hit wp-cron.php.', Config::TEXTDOMAIN); ?></li>
                        <li><?php _e('Click “Run Queue Now” and watch the Pending badge—if it drops, cron scheduling is the issue.', Config::TEXTDOMAIN); ?></li>
                    </ul>
                </div>
                <div class="cin-help-item">
                    <h4><?php _e('DLQ keeps growing', Config::TEXTDOMAIN); ?></h4>
                    <ul>
                        <li><?php _e('Inspect CRM/Email logs for the specific HTTP or SMTP status being returned.', Config::TEXTDOMAIN); ?></li>
                        <li><?php _e('Reset the related circuit breaker after fixing the upstream system, then Retry DLQ.', Config::TEXTDOMAIN); ?></li>
                    </ul>
                </div>
                <div class="cin-help-item cin-help-highlight">
                    <h4><?php _e('Cron overlaps or timeouts', Config::TEXTDOMAIN); ?></h4>
                    <ul>
                        <li><?php _e('Use “Reset Processing → Pending” with a low threshold (5 min) to free stuck jobs.', Config::TEXTDOMAIN); ?></li>
                        <li><?php _e('Reduce batch sizes via plugin filters or ensure the server PHP max execution time is at least 120 seconds.', Config::TEXTDOMAIN); ?></li>
                    </ul>
                </div>
            </div>
        </div>

    </div>
</div>
