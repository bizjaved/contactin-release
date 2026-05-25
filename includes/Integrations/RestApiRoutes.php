<?php
/**
 * Integrations – REST API (Refactored)
 *
 * Registers REST API routes for ContactIn.
 * Routes are only registered if REST API service is enabled
 * in plugin settings. Each route delegates to Admin\RestController.
 *
 * @package ContactIn\Integrations
 * @since   1.6.0
 */

namespace ContactInbox\Integrations;

use ContactInbox\Admin\Controllers\RestController;
use ContactInbox\Core\Config;
use ContactInbox\Core\Logger;
use ContactInbox\Core\Settings as CoreSettings;
use WP_Error;
use WP_REST_Request;
use WP_REST_Server;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
// phpcs:disable WordPress.WP.I18n.NonSingularStringLiteralDomain, WordPress.Security.ValidatedSanitizedInput.MissingUnslash, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized

final class RestApiRoutes {


	// Option key for storing token metadata (hashes only)
	private const TEST_TOKENS_OPTION = 'contactin_test_tokens';

	/**
	 * Initialize route registration on rest_api_init.
	 */
	public static function init(): void {
		add_action( 'rest_api_init', array( __CLASS__, 'register_routes' ) );
		// Allow unauthenticated access to submit endpoint without nonce validation
		add_filter( 'rest_authentication_errors', array( __CLASS__, 'allow_submit_endpoint_auth' ), 10, 1 );
		// Add centralized REST API request/response logging
		add_filter( 'rest_post_dispatch', array( __CLASS__, 'log_request' ), 10, 3 );
	}

	/**
	 * Register REST routes for ContactIN.
	 *
	 * Routes are always registered. Access control is enforced by
	 * each route permission_callback.
	 */
	public static function register_routes(): void {
		$namespace = 'contactin/v1';

		// Submit (create) message
		register_rest_route(
			$namespace,
			Config::REST_ENDPOINT_SUBMIT,
			array(
				'methods'             => 'POST',
				'callback'            => array( '\\ContactInbox\\Admin\\Controllers\\RestController', 'submit' ),
				'permission_callback' => array( __CLASS__, 'permission_public' ),
				'args'                => array(
					'name'    => array(
						'required'          => true,
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
					),
					'email'   => array(
						'required'          => true,
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_email',
					),
					'message' => array(
						'required'          => true,
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_textarea_field',
					),
					'subject' => array(
						'required'          => false,
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
					),
					'consent' => array(
						'required'          => false,
						'type'              => 'integer',
						'sanitize_callback' => 'absint',
					),
				),
			)
		);

		// Read single message
		register_rest_route(
			$namespace,
			self::normalize_route( Config::REST_ENDPOINT_READ ) . '/(?P<id>\d+)',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( RestController::class, 'read' ),
				'permission_callback' => array( __CLASS__, 'permission_guard' ),
				'args'                => array(
					'id' => array(
						'required'          => true,
						'type'              => 'integer',
						'sanitize_callback' => 'absint',
					),
				),
			)
		);

		// Update status
		register_rest_route(
			$namespace,
			self::normalize_route( Config::REST_ENDPOINT_STATUS ) . '/(?P<id>\d+)',
			array(
				'methods'             => WP_REST_Server::EDITABLE,
				'callback'            => array( RestController::class, 'update_status' ),
				'permission_callback' => array( __CLASS__, 'permission_guard' ),
				'args'                => array(
					'id'     => array(
						'required'          => true,
						'type'              => 'integer',
						'sanitize_callback' => 'absint',
					),
					'status' => array(
						'required'          => true,
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
					),
				),
			)
		);

