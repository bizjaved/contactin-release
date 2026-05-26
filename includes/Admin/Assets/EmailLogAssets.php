<?php
namespace ContactInbox\Admin\Assets;

use ContactInbox\Core\Config;

if (!defined('ABSPATH')) exit;
// phpcs:disable WordPress.WP.I18n.NonSingularStringLiteralDomain, WordPress.WP.I18n.MissingTranslatorsComment

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class EmailLogAssets {
    use AssetHelpers;

    /**
     * Enqueue Email Log page assets.
     */
    public function enqueue(): void {
        $handle = 'contactin-admin-email-log';
        $source_script_path = CONTACTINBOX_PATH . 'assets/src/js/admin-email-log.js';
        $source_script_url  = CONTACTINBOX_URL . 'assets/src/js/admin-email-log.js';

        // Enqueue CSS from dist/css
        $this->register_style(
            $handle,
            'logs.min.css'
        );

        // Load source JS when available to keep Email Log behavior consistent.
        if ( file_exists( $source_script_path ) ) {
            wp_enqueue_script(
                $handle,
                $source_script_url,
                [ 'jquery', 'contactin-admin-global' ],
                filemtime( $source_script_path ),
                true
            );
        } else {
            $this->register_script(
                $handle,
                'admin-email-log.min.js',
                [ 'jquery', 'contactin-admin-global' ]
            );
        }

        // Determine retention days from settings (default 90)
        $settings       = get_option( Config::OPTION_SETTINGS, [] );
        $retention_days = absint( $settings['email_log_retention_days'] ?? 90 );

        // Localize script with dynamic values and translations
        wp_localize_script( $handle, 'ContactINEmailLog', [
            'ajax_url'        => admin_url( 'admin-ajax.php' ),
            'nonce'           => wp_create_nonce( Config::NONCE_ACTION ),
            'clear_all_nonce' => wp_create_nonce( 'contactinbox_email_clear_all_logs' ),
            'retention_days'  => $retention_days,
            'summary_timeout' => 15000,
            'i18n'            => [
                'confirmPrune' => sprintf(
                    __( 'Pruning will permanently delete all email logs older than %d days. This cannot be undone.',  'contactin'),
                    $retention_days
                ),
                'confirmClearAll' => __( 'Are you sure you want to CLEAR ALL email logs? This will permanently delete all email logs and cannot be undone.',  'contactin'),
                'pruning'        => __( 'Pruning logs…',  'contactin'),
                'errorPrune'     => __( 'Failed to prune logs.',  'contactin'),
                'network_error'  => __( 'Network error. Please try again.',  'contactin'),
                'loadingHeaders' => __( 'Loading headers…',  'contactin'),
                'loadingBody'    => __( 'Loading body…',  'contactin'),
                'errorDetails'   => __( 'Failed to load details.',  'contactin'),
                'noDetails'      => __( 'No details available.',  'contactin'),
                'message_box'    => [
                    'header'       => __( 'Email Log Notice',  'contactin'),
                    'footer_close' => __( 'Close',  'contactin'),
                ],
            ],
        ] );
    }
}
