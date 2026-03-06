<?php
namespace ContactInbox;

use ContactInbox\Core\Config;
use ContactInbox\Core\CoreBootstrap;
use ContactInbox\Core\DB;
use ContactInbox\Core\Settings;
use ContactInbox\Cron\AnalyticsAggregationJob;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class Lifecycle {
    /**
     * Plugin activation.
     * - Creates DB tables
     * - Creates performance indexes on messages table
     * - Sets default settings
     * - Schedules cron jobs
     * 
     * Note: Conflict detection with Pro version is handled via activated_plugin hook in main plugin file
     */
    public static function activate(): void {
        // Ensure DB tables exist and create performance indexes
        DB::instance()->activate();

        // Ensure default settings are present
        $defaults = Settings::get_default_settings();
        $current  = get_option( Config::OPTION_SETTINGS, [] );
        update_option( Config::OPTION_SETTINGS, array_merge( $defaults, $current ) );

        // Install/update intent classification patterns with robust error handling
        $pattern_install = \ContactInbox\Core\IntentClassifier::install_patterns();
        if (!$pattern_install['success']) {
            error_log('[ContactInbox] Pattern installation failed: ' . implode(', ', $pattern_install['errors']));
            // Don't block activation - patterns can be installed later
        }

        self::schedule_cron_jobs();

        // Flush rewrite rules to ensure REST API routes are registered
        flush_rewrite_rules();
    }

    /**
     * Plugin deactivation.
     * - Clears scheduled cron jobs
     * - Flushes rewrite rules
     */
    public static function deactivate(): void {
        wp_clear_scheduled_hook( Config::CRON_CLEANUP );
        wp_clear_scheduled_hook( Config::CRON_GDPR );
        wp_clear_scheduled_hook( Config::CRON_PROCESS_EMAIL );
        wp_clear_scheduled_hook( Config::CRON_PROCESS_CRM );
        wp_clear_scheduled_hook( Config::CRON_GDPR_CLEANUP );
        wp_clear_scheduled_hook( Config::CRON_RECLASSIFY_UNCLASSIFIED );
        wp_clear_scheduled_hook( 'contactin_process_queue' ); // Legacy hook for backward compatibility
        AnalyticsAggregationJob::unschedule();

        // Flush rewrite rules to clean up REST API routes
        flush_rewrite_rules();
    }

    /**
     * Plugin uninstall.
     * - Deletes DB tables
     * - Removes all plugin options
     * - Clears scheduled cron jobs
     * - Removes transients and cached data
     * - Cleans up uploaded files
     */
    public static function uninstall(): void {
        global $wpdb;

        // Clear all scheduled cron jobs FIRST (before deleting tables)
        // This prevents background cron jobs from trying to access deleted tables
        // Use @ to suppress any warnings from wp_clear_scheduled_hook
        @wp_clear_scheduled_hook( Config::CRON_CLEANUP );
        @wp_clear_scheduled_hook( Config::CRON_GDPR );
        @wp_clear_scheduled_hook( Config::CRON_PROCESS_EMAIL );
        @wp_clear_scheduled_hook( Config::CRON_PROCESS_CRM );
        @wp_clear_scheduled_hook( Config::CRON_GDPR_CLEANUP );
        @wp_clear_scheduled_hook( Config::CRON_RECLASSIFY_UNCLASSIFIED );
        @wp_clear_scheduled_hook( 'contactin_process_queue' ); // Legacy hook
        
        // Unschedule analytics job with error suppression
        try {
            if ( class_exists( '\ContactInbox\Cron\AnalyticsAggregationJob' ) ) {
                @AnalyticsAggregationJob::unschedule();
            }
        } catch ( \Throwable $e ) {
            // Ignore errors - job doesn't need to exist
        }

        // Drop DB tables (now safe, no cron jobs running)
        try {
            DB::uninstall();
        } catch ( \Throwable $e ) {
            error_log( '[ContactInbox] Error dropping tables: ' . $e->getMessage() );
            // Continue with option cleanup
        }

        // Remove core plugin options with error suppression
        @delete_option( Config::OPTION_SETTINGS );
        @delete_option( Config::OPTION_VERSION );
        @delete_option( 'contactinbox_db_version' );
        
        // Remove intent classification options
        @delete_option( 'contactin_intent_patterns_version' );
        @delete_option( 'contactin_intent_patterns' );
        @delete_option( 'contactin_intent_patterns_backup' );
        @delete_option( 'contactin_intent_patterns_checksum' );
        @delete_option( 'contactin_patterns_installed' );
        @delete_option( 'contactin_patterns_installed_at' );
        
        // Remove REST API test tokens
        @delete_option( 'contactin_test_tokens' );

        // Delete all plugin-specific options (circuit breaker, rate limiter, queue triggers, etc.)
        // Use suppressWarnings to continue cleanup even if queries have issues
        try {
            $contactin_like = $wpdb->esc_like( 'contactin_' ) . '%';
            $contactinbox_like = $wpdb->esc_like( 'contactinbox_' ) . '%';
            @$wpdb->query(
                $wpdb->prepare(
                    "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
                    $contactin_like
                )
            );
            @$wpdb->query(
                $wpdb->prepare(
                    "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
                    $contactinbox_like
                )
            );
        } catch ( \Throwable $e ) {
            // Log but continue cleanup
            error_log( '[ContactInbox] Error deleting options: ' . $e->getMessage() );
        }
        
        // Delete all plugin transients
        try {
            $transient_contactin = $wpdb->esc_like( '_transient_contactin_' ) . '%';
            $transient_timeout_contactin = $wpdb->esc_like( '_transient_timeout_contactin_' ) . '%';
            $transient_contactinbox = $wpdb->esc_like( '_transient_contactinbox_' ) . '%';
            $transient_timeout_contactinbox = $wpdb->esc_like( '_transient_timeout_contactinbox_' ) . '%';
            @$wpdb->query(
                $wpdb->prepare(
                    "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
                    $transient_contactin
                )
            );
            @$wpdb->query(
                $wpdb->prepare(
                    "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
                    $transient_timeout_contactin
                )
            );
            @$wpdb->query(
                $wpdb->prepare(
                    "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
                    $transient_contactinbox
                )
            );
            @$wpdb->query(
                $wpdb->prepare(
                    "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
                    $transient_timeout_contactinbox
                )
            );
        } catch ( \Throwable $e ) {
            // Log but continue cleanup
            error_log( '[ContactInbox] Error deleting transients: ' . $e->getMessage() );
        }

        // Delete uploaded attachment files
        try {
            if ( defined( 'CONTACTINBOX_UPLOADS_PATH' ) && is_dir( CONTACTINBOX_UPLOADS_PATH ) ) {
                $upload_path = CONTACTINBOX_UPLOADS_PATH;
                error_log( '[ContactInbox] Attempting to delete upload directory: ' . $upload_path );
                
                $result = self::delete_directory_recursive( $upload_path );
                
                if ( $result ) {
                    error_log( '[ContactInbox] Successfully deleted upload directory' );
                } else {
                    error_log( '[ContactInbox] Failed to fully delete upload directory - some files may remain' );
                    
                    // Fallback: Try to delete as many files as possible even if directory removal fails
                    if ( is_dir( $upload_path ) ) {
                        $remaining_files = glob( $upload_path . '*', GLOB_MARK );
                        if ( $remaining_files ) {
                            error_log( '[ContactInbox] ' . count( $remaining_files ) . ' items remaining in upload directory' );
                            foreach ( $remaining_files as $file ) {
                                error_log( '[ContactInbox] Remaining: ' . $file . ' (writable: ' . ( is_writable( $file ) ? 'yes' : 'no' ) . ')' );
                            }
                        }
                    }
                }
            } elseif ( defined( 'CONTACTINBOX_UPLOADS_PATH' ) ) {
                error_log( '[ContactInbox] Upload directory does not exist: ' . CONTACTINBOX_UPLOADS_PATH );
            } else {
                error_log( '[ContactInbox] CONTACTINBOX_UPLOADS_PATH constant not defined during uninstall' );
            }
        } catch ( \Throwable $e ) {
            // Log but continue cleanup
            error_log( '[ContactInbox] Error deleting upload directory: ' . $e->getMessage() );
        }

        // Clear any cached data
        try {
            @wp_cache_flush();
        } catch ( \Throwable $e ) {
            error_log( '[ContactInbox] Error flushing cache: ' . $e->getMessage() );
        }
    }

    /**
     * Recursively delete a directory and all its contents.
     *
     * @param string $dir Directory path to delete.
     * @return bool True on success, false on failure.
     */
    private static function delete_directory_recursive( string $dir ): bool {
        try {
            if ( ! is_dir( $dir ) ) {
                error_log( '[ContactInbox] delete_directory_recursive: Not a directory: ' . $dir );
                return false;
            }

            if ( ! is_readable( $dir ) ) {
                error_log( '[ContactInbox] delete_directory_recursive: Directory not readable: ' . $dir );
                return false;
            }

            $entries = @scandir( $dir );
            if ( $entries === false ) {
                error_log( '[ContactInbox] delete_directory_recursive: scandir failed for: ' . $dir );
                return false;
            }
            
            $files = array_diff( $entries, [ '.', '..' ] );
            $success = true;
            
            foreach ( $files as $file ) {
                $path = $dir . DIRECTORY_SEPARATOR . $file;
                
                if ( is_dir( $path ) && ! is_link( $path ) ) {
                    // Recursively delete subdirectory
                    if ( ! self::delete_directory_recursive( $path ) ) {
                        error_log( '[ContactInbox] Failed to delete subdirectory: ' . $path );
                        $success = false;
                    }
                } else {
                    // Delete file or symlink
                    if ( ! @unlink( $path ) ) {
                        error_log( '[ContactInbox] Failed to unlink file: ' . $path . ' (writable: ' . ( is_writable( $path ) ? 'yes' : 'no' ) . ')' );
                        $success = false;
                    }
                }
            }
            
            // Try to remove the directory itself
            if ( ! @rmdir( $dir ) ) {
                error_log( '[ContactInbox] Failed to remove directory: ' . $dir . ' (writable: ' . ( is_writable( $dir ) ? 'yes' : 'no' ) . ')' );
                return false;
            }
            
            return $success;
        } catch ( \Throwable $e ) {
            error_log( '[ContactInbox] Error in delete_directory_recursive: ' . $e->getMessage() );
            return false;
        }
    }

    /**
     * Reschedule cron jobs after interval changes.
     *
     * @param string[] $hooks Optional list of hooks to reschedule.
     */
    public static function reschedule_cron_jobs(array $hooks = [], array $delays = []): void {
        self::schedule_cron_jobs(true, $hooks, $delays);
    }

    /**
     * Centralized cron scheduling.
     *
     * @param bool $force Clear existing schedules before creating new ones.
     * @param string[] $hooks Optional list of hooks to schedule.
     */
    private static function schedule_cron_jobs(bool $force = false, array $hooks = [], array $delays = []): void {
        $bootstrap = CoreBootstrap::instance();
        add_filter('cron_schedules', [$bootstrap, 'register_custom_schedules']);

        $now = time();
        $schedule_map = [
            Config::CRON_CLEANUP => [
                'schedule' => 'daily',
                'timestamp' => $now,
            ],
            Config::CRON_GDPR => [
                'schedule' => 'hourly',
                'timestamp' => $now,
            ],
            Config::CRON_PROCESS_EMAIL => [
                'schedule' => get_option('contactin_queue_interval', 'contactin_fifteen_minutes'),
                'timestamp' => $now,
            ],
            Config::CRON_PROCESS_CRM => [
                'schedule' => get_option(
                    'contactin_crm_queue_interval',
                    get_option('contactin_queue_interval', 'contactin_fifteen_minutes')
                ),
                'timestamp' => $now,
            ],
            Config::CRON_GDPR_CLEANUP => [
                'schedule' => 'daily',
                'timestamp' => $now,
            ],
            Config::CRON_RECLASSIFY_UNCLASSIFIED => [
                'schedule' => 'daily',
                'timestamp' => $now,
            ],
        ];

        if (!empty($hooks)) {
            $schedule_map = array_intersect_key($schedule_map, array_flip($hooks));
        }

        foreach ($schedule_map as $hook => $settings) {
            if ($force) {
                wp_clear_scheduled_hook($hook);
            }

            if (!wp_next_scheduled($hook)) {
                $delay = $delays[$hook] ?? 0;
                $timestamp = $settings['timestamp'] + max(0, (int) $delay);
                wp_schedule_event($timestamp, $settings['schedule'], $hook);
            }
        }

        if (empty($hooks) || in_array(AnalyticsAggregationJob::HOOK, $hooks, true)) {
            AnalyticsAggregationJob::instance()->schedule($force);
        }

        remove_filter('cron_schedules', [$bootstrap, 'register_custom_schedules']);
    }
}
