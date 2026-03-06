<?php
declare(strict_types=1);

namespace ContactInbox\Admin\Assets;

use ContactInbox\Core\Config;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * REST API Integration Assets - Stub for Free Version
 * 
 * REST API features are Pro-only.
 * This stub prevents fatal errors during asset enumeration.
 */
final class RestApiIntegrationAssets {
    use AssetHelpers;

    public function enqueue(): void {
        // Enqueue global admin styles
        $this->register_style('contactin-admin-global', 'admin-global.min.css');

        // Enqueue REST API integration styles
        $this->register_style('contactin-restapi-integration', 'integration.min.css', ['contactin-admin-global']);
        $this->register_style('contactin-restapi-integration-layout', 'restapi-integration-layout.css', ['contactin-admin-global']);
    }
}
