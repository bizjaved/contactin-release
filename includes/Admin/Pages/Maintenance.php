<?php
// phpcs:disable WordPress.WP.I18n.MissingTranslatorsComment, WordPress.WP.I18n.UnorderedPlaceholdersText, WordPress.WP.I18n.NonSingularStringLiteralText
declare(strict_types=1);
/**
 * Maintenance / Operations Page
 *
 * Provides admin-only operational controls: queue recovery, DLQ management,
 * circuit resets, schedule resync, and basic hygiene actions.
 *
 * @package ContactInbox\Admin\Pages
 */

namespace ContactInbox\Admin\Pages;

use ContactInbox\Core\Config;
use ContactInbox\Core\QueueManager;
use ContactInbox\Core\QueueTrigger;
use ContactInbox\Core\ProcessLock;
use ContactInbox\Core\CircuitBreaker;
use ContactInbox\Core\Logger;
use ContactInbox\Core\IntentClassifier;
use ContactInbox\Core\Repositories\MessageRepository;
use ContactInbox\Core\Repositories\QueueRepository;
use ContactInbox\Cron\CronJobs;
use ContactInbox\Lifecycle;
use ContactInbox\Traits\Singleton;

if (!defined('ABSPATH')) {
    exit;
}

final class Maintenance {
    use Singleton;

    private MessageRepository $message_repo;
    private QueueRepository $queue_repo;

    private function post_text(string $key, string $default = ''): string {
        $value = filter_input(INPUT_POST, $key, FILTER_UNSAFE_RAW);
        if (null === $value || false === $value) {
            return $default;
        }
        return sanitize_text_field(wp_unslash((string) $value));
    }

    private function post_int(string $key, int $default = 0): int {
        $value = filter_input(INPUT_POST, $key, FILTER_UNSAFE_RAW);
        if (null === $value || false === $value || '' === $value) {
            return $default;
        }
        return absint(wp_unslash((string) $value));
    }

    private function __construct() {
        $is_free = defined('CONTACTINBOX_IS_FREE') && CONTACTINBOX_IS_FREE;
        $this->message_repo = new MessageRepository();
        $this->queue_repo = new QueueRepository();
        // Diagnostics
        add_action('wp_ajax_contactin_maint_get_cron_diagnostics', [$this, 'ajax_get_cron_diagnostics']);
        // Intent classification maintenance
        add_action('wp_ajax_contactin_maint_reclassify_intent', [$this, 'ajax_reclassify_intent']);

        if ( ! $is_free ) {
            add_action('wp_ajax_contactin_maint_run_queue_email', [$this, 'ajax_run_queue_email']);
            add_action('wp_ajax_contactin_maint_run_queue_crm', [$this, 'ajax_run_queue_crm']);
            add_action('wp_ajax_contactin_maint_retry_dlq', [$this, 'ajax_retry_dlq']);
            add_action('wp_ajax_contactin_maint_retry_email_dlq', [$this, 'ajax_retry_email_dlq']);
            add_action('wp_ajax_contactin_maint_retry_crm_dlq', [$this, 'ajax_retry_crm_dlq']);
            add_action('wp_ajax_contactin_maint_reset_circuits', [$this, 'ajax_reset_circuits']);
            add_action('wp_ajax_contactin_maint_skip_email', [$this, 'ajax_skip_email']);
            add_action('wp_ajax_contactin_maint_reschedule_email_queue', [$this, 'ajax_reschedule_email_queue']);
            add_action('wp_ajax_contactin_maint_reschedule_crm_queue', [$this, 'ajax_reschedule_crm_queue']);
            add_action('wp_ajax_contactin_maint_cleanup_orphaned_attachments', [$this, 'ajax_cleanup_orphaned_attachments']);
            add_action('wp_ajax_contactin_maint_clean_stale_db_entries', [$this, 'ajax_clean_stale_db_entries']);
            // New event-driven queue actions
            add_action('wp_ajax_contactin_maint_get_lock_status', [$this, 'ajax_get_lock_status']);
            add_action('wp_ajax_contactin_maint_force_release_lock', [$this, 'ajax_force_release_lock']);
            add_action('wp_ajax_contactin_maint_trigger_email_processor', [$this, 'ajax_trigger_email_processor']);
            add_action('wp_ajax_contactin_maint_trigger_crm_processor', [$this, 'ajax_trigger_crm_processor']);
            add_action('wp_ajax_contactin_maint_queue_progress', [$this, 'ajax_queue_progress']);
            // GDPR CRM cleanup
            add_action('wp_ajax_contactin_maint_gdpr_queue_delete', [$this, 'ajax_gdpr_queue_delete']);
            add_action('wp_ajax_contactin_maint_gdpr_immediate_delete', [$this, 'ajax_gdpr_immediate_delete']);
        }
    }

    /**
     * AJAX: Clean up orphaned attachment files and update analytics
     */
    public function ajax_cleanup_orphaned_attachments(): void {
        $this->check_ajax('contactin_maint_cleanup_orphaned_attachments');
        try {
            $service = \ContactInbox\Core\AttachmentCleanupService::instance();
            $scan = $service->scan_orphaned_files();
            
            // Check if any orphaned files were found
            if (empty($scan['orphaned'])) {
                wp_send_json_success([
                    'deleted' => [],
                    'failed' => [],
                    'stats' => ['count' => 0, 'size' => 0, 'last_scan' => time()],
                    'message' => __('No orphaned files found to clean up.', 'contact-inbox'),
                ]);
                return;
            }
            
            $deleted = $service->delete_orphaned_files($scan['orphaned']);
            
            $deleted_count = count($deleted['deleted']);
            $failed_count = count($deleted['failed']);
            
            // If some files failed to delete, log and inform user
            if ($failed_count > 0) {
                Logger::warning('Orphaned file cleanup had failures', [
                    'deleted' => $deleted_count,
                    'failed' => $failed_count,
                    'failed_files' => $deleted['failed']
                ]);
            }
            
            // After successful deletion, also clean stale DB entries
            if ($deleted_count > 0) {
                $stale_cleaned = $service->clean_stale_db_entries();
                Logger::info("Cleaned {$stale_cleaned} stale database entries after file deletion");
            }
            
            // Reset analytics after deletion
            update_option('contactinbox_orphaned_attachments_stats', []);
            
            // Provide detailed message
            if ($deleted_count > 0 && $failed_count === 0) {
                $message = sprintf(__('Successfully deleted %d orphaned files.', 'contact-inbox'), $deleted_count);
            } elseif ($deleted_count > 0 && $failed_count > 0) {
                $message = sprintf(__('Deleted %d files, but %d files could not be deleted (permission denied).', 'contact-inbox'), $deleted_count, $failed_count);
            } else {
                // All files failed to delete - likely a permissions issue
                $uploads_dir = WP_CONTENT_DIR . '/uploads/contactin-attachments/';
                $is_writable = wp_is_writable($uploads_dir);
                $perms = substr(sprintf('%o', fileperms($uploads_dir)), -4);
                
                Logger::error('Orphaned file cleanup - permission denied for all files', [
                    'directory' => $uploads_dir,
                    'writable' => $is_writable,
                    'permissions' => $perms,
                    'files_attempted' => count($deleted['failed'])
                ]);
                
                wp_send_json_error([
                    'message' => sprintf(
                        __('Could not delete %d orphaned files. Check folder permissions. Directory: %s (Perms: %s)', 'contact-inbox'),
                        count($deleted['failed']),
                        $uploads_dir,
                        $perms
                    )
                ]);
                return;
            }
            
            wp_send_json_success([
                'deleted' => $deleted['deleted'],
                'failed' => $deleted['failed'],
                'stats' => ['count' => 0, 'size' => 0, 'last_scan' => time()],
                'message' => $message,
            ]);
        } catch (\Throwable $e) {
            Logger::error('Orphaned file cleanup error', ['error' => $e->getMessage()]);
            wp_send_json_error(['message' => $e->getMessage()]);
        }
    }

