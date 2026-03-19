<?php
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals
/**
 * Template: Admin Settings Page
 * File: templates/admin/settings-page.php
 */

if (!defined('ABSPATH')) exit;
use ContactInbox\Core\Config;
use ContactInbox\Core\Settings;
use ContactInbox\Admin\Helpers\UpgradeModalHelper;

// Define plugin path and URL constants
if ( ! defined( 'SCH_PATH' ) ) {
    define( 'SCH_PATH', plugin_dir_path( __FILE__ ) );
}

// Use the centralized option name from Config for consistency
$settings = get_option( Config::OPTION_SETTINGS, [] );
$defaults = Settings::instance()->get_default_settings();

$smtp_from_email   = isset($settings['smtp_from_email']) && $settings['smtp_from_email'] !== ''
    ? sanitize_email($settings['smtp_from_email'])
    : '';
$smtp_from_name    = isset($settings['smtp_from_name']) ? sanitize_text_field($settings['smtp_from_name']) : '';
$smtp_user         = isset($settings['smtp_user']) ? sanitize_text_field($settings['smtp_user']) : '';
$admin_fallback    = get_option('admin_email');
$settings_admin    = isset($settings['admin_email']) && $settings['admin_email'] !== '' ? $settings['admin_email'] : $admin_fallback;

$extract_domain = static function (?string $email): string {
    $email = $email ? trim($email) : '';
    if ($email === '' || strpos($email, '@') === false) {
        return '';
    }
    $parts = explode('@', $email);
    return strtolower(array_pop($parts));
};

$smtp_domain    = $extract_domain($smtp_from_email !== '' ? $smtp_from_email : $smtp_user);
$admin_domain   = $extract_domain($settings_admin);
$smtp_domain_display = $smtp_domain !== '' ? $smtp_domain : $extract_domain($smtp_from_email ?: $smtp_user);
$should_warn_sender_mismatch = !empty($smtp_domain) && !empty($admin_domain) && $smtp_domain !== $admin_domain;
?>


