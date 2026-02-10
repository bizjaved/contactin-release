<?php
/**
 * CRM Auth Stub
 * 
 * This is a stub class to prevent fatal errors.
 * Actual CRM authentication is a PRO feature.
 *
 * @package ContactInbox
 */

namespace ContactInbox\Core;

/**
 * CRMAuth stub class
 */
class CRMAuth {
    
    /**
     * Get access token
     *
     * @param bool $force_refresh Force refresh token
     * @return string|null
     */
    public static function get_access_token($force_refresh = false) {
        return null;
    }
    
    /**
     * Get auth tokens
     *
     * @param array $settings CRM settings
     * @return array|\WP_Error
     */
    public static function get_auth_tokens($settings) {
        return new \WP_Error('pro_feature', 'CRM authentication is a PRO feature');
    }
}
