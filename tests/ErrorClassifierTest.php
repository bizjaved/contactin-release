<?php
/**
 * ErrorClassifier Test
 *
 * Tests error classification logic for different error types and messages.
 */

declare(strict_types=1);

use ContactInbox\Core\ErrorClassifier;

if (!defined('ABSPATH')) {
    exit;
}

final class ErrorClassifierTest {

    public static function run_tests(): void {
        echo "\n=== ErrorClassifier Tests ===\n";
        
        self::test_classify_by_http_codes();
        self::test_classify_by_exception_messages();
        self::test_is_retriable();
        self::test_error_descriptions();
        
        echo "\n✓ All ErrorClassifier tests passed!\n";
    }

    private static function test_classify_by_http_codes(): void {
        echo "Testing HTTP code classification...\n";
        
        $tests = [
            [401, ErrorClassifier::AUTH, 'HTTP 401 should classify as AUTH'],
            [403, ErrorClassifier::AUTH, 'HTTP 403 should classify as AUTH'],
            [429, ErrorClassifier::RATE_LIMIT, 'HTTP 429 should classify as RATE_LIMIT'],
            [400, ErrorClassifier::VALIDATION, 'HTTP 400 should classify as VALIDATION'],
            [408, ErrorClassifier::TIMEOUT, 'HTTP 408 should classify as TIMEOUT'],
            [504, ErrorClassifier::TIMEOUT, 'HTTP 504 should classify as TIMEOUT'],
            [500, ErrorClassifier::SERVER_ERROR, 'HTTP 500 should classify as SERVER_ERROR'],
            [502, ErrorClassifier::SERVER_ERROR, 'HTTP 502 should classify as SERVER_ERROR'],
            [999, ErrorClassifier::UNKNOWN, 'HTTP 999 should classify as UNKNOWN'],
        ];
        
        foreach ($tests as [$code, $expected, $label]) {
            $result = ErrorClassifier::classify_by_http($code);
            assert($result === $expected, "FAILED: {$label}. Got: {$result}");
            echo "  ✓ {$label}\n";
        }
    }

    private static function test_classify_by_exception_messages(): void {
        echo "Testing exception message classification...\n";
        
        $tests = [
            ['Invalid token', ErrorClassifier::AUTH],
            ['Unauthorized access', ErrorClassifier::AUTH],
            ['Missing required field', ErrorClassifier::VALIDATION],
            ['Required field cannot be empty', ErrorClassifier::VALIDATION],
            ['Field mapping error', ErrorClassifier::FIELD_MAPPING],
            ['Too many requests', ErrorClassifier::RATE_LIMIT],
            ['Request timeout', ErrorClassifier::TIMEOUT],
            ['Internal server error', ErrorClassifier::SERVER_ERROR],
            ['Service unavailable', ErrorClassifier::SERVER_ERROR],
            ['Connection refused', ErrorClassifier::NETWORK_ERROR],
            ['DNS lookup failed', ErrorClassifier::NETWORK_ERROR],
            ['Unknown error', ErrorClassifier::UNKNOWN],
        ];
        
        foreach ($tests as [$message, $expected]) {
            $exception = new \Exception($message);
            $result = ErrorClassifier::classify_by_exception($exception);
            assert($result === $expected, "FAILED: Message '{$message}' should classify as {$expected}. Got: {$result}");
            echo "  ✓ '{$message}' → {$result}\n";
        }
    }

    private static function test_is_retriable(): void {
        echo "Testing retriability classification...\n";
        
        $retriable = [
            ErrorClassifier::RATE_LIMIT,
            ErrorClassifier::TIMEOUT,
            ErrorClassifier::SERVER_ERROR,
            ErrorClassifier::NETWORK_ERROR,
            ErrorClassifier::UNKNOWN,
        ];
        
        $non_retriable = [
            ErrorClassifier::AUTH,
            ErrorClassifier::VALIDATION,
            ErrorClassifier::FIELD_MAPPING,
        ];
        
        foreach ($retriable as $type) {
            $result = ErrorClassifier::is_retriable($type);
            assert($result === true, "FAILED: {$type} should be retriable");
            echo "  ✓ {$type} is retriable\n";
        }
        
        foreach ($non_retriable as $type) {
            $result = ErrorClassifier::is_retriable($type);
            assert($result === false, "FAILED: {$type} should NOT be retriable");
            echo "  ✓ {$type} is NOT retriable\n";
        }
    }

    private static function test_error_descriptions(): void {
        echo "Testing error descriptions...\n";
        
        $types = [
            ErrorClassifier::AUTH,
            ErrorClassifier::VALIDATION,
            ErrorClassifier::FIELD_MAPPING,
            ErrorClassifier::RATE_LIMIT,
            ErrorClassifier::TIMEOUT,
            ErrorClassifier::SERVER_ERROR,
            ErrorClassifier::NETWORK_ERROR,
        ];
        
        foreach ($types as $type) {
            $description = ErrorClassifier::get_description($type);
            assert(!empty($description), "FAILED: {$type} should have description");
            assert(strlen($description) > 10, "FAILED: {$type} description too short");
            echo "  ✓ {$type}: {$description}\n";
        }
    }
}

// Run tests if executed directly
if (basename(__FILE__) === basename($_SERVER['PHP_SELF'] ?? '')) {
    ErrorClassifierTest::run_tests();
}
