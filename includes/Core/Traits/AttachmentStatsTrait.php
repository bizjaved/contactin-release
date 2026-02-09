<?php
namespace ContactInbox\Core\Traits;

trait AttachmentStatsTrait {
    /**
     * Get stats for all, orphaned, and valid attachments
     * @return array
     */
    public function get_attachment_stats() {
        $uploads_dir = WP_CONTENT_DIR . '/uploads/contactin-attachments/';
        if (!is_dir($uploads_dir)) return [
            'total' => 0,
            'total_size' => 0,
            'orphaned' => 0,
            'orphaned_size' => 0,
            'valid' => 0,
            'valid_size' => 0,
        ];
        $all_files = array_diff(scandir($uploads_dir), ['.', '..']);
        $total = 0; $total_size = 0;
        foreach ($all_files as $file) {
            $path = $uploads_dir . $file;
            if (is_file($path)) {
                $total++;
                $total_size += filesize($path);
            }
        }
        $scan = $this->find_orphaned_attachments();
        $orphaned_size = 0;
        foreach ($scan['orphaned'] as $file) {
            $path = $uploads_dir . $file;
            if (is_file($path)) $orphaned_size += filesize($path);
        }
        $valid_size = 0;
        foreach ($scan['valid'] as $file) {
            $path = $uploads_dir . $file;
            if (is_file($path)) $valid_size += filesize($path);
        }
        return [
            'total' => $total,
            'total_size' => $total_size,
            'orphaned' => count($scan['orphaned']),
            'orphaned_size' => $orphaned_size,
            'valid' => count($scan['valid']),
            'valid_size' => $valid_size,
        ];
    }
}
