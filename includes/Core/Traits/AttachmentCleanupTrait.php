<?php
namespace ContactInbox\Core\Traits;

use ContactInbox\Core\AttachmentHelper;
use ContactInbox\Core\Logger;
use WP_Filesystem_Direct;

// phpcs:disable WordPress.WP.AlternativeFunctions.unlink_unlink, WordPress.WP.AlternativeFunctions.file_system_operations_fwrite, WordPress.WP.AlternativeFunctions.file_system_operations_is_writable, WordPress.WP.AlternativeFunctions.file_system_operations_fclose, WordPress.WP.AlternativeFunctions.rename_rename, WordPress.WP.AlternativeFunctions.file_system_operations_fopen, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
trait AttachmentCleanupTrait {
	/**
	 * Delete given attachments from disk
	 *
	 * @param array $files
	 * @return array [ 'deleted' => [...], 'failed' => [...] ]
	 */
	public function delete_attachments( array $files ) {
		$uploads_dir = AttachmentHelper::get_attachment_upload_dir();

		// Check if directory exists and is writable
		if ( ! is_dir( $uploads_dir ) ) {
			Logger::warning( 'Attachment directory does not exist', array( 'path' => $uploads_dir ) );
			return array(
				'deleted' => array(),
				'failed'  => $files,
			);
		}

		if ( ! is_writable( $uploads_dir ) ) {
			$perms = substr( sprintf( '%o', fileperms( $uploads_dir ) ), -4 );
			Logger::error(
				'Attachment directory is not writable',
				array(
					'path'        => $uploads_dir,
					'permissions' => $perms,
					'uid'         => getmyuid(),
					'gid'         => getmygid(),
				)
			);
			return array(
				'deleted' => array(),
				'failed'  => $files,
			);
		}

		$deleted = array();
		$failed  = array();

		foreach ( $files as $file ) {
			$path = $uploads_dir . $file;

			try {
				if ( ! file_exists( $path ) ) {
					// File doesn't exist, consider it deleted
					$deleted[] = $file;
					Logger::debug( "Attachment file does not exist, marking as deleted: {$file}" );
					continue;
				}

				// Use native PHP unlink for better reliability
				if ( is_file( $path ) && @unlink( $path ) ) { // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- Intentional: unlink() warnings suppressed; is_file() checked.
					$deleted[] = $file;
					Logger::debug( "Successfully deleted attachment file: {$file}" );
				} else {
					// If unlink fails, try WP_Filesystem as fallback
					$fs = new WP_Filesystem_Direct( false );
					if ( $fs->delete( $path ) ) {
						$deleted[] = $file;
						Logger::debug( "Successfully deleted attachment file via WP_Filesystem: {$file}" );
					} else {
						$perms = is_file( $path ) ? substr( sprintf( '%o', fileperms( $path ) ), -4 ) : 'N/A';
						Logger::warning(
							'Failed to delete attachment file',
							array(
								'file'        => $file,
								'path'        => $path,
								'permissions' => $perms,
								'exists'      => file_exists( $path ),
							)
						);
						$failed[] = $file;
					}
				}
			} catch ( \Throwable $e ) {
				Logger::error(
					'Exception while deleting attachment',
					array(
						'file'  => $file,
						'error' => $e->getMessage(),
					)
				);
				$failed[] = $file;
			}
		}

		Logger::info(
			'Attachment deletion completed',
			array(
				'total'   => count( $files ),
				'deleted' => count( $deleted ),
				'failed'  => count( $failed ),
			)
		);

		return array(
			'deleted' => $deleted,
			'failed'  => $failed,
		);
	}

	/**
	 * Delete old temporary files (older than 24 hours)
	 *
	 * @return array [ 'deleted' => [...], 'failed' => [...] ]
	 */
	public function delete_old_temp_files() {
		$temp_dir = AttachmentHelper::get_temp_upload_dir();

		if ( ! is_dir( $temp_dir ) ) {
			return array(
				'deleted' => array(),
				'failed'  => array(),
			);
		}

		if ( ! is_writable( $temp_dir ) ) {
			$perms = substr( sprintf( '%o', fileperms( $temp_dir ) ), -4 );
			Logger::warning(
				'Temp directory is not writable',
				array(
					'path'        => $temp_dir,
					'permissions' => $perms,
				)
			);
			return array(
				'deleted' => array(),
				'failed'  => array(),
			);
		}

		$fs      = new WP_Filesystem_Direct( false );
		$deleted = array();
		$failed  = array();
		$cutoff  = time() - ( 24 * 60 * 60 ); // 24 hours ago

		$files = scandir( $temp_dir );
		if ( ! is_array( $files ) ) {
			return array(
				'deleted' => array(),
				'failed'  => array(),
			);
		}

		foreach ( $files as $file ) {
			if ( $file === '.' || $file === '..' ) {
				continue;
			}

			$path = $temp_dir . $file;

			try {
				if ( is_file( $path ) && filemtime( $path ) < $cutoff ) {
					if ( $fs->delete( $path ) ) {
						$deleted[] = $file;
					} else {
						Logger::warning( 'Failed to delete old temp file', array( 'file' => $file ) );
						$failed[] = $file;
					}
				}
			} catch ( \Throwable $e ) {
				Logger::error(
					'Exception while deleting temp file',
					array(
						'file'  => $file,
						'error' => $e->getMessage(),
					)
				);
				$failed[] = $file;
			}
		}

		return array(
			'deleted' => $deleted,
			'failed'  => $failed,
		);
	}
}
