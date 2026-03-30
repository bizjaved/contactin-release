<?php
declare(strict_types=1);

namespace ContactInbox\Core;

use ContactInbox\Traits\Singleton;
use ContactInbox\Core\Repositories\GDPRRepository;
use ContactInbox\Core\Repositories\MessageRepository;
use ContactInbox\Core\Repositories\ContactRepository;
use ContactInbox\Core\AttachmentHelper;

// phpcs:disable WordPress.WP.I18n.TextDomainMismatch, WordPress.WP.I18n.NonSingularStringLiteralDomain, WordPress.Security.ValidatedSanitizedInput, WordPress.Security.NonceVerification, WordPress.Security.EscapeOutput, WordPress.WP.I18n.MissingTranslatorsComment, WordPress.WP.I18n.UnorderedPlaceholdersText, WordPress.WP.AlternativeFunctions.unlink_unlink, WordPress.WP.AlternativeFunctions.file_system_operations_fwrite, WordPress.WP.AlternativeFunctions.file_system_operations_is_writable, WordPress.WP.AlternativeFunctions.file_system_operations_fclose, WordPress.WP.AlternativeFunctions.rename_rename, WordPress.WP.AlternativeFunctions.file_system_operations_fopen, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber

if (!defined('ABSPATH')) {
    exit;
}

final class GDPR {
    use Singleton;

    public const EXPIRATION_DAYS = 7;
    private const EXPORT_BATCH   = 50;

    private function __construct() {
        add_action('template_redirect', [$this, 'maybe_handle_public_deletion']);
        add_action('template_redirect', [$this, 'maybe_show_success_page']);
        add_action('wp_ajax_ci_gdpr_frontend_delete', [$this, 'ajax_frontend_delete']);
        add_action('wp_ajax_nopriv_ci_gdpr_frontend_delete', [$this, 'ajax_frontend_delete']);
        add_filter('wp_privacy_personal_data_exporters', [$this, 'register_exporter']);
        add_filter('wp_privacy_personal_data_erasers',   [$this, 'register_eraser']);
    }

    // =========================================================================
    // Token Management
    // =========================================================================

    public static function generate_token(int $message_id): ?string {
        $expires = time() + (self::EXPIRATION_DAYS * DAY_IN_SECONDS);
        $repo = new GDPRRepository();
        return $repo->generate_token($message_id, $expires);
    }

    public static function build_delete_link(string $token, string $email): string {
        $email = sanitize_email($email);
        if ($token === '' || !is_email($email)) {
            return '';
        }

        return add_query_arg(
            [
                'contactin_gdpr_delete' => 1,
                'token'                => rawurlencode($token),
                'email'                => rawurlencode($email),
            ],
            home_url()
        );
    }

    // =========================================================================
    // Public Deletion Flow
    // =========================================================================
    public function maybe_show_success_page(): void {
        if (empty($_GET['contactin_gdpr_success']) || empty($_GET['email'])) {
            return;
        }

        $email = sanitize_email($_GET['email']);
        $this->render_success(['email' => $email]);
    }

    public function maybe_handle_public_deletion(): void {
        if (empty($_GET['contactin_gdpr_delete']) || empty($_GET['token']) || empty($_GET['email'])) {
            return;
        }

        $token = sanitize_text_field($_GET['token']);
        $email = sanitize_email($_GET['email']);

        if (!is_email($email)) {
            $this->render_error(__('Invalid email address.',  'contactin'));
            return;
        }

        $gdpr_repo = new GDPRRepository();
        $message_id = $gdpr_repo->validate_token_get_id($token, $email);
        if (!$message_id) {
            $this->render_error(__('This deletion link is invalid or has expired.',  'contactin'));
            return;
        }

        // Get statistics for confirmation page
        $stats = $this->get_deletion_stats($email);

        // Show confirmation page instead of immediately deleting
        $this->render_confirmation([
            'token' => $token,
            'email' => $email,
            'stats' => $stats
        ]);
    }

    /**
     * Get statistics about data to be deleted
     */
    private function get_deletion_stats(string $email): array {
        $message_repo = new MessageRepository();
        $messages = $message_repo->get_ids_and_attachments_by_email($email);

        $attachments_count = 0;
        foreach ($messages as $msg) {
            $path = AttachmentHelper::extract_file_path($msg->attachment ?? '');
            if ($path && file_exists($path)) {
                $attachments_count++;
            }
        }

        return [
            'messages' => count($messages),
            'attachments' => $attachments_count
        ];
    }

