<?php
namespace ContactInbox\Core;

use WP_Error;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Salesforce Authentication helper.
 * Uses OAuth 2.0 flow for token management.
 */
final class CRMAuth {
    /**
     * Get (or fetch) a Salesforce access token via OAuth 2.0.
     *
     * @param bool $force_refresh When true, bypasses cache and refreshes token.
     * @return string|WP_Error Access token or WP_Error on failure.
     */
    public static function get_access_token(bool $force_refresh = false) {
        return self::get_oauth_token($force_refresh);
    }

    /**
     * Get OAuth 2.0 access token (refresh if expired).
     *
     * @param bool $force_refresh When true, forces token refresh.
     * @return string|WP_Error Access token or WP_Error on failure.
     */
    private static function get_oauth_token(bool $force_refresh = false) {
        $settings = CRMSettings::get_settings();
        $access_token = trim((string)($settings['auth_token'] ?? ''));
        $refresh_token = trim((string)($settings['refresh_token'] ?? ''));

        // If force refresh or no access token, attempt to refresh using saved credentials
        if ($force_refresh || $access_token === '') {
            if ($refresh_token === '') {
                return new WP_Error('crm_oauth_no_refresh_token', __('No refresh token available. Please reconnect to Salesforce.', Config::TEXTDOMAIN));
            }

            $refreshed = self::refresh_oauth_token($refresh_token, $settings);
            if (is_wp_error($refreshed)) {
                return $refreshed;
            }

            return trim($refreshed);
        }

        return $access_token;
    }

    /**
     * Refresh OAuth 2.0 access token using refresh token.
     *
     * @param string $refresh_token The refresh token.
     * @param string $instance_url The Salesforce instance URL.
     * @return string|WP_Error New access token or WP_Error on failure.
     */
    private static function refresh_oauth_token(string $refresh_token, array $settings) {
        $environment = $settings['environment'] ?? 'production';
        $token_base = $environment === 'sandbox'
            ? 'https://test.salesforce.com'
            : 'https://login.salesforce.com';
        $token_url = $token_base . '/services/oauth2/token';

        $client_id = trim((string)($settings['salesforce_consumer_key'] ?? ''));
        if ($client_id === '') {
            return new WP_Error('crm_oauth_missing_client', __('Salesforce connected app client ID missing. Please reconnect to Salesforce.', Config::TEXTDOMAIN));
        }

        $body = [
            'grant_type'    => 'refresh_token',
            'refresh_token' => $refresh_token,
            'client_id'     => $client_id,
        ];

        $client_secret = trim((string)($settings['salesforce_consumer_secret'] ?? ''));
        if ($client_secret !== '') {
            $body['client_secret'] = $client_secret;
        }

        $response = wp_remote_post($token_url, [
            'body'    => $body,
            'timeout' => 20,
        ]);

        if (is_wp_error($response)) {
            return new WP_Error('crm_oauth_refresh_failed', $response->get_error_message());
        }

        $code = wp_remote_retrieve_response_code($response);
        $body = json_decode(wp_remote_retrieve_body($response), true);

        if ($code < 200 || $code >= 300 || empty($body['access_token'])) {
            $detail = isset($body['error_description']) ? $body['error_description'] : wp_remote_retrieve_body($response);
            
            // If refresh token is invalid, clear OAuth settings
            if (isset($body['error']) && $body['error'] === 'invalid_grant') {
                $settings['auth_token'] = '';
                $settings['refresh_token'] = '';
                $settings['oauth_enabled'] = false;
                CRMSettings::update_settings($settings);
            }
            
            return new WP_Error('crm_oauth_refresh_error', sprintf(__('Failed to refresh token (HTTP %d): %s', Config::TEXTDOMAIN), $code, $detail));
        }

        // Update settings with new access token
        $settings['auth_token'] = $body['access_token'];
        if (!empty($body['refresh_token'])) {
            $settings['refresh_token'] = $body['refresh_token'];
        }

        if (!empty($body['instance_url'])) {
            $settings['instance_url'] = $body['instance_url'];
        }

        CRMSettings::update_settings($settings);

        return $body['access_token'];
    }

}
