<?php
// phpcs:disable WordPress.WP.AlternativeFunctions.unlink_unlink, WordPress.WP.AlternativeFunctions.file_system_operations_fwrite, WordPress.WP.AlternativeFunctions.file_system_operations_is_writable, WordPress.WP.AlternativeFunctions.file_system_operations_fclose, WordPress.WP.AlternativeFunctions.rename_rename, WordPress.WP.AlternativeFunctions.file_system_operations_fopen, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber, Generic.PHP.ForbiddenFunctions.Found, PluginCheck.CodeAnalysis.DiscouragedFunctions.load_plugin_textdomainFound, PluginCheck.CodeAnalysis.Heredoc.NotAllowed, PluginCheck.Security.DirectDB.UnescapedDBParameter, Squiz.PHP.DiscouragedFunctions.Discouraged, WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.NamingConventions.PrefixAllGlobals.DynamicHooknameFound, WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound, WordPress.PHP.DevelopmentFunctions.error_log_debug_backtrace, WordPress.WP.AlternativeFunctions.file_system_operations_fsockopen, WordPress.WP.AlternativeFunctions.file_system_operations_readfile, WordPress.WP.AlternativeFunctions.file_system_operations_rmdir, WordPress.WP.EnqueuedResourceParameters.MissingVersion, WordPress.WP.EnqueuedResources.NonEnqueuedScript, WordPress.WP.I18n.MissingArgDomain, WordPress.WP.I18n.UnorderedPlaceholdersPlural, WordPress.WP.I18n.UnorderedPlaceholdersSingle
/**
 * Service Health Check – Verify External Service Availability
 *
 * Pre-flight checks before attempting operations on external services
 * Supports: SMTP, CRM endpoints, Webhook servers
 *
 * @package ContactIn
 */

namespace ContactInbox\Core;

