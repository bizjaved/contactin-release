<?php
namespace ContactInbox\Core;

use ContactInbox\Core\Config;

class AttachmentRenderer {
    /**
     * Build a secure download link for a message attachment.
     *
     * @param int    $id        Message ID
     * @param string $file_path Absolute file path
     * @param bool   $button    Render as button (modal) or plain link (table)
     * @param bool   $with_icon Include dashicon paperclip
     * @param string $filename  Optional filename override
     * @return string HTML markup or empty string
     */
    public static function link(
        int $id,
        string $file_path,
        bool $button = false,
        bool $with_icon = true,
        string $filename = ''
    ): string {
        if (empty($file_path)) {
            return '';
        }

        // Generate secure URL with "nonce" key
        $download_url = wp_nonce_url(
            admin_url('admin-ajax.php?action=ci_download_attachment&id=' . $id),
            Config::NONCE_ACTION,
            'nonce'
        );

        $name  = $filename ?: basename($file_path);
        $icon  = $with_icon ? '<span class="dashicons dashicons-paperclip"></span> ' : '';
        // Add cin-attachment-link class and button class if needed
        $class = 'cin-attachment-link';
        if ($button) {
            $class .= ' button';
        }

        return sprintf(
            '<a href="%s" class="%s" data-id="%d" data-filename="%s" onclick="return false;">%s%s</a>',
            esc_url($download_url),
            esc_attr($class),
            $id,
            esc_attr($name),
            $icon,
            esc_html($name)
        );
    }
}
