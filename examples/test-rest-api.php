<?php
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals, WordPress.Security.EscapeOutput.OutputNotEscaped, WordPress.WP.AlternativeFunctions.curl_curl_init, WordPress.WP.AlternativeFunctions.curl_curl_setopt, WordPress.WP.AlternativeFunctions.curl_curl_exec, WordPress.WP.AlternativeFunctions.curl_curl_getinfo, WordPress.WP.AlternativeFunctions.curl_curl_error, WordPress.WP.AlternativeFunctions.curl_curl_close
/**
 * Contact Inbox - REST API Test Script (PHP)
 *
 * This script demonstrates how to test the REST API endpoint
 * from an external PHP application.
 *
 * Usage:
 *   php test-rest-api.php "John Doe" "john@example.com" "Hello!"
 *
 * Or modify the variables below and run: php test-rest-api.php
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Configuration
$api_url = 'https://example.com/wp-json/contactin/v1/submit';

// Get parameters from command line or use defaults
$name = $argv[1] ?? 'John Doe';
$email = $argv[2] ?? 'john@example.com';
$message = $argv[3] ?? 'Test message from external PHP script';
$subject = $argv[4] ?? 'External Test Submission';

echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "REST API Test - Contact Inbox (PHP)\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";

echo "Endpoint: $api_url\n";
echo "Name: $name\n";
echo "Email: $email\n";
echo "Message: $message\n";
echo "Subject: $subject\n\n";

// Prepare payload
$payload = [
    'name'    => $name,
    'email'   => $email,
    'message' => $message,
    'subject' => $subject,
];

// Make the request
$ch = curl_init($api_url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json',
]);

$response = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$error = curl_error($ch);
curl_close($ch);

// Display response
if ($error) {
    echo "ERROR: $error\n";
} else {
    echo "HTTP Status: $http_code\n";
    echo "Response:\n";
    $decoded = json_decode($response, true);
    if ($decoded) {
        echo json_encode($decoded, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
    } else {
        echo $response . "\n";
    }
}

echo "\n━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
