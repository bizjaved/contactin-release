<?php
use ContactInbox\Core\Config;
/**
 * Maintenance / Operations Template
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals, WordPress.Security.EscapeOutput, WordPress.WP.I18n.NonSingularStringLiteralDomain, WordPress.WP.I18n.MissingTranslatorsComment, WordPress.Security.NonceVerification, WordPress.Security.ValidatedSanitizedInput, WordPress.WP.I18n.UnorderedPlaceholdersText, Generic.PHP.ForbiddenFunctions.Found, PluginCheck.CodeAnalysis.DiscouragedFunctions.load_plugin_textdomainFound, PluginCheck.CodeAnalysis.Heredoc.NotAllowed, PluginCheck.Security.DirectDB.UnescapedDBParameter, Squiz.PHP.DiscouragedFunctions.Discouraged, WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.NamingConventions.PrefixAllGlobals.DynamicHooknameFound, WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound, WordPress.PHP.DevelopmentFunctions.error_log_debug_backtrace, WordPress.WP.AlternativeFunctions.file_system_operations_fsockopen, WordPress.WP.AlternativeFunctions.file_system_operations_readfile, WordPress.WP.AlternativeFunctions.file_system_operations_rmdir, WordPress.WP.EnqueuedResourceParameters.MissingVersion, WordPress.WP.EnqueuedResources.NonEnqueuedScript, WordPress.WP.I18n.MissingArgDomain, WordPress.WP.I18n.UnorderedPlaceholdersPlural, WordPress.WP.I18n.UnorderedPlaceholdersSingle

?>
<?php
$css_path = CONTACTINBOX_PATH . ContactInbox\Core\Config::DIST_CSS . 'admin-global.min.css';
$css_url  = ContactInbox\Core\Config::URL . ContactInbox\Core\Config::DIST_CSS . 'admin-global.min.css';
if ( file_exists( $css_path ) ) {
	wp_enqueue_style( 'contactin-admin-global', $css_url, array(), filemtime( $css_path ) );
}

$nonce_run_email             = wp_create_nonce( 'contactin_maint_run_queue_email' );
$nonce_retry_email           = wp_create_nonce( 'contactin_maint_retry_email_dlq' );
$nonce_reset_cb              = wp_create_nonce( 'contactin_maint_reset_circuits' );
$nonce_skip_email            = wp_create_nonce( 'contactin_maint_skip_email' );
$nonce_resched_email         = wp_create_nonce( 'contactin_maint_reschedule_email_queue' );

$pending                  = intval( $queue_stats['pending'] ?? 0 );
$processing               = intval( $queue_stats['processing'] ?? 0 );
$retry                    = intval( $queue_stats['retry'] ?? 0 );
$completed                = intval( $queue_stats['completed'] ?? 0 );
$email_reschedule_default = intval( $email_reschedule_default ?? 120 );

// Get breakdown by processing type for detailed display
$admin_email_pending = intval( $queue_stats_by_type['admin_email']['pending'] ?? 0 );
$admin_email_sent    = intval( $queue_stats_by_type['admin_email']['sent'] ?? 0 );
$admin_email_failed  = intval( $queue_stats_by_type['admin_email']['failed'] ?? 0 );

$user_email_pending = intval( $queue_stats_by_type['user_email']['pending'] ?? 0 );
$user_email_sent    = intval( $queue_stats_by_type['user_email']['sent'] ?? 0 );
$user_email_failed  = intval( $queue_stats_by_type['user_email']['failed'] ?? 0 );

$crm_pending = intval( $queue_stats_by_type['crm']['pending'] ?? 0 );
$crm_sent    = intval( $queue_stats_by_type['crm']['sent'] ?? 0 );
$crm_failed  = intval( $queue_stats_by_type['crm']['failed'] ?? 0 );

$crm_queue_pending    = intval( $crm_queue_pending ?? 0 );
$crm_queue_processing = intval( $crm_queue_processing ?? 0 );
$crm_queue_retry      = intval( $crm_queue_retry ?? 0 );
$crm_queue_dlq        = intval( $crm_queue_dlq ?? 0 );
$crm_pending_total    = intval( $crm_pending_total ?? $crm_pending );

$email_queue_pending    = intval( $email_queue_pending ?? 0 );
$email_queue_processing = intval( $email_queue_processing ?? 0 );
$email_pending_total    = intval( $email_pending_total ?? ( $admin_email_pending + $user_email_pending ) );

// Attachment and deletion stats are provided by Maintenance::render()
$file_pending             = intval( $file_pending ?? 0 );
$file_synced              = intval( $file_synced ?? 0 );
$file_failed              = intval( $file_failed ?? 0 );
$attachment_retry_pending = intval( $attachment_retry_pending ?? 0 );
$attachment_retry_retry   = intval( $attachment_retry_retry ?? 0 );
$attachment_retry_dlq     = intval( $attachment_retry_dlq ?? 0 );
$email_retry              = intval( $email_retry ?? 0 );
$email_dlq                = intval( $email_dlq ?? 0 );
$delete_pending           = intval( $delete_pending ?? 0 );
$delete_retry             = intval( $delete_retry ?? 0 );
$delete_completed         = intval( $delete_completed ?? 0 );
$delete_dlq               = intval( $delete_dlq ?? 0 );
$delete_failed_total      = $delete_retry + $delete_dlq;
$crm_deleted              = intval( $crm_deleted ?? 0 );

$email_failed_total = $admin_email_failed + $user_email_failed;

$email_failed_summary = sprintf(
	/* translators: 1: legacy failures, 2: admin failures, 3: user failures, 4: queue retry, 5: queue dlq */
	__( 'Email failures (current): %1$d (admin: %2$d, user: %3$d) · Queue retry (current): %4$d · Queue DLQ (current): %5$d', 'contactin' ),
	$email_failed_total,
	$admin_email_failed,
	$user_email_failed,
	$email_retry,
	$email_dlq
);

