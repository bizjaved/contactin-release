<?php
declare(strict_types=1);

namespace ContactInbox\Core;

use ContactInbox\Core\Config;
use ContactInbox\Core\ErrorClassifier;
use ContactInbox\Core\Logger;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Alert Generator Service
 *
 * Emits actionable alerts for different failure scenarios based on error type,
 * severity, and admin preferences. Supports digest batching and multi-channel delivery.
 *
 * Alert Types:
 * - crm_auth_failure: Auth errors (non-retriable)
 * - crm_validation_failure: Validation errors (non-retriable)
 * - crm_rate_limited: Rate limit hit (retriable)
 * - crm_service_error: Server errors (retriable)
 * - email_delivery_failure: Email send failures
 * - circuit_breaker_trip: Service circuit breaker opened
 * - dlq_item_final: Item moved to DLQ (non-retriable)
 *
 * @package ContactInbox\Core
 */
final class AlertGenerator {

    /**
     * Alert type constants
     */
    public const CRM_AUTH_FAILURE = 'crm_auth_failure';
    public const CRM_VALIDATION_FAILURE = 'crm_validation_failure';
    public const CRM_FIELD_MAPPING_FAILURE = 'crm_field_mapping_failure';
    public const CRM_RATE_LIMITED = 'crm_rate_limited';
    public const CRM_SERVICE_ERROR = 'crm_service_error';
    public const EMAIL_DELIVERY_FAILURE = 'email_delivery_failure';
    public const CIRCUIT_BREAKER_TRIP = 'circuit_breaker_trip';
    public const DLQ_ITEM_FINAL = 'dlq_item_final';

    /**
     * Emit alert for CRM sync failure
     *
     * @param string $error_type Error classification (from ErrorClassifier)
     * @param string $error_message Detailed error message
     * @param int $message_id Associated message ID
     * @param array $context Additional context (http_code, retry_count, etc.)
     * @return void
     */
    public static function alert_crm_failure(
        string $error_type,
        string $error_message,
        int $message_id = 0,
        array $context = []
    ): void {
        // Map error type to alert type and severity
        $alert_type = self::error_type_to_alert_type($error_type);
        $severity = self::error_type_to_severity($error_type);

        $is_retriable = ErrorClassifier::is_retriable($error_type);
        $description = ErrorClassifier::get_description($error_type);

        $message = sprintf(
            __('CRM sync failed: %s. Error: %s%s', 'contact-inbox'),
            $description,
            substr($error_message, 0, 200),
            $message_id > 0 ? " (Message ID: {$message_id})" : ''
        );

        // Non-retriable errors require immediate attention
        if (!$is_retriable) {
            $severity = 'critical';
            $message .= __(' [Non-retriable - will not retry]', 'contact-inbox');
        }

        self::emit_alert($alert_type, $message, $severity, [
            'message_id' => $message_id,
            'error_type' => $error_type,
            'error_message' => $error_message,
            'retriable' => $is_retriable,
            ...array_slice($context, 0, 5), // Limit context size
        ]);
    }

    /**
     * Emit alert for email delivery failure
     *
     * @param string $error_message Error message
     * @param int $message_id Associated message ID
     * @param string $recipient Email recipient
     * @return void
     */
    public static function alert_email_failure(
        string $error_message,
        int $message_id = 0,
        string $recipient = ''
    ): void {
        $message = sprintf(
            __('Email delivery failed: %s%s%s', 'contact-inbox'),
            substr($error_message, 0, 150),
            $message_id > 0 ? " (Message ID: {$message_id})" : '',
            !empty($recipient) ? " → {$recipient}" : ''
        );

        self::emit_alert(
            self::EMAIL_DELIVERY_FAILURE,
            $message,
            'warning',
            [
                'message_id' => $message_id,
                'recipient' => $recipient,
                'error' => $error_message,
            ]
        );
    }

