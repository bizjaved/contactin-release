<?php
/**
 * Unit Test: CRM Queue Payload Builder
 *
 * Verifies CRM queue payload mapping from Message entities.
 */

namespace ContactInbox\Tests;

use ContactInbox\Core\CRMQueuePayloadBuilder;
use ContactInbox\Core\Message;

if (!defined('ABSPATH')) {
    define('ABSPATH', __DIR__ . '/');
}

class CRMQueuePayloadBuilderTest {
    public static function test_basic_payload(): void {
        echo "\n=== CRM Queue Payload Builder: Basic Payload ===\n";

        $row = (object) [
            'id' => 123,
            'salutation' => 'Mr.',
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'message' => 'Hello',
            'subject' => 'Test',
            'phone' => '+1-555-0000',
            'intent_category' => 'support',
            'intent_confidence' => 0.88,
            'attachment' => null,
        ];

        $message = new Message($row);
        $payload = CRMQueuePayloadBuilder::build_from_message($message);

        echo "Payload:\n" . json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";

        $checks = [
            'message_id' => 123,
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'subject' => 'Test',
            'phone' => '+1-555-0000',
            'intent_category' => 'support',
        ];

        foreach ($checks as $key => $expected) {
            if (($payload[$key] ?? null) === $expected) {
                echo "✓ {$key} mapped correctly\n";
            } else {
                echo "✗ {$key} mismatch. Expected: {$expected}\n";
            }
        }

        if (!isset($payload['attachment'])) {
            echo "✓ attachment omitted when empty\n";
        } else {
            echo "✗ attachment should be omitted when empty\n";
        }
    }

    public static function run_all_tests(): void {
        echo "\n" . str_repeat('=', 50) . "\n";
        echo "CRM QUEUE PAYLOAD BUILDER TESTS\n";
        echo str_repeat('=', 50) . "\n";

        self::test_basic_payload();

        echo "\n" . str_repeat('=', 50) . "\n";
        echo "Tests completed. Check output above for any ✗ marks.\n";
    }
}

CRMQueuePayloadBuilderTest::run_all_tests();
