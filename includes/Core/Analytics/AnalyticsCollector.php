<?php
/**
 * Analytics Collector – Real-time Event Tracking
 *
 * Tracks user interactions: form views, submissions, conversions.
 * Captures device type, operating system, browser, geographic location,
 * and traffic source (UTM parameters, referrer).
 *
 * @package ContactInbox\Core\Analytics
 * @since   1.7.0
 */

declare(strict_types=1);

namespace ContactInbox\Core\Analytics;

use ContactInbox\Core\Config;
use ContactInbox\Core\Logger;

if (!defined('ABSPATH')) {
    exit;
}

final class AnalyticsCollector {

    private static function get_server_text(string $key): string {
        $value = filter_input(INPUT_SERVER, $key, FILTER_UNSAFE_RAW);
        if (null === $value || false === $value) {
            return '';
        }
        return sanitize_text_field(wp_unslash((string) $value));
    }

    private static function get_query_text(string $key): ?string {
        $value = filter_input(INPUT_GET, $key, FILTER_UNSAFE_RAW);
        if (null === $value || false === $value) {
            return null;
        }
        $sanitized = sanitize_text_field(wp_unslash((string) $value));
        return '' === $sanitized ? null : $sanitized;
    }

    /**
     * Track a form view event
     *
     * @param string $form_id Form identifier
     * @param array $event_data Optional additional event data
     */
    public static function track_form_view(string $form_id, array $event_data = []): void {
        try {
            self::store_event('form_view', null, $form_id, $event_data);
        } catch (\Exception $e) {
            Logger::debug('Form view tracking failed: ' . $e->getMessage());
        }
    }

    /**
     * Track a form submission event
     *
     * @param string $form_id Form identifier
     * @param int $submission_id Message ID if available
     * @param array $event_data Optional additional event data
     */
    public static function track_form_submission(string $form_id, ?int $submission_id = null, array $event_data = []): void {
        try {
            self::store_event('form_submission', $submission_id, $form_id, $event_data);
        } catch (\Exception $e) {
            Logger::debug('Form submission tracking failed: ' . $e->getMessage());
        }
    }

    /**
     * Track a form conversion (successful submission)
     *
     * @param string $form_id Form identifier
     * @param int $submission_id Message ID
     * @param array $event_data Optional additional event data
     */
    public static function track_form_conversion(string $form_id, int $submission_id, array $event_data = []): void {
        try {
            self::store_event('form_conversion', $submission_id, $form_id, $event_data);
        } catch (\Exception $e) {
            Logger::debug('Form conversion tracking failed: ' . $e->getMessage());
        }
    }

    /**
     * Store event in analytics_events table
     *
     * @param string $event_type Type of event
     * @param int|null $submission_id Associated submission ID
     * @param string $form_id Form ID
     * @param array $additional_data Additional event data to store as JSON
     */
    private static function store_event(
        string $event_type,
        ?int $submission_id,
        string $form_id,
        array $additional_data = []
    ): bool {
        global $wpdb;
        $table = $wpdb->prefix . Config::TABLE_ANALYTICS_EVENTS;

        $user_ip = self::get_client_ip();
        $device_info = self::detect_device();
        $location = self::get_location_from_ip($user_ip);
        $utm = self::get_utm_parameters();
        $referrer = self::get_server_text('HTTP_REFERER');

        $data = [
            'event_type'     => $event_type,
            'submission_id'  => $submission_id,
            'form_id'        => $form_id,
            'user_ip'        => $user_ip,
            'device_type'    => $device_info['device_type'],
            'device_os'      => $device_info['os'],
            'browser'        => $device_info['browser'],
            'country'        => $location['country'],
            'utm_source'     => $utm['source'],
            'utm_medium'     => $utm['medium'],
            'utm_campaign'   => $utm['campaign'],
            'referrer'       => $referrer,
            'event_data'     => !empty($additional_data) ? wp_json_encode($additional_data) : null,
            'created_at'     => current_time('mysql'),
        ];

        $formats = [
            '%s', '%d', '%s', '%s', '%s', '%s', '%s', '%s',
            '%s', '%s', '%s', '%s', '%s', '%s', '%s'
        ];

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
        $result = $wpdb->insert($table, $data, $formats);

        if ($result === false) {
            Logger::error('Analytics event insert failed', ['error' => $wpdb->last_error]);
        }

        return (bool)$result;
    }

