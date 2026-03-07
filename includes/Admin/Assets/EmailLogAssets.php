<?php
namespace ContactInbox\Admin\Assets;

use ContactInbox\Core\Config;

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

        // Enqueue CSS from dist/css
        $this->register_style(
            $handle,
            'logs.min.css'
        );

        // Enqueue JS from dist/js, depend on global for helpers
        $this->register_script(
            $handle,
            'admin-email-log.min.js',
            [ 'jquery', 'contactin-admin-global' ]
        );

        // Determine retention days from settings (default 90)
        $settings       = get_option( Config::OPTION_SETTINGS, [] );
        $retention_days = absint( $settings['email_log_retention_days'] ?? 90 );

        /* translators: %d: number of days after which email logs are deleted. */
        $confirm_prune_text = __( 'Pruning will permanently delete all email logs older than %d days. This cannot be undone.', 'contact-inbox' );

        // Localize script with dynamic values and translations
        wp_localize_script( $handle, 'ContactINEmailLog', [
            'ajax_url'        => admin_url( 'admin-ajax.php' ),
            'nonce'           => wp_create_nonce( Config::NONCE_ACTION ),
            'clear_all_nonce' => wp_create_nonce( 'contactinbox_email_clear_all_logs' ),
            'retention_days'  => $retention_days,
            'summary_timeout' => 15000,
            'export_info_action' => 'contactinbox_email_export_info',
            'export_csv_action'  => 'contactinbox_download_email_csv',
            'i18n'            => [
                'confirmPrune' => sprintf(
                    $confirm_prune_text,
                    $retention_days
                ),
                'confirmClearAll' => __( 'Are you sure you want to CLEAR ALL email logs? This will permanently delete all email logs and cannot be undone.', 'contact-inbox' ),
                'pruning'        => __( 'Pruning logs…', 'contact-inbox' ),
                'errorPrune'     => __( 'Failed to prune logs.', 'contact-inbox' ),
                'network_error'  => __( 'Network error. Please try again.', 'contact-inbox' ),
                'loadingHeaders' => __( 'Loading headers…', 'contact-inbox' ),
                'loadingBody'    => __( 'Loading body…', 'contact-inbox' ),
                'errorDetails'   => __( 'Failed to load details.', 'contact-inbox' ),
                'noDetails'      => __( 'No details available.', 'contact-inbox' ),
                'message_box'    => [
                    'header'       => __( 'Email Log Notice', 'contact-inbox' ),
                    'footer_close' => __( 'Close', 'contact-inbox' ),
                ],
            ],
        ] );
    }
}
