<?php
namespace ContactInbox\Core\Traits;

use ContactInbox\Core\AttachmentHelper;
use ContactInbox\Core\DB;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
trait AttachmentScannerTrait {
	/**
	 * Find orphaned attachments (files not referenced in DB + old temp files)
	 *
	 * @return array [ 'orphaned' => [...], 'valid' => [...], 'temp_orphaned' => [...] ]
	 */
	public function find_orphaned_attachments() {
		$uploads_dir = AttachmentHelper::get_attachment_upload_dir();
		$temp_dir    = AttachmentHelper::get_temp_upload_dir();

		$orphaned      = array();
		$valid         = array();
		$temp_orphaned = array();

		// Scan main attachments folder for orphaned files
		if ( is_dir( $uploads_dir ) ) {
			$all_files = array_diff( scandir( $uploads_dir ), array( '.', '..' ) );
			$all_files = array_filter(
				$all_files,
				function ( $f ) use ( $uploads_dir ) {
					return is_file( $uploads_dir . $f );
				}
			);

			// Get all referenced files from DB
			$referenced = DB::instance()->get_all_attachment_paths();

			// Normalize for comparison (case-insensitive, UTF-8)
			$referenced_normalized = array_map(
				function ( $path ) {
					return strtolower( trim( $path ) );
				},
				$referenced
			);

			foreach ( $all_files as $file ) {
				$file_normalized = strtolower( trim( $file ) );
				if ( in_array( $file_normalized, $referenced_normalized, true ) ) {
					$valid[] = $file;
				} else {
					$orphaned[] = $file;
				}
			}
		}

		// Scan temp folder for old files (older than 24 hours)
		if ( is_dir( $temp_dir ) ) {
			$temp_files = array_diff( scandir( $temp_dir ), array( '.', '..' ) );
			$temp_files = array_filter(
				$temp_files,
				function ( $f ) use ( $temp_dir ) {
					return is_file( $temp_dir . $f );
				}
			);

			$cutoff = time() - ( 24 * 60 * 60 ); // 24 hours ago

			foreach ( $temp_files as $file ) {
				$file_path = $temp_dir . $file;
				// Include old temp files (older than 24 hours) as orphaned
				if ( filemtime( $file_path ) < $cutoff ) {
					$temp_orphaned[] = $file;
				}
			}
		}

		return array(
			'orphaned'      => $orphaned,
			'valid'         => $valid,
			'temp_orphaned' => $temp_orphaned,
		);
	}

	/**
	 * Find stale database entries (files referenced but no longer exist)
	 *
	 * @return array [ 'stale_count' => int, 'stale_files' => [...] ]
	 */
	public function find_stale_db_entries() {
		$uploads_dir = AttachmentHelper::get_attachment_upload_dir();

		// Get all files from disk (normalized)
		$disk_files = array();
		if ( is_dir( $uploads_dir ) ) {
			$files      = array_diff( scandir( $uploads_dir ), array( '.', '..' ) );
			$disk_files = array_filter(
				$files,
				function ( $f ) use ( $uploads_dir ) {
					return is_file( $uploads_dir . $f );
				}
			);
			// Normalize disk files for comparison
			$disk_files = array_map(
				function ( $f ) {
					return strtolower( trim( $f ) );
				},
				$disk_files
			);
		}

		// Get all referenced files from DB
		$referenced = DB::instance()->get_all_attachment_paths();

		// Normalize referenced files
		$referenced_normalized = array_map(
			function ( $path ) {
				return strtolower( trim( $path ) );
			},
			$referenced
		);

		// Find files referenced in DB but not on disk
		$stale_files = array_diff( $referenced_normalized, $disk_files );

		return array(
			'stale_count' => count( $stale_files ),
			'stale_files' => $stale_files,
		);
	}
}
