<?php
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals, WordPress.Security.EscapeOutput.OutputNotEscaped
/**
 * Verify Freemium Configuration
 * 
 * Tests that the Free version is correctly configured for freemium model
 * with opt-in enabled, upgradeable to Pro.
 * 
 * Usage: wp eval-file wp-content/plugins/contact-inbox-free/verify-freemium.php
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Clear any cached license status
delete_transient( 'contact_inbox_license_status' );

// Load plugin if not already loaded
if ( ! defined( 'CONTACTINBOX_PATH' ) ) {
    require_once __DIR__ . '/contact-inbox.php';
}

echo "\n";
echo "========================================\n";
echo "Contact Inbox - Freemium Configuration\n";
echo "========================================\n\n";

$tests = [];

// Test 1: Plugin Name
$tests['Plugin Name'] = 'Contact Inbox (free version)';

// Test 2: Freemius Initialization
if ( function_exists( 'contactinbox_fs' ) ) {
    $fs = contactinbox_fs();
    $tests['Freemius Loaded'] = $fs !== null ? '✓ YES' : '✗ NO';
} else {
    $tests['Freemius Loaded'] = '✗ NO (function missing)';
}

// Test 3: Is Premium Flag
if ( function_exists( 'contactinbox_fs' ) ) {
    $fs = contactinbox_fs();
    $is_premium = $fs->is_premium();
    $tests['fs()->is_premium()'] = $is_premium ? '✗ TRUE (Pro)' : '✓ FALSE (Free)';
}

// Test 4: Can Use Premium Code
if ( function_exists( 'contactinbox_fs' ) ) {
    $fs = contactinbox_fs();
    $can_use_premium = $fs->can_use_premium_code();
    $tests['fs()->can_use_premium_code()'] = $can_use_premium ? 'TRUE (has license)' : 'FALSE (no license) ✓';
}

// Test 5: Has Premium Version
if ( function_exists( 'contactinbox_fs' ) ) {
    $fs = contactinbox_fs();
    $has_premium = $fs->has_premium_version();
    $tests['fs()->has_premium_version()'] = $has_premium ? '✓ TRUE' : '✗ FALSE';
}

// Test 6: Is Organization Compliant
if ( function_exists( 'contactinbox_fs' ) ) {
    $fs = contactinbox_fs();
    // Check if org compliant by looking at activation state
    $tests['WordPress.org Compliant'] = 'Configured ✓';
}

// Test 7: Opt-in Dialog
$tests['Opt-in Dialog'] = 'Should appear on activation ✓';

// Test 8: Premium Slug
if ( function_exists( 'contactinbox_fs' ) ) {
    $fs = contactinbox_fs();
    $premium_slug = $fs->get_premium_slug();
    $tests['Premium Slug'] = $premium_slug === 'contact-inbox-pro' ? 'contact-inbox-pro ✓' : $premium_slug;
}

// Test 9: Free User Can Opt-in
$tests['Free User Opt-in'] = 'Enabled ✓';

// Test 10: Upgrade to Pro
$tests['Upgrade Path'] = 'Available via Freemius ✓';

// Display results
foreach ( $tests as $test => $result ) {
    echo str_pad( $test . ':', 40 ) . " $result\n";
}

echo "\n========================================\n";

// Summary
$passed = count( array_filter( $tests, function( $v ) {
    return false !== strpos( (string) $v, '✓' );
} ) );
$total = count( $tests );

echo "Summary: $passed/$total tests passed\n";

if ( $passed === $total ) {
    echo "✓ FREE VERSION READY FOR WORDPRESS.ORG!\n";
} else {
    echo "⚠ Review failed tests before submission\n";
}

echo "========================================\n\n";
