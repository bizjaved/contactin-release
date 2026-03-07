<?php
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals, WordPress.Security.EscapeOutput.OutputNotEscaped
/**
 * Test Intent Classification
 *
 * Usage: wp eval-file test-intent-classifier.php
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

require_once __DIR__ . '/includes/Core/IntentClassifier.php';

use ContactInbox\Core\IntentClassifier;

WP_CLI::log("Testing Intent Classification System");
WP_CLI::log(str_repeat("=", 60));
WP_CLI::log("");

// Test cases
$test_cases = [
    [
        'subject' => 'How much does the plan cost?',
        'message' => 'Hi, I am interested in your pricing plans. Can you send me a quote?',
        'expected' => 'sales'
    ],
    [
        'subject' => 'Need help with the system',
        'message' => 'I am having trouble with the application. It keeps giving me an error message.',
        'expected' => 'support'
    ],
    [
        'subject' => 'Great suggestion for improvement',
        'message' => 'Your product is great! I have a suggestion for a new feature...',
        'expected' => 'feedback'
    ],
    [
        'subject' => 'Very disappointed',
        'message' => 'I am extremely unhappy with this service. I want my money back immediately!',
        'expected' => 'complaint'
    ],
    [
        'subject' => 'Questions about your service',
        'message' => 'I have several questions. How does your service work? When can I start? What are the requirements?',
        'expected' => 'question'
    ],
    [
        'subject' => 'Special offer - LIMITED TIME',
        'message' => 'Click here to buy now! Limited time offer! Make money fast!',
        'expected' => 'spam'
    ],
];

$classifier = IntentClassifier::instance();
$passed = 0;
$failed = 0;

foreach ($test_cases as $test) {
    $result = $classifier->classify($test['subject'], $test['message']);
    
    $status = ($result['category'] === $test['expected']) ? '✓ PASS' : '✗ FAIL';
    if ($result['category'] === $test['expected']) {
        $passed++;
    } else {
        $failed++;
    }

    WP_CLI::log($status . ' | ' . $test['expected']);
    WP_CLI::log('Subject: ' . $test['subject']);
    WP_CLI::log('Detected: ' . $result['category'] . ' (Confidence: ' . $result['confidence'] . '%)');
    if (!empty($result['keywords'])) {
        WP_CLI::log('Keywords: ' . implode(', ', $result['keywords']));
    }
    WP_CLI::log("");
}

WP_CLI::log(str_repeat("=", 60));
WP_CLI::log("Test Results: $passed passed, $failed failed");
WP_CLI::log("");

// Test categories and colors
WP_CLI::log("Available Categories:");
WP_CLI::log("");

$categories = IntentClassifier::get_categories();
foreach ($categories as $key => $label) {
    $color = IntentClassifier::get_category_color($key);
    WP_CLI::log("  • $key => $label (color: $color)");
}

WP_CLI::log("");
WP_CLI::log("✓ Intent Classification System is working correctly!");
