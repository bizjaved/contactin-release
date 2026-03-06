<?php
/**
 * SettingsRepository: Handles enabling/disabling subject and attachment fields in the database.
 */

declare(strict_types=1);

namespace ContactInbox\Core\Repositories;

if (!defined('ABSPATH')) exit;

class SettingsRepository {
    /**
     * Enable or disable the subject field in settings.
     */
    public static function set_subject_enabled(bool $enabled): bool {
        $settings = get_option('contactinbox_settings', []);
        $settings['form_enable_subject'] = $enabled ? 1 : 0;
        return update_option('contactinbox_settings', $settings, true);
    }

    /**
     * Enable or disable the file attachment field in settings.
     */
    public static function set_attachment_enabled(bool $enabled): bool {
        $settings = get_option('contactinbox_settings', []);
        $settings['form_enable_attachment'] = $enabled ? 1 : 0;
        return update_option('contactinbox_settings', $settings, true);
    }

    /**
     * Get current subject/attachment enabled state.
     */
    public static function get_subject_enabled(): bool {
        $settings = get_option('contactinbox_settings', []);
        return !empty($settings['form_enable_subject']);
    }
    public static function get_attachment_enabled(): bool {
        $settings = get_option('contactinbox_settings', []);
        return !empty($settings['form_enable_attachment']);
    }
}
