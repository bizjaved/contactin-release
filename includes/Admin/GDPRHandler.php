<?php

namespace ContactInbox\Admin;

use ContactInbox\Traits\Singleton;
use ContactInbox\Core\Config;
use ContactInbox\Core\DB;
use ContactInbox\Core\Logger;
use ContactInbox\Core\SMTP;
use ContactInbox\Core\QueueTrigger;
use ContactInbox\Core\AttachmentHelper;
use ContactInbox\Core\CRMSettings;
use ContactInbox\Core\Repositories\ContactRepository;

if (!defined('ABSPATH')) exit;
// phpcs:disable WordPress.WP.I18n.NonSingularStringLiteralDomain, WordPress.WP.I18n.MissingTranslatorsComment, WordPress.Security.ValidatedSanitizedInput, WordPress.Security.NonceVerification, WordPress.Security.EscapeOutput.OutputNotEscaped, WordPress.WP.I18n.UnorderedPlaceholdersText, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.WP.AlternativeFunctions.unlink_unlink, WordPress.WP.AlternativeFunctions.file_system_operations_fwrite, WordPress.WP.AlternativeFunctions.file_system_operations_is_writable, WordPress.WP.AlternativeFunctions.file_system_operations_fclose, WordPress.WP.AlternativeFunctions.rename_rename, WordPress.WP.AlternativeFunctions.file_system_operations_fopen, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class GDPRHandler {
    use Singleton;

    private function __construct() {
        // Register AJAX actions (admin only)
        add_action('wp_ajax_ci_gdpr_link',          [ $this, 'handle_link' ]);
        add_action('wp_ajax_ci_gdpr_send_email',    [ $this, 'handle_send_email' ]);
        add_action('wp_ajax_ci_gdpr_delete',        [ $this, 'handle_delete' ]);
        add_action('wp_ajax_ci_gdpr_delete_contact',[ $this, 'handle_delete_contact' ]);
        add_action('wp_ajax_ci_gdpr_delete_from_crm', [ $this, 'handle_delete_from_crm' ]);
        add_action('contactinbox_send_gdpr_email_event', [ $this, 'send_gdpr_email_async' ], 10, 3);
    }

    /**
     * Generate GDPR deletion link (without auto-sending email)
     * Email is now sent separately via handle_send_email() when user confirms
     */
    public function handle_link(): void {
        // Security checks
        if ( ! isset($_POST['nonce']) || ! wp_verify_nonce($_POST['nonce'], Config::GDPR_NONCE_ACTION) ) {
            wp_send_json_error([ 'message' => Config::GDPR_MSG_SECURITY_FAIL ]);
        }
        if ( ! current_user_can(Config::CAPABILITY) ) {
            wp_send_json_error([ 'message' => Config::GDPR_MSG_PERMISSION ]);
        }

        // Validate message
        $id      = absint($_POST['id'] ?? 0);
        $message = DB::instance()->get_message_by_id($id);
        if ( ! $message || empty($message->email) ) {
            wp_send_json_error([ 'message' => Config::GDPR_MSG_NO_DATA ]);
        }

        $email = sanitize_email($message->email);

        // Use Core\GDPR to generate token with canonical expiry
        $token = \ContactInbox\Core\GDPR::generate_token($id);
        if ( ! $token ) {
            wp_send_json_error([ 'message' => Config::GDPR_MSG_LINKGEN_FAIL ]);
        }

        $expires = time() + (\ContactInbox\Core\GDPR::EXPIRATION_DAYS * DAY_IN_SECONDS);

        // Build deletion link
        $deletion_link = \ContactInbox\Core\GDPR::build_delete_link($token, $email);

        // Respond immediately with link (email NOT sent yet)
        wp_send_json_success([
            'link'       => esc_url($deletion_link),
            'email'      => esc_html($email),
            'site_name'  => get_bloginfo('name'),
            'expires'    => sprintf(__('Expires in %s',  'contactin'), human_time_diff($expires)),
            'message_id' => $id,
            'message'    => __('GDPR deletion link generated successfully.',  'contactin'),
        ]);
    }

    /**
     * Send GDPR email (called when user clicks "Send Email" button)
     */
    public function handle_send_email(): void {
        // Security checks
        if ( ! isset($_POST['nonce']) || ! wp_verify_nonce($_POST['nonce'], Config::GDPR_NONCE_ACTION) ) {
            wp_send_json_error([ 'message' => Config::GDPR_MSG_SECURITY_FAIL ]);
        }
        if ( ! current_user_can(Config::CAPABILITY) ) {
            wp_send_json_error([ 'message' => Config::GDPR_MSG_PERMISSION ]);
        }

        // Validate message
        $id           = absint($_POST['id'] ?? 0);
        $deletion_link = sanitize_url($_POST['link'] ?? '');
        $message       = DB::instance()->get_message_by_id($id);
        
        if ( ! $message || empty($message->email) ) {
            wp_send_json_error([ 'message' => Config::GDPR_MSG_NO_DATA ]);
        }

        $email = sanitize_email($message->email);

        // Prepare event args (logging handled inside SMTP)
        $event_args = [ $email, $deletion_link, [
            'name'         => $message->name ?? '',
            'subject'      => $message->subject ?? '',
            'message'      => $message->message ?? '',
            'phone'        => $message->phone ?? '',
            'submitted_at' => $message->submitted_at ?? '',
        ] ];

        // Check if this exact event is already scheduled (prevent duplicates)
        $next_scheduled = wp_next_scheduled( 'contactinbox_send_gdpr_email_event', $event_args );
        
        if ( ! $next_scheduled ) {
            // Schedule background send with message data
            wp_schedule_single_event(
                time() + 1,
                'contactinbox_send_gdpr_email_event',
                $event_args
            );

            // Nudge the fast-lane email processor similar to form submissions
            QueueTrigger::maybe_trigger_email_processor();
            
            wp_send_json_success([
                'message' => __('GDPR deletion link is being sent to: ',  'contactin') . $email,
            ]);
        } else {
            wp_send_json_error([
                'message' => __('Email is already scheduled to be sent.',  'contactin'),
            ]);
        }
    }

    /**
     * Handle deletion via AJAX (admin‑side)
     */
    public function handle_delete(): void {
        $nonce = $_REQUEST['nonce'] ?? '';
        $token = sanitize_text_field($_REQUEST['token'] ?? '');
        $email = sanitize_email($_REQUEST['email'] ?? '');

        $args = [
            'link_valid' => false,
            'email'      => $email,
            'message'    => '',
        ];

        if ( ! wp_verify_nonce($nonce, Config::GDPR_NONCE_ACTION) ) {
            $args['message'] = Config::GDPR_MSG_SECURITY_FAIL;
        } elseif ( ! is_email($email) ) {
            $args['message'] = __( 'Invalid email address.',  'contactin');
        } else {
            $message_id = DB::instance()->validate_gdpr_token_get_id($token, $email);
            if ($message_id) {
                $message = DB::instance()->get_message_by_id($message_id);
                if ($message && $message->email === $email) {
                    DB::instance()->delete_message($message_id);
                    DB::instance()->clear_gdpr_token($message_id);
                    $args['link_valid'] = true;
                    $args['message']    = Config::GDPR_SUCCESS_DEFAULT;

                        if (defined('WP_DEBUG') && WP_DEBUG) {
                            Logger::notice(
                                'Admin GDPR deletion completed',
                                [ 'message_id' => $message_id, 'email' => $email ]
                            );
                        }
                } else {
                    $args['message'] = Config::GDPR_MSG_NO_DATA;
                }
            } else {
                $args['message'] = Config::GDPR_MSG_INVALID;
            }
        }

        $template = CONTACTINBOX_PATH . Config::TEMPLATE_ADMIN_PART . 'gdpr-delete.php';
        if ( file_exists($template) ) {
            load_template($template, true, $args);
        } else {
            wp_die(esc_html__('GDPR delete template missing.',  'contactin'));
        }
    }

    public function send_gdpr_email_async( string $email, string $deletion_link, array $message_data = [] ): void {
        try {
            SMTP::send_gdpr_email( $email, $deletion_link, $message_data );
        } catch ( \Throwable $e ) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                Logger::error('GDPR email send failed', [ 'error' => $e->getMessage(), 'email' => $email ]);
            }
        }
    }

    /**
     * Handle contact deletion with comprehensive GDPR compliance
     * Deletes: messages, files, and contact record
     * Logs deletion with CRM sync status for later processing
     */
    public function handle_delete_contact(): void {
        // Security checks
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'ci_gdpr_delete_contact')) {
            wp_send_json_error(['message' => __('Security check failed.',  'contactin')]);
        }
        if (!current_user_can(Config::CAPABILITY)) {
            wp_send_json_error(['message' => __('Permission denied.',  'contactin')]);
        }

        $contact_id = absint($_POST['contact_id'] ?? 0);
        if (!$contact_id) {
            wp_send_json_error(['message' => __('Invalid contact ID.',  'contactin')]);
        }

        $contact_repo = new ContactRepository();
        $contact = $contact_repo->get_by_id($contact_id);
        
        if (!$contact) {
            wp_send_json_error(['message' => __('Contact not found.',  'contactin')]);
        }

        global $wpdb;
        $table_messages = $wpdb->prefix . Config::TABLE_MESSAGES;
        $table_contacts = $wpdb->prefix . Config::TABLE_CONTACTS;
        $table_gdpr_log = $wpdb->prefix . Config::TABLE_GDPR_DELETION_LOG;

        // Start deletion process
        $email = $contact->email ?? '';
        $name = $contact->name ?? '';
        $files_deleted = 0;
        $messages_deleted = 0;
        $deletion_errors = [];

        // Get all messages for this contact
        $messages = $wpdb->get_results(
            $wpdb->prepare("SELECT id, attachment, crm_status FROM {$table_messages} WHERE contact_id = %d", $contact_id)
        );

        // Determine CRM sync status (use the most recent synced status)
        $crm_sync_status = null;
        foreach ($messages as $msg) {
            if (!empty($msg->crm_status) && $msg->crm_status === Config::CRM_SENT) {
                $crm_sync_status = 'synced';
                break;
            }
        }
        if ($crm_sync_status === null) {
            $crm_sync_status = 'not_synced';
        }

        $crm_settings = CRMSettings::get_settings();
        if ($crm_sync_status === 'synced' && empty($crm_settings['crm_delete_sync'])) {
            $crm_sync_status = 'manual_required';
            $deletion_errors[] = __('CRM deletion sync disabled; delete in Salesforce manually.',  'contactin');
        }
        // Delete files attached to messages
        foreach ($messages as $msg) {
            if (!empty($msg->attachment)) {
                $file_path = AttachmentHelper::extract_file_path($msg->attachment);
                if ($file_path && file_exists($file_path)) {
                    if (@unlink($file_path)) {
                        $files_deleted++;
                    } else {
                        $deletion_errors[] = sprintf(
                            __('Failed to delete file: %s',  'contactin'),
                            basename($file_path)
                        );
                    }
                }
            }
        }

        // Delete all messages for this contact
        $deleted_count = $wpdb->delete($table_messages, ['contact_id' => $contact_id], ['%d']);
        if ($deleted_count !== false) {
            $messages_deleted = $deleted_count;
        }

        // Delete the contact record
        $contact_deleted = $wpdb->delete($table_contacts, ['id' => $contact_id], ['%d']);

        // Log the deletion
        $log_data = [
            'contact_id' => $contact_id,
            'email' => $email,
            'name' => $name,
            'crm_sync_status' => $crm_sync_status,
            'messages_deleted' => $messages_deleted,
            'files_deleted' => $files_deleted,
            'deletion_status' => ($contact_deleted !== false) ? 'completed' : 'failed',
            'error_message' => !empty($deletion_errors) ? implode('; ', $deletion_errors) : null,
            'deleted_by' => get_current_user_id(),
            'deleted_at' => current_time('mysql'),
        ];

        $wpdb->insert($table_gdpr_log, $log_data, [
            '%d', '%s', '%s', '%s', '%d', '%d', '%s', '%s', '%d', '%s'
        ]);

        if ($contact_deleted !== false) {
            wp_send_json_success([
                'message' => __('Contact and all associated data deleted successfully.',  'contactin'),
                'messages_deleted' => $messages_deleted,
                'files_deleted' => $files_deleted,
                'crm_sync_status' => $crm_sync_status,
            ]);
        } else {
            wp_send_json_error([
                'message' => __('Failed to delete contact.',  'contactin'),
                'errors' => $deletion_errors,
            ]);
        }
    }

    /**
     * Delete contacts from CRM that have been deleted locally
     */
    public function handle_delete_from_crm(): void {
        // Security checks
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'ci_gdpr_delete_from_crm')) {
            wp_send_json_error(['message' => __('Security check failed.',  'contactin')]);
        }
        if (!current_user_can(Config::CAPABILITY)) {
            wp_send_json_error(['message' => __('Permission denied.',  'contactin')]);
        }

        global $wpdb;
        $table_gdpr_log = $wpdb->prefix . Config::TABLE_GDPR_DELETION_LOG;

        $crm_settings = CRMSettings::get_settings();
        if (empty($crm_settings['crm_delete_sync'])) {
            wp_send_json_error([
                'message' => __('CRM deletion sync is disabled. Delete records in Salesforce manually or enable deletion sync in CRM settings.',  'contactin'),
            ]);
        }

        // Get all deletion logs where CRM sync status is 'synced' and deletion is completed
        $logs = $wpdb->get_results(
            "SELECT id, email, name, crm_id FROM {$table_gdpr_log} 
             WHERE crm_sync_status = 'synced' 
             AND deletion_status = 'completed'
             ORDER BY deleted_at DESC"
        );

        if (empty($logs)) {
            wp_send_json_success([
                'message' => __('No synced contacts found to delete from CRM.',  'contactin'),
                'deleted_count' => 0,
            ]);
            return;
        }

        $deleted_count = 0;
        $errors = [];

        // Queue deletions for CRM using QueueManager
        foreach ($logs as $log) {
            try {
                // Prepare payload for CRM deletion
                $payload = [
                    'contact_id' => '', // Will be looked up by email
                    'crm_id' => $log->crm_id ?? null,
                    'email' => $log->email,
                    'name' => $log->name,
                    'gdpr_log_id' => $log->id,
                    'operation' => 'crm_delete',
                ];
                
                $queue_id = \ContactInbox\Core\QueueManager::push(
                    'crm_delete',
                    $payload,
                    (string) $log->id,
                    2 // High priority for manual GDPR deletions
                );
                
                if (!is_wp_error($queue_id)) {
                    // Mark as queued in GDPR log
                    $wpdb->update(
                        $table_gdpr_log,
                        ['crm_deletion_queued_at' => current_time('mysql')],
                        ['id' => $log->id],
                        ['%s'],
                        ['%d']
                    );
                    $deleted_count++;
                } else {
                    $errors[] = sprintf(
                        __('Failed to queue %s: %s',  'contactin'),
                        $log->email,
                        $queue_id->get_error_message()
                    );
                }
            } catch (\Throwable $e) {
                $errors[] = sprintf(
                    __('Error queueing %s: %s',  'contactin'),
                    $log->email,
                    $e->getMessage()
                );
            }
        }

        if ($deleted_count > 0) {
            \ContactInbox\Core\QueueTrigger::maybe_trigger_crm_processor();
        }

        $message = sprintf(
            __('%d contact(s) queued for CRM deletion.',  'contactin'),
            $deleted_count
        );
        
        $response = [
            'message' => $message,
            'deleted_count' => $deleted_count,
        ];
        
        if (!empty($errors)) {
            $response['errors'] = $errors;
        }

        wp_send_json_success($response);
    }

}
