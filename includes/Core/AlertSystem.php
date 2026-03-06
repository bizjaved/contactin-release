<?php
// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
/**
 * Alert System – Trigger and Send Alerts for Queue Issues
 *
 * Handles alert generation, storage, and sending notifications
 * Supports email and admin notice alerts
 *
 * @package ContactInbox
 */

namespace ContactInbox\Core;

use ContactInbox\Traits\Singleton;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class AlertSystem {
    use Singleton;

    const ALERT_TABLE = 'contactin_alerts';
    const ALERT_CHECKED_OPTION = 'contactin_last_alert_check';
    const CHECK_INTERVAL = 300; // 5 minutes between checks

    /**
     * Send alert notification for queue issues
     *
     * @param string $type Alert type (dlq_overflow, queue_overflow, etc.)
     * @param string $message Alert message
     * @param string $level Alert level (info, warning, critical)
     */
    public static function trigger_alert(string $type, string $message, string $level = 'warning'): void {
        try {
            // Log to database
            self::log_alert($type, $message, $level);

            // Send notifications
            if ($level === 'critical') {
                self::send_admin_email($type, $message, $level);
                self::add_admin_notice($type, $message, $level);
            } elseif ($level === 'warning') {
                self::add_admin_notice($type, $message, $level);
            }

            Logger::warning("Alert triggered: {$type}", [
                'message' => $message,
                'level' => $level,
            ]);
        } catch (\Throwable $e) {
            Logger::error('Failed to trigger alert', [
                'error' => $e->getMessage(),
                'type' => $type,
            ]);
        }
    }

    /**
     * Check for queue issues and trigger alerts
     */
    public static function check_and_alert(): void {
        try {
            // Rate limit checks (every 5 minutes max)
            $last_check = get_option(self::ALERT_CHECKED_OPTION, 0);
            if (time() - intval($last_check) < self::CHECK_INTERVAL) {
                return;
            }

            update_option(self::ALERT_CHECKED_OPTION, time());

            // Get current alerts
            $alerts = QueueMonitor::check_alerts();

            // Store and process alerts
            foreach ($alerts as $alert) {
                self::trigger_alert(
                    $alert['type'],
                    $alert['message'],
                    $alert['level']
                );
            }
        } catch (\Throwable $e) {
            Logger::error('Failed to check and alert', [
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Get recent alerts
     *
     * @param int $limit Maximum number of alerts to retrieve
     * @param string $level Filter by level (null = all)
     * @return array Alert records
     */
    public static function get_recent_alerts(int $limit = 10, ?string $level = null): array {
        try {
            global $wpdb;
            $table = $wpdb->prefix . self::ALERT_TABLE;

            $query = "SELECT * FROM {$table} WHERE 1=1";
            $params = [];

            if ($level) {
                $query .= " AND level = %s";
                $params[] = $level;
            }

            $query .= " ORDER BY created_at DESC LIMIT %d";
            $params[] = $limit;

            if (!empty($params)) {
                $results = $wpdb->get_results($wpdb->prepare($query, ...$params));
            } else {
                $results = $wpdb->get_results($query);
            }

            return is_array($results) ? $results : [];
        } catch (\Throwable $e) {
            Logger::error('Failed to get recent alerts', [
                'error' => $e->getMessage(),
            ]);
            return [];
        }
    }

    /**
     * Get unread alerts count
     *
     * @return int Number of unread alerts
     */
    public static function get_unread_count(): int {
        try {
            global $wpdb;
            $table = $wpdb->prefix . self::ALERT_TABLE;
            $count = $wpdb->get_var("SELECT COUNT(*) FROM {$table} WHERE is_read = 0");
            return intval($count ?? 0);
        } catch (\Throwable $e) {
            Logger::error('Failed to get unread count', ['error' => $e->getMessage()]);
            return 0;
        }
    }

    /**
     * Mark alert as read
     *
     * @param int $alert_id Alert ID
     */
    public static function mark_as_read(int $alert_id): void {
        try {
            global $wpdb;
            $table = $wpdb->prefix . self::ALERT_TABLE;
            $wpdb->update($table, ['is_read' => 1], ['id' => $alert_id], ['%d'], ['%d']);
        } catch (\Throwable $e) {
            Logger::error('Failed to mark alert as read', [
                'error' => $e->getMessage(),
                'alert_id' => $alert_id,
            ]);
        }
    }

    /**
     * Clear old alerts
     *
     * @param int $days Delete alerts older than this many days
     * @return int Number of deleted alerts
     */
    public static function clear_old_alerts(int $days = 30): int {
        try {
            global $wpdb;
            $table = $wpdb->prefix . self::ALERT_TABLE;

            $deleted = $wpdb->query($wpdb->prepare(
                "DELETE FROM {$table} WHERE created_at < DATE_SUB(NOW(), INTERVAL %d DAY)",
                $days
            ));

            if ($deleted > 0) {
                Logger::notice("Cleared {$deleted} old alerts");
            }

            return intval($deleted ?? 0);
        } catch (\Throwable $e) {
            Logger::error('Failed to clear old alerts', [
                'error' => $e->getMessage(),
            ]);
            return 0;
        }
    }

    /**
     * Log alert to database
     *
     * @param string $type Alert type
     * @param string $message Alert message
     * @param string $level Alert level
     */
    private static function log_alert(string $type, string $message, string $level): void {
        try {
            global $wpdb;
            $table = $wpdb->prefix . self::ALERT_TABLE;

            // Check if table exists
            if ($wpdb->get_var("SHOW TABLES LIKE '{$table}'") !== $table) {
                return; // Table doesn't exist yet, skip
            }

            $wpdb->insert(
                $table,
                [
                    'type' => $type,
                    'message' => $message,
                    'level' => $level,
                    'is_read' => 0,
                    'created_at' => current_time('mysql'),
                ],
                ['%s', '%s', '%s', '%d', '%s']
            );
        } catch (\Throwable $e) {
            Logger::error('Failed to log alert', [
                'error' => $e->getMessage(),
                'type' => $type,
            ]);
        }
    }

    /**
     * Send email alert to admins
     *
     * @param string $type Alert type
     * @param string $message Alert message
     * @param string $level Alert level
     */
    private static function send_admin_email(string $type, string $message, string $level): void {
        try {
            $admin_email = get_option('admin_email');
            if (empty($admin_email)) {
                return;
            }

            $subject = sprintf(
                '[%s] Queue Alert: %s',
                get_bloginfo('name'),
                ucfirst(str_replace('_', ' ', $type))
            );

            $body = sprintf(
                "Queue Alert (%s)\n\n" .
                "Type: %s\n" .
                "Level: %s\n" .
                "Message: %s\n" .
                "Time: %s\n\n" .
                "Admin Dashboard: %s",
                strtoupper($level),
                $type,
                $level,
                $message,
                current_time('F j, Y g:i a'),
                admin_url('index.php')
            );

            wp_mail($admin_email, $subject, $body);
        } catch (\Throwable $e) {
            Logger::error('Failed to send admin email alert', [
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Add admin notice for alert
     *
     * @param string $type Alert type
     * @param string $message Alert message
     * @param string $level Alert level
     */
    private static function add_admin_notice(string $type, string $message, string $level): void {
        try {
            $notice_type = $level === 'critical' ? 'error' : 'warning';
            $transient_key = "contactin_alert_{$type}_" . date('YmdHi');

            // Store notice in transient to display on next admin page load
            set_transient($transient_key, [
                'message' => $message,
                'type' => $notice_type,
                'level' => $level,
            ], HOUR_IN_SECONDS);
        } catch (\Throwable $e) {
            Logger::error('Failed to add admin notice', [
                'error' => $e->getMessage(),
            ]);
        }
    }
}
