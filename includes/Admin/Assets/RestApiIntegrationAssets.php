<?php
namespace ContactInbox\Admin\Assets;

use ContactInbox\Core\Config;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class RestApiIntegrationAssets {
    use AssetHelpers;

    /**
     * Enqueue REST API Integration page assets.
     */
    public function enqueue(): void {
        $handle = 'contactin-admin-restapi-integration';

        // Enqueue CSS
        $this->register_style(
            $handle,
            'integration.min.css'
        );

        // Enqueue JS
        $this->register_script(
            $handle,
            'integration.min.js',
            [ 'jquery' ]
        );

        // Localize script with translations
        wp_localize_script( $handle, 'contactinIntegrationL10n', [
            'nonce'                   => wp_create_nonce( Config::NONCE_ACTION ),
            'submitUrl'               => rest_url( 'contactin/v1/submit' ),
            'tokenPrompt'             => __( 'Enter a name for this API token:', Config::TEXTDOMAIN ),
            'tokenGenerated'          => __( 'Token generated:', Config::TEXTDOMAIN ),
            'tokenCopyWarning'        => __( 'Copy it now – you won\'t see it again!', Config::TEXTDOMAIN ),
            'confirmRevoke'           => __( 'Are you sure you want to revoke this token?', Config::TEXTDOMAIN ),
            'copied'                  => __( 'Copied to clipboard!', Config::TEXTDOMAIN ),
            'copyFailed'              => __( 'Failed to copy to clipboard', Config::TEXTDOMAIN ),
            'subscribeMessageReceived' => __( 'Subscribe to message_received event?', Config::TEXTDOMAIN ),
            'selectEventError'        => __( 'At least one event must be selected.', Config::TEXTDOMAIN ),
            'confirmDelete'           => __( 'Are you sure?', Config::TEXTDOMAIN ),
            'allFieldsRequired'       => __( 'Please fill in all fields.', Config::TEXTDOMAIN ),
        ] );
    }
}
