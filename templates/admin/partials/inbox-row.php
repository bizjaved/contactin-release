<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
/**
 * Partial: Inbox Row – Updated
 *
 * Expects $msg (object) in scope.
 */

use ContactInbox\Core\Config;
use ContactInbox\Core\AttachmentRenderer;
use ContactInbox\Core\CRMStatus;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals, WordPress.Security.EscapeOutput, WordPress.WP.I18n.NonSingularStringLiteralDomain, WordPress.WP.I18n.MissingTranslatorsComment, WordPress.WP.I18n.TextDomainMismatch, WordPress.Security.NonceVerification.Recommended, WordPress.Security.ValidatedSanitizedInput.MissingUnslash, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized, WordPress.WP.I18n.UnorderedPlaceholdersText, WordPress.WP.I18n.NonSingularStringLiteralText, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, Generic.PHP.ForbiddenFunctions.Found, PluginCheck.CodeAnalysis.DiscouragedFunctions.load_plugin_textdomainFound, PluginCheck.CodeAnalysis.Heredoc.NotAllowed, PluginCheck.Security.DirectDB.UnescapedDBParameter, Squiz.PHP.DiscouragedFunctions.Discouraged, WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.NamingConventions.PrefixAllGlobals.DynamicHooknameFound, WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound, WordPress.PHP.DevelopmentFunctions.error_log_debug_backtrace, WordPress.WP.AlternativeFunctions.file_system_operations_fsockopen, WordPress.WP.AlternativeFunctions.file_system_operations_readfile, WordPress.WP.AlternativeFunctions.file_system_operations_rmdir, WordPress.WP.EnqueuedResourceParameters.MissingVersion, WordPress.WP.EnqueuedResources.NonEnqueuedScript, WordPress.WP.I18n.MissingArgDomain, WordPress.WP.I18n.UnorderedPlaceholdersPlural, WordPress.WP.I18n.UnorderedPlaceholdersSingle

if ( isset( $args ) && is_array( $args ) ) {
	$msg            = $args['msg'] ?? null;
	$search_term    = $args['search'] ?? '';
	$current_status = $args['current_status'] ?? 'all';
}

if ( ! $msg ) {
	return; // safety: no message passed
}

/**
 * Helper function to highlight search term in text
 */
if ( ! function_exists( 'ci_highlight_search_term' ) ) {
	function ci_highlight_search_term( $text, $search_term ) {
		if ( empty( $search_term ) || empty( $text ) ) {
			return esc_html( $text );
		}

		$search_term = trim( $search_term );
		// Escape special regex characters
		$search_escaped = preg_quote( $search_term, '/' );
		// Case-insensitive highlighting
		$highlighted = preg_replace(
			'/' . $search_escaped . '/i',
			'<mark class="cin-search-highlight">$0</mark>',
			$text
		);

		// Return HTML with mark tags, so we use wp_kses_post to allow <mark>
		return wp_kses_post( $highlighted );
	}
}

$is_unread = ( $msg->status === 'unread' );
$item      = $msg;

// Build phone display list with basic de-duplication
if ( ! class_exists( 'ContactInbox\\Core\\PhoneUtils' ) ) {
	require_once CONTACTINBOX_PATH . 'includes/Core/PhoneUtils.php';
}

$phone_sources = array(
	array(
		'label' => __( 'Mobile', 'contactin' ),
		'value' => $msg->mobile_phone ?? '',
	),
	array(
		'label' => __( 'Home', 'contactin' ),
		'value' => $msg->home_phone ?? '',
	),
	array(
		'label' => __( 'Other', 'contactin' ),
		'value' => $msg->other_phone ?? '',
	),
	array(
		'label' => __( 'Phone', 'contactin' ),
		'value' => $msg->phone ?? '',
	),
);

$phones = array();
$seen   = array();
foreach ( $phone_sources as $src ) {
	$raw = trim( (string) $src['value'] );
	if ( $raw === '' ) {
		continue;
	}
	$normalized = \ContactInbox\Core\PhoneUtils::normalize( $raw );
	$duplicate  = false;
	foreach ( $seen as $prev ) {
		if ( \ContactInbox\Core\PhoneUtils::are_equal( $normalized, $prev ) ) {
			$duplicate = true;
			break;
		}
	}
	if ( $duplicate ) {
		continue;
	}
	$seen[]   = $normalized;
	$phones[] = array(
		'label'   => $src['label'],
		'display' => \ContactInbox\Core\PhoneUtils::format( $normalized, 'international' ),
	);
}
?>

