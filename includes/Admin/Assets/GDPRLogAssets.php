<?php
namespace ContactInbox\Admin\Assets;

use ContactInbox\Core\Config;

if (!defined('ABSPATH')) exit;
// phpcs:disable WordPress.WP.I18n.NonSingularStringLiteralDomain, WordPress.WP.I18n.MissingTranslatorsComment, WordPress.Security.ValidatedSanitizedInput.MissingUnslash, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized, WordPress.Security.NonceVerification.Recommended

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class GDPRLogAssets {
    use AssetHelpers;

    /**
     * Enqueue GDPR Deletion Log page assets.
     */
    public function enqueue(): void {
        $handle = 'contactin-admin-gdpr-log';

        // Enqueue CSS from dist/css (shared logs.min.css for consistency)
        // Depends on global admin styles - logs.min.css loads after and overrides modal styles
        $this->register_style(
            $handle,
            'logs.min.css',
            ['contactin-admin-global']
        );

        // Enqueue JS from dist/js, depend on global for helpers
        $this->register_script(
            $handle,
            'gdpr-log.min.js',
            [ 'jquery', 'contactin-admin-global' ]
        );

        // Determine retention days from settings (default 90)
        $settings       = get_option( Config::OPTION_SETTINGS, [] );
        $retention_days = absint( $settings['gdpr_log_retention_days'] ?? 90 );

        // Get current filter values from URL
        $search = isset($_GET['s']) ? sanitize_text_field($_GET['s']) : '';
        $crm_status = isset($_GET['crm_status']) ? sanitize_text_field($_GET['crm_status']) : 'all';
        $deletion_status = isset($_GET['deletion_status']) ? sanitize_text_field($_GET['deletion_status']) : 'all';

        // Localize script with translations and dynamic values
        wp_localize_script( $handle, 'ContactINGDPRLog', [
            'ajax_url'       => admin_url( 'admin-ajax.php' ),
            'nonce'          => wp_create_nonce( Config::NONCE_ACTION ),
            'search'         => $search,
            'crm_status'     => $crm_status,
            'deletion_status' => $deletion_status,
            'retention_days' => $retention_days,
            'i18n'           => [
                'confirmPrune'    => sprintf(
                    __( 'Pruning will permanently delete all GDPR logs older than %d days.',  'contactin'),
                    $retention_days
                ),
                'confirmClear'    => __( 'This will delete ALL non-synced GDPR logs. This action cannot be undone.',  'contactin'),
                'confirmDeleteCrm' => __( 'Delete these contacts from the CRM? This action cannot be undone.',  'contactin'),
                'pruning'         => __( 'Pruning…',  'contactin'),
                'clearing'        => __( 'Clearing…',  'contactin'),
                'deleting'        => __( 'Deleting…',  'contactin'),
                'working'         => __( 'Working…',  'contactin'),
                'errorPrune'      => __( 'Error pruning logs.',  'contactin'),
                'errorClear'      => __( 'Error clearing logs.',  'contactin'),
                'errorDelete'     => __( 'Error deleting from CRM.',  'contactin'),
                'errorExport'     => __( 'Error getting export info.',  'contactin'),
                'errorBatch'      => __( 'Error exporting batch.',  'contactin'),
                'network_error'   => __( 'Network error. Please try again.',  'contactin'),
            ],
        ] );
    }

}
