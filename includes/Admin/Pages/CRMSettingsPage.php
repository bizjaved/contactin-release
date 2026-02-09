<?php
/**
 * Contact Inbox – CRM Integration Admin Page
 *
 * Enterprise-Grade – Explicit, safe, modular, future-proof.
 *
 * Provides a controller for rendering the CRM Integration settings page.
 * Ensures settings are registered, retrieved, and displayed via a template.
 *
 * @package ContactInbox\Admin\Pages
 */

declare(strict_types=1);

namespace ContactInbox\Admin\Pages;

use ContactInbox\Traits\Singleton;
use ContactInbox\Core\CRMAuth;
use ContactInbox\Core\CRMConnector;
use ContactInbox\Core\CRMSettings;
use ContactInbox\Core\Config;
use ContactInbox\Core\Logger;

if (!defined('ABSPATH')) {
    exit;
}

final class CRMSettingsPage {
    use Singleton;

    /**
     * Constructor – register AJAX handlers and settings.
     */
    protected function __construct() {
        add_action('admin_init', [$this, 'register_settings']);
        add_action('wp_ajax_ci_save_crm_settings', [$this, 'ajax_save_settings']);
        add_action('wp_ajax_ci_test_crm_connection', [$this, 'ajax_test_connection']);
        add_action('wp_ajax_ci_disconnect_crm', [$this, 'ajax_disconnect_crm']);
        add_action('wp_ajax_ci_toggle_crm_service', [$this, 'ajax_toggle_crm_service']);
        add_action('wp_ajax_ci_save_oauth_credentials', [$this, 'ajax_save_oauth_credentials']);
    }
    /**
     * Register CRM settings with WordPress.
     */
    public function register_settings(): void {
        CRMSettings::register();
    }

    /**
     * Render the CRM Integration page.
     * Instantiates the Singleton first to register AJAX handlers.
     */
    public static function render(): void {
        if ( ! current_user_can( Config::CAPABILITY ) ) {
            wp_die( esc_html__( 'Permission denied.', Config::TEXTDOMAIN ) );
        }
        self::instance()->display_page();
    }

    /**
     * Display the CRM Integration page content with tabs.
     */
    private function display_page(): void {
        // Retrieve current settings
        $settings = CRMSettings::get_settings();

        // Define tabs
        $tabs = [
            'salesforce' => __('Salesforce', Config::TEXTDOMAIN),
            'hubspot' => __('HubSpot (Coming Soon)', Config::TEXTDOMAIN),
            'zoho' => __('Zoho (Coming Soon)', Config::TEXTDOMAIN),
        ];

        // Get the active tab
        $active_tab = $_GET['tab'] ?? 'salesforce';
        if (!array_key_exists($active_tab, $tabs)) {
            $active_tab = 'salesforce';
        }

        echo '<h2 class="nav-tab-wrapper">';
        foreach ($tabs as $tab => $label) {
            $class = ($tab === $active_tab) ? ' nav-tab-active' : '';
            $url = add_query_arg('tab', $tab, menu_page_url('contactin-crm', false));
            echo '<a href="' . esc_url($url) . '" class="nav-tab' . esc_attr($class) . '">' . esc_html($label) . '</a>';
        }
        echo '</h2>';

        // Render content based on the active tab
        if ($active_tab === 'salesforce') {
            $template = CONTACTINBOX_PATH . Config::TEMPLATE_ADMIN . 'crm-settings-page.php';
            if (file_exists($template)) {
                // Include template with settings variable available
                include $template;
            } else {
                echo '<div class="notice notice-error"><p>'
                    . esc_html__('Salesforce settings template not found.', Config::TEXTDOMAIN)
                    . '</p></div>';
            }
        } else {
            echo '<div class="notice notice-info"><p>'
                . esc_html__('This integration is coming soon.', Config::TEXTDOMAIN)
                . '</p></div>';
        }


        // Check for OAuth errors in the query parameters
        if (isset($_GET['error']) || isset($_GET['error_description'])) {
            $error = sanitize_text_field($_GET['error'] ?? 'Unknown error');
            $error_description = sanitize_text_field($_GET['error_description'] ?? '');
            echo '<div class="notice notice-error is-dismissible">';
            echo '<p><strong>' . esc_html__('OAuth Error:', Config::TEXTDOMAIN) . '</strong> ' . esc_html($error) . '</p>';
            if (!empty($error_description)) {
                echo '<p>' . esc_html($error_description) . '</p>';
            }
            echo '</div>';
        }
    }

