<?php
declare(strict_types=1);

namespace ContactInbox\Admin\Pages;

use ContactInbox\Traits\Singleton;
use ContactInbox\Core\Config;
use ContactInbox\Admin\Traits\{
    SettingsPageRenderer,
    SettingsSaver,
    SmtpTester,
    CronManager,
    IntentSettingsTrait
};

// phpcs:disable WordPress.WP.I18n.NonSingularStringLiteralDomain, WordPress.Security.NonceVerification.Recommended

if (!defined('ABSPATH')) {
    exit;
}

final class Settings {
    use Singleton;
    use SettingsPageRenderer;
    use SettingsSaver;
    use SmtpTester;
    use CronManager;
    use IntentSettingsTrait;

    protected function __construct() {
        add_action('admin_init', [$this, 'register_settings']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_assets']);
        add_action('wp_ajax_' . Config::AJAX_SAVE_SETTINGS,     [$this, 'ajax_save']);
        add_action('wp_ajax_' . Config::AJAX_TEST_SMTP,         [$this, 'ajax_test_smtp']);
        add_action('wp_ajax_' . Config::AJAX_CHECK_SMTP_RESULT, [$this, 'ajax_check_smtp_result']);
        add_action('wp_ajax_ci_run_cron_now',                  [$this, 'ajax_run_cron_now']);
        add_action('wp_ajax_ci_update_cron_interval',          [$this, 'ajax_update_cron_interval']);
        add_action('wp_ajax_cin_toggle_smtp',                   [$this, 'ajax_toggle_smtp']);
        add_action('wp_ajax_cin_toggle_subject',                [$this, 'ajax_toggle_subject']);
        add_action('wp_ajax_cin_toggle_salutation',             [$this, 'ajax_toggle_salutation']);
        add_action('wp_ajax_cin_toggle_attachment',             [$this, 'ajax_toggle_attachment']);
        add_action('wp_ajax_ci_reclassify_message',             [$this, 'ajax_reclassify_message']);
    }

    /**
     * Ensure settings assets are enqueued on the Settings page.
     */
    public function enqueue_assets(string $hook = ''): void {
        $page = isset($_GET['page']) ? sanitize_key((string) $_GET['page']) : '';
        $is_settings_page = in_array($page, [Config::MENU_SETTINGS, 'contactin-settings'], true);

        if (!$is_settings_page) {
            return;
        }

        // If dispatcher already enqueued assets, avoid duplicates.
        if (wp_script_is('contactin-admin-settings', 'enqueued')) {
            return;
        }

        // Fallback: ensure global + settings assets are enqueued for settings page.
        \ContactInbox\Admin\AssetsDispatcher::instance()->dispatch($hook);
    }

    /**
     * AJAX handler: Toggle SMTP enable/disable
     */
    public function ajax_toggle_smtp(): void {
        check_ajax_referer(Config::SETTINGS_NONCE_ACTION, 'nonce');
        
        if (!current_user_can(Config::CAPABILITY)) {
            wp_send_json_error(['message' => __('Permission denied.',  'contactin')]);
        }
        
        $enabled = isset($_POST['enabled']) ? (int)$_POST['enabled'] : 0;
        $settings = \ContactInbox\Core\Settings::get_settings();
        $settings['smtp_enable'] = (bool)$enabled;
        
        // If disabling SMTP, also disable email notifications
        if (!$enabled) {
            $settings['send_admin_notification'] = false;
            $settings['send_user_copy'] = false;
        }
        
        \ContactInbox\Core\Settings::update_settings($settings);
        
        wp_send_json_success([
            'message' => $enabled ? __('SMTP enabled.',  'contactin') : __('SMTP disabled.',  'contactin'),
            'enabled' => $enabled
        ]);
    }

    /**
     * AJAX handler: Toggle subject field enable/disable
     */
    public function ajax_toggle_subject(): void {
        check_ajax_referer(Config::SETTINGS_NONCE_ACTION, 'nonce');
        
        if (!current_user_can(Config::CAPABILITY)) {
            wp_send_json_error(['message' => __('Permission denied.',  'contactin')]);
        }
        
        $enabled = isset($_POST['enabled']) ? (int)$_POST['enabled'] : 0;
        $settings = \ContactInbox\Core\Settings::get_settings();
        $settings['form_enable_subject'] = (bool)$enabled;
        
        \ContactInbox\Core\Settings::update_settings($settings);
        
        wp_send_json_success([
            'message' => $enabled ? __('Subject field enabled.',  'contactin') : __('Subject field disabled.',  'contactin'),
            'enabled' => $enabled
        ]);
    }

    /**
     * AJAX handler: Toggle salutation field enable/disable
     */
    public function ajax_toggle_salutation(): void {
        check_ajax_referer(Config::SETTINGS_NONCE_ACTION, 'nonce');
        
        if (!current_user_can(Config::CAPABILITY)) {
            wp_send_json_error(['message' => __('Permission denied.',  'contactin')]);
        }
        
        $enabled = isset($_POST['enabled']) ? (int)$_POST['enabled'] : 0;
        $settings = \ContactInbox\Core\Settings::get_settings();
        $settings['form_enable_salutation'] = (bool)$enabled;
        
        \ContactInbox\Core\Settings::update_settings($settings);
        
        wp_send_json_success([
            'message' => $enabled ? __('Salutation field enabled.',  'contactin') : __('Salutation field disabled.',  'contactin'),
            'enabled' => $enabled
        ]);
    }

    /**
     * AJAX handler: Toggle file attachment enable/disable
     */
    public function ajax_toggle_attachment(): void {
        check_ajax_referer(Config::SETTINGS_NONCE_ACTION, 'nonce');
        
        if (!current_user_can(Config::CAPABILITY)) {
            wp_send_json_error(['message' => __('Permission denied.',  'contactin')]);
        }

        if ( ! \ContactInbox\Integration\FreemiusIntegration::can_use_premium_features() ) {
            $settings = \ContactInbox\Core\Settings::get_settings();
            $settings['form_enable_attachment'] = false;
            \ContactInbox\Core\Settings::update_settings($settings);

            wp_send_json_error([
                'message' => __('File upload is a premium feature and is unavailable while your license is inactive.',  'contactin'),
                'enabled' => 0,
            ]);
        }
        
        $enabled = isset($_POST['enabled']) ? (int)$_POST['enabled'] : 0;
        $settings = \ContactInbox\Core\Settings::get_settings();
        $settings['form_enable_attachment'] = (bool)$enabled;
        
        \ContactInbox\Core\Settings::update_settings($settings);
        
        wp_send_json_success([
            'message' => $enabled ? __('File attachment enabled.',  'contactin') : __('File attachment disabled.',  'contactin'),
            'enabled' => $enabled
        ]);
    }

}