$crm_failed_summary = sprintf(
	/* translators: 1: record failures, 2: file failures */
	__( 'Record failures (current): %1$d, File failures (7d): %2$d', 'contactin' ),
	$crm_failed,
	$file_failed
);

$email_completed_total = intval( $email_completed_total ?? ( $admin_email_sent + $user_email_sent ) );
$crm_completed_total   = intval( $crm_completed_total ?? $crm_sent );
$crm_delete_completed  = intval( $crm_delete_completed ?? 0 );

$completed_summary = sprintf(
	/* translators: 1: email sent count, 2: record synced count, 3: file synced count, 4: CRM delete completed count, 5: CRM deleted count */
	__( 'Email Sent (7d): %1$d, Records Synced (7d): %2$d, Files Synced (7d): %3$d, CRM Deletes Completed (7d): %4$d, CRM Deleted (7d): %5$d', 'contactin' ),
	$email_completed_total,
	$crm_completed_total,
	$file_synced,
	$crm_delete_completed,
	$crm_deleted
);

$email_processing_status = sprintf(
	/* translators: 1: pending emails, 2: legacy pending, 3: queue pending, 4: queue processing, 5: sent emails, 6: retry emails, 7: dlq emails, 8: failed emails */
	__( 'Pending (current): %1$d (legacy: %2$d, queue: %3$d, processing: %4$d) · Sent (7d): %5$d · Retry (current): %6$d · DLQ (current): %7$d · Failed (current): %8$d', 'contactin' ),
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
	/* translators: 1: record pending total, 2: legacy pending, 3: queue pending, 4: queue processing, 5: record synced, 6: file pending, 7: file synced, 8: delete pending, 9: deleted */
	__( 'Records - Pending (current): %1$d (legacy: %2$d, queue: %3$d, processing: %4$d) · Synced (7d): %5$d | Files - Pending (current): %6$d · Synced (7d): %7$d | Deletions - Pending (current): %8$d · Deleted (7d): %9$d', 'contactin' ),
	$crm_pending_total,
	$crm_pending,
	$crm_queue_pending,
	$crm_queue_processing,
	$crm_sent,
	$file_pending,
	$file_synced,
	$delete_pending,
	$crm_deleted
);

