<?php
declare(strict_types=1);

namespace ContactInbox\Core;


if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
/**
 * Table Definitions Manager
 *
 * Centralized management of shared and version-specific tables.
 * Prevents data loss when uninstalling with both free and pro versions.
 *
 * @package ContactIn\Core
 */
final class TableDefinitions {

	/**
	 * Shared tables (exist in both free and pro versions)
	 */
	private const SHARED_TABLES = array(
		Config::TABLE_QUEUE,
		Config::TABLE_QUEUE_LOG,
		Config::TABLE_DEAD_LETTER,
		Config::TABLE_MESSAGES,
		Config::TABLE_CONTACTS,
		Config::TABLE_EMAIL_LOG,
		Config::TABLE_REST_LOG,
		Config::TABLE_SUBMISSION_LOG,
		Config::TABLE_ALERTS,
		Config::TABLE_CRM_LOG,
		Config::TABLE_ANALYTICS_DAILY,
		Config::TABLE_ANALYTICS_EVENTS,
		Config::TABLE_CRM_ERRORS,
		Config::TABLE_SUBMISSION_ATTEMPTS,
		Config::TABLE_LOGS,
		Config::TABLE_CRON_LOG,
		Config::TABLE_SF_ATTACHMENTS,
		Config::TABLE_GDPR_DELETION_LOG,
	);

	/**
	 * Pro-only tables (only in pro version)
	 */
	private const PRO_ONLY_TABLES = array(
		Config::TABLE_INTENT_FEEDBACK,
	);

	/**
	 * Get all table definitions with SQL
	 *
	 * @param string $charset Database charset and collation
	 * @return array<string, string> Table name => SQL definition
	 */
	public static function getAll( string $charset ): array {
		// All shared tables + pro-only tables
		$all_tables  = array_merge( self::SHARED_TABLES, self::PRO_ONLY_TABLES );
		$definitions = array();

		foreach ( $all_tables as $table_name ) {
			$sql = self::getTableSQL( $table_name, $charset );
			if ( $sql ) {
				$definitions[ $table_name ] = $sql;
			}
		}

		return $definitions;
	}

	/**
	 * Get shared tables that must be preserved when uninstalling pro
	 *
	 * @return array<string>
	 */
	public static function getSharedTables(): array {
		return self::SHARED_TABLES;
	}

	/**
	 * Get pro-only tables that can be safely dropped
	 *
	 * @return array<string>
	 */
	public static function getProOnlyTables(): array {
		return self::PRO_ONLY_TABLES;
	}

	/**
	 * Check if a table is shared (exists in both versions)
	 *
	 * @param string $table_name Table constant from Config
	 * @return bool
	 */
	public static function isSharedTable( string $table_name ): bool {
		return in_array( $table_name, self::SHARED_TABLES, true );
	}

	/**
	 * Check if a table is pro-only
	 *
	 * @param string $table_name Table constant from Config
	 * @return bool
	 */
	public static function isProOnlyTable( string $table_name ): bool {
		return in_array( $table_name, self::PRO_ONLY_TABLES, true );
	}

    /**
     * Get legacy table aliases for a canonical table name.
     *
     * This keeps free/pro plans on the same data store even when older installs
     * used different table prefixes.
     *
     * @param string $table_name Canonical table name from Config
     * @return array<string> Legacy table names without WordPress prefix
     */
    public static function getLegacyTableAliases( string $table_name ): array {
        $canonical_prefix = 'contactinbox_';

        if ( 0 !== strpos( $table_name, $canonical_prefix ) ) {
            return array();
        }

        $suffix  = substr( $table_name, strlen( $canonical_prefix ) );
        $aliases = array(
            'contactin_' . $suffix,
            'contact_inbox_' . $suffix,
        );

        if ( Config::TABLE_DEAD_LETTER === $table_name ) {
            $aliases[] = 'contactinbox_dead_letter';
            $aliases[] = 'contactin_dead_letter';
            $aliases[] = 'contact_inbox_dead_letter';
        }

        $aliases = array_values( array_unique( $aliases ) );

        return array_values(
            array_filter(
                $aliases,
                static function ( string $alias ) use ( $table_name ): bool {
                    return $alias !== $table_name;
                }
            )
        );
    }

