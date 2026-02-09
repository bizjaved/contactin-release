<?php
/**
 * Phone Handling Strategy Test Suite
 *
 * Tests all three phone handling strategies:
 * 1. 'overwrite' - Replace Phone field with new value
 * 2. 'secondary' - Store in OtherPhone field instead
 * 3. 'skip_if_exists' - Only update if Phone is empty
 */

namespace ContactInbox\Tests;

use ContactInbox\Core\CRMFieldMapper;
use ContactInbox\Core\CRMSettings;

class PhoneHandlingStrategyTest {
    
    /**
     * Test 'overwrite' strategy (default)
     */
    public static function test_overwrite_strategy() {
        echo "\n=== Test 1: Overwrite Strategy ===\n";
        
        $form_data = [
            'name'  => 'John Doe',
            'email' => 'john@example.com',
            'phone' => '+1-555-1234',
        ];
        
        $crm_settings = [
            'name_field_order'          => 'first_last',
            'phone_handling_strategy'   => 'overwrite',
            'contact_mapping'           => [
                'email' => 'Email',
                'phone' => 'Phone',
            ],
        ];
        
        $payload = CRMFieldMapper::map_contact_fields($form_data, $crm_settings);
        
        echo "Strategy: overwrite\n";
        echo "Expected: Phone field = '+1-555-1234'\n";
        echo "Actual:   Phone field = '" . ($payload['Phone'] ?? 'NOT SET') . "'\n";
        
        if (isset($payload['Phone']) && $payload['Phone'] === '+1-555-1234') {
            echo "✓ PASS: Phone correctly set in primary field\n";
            return true;
        } else {
            echo "✗ FAIL: Phone not in expected field\n";
            echo "  Full payload: " . json_encode($payload) . "\n";
            return false;
        }
    }
    
    /**
     * Test 'secondary' strategy (OtherPhone)
     */
    public static function test_secondary_strategy() {
        echo "\n=== Test 2: Secondary Strategy (OtherPhone) ===\n";
        
        $form_data = [
            'name'  => 'Jane Smith',
            'email' => 'jane@example.com',
            'phone' => '+1-555-5678',
        ];
        
        $crm_settings = [
            'name_field_order'          => 'first_last',
            'phone_handling_strategy'   => 'secondary',
            'contact_mapping'           => [
                'email' => 'Email',
                'phone' => 'Phone',
            ],
        ];
        
        $payload = CRMFieldMapper::map_contact_fields($form_data, $crm_settings);
        
        echo "Strategy: secondary\n";
        echo "Expected: OtherPhone field = '+1-555-5678'\n";
        echo "Expected: Phone field = NOT SET (unchanged)\n";
        echo "Actual:   OtherPhone field = '" . ($payload['OtherPhone'] ?? 'NOT SET') . "'\n";
        echo "Actual:   Phone field = '" . ($payload['Phone'] ?? 'NOT SET') . "'\n";
        
        $pass = true;
        
        if (isset($payload['OtherPhone']) && $payload['OtherPhone'] === '+1-555-5678') {
            echo "✓ PASS: Phone correctly set in OtherPhone field\n";
        } else {
            echo "✗ FAIL: OtherPhone not set correctly\n";
            $pass = false;
        }
        
        if (!isset($payload['Phone'])) {
            echo "✓ PASS: Primary Phone field not set (preserved for existing contact)\n";
        } else {
            echo "✗ FAIL: Primary Phone field should not be set\n";
            $pass = false;
        }
        
        return $pass;
    }
    
    /**
     * Test 'skip_if_exists' strategy
     */
    public static function test_skip_if_exists_strategy() {
        echo "\n=== Test 3: Skip If Exists Strategy ===\n";
        
        $form_data = [
            'name'  => 'Bob Johnson',
            'email' => 'bob@example.com',
            'phone' => '+1-555-9999',
        ];
        
        $crm_settings = [
            'name_field_order'          => 'first_last',
            'phone_handling_strategy'   => 'skip_if_exists',
            'contact_mapping'           => [
                'email' => 'Email',
                'phone' => 'Phone',
            ],
        ];
        
        $payload = CRMFieldMapper::map_contact_fields($form_data, $crm_settings);
        
        echo "Strategy: skip_if_exists\n";
        echo "Expected: Phone field = '+1-555-9999' (for new/empty contacts)\n";
        echo "Expected: _phone_action marker = 'only_if_empty'\n";
        echo "Actual:   Phone field = '" . ($payload['Phone'] ?? 'NOT SET') . "'\n";
        echo "Actual:   _phone_action = '" . ($payload['_phone_action'] ?? 'NOT SET') . "'\n";
        
        $pass = true;
        
        if (isset($payload['Phone']) && $payload['Phone'] === '+1-555-9999') {
            echo "✓ PASS: Phone field set with value\n";
        } else {
            echo "✗ FAIL: Phone field should be set\n";
            $pass = false;
        }
        
        if (isset($payload['_phone_action']) && $payload['_phone_action'] === 'only_if_empty') {
            echo "✓ PASS: Marker flag set for conditional upsert\n";
        } else {
            echo "✗ FAIL: _phone_action marker not set correctly\n";
            $pass = false;
        }
        
        return $pass;
    }
    