    /**
     * AJAX: Clean stale database entries (files referenced in DB but no longer on disk)
     */
    public function ajax_clean_stale_db_entries(): void {
        $this->check_ajax('contactin_maint_clean_stale_db_entries');
        try {
            $service = \ContactInbox\Core\AttachmentCleanupService::instance();
            $stale_before = $service->get_stale_entries();
            $cleaned_count = $service->clean_stale_db_entries();
            $stale_after = $service->get_stale_entries();
            
            Logger::info('Cleaned stale database entries', [
                'cleaned_count' => $cleaned_count,
                'stale_before' => $stale_before['stale_count'],
                'stale_after' => $stale_after['stale_count'],
            ]);
            
            wp_send_json_success([
                'cleaned' => $cleaned_count,
                'remaining' => $stale_after['stale_count'],
                'message' => sprintf(
                    __('Cleaned %d stale database entries. %d entries still referencing non-existent files.', 'contact-inbox'),
                    $cleaned_count,
                    $stale_after['stale_count']
                ),
            ]);
        } catch (\Throwable $e) {
            Logger::error('Stale DB entry cleanup error', ['error' => $e->getMessage()]);
            wp_send_json_error(['message' => $e->getMessage()]);
        }
    }

    public static function render(): void {
        if (!current_user_can(Config::CAPABILITY)) {
            wp_die(esc_html__('You do not have sufficient permissions to access this page.', 'contact-inbox'));
        }

        $instance = self::instance();

        $cb_states = CircuitBreaker::get_stats();

        // Build circuit status display (compute in controller, not template)
        $circuit_state_labels = [
            'closed'    => __('Available', 'contact-inbox'),
            'open'      => __('Tripped', 'contact-inbox'),
            'half_open' => __('Recovering', 'contact-inbox'),
        ];
        $circuit_summary = [];
        $circuit_badges = []; // For badge display
        foreach ($cb_states as $service => $state) {
            $raw_state = strtolower((string) ($state['state'] ?? ''));
            $fallback_label = $raw_state !== '' ? ucwords(str_replace('_', ' ', $raw_state)) : __('Unknown', 'contact-inbox');
            $display_label = $circuit_state_labels[$raw_state] ?? $fallback_label;
            $circuit_summary[] = sprintf('%s: %s', strtoupper($service), $display_label);
            
            // Skip webhook badges (not typically displayed separately)
            if (strtolower($service) !== 'webhook' && strtolower($service) !== 'webhooks') {
                $circuit_badges[$service] = [
                    'state' => $raw_state,
                    'label' => $display_label,
                    'tooltip' => sprintf(__('Circuit state: %s', 'contact-inbox'), $raw_state !== '' ? strtoupper($raw_state) : __('Unknown', 'contact-inbox')),
                ];
            }
        }
        $circuit_status_line = implode(' · ', $circuit_summary);

        // Get message status counts from repository
        $message_stats = $instance->message_repo->get_status_counts();

        $next_run_email = wp_next_scheduled(Config::CRON_PROCESS_EMAIL);
        $next_run_crm = wp_next_scheduled(Config::CRON_PROCESS_CRM);
        $email_schedule_slug = get_option('contactin_queue_interval', 'contactin_fifteen_minutes');
        $crm_schedule_slug = get_option('contactin_crm_queue_interval', $email_schedule_slug);
        $email_reschedule_default = $instance->get_interval_seconds($email_schedule_slug);
        $crm_reschedule_default = $instance->get_interval_seconds($crm_schedule_slug);
        $format_status = static function (?int $timestamp): string {
            if (!$timestamp) {
                return __('Not currently scheduled', 'contact-inbox');
            }

            return sprintf(
                __('Next run %1$s (%2$s from now)', 'contact-inbox'),
                date_i18n(get_option('date_format') . ' ' . get_option('time_format'), $timestamp),
                human_time_diff(time(), $timestamp)
            );
        };

        $next_run_email_text = $format_status($next_run_email);
        $next_run_crm_text = $format_status($next_run_crm);

        // Fetch unified queue stats (includes attachment_retry, crm_delete, etc.)
        $unified_queue_stats = $instance->queue_repo->get_stats_by_type();
        
        // Extract counts by type from unified queue
        $attachment_retry_stats = $unified_queue_stats['attachment_retry'] ?? [
            'pending' => 0, 'processing' => 0, 'retry' => 0, 'completed' => 0, 'dlq' => 0
        ];
        $crm_delete_stats = $unified_queue_stats['crm_delete'] ?? [
            'pending' => 0, 'processing' => 0, 'retry' => 0, 'completed' => 0, 'dlq' => 0
        ];
        $email_stats = $unified_queue_stats['email'] ?? [
            'pending' => 0, 'processing' => 0, 'retry' => 0, 'completed' => 0, 'dlq' => 0
        ];
        $crm_stats = $unified_queue_stats['crm'] ?? [
            'pending' => 0, 'processing' => 0, 'retry' => 0, 'completed' => 0, 'dlq' => 0
        ];

        // Email queue stats (unified queue)
        $email_queue_pending = (int) ($email_stats['pending'] ?? 0);
        $email_queue_processing = (int) ($email_stats['processing'] ?? 0);
        $email_pending_total = (int) $message_stats['admin_email_pending']
            + (int) $message_stats['user_email_pending']
            + $email_queue_pending
            + $email_queue_processing;

        // CRM queue stats (unified queue)
        $crm_queue_pending = (int) ($crm_stats['pending'] ?? 0);
        $crm_queue_processing = (int) ($crm_stats['processing'] ?? 0);
        $crm_queue_retry = (int) ($crm_stats['retry'] ?? 0);
        $crm_queue_dlq = (int) ($crm_stats['dlq'] ?? 0);
        $crm_pending_total = (int) $message_stats['crm_pending']
            + $crm_queue_pending
            + $crm_queue_processing;

        // Merge legacy message stats with unified queue stats
        $queue_stats = [
            'pending'    => $message_stats['admin_email_pending'] + $message_stats['user_email_pending'] + $message_stats['crm_pending']
                         + $email_stats['pending'] + $crm_stats['pending'] + $attachment_retry_stats['pending'] + $crm_delete_stats['pending'],
            'processing' => $email_stats['processing'] + $crm_stats['processing'] + $attachment_retry_stats['processing'] + $crm_delete_stats['processing'],
            'retry'      => $email_stats['retry'] + $crm_stats['retry'] + $attachment_retry_stats['retry'] + $crm_delete_stats['retry'],
            'completed'  => $message_stats['admin_email_sent'] + $message_stats['user_email_sent'] + $message_stats['crm_sent']
                         + $email_stats['completed'] + $crm_stats['completed'] + $attachment_retry_stats['completed'] + $crm_delete_stats['completed'],
            'dlq'        => $message_stats['admin_email_failed'] + $message_stats['user_email_failed'] + $message_stats['crm_failed']
                         + $email_stats['dlq'] + $crm_stats['dlq'] + $attachment_retry_stats['dlq'] + $crm_delete_stats['dlq'],
        ];

        $queue_stats_by_type = [
            'admin_email' => [
                'pending' => $message_stats['admin_email_pending'],
                'sent'    => $message_stats['admin_email_sent'],
                'failed'  => $message_stats['admin_email_failed'],
            ],
            'user_email' => [
                'pending' => $message_stats['user_email_pending'],
                'sent'    => $message_stats['user_email_sent'],
                'failed'  => $message_stats['user_email_failed'],
            ],
            'crm' => [
                'pending' => $message_stats['crm_pending'],
                'sent'    => $message_stats['crm_sent'],
                'failed'  => $message_stats['crm_failed'],
            ],
            'attachment_retry' => [
                'pending'    => $attachment_retry_stats['pending'],
                'processing' => $attachment_retry_stats['processing'],
                'retry'      => $attachment_retry_stats['retry'],
                'completed'  => $attachment_retry_stats['completed'],
                'dlq'        => $attachment_retry_stats['dlq'],
            ],
            'crm_delete' => [
                'pending'    => $crm_delete_stats['pending'],
                'processing' => $crm_delete_stats['processing'],
                'retry'      => $crm_delete_stats['retry'],
                'completed'  => $crm_delete_stats['completed'],
                'dlq'        => $crm_delete_stats['dlq'],
            ],
        ];

        // Attachment sync stats (SF attachment logs)
        $attachment_repo = new \ContactInbox\Core\Repositories\SalesforceAttachmentRepository();
        $attachment_stats = $attachment_repo->get_status_counts();
        
        // Attachment retry queue stats (unified queue)
        $attachment_retry_pending = (int) ($attachment_retry_stats['pending'] ?? 0)
            + (int) ($attachment_retry_stats['processing'] ?? 0);
        $attachment_retry_retry = (int) ($attachment_retry_stats['retry'] ?? 0);
        $attachment_retry_dlq = (int) ($attachment_retry_stats['dlq'] ?? 0);
        
        // File pending uses unified queue only (SF log is just for tracking/history)
        // Each attachment is queued once in unified queue when created, avoiding double counting
        $file_pending = $attachment_retry_pending + $attachment_retry_retry;
        $file_synced = (int) ($attachment_stats['delivered'] ?? 0);
        $file_failed = (int) ($attachment_stats['failed'] ?? 0);

        // Email queue stats (retry/DLQ from unified queue)
        $email_retry = (int) ($email_stats['retry'] ?? 0);
        $email_dlq = (int) ($email_stats['dlq'] ?? 0);
        $email_completed_total = (int) $message_stats['admin_email_sent']
            + (int) $message_stats['user_email_sent']
            + (int) ($email_stats['completed'] ?? 0);
        $crm_completed_total = (int) $message_stats['crm_sent']
            + (int) ($crm_stats['completed'] ?? 0);
        $crm_delete_completed = (int) ($crm_delete_stats['completed'] ?? 0);

        // CRM deletion stats (queue + GDPR log)
        $delete_pending = (int) ($crm_delete_stats['pending'] ?? 0)
            + (int) ($crm_delete_stats['processing'] ?? 0);
        $delete_retry = (int) ($crm_delete_stats['retry'] ?? 0);
        $delete_completed = (int) ($crm_delete_stats['completed'] ?? 0);
        $delete_dlq = (int) ($crm_delete_stats['dlq'] ?? 0);

        // GDPR is a Pro feature - return empty stats
        $gdpr_stats = ['crm_deleted' => 0, 'ready_for_deletion' => 0];
        $crm_deleted = (int) ($gdpr_stats['crm_deleted'] ?? 0);
        $synced_count = (int) ($gdpr_stats['ready_for_deletion'] ?? 0);

        $failed_messages = $instance->message_repo->get_failed(100);

        // Event-driven queue status
        $email_processor_status = QueueTrigger::get_email_processor_status();
        $crm_processor_status = QueueTrigger::get_crm_processor_status();
        $all_locks = ProcessLock::get_all_locks();

        // Intent Classification stats (compute in controller, pass to template)
        $unclassified_count = $instance->message_repo->count_unclassified();
        $intent_stats = $instance->message_repo->get_intent_stats();
        $intent_categories = IntentClassifier::get_categories();
        
        // Pre-compute category colors for template rendering
        $intent_colors_map = [];
        foreach (array_keys($intent_categories) as $category) {
            $intent_colors_map[$category] = IntentClassifier::get_category_color($category);
        }

        // Attachment cleanup stats (scan once, pass to template)
        $attachment_cleanup_service = \ContactInbox\Core\AttachmentCleanupService::instance();
        $attachment_scan = $attachment_cleanup_service->scan_orphaned_files();
        $attachment_stale = $attachment_cleanup_service->get_stale_entries();
        
        // Calculate orphaned file analytics
        $uploads_dir = WP_CONTENT_DIR . '/uploads/contactin-attachments/';
        $orph_count = count($attachment_scan['orphaned'] ?? []);
        $orph_size = 0;
        foreach ($attachment_scan['orphaned'] ?? [] as $file) {
            $path = $uploads_dir . $file;
            if (is_file($path)) {
                $orph_size += filesize($path);
            }
        }
        
        $temp_orphaned_count = count($attachment_scan['temp_orphaned'] ?? []);
        $orph_size_mb = $orph_size > 0 ? $orph_size / 1048576 : 0;
        $last_scan_timestamp = time();
        $last_scan = date_i18n(get_option('date_format') . ' ' . get_option('time_format'), $last_scan_timestamp);
        
        $disk_file_count = 0;
        if (is_dir($uploads_dir)) {
            $disk_files = array_diff(scandir($uploads_dir), ['.', '..']);
            $disk_file_count = count(array_filter($disk_files, function($f) use ($uploads_dir) {
                return is_file($uploads_dir . $f);
            }));
        }

        $template = CONTACTINBOX_PATH . Config::TEMPLATE_ADMIN . 'maintenance-page.php';
        if (file_exists($template)) {
            include $template;
        } else {
            echo '<div class="notice notice-error"><p>' . esc_html__('Maintenance template not found.', 'contact-inbox') . '</p></div>';
        }
    }

