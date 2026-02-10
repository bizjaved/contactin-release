<?php
/**
 * Core – Settings Logic
 *
 * Centralized settings management: defaults, sanitization, encryption, getters, registration.
 *
 * @package ContactInbox\Core
 * @since   1.0.0
 */

declare(strict_types=1);

namespace ContactInbox\Core;

use ContactInbox\Traits\Singleton;

if (!defined('ABSPATH')) {
    exit;
}

final class Settings {
    use Singleton;

    public const OPTION_NAME = Config::OPTION_SETTINGS;

    private static ?array $cache = null;

    /**
     * Register settings with WordPress (idempotent).
     */
    public static function register(): void {
        register_setting(
            
            Config::SETTINGS_GROUP,
            self::OPTION_NAME,
            [
                'sanitize_callback' => [self::class, 'sanitize_settings'],
                'default'           => self::get_default_settings(),
                'show_in_rest'      => false,
            ]
        );
    }

    /**
     * Return merged settings (saved + defaults) with in‑memory caching.
     */
    public static function get_settings(): array {
        if (self::$cache !== null) {
            return self::$cache;
        }
        $defaults = self::get_default_settings();
        $saved    = get_option(self::OPTION_NAME, []);
        self::$cache = wp_parse_args(is_array($saved) ? $saved : [], $defaults);
        return self::$cache;
    }

    /**
     * Persist settings and refresh cache.
     */
    public static function update_settings(array $new_settings): bool {
        $updated = update_option(self::OPTION_NAME, $new_settings, true);
        // Always refresh cache to prevent stale reads.
        self::$cache = $new_settings;
        return $updated;
    }

    /**
     * Clear the settings cache (useful after direct database updates).
     */
    public static function clear_cache(): void {
        self::$cache = null;
    }

    /**
     * Default settings.
     */
    public static function get_default_settings(): array {
        return [
            // reCAPTCHA
            'recaptcha_site_key'   => '',
            'recaptcha_secret_key' => '',
            'recaptcha_enable'     => false,

            // SMTP
            'smtp_enable'      => false,
            'smtp_host'        => 'smtp.gmail.com',
            'smtp_port'        => 587,
            'smtp_encryption'  => 'tls',
            'smtp_user'        => '',
            'smtp_pass'        => '',
            'smtp_from_email'  => '',
            'smtp_from_name'   => '',

            // Notifications
            'send_admin_notification' => true,
            'admin_email'             => get_option('admin_email'),
            'send_user_copy'          => true,

            // Privacy & UX
            'privacy_url'     => get_privacy_policy_url(),
            'consent_text'    => __('I consent to my data being used to respond to this message.', Config::TEXTDOMAIN),
            'success_message' => __('Thank you! Your message has been sent successfully.', Config::TEXTDOMAIN),
            'confetti_enable' => true,
            'gdpr_enable'     => true,

            // Email Log Retention
            'email_log_retention_days' => 90,

            // Services
            'restapi_enable'   => true,
            'webhooks_enable'  => true,

            // Webhooks
            'webhooks' => [],

            // Log Retention
            'rest_log_retention_days'    => 30,
            'crm_log_retention_days'     => 30,
            'gdpr_log_retention_days'    => 90,

            // Form Customisation
            'form_enable_subject'    => true,
            'form_enable_attachment' => true,
            'form_enable_salutation' => true,

            'max_name_chars'         => 100,
            'max_subject_chars'      => 150,
            'max_message_chars'      => 2000,

            'min_name_words'         => 2,
            'min_subject_words'      => 3,
            'min_message_words'      => 5,

            'allowed_file_types'     => 'jpg,jpeg,png,gif,bmp,pdf,doc,docx,xls,xlsx,txt,csv',
            'max_file_size'          => 5, // in MB

            // Rate limiting (per IP)
            'rate_limit_per_minute'  => 60,
            'rate_limit_per_hour'    => 600,
            'rate_limit_per_day'     => 3000,

            // Intent Classification
            'intent_enable' => true,
        ];
    }

