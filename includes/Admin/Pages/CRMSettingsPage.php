<?php
/**
 * CRM Settings Page - Free Version Stub
 * 
 * Shows upgrade prompt for Pro features
 *
 * @package ContactInbox
 */

namespace ContactInbox\Admin\Pages;

use ContactInbox\Traits\Singleton;
use ContactInbox\Core\Config;
use ContactInbox\Admin\Assets\CRMSettingsAssets;

if (!defined('ABSPATH')) {
    exit;
}

final class CRMSettingsPage {
    use Singleton;

    protected function __construct() {
        // Initialize assets for this page
        add_action('admin_head', [$this, 'enqueue_assets']);
    }

    /**
     * Enqueue page-specific assets
     */
    public function enqueue_assets(): void {
        (new CRMSettingsAssets())->enqueue();
    }

    public static function render(): void {
        if (!current_user_can(Config::CAPABILITY)) {
            wp_die(esc_html__('Permission denied.', Config::TEXTDOMAIN));
        }

        // Load upgrade modal template
        include CONTACTINBOX_ADMIN_TEMPLATES . 'crm-settings-page.php';
    }
}