    /**
     * Emit alert for circuit breaker trip
     *
     * @param string $service Service name (smtp, crm)
     * @param string $reason Trip reason
     * @return void
     */
    public static function alert_circuit_trip(string $service, string $reason): void {
        $service_label = strtoupper($service);
        $message = sprintf(
            __('Circuit breaker tripped for %s: %s - service will be temporarily unavailable', 'contact-inbox'),
            $service_label,
            $reason
        );

        self::emit_alert(
            self::CIRCUIT_BREAKER_TRIP,
            $message,
            'critical',
            [
                'service' => $service,
                'reason' => $reason,
            ]
        );
    }

    /**
     * Emit alert for DLQ item (permanent failure)
     *
     * @param string $type Queue item type (crm, email, etc.)
     * @param string $error_message Error that caused DLQ
     * @param int $message_id Associated message ID
     * @param string $dlq_reason DLQ classification
     * @return void
     */
    public static function alert_dlq_item(
        string $type,
        string $error_message,
        int $message_id = 0,
        string $dlq_reason = ''
    ): void {
        $type_label = ucfirst($type);
        $message = sprintf(
            __('%s item permanently failed and moved to dead letter queue: %s%s', 'contact-inbox'),
            $type_label,
            substr($dlq_reason ?: $error_message, 0, 150),
            $message_id > 0 ? " (Message ID: {$message_id})" : ''
        );

        self::emit_alert(
            self::DLQ_ITEM_FINAL,
            $message,
            'critical',
            [
                'type' => $type,
                'message_id' => $message_id,
                'dlq_reason' => $dlq_reason,
            ]
        );
    }

    /**
     * Core alert emission function
     *
     * Routes to AlertSystem and logs to database.
     * Respects admin notification preferences.
     *
     * @param string $alert_type Alert type constant
     * @param string $message Alert message
     * @param string $severity Severity level (info, warning, critical)
     * @param array $context Alert context for tracking
     * @return void
     */
    private static function emit_alert(
        string $alert_type,
        string $message,
        string $severity = 'warning',
        array $context = []
    ): void {
        // Check if alerts are enabled
        $alerts_enabled = get_option('contactin_alerts_enabled', true);
        if (!$alerts_enabled) {
            Logger::debug('Alerts disabled, skipping emission', ['type' => $alert_type]);
            return;
        }

        // Check if this alert type is enabled
        $disabled_types = get_option('contactin_disabled_alert_types', []);
        if (in_array($alert_type, (array)$disabled_types, true)) {
            Logger::debug('Alert type disabled, skipping', ['type' => $alert_type]);
            return;
        }

        try {
            // Emit via AlertSystem
            AlertSystem::trigger_alert($alert_type, $message, $severity);

            Logger::info('Alert emitted', [
                'type' => $alert_type,
                'severity' => $severity,
                'context' => $context,
            ]);
        } catch (\Throwable $e) {
            Logger::error('Failed to emit alert', [
                'type' => $alert_type,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Map error type to alert type
     *
     * @param string $error_type Error type constant
     * @return string Alert type constant
     */
    private static function error_type_to_alert_type(string $error_type): string {
        return match ($error_type) {
            ErrorClassifier::AUTH          => self::CRM_AUTH_FAILURE,
            ErrorClassifier::VALIDATION    => self::CRM_VALIDATION_FAILURE,
            ErrorClassifier::FIELD_MAPPING => self::CRM_FIELD_MAPPING_FAILURE,
            ErrorClassifier::RATE_LIMIT    => self::CRM_RATE_LIMITED,
            ErrorClassifier::SERVER_ERROR  => self::CRM_SERVICE_ERROR,
            default                         => self::CRM_SERVICE_ERROR,
        };
    }

    /**
     * Map error type to severity level
     *
     * @param string $error_type Error type constant
     * @return string Severity (info, warning, critical)
     */
    private static function error_type_to_severity(string $error_type): string {
        // Non-retriable errors are critical
        if (!ErrorClassifier::is_retriable($error_type)) {
            return 'critical';
        }

        // Retriable errors are warnings
        if ($error_type === ErrorClassifier::RATE_LIMIT) {
            return 'warning'; // Rate limits are expected, just informational
        }

        return 'warning';
    }
}