    /**
     * AJAX handler for frontend deletion
     */
    public function ajax_frontend_delete(): void {
        // Get and validate parameters
        $token = sanitize_text_field($_POST['token'] ?? '');
        $email = sanitize_email($_POST['email'] ?? '');

        if (!is_email($email)) {
            wp_send_json_error(__('Invalid email address.',  'contactin'));
        }

        // Validate token
        $gdpr_repo = new GDPRRepository();
        $message_id = $gdpr_repo->validate_token_get_id($token, $email);
        if (!$message_id) {
            wp_send_json_error(__('This deletion link is invalid or has expired.',  'contactin'));
        }

        global $wpdb;
        
        try {
            // Start transaction
            $gdpr_repo->start_transaction();
            
            // Get all messages for this email
            $message_repo = new MessageRepository();
            $messages = $message_repo->get_ids_and_attachments_by_email($email);

            // Track contact linkage for fallback deletion when email lookup fails
            $contact_ids = [];
            $deleted_files = 0;
            $synced_count = 0;
            $unsynced_count = 0;

            // Track sync status and clear GDPR tokens (file/message deletion handled by delete_with_messages)
            foreach ($messages as $msg) {
                // Track CRM sync status
                if ($msg->crm_status === Config::CRM_SENT) {
                    $synced_count++;
                } else {
                    $unsynced_count++;
                }

                $contact_ids[] = (int) ($msg->contact_id ?? 0);
                
                // Clear token (messages and files will be deleted by delete_with_messages)
                $gdpr_repo->clear_token((int)$msg->id);
            }

            // Delete contact record (this will also delete messages, files, and queue CRM deletion if needed)
            $contact_repo = new ContactRepository();
            $contact = $contact_repo->find_by_email($email);
            $deleted_contacts = 0;
            $deleted_messages = 0;
            $log_contact_id = 0;
            $log_contact_name = '';
            $log_contact_crm_id = null;
            $crm_queued_in_repo = false;

            if ($contact) {
                // Use delete_with_messages() which handles messages + CRM queueing + file cleanup automatically
                $delete_result = $contact_repo->delete_with_messages((int)$contact->id);
                
                if ($delete_result['contact_deleted']) {
                    $deleted_contacts = 1;
                    $deleted_messages = $delete_result['messages_deleted'];
                    $deleted_files = $delete_result['attachments_deleted'];
                    $log_contact_id = (int) $contact->id;
                    $log_contact_name = $contact->name;
                    $log_contact_crm_id = $contact->crm_id ?? null;
                    $crm_queued_in_repo = true; // CRM deletion already queued in delete_with_messages()
                } else {
                    throw new \Exception('Failed to delete contact ID: ' . (int)$contact->id);
                }
            } else {
                // Fallback: delete any contacts linked by contact_id on the messages
                $unique_contact_ids = array_values(array_filter(array_unique($contact_ids)));
                foreach ($unique_contact_ids as $contact_id) {
                    $linked_contact = $contact_repo->get_by_id((int) $contact_id);
                    if (!$linked_contact) {
                        continue;
                    }

                    $delete_result = $contact_repo->delete_with_messages((int)$linked_contact->id);
                    if (!$delete_result['contact_deleted']) {
                        throw new \Exception('Failed to delete contact ID: ' . (int)$linked_contact->id);
                    }

                    $deleted_contacts++;
                    $deleted_messages += $delete_result['messages_deleted'];
                    $deleted_files += $delete_result['attachments_deleted'];
                    $crm_queued_in_repo = true;

                    // Use the first successfully deleted contact for logging context
                    if ($log_contact_id === 0) {
                        $log_contact_id = (int) $linked_contact->id;
                        $log_contact_name = $linked_contact->name;
                        $log_contact_crm_id = $linked_contact->crm_id ?? null;
                    }
                }
            }

            $contact_note = null;
            if ($deleted_contacts === 0 && !empty(array_filter($contact_ids))) {
                $contact_note = 'Contact not deleted; no matching contact found for provided email or linked contact IDs.';
            }

            // Determine overall CRM sync status
            $crm_sync_status = 'unsynced';
            if ($synced_count > 0 && $unsynced_count === 0) {
                $crm_sync_status = 'synced';
            } elseif ($synced_count > 0 && $unsynced_count > 0) {
                $crm_sync_status = 'partially_synced';
            }

            // Log deletion to GDPR deletion log table
            $log_data = [
                'contact_id' => $log_contact_id,
                'crm_id' => $log_contact_crm_id,
                'email' => $email,
                'name' => $log_contact_name,
                'crm_sync_status' => $crm_sync_status,
                'messages_deleted' => $deleted_messages,
                'messages_synced' => $synced_count,
                'messages_unsynced' => $unsynced_count,
                'files_deleted' => $deleted_files,
                'deletion_status' => 'completed',
                'error_message' => $contact_note,
                'deleted_by' => get_current_user_id(),
                'deleted_at' => current_time('mysql'),
            ];

            if (!$gdpr_repo->log_deletion($log_data)) {
                throw new \Exception('Failed to log GDPR deletion');
            }

            // Commit transaction
            $gdpr_repo->commit_transaction();

            // Note: CRM deletion is already queued by delete_with_messages() if contact was synced
            // Just mark the GDPR log as queued if CRM deletion was triggered
            if ($crm_queued_in_repo && $synced_count > 0) {
                global $wpdb;
                $log_id_inserted = $wpdb->insert_id;
                $gdpr_repo->mark_deletion_queued($log_id_inserted);
                
                Logger::log(Logger::INFO, 'CRM deletion queued from frontend GDPR link via delete_with_messages()', [
                    'contact_id' => $log_contact_id,
                    'email' => $email,
                ]);
            }

            wp_send_json_success([
                'message' => sprintf(
                    __('Successfully deleted %d messages, %d files, and %d contact records.',  'contactin'),
                    $deleted_messages,
                    $deleted_files,
                    $deleted_contacts
                )
            ]);

        } catch (\Exception $e) {
            // Rollback on error
            $gdpr_repo->rollback_transaction();
            
            Logger::log(Logger::ERROR, 'GDPR frontend deletion failed: ' . $e->getMessage(), [
                'email' => $email,
                'error' => $e->getMessage()
            ]);

            wp_send_json_error(
                sprintf(
                    __('Deletion failed: %s',  'contactin'),
                    $e->getMessage()
                )
            );
        }
    }

