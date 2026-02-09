<?php
declare(strict_types=1);

namespace ContactInbox\Core;

use ContactInbox\Core\Config;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Error Classifier Service
 *
 * Classifies errors into types (AUTH, VALIDATION, TIMEOUT, RATE_LIMIT, SERVER_ERROR, etc.)
 * and determines if they are retriable based on error characteristics.
 *
 * Used by queue processors to decide between retrying vs marking as non-retriable DLQ.
 *
 * @package ContactInbox\Core
 */
final class ErrorClassifier {

    /**
     * Error type constants
     */
    public const AUTH = 'AUTH';
    public const VALIDATION = 'VALIDATION';
    public const FIELD_MAPPING = 'FIELD_MAPPING';
    public const RATE_LIMIT = 'RATE_LIMIT';
    public const TIMEOUT = 'TIMEOUT';
    public const SERVER_ERROR = 'SERVER_ERROR';
    public const NETWORK_ERROR = 'NETWORK_ERROR';
    public const UNKNOWN = 'UNKNOWN';

    /**
     * Non-retriable error types - errors that should not be retried
     */
    private const NON_RETRIABLE = [
        self::AUTH,
        self::VALIDATION,
        self::FIELD_MAPPING,
    ];

    /**
     * Classify error based on exception message and HTTP code
     *
     * @param \Throwable $exception The exception thrown
     * @param int $http_code HTTP response code (if available)
     * @return string Error type constant
     */
    public static function classify(\Throwable $exception, int $http_code = 0): string {
        // If HTTP code provided, use it
        if ($http_code > 0) {
            return self::classify_by_http($http_code);
        }

        // Otherwise, analyze exception message
        return self::classify_by_exception($exception);
    }

    /**
     * Classify error based on HTTP status code
     *
     * @param int $http_code HTTP response code
     * @return string Error type constant
     */
    public static function classify_by_http(int $http_code): string {
        return match ($http_code) {
            401, 403            => self::AUTH,
            429                 => self::RATE_LIMIT,
            400                 => self::VALIDATION,
            408, 504            => self::TIMEOUT,
            default             => $http_code >= 500 ? self::SERVER_ERROR : self::UNKNOWN,
        };
    }

    /**
     * Classify error based on exception message
     *
     * @param \Throwable $exception The exception to classify
     * @return string Error type constant
     */
    public static function classify_by_exception(\Throwable $exception): string {
        $message = strtolower($exception->getMessage());

        // Auth errors
        if (self::contains_any($message, ['unauthorized', 'invalid token', 'expired', 'forbidden', '401', '403'])) {
            return self::AUTH;
        }

        // Validation errors
        if (self::contains_any($message, ['required', 'invalid', 'missing field', 'validation', 'bad request', '400'])) {
            return self::VALIDATION;
        }

        // Field mapping errors (data structure issues)
        if (self::contains_any($message, ['field mapping', 'no such column', 'unknown attribute', 'field does not exist'])) {
            return self::FIELD_MAPPING;
        }

        // Rate limiting
        if (self::contains_any($message, ['rate limit', 'too many requests', '429', 'throttle'])) {
            return self::RATE_LIMIT;
        }

        // Timeout errors
        if (self::contains_any($message, ['timeout', 'time out', 'timed out', 'connection timeout', '408', '504'])) {
            return self::TIMEOUT;
        }

        // Server errors
        if (self::contains_any($message, ['server error', '500', '502', '503', 'service unavailable', 'bad gateway'])) {
            return self::SERVER_ERROR;
        }

        // Network errors
        if (self::contains_any($message, ['connection failed', 'network error', 'dns', 'unreachable', 'refused'])) {
            return self::NETWORK_ERROR;
        }

        return self::UNKNOWN;
    }

    /**
     * Determine if error is retriable
     *
     * Non-retriable errors should not be retried, even after fast retries.
     * Retriable errors can be safely retried with exponential backoff.
     *
     * @param string $error_type Error type constant
     * @return bool True if error should be retried, false if non-retriable
     */
    public static function is_retriable(string $error_type): bool {
        return !in_array($error_type, self::NON_RETRIABLE, true);
    }

    /**
     * Get human-readable description of error type
     *
     * @param string $error_type Error type constant
     * @return string Readable error type description
     */
    public static function get_description(string $error_type): string {
        return match ($error_type) {
            self::AUTH          => __('Authentication failed - check API credentials or token', Config::TEXTDOMAIN),
            self::VALIDATION    => __('Validation error - invalid data or required fields missing', Config::TEXTDOMAIN),
            self::FIELD_MAPPING => __('Field mapping error - data structure mismatch', Config::TEXTDOMAIN),
            self::RATE_LIMIT    => __('Rate limited - API quota exceeded, will retry later', Config::TEXTDOMAIN),
            self::TIMEOUT       => __('Timeout - request took too long, will retry', Config::TEXTDOMAIN),
            self::SERVER_ERROR  => __('Server error - CRM service issue, will retry', Config::TEXTDOMAIN),
            self::NETWORK_ERROR => __('Network error - connection failed, will retry', Config::TEXTDOMAIN),
            default             => __('Unknown error type', Config::TEXTDOMAIN),
        };
    }

    /**
     * Helper: Check if message contains any of the given strings
     *
     * @param string $message Message to search
     * @param array $keywords Keywords to search for
     * @return bool True if any keyword found
     */
    private static function contains_any(string $message, array $keywords): bool {
        foreach ($keywords as $keyword) {
            if (strpos($message, $keyword) !== false) {
                return true;
            }
        }
        return false;
    }
}
