<?php
/**
 * Template: Maintenance Help Modal
 * File: templates/admin/partials/maintenance-help-modal.php
 * Description: Comprehensive help guide for maintenance/operations
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
use ContactInbox\Core\Config;

// phpcs:disable WordPress.Security.EscapeOutput.UnsafePrintingFunction, WordPress.WP.I18n.NonSingularStringLiteralDomain
?>

<div id="contactin-maint-help-modal" class="cin-modal cin-modal-hidden" data-cin-help-modal="true">
	<div class="cin-modal-overlay"></div>
	<div class="cin-modal-content">
		<div class="cin-modal-header">
			<h2><?php esc_html_e( 'ContactIn - Maintenance Guide', 'contactin' ); ?></h2>
			<button type="button" class="cin-modal-close" aria-label="<?php esc_html_e( 'Close', 'contactin' ); ?>">
				<span aria-hidden="true">&times;</span>
			</button>
		</div>
		<div class="cin-modal-body">
			<div class="cin-help-nav">
				<h3><?php esc_html_e( 'Quick Navigation', 'contactin' ); ?></h3>
				<ul>
					<li><a href="#maint-overview" class="cin-help-link"><?php esc_html_e( 'Overview', 'contactin' ); ?></a></li>
					<li><a href="#maint-queue" class="cin-help-link"><?php esc_html_e( 'Queue Recovery', 'contactin' ); ?></a></li>
					<li><a href="#maint-dlq" class="cin-help-link"><?php esc_html_e( 'Dead Letter Queue & Failed Syncs', 'contactin' ); ?></a></li>
					<li><a href="#maint-completed" class="cin-help-link"><?php esc_html_e( 'Completed & Stuck', 'contactin' ); ?></a></li>
					<li><a href="#maint-circuits" class="cin-help-link"><?php esc_html_e( 'Circuits & Email', 'contactin' ); ?></a></li>
					<li><a href="#maint-intent" class="cin-help-link"><?php esc_html_e( 'Intent Classification', 'contactin' ); ?></a></li>
					<li><a href="#maint-gdpr" class="cin-help-link"><?php esc_html_e( 'GDPR & CRM Cleanup', 'contactin' ); ?></a></li>
					<li><a href="#maint-attachments" class="cin-help-link"><?php esc_html_e( 'Attachment Cleanup', 'contactin' ); ?></a></li>
					<li><a href="#maint-learning" class="cin-help-link"><?php esc_html_e( 'Intent Learning', 'contactin' ); ?></a></li>
					<li><a href="#maint-badges" class="cin-help-link"><?php esc_html_e( 'Badges & Status', 'contactin' ); ?></a></li>
					<li><a href="#maint-troubleshoot" class="cin-help-link"><?php esc_html_e( 'Troubleshooting', 'contactin' ); ?></a></li>
				</ul>
			</div>

			<div id="maint-overview" class="cin-help-section">
				<h3><?php esc_html_e( '🛠️ Maintenance Overview', 'contactin' ); ?></h3>
				<div class="cin-help-item">
					<h4><?php esc_html_e( 'Why this center exists', 'contactin' ); ?></h4>
					<p><?php esc_html_e( 'Every background job (email, CRM, webhooks, attachments) moves through the ContactIn queue. The Maintenance tab lets operators inspect those flows, retrigger stuck work, and keep storage lean without touching the database.', 'contactin' ); ?></p>
					<ul>
						<li><?php esc_html_e( 'Use it after outages, credential changes, or deployments that paused cron.', 'contactin' ); ?></li>
						<li><?php esc_html_e( 'All actions require the Manage Options capability, so everyday editors cannot run them accidentally.', 'contactin' ); ?></li>
						<li><?php esc_html_e( 'Manual processing operations run asynchronously with real-time progress indication—no page blocking.', 'contactin' ); ?></li>
						<li><?php esc_html_e( 'Status badges update in real time—refresh the page after taking action to confirm counts drop.', 'contactin' ); ?></li>
						<li><?php esc_html_e( 'All recurring cron schedules are created during plugin activation only, preventing duplicate schedules.', 'contactin' ); ?></li>
					</ul>
				</div>
			</div>

			<div id="maint-queue" class="cin-help-section">
				<h3><?php esc_html_e( '⚡ Queue Recovery', 'contactin' ); ?></h3>
				<div class="cin-help-item">
					<h4><?php esc_html_e( 'Process Email/CRM Pending Now', 'contactin' ); ?></h4>
					<p><?php esc_html_e( 'Triggers asynchronous processing of pending email or CRM items without waiting for WP-Cron. Great for clearing a surge of pending emails or CRM syncs right after you fix credentials.', 'contactin' ); ?></p>
					<ul>
						<li><?php esc_html_e( 'Processing runs in the background—you can continue working while it completes.', 'contactin' ); ?></li>
						<li><?php esc_html_e( 'A progress bar appears showing real-time status with pending counts updating every 2 seconds.', 'contactin' ); ?></li>
						<li><?php esc_html_e( 'Progress bar auto-hides 3 seconds after completion.', 'contactin' ); ?></li>
						<li><?php esc_html_e( 'CRM processing includes record syncs, attachment retries, and queued CRM deletions in the progress count.', 'contactin' ); ?></li>
						<li><?php esc_html_e( 'If counts remain high after completion, move to Completed & Stuck → Reset Processing.', 'contactin' ); ?></li>
					</ul>
				</div>
				<div class="cin-help-item">
					<h4><?php esc_html_e( 'Reschedule Queue', 'contactin' ); ?></h4>
					<p><?php esc_html_e( 'Adjusts the next run time for email or CRM queue processors. Enter the number of seconds until the next run (e.g., 120 for 2 minutes).', 'contactin' ); ?></p>
					<ul>
						<li><?php esc_html_e( 'All recurring cron schedules are created during plugin activation only—no runtime scheduling occurs.', 'contactin' ); ?></li>
						<li><?php esc_html_e( 'This reschedule operation updates the existing recurring schedule without creating duplicates.', 'contactin' ); ?></li>
						<li><?php esc_html_e( 'Manual processing triggers single-event execution and does not affect recurring schedules.', 'contactin' ); ?></li>
						<li><?php esc_html_e( 'Next run time displays in the card status line so you can confirm the change.', 'contactin' ); ?></li>
					</ul>
				</div>
			</div>

			<div id="maint-dlq" class="cin-help-section">
				<h3><?php esc_html_e( '🧰 Dead Letter Queue & Failed Syncs', 'contactin' ); ?></h3>
				<div class="cin-help-item">
					<h4><?php esc_html_e( 'Retry Failed CRM Syncs', 'contactin' ); ?></h4>
					<p><?php esc_html_e( 'Retries all failed CRM record syncs, attachment uploads, and deletions that are in retry or DLQ status. Items are processed immediately and the operation provides detailed before/after statistics.', 'contactin' ); ?></p>
					<ul>
						<li><?php esc_html_e( 'Run this after fixing CRM credentials, Salesforce authentication, or API issues.', 'contactin' ); ?></li>
						<li><?php esc_html_e( 'Success message shows: items retried, and status change for records/files/deletions (e.g., "↓3" = 3 fewer failures, "✓ Cleared" = all resolved).', 'contactin' ); ?></li>
						<li><?php esc_html_e( 'Last retry timestamp displays below the status line showing when the operation last ran and how many failures were resolved.', 'contactin' ); ?></li>
						<li><?php esc_html_e( 'If failure counts persist after retry, it means items failed again during processing—check CRM logs for specific API errors.', 'contactin' ); ?></li>
						<li><?php esc_html_e( '"Retry" status = pending automatic retry with exponential backoff. "DLQ" status = exhausted all retry attempts (dead letter queue).', 'contactin' ); ?></li>
					</ul>
				</div>
				<div class="cin-help-item">
					<h4><?php esc_html_e( 'Understanding Active Failures', 'contactin' ); ?></h4>
					<p><?php esc_html_e( 'The Failed CRM Syncs widget shows "Active Failures" which are items currently in retry or DLQ status. These counts represent ongoing issues, not historical records.', 'contactin' ); ?></p>
					<ul>
						<li><?php esc_html_e( 'Counts decrease when items succeed, and stay the same or increase if items fail again after retry.', 'contactin' ); ?></li>
						<li><?php esc_html_e( 'Successfully completed items are automatically cleaned up daily (see Completed & Stuck section below).', 'contactin' ); ?></li>
						<li><?php esc_html_e( 'Hover over the status text to see a tooltip explaining how retry and DLQ counts work.', 'contactin' ); ?></li>
						<li><?php esc_html_e( 'Page auto-reloads after retry operations to show updated counts—wait for reload before interpreting results.', 'contactin' ); ?></li>
					</ul>
				</div>
				<div class="cin-help-item">
					<h4><?php esc_html_e( 'Retry All DLQ', 'contactin' ); ?></h4>
					<p><?php esc_html_e( 'Moves every failed email attempt back to Pending so the queue can reprocess them with the latest credentials and circuit state.', 'contactin' ); ?></p>
					<ul>
						<li><?php esc_html_e( 'Run this after fixing SMTP credentials or email configuration issues.', 'contactin' ); ?></li>
						<li><?php esc_html_e( 'If items fail again, check the Email logs for the exact SMTP error.', 'contactin' ); ?></li>
					</ul>
				</div>
				<div class="cin-help-item">
					<h4><?php esc_html_e( 'Clear DLQ', 'contactin' ); ?></h4>
					<p><?php esc_html_e( 'Permanently deletes failed jobs older than the number of days you specify (0 deletes everything). Helpful when bad data is no longer relevant.', 'contactin' ); ?></p>
					<ul>
						<li><?php esc_html_e( 'Record the DLQ count before clearing so auditors know what was removed.', 'contactin' ); ?></li>
						<li><?php esc_html_e( 'Use a smaller window (e.g., 30 days) in regulated environments to keep forensic history.', 'contactin' ); ?></li>
						<li><?php esc_html_e( 'DLQ items older than 30 days are automatically cleaned up daily by the maintenance cron.', 'contactin' ); ?></li>
					</ul>
				</div>
			</div>

			<div id="maint-completed" class="cin-help-section">
				<h3><?php esc_html_e( '📦 Completed & Stuck Items', 'contactin' ); ?></h3>
				<div class="cin-help-item">
					<h4><?php esc_html_e( 'Automatic Daily Cleanup', 'contactin' ); ?></h4>
					<p><?php esc_html_e( 'The system automatically cleans up old queue items every 24 hours via the maintenance cron job. This keeps database tables lean without manual intervention.', 'contactin' ); ?></p>
					<ul>
						<li><?php esc_html_e( 'Completed queue items older than 7 days are automatically deleted daily.', 'contactin' ); ?></li>
						<li><?php esc_html_e( 'DLQ items older than 30 days are automatically deleted daily.', 'contactin' ); ?></li>
						<li><?php esc_html_e( 'Check the ContactIn logs to see cleanup statistics after each daily maintenance run.', 'contactin' ); ?></li>
						<li><?php esc_html_e( 'This automatic cleanup ensures retry/DLQ stats show only active failures, not historical ones.', 'contactin' ); ?></li>
					</ul>
				</div>
				<div class="cin-help-item">
					<h4><?php esc_html_e( 'Manual Clear Completed', 'contactin' ); ?></h4>
					<p><?php esc_html_e( 'Manually removes successfully processed jobs older than the threshold you set. Use this if you need immediate cleanup or want a custom retention period.', 'contactin' ); ?></p>
					<ul>
						<li><?php esc_html_e( 'Useful for debugging when you want to clear recent completions to see new activity clearly.', 'contactin' ); ?></li>
						<li><?php esc_html_e( 'Completed count in the card status line tells you how many items are eligible for cleanup.', 'contactin' ); ?></li>
						<li><?php esc_html_e( 'Automatic daily cleanup uses 7-day retention; use this for shorter or longer periods as needed.', 'contactin' ); ?></li>
					</ul>
				</div>
				<div class="cin-help-item">
					<h4><?php esc_html_e( 'Reset Processing → Pending', 'contactin' ); ?></h4>
					<p><?php esc_html_e( 'Detects messages stuck in the “processing” state beyond the minutes you enter and moves them back to Pending so the worker can retry.', 'contactin' ); ?></p>
					<ul>
						<li><?php esc_html_e( 'Use 5–10 minutes for most hosts; increase if you routinely send large attachments.', 'contactin' ); ?></li>
						<li><?php esc_html_e( 'If the same items keep returning to Processing, investigate server CPU limits or third-party rate limits.', 'contactin' ); ?></li>
					</ul>
				</div>
			</div>

			<div id="maint-circuits" class="cin-help-section">
				<h3><?php esc_html_e( '🚦 Circuits & Email', 'contactin' ); ?></h3>
				<div class="cin-help-item">
					<h4><?php esc_html_e( 'Reset Circuit Breakers', 'contactin' ); ?></h4>
					<p><?php esc_html_e( 'Closes open SMTP and CRM circuit breakers after you fix upstream issues. Until reset, related queue delivery stays paused to avoid repeated failures.', 'contactin' ); ?></p>
					<ul>
						<li><?php esc_html_e( 'Run this after updating SMTP passwords, Salesforce tokens, or webhook endpoints.', 'contactin' ); ?></li>
						<li><?php esc_html_e( 'Check the circuit badges at the top—Closed = healthy, Open = tripped.', 'contactin' ); ?></li>
					</ul>
				</div>
				<div class="cin-help-item">
					<h4><?php esc_html_e( 'Skip Email Items (SMTP Off)', 'contactin' ); ?></h4>
					<p><?php esc_html_e( 'Marks email work as handled while SMTP is intentionally disabled (legacy email statuses + unified email queue, and clears active email DLQ entries).', 'contactin' ); ?></p>
					<ul>
						<li><?php esc_html_e( 'Use during maintenance windows when SMTP is blocked by your host.', 'contactin' ); ?></li>
						<li><?php esc_html_e( 'Communicate with stakeholders first—skipped notifications are not resent later.', 'contactin' ); ?></li>
					</ul>
				</div>
			</div>

			<div id="maint-intent" class="cin-help-section">
				<h3><?php esc_html_e( '🤖 Intent Classification', 'contactin' ); ?></h3>
				<div class="cin-help-item">
					<h4><?php esc_html_e( 'Reclassify Unclassified Messages', 'contactin' ); ?></h4>
					<p><?php esc_html_e( 'Uses the machine learning classifier to automatically categorize messages that lack intent labels. This is useful after updating classification patterns or following a major inbox migration.', 'contactin' ); ?></p>
					<ul>
						<li><?php esc_html_e( 'The counter shows how many messages are currently unclassified across your inbox.', 'contactin' ); ?></li>
						<li><?php esc_html_e( 'Classification runs asynchronously—the page displays current intent distribution (e.g., Sales: 45%, Support: 30%, Other: 25%).', 'contactin' ); ?></li>
						<li><?php esc_html_e( 'Once complete, the unclassified count will decrease and messages appear with their new intent labels in the inbox.', 'contactin' ); ?></li>
						<li><?php esc_html_e( 'If you do not like the automatic classifications, use the Intent Learning widget below to improve patterns.', 'contactin' ); ?></li>
					</ul>
				</div>
			</div>

			<div id="maint-gdpr" class="cin-help-section">
				<h3><?php esc_html_e( '🔐 GDPR & CRM Cleanup', 'contactin' ); ?></h3>
				<div class="cin-help-item">
					<h4><?php esc_html_e( 'Synced Contacts Ready for Deletion', 'contactin' ); ?></h4>
					<p><?php esc_html_e( 'Shows the number of contacts previously synced to your CRM (Salesforce) that are marked for deletion per GDPR requests or bulk cleanup operations.', 'contactin' ); ?></p>
					<ul>
						<li><?php esc_html_e( 'Each contact is tracked with a full audit trail—deletion timestamps, sync status, and error logs are retained for compliance.', 'contactin' ); ?></li>
					</ul>
				</div>
				<div class="cin-help-item">
					<h4><?php esc_html_e( 'Queue for Deletion', 'contactin' ); ?></h4>
					<p><?php esc_html_e( 'Schedules synced contacts for batch processing using the background queue. Recommended for high-volume deletions to avoid blocking the admin interface.', 'contactin' ); ?></p>
					<ul>
						<li><?php esc_html_e( 'Deletions go through the unified queue system with automatic retries and exponential backoff.', 'contactin' ); ?></li>
						<li><?php esc_html_e( 'Check the CRM Delete badges (Pending, Failed, Deleted) at the top to monitor progress.', 'contactin' ); ?></li>
						<li><?php esc_html_e( 'Failed deletions will retry automatically; review CRM logs if deletions remain stuck in DLQ.', 'contactin' ); ?></li>
					</ul>
				</div>
				<div class="cin-help-item">
					<h4><?php esc_html_e( 'Delete Now (Immediate)', 'contactin' ); ?></h4>
					<p><?php esc_html_e( 'Processes deletions immediately with full sync logic and fallback handling. Use this when you need a fast resolution to a GDPR deletion request.', 'contactin' ); ?></p>
					<ul>
						<li><?php esc_html_e( 'When a CRM endpoint is unavailable, the tool attempts fallback strategies (e.g., mark inactive in Salesforce instead of hard delete).', 'contactin' ); ?></li>
						<li><?php esc_html_e( 'All deletion attempts are logged with timestamps and sync status in the GDPR audit log.', 'contactin' ); ?></li>
						<li><?php esc_html_e( 'Use the "View GDPR Log" button to inspect the full deletion history for a contact.', 'contactin' ); ?></li>
					</ul>
				</div>
			</div>

			<div id="maint-attachments" class="cin-help-section">
				<h3><?php esc_html_e( '🧹 Attachment Cleanup', 'contactin' ); ?></h3>
				<div class="cin-help-item">
					<h4><?php esc_html_e( 'When to run a scan', 'contactin' ); ?></h4>
					<p><?php esc_html_e( 'Scans the uploads directory for orphaned files—typically created when submissions are deleted or truncated by storage quotas.', 'contactin' ); ?></p>
					<ul>
						<li><?php esc_html_e( 'Trigger a scan monthly on busy sites or after bulk inbox cleanup.', 'contactin' ); ?></li>
						<li><?php esc_html_e( 'Watch the Last Scan timestamp and size summary to estimate impact before deleting.', 'contactin' ); ?></li>
					</ul>
				</div>
				<div class="cin-help-item">
					<h4><?php esc_html_e( 'Cleaning up safely', 'contactin' ); ?></h4>
					<p><?php esc_html_e( 'Use “Clean Up Orphaned Files” once you have reviewed the counts. The tool only deletes files no longer referenced in the database.', 'contactin' ); ?></p>
					<ul>
						<li><?php esc_html_e( 'If the button is disabled, no orphaned files were detected in the last scan.', 'contactin' ); ?></li>
						<li><?php esc_html_e( 'Download a server backup before deleting large batches in environments without snapshots.', 'contactin' ); ?></li>
					</ul>
				</div>
			</div>

			<div id="maint-learning" class="cin-help-section">
				<h3><?php esc_html_e( '📚 Intent Learning', 'contactin' ); ?></h3>
				<div class="cin-help-item">
					<h4><?php esc_html_e( 'What is Intent Learning?', 'contactin' ); ?></h4>
					<p><?php esc_html_e( 'The Intent Learning widget at the bottom allows you to manually train and improve the ML classifier by marking messages with the correct intent category. Over time, the model learns your business patterns and automatically classifies new messages more accurately.', 'contactin' ); ?></p>
					<ul>
						<li><?php esc_html_e( 'Select a message from your inbox and mark it with the correct intent (e.g., Sales, Support, Billing, etc.).', 'contactin' ); ?></li>
						<li><?php esc_html_e( 'The system records your feedback in the learning dataset.', 'contactin' ); ?></li>
						<li><?php esc_html_e( 'After collecting enough corrected examples, retrain the classifier using "Reclassify Unclassified Messages" to apply improvements.', 'contactin' ); ?></li>
						<li><?php esc_html_e( 'View learning history and training statistics in the widget to track model improvement.', 'contactin' ); ?></li>
					</ul>
				</div>
				<div class="cin-help-item">
					<h4><?php esc_html_e( 'Best practices for training', 'contactin' ); ?></h4>
					<ul>
						<li><?php esc_html_e( 'Start with 10-20 correct examples per intent category to establish patterns.', 'contactin' ); ?></li>
						<li><?php esc_html_e( 'Regularly review misclassified messages in your inbox and correct them via the learning widget.', 'contactin' ); ?></li>
						<li><?php esc_html_e( 'Check the learning statistics monthly to see which categories have sufficient training data.', 'contactin' ); ?></li>
						<li><?php esc_html_e( 'The more examples you provide, the more accurate automated classifications become.', 'contactin' ); ?></li>
					</ul>
				</div>
			</div>

			<div id="maint-badges" class="cin-help-section">
				<h3><?php esc_html_e( '📊 Badges & Status Lines', 'contactin' ); ?></h3>
				<div class="cin-help-item">
					<ul>
						<li><?php esc_html_e( 'Top badges mirror live queue counts so you never guess which control to use.', 'contactin' ); ?></li>
						<li><?php esc_html_e( 'CRM Record, CRM File, and CRM Delete each use a consistent summary: Pending, Synced/Deleted, Failed.', 'contactin' ); ?></li>
						<li><?php esc_html_e( 'Circuit badges list each integration (SMTP, CRM, Webhooks). Closed = delivering, Open = paused.', 'contactin' ); ?></li>
						<li><?php esc_html_e( 'Each card surfaces the metric that action affects (e.g., DLQ count, next run time, completed totals).', 'contactin' ); ?></li>
					</ul>
				</div>
			</div>

			<div id="maint-troubleshoot" class="cin-help-section">
				<h3><?php esc_html_e( '🩺 Troubleshooting', 'contactin' ); ?></h3>
				<div class="cin-help-item">
					<h4><?php esc_html_e( 'Queue never processes', 'contactin' ); ?></h4>
					<ul>
						<li><?php esc_html_e( 'Verify WP-Cron is enabled or use a server-side cron to hit wp-cron.php.', 'contactin' ); ?></li>
						<li><?php esc_html_e( 'Click “Process Email Pending Now” or “Process CRM Pending Now” and watch Pending badges—if they drop, cron scheduling is the issue.', 'contactin' ); ?></li>
					</ul>
				</div>
				<div class="cin-help-item">
					<h4><?php esc_html_e( 'DLQ or Retry counts remain high after retry', 'contactin' ); ?></h4>
					<ul>
						<li><?php esc_html_e( 'Check the retry success message for before/after stats—persistent counts mean items are failing again, not a display bug.', 'contactin' ); ?></li>
						<li><?php esc_html_e( 'Look at "Last retry" timestamp below the status line to see if the retry operation actually ran recently.', 'contactin' ); ?></li>
						<li><?php esc_html_e( 'Inspect CRM/Email logs for the specific HTTP or SMTP status code causing repeated failures.', 'contactin' ); ?></li>
						<li><?php esc_html_e( 'Reset the related circuit breaker after fixing the upstream system, then retry again.', 'contactin' ); ?></li>
						<li><?php esc_html_e( 'If the same items repeatedly fail, they may have invalid data (bad email format, missing required CRM fields, etc.).', 'contactin' ); ?></li>
					</ul>
				</div>
				<div class="cin-help-item cin-help-highlight">
					<h4><?php esc_html_e( 'Cron overlaps or timeouts', 'contactin' ); ?></h4>
					<ul>
						<li><?php esc_html_e( 'Use “Reset Processing → Pending” with a low threshold (5 min) to free stuck jobs.', 'contactin' ); ?></li>
						<li><?php esc_html_e( 'Reduce batch sizes via plugin filters or ensure the server PHP max execution time is at least 120 seconds.', 'contactin' ); ?></li>
					</ul>
				</div>                <div class="cin-help-item">
					<h4><?php esc_html_e( 'Intent classifications seem inaccurate', 'contactin' ); ?></h4>
					<ul>
						<li><?php esc_html_e( 'Check how many training examples have been collected in the Intent Learning widget.', 'contactin' ); ?></li>
						<li><?php esc_html_e( 'Provide at least 10-20 correct examples per intent to improve model accuracy.', 'contactin' ); ?></li>
						<li><?php esc_html_e( 'Mark misclassified messages as you review them to continuously train the model.', 'contactin' ); ?></li>
					</ul>
				</div>
				<div class="cin-help-item">
					<h4><?php esc_html_e( 'GDPR deletions stuck in queue or DLQ', 'contactin' ); ?></h4>
					<ul>
						<li><?php esc_html_e( 'Verify CRM credentials are still valid by testing a manual record sync.', 'contactin' ); ?></li>
						<li><?php esc_html_e( 'Review the CRM logs for specific API errors (e.g., invalid OAuth token, rate limits exceeded).', 'contactin' ); ?></li>
						<li><?php esc_html_e( 'Reset the CRM circuit breaker after fixing the issue, then retry the failed deletions.', 'contactin' ); ?></li>
						<li><?php esc_html_e( 'If immediate deletion is critical, use the "Delete Now" button and check fallback strategy results in the GDPR log.', 'contactin' ); ?></li>
					</ul>
				</div>            </div>
		</div>

	</div>
</div>
