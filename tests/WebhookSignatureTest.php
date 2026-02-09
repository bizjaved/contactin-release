<?php
/**
 * WebhookSignature Test
 *
 * Validates HMAC signing and replay protection.
 */

declare(strict_types=1);

use ContactInbox\Core\WebhookSignature;

if (!defined('ABSPATH')) {
    exit;
}

final class WebhookSignatureTest {

    public static function run_tests(): void {
        echo "\n=== WebhookSignature Tests ===\n";

        self::test_sign_and_verify();
        self::test_timestamp_freshness();
        self::test_replay_detection();

        echo "\n✓ All WebhookSignature tests passed!\n";
    }

    private static function test_sign_and_verify(): void {
        echo "Testing sign and verify...\n";
        $payload = '{"message":"hello"}';
        $secret = 'test_secret';
        $timestamp = time();

        $signature = WebhookSignature::sign($payload, $secret, $timestamp);
        $valid = WebhookSignature::verify($payload, $secret, 'sha256=' . $signature, $timestamp);
        assert($valid === true, 'Signature should validate with timestamp');

        $legacy = WebhookSignature::sign($payload, $secret);
        $valid_legacy = WebhookSignature::verify($payload, $secret, 'sha256=' . $legacy, null);
        assert($valid_legacy === true, 'Signature should validate without timestamp');

        echo "  ✓ Signature verification passed\n";
    }

    private static function test_timestamp_freshness(): void {
        echo "Testing timestamp freshness...\n";
        $fresh = WebhookSignature::is_fresh(time());
        assert($fresh === true, 'Current timestamp should be fresh');
        echo "  ✓ Timestamp freshness passed\n";
    }

    private static function test_replay_detection(): void {
        echo "Testing replay detection...\n";
        $sig = 'abc123';
        $ts = time();

        $first = WebhookSignature::is_replay($sig, $ts);
        $second = WebhookSignature::is_replay($sig, $ts);

        assert($first === false, 'First use should not be replay');
        assert($second === true, 'Second use should be replay');

        echo "  ✓ Replay detection passed\n";
    }
}

// Run tests if executed directly
if (basename(__FILE__) === basename($_SERVER['PHP_SELF'] ?? '')) {
    WebhookSignatureTest::run_tests();
}
