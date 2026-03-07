<?php
/**
 * Contact Inbox – Dashboard Widget
 * 
 * Displays comprehensive inbox statistics on WordPress dashboard:
 * - Time-based message counts (Today, This Week, This Month, This Year)
 * - Status breakdown (Read vs Unread)
 * - Visual chart showing message trends
 * - Recent message preview
 * - Quick link to inbox
 *
 * Uses template for HTML rendering, respects strict architectural patterns
 *
 * @package ContactInbox\Admin
 */

namespace ContactInbox\Admin;

use ContactInbox\Core\Config;
use ContactInbox\Core\Repositories\MessageRepository;
use ContactInbox\Traits\Singleton;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class DashboardWidget {
    use Singleton;

    private MessageRepository $message_repo;

    protected function __construct() {
        $this->message_repo = new MessageRepository();
        
        // Only register hooks in admin
        if ( is_admin() ) {
            add_action( 'wp_dashboard_setup', [ $this, 'register_widget' ], 10 );
            add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_assets' ] );
        }
    }

    /**
     * Enqueue Chart.js library for graphs
     */
    public function enqueue_assets(): void {
        // Only load on dashboard page
        $current_screen = get_current_screen();
        if ( ! $current_screen || $current_screen->id !== 'dashboard' ) {
            return;
        }

        // Enqueue admin global CSS for dashboard widget styling
        $css_path = CONTACTINBOX_PATH . Config::DIST_CSS . 'admin-global.min.css';
        $css_url  = CONTACTINBOX_URL . Config::DIST_CSS . 'admin-global.min.css';
        if ( file_exists( $css_path ) ) {
            wp_enqueue_style(
                'contactin-admin-global',
                $css_url,
                [],
                filemtime( $css_path )
            );
        }

        wp_enqueue_script(
            'chart-js',
            Config::URL . 'assets/js/vendor/chart.min.js',
            [],
            '4.4.0',
            false
        );
    }

    /**
     * Register the dashboard widget
     */
    public function register_widget(): void {
        // Check if we're in admin
        if ( ! is_admin() ) {
            return;
        }

        // Check capability
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }

        // Register the dashboard widget
        wp_add_dashboard_widget(
            Config::DASHBOARD_WIDGET_ID,
            __( 'Contact Inbox Pro - Messages Status', 'contact-inbox' ),
            [ $this, 'render_widget' ]
        );
    }

    /**
     * Get message count for a specific time period
     */
    private function get_count_by_period( string $period ): int {
        return $this->message_repo->count_by_period( $period );
    }

    /**
     * Get messages by status
     */
    private function get_count_by_status( string $status ): int {
        return $this->message_repo->count( '', $status );
    }

    /**
     * Get 7-day trend data for chart
     */
    private function get_seven_day_trend(): array {
        // Repository returns an ordered array of label/count pairs; convert to label => count map for the widget
        $trend = $this->message_repo->get_trend( 7 );
        $mapped = [];
        foreach ( $trend as $row ) {
            $mapped[ $row['label'] ] = $row['count'];
        }
        return $mapped;
    }

    /**
     * Render the dashboard widget using template
     */
    public function render_widget(): void {
        // Get time-based counts
        $today = $this->get_count_by_period( 'today' );
        $week = $this->get_count_by_period( 'week' );
        $month = $this->get_count_by_period( 'month' );
        $year = $this->get_count_by_period( 'year' );

        // Get status breakdown
        $unread_count = $this->get_count_by_status( 'unread' );
        $read_count = $this->get_count_by_status( 'read' );
        $total_count = $unread_count + $read_count;

        // Get recent message
        $recent_messages = $this->message_repo->get_paginated( 1, 1 );
        $recent_message = ! empty( $recent_messages ) ? $recent_messages[0] : null;

        // Get 7-day trend data
        $trend_data = $this->get_seven_day_trend();

        // Build inbox URL
        $inbox_url = add_query_arg(
            [ 'page' => Config::MENU_INBOX ],
            admin_url( 'admin.php' )
        );

        // Load template with proper template path
        $template = CONTACTINBOX_PATH . Config::TEMPLATE_ADMIN . 'dashboard-widget.php';

        if ( file_exists( $template ) ) {
            // Include template with variables in parent scope
            include $template;
        } else {
            echo '<div class="notice notice-error"><p>'
                . esc_html__( 'Dashboard widget template not found.', 'contact-inbox' )
                . '</p></div>';
        }
    }
}
