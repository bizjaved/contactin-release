<?php
declare(strict_types=1);

namespace ContactInbox\Admin\Assets;

use ContactInbox\Core\Config;

// phpcs:disable WordPress.WP.I18n.NonSingularStringLiteralDomain

if (!defined('ABSPATH')) {
    exit;
}

final class SettingsAssets {
    use AssetHelpers;

    /**
     * Enqueue Settings page assets.
     */
    public function enqueue(): void {
        // (Removed) admin-settings-autosave.js is deprecated and not enqueued.
        $handle = 'contactin-admin-settings';

        // Ensure wp-pointer (script + style) is available on the settings page.
        // Freemius outputs an inline <script> that calls jQuery .pointer() when
        // the plugin opt-in state is pending. Without wp-pointer enqueued,
        // that call throws: "Uncaught TypeError: $(...).pointer is not a function".
        wp_enqueue_script('wp-pointer');
        wp_enqueue_style('wp-pointer');

        // CSS: dist/css/admin-settings.min.css
        $this->register_style($handle, 'admin-settings.min.css');

        // JS: dist/js/admin-settings.min.js (depends on global helpers + wp-pointer)
        $this->register_script($handle, 'admin-settings.min.js', ['jquery', 'contactin-admin-global', 'wp-pointer']);

        // Localize script with nonces and translations
        $settings_for_js = \ContactInbox\Core\Settings::get_settings();
        wp_localize_script($handle, 'contactinbox_admin', [
            'ajaxurl'    => admin_url('admin-ajax.php'),
            'nonce'      => wp_create_nonce(Config::SETTINGS_NONCE_ACTION),
            'nonce_save' => wp_create_nonce(Config::SETTINGS_NONCE_ACTION),
            'nonce_smtp' => wp_create_nonce(Config::SMTP_TEST_NONCE_ACTION),
            'nonce_cron' => wp_create_nonce('ci_cron_action'),
            // Whether the global File Attachment switch is currently on and the license allows it.
            // Used by the profile editor JS to apply the global ceiling on the per-profile toggle.
            'global_attachment_enabled' => ! empty( $settings_for_js['form_enable_attachment'] ) ? '1' : '0',
            'i18n'       => [
                'messages' => [
                    'save_success'        => __('Settings saved successfully.',  'contactin'),
                    'save_error'          => __('An error occurred while saving settings',  'contactin'),
                    'save_network_error'  => __('Network error occurred while saving',  'contactin'),
                    'smtp_success'        => __('SMTP test email sent successfully.',  'contactin'),
                    'smtp_error'          => __('SMTP test failed.',  'contactin'),
                    'search_no_matches'   => __('No matches found',  'contactin'),
                ],
                'confirm' => [
                    'default_title'   => __('Confirm',  'contactin'),
                    'default_proceed' => __('I understand, proceed',  'contactin'),
                    'default_cancel'  => __('Cancel',  'contactin'),
                ],
                'smtp_disable' => [
                    'title'         => __('Disable SMTP?',  'contactin'),
                    'message_intro' => __('Disabling SMTP will affect all outgoing email from this plugin.',  'contactin'),
                    'bullet_1'      => __('Admin notification emails will stop sending.',  'contactin'),
                    'bullet_2'      => __('User confirmation emails will stop sending.',  'contactin'),
                    'bullet_3'      => __('WordPress will fall back to PHP mail(), which is often blocked or marked as spam.',  'contactin'),
                    'badge'         => __('Email Impact',  'contactin'),
                    'confirm_label' => __('Yes, disable SMTP',  'contactin'),
                    'cancel_label'  => __('Keep SMTP enabled',  'contactin'),
                ],
                'smtp_enabled_notice' => [
                    'title'      => __('SMTP Enabled Successfully!',  'contactin'),
                    'body'       => __('Important: SMTP only handles email delivery. To send notifications, please enable them in the',  'contactin'),
                    'tab_link'   => __('Notifications tab',  'contactin'),
                    'admin_hint' => __('Enable "Send notification to admin" for admin alerts',  'contactin'),
                    'user_hint'  => __('Enable "Send confirmation to user" for user receipts',  'contactin'),
                ],
                'attachment_disable' => [
                    'title'         => __('Disable File Uploads Globally?',  'contactin'),
                    'message_intro' => __('This is a site-wide security lock, not a form-level setting.',  'contactin'),
                    'bullet_1'      => __('File uploads will be disabled across every contact form on this site.',  'contactin'),
                    'bullet_2'      => __('Individual Form Profiles cannot override this, it is a hard security lock.',  'contactin'),
                    'bullet_3'      => __('You can re-enable file uploads at any time from this setting.',  'contactin'),
                    'badge'         => __('Security Lock',  'contactin'),
                    'confirm_label' => __('Yes, disable globally',  'contactin'),
                    'cancel_label'  => __('Cancel',  'contactin'),
                ],
                'clear_file_types' => [
                    'title'         => __('Clear all allowed file types?',  'contactin'),
                    'message'       => __('This will remove every allowed file type from the list. Users will be unable to upload any file even if file attachment is globally enabled.',  'contactin'),
                    'message_2'     => __('You can restore recommended types with the "Select Recommended Types" button.',  'contactin'),
                    'badge'         => __('Destructive',  'contactin'),
                    'confirm_label' => __('Yes, clear all',  'contactin'),
                    'cancel_label'  => __('Cancel',  'contactin'),
                ],
                'recaptcha_disable' => [
                    'title'         => __('Disable reCAPTCHA?',  'contactin'),
                    'message_intro' => __('Removing bot protection exposes your contact form to automated spam.',  'contactin'),
                    'bullet_1'      => __('Spam volume typically increases significantly without reCAPTCHA.',  'contactin'),
                    'bullet_2'      => __('Rate limiting will still apply, but cannot prevent all automated submissions.',  'contactin'),
                    'badge'         => __('Security Risk',  'contactin'),
                    'confirm_label' => __('Yes, disable reCAPTCHA',  'contactin'),
                    'cancel_label'  => __('Keep reCAPTCHA enabled',  'contactin'),
                ],
                'ip_allowlist_enable' => [
                    'title'         => __('Enable IP Allowlist Mode?',  'contactin'),
                    'message_intro' => __('Only IP addresses in the allowlist will be able to submit the contact form.',  'contactin'),
                    'bullet_1'      => __('Every visitor NOT on the list will receive a blocked response — including legitimate users.',  'contactin'),
                    'bullet_2'      => __('Ensure your IP Allowlist below is correct and complete before saving.',  'contactin'),
                    'bullet_3'      => __('Dynamic IPs (mobile, home broadband) change frequently — this setting is best for controlled environments.',  'contactin'),
                    'badge'         => __('Lockout Risk',  'contactin'),
                    'confirm_label' => __('Yes, restrict to allowlist',  'contactin'),
                    'cancel_label'  => __('Cancel',  'contactin'),
                ],
                'rate_limit_advisory' => __('⚠ This value is very low and may block legitimate visitors.',  'contactin'),
                'profiles' => [
                    'no_profiles'        => __('No profiles yet. Click "+ New Profile" to create one.',  'contactin'),
                    'new_profile'        => __('New Profile',  'contactin'),
                    'edit_profile'       => __('Edit Profile',  'contactin'),
                    'slug_required'      => __('Slug is required.',  'contactin'),
                    'profile_saved'      => __('Profile saved.',  'contactin'),
                    'save_failed'        => __('Save failed.',  'contactin'),
                    'delete_confirm'     => __('Delete this form profile? This cannot be undone.',  'contactin'),
                    'delete_failed'      => __('Could not delete profile.',  'contactin'),
                    'notify_email_label' => __('Notification email',  'contactin'),
                    'attachment_label'   => __('File attachment',  'contactin'),
                ],
                'message_box' => [
                    'header'       => __('Settings Notice',  'contactin'),
                    'footer_close' => __('Close',  'contactin'),
                ],
            ],
        ]);
    }
}
