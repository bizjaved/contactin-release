<?php
/**
 * RetryStrategy Test
 *
 * Validates adaptive delay logic and jitter bounds.
 */

declare(strict_types=1);

use ContactInbox\Core\RetryStrategy;
use ContactInbox\Core\ErrorClassifier;

if (!defined('ABSPATH')) {
    exit;
}

final class RetryStrategyTest {

    public static function run_tests(): void {
        echo "\n=== RetryStrategy Tests ===\n";

        self::test_base_delays();
        self::test_error_multipliers();
        self::test_jitter_bounds();

        echo "\n✓ All RetryStrategy tests passed!\n";
    }

    private static function test_base_delays(): void {
        echo "Testing base delays...\n";

        $delay0 = RetryStrategy::get_delay(0, '');
        $delay1 = RetryStrategy::get_delay(1, '');
        $delay2 = RetryStrategy::get_delay(2, '');
        $delay3 = RetryStrategy::get_delay(3, '');

        assert($delay0 > 0, 'Delay 0 should be > 0');
        assert($delay1 > 0, 'Delay 1 should be > 0');
        assert($delay2 > 0, 'Delay 2 should be > 0');
        assert($delay3 > 0, 'Delay 3 should be > 0');

        echo "  ✓ Base delays produced valid values\n";
    }

    private static function test_error_multipliers(): void {
        echo "Testing error multipliers...\n";

        $default = RetryStrategy::get_delay(1, '');
        $rate_limit = RetryStrategy::get_delay(1, ErrorClassifier::RATE_LIMIT);
        $timeout = RetryStrategy::get_delay(1, ErrorClassifier::TIMEOUT);

        assert($rate_limit >= $default, 'Rate limit delay should be >= default');
        assert($timeout >= $default, 'Timeout delay should be >= default');

        echo "  ✓ Multipliers increase delays as expected\n";
    }

    private static function test_jitter_bounds(): void {
        echo "Testing jitter bounds...\n";

        $base = RetryStrategy::get_delay(2, '');
        for ($i = 0; $i < 10; $i++) {
            $delay = RetryStrategy::get_delay(2, '');
            assert($delay > 0, 'Jittered delay should be > 0');
        }

        echo "  ✓ Jitter produced valid delays\n";
    }
}

// Run tests if executed directly
if (basename(__FILE__) === basename($_SERVER['PHP_SELF'] ?? '')) {
    RetryStrategyTest::run_tests();
}