    /**
     * Delete file with retry logic
     * Attempts to delete a file multiple times with small delays
     */
    private function delete_file_with_retry(string $path, int $max_attempts = 3): bool {
        for ($attempt = 1; $attempt <= $max_attempts; $attempt++) {
            if (@unlink($path)) {
                return true;
            }
            
            // File doesn't exist - consider it deleted
            if (!file_exists($path)) {
                return true;
            }
            
            // Wait before retry (100ms increments)
            if ($attempt < $max_attempts) {
                usleep(100000 * $attempt); // 100ms, 200ms, etc.
            }
        }
        
        return false;
    }

    // =========================================================================
    // Template Rendering
    // =========================================================================
    private function render_confirmation(array $vars = []): never {
        $this->enqueue_frontend_assets(true);
        $template = $this->locate_template('gdpr/deletion-confirm.php');
        if ($template) {
            load_template($template, true, $vars);
        } else {
            wp_die(esc_html__('GDPR confirmation template missing.',  'contactin'));
        }
        exit;
    }

    private function render_success(array $vars = []): never {
        $this->enqueue_frontend_assets(false);
        $template = $this->locate_template('gdpr/deletion-success.php');
        if ($template) {
            load_template($template, true, $vars);
        } else {
            wp_die(esc_html__('GDPR success template missing.',  'contactin'));
        }
        exit;
    }

    private function render_error(string $message): never {
        $this->enqueue_frontend_assets(false);
        $template = $this->locate_template('gdpr/deletion-error.php');

        if ($template) {
            set_query_var('gdpr_error_message', $message);
            load_template($template, true);
        } else {
            wp_die(esc_html__('GDPR error template missing.',  'contactin'));
        }

        if (defined('WP_DEBUG') && WP_DEBUG) {
            Logger::warning('GDPR error rendered', [ 'message' => $message ]);
        }

        exit;
    }

    private function locate_template(string $template_name): ?string {
        // Allow theme override: wp-content/themes/<theme>/contactin-hub/gdpr/...
        $theme_template  = locate_template(['contactin-hub/' . $template_name]);

        // Plugin default location: go up from /includes/Core/ to plugin root
        $plugin_template = plugin_dir_path(dirname(__DIR__)) . 'templates/' . $template_name;

        return $theme_template ?: (file_exists($plugin_template) ? $plugin_template : null);
    }