		// List messages
		register_rest_route(
			$namespace,
			self::normalize_route( Config::REST_ENDPOINT_MESSAGES ),
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( RestController::class, 'list_messages' ),
				'permission_callback' => array( __CLASS__, 'permission_guard' ),
				'args'                => array(
					'page'     => array(
						'required'          => false,
						'type'              => 'integer',
						'sanitize_callback' => 'absint',
						'default'           => 1,
					),
					'per_page' => array(
						'required'          => false,
						'type'              => 'integer',
						'sanitize_callback' => 'absint',
						'default'           => Config::INBOX_PER_PAGE,
					),
				),
			)
		);

		// Search messages
		register_rest_route(
			$namespace,
			self::normalize_route( Config::REST_ENDPOINT_SEARCH ),
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( RestController::class, 'search_messages' ),
				'permission_callback' => array( __CLASS__, 'permission_guard' ),
				'args'                => array(
					'q'        => array(
						'required'          => true,
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
					),
					'page'     => array(
						'required'          => false,
						'type'              => 'integer',
						'sanitize_callback' => 'absint',
						'default'           => 1,
					),
					'per_page' => array(
						'required'          => false,
						'type'              => 'integer',
						'sanitize_callback' => 'absint',
						'default'           => Config::INBOX_PER_PAGE,
					),
				),
			)
		);

		// Bulk delete
		register_rest_route(
			$namespace,
			self::normalize_route( Config::REST_ENDPOINT_BULK_DELETE ),
			array(
				'methods'             => WP_REST_Server::DELETABLE,
				'callback'            => array( RestController::class, 'bulk_delete' ),
				'permission_callback' => array( __CLASS__, 'permission_guard' ),
				'args'                => array(
					'ids' => array(
						'required'          => true,
						'type'              => 'array',
						'items'             => array(
							'type' => 'integer',
						),
						'sanitize_callback' => function ( $value ) {
							// Ensure array of positive ints
							if ( ! is_array( $value ) ) {
								return array();
							}
							$out = array_map( 'absint', $value );
							return array_values(
								array_filter(
									$out,
									function ( $v ) {
										return $v > 0;
									}
								)
							);
						},
					),
				),
			)
		);

	}

	/**
	 * Permission guard for all routes.
	 *
	 * Accepts the WP_REST_Request so we can inspect context if needed.
	 *
	 * @param WP_REST_Request $request
	 * @return bool|WP_Error
	 */
	public static function permission_guard( WP_REST_Request $request ): bool|WP_Error {
		$settings = CoreSettings::get_settings();
		if ( empty( $settings['restapi_enable'] ) ) {
			return new WP_Error( Config::ERR_REST_DISABLED, 'REST API service is disabled.', array( 'status' => 403 ) );
		}

		// 1) WP-authenticated user (cookie or Application Passwords)
		if ( is_user_logged_in() && current_user_can( Config::CAPABILITY ) ) {
			return true;
		}

		// 2) Nonce-only admin flow (for admin pages that send X-WP-Nonce)
		$nonce = $request->get_header( 'X-WP-Nonce' ) ?: $request->get_param( 'nonce' );
		if ( $nonce && wp_verify_nonce( $nonce, Config::NONCE_ACTION ) && current_user_can( Config::CAPABILITY ) ) {
			return true;
		}

		// 3) Test token validation (for automated integrations with short-lived tokens)
		$test_token = $request->get_header( 'X-ContactIN-Test-Token' ) ?: $request->get_param( 'token' );
		$route      = $request->get_route();
		if ( $test_token && self::validate_test_token( $test_token, $route ) ) {
			return true;
		}

		return new WP_Error( 'rest_forbidden', 'You do not have permission to access this resource.', array( 'status' => 403 ) );
	}

	/**
	 * Permission guard for public endpoints (form submission, etc.).
	 * Allows anonymous requests since form submission should be publicly accessible.
	 *
	 * @param WP_REST_Request $request
	 * @return bool|WP_Error
	 */
	public static function permission_guard_public( WP_REST_Request $request ): bool|WP_Error {
		$settings = CoreSettings::get_settings();
		if ( empty( $settings['restapi_enable'] ) ) {
			return new WP_Error( Config::ERR_REST_DISABLED, 'REST API service is disabled.', array( 'status' => 403 ) );
		}
		// Public endpoint - allow anonymous submissions
		return true;
	}

	/**
	 * Permission guard for authenticated endpoints (read, update, delete, etc.).
	 * Requires admin user or valid nonce.
	 *
	 * @param WP_REST_Request $request
	 * @return bool|WP_Error
	 */
	private static function normalize_route( string $route ): string {
		return '/' . ltrim( (string) $route, '/' );
	}

	/**
	 * Issue a new test token.
	 *
	 * Returns array with id, raw token (show once), expires_at timestamp.
	 *
	 * @param array $scopes Array of route prefixes allowed, e.g. ['/contactin/v1/submit']
	 * @param int   $ttl_seconds Token lifetime in seconds (default 3600)
	 * @return array
	 */
	public static function issue_test_token( array $scopes, int $ttl_seconds = 3600 ): array {
		$raw           = bin2hex( random_bytes( 24 ) ); // raw token returned to caller once
		$hash          = wp_hash_password( $raw );
		$tokens        = get_option( self::TEST_TOKENS_OPTION, array() );
		$id            = wp_generate_uuid4();
		$tokens[ $id ] = array(
			'hash'       => $hash,
			'expires_at' => time() + $ttl_seconds,
			'scope'      => array_values( $scopes ),
			'created_by' => get_current_user_id(),
			'created_at' => time(),
			'revoked'    => false,
		);
		update_option( self::TEST_TOKENS_OPTION, $tokens );
		return array(
			'id'         => $id,
			'token'      => $raw,
			'expires_at' => $tokens[ $id ]['expires_at'],
		);
	}

	/**
	 * Revoke a test token by id.
	 *
	 * @param string $id
	 * @return bool
	 */
	public static function revoke_test_token( string $id ): bool {
		$tokens = get_option( self::TEST_TOKENS_OPTION, array() );
		if ( empty( $tokens[ $id ] ) ) {
			return false;
		}
		$tokens[ $id ]['revoked'] = true;
		update_option( self::TEST_TOKENS_OPTION, $tokens );
		return true;
	}

	/**
	 * Validate a raw test token for a given route.
	 *
	 * @param string $token Raw token provided by client
	 * @param string $route Matched route (from WP_REST_Request::get_route())
	 * @return bool
	 */
	public static function validate_test_token( string $token, string $route ): bool {
		$tokens = get_option( self::TEST_TOKENS_OPTION, array() );
		if ( empty( $tokens ) || ! is_array( $tokens ) ) {
			return false;
		}

		foreach ( $tokens as $id => $meta ) {
			if ( empty( $meta['hash'] ) || ! empty( $meta['revoked'] ) ) {
				continue;
			}
			if ( ! empty( $meta['expires_at'] ) && $meta['expires_at'] < time() ) {
				continue;
			}

			// Scope check: allow exact match or prefix match
			if ( ! empty( $meta['scope'] ) && is_array( $meta['scope'] ) ) {
				$allowed = false;
				foreach ( $meta['scope'] as $s ) {
					if ( $s === $route || strpos( $route, rtrim( $s, '/' ) ) === 0 ) {
						$allowed = true;
						break;
					}
				}
				if ( ! $allowed ) {
					continue;
				}
			}

			// Compare token using WP password check
			if ( wp_check_password( $token, $meta['hash'] ) ) {
				// Rate limit check (simple transient per token id)
				$rl_key = 'contactin_token_rl_' . md5( $id );
				$limit  = 60; // requests per window
				$window = 60; // seconds
				$count  = (int) get_transient( $rl_key );
				if ( $count >= $limit ) {
					// Optionally log rate limit event
					Logger::warning(
						'REST test token rate limited',
						array(
							'token_id' => $id,
							'route'    => $route,
							'ip'       => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
						)
					);
					return false;
				}
				set_transient( $rl_key, $count + 1, $window );

				// Log usage (minimal): token id, route, IP, timestamp
				$ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
				Logger::debug(
					'REST test token used',
					array(
						'token_id'  => $id,
						'route'     => $route,
						'ip'        => $ip,
						'timestamp' => time(),
					)
				);

				return true;
			}
		}

		return false;
	}

	/**
	 * Public permission callback for form submission endpoint.
	 * Allows both unauthenticated public requests and authenticated admin requests with valid nonce.
	 *
	 * @param WP_REST_Request $request The REST request.
	 * @return bool|WP_Error True to allow access, WP_Error if REST API is disabled.
	 */
	public static function permission_public( WP_REST_Request $request ) {
		// Check if REST API is enabled in settings
		$settings = CoreSettings::get_settings();
		if ( empty( $settings['restapi_enable'] ) ) {
			return new WP_Error(
				'restapi_disabled',
				__( 'REST API service is disabled', 'contactin' ),
				array( 'status' => 403 )
			);
		}

		// If user is authenticated and has the capability, allow with nonce verification
		if ( is_user_logged_in() && current_user_can( 'manage_options' ) ) {
			// Verify nonce for admin users
			$nonce = $request->get_header( 'X-WP-Nonce' );
			if ( $nonce && wp_verify_nonce( $nonce, Config::NONCE_ACTION ) ) {
				return true;
			}
			// Admin without valid nonce gets 403
			return new WP_Error(
				'rest_cookie_invalid_nonce',
				__( 'Nonce verification failed', 'contactin' ),
				array( 'status' => 403 )
			);
		}

		// Allow unauthenticated public access for form submissions
		return true;
	}

	/**
	 * Allow the submit endpoint to bypass nonce verification.
	 * WordPress enforces nonce validation for authenticated requests,
	 * but we want to allow both authenticated and unauthenticated access.
	 *
	 * @param WP_Error|null|bool $auth_error The authentication error, or null/true if authenticated.
	 * @return WP_Error|null|bool The authentication error or null to allow access.
	 */
	public static function allow_submit_endpoint_auth( $auth_error ) {
		// Check if this is a request to our submit endpoint
		$request = \rest_get_server()->last_request ?? null;
		if ( ! $request ) {
			return $auth_error;
		}

		$route = $request->get_route();
		// If this is the submit endpoint, allow access regardless of auth errors
		if ( strpos( $route, '/contactin/v1/submit' ) !== false ) {
			return null; // Null = allow access (no auth error)
		}

		return $auth_error;
	}

	/**
	 * Centralized REST API logging middleware.
	 * Logs all REST API requests and responses for ContactIN endpoints.
	 *
	 * @param WP_REST_Response|WP_Error $response The response object.
	 * @param WP_REST_Server            $server   The REST server.
	 * @param WP_REST_Request           $request  The request object.
	 * @return WP_REST_Response|WP_Error The unmodified response.
	 */
	public static function log_request( $response, $server, $request ) {
		// Only log our ContactIN REST API routes
		$route = $request->get_route();
		if ( strpos( $route, '/contactin/v1/' ) === false ) {
			return $response;
		}

		// Extract request details
		$method          = $request->get_method();
		$endpoint        = $request->get_route();
		$request_headers = $request->get_headers();
		$request_payload = $request->get_json_params() ?? $request->get_body_params() ?? array();

		// Extract response details
		$response_code = 200;
		$response_body = array();

		if ( $response instanceof \WP_REST_Response ) {
			$response_code = $response->get_status();
			$response_body = $response->get_data();
		} elseif ( $response instanceof \WP_Error ) {
			$response_code = $response->get_error_data()['status'] ?? 400;
			$response_body = array(
				'code'    => $response->get_error_code(),
				'message' => $response->get_error_message(),
			);
		}

		// Log the request through the centralized DB class
		\ContactInbox\Core\DB::instance()->log_rest_call(
			array(
				'http_method'     => $method,
				'endpoint'        => $endpoint,
				'request_headers' => $request_headers,
				'request_payload' => $request_payload,
				'response_body'   => $response_body,
				'response_code'   => $response_code,
				'ip_address'      => $_SERVER['REMOTE_ADDR'] ?? '',
				'user_agent'      => $_SERVER['HTTP_USER_AGENT'] ?? '',
				'user_id'         => get_current_user_id(),
			)
		);

		return $response;
	}
}
