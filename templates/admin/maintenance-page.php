<?php
use ContactInbox\Core\Config;
use ContactInbox\Integration\FreemiusIntegration;
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
$nonce_run_crm               = wp_create_nonce( 'contactin_maint_run_queue_crm' );
$nonce_retry_email           = wp_create_nonce( 'contactin_maint_retry_email_dlq' );
$nonce_retry_crm             = wp_create_nonce( 'contactin_maint_retry_crm_dlq' );
$nonce_reset_cb              = wp_create_nonce( 'contactin_maint_reset_circuits' );
$nonce_skip_email            = wp_create_nonce( 'contactin_maint_skip_email' );
$nonce_resched_email         = wp_create_nonce( 'contactin_maint_reschedule_email_queue' );
$nonce_resched_crm           = wp_create_nonce( 'contactin_maint_reschedule_crm_queue' );
$nonce_gdpr_queue_delete     = wp_create_nonce( 'contactin_maint_gdpr_queue_delete' );
$nonce_gdpr_immediate_delete = wp_create_nonce( 'contactin_maint_gdpr_immediate_delete' );
$nonce_reclassify_intent     = wp_create_nonce( 'contactin_maint_reclassify_intent' );
$is_expired_license_state    = FreemiusIntegration::is_non_premium_state();