<div class="wrap">
    <div class="cin-settings-header-wrapper">
        <h1 class="cin-settings-title"><?php esc_html_e('Settings', 'contact-inbox'); ?></h1>
        <button type="button" class="button button-secondary cin-settings-help-button" data-cin-help-open="cin-help-modal" aria-haspopup="dialog" aria-controls="cin-help-modal">
            <span class="cin-settings-help-icon">ℹ️</span><?php esc_html_e('Help', 'contact-inbox'); ?>
        </button>
    </div>

    <!-- Global Settings Notice Area -->
    <div id="cin-global-settings-notice" class="notice cin-hidden">
        <button type="button" class="notice-dismiss cin-notice-dismiss" aria-label="<?php esc_attr_e('Dismiss this notice.', 'contact-inbox'); ?>">
            <span class="screen-reader-text"><?php esc_html_e('Dismiss this notice.', 'contact-inbox'); ?></span>
        </button>
        <p id="cin-notice-message"></p>
    </div>

    <form id="contactin-settings-form" method="post">
        <input type="hidden" name="action" value="<?php echo esc_attr(Config::AJAX_SAVE_SETTINGS); ?>">
        <?php wp_nonce_field(Config::SETTINGS_NONCE_ACTION, 'nonce'); ?>

        <!-- Search Box -->
        <div class="cin-settings-search-box">
            <span class="cin-settings-search-icon">🔍</span>
            <input type="search" id="cin-settings-search" class="cin-settings-search-input" placeholder="<?php esc_attr_e('Search settings by name or keyword...', 'contact-inbox'); ?>" aria-label="<?php esc_attr_e('Search settings', 'contact-inbox'); ?>" />
            <span id="cin-search-results" class="cin-hidden cin-settings-search-results"><?php esc_html_e('No matches', 'contact-inbox'); ?></span>
            <button type="button" id="cin-clear-search" class="cin-hidden button button-small cin-settings-clear-btn" aria-label="<?php esc_attr_e('Clear search', 'contact-inbox'); ?>">✕</button>
        </div>

        <!-- Tab Navigation -->
        <div class="nav-tab-wrapper">
            <a href="#cin-tab-general" class="nav-tab nav-tab-active" data-tab="general"><?php esc_html_e('General', 'contact-inbox'); ?></a>
            <a href="#cin-tab-recaptcha" class="nav-tab" data-tab="recaptcha">reCAPTCHA</a>
            <a href="#cin-tab-smtp" class="nav-tab" data-tab="smtp">SMTP</a>
            <a href="#cin-tab-notifications" class="nav-tab" data-tab="notifications"><?php esc_html_e('Notifications', 'contact-inbox'); ?></a>
            <a href="#cin-tab-form" class="nav-tab" data-tab="form"><?php esc_html_e('Form', 'contact-inbox'); ?></a>
            <a href="#cin-tab-advanced" class="nav-tab" data-tab="advanced"><?php esc_html_e('Advanced', 'contact-inbox'); ?></a>
        </div>


        <!-- Tab: General -->
        <div id="cin-tab-general" class="cin-tab-content is-active">
            <h3><?php esc_html_e('General Settings', 'contact-inbox'); ?></h3>
            <table class="form-table" role="presentation">
                <tr>
                    <th scope="row"><label for="privacy_url"><?php esc_html_e('Privacy Policy URL', 'contact-inbox'); ?></label></th>
                    <td><input name="privacy_url" type="url" id="privacy_url" value="<?php echo esc_url($settings['privacy_url'] ?? get_privacy_policy_url()); ?>" class="large-text" data-search="privacy policy url" /></td>
                </tr>
                <tr>
                    <th scope="row"><label for="consent_text"><?php esc_html_e('Consent Text', 'contact-inbox'); ?></label></th>
                    <td><textarea name="consent_text" id="consent_text" rows="3" class="large-text" data-search="consent text"><?php echo esc_textarea($settings['consent_text'] ?? 'I consent to data processing as per Privacy Policy.'); ?></textarea></td>
                </tr>
                <tr>
                    <th scope="row"><label for="success_message"><?php esc_html_e('Success Message', 'contact-inbox'); ?></label></th>
                    <td><textarea name="success_message" id="success_message" rows="3" class="large-text" data-search="success message"><?php echo esc_textarea($settings['success_message'] ?? 'Thank you! Your message has been sent.'); ?></textarea></td>
                </tr>
                <tr>
                    <th scope="row"><?php esc_html_e('Confetti on Success', 'contact-inbox'); ?></th>
                    <td>
                        <fieldset>
                            <legend class="screen-reader-text"><span><?php esc_html_e('Confetti on Success', 'contact-inbox'); ?></span></legend>
                            <input type="hidden" name="confetti_enable" value="0" />
                            <div class="cin-flex-center-gap">
                                <input id="confetti-enable-checkbox" name="confetti_enable" type="checkbox" value="1" <?php checked(!empty($settings['confetti_enable'])); ?> data-search="confetti" class="cin-cursor-pointer" style="width: 20px; height: 20px; min-width: 20px; min-height: 20px; pointer-events: auto !important; opacity: 1 !important; visibility: visible !important; accent-color: #2271b1; position: relative; z-index: 1000;" />
                                <label for="confetti-enable-checkbox" class="cin-cursor-pointer cin-m-0"><?php esc_html_e('Show confetti animation', 'contact-inbox'); ?></label>
                            </div>
                            <!-- Confetti checkbox JS moved to page footer for best practice -->
                        <!-- Move all inline JS to the footer for best practice and to prevent JS leaking into HTML -->
                        <?php
                        $settings_confetti_inline_js = <<<'JS'
                        (function() {
                            document.addEventListener('DOMContentLoaded', function() {
                                var checkbox = document.getElementById('confetti-enable-checkbox');
                                if (!checkbox) return;
                                var hiddenField = checkbox.closest('fieldset').querySelector('input[type="hidden"][name="confetti_enable"]');
                                if (checkbox && hiddenField) {
                                    checkbox.addEventListener('change', function() {
                                        hiddenField.value = this.checked ? '1' : '0';
                                    });
                                }
                            });
                        })();
                        JS;
                        wp_add_inline_script('contactin-admin-settings', $settings_confetti_inline_js);
                        ?>
                        </fieldset>
                    </td>
                </tr>
            </table>
        </div>

        <!-- Tab: reCAPTCHA -->
        <div id="cin-tab-recaptcha" class="cin-tab-content">
            <h3><?php esc_html_e('reCAPTCHA v3 Configuration', 'contact-inbox'); ?></h3>
            <table class="form-table" role="presentation">
                <tr>
                    <th scope="row"><label for="recaptcha_site_key"><?php esc_html_e('Site Key', 'contact-inbox'); ?></label></th>
                    <td><input name="recaptcha_site_key" type="text" id="recaptcha_site_key" value="<?php echo esc_attr($settings['recaptcha_site_key'] ?? ''); ?>" class="large-text"  data-search="recaptcha site key" /></td>
                </tr>
                <tr>
                    <th scope="row"><label for="recaptcha_secret_key"><?php esc_html_e('Secret Key', 'contact-inbox'); ?></label></th>
                    <td><input name="recaptcha_secret_key" type="text" id="recaptcha_secret_key" value="<?php echo esc_attr($settings['recaptcha_secret_key'] ?? ''); ?>" class="large-text"  data-search="recaptcha secret key" /></td>
                </tr>
                <tr>
                    <th scope="row"><?php esc_html_e('Enable reCAPTCHA', 'contact-inbox'); ?></th>
                    <td>
                        <fieldset>
                            <legend class="screen-reader-text"><span><?php esc_html_e('Enable reCAPTCHA', 'contact-inbox'); ?></span></legend>
                            <input type="hidden" name="recaptcha_enable" value="0" />
                            <label><input name="recaptcha_enable" type="checkbox" value="1" <?php checked(!empty($settings['recaptcha_enable'])); ?> data-search="enable recaptcha" /> <?php esc_html_e('Enable', 'contact-inbox'); ?></label>
                        </fieldset>
                    </td>
                </tr>
            </table>
        </div>

        <!-- Tab: SMTP -->
        <div id="cin-tab-smtp" class="cin-tab-content">
            <h3><?php esc_html_e('SMTP Configuration', 'contact-inbox'); ?></h3>
            <?php if (!empty($settings['smtp_enable'])): ?>
                <div class="notice notice-warning is-dismissible" style="margin:12px 0;<?php echo $should_warn_sender_mismatch ? '' : ' display:none;'; ?>" id="cin-smtp-domain-warning" data-notice-id="smtp-domain-mismatch">
                    <p>
                        <strong><?php esc_html_e('Sender domain mismatch detected.', 'contact-inbox'); ?></strong>
                        <?php
                        /* translators: 1: SMTP sender domain, 2: WordPress admin email domain. */
                        $sender_domain_notice = esc_html__('The configured sender domain (%1$s) differs from the WordPress admin domain (%2$s). Ensure the "From" address belongs to the authenticated SMTP domain to pass SPF, DKIM, and DMARC.', 'contact-inbox');
                        printf(
                            esc_html( $sender_domain_notice ),
                            esc_html($smtp_domain_display ?: '—'),
                            esc_html($admin_domain ?: '—')
                        );
                        ?>
                    </p>
                    <button type="button" class="notice-dismiss cin-notice-dismiss"><span class="screen-reader-text"><?php esc_html_e('Dismiss this notice.', 'contact-inbox'); ?></span></button>
                </div>
            <?php endif; ?>
            <table class="form-table" role="presentation">
                <tr>
                    <th scope="row"><label for="smtp-enable-btn"><?php esc_html_e('Enable SMTP', 'contact-inbox'); ?></label></th>
                    <td>
                        <input type="hidden" name="smtp_enable" id="smtp-enable-hidden" value="<?php echo !empty($settings['smtp_enable']) ? '1' : '0'; ?>" />
                        <button type="button" id="smtp-enable-btn" class="button button-small<?php echo !empty($settings['smtp_enable']) ? ' enabled' : ''; ?>" data-enabled="<?php echo !empty($settings['smtp_enable']) ? '1' : '0'; ?>">
                            <?php echo !empty($settings['smtp_enable']) ? esc_html__('Disable SMTP', 'contact-inbox') : esc_html__('Enable SMTP', 'contact-inbox'); ?>
                        </button>
                        <span id="contactin-smtp-status-label" class="<?php echo !empty($settings['smtp_enable']) ? 'enabled' : 'disabled'; ?> cin-ml-lg">
                            <?php echo !empty($settings['smtp_enable']) ? esc_html__('Enabled', 'contact-inbox') : esc_html__('Disabled', 'contact-inbox'); ?>
                        </span>
                        <span class="description"><?php esc_html_e('Send emails via SMTP instead of WordPress default mail.', 'contact-inbox'); ?></span>
                    </td>
                </tr>
                <tr class="smtp-dependent-field">
                    <th scope="row"><label for="smtp_host"><?php esc_html_e('Host', 'contact-inbox'); ?></label></th>
                    <td><input name="smtp_host" type="text" id="smtp_host" value="<?php echo esc_attr($settings['smtp_host'] ?? ''); ?>" class="large-text"  placeholder="smtp.gmail.com" data-search="smtp host" <?php disabled(empty($settings['smtp_enable'])); ?> /></td>
                </tr>
                <tr class="smtp-dependent-field">
                    <th scope="row"><label for="smtp_port"><?php esc_html_e('Port', 'contact-inbox'); ?></label></th>
                    <td><input name="smtp_port" type="number" id="smtp_port" value="<?php echo esc_attr($settings['smtp_port'] ?? '587'); ?>"  data-search="smtp port" <?php disabled(empty($settings['smtp_enable'])); ?> /></td>
                </tr>
                <tr class="smtp-dependent-field">
                    <th scope="row"><label for="smtp_encryption"><?php esc_html_e('Encryption', 'contact-inbox'); ?></label></th>
                    <td>
                        <select name="smtp_encryption" id="smtp_encryption"  data-search="smtp encryption" <?php disabled(empty($settings['smtp_enable'])); ?>>
                            <option value="none" <?php selected($settings['smtp_encryption'] ?? '', 'none'); ?>><?php esc_html_e('None', 'contact-inbox'); ?></option>
                            <option value="ssl" <?php selected($settings['smtp_encryption'] ?? '', 'ssl'); ?>>SSL</option>
                            <option value="tls" <?php selected($settings['smtp_encryption'] ?? '', 'tls'); ?>>TLS</option>
                        </select>
                    </td>
                </tr>
                <tr class="smtp-dependent-field">
                    <th scope="row"><label for="smtp_user"><?php esc_html_e('Username', 'contact-inbox'); ?></label></th>
                    <td><input name="smtp_user" type="text" id="smtp_user" value="<?php echo esc_attr($settings['smtp_user'] ?? ''); ?>" class="large-text"  autocomplete="username" data-search="smtp username" <?php disabled(empty($settings['smtp_enable'])); ?> /></td>
                </tr>
                <tr class="smtp-dependent-field">
                    <th scope="row"><label for="smtp_pass"><?php esc_html_e('Password', 'contact-inbox'); ?></label></th>
                    <td>
                        <input name="smtp_pass" type="password" id="smtp_pass" value="" class="large-text"  
                            placeholder="<?php echo !empty($settings['smtp_pass']) ? esc_attr(__('Existing password set (leave blank to keep)', 'contact-inbox')) : esc_attr(__('Enter SMTP password', 'contact-inbox')); ?>" 
                            autocomplete="current-password" data-search="smtp password" <?php disabled(empty($settings['smtp_enable'])); ?> />
                        <p class="description">
                            <?php if (!empty($settings['smtp_pass'])) {
                                esc_html_e('Password is already saved. Leave blank to keep existing password, or enter a new one to change it.', 'contact-inbox');
                            } else {
                                esc_html_e('Enter the SMTP password for authentication.', 'contact-inbox');
                            } ?>
                        </p>
                    </td>
                </tr>
                <tr class="smtp-dependent-field">
                    <th scope="row"><label for="smtp_from_email"><?php esc_html_e('Sender Email Address', 'contact-inbox'); ?></label></th>
                    <td>
                        <input name="smtp_from_email" type="email" id="smtp_from_email" value="<?php echo esc_attr($smtp_from_email); ?>" class="large-text" autocomplete="off" data-search="smtp sender email" <?php disabled(empty($settings['smtp_enable'])); ?> />
                        <p class="description">
                            <?php esc_html_e('Must be a mailbox you own on the authenticated SMTP domain. This becomes the visible From address.', 'contact-inbox'); ?>
                        </p>
                    </td>
                </tr>
                <tr class="smtp-dependent-field">
                    <th scope="row"><label for="smtp_from_name"><?php esc_html_e('Sender Name', 'contact-inbox'); ?></label></th>
                    <td>
                        <input name="smtp_from_name" type="text" id="smtp_from_name" value="<?php echo esc_attr($smtp_from_name); ?>" class="large-text" autocomplete="off" data-search="smtp sender name" <?php disabled(empty($settings['smtp_enable'])); ?> />
                        <p class="description">
                            <?php esc_html_e('Shown alongside the sender email. Defaults to the site name if left blank.', 'contact-inbox'); ?>
                        </p>
                    </td>
                </tr>
            </table>
            <div style="margin-top: 10px; padding-top: 8px;">
                <button id="contactin-test-smtp" class="button button-secondary" type="button">
                    <span class="btn-text"><?php esc_html_e('Test SMTP', 'contact-inbox'); ?></span>
                    <span class="spinner" style="display:none;"></span>
                </button>
                <span id="contactin-smtp-result" style="font-weight:bold;margin-left:10px;"></span>
            </div>
        </div>

        <!-- Tab: Notifications -->
        <div id="cin-tab-notifications" class="cin-tab-content">
            <h3><?php esc_html_e('Email Notifications', 'contact-inbox'); ?></h3>
            <?php if (empty($settings['smtp_enable'])): ?>
                <div class="notice notice-warning is-dismissible" style="margin:12px 0;" id="cin-smtp-disabled-warning" data-notice-id="smtp-disabled-notifications">
                    <p>
                        <strong><?php esc_html_e('SMTP is disabled.', 'contact-inbox'); ?></strong>
                        <?php esc_html_e('Enable SMTP in the SMTP tab to activate email notifications.', 'contact-inbox'); ?>
                    </p>
                    <button type="button" class="notice-dismiss cin-notice-dismiss"><span class="screen-reader-text"><?php esc_html_e('Dismiss this notice.', 'contact-inbox'); ?></span></button>
                </div>
            <?php endif; ?>
            <table class="form-table" role="presentation">
                <tr class="smtp-notification-field">
                    <th scope="row"><?php esc_html_e('Send Form Submission to Admin', 'contact-inbox'); ?></th>
                    <td>
                        <fieldset>
                            <legend class="screen-reader-text"><span><?php esc_html_e('Send Form Submission to Admin', 'contact-inbox'); ?></span></legend>
                            <input type="hidden" name="send_admin_notification" value="0" />
                            <label><input name="send_admin_notification" type="checkbox" value="1" <?php checked(!empty($settings['send_admin_notification']) && !empty($settings['smtp_enable'])); ?> data-search="send admin notification" <?php disabled(empty($settings['smtp_enable'])); ?> /> <?php esc_html_e('Send notification email to admin when a new message is received.', 'contact-inbox'); ?></label>
                        </fieldset>
                    </td>
                </tr>
                <tr class="smtp-notification-field">
                    <th scope="row"><label for="admin_email"><?php esc_html_e('Admin Email(s)', 'contact-inbox'); ?></label></th>
                    <td>
                        <input name="admin_email" type="text" id="admin_email"
                            value="<?php echo esc_attr($settings['admin_email'] ?? get_option('admin_email')); ?>"
                            class="large-text"  maxlength="254" data-search="admin email" <?php disabled(empty($settings['smtp_enable'])); ?> />
                        <p class="description">
                            <?php esc_html_e('Comma-separated for multiple emails. Each must be valid and no longer than 254 characters.', 'contact-inbox'); ?>
                        </p>
                    </td>
                </tr>
                <tr class="smtp-notification-field">
                    <th scope="row"><?php esc_html_e('Send Copy to User', 'contact-inbox'); ?></th>
                    <td>
                        <fieldset>
                            <legend class="screen-reader-text"><span><?php esc_html_e('Send Copy to User', 'contact-inbox'); ?></span></legend>
                            <input type="hidden" name="send_user_copy" value="0" />
                            <label><input name="send_user_copy" type="checkbox" value="1" <?php checked(!empty($settings['send_user_copy']) && !empty($settings['smtp_enable'])); ?> data-search="send user copy" <?php disabled(empty($settings['smtp_enable'])); ?> /> <?php esc_html_e('Send confirmation email to user', 'contact-inbox'); ?></label>
                        </fieldset>
                    </td>
                </tr>
            </table>
        </div>

        <!-- Tab: Form -->
        <div id="cin-tab-form" class="cin-tab-content">
            <h3><?php esc_html_e('Form Customisation', 'contact-inbox'); ?></h3>

            <table class="form-table" role="presentation">
                <tr>
                    <th scope="row"><label for="form-enable-subject-btn"><?php esc_html_e('Subject Field', 'contact-inbox'); ?></label></th>
                    <td>
                        <input type="hidden" name="form_enable_subject" id="form-enable-subject-hidden" value="<?php echo !empty($settings['form_enable_subject']) ? '1' : '0'; ?>" />
                        <button type="button" id="form-enable-subject-btn" class="button button-small<?php echo !empty($settings['form_enable_subject']) ? ' enabled' : ''; ?>" data-enabled="<?php echo !empty($settings['form_enable_subject']) ? '1' : '0'; ?>">
                            <?php echo !empty($settings['form_enable_subject']) ? esc_html__('Disable Subject Field', 'contact-inbox') : esc_html__('Enable Subject Field', 'contact-inbox'); ?>
                        </button>
                        <span id="contactin-subject-status-label" class="<?php echo !empty($settings['form_enable_subject']) ? 'enabled' : 'disabled'; ?>" style="margin-left:10px;">
                            <?php echo !empty($settings['form_enable_subject']) ? esc_html__('Enabled', 'contact-inbox') : esc_html__('Disabled', 'contact-inbox'); ?>
                        </span>
                        <span class="description"><?php esc_html_e('Allow users to enter a subject.', 'contact-inbox'); ?></span>
                    </td>
                </tr>

                <tr>
                    <th scope="row"><label for="form-enable-salutation-btn"><?php esc_html_e('Salutation Field', 'contact-inbox'); ?></label></th>
                    <td>
                        <input type="hidden" name="form_enable_salutation" id="form-enable-salutation-hidden" value="<?php echo !empty($settings['form_enable_salutation']) ? '1' : '0'; ?>" />
                        <button type="button" id="form-enable-salutation-btn" class="button button-small<?php echo !empty($settings['form_enable_salutation']) ? ' enabled' : ''; ?>" data-enabled="<?php echo !empty($settings['form_enable_salutation']) ? '1' : '0'; ?>">
                            <?php echo !empty($settings['form_enable_salutation']) ? esc_html__('Disable Salutation Field', 'contact-inbox') : esc_html__('Enable Salutation Field', 'contact-inbox'); ?>
                        </button>
                        <span id="contactin-salutation-status-label" class="<?php echo !empty($settings['form_enable_salutation']) ? 'enabled' : 'disabled'; ?>" style="margin-left:10px;">
                            <?php echo !empty($settings['form_enable_salutation']) ? esc_html__('Enabled', 'contact-inbox') : esc_html__('Disabled', 'contact-inbox'); ?>
                        </span>
                        <span class="description"><?php esc_html_e('Allow users to select a salutation (Mr/Ms/Mrs/Dr/etc.).', 'contact-inbox'); ?></span>
                    </td>
                </tr>

                <tr>
                    <th scope="row"><label for="form-enable-attachment-btn"><?php esc_html_e('File Attachment', 'contact-inbox'); ?></label></th>
                    <td>
                        <input type="hidden" name="form_enable_attachment" id="form-enable-attachment-hidden" value="<?php echo !empty($settings['form_enable_attachment']) ? '1' : '0'; ?>" />
                        <input type="hidden" name="restapi_enable" id="restapi-enable-hidden" value="<?php echo !empty($settings['restapi_enable']) ? '1' : '0'; ?>" />
                        <button type="button" id="form-enable-attachment-btn" class="button button-small<?php echo !empty($settings['form_enable_attachment']) ? ' enabled' : ''; ?>" data-enabled="<?php echo !empty($settings['form_enable_attachment']) ? '1' : '0'; ?>">
                            <?php echo !empty($settings['form_enable_attachment']) ? esc_html__('Disable File Attachment', 'contact-inbox') : esc_html__('Enable File Attachment', 'contact-inbox'); ?>
                        </button>
                        <span id="contactin-attachment-status-label" class="<?php echo !empty($settings['form_enable_attachment']) ? 'enabled' : 'disabled'; ?>" style="margin-left:10px;">
                            <?php echo !empty($settings['form_enable_attachment']) ? esc_html__('Enabled', 'contact-inbox') : esc_html__('Disabled', 'contact-inbox'); ?>
                        </span>
                        <span class="description"><?php esc_html_e('Allow users to upload files.', 'contact-inbox'); ?></span>
                        <div id="cin-attachment-restapi-notice" class="cin-settings-response cin-inline-notice" style="display:none;"></div>
                    </td>
                </tr>

                <tr>
                    <th scope="row"><label for="max_name_chars"><?php esc_html_e('Name Field Length (Max Chars)', 'contact-inbox'); ?></label></th>
                    <td>
                        <input name="max_name_chars" type="number" id="max_name_chars"
                            value="<?php echo esc_attr($settings['max_name_chars'] ?? $defaults['max_name_chars']); ?>"
                             min="1" data-search="name field length max chars" />
                        <p class="description"><?php
                            /* translators: %d: default max characters for name field */
                            printf(esc_html__('Default: %d characters', 'contact-inbox'), (int) $defaults['max_name_chars']);
                        ?></p>

                        <div id="cin-min-words-error" class="cin-hidden cin-color-error cin-mt-md" style="font-weight: bold;">
                            <?php esc_html_e('Error: Minimum words must be at least 2', 'contact-inbox'); ?>
                        </div>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="max_subject_chars"><?php esc_html_e('Subject Field Length (Max Chars)', 'contact-inbox'); ?></label></th>
                    <td>
                        <input name="max_subject_chars" type="number" id="max_subject_chars"
                            value="<?php echo esc_attr($settings['max_subject_chars'] ?? $defaults['max_subject_chars']); ?>"
                             min="1" data-search="subject field length max chars" />
                        <p class="description"><?php
                            /* translators: %d: default max characters for subject field */
                            printf(esc_html__('Default: %d characters', 'contact-inbox'), (int) $defaults['max_subject_chars']);
                        ?></p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="min_subject_words"><?php esc_html_e('Subject Field Min Words', 'contact-inbox'); ?></label></th>
                    <td>
                        <input name="min_subject_words" type="number" id="min_subject_words"
                            value="<?php echo esc_attr($settings['min_subject_words'] ?? $defaults['min_subject_words']); ?>"
                             min="1" data-search="subject field min words" />
                        <p class="description"><?php
                            /* translators: %d: default minimum words for subject field */
                            printf(esc_html__('Default: %d words', 'contact-inbox'), (int) $defaults['min_subject_words']);
                        ?></p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="max_message_chars"><?php esc_html_e('Message Field Length (Max Chars)', 'contact-inbox'); ?></label></th>
                    <td>
                        <input name="max_message_chars" type="number" id="max_message_chars"
                            value="<?php echo esc_attr($settings['max_message_chars'] ?? $defaults['max_message_chars']); ?>"
                             min="1" data-search="message field length max chars" />
                        <p class="description"><?php
                            /* translators: %d: default max characters for message field */
                            printf(esc_html__('Default: %d characters', 'contact-inbox'), (int) $defaults['max_message_chars']);
                        ?></p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="min_message_words"><?php esc_html_e('Message Field Min Words', 'contact-inbox'); ?></label></th>
                    <td>
                        <input name="min_message_words" type="number" id="min_message_words"
                            value="<?php echo esc_attr($settings['min_message_words'] ?? $defaults['min_message_words']); ?>"
                             min="1" data-search="message field min words" />
                        <p class="description"><?php
                            /* translators: %d: default minimum words for message field */
                            printf(esc_html__('Default: %d words', 'contact-inbox'), (int) $defaults['min_message_words']);
                        ?></p>
                    </td>
                </tr>

                <!-- File Upload Restrictions -->
                <?php
                    $all_mimes = get_allowed_mime_types();
                    $popular = ['jpg','jpeg','png','gif','pdf','doc','docx','xls','xlsx','zip'];
                    $popular_mimes = array_intersect_key($all_mimes, array_flip($popular));
                    $other_mimes   = array_diff_key($all_mimes, $popular_mimes);
                    $mime_list     = $popular_mimes + $other_mimes;
                    $current_types = explode(',', $settings['allowed_file_types'] ?? $defaults['allowed_file_types']);
                ?>
                <tr>
                    <th scope="row"><label for="allowed_file_types"><?php esc_html_e('Allowed File Types', 'contact-inbox'); ?></label> <?php UpgradeModalHelper::render_badge(); ?></th>
                    <td>
                        <select name="allowed_file_types[]" id="allowed_file_types" multiple size="10" class="regular-text" data-search="allowed file types" disabled aria-disabled="true">
                            <?php foreach ($mime_list as $ext => $mime): ?>
                                <option value="<?php echo esc_attr($ext); ?>"
                                    <?php selected(in_array($ext, $current_types, true)); ?>>
                                    <?php echo esc_html(strtoupper($ext)); ?> (<?php echo esc_html($mime); ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <p class="description">
                            <?php esc_html_e('Hold Ctrl/Command to select multiple types. Popular types are listed first.', 'contact-inbox'); ?>
                        </p>
                        <button type="button" class="button button-secondary contactinbox-show-upgrade-modal">
                            <?php esc_html_e('Upgrade to Pro', 'contact-inbox'); ?>
                        </button>
                    </td>
                </tr>

                <tr>
                    <th scope="row"><label for="max_file_size"><?php esc_html_e('Max File Size (MB)', 'contact-inbox'); ?></label> <?php UpgradeModalHelper::render_badge(); ?></th>
                    <td>
                        <input name="max_file_size" type="number" id="max_file_size"
                            value="<?php echo esc_attr($settings['max_file_size'] ?? $defaults['max_file_size']); ?>"
                             min="1" data-search="max file size" disabled aria-disabled="true" />
                        <p class="description">
                            <?php
                                /* translators: %d: default max upload file size in MB */
                                printf(esc_html__('Default safe size: %d MB', 'contact-inbox'), (int) $defaults['max_file_size']);
                            ?>
                        </p>
                        <button type="button" class="button button-secondary contactinbox-show-upgrade-modal">
                            <?php esc_html_e('Upgrade to Pro', 'contact-inbox'); ?>
                        </button>
                    </td>
                </tr>
            </table>
        </div>

        <!-- Tab: Advanced -->
        <div id="cin-tab-advanced" class="cin-tab-content">
            <h3><?php esc_html_e('Advanced Settings', 'contact-inbox'); ?></h3>

            <table class="form-table" role="presentation">
                <tr>
                    <th scope="row"><label for="email_log_retention_days"><?php esc_html_e('Email Log Retention (days)', 'contact-inbox'); ?></label> <?php UpgradeModalHelper::render_badge(); ?></th>
                    <td>
                        <input name="email_log_retention_days" type="number" id="email_log_retention_days"
                            value="<?php echo esc_attr($settings['email_log_retention_days'] ?? 90); ?>"
                             min="1" data-search="email log retention" disabled aria-disabled="true" />
                        <p class="description"><?php esc_html_e('Number of days to keep email logs before automatic cleanup.', 'contact-inbox'); ?></p>
                        <button type="button" class="button button-secondary contactinbox-show-upgrade-modal">
                            <?php esc_html_e('Upgrade to Pro', 'contact-inbox'); ?>
                        </button>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="rest_log_retention_days"><?php esc_html_e('REST Log Retention (days)', 'contact-inbox'); ?></label> <?php UpgradeModalHelper::render_badge(); ?></th>
                    <td>
                        <input name="rest_log_retention_days" type="number" id="rest_log_retention_days"
                            value="<?php echo esc_attr($settings['rest_log_retention_days'] ?? 30); ?>"
                             min="1" data-search="rest log retention" disabled aria-disabled="true" />
                        <p class="description"><?php esc_html_e('Number of days to keep REST API logs before automatic cleanup.', 'contact-inbox'); ?></p>
                        <button type="button" class="button button-secondary contactinbox-show-upgrade-modal">
                            <?php esc_html_e('Upgrade to Pro', 'contact-inbox'); ?>
                        </button>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="crm_log_retention_days"><?php esc_html_e('CRM Log Retention (days)', 'contact-inbox'); ?></label> <?php UpgradeModalHelper::render_badge(); ?></th>
                    <td>
                        <input name="crm_log_retention_days" type="number" id="crm_log_retention_days"
                            value="<?php echo esc_attr($settings['crm_log_retention_days'] ?? 30); ?>"
                             min="1" data-search="crm log retention" disabled aria-disabled="true" />
                        <p class="description"><?php esc_html_e('Number of days to keep CRM logs before automatic cleanup.', 'contact-inbox'); ?></p>
                        <button type="button" class="button button-secondary contactinbox-show-upgrade-modal">
                            <?php esc_html_e('Upgrade to Pro', 'contact-inbox'); ?>
                        </button>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="gdpr_log_retention_days"><?php esc_html_e('GDPR Log Retention (days)', 'contact-inbox'); ?></label> <?php UpgradeModalHelper::render_badge(); ?></th>
                    <td>
                        <input name="gdpr_log_retention_days" type="number" id="gdpr_log_retention_days"
                            value="<?php echo esc_attr($settings['gdpr_log_retention_days'] ?? 90); ?>"
                             min="1" data-search="gdpr log retention" disabled aria-disabled="true" />
                        <p class="description"><?php esc_html_e('Number of days to keep GDPR deletion logs before pruning.', 'contact-inbox'); ?></p>
                        <button type="button" class="button button-secondary contactinbox-show-upgrade-modal">
                            <?php esc_html_e('Upgrade to Pro', 'contact-inbox'); ?>
                        </button>
                    </td>
                </tr>
            </table>

            <?php
            $queue_event_email = Config::CRON_PROCESS_EMAIL;
            $queue_event_crm   = Config::CRON_PROCESS_CRM;
            $queue_next_run_email = wp_next_scheduled($queue_event_email);
            $queue_next_run_crm   = wp_next_scheduled($queue_event_crm);

            $current_interval_email = get_option('contactin_queue_interval', 'contactin_fifteen_minutes');
            $current_interval_crm   = get_option('contactin_crm_queue_interval', $current_interval_email);

            $cron_array = _get_cron_array();
            foreach ($cron_array as $timestamp => $cron) {
                if (isset($cron[$queue_event_email])) {
                    foreach ($cron[$queue_event_email] as $data) {
                        if (!empty($data['schedule'])) {
                            $current_interval_email = $data['schedule'];
                            break 2;
                        }
                    }
                }
            }

            foreach ($cron_array as $timestamp => $cron) {
                if (isset($cron[$queue_event_crm])) {
                    foreach ($cron[$queue_event_crm] as $data) {
                        if (!empty($data['schedule'])) {
                            $current_interval_crm = $data['schedule'];
                            break 2;
                        }
                    }
                }
            }
            $schedules = wp_get_schedules();
            $allowed_intervals = [
                'contactin_one_minute' => __('Every 1 minute', 'contact-inbox'),
                'contactin_two_minutes' => __('Every 2 minutes', 'contact-inbox'),
                'contactin_five_minutes' => __('Every 5 minutes', 'contact-inbox'),
                'contactin_fifteen_minutes' => __('Every 15 minutes', 'contact-inbox'),
                'hourly' => __('Hourly', 'contact-inbox'),
            ];
            ?>

            <h4 style="margin-top:24px;">&raquo; <?php esc_html_e('Background Job Scheduling', 'contact-inbox'); ?> <?php UpgradeModalHelper::render_badge(); ?></h4>
            <p class="description" style="margin-bottom:10px;">
                <?php esc_html_e('Adjust how often the queue processors run. Use Dashboard → Background Jobs to monitor executions and health.', 'contact-inbox'); ?>
            </p>
            <button type="button" class="button button-secondary contactinbox-show-upgrade-modal" style="margin-bottom:10px;">
                <?php esc_html_e('Upgrade to Pro', 'contact-inbox'); ?>
            </button>

            <table class="wp-list-table widefat fixed striped" style="margin-top: 10px;">
                <thead>
                    <tr>
                        <th style="width: 30%;"><?php esc_html_e('Job', 'contact-inbox'); ?></th>
                        <th style="width: 30%;"><?php esc_html_e('Schedule', 'contact-inbox'); ?></th>
                        <th style="width: 25%;"><?php esc_html_e('Next Run', 'contact-inbox'); ?></th>
                        <th style="width: 15%;"><?php esc_html_e('Actions', 'contact-inbox'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td><strong><?php esc_html_e('Email Processor', 'contact-inbox'); ?></strong><br><small><?php echo esc_html($queue_event_email); ?></small></td>
                        <td>
                            <select name="queue_cron_interval" id="queue_cron_interval" class="cron-interval-select" data-event="<?php echo esc_attr($queue_event_email); ?>" data-old="<?php echo esc_attr($current_interval_email); ?>" disabled aria-disabled="true">
                                <?php foreach ($allowed_intervals as $key => $label): ?>
                                    <?php if (!isset($schedules[$key])) { continue; } ?>
                                    <option value="<?php echo esc_attr($key); ?>" <?php selected($current_interval_email, $key); ?>><?php echo esc_html($label); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                        <td><?php echo $queue_next_run_email ? esc_html( date_i18n('Y-m-d H:i:s', $queue_next_run_email) ) : esc_html__('Not scheduled', 'contact-inbox'); ?></td>
                        <td>
                            <button type="button" class="button button-small run-cron-now" data-event="<?php echo esc_attr($queue_event_email); ?>" disabled aria-disabled="true"><?php esc_html_e('Run Now', 'contact-inbox'); ?></button>
                        </td>
                    </tr>
                    <tr>
                        <td><strong><?php esc_html_e('CRM Processor', 'contact-inbox'); ?></strong><br><small><?php echo esc_html($queue_event_crm); ?></small></td>
                        <td>
                            <select name="queue_cron_interval_crm" id="queue_cron_interval_crm" class="cron-interval-select" data-event="<?php echo esc_attr($queue_event_crm); ?>" data-old="<?php echo esc_attr($current_interval_crm); ?>" disabled aria-disabled="true">
                                <?php foreach ($allowed_intervals as $key => $label): ?>
                                    <?php if (!isset($schedules[$key])) { continue; } ?>
                                    <option value="<?php echo esc_attr($key); ?>" <?php selected($current_interval_crm, $key); ?>><?php echo esc_html($label); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                        <td><?php echo $queue_next_run_crm ? esc_html( date_i18n('Y-m-d H:i:s', $queue_next_run_crm) ) : esc_html__('Not scheduled', 'contact-inbox'); ?></td>
                        <td>
                            <button type="button" class="button button-small run-cron-now" data-event="<?php echo esc_attr($queue_event_crm); ?>" disabled aria-disabled="true"><?php esc_html_e('Run Now', 'contact-inbox'); ?></button>
                        </td>
                    </tr>
                </tbody>
            </table>

            <div class="notice notice-warning inline" style="margin:12px 0;">
                <p>
                    <?php esc_html_e('Running jobs more frequently than every 15 minutes can add load. Form submissions already trigger immediate one-off runs to avoid notification delays, so 15 minutes is recommended for the recurring schedule.', 'contact-inbox'); ?>
                </p>
            </div>

            <div class="notice notice-info inline" style="margin:12px 0;">
                <p>
                    <?php esc_html_e('Background Jobs monitoring lives in Dashboard → Background Jobs. Email and CRM processors can be scheduled independently.', 'contact-inbox'); ?>
                    <a href="<?php echo esc_url(admin_url('admin.php?page=contactin-analytics#tab-cron')); ?>"><?php esc_html_e('Open Background Jobs', 'contact-inbox'); ?></a>
                </p>
            </div>

            <!-- Intent Classification Settings -->
            <h4 style="margin-top:24px;">&raquo; <?php esc_html_e('Intent Classification', 'contact-inbox'); ?></h4>
            <?php $this->render_intent_settings($settings); ?>
        </div>

        <div class="cin-settings-save-wrapper" style="margin-top: 30px; padding-top: 15px; border-top: 1px solid #ccc; position: relative; z-index: 1001;">
            <button type="submit" id="contactin-save-button" class="button button-primary" data-state="default">
                <span class="cin-btn-text"><?php esc_html_e('Save Settings', 'contact-inbox'); ?></span>
                <span class="cin-btn-spinner" style="display:none;margin-left:6px;">
                    <span class="spinner" style="display:inline-block;vertical-align:middle;"></span>
                </span>
                <span class="cin-btn-saved" style="display:none;margin-left:6px;">✓ <?php esc_html_e('Saved', 'contact-inbox'); ?></span>
            </button>
        </div>
    </form>

    <!-- Support Boxes Row -->
    <div class="cin-support-boxes">
        <div class="cin-support-box">
            <?php include SCH_PATH . 'partials/upgrade-box.php'; ?>
        </div>
        <div class="cin-support-box">
            <?php include SCH_PATH . 'partials/review-box.php'; ?>
        </div>
    </div>

    <!-- Footer Info -->
    <div style="margin-top: 40px; padding-top: 20px; border-top: 1px solid #e0e0e0; text-align: center; color: #666; font-size: 12px;">
        <p>ContactIn v<?php echo esc_html(Config::VERSION); ?> • Enterprise-Grade</p>
    </div>
</div>

<?php ob_start(); ?>
(function($) {
    'use strict';

    // Initialize global nonce variable
    window.cin_settings_nonce = '';
    
    // Wait for DOM to be fully loaded before accessing form fields
    $(document).ready(function() {
        // Get nonce immediately from form - this is GUARANTEED to have the value from wp_nonce_field()
        var formNonceInput = $('#contactin-settings-form input[name="nonce"]');
        var formNonceValue = formNonceInput.length > 0 ? formNonceInput.val() : '';
        
        // Fallback: PHP-generated nonce - using direct string to ensure it matches
        var phpGeneratedNonce = '<?php echo esc_js( wp_create_nonce("contactinbox_settings_nonce") ); ?>';
        
        // Store the most reliable nonce source globally
        window.cin_settings_nonce = formNonceValue || phpGeneratedNonce;
        
        // Debug: Log the nonce value on page load
        if (window.cin_settings_nonce && window.cin_settings_nonce.length > 0) {
            console.log('✓ Settings nonce loaded:', window.cin_settings_nonce.substring(0, 8) + '...', '(length: ' + window.cin_settings_nonce.length + ')');
        } else {
            console.error('✗ ERROR: No nonce loaded! Form nonce:', formNonceValue, 'PHP nonce:', phpGeneratedNonce);
            console.error('Form field count:', formNonceInput.length, 'Form ID exists:', $('#contactin-settings-form').length);
        }
    });
    
    const adminData = window.contactinbox_admin || {};
    
    // Function to safely get nonce from multiple sources
    function getNonce() {
        // Use the global nonce we determined at load time
        if (window.cin_settings_nonce && window.cin_settings_nonce.length > 0) {
            return window.cin_settings_nonce;
        }
        
        // Try from form field directly (in case it changed)
        var formNonce = $('#contactin-settings-form input[name="nonce"]').val();
        if (formNonce && formNonce.length > 0) {
            return formNonce;
        }
        
        // Try from adminData
        if (adminData.nonce && adminData.nonce.length > 0) {
            return adminData.nonce;
        }
        
        // Last resort - log error and return empty
        console.error('⚠️ CRITICAL: No nonce available for AJAX requests!');
        console.error('Debug info:', {
            global_nonce: window.cin_settings_nonce,
            form_field: $('#contactin-settings-form input[name="nonce"]').length,
            form_value: $('#contactin-settings-form input[name="nonce"]').val(),
            adminData: adminData.nonce
        });
        return '';
    }
    
    // Initialize nonce on adminData for backward compatibility
    if (!adminData.nonce) {
        adminData.nonce = getNonce();
    }

    
    // ====== Gold Standard Toggle Button Styles ======
    const toggleStyles = `
            /* Defensive button protection - ensures all buttons are clickable */
            .wrap .button,
            #contactin-settings-form .button {
                position: relative !important;
                pointer-events: auto !important;
            }
            
            /* Defensive tab navigation protection */
            .nav-tab-wrapper .nav-tab {
                position: relative !important;
                z-index: 100 !important;
                pointer-events: auto !important;
            }
            
            /* Settings Search Box Styles */
            .cin-settings-search-box {
                display: flex;
                align-items: center;
                gap: 8px;
                margin-bottom: 16px;
                padding: 10px 12px;
                background: #f9f9f9;
                border-radius: 4px;
                border-left: 3px solid #0073aa;
                transition: all 0.2s ease;
            }

            .cin-settings-search-icon {
                font-size: 16px;
                color: #666;
                min-width: 20px;
                flex-shrink: 0;
            }

            .cin-settings-search-input {
                flex: 1;
                padding: 8px 10px;
                border: 1px solid #ddd;
                border-radius: 4px;
                font-size: 14px;
                font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Oxygen-Sans, Ubuntu, Cantarell, "Helvetica Neue", sans-serif;
                transition: border-color 0.2s ease, box-shadow 0.2s ease;
            }

            .cin-settings-search-input:focus {
                outline: none;
                border-color: #0073aa;
                box-shadow: 0 0 0 2px rgba(0, 115, 170, 0.1);
            }

            .cin-settings-search-input::placeholder {
                color: #999;
                opacity: 1;
                font-style: normal;
            }

            .cin-settings-search-results {
                font-size: 13px;
                margin: 8px 0;
                padding: 8px 12px;
                border-radius: 4px;
                white-space: nowrap;
                transition: all 0.3s ease;
            }

            .cin-settings-search-results.matches-found {
                color: #155724;
                font-weight: 600;
                background-color: #f0f6f0;
            }

            .cin-settings-search-results.no-matches {
                color: #d63638;
                font-weight: 600;
                background-color: #fcf0f1;
            }

            .cin-settings-clear-btn {
                background: none;
                border: none;
                cursor: pointer;
                color: #999;
                font-size: 18px;
                padding: 4px 8px;
                margin-left: 4px;
                flex-shrink: 0;
                transition: color 0.2s ease;
            }

            .cin-settings-clear-btn:hover {
                color: #333;
            }

            /* Settings Form Notification */
            #cin-global-settings-notice {
                margin: 12px 0 20px 0;
                padding: 12px 40px 12px 15px;
                border-left: 4px solid #0073aa;
                background-color: #f0f6fc;
                color: #003a70;
                border-radius: 4px;
                display: none;
                position: relative;
                transition: all 0.3s ease;
            }

            #cin-global-settings-notice.cin-show {
                display: block;
                animation: slideDown 0.3s ease;
            }

            #cin-global-settings-notice.cin-hidden {
                display: none;
            }

            /* Utility class for hidden elements */
            .cin-hidden {
                display: none !important;
            }

            @keyframes slideDown {
                from {
                    opacity: 0;
                    transform: translateY(-10px);
                }
                to {
                    opacity: 1;
                    transform: translateY(0);
                }
            }

            #cin-notice-message {
                margin: 0;
                padding: 0;
                line-height: 1.6;
            }

            #cin-global-settings-notice.notice-success {
                border-left-color: #00a32a;
                background-color: #f0f6f0;
                color: #003300;
            }

            #cin-global-settings-notice.notice-error {
                border-left-color: #d63638;
                background-color: #fcf0f1;
                color: #660000;
            }

            #cin-global-settings-notice.notice-warning {
                border-left-color: #dba617;
                background-color: #fef9f0;
                color: #664400;
            }

            .cin-notice-dismiss {
                position: absolute;
                top: 12px;
                right: 12px;
                background: none;
                border: none;
                color: inherit;
                cursor: pointer;
                font-size: 16px;
                padding: 4px 8px;
                opacity: 0.7;
                transition: opacity 0.2s ease;
            }

            .cin-notice-dismiss:hover,
            .cin-notice-dismiss:focus {
                opacity: 1;
            }

            .screen-reader-text {
                border: 0;
                clip: rect(1px, 1px, 1px, 1px);
                clip-path: inset(50%);
                height: 1px;
                margin: -1px;
                overflow: hidden;
                padding: 0;
                position: absolute;
                width: 1px;
                word-wrap: normal;
            }

            .button.button-small.enabled {
                background: #00a32a;
                color: #fff;
                border-color: #008a20;
                position: relative !important;
                z-index: 1000 !important;
                pointer-events: auto !important;
            }
            .button.button-small.enabled:hover {
                background: #008a20;
                border-color: #007017;
            }
            .button.button-small {
                position: relative !important;
                z-index: 1000 !important;
                pointer-events: auto !important;
            }
            #contactin-smtp-status-label,
            #contactin-subject-status-label,
            #contactin-attachment-status-label {
                font-weight: 600;
                padding: 4px 10px;
                border-radius: 3px;
                font-size: 12px;
            }
            #contactin-smtp-status-label.enabled,
            #contactin-subject-status-label.enabled,
            #contactin-attachment-status-label.enabled {
                color: #00a32a;
                background: #f0f6f0;
            }
            #contactin-smtp-status-label.disabled,
            #contactin-subject-status-label.disabled,
            #contactin-attachment-status-label.disabled {
                color: #d63638;
                background: #fcf0f1;
            }
            .smtp-dependent-field,
            .smtp-notification-field {
                transition: opacity 0.3s ease;
            }
            /* Dismissible notice close button styling */
            .notice.is-dismissible {
                position: relative;
                padding-right: 38px;
            }
            .notice .notice-dismiss,
            .notice .cin-notice-dismiss {
                position: absolute;
                top: 0;
                right: 1px;
                border: none;
                margin: 0;
                padding: 9px;
                background: none;
                color: #787c82;
                cursor: pointer;
                text-decoration: none;
            }
            .notice .notice-dismiss:before,
            .notice .cin-notice-dismiss:before {
                background: none;
                color: #787c82;
                content: \"\\f153\";
                display: block;
                font: normal 16px/20px dashicons;
                speak: never;
                height: 20px;
                text-align: center;
                width: 20px;
                -webkit-font-smoothing: antialiased;
                -moz-osx-font-smoothing: grayscale;
            }
            .notice .notice-dismiss:hover:before,
            .notice .cin-notice-dismiss:hover:before {
                color: #d63638;
            }
            
            /* Save Button States */
            #contactin-save-button {
                display: inline-flex;
                align-items: center;
                gap: 4px;
                min-width: 150px;
                transition: all 0.3s ease;
                position: relative !important;
                z-index: 1001 !important;
                pointer-events: auto !important;
            }
            
            #contactin-save-button .cin-btn-text {
                display: inline;
                transition: opacity 0.3s ease;
            }
            
            #contactin-save-button .cin-btn-spinner {
                display: none;
            }
            
            #contactin-save-button .cin-btn-saved {
                display: none;
                color: #00a32a;
                font-weight: 600;
            }
            
            /* Saving State */
            #contactin-save-button[data-state="saving"] {
                background-color: #f0f6fc;
                border-color: #0073aa;
                color: #0073aa;
            }
            
            #contactin-save-button[data-state="saving"] .cin-btn-text {
                opacity: 0.6;
            }
            
            #contactin-save-button[data-state="saving"] .cin-btn-spinner {
                display: inline !important;
            }
            
            /* Saved State */
            #contactin-save-button[data-state="saved"] {
                background-color: #f0f6f0;
                border-color: #00a32a;
                color: #00a32a;
            }
            
            #contactin-save-button[data-state="saved"] .cin-btn-text {
                display: none;
            }
            
            #contactin-save-button[data-state="saved"] .cin-btn-spinner {
                display: none !important;
            }
            
            #contactin-save-button[data-state="saved"] .cin-btn-saved {
                display: inline !important;
            }
            
            #contactin-save-button:disabled {
                opacity: 0.7;
                cursor: not-allowed;
            }
    `;
    var styleNode = document.createElement('style');
    styleNode.type = 'text/css';
    styleNode.appendChild(document.createTextNode(toggleStyles));
    document.head.appendChild(styleNode);
    
    // ====== Dismissible Notices with Persistence ======
    // Check dismissed notices from localStorage and hide them
    $('.notice.is-dismissible[data-notice-id]').each(function() {
        var $notice = $(this);
        var noticeId = $notice.data('notice-id');
        var dismissedNotices = JSON.parse(localStorage.getItem('cin_dismissed_notices') || '{}');
        
        if (dismissedNotices[noticeId]) {
            $notice.hide();
        }
    });
    
    // Handle notice dismiss button clicks
    $(document).on('click', '.cin-notice-dismiss', function(e) {
        e.preventDefault();
        var $notice = $(this).closest('.notice[data-notice-id]');
        var noticeId = $notice.data('notice-id');
        
        if (noticeId) {
            // Save to localStorage
            var dismissedNotices = JSON.parse(localStorage.getItem('cin_dismissed_notices') || '{}');
            dismissedNotices[noticeId] = true;
            localStorage.setItem('cin_dismissed_notices', JSON.stringify(dismissedNotices));
        }
        
        // Fade out and remove
        $notice.fadeOut(300, function() {
            $(this).remove();
        });
    });
    
    // Clear dismissed notice flags when relevant settings change
    function clearDismissedNotice(noticeId) {
        var dismissedNotices = JSON.parse(localStorage.getItem('cin_dismissed_notices') || '{}');
        if (dismissedNotices[noticeId]) {
            delete dismissedNotices[noticeId];
            localStorage.setItem('cin_dismissed_notices', JSON.stringify(dismissedNotices));
        }
    }
    
    // Tab switching
    $('.nav-tab').on('click', function(e) {
        e.preventDefault();
        const tab = $(this).data('tab');
        const tabId = '#cin-tab-' + tab;
        
        $('.nav-tab').removeClass('nav-tab-active');
        $(this).addClass('nav-tab-active');
        
        $('.cin-tab-content').removeClass('is-active').hide();
        $(tabId).addClass('is-active').show();
        
        localStorage.setItem('ci_settings_tab', tab);
    });
    
    // Restore last selected tab
    const lastTab = localStorage.getItem('ci_settings_tab') || 'general';
    $('[data-tab="' + lastTab + '"]').click();
    
    // Search functionality
    $('#cin-settings-search').on('keyup', function() {
        const query = $(this).val().toLowerCase().trim();
        const resultsEl = $('#cin-search-results');
        const clearBtn = $('#cin-clear-search');
        
        // Show/hide clear button using class
        if (query) {
            clearBtn.removeClass('cin-hidden');
        } else {
            clearBtn.addClass('cin-hidden');
            resultsEl.addClass('cin-hidden');
        }
    
        if (!query) {
            $('.cin-tab-content').show();
            $('tr').show();
            resultsEl.addClass('cin-hidden');
            // Show all tabs in nav
            $('.nav-tab-wrapper .nav-tab').show();
            return;
        }
        
        let matchCount = 0;
        const matchedTabs = new Set();
        
        $('[data-search]').each(function() {
            const searchText = $(this).data('search').toLowerCase();
            const label = $(this).closest('tr').find('th, label').text().toLowerCase();
            const isMatch = searchText.includes(query) || label.includes(query);
            
            const row = $(this).closest('tr');
            row.toggle(isMatch);
            
            if (isMatch) {
                matchCount++;
                const tab = row.closest('.cin-tab-content');
                if (tab.length) {
                    matchedTabs.add(tab.attr('id'));
                }
            }
        });
        
        // Show matched tabs, hide others
        $('.cin-tab-content').each(function() {
            const tabId = $(this).attr('id');
            const isMatched = matchedTabs.has(tabId);
            $(this).toggle(isMatched);
            if (isMatched) {
                $(this).addClass('is-active');
            } else {
                $(this).removeClass('is-active');
            }
        });
        
        // Update tab navigation visibility
        $('.nav-tab-wrapper .nav-tab').each(function() {
            const tabId = $(this).attr('href').substring(1);
            if (matchedTabs.has(tabId)) {
                $(this).show();
            } else {
                $(this).hide();
            }
        });
        
        // Update results text with CSS classes instead of inline styles
        if (matchCount === 0) {
            resultsEl
                .removeClass('matches-found')
                .addClass('no-matches')
                .html('⚠️ <?php echo esc_js(__('No matches found', 'contact-inbox')); ?>')
                .removeClass('cin-hidden');
            } else {
                resultsEl
                    .removeClass('no-matches')
                    .addClass('matches-found')
                    .html('✓ ' + matchCount + ' match' + (matchCount !== 1 ? 'es' : ''))
                    .removeClass('cin-hidden');
            }
    });
    
    // Clear search button
    $('#cin-clear-search').on('click', function(e) {
        e.preventDefault();
        $('#cin-settings-search').val('').trigger('keyup').focus();
    });
    
    // Clear search on tab click
    $('.nav-tab').on('click', function() {
        $('#cin-settings-search').val('').trigger('keyup');
    });

    /**
     * Show notification message in the global notice area
     * @param {string} message - The message to display
     * @param {string} type - The notification type: 'success', 'error', or 'warning'
     * @param {number} duration - Auto-hide duration in milliseconds (0 = manual dismiss only)
     */
    function showNotification(message, type, duration) {
        type = type || 'success';
        duration = duration || 5000;
        
        const $notice = $('#cin-global-settings-notice');
        const $message = $('#cin-notice-message');
        
        // Update message and state
        $message.html(message);
        $notice
            .removeClass('notice-success notice-error notice-warning cin-hidden')
            .addClass('notice-' + type + ' cin-show');
        
        // Auto-dismiss if duration specified
        if (duration > 0) {
            // Clear any existing timeout
            if ($notice.data('dismiss-timeout')) {
                clearTimeout($notice.data('dismiss-timeout'));
            }
            
            // Set new timeout
            const timeout = setTimeout(function() {
                $notice.removeClass('cin-show').addClass('cin-hidden');
            }, duration);
            
            $notice.data('dismiss-timeout', timeout);
        }
        
        // Scroll to notification for visibility
        $('html, body').animate({
            scrollTop: $notice.offset().top - 50
        }, 300);
    }
    
    // Handle manual dismiss of global notice
    $(document).on('click', '.cin-notice-dismiss', function(e) {
        e.preventDefault();
        const $notice = $(this).closest('#cin-global-settings-notice');
        
        // Clear any auto-dismiss timeout
        if ($notice.data('dismiss-timeout')) {
            clearTimeout($notice.data('dismiss-timeout'));
        }
        
        // Hide with animation
        $notice.removeClass('cin-show').addClass('cin-hidden');
    });
    
    // Handle settings form submission with button states
    $('#contactin-settings-form').on('submit', function(e) {
        e.preventDefault();
        
        const $form = $(this);
        const $button = $('#contactin-save-button');
        const formData = $form.serializeArray();
        
        // Change button to saving state
        $button.attr('data-state', 'saving').prop('disabled', true);
        
        // Send AJAX request
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: formData,
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    // Show saved state
                    $button.attr('data-state', 'saved').prop('disabled', false);
                    
                    // Show success notification
                    showNotification(
                        response.data?.message || '<?php echo esc_js(__('Settings saved successfully!', 'contact-inbox')); ?>',
                        'success',
                        4000
                    );
                    
                    // Reset button to default state after 2 seconds
                    setTimeout(function() {
                        $button.attr('data-state', 'default').prop('disabled', false);
                    }, 2000);
                } else {
                    // Show error state and message
                    $button.attr('data-state', 'default').prop('disabled', false);
                    
                    showNotification(
                        response.data?.message || '<?php echo esc_js(__('An error occurred while saving settings', 'contact-inbox')); ?>',
                        'error',
                        5000
                    );
                }
            },
            error: function(xhr, status, error) {
                // Reset button on error
                $button.attr('data-state', 'default').prop('disabled', false);
                
                showNotification(
                    '<?php echo esc_js(__('Network error occurred while saving', 'contact-inbox')); ?>',
                    'error',
                    5000
                );
            }
        });
    });
    
    // Show notification if form was just submitted (on page load)
    $(document).ready(function() {
        if (sessionStorage.getItem('cin_form_submitted') === 'true') {
            sessionStorage.removeItem('cin_form_submitted');

            // Show inline saved message next to Save button
            const $button = $('#contactin-save-button');
            if ($button.length) {
                $button.attr('data-state', 'saved');
                
                setTimeout(function() {
                    $button.attr('data-state', 'default');
                }, 2000);
            }
            

            // Check if there's an existing WordPress notice (success or error)
            if ($('.notice-success').length > 0) {
                showNotification(
                    '<?php echo esc_js(__('Settings saved successfully!', 'contact-inbox')); ?>',
                    'success',
                    4000
                );
            }
        }
    });

    // ====== Cron Management ======
    
    // Run cron job manually
    $('.run-cron-now').on('click', function() {
        const button = $(this);
        const event = button.data('event');
        const row = button.closest('tr');
        
        button.prop('disabled', true).text('Running...');
        
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'ci_run_cron_now',
                event: event,
                nonce: '<?php echo esc_js( wp_create_nonce('ci_cron_action') ); ?>'
            },
            success: function(response) {
                if (response.success) {
                    button.text('✓ Done');
                    setTimeout(function() {
                        location.reload(); // Reload to show updated stats
                    }, 1000);
                } else {
                    alert('Error: ' + (response.data || 'Unknown error'));
                    button.prop('disabled', false).text('Run Now');
                }
            },
            error: function() {
                alert('AJAX error occurred');
                button.prop('disabled', false).text('Run Now');
            }
        });
    });
    
    // Change cron interval
    $('.cron-interval-select').on('change', function() {
        const select = $(this);
        const event = select.data('event');
        const newInterval = select.val();
        const oldInterval = select.data('old') || select.find('option:selected').data('old') || select.val();
        const aggressiveIntervals = ['contactin_one_minute', 'contactin_two_minutes', 'contactin_five_minutes'];
        
        let prompt = 'Change schedule interval for this job? The job will be rescheduled immediately.';
        if (aggressiveIntervals.includes(newInterval)) {
            prompt += '\n\nWarning: Running more often than every 15 minutes can increase site load. Form submissions already trigger immediate runs to avoid delivery delays.';
        }
        
        if (!confirm(prompt)) {
            select.val(oldInterval);
            return;
        }
        
        select.prop('disabled', true);
        
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'ci_update_cron_interval',
                event: event,
                interval: newInterval,
                nonce: '<?php echo esc_js( wp_create_nonce('ci_cron_action') ); ?>'
            },
            success: function(response) {
                if (response.success) {
                    if (response.data && response.data.warning) {
                        alert(response.data.warning);
                    }
                    alert('Schedule updated successfully!');
                    select.data('old', newInterval);
                    location.reload();
                } else {
                    alert('Error: ' + (response.data || 'Unknown error'));
                    select.val(oldInterval);
                    select.prop('disabled', false);
                }
            },
            error: function() {
                alert('AJAX error occurred');
                select.val(oldInterval);
                select.prop('disabled', false);
            }
        });
    });

    // ====== Auto-validation for min_name_words ======
    const minNameWordsInput = document.getElementById('cin-min-name-words-input');
    const errorDiv = document.getElementById('cin-min-words-error');
    const settingsForm = document.getElementById('contactin-settings-form');

    if (minNameWordsInput) {
        minNameWordsInput.addEventListener('change', validateMinNameWords);
        minNameWordsInput.addEventListener('input', validateMinNameWords);

        settingsForm.addEventListener('submit', function(e) {
            if (!validateMinNameWords()) {
                e.preventDefault();
                minNameWordsInput.focus();
                minNameWordsInput.style.borderColor = '#d63638';
                return false;
            }
        });

        validateMinNameWords();
    }

    function validateMinNameWords() {
        const value = parseInt(minNameWordsInput.value, 10);
        const isInvalid = Number.isNaN(value) || value < 2;

        errorDiv.style.display = isInvalid ? 'block' : 'none';
        minNameWordsInput.style.borderColor = isInvalid ? '#d63638' : '';
        minNameWordsInput.style.backgroundColor = isInvalid ? '#fff5f5' : '';

        return !isInvalid;
    }
    // Update status labels after AJAX toggle
    function updateSmtpStatus(enabled) {
        var label = $('#contactin-smtp-status-label');
        label.text(enabled ? 'Enabled' : 'Disabled');
        label.removeClass('enabled disabled').addClass(enabled ? 'enabled' : 'disabled');
        var btn = $('#smtp-enable-btn');
        btn.text(enabled ? 'Disable SMTP' : 'Enable SMTP');
        btn.toggleClass('enabled', enabled);
        btn.data('enabled', enabled ? '1' : '0');
        // CRITICAL: Update the HTML attribute, not just jQuery's data cache
        btn.attr('data-enabled', enabled ? '1' : '0');
        $('#smtp-enable-hidden').val(enabled ? '1' : '0');
        
        // Clear dismissed notices when SMTP state changes
        clearDismissedNotice('smtp-disabled-notifications');
        clearDismissedNotice('smtp-domain-mismatch');
        
        // Update dependent fields
        $('.smtp-dependent-field input, .smtp-dependent-field select').prop('disabled', !enabled);
        $('.smtp-dependent-field').css('opacity', enabled ? '1' : '0.5');
        
        // Update notification fields
        if (!enabled) {
            // Uncheck and disable notification checkboxes when SMTP is disabled
            $('input[name="send_admin_notification"]').prop('checked', false).prop('disabled', true);
            $('input[name="send_user_copy"]').prop('checked', false).prop('disabled', true);
            $('input[type="hidden"][name="send_admin_notification"]').val('0');
            $('input[type="hidden"][name="send_user_copy"]').val('0');
            $('.smtp-notification-field input:not([type="hidden"]), .smtp-notification-field select').prop('disabled', true);
            $('.smtp-notification-field').css('opacity', '0.5');
            // Show warning notice (it will check localStorage before showing)
            var $smtpNotice = $('#cin-smtp-disabled-warning');
            if ($smtpNotice.length) {
                var dismissedNotices = JSON.parse(localStorage.getItem('cin_dismissed_notices') || '{}');
                if (!dismissedNotices['smtp-disabled-notifications']) {
                    $smtpNotice.show();
                }
            }
        } else {
            $('input[name="send_admin_notification"], input[name="send_user_copy"]').prop('disabled', false);
            $('.smtp-notification-field input:not([type="hidden"]), .smtp-notification-field select').prop('disabled', false);
            $('.smtp-notification-field').css('opacity', '1');
            // Hide warning notice
            $('#cin-smtp-disabled-warning').hide();
        }
    }
    function updateSubjectStatus(enabled) {
        var label = $('#contactin-subject-status-label');
        label.text(enabled ? 'Enabled' : 'Disabled');
        label.removeClass('enabled disabled').addClass(enabled ? 'enabled' : 'disabled');
        var btn = $('#form-enable-subject-btn');
        btn.text(enabled ? 'Disable Subject Field' : 'Enable Subject Field');
        btn.toggleClass('enabled', enabled);
        btn.data('enabled', enabled ? '1' : '0');
        btn.attr('data-enabled', enabled ? '1' : '0');
        $('#form-enable-subject-hidden').val(enabled ? '1' : '0');
    }
    function updateAttachmentStatus(enabled) {
        var label = $('#contactin-attachment-status-label');
        label.text(enabled ? 'Enabled' : 'Disabled');
        label.removeClass('enabled disabled').addClass(enabled ? 'enabled' : 'disabled');
        var btn = $('#form-enable-attachment-btn');
        btn.text(enabled ? 'Disable File Attachment' : 'Enable File Attachment');
        btn.toggleClass('enabled', enabled);
        btn.data('enabled', enabled ? '1' : '0');
        btn.attr('data-enabled', enabled ? '1' : '0');
        $('#form-enable-attachment-hidden').val(enabled ? '1' : '0');
        $('#restapi-enable-hidden').val(enabled ? '1' : '0');
    }

    function updateSalutationStatus(enabled) {
        var label = $('#contactin-salutation-status-label');
        label.text(enabled ? 'Enabled' : 'Disabled');
        label.removeClass('enabled disabled').addClass(enabled ? 'enabled' : 'disabled');
        var btn = $('#form-enable-salutation-btn');
        btn.text(enabled ? 'Disable Salutation Field' : 'Enable Salutation Field');
        btn.toggleClass('enabled', enabled);
        btn.data('enabled', enabled ? '1' : '0');
        btn.attr('data-enabled', enabled ? '1' : '0');
        $('#form-enable-salutation-hidden').val(enabled ? '1' : '0');
    }

    // Initialize SMTP dependent fields state on page load
    (function() {
        var smtpEnabled = $('#smtp-enable-btn').data('enabled') === 1 || $('#smtp-enable-btn').data('enabled') === '1';
        if (!smtpEnabled) {
            $('.smtp-dependent-field').css('opacity', '0.5');
            $('.smtp-notification-field').css('opacity', '0.5');
        }
    })();

    // SMTP toggle handler
    $('#smtp-enable-btn').off('click').on('click', function() {
        var btn = $(this);
        var enabled = btn.data('enabled') === 1 || btn.data('enabled') === '1';
        var newState = !enabled;
        var nonceValue = getNonce();
        
        // DEBUG: Log nonce value
        console.log('SMTP Toggle - Nonce value:', nonceValue ? (nonceValue.substring(0, 8) + '...') : '❌ EMPTY');
        
        btn.prop('disabled', true);
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'cin_toggle_smtp',
                enabled: enabled ? 0 : 1,
                nonce: nonceValue
            },
            success: function(resp) {
                if (resp && resp.success) {
                    updateSmtpStatus(newState);
                    
                    // Show guidance message when SMTP is enabled
                    if (newState) {
                        var message = '<div class="notice notice-info is-dismissible" style="margin:15px 0;padding:12px 15px;"><p style="margin:0.5em 0;"><strong><?php esc_html_e('SMTP Enabled Successfully!', 'contact-inbox'); ?></strong></p><p style="margin:0.5em 0;"><?php esc_html_e('Important: SMTP only handles email delivery. To send notifications, please enable them in the', 'contact-inbox'); ?> <a href="#cin-tab-notifications" class="cin-switch-tab-link" data-target-tab="notifications" style="font-weight:bold;"><?php esc_html_e('Notifications tab', 'contact-inbox'); ?></a>.</p><p style="margin:0.5em 0;"><?php esc_html_e('☑️ Enable "Send notification to admin" for admin alerts', 'contact-inbox'); ?><br><?php esc_html_e('☑️ Enable "Send confirmation to user" for user receipts', 'contact-inbox'); ?></p><button type="button" class="notice-dismiss"><span class="screen-reader-text">Dismiss this notice.</span></button></div>';
                        $('#cin-tab-smtp > h3').after(message);
                        
                        // Tab switch handler for the link
                        $(document).on('click', '.cin-switch-tab-link', function(e) {
                            e.preventDefault();
                            var targetTab = $(this).data('target-tab');
                            $('.nav-tab').removeClass('nav-tab-active');
                            $('.nav-tab[data-tab="' + targetTab + '"]').addClass('nav-tab-active');
                            $('.cin-tab-content').removeClass('is-active').hide();
                            $('#cin-tab-' + targetTab).addClass('is-active').show();
                            localStorage.setItem('ci_settings_tab', targetTab);
                            
                            // Scroll to top of page
                            $('html, body').animate({ scrollTop: 0 }, 300);
                        });
                        
                        // Auto-dismiss handler
                        $(document).on('click', '.notice-dismiss', function(e) {
                            $(this).closest('.notice').fadeOut(300, function() {
                                $(this).remove();
                            });
                        });
                    }
                } else {
                    alert('Error: ' + (resp.data && resp.data.message ? resp.data.message : 'Unknown error'));
                }
            },
            error: function(xhr, status, error) {
                console.error('SMTP toggle error:', error);
                alert('Request failed. Please try again.');
            },
            complete: function() {
                btn.prop('disabled', false);
            }
        });
    });

    // Patch AJAX toggle handlers to update status after success
    $('#form-enable-subject-btn').off('click').on('click', function() {
        var btn = $(this);
        var enabled = btn.data('enabled') === 1 || btn.data('enabled') === '1';
        btn.prop('disabled', true);
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'cin_toggle_subject',
                enabled: enabled ? 0 : 1,
                nonce: getNonce()
            },
            success: function(resp) {
                if (resp && resp.success) {
                    updateSubjectStatus(!enabled);
                } else {
                    alert('Error: ' + (resp.data && resp.data.message ? resp.data.message : 'Unknown error'));
                }
            },
            error: function(xhr, status, error) {
                console.error('Subject toggle error:', error);
                alert('Request failed. Please try again.');
            },
            complete: function() {
                btn.prop('disabled', false);
            }
        });
    });

    $('#form-enable-salutation-btn').off('click').on('click', function() {
        var btn = $(this);
        var enabled = btn.data('enabled') === 1 || btn.data('enabled') === '1';
        btn.prop('disabled', true);
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'cin_toggle_salutation',
                enabled: enabled ? 0 : 1,
                nonce: getNonce()
            },
            success: function(resp) {
                if (resp && resp.success) {
                    updateSalutationStatus(!enabled);
                } else {
                    alert('Error: ' + (resp.data && resp.data.message ? resp.data.message : 'Unknown error'));
                }
            },
            error: function(xhr, status, error) {
                console.error('Salutation toggle error:', error);
                alert('Request failed. Please try again.');
            },
            complete: function() {
                btn.prop('disabled', false);
            }
        });
    });
    $('#form-enable-attachment-btn').off('click').on('click', function() {
        var btn = $(this);
        var enabled = btn.data('enabled') === 1 || btn.data('enabled') === '1';
        btn.prop('disabled', true);
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'cin_toggle_attachment',
                enabled: enabled ? 0 : 1,
                nonce: getNonce()
            },
            success: function(resp) {
                if (resp && resp.success) {
                    updateAttachmentStatus(!enabled);
                } else {
                    alert('Error: ' + (resp.data && resp.data.message ? resp.data.message : 'Unknown error'));
                }
            },
            error: function(xhr, status, error) {
                console.error('Attachment toggle error:', error);
                alert('Request failed. Please try again.');
            },
            complete: function() {
                btn.prop('disabled', false);
            }
        });
    });
})(jQuery);
<?php
$settings_page_inline_js = trim((string) ob_get_clean());
wp_add_inline_script('contactin-admin-settings', $settings_page_inline_js);
?>

    <div id="contactin-attachment-restapi-modal" class="cin-modal cin-modal-hidden">
        <div class="cin-modal-overlay"></div>
        <div class="cin-modal-content">
            <div class="cin-modal-header">
                <h2 class="cin-modal-title"><?php esc_html_e( 'Enable REST API for attachments?', 'contact-inbox' ); ?></h2>
                <button type="button" class="cin-modal-close" aria-label="<?php esc_attr_e( 'Close', 'contact-inbox' ); ?>">×</button>
            </div>
            <div class="cin-modal-body">
                <p><?php esc_html_e( 'File attachments rely on the REST API to upload files. Enabling attachments will also enable the REST API service.', 'contact-inbox' ); ?></p>
                <p><?php esc_html_e( 'Do you want to enable both now?', 'contact-inbox' ); ?></p>
            </div>
            <div class="cin-modal-footer cin-confirm-actions">
                <button type="button" class="button button-secondary cin-modal-close" id="contactin-attachment-restapi-cancel">
                    <?php esc_html_e( 'No, keep disabled', 'contact-inbox' ); ?>
                </button>
                <button type="button" class="button button-primary" id="contactin-attachment-restapi-confirm">
                    <?php esc_html_e( 'Yes, enable REST API and attachments', 'contact-inbox' ); ?>
                </button>
            </div>
        </div>
    </div>

    <div id="contactin-attachment-restapi-disable-modal" class="cin-modal cin-modal-hidden">
        <div class="cin-modal-overlay"></div>
        <div class="cin-modal-content">
            <div class="cin-modal-header">
                <h2 class="cin-modal-title"><?php esc_html_e( 'Disable File Attachments?', 'contact-inbox' ); ?></h2>
                <button type="button" class="cin-modal-close" aria-label="<?php esc_attr_e( 'Close', 'contact-inbox' ); ?>">×</button>
            </div>
            <div class="cin-modal-body">
                <p><?php esc_html_e( 'REST API is currently enabled to support file uploads. If you disable attachments, do you also want to disable the REST API?', 'contact-inbox' ); ?></p>
            </div>
            <div class="cin-modal-footer cin-confirm-actions">
                <button type="button" class="button button-secondary cin-modal-close" id="contactin-attachment-restapi-disable-cancel">
                    <?php esc_html_e( 'Keep REST API enabled', 'contact-inbox' ); ?>
                </button>
                <button type="button" class="button button-primary" id="contactin-attachment-restapi-disable-confirm">
                    <?php esc_html_e( 'Disable both REST API and attachments', 'contact-inbox' ); ?>
                </button>
            </div>
        </div>
    </div>

<!-- Help Modal -->
<?php include plugin_dir_path(__FILE__) . 'partials/settings-help-modal.php'; ?>

</div>