    private function enqueue_frontend_assets(bool $with_script): void {
        static $enqueued = false;

        if ($enqueued) {
            return;
        }
        $enqueued = true;

        add_action('wp_enqueue_scripts', function () use ($with_script): void {
            // Plugin global styles (fallback layer)
            wp_enqueue_style(
                'contactin-frontend',
                CONTACTINBOX_URL . 'dist/css/frontend.min.css',
                [],
                CONTACTINBOX_VERSION
            );

            // Page-specific GDPR styles (lowest priority, still before theme)
            wp_enqueue_style(
                'contactin-gdpr-frontend',
                CONTACTINBOX_URL . 'dist/css/gdpr-frontend.css',
                ['contactin-frontend'],
                CONTACTINBOX_VERSION
            );

            if ($with_script) {
                wp_enqueue_script(
                    'contactin-gdpr-frontend',
                    CONTACTINBOX_URL . 'dist/js/gdpr-frontend.js',
                    [],
                    CONTACTINBOX_VERSION,
                    true
                );

                wp_localize_script('contactin-gdpr-frontend', 'cinGdprFrontend', [
                    'ajaxUrl' => admin_url('admin-ajax.php'),
                    'homeUrl' => home_url('/'),
                    'i18n' => [
                        'starting' => __( 'Starting deletion process...',  'contactin'),
                        'deleting' => __( 'Deleting messages and files...',  'contactin'),
                        'done' => __( 'Deletion complete!',  'contactin'),
                        'error' => __( 'An unknown error occurred.',  'contactin'),
                        'network_error' => __( 'Network error. Please check your connection and try again.',  'contactin'),
                        'try_again' => __( 'Try Again',  'contactin'),
                        'cancel' => __( 'Cancel',  'contactin'),
                    ],
                ]);
            }
        }, 1);
    }

    // =========================================================================
    // WP Privacy Tools
    // =========================================================================

    public function register_exporter(array $exporters): array {
        $exporters['contactin-hub'] = [
            'exporter_friendly_name' => __('ContactIn Messages',  'contactin'),
            'callback'               => [$this, 'data_exporter'],
        ];
        return $exporters;
    }

    public function register_eraser(array $erasers): array {
        $erasers['contactin-hub'] = [
            'eraser_friendly_name' => __('ContactIn Messages',  'contactin'),
            'callback'             => [$this, 'data_eraser'],
        ];
        return $erasers;
    }

    public function data_exporter(string $email, int $page = 1): array {
        $message_repo = new MessageRepository();
        $messages = $message_repo->get_by_email($email, $page, self::EXPORT_BATCH);
        $data     = [];

        foreach ($messages as $msg) {
            $data[] = [
                'group_id'    => 'contactin_messages',
                'group_label' => __('Contact Form Submissions',  'contactin'),
                'item_id'     => "message-{$msg->id}",
                'data'        => [
                    ['name' => 'Name',       'value' => $msg->name],
                    ['name' => 'Email',      'value' => $msg->email],
                    ['name' => 'Phone',      'value' => $msg->phone ?? ''],
                    ['name' => 'Message',    'value' => $msg->message],
                    ['name' => 'Submitted',  'value' => $msg->submitted_at],
                    ['name' => 'IP Address', 'value' => $msg->ip_address ?? ''],
                ],
            ];
        }

        return ['data' => $data, 'done' => count($messages) < self::EXPORT_BATCH];
    }

    public function data_eraser(string $email, int $page = 1): array {
        $message_repo = new MessageRepository();
        $gdpr_repo = new GDPRRepository();
        $messages = $message_repo->get_by_email($email, $page, self::EXPORT_BATCH);
        $deleted  = 0;

        foreach ($messages as $msg) {
            $path = AttachmentHelper::extract_file_path($msg->attachment ?? '');
            if ($path && file_exists($path)) {
                @unlink($path);
            }
            $message_repo->delete((int)$msg->id);
            $gdpr_repo->clear_token((int)$msg->id);
            $deleted++;
        }

        if (defined('WP_DEBUG') && WP_DEBUG && $deleted > 0) {
            Logger::notice(
                'GDPR eraser removed messages',
                [ 'count' => $deleted, 'email' => $email ]
            );
        }

        return [
            'items_removed'  => $deleted > 0,
            'items_retained' => false,
            'messages'       => [],
            'done'           => count($messages) < self::EXPORT_BATCH,
        ];
    }
}
