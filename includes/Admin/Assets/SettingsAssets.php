<?php
declare(strict_types=1);

namespace ContactInbox\Admin\Assets;

use ContactInbox\Core\Config;

if (!defined('ABSPATH')) {
    exit;
}

final class SettingsAssets {
    use AssetHelpers;

    /**
     * Enqueue Settings page assets.
     */
    public function enqueue(): void {
            // Ensure jQuery is loaded for all admin settings scripts
            wp_enqueue_script('jquery');
        // (Removed) admin-settings-autosave.js is deprecated and not enqueued.
        $handle = 'contactin-admin-settings';

        // CSS: dist/css/admin-settings.min.css
        $this->register_style($handle, 'admin-settings.min.css');

        // JS: dist/js/admin-settings.min.js (depends on global helpers)
        $this->register_script($handle, 'admin-settings.min.js', ['jquery', 'contactin-admin-global']);

        // Localize script with nonces and translations
        wp_localize_script($handle, 'contactinbox_admin', [
            'ajaxurl'    => admin_url('admin-ajax.php'),
            'nonce'      => wp_create_nonce(Config::SETTINGS_NONCE_ACTION), // For toggle actions
            'nonce_save' => wp_create_nonce(Config::SETTINGS_NONCE_ACTION),
            'nonce_smtp' => wp_create_nonce(Config::SMTP_TEST_NONCE_ACTION),
            'i18n'       => [
                'messages' => [
                    'save_success' => __('Settings saved successfully.', Config::TEXTDOMAIN),
                    'save_error'   => __('Failed to save settings.', Config::TEXTDOMAIN),
                    'smtp_success' => __('SMTP test email sent successfully.', Config::TEXTDOMAIN),
                    'smtp_error'   => __('SMTP test failed.', Config::TEXTDOMAIN),
                ],
                'message_box' => [
                    'header'       => __('Settings Notice', Config::TEXTDOMAIN),
                    'footer_close' => __('Close', Config::TEXTDOMAIN),
                ],
            ],
        ]);
    }
}
