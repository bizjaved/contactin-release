<?php
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals, WordPress.Security.EscapeOutput.OutputNotEscaped

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

/**
 * Partial: Inbox Row – Updated
 *
 * Expects $msg (object) in scope.
 */

use ContactInbox\Core\Config;
use ContactInbox\Core\AttachmentRenderer; 
use ContactInbox\Core\CRMStatus;

$contactinbox_request_search = filter_input(INPUT_GET, 's', FILTER_UNSAFE_RAW);
if (null === $contactinbox_request_search || false === $contactinbox_request_search) {
    $contactinbox_request_search = filter_input(INPUT_POST, 's', FILTER_UNSAFE_RAW);
}
$contactinbox_request_status = filter_input(INPUT_GET, 'status', FILTER_UNSAFE_RAW);
if (null === $contactinbox_request_status || false === $contactinbox_request_status) {
    $contactinbox_request_status = filter_input(INPUT_POST, 'status', FILTER_UNSAFE_RAW);
}
$contactinbox_request_folder = filter_input(INPUT_GET, 'folder', FILTER_UNSAFE_RAW);

$contactinbox_search_attr = sanitize_text_field(wp_unslash((string) ($contactinbox_request_search ?? '')));
$contactinbox_status_attr = sanitize_key(wp_unslash((string) ($contactinbox_request_status ?? 'all')));
$contactinbox_folder = sanitize_key(wp_unslash((string) ($contactinbox_request_folder ?? '')));

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

$is_unread = ($msg->status === 'unread');
$item = $msg;

// Build phone display list with basic de-duplication
if (!class_exists('ContactInbox\\Core\\PhoneUtils')) {
    require_once CONTACTINBOX_PATH . 'includes/Core/PhoneUtils.php';
}

$phone_sources = [
    ['label' => __('Mobile', 'contact-inbox'), 'value' => $msg->mobile_phone ?? ''],
    ['label' => __('Home', 'contact-inbox'),   'value' => $msg->home_phone ?? ''],
    ['label' => __('Other', 'contact-inbox'),  'value' => $msg->other_phone ?? ''],
    ['label' => __('Phone', 'contact-inbox'),  'value' => $msg->phone ?? ''],
];

$phones = [];
$seen = [];
foreach ($phone_sources as $src) {
    $raw = trim((string) $src['value']);
    if ($raw === '') {
        continue;
    }
    $normalized = \ContactInbox\Core\PhoneUtils::normalize($raw);
    $duplicate = false;
    foreach ($seen as $prev) {
        if (\ContactInbox\Core\PhoneUtils::are_equal($normalized, $prev)) {
            $duplicate = true;
            break;
        }
    }
    if ($duplicate) {
        continue;
    }
    $seen[] = $normalized;
    $phones[] = [
        'label' => $src['label'],
        'display' => \ContactInbox\Core\PhoneUtils::format($normalized, 'international'),
    ];
}
?>

