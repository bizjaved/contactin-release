<?php
/**
 * Salesforce Attachment Settings Assets
 * 
 * Enqueues CSS and JS for Salesforce attachment configuration UI.
 *
 * @package ContactIn\Admin\Assets
 */

declare(strict_types=1);

namespace ContactInbox\Admin\Assets;

use ContactInbox\Core\Config;

if (!defined('ABSPATH')) {
    exit;
}

final class SalesforceAttachmentAssets {
    use AssetHelpers;

    /**
     * Enqueue Salesforce Attachment assets.
     */
    public function enqueue(): void {
        $handle = 'contactin-sf-attachment-settings';

        // CSS for attachment settings
        $this->register_style($handle, 'sf-attachment-settings.min.css');

        // JS for attachment settings
        $this->register_script(
            $handle,
            'sf-attachment-settings.min.js',
            ['jquery', 'contactin-admin-global']
        );

        // Localize with necessary data
        wp_localize_script($handle, 'cinSFAttachmentSettings', [
            'nonce' => wp_create_nonce('contactin_sf_attachment_settings'),
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'optionName' => Config::OPTION_CRM,
            'maxFileSizeMB' => 50,
            'supportedFormats' => 'jpg,jpeg,png,pdf,doc,docx,xls,xlsx,txt,csv',
            'strings' => [
                'success' => __('Settings saved successfully',  'contactin'),
                'error' => __('An error occurred while saving settings',  'contactin'),
            ],
        ]);
    }
}