	/**
	 * Get SQL definition for specific table
	 *
	 * @param string $table_name Table constant from Config
	 * @param string $charset Database charset and collation
	 * @return string|null SQL definition or null if not found
	 */
	private static function getTableSQL( string $table_name, string $charset ): ?string {
		global $wpdb;
		$prefix = $wpdb->prefix;

		$definitions = array(
			Config::TABLE_QUEUE               => "CREATE TABLE {$prefix}" . Config::TABLE_QUEUE . " (
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
			Config::TABLE_QUEUE_LOG           => "CREATE TABLE {$prefix}" . Config::TABLE_QUEUE_LOG . " (
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
			Config::TABLE_DEAD_LETTER         => "CREATE TABLE {$prefix}" . Config::TABLE_DEAD_LETTER . " (
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
			Config::TABLE_MESSAGES            => "CREATE TABLE {$prefix}" . Config::TABLE_MESSAGES . " (
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
			Config::TABLE_CONTACTS            => "CREATE TABLE {$prefix}" . Config::TABLE_CONTACTS . " (
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
			Config::TABLE_EMAIL_LOG           => "CREATE TABLE {$prefix}" . Config::TABLE_EMAIL_LOG . " (
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
			Config::TABLE_REST_LOG            => "CREATE TABLE {$prefix}" . Config::TABLE_REST_LOG . " (
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
			Config::TABLE_SUBMISSION_LOG      => "CREATE TABLE {$prefix}" . Config::TABLE_SUBMISSION_LOG . " (
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
			Config::TABLE_ALERTS              => "CREATE TABLE {$prefix}" . Config::TABLE_ALERTS . " (
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
			Config::TABLE_CRM_LOG             => "CREATE TABLE {$prefix}" . Config::TABLE_CRM_LOG . " (
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
			Config::TABLE_ANALYTICS_DAILY     => "CREATE TABLE {$prefix}" . Config::TABLE_ANALYTICS_DAILY . " (
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
			Config::TABLE_ANALYTICS_EVENTS    => "CREATE TABLE {$prefix}" . Config::TABLE_ANALYTICS_EVENTS . " (
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
			Config::TABLE_CRM_ERRORS          => "CREATE TABLE {$prefix}" . Config::TABLE_CRM_ERRORS . " (
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
			Config::TABLE_LOGS                => "CREATE TABLE {$prefix}" . Config::TABLE_LOGS . " (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                level VARCHAR(20) NOT NULL,
                message TEXT NOT NULL,
                context LONGTEXT DEFAULT NULL,
                timestamp DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                KEY level (level),
                KEY timestamp (timestamp)
            ) $charset;",
			Config::TABLE_CRON_LOG            => "CREATE TABLE {$prefix}" . Config::TABLE_CRON_LOG . " (
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
			Config::TABLE_SF_ATTACHMENTS      => "CREATE TABLE {$prefix}" . Config::TABLE_SF_ATTACHMENTS . " (
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
			Config::TABLE_GDPR_DELETION_LOG   => "CREATE TABLE {$prefix}" . Config::TABLE_GDPR_DELETION_LOG . " (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                contact_id BIGINT UNSIGNED DEFAULT NULL,
                crm_id VARCHAR(255) DEFAULT NULL,
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
                KEY crm_id (crm_id),
                KEY crm_sync_status (crm_sync_status),
                KEY deletion_status (deletion_status),
                KEY deleted_at (deleted_at),
                KEY deleted_by (deleted_by)
            ) $charset;",
			Config::TABLE_INTENT_FEEDBACK     => "CREATE TABLE {$prefix}" . Config::TABLE_INTENT_FEEDBACK . " (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                message_id BIGINT UNSIGNED NOT NULL,
                original_category VARCHAR(50) NOT NULL,
                original_confidence DECIMAL(5,2) DEFAULT NULL,
                corrected_category VARCHAR(50) NOT NULL,
                correction_source ENUM('user', 'admin', 'api') DEFAULT 'user',
                corrected_by BIGINT UNSIGNED DEFAULT NULL,
                matched_keywords LONGTEXT DEFAULT NULL,
                feedback TEXT DEFAULT NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                KEY message_id (message_id),
                KEY original_category (original_category),
                KEY corrected_category (corrected_category),
                KEY created_at (created_at),
                KEY idx_feedback_week (created_at, original_category)
            ) $charset;",
		);

		return $definitions[ $table_name ] ?? null;
	}
}
