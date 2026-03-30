<?php
namespace ContactInbox\Admin\Assets;

use ContactInbox\Core\Config;

if (!defined('ABSPATH')) exit;
// phpcs:disable WordPress.WP.I18n.NonSingularStringLiteralDomain, WordPress.WP.I18n.MissingTranslatorsComment

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class RestLogAssets {
    use AssetHelpers;

    /**
     * Enqueue REST API Log page assets.
     */
    public function enqueue(): void {
        $handle = 'contactin-admin-rest-log';

        // Enqueue CSS from dist/css
        $this->register_style(
            $handle,
            'logs.min.css'
        );

        // Enqueue JS from dist/js, depend on global for helpers
        $this->register_script(
            $handle,
            'rest-log.min.js',
            [ 'jquery', 'contactin-admin-global' ]
        );

        // Determine retention days from settings (default 30)
        $settings       = get_option( Config::OPTION_SETTINGS, [] );
        $retention_days = absint( $settings['rest_log_retention_days'] ?? 30 );

        // Localize script with translations and dynamic values
        wp_localize_script( $handle, 'ContactINRestLog', [
            'ajax_url'       => admin_url( 'admin-ajax.php' ),
            'nonce'          => wp_create_nonce( Config::NONCE_ACTION ),
            'retention_days' => $retention_days,
            'export_info_action' => 'contactinbox_rest_export_info',
            'export_csv_action'  => 'contactinbox_download_rest_csv',
            'i18n'           => [
                'confirmPrune'    => sprintf(
                    __( 'Pruning will permanently delete all REST API logs older than %d days.',  'contactin'),
                    $retention_days
                ),
                'pruning'         => __( 'Pruning…',  'contactin'),
                'errorPrune'      => __( 'Error pruning logs.',  'contactin'),
                'loadingRequest'  => __( 'Loading request…',  'contactin'),
                'loadingResponse' => __( 'Loading response…',  'contactin'),
                'errorDetails'    => __( 'Failed to load log details.',  'contactin'),
                'network_error'   => __( 'Network error.',  'contactin'),
                'message_box'     => [
                    'header'       => __( 'Log Notice',  'contactin'),
                    'footer_close' => __( 'Close',  'contactin'),
                ],
            ],
        ] );
    }

}
