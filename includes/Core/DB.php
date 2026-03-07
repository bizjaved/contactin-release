<?php
// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.PHP.DevelopmentFunctions.error_log_error_log, WordPress.DateTime.RestrictedFunctions.date_date
declare(strict_types=1);

namespace ContactInbox\Core;

use ContactInbox\Traits\Singleton;
use ContactInbox\Core\EmailLog;
use ContactInbox\Core\Config;
use ContactInbox\Core\DatabaseOptimizer;
use ContactInbox\Core\Repositories\MessageRepository;
use ContactInbox\Core\Repositories\EmailLogRepository;
use ContactInbox\Core\Repositories\RestLogRepository;
use WP_Error;

final class DB {
    use Singleton;

    private string $table_messages;
    private string $table_contacts;
    private string $table_email_log;

    // Repositories
    private MessageRepository $message_repo;
    private EmailLogRepository $email_log_repo;
    private RestLogRepository $rest_log_repo;

    public const OPTION_VERSION = '1.0_contactin_db_version';
    public const CURRENT_VERSION = '1.3';
    // Mirror email status constants for backward compatibility
    public const EMAIL_SENT    = Config::EMAIL_SENT;
    public const EMAIL_FAILED  = Config::EMAIL_FAILED;
    public const EMAIL_PENDING = Config::EMAIL_PENDING;
    public const EMAIL_PROCESSING = Config::EMAIL_PROCESSING;
    public const EMAIL_SKIPPED = Config::EMAIL_SKIPPED;

    private function __construct() {
        global $wpdb;
        $this->table_messages  = $wpdb->prefix . Config::TABLE_MESSAGES;
        $this->table_contacts  = $wpdb->prefix . Config::TABLE_CONTACTS;
        $this->table_email_log = $wpdb->prefix . Config::TABLE_EMAIL_LOG;

        // Initialize repositories
        $this->message_repo = new MessageRepository();
        $this->email_log_repo = new EmailLogRepository();
        $this->rest_log_repo = new RestLogRepository();

    }

    public static function activate(): void {
        global $wpdb;
        $charset = $wpdb->get_charset_collate();

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        foreach (self::get_table_definitions($charset) as $sql) {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.SchemaChange
            dbDelta($sql);
        }

        // Migration: Add next_attempt column if missing (safety for existing installations)
        try {
            $queue_table = $wpdb->prefix . Config::TABLE_QUEUE;
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.SchemaChange
            if ( $wpdb->get_var( "SHOW TABLES LIKE '$queue_table'" ) ) {
                // phpcs:ignore WordPress.DB.DirectDatabaseQuery.SchemaChange
                $columns = $wpdb->get_col( "DESC $queue_table", 0 );
                if ( ! in_array( 'next_attempt', $columns, true ) ) {
                    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.SchemaChange
                    @$wpdb->query( "ALTER TABLE $queue_table ADD COLUMN next_attempt DATETIME DEFAULT NULL" );
                    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.SchemaChange
                    @$wpdb->query( "ALTER TABLE $queue_table ADD KEY idx_queue_next_attempt (next_attempt)" );
                }
            }
        } catch ( \Throwable $e ) {
            // Log but don't block activation
            error_log( '[ContactInbox] Migration error: ' . $e->getMessage() );
        }

        // Verify performance indexes (all indexes created via CREATE TABLE definitions)
        DatabaseOptimizer::optimize_submission_table();

        update_option(self::OPTION_VERSION, self::CURRENT_VERSION);
    }

