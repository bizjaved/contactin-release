<?php
namespace ContactInbox\Admin\Assets;

use ContactInbox\Core\Config;
use ContactInbox\Core\Logger;

if (!defined('ABSPATH')) exit;
trait AssetHelpers {
    /**
     * @param int|string|null $version Optional asset version override.
     */
    protected function register_style(
        string $handle,
        string $filename,
        array $deps = [],
        $version = null,
        string $media = 'all'
    ): void {
        $path = CONTACTINBOX_PATH . Config::DIST_CSS . ltrim( $filename, '/' );
        $url  = CONTACTINBOX_URL  . Config::DIST_CSS . ltrim( $filename, '/' );

        if ( file_exists( $path ) ) {
            $asset_version = $version ?? filemtime( $path );
            wp_enqueue_style( $handle, $url, $deps, $asset_version, $media );
        } else {
            Logger::warning('Admin CSS asset missing', [ 'handle' => $handle, 'path' => $path ]);
        }
    }

    /**
     * @param int|string|null $version Optional asset version override.
     */
    protected function register_script(
        string $handle,
        string $filename,
        array $deps = [],
        $version = null,
        bool $in_footer = true
    ): void {
        $path = CONTACTINBOX_PATH . Config::DIST_JS . ltrim( $filename, '/' );
        $url  = CONTACTINBOX_URL  . Config::DIST_JS . ltrim( $filename, '/' );

        if ( file_exists( $path ) ) {
            $asset_version = $version ?? filemtime( $path );
            wp_enqueue_script( $handle, $url, $deps, $asset_version, $in_footer );
        } else {
            Logger::warning('Admin JS asset missing', [ 'handle' => $handle, 'path' => $path ]);
        }
    }
}


