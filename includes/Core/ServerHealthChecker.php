<?php
declare(strict_types=1);

namespace ContactInbox\Core;

if (!defined('ABSPATH')) exit;
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Server Health Checker
 * 
 * Validates server configuration and displays admin notices
 * for potential compatibility issues.
 * 
 * @package ContactIn\Core
 */
final class ServerHealthChecker {

    /**
     * Minimum required upload size (in MB)
     * Needed for file attachments feature
     */
    const MIN_UPLOAD_SIZE_MB = 10;

    /**
     * Minimum required post max size (in MB)
     */
    const MIN_POST_SIZE_MB = 10;

    /**
     * Minimum required memory limit (in MB)
     */
    const MIN_MEMORY_MB = 64;

    /**
     * Check server configuration and display notices
     * 
     * @return void
     */
    public static function check_server_health(): void {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }

        $issues = self::get_server_issues();

        if ( empty( $issues ) ) {
            return;
        }

        add_action( 'admin_notices', function() use ( $issues ) {
            self::render_health_notices( $issues );
        } );
    }

    /**
     * Get array of server configuration issues
     * 
     * @return array<string, array> Array of issues with severity and message
     */
    private static function get_server_issues(): array {
        $issues = [];

        // Check upload_max_filesize
        $upload_max = self::get_php_size_in_mb( 'upload_max_filesize' );
        if ( $upload_max < self::MIN_UPLOAD_SIZE_MB ) {
            $issues['upload_max_filesize'] = [
                'severity' => 'warning',
                'current'  => $upload_max,
                'required' => self::MIN_UPLOAD_SIZE_MB,
                'setting'  => 'upload_max_filesize',
            ];
        }

        // Check post_max_size
        $post_max = self::get_php_size_in_mb( 'post_max_size' );
        if ( $post_max < self::MIN_POST_SIZE_MB ) {
            $issues['post_max_size'] = [
                'severity' => 'warning',
                'current'  => $post_max,
                'required' => self::MIN_POST_SIZE_MB,
                'setting'  => 'post_max_size',
            ];
        }

        // Check memory limit
        $memory_limit = self::get_php_size_in_mb( 'memory_limit' );
        if ( $memory_limit < self::MIN_MEMORY_MB ) {
            $issues['memory_limit'] = [
                'severity' => 'warning',
                'current'  => $memory_limit,
                'required' => self::MIN_MEMORY_MB,
                'setting'  => 'memory_limit',
            ];
        }

        return $issues;
    }

    /**
     * Convert PHP ini value to MB
     * 
     * Handles values like "128M", "512K", "1G" etc.
     * 
     * @param string $setting PHP ini setting name
     * @return int Size in MB
     */
    private static function get_php_size_in_mb( string $setting ): int {
        $value = ini_get( $setting );

        if ( ! $value || 'unlimited' === $value || '-1' === $value ) {
            return 9999; // Return large number for unlimited
        }

        $value = trim( $value );
        $unit  = strtoupper( substr( $value, -1 ) );
        $num   = (int) $value;

        switch ( $unit ) {
            case 'G':
                $num *= 1024;
                // Fall through
            case 'M':
                $num *= 1024;
                // Fall through
            case 'K':
                $num /= 1024;
                break;
        }

        return (int) $num;
    }

    /**
     * Render health check admin notices
     * 
     * @param array<string, array> $issues Array of server issues
     * @return void
     */
    private static function render_health_notices( array $issues ): void {
        $plugin_name = __( 'ContactIn',  'contactin');
        ?>
        <div class="notice notice-warning is-dismissible" style="margin-top: 20px;">
            <p><strong><?php echo esc_html( $plugin_name ); ?> - Server Configuration Warning</strong></p>
            <p>
                <?php esc_html_e( 'Your server configuration may limit plugin functionality. Please increase these PHP settings:',  'contactin'); ?>
            </p>
            <ul style="margin: 10px 20px;">
                <?php foreach ( $issues as $issue ) : ?>
                    <li>
                        <code><?php echo esc_html( $issue['setting'] ); ?></code>
                        <strong>Current: <?php echo (int) $issue['current']; ?>MB</strong> 
                        → Required: <?php echo (int) $issue['required']; ?>MB
                        <?php if ( $issue['current'] < 1 ) : ?>
                            <em style="color: #d63638;"> (NOT SET)</em>
                        <?php endif; ?>
                    </li>
                <?php endforeach; ?>
            </ul>
            <p style="margin-top: 10px; font-size: 12px; color: #646970;">
                <?php esc_html_e( 'Contact your hosting provider to update php.ini, or add these lines to wp-config.php if supported by your host:',  'contactin'); ?>
            </p>
            <code style="display: block; background: #f0f0f0; padding: 10px; margin: 10px 0; border-radius: 3px; font-size: 12px;">
                // Add to wp-config.php (before "That's all, stop editing!")<br>
                define( 'WP_MEMORY_LIMIT', '256M' );<br>
                // For upload size, contact hosting provider
            </code>
            <p style="font-size: 12px; color: #646970;">
                <?php printf(
                    /* translators: %s = WordPress.org documentation link */
                    esc_html__( 'Learn more about %s',  'contactin'),
                    '<a href="https://wordpress.org/support/article/editing-wp-config-php/" target="_blank" rel="noopener">editing wp-config.php</a>'
                ); ?>
            </p>
        </div>
        <?php
    }
}