    public function ajax_run_queue_email(): void {
        $this->check_ajax('contactin_maint_run_queue_email');
        try {
            if (ProcessLock::is_locked('email')) {
                $duration = ProcessLock::get_lock_duration('email');
                wp_send_json_error(
                    sprintf(
                        __('Email processor is already running (for %d seconds). Wait for it to complete or force release if stuck.', 'contact-inbox'),
                        $duration
                    )
                );
            }

            $before = $this->message_repo->get_status_counts();
            $before_queue = $this->queue_repo->get_stats_by_type();
            $before_email_stats = $before_queue['email'] ?? [
                'pending' => 0,
                'processing' => 0,
                'retry' => 0,
                'completed' => 0,
                'dlq' => 0,
            ];

            $pending_before = (int) ($before['admin_email_pending'] ?? 0)
                + (int) ($before['user_email_pending'] ?? 0)
                + (int) ($before_email_stats['pending'] ?? 0)
                + (int) ($before_email_stats['processing'] ?? 0);

            if ($pending_before === 0) {
                wp_send_json_success([
                    'message' => __('No pending email items to process.', 'contact-inbox'),
                    'processed' => 0,
                    'pending_remaining' => 0,
                ]);
            }

            wp_schedule_single_event(time(), Config::CRON_PROCESS_EMAIL);
            $spawned = spawn_cron();

            Logger::notice('Maintenance: manual email queue run queued', [
                'spawned' => $spawned,
                'pending_before' => $pending_before,
            ]);

            wp_send_json_success([
                'message' => __('Email processor queued. Progress will update shortly.', 'contact-inbox'),
                'spawned' => (bool) $spawned,
                'pending_before' => $pending_before,
            ]);
        } catch (\Throwable $e) {
            Logger::error('Maintenance run queue failed', ['error' => $e->getMessage()]);
            wp_send_json_error($e->getMessage());
        }
    }

