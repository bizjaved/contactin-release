<?php
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals, WordPress.Security.EscapeOutput.OutputNotEscaped, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DateTime.RestrictedFunctions.date_date
/**
 * Test Spam Blocked Card Calculation
 * 
 * Traces the data sources for the Spam Blocked dashboard card:
 * 1. reCAPTCHA failures from submission_attempts rejection_reasons table
 * 2. Classifier spam from messages table (intent_category = 'spam')
 * 
 * Run from WordPress CLI or browser: wp-content/plugins/contact-inbox-pro/test-spam-blocked-card.php
 */

if (!defined('ABSPATH')) {
    if (!defined('WP_CLI') || !WP_CLI) {
        exit;
    }
    $wp_load_path = dirname(__DIR__, 3) . '/wp-load.php';
    if (!file_exists($wp_load_path)) {
        exit;
    }
    require_once $wp_load_path;
}

use ContactInbox\Core\Config;
use ContactInbox\Core\Repositories\AnalyticsRepository;
use ContactInbox\Core\Repositories\SubmissionAttemptsRepository;
use ContactInbox\Core\Repositories\MessageRepository;
use ContactInbox\Core\IntentClassifier;

global $wpdb;

echo "\n========== SPAM BLOCKED CARD DATA VERIFICATION ==========\n";
echo "Testing data sources for Spam Blocked metric (reCAPTCHA + Classifier)\n";

// Test parameters
$days = 7;
$start_date = date('Y-m-d', strtotime('-7 days'));
$end_date = date('Y-m-d');

echo "\nDate Range: {$start_date} to {$end_date}\n";
echo "Days: {$days}\n";

// 1. Get rejection reasons from database directly
echo "\n--- 1. REJECTION REASONS (Raw Database Query) ---\n";
$attempts_table = $wpdb->prefix . Config::TABLE_SUBMISSION_ATTEMPTS;
$rejection_query = $wpdb->prepare(
    "SELECT rejection_reason, COUNT(*) as count 
     FROM {$attempts_table}
     WHERE DATE(created_at) >= %s 
     AND DATE(created_at) <= %s
     AND rejection_reason IS NOT NULL
     AND rejection_reason != ''
     GROUP BY rejection_reason
     ORDER BY count DESC",
    $start_date,
    $end_date
);

$rejection_results = $wpdb->get_results($rejection_query);
echo "Total rejection reasons queries: " . count($rejection_results) . "\n";
foreach ($rejection_results as $result) {
    echo "  - {$result->rejection_reason}: {$result->count}\n";
}

// Get recaptcha_failed count specifically
$recaptcha_count = $wpdb->get_var($wpdb->prepare(
    "SELECT COUNT(*) FROM {$attempts_table}
     WHERE rejection_reason = %s
     AND DATE(created_at) >= %s 
     AND DATE(created_at) <= %s",
    'recaptcha_failed',
    $start_date,
    $end_date
));
echo "\n✓ reCAPTCHA Failed Count: {$recaptcha_count}\n";

// 2. Get classifier spam from database directly
echo "\n--- 2. CLASSIFIER SPAM (Raw Database Query) ---\n";
$messages_table = $wpdb->prefix . Config::TABLE_MESSAGES;
$classifier_query = $wpdb->prepare(
    "SELECT COUNT(*) as count 
     FROM {$messages_table}
     WHERE intent_category = %s
     AND DATE(submitted_at) >= %s 
     AND DATE(submitted_at) <= %s",
    IntentClassifier::CATEGORY_SPAM,
    $start_date,
    $end_date
);

$classifier_count = $wpdb->get_var($classifier_query);
echo "✓ Classifier Spam Count: {$classifier_count}\n";

// 3. Use repositories to verify they match
echo "\n--- 3. REPOSITORY METHODS VERIFICATION ---\n";

$attempts_repo = new SubmissionAttemptsRepository();
$rejection_reasons_raw = $attempts_repo->get_rejection_reasons($days, $start_date, $end_date);
echo "Rejection reasons from SubmissionAttemptsRepository::get_rejection_reasons():\n";
$recaptcha_from_repo = 0;
foreach ($rejection_reasons_raw as $reason) {
    echo "  - {$reason['rejection_reason']}: {$reason['count']}\n";
    if ($reason['rejection_reason'] === 'recaptcha_failed') {
        $recaptcha_from_repo = (int)$reason['count'];
    }
}
echo "reCAPTCHA from repo: {$recaptcha_from_repo}\n";

echo "\nMessageRepository for classifier spam:\n";
$message_repo = new MessageRepository();
$classifier_from_repo = $message_repo->count_by_intent_range(
    IntentClassifier::CATEGORY_SPAM,
    $days,
    $start_date,
    $end_date
);
echo "Classifier spam from repo: {$classifier_from_repo}\n";

// 4. Use AnalyticsRepository
echo "\n--- 4. ANALYTICS REPOSITORY ---\n";
$analytics_repo = new AnalyticsRepository();
$classifier_from_analytics = $analytics_repo->get_classifier_spam_count($days, $start_date, $end_date);
echo "Classifier spam from AnalyticsRepository: {$classifier_from_analytics}\n";

// 5. Calculate final spam_blocked
echo "\n--- 5. FINAL CALCULATION ---\n";
$spam_blocked = $recaptcha_from_repo + $classifier_from_analytics;
echo "Spam Blocked = reCAPTCHA + Classifier Spam\n";
echo "Spam Blocked = {$recaptcha_from_repo} + {$classifier_from_analytics} = {$spam_blocked}\n";

// 6. Verification checks
echo "\n--- 6. VERIFICATION CHECKS ---\n";
$checks = [
    "Raw DB recaptcha matches repo" => ($recaptcha_count == $recaptcha_from_repo),
    "Raw DB classifier matches repo" => ($classifier_count == $classifier_from_analytics),
    "Classifier repo matches analytics" => ($classifier_from_repo == $classifier_from_analytics),
];

foreach ($checks as $check => $result) {
    echo ($result ? "✓" : "✗") . " {$check}\n";
}

// 7. Show what the dashboard would display
echo "\n--- 7. DASHBOARD DISPLAY ---\n";
echo "Spam Blocked card will show: {$spam_blocked}\n";
echo "Rejection Reasons detail:\n";
echo "  - reCAPTCHA Failed: {$recaptcha_from_repo}\n";
echo "  - Classifier Spam (separate in response): {$classifier_from_analytics}\n";

echo "\n========== END OF VERIFICATION ==========\n\n";
