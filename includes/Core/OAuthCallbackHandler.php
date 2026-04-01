<?php
/**
 * OAuth Callback Handler
 *
 * Handles the Salesforce OAuth redirect callback.
 * This is kept separate to ensure it fires regardless of other plugin logic.
 *
 * @package ContactIn\Core
 */

declare(strict_types=1);

namespace ContactInbox\Core;

use ContactInbox\Core\CRMSettings;
use ContactInbox\Core\Config;
use ContactInbox\Core\Logger;

// phpcs:disable WordPress.WP.I18n.NonSingularStringLiteralDomain, WordPress.Security.ValidatedSanitizedInput, WordPress.Security.NonceVerification, WordPress.Security.EscapeOutput

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class OAuthCallbackHandler {
	/**
	 * Initialize the callback handler
	 */
	public static function init(): void {
		// Hook into init to catch the redirect early
		add_action( 'init', array( __CLASS__, 'handle_callback' ) );
	}

	/**
	 * Handle OAuth callback from Salesforce
	 */
	public static function handle_callback(): void {
		// Check if this is an OAuth callback request
		// Look for code and state parameters without requiring specific action
		if ( ! isset( $_GET['code'] ) || ! isset( $_GET['state'] ) ) {
			return;
		}

		// Additional check: must be admin-ajax.php or have oauth context
		$request_uri = $_SERVER['REQUEST_URI'] ?? '';
		if ( strpos( $request_uri, 'admin-ajax.php' ) === false && ! isset( $_GET['action'] ) ) {
			return;
		}

		// Verify state parameter
		$state        = isset( $_GET['state'] ) ? sanitize_text_field( $_GET['state'] ) : '';
		$stored_state = get_transient( 'ci_crm_oauth_state' );

		if ( ! $stored_state ) {
			Logger::warning( 'OAuth state transient expired' );
			wp_die( esc_html__( 'OAuth state not found. Session may have expired. Please try again.', 'contactin' ) );
		}

		if ( ! hash_equals( $state, (string) $stored_state ) ) {
			Logger::warning( 'OAuth state mismatch detected' );
			wp_die( esc_html__( 'OAuth state mismatch - possible CSRF attack. Please try again.', 'contactin' ) );
		}

		// Delete the state transient
		delete_transient( 'ci_crm_oauth_state' );

		// Get authorization code
		$code = isset( $_GET['code'] ) ? sanitize_text_field( $_GET['code'] ) : '';
		if ( ! $code ) {
			$error      = isset( $_GET['error'] ) ? sanitize_text_field( $_GET['error'] ) : 'unknown_error';
			$error_desc = isset( $_GET['error_description'] ) ? sanitize_text_field( $_GET['error_description'] ) : '';
			Logger::error(
				'OAuth authorization failed',
				array(
					'error'       => $error,
					'description' => $error_desc,
				)
			);
			wp_die( esc_html__( 'Authorization failed: ', 'contactin' ) . esc_html( $error ) . ' - ' . esc_html( $error_desc ) );
		}

		// Get client ID and secret from settings
		$settings = CRMSettings::get_settings();
		// Choose token URL based on configured environment
		$environment = $settings['environment'] ?? 'production';
		$token_url   = $environment === 'sandbox'
			? 'https://test.salesforce.com/services/oauth2/token'
			: 'https://login.salesforce.com/services/oauth2/token';

		// Use consistent redirect URI (same as generate_oauth_url)
		$redirect_uri = admin_url( 'admin-ajax.php' );

		// Get the code verifier from transient
		$code_verifier = get_transient( 'ci_crm_code_verifier' );
		if ( ! $code_verifier ) {
			Logger::warning( 'OAuth PKCE code verifier missing during callback' );
			wp_die( esc_html__( 'PKCE code verifier expired. Please try again.', 'contactin' ) );
		}

		// Build token request body
		$settings      = CRMSettings::get_settings();
		$client_id     = $settings['salesforce_consumer_key'] ?? '';
		$client_secret = $settings['salesforce_consumer_secret'] ?? '';

		if ( empty( $client_id ) ) {
			Logger::error( 'OAuth Consumer Key missing from settings during callback' );
			wp_die( esc_html__( 'OAuth Consumer Key not configured. Please configure Salesforce OAuth settings.', 'contactin' ) );
		}

		$token_body = array(
			'grant_type'    => 'authorization_code',
			'code'          => $code,
			'client_id'     => $client_id,
			'redirect_uri'  => $redirect_uri,
			'code_verifier' => $code_verifier,
		);

		// Add client secret if available (optional for PKCE, but recommended)
		if ( ! empty( $client_secret ) ) {
			$token_body['client_secret'] = $client_secret;
		}
		$response = wp_remote_post(
			$token_url,
			array(
				'body'    => $token_body,
				'timeout' => 30,
			)
		);

		// Delete the code verifier after use
		delete_transient( 'ci_crm_code_verifier' );

		if ( is_wp_error( $response ) ) {
			Logger::error(
				'OAuth token request failed',
				array(
					'error'    => $response->get_error_message(),
					'endpoint' => $token_url,
				)
			);
			wp_die( esc_html__( 'Token exchange failed: ', 'contactin' ) . esc_html( $response->get_error_message() ) );
		}

		$body      = json_decode( wp_remote_retrieve_body( $response ), true );
		$http_code = wp_remote_retrieve_response_code( $response );

		if ( isset( $body['error'] ) ) {
			$error_desc = $body['error_description'] ?? $body['error'];
			Logger::warning(
				'OAuth token response returned error',
				array(
					'status'      => $http_code,
					'error'       => $body['error'] ?? 'unknown',
					'description' => $error_desc,
				)
			);

			// Redirect to integration page with error details
			wp_safe_redirect(
				add_query_arg(
					array(
						'page'              => 'contactin-crm',
						'error'             => sanitize_text_field( $body['error'] ),
						'error_description' => sanitize_text_field( $error_desc ),
					),
					admin_url( 'admin.php' )
				)
			);
			exit;
		}

		if ( ! isset( $body['access_token'] ) ) {
			Logger::error(
				'OAuth token response missing access token',
				array( 'status' => $http_code )
			);
			wp_die( esc_html__( 'No access token received from Salesforce.', 'contactin' ) );
		}

		// Save tokens securely
		$settings                  = CRMSettings::get_settings();
		$settings['auth_token']    = $body['access_token'];
		$settings['refresh_token'] = $body['refresh_token'] ?? '';
		$settings['instance_url']  = $body['instance_url'];
		$settings['oauth_enabled'] = true;

		// Auto-set the endpoint from instance URL (used by CRMConnector for all sync operations)
		$settings['endpoint'] = $body['instance_url'] . '/services/data/v58.0/sobjects/Contact/';

		Logger::info( 'OAuth endpoint configured', array( 'endpoint' => $settings['endpoint'] ) );

		// Fetch Connected App name for display purposes
		$app_name = self::fetch_connected_app_name( $body['access_token'], $body['instance_url'] );
		if ( ! is_wp_error( $app_name ) ) {
			$settings['connected_app_name'] = $app_name;
		}

		CRMSettings::update_settings( $settings );

		Logger::info( 'OAuth tokens stored successfully' );

		// Redirect back to settings page with success message
		wp_safe_redirect(
			add_query_arg(
				array(
					'page'  => 'contactin-crm',
					'oauth' => 'success',
				),
				admin_url( 'admin.php' )
			)
		);

		exit;
	}

	/**
	 * Fetch the Connected App name from Salesforce
	 *
	 * @param string $access_token OAuth access token
	 * @param string $instance_url Salesforce instance URL
	 * @return string|WP_Error App name or error
	 */
	private static function fetch_connected_app_name( string $access_token, string $instance_url ) {
		// Query the OAuth token info to get app details
		$userinfo_url = $instance_url . '/services/oauth2/userinfo';

		$response = wp_remote_get(
			$userinfo_url,
			array(
				'headers' => array(
					'Authorization' => 'Bearer ' . $access_token,
				),
				'timeout' => 10,
			)
		);

		if ( is_wp_error( $response ) ) {
			return new \WP_Error( 'app_fetch_failed', 'Could not fetch app name' );
		}

		$body = json_decode( wp_remote_retrieve_body( $response ), true );

		// The app name is typically in the response, but if not available,
		// we'll query the connected app via REST API
		if ( isset( $body['organization_id'] ) ) {
			// App name is typically available in OAuth response or instance metadata
			// For now, return a generic name (app name will be set during OAuth setup)
			return 'Salesforce Connected App';
		}

		// Fallback: return a generic name
		return 'Salesforce Connected App';
	}
}
