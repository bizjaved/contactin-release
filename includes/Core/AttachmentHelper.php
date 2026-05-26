<?php
// phpcs:disable WordPress.WP.AlternativeFunctions.unlink_unlink, WordPress.WP.AlternativeFunctions.file_system_operations_fwrite, WordPress.WP.AlternativeFunctions.file_system_operations_is_writable, WordPress.WP.AlternativeFunctions.file_system_operations_fclose, WordPress.WP.AlternativeFunctions.rename_rename, WordPress.WP.AlternativeFunctions.file_system_operations_fopen, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber
/**
 * Attachment Helper
 *
 * Centralized utility methods for attachment file handling:
 * - URL to file path conversion
 * - File validation
 * - Size formatting
 * - JSON data parsing
 *
 * @package ContactIn\Core
 */

declare(strict_types=1);

namespace ContactInbox\Core;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class AttachmentHelper {

	/**
	 * Convert attachment URL to file system path.
	 *
	 * Handles:
	 * - Full URLs from wp_upload_dir (baseurl)
	 * - Full URLs from WP_CONTENT_URL
	 * - Relative paths (fallback)
	 *
	 * @param string $url_or_path URL or relative path
	 * @return string|null File system path, or null if invalid
	 */
	public static function url_to_path( string $url_or_path ): ?string {
		if ( empty( $url_or_path ) ) {
			return null;
		}

		$upload_dir = wp_upload_dir();
		$file_path  = null;

		// Handle full URL from uploads directory
		if ( strpos( $url_or_path, $upload_dir['baseurl'] ) === 0 ) {
			$file_path = str_replace( $upload_dir['baseurl'], $upload_dir['basedir'], $url_or_path );
		}
		// Handle full URL from WP_CONTENT_URL
		elseif ( strpos( $url_or_path, WP_CONTENT_URL ) === 0 ) {
			$file_path = str_replace( WP_CONTENT_URL, WP_CONTENT_DIR, $url_or_path );
		}
		// Handle content_url() alternative
		elseif ( strpos( $url_or_path, content_url( '/uploads/' ) ) === 0 ) {
			$file_path = str_replace( content_url( '/uploads/' ), WP_CONTENT_DIR . '/uploads/', $url_or_path );
		}
		// Handle relative path (fallback)
		elseif ( strpos( $url_or_path, 'http' ) !== 0 ) {
			$file_path = $upload_dir['basedir'] . '/' . ltrim( $url_or_path, '/' );
		}
		// If still null, last attempt with direct replacement
		else {
			$file_path = str_replace(
				array( WP_CONTENT_URL . '/uploads/', content_url( '/uploads/' ) ),
				array( WP_CONTENT_DIR . '/uploads/', WP_CONTENT_DIR . '/uploads/' ),
				$url_or_path
			);
		}

		// Sanitize and validate
		if ( $file_path ) {
			$file_path = realpath( $file_path );
			// Ensure file is within uploads directory for security
			if ( $file_path && strpos( $file_path, $upload_dir['basedir'] ) === 0 ) {
				return $file_path;
			}
		}

		return null;
	}

	/**
	 * Parse attachment JSON data from database.
	 *
	 * Handles:
	 * - JSON string with name, size, path, etc.
	 * - Plain URL string
	 * - Empty/null values
	 *
	 * @param mixed $attachment_data JSON string, URL, or array
	 * @return array Parsed attachment data with 'path' key
	 */
	public static function parse_attachment_data( $attachment_data ): array {
		if ( empty( $attachment_data ) ) {
			return array();
		}

		// Already an array
		if ( is_array( $attachment_data ) ) {
			return $attachment_data;
		}

		// Try to decode as JSON
		if ( is_string( $attachment_data ) && ( $attachment_data[0] === '{' || $attachment_data[0] === '[' ) ) {
			$decoded = json_decode( $attachment_data, true );
			if ( json_last_error() === JSON_ERROR_NONE && is_array( $decoded ) ) {
				return $decoded;
			}
		}

		// Treat as plain URL/path
		if ( is_string( $attachment_data ) ) {
			return array( 'path' => $attachment_data );
		}

		return array();
	}

	/**
	 * Get file information from URL or path.
	 *
	 * @param string $url_or_path Attachment URL or file path
	 * @return array File info: name, size, size_bytes, exists, path
	 */
	public static function get_file_info( string $url_or_path ): array {
		$file_path = self::url_to_path( $url_or_path );

		if ( ! $file_path || ! file_exists( $file_path ) ) {
			return array(
				'name'       => basename( $url_or_path ),
				'size'       => '0 B',
				'size_bytes' => 0,
				'exists'     => false,
				'path'       => $file_path,
			);
		}

		$file_size = filesize( $file_path );

		return array(
			'name'       => basename( $file_path ),
			'size'       => size_format( (int) $file_size ),
			'size_bytes' => (int) $file_size,
			'exists'     => true,
			'path'       => $file_path,
		);
	}