use ContactInbox\Traits\Singleton;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class ServiceHealthCheck {
	use Singleton;

	const HEALTH_CHECK_INTERVAL = 300; // 5 minutes
	const HEALTH_CHECK_OPTION   = 'contactin_health_checks';

	/**
	 * Check SMTP service health
	 *
	 * @return array Health status
	 */
	public static function check_smtp(): array {
		try {
			$settings = Settings::get_settings();

			// Check if SMTP is configured
			if ( empty( $settings['smtp_host'] ) || empty( $settings['smtp_port'] ) ) {
				return array(
					'available' => false,
					'reason'    => 'SMTP not configured',
					'timestamp' => current_time( 'mysql' ),
				);
			}

			// Try to connect to SMTP server
			$host    = $settings['smtp_host'];
			$port    = intval( $settings['smtp_port'] );
			$timeout = 5;

			$connection = @fsockopen( $host, $port, $errno, $errstr, $timeout ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- Intentional: fsockopen() generates warnings on unreachable hosts $errno/$errstr capture the error.;

			if ( $connection === false ) {
				return array(
					'available' => false,
					'reason'    => "Cannot connect to {$host}:{$port} ({$errstr})",
					'timestamp' => current_time( 'mysql' ),
				);
			}

			fclose( $connection );

			return array(
				'available' => true,
				'reason'    => 'SMTP server responding',
				'timestamp' => current_time( 'mysql' ),
			);
		} catch ( \Throwable $e ) {
			return array(
				'available' => false,
				'reason'    => $e->getMessage(),
				'timestamp' => current_time( 'mysql' ),
			);
		}
	}

	/**
	 * Check CRM endpoint health
	 *
	 * @return array Health status
	 */
	public static function check_crm(): array {
		try {
			$crm_settings = get_option( 'contactin_crm_settings', array() );

			// Check if CRM is configured
			if ( empty( $crm_settings['enabled'] ) || empty( $crm_settings['endpoint'] ) ) {
				return array(
					'available' => false,
					'reason'    => 'CRM not configured',
					'timestamp' => current_time( 'mysql' ),
				);
			}

			$endpoint = $crm_settings['endpoint'];

			// HEAD request to check endpoint availability (lightweight)
			$response = wp_remote_head(
				$endpoint,
				array(
					'timeout'   => 5,
					'sslverify' => apply_filters( 'https_local_ssl_verify', false ),
				)
			);

			if ( is_wp_error( $response ) ) {
				return array(
					'available' => false,
					'reason'    => 'CRM endpoint unreachable: ' . $response->get_error_message(),
					'timestamp' => current_time( 'mysql' ),
				);
			}

			$code = wp_remote_retrieve_response_code( $response );

			if ( $code >= 400 ) {
				return array(
					'available' => false,
					'reason'    => "CRM endpoint returned HTTP {$code}",
					'timestamp' => current_time( 'mysql' ),
				);
			}

			return array(
				'available' => true,
				'reason'    => "CRM endpoint responding (HTTP {$code})",
				'timestamp' => current_time( 'mysql' ),
			);
		} catch ( \Throwable $e ) {
			return array(
				'available' => false,
				'reason'    => $e->getMessage(),
				'timestamp' => current_time( 'mysql' ),
			);
		}
	}

	/**
	 * Check webhook endpoint health (if configured)
	 *
	 * @return array Health status
	 */
	public static function check_webhooks(): array {
		try {
			$settings = get_option( 'contactin_webhook_settings', array() );

			if ( empty( $settings['enabled'] ) || empty( $settings['url'] ) ) {
				return array(
					'available' => false,
					'reason'    => 'Webhooks not configured',
					'timestamp' => current_time( 'mysql' ),
				);
			}

			$endpoint = $settings['url'];

			// HEAD request to check endpoint
			$response = wp_remote_head(
				$endpoint,
				array(
					'timeout'   => 5,
					'sslverify' => apply_filters( 'https_local_ssl_verify', false ),
				)
			);

			if ( is_wp_error( $response ) ) {
				return array(
					'available' => false,
					'reason'    => 'Webhook endpoint unreachable: ' . $response->get_error_message(),
					'timestamp' => current_time( 'mysql' ),
				);
			}

			$code = wp_remote_retrieve_response_code( $response );

			if ( $code >= 400 ) {
				return array(
					'available' => false,
					'reason'    => "Webhook endpoint returned HTTP {$code}",
					'timestamp' => current_time( 'mysql' ),
				);
			}

			return array(
				'available' => true,
				'reason'    => "Webhook endpoint responding (HTTP {$code})",
				'timestamp' => current_time( 'mysql' ),
			);
		} catch ( \Throwable $e ) {
			return array(
				'available' => false,
				'reason'    => $e->getMessage(),
				'timestamp' => current_time( 'mysql' ),
			);
		}
	}

	/**
	 * Run all health checks (cached)
	 *
	 * @return array All service health statuses
	 */
	public static function check_all(): array {
		try {
			$checks = get_transient( self::HEALTH_CHECK_OPTION );

			if ( is_array( $checks ) && ! empty( $checks['timestamp'] ) ) {
				$age = time() - strtotime( $checks['timestamp'] );
				if ( $age < self::HEALTH_CHECK_INTERVAL ) {
					return $checks; // Use cached results
				}
			}

			$checks = array(
				'smtp'      => self::check_smtp(),
				'crm'       => self::check_crm(),
				'webhooks'  => self::check_webhooks(),
				'timestamp' => current_time( 'mysql' ),
			);

			set_transient( self::HEALTH_CHECK_OPTION, $checks, self::HEALTH_CHECK_INTERVAL );

			return $checks;
		} catch ( \Throwable $e ) {
			Logger::error(
				'Failed to run health checks',
				array(
					'error' => $e->getMessage(),
				)
			);
			return array();
		}
	}

	/**
	 * Clear cached health checks (force refresh)
	 */
	public static function clear_cache(): void {
		delete_transient( self::HEALTH_CHECK_OPTION );
	}
}
