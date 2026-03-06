<?php
declare(strict_types=1);

namespace ContactInbox\Admin\Assets;

use ContactInbox\Core\Config;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * CRM Settings Assets - Stub for Free Version
 * 
 * CRM features are Pro-only.
 * This stub prevents fatal errors during asset enumeration.
 */
final class CRMSettingsAssets {
    use AssetHelpers;

    public function enqueue(): void {
        // Enqueue global admin styles
        $this->register_style('contactin-admin-global', 'admin-global.min.css');
        
        // Enqueue CRM settings styles
        $this->register_style('contactin-crm-settings', 'crm-settings.min.css', ['contactin-admin-global']);
    }
}