<tr id="contactin-row-<?php echo esc_attr($msg->id); ?>"
    class="contactin-inbox-row <?php echo $is_unread ? 'unread' : 'read'; ?>"
    data-id="<?php echo esc_attr($msg->id); ?>"
    data-s="<?php echo esc_attr($contactinbox_search_attr); ?>"
    data-status="<?php echo esc_attr($contactinbox_status_attr); ?>"
    style="<?php echo $is_unread ? 'font-weight:700;' : ''; ?>">

    <!-- Checkbox -->
    <td class="manage-column column-cb check-column">
        <input type="checkbox" name="message_ids[]" value="<?php echo esc_attr($msg->id); ?>">
    </td>

    <!-- From (linked to contact when available) -->
    <td class="column-user" data-label="<?php esc_attr_e('From', 'contact-inbox'); ?>">
        <?php
        $contact_url = !empty($msg->contact_id)
            ? admin_url('admin.php?page=' . \ContactInbox\Core\Config::MENU_CONTACTS . '&contact_id=' . intval($msg->contact_id))
            : '';
        $from_label = trim(($msg->get_display_name() ?? '') . ' <' . ($msg->email ?? '') . '>');
        if ($contact_url) {
            echo '<a href="' . esc_url($contact_url) . '">' . esc_html($from_label) . '</a>';
        } else {
            echo esc_html($from_label);
        }
        ?>
    </td>

    <!-- Subject -->
    <td class="column-subject" data-label="<?php esc_attr_e('Subject', 'contact-inbox'); ?>">
        <?php
        // Intent Badge
        $settings = \ContactInbox\Core\Settings::get_settings();
        $current_folder = $contactinbox_folder;
        $is_spam_context = ($current_status ?? 'all') === Config::STATUS_SPAM || $current_folder === 'spam';
        $is_spam_message = $is_spam_context || (
            isset($msg->recaptcha_score)
            && $msg->recaptcha_score !== null
            && (float) $msg->recaptcha_score < Config::SPAM_SCORE_THRESHOLD
        );
        $effective_intent = $is_spam_message
            ? \ContactInbox\Core\IntentClassifier::CATEGORY_SPAM
            : ($msg->intent_category ?? 'unclassified');

        if (!empty($settings['intent_enable']) && $effective_intent !== 'unclassified'):
            $intent_label = \ContactInbox\Core\IntentClassifier::get_category_label($effective_intent);
            $intent_color = \ContactInbox\Core\IntentClassifier::get_category_color($effective_intent);
        ?>
            <span class="cin-intent-badge cin-intent-<?php echo esc_attr($intent_color); ?>" 
                  title="<?php echo esc_attr(sprintf(__('Intent: %s (Confidence: %.0f%%)', 'contact-inbox'), $intent_label, $msg->intent_confidence ?? 0)); ?>">
                <?php echo esc_html($intent_label); ?>
            </span>
        <?php endif; ?>
        <?php
        $subject_text = wp_strip_all_tags($msg->subject ?? '');
        $subject_display = mb_strlen($subject_text) > 60 ? mb_substr($subject_text, 0, 60) . '…' : $subject_text;
        // Highlight search term if present
        echo ! empty( $search_term ) ? ci_highlight_search_term( $subject_display, $search_term ) : esc_html( $subject_display );
        ?>
    </td>

    <!-- Message preview -->
    <td class="column-message" data-label="<?php esc_attr_e('Message', 'contact-inbox'); ?>">
        <?php
        $msg_text = wp_strip_all_tags($msg->message ?? '');
        $msg_display = mb_strlen($msg_text) > 80 ? mb_substr($msg_text, 0, 80) . '…' : $msg_text;
        // Highlight search term if present
        echo ! empty( $search_term ) ? ci_highlight_search_term( $msg_display, $search_term ) : esc_html( $msg_display );
        ?>
    </td>

    <!-- Attachment (icon + filename) -->
    <td class="column-attachment" data-label="<?php esc_attr_e('Attachment', 'contact-inbox'); ?>">
        <?php if ( $attachment = $msg->get_attachment() ) : ?>
            <?php
            $filename = isset($attachment['name']) ? $attachment['name'] : (isset($attachment['path']) ? basename($attachment['path']) : '');
            $filesize = $attachment['size'] ?? '';
            $display_name = $filename;
            if (!empty($filesize)) {
                $display_name .= ' (' . $filesize . ')';
            }
            ?>
            <a href="<?php echo esc_url( admin_url( 'admin-ajax.php?action=ci_download_attachment&id=' . $msg->id ) ); ?>"
            class="cin-attachment-link"
            data-id="<?php echo esc_attr( $msg->id ); ?>"
            data-filename="<?php echo esc_attr( $filename ); ?>"
            title="<?php echo esc_attr( $display_name ); ?>"
            onclick="return false;">
                <span class="dashicons dashicons-paperclip" aria-label="<?php echo esc_attr( $filename ); ?>"></span>
                <span class="attachment-filename"><?php echo esc_html( $display_name ); ?></span>
            </a>
        <?php endif; ?>
    </td>

    <!-- Date -->
    <td class="column-date" data-label="<?php esc_attr_e('Date', 'contact-inbox'); ?>">
        <?php
        $date = $msg->submitted_at ?? '';
        echo esc_html($date ? date_i18n('M j, Y g:i A', strtotime($date)) : '');
        ?>
    </td>

    <!-- Email Sync Status (Admin + User) -->
    <td class="column-email-sync" data-label="<?php esc_attr_e('Email Sync', 'contact-inbox'); ?>">
        <?php
        $admin_status = $msg->admin_email_status ?? Config::EMAIL_PENDING;
        $user_status  = $msg->user_email_status ?? Config::EMAIL_PENDING;

        $admin_label_text = 'Pending';
        $admin_icon = '⏳';
        $admin_badge_class = 'status-pending';
        if ($admin_status === Config::EMAIL_SENT) {
            $admin_label_text = 'Sent';
            $admin_icon = '✅';
            $admin_badge_class = 'status-sent';
        } elseif ($admin_status === Config::EMAIL_FAILED) {
            $admin_label_text = 'Failed';
            $admin_icon = '❌';
            $admin_badge_class = 'status-failed';
        } elseif ($admin_status === Config::EMAIL_PROCESSING) {
            $admin_label_text = 'Processing';
            $admin_icon = '🔄';
            $admin_badge_class = 'status-processing';
        } elseif ($admin_status === Config::EMAIL_SKIPPED) {
            $admin_label_text = 'N/A';
            $admin_icon = '➖';
            $admin_badge_class = 'status-skipped';
        }

        $user_label_text = 'Pending';
        $user_icon = '⏳';
        $user_badge_class = 'status-pending';
        if ($user_status === Config::EMAIL_SENT) {
            $user_label_text = 'Sent';
            $user_icon = '✅';
            $user_badge_class = 'status-sent';
        } elseif ($user_status === Config::EMAIL_FAILED) {
            $user_label_text = 'Failed';
            $user_icon = '❌';
            $user_badge_class = 'status-failed';
        } elseif ($user_status === Config::EMAIL_PROCESSING) {
            $user_label_text = 'Processing';
            $user_icon = '🔄';
            $user_badge_class = 'status-processing';
        } elseif ($user_status === Config::EMAIL_SKIPPED) {
            $user_label_text = 'N/A';
            $user_icon = '➖';
            $user_badge_class = 'status-skipped';
        }
        ?>
        <div class="delivery-items" style="display:flex;gap:6px;align-items:center;flex-wrap:wrap;">
            <span class="delivery-badge <?php echo $admin_badge_class; ?>" title="<?php echo esc_attr('Admin Email: ' . $admin_label_text); ?>" style="display:inline-flex;align-items:center;gap:3px;font-size:11px;padding:2px 6px;border-radius:3px;">
                <span><?php echo esc_html($admin_icon); ?></span>
                <span><?php echo esc_html('A: ' . $admin_label_text); ?></span>
            </span>
            <span class="delivery-badge <?php echo $user_badge_class; ?>" title="<?php echo esc_attr('User Email: ' . $user_label_text); ?>" style="display:inline-flex;align-items:center;gap:3px;font-size:11px;padding:2px 6px;border-radius:3px;">
                <span><?php echo esc_html($user_icon); ?></span>
                <span><?php echo esc_html('U: ' . $user_label_text); ?></span>
            </span>
        </div>
    </td>

    <!-- CRM Sync Status (Split: Record + File) -->
    <td class="column-crm-sync" data-label="<?php esc_attr_e('CRM Sync', 'contact-inbox'); ?>">
        <?php
        // Record sync status (Contact + Case/Task)
        $crm_status = $msg->crm_status ?? Config::CRM_PENDING;
        $record_label = 'Pending';
        $record_display = '⏳ Record: Pending';
        $record_title = 'Record Sync (Contact + Case): Pending';
        if ($crm_status === Config::CRM_SENT) {
            $record_label = 'Synced';
            $record_display = '✅ Record: Synced';
            $record_title = 'Record Sync (Contact + Case): Synced';
        } elseif ($crm_status === Config::CRM_FAILED) {
            $record_label = 'Failed';
            $record_display = '❌ Record: Failed';
            $record_title = 'Record Sync (Contact + Case): Failed';
        } elseif ($crm_status === Config::CRM_PROCESSING) {
            $record_label = 'Processing';
            $record_display = '🔄 Record: Processing';
            $record_title = 'Record Sync (Contact + Case): Processing';
        } elseif ($crm_status === Config::CRM_SKIPPED) {
            $record_label = 'N/A';
            $record_display = '➖ Record: None';
            $record_title = 'Record Sync: Not Applicable';
        }
        
        // File sync status (Attachment upload) - only show if message has attachment
        $file_display = '';
        $file_title = '';
        if (!empty($msg->attachment)) {
            global $wpdb;
            
            // Check queue status FIRST (unified queue table takes priority)
            $queue_attachment = $wpdb->get_row($wpdb->prepare(
                "SELECT status FROM {$wpdb->prefix}queue WHERE type = %s AND data LIKE %s ORDER BY created_at DESC LIMIT 1",
                'attachment_retry',
                '%"message_id":' . (int)$msg->id . '%'
            ));
            
            if ($queue_attachment) {
                $queue_status = $queue_attachment->status;
                if ($queue_status === 'pending') {
                    $file_display = '<br/>⏳ File: Queued';
                    $file_title = ' | File Sync: Queued for Upload';
                } elseif ($queue_status === 'processing') {
                    $file_display = '<br/>🔄 File: Uploading';
                    $file_title = ' | File Sync: Upload in Progress';
                } elseif ($queue_status === 'retry') {
                    $file_display = '<br/>🔄 File: Retrying';
                    $file_title = ' | File Sync: Retry in Progress';
                } elseif ($queue_status === 'completed') {
                    $file_display = '<br/>✅ File: Synced';
                    $file_title = ' | File Sync: Uploaded Successfully';
                } elseif ($queue_status === 'dlq') {
                    $file_display = '<br/>❌ File: Failed (DLQ)';
                    $file_title = ' | File Sync: Permanently Failed - Check Logs';
                }
            } else {
                // Fallback: Query attachment status from sf_attachments table (legacy)
                $attachment_table = $wpdb->prefix . 'contactinbox_sf_attachments';
                $attachment_status = $wpdb->get_var($wpdb->prepare(
                    "SELECT status FROM {$attachment_table} WHERE message_id = %d ORDER BY created_at DESC LIMIT 1",
                    $msg->id
                ));
                
                if ($attachment_status === 'delivered') {
                    $file_display = '<br/>✅ File: Synced';
                    $file_title = ' | File Sync: Uploaded Successfully';
                } elseif ($attachment_status === 'failed') {
                    $file_display = '<br/>❌ File: Failed';
                    $file_title = ' | File Sync: Upload Failed';
                } elseif ($attachment_status === 'queued') {
                    $file_display = '<br/>⏳ File: Queued';
                    $file_title = ' | File Sync: Queued for Upload';
                } elseif ($attachment_status === 'uploading') {
                    $file_display = '<br/>🔄 File: Uploading';
                    $file_title = ' | File Sync: Upload in Progress';
                } else {
                    // No status yet or pending
                    $file_display = '<br/>⏳ File: Pending';
                    $file_title = ' | File Sync: Pending Upload';
                }
            }
        }
        ?>
        <span class="delivery-item crm-sync-item" title="<?php echo esc_attr($record_title . $file_title); ?>" style="font-size:12px;white-space:nowrap;display:inline-block;line-height:1.6;">
            <?php echo wp_kses_post($record_display . $file_display); ?>
        </span>
    </td>

    <!-- Actions -->
    <td class="column-actions actions"
        data-label="<?php esc_attr_e( Config::ACTIONS_LABEL, 'contact-inbox' ); ?>">
        <?php
        $item           = $msg;
        $search_term    = $contactinbox_search_attr;
        
        // Determine status from folder parameter
        $folder_param = $contactinbox_folder !== '' ? $contactinbox_folder : 'main';
        if ($folder_param === 'spam') {
            $current_status = Config::STATUS_SPAM;
        } elseif ($folder_param === 'archived') {
            $current_status = Config::STATUS_ARCHIVED;
        } else {
            $current_status = 'all';
        }

        include CONTACTINBOX_PATH . Config::TEMPLATE_ADMIN_PART . 'inbox-actions.php';
        ?>
    </td>
</tr>