<tr id="contactin-row-<?php echo esc_attr( $msg->id ); ?>"
	class="contactin-inbox-row <?php echo esc_attr( $is_unread ? 'unread' : 'read' ); ?>"
	data-id="<?php echo esc_attr( $msg->id ); ?>"
	data-s="<?php echo esc_attr( $_REQUEST['s'] ?? '' ); ?>"
	data-status="<?php echo esc_attr( $_REQUEST['status'] ?? 'all' ); ?>"
	style="<?php echo esc_attr( $is_unread ? 'font-weight:700;' : '' ); ?>">

	<!-- Checkbox -->
	<td class="manage-column column-cb check-column">
		<input type="checkbox" name="message_ids[]" value="<?php echo esc_attr( $msg->id ); ?>">
	</td>

	<!-- From (linked to contact when available) -->
	<td class="column-user" data-label="<?php esc_attr_e( 'From', 'contactin' ); ?>">
		<?php
		$contact_url = ! empty( $msg->contact_id )
			? admin_url( 'admin.php?page=' . \ContactInbox\Core\Config::MENU_CONTACTS . '&contact_id=' . intval( $msg->contact_id ) )
			: '';
		$from_label  = trim( ( $msg->get_display_name() ?? '' ) . ' <' . ( $msg->email ?? '' ) . '>' );
		if ( $contact_url ) {
			echo '<a href="' . esc_url( $contact_url ) . '">' . esc_html( $from_label ) . '</a>';
		} else {
			echo esc_html( $from_label );
		}
		?>
	</td>

	<!-- Subject -->
	<td class="column-subject" data-label="<?php esc_attr_e( 'Subject', 'contactin' ); ?>">
		<?php
		// Intent Badge
		$settings = \ContactInbox\Core\Settings::get_settings();
		if ( ! empty( $settings['intent_enable'] ) && isset( $msg->intent_category ) && $msg->intent_category !== 'unclassified' ) :
			$intent_label = \ContactInbox\Core\IntentClassifier::get_category_label( $msg->intent_category );
			$intent_color = \ContactInbox\Core\IntentClassifier::get_category_color( $msg->intent_category );
			?>
			<span class="cin-intent-badge cin-intent-<?php echo esc_attr( $intent_color ); ?>" 
					title="<?php echo esc_attr( sprintf( __( 'Intent: %s (Confidence: %.0f%%)', 'contactin' ), $intent_label, $msg->intent_confidence ?? 0 ) ); ?>">
				<?php echo esc_html( $intent_label ); ?>
			</span>
		<?php endif; ?>
		<?php
		$subject_text    = wp_strip_all_tags( $msg->subject ?? '' );
		$subject_display = mb_strlen( $subject_text ) > 60 ? mb_substr( $subject_text, 0, 60 ) . '…' : $subject_text;
		// Highlight search term if present
		echo ! empty( $search_term ) ? ci_highlight_search_term( $subject_display, $search_term ) : esc_html( $subject_display );
		?>
	</td>

	<!-- Message preview -->
	<td class="column-message" data-label="<?php esc_attr_e( 'Message', 'contactin' ); ?>">
		<?php
		$msg_text    = wp_strip_all_tags( $msg->message ?? '' );
		$msg_display = mb_strlen( $msg_text ) > 80 ? mb_substr( $msg_text, 0, 80 ) . '…' : $msg_text;
		// Highlight search term if present
		echo ! empty( $search_term ) ? ci_highlight_search_term( $msg_display, $search_term ) : esc_html( $msg_display );
		?>
	</td>

	<!-- Attachment (icon + filename) -->
	<td class="column-attachment" data-label="<?php esc_attr_e( 'Attachment', 'contactin' ); ?>">
		<?php if ( $attachment = $msg->get_attachment() ) : ?>
			<?php
			$filename     = isset( $attachment['name'] ) ? $attachment['name'] : ( isset( $attachment['path'] ) ? basename( $attachment['path'] ) : '' );
			$filesize     = $attachment['size'] ?? '';
			$display_name = $filename;
			if ( ! empty( $filesize ) ) {
				$display_name .= ' (' . $filesize . ')';
			}
			?>
			<a href="<?php echo esc_url( admin_url( 'admin-ajax.php?action=ci_download_attachment&id=' . $msg->id ) ); ?>"
			class="cin-attachment-link"
			data-id="<?php echo esc_attr( $msg->id ); ?>"
			data-filename="<?php echo esc_attr( $filename ); ?>"
			title="<?php echo esc_attr( $display_name ); ?>">
				<span class="dashicons dashicons-paperclip" aria-label="<?php echo esc_attr( $filename ); ?>"></span>
				<span class="attachment-filename"><?php echo esc_html( $display_name ); ?></span>
			</a>
		<?php endif; ?>
	</td>

	<!-- Date -->
	<td class="column-date" data-label="<?php esc_attr_e( 'Date', 'contactin' ); ?>">
		<?php
		$date = $msg->submitted_at ?? '';
		echo esc_html( $date ? date_i18n( 'M j, Y g:i A', strtotime( $date ) ) : '' );
		?>
	</td>

	<!-- Email Sync Status (Admin + User) -->
	<td class="column-email-sync" data-label="<?php esc_attr_e( 'Email Sync', 'contactin' ); ?>">
		<?php
		$admin_status = $msg->admin_email_status ?? Config::EMAIL_PENDING;
		$user_status  = $msg->user_email_status ?? Config::EMAIL_PENDING;

		$admin_label_text  = 'Pending';
		$admin_icon        = '⏳';
		$admin_badge_class = 'status-pending';
		if ( $admin_status === Config::EMAIL_SENT ) {
			$admin_label_text  = 'Sent';
			$admin_icon        = '✅';
			$admin_badge_class = 'status-sent';
		} elseif ( $admin_status === Config::EMAIL_FAILED ) {
			$admin_label_text  = 'Failed';
			$admin_icon        = '❌';
			$admin_badge_class = 'status-failed';
		} elseif ( $admin_status === Config::EMAIL_PROCESSING ) {
			$admin_label_text  = 'Processing';
			$admin_icon        = '🔄';
			$admin_badge_class = 'status-processing';
		} elseif ( $admin_status === Config::EMAIL_SKIPPED ) {
			$admin_label_text  = 'N/A';
			$admin_icon        = '➖';
			$admin_badge_class = 'status-skipped';
		}

		$user_label_text  = 'Pending';
		$user_icon        = '⏳';
		$user_badge_class = 'status-pending';
		if ( $user_status === Config::EMAIL_SENT ) {
			$user_label_text  = 'Sent';
			$user_icon        = '✅';
			$user_badge_class = 'status-sent';
		} elseif ( $user_status === Config::EMAIL_FAILED ) {
			$user_label_text  = 'Failed';
			$user_icon        = '❌';
			$user_badge_class = 'status-failed';
		} elseif ( $user_status === Config::EMAIL_PROCESSING ) {
			$user_label_text  = 'Processing';
			$user_icon        = '🔄';
			$user_badge_class = 'status-processing';
		} elseif ( $user_status === Config::EMAIL_SKIPPED ) {
			$user_label_text  = 'N/A';
			$user_icon        = '➖';
			$user_badge_class = 'status-skipped';
		}
		?>
		<div class="delivery-items" style="display:flex;gap:6px;align-items:center;flex-wrap:wrap;">
			<span class="delivery-badge <?php echo esc_attr( $admin_badge_class ); ?>" title="<?php echo esc_attr( 'Admin Email: ' . $admin_label_text ); ?>" style="display:inline-flex;align-items:center;gap:3px;font-size:11px;padding:2px 6px;border-radius:3px;">
				<span><?php echo esc_html( $admin_icon ); ?></span>
				<span><?php echo esc_html( 'A: ' . $admin_label_text ); ?></span>
			</span>
			<span class="delivery-badge <?php echo esc_attr( $user_badge_class ); ?>" title="<?php echo esc_attr( 'User Email: ' . $user_label_text ); ?>" style="display:inline-flex;align-items:center;gap:3px;font-size:11px;padding:2px 6px;border-radius:3px;">
				<span><?php echo esc_html( $user_icon ); ?></span>
				<span><?php echo esc_html( 'U: ' . $user_label_text ); ?></span>
			</span>
		</div>
	</td>

	<!-- CRM Sync Status (Split: Record + File) -->
	<td class="column-crm-sync" data-label="<?php esc_attr_e( 'CRM Sync', 'contactin' ); ?>">
		<?php
		// Record sync status (Contact + Case/Task)
		$crm_status     = $msg->crm_status ?? Config::CRM_PENDING;
		$record_label   = 'Pending';
		$record_display = '⏳ Record: Pending';
		$record_title   = 'Record Sync (Contact + Case): Pending';
		if ( $crm_status === Config::CRM_SENT ) {
			$record_label   = 'Synced';
			$record_display = '✅ Record: Synced';
			$record_title   = 'Record Sync (Contact + Case): Synced';
		} elseif ( $crm_status === Config::CRM_FAILED ) {
			$record_label   = 'Failed';
			$record_display = '❌ Record: Failed';
			$record_title   = 'Record Sync (Contact + Case): Failed';
		} elseif ( $crm_status === Config::CRM_PROCESSING ) {
			$record_label   = 'Processing';
			$record_display = '🔄 Record: Processing';
			$record_title   = 'Record Sync (Contact + Case): Processing';
		} elseif ( $crm_status === Config::CRM_SKIPPED ) {
			$record_label   = 'N/A';
			$record_display = '➖ Record: None';
			$record_title   = 'Record Sync: Not Applicable';
		}

		// File sync status (Attachment upload) - only show if message has attachment AND CRM is enabled
		$file_display = '';
		$file_title   = '';

		// If CRM is disabled (skipped), show "None" for file status regardless of attachment
		if ( $crm_status === Config::CRM_SKIPPED ) {
			$file_display = '<br/>➖ File: None';
			$file_title   = ' | File Sync: Not Applicable';
		} elseif ( ! empty( $msg->attachment ) ) {
			global $wpdb;

			// Check queue status FIRST (unified queue table takes priority)
			$queue_attachment = $wpdb->get_row(
				$wpdb->prepare(
					"SELECT status FROM {$wpdb->prefix}queue WHERE type = %s AND data LIKE %s ORDER BY created_at DESC LIMIT 1",
					'attachment_retry',
					'%"message_id":' . (int) $msg->id . '%'
				)
			);

			if ( $queue_attachment ) {
				$queue_status = $queue_attachment->status;
				if ( $queue_status === 'pending' ) {
					$file_display = '<br/>⏳ File: Queued';
					$file_title   = ' | File Sync: Queued for Upload';
				} elseif ( $queue_status === 'processing' ) {
					$file_display = '<br/>🔄 File: Uploading';
					$file_title   = ' | File Sync: Upload in Progress';
				} elseif ( $queue_status === 'retry' ) {
					$file_display = '<br/>🔄 File: Retrying';
					$file_title   = ' | File Sync: Retry in Progress';
				} elseif ( $queue_status === 'completed' ) {
					$file_display = '<br/>✅ File: Synced';
					$file_title   = ' | File Sync: Uploaded Successfully';
				} elseif ( $queue_status === 'dlq' ) {
					$file_display = '<br/>❌ File: Failed (DLQ)';
					$file_title   = ' | File Sync: Permanently Failed - Check Logs';
				}
			} else {
				// Fallback: Query attachment status from sf_attachments table (legacy)
				$attachment_table  = $wpdb->prefix . 'contactinbox_sf_attachments';
				$attachment_status = $wpdb->get_var(
					$wpdb->prepare(
						"SELECT status FROM {$attachment_table} WHERE message_id = %d ORDER BY created_at DESC LIMIT 1",
						$msg->id
					)
				);

				if ( $attachment_status === 'delivered' ) {
					$file_display = '<br/>✅ File: Synced';
					$file_title   = ' | File Sync: Uploaded Successfully';
				} elseif ( $attachment_status === 'failed' ) {
					$file_display = '<br/>❌ File: Failed';
					$file_title   = ' | File Sync: Upload Failed';
				} elseif ( $attachment_status === 'queued' ) {
					$file_display = '<br/>⏳ File: Queued';
					$file_title   = ' | File Sync: Queued for Upload';
				} elseif ( $attachment_status === 'uploading' ) {
					$file_display = '<br/>🔄 File: Uploading';
					$file_title   = ' | File Sync: Upload in Progress';
				} else {
					// No status yet or pending
					$file_display = '<br/>⏳ File: Pending';
					$file_title   = ' | File Sync: Pending Upload';
				}
			}
		}
		?>
		<span class="delivery-item crm-sync-item" title="<?php echo esc_attr( $record_title . $file_title ); ?>" style="font-size:12px;white-space:nowrap;display:inline-block;line-height:1.6;">
			<?php echo wp_kses_post( $record_display . $file_display ); ?>
		</span>
	</td>

	<!-- Actions -->
	<td class="column-actions actions"
		data-label="<?php echo esc_attr( Config::ACTIONS_LABEL ); ?>">
		<?php
		$item        = $msg;
		$search_term = $_REQUEST['s'] ?? '';

		// Determine status from folder parameter
		$folder_param = $_REQUEST['folder'] ?? 'main';
		if ( $folder_param === 'spam' ) {
			$current_status = Config::STATUS_SPAM;
		} elseif ( $folder_param === 'archived' ) {
			$current_status = Config::STATUS_ARCHIVED;
		} else {
			$current_status = 'all';
		}

		require CONTACTINBOX_PATH . Config::TEMPLATE_ADMIN_PART . 'inbox-actions.php';
		?>
	</td>
</tr>
