<?php
namespace ContactInbox\Core\Traits;

use WP_Filesystem_Direct;
use ContactInbox\Core\Logger;

trait AttachmentCleanupTrait {
    /**
     * Delete given attachments from disk
     * @param array $files
     * @return array [ 'deleted' => [...], 'failed' => [...] ]
     */
    public function delete_attachments(array $files) {
        $uploads_dir = WP_CONTENT_DIR . '/uploads/contactin-attachments/';
        
        // Check if directory exists and is writable
        if (!is_dir($uploads_dir)) {
            Logger::warning('Attachment directory does not exist', ['path' => $uploads_dir]);
            return ['deleted' => [], 'failed' => $files];
        }
        
        if (!is_writable($uploads_dir)) {
            $perms = substr(sprintf('%o', fileperms($uploads_dir)), -4);
            Logger::error('Attachment directory is not writable', [
                'path' => $uploads_dir,
                'permissions' => $perms,
                'uid' => getmyuid(),
                'gid' => getmygid()
            ]);
            return ['deleted' => [], 'failed' => $files];
        }
        
        $deleted = [];
        $failed = [];
        
        foreach ($files as $file) {
            $path = $uploads_dir . $file;
            
            try {
                if (!file_exists($path)) {
                    // File doesn't exist, consider it deleted
                    $deleted[] = $file;
                    Logger::debug("Attachment file does not exist, marking as deleted: {$file}");
                    continue;
                }
                
                // Use native PHP unlink for better reliability
                if (is_file($path) && @unlink($path)) {
                    $deleted[] = $file;
                    Logger::debug("Successfully deleted attachment file: {$file}");
                } else {
                    // If unlink fails, try WP_Filesystem as fallback
                    $fs = new WP_Filesystem_Direct(false);
                    if ($fs->delete($path)) {
                        $deleted[] = $file;
                        Logger::debug("Successfully deleted attachment file via WP_Filesystem: {$file}");
                    } else {
                        $perms = is_file($path) ? substr(sprintf('%o', fileperms($path)), -4) : 'N/A';
                        Logger::warning('Failed to delete attachment file', [
                            'file' => $file,
                            'path' => $path,
                            'permissions' => $perms,
                            'exists' => file_exists($path)
                        ]);
                        $failed[] = $file;
                    }
                }
            } catch (\Throwable $e) {
                Logger::error('Exception while deleting attachment', [
                    'file' => $file,
                    'error' => $e->getMessage()
                ]);
                $failed[] = $file;
            }
        }
        
        Logger::info("Attachment deletion completed", [
            'total' => count($files),
            'deleted' => count($deleted),
            'failed' => count($failed)
        ]);
        
        return ['deleted' => $deleted, 'failed' => $failed];
    }

    /**
     * Delete old temporary files (older than 24 hours)
     * @return array [ 'deleted' => [...], 'failed' => [...] ]
     */
    public function delete_old_temp_files() {
        $temp_dir = WP_CONTENT_DIR . '/uploads/contactin-temp-uploads/';
        
        if (!is_dir($temp_dir)) {
            return ['deleted' => [], 'failed' => []];
        }
        
        if (!is_writable($temp_dir)) {
            $perms = substr(sprintf('%o', fileperms($temp_dir)), -4);
            Logger::warning('Temp directory is not writable', [
                'path' => $temp_dir,
                'permissions' => $perms
            ]);
            return ['deleted' => [], 'failed' => []];
        }
        
        $fs = new WP_Filesystem_Direct(false);
        $deleted = [];
        $failed = [];
        $cutoff = time() - (24 * 60 * 60); // 24 hours ago
        
        $files = scandir($temp_dir);
        if (!is_array($files)) {
            return ['deleted' => [], 'failed' => []];
        }
        
        foreach ($files as $file) {
            if ($file === '.' || $file === '..') {
                continue;
            }
            
            $path = $temp_dir . $file;
            
            try {
                if (is_file($path) && filemtime($path) < $cutoff) {
                    if ($fs->delete($path)) {
                        $deleted[] = $file;
                    } else {
                        Logger::warning('Failed to delete old temp file', ['file' => $file]);
                        $failed[] = $file;
                    }
                }
            } catch (\Throwable $e) {
                Logger::error('Exception while deleting temp file', [
                    'file' => $file,
                    'error' => $e->getMessage()
                ]);
                $failed[] = $file;
            }
        }
        
        return ['deleted' => $deleted, 'failed' => $failed];
    }
}
