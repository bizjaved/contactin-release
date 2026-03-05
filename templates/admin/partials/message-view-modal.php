<?php
/**
 * Message View Modal Template – unified with global/log modal rhythm
 */
use ContactInbox\Core\Config;
use ContactInbox\Core\AttachmentRenderer;
use ContactInbox\Core\Message;
use ContactInbox\Core\CRMStatus;
use ContactInbox\Admin\Helpers\InboxActionHelper;

if ( ! defined( 'ABSPATH' ) ) exit;

$nonce     = wp_create_nonce(Config::NONCE_ACTION);
$gdprNonce = wp_create_nonce(Config::GDPR_NONCE_ACTION);

// Set filter_status early so it's available for action helper
$filter_status = $filter_status ?? 'all';

// Get current context and available actions - use filter_status parameter
$available_actions = InboxActionHelper::get_available_actions($filter_status);

$current_folder = sanitize_key($_GET['folder'] ?? '');
$is_spam_context = ($filter_status ?? 'all') === Config::STATUS_SPAM || $current_folder === 'spam';
$is_spam_message = $is_spam_context || (
    isset($message->recaptcha_score)
    && $message->recaptcha_score !== null
    && (float) $message->recaptcha_score < Config::SPAM_SCORE_THRESHOLD
);
$effective_intent_category = $is_spam_message
    ? \ContactInbox\Core\IntentClassifier::CATEGORY_SPAM
    : ($message->intent_category ?? \ContactInbox\Core\IntentClassifier::CATEGORY_UNCLASSIFIED);