    public function ajax_run_queue_crm(): void {
        $this->check_ajax('contactin_maint_run_queue_crm');
        try {
            if (ProcessLock::is_locked('crm')) {
                $duration = ProcessLock::get_lock_duration('crm');
                wp_send_json_error(
                    sprintf(
                        __('CRM processor is already running (for %d seconds). Wait for it to complete or force release if stuck.', 'contact-inbox'),
                        $duration
                    )
                );
            }

            $before = $this->message_repo->get_status_counts();
            $queue_before = $this->queue_repo->get_stats_by_type();
            $crm_before_stats = $queue_before['crm'] ?? [
                'pending' => 0,
                'processing' => 0,
                'retry' => 0,
                'completed' => 0,
                'dlq' => 0,
            ];
            $attachment_before_stats = $queue_before['attachment_retry'] ?? [
                'pending' => 0,
                'processing' => 0,
                'retry' => 0,
                'completed' => 0,
                'dlq' => 0,
            ];

            $pending_before = (int) ($before['crm_pending'] ?? 0)
                + (int) ($crm_before_stats['pending'] ?? 0)
                + (int) ($crm_before_stats['processing'] ?? 0)
                + (int) ($crm_before_stats['retry'] ?? 0);

            $attachment_before = (int) ($attachment_before_stats['pending'] ?? 0)
                + (int) ($attachment_before_stats['processing'] ?? 0)
                + (int) ($attachment_before_stats['retry'] ?? 0);

            if ($pending_before === 0 && $attachment_before === 0) {
                wp_send_json_success([
                    'message' => __('No pending CRM items to process.', 'contact-inbox'),
                    'records_processed' => 0,
                    'attachments_processed' => 0,
                ]);
            }

            CronJobs::instance()->process_crm_queue();

            $after = $this->message_repo->get_status_counts();
            $queue_after = $this->queue_repo->get_stats_by_type();
            $crm_after_stats = $queue_after['crm'] ?? [
                'pending' => 0,
                'processing' => 0,
                'retry' => 0,
                'completed' => 0,
                'dlq' => 0,
            ];
            $attachment_after_stats = $queue_after['attachment_retry'] ?? [
                'pending' => 0,
                'processing' => 0,
                'retry' => 0,
                'completed' => 0,
                'dlq' => 0,
            ];

            $pending_after = (int) ($after['crm_pending'] ?? 0)
                + (int) ($crm_after_stats['pending'] ?? 0)
                + (int) ($crm_after_stats['processing'] ?? 0)
                + (int) ($crm_after_stats['retry'] ?? 0);

            $processed = max(0, $pending_before - $pending_after);

            // Count attachment queue items after processing
            $attachment_after = (int) ($attachment_after_stats['pending'] ?? 0)
                + (int) ($attachment_after_stats['processing'] ?? 0)
                + (int) ($attachment_after_stats['retry'] ?? 0);
            wp_schedule_single_event(time(), Config::CRON_PROCESS_CRM);
            $spawned = spawn_cron();

            Logger::notice('Maintenance: manual CRM queue run queued', [
                'spawned' => $spawned,
                'pending_before' => $pending_before,
                'attachments_before' => $attachment_before,
            ]);

            wp_send_json_success([
                'message' => __('CRM processor queued. Progress will update shortly.', 'contact-inbox'),
                'spawned' => (bool) $spawned,
                'pending_before' => $pending_before,
                'attachments_before' => $attachment_before
            ]);
        } catch (Exception $e) {
            Logger::alert('Maintenance: Manual CRM queue run failed', ['error' => $e->getMessage()]);
            wp_send_json_error(['message' => $e->getMessage()]);
        }
    }

