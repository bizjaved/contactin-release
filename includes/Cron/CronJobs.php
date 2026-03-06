<?php
namespace ContactInbox\Cron;

use ContactInbox\Traits\Singleton;
use ContactInbox\Core\Config;
use ContactInbox\Core\DB;
use ContactInbox\Core\Settings;
use ContactInbox\Core\Logger;
use ContactInbox\Core\QueueManager;
use ContactInbox\Core\QueueMonitor;
use ContactInbox\Core\AlertSystem;
use ContactInbox\Core\CircuitBreaker;
use ContactInbox\Core\CRMConnector;
use ContactInbox\Core\RateLimiter;
use ContactInbox\Core\GDPR;
use ContactInbox\Core\ProcessLock;
use ContactInbox\Core\NameFormatter;
use ContactInbox\Core\IntentClassifier;
use ContactInbox\Core\ErrorClassifier;
use ContactInbox\Core\AlertGenerator;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class CronJobs {
    use Singleton;

    /**
     * Register cron job hooks.
     */
    public function register(): void {
        $is_free = defined('CONTACTINBOX_IS_FREE') && CONTACTINBOX_IS_FREE;
        add_action( Config::CRON_CLEANUP, [ $this, 'run_cleanup' ] );
        // Email and CRM processing - now separate crons
        add_action( Config::CRON_PROCESS_EMAIL, [ $this, 'process_email_queue' ] );
        if ( ! $is_free ) {
            add_action( Config::CRON_GDPR,    [ $this, 'run_gdpr_expiry' ] );
            add_action( Config::CRON_PROCESS_CRM,   [ $this, 'process_crm_queue' ] );
            add_action( Config::CRON_GDPR_CLEANUP,  [ $this, 'run_gdpr_deletion_cleanup' ] );
        }
        // Intent classification maintenance
        add_action( Config::CRON_RECLASSIFY_UNCLASSIFIED, [ $this, 'run_reclassify_unclassified' ] );

        // DISABLED: Cron health check was causing duplicate schedules
        // Schedules are created during plugin activation
        // self::ensure_cron_health();
    }

    /**
     * Ensure cron jobs are scheduled and healthy
     *
     * Detects missing schedules and creates them (throttled to once per hour).
     * This is a recovery mechanism for when schedules get accidentally deleted.
     */
    private static function ensure_cron_health(): void {
        $email_interval = get_option('contactin_queue_interval', 'contactin_fifteen_minutes');
        $crm_interval = get_option('contactin_crm_queue_interval', $email_interval);

        $schedules = wp_get_schedules();
        $email_interval_seconds = $schedules[$email_interval]['interval'] ?? 900;
        $crm_interval_seconds = $schedules[$crm_interval]['interval'] ?? 900;

        // Email queue processor - only schedule if truly missing
        $email_next = wp_next_scheduled(Config::CRON_PROCESS_EMAIL);
        if (!$email_next) {
            // Double-check by looking at the cron array directly to avoid wp_next_scheduled issues
            $cron = get_option('cron', []);
            $has_email_cron = false;
            foreach ($cron as $timestamp => $hooks) {
                if (isset($hooks[Config::CRON_PROCESS_EMAIL])) {
                    $has_email_cron = true;
                    break;
                }
            }
            
            if (!$has_email_cron) {
                wp_schedule_event(time() + $email_interval_seconds, $email_interval, Config::CRON_PROCESS_EMAIL);
                Logger::info('Email queue cron recovered: was missing, rescheduled', [
                    'interval' => $email_interval,
                    'interval_seconds' => $email_interval_seconds,
                ]);
            }
        }

        // CRM queue processor - only schedule if truly missing
        $crm_next = wp_next_scheduled(Config::CRON_PROCESS_CRM);
        if (!$crm_next) {
            // Double-check by looking at the cron array directly to avoid wp_next_scheduled issues
            $cron = get_option('cron', []);
            $has_crm_cron = false;
            foreach ($cron as $timestamp => $hooks) {
                if (isset($hooks[Config::CRON_PROCESS_CRM])) {
                    $has_crm_cron = true;
                    break;
                }
            }
            
            if (!$has_crm_cron) {
                wp_schedule_event(time() + $crm_interval_seconds, $crm_interval, Config::CRON_PROCESS_CRM);
                Logger::info('CRM queue cron recovered: was missing, rescheduled', [
                    'interval' => $crm_interval,
                    'interval_seconds' => $crm_interval_seconds,
                ]);
            }
        }
    }

    /**
     * Daily cleanup job – prune logs, clear caches.
     */
    public function run_cleanup(): void {
        $record_id = CronMonitor::start_job(Config::CRON_CLEANUP);
        if (!$record_id) {
            return;
        }

        try {
            $items_deleted = 0; // Initialize to avoid undefined variable warning
            $settings = Settings::get_settings();

            // ...existing cleanup code...

            // Orphaned attachment analytics (daily scan, no deletion)
            $stats = \ContactInbox\Core\AttachmentCleanupService::instance()->get_orphaned_analytics();
            update_option('contactin_orphaned_attachments_stats', $stats);
            
            // Clean up old temporary files (older than 24 hours)
            $temp_cleanup = \ContactInbox\Core\AttachmentCleanupService::instance()->delete_old_temp_files_cleanup();

            // ...existing cleanup code...

            Logger::info(
                'Daily cleanup executed',
                [
                    // ...existing log fields...
                    'orphaned_attachments' => $stats,
                    'temp_files_deleted' => $temp_cleanup,
                ]
            );

            CronMonitor::success_job($record_id, $items_deleted);
        } catch ( \Throwable $e ) {
            CronMonitor::fail_job($record_id, 'exception', $e->getMessage());
            Logger::error(
                'Cleanup cron error: ' . $e->getMessage(),
                ['file' => $e->getFile(), 'line' => $e->getLine()]
            );
        }
    }

    /**
     * Hourly GDPR expiry check – delete expired GDPR records.
     */
    public function run_gdpr_expiry(): void {
        if ( defined('CONTACTINBOX_IS_FREE') && CONTACTINBOX_IS_FREE ) {
            return;
        }
        $record_id = CronMonitor::start_job(Config::CRON_GDPR);
        if (!$record_id) {
            return;
        }

        try {
            $deleted = DB::instance()->delete_expired_gdpr();

            Logger::info(
                'GDPR expiry check completed',
                ['deleted' => $deleted]
            );

            CronMonitor::success_job($record_id, $deleted);
        } catch ( \Throwable $e ) {
            CronMonitor::fail_job($record_id, 'exception', $e->getMessage());
            Logger::error(
                'GDPR expiry check error: ' . $e->getMessage(),
                ['file' => $e->getFile(), 'line' => $e->getLine()]
            );
        }
    }

    /**
     * Process email queue with distributed locking
     *
     * Processes pending admin and user emails using ProcessLock to ensure
     * only one processor runs at a time. Fails gracefully if another process
     * is already running (lock is held).
     *
     * Lock TTL: 5 minutes (auto-expires if process crashes)
     * Runs on scheduled interval (default: 15 minutes) or triggered by form submission
     */
    public function process_email_queue(): void {
        // Attempt to acquire lock
        if (!ProcessLock::acquire('email', 300)) {
            Logger::debug('Could not acquire email processor lock, another process is running');
            return;
        }

        $record_id = CronMonitor::start_job(Config::CRON_PROCESS_EMAIL);
        if (!$record_id) {
            ProcessLock::release('email');
            return;
        }

        $max_iterations = 50;
        $processed = 0;

        Logger::info('Email queue processor started with lock acquired');

        try {
            global $wpdb;
            $table = $wpdb->prefix . Config::TABLE_MESSAGES;
            $settings = get_option(Config::OPTION_SETTINGS, []);

            // PHASE 1: Process queue table items (new unified queue system)
            // This processes queued items (email, attachment_retry types) with automatic retry logic
            while ($processed < $max_iterations) {
                $queue_item = QueueManager::get_next_item();
                if (!$queue_item) {
                    break; // No more queue items
                }

                try {
                    QueueManager::mark_processing($queue_item['id']);
                    $queue_data = json_decode($queue_item['data'], true) ?: [];

                    // Dispatch by queue type
                    switch ($queue_item['type']) {
                        case 'email':
                            self::process_email_from_queue((int)$queue_item['id'], $queue_data);
                            break;

                        case 'attachment_retry':
                            self::process_attachment_retry((int)$queue_item['id'], $queue_data);
                            break;

                        case 'webhook':
                            self::process_webhook((int)$queue_item['id'], $queue_data);
                            break;

                        default:
                            Logger::warning('Unknown queue type in email processor', [
                                'queue_id' => $queue_item['id'],
                                'type' => $queue_item['type'],
                            ]);
                            QueueManager::mark_failed($queue_item['id'], "Unknown queue type: {$queue_item['type']}");
                            break;
                    }

                    $processed++;
                } catch (\Throwable $e) {
                    Logger::error('Error processing queue item', [
                        'queue_id' => $queue_item['id'],
                        'type' => $queue_item['type'],
                        'error' => $e->getMessage(),
                    ]);
                    QueueManager::mark_failed($queue_item['id'], $e->getMessage());
                    $processed++;
                }
            }

            // PHASE 2: Process legacy message table items (for backward compatibility)
            // If we still have iterations left, process messages from the old system
            if ($processed < $max_iterations) {
                // Process admin emails
                $admin_count = self::process_pending_admin_emails($table, $settings, $max_iterations - $processed);
                $processed += $admin_count;
            }

            // Process user emails
            if ($processed < $max_iterations) {
                $user_count = self::process_pending_user_emails($table, $settings, $max_iterations - $processed);
                $processed += $user_count;
            }

            Logger::info("Email queue processor completed. Processed {$processed} items");

            // Record success with items processed
            CronMonitor::success_job($record_id, $processed);

        } catch (\Throwable $e) {
            CronMonitor::fail_job($record_id, 'exception', $e->getMessage());
            Logger::critical(
                'Email queue processor fatal error: ' . $e->getMessage(),
                ['file' => $e->getFile(), 'line' => $e->getLine()]
            );
        } finally {
            // DISABLED: This was creating duplicate schedules in a feedback loop
            // Schedules are created during plugin activation
            // self::ensure_recurring_schedule(
            //     Config::CRON_PROCESS_EMAIL,
            //     'contactin_queue_interval',
            //     'contactin_fifteen_minutes'
            // );

            // Always release lock, even if exception occurred
            ProcessLock::release('email');
        }
    }

    /**
     * Process CRM queue with distributed locking
     *
     * Processes pending CRM syncs using ProcessLock to ensure only one
     * processor runs at a time. Fails gracefully if another process is
     * already running (lock is held).
     *
     * Lock TTL: 5 minutes (auto-expires if process crashes)
     * Runs on scheduled interval (default: 15 minutes) or triggered by form submission
     */
    public function process_crm_queue(): void {
        // Attempt to acquire lock
        if (!ProcessLock::acquire('crm', 300)) {
            Logger::debug('Could not acquire CRM processor lock, another process is running');
            return;
        }

        $record_id = CronMonitor::start_job(Config::CRON_PROCESS_CRM);
        if (!$record_id) {
            ProcessLock::release('crm');
            return;
        }

        $max_iterations = 50;
        $processed = 0;

        Logger::info('CRM queue processor started with lock acquired');

        try {
            global $wpdb;
            $table = $wpdb->prefix . Config::TABLE_MESSAGES;
            $crm_settings = \ContactInbox\Core\CRMSettings::get_settings();

            // PHASE 1: Process queue table items (new unified queue system)
            // This processes queued items (crm, attachment_retry types) with automatic retry logic
            while ($processed < $max_iterations) {
                $queue_item = QueueManager::get_next_item(['crm', 'attachment_retry', 'crm_delete']);
                if (!$queue_item) {
                    break; // No more queue items
                }

                try {
                    QueueManager::mark_processing($queue_item['id']);
                    $queue_data = json_decode($queue_item['data'], true) ?: [];

                    // Dispatch by queue type
                    switch ($queue_item['type']) {
                        case 'crm':
                            self::process_crm_from_queue((int)$queue_item['id'], $queue_data);
                            break;

                        case 'attachment_retry':
                            self::process_attachment_retry((int)$queue_item['id'], $queue_data);
                            break;

                        case 'crm_delete':
                            self::process_crm_delete((int)$queue_item['id'], $queue_data);
                            break;

                        default:
                            Logger::warning('Unknown queue type in CRM processor', [
                                'queue_id' => $queue_item['id'],
                                'type' => $queue_item['type'],
                            ]);
                            QueueManager::mark_failed($queue_item['id'], "Unknown queue type: {$queue_item['type']}");
                            break;
                    }

                    $processed++;
                } catch (\Throwable $e) {
                    Logger::error('Error processing queue item', [
                        'queue_id' => $queue_item['id'],
                        'type' => $queue_item['type'],
                        'error' => $e->getMessage(),
                    ]);
                    QueueManager::mark_failed($queue_item['id'], $e->getMessage());
                    $processed++;
                }
            }

            // PHASE 2: Process legacy message table items (for backward compatibility)
            // If we still have iterations left, process messages from the old system
            if ($processed < $max_iterations && !QueueManager::has_pending_type('crm')) {
                $crm_count = self::process_pending_crm_syncs($table, $crm_settings, $max_iterations - $processed);
                $processed += $crm_count;
            }

            Logger::info("CRM queue processor completed. Processed {$processed} items");

            // Check for alerts
            AlertSystem::check_and_alert();

            // Record success with items processed
            CronMonitor::success_job($record_id, $processed);

        } catch (\Throwable $e) {
            CronMonitor::fail_job($record_id, 'exception', $e->getMessage());
            Logger::critical(
                'CRM queue processor fatal error: ' . $e->getMessage(),
                ['file' => $e->getFile(), 'line' => $e->getLine()]
            );
        } finally {
            // DISABLED: This was creating duplicate schedules in a feedback loop
            // Schedules are created during plugin activation
            // self::ensure_recurring_schedule(
            //     Config::CRON_PROCESS_CRM,
            //     'contactin_crm_queue_interval',
            //     'contactin_fifteen_minutes',
            //     'contactin_queue_interval'
            // );

            // Always release lock, even if exception occurred
            ProcessLock::release('crm');
        }
    }

    /**
     * Process email item from queue table
     *
     * Handles email queue items with data payload from QueueManager
     *
     * @param int $queue_id Queue item ID
     * @param array $data Queue item payload
     */
    private static function process_email_from_queue(int $queue_id, array $data): void {
        try {
            // Email queue items would contain email-specific data
            // For now, just mark as completed since email processing is handled by messages table
            QueueManager::mark_completed($queue_id, ['processed_as' => 'email_from_queue']);
            
            Logger::info('Email queue item processed', ['queue_id' => $queue_id]);
        } catch (\Throwable $e) {
            Logger::error('Failed to process email queue item', [
                'queue_id' => $queue_id,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Process CRM item from queue table
     *
     * Handles CRM queue items with data payload from QueueManager
     *
     * @param int $queue_id Queue item ID
     * @param array $data Queue item payload containing CRM sync data
     */
    private static function process_crm_from_queue(int $queue_id, array $data): void {
        $start_time = microtime(true);
        $message_id = (int)($data['message_id'] ?? 0);

        try {
            // Validate required data
            if (empty($data['email']) || empty($data['name'])) {
                Logger::warning('CRM queue item missing required fields', [
                    'queue_id' => $queue_id,
                    'data' => $data,
                ]);
                QueueManager::mark_failed($queue_id, 'Missing required fields: email or name');
                return;
            }

            Logger::debug('Processing CRM sync from queue', [
                'queue_id' => $queue_id,
                'message_id' => $message_id,
                'email' => $data['email'],
            ]);

            // Send to CRM using the data from queue
            $result = CRMConnector::send($data, $message_id);

            // Check for WP_Error
            if (is_wp_error($result)) {
                $error_code = $result->get_error_code();
                $error_message = $result->get_error_message();
                
                Logger::error('CRM sync from queue returned WP_Error', [
                    'queue_id' => $queue_id,
                    'message_id' => $message_id,
                    'error_code' => $error_code,
                    'error_message' => $error_message,
                ]);
                
                throw new \Exception("CRM Error [{$error_code}]: {$error_message}");
            }

            // Check for failure in result array
            if (is_array($result) && isset($result['success']) && $result['success'] === false) {
                $error_message = $result['error_message'] ?? $result['crm_status'] ?? 'Unknown CRM error';
                
                Logger::error('CRM sync from queue returned failure', [
                    'queue_id' => $queue_id,
                    'message_id' => $message_id,
                    'result' => $result,
                ]);
                
                throw new \Exception("CRM sync failed: {$error_message}");
            }

            // Success - update message table if message_id provided
            if ($message_id > 0) {
                DB::instance()->mark_crm_sent($message_id);
            }

            CircuitBreaker::record_success('crm');
            $duration_ms = intval((microtime(true) - $start_time) * 1000);
            QueueMonitor::record_operation('crm', true, $duration_ms);

            Logger::info('CRM sync from queue completed successfully', [
                'queue_id' => $queue_id,
                'message_id' => $message_id,
                'contact_id' => $result['contact_id'] ?? null,
                'inquiry_id' => $result['inquiry_id'] ?? null,
                'duration_ms' => $duration_ms,
            ]);

            QueueManager::mark_completed($queue_id, [
                'contact_id' => $result['contact_id'] ?? null,
                'inquiry_id' => $result['inquiry_id'] ?? null,
            ]);

        } catch (\Throwable $e) {
            // Failure - classify error to determine if retriable
            if ($message_id > 0) {
                DB::instance()->mark_crm_failed($message_id, $e->getMessage());
            }

            CircuitBreaker::record_failure('crm', $e->getMessage());
            $duration_ms = intval((microtime(true) - $start_time) * 1000);
            QueueMonitor::record_operation('crm', false, $duration_ms, $e->getMessage());

            // Classify the error to determine retriability
            $error_type = ErrorClassifier::classify($e);
            $is_retriable = ErrorClassifier::is_retriable($error_type);

            Logger::error('CRM sync from queue failed', [
                'queue_id' => $queue_id,
                'message_id' => $message_id,
                'error' => $e->getMessage(),
                'error_type' => $error_type,
                'is_retriable' => $is_retriable,
            ]);

            // Log to CRM log table for visibility in CRM logs page
            if ($message_id > 0) {
                DB::instance()->insert_crm_log([
                    'message_id' => $message_id,
                    'crm_system' => 'salesforce',
                    'operation' => 'sync',
                    'crm_id' => null,
                    'status' => 'failed',
                    'response' => [
                        'error' => $e->getMessage(),
                        'error_type' => $error_type,
                        'queue_id' => $queue_id,
                        'retriable' => $is_retriable,
                        'trace' => substr($e->getTraceAsString(), 0, 2000),
                    ],
                    'error_message' => $e->getMessage(),
                ]);
            }

            // Emit alert for critical errors
            AlertGenerator::alert_crm_failure($error_type, $e->getMessage(), $message_id, [
                'queue_id' => $queue_id,
                'retriable' => $is_retriable,
            ]);

            // Handle retriable vs non-retriable errors
            if ($is_retriable) {
                // Retriable error - use adaptive retry logic with jitter
                QueueManager::mark_failed($queue_id, $e->getMessage(), $error_type);
            } else {
                // Non-retriable error - move directly to DLQ without retrying
                QueueManager::mark_non_retryable($queue_id, $error_type, $e->getMessage());
                // Emit DLQ alert for permanent failures
                AlertGenerator::alert_dlq_item('crm', $e->getMessage(), $message_id, "[{$error_type}]");
            }
        }
    }

    /**
     * Process attachment retry item from queue table
     *
     * Retrieves failed attachment data from queue and attempts re-upload to Salesforce
     * with automatic retry logic handled by QueueManager.
     *
     * @param int $queue_id Queue item ID
     * @param array $data Queue item payload containing attachment metadata
     */
    /**
     * Process CRM contact deletion (GDPR cleanup)
     */
    private static function process_crm_delete(int $queue_id, array $data): void {
        try {
            $contact_id = (string)($data['contact_id'] ?? '');
            $email = (string)($data['email'] ?? '');
            $name = (string)($data['name'] ?? '');
            $gdpr_log_id = (int)($data['gdpr_log_id'] ?? 0);

            // Get CRM settings
            $settings = \ContactInbox\Core\CRMSettings::get_settings();
            if (empty($settings['crm_enabled'])) {
                Logger::info('CRM disabled, skipping delete', ['queue_id' => $queue_id]);
                QueueManager::mark_completed($queue_id);
                return;
            }

            // Get auth tokens
            $auth_result = \ContactInbox\Core\CRMAuth::get_auth_tokens($settings);
            if (is_wp_error($auth_result)) {
                throw new \Exception('CRM authentication failed: ' . $auth_result->get_error_message());
            }

            $access_token = $auth_result['access_token'];
            $instance_url = $auth_result['instance_url'];
            $api_version = $settings['api_version'] ?? 'v59.0';

            $resolved_contact_id = $contact_id;
            if (!self::is_salesforce_id($resolved_contact_id)) {
                if (empty($email)) {
                    throw new \Exception('CRM contact ID missing and email not provided for lookup');
                }
                $resolved_contact_id = self::fetch_contact_id_by_email(
                    $email,
                    $instance_url,
                    $api_version,
                    $access_token
                );
            }

            if (empty($resolved_contact_id)) {
                throw new \Exception('CRM contact not found for deletion');
            }

            // Salesforce DELETE API endpoint
            $endpoint = "{$instance_url}/services/data/{$api_version}/sobjects/Contact/{$resolved_contact_id}";

            $response = wp_remote_request($endpoint, [
                'method' => 'DELETE',
                'headers' => [
                    'Authorization' => 'Bearer ' . $access_token,
                    'Content-Type' => 'application/json',
                ],
                'timeout' => 30,
            ]);

            if (is_wp_error($response)) {
                throw new \Exception('CRM delete request failed: ' . $response->get_error_message());
            }

            $http_code = wp_remote_retrieve_response_code($response);
            $body = wp_remote_retrieve_body($response);

            // 204 No Content is success for DELETE
            if ($http_code === 204 || $http_code === 200) {
                // Success - update GDPR log
                if ($gdpr_log_id > 0) {
                    global $wpdb;
                    $table = $wpdb->prefix . Config::TABLE_GDPR_DELETION_LOG;
                    $wpdb->update(
                        $table,
                        [
                            'crm_sync_status' => 'deleted',
                            'error_message' => null,
                        ],
                        ['id' => $gdpr_log_id],
                        ['%s', '%s'],
                        ['%d']
                    );
                }

                Logger::info('CRM contact deleted successfully', [
                    'queue_id' => $queue_id,
                    'contact_id' => $resolved_contact_id,
                    'email' => $email,
                    'gdpr_log_id' => $gdpr_log_id,
                ]);

                // Pro feature - CRM logging not available in free version
                // $crm_repo = new \ContactInbox\Core\Repositories\CRMRepository();
                // $crm_repo->insert_log([
                //     'message_id' => 0,
                //     'crm_system' => 'salesforce',
                //     'operation' => 'contact_delete',
                //     'crm_id' => $resolved_contact_id,
                //     'status' => 'delivered',
                //     'response' => [
                //         'contact_id' => $resolved_contact_id,
                //         'email' => $email,
                //         'name' => $name,
                //         'http_code' => $http_code,
                //         'gdpr_log_id' => $gdpr_log_id,
                //     ],
                //     'error_message' => null,
                // ]);

                QueueManager::mark_completed($queue_id);
            } else {
                // Handle specific error cases
                $error_data = json_decode($body, true);
                $error_message = $error_data[0]['message'] ?? "HTTP {$http_code}: {$body}";

                // 404 means contact already deleted - treat as success
                if ($http_code === 404) {
                    Logger::info('CRM contact already deleted (404)', [
                        'queue_id' => $queue_id,
                        'contact_id' => $contact_id,
                    ]);

                    if ($gdpr_log_id > 0) {
                        global $wpdb;
                        $table = $wpdb->prefix . Config::TABLE_GDPR_DELETION_LOG;
                        $wpdb->update(
                            $table,
                            ['crm_sync_status' => 'deleted'],
                            ['id' => $gdpr_log_id],
                            ['%s'],
                            ['%d']
                        );
                    }

                    // Pro feature - CRM logging not available in free version
                    // $crm_repo = new \ContactInbox\Core\Repositories\CRMRepository();
                    // $crm_repo->insert_log([
                    //     'message_id' => 0,
                    //     'crm_system' => 'salesforce',
                    //     'operation' => 'contact_delete',
                    //     'crm_id' => $contact_id,
                    //     'status' => 'delivered',
                    //     'response' => [
                    //         'contact_id' => $resolved_contact_id,
                    //         'email' => $email,
                    //         'name' => $name,
                    //         'http_code' => 404,
                    //         'note' => 'Contact already deleted',
                    //         'gdpr_log_id' => $gdpr_log_id,
                    //     ],
                    //     'error_message' => null,
                    // ]);

                    QueueManager::mark_completed($queue_id);
                    return;
                }

                throw new \Exception($error_message);
            }
        } catch (\Throwable $e) {
            Logger::error('CRM delete failed', [
                'queue_id' => $queue_id,
                'error' => $e->getMessage(),
                'contact_id' => $data['contact_id'] ?? null,
            ]);

            // Pro feature - CRM logging not available in free version
            // try {
            //     $crm_repo = new \ContactInbox\Core\Repositories\CRMRepository();
            //     $crm_repo->insert_log([
            //         'message_id' => 0,
            //         'crm_system' => 'salesforce',
            //         'operation' => 'contact_delete',
            //         'crm_id' => $data['contact_id'] ?? '',
            //         'status' => 'failed',
            //         'response' => [
            //             'contact_id' => $resolved_contact_id ?? ($data['contact_id'] ?? null),
            //             'email' => $data['email'] ?? null,
            //             'name' => $data['name'] ?? null,
            //             'gdpr_log_id' => $data['gdpr_log_id'] ?? null,
            //         ],
            //         'error_message' => $e->getMessage(),
            //     ]);
            // } catch (\Throwable $log_error) {
            //     Logger::error('Failed to log CRM delete error', [
            //         'queue_id' => $queue_id,
            //         'log_error' => $log_error->getMessage(),
            //         'original_error' => $e->getMessage(),
            //     ]);
            // }

            // Update GDPR log with error
            if (!empty($data['gdpr_log_id'])) {
                global $wpdb;
                $table = $wpdb->prefix . Config::TABLE_GDPR_DELETION_LOG;
                $wpdb->update(
                    $table,
                    ['error_message' => substr($e->getMessage(), 0, 500)],
                    ['id' => (int)$data['gdpr_log_id']],
                    ['%s'],
                    ['%d']
                );
            }

            QueueManager::mark_failed($queue_id, $e->getMessage());
        }
    }

    private static function is_salesforce_id(string $contact_id): bool {
        return (bool) preg_match('/^[a-zA-Z0-9]{15,18}$/', $contact_id);
    }

    private static function fetch_contact_id_by_email(
        string $email,
        string $instance_url,
        string $api_version,
        string $access_token
    ): ?string {
        $escaped_email = str_replace("'", "\\'", $email);
        $query = rawurlencode("SELECT Id FROM Contact WHERE Email = '{$escaped_email}' LIMIT 1");
        $query_url = "{$instance_url}/services/data/{$api_version}/query?q={$query}";

        $response = wp_remote_get($query_url, [
            'headers' => [
                'Authorization' => 'Bearer ' . $access_token,
                'Content-Type' => 'application/json',
            ],
            'timeout' => 15,
        ]);

        if (is_wp_error($response)) {
            throw new \Exception('CRM contact lookup failed: ' . $response->get_error_message());
        }

        $code = wp_remote_retrieve_response_code($response);
        $body = json_decode(wp_remote_retrieve_body($response), true);

        if ($code !== 200 || empty($body['records'][0]['Id'])) {
            $detail = $body['message'] ?? 'Contact lookup returned no results';
            throw new \Exception('CRM contact lookup failed: ' . $detail);
        }

        return $body['records'][0]['Id'];
    }

    private static function process_attachment_retry(int $queue_id, array $data): void {
        try {
            // Extract required fields from queue data
            $log_id = (int)($data['log_id'] ?? 0);
            $message_id = (int)($data['message_id'] ?? 0);
            $attachment_path = $data['attachment_path'] ?? '';
            $case_id = $data['case_id'] ?? '';
            $settings = (array)($data['settings'] ?? []);
            $headers = (array)($data['headers'] ?? []);
            $base_url = $data['base_url'] ?? '';
            $api_version = $data['api_version'] ?? '';

            // Validate required fields
            if (empty($attachment_path) || empty($case_id)) {
                Logger::warning('Attachment retry queue item missing required fields', [
                    'queue_id' => $queue_id,
                    'log_id' => $log_id,
                    'case_id' => $case_id,
                    'attachment_path' => $attachment_path,
                ]);
                QueueManager::mark_failed(
                    $queue_id,
                    'Missing required fields: case_id or attachment_path'
                );
                return;
            }

            if (empty($log_id)) {
                Logger::warning('Attachment retry queue item missing log_id, proceeding without log update', [
                    'queue_id' => $queue_id,
                    'case_id' => $case_id,
                    'attachment_path' => $attachment_path,
                ]);
            }

            // Check if file still exists (file may have been deleted before retry window)
            if (!is_file($attachment_path)) {
                Logger::warning('Attachment file not found for retry', [
                    'queue_id' => $queue_id,
                    'log_id' => $log_id,
                    'path' => $attachment_path,
                ]);
                QueueManager::mark_failed(
                    $queue_id,
                    "Attachment file no longer exists: {$attachment_path}"
                );
                return;
            }

            // Refresh headers using current OAuth token to avoid expired credentials
            $current_settings = \ContactInbox\Core\CRMSettings::get_settings();
            if (!empty($current_settings['instance_url'])) {
                $base_url = rtrim($current_settings['instance_url'], '/');
                $settings = array_merge($settings, $current_settings);
            }

            $access_token = \ContactInbox\Core\CRMAuth::get_access_token();
            if (is_wp_error($access_token)) {
                Logger::warning('Attachment retry auth failed', [
                    'queue_id' => $queue_id,
                    'log_id' => $log_id,
                    'error' => $access_token->get_error_message(),
                ]);
                QueueManager::mark_failed($queue_id, $access_token->get_error_message());
                return;
            }

            $headers = [
                'Content-Type' => 'application/json',
                'Authorization' => 'Bearer ' . $access_token,
            ];

            if (empty($api_version)) {
                $api_version = 'v58.0';
            }

            // Attempt re-upload
            $crm_connector = new \ContactInbox\Core\CRMConnector();
            $result = $crm_connector->upload_attachment_to_case_retry(
                $attachment_path,
                $case_id,
                $settings,
                $headers,
                $base_url,
                $api_version,
                $message_id,
                $log_id
            );

            // Handle result
            if (is_wp_error($result)) {
                Logger::warning('Attachment retry failed, will be retried', [
                    'queue_id' => $queue_id,
                    'log_id' => $log_id,
                    'error' => $result->get_error_message(),
                ]);
                QueueManager::mark_failed($queue_id, $result->get_error_message());
            } else {
                // Success - mark queue item as completed
                QueueManager::mark_completed($queue_id, [
                    'log_id' => $log_id,
                    'content_version_id' => $result['content_version_id'] ?? null,
                    'content_document_id' => $result['content_document_id'] ?? null,
                ]);

                Logger::info('Attachment successfully retried and uploaded', [
                    'queue_id' => $queue_id,
                    'log_id' => $log_id,
                    'message_id' => $message_id,
                    'attachment_path' => $attachment_path,
                ]);
            }
        } catch (\Throwable $e) {
            Logger::error('Exception during attachment retry processing', [
                'queue_id' => $queue_id,
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);
            throw $e;
        }
    }

    /**
     * Process pending messages with retry logic (Phase 2: Message-Centric Processing)
     *
     * Scans messages table for pending/failed statuses and processes them.
     * Runs every 2 minutes. Uses exponential backoff for retries.
     *
     * @deprecated Use process_email_queue() and process_crm_queue() instead
     */
    public function process_pending_messages(): void {
        $record_id = CronMonitor::start_job('contactin_process_queue');
        if (!$record_id) {
            return;
        }

        $max_iterations = 50;
        $processed = 0;

        Logger::info('Message processor started');

        try {
            global $wpdb;
            $table = $wpdb->prefix . Config::TABLE_MESSAGES;
            $settings = get_option(Config::OPTION_SETTINGS, []);
            $crm_settings = \ContactInbox\Core\CRMSettings::get_settings();

            // Process admin emails
            $admin_count = self::process_pending_admin_emails($table, $settings, $max_iterations - $processed);
            $processed += $admin_count;

            // Process user emails
            if ($processed < $max_iterations) {
                $user_count = self::process_pending_user_emails($table, $settings, $max_iterations - $processed);
                $processed += $user_count;
            }

            // Process CRM syncs
            if ($processed < $max_iterations) {
                $crm_count = self::process_pending_crm_syncs($table, $crm_settings, $max_iterations - $processed);
                $processed += $crm_count;
            }

            Logger::info("Message processor completed. Processed {$processed} items");

            // Check for alerts
            AlertSystem::check_and_alert();

            // Record success with items processed
            CronMonitor::success_job($record_id, $processed);

        } catch (\Throwable $e) {
            CronMonitor::fail_job($record_id, 'exception', $e->getMessage());
            Logger::critical(
                'Message processor fatal error: ' . $e->getMessage(),
                ['file' => $e->getFile(), 'line' => $e->getLine()]
            );
        }
    }

    /**
     * Process pending admin email notifications
     */
    private static function process_pending_admin_emails(string $table, array $settings, int $limit): int {
        global $wpdb;
        $processed = 0;

        // Skip if SMTP is disabled
        if (empty($settings['smtp_enable'])) {
            Logger::debug('Admin email processing skipped: SMTP disabled');
            return 0;
        }

        // Skip if admin notifications are disabled
        if (empty($settings['send_admin_notification'])) {
            $wpdb->query(
                $wpdb->prepare(
                    "UPDATE {$table}
                     SET admin_email_status = %s,
                         admin_email_error = NULL,
                         admin_email_sent_at = NULL,
                         admin_email_retries = 0
                     WHERE admin_email_status IS NULL
                        OR admin_email_status IN (%s, %s, %s)",
                    Config::EMAIL_SKIPPED,
                    Config::EMAIL_PENDING,
                    Config::EMAIL_FAILED,
                    Config::EMAIL_PROCESSING
                )
            );

            Logger::debug('Admin email processing skipped: Admin notifications disabled');
            return 0;
        }

        // Check if SMTP circuit is available
        if (!CircuitBreaker::is_available('smtp')) {
            Logger::warning('Admin email processing skipped: SMTP circuit breaker open', [
                'state' => CircuitBreaker::get_state('smtp'),
            ]);
            return 0;
        }

        // Get pending or failed admin emails with exponential backoff
        $messages = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$table}
            WHERE (admin_email_status IS NULL OR admin_email_status = %s OR admin_email_status = %s)
            AND admin_email_retries < 5
            ORDER BY submitted_at ASC
            LIMIT %d",
            Config::EMAIL_PENDING,
            Config::EMAIL_FAILED,
            $limit
        ));

        if (empty($messages)) {
            return 0;
        }

        foreach ($messages as $message) {
            $message_id = (int) $message->id;
            $retry_count = (int) ($message->admin_email_retries ?? 0);

            $claimed = $wpdb->query(
                $wpdb->prepare(
                    "UPDATE {$table}
                     SET admin_email_status = %s
                     WHERE id = %d
                       AND (admin_email_status IS NULL OR admin_email_status = %s OR admin_email_status = %s)",
                    Config::EMAIL_PROCESSING,
                    $message_id,
                    Config::EMAIL_PENDING,
                    Config::EMAIL_FAILED
                )
            );

            if (!$claimed) {
                Logger::debug('Admin email already claimed by another worker', [
                    'message_id' => $message_id,
                    'status' => $message->admin_email_status ?? 'unknown',
                ]);
                continue;
            }

            // Exponential backoff: skip if not enough time has passed since last attempt
            if ($retry_count > 0 && !empty($message->submitted_at)) {
                $backoff_seconds = pow(2, $retry_count) * 60; // 2min, 4min, 8min, 16min, 32min
                $next_attempt = strtotime($message->submitted_at) + $backoff_seconds;
                if (time() < $next_attempt) {
                    $wpdb->query(
                        $wpdb->prepare(
                            "UPDATE {$table} SET admin_email_status = %s WHERE id = %d",
                            Config::EMAIL_PENDING,
                            $message_id
                        )
                    );
                    Logger::debug("Skipping message #{$message_id}: backoff not expired", [
                        'message_id' => $message_id,
                        'retry_count' => $retry_count,
                        'next_attempt' => date('Y-m-d H:i:s', $next_attempt),
                    ]);
                    continue;
                }
            }

            $start_time = microtime(true);

            try {
                $delete_link = self::resolve_delete_link($message);

                // Build email data
                $display_name = NameFormatter::display($message->salutation ?? '', $message->name ?? '');
                $email_data = [
                    'message_id'   => $message_id,
                    'name'         => $display_name,
                    'salutation'   => $message->salutation ?? '',
                    'email'        => $message->email,
                    'subject'      => $message->subject,
                    'message'      => $message->message,
                    'phone'        => $message->phone ?? '',
                    'ip_address'   => $message->ip_address ?? '',
                    'ip'           => $message->ip_address ?? '',
                    'submitted_at' => $message->submitted_at,
                    'date'         => $message->submitted_at,
                    'attachment'   => $message->attachment ?? '',
                    'delete_link'  => $delete_link,
                    'inbox_link'   => \admin_url(sprintf('admin.php?page=%1$s', Config::MENU_INBOX)),
                ];

                // Send admin notification
                $result = \ContactInbox\Core\SMTP::send_admin_notification($email_data);

                if ($result) {
                    // Success: mark as sent
                    $wpdb->update(
                        $table,
                        [
                            'admin_email_status' => Config::EMAIL_SENT,
                            'admin_email_sent_at' => current_time('mysql'),
                            'admin_email_error' => null,
                        ],
                        ['id' => $message_id],
                        ['%s', '%s', '%s'],
                        ['%d']
                    );

                    CircuitBreaker::record_success('smtp');
                    $duration_ms = intval((microtime(true) - $start_time) * 1000);
                    QueueMonitor::record_operation('admin_email', true, $duration_ms);

                    Logger::info("Admin email sent for message #{$message_id}");
                    $processed++;
                } else {
                    $error = \ContactInbox\Core\SMTP::get_last_error();
                    if ($error === '') {
                        $error = 'Admin notification send returned false';
                    }
                    throw new \Exception($error);
                }

            } catch (\Throwable $e) {
                // Failure: classify error and emit alert
                $error_type = ErrorClassifier::classify($e);
                $is_retriable = ErrorClassifier::is_retriable($error_type);

                // Mark as failed with alert
                $wpdb->update(
                    $table,
                    [
                        'admin_email_status' => Config::EMAIL_FAILED,
                        'admin_email_error' => substr($e->getMessage(), 0, 1000),
                        'admin_email_retries' => $retry_count + 1,
                    ],
                    ['id' => $message_id],
                    ['%s', '%s', '%d'],
                    ['%d']
                );

                CircuitBreaker::record_failure('smtp', $e->getMessage());
                $duration_ms = intval((microtime(true) - $start_time) * 1000);
                QueueMonitor::record_operation('admin_email', false, $duration_ms, $e->getMessage());

                // Emit alert for email failures
                AlertGenerator::alert_email_failure(
                    $e->getMessage(),
                    $message_id,
                    $message->email ?? ''
                );

                Logger::error("Admin email failed for message #{$message_id}: " . $e->getMessage(), [
                    'message_id' => $message_id,
                    'retry_count' => $retry_count + 1,
                    'error_type' => $error_type,
                    'retriable' => $is_retriable,
                ]);
            }
        }

        return $processed;
    }

    /**
     * Process pending user email confirmations
     */
    private static function process_pending_user_emails(string $table, array $settings, int $limit): int {
        global $wpdb;
        $processed = 0;

        // Skip if SMTP is disabled
        if (empty($settings['smtp_enable'])) {
            return 0;
        }

        // Skip if user confirmations are disabled
        if (empty($settings['send_user_copy'])) {
            $wpdb->query(
                $wpdb->prepare(
                    "UPDATE {$table}
                     SET user_email_status = %s,
                         user_email_error = NULL,
                         user_email_sent_at = NULL,
                         user_email_retries = 0
                     WHERE user_email_status IS NULL
                        OR user_email_status IN (%s, %s, %s)",
                    Config::EMAIL_SKIPPED,
                    Config::EMAIL_PENDING,
                    Config::EMAIL_FAILED,
                    Config::EMAIL_PROCESSING
                )
            );

            return 0;
        }

        // Check if SMTP circuit is available
        if (!CircuitBreaker::is_available('smtp')) {
            return 0;
        }

        // Get pending or failed user emails with exponential backoff
        $messages = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$table}
            WHERE (user_email_status IS NULL OR user_email_status = %s OR user_email_status = %s)
            AND user_email_retries < 5
            ORDER BY submitted_at ASC
            LIMIT %d",
            Config::EMAIL_PENDING,
            Config::EMAIL_FAILED,
            $limit
        ));

        if (empty($messages)) {
            return 0;
        }

        foreach ($messages as $message) {
            $message_id = (int) $message->id;
            $retry_count = (int) ($message->user_email_retries ?? 0);

            $claimed = $wpdb->query(
                $wpdb->prepare(
                    "UPDATE {$table}
                     SET user_email_status = %s
                     WHERE id = %d
                       AND (user_email_status IS NULL OR user_email_status = %s OR user_email_status = %s)",
                    Config::EMAIL_PROCESSING,
                    $message_id,
                    Config::EMAIL_PENDING,
                    Config::EMAIL_FAILED
                )
            );

            if (!$claimed) {
                Logger::debug('User email already claimed by another worker', [
                    'message_id' => $message_id,
                    'status' => $message->user_email_status ?? 'unknown',
                ]);
                continue;
            }

            // Exponential backoff
            if ($retry_count > 0 && !empty($message->submitted_at)) {
                $backoff_seconds = pow(2, $retry_count) * 60;
                $next_attempt = strtotime($message->submitted_at) + $backoff_seconds;
                if (time() < $next_attempt) {
                    $wpdb->query(
                        $wpdb->prepare(
                            "UPDATE {$table} SET user_email_status = %s WHERE id = %d",
                            Config::EMAIL_PENDING,
                            $message_id
                        )
                    );
                    Logger::debug("Skipping user email {$message_id}: backoff not expired", [
                        'message_id' => $message_id,
                        'retry_count' => $retry_count,
                        'next_attempt' => date('Y-m-d H:i:s', $next_attempt),
                    ]);
                    continue;
                }
            }

            $start_time = microtime(true);

            try {
                $delete_link = self::resolve_delete_link($message);

                // Build email data
                $display_name = NameFormatter::display($message->salutation ?? '', $message->name ?? '');
                $email_data = [
                    'message_id'    => $message_id,
                    'name'          => $display_name,
                    'salutation'    => $message->salutation ?? '',
                    'email'         => $message->email,
                    'subject'       => $message->subject,
                    'message'       => $message->message,
                    'phone'         => $message->phone ?? '',
                    'receipt_token' => $message->receipt_token ?? '',
                    'submitted_at'  => $message->submitted_at ?? '',
                    'delete_link'   => $delete_link,
                ];

                // Send user confirmation
                $result = \ContactInbox\Core\SMTP::send_user_confirmation($email_data);

                if ($result) {
                    // Success
                    $wpdb->update(
                        $table,
                        [
                            'user_email_status' => Config::EMAIL_SENT,
                            'user_email_sent_at' => current_time('mysql'),
                            'user_email_error' => null,
                        ],
                        ['id' => $message_id],
                        ['%s', '%s', '%s'],
                        ['%d']
                    );

                    CircuitBreaker::record_success('smtp');
                    $duration_ms = intval((microtime(true) - $start_time) * 1000);
                    QueueMonitor::record_operation('user_email', true, $duration_ms);

                    Logger::info("User confirmation sent for message #{$message_id}");
                    $processed++;
                } else {
                    $error = \ContactInbox\Core\SMTP::get_last_error();
                    if ($error === '') {
                        $error = 'User confirmation send returned false';
                    }
                    throw new \Exception($error);
                }

            } catch (\Throwable $e) {
                // Failure: classify error and emit alert
                $error_type = ErrorClassifier::classify($e);
                $is_retriable = ErrorClassifier::is_retriable($error_type);

                // Mark as failed with alert
                $wpdb->update(
                    $table,
                    [
                        'user_email_status' => Config::EMAIL_FAILED,
                        'user_email_error' => substr($e->getMessage(), 0, 1000),
                        'user_email_retries' => $retry_count + 1,
                    ],
                    ['id' => $message_id],
                    ['%s', '%s', '%d'],
                    ['%d']
                );

                CircuitBreaker::record_failure('smtp', $e->getMessage());
                $duration_ms = intval((microtime(true) - $start_time) * 1000);
                QueueMonitor::record_operation('user_email', false, $duration_ms, $e->getMessage());

                // Emit alert for email failures
                AlertGenerator::alert_email_failure(
                    $e->getMessage(),
                    $message_id,
                    $message->email ?? ''
                );

                Logger::error("User confirmation failed for message #{$message_id}: " . $e->getMessage(), [
                    'message_id' => $message_id,
                    'retry_count' => $retry_count + 1,
                    'error_type' => $error_type,
                    'retriable' => $is_retriable,
                ]);
            }
        }

        return $processed;
    }

    /**
     * Process pending CRM syncs
     */
    private static function process_pending_crm_syncs(string $table, array $crm_settings, int $limit): int {
        global $wpdb;
        $processed = 0;

        // Skip if CRM is disabled
        if (empty($crm_settings['crm_enabled'])) {
            $wpdb->query(
                $wpdb->prepare(
                    "UPDATE {$table}
                     SET crm_status = %s,
                         crm_error = NULL,
                         crm_synced_at = NULL,
                         crm_retries = 0
                     WHERE crm_status IS NULL
                        OR crm_status IN (%s, %s)",
                    Config::CRM_SKIPPED,
                    Config::CRM_PENDING,
                    Config::CRM_FAILED
                )
            );

            return 0;
        }

        // Check if CRM circuit is available
        if (!CircuitBreaker::is_available('crm')) {
            Logger::warning('CRM processing skipped: circuit breaker open', [
                'state' => CircuitBreaker::get_state('crm'),
            ]);
            return 0;
        }

        // Get pending or failed CRM syncs with exponential backoff
        $messages = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$table}
            WHERE (crm_status IS NULL OR crm_status = %s OR crm_status = %s)
            AND crm_retries < 5
            ORDER BY submitted_at ASC
            LIMIT %d",
            Config::CRM_PENDING,
            Config::CRM_FAILED,
            $limit
        ));

        if (empty($messages)) {
            return 0;
        }

        foreach ($messages as $message) {
            $message_id = (int) $message->id;
            $retry_count = (int) ($message->crm_retries ?? 0);

            // Exponential backoff
            if ($retry_count > 0 && !empty($message->submitted_at)) {
                $backoff_seconds = pow(2, $retry_count) * 60;
                $next_attempt = strtotime($message->submitted_at) + $backoff_seconds;
                if (time() < $next_attempt) {
                    continue;
                }
            }

            $start_time = microtime(true);

            try {
                $crm_payload = \ContactInbox\Core\CRMQueuePayloadBuilder::build_from_message(new \ContactInbox\Core\Message($message));
                $display_name = $crm_payload['display_name'] ?? '';

                if (empty($crm_payload['attachment']) && !empty($message->attachment)) {
                    Logger::warning('Attachment file not found for CRM sync', [
                        'message_id' => $message_id,
                        'attachment_data' => substr($message->attachment, 0, 200),
                    ]);
                }

                Logger::debug("CRM sync starting for message #{$message_id}", [
                    'message_id' => $message_id,
                    'email' => $message->email,
                    'retry_count' => $retry_count,
                    'has_attachment' => !empty($crm_payload['attachment']),
                ]);

                // Send to CRM
                $result = CRMConnector::send($crm_payload, $message_id);

                // Check for WP_Error
                if (is_wp_error($result)) {
                    $error_code = $result->get_error_code();
                    $error_message = $result->get_error_message();
                    
                    Logger::error("CRM sync returned WP_Error for message #{$message_id}", [
                        'message_id' => $message_id,
                        'error_code' => $error_code,
                        'error_message' => $error_message,
                        'retry_count' => $retry_count,
                    ]);
                    
                    throw new \Exception("CRM Error [{$error_code}]: {$error_message}");
                }

                // Check for failure in result array
                if (is_array($result) && isset($result['success']) && $result['success'] === false) {
                    $error_message = $result['error_message'] ?? $result['crm_status'] ?? 'Unknown CRM error';
                    
                    Logger::error("CRM sync returned failure for message #{$message_id}", [
                        'message_id' => $message_id,
                        'result' => $result,
                        'retry_count' => $retry_count,
                    ]);
                    
                    throw new \Exception("CRM sync failed: {$error_message}");
                }

                // Success
                $wpdb->update(
                    $table,
                    [
                        'crm_status' => Config::CRM_SENT,
                        'crm_synced_at' => current_time('mysql'),
                        'crm_error' => null,
                    ],
                    ['id' => $message_id],
                    ['%s', '%s', '%s'],
                    ['%d']
                );

                CircuitBreaker::record_success('crm');
                $duration_ms = intval((microtime(true) - $start_time) * 1000);
                QueueMonitor::record_operation('crm', true, $duration_ms);

                Logger::info("CRM sync completed successfully for message #{$message_id}", [
                    'message_id' => $message_id,
                    'contact_id' => $result['contact_id'] ?? null,
                    'inquiry_id' => $result['inquiry_id'] ?? null,
                    'duration_ms' => $duration_ms,
                ]);
                $processed++;

            } catch (\Throwable $e) {
                // Failure - comprehensive logging
                $error_details = [
                    'message_id' => $message_id,
                    'retry_count' => $retry_count + 1,
                    'error_message' => $e->getMessage(),
                    'error_file' => $e->getFile(),
                    'error_line' => $e->getLine(),
                    'email' => $message->email ?? 'unknown',
                    'name' => $display_name !== '' ? $display_name : ($message->name ?? 'unknown'),
                ];
                
                $wpdb->update(
                    $table,
                    [
                        'crm_status' => Config::CRM_FAILED,
                        'crm_error' => substr($e->getMessage(), 0, 1000),
                        'crm_retries' => $retry_count + 1,
                    ],
                    ['id' => $message_id],
                    ['%s', '%s', '%d'],
                    ['%d']
                );

                CircuitBreaker::record_failure('crm', $e->getMessage());
                $duration_ms = intval((microtime(true) - $start_time) * 1000);
                QueueMonitor::record_operation('crm', false, $duration_ms, $e->getMessage());

                Logger::error("CRM sync failed for message #{$message_id}: " . $e->getMessage(), $error_details);
                
                // Also log to CRM log table for visibility
                DB::instance()->insert_crm_log([
                    'message_id' => $message_id,
                    'crm_system' => 'salesforce',
                    'operation' => 'sync',
                    'crm_id' => null,
                    'status' => 'failed',
                    'response' => [
                        'error' => $e->getMessage(),
                        'retry_count' => $retry_count + 1,
                        'trace' => substr($e->getTraceAsString(), 0, 2000),
                    ],
                    'error_message' => $e->getMessage(),
                ]);
            }
        }

        return $processed;
    }

    private static function resolve_delete_link(object $message): string {
        $email = sanitize_email($message->email ?? '');
        if ($email === '' || !is_email($email)) {
            return '';
        }

        $token   = '';
        $expires = isset($message->gdpr_expires) ? (int) $message->gdpr_expires : 0;
        if (!empty($message->gdpr_token) && $expires > time()) {
            $token = (string) $message->gdpr_token;
        } else {
            $generated = GDPR::generate_token((int) ($message->id ?? 0));
            if (!empty($generated)) {
                $token = $generated;
            }
        }

        return $token !== '' ? GDPR::build_delete_link($token, $email) : '';
    }

    /**
     * Legacy process_email method - kept for backward compatibility with old queue items
     * @deprecated Will be removed after queue table migration is complete
     */
    private static function process_email(int $queue_id, array $data): void {
        // Extract email_data and settings from queue data
        $email_data = $data['email_data'] ?? [];
        $settings = $data['settings'] ?? [];

        $display_name = NameFormatter::display($email_data['salutation'] ?? '', $email_data['name'] ?? '');
        if ($display_name !== '') {
            $email_data['name'] = $display_name;
        }

        // If SMTP is currently disabled, skip email work and mark as completed
        $current_settings = get_option( Config::OPTION_SETTINGS, [] );
        if ( empty( $current_settings['smtp_enable'] ) ) {
            QueueManager::mark_completed( $queue_id, [ 'email_status' => 'smtp_disabled' ] );
            Logger::info(
                'Email operations skipped because SMTP is disabled',
                [ 'queue_id' => $queue_id ]
            );
            return;
        }

        if (empty($email_data['email']) || empty($email_data['name'])) {
            throw new \Exception('Invalid email data: missing email or name');
        }

        $result = [];
        $errors = [];

        // Check if SMTP circuit is open (graceful degradation)
        if (!CircuitBreaker::is_available('smtp')) {
            $errors[] = 'SMTP service unavailable (circuit breaker open)';
            Logger::warning('SMTP operation skipped', [
                'queue_id' => $queue_id,
                'reason' => 'Circuit breaker open',
                'state' => CircuitBreaker::get_state('smtp'),
            ]);
        } else {
            // Send admin notification if enabled
            if (!empty($settings['send_admin_notification'])) {
                try {
                    $admin_result = \ContactInbox\Core\SMTP::send_admin_notification($email_data);
                    if ($admin_result) {
                        $result['admin_notification'] = 'sent';
                        CircuitBreaker::record_success('smtp');
                    } else {
                        $admin_error = \ContactInbox\Core\SMTP::get_last_error();
                        $admin_error = $admin_error !== '' ? $admin_error : 'Admin notification returned false';
                        $errors[] = 'Admin notification failed: ' . $admin_error;
                        CircuitBreaker::record_failure('smtp', $admin_error);
                    }
                } catch (\Throwable $e) {
                    $errors[] = 'Admin notification error: ' . $e->getMessage();
                    CircuitBreaker::record_failure('smtp', $e->getMessage());
                }
            }

            // Send user confirmation if enabled
            if (!empty($settings['send_user_copy'])) {
                try {
                    $user_result = \ContactInbox\Core\SMTP::send_user_confirmation($email_data);
                    if ($user_result) {
                        $result['user_confirmation'] = 'sent';
                        CircuitBreaker::record_success('smtp');
                    } else {
                        $user_error = \ContactInbox\Core\SMTP::get_last_error();
                        $user_error = $user_error !== '' ? $user_error : 'User confirmation returned false';
                        $errors[] = 'User confirmation failed: ' . $user_error;
                        CircuitBreaker::record_failure('smtp', $user_error);
                    }
                } catch (\Throwable $e) {
                    $errors[] = 'User confirmation error: ' . $e->getMessage();
                    CircuitBreaker::record_failure('smtp', $e->getMessage());
                }
            }
        }

        // Fail queue item only if all operations failed and are not optional
        if (!empty($errors) && empty($result)) {
            throw new \Exception(implode('; ', $errors));
        }

        QueueManager::mark_completed($queue_id, $result);

        Logger::info(
            "Email operations completed for {$email_data['email']}",
            [
                'queue_id' => $queue_id,
                'email' => $email_data['email'],
                'result' => $result,
                'errors' => $errors,
            ]
        );
    }

    /**
     * Process CRM queue item with graceful degradation
     */
    private static function process_crm(int $queue_id, array $data): void {
        // Extract email_data and crm_settings from queue data
        $email_data = $data['email_data'] ?? [];
        $crm_settings = $data['crm_settings'] ?? [];

        if (empty($email_data['email']) || empty($email_data['name'])) {
            throw new \Exception('CRM processing: invalid email data');
        }

        // Check CURRENT CRM settings (not historical), matching the Email pattern
        // This allows pending items to resume when sync is re-enabled
        $current_settings = CRMSettings::get_settings();
        if (empty($current_settings['crm_enabled'])) {
            QueueManager::mark_completed($queue_id, ['crm_status' => 'disabled']);
            return;
        }

        // Check if CRM circuit is open (graceful degradation)
        if (!CircuitBreaker::is_available('crm')) {
            Logger::warning('CRM operation skipped', [
                'queue_id' => $queue_id,
                'reason' => 'Circuit breaker open',
                'state' => CircuitBreaker::get_state('crm'),
            ]);
            
            // Mark as completed anyway (CRM is optional, form submission succeeded)
            QueueManager::mark_completed($queue_id, [
                'crm_status' => 'skipped',
                'reason' => 'CRM service unavailable',
            ]);
            return;
        }

        $display_name = NameFormatter::display($email_data['salutation'] ?? '', $email_data['name'] ?? '');

        // Build CRM data from email_data
        $crm_data = [
            'name'    => $email_data['name'],
            'salutation' => $email_data['salutation'] ?? '',
            'display_name' => $display_name !== '' ? $display_name : ($email_data['name'] ?? ''),
            'email'   => $email_data['email'],
            'message' => $email_data['message'] ?? '',
            'subject' => $email_data['subject'] ?? '',
            'phone'   => $email_data['phone'] ?? '',
        ];

        // Extract message_id for CRM log linkage
        $message_id = $email_data['message_id'] ?? null;

        try {
            // Use CRMConnector to send data with message_id for log linkage
            $result = CRMConnector::send($crm_data, $message_id);

            if (is_wp_error($result)) {
                CircuitBreaker::record_failure('crm', $result->get_error_message());
                throw new \Exception('CRM sync failed: ' . $result->get_error_message());
            }

            CircuitBreaker::record_success('crm');

            // Update message status to 'sent' to reflect successful sync in UI
            if ($message_id) {
                DB::instance()->update_message_status($message_id, 'crm', Config::CRM_SENT);
            }

            QueueManager::mark_completed(
                $queue_id,
                ['crm_sync' => true, 'crm_status' => $result['crm_status'] ?? 'synced']
            );

            Logger::info(
                "CRM sync completed for {$email_data['email']}",
                ['queue_id' => $queue_id, 'email' => $email_data['email'], 'message_id' => $message_id]
            );
        } catch (\Throwable $e) {
            CircuitBreaker::record_failure('crm', $e->getMessage());
            throw $e;
        }
    }

    /**
     * Process webhook queue item
     */
    private static function process_webhook(int $queue_id, array $data): void {
        $start_time = microtime(true);
        $webhook_url = $data['url'] ?? '';
        $payload = $data['payload'] ?? [];
        $secret = $data['secret'] ?? '';
        $message_id = (int)($payload['message_id'] ?? 0);

        if (empty($webhook_url) || !filter_var($webhook_url, FILTER_VALIDATE_URL)) {
            QueueManager::mark_non_retryable($queue_id, ErrorClassifier::VALIDATION, 'Invalid webhook URL');
            Logger::error('Webhook delivery failed: invalid URL', [
                'queue_id' => $queue_id,
                'url' => $webhook_url,
            ]);
            return;
        }

        $payload_json = wp_json_encode($payload);
        $headers = [
            'Content-Type' => 'application/json',
            'X-ContactIN-Delivery-Id' => (string)$queue_id,
        ];

        if ($message_id > 0) {
            $headers['X-ContactIN-Message-Id'] = (string)$message_id;
        }

        if (!empty($secret)) {
            $timestamp = current_time('timestamp');
            $signature = WebhookSignature::sign($payload_json, $secret, $timestamp);

            $headers['X-ContactIN-Timestamp'] = (string)$timestamp;
            $headers['X-ContactIN-Signature'] = 'sha256=' . $signature;
            // Legacy compatibility header (body-only signature)
            $headers['X-Webhook-Signature'] = 'sha256=' . WebhookSignature::sign($payload_json, $secret);
        }

        $response = wp_remote_post(
            $webhook_url,
            [
                'body'      => $payload_json,
                'headers'   => $headers,
                'timeout'   => 30,
                'sslverify' => apply_filters('https_local_ssl_verify', false),
            ]
        );

        if (is_wp_error($response)) {
            $error_message = $response->get_error_message();
            $error_type = ErrorClassifier::NETWORK_ERROR;
            QueueManager::mark_failed($queue_id, $error_message, $error_type);
            CircuitBreaker::record_failure('webhook', $error_message);
            $duration_ms = intval((microtime(true) - $start_time) * 1000);
            QueueMonitor::record_operation('webhook', false, $duration_ms, $error_message);
            Logger::error('Webhook delivery failed', [
                'queue_id' => $queue_id,
                'url' => $webhook_url,
                'error' => $error_message,
            ]);
            return;
        }

        $code = (int)wp_remote_retrieve_response_code($response);

        if ($code < 200 || $code >= 300) {
            $error_message = "Webhook returned HTTP {$code}";
            $error_type = ErrorClassifier::classify_by_http($code);

            CircuitBreaker::record_failure('webhook', $error_message);
            $duration_ms = intval((microtime(true) - $start_time) * 1000);
            QueueMonitor::record_operation('webhook', false, $duration_ms, $error_message);

            if (ErrorClassifier::is_retriable($error_type)) {
                QueueManager::mark_failed($queue_id, $error_message, $error_type);
            } else {
                QueueManager::mark_non_retryable($queue_id, $error_type, $error_message);
            }

            Logger::error('Webhook delivery failed', [
                'queue_id' => $queue_id,
                'url' => $webhook_url,
                'status_code' => $code,
            ]);
            return;
        }

        QueueManager::mark_completed(
            $queue_id,
            ['webhook_sent' => $webhook_url, 'status_code' => $code]
        );

        CircuitBreaker::record_success('webhook');
        $duration_ms = intval((microtime(true) - $start_time) * 1000);
        QueueMonitor::record_operation('webhook', true, $duration_ms);

        Logger::info(
            "Webhook sent successfully to {$webhook_url}",
            ['queue_id' => $queue_id, 'url' => $webhook_url, 'code' => $code]
        );
    }

    /**
     * Ensure a recurring schedule exists and is spaced after immediate runs
     */
    private static function ensure_recurring_schedule(
        string $hook,
        string $interval_option,
        string $default_interval,
        ?string $fallback_interval_option = null
    ): void {
        $schedules = wp_get_schedules();

        $interval_slug = get_option($interval_option, $default_interval);
        if ($fallback_interval_option && empty($interval_slug)) {
            $interval_slug = get_option($fallback_interval_option, $default_interval);
        }

        $interval_seconds = $schedules[$interval_slug]['interval'] ?? 900;
        $next_run = wp_next_scheduled($hook);

        // If missing or too close, reschedule to now + interval
        if (!$next_run || $next_run <= time()) {
            if ($next_run) {
                wp_unschedule_event($next_run, $hook);
            }
            wp_schedule_event(time() + $interval_seconds, $interval_slug, $hook);
            return;
        }

        // If a fast-lane just ran, ensure the next recurring run is spaced out
        if (($next_run - time()) < ($interval_seconds / 2)) {
            wp_unschedule_event($next_run, $hook);
            wp_schedule_event(time() + $interval_seconds, $interval_slug, $hook);
        }
    }

    /**
     * GDPR Deletion Cleanup Job
     * Retries failed GDPR deletions once every 24 hours
     */
    public function run_gdpr_deletion_cleanup(): void {
        $start_time = microtime(true);
        
        // Log cron start
        $log_id = $this->log_cron_start(Config::CRON_GDPR_CLEANUP);

        try {
            // Pro feature - GDPR is not available in free version
            $this->log_cron_end($log_id, 0, 0, 0, ['message' => 'GDPR features are Pro only']);
            return;
            // $gdpr_repo = new \ContactInbox\Core\Repositories\GDPRRepository();
            // $contact_repo = new \ContactInbox\Core\Repositories\ContactRepository();

            // Get failed or pending deletions
            $failed_deletions = $gdpr_repo->get_failed_pending_deletions(10, 1);

            $processed = 0;
            $success = 0;
            $failed = 0;

            foreach ($failed_deletions as $log) {
                $processed++;
                
                // Check if contact still exists
                if (!$contact_repo->exists((int) $log->contact_id)) {
                    // Contact already deleted, mark as completed
                    $gdpr_repo->update_deletion_status((int) $log->id, 'completed', null);
                    $success++;
                    continue;
                }

                // Retry deletion
                try {
                    // Delete contact and messages
                    $result = $contact_repo->delete_with_messages((int) $log->contact_id);

                    if ($result['contact_deleted']) {
                        $gdpr_repo->update_deletion_status(
                            (int) $log->id,
                            'completed',
                            null,
                            $result['messages_deleted']
                        );
                        $success++;
                    } else {
                        $gdpr_repo->update_deletion_status(
                            (int) $log->id,
                            'failed',
                            'Failed to delete contact record'
                        );
                        $failed++;
                    }
                } catch (\Throwable $e) {
                    $gdpr_repo->update_deletion_status(
                        (int) $log->id,
                        'failed',
                        $e->getMessage()
                    );
                    $failed++;
                }
            }

            // Log completion
            $duration_ms = (int) ((microtime(true) - $start_time) * 1000);
            $this->log_cron_end($log_id, 'success', $duration_ms, $processed);

            if (defined('WP_DEBUG') && WP_DEBUG) {
                Logger::notice('GDPR deletion cleanup completed', [
                    'processed' => $processed,
                    'success' => $success,
                    'failed' => $failed,
                    'duration_ms' => $duration_ms,
                ]);
            }
        } catch (\Throwable $e) {
            $duration_ms = (int) ((microtime(true) - $start_time) * 1000);
            $this->log_cron_end($log_id, 'failed', $duration_ms, 0, $e->getMessage());
            
            if (defined('WP_DEBUG') && WP_DEBUG) {
                Logger::error('GDPR deletion cleanup failed', [
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }

    /**
     * Fallback maintenance job - reclassify unclassified messages.
     * 
     * This runs daily to catch any messages that failed initial classification,
     * enabling a second chance at intent detection using current patterns.
     * Processes in batches to avoid performance issues.
     */
    public function run_reclassify_unclassified(): void {
        $record_id = CronMonitor::start_job(Config::CRON_RECLASSIFY_UNCLASSIFIED);
        if (!$record_id) {
            return;
        }

        $start_time = microtime(true);

        try {
            $classifier = IntentClassifier::instance();
            $batch_size = 100;
            $processed = 0;
            $success = 0;
            $failed = 0;
            $total_unclassified = 0;

            // Get total count of unclassified messages
            $total_unclassified = $classifier->count_unclassified_messages();

            if ($total_unclassified === 0) {
                Logger::info('Intent reclassification: no unclassified messages found');
                $this->log_cron_end($record_id, 'success', 0, 0);
                return;
            }

            Logger::info('Intent reclassification started', [
                'total_unclassified' => $total_unclassified,
                'batch_size' => $batch_size,
            ]);

            // Process in batches
            $batches = ceil($total_unclassified / $batch_size);
            for ($batch = 0; $batch < $batches; $batch++) {
                try {
                    $offset = $batch * $batch_size;
                    $result = $classifier->bulk_classify_unclassified($batch_size, $offset);
                    
                    if (is_array($result)) {
                        $processed += $result['processed'] ?? 0;
                        $success += $result['success'] ?? 0;
                        $failed += $result['failed'] ?? 0;

                        if (defined('WP_DEBUG') && WP_DEBUG) {
                            Logger::debug('Intent reclassification batch processed', [
                                'batch' => $batch + 1,
                                'of' => $batches,
                                'batch_processed' => $result['processed'] ?? 0,
                                'batch_success' => $result['success'] ?? 0,
                                'batch_failed' => $result['failed'] ?? 0,
                            ]);
                        }
                    }
                } catch (\Throwable $e) {
                    Logger::error('Intent reclassification batch failed', [
                        'batch' => $batch + 1,
                        'error' => $e->getMessage(),
                    ]);
                    $failed += $batch_size;
                }

                // Small delay between batches to reduce database load
                usleep(50000); // 50ms
            }

            // Log completion
            $duration_ms = (int) ((microtime(true) - $start_time) * 1000);
            $this->log_cron_end($record_id, 'success', $duration_ms, $processed);

            Logger::info('Intent reclassification completed', [
                'total_unclassified_found' => $total_unclassified,
                'total_processed' => $processed,
                'success' => $success,
                'failed' => $failed,
                'duration_ms' => $duration_ms,
            ]);

        } catch (\Throwable $e) {
            $duration_ms = (int) ((microtime(true) - $start_time) * 1000);
            $this->log_cron_end($record_id, 'failed', $duration_ms, 0, $e->getMessage());
            
            Logger::error('Intent reclassification cron job failed', [
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);
        }
    }
}

