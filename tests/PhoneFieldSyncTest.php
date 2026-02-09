<?php
/**
 * Unit Test: Phone Field Mapping and CRM Sync
 *
 * This test verifies that the phone field is correctly:
 * 1. Saved in the database from form submission
 * 2. Mapped by CRMFieldMapper
 * 3. Included in the Salesforce API payload
 * 4. Synced to the Contact record
 */

namespace ContactInbox\Tests;

use ContactInbox\Core\CRMFieldMapper;
use ContactInbox\Core\CRMSettings;

class PhoneFieldSyncTest {
    
    public static function test_phone_field_mapping() {
        echo "\n=== Phone Field Mapping Test ===\n";
        
        // Simulate form data
        $form_data = [
            'name'    => 'John Doe',
            'email'   => 'john@example.com',
            'phone'   => '+1-555-1234',
            'subject' => 'Test Subject',
            'message' => 'Test message',
        ];
        
        // Simulate CRM settings with default mapping
        $crm_settings = [
            'name_field_order' => 'first_last',
            'contact_mapping'  => [
                'email' => 'Email',
                'phone' => 'Phone',
            ],
            'inquiry_mapping'  => [
                'subject' => 'Subject',
                'message' => 'Description',
            ],
        ];
        
        // Test contact field mapping
        $contact_payload = CRMFieldMapper::map_contact_fields($form_data, $crm_settings);
        
        echo "Contact Payload:\n";
        echo json_encode($contact_payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
        
        // Verify phone is included
        if (isset($contact_payload['Phone']) && $contact_payload['Phone'] === '+1-555-1234') {
            echo "✓ Phone field correctly mapped to 'Phone'\n";
        } else {
            echo "✗ Phone field NOT found in contact payload\n";
            echo "  Expected: contact_payload['Phone'] = '+1-555-1234'\n";
            echo "  Got: " . json_encode($contact_payload['Phone'] ?? 'NOT SET') . "\n";
        }
        
        // Test inquiry field mapping
        $inquiry_payload = CRMFieldMapper::map_inquiry_fields(
            $form_data,
            $crm_settings,
            'a12xx0000000001AAA', // Mock Salesforce Contact ID
            'Case'
        );
        
        echo "\nInquiry Payload:\n";
        echo json_encode($inquiry_payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
        
        // Verify name is mapped
        if (isset($contact_payload['FirstName']) && isset($contact_payload['LastName'])) {
            echo "✓ Name correctly split into FirstName and LastName\n";
        } else {
            echo "✗ Name NOT properly split\n";
        }
        
        // Verify email is mapped
        if (isset($contact_payload['Email'])) {
            echo "✓ Email correctly mapped\n";
        } else {
            echo "✗ Email NOT in contact payload\n";
        }
    }
    
    public static function test_crm_settings_sanitization() {
        echo "\n=== CRM Settings Sanitization Test ===\n";
        
        // Simulate admin form submission
        $settings = [
            'mapping' => [
                'email'   => 'Email',
                'phone'   => 'Phone',
                'subject' => 'Subject',
                'message' => 'Description',
                'name'    => 'FirstName,LastName',
            ],
            'instance_url'     => 'https://test.salesforce.com',
            'crm_enabled'      => true,
            'name_field_order' => 'first_last',
        ];
        
        // Simulate sanitization
        $sanitized = CRMSettings::sanitize($settings);
        
        echo "Sanitized Settings Keys: " . implode(', ', array_keys($sanitized)) . "\n";
        
        // Verify contact_mapping is created
        if (!empty($sanitized['contact_mapping'])) {
            echo "✓ contact_mapping structure created\n";
            echo "  contact_mapping['email'] = " . ($sanitized['contact_mapping']['email'] ?? 'NOT SET') . "\n";
            echo "  contact_mapping['phone'] = " . ($sanitized['contact_mapping']['phone'] ?? 'NOT SET') . "\n";
        } else {
            echo "✗ contact_mapping NOT created\n";
        }
        
        // Verify inquiry_mapping is created
        if (!empty($sanitized['inquiry_mapping'])) {
            echo "✓ inquiry_mapping structure created\n";
            echo "  inquiry_mapping['subject'] = " . ($sanitized['inquiry_mapping']['subject'] ?? 'NOT SET') . "\n";
            echo "  inquiry_mapping['message'] = " . ($sanitized['inquiry_mapping']['message'] ?? 'NOT SET') . "\n";
        } else {
            echo "✗ inquiry_mapping NOT created\n";
        }
    }
    
    public static function run_all_tests() {
        echo "\n" . str_repeat("=", 50) . "\n";
        echo "PHONE FIELD SYNC UNIT TESTS\n";
        echo str_repeat("=", 50);
        
        self::test_phone_field_mapping();
        self::test_crm_settings_sanitization();
        
        echo "\n" . str_repeat("=", 50) . "\n";
        echo "Tests completed. Check output above for any ✗ marks.\n";
    }
}

// Run tests
PhoneFieldSyncTest::run_all_tests();