    /**
     * Get client IP address
     */
    private static function get_client_ip(): string {
        $cf_ip = self::get_server_text('HTTP_CF_CONNECTING_IP');
        if ('' !== $cf_ip) {
            // Cloudflare
            return $cf_ip;
        }

        $forwarded_for = self::get_server_text('HTTP_X_FORWARDED_FOR');
        if ('' !== $forwarded_for) {
            // Proxy/load balancer
            $ips = explode(',', $forwarded_for);
            return sanitize_text_field(trim($ips[0]));
        }

        $x_forwarded = self::get_server_text('HTTP_X_FORWARDED');
        if ('' !== $x_forwarded) {
            return $x_forwarded;
        }

        $forwarded_for_alt = self::get_server_text('HTTP_FORWARDED_FOR');
        if ('' !== $forwarded_for_alt) {
            return $forwarded_for_alt;
        }

        $forwarded = self::get_server_text('HTTP_FORWARDED');
        if ('' !== $forwarded) {
            return $forwarded;
        }

        $remote_addr = self::get_server_text('REMOTE_ADDR');
        if ('' !== $remote_addr) {
            return $remote_addr;
        }

        return '';
    }

    /**
     * Detect device type, OS, and browser
     */
    private static function detect_device(): array {
        $user_agent = self::get_server_text('HTTP_USER_AGENT');

        $device_type = self::get_device_type($user_agent);
        $os = self::get_operating_system($user_agent);
        $browser = self::get_browser_name($user_agent);

        return [
            'device_type' => $device_type,
            'os'          => $os,
            'browser'     => $browser,
        ];
    }

    /**
     * Determine device type from user agent
     */
    private static function get_device_type(string $user_agent): string {
        if (stripos($user_agent, 'mobile') !== false || stripos($user_agent, 'android') !== false) {
            return 'mobile';
        }
        if (stripos($user_agent, 'tablet') !== false || stripos($user_agent, 'ipad') !== false) {
            return 'tablet';
        }
        return 'desktop';
    }

    /**
     * Extract operating system from user agent
     */
    private static function get_operating_system(string $user_agent): string {
        if (stripos($user_agent, 'Windows') !== false) {
            return 'Windows';
        } elseif (stripos($user_agent, 'Macintosh') !== false) {
            return 'macOS';
        } elseif (stripos($user_agent, 'iPhone') !== false || stripos($user_agent, 'iPad') !== false) {
            return 'iOS';
        } elseif (stripos($user_agent, 'Android') !== false) {
            return 'Android';
        } elseif (stripos($user_agent, 'Linux') !== false) {
            return 'Linux';
        }
        return 'Unknown';
    }

    /**
     * Extract browser name from user agent
     */
    private static function get_browser_name(string $user_agent): string {
        if (stripos($user_agent, 'Chrome') !== false && stripos($user_agent, 'Chromium') === false) {
            return 'Chrome';
        } elseif (stripos($user_agent, 'Safari') !== false && stripos($user_agent, 'Chrome') === false) {
            return 'Safari';
        } elseif (stripos($user_agent, 'Firefox') !== false) {
            return 'Firefox';
        } elseif (stripos($user_agent, 'Edge') !== false) {
            return 'Edge';
        } elseif (stripos($user_agent, 'Opera') !== false || stripos($user_agent, 'OPR') !== false) {
            return 'Opera';
        } elseif (stripos($user_agent, 'Trident') !== false) {
            return 'Internet Explorer';
        }
        return 'Other';
    }

    /**
     * Get country code from IP address
     * Uses WordPress built-in geolocation if available
     */
    private static function get_location_from_ip(string $ip): array {
        // For now, just return empty (can be enhanced with GeoIP2 library later)
        // WordPress.com Jetpack provides wp_geoip_get_info_by_ip if available
        if (function_exists('wp_geoip_get_info_by_ip')) {
            $info = wp_geoip_get_info_by_ip($ip);
            if (!empty($info['country_code'])) {
                return [
                    'country' => $info['country_code'],
                    'city'    => $info['city'] ?? null,
                ];
            }
        }

        return [
            'country' => null,
            'city'    => null,
        ];
    }

    /**
     * Extract UTM parameters from URL/request
     */
    private static function get_utm_parameters(): array {
        return [
            'source'   => self::get_query_text('utm_source'),
            'medium'   => self::get_query_text('utm_medium'),
            'campaign' => self::get_query_text('utm_campaign'),
        ];
    }

    /**
     * Prune old event records (keep last 90 days)
     */
    public static function prune_old_events(int $days = 90): int {
        global $wpdb;
        $table = $wpdb->prefix . Config::TABLE_ANALYTICS_EVENTS;

        $cutoff_date = wp_date('Y-m-d', time() - ($days * DAY_IN_SECONDS), new \DateTimeZone('UTC'));

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
        $result = $wpdb->query($wpdb->prepare(
            'DELETE FROM %i WHERE created_at < %s',
            $table,
            $cutoff_date . ' 00:00:00'
        ));

        return (int)($result ?? 0);
    }
}