	/**
	 * Extract file path from attachment data (JSON or URL).
	 *
	 * Convenience method that combines parse + url_to_path.
	 *
	 * @param mixed $attachment_data JSON string, URL, or array
	 * @return string|null File system path, or null if not found/invalid
	 */
	public static function extract_file_path( $attachment_data ): ?string {
		$parsed = self::parse_attachment_data( $attachment_data );

		if ( empty( $parsed['path'] ) ) {
			return null;
		}

		return self::url_to_path( $parsed['path'] );
	}

	/**
	 * Extract one or more file paths from attachment data.
	 *
	 * Supports:
	 * - Single attachment array with 'path'
	 * - Array of attachments (list)
	 * - JSON string (single or list)
	 * - Plain URL/path string
	 *
	 * @param mixed $attachment_data JSON string, URL, or array
	 * @return array List of valid file paths
	 */
	public static function extract_file_paths( $attachment_data ): array {
		$parsed = self::parse_attachment_data( $attachment_data );
		$paths  = array();

		if ( empty( $parsed ) ) {
			return $paths;
		}

		if ( isset( $parsed['path'] ) ) {
			$path = self::url_to_path( $parsed['path'] );
			if ( empty( $path ) ) {
				Logger::warning(
					'Failed to convert attachment URL to file path',
					array(
						'url'    => $parsed['path'],
						'reason' => 'No matching URL pattern matched in url_to_path()',
					)
				);
			} elseif ( ! file_exists( $path ) ) {
				Logger::warning(
					'Attachment file does not exist at converted path',
					array(
						'url'            => $parsed['path'],
						'converted_path' => $path,
						'reason'         => 'File was deleted or path conversion failed',
					)
				);
			} else {
				$paths[] = $path;
			}
			return $paths;
		}

		if ( is_array( $parsed ) ) {
			foreach ( $parsed as $item ) {
				if ( ! is_array( $item ) || empty( $item['path'] ) ) {
					continue;
				}
				$path = self::url_to_path( $item['path'] );
				if ( empty( $path ) ) {
					Logger::warning(
						'Failed to convert attachment URL to file path in array',
						array(
							'url'    => $item['path'],
							'reason' => 'No matching URL pattern matched in url_to_path()',
						)
					);
				} elseif ( ! file_exists( $path ) ) {
					Logger::warning(
						'Attachment file does not exist at converted path in array',
						array(
							'url'            => $item['path'],
							'converted_path' => $path,
							'reason'         => 'File was deleted or path conversion failed',
						)
					);
				} else {
					$paths[] = $path;
				}
			}
		}

		return $paths;
	}

	/**
	 * Validate file exists and is readable.
	 *
	 * @param string $file_path File system path
	 * @return bool True if file exists and is readable
	 */
	public static function is_valid_file( string $file_path ): bool {
		return ! empty( $file_path ) && file_exists( $file_path ) && is_readable( $file_path );
	}

	/**
	 * Check if file is within allowed uploads directory.
	 * Security check to prevent directory traversal.
	 *
	 * @param string $file_path File system path
	 * @return bool True if file is within uploads directory
	 */
	public static function is_within_uploads_dir( string $file_path ): bool {
		$upload_dir = wp_upload_dir();
		$real_path  = realpath( $file_path );

		return (bool) ( $real_path && strpos( $real_path, $upload_dir['basedir'] ) === 0 );
	}

