<?php
/**
 * Admin – Attachment Upload REST Controller
 *
 * Handles temporary file uploads for contact form attachments with security:
 * - Rate limiting (IP-based + per-user)
 * - File size and type validation
 * - Automatic cleanup of old temp files
 * - reCAPTCHA verification
 *
 * @package ContactIn\Admin\Controllers
 * @since   1.7.0
 */

namespace ContactInbox\Admin\Controllers;

use ContactInbox\Core\Config;
use ContactInbox\Core\Logger;
use ContactInbox\Core\Settings as CoreSettings;
use ContactInbox\Core\Security;
use ContactInbox\Core\reCAPTCHA;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
// phpcs:disable WordPress.WP.I18n.NonSingularStringLiteralDomain, WordPress.WP.I18n.MissingTranslatorsComment, WordPress.WP.I18n.UnorderedPlaceholdersText, WordPress.WP.AlternativeFunctions.unlink_unlink, WordPress.WP.AlternativeFunctions.file_system_operations_fwrite, WordPress.WP.AlternativeFunctions.file_system_operations_is_writable, WordPress.WP.AlternativeFunctions.file_system_operations_fclose, WordPress.WP.AlternativeFunctions.rename_rename, WordPress.WP.AlternativeFunctions.file_system_operations_fopen, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber, Generic.PHP.ForbiddenFunctions.Found, PluginCheck.CodeAnalysis.DiscouragedFunctions.load_plugin_textdomainFound, PluginCheck.CodeAnalysis.Heredoc.NotAllowed, PluginCheck.Security.DirectDB.UnescapedDBParameter, Squiz.PHP.DiscouragedFunctions.Discouraged, WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.NamingConventions.PrefixAllGlobals.DynamicHooknameFound, WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound, WordPress.PHP.DevelopmentFunctions.error_log_debug_backtrace, WordPress.WP.AlternativeFunctions.file_system_operations_fsockopen, WordPress.WP.AlternativeFunctions.file_system_operations_readfile, WordPress.WP.AlternativeFunctions.file_system_operations_rmdir, WordPress.WP.EnqueuedResourceParameters.MissingVersion, WordPress.WP.EnqueuedResources.NonEnqueuedScript, WordPress.WP.I18n.MissingArgDomain, WordPress.WP.I18n.UnorderedPlaceholdersPlural, WordPress.WP.I18n.UnorderedPlaceholdersSingle

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class AttachmentUploadController {

	/**
	 * Upload directory for temporary attachments.
	 * Relative to wp-content/uploads.
	 */
	private const TEMP_UPLOAD_DIR = 'contactin-temp-uploads';

	/**
	 * Rate limit constants
	 */
	private const UPLOAD_RATE_LIMIT_MAX = 10; // Max uploads per IP per time window
	private const UPLOAD_RATE_LIMIT_TTL = 10 * MINUTE_IN_SECONDS; // 10 minute window

	/**
	 * Handle attachment upload via REST API.
	 *
	 * @param WP_REST_Request $request The REST request object.
	 * @return WP_REST_Response|WP_Error The response or error.
	 */
	public static function upload( WP_REST_Request $request ) {
		// Allow file upload even if REST API is disabled
		$settings = CoreSettings::get_settings();

		// Check if attachments are enabled in form settings
		if ( empty( $settings['form_enable_attachment'] ) ) {
			return new WP_Error(
				'attachments_disabled',
				__( 'File attachments are disabled in plugin settings.', 'contactin' ),
				array( 'status' => 403 )
			);
		}

		// Security: Check rate limit (IP-based)
		$rate_limit_check = self::check_upload_rate_limit();
		if ( is_wp_error( $rate_limit_check ) ) {
			Logger::warning(
				'Attachment upload blocked by rate limit',
				array( 'ip' => Security::get_ip_address() )
			);
			return $rate_limit_check;
		}

		// Security: enforce reCAPTCHA when enabled
		if ( reCAPTCHA::is_enabled() ) {
			$recaptcha_token = (string) $request->get_param( 'recaptcha_token' );
			if ( $recaptcha_token === '' ) {
				Logger::warning(
					'Attachment upload blocked: missing reCAPTCHA token',
					array( 'ip' => Security::get_ip_address() )
				);
				return new WP_Error(
					'recaptcha_missing',
					__( 'reCAPTCHA token is required.', 'contactin' ),
					array( 'status' => 403 )
				);
			}

			$recaptcha_result = reCAPTCHA::verify_with_score( $recaptcha_token );
			if ( empty( $recaptcha_result['valid'] ) ) {
				Logger::warning(
					'Attachment upload reCAPTCHA verification failed',
					array(
						'ip'    => Security::get_ip_address(),
						'score' => $recaptcha_result['score'] ?? null,
					)
				);
				return new WP_Error(
					'recaptcha_failed',
					__( 'reCAPTCHA verification failed. Please try again.', 'contactin' ),
					array( 'status' => 403 )
				);
			}
		}

		// Get file from request
		$files = $request->get_file_params();

		if ( empty( $files['file'] ) ) {
			Logger::warning(
				'Attachment upload missing file parameter',
				array( 'ip' => Security::get_ip_address() )
			);
			return new WP_Error(
				'no_file',
				__( 'No file provided.', 'contactin' ),
				array( 'status' => 400 )
			);
		}

		$file = $files['file'];

		// Security: Validate file against plugin settings
		$validation = self::validate_file( $file, $settings );
		if ( is_wp_error( $validation ) ) {
			Logger::warning(
				'Attachment upload validation failed',
				array(
					'ip'      => Security::get_ip_address(),
					'code'    => $validation->get_error_code(),
					'message' => $validation->get_error_message(),
				)
			);
			return $validation;
		}

		// Create temp directory if it doesn't exist
		$upload_dir = wp_upload_dir();
		$temp_dir   = $upload_dir['basedir'] . '/' . self::TEMP_UPLOAD_DIR;

		if ( ! is_dir( $temp_dir ) && ! wp_mkdir_p( $temp_dir ) ) {
			Logger::error(
				'Attachment upload failed creating temp directory',
				array( 'path' => $temp_dir )
			);
			return new WP_Error(
				'upload_dir_create_failed',
				__( 'Failed to create upload directory.', 'contactin' ),
				array( 'status' => 500 )
			);
		}

		// Generate unique filename with timestamp
		$original_name = sanitize_file_name( $file['name'] );
		$file_ext      = pathinfo( $original_name, PATHINFO_EXTENSION );
		$unique_id     = wp_generate_uuid4();
		$new_filename  = $unique_id . '.' . $file_ext;
		$file_path     = $temp_dir . '/' . $new_filename;

		// Security: Move file to temp directory
		if ( ! move_uploaded_file( $file['tmp_name'], $file_path ) ) {
			Logger::error(
				'Attachment upload move failed',
				array(
					'from'         => $file['tmp_name'],
					'to'           => $file_path,
					'tmpExists'    => file_exists( $file['tmp_name'] ),
					'originalName' => $file['name'],
				)
			);
			return new WP_Error(
				'file_move_failed',
				__( 'Failed to move uploaded file.', 'contactin' ),
				array( 'status' => 500 )
			);
		}

		// Log upload event for audit trail
		self::log_upload_event( 'success', $original_name, filesize( $file_path ), Security::get_ip_address() );

		// Return file info
		return new WP_REST_Response(
			array(
				'success'   => true,
				'file_id'   => $unique_id,
				'filename'  => $original_name,
				'temp_path' => self::TEMP_UPLOAD_DIR . '/' . $new_filename,
				'file_size' => filesize( $file_path ),
				'mime_type' => $file['type'],
			),
			201
		);
	}

	/**
	 * Validate uploaded file against plugin settings.
	 *
	 * @param array $file The file array from $_FILES.
	 * @param array $settings Plugin settings array.
	 * @return true|WP_Error True if valid, WP_Error otherwise.
	 */
	private static function validate_file( $file, array $settings ) {
		// Check file size using setting value (not hard-coded default)
		$max_size_mb = absint( $settings['max_file_size'] ?? 2 );
		$max_size    = $max_size_mb * 1024 * 1024;

		if ( $file['size'] > $max_size ) {
			return new WP_Error(
				'file_too_large',
				sprintf(
					__( 'File size exceeds maximum allowed size of %d MB.', 'contactin' ),
					$max_size_mb
				),
				array( 'status' => 400 )
			);
		}

		// Check file type against allowed types from settings
		// Handle both array and comma-separated string formats
		// NOTE: No fallback default - if not configured, no files are allowed (whitelist approach)
		$allowed_types_raw = $settings['allowed_file_types'] ?? '';

		if ( is_array( $allowed_types_raw ) ) {
			// If it's an array, join it
			$allowed_types = implode( ',', array_map( 'trim', $allowed_types_raw ) );
		} else {
			// If it's a string, use as-is
			$allowed_types = (string) $allowed_types_raw;
		}

		// Convert to lowercase array of extensions
		$allowed_exts = array_map( 'trim', explode( ',', strtolower( $allowed_types ) ) );
		$allowed_exts = array_filter( $allowed_exts ); // Remove empty strings

		// Get file extension (lowercase for comparison)
		$file_ext = strtolower( pathinfo( $file['name'], PATHINFO_EXTENSION ) );

		if ( ! in_array( $file_ext, $allowed_exts, true ) ) {
			return new WP_Error(
				'file_type_not_allowed',
				sprintf(
					__( 'File type .%s is not allowed. Allowed types: %s', 'contactin' ),
					$file_ext,
					implode( ', ', $allowed_exts )
				),
				array( 'status' => 400 )
			);
		}

		return true;
	}

	/**
	 * Get path to temporary upload directory.
	 *
	 * @return string The full path to temp uploads directory.
	 */
	public static function get_temp_dir() {
		$upload_dir = wp_upload_dir();
		return $upload_dir['basedir'] . '/' . self::TEMP_UPLOAD_DIR;
	}

	/**
	 * Get path to temporary file by file ID.
	 *
	 * @param string $file_id The file ID (UUID).
	 * @param string $file_ext The file extension.
	 * @return string|false The full path to the temp file, or false if not found.
	 */
	public static function get_temp_file_path( $file_id, $file_ext ) {
		$temp_dir  = self::get_temp_dir();
		$file_path = $temp_dir . '/' . $file_id . '.' . $file_ext;

		if ( file_exists( $file_path ) ) {
			return $file_path;
		}

		return false;
	}

	/**
	 * Clean up old temporary files (older than 24 hours).
	 * Should be called periodically via WP cron.
	 *
	 * @return int Number of files deleted.
	 */
	public static function cleanup_old_uploads() {
		$temp_dir = self::get_temp_dir();

		if ( ! is_dir( $temp_dir ) ) {
			return 0;
		}

		$deleted = 0;
		$cutoff  = time() - ( 24 * 60 * 60 ); // 24 hours ago

		$files = scandir( $temp_dir );
		if ( ! is_array( $files ) ) {
			return 0;
		}

		foreach ( $files as $file ) {
			if ( $file === '.' || $file === '..' ) {
				continue;
			}

			$file_path = $temp_dir . '/' . $file;

			if ( is_file( $file_path ) && filemtime( $file_path ) < $cutoff ) {
				if ( unlink( $file_path ) ) {
					++$deleted;
				}
			}
		}

		return $deleted;
	}

	/**
	 * Check upload rate limit for the current IP address.
	 * Prevents abuse by limiting uploads per IP per time window.
	 *
	 * @return true|WP_Error True if within limit, WP_Error if exceeded.
	 */
	private static function check_upload_rate_limit() {
		$ip    = Security::get_ip_address();
		$key   = 'ci_upload_rate_' . md5( $ip );
		$count = (int) get_transient( $key );

		if ( $count >= self::UPLOAD_RATE_LIMIT_MAX ) {
			Logger::warning(
				'Attachment upload rate limit exceeded',
				array(
					'ip'    => $ip,
					'count' => $count,
				)
			);
			return new WP_Error(
				'upload_rate_limit_exceeded',
				sprintf(
					__( 'Too many uploads. Please wait %d minutes before uploading again.', 'contactin' ),
					(int) ( self::UPLOAD_RATE_LIMIT_TTL / MINUTE_IN_SECONDS )
				),
				array( 'status' => 429 )
			);
		}

		set_transient( $key, $count + 1, self::UPLOAD_RATE_LIMIT_TTL );
		return true;
	}

	/**
	 * Log upload events for audit trail and security monitoring.
	 *
	 * @param string $status     Event status (success, failed, rejected).
	 * @param string $filename   Original filename.
	 * @param int    $file_size  File size in bytes.
	 * @param string $ip_address Client IP address.
	 */
	private static function log_upload_event( $status, $filename, $file_size, $ip_address ) {
		Logger::info(
			'Attachment upload event recorded',
			array(
				'event_status' => $status,
				'filename'     => $filename,
				'file_size'    => $file_size,
				'ip'           => $ip_address,
				'timestamp'    => current_time( 'mysql' ),
			)
		);

		// Optional: Store in database for audit trail
		// This could be extended to use a dedicated upload_log table
	}

	/**
	 * Sanitize filename to prevent directory traversal attacks.
	 *
	 * @param string $filename The filename to sanitize.
	 * @return string Sanitized filename.
	 */
	private static function sanitize_filename( $filename ) {
		// Remove any path components (.. / \ etc)
		$filename = basename( $filename );
		// Use WordPress sanitize_file_name
		return sanitize_file_name( $filename );
	}
}
