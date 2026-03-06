<?php
declare(strict_types=1);

namespace ContactInbox\Admin\Traits;

use ContactInbox\Core\Config;
use ContactInbox\Core\SMTP;
use ContactInbox\Core\Settings;

if (!defined('ABSPATH')) {
    exit;
}

trait SmtpTester {
    private function post_text(string $key, string $default = ''): string {
        $value = filter_input(INPUT_POST, $key, FILTER_UNSAFE_RAW);
        if (null === $value || false === $value) {
            return $default;
        }
        return sanitize_text_field(wp_unslash((string) $value));
    }

    private function post_key(string $key, string $default = ''): string {
        $value = filter_input(INPUT_POST, $key, FILTER_UNSAFE_RAW);
        if (null === $value || false === $value) {
            return $default;
        }
        return sanitize_key(wp_unslash((string) $value));
    }

    private function post_email(string $key, string $default = ''): string {
        $value = filter_input(INPUT_POST, $key, FILTER_UNSAFE_RAW);
        if (null === $value || false === $value) {
            return $default;
        }
        return sanitize_email(wp_unslash((string) $value));
    }

    public function ajax_test_smtp(): void {
        check_ajax_referer(Config::SMTP_TEST_NONCE_ACTION, 'nonce');

        if (!current_user_can(Config::CAPABILITY)) {
            wp_send_json_error(['code' => 'permission_denied', 'message' => __('Permission denied.', 'contact-inbox')]);
        }

        $to_raw          = $this->post_email('to');
        $admin_email_raw = $this->post_email('admin_email');
        $to              = $to_raw !== '' ? $to_raw : ($admin_email_raw !== '' ? $admin_email_raw : (string) get_option('admin_email'));
        if (!is_email($to)) {
            wp_send_json_error(['code' => 'invalid_email', 'message' => __('Invalid test email address.', 'contact-inbox')]);
        }

        $enc_raw   = $this->post_key('smtp_encryption');
        $enc_alt   = $this->post_key('enc');
        $enc_input = $enc_raw !== '' ? $enc_raw : $enc_alt;
        $enc       = in_array($enc_input, ['ssl', 'tls', 'none', 'auto'], true) ? $enc_input : 'none';
        $port_raw   = $this->post_text('smtp_port');
        $port_alt   = $this->post_text('port');
        $port_input = $port_raw !== '' ? $port_raw : ($port_alt !== '' ? $port_alt : 587);
        $port       = absint($port_input);
        if ($port < 1 || $port > 65535) {
            wp_send_json_error(['code' => 'invalid_port', 'message' => __('Invalid SMTP port.', 'contact-inbox')]);
        }

        $existing_settings = get_option(Config::OPTION_SETTINGS, []);
        if (!is_array($existing_settings)) {
            $existing_settings = [];
        }

        $override = [
            'smtp_host'       => $this->post_text('smtp_host', $this->post_text('host')),
            'smtp_port'       => $port,
            'smtp_user'       => $this->post_text('smtp_user', $this->post_text('username')),
            'smtp_pass'       => $this->post_text('smtp_pass', $this->post_text('password')),
            'smtp_encryption' => $enc,
            'smtp_from_email' => $this->post_email('smtp_from_email', $this->post_email('from_email', $to)),
            'smtp_from_name'  => $this->post_text('smtp_from_name', $this->post_text('from_name', get_bloginfo('name'))),
        ];

        if ($override['smtp_pass'] === '' && !empty($existing_settings['smtp_pass'])) {
            $override['smtp_pass'] = (string) $existing_settings['smtp_pass'];
        }

        $result = SMTP::test_smtp($to, $override, true);

        if ($result['success']) {
            $merged = array_merge($existing_settings, $override);
            $merged['smtp_enable'] = 1;

            $sanitized = Settings::sanitize_settings($merged);
            Settings::update_settings($sanitized);

            wp_send_json_success([
                'code'    => 'smtp_success',
                'message' => $result['message'],
            ]);
        }

        wp_send_json_error([
            'code'    => 'smtp_failed',
            'message' => $result['message'],
        ]);
    }

    public function ajax_check_smtp_result(): void {
        check_ajax_referer(Config::SMTP_TEST_NONCE_ACTION, 'nonce');

        if (!current_user_can(Config::CAPABILITY)) {
            wp_send_json_error(['code' => 'permission_denied', 'message' => __('Permission denied.', 'contact-inbox')]);
        }

        $key = $this->post_text('key');
        if ($key === '') {
            wp_send_json_error(['code' => 'missing_key', 'message' => __('Missing key.', 'contact-inbox')]);
        }

        if (!apply_filters('contactinbox_allow_smtp_poll', true, $key)) {
            wp_send_json_error(['code' => 'poll_limited', 'message' => __('Polling limited. Please wait a moment.', 'contact-inbox')]);
        }

        $result = get_transient($key);
        if ($result) {
            do_action('contactinbox_smtp_result_checked', $key, $result);
            wp_send_json_success([
                'code'    => 'smtp_result_ready',
                'message' => __('SMTP result available.', 'contact-inbox'),
                'result'  => $result,
            ]);
        }

        wp_send_json_error(['code' => 'smtp_result_pending', 'message' => __('No result yet.', 'contact-inbox')]);
    }
}
