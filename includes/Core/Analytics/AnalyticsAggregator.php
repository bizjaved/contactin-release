<?php
// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
/**
 * Analytics Aggregator – Daily Metric Pre-calculation
 *
 * Pre-aggregates daily metrics into wp_contactin_analytics_daily table
 * for faster dashboard queries. Runs daily via WP-Cron.
 *
 * @package ContactInbox\Core\Analytics
 * @since   1.7.0
 */

declare(strict_types=1);

namespace ContactInbox\Core\Analytics;

use ContactInbox\Core\Config;
use ContactInbox\Core\Repositories\{
    MessageRepository, EmailLogRepository, RestLogRepository,
    WebhookLogRepository, SubmissionRepository
};
use ContactInbox\Core\QueueManager;
use ContactInbox\Core\CRMMonitor;
use ContactInbox\Core\Logger;

if (!defined('ABSPATH')) {
    exit;
}

final class AnalyticsAggregator {

    /**
     * Aggregate all daily metrics and store in analytics_daily table
     *
     * Called daily via WP-Cron. Calculates metrics for yesterday to ensure
     * complete data. Uses existing repositories for queries.
     */
    public static function aggregate_daily_metrics(string $date = ''): bool {
        try {
            global $wpdb;

            // Use yesterday's date if not provided
            if (empty($date)) {
                $date = gmdate('Y-m-d', time() - DAY_IN_SECONDS);
            }

            // Get submissions count for the day
            $submission_count = self::get_submission_count($date);
            self::store_metric($date, 'submission_volume', null, $submission_count);

            // Get conversion rate for the day
            $conversion_rate = self::get_conversion_rate($date);
            self::store_metric($date, 'conversion_rate', null, $conversion_rate);

            // Get email delivery rate
            $email_delivery = self::get_email_delivery_rate($date);
            self::store_metric($date, 'email_delivery_rate', null, $email_delivery);

            // Get API response times
            $api_times = self::get_api_response_times($date);
            foreach ($api_times as $metric => $value) {
                self::store_metric($date, $metric, null, $value);
            }

            // Get queue metrics
            $queue_metrics = self::get_queue_metrics($date);
            foreach ($queue_metrics as $metric => $value) {
                self::store_metric($date, $metric, null, $value);
            }

            // Get CRM sync rate
            $crm_rate = self::get_crm_sync_rate($date);
            self::store_metric($date, 'crm_sync_rate', null, $crm_rate);

            // Prune old events (90-day retention)
            \ContactInbox\Core\AnalyticsCollector::prune_old_events(90);

            // Log success
            Logger::info('Analytics aggregation completed for ' . $date);
            return true;

        } catch (\Exception $e) {
            Logger::error('Analytics aggregation failed: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Get submission count for a specific date
     */
    private static function get_submission_count(string $date): float {
        global $wpdb;
        $table = $wpdb->prefix . Config::TABLE_MESSAGES;

        $count = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$table} WHERE DATE(submitted_at) = %s",
            $date
        ));

        return (float) ($count ?? 0);
    }

    /**
     * Get conversion rate (completed submissions / total submissions)
     */
    private static function get_conversion_rate(string $date): float {
        global $wpdb;
        $table = $wpdb->prefix . Config::TABLE_SUBMISSION_LOG;

        // Total submissions
        $total = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$table} WHERE DATE(attempted_at) = %s",
            $date
        ));

        if (!$total) {
            return 0.0;
        }

        // Completed submissions
        $completed = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$table} WHERE DATE(attempted_at) = %s AND status = 'completed'",
            $date
        ));

        return ($completed / $total) * 100;
    }

    /**
     * Get email delivery rate for the day
     */
    private static function get_email_delivery_rate(string $date): float {
        $repo = new EmailLogRepository();
        $start_date = $date . ' 00:00:00';
        $end_date = $date . ' 23:59:59';
        
        $total = $repo->count('', null, $start_date, $end_date);

        if (!$total) {
            return 0.0;
        }

        $sent = $repo->count(Config::EMAIL_SENT, null, $start_date, $end_date);

        return ($sent / $total) * 100;
    }

    /**
     * Get API response time metrics (P50, P95, P99)
     */
    private static function get_api_response_times(string $date): array {
        global $wpdb;
        $table = $wpdb->prefix . Config::TABLE_REST_LOG;

        // Calculate response times (crude approximation based on log entry timing)
        $metrics = [];

        // Average response code 200 rate
        $total = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$table} WHERE DATE(timestamp) = %s",
            $date
        ));

        if ($total) {
            $success = $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$table} WHERE DATE(timestamp) = %s AND response_code IN (200, 201, 204)",
                $date
            ));
            $metrics['api_success_rate'] = ($success / $total) * 100;
        } else {
            $metrics['api_success_rate'] = 0.0;
        }

        return $metrics;
    }

    /**
     * Get queue processing metrics
     */
    private static function get_queue_metrics(string $date): array {
        global $wpdb;
        $table = $wpdb->prefix . Config::TABLE_QUEUE_LOG;

        $metrics = [];

        // Completed items
        $completed = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$table} WHERE DATE(logged_at) = %s AND status = 'completed'",
            $date
        ));
        $metrics['queue_completed'] = (float) ($completed ?? 0);

        // Failed items
        $failed = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$table} WHERE DATE(logged_at) = %s AND status = 'dlq'",
            $date
        ));
        $metrics['queue_failed'] = (float) ($failed ?? 0);

        return $metrics;
    }

    /**
     * Get CRM sync rate for the day
     */
    private static function get_crm_sync_rate(string $date): float {
        $crm = CRMMonitor::instance();
        $stats = $crm->get_stats();

        if (!isset($stats['success_rate'])) {
            return 0.0;
        }

        return (float) $stats['success_rate'];
    }

    /**
     * Store aggregated metric in analytics_daily table
     */
    private static function store_metric(
        string $date,
        string $metric_type,
        ?string $form_id,
        float $value,
        ?array $metadata = null
    ): bool {
        global $wpdb;
        $table = $wpdb->prefix . Config::TABLE_ANALYTICS_DAILY;

        $data = [
            'date'         => $date,
            'metric_type'  => $metric_type,
            'form_id'      => $form_id,
            'value'        => $value,
            'metadata'     => $metadata ? wp_json_encode($metadata) : null,
        ];

        $formats = ['%s', '%s', '%s', '%f', '%s'];

        // Try to insert, update if exists
        $wpdb->query($wpdb->prepare(
            "INSERT INTO {$table} (date, metric_type, form_id, value, metadata, created_at) 
             VALUES (%s, %s, %s, %f, %s, NOW())
             ON DUPLICATE KEY UPDATE value = VALUES(value), updated_at = NOW()",
            $date, $metric_type, $form_id, $value, $metadata ? wp_json_encode($metadata) : null
        ));

        return true;
    }

    /**
     * Prune old analytics records (keep last 90 days)
     */
    public static function prune_old_records(int $days = 90): int {
        global $wpdb;
        $table = $wpdb->prefix . Config::TABLE_ANALYTICS_DAILY;

        $cutoff_date = gmdate('Y-m-d', time() - ($days * DAY_IN_SECONDS));

        $result = $wpdb->query($wpdb->prepare(
            "DELETE FROM {$table} WHERE date < %s",
            $cutoff_date
        ));

        return $result ?? 0;
    }
}