    /**
     * AJAX: Save CRM settings securely.
     */
    public function ajax_save_settings(): void {
        // Security checks
        $nonce = $_POST['nonce'] ?? '';
        if (!wp_verify_nonce($nonce, Config::CRM_SETTINGS_NONCE_ACTION)) {
            wp_send_json_error(['message' => __('Security check failed.', Config::TEXTDOMAIN)]);
        }
        if (!current_user_can(Config::CAPABILITY)) {
            wp_send_json_error(['message' => __('Permission denied.', Config::TEXTDOMAIN)]);
        }

        // Build settings array from POST data (OAuth-only; remove legacy JWT fields)
        // Merge with existing settings to preserve OAuth tokens and other fields not in the form
        $existing_settings = CRMSettings::get_settings();
        
        // Extract settings from the form array structure
        $posted_settings = $_POST[Config::OPTION_CRM] ?? [];
        
        $new_settings = array_merge($existing_settings, [
            'crm_type'        => sanitize_text_field($posted_settings['crm_type'] ?? ''),
            'endpoint'        => esc_url_raw($posted_settings['endpoint'] ?? ''),
            // Note: crm_enabled is managed by the toggle button, not the form - preserve existing value
            'name_field_order'=> sanitize_text_field($posted_settings['name_field_order'] ?? 'first_last'),
            'environment'     => in_array($posted_settings['environment'] ?? 'production', ['production', 'sandbox'], true)
                ? $posted_settings['environment']
                : 'production',
            'attachment_sync' => !empty($posted_settings['attachment_sync']),
            'prepend_intent_to_subject' => !empty($posted_settings['prepend_intent_to_subject']),
            'max_attachment_size_mb' => isset($posted_settings['max_attachment_size_mb']) 
                ? absint($posted_settings['max_attachment_size_mb']) 
                : 50,
            'attachment_visibility' => in_array($posted_settings['attachment_visibility'] ?? 'AllUsers', ['AllUsers', 'InternalUsers', 'SharedUsers'], true)
                ? $posted_settings['attachment_visibility']
                : 'AllUsers',
            'phone_handling_strategy' => in_array($posted_settings['phone_handling_strategy'] ?? 'secondary', ['overwrite', 'secondary', 'skip_if_exists'], true)
                ? $posted_settings['phone_handling_strategy']
                : 'secondary',
            'phone_validation' => in_array($posted_settings['phone_validation'] ?? 'none', ['none', 'basic'], true)
                ? $posted_settings['phone_validation']
                : 'none',
        ]);

        // Handle field mapping if present
        if (!empty($posted_settings['mapping']) && is_array($posted_settings['mapping'])) {
            $new_settings['mapping'] = array_map('sanitize_text_field', $posted_settings['mapping']);
        } else {
            // Preserve existing mapping if not provided in POST
            $new_settings['mapping'] = $existing_settings['mapping'] ?? [];
        }

        // Auto-set name field mapping to FirstName,LastName (automatic based on Regional Name Format)
        $new_settings['mapping']['name'] = 'FirstName,LastName';

        // Store OAuth credentials if provided in POST (otherwise preserve existing)
        if (isset($posted_settings['salesforce_consumer_key'])) {
            $new_settings['salesforce_consumer_key'] = sanitize_text_field($posted_settings['salesforce_consumer_key']);
        }
        if (isset($posted_settings['salesforce_consumer_secret'])) {
            $new_settings['salesforce_consumer_secret'] = trim((string) $posted_settings['salesforce_consumer_secret']);
        }

        CRMSettings::update_settings($new_settings);

        wp_send_json_success(['message' => __('CRM settings saved successfully.', Config::TEXTDOMAIN)]);
    }

