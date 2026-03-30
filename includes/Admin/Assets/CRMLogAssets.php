<?php
namespace ContactInbox\Admin\Assets;

use ContactInbox\Core\Config;
use ContactInbox\Core\CRMStatus;
use ContactInbox\Traits\Singleton;

if (!defined('ABSPATH')) exit;
final class CRMLogAssets {
    use Singleton;
    use AssetHelpers;

    public function enqueue(): void {
        // Assets are loaded by AssetsDispatcher based on hook match
        // Use shared logs.min.css for consistent error/response styling across all log pages
        $this->register_style( 'contactin-logs', 'logs.min.css', [], Config::ASSETS_VERSION );
        
        $this->register_script( 'contactin-crm-log', 'crm-log.min.js', [ 'jquery', 'contactin-admin-global' ], Config::ASSETS_VERSION );
        wp_localize_script( 'contactin-crm-log', 'contactinCrmLog', [
            'ajaxUrl' => admin_url( 'admin-ajax.php' ),
            'nonce'   => wp_create_nonce( Config::CRM_LOG_NONCE ),
            'filters' => CRMStatus::get_filter_options(),
            'colors'  => CRMStatus::get_colors(),
            'export_info_action' => 'contactinbox_crm_export_info',
            'export_csv_action'  => 'contactinbox_download_crm_csv',
            'i18n'    => [
                'message_box' => [
                    'header'       => __( 'CRM Log Notice',  'contactin'),
                    'footer_close' => __( 'Close',  'contactin'),
                ],
            ],
        ] );
    }
}
