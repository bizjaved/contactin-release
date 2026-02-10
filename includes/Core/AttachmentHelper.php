<?php
/**
 * Attachment Helper Stub
 * 
 * This is a stub class to prevent fatal errors.
 * Actual attachment functionality is a PRO feature.
 *
 * @package ContactInbox
 */

namespace ContactInbox\Core;

/**
 * AttachmentHelper stub class
 */
class AttachmentHelper {
    
    /**
     * Extract file path from attachment data
     *
     * @param mixed $attachment Attachment data
     * @return string|null
     */
    public static function extract_file_path($attachment) {
        return null;
    }
    
    /**
     * Extract multiple file paths from attachment data
     *
     * @param mixed $attachment Attachment data
     * @return array
     */
    public static function extract_file_paths($attachment) {
        return [];
    }
    
    /**
     * Check if file is valid
     *
     * @param string $file_path File path
     * @return bool
     */
    public static function is_valid_file($file_path) {
        return false;
    }
    
    /**
     * Get file info
     *
     * @param string $file_path File path
     * @return array
     */
    public static function get_file_info($file_path) {
        return [
            'name' => '',
            'size' => 0,
            'type' => '',
            'path' => '',
        ];
    }
}