if ( ! ($message instanceof Message) ) {
    ?>
    <div id="cin-message-view-modal"
        class="cin-breathing-modal cin-message-view-modal"
        data-s="<?php echo esc_attr($s); ?>"
        data-status="<?php echo esc_attr($filter_status); ?>">
        data-total="<?php echo esc_attr($total); ?>">
        <div class="contactin-modal-content">
            <!-- Header -->
            <div class="contactin-modal-header">
                <h2 class="cin-modal-title"><?php esc_html_e( 'Message Details', Config::TEXTDOMAIN ); ?></h2>
                <?php if (in_array('classification', $available_actions, true)) : ?>
                <button type="button" class="cin-btn cin-btn-icon cin-btn-secondary cin-action-classification"
                        data-id="<?php echo esc_attr($id); ?>"
                        data-current-category="<?php echo esc_attr($effective_intent_category); ?>"
                        aria-label="<?php esc_attr_e('Change classification', Config::TEXTDOMAIN); ?>"
                        title="<?php esc_attr_e('Change Classification', Config::TEXTDOMAIN); ?>">
                    <span class="dashicons dashicons-tag"></span>
                </button>
                <?php endif; ?>
                <div class="cin-modal-keyboard-hint">
                    <small>
                        <?php printf(
                            esc_html__( 'Keyboard: %s Read | %s Delete | %s GDPR | %s Previous | %s Next | %s Close | %s Help', Config::TEXTDOMAIN ),
                            '<kbd>R</kbd>',
                            '<kbd>D</kbd>',
                            '<kbd>G</kbd>',
                            '<kbd>←</kbd>',
                            '<kbd>→</kbd>',
                            '<kbd>Esc</kbd>',
                            '<kbd>?</kbd>'
                        ); ?>
                    </small>
                </div>
            </div>

            <!-- Meta fields (like email headers) -->
            <div class="contactin-modal-meta">
                <div class="contactin-meta-item">
                    <span class="contactin-meta-label"><?php esc_html_e( 'From:', Config::TEXTDOMAIN ); ?></span>
                    <span class="contactin-meta-value">
                        <?php echo esc_html( $name ); ?> &lt;<?php echo esc_html( $email ); ?>&gt;
                    </span>
                </div>
                <div class="contactin-meta-item">
                    <span class="contactin-meta-label"><?php esc_html_e( 'Subject:', Config::TEXTDOMAIN ); ?></span>
                    <span class="contactin-meta-value"><?php echo esc_html( $subject ); ?></span>
                </div>
                <div class="contactin-meta-item">
                    <span class="contactin-meta-label"><?php esc_html_e( 'Received:', Config::TEXTDOMAIN ); ?></span>
                    <span class="contactin-meta-value"><?php echo esc_html( $date ); ?></span>
                </div>
                <div class="contactin-meta-item">
                    <span class="contactin-meta-label"><?php esc_html_e( 'Status:', Config::TEXTDOMAIN ); ?></span>
                    <span class="status-badge cin-read-status <?php echo $status === 'unread' ? 'status-unread' : 'status-read'; ?>">
                        <?php echo $status === 'unread'
                            ? esc_html__( 'Unread', Config::TEXTDOMAIN )
                            : esc_html__( 'Read', Config::TEXTDOMAIN ); ?>
                    </span>
                </div>

                <!-- Folder and Classification -->
                <div class="contactin-meta-item">
                    <span class="contactin-meta-label"><?php esc_html_e( 'Folder & Classification:', Config::TEXTDOMAIN ); ?></span>
                    <div class="cin-folder-classification">
                        <?php
                        // Determine folder from message status
                        $folder_label = 'Main';
                        $folder_class = 'cin-folder-main';
                        if ( $message->status === Config::STATUS_SPAM || in_array( Config::STATUS_SPAM, array_keys( [$message->status] ), true ) ) {
                            $folder_label = 'Spam';
                            $folder_class = 'cin-folder-spam';
                        } elseif ( $message->status === Config::STATUS_ARCHIVED || in_array( Config::STATUS_ARCHIVED, array_keys( [$message->status] ), true ) ) {
                            $folder_label = 'Archives';
                            $folder_class = 'cin-folder-archived';
                        }
                        ?>
                        <span class="cin-folder-badge <?php echo esc_attr($folder_class); ?>">
                            <?php echo esc_html($folder_label); ?>
                        </span>

                        <!-- Classification Badge -->
                        <?php
                        $settings = \ContactInbox\Core\Settings::get_settings();
                        if ( !empty($settings['intent_enable']) && $effective_intent_category !== 'unclassified' ):
                            $intent_label = \ContactInbox\Core\IntentClassifier::get_category_label($effective_intent_category);
                            $intent_color = \ContactInbox\Core\IntentClassifier::get_category_color($effective_intent_category);
                        ?>
                            <span class="cin-intent-badge cin-intent-<?php echo esc_attr($intent_color); ?>">
                                <?php echo esc_html($intent_label); ?>
                            </span>
                        <?php else: ?>
                            <span class="cin-classification-unset"><?php esc_html_e( 'Not Classified', Config::TEXTDOMAIN ); ?></span>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="contactin-meta-item">
                    <span class="contactin-meta-label"><?php esc_html_e( 'Email Sync:', Config::TEXTDOMAIN ); ?></span>
                    <?php
                    // Admin Email
                    $admin_status = $message->admin_email_status ?? Config::EMAIL_PENDING;
                    $admin_label = 'Pending';
                    $admin_display = 'Admin (Pending)';
                    if ($admin_status === Config::EMAIL_SENT) {
                        $admin_label = 'Sent';
                        $admin_display = 'Admin (Sent)';
                    } elseif ($admin_status === Config::EMAIL_FAILED) {
                        $admin_label = 'Failed';
                        $admin_display = 'Admin (Failed)';
                    } elseif ($admin_status === Config::EMAIL_PROCESSING) {
                        $admin_label = 'Processing';
                        $admin_display = 'Admin (Processing)';
                    } elseif ($admin_status === Config::EMAIL_SKIPPED) {
                        $admin_label = 'Not Applicable';
                        $admin_display = 'Admin (Not Applicable)';
                    }

                    // User Email
                    $user_status = $message->user_email_status ?? Config::EMAIL_PENDING;
                    $user_label = 'Pending';
                    $user_display = 'User (Pending)';
                    if ($user_status === Config::EMAIL_SENT) {
                        $user_label = 'Sent';
                        $user_display = 'User (Sent)';
                    } elseif ($user_status === Config::EMAIL_FAILED) {
                        $user_label = 'Failed';
                        $user_display = 'User (Failed)';
                    } elseif ($user_status === Config::EMAIL_PROCESSING) {
                        $user_label = 'Processing';
                        $user_display = 'User (Processing)';
                    } elseif ($user_status === Config::EMAIL_SKIPPED) {
                        $user_label = 'Not Applicable';
                        $user_display = 'User (Not Applicable)';
                    }
                    ?>
                    <span class="delivery-badge admin" title="<?php echo esc_attr('Admin Email: ' . $admin_label); ?>"><?php echo esc_html($admin_display); ?></span>
                    <span class="delivery-badge user" title="<?php echo esc_attr('User Email: ' . $user_label); ?>"><?php echo esc_html($user_display); ?></span>
                </div>
                <div class="contactin-meta-item">
                    <span class="contactin-meta-label"><?php esc_html_e( 'CRM Sync:', Config::TEXTDOMAIN ); ?></span>
                    <?php
                    // Record sync status (Contact + Case/Task)
                    $crm_status = $message->crm_status ?? Config::CRM_PENDING;
                    $record_label = 'Pending';
                    $record_display = 'Record: Pending';
                    if ($crm_status === Config::CRM_SENT) {
                        $record_label = 'Synced';
                        $record_display = 'Record: Synced';
                    } elseif ($crm_status === Config::CRM_FAILED) {
                        $record_label = 'Failed';
                        $record_display = 'Record: Failed';
                    } elseif ($crm_status === Config::CRM_PROCESSING) {
                        $record_label = 'Processing';
                        $record_display = 'Record: Processing';
                    } elseif ($crm_status === Config::CRM_SKIPPED) {
                        $record_label = 'N/A';
                        $record_display = 'Record: N/A';
                    }
                    ?>
                    <span class="delivery-badge crm" title="<?php echo esc_attr('Record Sync (Contact + Case): ' . $record_label); ?>"><?php echo esc_html($record_display); ?></span>
                    
                    <?php
                    // File sync status (Attachment upload) - only show if message has attachment
                    if (!empty($message->attachment)) {
                        global $wpdb;
                        $attachment_table = $wpdb->prefix . 'contactinbox_sf_attachments';
                        $attachment_status = $wpdb->get_var($wpdb->prepare(
                            "SELECT status FROM {$attachment_table} WHERE message_id = %d ORDER BY created_at DESC LIMIT 1",
                            $message->id
                        ));
                        
                        $file_label = 'Pending';
                        $file_display = 'File: Pending';
                        if ($attachment_status === 'delivered') {
                            $file_label = 'Synced';
                            $file_display = 'File: Synced';
                        } elseif ($attachment_status === 'failed') {
                            $file_label = 'Failed';
                            $file_display = 'File: Failed';
                        } elseif ($attachment_status === 'queued') {
                            $file_label = 'Queued';
                            $file_display = 'File: Queued';
                        } elseif ($attachment_status === 'uploading') {
                            $file_label = 'Uploading';
                            $file_display = 'File: Uploading';
                        }
                        ?>
                        <span class="delivery-badge crm-file" title="<?php echo esc_attr('File Sync (Attachment): ' . $file_label); ?>"><?php echo esc_html($file_display); ?></span>
                    <?php } ?>
                </div>
                <!-- Spam Flag (Phase 1: Gold Standard Logging) -->
                <div class="contactin-meta-item">
                    <span class="contactin-meta-label"><?php esc_html_e( 'Spam Status:', Config::TEXTDOMAIN ); ?></span>
                        <?php
                        $score = isset($message->recaptcha_score) ? (float)$message->recaptcha_score : null;
                        if ($score !== null) {
                            if ($score < 0.5) {
                                ?>
                                <span class="spam-flag-indicator suspicious">
                                    ⚠️ <?php esc_html_e('Flagged as Suspicious', 'contact-inbox-hub'); ?>
                                </span>
                            <?php
                            } else {
                                // Not flagged
                                ?>
                                    ✓ <?php esc_html_e('Clean', 'contact-inbox-hub'); ?>
                                </span>
                                <small style="color:#46b450;"><?php echo esc_html(sprintf(__('reCAPTCHA Score: %.2f', 'contact-inbox-hub'), $score)); ?></small>
                            <?php
                            }
                        } else {
                        ?>
                            <span class="spam-flag-indicator neutral">
                                - <?php esc_html_e('Not Available', 'contact-inbox-hub'); ?>
                            </span>
                        <?php
                        }
                        ?>
                    </span>
                </div>

                <!-- Intent Classification -->
                <?php
                $settings = \ContactInbox\Core\Settings::get_settings();
                if (!empty($settings['intent_enable'])):
                    $intent_label = \ContactInbox\Core\IntentClassifier::get_category_label($effective_intent_category);
                    $intent_color = \ContactInbox\Core\IntentClassifier::get_category_color($effective_intent_category);
                    $intent_keywords = !empty($message->intent_keywords) ? json_decode($message->intent_keywords, true) : [];
                ?>
                <div class="contactin-meta-item">
                    <span class="contactin-meta-label"><?php esc_html_e( 'Intent:', Config::TEXTDOMAIN ); ?></span>
                    <span class="cin-intent-badge cin-intent-<?php echo esc_attr($intent_color); ?>">
                        <?php echo esc_html($intent_label); ?>
                    </span>
                    <?php if (isset($message->intent_confidence) && $message->intent_confidence > 0): ?>
                        <small class="cin-intent-confidence">
                            (<?php echo esc_html(sprintf(__('%.0f%% confidence', Config::TEXTDOMAIN), $message->intent_confidence)); ?>)
                        </small>
                    <?php endif; ?>
                    <?php if (!empty($intent_keywords) && is_array($intent_keywords)): ?>
                        <div class="cin-intent-keywords">
                            <small><?php esc_html_e('Keywords:', Config::TEXTDOMAIN); ?> 
                                <?php echo esc_html(implode(', ', array_slice($intent_keywords, 0, 5))); ?>
                            </small>
                        </div>
                    <?php endif; ?>
                </div>
                <?php endif; ?>

                <?php if ( $attachment ) : ?>
                <div class="contactin-meta-item">
                    <span class="contactin-meta-label"><?php esc_html_e( 'Attachment:', Config::TEXTDOMAIN ); ?></span>
                    <?php
                    echo AttachmentRenderer::link(
                        $id,
                        $attachment['path'],
                        true,
                        true,
                        $attachment['name']
                    );
                    ?>
                </div>
                <?php endif; ?>
            </div>

            <!-- Message body -->
            <div class="contactin-modal-payload">
                <div class="cin-message">
                    <?php echo nl2br( esc_html( $body ) ); ?>
                </div>
            </div>

            <!-- Footer -->
            <div class="contactin-modal-footer">
                <div class="cin-nav-info">
                    <?php
                    printf(
                        _n( 'Message %1$d of %2$d', 'Message %1$d of %2$d', $total, Config::TEXTDOMAIN ),
                        $index + 1,
                        $total
                    );
                    ?>
                </div>

                <div class="cin-footer-actions">
                    <!-- Toggle Read/Unread Icon Button -->
                    <button type="button"
                            class="cin-btn cin-btn-icon cin-toggle-status <?php echo $status === 'read' ? 'cin-btn-secondary' : 'cin-btn-warning'; ?>"
                            data-id="<?php echo esc_attr($id); ?>"
                            data-s="<?php echo esc_attr($s); ?>"
                            data-status="<?php echo esc_attr($filter_status); ?>"
                            data-nonce="<?php echo esc_attr($nonce); ?>"
                            title="<?php echo $status === 'read' ? esc_attr_e('Mark as Unread', Config::TEXTDOMAIN) : esc_attr_e('Mark as Read', Config::TEXTDOMAIN); ?>"
                            aria-label="<?php echo $status === 'read' ? esc_attr_e('Mark as Unread', Config::TEXTDOMAIN) : esc_attr_e('Mark as Read', Config::TEXTDOMAIN); ?>">
                        <span class="dashicons <?php echo $status === 'read' ? 'dashicons-marker' : 'dashicons-yes-alt'; ?>"></span>
                    </button>

                    <!-- Archive Icon Button -->
                    <button type="button"
                            class="cin-btn cin-btn-icon cin-btn-secondary cin-toggle-archive"
                            data-id="<?php echo esc_attr($id); ?>"
                            data-s="<?php echo esc_attr($s); ?>"
                            data-status="<?php echo esc_attr($filter_status); ?>"
                            data-nonce="<?php echo esc_attr($nonce); ?>"
                            title="<?php esc_attr_e('Archive', Config::TEXTDOMAIN); ?>"
                            aria-label="<?php esc_attr_e('Archive message', Config::TEXTDOMAIN); ?>">
                        <span class="dashicons dashicons-archive"></span>
                    </button>

                    <!-- Delete Icon Button -->
                    <button type="button"
                            class="cin-btn cin-btn-icon cin-btn-danger contactin-delete"
                            data-id="<?php echo esc_attr($id); ?>"
                            data-s="<?php echo esc_attr($s); ?>"
                            data-status="<?php echo esc_attr($filter_status); ?>"
                            data-nonce="<?php echo esc_attr($nonce); ?>"
                            data-name="<?php echo esc_attr($name); ?>"
                            data-email="<?php echo esc_attr($email); ?>"
                            data-subject="<?php echo esc_attr($subject); ?>"
                            title="<?php esc_attr_e('Delete', Config::TEXTDOMAIN); ?>"
                            aria-label="<?php esc_attr_e('Delete this message permanently', Config::TEXTDOMAIN); ?>">
                        <span class="dashicons dashicons-trash"></span>
                    </button>

                    <!-- GDPR Delete Link Icon Button -->
                    <button type="button"
                            class="cin-btn cin-btn-icon cin-btn-info contactin-gdpr"
                            data-id="<?php echo esc_attr($id); ?>"
                            data-email="<?php echo esc_attr($email); ?>"
                            data-nonce="<?php echo esc_attr($gdprNonce); ?>"
                            title="<?php esc_attr_e('GDPR Link', Config::TEXTDOMAIN); ?>"
                            aria-label="<?php esc_attr_e('Generate GDPR deletion link', Config::TEXTDOMAIN); ?>">
                        <span class="dashicons dashicons-privacy"></span>
                    </button>
                </div>

                <div class="cin-nav-buttons">
                    <button type="button" class="button cin-nav-prev" <?php disabled( ! $has_prev ); ?>>
                        <?php esc_html_e( 'Previous', Config::TEXTDOMAIN ); ?>
                    </button>
                    <button type="button" class="button button-primary cin-nav-next" <?php disabled( ! $has_next ); ?>>
                        <?php esc_html_e( 'Next', Config::TEXTDOMAIN ); ?>
                    </button>
                </div>
            </div>
        </div>
    </div>
    <?php
    return;
}

