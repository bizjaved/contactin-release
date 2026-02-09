<?php
/**
 * AlertGenerator Test
 *
 * Tests alert generation for different error types and failure scenarios.
 */

declare(strict_types=1);

use ContactInbox\Core\AlertGenerator;
use ContactInbox\Core\ErrorClassifier;

if (!defined('ABSPATH')) {
    exit;
}

final class AlertGeneratorTest {

    public static function run_tests(): void {
        echo "\n=== AlertGenerator Tests ===\n";
        
        self::test_crm_failure_alerts();
        self::test_email_failure_alert();
        self::test_circuit_breaker_alert();
        self::test_dlq_item_alert();
        
        echo "\n✓ All AlertGenerator tests passed!\n";
    }

    private static function test_crm_failure_alerts(): void {
        echo "Testing CRM failure alerts...\n";
        
        // Test that we can generate alerts for different error types without exceptions
        $error_types = [
            ErrorClassifier::AUTH,
            ErrorClassifier::VALIDATION,
            ErrorClassifier::FIELD_MAPPING,
            ErrorClassifier::RATE_LIMIT,
            ErrorClassifier::TIMEOUT,
            ErrorClassifier::SERVER_ERROR,
            ErrorClassifier::NETWORK_ERROR,
        ];
        
        foreach ($error_types as $type) {
            try {
                AlertGenerator::alert_crm_failure($type, "Test error: {$type}", 123, ['test' => true]);
                echo "  ✓ Alert for {$type} generated successfully\n";
            } catch (\Throwable $e) {
                echo "  ✗ FAILED to generate alert for {$type}: {$e->getMessage()}\n";
                throw $e;
            }
        }
    }

    private static function test_email_failure_alert(): void {
        echo "Testing email failure alert...\n";
        
        try {
            AlertGenerator::alert_email_failure(
                'SMTP connection timeout',
                456,
                'admin@example.com'
            );
            echo "  ✓ Email failure alert generated successfully\n";
        } catch (\Throwable $e) {
            echo "  ✗ FAILED: {$e->getMessage()}\n";
            throw $e;
        }
    }

    private static function test_circuit_breaker_alert(): void {
        echo "Testing circuit breaker trip alert...\n";
        
        try {
            AlertGenerator::alert_circuit_trip('crm', 'Too many failures in last 5 minutes');
            echo "  ✓ Circuit breaker alert generated successfully\n";
        } catch (\Throwable $e) {
            echo "  ✗ FAILED: {$e->getMessage()}\n";
            throw $e;
        }
    }

    private static function test_dlq_item_alert(): void {
        echo "Testing DLQ item alert...\n";
        
        try {
            AlertGenerator::alert_dlq_item(
                'crm',
                'Invalid API credentials',
                789,
                '[AUTH] The provided access token is invalid or expired'
            );
            echo "  ✓ DLQ item alert generated successfully\n";
        } catch (\Throwable $e) {
            echo "  ✗ FAILED: {$e->getMessage()}\n";
            throw $e;
        }
    }
}

// Run tests if executed directly
if (basename(__FILE__) === basename($_SERVER['PHP_SELF'] ?? '')) {
    AlertGeneratorTest::run_tests();
}
