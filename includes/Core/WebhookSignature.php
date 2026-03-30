<?php
declare(strict_types=1);

namespace ContactInbox\Core;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Webhook Signature Helper
 *
 * Builds and verifies HMAC signatures with optional timestamp support and
 * replay protection via transient storage.
 *
 * @package ContactIn\Core
 */
final class WebhookSignature {

    private const MAX_AGE_SECONDS = 300; // 5 minutes
    private const REPLAY_TTL_SECONDS = 600; // 10 minutes

    /**
     * Build a hex-encoded HMAC-SHA256 signature.
     *
     * @param string $payload Raw request body
     * @param string $secret  Shared secret
     * @param int|null $timestamp Optional unix timestamp
     * @return string
     */
    public static function sign(string $payload, string $secret, ?int $timestamp = null): string {
        $base = $timestamp ? $timestamp . '.' . $payload : $payload;
        return hash_hmac('sha256', $base, $secret);
    }

    /**
     * Verify an incoming signature.
     *
     * @param string $payload Raw request body
     * @param string $secret Shared secret
     * @param string $signature Incoming signature (hex, optional sha256= prefix)
     * @param int|null $timestamp Optional unix timestamp
     * @return bool
     */
    public static function verify(string $payload, string $secret, string $signature, ?int $timestamp = null): bool {
        if ($signature === '') {
            return false;
        }

        $normalized = strtolower(preg_replace('/^sha256=/', '', $signature));
        $expected = strtolower(self::sign($payload, $secret, $timestamp));

        return hash_equals($expected, $normalized);
    }

    /**
     * Check if timestamp is within acceptable window.
     */
    public static function is_fresh(?int $timestamp): bool {
        if (!$timestamp) {
            return false;
        }
        $now = current_time('timestamp');
        return abs($now - $timestamp) <= self::MAX_AGE_SECONDS;
    }

    /**
     * Detect and record replayed signatures using a transient cache.
     */
    public static function is_replay(string $signature, int $timestamp): bool {
        $key = 'contactin_webhook_sig_' . md5($signature . '|' . $timestamp);
        if (get_transient($key)) {
            return true;
        }
        set_transient($key, 1, self::REPLAY_TTL_SECONDS);
        return false;
    }
}