    public function ajax_retry_email_dlq(): void {
        $this->check_ajax('contactin_maint_retry_email_dlq');
        try {
            $retries = $this->retry_failed_channels(['admin_email', 'user_email'], 200);
            $admin_retried = $retries['admin_email'] ?? 0;
            $user_retried = $retries['user_email'] ?? 0;
            $total = $admin_retried + $user_retried;

            $queue_failed = $this->queue_repo->get_failed_by_type('email', 200);
            $queue_retried = 0;
            foreach ($queue_failed as $item) {
                if (QueueManager::mark_retry((int) $item['id'])) {
                    $queue_retried++;
                }
            }

            $dlq_ids = $this->queue_repo->get_dlq_ids_by_type('email', 200);
            $dlq_retried = 0;
            foreach ($dlq_ids as $dlq_id) {
                $result = QueueManager::retry_dlq_item((int) $dlq_id);
                if (!is_wp_error($result)) {
                    $dlq_retried++;
                }
            }

            Logger::notice('Maintenance: retried failed email notifications', [
                'admin' => $admin_retried,
                'user'  => $user_retried,
                'queue_retried' => $queue_retried,
                'dlq_retried' => $dlq_retried,
                'total' => $total + $queue_retried + $dlq_retried,
            ]);

            wp_send_json_success([
                'message' => sprintf(
                    __('Queued %1$d failed email notifications for retry (legacy: %2$d, queue: %3$d, dlq: %4$d).', 'contact-inbox'),
                    $total + $queue_retried + $dlq_retried,
                    $total,
                    $queue_retried,
                    $dlq_retried
                ),
            ]);
        } catch (\Throwable $e) {
            Logger::error('Maintenance retry email DLQ failed', ['error' => $e->getMessage()]);
            wp_send_json_error($e->getMessage());
        }
    }

    public function ajax_retry_crm_dlq(): void {
        $this->check_ajax('contactin_maint_retry_crm_dlq');
        try {
            $failed = $this->message_repo->get_failed(200);
            $crm_retried = 0;
            $file_retried = 0;
            $queue_crm_retried = 0;
            $queue_file_retried = 0;
            $dlq_crm_retried = 0;
            $dlq_file_retried = 0;

            // Retry failed CRM record syncs - push them into the queue for proper processing
            foreach ($failed as $message) {
                $message_id = (int)($message->id ?? 0);
                if ($message_id <= 0) {
                    continue;
                }

                if (($message->crm_status ?? null) === Config::CRM_FAILED) {
                    $queued = \ContactInbox\Core\CRMQueueService::queue_message_sync($message_id, 2, true);
                    if ($queued) {
                        $crm_retried++;
                    }
                }
            }

            // Retry failed attachment uploads from queue
            $failed_attachments = $this->queue_repo->get_failed_by_type('attachment_retry', 100);

            foreach ($failed_attachments as $item) {
                QueueManager::mark_retry((int)$item['id']);
                $queue_file_retried++;
            }

            // Retry failed CRM queue items
            $failed_crm_queue = $this->queue_repo->get_failed_by_type('crm', 100);
            foreach ($failed_crm_queue as $item) {
                if (QueueManager::mark_retry((int) $item['id'])) {
                    $queue_crm_retried++;
                }
            }

            // Retry DLQ items for CRM and attachment retries
            $crm_dlq_ids = $this->queue_repo->get_dlq_ids_by_type('crm', 100);
            foreach ($crm_dlq_ids as $dlq_id) {
                $result = QueueManager::retry_dlq_item((int) $dlq_id);
                if (!is_wp_error($result)) {
                    $dlq_crm_retried++;
                }
            }

            $attachment_dlq_ids = $this->queue_repo->get_dlq_ids_by_type('attachment_retry', 100);
            foreach ($attachment_dlq_ids as $dlq_id) {
                $result = QueueManager::retry_dlq_item((int) $dlq_id);
                if (!is_wp_error($result)) {
                    $dlq_file_retried++;
                }
            }

            Logger::notice('Maintenance: retried failed CRM syncs', [
                'records' => $crm_retried,
                'queue_records' => $queue_crm_retried,
                'queue_files' => $queue_file_retried,
                'dlq_records' => $dlq_crm_retried,
                'dlq_files' => $dlq_file_retried,
            ]);

            wp_send_json_success([
                'message' => sprintf(
                    __('Queued %1$d failed CRM record(s) and %2$d failed file(s) for retry (queue: %3$d/%4$d, dlq: %5$d/%6$d).', 'contact-inbox'),
                    $crm_retried + $queue_crm_retried + $dlq_crm_retried,
                    $queue_file_retried + $dlq_file_retried,
                    $queue_crm_retried,
                    $queue_file_retried,
                    $dlq_crm_retried,
                    $dlq_file_retried
                ),
            ]);
        } catch (\Throwable $e) {
            Logger::error('Maintenance retry CRM DLQ failed', ['error' => $e->getMessage()]);
            wp_send_json_error($e->getMessage());
        }
    }

    public function ajax_reset_circuits(): void {
        $this->check_ajax('contactin_maint_reset_circuits');
        try {
            $services = ['smtp', 'crm'];
            foreach ($services as $service) {
                CircuitBreaker::reset($service);
            }
            Logger::notice('Maintenance: reset circuit breakers', ['services' => $services]);
            wp_send_json_success(['message' => sprintf(__('Reset circuit breakers for: %s', 'contact-inbox'), strtoupper(implode(', ', $services)))]);
        } catch (\Throwable $e) {
            Logger::error('Maintenance reset circuits failed', ['error' => $e->getMessage()]);
            wp_send_json_error($e->getMessage());
        }
    }

