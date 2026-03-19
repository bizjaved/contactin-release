<?php
namespace ContactInbox\Core;


final class Config {
    public const MENU_CRM_LOG = 'contactinbox-crm-log';

    // Database table names
    public const TABLE_MESSAGES          = 'contactinbox_messages';
    public const TABLE_CONTACTS          = 'contactinbox_contacts';
    public const TABLE_EMAIL_LOG         = 'contactinbox_email_log'; 
        // Removed: public const TABLE_WEBHOOK_LOG
    public const TABLE_REST_LOG          = 'contactinbox_rest_log';
    public const TABLE_SUBMISSION_LOG    = 'contactinbox_submission_log';
    public const TABLE_ERROR_LOG         = 'contactinbox_error_log';
    public const TABLE_QUEUE             = 'contactinbox_queue';
    public const TABLE_QUEUE_LOG         = 'contactinbox_queue_log';
    public const TABLE_DEAD_LETTER       = 'contactinbox_dead_letter_queue';
    public const TABLE_ALERTS            = 'contactinbox_alerts';
    public const TABLE_ANALYTICS_DAILY   = 'contactinbox_analytics_daily';
    public const TABLE_ANALYTICS_EVENTS  = 'contactinbox_analytics_events';
    public const TABLE_CRM_LOG           = 'contactinbox_crm_log';
    public const TABLE_SUBMISSION_ATTEMPTS = 'contactinbox_submission_attempts';
    public const TABLE_CRM_ERRORS        = 'contactinbox_crm_errors';
    public const TABLE_SF_ATTACHMENTS    = 'contactinbox_sf_attachments';
    public const TABLE_LOGS              = 'contactinbox_logs';
    public const TABLE_CRON_LOG          = 'contactinbox_cron_log';
    public const TABLE_GDPR_DELETION_LOG = 'contactinbox_gdpr_deletion_log';

    // Settings keys
    public const SETTING_SEND_ADMIN_NOTIFICATION = 'send_admin_notification';
    public const SETTING_ADMIN_EMAIL             = 'admin_email';
        // Removed: public const MENU_WEBHOOK_LOG, MENU_WEBHOOKS_TEST
    public const SETTING_SEND_USER_COPY          = 'send_user_copy';
    public const SETTING_SUCCESS_MESSAGE         = 'success_message';
    public const SETTING_CONFETTI_ENABLE         = 'confetti_enable';

        // Removed: public const WEBHOOK_LOG_ACTION, WEBHOOK_LOG_NONCE
    // Notification labels
    public const LABEL_ADMIN_NOTIFICATIONS = 'Admin Notifications';
    public const LABEL_USER_COPY           = 'Send Copy to User';

        // Removed: public const ERR_WEBHOOKS_DISABLED
    // General constants
    public const CRON_CLEANUP           = 'contactinbox_cleanup_cron';
    public const CRON_GDPR              = 'contactinbox_gdpr_expiry_check';
    public const CRON_SMTP_TEST         = 'contactinbox_run_smtp_test'; // used by SMTP tester
    public const CRON_PROCESS_EMAIL     = 'contactinbox_process_email_queue';
    public const CRON_PROCESS_CRM       = 'contactinbox_process_crm_queue';
    public const CRON_GDPR_CLEANUP      = 'contactinbox_gdpr_deletion_cleanup';
    public const CRON_RECLASSIFY_UNCLASSIFIED = 'contactinbox_reclassify_unclassified';
    public const VERSION            = '1.0';
    public const MIN_PHP            = '7.4';
    public const TEXTDOMAIN         = 'contact-inbox';
    public const ASSETS_VERSION     = 'contactinbox_assets_version';
    public const UPGRADE_URL        = 'https://contactinbox.app/';

    /**
     * Resolve the Pro upgrade URL.
     *
     * Uses Freemius upgrade URL when available, with static fallback.
     */
    public static function get_upgrade_url(): string {
        if ( function_exists( 'contactinbox_fs' ) ) {
            try {
                $fs = contactinbox_fs();
                if ( is_object( $fs ) && method_exists( $fs, 'get_upgrade_url' ) ) {
                    $url = (string) $fs->get_upgrade_url();
                    if ( '' !== $url ) {
                        return $url;
                    }
                }
            } catch ( \Throwable $e ) {
            }
        }

        return self::UPGRADE_URL;
    }

