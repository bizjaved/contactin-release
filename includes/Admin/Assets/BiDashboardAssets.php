<?php
/**
 * BI Dashboard Assets Handler
 *
 * Enqueues scripts and styles for the Business Intelligence dashboard page.
 *
 * @package ContactInbox\Admin\Assets
 */

declare(strict_types=1);

namespace ContactInbox\Admin\Assets;

use ContactInbox\Traits\Singleton;
use ContactInbox\Core\Config;

if (!defined('ABSPATH')) {
    exit;
}

final class BiDashboardAssets {
    use Singleton;

    protected function __construct() {
        add_action('admin_enqueue_scripts', [$this, 'enqueue']);
    }

    /**
     * Enqueue scripts and styles for BI Dashboard page
     */
    public function enqueue(): void {
        $current_page = isset($_GET['page']) ? sanitize_text_field($_GET['page']) : '';
        
        if ($current_page !== 'contactin-bi-dashboard') {
            return;
        }

        // Enqueue Chart.js library bundled locally
        wp_enqueue_script(
            'chart-js',
            Config::URL . 'includes/Admin/Assets/js/vendor/chart.min.js',
            [],
            '4.4.0',
            Config::ASSETS_VERSION
        );
    }
}
