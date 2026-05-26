<?php
namespace ContactInbox\Core;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
/**
 * Service for scanning, reporting, and cleaning up orphaned attachment files.
 * Uses traits for modularity.
 */
class AttachmentCleanupService {
	use Traits\AttachmentScannerTrait;
	use Traits\AttachmentCleanupTrait;
	use Traits\AttachmentStatsTrait;

	/**
	 * Get singleton instance
	 */
	public static function instance(): self {
		static $inst = null;
		if ( $inst === null ) {
			$inst = new self();
		}
		return $inst;
	}

	/**
	 * Get all stats for the cleanup card (total, orphaned, valid, sizes)
	 *
	 * @return array
	 */
	public function get_stats(): array {
		return $this->get_attachment_stats();
	}

	/**
	 * Scan for orphaned files (not referenced in DB)
	 *
	 * @return array
	 */
	public function scan_orphaned_files(): array {
		return $this->find_orphaned_attachments();
	}

	/**
	 * Get stale database entries (files referenced but no longer exist)
	 *
	 * @return array
	 */
	public function get_stale_entries(): array {
		return $this->find_stale_db_entries();
	}

	/**
	 * Clean stale database entries (files that no longer exist on disk)
	 *
	 * @return int Number of records cleaned
	 */
	public function clean_stale_db_entries(): int {
		$uploads_dir = AttachmentHelper::get_attachment_upload_dir();
		$cleaned     = DB::instance()->clean_stale_attachments( $uploads_dir );
		Logger::info( "Cleaned stale database entries: {$cleaned} records updated" );
		return $cleaned;
	}

	/**
	 * Delete old temporary files (older than 24 hours)
	 *
	 * @return int Number of files deleted
	 */
	public function delete_old_temp_files_cleanup(): int {
		$result = $this->delete_old_temp_files();
		return count( $result['deleted'] ?? array() );
	}

	/**
	 * Delete orphaned files (returns summary)
	 *
	 * @param array $files
	 * @return array
	 */
	public function delete_orphaned_files( array $files ): array {
		$result = $this->delete_attachments( $files );

		// Log the cleanup results
		if ( ! empty( $result['deleted'] ) ) {
			Logger::info(
				sprintf( 'Cleaned up %d orphaned attachment files', count( $result['deleted'] ) ),
				array( 'files' => $result['deleted'] )
			);
		}

		if ( ! empty( $result['failed'] ) ) {
			Logger::warning(
				sprintf( 'Failed to delete %d attachment files', count( $result['failed'] ) ),
				array( 'files' => $result['failed'] )
			);
		}

		return $result;
	}

	/**
	 * Scan orphaned files and return analytics (count, size)
	 *
	 * @return array
	 */
	public function get_orphaned_analytics(): array {
		$scan        = $this->scan_orphaned_files();
		$uploads_dir = AttachmentHelper::get_attachment_upload_dir();
		$orphaned    = is_array( $scan['orphaned'] ?? null ) ? $scan['orphaned'] : array();
		$count       = count( $orphaned );
		$size        = 0;
		foreach ( $orphaned as $file ) {
			$path = $uploads_dir . $file;
			if ( is_file( $path ) ) {
				$size += (int) filesize( $path );
			}
		}
		return array(
			'count'     => $count,
			'size'      => $size,
			'last_scan' => time(),
		);
	}
}
