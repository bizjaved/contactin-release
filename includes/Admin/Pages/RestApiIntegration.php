<?php
/**
 * REST API Integration Page - Free Version Stub
 * 
 * Shows upgrade prompt for Pro features
 *
 * @package ContactInbox
 */

namespace ContactInbox\Admin\Pages;

use ContactInbox\Traits\Singleton;
use ContactInbox\Core\Config;
use ContactInbox\Admin\Assets\RestApiIntegrationAssets;

if (!defined('ABSPATH')) {
    exit;
}

final class RestApiIntegration {
    use Singleton;

    protected function __construct() {
        // Initialize assets for this page
        add_action('admin_head', [$this, 'enqueue_assets']);
    }

    /**
     * Enqueue page-specific assets
     */
    public function enqueue_assets(): void {
        (new RestApiIntegrationAssets())->enqueue();
    }

    public static function render(): void {
        if (!current_user_can(Config::CAPABILITY)) {
            wp_die(esc_html__('Permission denied.', 'contact-inbox'));
        }

        // Load upgrade modal template
        include CONTACTINBOX_ADMIN_TEMPLATES . 'restapi-integration-page.php';
    }

    /**
     * Get active REST API tokens (stub for Pro feature)
     */
    public static function get_active_tokens(): array {
        return []; // Pro feature
    }

    /**
     * Create token (stub for Pro feature)
     */
    public static function create_token(string $name = ''): string {
        return ''; // Pro feature
    }

    /**
     * Revoke token (stub for Pro feature)
     */
    public static function revoke_token(string $token_id): bool {
        return false; // Pro feature
    }

    /**
     * Get health stats (stub for Pro feature)
     */
    public static function get_health_stats(): array {
        return [
            'status' => 'disabled',
            'message' => 'REST API features are available in ContactIn Pro',
        ]; // Pro feature
    }
}
