<?php
declare(strict_types=1);

namespace ContactInbox\Admin\Assets;

use ContactInbox\Core\Config;
use ContactInbox\Integration\FreemiusIntegration;

if (!defined('ABSPATH')) {
    exit;
}

final class CRMSettingsAssets {
    use AssetHelpers;

    /**
     * Enqueue CRM Settings page assets.
     */
    public function enqueue(): void {
        $handle = 'contactin-crm-settings';

        // CSS: dist/css/crm-settings.min.css
        $this->register_style($handle, 'crm-settings.min.css');

        // JS: dist/js/crm-settings.min.js (depends on admin-global for cinShowMessage)
        $this->register_script($handle, 'crm-settings.min.js', ['jquery', 'contactin-admin-global']);

        // Localize script with nonce and AJAX URL
        wp_localize_script($handle, 'cinCRMSettings', [
            'nonce' => wp_create_nonce(Config::CRM_SETTINGS_NONCE_ACTION),
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'optionName' => Config::OPTION_CRM,
            'disabledMode' => true,
            'upgradeUrl' => FreemiusIntegration::get_upgrade_url('admin-crm'),
            'upgradeTitle' => __('Unlock Premium Features', 'contactin'),
            'upgradeMessage' => __('CRM integration controls are available in ContactIn Pro.', 'contactin'),
            'upgradeCta' => __('Upgrade to Pro', 'contactin'),
            'upgradeDismiss' => __('Maybe later', 'contactin'),
        ]);
    }
}