// Extract fields
$id         = (int) $message->id;
$name       = $message->get_display_name() ?: '—';
$email      = $message->email ?: '—';
$subject    = $message->subject ?: '—';
$date       = $message->submitted_at
    ? date_i18n('M j, Y @ g:i A', strtotime($message->submitted_at))
    : '—';
$status     = $message->status ?: 'read';
$body       = $message->message ?: '—';
$contact_id = $message->contact_id ?? null;
$attachment = $message->get_attachment();

if (!class_exists('ContactInbox\\Core\\PhoneUtils')) {
    require_once CONTACTINBOX_PATH . 'includes/Core/PhoneUtils.php';
}

$phone_sources = [
    ['label' => __('Mobile', Config::TEXTDOMAIN), 'value' => $message->mobile_phone ?? ''],
    ['label' => __('Home', Config::TEXTDOMAIN),   'value' => $message->home_phone ?? ''],
    ['label' => __('Other', Config::TEXTDOMAIN),  'value' => $message->other_phone ?? ''],
    ['label' => __('Phone', Config::TEXTDOMAIN),  'value' => $message->phone ?? ''],
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

$index         = $index         ?? 0;
$total         = $total         ?? 1;
$has_prev      = $has_prev      ?? false;
$has_next      = $has_next      ?? false;
$s             = $s             ?? '';
?>
<div id="cin-message-view-modal"
     class="cin-breathing-modal cin-message-view-modal"
     data-s="<?php echo esc_attr($s); ?>"
     data-status="<?php echo esc_attr($filter_status); ?>"
     data-id="<?php echo esc_attr($id); ?>"
     data-name="<?php echo esc_attr($name); ?>"
     data-email="<?php echo esc_attr($email); ?>"
     data-subject="<?php echo esc_attr($subject); ?>"
     data-contact-id="<?php echo $contact_id ? esc_attr($contact_id) : ''; ?>">

    <div class="contactin-modal-content">
        <!-- Header -->
        <div class="contactin-modal-header">
            <h2 class="cin-modal-title"><?php esc_html_e( 'Message Details', Config::TEXTDOMAIN ); ?></h2>
            <?php if ($contact_id) : ?>
                <a class="button button-secondary" href="<?php echo esc_url(admin_url('admin.php?page=' . Config::MENU_CONTACTS . '&contact_id=' . intval($contact_id))); ?>">
                    <?php esc_html_e('View contact', Config::TEXTDOMAIN); ?>
                </a>
            <?php endif; ?>
        </div>

<div class="contactin-modal-meta">
    <!-- Line 1: From + Received -->
    <div class="contactin-meta-item">
        <span class="contactin-meta-label"><?php esc_html_e( 'From:', Config::TEXTDOMAIN ); ?></span>
        <span class="contactin-meta-value">
            <?php echo esc_html( $name ); ?> &lt;<?php echo esc_html( $email ); ?>&gt;
        </span>
        <span class="contactin-meta-label"><?php esc_html_e( 'Received:', Config::TEXTDOMAIN ); ?></span>
        <span class="contactin-meta-value"><?php echo esc_html( $date ); ?></span>
    </div>

    <!-- Line 1b: Phones -->
    <div class="contactin-meta-item">
        <span class="contactin-meta-label"><?php esc_html_e( 'Phones:', Config::TEXTDOMAIN ); ?></span>
        <?php if (empty($phones)) : ?>
            <span class="contactin-meta-value" style="color:#666;">&mdash;</span>
        <?php else : ?>
            <span class="contactin-meta-value" style="display:flex;flex-wrap:wrap;gap:6px;align-items:center;">
                <?php foreach ($phones as $phone) : ?>
                    <span class="cin-phone-pill" style="display:inline-flex;align-items:center;gap:6px;padding:3px 8px;border-radius:4px;background:#f6f7f7;border:1px solid #dcdcde;font-size:12px;">
                        <span><?php echo esc_html($phone['display'] . ' (' . $phone['label'] . ')'); ?></span>
                    </span>
                <?php endforeach; ?>
            </span>
        <?php endif; ?>
    </div>

    <!-- Line 2: Status Information (Read + Email + CRM in one line) -->
    <div class="contactin-meta-item">
        <!-- Read Status -->
        <span class="contactin-meta-label"><?php esc_html_e( 'Read:', Config::TEXTDOMAIN ); ?></span>
        <?php
        $is_read = $status !== 'unread';
        $read_label = $is_read ? esc_html__( 'Read', Config::TEXTDOMAIN ) : esc_html__( 'Unread', Config::TEXTDOMAIN );
        ?>
        <span class="status-badge cin-read-status <?php echo $is_read ? 'status-read' : 'status-unread'; ?>" title="<?php echo esc_attr($read_label); ?>">
            <?php echo $read_label; ?>
        </span>

        <!-- Email Notification Status -->
        <span class="contactin-meta-label" style="margin-left:20px;"><?php esc_html_e( 'Email Notification Sent to:', Config::TEXTDOMAIN ); ?></span>
        <?php
        // Admin Email
        $admin_status = $message->admin_email_status ?? Config::EMAIL_PENDING;
        $admin_icon = '⏳';
        $admin_label = 'Pending';
        $admin_display = 'Admin (Pending)';
        $admin_class = 'status-pending';
        if ($admin_status === Config::EMAIL_SENT) {
            $admin_icon = '✅';
            $admin_label = 'Sent';
            $admin_display = 'Admin (Sent)';
            $admin_class = 'status-sent';
        } elseif ($admin_status === Config::EMAIL_FAILED) {
            $admin_icon = '❌';
            $admin_label = 'Failed';
            $admin_display = 'Admin (Failed)';
            $admin_class = 'status-failed';
        } elseif ($admin_status === Config::EMAIL_PROCESSING) {
            $admin_icon = '🔄';
            $admin_label = 'Processing';
            $admin_display = 'Admin (Processing)';
            $admin_class = 'status-processing';
        } elseif ($admin_status === Config::EMAIL_SKIPPED) {
            $admin_icon = '➖';
            $admin_label = 'Not Applicable';
            $admin_display = 'Admin (Not Applicable)';
            $admin_class = 'status-skipped';
        }

        // User Email
        $user_status = $message->user_email_status ?? Config::EMAIL_PENDING;
        $user_icon = '⏳';
        $user_label = 'Pending';
        $user_display = 'User (Pending)';
        $user_class = 'status-pending';
        if ($user_status === Config::EMAIL_SENT) {
            $user_icon = '✅';
            $user_label = 'Sent';
            $user_display = 'User (Sent)';
            $user_class = 'status-sent';
        } elseif ($user_status === Config::EMAIL_FAILED) {
            $user_icon = '❌';
            $user_label = 'Failed';
            $user_display = 'User (Failed)';
            $user_class = 'status-failed';
        } elseif ($user_status === Config::EMAIL_PROCESSING) {
            $user_icon = '🔄';
            $user_label = 'Processing';
            $user_display = 'User (Processing)';
            $user_class = 'status-processing';
        } elseif ($user_status === Config::EMAIL_SKIPPED) {
            $user_icon = '➖';
            $user_label = 'Not Applicable';
            $user_display = 'User (Not Applicable)';
            $user_class = 'status-skipped';
        }
        ?>
        <span class="status-badge <?php echo $admin_class; ?>" title="<?php echo esc_attr('Admin Email: ' . $admin_label); ?>" style="display:inline-flex;align-items:center;gap:4px;">
            <span style="font-size:16px;"><?php echo esc_html($admin_icon); ?></span>
            <span style="font-size:12px;"><?php echo esc_html($admin_display); ?></span>
        </span>
        <span class="status-badge <?php echo $user_class; ?>" title="<?php echo esc_attr('User Email: ' . $user_label); ?>" style="display:inline-flex;align-items:center;gap:4px;">
            <span style="font-size:16px;"><?php echo esc_html($user_icon); ?></span>
            <span style="font-size:12px;"><?php echo esc_html($user_display); ?></span>
        </span>

        <!-- CRM Sync Status (Split: Record + File) -->
        <span class="contactin-meta-label" style="margin-left:20px;"><?php esc_html_e( 'CRM Sync:', Config::TEXTDOMAIN ); ?></span>
        <?php
        // Record sync status (Contact + Case/Task)
        $crm_status = $message->crm_status ?? Config::CRM_PENDING;
        $record_icon = '⏳';
        $record_label = 'Pending';
        $record_display = 'Record: Pending';
        $record_class = 'status-pending';
        if ($crm_status === Config::CRM_SENT) {
            $record_icon = '✅';
            $record_label = 'Synced';
            $record_display = 'Record: Synced';
            $record_class = 'status-synced';
        } elseif ($crm_status === Config::EMAIL_FAILED) {
            $record_icon = '❌';
            $record_label = 'Failed';
            $record_display = 'Record: Failed';
            $record_class = 'status-failed';
        } elseif ($crm_status === Config::EMAIL_PROCESSING) {
            $record_icon = '🔄';
            $record_label = 'Processing';
            $record_display = 'Record: Processing';
            $record_class = 'status-processing';
        } elseif ($crm_status === Config::EMAIL_SKIPPED) {
            $record_icon = '➖';
            $record_label = 'Not Applicable';
            $record_display = 'Record: N/A';
            $record_class = 'status-skipped';
        }
        ?>
        <span class="status-badge <?php echo $record_class; ?>" title="<?php echo esc_attr('Record Sync (Contact + Case): ' . $record_label); ?>" style="display:inline-flex;align-items:center;gap:4px;">
            <span style="font-size:16px;"><?php echo esc_html($record_icon); ?></span>
            <span style="font-size:12px;"><?php echo esc_html($record_display); ?></span>
        </span>

        <?php
        // File sync status (Attachment upload) - only show if message has attachment
        if (!empty($message->attachment)) {
            global $wpdb;
            $attachment_table = $wpdb->prefix . 'contactinbox_sf_attachments';
            $attachment_status = $wpdb->get_var($wpdb->prepare(
                "SELECT status FROM {$attachment_table} WHERE message_id = %d ORDER BY created_at DESC LIMIT 1",
                $message->id
            ));
            
            $file_icon = '⏳';
            $file_label = 'Pending';
            $file_display = 'File: Pending';
            $file_class = 'status-pending';
            
            if ($attachment_status === 'delivered') {
                $file_icon = '✅';
                $file_label = 'Synced';
                $file_display = 'File: Synced';
                $file_class = 'status-synced';
            } elseif ($attachment_status === 'failed') {
                $file_icon = '❌';
                $file_label = 'Failed';
                $file_display = 'File: Failed';
                $file_class = 'status-failed';
            } elseif ($attachment_status === 'queued') {
                $file_icon = '⏳';
                $file_label = 'Queued';
                $file_display = 'File: Queued';
                $file_class = 'status-pending';
            } elseif ($attachment_status === 'uploading') {
                $file_icon = '🔄';
                $file_label = 'Uploading';
                $file_display = 'File: Uploading';
                $file_class = 'status-processing';
            }
            ?>
            <span class="status-badge <?php echo $file_class; ?>" title="<?php echo esc_attr('File Sync (Attachment): ' . $file_label); ?>" style="display:inline-flex;align-items:center;gap:4px;">
                <span style="font-size:16px;"><?php echo esc_html($file_icon); ?></span>
                <span style="font-size:12px;"><?php echo esc_html($file_display); ?></span>
            </span>
        <?php } ?>
    </div>

    <!-- Line 3: Subject -->
    <div class="contactin-meta-item">
        <span class="contactin-meta-label"><?php esc_html_e( 'Subject:', Config::TEXTDOMAIN ); ?></span>
        <span class="contactin-meta-value"><?php echo esc_html( $subject ); ?></span>
    </div>

    <!-- Optional: Attachment row -->
    <?php if ( $attachment ) : ?>
    <div class="contactin-meta-item">
        <span class="contactin-meta-label"><?php esc_html_e( 'Attachment:', Config::TEXTDOMAIN ); ?></span>
        <?php
        $filename = isset($attachment['name']) ? $attachment['name'] : (isset($attachment['path']) ? basename($attachment['path']) : '');
        $filesize = $attachment['size'] ?? '';
        
        // Build download link with proper data-filename (without size)
        $download_url = wp_nonce_url(
            admin_url('admin-ajax.php?action=ci_download_attachment&id=' . $id),
            Config::NONCE_ACTION,
            'nonce'
        );
        ?>
        <a href="<?php echo esc_url($download_url); ?>" 
           class="cin-attachment-link" 
           data-id="<?php echo esc_attr($id); ?>" 
           data-filename="<?php echo esc_attr($filename); ?>"
           onclick="return false;">
            <span class="dashicons dashicons-paperclip"></span>
            <?php 
            echo esc_html($filename);
            if (!empty($filesize)) {
                echo ' <span style="color:#666;">(' . esc_html($filesize) . ')</span>';
            }
            ?>
        </a>
    </div>
    <?php endif; ?>

            <!-- Message -->
            <div class="contactin-meta-item contactin-meta-message">
                <div class="contactin-meta-label"><?php esc_html_e( 'Message:', Config::TEXTDOMAIN ); ?></div>
                <div class="contactin-meta-value">
                    <div class="cin-message-body"><?php echo nl2br( esc_html( $body ) ); ?></div>
                </div>
            </div>
</div>


        <!-- Footer -->
        <div class="contactin-modal-footer">
            <div class="cin-nav-info">
                <?php
                printf(
                    _n( 'Message %1$d of %2$d', 'Message %1$d of %2$d', $total, Config::TEXTDOMAIN ),
                    $index + 1,
                    $total
                );
                ?>
            </div>

            <div class="cin-footer-actions">
                <!-- Toggle Read/Unread -->
                <?php if (in_array('toggle_status', $available_actions, true)) : ?>
                <button type="button"
                        class="cin-btn cin-btn-icon cin-toggle-status <?php echo $status === 'read' ? 'cin-btn-secondary' : 'cin-btn-warning'; ?>"
                        data-id="<?php echo esc_attr($id); ?>"
                        data-s="<?php echo esc_attr($s); ?>"
                        data-status="<?php echo esc_attr($filter_status); ?>"
                        data-nonce="<?php echo esc_attr($nonce); ?>"
                        title="<?php echo $status === 'read' ? esc_attr_e('Mark as Unread', Config::TEXTDOMAIN) : esc_attr_e('Mark as Read', Config::TEXTDOMAIN); ?>"
                        aria-label="<?php echo $status === 'read' ? esc_attr_e('Mark as Unread', Config::TEXTDOMAIN) : esc_attr_e('Mark as Read', Config::TEXTDOMAIN); ?>">
                    <span class="dashicons <?php echo $status === 'read' ? 'dashicons-marker' : 'dashicons-yes-alt'; ?>"></span>
                </button>
                <?php endif; ?>

                <!-- Archive (Main tab only) -->
                <?php if (in_array('archive', $available_actions, true)) : ?>
                <button type="button"
                        class="cin-btn cin-btn-icon cin-btn-secondary cin-toggle-archive"
                        data-id="<?php echo esc_attr($id); ?>"
                        data-s="<?php echo esc_attr($s); ?>"
                        data-status="<?php echo esc_attr($filter_status); ?>"
                        data-nonce="<?php echo esc_attr($nonce); ?>"
                        data-action="archive"
                        title="<?php esc_attr_e('Archive', Config::TEXTDOMAIN); ?>"
                        aria-label="<?php esc_attr_e('Archive message', Config::TEXTDOMAIN); ?>">
                    <span class="dashicons dashicons-archive"></span>
                </button>
                <?php endif; ?>

                <!-- Unarchive (Archive tab only) -->
                <?php if (in_array('unarchive', $available_actions, true)) : ?>
                <button type="button"
                        class="cin-btn cin-btn-icon cin-btn-info cin-toggle-archive"
                        data-id="<?php echo esc_attr($id); ?>"
                        data-s="<?php echo esc_attr($s); ?>"
                        data-status="<?php echo esc_attr($filter_status); ?>"
                        data-nonce="<?php echo esc_attr($nonce); ?>"
                        data-action="unarchive"
                        title="<?php esc_attr_e('Unarchive', Config::TEXTDOMAIN); ?>"
                        aria-label="<?php esc_attr_e('Restore message', Config::TEXTDOMAIN); ?>">
                    <span class="dashicons dashicons-undo"></span>
                </button>
                <?php endif; ?>

                <!-- Mark as Spam (Main tab only) -->
                <?php if (in_array('spam', $available_actions, true)) : ?>
                <button type="button"
                        class="cin-btn cin-btn-icon cin-btn-danger cin-toggle-spam"
                        data-id="<?php echo esc_attr($id); ?>"
                        data-s="<?php echo esc_attr($s); ?>"
                        data-status="<?php echo esc_attr($filter_status); ?>"
                        data-nonce="<?php echo esc_attr($nonce); ?>"
                        data-action="spam"
                        title="<?php esc_attr_e('Mark as Spam', Config::TEXTDOMAIN); ?>"
                        aria-label="<?php esc_attr_e('Mark message as spam', Config::TEXTDOMAIN); ?>">
                    <span class="dashicons dashicons-warning"></span>
                </button>
                <?php endif; ?>

                <!-- Not Spam (Spam tab only) -->
                <?php if (in_array('not_spam', $available_actions, true)) : ?>
                <button type="button"
                        class="cin-btn cin-btn-icon cin-btn-secondary cin-toggle-spam"
                        data-id="<?php echo esc_attr($id); ?>"
                        data-s="<?php echo esc_attr($s); ?>"
                        data-status="<?php echo esc_attr($filter_status); ?>"
                        data-nonce="<?php echo esc_attr($nonce); ?>"
                        data-action="not_spam"
                        title="<?php esc_attr_e('Not Spam', Config::TEXTDOMAIN); ?>"
                        aria-label="<?php esc_attr_e('Mark as not spam', Config::TEXTDOMAIN); ?>">
                    <span class="dashicons dashicons-yes"></span>
                </button>
                <?php endif; ?>

                <!-- Delete -->
                <?php if (in_array('delete', $available_actions, true)) : ?>
                <button type="button"
                        class="cin-btn cin-btn-icon cin-btn-danger contactin-delete"
                        data-id="<?php echo esc_attr($id); ?>"
                        data-s="<?php echo esc_attr($s); ?>"
                        data-status="<?php echo esc_attr($filter_status); ?>"
                        data-nonce="<?php echo esc_attr($nonce); ?>"
                        data-name="<?php echo esc_attr($name); ?>"
                        data-email="<?php echo esc_attr($email); ?>"
                        data-subject="<?php echo esc_attr($subject); ?>"
                        title="<?php esc_attr_e('Delete', Config::TEXTDOMAIN); ?>"
                        aria-label="<?php esc_attr_e('Delete this message permanently', Config::TEXTDOMAIN); ?>">
                    <span class="dashicons dashicons-trash"></span>
                </button>
                <?php endif; ?>
            </div>

            <div class="cin-nav-buttons">
                <button type="button" class="button cin-nav-prev" <?php disabled( ! $has_prev ); ?>>
                    <?php esc_html_e( 'Previous', Config::TEXTDOMAIN ); ?>
                </button>
                <button type="button" class="button button-primary cin-nav-next" <?php disabled( ! $has_next ); ?>>
                    <?php esc_html_e( 'Next', Config::TEXTDOMAIN ); ?>
                </button>
            </div>
        </div>
    </div>
</div>