    /**
     * Sanitize settings input.
     */
    public static function sanitize_settings(array $input): array {
        $defaults  = self::get_default_settings();
        $existing  = get_option(self::OPTION_NAME, []);
        if (!is_array($existing)) {
            $existing = [];
        }

        // --- Attachment/REST API dependency logic ---
                $attachment_requested = isset($input['form_enable_attachment']) && self::normalize_checkbox_value($input['form_enable_attachment']);
                $restapi_requested = isset($input['restapi_enable']) ? self::normalize_checkbox_value($input['restapi_enable']) : ($existing['restapi_enable'] ?? $defaults['restapi_enable']);

                // If file upload is enabled, force REST API enabled
                if ($attachment_requested && !$restapi_requested) {
                    $input['restapi_enable'] = true;
                    add_settings_error(
                        Config::OPTION_SETTINGS,
                        'contactin_attachment_restapi_dependency',
                        __('File upload requires REST API. REST API has been enabled automatically.', Config::TEXTDOMAIN),
                        'info'
                    );
                }
                // If file upload is disabled, allow REST API to be disabled (user's choice)
                // No forced disabling here; user can disable REST API if not used elsewhere
        $privacy_input = isset($input['privacy_url']) ? trim((string) $input['privacy_url']) : null;
        if ($privacy_input === null) {
            $privacy_url = $existing['privacy_url'] ?? $defaults['privacy_url'];
        } elseif ($privacy_input === '') {
            $privacy_url = $defaults['privacy_url'];
        } else {
            $privacy_url = esc_url_raw($privacy_input);
            if ($privacy_url === '') {
                add_settings_error(
                    Config::OPTION_SETTINGS,
                    'contactin_privacy_url_invalid',
                    __('Privacy Policy URL is invalid. Keeping the previous value.', Config::TEXTDOMAIN),
                    'error'
                );
                $privacy_url = $existing['privacy_url'] ?? $defaults['privacy_url'];
            }
        }

        $sanitized = [
            // reCAPTCHA
            'recaptcha_site_key'   => sanitize_text_field($input['recaptcha_site_key'] ?? $defaults['recaptcha_site_key']),
            'recaptcha_secret_key' => sanitize_text_field($input['recaptcha_secret_key'] ?? $defaults['recaptcha_secret_key']),
            'recaptcha_enable'     => self::normalize_checkbox_value($input['recaptcha_enable'] ?? false),

            // SMTP
            'smtp_enable'      => self::normalize_checkbox_value($input['smtp_enable'] ?? false),
            'smtp_host'        => sanitize_text_field($input['smtp_host'] ?? $defaults['smtp_host']),
            'smtp_port'        => absint($input['smtp_port'] ?? $defaults['smtp_port']),
            'smtp_encryption'  => in_array($input['smtp_encryption'] ?? '', ['ssl', 'tls', 'none'], true)
                ? $input['smtp_encryption']
                : $defaults['smtp_encryption'],
            'smtp_user'        => sanitize_text_field($input['smtp_user'] ?? $defaults['smtp_user']),
            'smtp_pass'        => $input['smtp_pass'] ?? '',
            'smtp_from_email'  => sanitize_email($input['smtp_from_email'] ?? ($existing['smtp_from_email'] ?? $defaults['smtp_from_email'])),
            'smtp_from_name'   => sanitize_text_field($input['smtp_from_name'] ?? ($existing['smtp_from_name'] ?? $defaults['smtp_from_name'])),

            // Notifications
            'send_admin_notification' => self::normalize_checkbox_value($input['send_admin_notification'] ?? false),
            'send_user_copy'          => self::normalize_checkbox_value($input['send_user_copy'] ?? false),

            // Privacy & UX
            'privacy_url'     => $privacy_url,
            'consent_text'    => wp_kses_post($input['consent_text'] ?? $defaults['consent_text']),
            'success_message' => wp_kses_post($input['success_message'] ?? $defaults['success_message']),
            'confetti_enable' => self::normalize_checkbox_value($input['confetti_enable'] ?? false),
            'gdpr_enable'     => self::normalize_checkbox_value($input['gdpr_enable'] ?? false),

            // Retention
            'email_log_retention_days'   => absint($input['email_log_retention_days'] ?? $defaults['email_log_retention_days']),
            'rest_log_retention_days'    => absint($input['rest_log_retention_days'] ?? $defaults['rest_log_retention_days']),
            'crm_log_retention_days'     => absint($input['crm_log_retention_days'] ?? $defaults['crm_log_retention_days']),
            'gdpr_log_retention_days'    => absint($input['gdpr_log_retention_days'] ?? $defaults['gdpr_log_retention_days']),

            // Services
            'restapi_enable'   => self::normalize_checkbox_value($input['restapi_enable'] ?? $existing['restapi_enable'] ?? $defaults['restapi_enable']),
            'webhooks_enable'  => self::normalize_checkbox_value($input['webhooks_enable'] ?? $existing['webhooks_enable'] ?? $defaults['webhooks_enable']),

            // Webhooks
            'webhooks' => self::sanitize_webhooks($input['webhooks'] ?? []),
        ];

        // Admin email: allow comma-separated list, validate each
        $raw_admin_email = $input['admin_email'] ?? get_option('admin_email');
        $emails = preg_split('/[;,\r\n]+/', (string) $raw_admin_email) ?: [];
        $emails = array_map('trim', $emails);
        $valid_emails = [];
        foreach ($emails as $email) {
            $sanitized_email = sanitize_email($email);
            if ($sanitized_email && is_email($sanitized_email) && strlen($sanitized_email) <= 254) {
                $valid_emails[] = $sanitized_email;
            }
        }
        if (!empty($valid_emails)) {
            $sanitized['admin_email'] = implode(', ', $valid_emails);
        } else {
            $fallback = sanitize_email(get_option('admin_email'));
            $sanitized['admin_email'] = ($fallback && is_email($fallback)) ? $fallback : '';
        }

        $smtp_enabled  = $sanitized['smtp_enable'];
        $sender_email  = $sanitized['smtp_from_email'];
        $smtp_username = $sanitized['smtp_user'];

        if ($smtp_enabled) {
            if ($sender_email === '' && is_email($smtp_username)) {
                $sanitized['smtp_from_email'] = $smtp_username;
                add_settings_error(
                    Config::OPTION_SETTINGS,
                    'contactin_smtp_from_email_fallback',
                    __('SMTP sender email was empty; using the SMTP username instead.', Config::TEXTDOMAIN),
                    'warning'
                );
            } elseif ($sender_email === '') {
                add_settings_error(
                    Config::OPTION_SETTINGS,
                    'contactin_smtp_from_email_missing',
                    __('Please provide a valid sender email address that matches your SMTP mailbox.', Config::TEXTDOMAIN),
                    'error'
                );
            }

            $sender_domain = self::extract_domain($sanitized['smtp_from_email']);
            $user_domain   = self::extract_domain($smtp_username);
            if ($sender_domain !== '' && $user_domain !== '' && $sender_domain !== $user_domain) {
                add_settings_error(
                    Config::OPTION_SETTINGS,
                    'contactin_smtp_domain_mismatch',
                    __('Sender email domain differs from SMTP username domain. Align them to avoid DMARC failures.', Config::TEXTDOMAIN),
                    'warning'
                );
            }
        }

        // Form Customisation
        $sanitized['form_enable_subject']    = self::normalize_checkbox_value($input['form_enable_subject'] ?? false);
        $sanitized['form_enable_attachment'] = self::normalize_checkbox_value($input['form_enable_attachment'] ?? false);
        $sanitized['form_enable_salutation'] = self::normalize_checkbox_value($input['form_enable_salutation'] ?? false);

        $sanitized['max_name_chars']    = absint($input['max_name_chars'] ?? $defaults['max_name_chars']);
        $sanitized['max_subject_chars'] = absint($input['max_subject_chars'] ?? $defaults['max_subject_chars']);
        $sanitized['max_message_chars'] = absint($input['max_message_chars'] ?? $defaults['max_message_chars']);

        $sanitized['min_name_words']    = absint($input['min_name_words'] ?? $defaults['min_name_words']);
        $sanitized['min_subject_words'] = absint($input['min_subject_words'] ?? $defaults['min_subject_words']);
        $sanitized['min_message_words'] = absint($input['min_message_words'] ?? $defaults['min_message_words']);

        // File types: from multi-select, validated against WordPress MIME map
        if (!empty($input['allowed_file_types'])) {
            // Handle both array (normal form submission) and array with empty string (AJAX with FormData)
            $file_types = $input['allowed_file_types'];
            
            // If it's a string, convert to array
            if (is_string($file_types)) {
                $file_types = array_filter(array_map('trim', explode(',', $file_types)));
                if (empty($file_types)) {
                    $file_types = []; // Treat empty string as "no types selected"
                }
            }
            
            // If it's an array, process it
            if (is_array($file_types) && !empty($file_types)) {
                // Filter out empty values from FormData
                $file_types = array_filter(array_map('trim', $file_types), fn($v) => $v !== '');
                
                if (!empty($file_types)) {
                    $all_mimes = get_allowed_mime_types();
                    $allowed_extensions = [];
                    foreach ($all_mimes as $ext_string => $mime) {
                        $exts = explode('|', $ext_string);
                        $allowed_extensions = array_merge($allowed_extensions, $exts);
                    }
                    $types = array_map('strtolower', $file_types);
                    $types = array_intersect($types, $allowed_extensions);
                    $sanitized['allowed_file_types'] = implode(',', $types);
                } else {
                    // User submitted form but selected nothing
                    $sanitized['allowed_file_types'] = '';
                }
            } else {
                // User submitted form but selected nothing
                $sanitized['allowed_file_types'] = '';
            }
        } else {
            $sanitized['allowed_file_types'] = $defaults['allowed_file_types'];
        }

        // File size: enforce minimum 1 MB
        $size = absint($input['max_file_size'] ?? $defaults['max_file_size']);
        $sanitized['max_file_size'] = $size > 0 ? $size : $defaults['max_file_size'];

        // Rate Limits: enforce minimum 1 request per period
        $sanitized['rate_limit_per_minute'] = max( 1, absint( $input['rate_limit_per_minute'] ?? $defaults['rate_limit_per_minute'] ) );
        $sanitized['rate_limit_per_hour']   = max( 1, absint( $input['rate_limit_per_hour'] ?? $defaults['rate_limit_per_hour'] ) );
        $sanitized['rate_limit_per_day']    = max( 1, absint( $input['rate_limit_per_day'] ?? $defaults['rate_limit_per_day'] ) );

        // Intent Classification
        $sanitized['intent_enable'] = self::normalize_checkbox_value($input['intent_enable'] ?? $existing['intent_enable'] ?? $defaults['intent_enable'] ?? false);

        // Encrypt SMTP password only if it's a new plaintext password (not already encrypted or placeholder)
        // Encrypted passwords contain "::" separator from base64_iv encoding
        $incoming_pass = (string) ($sanitized['smtp_pass'] ?? '');
        $existing_pass = (string) ($existing['smtp_pass'] ?? $defaults['smtp_pass']);

        $decoded_pass = base64_decode($incoming_pass, true);
        $looks_encrypted = false;
        if (strpos($incoming_pass, '::') !== false) {
            $looks_encrypted = true;
        } elseif ($decoded_pass !== false && strpos($decoded_pass, '::') !== false) {
            $looks_encrypted = true;
        }

        if ($incoming_pass === '' || $incoming_pass === '******') {
            $sanitized['smtp_pass'] = $existing_pass;
        } elseif (!$looks_encrypted) {
            $sanitized['smtp_pass'] = self::instance()->encrypt_password($incoming_pass);
        } else {
            // Already encrypted payload – keep as-is.
            $sanitized['smtp_pass'] = $incoming_pass;
        }

        return $sanitized;
    }

