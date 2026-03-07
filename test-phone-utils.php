<?php
/**
 * Test PhoneUtils - Phone Detection & Normalization
 * 
 * Usage: wp eval-file test-phone-utils.php
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

require_once __DIR__ . '/includes/Core/PhoneUtils.php';

use ContactInbox\Core\PhoneUtils;

WP_CLI::log("Testing Phone Number Detection & Normalization");
WP_CLI::log(str_repeat("=", 60));
WP_CLI::log("");

$test_cases = [
    // US/Canada Mobile
    ['+1 917-555-1234', '1', 'US Mobile (NYC)', PhoneUtils::TYPE_MOBILE],
    ['(646) 555-9876', '1', 'US Mobile (NYC formatted)', PhoneUtils::TYPE_MOBILE],
    ['+1-310-555-7890', '1', 'US Mobile (LA)', PhoneUtils::TYPE_MOBILE],
    ['7205551234', '1', 'US Mobile (Denver, no formatting)', PhoneUtils::TYPE_MOBILE],
    
    // UK Mobile
    ['+44 7911 123456', '44', 'UK Mobile', PhoneUtils::TYPE_MOBILE],
    ['07911123456', '44', 'UK Mobile (national format)', PhoneUtils::TYPE_MOBILE],
    
    // India Mobile
    ['+91 9876543210', '91', 'India Mobile', PhoneUtils::TYPE_MOBILE],
    ['9876543210', '91', 'India Mobile (no prefix)', PhoneUtils::TYPE_MOBILE],
    ['+91 6123456789', '91', 'India Mobile (starts with 6)', PhoneUtils::TYPE_MOBILE],
    
    // Australia Mobile
    ['+61 412 345 678', '61', 'Australia Mobile', PhoneUtils::TYPE_MOBILE],
    ['0412345678', '61', 'Australia Mobile (national)', PhoneUtils::TYPE_MOBILE],
    
    // UAE Mobile
    ['+971 50 123 4567', '971', 'UAE Mobile', PhoneUtils::TYPE_MOBILE],
    ['0501234567', '971', 'UAE Mobile (national)', PhoneUtils::TYPE_MOBILE],
    
    // Pakistan Mobile
    ['+92 300 1234567', '92', 'Pakistan Mobile', PhoneUtils::TYPE_MOBILE],
    ['03001234567', '92', 'Pakistan Mobile (national)', PhoneUtils::TYPE_MOBILE],
    
    // Saudi Arabia Mobile
    ['+966 50 123 4567', '966', 'Saudi Mobile', PhoneUtils::TYPE_MOBILE],
    
    // Kuwait Mobile
    ['+965 5123 4567', '965', 'Kuwait Mobile', PhoneUtils::TYPE_MOBILE],
    
    // Unknown/Landline
    ['+1 212-555-1234', '1', 'US Landline (likely)', PhoneUtils::TYPE_UNKNOWN],
    ['+44 20 7946 0958', '44', 'UK Landline', PhoneUtils::TYPE_UNKNOWN],
];

WP_CLI::log("Test Case Results:");
WP_CLI::log(str_repeat("-", 60));

$passed = 0;
$failed = 0;

foreach ($test_cases as $index => $test) {
    list($input, $default_country, $description, $expected_type) = $test;
    
    // Test normalization
    $normalized = PhoneUtils::normalize($input, $default_country);
    
    // Test detection
    $detected_type = PhoneUtils::detect_type($input, ['default_country' => $default_country]);
    
    // Check result
    $status = ($detected_type === $expected_type) ? '✓ PASS' : '✗ FAIL';
    if ($detected_type === $expected_type) {
        $passed++;
    } else {
        $failed++;
    }
    
    WP_CLI::log(sprintf(
        "%s | %s",
        $status,
        $description
    ));
    WP_CLI::log(sprintf(
        "       Input: %s → Normalized: %s",
        $input,
        $normalized
    ));
    WP_CLI::log(sprintf(
        "       Expected: %s | Detected: %s",
        $expected_type,
        $detected_type
    ));
    WP_CLI::log("");
}

WP_CLI::log(str_repeat("=", 60));
WP_CLI::log(sprintf(
    "Results: %d passed, %d failed out of %d tests",
    $passed,
    $failed,
    count($test_cases)
));

// Test phone equality
WP_CLI::log("");
WP_CLI::log("Testing Phone Equality:");
WP_CLI::log(str_repeat("-", 60));

$equality_tests = [
    ['+1 917-555-1234', '(917) 555-1234', true, 'Same phone, different format'],
    ['9175551234', '+19175551234', true, 'Same phone, with/without prefix'],
    ['+44 7911 123456', '07911 123456', true, 'UK same phone'],
    ['+1 917-555-1234', '+1 646-555-9876', false, 'Different phones'],
];

foreach ($equality_tests as $test) {
    list($phone1, $phone2, $expected, $description) = $test;
    $result = PhoneUtils::are_equal($phone1, $phone2);
    $status = ($result === $expected) ? '✓ PASS' : '✗ FAIL';
    
    WP_CLI::log(sprintf(
        "%s | %s",
        $status,
        $description
    ));
    WP_CLI::log(sprintf(
        "       %s == %s ? %s (expected: %s)",
        $phone1,
        $phone2,
        $result ? 'true' : 'false',
        $expected ? 'true' : 'false'
    ));
    WP_CLI::log("");
}

if ($failed === 0) {
    WP_CLI::success("All tests passed!");
} else {
    WP_CLI::error(sprintf("%d tests failed!", $failed));
}