    /**
     * AJAX: Test CRM connection.
     */
    public function ajax_test_connection(): void {
        // Security checks
        $nonce = $_POST['nonce'] ?? '';
        if (!wp_verify_nonce($nonce, Config::CRM_SETTINGS_NONCE_ACTION)) {
            wp_send_json_error(['message' => __('Security check failed (nonce).', Config::TEXTDOMAIN)]);
        }

        if (!current_user_can(Config::CAPABILITY)) {
            wp_send_json_error(['message' => __('Permission denied.', Config::TEXTDOMAIN)]);
        }

        $settings = CRMSettings::get_settings();

        // Get endpoint from POST or fallback to saved value
        $endpoint = esc_url_raw($_POST['endpoint'] ?? ($settings['endpoint'] ?? ''));
        if (empty($endpoint)) {
            wp_send_json_error(['message' => __('Endpoint is required.', Config::TEXTDOMAIN)]);
        }

        // Fetch a fresh OAuth token using saved settings
        $token = CRMAuth::get_access_token(true);

        if (is_wp_error($token)) {
            wp_send_json_error(['message' => $token->get_error_message()]);
        }

        // Test connection using wp_remote_head
        try {
            $headers = [
                'Authorization' => 'Bearer ' . $token,
                'Content-Type'  => 'application/json',
            ];

            $test_response = wp_remote_head(
                $endpoint,
                [
                    'headers' => $headers,
                    'timeout' => 10,
                    'sslverify' => true,
                ]
            );

            if (is_wp_error($test_response)) {
                wp_send_json_error(['message' => __('Connection failed: ', Config::TEXTDOMAIN) . $test_response->get_error_message()]);
            }

            $status_code = wp_remote_retrieve_response_code($test_response);
            if ($status_code >= 200 && $status_code < 400) {
                wp_send_json_success(['message' => sprintf(__('Connection successful (HTTP %d)', Config::TEXTDOMAIN), $status_code)]);
            } else {
                $response_body = wp_remote_retrieve_body($test_response);
                wp_send_json_error(['message' => sprintf(__('Connection failed (HTTP %d) - Check your credentials', Config::TEXTDOMAIN), $status_code)]);
            }
        } catch (\Exception $e) {
            wp_send_json_error(['message' => __('Test failed: ', Config::TEXTDOMAIN) . $e->getMessage()]);
        }
    }

    /**
     * Generate Salesforce OAuth URL based on environment.
     */
    public function generate_oauth_url(): string {
        $settings = CRMSettings::get_settings();
        $client_id = $settings['salesforce_consumer_key'] ?? '';

        if (empty($client_id)) {
            Logger::warning('CRM OAuth client ID missing when generating URL');
            return '';
        }

        $environment = $settings['environment'] ?? 'production';

        $login_url = $environment === 'sandbox'
            ? 'https://test.salesforce.com/services/oauth2/authorize'
            : 'https://login.salesforce.com/services/oauth2/authorize';

        $redirect_uri = admin_url('admin-ajax.php');

        // Generate a code verifier and code challenge for PKCE
        $code_verifier = bin2hex(random_bytes(64));
        $code_challenge = rtrim(strtr(base64_encode(hash('sha256', $code_verifier, true)), '+/', '-_'), '=');

        // Generate state for CSRF protection
        $state = bin2hex(random_bytes(32));

        // Store the code verifier and state in transients
        set_transient('ci_crm_code_verifier', $code_verifier, 15 * MINUTE_IN_SECONDS);
        set_transient('ci_crm_oauth_state', $state, 15 * MINUTE_IN_SECONDS);

        // Define the required scopes for Salesforce OAuth
        // Note: 'refresh_token' scope is required to receive a refresh token
        // 'offline_access' is NOT a valid Salesforce scope
        $scopes = 'api refresh_token';

        // Add scopes to the authorization URL
        $auth_url = add_query_arg([
            'response_type' => 'code',
            'client_id' => $client_id,
            'redirect_uri' => $redirect_uri,
            'code_challenge' => $code_challenge,
            'code_challenge_method' => 'S256',
            'scope' => $scopes,
            'state' => $state,
        ], $login_url);

        return $auth_url;
    }