    private static function extract_domain(?string $email): string {
        $email = $email ? trim((string) $email) : '';
        if ($email === '' || strpos($email, '@') === false) {
            return '';
        }

        $parts = explode('@', $email);
        $domain = strtolower(array_pop($parts));

        return is_string($domain) ? $domain : '';
    }

    /**
     * Sanitize webhooks array.
     */
    private static function sanitize_webhooks(array $webhooks): array {
        $sanitized = [];
        foreach ($webhooks as $id => $webhook) {
            if (!is_array($webhook)) continue;
            $sanitized[$id] = [
                'id'      => sanitize_text_field($webhook['id'] ?? ''),
                'url'     => esc_url_raw($webhook['url'] ?? ''),
                'events'  => array_map('sanitize_text_field', $webhook['events'] ?? []),
                'secret'  => sanitize_text_field($webhook['secret'] ?? ''),
                'created' => sanitize_text_field($webhook['created'] ?? ''),
                'active'  => self::normalize_checkbox_value($webhook['active'] ?? false),
            ];
        }
        return $sanitized;
    }

    /**
     * Encrypt SMTP password (AES-256-CBC).
     */
    private function encrypt_password(string $password): string {
        $key = substr(hash('sha256', wp_salt('nonce')), 0, 32);
        $iv  = substr(hash('sha256', wp_salt('auth')), 0, 16);

        return base64_encode(
            openssl_encrypt($password, 'AES-256-CBC', $key, 0, $iv)
            . '::' . base64_encode($iv)
        );
    }

