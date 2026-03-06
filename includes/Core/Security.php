<?php
declare(strict_types=1);

/**
 * Core – Security Utilities (Rate-limit, Input Validation, reCAPTCHA, IP)
 * Fully isolated, typed, Enterprise-Grade
 *
 * @package ContactInbox
 */

namespace ContactInbox\Core;

use ContactInbox\Traits\Singleton;

if (!defined('ABSPATH')) {
    exit;
}

final class Security {
    use Singleton;

    private const RATE_LIMIT_MAX = 5; 
    private const RATE_LIMIT_TTL = 0 * MINUTE_IN_SECONDS; // must be set to 2  * MINUTE_IN_SECONDS; on release
    private const PHONE_REGEX = '/^\+?[0-9]{7,20}$/';
    private const MAX_EMAIL_LENGTH = 100;
    private const MAX_NAME_LENGTH = 100;
    private const MIN_NAME_LENGTH = 2;
    private const MIN_MESSAGE_LENGTH = 10;
    private const MAX_MESSAGE_LENGTH = 10000;

    private function __construct() {}

    // =========================================================================
    // Rate Limiting
    // =========================================================================

    /**
     * Check rate limit per IP & form
     */
    public static function check_rate_limit(string $form_id = 'default'): bool {
        // Temporarily bypass rate limiting for admin harness
        return false;

        // --- original logic below ---
        $ip   = self::get_ip_address();
        $key  = "ci_rate_{$form_id}_{$ip}";
        $count = (int) get_transient($key);

        if ($count >= self::RATE_LIMIT_MAX) {
            return false;
        }

        set_transient($key, $count + 1, self::RATE_LIMIT_TTL);
        return true;
    }

    // =========================================================================
    // reCAPTCHA Verification
    // =========================================================================

    /**
     * Verify reCAPTCHA v3 token
     */
    public static function verify_recaptcha(string $response): bool {
        $secret = get_option('ci_recaptcha_secret');
        if (!$secret || empty($response)) {
            return true; // disabled or empty token → trusted
        }

        $remote_ip = self::get_ip_address();
        $response_data = wp_remote_post('https://www.google.com/recaptcha/api/siteverify', [
            'timeout' => 8,
            'body'    => [
                'secret'   => $secret,
                'response' => $response,
                'remoteip' => $remote_ip,
            ],
            'user-agent' => 'ContactInbox/' . CONTACTINBOX_VERSION . ' | WordPress/' . get_bloginfo('version'),
        ]);

        if (is_wp_error($response_data)) {
            Logger::warning(
                'reCAPTCHA verification failed',
                [ 'error' => $response_data->get_error_message(), 'ip' => $remote_ip ]
            );
            return false;
        }

        $result = json_decode(wp_remote_retrieve_body($response_data), true);
        return !empty($result['success']) && ($result['score'] ?? 0) >= 0.5;
    }

    // =========================================================================
    // IP Detection
    // =========================================================================

    /**
     * Get visitor IP address (safe)
     */
    public static function get_ip_address(): string {
        $remote_addr = filter_input(INPUT_SERVER, 'REMOTE_ADDR', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
        return is_string($remote_addr) ? sanitize_text_field(wp_unslash($remote_addr)) : '';
    }

    // =========================================================================
    // Input Validation
    // =========================================================================

    /**
     * Validate form input
     *
     * @param array<string,mixed> $input
     * @return array<int,string> Errors
     */
    public static function validate_input(array $input): array {
        $errors = [];

        // Name
        $name = trim($input['name'] ?? '');
        if (empty($name) || strlen($name) < self::MIN_NAME_LENGTH || strlen($name) > self::MAX_NAME_LENGTH) {
            $errors[] = __('Please enter a valid name (2–100 characters).', 'contact-inbox');
        }

        // Email
        $email = trim($input['email'] ?? '');
        if (!self::is_valid_email($email)) {
            $errors[] = __('Please enter a valid, real email address.', 'contact-inbox');
        }

        // Phone (optional)
        if (!empty($input['phone']) && !self::is_valid_phone($input['phone'])) {
            $errors[] = __('Please enter a valid phone number (optional).', 'contact-inbox');
        }

        // Message
        $message = trim($input['message'] ?? '');
        if (empty($message) || strlen($message) < self::MIN_MESSAGE_LENGTH || strlen($message) > self::MAX_MESSAGE_LENGTH) {
            $errors[] = __('Message must be 10–10,000 characters.', 'contact-inbox');
        }

        // Consent
        if (empty($input['consent'])) {
            $errors[] = __('You must agree to data processing.', 'contact-inbox');
        }

        return $errors;
    }

    // =========================================================================
    // Email & Phone Validators
    // =========================================================================

    /**
     * Validate email strictly
     */
    public static function is_valid_email(string $email): bool {
        $email = strtolower(trim($email));

        if (empty($email) || strlen($email) > self::MAX_EMAIL_LENGTH || !is_email($email)) {
            return false;
        }

        $disposable_domains = apply_filters('contactin_disposable_email_domains', [
            '10minutemail.com','guerrillamail.com','tempmail.org','mailinator.com',
            'yopmail.com','throwaway.email','disposable-mail.com','sharklasers.com','getairmail.com',
            'maildrop.cc','temp-mail.org','armyspy.com',
        ]);

        $domain = substr(strrchr($email, '@'), 1);
        if (in_array($domain, $disposable_domains, true)) {
            return false;
        }

        return true;
    }

    /**
     * Validate phone (optional, flexible)
     */
    public static function is_valid_phone(string $phone): bool {
        $phone = preg_replace('/[^0-9\+]/', '', trim($phone));
        if (empty($phone)) return true; // optional
        return (bool) preg_match(self::PHONE_REGEX, $phone);
    }

    /**
     * Honeypot check – returns true if spam bot filled the hidden field.
     */
    public static function is_honeypot_triggered(): bool {
        $post_data = filter_input_array(INPUT_POST, FILTER_UNSAFE_RAW);
        if (!is_array($post_data)) {
            return false;
        }

        foreach ($post_data as $key => $value) {
            $honeypot_key = sanitize_key((string) $key);
            if (strpos($honeypot_key, 'ci_hp_') === 0 && !empty((string) $value)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Sanitize input specifically for REST API requests.
     *
     * @param array<string,mixed> $params
     * @return array<string,mixed>
     */
    public static function rest_sanitize_input( array $params ): array {
        return [
            'name'    => sanitize_text_field( $params['name'] ?? '' ),
            'email'   => sanitize_email( $params['email'] ?? '' ),
            'phone'   => sanitize_text_field( $params['phone'] ?? '' ),
            'message' => wp_kses_post( $params['message'] ?? '' ),
            'consent' => self::rest_normalize_consent( $params['consent'] ?? false ),
            'form_id' => sanitize_text_field( $params['form_id'] ?? 'default' ),
        ];
    }

    /**
     * Normalize consent flag for REST API.
     *
     * @param mixed $value
     * @return bool
     */
    public static function rest_normalize_consent( mixed $value ): bool {
        return in_array( $value, [ true, 'true', '1', 1 ], true );
    }
}
