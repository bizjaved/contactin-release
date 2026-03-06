<?php
declare(strict_types=1);

namespace ContactInbox\Admin\Traits;

use ContactInbox\Core\Config;
use ContactInbox\Core\Settings as CoreSettings;

if (!defined('ABSPATH')) {
    exit;
}   

trait SettingsSaver {
    public function ajax_save(): void {
       check_ajax_referer(Config::SETTINGS_NONCE_ACTION, 'nonce');
        // Capability check
        if (!current_user_can(Config::CAPABILITY)) {
            wp_send_json_error([
                'code'     => 'permission_denied',
                'message'  => __('Permission denied.', 'contact-inbox'),
                'settings' => CoreSettings::get_settings(),
            ]);
        }

        $input     = wp_unslash($_POST);
        $sanitized = CoreSettings::sanitize_settings($input);
        $existing  = CoreSettings::get_settings();

        if ($sanitized === $existing) {
            wp_send_json_error([
                'code'     => 'no_changes',
                'message'  => __('No changes detected.', 'contact-inbox'),
                'settings' => $existing,
            ]);
        }

        CoreSettings::update_settings($sanitized);

        do_action('contactin_after_save_settings', $sanitized);

        wp_send_json_success([
            'code'     => 'saved',
            'message'  => __('Settings saved successfully.', 'contact-inbox'),
            'settings' => $sanitized,
        ]);
    }
}
