<?php
/**
 * Admin Asset Handler – CRM Dashboard Page
 *
 * Enqueues styles and scripts for the CRM Dashboard admin page.
 *
 * @package ContactInbox\Admin\Assets
 */

declare(strict_types=1);

namespace ContactInbox\Admin\Assets;

if (!defined('ABSPATH')) {
    exit;
}

final class CRMDashboardAssets {
    use AssetHelpers;

    /**
     * Enqueue styles and scripts for CRM Dashboard page.
     */
    public function enqueue(): void {
        $this->enqueue_styles();
        $this->enqueue_scripts();
    }

    /**
     * Enqueue styles for CRM Dashboard.
     */
    private function enqueue_styles(): void {
        $handle = 'contactin-crm-dashboard';

        // Inline styles are handled in the template
        // But we can enqueue admin common styles if needed
        wp_enqueue_style('wp-admin');
    }

    /**
     * Enqueue scripts for CRM Dashboard.
     */
    private function enqueue_scripts(): void {
        $handle = 'contactin-crm-dashboard';

        // Localize nonce and ajaxurl for JavaScript
        wp_localize_script('jquery', 'contactinCRM', [
            'ajaxurl' => admin_url('admin-ajax.php'),
            'nonce'   => wp_create_nonce('contactinbox_nonce_action'),
        ]);
    }
}
