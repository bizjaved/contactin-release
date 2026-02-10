<?php
declare(strict_types=1);

namespace ContactInbox\Core;

use ContactInbox\Traits\Singleton;

/**
 * Attachment Cleanup Service - Stub for Free Version
 * 
 * File attachments are a Pro-only feature.
 * This stub prevents fatal errors where the class is referenced.
 */
final class AttachmentCleanupService {
    use Singleton;

    /**
     * Scan for orphaned files (stub)
     */
    public function scan_orphaned_files(): array {
        return ['orphaned' => [], 'referenced' => []];
    }

    /**
     * Get stale entries (stub)
     */
    public function get_stale_entries(): array {
        return ['stale_count' => 0, 'entries' => []];
    }

    /**
     * Clean stale DB entries (stub)
     */
    public function clean_stale_db_entries(): int {
        return 0;
    }
}