    /**
     * Decrypt SMTP password.
     */
    public static function decrypt_password(string $encrypted): string {
        if ($encrypted === '') {
            return '';
        }

        $payload = $encrypted;
        if (strpos($payload, '::') === false) {
            $maybe = base64_decode($payload, true);
            if ($maybe !== false && strpos($maybe, '::') !== false) {
                $payload = $maybe;
            } else {
                return $encrypted; // fallback for legacy plaintext
            }
        }

        [$data, $iv] = explode('::', $payload, 2);
        $key = substr(hash('sha256', wp_salt('nonce')), 0, 32);
        $iv  = base64_decode($iv);

        return openssl_decrypt($data, 'AES-256-CBC', $key, 0, $iv) ?: '';
    }

    /**
     * Check if a specific setting is enabled (truthy).
     *
     * @param string $key Setting key to check.
     * @return bool True if enabled, false otherwise.
     */
    public static function is_setting_enabled(string $key): bool {
        $settings = self::get_settings();
        return !empty($settings[$key]);
    }

    /**
     * Normalize checkbox / toggle payloads coming from mixed sources.
     */
    private static function normalize_checkbox_value($value): bool {
        if (is_array($value)) {
            $value = end($value);
        }

        if ($value === null) {
            return false;
        }

        $filtered = filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);

        return $filtered ?? false;
    }
}
