<?php
/**
 * Receipt Token Service
 *
 * Manages submission receipt tokens for user tracking and confirmation.
 * Provides secure token generation and validation.
 *
 * @package ContactInbox\Core
 */

declare(strict_types=1);

namespace ContactInbox\Core;

use ContactInbox\Traits\Singleton;

if (!defined('ABSPATH')) exit;

final class ReceiptTokenService {
    use Singleton;

    private const TOKEN_LENGTH = 32;
    private const EXPIRY_DAYS = 30;

    /**
     * Generate a unique receipt token
     *
     * @return string 64-character hex string
     */
    public static function generate(): string {
        return bin2hex(random_bytes(self::TOKEN_LENGTH));
    }

    /**
     * Validate a receipt token format
     *
     * @param string $token Token to validate
     * @return bool True if valid format
     */
    public static function validate_format(string $token): bool {
        // Must be 64 hex characters (32 bytes * 2)
        return preg_match('/^[a-f0-9]{64}$/i', $token) === 1;
    }

    /**
     * Get submission info from receipt token
     *
     * @param string $token Receipt token
     * @return array|null Submission info or null
     */
    public static function get_submission(string $token): ?array {
        if (!self::validate_format($token)) {
            return null;
        }

        $repo = new Repositories\SubmissionRepository();
        return $repo->get_receipt_info($token);
    }

    /**
     * Get expiry timestamp for tokens
     *
     * @return int Unix timestamp
     */
    public static function get_expiry_timestamp(): int {
        return strtotime('+' . self::EXPIRY_DAYS . ' days');
    }

    /**
     * Format receipt token for display (show partial for security)
     *
     * @param string $token Full token
     * @param int $visible_chars Number of characters to show
     * @return string Masked token (e.g., "abc123****...****def456")
     */
    public static function mask_token(string $token, int $visible_chars = 6): string {
        $visible = min($visible_chars, strlen($token) / 2);
        $start = substr($token, 0, $visible);
        $end = substr($token, -$visible);
        return $start . '****...****' . $end;
    }
}
