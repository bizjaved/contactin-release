<?php
namespace ContactInbox\Admin\Assets;

use ContactInbox\Core\Config;
use ContactInbox\Integration\FreemiusIntegration;

if (!defined('ABSPATH')) exit;
// phpcs:disable WordPress.WP.I18n.NonSingularStringLiteralDomain

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
            'disabledMode'            => true,
            'upgradeUrl'              => FreemiusIntegration::get_upgrade_url( 'admin-restapi' ),
            'upgradeTitle'            => __( 'Unlock Premium Features', 'contactin' ),
            'upgradeMessage'          => __( 'REST API authentication controls are available in ContactIn Pro.', 'contactin' ),
            'upgradeCta'              => __( 'Upgrade to Pro', 'contactin' ),
            'upgradeDismiss'          => __( 'Maybe later', 'contactin' ),
            'tokenPrompt'             => __( 'Enter a name for this API token:',  'contactin'),
            'tokenGenerated'          => __( 'Token generated:',  'contactin'),
            'tokenCopyWarning'        => __( 'Copy it now – you won\'t see it again!',  'contactin'),
            'confirmRevoke'           => __( 'Are you sure you want to revoke this token?',  'contactin'),
            'copied'                  => __( 'Copied to clipboard!',  'contactin'),
            'copyFailed'              => __( 'Failed to copy to clipboard',  'contactin'),
            'subscribeMessageReceived' => __( 'Subscribe to message_received event?',  'contactin'),
            'selectEventError'        => __( 'At least one event must be selected.',  'contactin'),
            'confirmDelete'           => __( 'Are you sure?',  'contactin'),
            'allFieldsRequired'       => __( 'Please fill in all fields.',  'contactin'),
        ] );
    }
}