    /**
     * Backward-compatible alias for upgrade URL.
     *
     * @deprecated Use get_upgrade_url().
     */
    public static function get_trial_url(): string {
        return self::get_upgrade_url();
    }

    // Plugin root paths
    public const PATH     = CONTACTINBOX_PATH;
    public const URL      = CONTACTINBOX_URL;

        // Removed: public const WEBHOOK_ENDPOINT_SUBMIT, WEBHOOK_ENDPOINT_STATUS
    // Dist (compiled assets)
    public const DIST     = 'dist/';
    public const DIST_CSS = 'dist/css/';
    public const DIST_JS  = 'dist/js/';

    // Upload paths for attachments
    public const UPLOADS_SUBDIR = 'contactinbox-attachments/';

    // Templates
    public const TEMPLATE_DIR        = 'templates/';
    public const TEMPLATE_ADMIN      = 'templates/admin/';
    public const TEMPLATE_ADMIN_PART = 'templates/admin/partials/';
    public const TEMPLATE_FRONTEND   = 'templates/frontend/';
    public const TEMPLATE_EMAIL      = 'templates/emails/';
    public const TEMPLATE_GDPR       = 'templates/gdpr/';

    // Includes
    public const INCLUDES    = 'includes/';
    public const ADMIN_DIR   = 'includes/Admin/';
    public const CORE_DIR    = 'includes/Core/';
    public const FRONTEND    = 'includes/Frontend/';
    public const INTEGRATION = 'includes/Integrations/';

    // Admin menu slugs
    public const MENU_INBOX         = 'contactinbox-inbox';
    public const MENU_INBOX_UNIFIED = 'contactinbox-inbox-unified';
    public const MENU_SPAM          = 'contactinbox-spam';
    public const MENU_ARCHIVED      = 'contactinbox-archived';
    public const MENU_SETTINGS      = 'contactinbox-settings';
    public const MENU_EMAIL_LOG     = 'contactinbox-email-log';
    public const MENU_MAINTENANCE   = 'contactinbox-maintenance';
    public const MENU_CONTACTS      = 'contactinbox-contacts';
    public const MENU_GDPR_LOG      = 'contactinbox-gdpr-log';
    
    // Dashboard widget
    public const DASHBOARD_WIDGET_ID = 'contactinbox_inbox_status';
    public const MENU_REST_LOG      = 'contactinbox-rest-log';
    public const MENU_REST_API_TEST = 'contactinbox-rest-api-test';
    public const MENU_CRM           = 'contactinbox-crm'; // define once here

    // Capability check
    public const CAPABILITY = 'manage_options';

    // Settings group + option names
    public const SETTINGS_GROUP      = 'contactinbox_settings_group';
    public const OPTION_SETTINGS     = 'contactinbox_settings';
    public const OPTION_CRM          = 'contactinbox_crm_settings';
    public const SETTINGS_GROUP_CRM  = 'contactinbox_crm';

    // Nonce actions
        public const CRM_LOG_NONCE         = 'contactin_crm_clear_all_logs';
        public const CRM_LOG_ACTION        = 'contactin_crm_log_action';
    public const NONCE_ACTION            = 'ci_admin_nonce';
    public const SETTINGS_NONCE_ACTION   = 'contactinbox_settings_nonce';
    public const SMTP_TEST_NONCE_ACTION  = 'contactinbox_smtp_test_nonce';
    public const INBOX_NONCE_ACTION      = 'contactinbox_inbox_nonce_action';
    public const EMAIL_LOG_NONCE         = 'contactinbox_email_log_nonce';
    public const EMAIL_LOG_ACTION        = 'contactinbox_email_log_action';
    public const FORM_SUBMIT_NONCE       = 'contactinbox_submit';
    public const GDPR_NONCE_ACTION       = 'contactinbox_gdpr_nonce_action';
    public const CRM_SETTINGS_NONCE_ACTION = 'contactinbox_crm_settings_nonce';