    /**
     * AJAX: Toggle CRM service enable/disable.
     */
    public function ajax_toggle_crm_service(): void {
        check_ajax_referer(Config::CRM_SETTINGS_NONCE_ACTION, 'nonce');

        if (!current_user_can(Config::CAPABILITY)) {
            wp_send_json_error(['message' => __('Permission denied.', Config::TEXTDOMAIN)]);
        }

        $enable = isset($_POST['enabled']) ? ((int)$_POST['enabled'] === 1) : false;
        $settings = CRMSettings::get_settings();
        $settings['crm_enabled'] = $enable;
        CRMSettings::update_settings($settings);

        $button = $enable
            ? esc_html__('Disable Salesforce Sync', Config::TEXTDOMAIN)
            : esc_html__('Enable Salesforce Sync', Config::TEXTDOMAIN);
        $label = $enable ? __('Service Enabled', Config::TEXTDOMAIN) : __('Service Disabled', Config::TEXTDOMAIN);

        wp_send_json_success([
            'enabled' => $enable,
            'button' => $button,
            'label' => $label,
        ]);
    }

    /**
     * AJAX: Save OAuth credentials and return fresh OAuth URL.
     * This allows users to enter credentials and click Connect without a separate save step.
     */
    public function ajax_save_oauth_credentials(): void {
        check_ajax_referer(Config::CRM_SETTINGS_NONCE_ACTION, 'nonce');

        if (!current_user_can(Config::CAPABILITY)) {
            wp_send_json_error(['message' => __('Unauthorized', Config::TEXTDOMAIN)]);
        }

        $consumer_key = sanitize_text_field($_POST['consumer_key'] ?? '');
        $consumer_secret = sanitize_text_field($_POST['consumer_secret'] ?? '');

        if (empty($consumer_key)) {
            wp_send_json_error(['message' => __('Consumer Key is required', Config::TEXTDOMAIN)]);
        }

        // Retrieve current settings
        $settings = CRMSettings::get_settings();

        // Update OAuth credentials
        $settings['salesforce_consumer_key'] = $consumer_key;
        if (!empty($consumer_secret)) {
            $settings['salesforce_consumer_secret'] = $consumer_secret;
        }

        // Save updated settings
        CRMSettings::update_settings($settings);

        // Generate fresh OAuth URL with updated credentials
        $oauth_url = $this->generate_oauth_url();

        if (empty($oauth_url)) {
            wp_send_json_error(['message' => __('Failed to generate OAuth URL. Please check your credentials.', Config::TEXTDOMAIN)]);
        }

        wp_send_json_success([
            'message' => __('Credentials saved. Redirecting to Salesforce...', Config::TEXTDOMAIN),
            'oauth_url' => $oauth_url,
        ]);
    }

    /**
     * AJAX: Disconnect CRM and revoke tokens.
     */
    public function ajax_disconnect_crm(): void {
        // Security checks
        $nonce = $_POST['nonce'] ?? '';
        if (!wp_verify_nonce($nonce, Config::CRM_SETTINGS_NONCE_ACTION)) {
            wp_send_json_error(['message' => __('Security check failed.', Config::TEXTDOMAIN)]);
        }
        if (!current_user_can(Config::CAPABILITY)) {
            wp_send_json_error(['message' => __('Permission denied.', Config::TEXTDOMAIN)]);
        }

        $settings = CRMSettings::get_settings();
        $access_token = $settings['auth_token'] ?? '';
        $instance_url = $settings['instance_url'] ?? '';

        // Attempt to revoke the token with Salesforce
        if (!empty($access_token) && !empty($instance_url)) {
            $revoke_url = $instance_url . '/services/oauth2/revoke';
            wp_remote_post($revoke_url, [
                'body' => ['token' => $access_token],
                'timeout' => 10,
            ]);
        }

        // Clear OAuth tokens from settings
        $settings['auth_token'] = '';
        $settings['refresh_token'] = '';
        $settings['instance_url'] = '';
        $settings['oauth_enabled'] = false;

        CRMSettings::update_settings($settings);

        wp_send_json_success(['message' => __('Disconnected from Salesforce successfully.', Config::TEXTDOMAIN)]);
    }
}