?>

<div class="wrap contactin-maint-wrap">
	<div class="cin-settings-header-wrapper">
		<h1 class="cin-settings-title">
			<?php esc_html_e( 'Maintenance & Operations', 'contactin' ); ?>
		</h1>
		<button type="button" class="button button-secondary cin-settings-help-button"
				data-cin-help-open="contactin-maint-help-modal"
				aria-haspopup="dialog"
				aria-controls="contactin-maint-help-modal">
			<span class="cin-settings-help-icon">ℹ️</span><?php _e( 'Help', 'contactin' ); ?>
		</button>
	</div>
	<p class="contactin-maint-description"><?php esc_html_e( 'Manage background processing, DLQ, circuits, and schedule alignment from a single control panel.', 'contactin' ); ?></p>


	<div class="contactin-badges">
		<div class="contactin-badge"><span class="label"><?php esc_html_e( 'Email Pending (Current)', 'contactin' ); ?></span><span class="value" title="<?php echo esc_attr( __( 'Includes legacy message table + unified queue pending/processing.', 'contactin' ) ); ?>"><?php echo $email_pending_total; ?></span></div>
		<div class="contactin-badge"><span class="label"><?php esc_html_e( 'Email Sent (7d)', 'contactin' ); ?></span><span class="value"><?php echo $admin_email_sent + $user_email_sent; ?></span></div>
		<div class="contactin-badge"><span class="label"><?php esc_html_e( 'Email Retry (Current)', 'contactin' ); ?></span><span class="value"><?php echo $email_retry; ?></span></div>
		<div class="contactin-badge"><span class="label"><?php esc_html_e( 'Email DLQ (Current)', 'contactin' ); ?></span><span class="value"><?php echo $email_dlq; ?></span></div>
		<div class="contactin-badge"><span class="label"><?php esc_html_e( 'Email Failed (Current)', 'contactin' ); ?></span><span class="value"><?php echo $admin_email_failed + $user_email_failed; ?></span></div>
		<div class="contactin-badge"><span class="label"><?php esc_html_e( 'CRM Record Pending (Current)', 'contactin' ); ?></span><span class="value" title="<?php echo esc_attr( __( 'Includes legacy table + queue pending/processing.', 'contactin' ) ); ?>">Nil</span></div>
		<div class="contactin-badge"><span class="label"><?php esc_html_e( 'CRM Record Synced (7d)', 'contactin' ); ?></span><span class="value">Nil</span></div>
		<div class="contactin-badge"><span class="label"><?php esc_html_e( 'CRM Record Failed (Current)', 'contactin' ); ?></span><span class="value">Nil</span></div>
		<div class="contactin-badge"><span class="label"><?php esc_html_e( 'CRM Delete Pending (Current)', 'contactin' ); ?></span><span class="value">Nil</span></div>
		<div class="contactin-badge"><span class="label"><?php esc_html_e( 'CRM Delete Failed (Current)', 'contactin' ); ?></span><span class="value" title="<?php echo esc_attr( __( 'Includes retry + DLQ deletion items.', 'contactin' ) ); ?>">Nil</span></div>
		<div class="contactin-badge"><span class="label"><?php esc_html_e( 'CRM Deleted (7d)', 'contactin' ); ?></span><span class="value">Nil</span></div>
		<div class="contactin-badge"><span class="label"><?php esc_html_e( 'CRM File Pending (Current)', 'contactin' ); ?></span><span class="value">Nil</span></div>
		<div class="contactin-badge"><span class="label"><?php esc_html_e( 'CRM File Synced (7d)', 'contactin' ); ?></span><span class="value">Nil</span></div>
		<div class="contactin-badge"><span class="label"><?php esc_html_e( 'CRM File Failed (7d)', 'contactin' ); ?></span><span class="value">Nil</span></div>
		<?php foreach ( $circuit_badges as $service => $badge_data ) : ?>
			<div class="contactin-badge">
				<span class="label"><?php echo esc_html( strtoupper( $service ) ); ?></span>
				<span class="value" data-state="<?php echo esc_attr( $badge_data['state'] ?: 'unknown' ); ?>" title="<?php echo esc_attr( $badge_data['tooltip'] ); ?>"><?php echo esc_html( $badge_data['label'] ); ?></span>
			</div>
		<?php endforeach; ?>
	</div>

	<div class="contactin-maint-grid">
		<div class="contactin-card">
			<h2><?php esc_html_e( 'Email Processing', 'contactin' ); ?></h2>
			<p><?php esc_html_e( 'Monitor email queue processing status.', 'contactin' ); ?></p>
			<div class="contactin-status">
				<span class="contactin-status-label"><?php esc_html_e( 'Status:', 'contactin' ); ?></span>
				<span class="contactin-status-text" title="<?php echo esc_attr( __( 'Legacy pending = message table status. Queue pending/processing = unified queue items.', 'contactin' ) ); ?>"><?php echo esc_html( $email_processing_status ); ?></span>
			</div>
			<p class="description cin-mt-sm">
				<?php echo esc_html( $next_run_email_text ); ?>
			</p>
			<div class="contactin-actions">
				<button class="button button-primary js-maint-action" data-action="contactin_maint_run_queue_email" data-nonce="<?php echo esc_attr( $nonce_run_email ); ?>">
					<?php esc_html_e( 'Process Email Pending Now', 'contactin' ); ?>
				</button>
				<button class="button js-maint-action" data-action="contactin_maint_reschedule_email_queue" data-nonce="<?php echo esc_attr( $nonce_resched_email ); ?>" data-delay-default="<?php echo esc_attr( $email_reschedule_default ); ?>">
					<?php esc_html_e( 'Reschedule Email Queue', 'contactin' ); ?>
				</button>
			</div>
			<div class="contactin-progress" data-progress-scope="email" aria-live="polite">
				<div class="contactin-progress-track">
					<span class="contactin-progress-fill" style="width: 0%"></span>
				</div>
				<div class="contactin-progress-meta">
					<span class="contactin-progress-text"><?php esc_html_e( 'Idle', 'contactin' ); ?></span>
					<span class="contactin-progress-count" data-progress-count></span>
				</div>
			</div>
		</div>

		<div class="contactin-card">
			<h2><?php esc_html_e( 'Failed Email Messages', 'contactin' ); ?></h2>
			<p><?php esc_html_e( 'Retry failed email delivery attempts (legacy statuses + queue/DLQ).', 'contactin' ); ?></p>
			<div class="contactin-status">
				<span class="contactin-status-label"><?php esc_html_e( 'Status:', 'contactin' ); ?></span>
				<span class="contactin-status-text"><?php echo esc_html( $email_failed_summary ); ?></span>
			</div>
			<div class="contactin-actions">
				<button class="button button-primary js-maint-action" data-action="contactin_maint_retry_email_dlq" data-nonce="<?php echo esc_attr( $nonce_retry_email ); ?>">
					<?php esc_html_e( 'Retry Failed Emails', 'contactin' ); ?>
				</button>
			</div>
		</div>

		<div class="contactin-card">
			<h2><?php esc_html_e( 'Processed Messages', 'contactin' ); ?></h2>
			<p><?php esc_html_e( 'Review aggregate delivery stats for reference.', 'contactin' ); ?></p>
			<div class="contactin-status">
				<span class="contactin-status-label"><?php esc_html_e( 'Status:', 'contactin' ); ?></span>
				<span class="contactin-status-text"><?php echo esc_html( $completed_summary ); ?></span>
			</div>
			<p class="description"><?php esc_html_e( 'Lifecycle clean-up is automatic; no manual queue maintenance is required.', 'contactin' ); ?></p>
		</div>

		<div class="contactin-card">
			<h2><?php esc_html_e( 'CRM Sync Processing', 'contactin' ); ?> <button type="button" class="button button-small disabled" data-upgrade-only="1" aria-disabled="true"><span class="cin-pro-badge cin-pro-badge--button"><?php esc_html_e( 'PRO', 'contactin' ); ?></span></button></h2>
			<p><?php esc_html_e( 'Queue-driven CRM record syncs (Contact/Case creation) and file uploads. Records are queued immediately at form submission. Files are queued after case creation in Salesforce.', 'contactin' ); ?></p>
			<div class="contactin-status">
				<span class="contactin-status-label"><?php esc_html_e( 'Status:', 'contactin' ); ?></span>
				<span class="contactin-status-text">Nil</span>
			</div>
			<p class="description cin-mt-sm">
				Nil
			</p>
			<div class="contactin-actions">
				<button type="button" class="button button-primary disabled" data-upgrade-only="1" aria-disabled="true">
					<?php esc_html_e( 'Process CRM Pending Now', 'contactin' ); ?>
				</button>
				<button type="button" class="button disabled" data-upgrade-only="1" aria-disabled="true">
					<?php esc_html_e( 'Reschedule CRM Queue', 'contactin' ); ?>
				</button>
			</div>
			<p class="description cin-mt-sm">Nil</p>
		</div>
		<div class="contactin-card">
			<h2><?php esc_html_e( 'Failed CRM Syncs', 'contactin' ); ?> <button type="button" class="button button-small disabled" data-upgrade-only="1" aria-disabled="true"><span class="cin-pro-badge cin-pro-badge--button"><?php esc_html_e( 'PRO', 'contactin' ); ?></span></button></h2>
			<p><?php esc_html_e( 'Retry failed record syncs (Contact/Case creation), attachment uploads, and deletions. Items auto-retry with exponential backoff. "Retry" = pending retry. "DLQ" = exhausted all retries (dead letter queue).', 'contactin' ); ?></p>
			<div class="contactin-status">
				<span class="contactin-status-label"><?php esc_html_e( 'Active Failures:', 'contactin' ); ?></span>
				<span class="contactin-status-text">Nil</span>
			</div>
			<p class="description cin-mt-sm" style="color: #666;">Nil</p>
			<div class="contactin-actions">
				<button type="button" class="button button-primary disabled" data-upgrade-only="1" aria-disabled="true">
					<?php esc_html_e( 'Retry Failed CRM Syncs', 'contactin' ); ?>
				</button>
			</div>
			<p class="contactin-note-text">
				<?php esc_html_e( 'Note: Retries are processed immediately and go through unified queue with automatic exponential backoff (1s, 4s, 16s, 64s). Items that fail again will return to retry or DLQ status.', 'contactin' ); ?>
			</p>
		</div>

		<div class="contactin-card">
			<h2><?php esc_html_e( 'Intent Classification', 'contactin' ); ?> <button type="button" class="button button-small disabled" data-upgrade-only="1" aria-disabled="true"><span class="cin-pro-badge cin-pro-badge--button"><?php esc_html_e( 'PRO', 'contactin' ); ?></span></button></h2>
			<p><?php esc_html_e( 'Reclassify unclassified messages using current classification patterns.', 'contactin' ); ?></p>
			<div class="contactin-status">
				<span class="contactin-status-label"><?php esc_html_e( 'Unclassified Messages:', 'contactin' ); ?></span>
				<span class="contactin-status-text">Nil</span>
			</div>
			<p class="description cin-mt-sm">Nil</p>
			<div class="contactin-actions">
				<button type="button" class="button button-primary disabled" data-upgrade-only="1" aria-disabled="true">
					<?php esc_html_e( 'Reclassify Unclassified Messages', 'contactin' ); ?>
				</button>
			</div>
			<p class="description cin-mt-sm">Nil</p>
		</div>

		<div class="contactin-card">
			<h2><?php esc_html_e( 'Circuits & Email', 'contactin' ); ?></h2>
			<p><?php esc_html_e( 'Reset circuit breakers or skip email items while SMTP is disabled.', 'contactin' ); ?></p>
			<div class="contactin-status">
				<span class="contactin-status-label"><?php esc_html_e( 'Status:', 'contactin' ); ?></span>
				<span class="contactin-status-text"><?php echo esc_html( $circuit_status_line ); ?></span>
			</div>
			<div class="contactin-warning-box" style="background-color: #e8f4fd; border-left: 4px solid #0176d3; padding: 0; margin-bottom: 16px; border-radius: 3px; max-height: 180px; overflow-y: auto;">
				<div style="padding: 12px 15px;">
					<p style="margin: 0 0 8px 0; font-weight: 600; color: #333; position: sticky; top: 0; background-color: #e8f4fd; padding-top: 4px;">
						💡 <?php esc_html_e( 'When to Use These Actions:', 'contactin' ); ?>
					</p>
					<ul class="contactin-gdpr-info-list">
						<li><?php esc_html_e( 'Reset Circuit Breakers: Use after fixing SMTP/CRM connectivity so the plugin can resume normal retries immediately.', 'contactin' ); ?></li>
						<li><?php esc_html_e( 'Skip Email Items (SMTP off): Use only while SMTP is intentionally disabled to stop repeated email retry attempts.', 'contactin' ); ?></li>
						<li><?php esc_html_e( 'Skipping affects email queue items only; CRM record/case sync processing is not skipped by this action.', 'contactin' ); ?></li>
						<li><?php esc_html_e( 'After SMTP is restored, run “Process Email Pending Now” from Email Sync Processing to drain pending email work.', 'contactin' ); ?></li>
					</ul>
				</div>
			</div>
			<div class="contactin-actions">
				<button class="button js-maint-action" data-action="contactin_maint_reset_circuits" data-nonce="<?php echo esc_attr( $nonce_reset_cb ); ?>">
					<?php esc_html_e( 'Reset Circuit Breakers', 'contactin' ); ?>
				</button>
				<button class="button js-maint-action" data-action="contactin_maint_skip_email" data-nonce="<?php echo esc_attr( $nonce_skip_email ); ?>">
					<?php esc_html_e( 'Skip Email Items (SMTP off)', 'contactin' ); ?>
				</button>
			</div>
		</div>
		
		<div class="contactin-card">
			<h2><?php esc_html_e( 'GDPR Compliance - CRM Cleanup', 'contactin' ); ?> <button type="button" class="button button-small disabled" data-upgrade-only="1" aria-disabled="true"><span class="cin-pro-badge cin-pro-badge--button"><?php esc_html_e( 'PRO', 'contactin' ); ?></span></button></h2>
			<p><?php esc_html_e( 'Manage deletion of contacts synced to CRM. Queue for processing or delete immediately with full audit trail.', 'contactin' ); ?></p>
			
			<!-- IMPORTANT: Cascade Delete Configuration Warning -->
			<div class="contactin-warning-box" style="background-color: #e8f4fd; border-left: 4px solid #0176d3; padding: 0; margin-bottom: 16px; border-radius: 3px; max-height: 180px; overflow-y: auto;">
				<div style="padding: 12px 15px;">
					<p style="margin: 0 0 8px 0; font-weight: 600; color: #333; position: sticky; top: 0; background-color: #e8f4fd; padding-top: 4px;">
						ℹ️ <?php esc_html_e( 'How Salesforce Handles Contact Deletion', 'contactin' ); ?>
					</p>
					<p style="margin: 0; font-size: 13px; color: #555; line-height: 1.5;">
						<?php esc_html_e( 'When you delete a Contact from Salesforce, the Contact record and its Tasks/Events are permanently removed. However, business records like Cases and Opportunities are preserved with the Contact reference cleared. This is Salesforce\'s standard behavior designed to maintain business continuity while removing personal data.', 'contactin' ); ?>
					</p>
				</div>
			</div>
			
			<div class="contactin-status">
				<span class="contactin-status-label"><?php esc_html_e( 'Synced Contacts Ready:', 'contactin' ); ?></span>
				<span class="contactin-status-text contactin-status-highlight">
					Nil
				</span>
			</div>
			<?php if ( $synced_count > 0 ) : ?>
				<div class="contactin-gdpr-info-box">
					<p class="contactin-gdpr-info-title">
						📋 <strong><?php esc_html_e( 'What Gets Deleted from Salesforce:', 'contactin' ); ?></strong>
					</p>
					<ul class="contactin-gdpr-info-list">
						<li><?php esc_html_e( '✓ Contact record (personal data) permanently deleted', 'contactin' ); ?></li>
						<li><?php esc_html_e( '✓ Tasks & Events automatically deleted by Salesforce', 'contactin' ); ?></li>
						<li><?php esc_html_e( '✓ All deletion attempts logged for audit trail', 'contactin' ); ?></li>
						<li><?php esc_html_e( '✓ Deleted records move to Salesforce Recycle Bin (15-day retention)', 'contactin' ); ?></li>
					</ul>
					
					<p class="contactin-gdpr-info-title" style="margin-top: 12px;">
						📝 <strong><?php esc_html_e( 'What Happens to Related Records:', 'contactin' ); ?></strong>
					</p>
					<ul class="contactin-gdpr-info-list">
						<li><?php esc_html_e( 'Cases: Remain in Salesforce with Contact field cleared (work history preserved)', 'contactin' ); ?></li>
						<li><?php esc_html_e( 'Opportunities: Remain in Salesforce with Contact Role removed', 'contactin' ); ?></li>
						<li><?php esc_html_e( 'Custom Objects: Contact lookup field cleared (record preserved by default)', 'contactin' ); ?></li>
					</ul>
					
					<p class="contactin-gdpr-info-title" style="margin-top: 12px;">
						⚙️ <strong><?php esc_html_e( 'Processing Options:', 'contactin' ); ?></strong>
					</p>
					<ul class="contactin-gdpr-info-list">
						<li><?php esc_html_e( 'Queue for Deletion: Schedules for batch processing (recommended)', 'contactin' ); ?></li>
						<li><?php esc_html_e( 'Delete Now: Processes immediately with full sync logic and fallbacks', 'contactin' ); ?></li>
					</ul>
				</div>
			<?php endif; ?>
			<div class="contactin-actions">
				<button type="button" class="button disabled" data-upgrade-only="1" aria-disabled="true">
					<?php esc_html_e( 'Queue for Deletion', 'contactin' ); ?>
				</button>
				<button type="button" class="button button-primary disabled" data-upgrade-only="1" aria-disabled="true">
					<?php esc_html_e( 'Delete Now', 'contactin' ); ?>
				</button>
			</div>
		</div>
		
		<div class="contactin-card contactin-attachment-cleanup-card">
			<h2><?php esc_html_e( 'Attachment Cleanup', 'contactin' ); ?> <button type="button" class="button button-small disabled" data-upgrade-only="1" aria-disabled="true"><span class="cin-pro-badge cin-pro-badge--button"><?php esc_html_e( 'PRO', 'contactin' ); ?></span></button></h2>
			<p><?php esc_html_e( 'Scan and clean orphaned attachment files and stale attachment records.', 'contactin' ); ?></p>
			<div class="contactin-status">
				<span class="contactin-status-label"><?php esc_html_e( 'Status:', 'contactin' ); ?></span>
				<span class="contactin-status-text">Nil</span>
			</div>
			<p class="description cin-mt-sm">Nil</p>
			<div class="contactin-attachment-actions cin-flex-column-gap">
				<button type="button" class="button button-primary disabled" data-upgrade-only="1" aria-disabled="true"><?php esc_html_e( 'Clean Up Orphaned Files', 'contactin' ); ?></button>
				<button type="button" class="button disabled" data-upgrade-only="1" aria-disabled="true"><?php esc_html_e( 'Clean Stale DB Entries', 'contactin' ); ?></button>
			</div>
		</div>

		<!-- Pro Feature: Intent Learning Widget -->
		<?php
		require CONTACTINBOX_PATH . Config::TEMPLATE_ADMIN_PART . 'intent-learning-widget.php';
		?>
	</div>

	<?php load_template( CONTACTINBOX_PATH . \ContactInbox\Core\Config::TEMPLATE_ADMIN_PART . 'maintenance-help-modal.php' ); ?>
	<!-- Attachment Cleanup Card assets will be enqueued via Assets class. -->

	<div id="contactin-maint-message" class="notice"></div>

</div>
