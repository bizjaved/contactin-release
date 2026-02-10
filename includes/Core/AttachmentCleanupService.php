<?php
/**
 * Attachment Cleanup Service Stub
 * 
 * This is a stub class to prevent fatal errors.
 * Actual attachment cleanup is a PRO feature.
 *
 * @package ContactInbox
 */

namespace ContactInbox\Core;

/**
 * AttachmentCleanupService stub class
 */
class AttachmentCleanupService {
    
    /**
     * Singleton instance
     *
     * @var AttachmentCleanupService|null
     */
    private static $instance = null;
    
    /**
     * Get singleton instance
     *
     * @return AttachmentCleanupService
     */
    public static function instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Get orphaned analytics
     *
     * @return array
     */
    public function get_orphaned_analytics() {
        return [
            'orphaned_count' => 0,
            'orphaned_size' => 0,
        ];
    }
    
    /**
     * Delete old temp files cleanup
     *
     * @return int
     */
    public function delete_old_temp_files_cleanup() {
        return 0;
    }
}