	/**
	 * Robustly delete a file with retry logic and detailed logging.
	 *
	 * Uses WordPress Filesystem API with fallback to direct deletion.
	 * Implements retry logic to handle file locks and permission issues.
	 *
	 * @param string $file_path File system path to delete
	 * @param int    $max_retries Maximum retry attempts (default: 3)
	 * @param int    $retry_delay Delay between retries in milliseconds (default: 100ms)
	 * @return array Status: ['success' => bool, 'message' => string, 'path' => string, 'attempts' => int]
	 */
	public static function delete_file_safely( string $file_path, int $max_retries = 3, int $retry_delay = 100 ): array {
		if ( empty( $file_path ) || ! file_exists( $file_path ) ) {
			return array(
				'success'  => false,
				'message'  => 'File does not exist',
				'path'     => $file_path,
				'attempts' => 0,
			);
		}

		// Security check: ensure file is within uploads directory
		if ( ! self::is_within_uploads_dir( $file_path ) ) {
			return array(
				'success'  => false,
				'message'  => 'File is outside uploads directory (security check)',
				'path'     => $file_path,
				'attempts' => 0,
			);
		}

		$attempt    = 0;
		$last_error = '';

		while ( $attempt < $max_retries ) {
			++$attempt;

			try {
				// Try WordPress Filesystem API first (respects file permissions better)
				$wp_filesystem_initialized = false;
				if ( function_exists( 'WP_Filesystem' ) ) {
					require_once ABSPATH . 'wp-admin/includes/file.php';
					if ( WP_Filesystem() ) {
						global $wp_filesystem;
						if ( $wp_filesystem && method_exists( $wp_filesystem, 'delete' ) ) {
							if ( $wp_filesystem->delete( $file_path ) ) {
								Logger::info(
									'File deleted successfully (WP_Filesystem)',
									array(
										'path'    => $file_path,
										'attempt' => $attempt,
									)
								);
								return array(
									'success'  => true,
									'message'  => 'File deleted successfully',
									'path'     => $file_path,
									'attempts' => $attempt,
								);
							}
							$wp_filesystem_initialized = true;
						}
					}
				}

				// Fallback: direct deletion if WP_Filesystem failed or unavailable
				if ( ! $wp_filesystem_initialized || file_exists( $file_path ) ) {
					// Ensure permissions allow deletion
					if ( is_file( $file_path ) && is_writable( $file_path ) ) {
						if ( @unlink( $file_path ) ) { // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- Intentional: unlink() warnings suppressed; pre-flight checks done above.
							Logger::info(
								'File deleted successfully (direct)',
								array(
									'path'    => $file_path,
									'attempt' => $attempt,
								)
							);
							return array(
								'success'  => true,
								'message'  => 'File deleted successfully',
								'path'     => $file_path,
								'attempts' => $attempt,
							);
						} else {
							$last_error = 'unlink() failed - possible permission issue';
						}
					} else {
						$last_error = 'File is not writable or not a regular file';
					}
				}
			} catch ( \Throwable $e ) {
				$last_error = $e->getMessage();
			}

			// If not last attempt, wait before retry
			if ( $attempt < $max_retries && file_exists( $file_path ) ) {
				usleep( $retry_delay * 1000 ); // Convert ms to microseconds
			}
		}

		// All retries failed
		Logger::warning(
			'Failed to delete file after retries',
			array(
				'path'     => $file_path,
				'attempts' => $attempt,
				'reason'   => $last_error,
			)
		);

		return array(
			'success'  => false,
			'message'  => "Failed to delete after {$attempt} attempt(s): {$last_error}",
			'path'     => $file_path,
			'attempts' => $attempt,
		);
	}

	/**
	 * Delete multiple files and return detailed results.
	 *
	 * @param array $file_paths Array of file paths to delete
	 * @param int   $max_retries Maximum retry attempts per file
	 * @return array ['deleted' => [], 'failed' => [], 'stats' => ['total' => int, 'succeeded' => int, 'failed' => int]]
	 */
	public static function delete_files_safely( array $file_paths, int $max_retries = 3 ): array {
		$deleted = array();
		$failed  = array();

		foreach ( $file_paths as $path ) {
			$result = self::delete_file_safely( $path, $max_retries );

			if ( $result['success'] ) {
				$deleted[] = $path;
			} else {
				$failed[] = array(
					'path'     => $path,
					'reason'   => $result['message'],
					'attempts' => $result['attempts'],
				);
			}
		}

		return array(
			'deleted' => $deleted,
			'failed'  => $failed,
			'stats'   => array(
				'total'     => count( $file_paths ),
				'succeeded' => count( $deleted ),
				'failed'    => count( $failed ),
			),
		);
	}

	/**
	 * Extract the original filename from attachment JSON data.
	 *
	 * Resolves the original uploaded filename (not the file ID).
	 * Mirrors Message::get_attachment() logic.
	 *
	 * @param mixed $attachment_data JSON string, URL, or array
	 * @return string Original filename, or basename of path if not stored
	 */
	public static function get_original_filename( $attachment_data ): string {
		if ( empty( $attachment_data ) ) {
			return '';
		}

		// Parse the attachment data
		$parsed = self::parse_attachment_data( $attachment_data );

		if ( empty( $parsed ) ) {
			return '';
		}

		// Check for explicit name field (stored original filename)
		if ( ! empty( $parsed['name'] ) ) {
			return $parsed['name'];
		}

		// Fallback to basename of path
		if ( ! empty( $parsed['path'] ) ) {
			return basename( $parsed['path'] );
		}

		return '';
	}
}
