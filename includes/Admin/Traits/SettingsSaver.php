<?php
declare(strict_types=1);

namespace ContactInbox\Admin\Traits;

use ContactInbox\Core\Config;
use ContactInbox\Core\Settings as CoreSettings;
use ContactInbox\Core\BusinessPatterns;

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
                'message'  => __('Permission denied.',  'contactin'),
                'settings' => CoreSettings::get_settings(),
            ]);
        }

        $input     = wp_unslash($_POST);
        
        // Extract business_type before sanitizing (it's stored separately)
        $business_type = isset($input['business_type']) ? sanitize_text_field($input['business_type']) : 'generic';
        $valid_types = array_keys(BusinessPatterns::get_business_types());
        if (!in_array($business_type, $valid_types, true)) {
            $business_type = 'generic';
        }
        
        // Remove business_type from input before sanitizing (won't be in main settings)
        unset($input['business_type']);
        
        $sanitized = CoreSettings::sanitize_settings($input);
        $existing  = CoreSettings::get_settings();

        if ($sanitized === $existing && get_option('contactin_business_type', 'generic') === $business_type) {
            wp_send_json_error([
                'code'     => 'no_changes',
                'message'  => __('No changes detected.',  'contactin'),
                'settings' => $existing,
            ]);
        }

        CoreSettings::update_settings($sanitized);
        
        // Save business_type separately
        update_option('contactin_business_type', $business_type, true);

        do_action('contactin_after_save_settings', $sanitized);

        wp_send_json_success([
            'code'     => 'saved',
            'message'  => __('Settings saved successfully.',  'contactin'),
            'settings' => $sanitized,
            'business_type' => $business_type,
        ]);
    }
}