    public function ajax_skip_email(): void {
        $this->check_ajax('contactin_maint_skip_email');
        try {
            $result = QueueManager::skip_email_items_if_smtp_disabled();
            Logger::notice('Maintenance: skipped email items (SMTP disabled)', $result);
            $total_skipped = ($result['skipped_admin'] ?? 0) + ($result['skipped_user'] ?? 0);

            wp_send_json_success([
                'message' => sprintf(
                    __('Skipped %1$d email notifications (admin: %2$d, user: %3$d).', 'contact-inbox'),
                    $total_skipped,
                    $result['skipped_admin'] ?? 0,
                    $result['skipped_user'] ?? 0
                ),
            ]);
        } catch (\Throwable $e) {
            Logger::error('Maintenance skip email failed', ['error' => $e->getMessage()]);
            wp_send_json_error($e->getMessage());
        }
    }

    public function ajax_reschedule_email_queue(): void {
        $this->check_ajax('contactin_maint_reschedule_email_queue');
        try {
            $interval = get_option('contactin_queue_interval', 'contactin_fifteen_minutes');
            $default_delay = $this->get_interval_seconds($interval);
            $delay = $this->validate_delay_seconds($default_delay, 60, 3600);

            Lifecycle::reschedule_cron_jobs(
                [Config::CRON_PROCESS_EMAIL],
                [Config::CRON_PROCESS_EMAIL => $delay]
            );

            Logger::notice('Maintenance: rescheduled email queue', [
                'interval' => $interval,
                'delay' => $delay,
            ]);
            
            $interval_label = str_replace(['contactin_', '_'], ['', ' '], $interval);
            wp_send_json_success([
                'message' => sprintf(
                    __('Email queue rescheduled to interval: %1$s (next run in %2$d seconds).', 'contact-inbox'),
                    ucwords($interval_label),
                    $delay
                ),
            ]);
        } catch (\Throwable $e) {
            Logger::error('Maintenance reschedule email queue failed', ['error' => $e->getMessage()]);
            wp_send_json_error($e->getMessage());
        }
    }

    public function ajax_reschedule_crm_queue(): void {
        $this->check_ajax('contactin_maint_reschedule_crm_queue');
        try {
            $interval = get_option('contactin_crm_queue_interval', get_option('contactin_queue_interval', 'contactin_fifteen_minutes'));
            $default_delay = $this->get_interval_seconds($interval);
            $delay = $this->validate_delay_seconds($default_delay, 60, 3600);

            Lifecycle::reschedule_cron_jobs(
                [Config::CRON_PROCESS_CRM],
                [Config::CRON_PROCESS_CRM => $delay]
            );

            Logger::notice('Maintenance: rescheduled CRM queue', [
                'interval' => $interval,
                'delay' => $delay,
            ]);
            
            $interval_label = str_replace(['contactin_', '_'], ['', ' '], $interval);
            wp_send_json_success([
                'message' => sprintf(
                    __('CRM queue rescheduled to interval: %1$s (next run in %2$d seconds).', 'contact-inbox'),
                    ucwords($interval_label),
                    $delay
                ),
            ]);
        } catch (\Throwable $e) {
            Logger::error('Maintenance reschedule CRM queue failed', ['error' => $e->getMessage()]);
            wp_send_json_error($e->getMessage());
        }
    }

    private function get_interval_seconds(string $schedule_slug): int {
        $schedules = wp_get_schedules();
        if (isset($schedules[$schedule_slug]['interval'])) {
            return (int)$schedules[$schedule_slug]['interval'];
        }

        return 120;
    }

    /**
     * AJAX: Get current lock status
     */
    public function ajax_get_lock_status(): void {
        $this->check_ajax('contactin_maint_get_lock_status');
        
        try {
            $email_status = QueueTrigger::get_email_processor_status();
            $crm_status = QueueTrigger::get_crm_processor_status();
            $all_locks = ProcessLock::get_all_locks();

            wp_send_json_success([
                'email' => $email_status,
                'crm' => $crm_status,
                'locks' => $all_locks,
            ]);
        } catch (\Throwable $e) {
            Logger::error('Maintenance get lock status failed', ['error' => $e->getMessage()]);
            wp_send_json_error($e->getMessage());
        }
    }

    /**
     * AJAX: Force release a process lock
     */
    public function ajax_force_release_lock(): void {
        $this->check_ajax('contactin_maint_force_release_lock');
        
        try {
            // Validate and sanitize process parameter
            $process = $this->post_text('process');
            if ($process === '') {
                wp_send_json_error(__('Process parameter is required.', 'contact-inbox'));
                return;
            }
            
            // Whitelist validation
            if (!in_array($process, ['email', 'crm'], true)) {
                wp_send_json_error(
                    sprintf(
                        __('Invalid process type "%s". Must be "email" or "crm".', 'contact-inbox'),
                        esc_html($process)
                    )
                );
                return;
            }

            $duration = ProcessLock::get_lock_duration($process);
            
            // Safety check: only force release if lock is > 5 minutes old
            if ($duration < 300) {
                wp_send_json_error(
                    sprintf(
                        __('Lock is only %d seconds old. Wait until it\'s at least 5 minutes old before force releasing.', 'contact-inbox'),
                        $duration
                    )
                );
                return;
            }

            $success = ProcessLock::force_release($process, 300);
            
            if ($success) {
                Logger::warning("Maintenance: Force released {$process} lock", ['duration' => $duration]);
                wp_send_json_success([
                    'message' => sprintf(
                        __('%s lock force released (was held for %d seconds).', 'contact-inbox'),
                        ucfirst($process),
                        $duration
                    ),
                ]);
            } else {
                wp_send_json_error(__('Failed to release lock.', 'contact-inbox'));
            }
        } catch (\Throwable $e) {
            Logger::error('Maintenance force release lock failed', ['error' => $e->getMessage()]);
            wp_send_json_error($e->getMessage());
        }
    }

    /**
     * AJAX: Manually trigger email processor
     */
    public function ajax_trigger_email_processor(): void {
        $this->check_ajax('contactin_maint_trigger_email_processor');
        
        try {
            // Check if processor is already running
            if (ProcessLock::is_locked('email')) {
                $duration = ProcessLock::get_lock_duration('email');
                wp_send_json_error(
                    sprintf(
                        __('Email processor is already running (for %d seconds). Wait for it to complete or force release if stuck.', 'contact-inbox'),
                        $duration
                    )
                );
                return;
            }

            // Check if there are pending items
            $pending_count = QueueTrigger::get_pending_email_count();
            if ($pending_count === 0) {
                wp_send_json_success([
                    'message' => __('No pending emails to process.', 'contact-inbox'),
                    'processed' => 0,
                ]);
                return;
            }

            // Trigger processing
            $triggered = QueueTrigger::maybe_trigger_email_processor();
            
            if ($triggered) {
                Logger::notice('Maintenance: Manually triggered email processor', ['pending' => $pending_count]);
                wp_send_json_success([
                    'message' => sprintf(
                        __('Email processor triggered. %d pending items will be processed.', 'contact-inbox'),
                        $pending_count
                    ),
                    'pending' => $pending_count,
                    'triggered' => true,
                ]);
            } else {
                wp_send_json_error(
                    __('Failed to trigger email processor. Check that SMTP is enabled and configured.', 'contact-inbox')
                );
            }
        } catch (\Throwable $e) {
            Logger::error('Maintenance trigger email processor failed', ['error' => $e->getMessage()]);
            wp_send_json_error($e->getMessage());
        }
    }

