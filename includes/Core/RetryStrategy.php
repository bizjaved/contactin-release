<?php
declare(strict_types=1);

namespace ContactInbox\Core;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Retry Strategy
 *
 * Provides adaptive backoff delays with jitter based on error type.
 * Used by QueueManager to schedule next retry attempts.
 *
 * @package ContactIn\Core
 */
final class RetryStrategy {

    /**
     * Base delays in seconds for retry attempts (index = retry_count)
     */
    private const BASE_DELAYS = [1, 4, 16, 64];

    /**
     * Max jitter percentage applied to delay
     */
    private const JITTER_PERCENT = 0.3; // +/- 30%

    /**
     * Get next retry delay in seconds based on error type and retry count
     *
     * @param int $retry_count Current retry count (0-based)
     * @param string $error_type Error classification (RATE_LIMIT, TIMEOUT, SERVER_ERROR, etc.)
     * @return int Delay in seconds
     */
    public static function get_delay(int $retry_count, string $error_type = ''): int {
        $base = self::BASE_DELAYS[$retry_count] ?? end(self::BASE_DELAYS);

        // Apply error-type specific multipliers
        $multiplier = match ($error_type) {
            ErrorClassifier::RATE_LIMIT => 4.0,
            ErrorClassifier::TIMEOUT => 2.0,
            ErrorClassifier::SERVER_ERROR => 2.0,
            ErrorClassifier::NETWORK_ERROR => 1.5,
            default => 1.0,
        };

        $delay = (int)round($base * $multiplier);

        return max(1, self::apply_jitter($delay));
    }

    /**
     * Apply jitter to delay to avoid thundering herd
     *
     * @param int $delay Base delay in seconds
     * @return int Jittered delay in seconds
     */
    private static function apply_jitter(int $delay): int {
        $jitter = (int)round($delay * self::JITTER_PERCENT);
        if ($jitter <= 0) {
            return $delay;
        }

        $min = max(1, $delay - $jitter);
        $max = $delay + $jitter;

        try {
            return random_int($min, $max);
        } catch (\Throwable $e) {
            return $delay;
        }
    }
}