    // AJAX actions
    public const AJAX_SAVE_SETTINGS     = 'ci_save_settings';
    public const AJAX_TEST_SMTP         = 'ci_test_smtp';
    public const AJAX_CHECK_SMTP_RESULT = 'ci_check_smtp_result';


    // Bulk action messages
    public const BULK_MSG_NO_SELECTION   = 'Please select messages.';
    public const BULK_MSG_NO_ACTION      = 'Please choose an action.';
    public const BULK_MSG_CONFIRM_DELETE = 'Delete permanently?';
    public const BULK_MSG_APPLYING       = 'Applying...';
    public const BULK_MSG_APPLY          = 'Apply';

    // GDPR messages
    public const GDPR_MSG_SECURITY_FAIL   = 'Security check failed.';
    public const GDPR_MSG_PERMISSION      = 'Permission denied.';
    public const GDPR_MSG_NO_DATA         = 'Message or email not found.';
    public const GDPR_MSG_LINKGEN_FAIL    = 'Failed to generate deletion link.';
    public const GDPR_MSG_GENERATED       = 'Deletion link generated and sent!';
    public const GDPR_MSG_INVALID         = 'Invalid or expired deletion link.';

    // Status labels
    // Action labels
    public const ACTIONS_LABEL = 'Actions';
    public const STATUS_LABEL_READ       = 'Read';
    public const STATUS_LABEL_UNREAD     = 'Unread';
    public const STATUS_ACTION_READ      = 'Read';
    public const STATUS_ACTION_UNREAD    = 'Unread';

    // messsage statuses in database
    public const STATUS_UNREAD    = 'unread';
    public const STATUS_READ      = 'read';
    public const STATUS_ARCHIVED  = 'archived';
    // Virtual status for spam filtering (not stored in DB status column)
    public const STATUS_SPAM      = 'spam';

    // Spam detection
    public const SPAM_SCORE_THRESHOLD = 0.5;

    // Message email notification status
    public const EMAIL_SENT      = 'sent';
    public const EMAIL_FAILED    = 'failed';
    public const EMAIL_PENDING   = 'pending';
    public const EMAIL_PROCESSING = 'processing';
    public const EMAIL_SKIPPED   = 'skipped';

    // GDPR settings
    public const GDPR_EXPIRATION_DAYS = 86400; // 24 hours in seconds

    // CRM sync status
    public const CRM_SENT        = 'synced';
    public const CRM_FAILED      = 'failed';
    public const CRM_PENDING     = 'pending';
    public const CRM_PROCESSING  = 'processing';
    public const CRM_SKIPPED     = 'skipped';

    // Export messages
    public const EXPORT_MSG_RUNNING      = 'Exporting...';
    public const EXPORT_MSG_DEFAULT      = 'Export CSV';
    public const EXPORT_LIMIT = 1000; // Max messages to export at once

    // Pagination
    public const INBOX_PER_PAGE = 20; // or whatever default you want

    // Size limits
    public const MAX_FILE_UPLOAD_SIZE = 1; // In MB;

    // Queue processing
    public const FAST_LANE_THRESHOLD = 120; // Seconds - skip fast-lane if next run is within this time

    public const REST_ENDPOINT_SUBMIT   = '/submit';
    public const REST_ENDPOINT_READ     = '/read';
    public const REST_ENDPOINT_STATUS   = '/status';
    public const REST_ENDPOINT_GDPR     = '/gdpr';
    public const REST_ENDPOINT_DELETE   = '/delete';
    public const REST_ENDPOINT_BULK_DELETE = '/bulk-delete';
    public const REST_ENDPOINT_MESSAGES = '/messages';
    public const REST_ENDPOINT_SEARCH   = '/search';
    public const REST_NAMESPACE = 'contactinbox/v1';



    public const ERR_REST_DISABLED        = 'rest_disabled';
    public const ERR_WEBHOOKS_DISABLED    = 'webhooks_disabled';

}