    /**
     * AJAX: Manually trigger CRM processor
     */
    public function ajax_trigger_crm_processor(): void {
        $this->check_ajax('contactin_maint_trigger_crm_processor');
        
        try {
            // Check if processor is already running
            if (ProcessLock::is_locked('crm')) {
                $duration = ProcessLock::get_lock_duration('crm');
                wp_send_json_error(
                    sprintf(
                        __('CRM processor is already running (for %d seconds). Wait for it to complete or force release if stuck.', 'contact-inbox'),
                        $duration
                    )
                );
                return;
            }

            // Check if there are pending items
            $pending_count = QueueTrigger::get_pending_crm_count();
            if ($pending_count === 0) {
                wp_send_json_success([
                    'message' => __('No pending CRM syncs to process.', 'contact-inbox'),
                    'processed' => 0,
                ]);
                return;
            }

            // Trigger processing
            $triggered = QueueTrigger::maybe_trigger_crm_processor();
            
            if ($triggered) {
                Logger::notice('Maintenance: Manually triggered CRM processor', ['pending' => $pending_count]);
                wp_send_json_success([
                    'message' => sprintf(
                        __('CRM processor triggered. %d pending syncs will be processed.', 'contact-inbox'),
                        $pending_count
                    ),
                    'pending' => $pending_count,
                    'triggered' => true,
                ]);
            } else {
                wp_send_json_error(
                    __('Failed to trigger CRM processor. Check that CRM integration is enabled and configured.', 'contact-inbox')
                );
            }
        } catch (\Throwable $e) {
            Logger::error('Maintenance trigger CRM processor failed', ['error' => $e->getMessage()]);
            wp_send_json_error($e->getMessage());
        }
    }

    /**
     * AJAX: Get comprehensive cron diagnostics
     */
    public function ajax_queue_progress(): void {
        $this->check_ajax('contactin_maint_queue_progress');

        try {
            $status_counts = $this->message_repo->get_status_counts();
            $queue_stats = $this->queue_repo->get_stats_by_type();

            $email_queue = $queue_stats['email'] ?? [
                'pending' => 0,
                'processing' => 0,
                'retry' => 0,
                'completed' => 0,
                'dlq' => 0,
                'total' => 0,
            ];
            $crm_queue = $queue_stats['crm'] ?? [
                'pending' => 0,
                'processing' => 0,
                'retry' => 0,
                'completed' => 0,
                'dlq' => 0,
                'total' => 0,
            ];
            $attachment_queue = $queue_stats['attachment_retry'] ?? [
                'pending' => 0,
                'processing' => 0,
                'retry' => 0,
                'completed' => 0,
                'dlq' => 0,
                'total' => 0,
            ];

            $email_pending_total = (int) ($status_counts['admin_email_pending'] ?? 0)
                + (int) ($status_counts['user_email_pending'] ?? 0)
                + (int) ($email_queue['pending'] ?? 0)
                + (int) ($email_queue['processing'] ?? 0)
                + (int) ($email_queue['retry'] ?? 0);

            $crm_pending_total = (int) ($status_counts['crm_pending'] ?? 0)
                + (int) ($crm_queue['pending'] ?? 0)
                + (int) ($crm_queue['processing'] ?? 0)
                + (int) ($crm_queue['retry'] ?? 0);

            $attachment_pending_total = (int) ($attachment_queue['pending'] ?? 0)
                + (int) ($attachment_queue['processing'] ?? 0)
                + (int) ($attachment_queue['retry'] ?? 0);

            wp_send_json_success([
                'email' => [
                    'pending_total' => $email_pending_total,
                    'queue' => $email_queue,
                    'legacy_pending' => [
                        'admin' => (int) ($status_counts['admin_email_pending'] ?? 0),
                        'user' => (int) ($status_counts['user_email_pending'] ?? 0),
                    ],
                    'in_progress' => ProcessLock::is_locked('email'),
                ],
                'crm' => [
                    'pending_total' => $crm_pending_total,
                    'queue' => $crm_queue,
                    'legacy_pending' => (int) ($status_counts['crm_pending'] ?? 0),
                    'in_progress' => ProcessLock::is_locked('crm'),
                ],
                'attachments' => [
                    'pending_total' => $attachment_pending_total,
                    'queue' => $attachment_queue,
                ],
            ]);
        } catch (\Throwable $e) {
            Logger::error('Maintenance queue progress failed', ['error' => $e->getMessage()]);
            wp_send_json_error($e->getMessage());
        }
    }

    public function ajax_get_cron_diagnostics(): void {
        $this->check_ajax('contactin_maint_get_cron_diagnostics');

        try {
            $diagnostics = \ContactInbox\Core\CronDiagnostics::run_diagnostics();
            $duplicates = \ContactInbox\Core\CronDiagnostics::check_for_duplicate_singles();

            wp_send_json_success([
                'diagnostics' => $diagnostics,
                'duplicates' => $duplicates,
                'text_report' => \ContactInbox\Core\CronDiagnostics::get_diagnostic_text(),
            ]);
        } catch (\Throwable $e) {
            Logger::error('Maintenance get cron diagnostics failed', ['error' => $e->getMessage()]);
            wp_send_json_error($e->getMessage());
        }
    }

    private function check_ajax(string $action): void {
        check_ajax_referer($action, 'nonce');
        if (!current_user_can(Config::CAPABILITY)) {
            wp_send_json_error(__('Insufficient permissions.', 'contact-inbox'));
        }
    }

