<?php
/**
 * Service Health Check – Verify External Service Availability
 *
 * Pre-flight checks before attempting operations on external services
 * Supports: SMTP, CRM endpoints, Webhook servers
 *
 * @package ContactInbox
 */

namespace ContactInbox\Core;

use ContactInbox\Traits\Singleton;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class ServiceHealthCheck {
    use Singleton;

    const HEALTH_CHECK_INTERVAL = 300; // 5 minutes
    const HEALTH_CHECK_OPTION = 'contactin_health_checks';

    /**
     * Check SMTP service health
     *
     * @return array Health status
     */
    public static function check_smtp(): array {
        try {
            $settings = Settings::get_settings();

            // Check if SMTP is configured
            if (empty($settings['smtp_host']) || empty($settings['smtp_port'])) {
                return [
                    'available' => false,
                    'reason' => 'SMTP not configured',
                    'timestamp' => current_time('mysql'),
                ];
            }

            // Try to connect to SMTP server
            $host = $settings['smtp_host'];
            $port = intval($settings['smtp_port']);
            $timeout = 5;

            $connection = @fsockopen($host, $port, $errno, $errstr, $timeout);

            if ($connection === false) {
                return [
                    'available' => false,
                    'reason' => "Cannot connect to {$host}:{$port} ({$errstr})",
                    'timestamp' => current_time('mysql'),
                ];
            }

            fclose($connection);

            return [
                'available' => true,
                'reason' => 'SMTP server responding',
                'timestamp' => current_time('mysql'),
            ];
        } catch (\Throwable $e) {
            return [
                'available' => false,
                'reason' => $e->getMessage(),
                'timestamp' => current_time('mysql'),
            ];
        }
    }

    /**
     * Check CRM endpoint health
     *
     * @return array Health status
     */
    public static function check_crm(): array {
        try {
            $crm_settings = get_option('contactin_crm_settings', []);

            // Check if CRM is configured
            if (empty($crm_settings['enabled']) || empty($crm_settings['endpoint'])) {
                return [
                    'available' => false,
                    'reason' => 'CRM not configured',
                    'timestamp' => current_time('mysql'),
                ];
            }

            $endpoint = $crm_settings['endpoint'];

            // HEAD request to check endpoint availability (lightweight)
            $response = wp_remote_head(
                $endpoint,
                [
                    'timeout' => 5,
                    'sslverify' => apply_filters('https_local_ssl_verify', false),
                ]
            );

            if (is_wp_error($response)) {
                return [
                    'available' => false,
                    'reason' => "CRM endpoint unreachable: " . $response->get_error_message(),
                    'timestamp' => current_time('mysql'),
                ];
            }

            $code = wp_remote_retrieve_response_code($response);

            if ($code >= 400) {
                return [
                    'available' => false,
                    'reason' => "CRM endpoint returned HTTP {$code}",
                    'timestamp' => current_time('mysql'),
                ];
            }

            return [
                'available' => true,
                'reason' => "CRM endpoint responding (HTTP {$code})",
                'timestamp' => current_time('mysql'),
            ];
        } catch (\Throwable $e) {
            return [
                'available' => false,
                'reason' => $e->getMessage(),
                'timestamp' => current_time('mysql'),
            ];
        }
    }

    /**
     * Check webhook endpoint health (if configured)
     *
     * @return array Health status
     */
    public static function check_webhooks(): array {
        try {
            $settings = get_option('contactin_webhook_settings', []);

            if (empty($settings['enabled']) || empty($settings['url'])) {
                return [
                    'available' => false,
                    'reason' => 'Webhooks not configured',
                    'timestamp' => current_time('mysql'),
                ];
            }

            $endpoint = $settings['url'];

            // HEAD request to check endpoint
            $response = wp_remote_head(
                $endpoint,
                [
                    'timeout' => 5,
                    'sslverify' => apply_filters('https_local_ssl_verify', false),
                ]
            );

            if (is_wp_error($response)) {
                return [
                    'available' => false,
                    'reason' => "Webhook endpoint unreachable: " . $response->get_error_message(),
                    'timestamp' => current_time('mysql'),
                ];
            }

            $code = wp_remote_retrieve_response_code($response);

            if ($code >= 400) {
                return [
                    'available' => false,
                    'reason' => "Webhook endpoint returned HTTP {$code}",
                    'timestamp' => current_time('mysql'),
                ];
            }

            return [
                'available' => true,
                'reason' => "Webhook endpoint responding (HTTP {$code})",
                'timestamp' => current_time('mysql'),
            ];
        } catch (\Throwable $e) {
            return [
                'available' => false,
                'reason' => $e->getMessage(),
                'timestamp' => current_time('mysql'),
            ];
        }
    }

    /**
     * Run all health checks (cached)
     *
     * @return array All service health statuses
     */
    public static function check_all(): array {
        try {
            $checks = get_transient(self::HEALTH_CHECK_OPTION);

            if (is_array($checks) && !empty($checks['timestamp'])) {
                $age = current_time('timestamp') - strtotime($checks['timestamp']);
                if ($age < self::HEALTH_CHECK_INTERVAL) {
                    return $checks; // Use cached results
                }
            }

            $checks = [
                'smtp' => self::check_smtp(),
                'crm' => self::check_crm(),
                'webhooks' => self::check_webhooks(),
                'timestamp' => current_time('mysql'),
            ];

            set_transient(self::HEALTH_CHECK_OPTION, $checks, self::HEALTH_CHECK_INTERVAL);

            return $checks;
        } catch (\Throwable $e) {
            Logger::error('Failed to run health checks', [
                'error' => $e->getMessage(),
            ]);
            return [];
        }
    }

    /**
     * Clear cached health checks (force refresh)
     */
    public static function clear_cache(): void {
        delete_transient(self::HEALTH_CHECK_OPTION);
    }
}