    /**
     * Build SQL definitions for core plugin tables.
     *
     * @return array<string, string>
     */
    private static function get_table_definitions(string $charset): array {
        global $wpdb;
        $prefix = $wpdb->prefix;

        return [
            // Queue Table
            Config::TABLE_QUEUE => "CREATE TABLE {$prefix}" . Config::TABLE_QUEUE . " (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                type VARCHAR(50) NOT NULL DEFAULT 'email',
                data LONGTEXT DEFAULT NULL,
                message_id BIGINT UNSIGNED DEFAULT NULL,
                priority TINYINT UNSIGNED NOT NULL DEFAULT 3,
                status VARCHAR(20) NOT NULL DEFAULT 'pending',
                retry_count TINYINT UNSIGNED NOT NULL DEFAULT 0,
                last_error TEXT DEFAULT NULL,
                next_attempt DATETIME DEFAULT NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT NULL,
                KEY status (status),
                KEY message_id (message_id),
                KEY idx_queue_type_message_status (type, message_id, status),
                KEY idx_queue_priority_created (priority, created_at),
                KEY idx_queue_next_attempt (next_attempt)
            ) $charset;",
            // Queue Log Table
            Config::TABLE_QUEUE_LOG => "CREATE TABLE {$prefix}" . Config::TABLE_QUEUE_LOG . " (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                queue_id BIGINT UNSIGNED NOT NULL,
                action VARCHAR(50) NOT NULL,
                status VARCHAR(20) NOT NULL,
                message TEXT DEFAULT NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                KEY queue_id (queue_id),
                KEY status (status),
                KEY action (action),
                KEY created_at (created_at)
            ) $charset;",
            // Dead Letter Queue Table
            Config::TABLE_DEAD_LETTER => "CREATE TABLE {$prefix}" . Config::TABLE_DEAD_LETTER . " (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                queue_id BIGINT UNSIGNED DEFAULT NULL,
                type VARCHAR(50) NOT NULL DEFAULT 'email',
                data LONGTEXT DEFAULT NULL,
                message_id BIGINT UNSIGNED DEFAULT NULL,
                error_message TEXT DEFAULT NULL,
                failed_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                KEY queue_id (queue_id),
                KEY message_id (message_id),
                KEY idx_dlq_type_created (type, failed_at)
            ) $charset;",
            Config::TABLE_MESSAGES => "CREATE TABLE {$prefix}" . Config::TABLE_MESSAGES . " (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                contact_id BIGINT UNSIGNED DEFAULT NULL,
                form_id VARCHAR(50) NOT NULL DEFAULT 'default',
                salutation VARCHAR(30) DEFAULT NULL,
                name VARCHAR(100) NOT NULL,
                email VARCHAR(100) NOT NULL,
                phone VARCHAR(50) DEFAULT NULL,
                mobile_phone VARCHAR(50) DEFAULT NULL,
                home_phone VARCHAR(50) DEFAULT NULL,
                other_phone VARCHAR(50) DEFAULT NULL,
                subject VARCHAR(255) NOT NULL,
                message LONGTEXT NOT NULL,
                attachment TEXT DEFAULT NULL,
                consent TINYINT(1) DEFAULT 0,
                ip_address VARCHAR(45) DEFAULT NULL,
                user_agent TEXT DEFAULT NULL,
                receipt_token VARCHAR(64) DEFAULT NULL,
                            status ENUM('" . Config::STATUS_UNREAD . "','" . Config::STATUS_READ . "') 
                    DEFAULT '" . Config::STATUS_UNREAD . "',
                is_archived BOOLEAN DEFAULT FALSE,
                                admin_email_status ENUM('" . Config::EMAIL_SENT . "','" . Config::EMAIL_FAILED . "','" . Config::EMAIL_PENDING . "','" . Config::EMAIL_PROCESSING . "','" . Config::EMAIL_SKIPPED . "') DEFAULT NULL,
                    user_email_status ENUM('" . Config::EMAIL_SENT . "','" . Config::EMAIL_FAILED . "','" . Config::EMAIL_PENDING . "','" . Config::EMAIL_PROCESSING . "','" . Config::EMAIL_SKIPPED . "') DEFAULT NULL,
                    crm_status ENUM('" . Config::CRM_SENT . "','" . Config::CRM_FAILED . "','" . Config::CRM_PENDING . "','" . Config::CRM_PROCESSING . "','" . Config::CRM_SKIPPED . "') DEFAULT NULL,
                admin_email_sent_at DATETIME DEFAULT NULL,
                user_email_sent_at DATETIME DEFAULT NULL,
                crm_synced_at DATETIME DEFAULT NULL,
                admin_email_error TEXT DEFAULT NULL,
                user_email_error TEXT DEFAULT NULL,
                crm_error TEXT DEFAULT NULL,
                admin_email_retries TINYINT UNSIGNED DEFAULT 0,
                user_email_retries TINYINT UNSIGNED DEFAULT 0,
                crm_retries TINYINT UNSIGNED DEFAULT 0,
                submitted_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                gdpr_token VARCHAR(64) DEFAULT NULL,
                gdpr_expires BIGINT UNSIGNED DEFAULT NULL,
                recaptcha_score DECIMAL(3,2) DEFAULT NULL,
                processing_time_ms INT DEFAULT NULL,
                intent_category VARCHAR(50) DEFAULT 'unclassified',
                intent_confidence DECIMAL(4,2) DEFAULT NULL,
                intent_keywords TEXT DEFAULT NULL,
                intent_classified_at DATETIME DEFAULT NULL,
                UNIQUE KEY gdpr_token (gdpr_token),
                UNIQUE KEY receipt_token (receipt_token),
                KEY idx_contact_id (contact_id),
                KEY idx_consent_status (consent, status),
                KEY subject (subject),
                KEY idx_messages_admin_email (admin_email_status, submitted_at),
                KEY idx_messages_user_email (user_email_status, submitted_at),
                KEY idx_messages_crm (crm_status, submitted_at),
                KEY idx_pending_emails (admin_email_status, user_email_status),
                KEY idx_email (email),
                KEY idx_status (status),
                KEY idx_form_id (form_id),
                KEY idx_ip_address (ip_address),
                KEY idx_is_archived (is_archived),
                KEY idx_intent_category (intent_category),
                KEY idx_intent_classified_at (intent_classified_at)
            ) $charset;",
            Config::TABLE_CONTACTS => "CREATE TABLE {$prefix}" . Config::TABLE_CONTACTS . " (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                name VARCHAR(150) NOT NULL DEFAULT '',
                salutation VARCHAR(30) DEFAULT NULL,
                email VARCHAR(150) DEFAULT NULL,
                primary_phone VARCHAR(50) DEFAULT NULL,
                mobile_phone VARCHAR(50) DEFAULT NULL,
                home_phone VARCHAR(50) DEFAULT NULL,
                other_phone VARCHAR(50) DEFAULT NULL,
                source VARCHAR(50) DEFAULT NULL,
                crm_sync_status ENUM('" . Config::CRM_SENT . "','" . Config::CRM_FAILED . "','" . Config::CRM_PENDING . "','" . Config::CRM_PROCESSING . "','" . Config::CRM_SKIPPED . "') DEFAULT NULL,
                last_message_at DATETIME DEFAULT NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                UNIQUE KEY uniq_email (email),
                KEY idx_primary_phone (primary_phone),
                KEY idx_mobile_phone (mobile_phone),
                KEY idx_home_phone (home_phone),
                KEY idx_other_phone (other_phone),
                KEY idx_last_message_at (last_message_at),
                KEY idx_crm_sync_status (crm_sync_status)
            ) $charset;",
            Config::TABLE_EMAIL_LOG => "CREATE TABLE {$prefix}" . Config::TABLE_EMAIL_LOG . " (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                recipient VARCHAR(255) NOT NULL,
                subject VARCHAR(255) NOT NULL,
                status ENUM('" . Config::EMAIL_SENT . "','" . Config::EMAIL_FAILED . "','" . Config::EMAIL_PENDING . "')
                    DEFAULT '" . Config::EMAIL_PENDING . "',
                error_message TEXT DEFAULT NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                type VARCHAR(50) NOT NULL DEFAULT 'contact_form',
                KEY status (status),
                KEY created_at (created_at),
                KEY type (type)
            ) $charset;",
            Config::TABLE_REST_LOG => "CREATE TABLE {$prefix}" . Config::TABLE_REST_LOG . " (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                timestamp DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                ip_address VARCHAR(45) DEFAULT NULL,
                user_agent TEXT DEFAULT NULL,
                user_id BIGINT UNSIGNED DEFAULT NULL,
                http_method VARCHAR(10) NOT NULL,
                endpoint VARCHAR(255) NOT NULL,
                request_headers LONGTEXT DEFAULT NULL,
                request_payload LONGTEXT DEFAULT NULL,
                response_code INT DEFAULT NULL,
                response_body LONGTEXT DEFAULT NULL,
                validated TINYINT(1) DEFAULT 0,
                token_valid TINYINT(1) DEFAULT NULL,
                error_code VARCHAR(100) DEFAULT NULL,
                error_message TEXT DEFAULT NULL,
                validation_errors LONGTEXT DEFAULT NULL,
                KEY timestamp (timestamp),
                KEY http_method (http_method),
                KEY endpoint (endpoint),
                KEY response_code (response_code),
                KEY token_valid (token_valid),
                KEY user_id (user_id)
            ) $charset;",
            Config::TABLE_SUBMISSION_LOG => "CREATE TABLE {$prefix}" . Config::TABLE_SUBMISSION_LOG . " (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                message_id BIGINT UNSIGNED NOT NULL,
                email VARCHAR(255) NOT NULL,
                ip_address VARCHAR(45) DEFAULT NULL,
                status VARCHAR(20) DEFAULT 'pending',
                attempted_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                completed_at DATETIME DEFAULT NULL,
                error_message TEXT DEFAULT NULL,
                KEY message_id (message_id),
                KEY email (email),
                KEY status (status),
                KEY attempted_at (attempted_at)
            ) $charset;",
            Config::TABLE_ALERTS => "CREATE TABLE {$prefix}" . Config::TABLE_ALERTS . " (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                type VARCHAR(100) NOT NULL,
                message TEXT NOT NULL,
                level ENUM('info', 'warning', 'critical') DEFAULT 'warning',
                is_read TINYINT(1) DEFAULT 0,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                KEY type (type),
                KEY level (level),
                KEY is_read (is_read),
                KEY created_at (created_at)
            ) $charset;",
            Config::TABLE_CRM_LOG => "CREATE TABLE {$prefix}" . Config::TABLE_CRM_LOG . " (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                message_id BIGINT UNSIGNED DEFAULT NULL,
                crm_system VARCHAR(50) DEFAULT 'salesforce',
                operation VARCHAR(50) DEFAULT 'sync',
                crm_id VARCHAR(100) DEFAULT NULL,
                status VARCHAR(20) DEFAULT 'pending',
                response LONGTEXT DEFAULT NULL,
                error_message TEXT DEFAULT NULL,
                files_queued INT DEFAULT 0,
                queued_filenames TEXT DEFAULT NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                KEY message_id (message_id),
                KEY status (status),
                KEY created_at (created_at)
            ) $charset;",
            Config::TABLE_ANALYTICS_DAILY => "CREATE TABLE {$prefix}" . Config::TABLE_ANALYTICS_DAILY . " (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                date DATE NOT NULL,
                metric_type VARCHAR(100) NOT NULL,
                form_id VARCHAR(50) DEFAULT NULL,
                value DECIMAL(10, 2) NOT NULL DEFAULT 0,
                metadata LONGTEXT DEFAULT NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                UNIQUE KEY date_metric_form (date, metric_type, form_id),
                KEY date (date),
                KEY metric_type (metric_type),
                KEY form_id (form_id)
            ) $charset;",
            Config::TABLE_ANALYTICS_EVENTS => "CREATE TABLE {$prefix}" . Config::TABLE_ANALYTICS_EVENTS . " (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                event_type VARCHAR(50) NOT NULL,
                submission_id BIGINT UNSIGNED DEFAULT NULL,
                form_id VARCHAR(50) DEFAULT NULL,
                user_ip VARCHAR(45) DEFAULT NULL,
                device_type VARCHAR(20) DEFAULT NULL,
                device_os VARCHAR(50) DEFAULT NULL,
                browser VARCHAR(50) DEFAULT NULL,
                country VARCHAR(2) DEFAULT NULL,
                utm_source VARCHAR(100) DEFAULT NULL,
                utm_medium VARCHAR(100) DEFAULT NULL,
                utm_campaign VARCHAR(100) DEFAULT NULL,
                referrer VARCHAR(500) DEFAULT NULL,
                event_data LONGTEXT DEFAULT NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                KEY event_type (event_type),
                KEY submission_id (submission_id),
                KEY form_id (form_id),
                KEY created_at (created_at),
                KEY device_type (device_type),
                KEY country (country),
                KEY user_ip (user_ip)
            ) $charset;",
            Config::TABLE_CRM_ERRORS => "CREATE TABLE {$prefix}" . Config::TABLE_CRM_ERRORS . " (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                webhook_log_id BIGINT UNSIGNED DEFAULT NULL,
                message_id BIGINT UNSIGNED DEFAULT NULL,
                endpoint VARCHAR(255) NOT NULL,
                error_type VARCHAR(50) NOT NULL,
                error_message LONGTEXT NOT NULL,
                http_code INT DEFAULT NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                KEY error_type (error_type),
                KEY endpoint (endpoint),
                KEY created_at (created_at),
                KEY webhook_log_id (webhook_log_id),
                KEY message_id (message_id)
            ) $charset;",
            Config::TABLE_SUBMISSION_ATTEMPTS => "CREATE TABLE {$prefix}" . Config::TABLE_SUBMISSION_ATTEMPTS . " (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                form_id VARCHAR(50) NOT NULL DEFAULT 'default',
                email VARCHAR(100) DEFAULT NULL,
                ip_address VARCHAR(45) NOT NULL,
                user_agent TEXT DEFAULT NULL,
                rejection_reason VARCHAR(100) NOT NULL,
                recaptcha_score DECIMAL(3,2) DEFAULT NULL,
                processing_time_ms INT DEFAULT NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                KEY form_id (form_id),
                KEY created_at (created_at),
                KEY ip_address (ip_address),
                KEY rejection_reason (rejection_reason)
            ) $charset;",
            Config::TABLE_LOGS => "CREATE TABLE {$prefix}" . Config::TABLE_LOGS . " (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                level VARCHAR(20) NOT NULL,
                message TEXT NOT NULL,
                context LONGTEXT DEFAULT NULL,
                timestamp DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                KEY level (level),
                KEY timestamp (timestamp)
            ) $charset;",
            Config::TABLE_CRON_LOG => "CREATE TABLE {$prefix}" . Config::TABLE_CRON_LOG . " (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                cron_hook VARCHAR(100) NOT NULL,
                status ENUM('running','success','failed','timeout') NOT NULL DEFAULT 'running',
                start_time DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                end_time DATETIME NULL,
                duration_ms INT UNSIGNED DEFAULT 0,
                error_message TEXT NULL,
                error_code VARCHAR(50) NULL,
                last_failure_time DATETIME NULL,
                failure_count INT UNSIGNED DEFAULT 0,
                items_processed INT UNSIGNED DEFAULT 0,
                KEY cron_hook (cron_hook),
                KEY status (status),
                KEY start_time (start_time),
                KEY failure_count (failure_count)
            ) $charset;",
            Config::TABLE_SF_ATTACHMENTS => "CREATE TABLE {$prefix}" . Config::TABLE_SF_ATTACHMENTS . " (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                message_id BIGINT UNSIGNED NOT NULL,
                filename VARCHAR(255) NOT NULL,
                file_size BIGINT UNSIGNED NOT NULL,
                case_id VARCHAR(100) NOT NULL,
                content_version_id VARCHAR(100) DEFAULT NULL,
                content_document_id VARCHAR(100) DEFAULT NULL,
                status VARCHAR(20) DEFAULT 'pending',
                error_message TEXT DEFAULT NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT NULL,
                KEY message_id (message_id),
                KEY case_id (case_id),
                KEY status (status),
                KEY created_at (created_at)
            ) $charset;",
            Config::TABLE_GDPR_DELETION_LOG => "CREATE TABLE {$prefix}" . Config::TABLE_GDPR_DELETION_LOG . " (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                contact_id BIGINT UNSIGNED DEFAULT NULL,
                email VARCHAR(150) NOT NULL,
                name VARCHAR(150) DEFAULT NULL,
                crm_sync_status VARCHAR(20) DEFAULT NULL,
                messages_deleted INT UNSIGNED DEFAULT 0,
                messages_synced INT UNSIGNED DEFAULT 0,
                messages_unsynced INT UNSIGNED DEFAULT 0,
                files_deleted INT UNSIGNED DEFAULT 0,
                deletion_status ENUM('pending','in_progress','completed','failed') DEFAULT 'pending',
                error_message TEXT DEFAULT NULL,
                deleted_by BIGINT UNSIGNED DEFAULT NULL,
                deleted_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                crm_deletion_queued_at DATETIME DEFAULT NULL,
                KEY email (email),
                KEY crm_sync_status (crm_sync_status),
                KEY deletion_status (deletion_status),
                KEY deleted_at (deleted_at),
                KEY deleted_by (deleted_by)
            ) $charset;",
        ];
    }


    /**
     * Plugin deactivate - does not drop tables to preserve data
     */
    public static function deactivate(): void {
        // No table drops on deactivate - data preservation
    }

    public static function uninstall(): void {
        if (!defined('WP_UNINSTALL_PLUGIN')) {
            return;
        }

        global $wpdb;
        $charset = $wpdb->get_charset_collate();

        // Get all table names from schema definition
        $tables = array_keys(self::get_table_definitions($charset));

        // Drop all plugin tables
        foreach ($tables as $table) {
            $table_name = $wpdb->prefix . esc_sql($table);
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.SchemaChange
            $wpdb->query("DROP TABLE IF EXISTS `{$table_name}`");
        }
    }

    // ==================== REPOSITORY DELEGATION METHODS ====================
    // These methods delegate to specialized repositories for better maintainability
    // All method signatures remain unchanged for 100% backward compatibility

    // ==================== MESSAGE REPOSITORY DELEGATION ====================
    
    public function insert_message(array $data): int|false {
        return $this->message_repo->insert($data);
    }

    public function get_messages(
        int $page = 1,
        string $search = '',
        string $status = 'all',
        int $per_page = Config::INBOX_PER_PAGE,
        string $orderby = 'submitted_at',
        string $order = 'DESC',
        ?int $contact_id = null,
        ?string $intent = null
    ): array {
        return $this->message_repo->get_paginated($page, $per_page, $search, $status, $orderby, $order, $contact_id, $intent);
    }

    public function get_message_ids(string $search = '', string $status = 'all', ?int $contact_id = null): array {
        return $this->message_repo->get_all_ids($search, $status, $contact_id);
    }

    public function count_messages_by_period(string $period): int {
        return $this->message_repo->count_by_period($period);
    }

    public function get_total_messages(string $search = '', string $status = 'all', ?int $contact_id = null, ?string $intent = null): int {
        return $this->message_repo->count($search, $status, $contact_id, $intent);
    }

    public function get_message_by_id(int $id): ?\ContactInbox\Core\Message {
        return $this->message_repo->get_by_id($id);
    }

    public function get_all_message_ids(string $search = '', string $status = 'all', ?int $contact_id = null): array {
        return $this->message_repo->get_all_ids($search, $status, $contact_id);
    }

    public function get_table_messages(): string {
        return $this->message_repo->get_table_name();
    }

    public function reset_email_failures(): int {
        return $this->message_repo->reset_email_failures();
    }

    public function reset_crm_failures(): int {
        return $this->message_repo->reset_crm_failures();
    }

    public function get_messages_for_export(string $search = '', string $status = 'all', int $limit = Config::EXPORT_LIMIT, int $offset = 0, ?int $contact_id = null, ?string $intent = null): array {
        $limit  = max(1, min($limit, Config::EXPORT_LIMIT));
        $offset = max(0, $offset);

        return $this->message_repo->get_for_export($search, $status, $limit, $offset, $contact_id, $intent);
    }

    public function get_message_trend(int $days = 7): array {
        return $this->message_repo->get_trend($days);
    }

    public function delete_message(int $id): bool {
        return $this->message_repo->delete($id);
    }

    public function toggle_status(int $id): string|false {
        return $this->message_repo->toggle_status($id);
    }

    public function toggle_archive(int $id, bool $archived): bool {
        return $this->message_repo->update_archive($id, $archived);
    }

    public function bulk_delete(array $ids): int {
        return $this->message_repo->bulk_delete($ids);
    }

    public function bulk_update_status(array $ids, string $status): int {
        return $this->message_repo->bulk_update_status($ids, $status);
    }

    public function bulk_update_archive(array $ids, bool $archived): int {
        return $this->message_repo->bulk_update_archive($ids, $archived);
    }

    public function bulk_clear_spam(array $ids): int {
        return $this->message_repo->bulk_clear_spam($ids);
    }

    public function bulk_mark_spam(array $ids): int {
        return $this->message_repo->bulk_mark_spam($ids);
    }

    public function mark_spam(int $message_id): bool {
        return $this->message_repo->mark_message_as_spam($message_id);
    }

    public function clear_spam(int $message_id): bool {
        return $this->message_repo->mark_message_as_not_spam($message_id);
    }

    public function delete_all_spam(): int {
        return $this->message_repo->delete_all_spam();
    }

    public function delete_all_archived(): int {
        return $this->message_repo->delete_all_archived();
    }

    public function export_all(): array {
        return $this->message_repo->get_for_export();
    }

    public function search_messages(string $query, int $page = 1, int $per_page = Config::INBOX_PER_PAGE): array {
        return $this->message_repo->search($query, $page, $per_page);
    }

    // ==================== INTENT CLASSIFICATION DELEGATION ====================

    public function update_message_intent(int $message_id, array $intent): bool {
        return $this->message_repo->update_intent($message_id, $intent);
    }

    public function get_intent_stats(): array {
        return $this->message_repo->get_intent_stats();
    }

    public function get_intent_trend(int $days = 7): array {
        return $this->message_repo->get_intent_trend($days);
    }

    public function get_messages_by_intent(string $category, int $page = 1, int $per_page = 20): array {
        return $this->message_repo->get_by_intent($category, $page, $per_page);
    }

    public function count_messages_by_intent(string $category): int {
        return $this->message_repo->count_by_intent($category);
    }

    public function count_unclassified_messages(): int {
        return $this->message_repo->count_unclassified();
    }

    public function get_unclassified_batch(int $limit = 100, int $offset = 0): array {
        return $this->message_repo->get_unclassified_batch($limit, $offset);
    }

    // ==================== MESSAGE STATUS HELPER METHODS (Phase 1: Queue Redesign) ====================
    
    /**
     * Get message status counts across all processing types.
     *
     * @param string|null $start_date Optional start date (Y-m-d).
     * @param string|null $end_date   Optional end date (Y-m-d).
     * @return array{
     *     admin_email_pending:int,
     *     admin_email_sent:int,
     *     admin_email_failed:int,
     *     admin_email_skipped:int,
     *     user_email_pending:int,
     *     user_email_sent:int,
     *     user_email_failed:int,
     *     user_email_skipped:int,
     *     crm_pending:int,
     *     crm_sent:int,
     *     crm_failed:int,
     *     crm_skipped:int
     * }
     */
    public function get_message_status_counts(?string $start_date = null, ?string $end_date = null): array {
        global $wpdb;

        $where_clause = '';
        if ($start_date && $end_date) {
            $where_clause = $wpdb->prepare(
                " WHERE submitted_at >= %s AND submitted_at <= %s",
                $start_date . ' 00:00:00',
                $end_date . ' 23:59:59'
            );
        }

        $table = $this->table_messages;
        $query = "SELECT
            COALESCE(SUM(CASE WHEN admin_email_status IS NULL OR admin_email_status IN (%s, %s) THEN 1 ELSE 0 END), 0) AS admin_email_pending,
            COALESCE(SUM(CASE WHEN admin_email_status = %s THEN 1 ELSE 0 END), 0) AS admin_email_sent,
            COALESCE(SUM(CASE WHEN admin_email_status = %s THEN 1 ELSE 0 END), 0) AS admin_email_failed,
            COALESCE(SUM(CASE WHEN admin_email_status = %s THEN 1 ELSE 0 END), 0) AS admin_email_skipped,
            COALESCE(SUM(CASE WHEN user_email_status IS NULL OR user_email_status IN (%s, %s) THEN 1 ELSE 0 END), 0) AS user_email_pending,
            COALESCE(SUM(CASE WHEN user_email_status = %s THEN 1 ELSE 0 END), 0) AS user_email_sent,
            COALESCE(SUM(CASE WHEN user_email_status = %s THEN 1 ELSE 0 END), 0) AS user_email_failed,
            COALESCE(SUM(CASE WHEN user_email_status = %s THEN 1 ELSE 0 END), 0) AS user_email_skipped,
            COALESCE(SUM(CASE WHEN crm_status IS NULL OR crm_status IN (%s, %s) THEN 1 ELSE 0 END), 0) AS crm_pending,
            COALESCE(SUM(CASE WHEN crm_status = %s THEN 1 ELSE 0 END), 0) AS crm_sent,
            COALESCE(SUM(CASE WHEN crm_status = %s THEN 1 ELSE 0 END), 0) AS crm_failed,
            COALESCE(SUM(CASE WHEN crm_status = %s THEN 1 ELSE 0 END), 0) AS crm_skipped
        FROM {$table}{$where_clause}";

        $result = $wpdb->get_row($wpdb->prepare(
            $query,
            Config::EMAIL_PENDING,
            Config::EMAIL_PROCESSING,
            Config::EMAIL_SENT,
            Config::EMAIL_FAILED,
            Config::EMAIL_SKIPPED,
            Config::EMAIL_PENDING,
            Config::EMAIL_PROCESSING,
            Config::EMAIL_SENT,
            Config::EMAIL_FAILED,
            Config::EMAIL_SKIPPED,
            Config::CRM_PENDING,
            Config::CRM_PROCESSING,
            Config::CRM_SENT,
            Config::CRM_FAILED,
            Config::CRM_SKIPPED
        ));

        return [
            'admin_email_pending' => (int) ($result->admin_email_pending ?? 0),
            'admin_email_sent' => (int) ($result->admin_email_sent ?? 0),
            'admin_email_failed' => (int) ($result->admin_email_failed ?? 0),
            'admin_email_skipped' => (int) ($result->admin_email_skipped ?? 0),
            'user_email_pending' => (int) ($result->user_email_pending ?? 0),
            'user_email_sent' => (int) ($result->user_email_sent ?? 0),
            'user_email_failed' => (int) ($result->user_email_failed ?? 0),
            'user_email_skipped' => (int) ($result->user_email_skipped ?? 0),
            'crm_pending' => (int) ($result->crm_pending ?? 0),
            'crm_sent' => (int) ($result->crm_sent ?? 0),
            'crm_failed' => (int) ($result->crm_failed ?? 0),
            'crm_skipped' => (int) ($result->crm_skipped ?? 0),
        ];
    }

    /**
     * Get message status trends over time (for sparkline charts)
     * Returns count of pending messages per day for the last N days
     * 
     * @param int $days Number of days to look back (default: 30)
     * @return array Array of objects with 'date' and 'admin_pending', 'user_pending', 'crm_pending' counts
     */
    public function get_message_status_trends(int $days = 30): array {
        global $wpdb;
        $table = $this->table_messages;
        
        $date_from = date('Y-m-d H:i:s', strtotime("-{$days} days"));
        
        $results = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT 
                    DATE(submitted_at) as date,
                    COALESCE(SUM(CASE WHEN admin_email_status IS NULL OR admin_email_status = %s THEN 1 ELSE 0 END), 0) as admin_pending,
                    COALESCE(SUM(CASE WHEN user_email_status IS NULL OR user_email_status = %s THEN 1 ELSE 0 END), 0) as user_pending,
                    COALESCE(SUM(CASE WHEN crm_status IS NULL OR crm_status = %s THEN 1 ELSE 0 END), 0) as crm_pending
                FROM {$table}
                WHERE submitted_at >= %s
                GROUP BY DATE(submitted_at)
                ORDER BY date ASC",
                Config::EMAIL_PENDING,
                Config::EMAIL_PENDING,
                Config::EMAIL_PENDING,
                $date_from
            )
        );
        
        return $results ?: [];
    }

    /**
     * Get failed messages that need retry
     * Returns messages where any processing type has failed status
     * 
     * @param int $limit Maximum number of messages to return
     * @return array Array of message objects with failed status
     */
    public function get_failed_messages(int $limit = 100): array {
        global $wpdb;
        $table = $this->table_messages;
        
        $results = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$table}
                WHERE admin_email_status = %s 
                   OR user_email_status = %s 
                   OR crm_status = %s
                ORDER BY submitted_at DESC
                LIMIT %d",
                Config::EMAIL_FAILED,
                Config::EMAIL_FAILED,
                Config::CRM_FAILED,
                $limit
            )
        );
        
        return $results ?: [];
    }

    /**
     * Update a specific processing channel status for a message.
     * Safely resets timestamps, error notes, and retry counters when appropriate.
     */
    public function update_message_status(int $message_id, string $processing_type, string $status, ?string $error_message = null): bool {
        global $wpdb;
        $table = $this->table_messages;

        $map = [
            'admin_email' => [
                'status'    => 'admin_email_status',
                'error'     => 'admin_email_error',
                'timestamp' => 'admin_email_sent_at',
                'retries'   => 'admin_email_retries',
            ],
            'user_email' => [
                'status'    => 'user_email_status',
                'error'     => 'user_email_error',
                'timestamp' => 'user_email_sent_at',
                'retries'   => 'user_email_retries',
            ],
            'crm' => [
                'status'    => 'crm_status',
                'error'     => 'crm_error',
                'timestamp' => 'crm_synced_at',
                'retries'   => 'crm_retries',
            ],
        ];

        if (!isset($map[$processing_type])) {
            return false;
        }

        $allowed_statuses = match ($processing_type) {
            'crm' => [Config::CRM_PENDING, Config::CRM_PROCESSING, Config::CRM_FAILED, Config::CRM_SENT, Config::CRM_SKIPPED],
            default => [Config::EMAIL_PENDING, Config::EMAIL_PROCESSING, Config::EMAIL_FAILED, Config::EMAIL_SENT, Config::EMAIL_SKIPPED],
        };

        if (!in_array($status, $allowed_statuses, true)) {
            return false;
        }

        $pending_status = $processing_type === 'crm' ? Config::CRM_PENDING : Config::EMAIL_PENDING;
        $processing_status = $processing_type === 'crm' ? Config::CRM_PROCESSING : Config::EMAIL_PROCESSING;
        $sent_status = $processing_type === 'crm' ? Config::CRM_SENT : Config::EMAIL_SENT;
        $skipped_status = $processing_type === 'crm' ? Config::CRM_SKIPPED : Config::EMAIL_SKIPPED;

        $columns = $map[$processing_type];

        if ($status === $processing_status) {
            $sql = "UPDATE {$table} SET {$columns['status']} = %s WHERE id = %d";
            $prepared = $wpdb->prepare($sql, $status, $message_id);
        } elseif ($status === $pending_status) {
            $sql = "UPDATE {$table} SET {$columns['status']} = %s, {$columns['error']} = NULL, {$columns['timestamp']} = NULL, {$columns['retries']} = 0 WHERE id = %d";
            $prepared = $wpdb->prepare($sql, $status, $message_id);
        } elseif ($status === $sent_status) {
            $sql = "UPDATE {$table} SET {$columns['status']} = %s, {$columns['error']} = NULL, {$columns['timestamp']} = %s WHERE id = %d";
            $prepared = $wpdb->prepare($sql, $status, current_time('mysql'), $message_id);
        } elseif ($status === $skipped_status) {
            $sql = "UPDATE {$table} SET {$columns['status']} = %s, {$columns['error']} = NULL, {$columns['timestamp']} = NULL, {$columns['retries']} = 0 WHERE id = %d";
            $prepared = $wpdb->prepare($sql, $status, $message_id);
        } else { // failed
            if ($error_message !== null && $error_message !== '') {
                $truncated_error = substr($error_message, 0, 1000);
                $sql = "UPDATE {$table} SET {$columns['status']} = %s, {$columns['error']} = %s WHERE id = %d";
                $prepared = $wpdb->prepare($sql, $status, $truncated_error, $message_id);
            } else {
                $sql = "UPDATE {$table} SET {$columns['status']} = %s, {$columns['error']} = NULL WHERE id = %d";
                $prepared = $wpdb->prepare($sql, $status, $message_id);
            }
        }

        if (!$prepared) {
            return false;
        }

        $updated = $wpdb->query($prepared);

        return $updated !== false;
    }

    /**
     * Mark CRM sync as delivered and timestamp it.
     */
    public function mark_crm_sent(int $message_id): bool {
        return $this->message_repo->mark_crm_sent($message_id);
    }

    /**
     * Mark CRM sync as failed and increment retry count.
     */
    public function mark_crm_failed(int $message_id, string $error_message): bool {
        return $this->message_repo->mark_crm_failed($message_id, $error_message);
    }

    /**
     * Reset retry count for failed messages (for manual retry)
     * 
     * @param int $message_id Message ID to reset
     * @param string $processing_type 'admin_email', 'user_email', or 'crm'
     * @return bool True if successful
     */
    public function reset_message_retry_count(int $message_id, string $processing_type): bool {
        global $wpdb;
        $table = $this->table_messages;
        
        $retry_column = match($processing_type) {
            'admin_email' => 'admin_email_retries',
            'user_email' => 'user_email_retries',
            'crm' => 'crm_retries',
            default => null,
        };
        
        if (!$retry_column) {
            return false;
        }
        
        $result = $wpdb->update(
            $table,
            [$retry_column => 0],
            ['id' => $message_id],
            ['%d'],
            ['%d']
        );
        
        return $result !== false;
    }

    // ==================== EMAIL LOG REPOSITORY DELEGATION ====================

    public function insert_email_log(array $data): int {
        return $this->email_log_repo->insert($data);
    }

    public function get_email_logs(
        int $limit = 100,
        int $offset = 0,
        string $status = '',
        string $orderby = 'created_at',
        string $order = 'DESC'
    ): array {
        return $this->email_log_repo->get_with_limit_offset($limit, $offset, $status, $orderby, $order);
    }

    public function delete_email_logs(array $ids): int {
        return $this->email_log_repo->bulk_delete($ids);
    }

    public function count_email_logs(string $status = ''): int {
        return $this->email_log_repo->count($status);
    }

    public function update_email_log_status(int $log_id, string $status, string $error_message = ''): bool {
        return $this->email_log_repo->update_status($log_id, $status, $error_message);
    }

    public function prune_email_logs(int $retention_days = 90): int {
        return $this->email_log_repo->prune($retention_days);
    }

    public function get_table_email_log(): string {
        return $this->email_log_repo->get_table_name();
    }

    // ==================== WEBHOOK LOG REPOSITORY DELEGATION ====================

    public function insert_webhook_log(array $data): int {
        return $this->webhook_log_repo->insert($data);
    }

    public function update_webhook_log_status(
        int $id,
        int $validated,
        ?int $http_code = null,
        ?string $error_message = null,
        ?string $crm_status = null,
        ?int $response_time = null
    ): bool {
        return $this->webhook_log_repo->update_status($id, $validated, $http_code, $error_message, $crm_status, $response_time);
    }

    public function get_table_webhook_log(): string {
        return $this->webhook_log_repo->get_table_name();
    }

    public function get_webhook_log(int $id): ?array {
        return $this->webhook_log_repo->get_by_id($id);
    }

    public function count_webhook_logs(
        $http_code = 'all',
        $validated = 'all',
        $replay_detected = 'all',
        $crm_send_status = 'all'
    ): int {
        return $this->webhook_log_repo->count($http_code, $validated, $replay_detected, $crm_send_status);
    }

    public function get_webhook_logs(
        int $per_page = 20,
        int $offset = 0,
        string $orderby = 'timestamp',
        string $order = 'DESC',
        $http_code = 'all',
        $validated = 'all',
        $replay_detected = 'all',
        $crm_send_status = 'all'
    ): array {
        return $this->webhook_log_repo->get_with_limit_offset($per_page, $offset, $orderby, $order, $http_code, $validated, $replay_detected, $crm_send_status);
    }

    public function get_adjacent_webhook_log(
        int $current_id,
        string $direction,
        string $http_code = 'all',
        string $validated = 'all',
        string $replay_detected = 'all'
    ): ?array {
        return $this->webhook_log_repo->get_adjacent($current_id, $direction, $http_code, $validated, $replay_detected);
    }

    public function prune_webhook_logs(int $days): int {
        return $this->webhook_log_repo->prune($days);
    }

    // ==================== REST LOG REPOSITORY DELEGATION ====================

    public function log_rest_call(array $data): int {
        return $this->rest_log_repo->insert($data);
    }

    public function get_rest_log(int $id): ?array {
        return $this->rest_log_repo->get_by_id($id);
    }

    public function count_rest_logs(?string $method = null, ?string $endpoint = null, ?int $http_code = null, ?int $validated = null): int {
        return $this->rest_log_repo->count($method, $endpoint, $http_code, $validated);
    }

    public function get_rest_logs(int $limit, int $offset, ?string $method = null, ?string $endpoint = null, ?int $http_code = null, ?int $validated = null, string $orderby = 'timestamp', string $order = 'DESC'): array {
        return $this->rest_log_repo->get_with_limit_offset($limit, $offset, $method, $endpoint, $http_code, $validated, $orderby, $order);
    }

    public function update_rest_log_status(
        int $id,
        int $response_code,
        int $validated,
        ?string $error_code = null,
        ?string $error_message = null
    ): bool {
        return $this->rest_log_repo->update_status($id, $response_code, $validated, $error_code, $error_message);
    }

    public function prune_rest_logs(int $days): int {
        return $this->rest_log_repo->prune($days);
    }

    public function get_adjacent_rest_log(
        int $current_id,
        string $direction,
        string $http_method = 'all',
        string $endpoint = 'all',
        string $http_code = 'all',
        string $validated = 'all'
    ): ?array {
        return $this->rest_log_repo->get_adjacent($current_id, $direction, $http_method, $endpoint, $http_code, $validated);
    }

    public function get_distinct_endpoints(): array {
        return $this->rest_log_repo->get_distinct_endpoints();
    }

    // ==================== GDPR REPOSITORY DELEGATION (Pro Only - Stubs) ====================

    public function delete_expired_gdpr(): int {
        return 0; // Pro feature
    }

    public function generate_gdpr_token(int $message_id, int $expires): ?string {
        return null; // Pro feature
    }

    public function save_gdpr_token(int $message_id, string $token, int $expires): bool {
        return false; // Pro feature
    }

    public function validate_gdpr_token_get_id(string $token, ?string $email = null): ?int {
        return null; // Pro feature
    }

    public function clear_gdpr_token(int $message_id): bool {
        return false; // Pro feature
    }

    // ==================== CRM REPOSITORY DELEGATION (Pro Only - Stubs) ====================

    public function insert_crm_log(array $data): int {
        return 0; // Pro feature
    }

    public function has_successful_crm_sync(int $message_id): bool {
        return false; // Pro feature
    }

    public function get_crm_logs(
        int $per_page = 20,
        int $offset = 0,
        string $orderby = 'timestamp',
        string $order = 'DESC',
        string $status = 'all',
        ?string $operation = null,
        ?int $days = null,
        ?string $start_date = null,
        ?string $end_date = null
    ): array {
        return []; // Pro feature
    }

    public function count_crm_logs(string $status = 'all', ?string $operation = null): int {
        return 0; // Pro feature
    }

    public function get_crm_stats(?int $days = null, ?string $start_date = null, ?string $end_date = null, ?string $operation = null): array {
        return []; // Pro feature
    }

    public function get_crm_log(int $id): ?array {
        return null; // Pro feature
    }


    /**
     * Get all attachment paths from the messages table (for orphaned file scan)
     * @return array Array of attachment paths (may be JSON or string)
     */
    public function get_all_attachment_paths(): array {
        global $wpdb;
        $table = $this->table_messages;
        $results = $wpdb->get_col("SELECT attachment FROM {$table} WHERE attachment IS NOT NULL AND attachment != ''");
        
        $paths = [];
        foreach ($results as $attachment) {
            // Handle JSON-encoded attachment data
            if (is_string($attachment) && ($attachment[0] === '{' || $attachment[0] === '[')) {
                $data = json_decode($attachment, true);
                if (is_array($data)) {
                    // Single attachment as JSON object
                    if (isset($data['path'])) {
                        $paths[] = basename($data['path']);
                    }
                    // Multiple attachments as JSON array
                    elseif (is_array($data) && count($data) > 0) {
                        foreach ($data as $item) {
                            if (is_array($item) && isset($item['path'])) {
                                $paths[] = basename($item['path']);
                            }
                        }
                    }
                } else {
                    // Fallback: treat as plain filename
                    $paths[] = basename($attachment);
                }
            } else {
                // Plain filename
                $paths[] = basename($attachment);
            }
        }
        
        return array_unique(array_filter($paths));
    }

    /**
     * Clean stale database entries (files that no longer exist on disk)
     * @param string $uploads_dir Directory where attachments are stored
     * @return int Number of records cleaned
     */
    public function clean_stale_attachments(string $uploads_dir): int {
        return $this->message_repo->clean_stale_attachments($uploads_dir);
    }

}