    /**
     * Helper method to retry failed messages for specified channels
     *
     * @param array<string> $channels Channels to retry: 'admin_email', 'user_email', 'crm'
     * @param int $limit Maximum number of messages to attempt
     * @return array<string, int> Count of retries per channel
     */
    private function retry_failed_channels(array $channels, int $limit = 200): array {
        $failed = $this->message_repo->get_failed($limit);
        $retries = array_fill_keys($channels, 0);

        foreach ($failed as $message) {
            $message_id = (int)($message->id ?? 0);
            if ($message_id <= 0) {
                continue;
            }

            if (in_array('admin_email', $channels, true) && ($message->admin_email_status ?? null) === Config::EMAIL_FAILED) {
                if ($this->message_repo->update_channel_status($message_id, 'admin_email', Config::EMAIL_PENDING)) {
                    $retries['admin_email']++;
                }
            }
            
            if (in_array('user_email', $channels, true) && ($message->user_email_status ?? null) === Config::EMAIL_FAILED) {
                if ($this->message_repo->update_channel_status($message_id, 'user_email', Config::EMAIL_PENDING)) {
                    $retries['user_email']++;
                }
            }
            
            if (in_array('crm', $channels, true) && ($message->crm_status ?? null) === Config::CRM_FAILED) {
                if ($this->message_repo->update_channel_status($message_id, 'crm', Config::CRM_PENDING)) {
                    $retries['crm']++;
                }
            }
        }

        return $retries;
    }

    /**
     * Validate and sanitize delay_seconds input
     *
     * @param int $default Default value if not provided or invalid
     * @param int $min Minimum allowed value
     * @param int $max Maximum allowed value
     * @return int Validated delay in seconds
     */
    private function validate_delay_seconds(int $default, int $min = 60, int $max = 3600): int {
        $delay = $this->post_int('delay_seconds', 0);
        if ($delay <= 0) {
            return $default;
        }
        
        // Enforce reasonable limits
        return max($min, min($delay, $max));
    }

    /**
     * AJAX: Queue synced GDPR deletions for CRM cleanup.
     * This schedules deletions for background processing using existing sync infrastructure.
     */
    public function ajax_gdpr_queue_delete(): void {
        $this->check_ajax('contactin_maint_gdpr_queue_delete');

        // GDPR is a Pro feature
        wp_send_json_error([
            'message' => __('GDPR features are available in Contact Inbox Pro.', 'contact-inbox')
        ]);
    }

    /**
     * AJAX: Immediately delete synced contacts from CRM.
     * Uses same sync logic as form submissions with full fallback support.
     */
    public function ajax_gdpr_immediate_delete(): void {
        $this->check_ajax('contactin_maint_gdpr_immediate_delete');

        // GDPR is a Pro feature
        wp_send_json_error([
            'message' => __('GDPR features are available in Contact Inbox Pro.', 'contact-inbox')
        ]);
    }

    /**
     * AJAX: Reclassify unclassified messages using current intent patterns
     */
    public function ajax_reclassify_intent(): void {
        $this->check_ajax('contactin_maint_reclassify_intent');

        try {
            $classifier = IntentClassifier::instance();
            
            // Get count of unclassified messages
            $total_unclassified = $classifier->count_unclassified_messages();
            
            if ($total_unclassified === 0) {
                wp_send_json_success([
                    'message' => __('No unclassified messages found.', 'contact-inbox'),
                    'total_unclassified' => 0,
                    'processed' => 0,
                    'success' => 0,
                    'failed' => 0,
                ]);
                return;
            }

            // Process first batch (100 messages)
            $batch_size = 100;
            $result = $classifier->bulk_classify_unclassified($batch_size, 0);
            
            if (!is_array($result)) {
                throw new \Exception('Invalid reclassification result');
            }

            $remaining = max(0, $total_unclassified - ($result['processed'] ?? 0));
            $processed = $result['processed'] ?? 0;
            $success = $result['success'] ?? 0;
            $failed = $result['failed'] ?? 0;
            
            // Build breakdown message with category labels and percentages
            $breakdown = $result['breakdown'] ?? [];
            $categories = IntentClassifier::get_categories();
            $breakdownParts = [];
            
            if (!empty($breakdown) && $success > 0) {
                foreach ($breakdown as $category => $count) {
                    if ($count > 0) {
                        $percentage = ($count / $success) * 100;
                        $label = $categories[$category] ?? ucfirst($category);
                        $breakdownParts[] = sprintf('%s: %d (%.1f%%)', $label, $count, $percentage);
                    }
                }
            }
            
            // Build user-friendly message based on success rate
            $message = '';
            $success_rate = $processed > 0 ? ($success / $processed) * 100 : 0;
            
            if ($success > 0) {
                // Show positive results first
                if (!empty($breakdownParts)) {
                    $message = sprintf(
                        __('Successfully classified %d message(s): ', 'contact-inbox'),
                        $success
                    ) . implode(', ', $breakdownParts) . '. ';
                } else {
                    $message = sprintf(
                        __('Successfully classified %d out of %d message(s). ', 'contact-inbox'),
                        $success,
                        $processed
                    );
                }
                
                if ($failed > 0) {
                    $message .= sprintf(
                        __('%d message(s) could not be automatically classified.', 'contact-inbox'),
                        $failed
                    );
                }
            } else {
                // No messages were classified - provide helpful guidance
                $message = sprintf(
                    __('Processed %d message(s), but automatic classification was not confident enough.', 'contact-inbox'),
                    $processed
                ) . ' ';
                
                $message .= __(
                    'This happens when messages don\'t match existing intent patterns. You can classify these manually from the Messages page by clicking on individual messages and selecting their intent category.',
                    'contact-inbox'
                );
            }

            if ($remaining > 0) {
                $message .= ' ' . sprintf(
                    __('%d unclassified message(s) remaining for next batch.', 'contact-inbox'),
                    $remaining
                );
            }

            Logger::info('Maintenance: Manual intent reclassification triggered', [
                'batch_size' => $batch_size,
                'processed' => $processed,
                'success' => $success,
                'failed' => $failed,
                'success_rate' => round($success_rate, 2) . '%',
                'breakdown' => $breakdown,
                'total_unclassified' => $total_unclassified,
                'remaining' => $remaining,
            ]);

            wp_send_json_success([
                'message' => $message,
                'breakdown' => $breakdownParts,
                'total_unclassified' => $total_unclassified,
                'processed' => $processed,
                'success' => $success,
                'failed' => $failed,
                'remaining' => $remaining,
                'success_rate' => round($success_rate, 2),
            ]);

        } catch (\Throwable $e) {
            Logger::error('Maintenance: Intent reclassification failed', [
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);

            wp_send_json_error([
                'message' => __('Reclassification failed: ', 'contact-inbox') . $e->getMessage(),
            ]);
        }
    }
}