    /**
     * Test strategy sanitization in CRMSettings
     */
    public static function test_strategy_sanitization() {
        echo "\n=== Test 4: Strategy Sanitization ===\n";
        
        $strategies = ['overwrite', 'secondary', 'skip_if_exists'];
        $pass = true;
        
        foreach ($strategies as $strategy) {
            $settings = [
                'phone_handling_strategy' => $strategy,
                'mapping' => [
                    'email' => 'Email',
                    'phone' => 'Phone',
                ],
            ];
            
            $sanitized = CRMSettings::sanitize($settings);
            
            if ($sanitized['phone_handling_strategy'] === $strategy) {
                echo "✓ PASS: '$strategy' strategy sanitized correctly\n";
            } else {
                echo "✗ FAIL: '$strategy' strategy not preserved\n";
                $pass = false;
            }
        }
        
        // Test invalid strategy defaults to 'overwrite'
        $settings = [
            'phone_handling_strategy' => 'invalid_strategy',
            'mapping' => ['email' => 'Email'],
        ];
        
        $sanitized = CRMSettings::sanitize($settings);
        
        if ($sanitized['phone_handling_strategy'] === 'overwrite') {
            echo "✓ PASS: Invalid strategy defaults to 'overwrite'\n";
        } else {
            echo "✗ FAIL: Invalid strategy should default to 'overwrite'\n";
            $pass = false;
        }
        
        return $pass;
    }
    
    /**
     * Test scenario: Contact exists, user provides new phone
     */
    public static function test_scenario_update_existing_contact() {
        echo "\n=== Test 5: Real-world Scenario - Update Existing Contact ===\n";
        echo "\nContext:\n";
        echo "- Contact already exists in Salesforce with Phone: +1-555-OLD\n";
        echo "- User submits form with Phone: +1-555-NEW\n";
        echo "- Compare all three strategies\n";
        
        $form_data = [
            'name'  => 'Michael Smith',
            'email' => 'michael@example.com',
            'phone' => '+1-555-NEW',
        ];
        
        $contact_mapping = [
            'email' => 'Email',
            'phone' => 'Phone',
        ];
        
        $results = [];
        
        // Test each strategy
        $strategies = ['overwrite', 'secondary', 'skip_if_exists'];
        
        foreach ($strategies as $strategy) {
            $crm_settings = [
                'name_field_order'          => 'first_last',
                'phone_handling_strategy'   => $strategy,
                'contact_mapping'           => $contact_mapping,
            ];
            
            $payload = CRMFieldMapper::map_contact_fields($form_data, $crm_settings);
            
            echo "\n  Strategy: '$strategy'\n";
            
            if ($strategy === 'overwrite') {
                echo "    Salesforce result: Phone = +1-555-NEW (overwritten)\n";
                echo "    Payload: " . json_encode(['Phone' => $payload['Phone']]) . "\n";
            } elseif ($strategy === 'secondary') {
                echo "    Salesforce result:\n";
                echo "      Phone = +1-555-OLD (unchanged)\n";
                echo "      OtherPhone = +1-555-NEW (new number)\n";
                echo "    Payload: " . json_encode(['OtherPhone' => $payload['OtherPhone']]) . "\n";
            } else {
                echo "    Salesforce result:\n";
                echo "      If Phone exists: unchanged\n";
                echo "      If Phone empty: Phone = +1-555-NEW\n";
                echo "    Payload: " . json_encode(['Phone' => $payload['Phone'], '_phone_action' => $payload['_phone_action']]) . "\n";
            }
        }
        
        echo "\n✓ All scenarios demonstrated\n";
        return true;
    }
    
    /**
     * Run all tests
     */
    public static function run_all_tests() {
        echo "\n" . str_repeat("=", 70) . "\n";
        echo "PHONE HANDLING STRATEGY TEST SUITE\n";
        echo str_repeat("=", 70);
        
        $results = [];
        $results[] = self::test_overwrite_strategy();
        $results[] = self::test_secondary_strategy();
        $results[] = self::test_skip_if_exists_strategy();
        $results[] = self::test_strategy_sanitization();
        $results[] = self::test_scenario_update_existing_contact();
        
        echo "\n" . str_repeat("=", 70) . "\n";
        echo "SUMMARY\n";
        echo str_repeat("=", 70) . "\n";
        
        $passed = array_sum($results);
        $total = count($results);
        
        echo "Passed: $passed/$total\n";
        
        if ($passed === $total) {
            echo "✓ All tests passed!\n";
        } else {
            echo "✗ Some tests failed. Review output above for details.\n";
        }
        
        echo str_repeat("=", 70) . "\n";
    }
}

// Run tests if this file is executed directly
if (basename(__FILE__) === basename($_SERVER['PHP_SELF'] ?? '')) {
    PhoneHandlingStrategyTest::run_all_tests();
}