$pending                  = intval( $queue_stats['pending'] ?? 0 );
$processing               = intval( $queue_stats['processing'] ?? 0 );
$retry                    = intval( $queue_stats['retry'] ?? 0 );
$completed                = intval( $queue_stats['completed'] ?? 0 );
$email_reschedule_default = intval( $email_reschedule_default ?? 120 );
$crm_reschedule_default   = intval( $crm_reschedule_default ?? $email_reschedule_default );

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
		<div class="contactin-badge"><span class="label"><?php esc_html_e( 'CRM Record Pending (Current)', 'contactin' ); ?></span><span class="value" title="<?php echo esc_attr( __( 'Includes legacy table + queue pending/processing.', 'contactin' ) ); ?>"><?php echo $crm_pending_total; ?></span></div>
		<div class="contactin-badge"><span class="label"><?php esc_html_e( 'CRM Record Synced (7d)', 'contactin' ); ?></span><span class="value"><?php echo $crm_completed_total; ?></span></div>
		<div class="contactin-badge"><span class="label"><?php esc_html_e( 'CRM Record Failed (Current)', 'contactin' ); ?></span><span class="value"><?php echo $crm_failed; ?></span></div>
		<div class="contactin-badge"><span class="label"><?php esc_html_e( 'CRM Delete Pending (Current)', 'contactin' ); ?></span><span class="value"><?php echo $delete_pending; ?></span></div>
		<div class="contactin-badge"><span class="label"><?php esc_html_e( 'CRM Delete Failed (Current)', 'contactin' ); ?></span><span class="value" title="<?php echo esc_attr( __( 'Includes retry + DLQ deletion items.', 'contactin' ) ); ?>"><?php echo $delete_failed_total; ?></span></div>
		<div class="contactin-badge"><span class="label"><?php esc_html_e( 'CRM Deleted (7d)', 'contactin' ); ?></span><span class="value"><?php echo $crm_deleted; ?></span></div>
		<div class="contactin-badge"><span class="label"><?php esc_html_e( 'CRM File Pending (Current)', 'contactin' ); ?></span><span class="value"><?php echo $file_pending; ?></span></div>
		<div class="contactin-badge"><span class="label"><?php esc_html_e( 'CRM File Synced (7d)', 'contactin' ); ?></span><span class="value"><?php echo $file_synced; ?></span></div>
		<div class="contactin-badge"><span class="label"><?php esc_html_e( 'CRM File Failed (7d)', 'contactin' ); ?></span><span class="value"><?php echo $file_failed; ?></span></div>
		<?php foreach ( $circuit_badges as $service => $badge_data ) : ?>
			<div class="contactin-badge">
				<span class="label"><?php echo esc_html( strtoupper( $service ) ); ?></span>
				<span class="value" data-state="<?php echo esc_attr( $badge_data['state'] ?: 'unknown' ); ?>" title="<?php echo esc_attr( $badge_data['tooltip'] ); ?>"><?php echo esc_html( $badge_data['label'] ); ?></span>
			</div>
		<?php endforeach; ?>
	</div>

	<div class="contactin-maint-grid">
		<div class="contactin-card <?php echo $is_expired_license_state ? 'contactin-card-disabled' : ''; ?>">
			<h2><?php esc_html_e( 'Email Processing', 'contactin' ); ?><?php FreemiusIntegration::echo_pro_badge( 'heading' ); ?></h2>
			<p><?php esc_html_e( 'Monitor email queue processing status.', 'contactin' ); ?></p>
			<div class="contactin-status">
				<span class="contactin-status-label"><?php esc_html_e( 'Status:', 'contactin' ); ?></span>
				<span class="contactin-status-text" title="<?php echo esc_attr( __( 'Legacy pending = message table status. Queue pending/processing = unified queue items.', 'contactin' ) ); ?>"><?php echo esc_html( $email_processing_status ); ?></span>
			</div>
			<p class="description cin-mt-sm">
				<?php echo esc_html( $next_run_email_text ); ?>
			</p>
			<div class="contactin-actions">
				<button class="button button-primary <?php echo $is_expired_license_state ? 'disabled' : 'js-maint-action'; ?>" <?php echo $is_expired_license_state ? 'disabled aria-disabled="true" tabindex="-1"' : 'data-action="contactin_maint_run_queue_email" data-nonce="' . esc_attr( $nonce_run_email ) . '"'; ?> title="<?php echo $is_expired_license_state ? esc_attr__( 'Renew your license to use this feature', 'contactin' ) : ''; ?>">
					<?php esc_html_e( 'Process Email Pending Now', 'contactin' ); ?>
					<?php FreemiusIntegration::echo_pro_badge( 'button' ); ?>
				</button>
				<button class="button <?php echo $is_expired_license_state ? 'disabled' : 'js-maint-action'; ?>" <?php echo $is_expired_license_state ? 'disabled aria-disabled="true" tabindex="-1"' : 'data-action="contactin_maint_reschedule_email_queue" data-nonce="' . esc_attr( $nonce_resched_email ) . '" data-delay-default="' . esc_attr( $email_reschedule_default ) . '"'; ?> title="<?php echo $is_expired_license_state ? esc_attr__( 'Renew your license to use this feature', 'contactin' ) : ''; ?>">
					<?php esc_html_e( 'Reschedule Email Queue', 'contactin' ); ?>
					<?php FreemiusIntegration::echo_pro_badge( 'button' ); ?>
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

		<div class="contactin-card <?php echo $is_expired_license_state ? 'contactin-card-disabled' : ''; ?>">
			<h2><?php esc_html_e( 'Failed Email Messages', 'contactin' ); ?><?php FreemiusIntegration::echo_pro_badge( 'heading' ); ?></h2>
			<p><?php esc_html_e( 'Retry failed email delivery attempts (legacy statuses + queue/DLQ).', 'contactin' ); ?></p>
			<div class="contactin-status">
				<span class="contactin-status-label"><?php esc_html_e( 'Status:', 'contactin' ); ?></span>
				<span class="contactin-status-text"><?php echo esc_html( $email_failed_summary ); ?></span>
			</div>
			<div class="contactin-actions">
				<button class="button button-primary <?php echo $is_expired_license_state ? 'disabled' : 'js-maint-action'; ?>" <?php echo $is_expired_license_state ? 'disabled aria-disabled="true" tabindex="-1"' : 'data-action="contactin_maint_retry_email_dlq" data-nonce="' . esc_attr( $nonce_retry_email ) . '"'; ?> title="<?php echo $is_expired_license_state ? esc_attr__( 'Renew your license to use this feature', 'contactin' ) : ''; ?>">
					<?php esc_html_e( 'Retry Failed Emails', 'contactin' ); ?>
					<?php FreemiusIntegration::echo_pro_badge( 'button' ); ?>
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

		<div class="contactin-card <?php echo $is_expired_license_state ? 'contactin-card-disabled' : ''; ?>">
			<h2><?php esc_html_e( 'CRM Sync Processing', 'contactin' ); ?><?php FreemiusIntegration::echo_pro_badge( 'heading' ); ?></h2>
			<p><?php esc_html_e( 'Queue-driven CRM record syncs (Contact/Case creation) and file uploads. Records are queued immediately at form submission. Files are queued after case creation in Salesforce.', 'contactin' ); ?></p>
			<div class="contactin-status">
				<span class="contactin-status-label"><?php esc_html_e( 'Status:', 'contactin' ); ?></span>
				<span class="contactin-status-text" title="<?php echo esc_attr( __( 'Legacy pending = message table status. Queue pending/processing = unified queue items.', 'contactin' ) ); ?>"><?php echo esc_html( $crm_processing_status ); ?></span>
			</div>
			<p class="description cin-mt-sm">
				<?php echo esc_html( $next_run_crm_text ); ?>
			</p>
			<div class="contactin-actions">
				<button class="button button-primary <?php echo $is_expired_license_state ? 'disabled' : 'js-maint-action'; ?>" <?php echo $is_expired_license_state ? 'disabled aria-disabled="true" tabindex="-1"' : 'data-action="contactin_maint_run_queue_crm" data-nonce="' . esc_attr( $nonce_run_crm ) . '"'; ?> title="<?php echo $is_expired_license_state ? esc_attr__( 'Renew your license to use this feature', 'contactin' ) : ''; ?>">
					<?php esc_html_e( 'Process CRM Pending Now', 'contactin' ); ?>
					<?php FreemiusIntegration::echo_pro_badge( 'button' ); ?>
				</button>
				<button class="button <?php echo $is_expired_license_state ? 'disabled' : 'js-maint-action'; ?>" <?php echo $is_expired_license_state ? 'disabled aria-disabled="true" tabindex="-1"' : 'data-action="contactin_maint_reschedule_crm_queue" data-nonce="' . esc_attr( $nonce_resched_crm ) . '" data-delay-default="' . esc_attr( $crm_reschedule_default ) . '"'; ?> title="<?php echo $is_expired_license_state ? esc_attr__( 'Renew your license to use this feature', 'contactin' ) : ''; ?>">
					<?php esc_html_e( 'Reschedule CRM Queue', 'contactin' ); ?>
					<?php FreemiusIntegration::echo_pro_badge( 'button' ); ?>
				</button>
			</div>
			<div class="contactin-progress" data-progress-scope="crm" aria-live="polite">
				<div class="contactin-progress-track">
					<span class="contactin-progress-fill" style="width: 0%"></span>
				</div>
				<div class="contactin-progress-meta">
					<span class="contactin-progress-text"><?php esc_html_e( 'Idle', 'contactin' ); ?></span>
					<span class="contactin-progress-count" data-progress-count></span>
				</div>
			</div>
		</div>
		<div class="contactin-card <?php echo $is_expired_license_state ? 'contactin-card-disabled' : ''; ?>">
			<h2><?php esc_html_e( 'Failed CRM Syncs', 'contactin' ); ?><?php FreemiusIntegration::echo_pro_badge( 'heading' ); ?></h2>
			<p><?php esc_html_e( 'Retry failed record syncs (Contact/Case creation), attachment uploads, and deletions. Items auto-retry with exponential backoff. "Retry" = pending retry. "DLQ" = exhausted all retries (dead letter queue).', 'contactin' ); ?></p>
			<div class="contactin-status">
				<span class="contactin-status-label"><?php esc_html_e( 'Active Failures:', 'contactin' ); ?></span>
				<span class="contactin-status-text" title="<?php echo esc_attr( __( 'These counts show items currently in retry or DLQ status. Counts decrease when items succeed, or persist/increase if items fail again after retry.', 'contactin' ) ); ?>">
					<?php
					echo esc_html(
						sprintf(
						/* translators: 1: legacy record failed, 2: queue retry, 3: queue dlq, 4: file failed, 5: attachment retry pending, 6: attachment retry retry, 7: attachment retry dlq, 8: delete retry, 9: delete dlq */
							__( 'Records - Failed (current): %1$d · Queue Retry (current): %2$d · Queue DLQ (current): %3$d | Files - Failed (7d): %4$d · Retry Pending (current): %5$d · Retry (current): %6$d · DLQ (current): %7$d | Deletions - Retry (current): %8$d · DLQ (current): %9$d', 'contactin' ),
							$crm_failed,
							$crm_queue_retry,
							$crm_queue_dlq,
							$file_failed,
							$attachment_retry_pending,
							$attachment_retry_retry,
							$attachment_retry_dlq,
							$delete_retry,
							$delete_dlq
						)
					);
					?>
				</span>
			</div>
			<?php if ( ! empty( $last_retry_text ) ) : ?>
				<p class="description cin-mt-sm" style="color: #666;">
					<?php echo esc_html( $last_retry_text ); ?>
				</p>
			<?php endif; ?>
			<div class="contactin-actions">
				<button class="button button-primary <?php echo $is_expired_license_state ? 'disabled' : 'js-maint-action'; ?>" <?php echo $is_expired_license_state ? 'disabled aria-disabled="true" tabindex="-1"' : 'data-action="contactin_maint_retry_crm_dlq" data-nonce="' . esc_attr( $nonce_retry_crm ) . '"'; ?> title="<?php echo $is_expired_license_state ? esc_attr__( 'Renew your license to use this feature', 'contactin' ) : ''; ?>">
					<?php esc_html_e( 'Retry Failed CRM Syncs', 'contactin' ); ?>
					<?php FreemiusIntegration::echo_pro_badge( 'button' ); ?>
				</button>
			</div>
			<p class="contactin-note-text">
				<?php esc_html_e( 'Note: Retries are processed immediately and go through unified queue with automatic exponential backoff (1s, 4s, 16s, 64s). Items that fail again will return to retry or DLQ status.', 'contactin' ); ?>
			</p>
		</div>

		<div class="contactin-card">
			<h2><?php esc_html_e( 'Intent Classification', 'contactin' ); ?></h2>
			<p><?php esc_html_e( 'Reclassify unclassified messages using current classification patterns.', 'contactin' ); ?></p>
			<div class="contactin-status">
				<span class="contactin-status-label"><?php esc_html_e( 'Unclassified Messages:', 'contactin' ); ?></span>
				<span class="contactin-status-text"><?php echo esc_html( $unclassified_count ); ?></span>
			</div>
			<?php if ( ! empty( $intent_stats ) ) : ?>
				<div class="contactin-intent-stats-mini">
					<?php
					foreach ( $intent_stats as $category => $count ) :
						$label      = $intent_categories[ $category ] ?? ucfirst( $category );
						$color      = $intent_colors_map[ $category ] ?? 'muted';
						$total      = array_sum( $intent_stats );
						$percentage = $total > 0 ? round( ( $count / $total ) * 100, 1 ) : 0;
						?>
						<span class="intent-badge intent-<?php echo esc_attr( $color ); ?>">
							<?php echo esc_html( $label ); ?>: <strong><?php echo esc_html( $count ); ?></strong> (<?php echo esc_html( $percentage ); ?>%)
						</span>
					<?php endforeach; ?>
				</div>
			<?php endif; ?>
			<div class="contactin-actions">
				<button class="button button-primary" id="cin-reclassify-intent-btn" data-nonce="<?php echo esc_attr( $nonce_reclassify_intent ); ?>" 
				<?php
				if ( $unclassified_count === 0 ) {
					echo 'disabled';}
				?>
				>
					<?php esc_html_e( 'Reclassify Unclassified Messages', 'contactin' ); ?>
				</button>
			</div>
			<p class="description cin-mt-sm" id="cin-reclassify-result"></p>
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
			<h2><?php esc_html_e( 'GDPR Compliance - CRM Cleanup', 'contactin' ); ?></h2>
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
					<?php echo esc_html( number_format_i18n( $synced_count ) ); ?>
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
				<button class="button 
				<?php
				if ( $synced_count === 0 ) {
					echo 'disabled';}
				?>
				cin-gdpr-queue-delete-btn" data-nonce="<?php echo esc_attr( $nonce_gdpr_queue_delete ); ?>" <?php disabled( $synced_count === 0 ); ?>>
					<?php esc_html_e( 'Queue for Deletion', 'contactin' ); ?>
				</button>
				<button class="button button-primary 
				<?php
				if ( $synced_count === 0 ) {
					echo 'disabled';}
				?>
				cin-gdpr-immediate-delete-btn" data-nonce="<?php echo esc_attr( $nonce_gdpr_immediate_delete ); ?>" <?php disabled( $synced_count === 0 ); ?>>
					<?php esc_html_e( 'Delete Now', 'contactin' ); ?>
				</button>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=' . Config::MENU_GDPR_LOG ) ); ?>" class="button">
					<?php esc_html_e( 'View GDPR Log', 'contactin' ); ?>
				</a>
			</div>
		</div>
		
		<div class="contactin-card contactin-attachment-cleanup-card">
			<h2><?php esc_html_e( 'Attachment Cleanup', 'contactin' ); ?></h2>
			<p><?php esc_html_e( 'Scan for orphaned attachments, files left behind after their database records were deleted. Remove them to reclaim disk space.', 'contactin' ); ?></p>
			
			<!-- Diagnostics: Show discrepancies -->
			<?php if ( $attachment_stale['stale_count'] > 0 || $temp_orphaned_count > 0 ) : ?>
				<div class="notice notice-info is-dismissible contactin-notice-compact">
					<p>
						<strong><?php esc_html_e( 'Attachment Diagnostics', 'contactin' ); ?></strong><br>
						<?php if ( $attachment_stale['stale_count'] > 0 ) : ?>
							<?php printf( esc_html__( 'Database has %d attachment(s) referencing files that no longer exist on disk.', 'contactin' ), $attachment_stale['stale_count'] ); ?><br>
						<?php endif; ?>
						<?php if ( $temp_orphaned_count > 0 ) : ?>
							<?php printf( esc_html__( 'Temp folder has %d old file(s) older than 24 hours (will be auto-cleaned daily).', 'contactin' ), $temp_orphaned_count ); ?><br>
						<?php endif; ?>
					</p>
					<button type="button" class="notice-dismiss"><span class="screen-reader-text"><?php esc_html_e( 'Dismiss this notice.' ); ?></span></button>
				</div>
			<?php endif; ?>
			
			<?php if ( $orph_count > 0 ) : ?>
				<div class="notice notice-warning is-dismissible contactin-notice-compact">
					<p>
						<strong><?php esc_html_e( 'Orphaned attachment files detected!', 'contactin' ); ?></strong><br>
						<?php printf( esc_html__( 'There are %d orphaned files taking up %s of disk space.', 'contactin' ), $orph_count, size_format( $orph_size ) ); ?>
					</p>
					<button type="button" class="notice-dismiss"><span class="screen-reader-text"><?php esc_html_e( 'Dismiss this notice.' ); ?></span></button>
				</div>
			<?php endif; ?>
			<ul>
				<li><span class="label"><?php esc_html_e( 'Orphaned Files:', 'contactin' ); ?></span> <span class="value" id="cin-attach-orphaned"><?php echo esc_html( $orph_count ); ?></span></li>
				<li><span class="label"><?php esc_html_e( 'Orphaned Size:', 'contactin' ); ?></span> <span class="value" id="cin-attach-orphaned-size"><?php echo esc_html( number_format( $orph_size_mb, 2 ) ); ?> MB</span></li>
				<li><span class="label"><?php esc_html_e( 'Old Temp Files:', 'contactin' ); ?></span> <span class="value"><?php echo esc_html( $temp_orphaned_count ); ?> (auto-cleaned daily)</span></li>
				<li><span class="label"><?php esc_html_e( 'Stale DB Entries:', 'contactin' ); ?></span> <span class="value"><?php echo esc_html( $attachment_stale['stale_count'] ); ?></span></li>
				<li><span class="label"><?php esc_html_e( 'Last Scan:', 'contactin' ); ?></span> <span class="value" id="cin-attach-last-scan"><?php echo esc_html( $last_scan ); ?></span></li>
			</ul>
			<div class="contactin-attachment-actions cin-flex-column-gap">
				<button class="button button-primary" id="cin-attach-delete-btn" 
				<?php
				if ( $orph_count === 0 ) {
					echo 'disabled';}
				?>
				><?php esc_html_e( 'Clean Up Orphaned Files', 'contactin' ); ?></button>
				<?php if ( $attachment_stale['stale_count'] > 0 ) : ?>
					<button class="button" id="cin-attach-clean-stale-btn"><?php printf( esc_html__( 'Clean Stale DB Entries (%d)', 'contactin' ), $attachment_stale['stale_count'] ); ?></button>
				<?php endif; ?>
			</div>
			<div class="contactin-attachment-summary" id="cin-attach-summary"></div>
		</div>

		<!-- Pro Feature: Intent Learning Widget -->
		<?php
		require CONTACTINBOX_PATH . Config::TEMPLATE_ADMIN_PART . 'intent-learning-widget.php';
		?>
	</div>

	<?php load_template( CONTACTINBOX_PATH . \ContactInbox\Core\Config::TEMPLATE_ADMIN_PART . 'maintenance-help-modal.php' ); ?>
	<!-- Attachment Cleanup Card assets will be enqueued via Assets class. -->

	<div id="contactin-maint-message" class="notice"></div>

	<!-- Intent Reclassification Handler -->
	<script>
	(function() {
		document.addEventListener('DOMContentLoaded', function() {
			const reclassifyBtn = document.getElementById('cin-reclassify-intent-btn');
			if (!reclassifyBtn) return;

			reclassifyBtn.addEventListener('click', function(e) {
				e.preventDefault();
				
				const nonce = reclassifyBtn.dataset.nonce;
				if (!nonce) {
					alert('<?php echo esc_js( __( 'Security nonce missing.', 'contactin' ) ); ?>');
					return;
				}

				// Show loading state
				const originalText = reclassifyBtn.textContent;
				reclassifyBtn.disabled = true;
				reclassifyBtn.textContent = '<?php echo esc_js( __( 'Processing...', 'contactin' ) ); ?>';

				// Make AJAX request
				jQuery.post(
					ajaxurl,
					{
						'action': 'contactin_maint_reclassify_intent',
						'nonce': nonce
					},
					function(response) {
						reclassifyBtn.disabled = false;
						reclassifyBtn.textContent = originalText;

						const resultDiv = document.getElementById('cin-reclassify-result');
						if (resultDiv) {
							resultDiv.style.color = response.success ? '#155724' : '#721c24';
							resultDiv.style.backgroundColor = response.success ? '#d4edda' : '#f8d7da';
							resultDiv.style.border = response.success ? '1px solid #c3e6cb' : '1px solid #f5c6cb';
							resultDiv.style.borderRadius = '4px';
							resultDiv.style.padding = '8px 12px';
							resultDiv.style.marginTop = '8px';
							
							let details = response.data.message || 'Operation completed';
							if (response.data.remaining > 0) {
								const retryMsg = '<?php echo esc_js( __( 'Run again to process next batch', 'contactin' ) ); ?>';
								details += '<br><small>(' + retryMsg + ')</small>';
							}
							resultDiv.innerHTML = details;
						}
					}
				).fail(function() {
					reclassifyBtn.disabled = false;
					reclassifyBtn.textContent = originalText;
					alert('<?php echo esc_js( __( 'An error occurred while processing your request.', 'contactin' ) ); ?>');
				});
			});
		});
	})();
	</script>
</div>
