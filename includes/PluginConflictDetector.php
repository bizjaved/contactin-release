<?php
declare(strict_types=1);

namespace ContactInbox;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Detects and prevents conflicts when both free and premium versions are active.
 * 
 * This class ensures a smooth upgrade path by:
 * - Detecting when premium version is active
 * - Showing admin notices about conflicts
 * - Auto-deactivating free version when premium is activated
 * - Preserving all data during the transition
 */
final class PluginConflictDetector {
    
    /**
     * Plugin slugs for detection
     */
    private const FREE_PLUGIN = 'contact-inbox/contact-inbox.php';
    private const PREMIUM_PLUGIN = 'contact-inbox-pro/contact-inbox.php';
    
    /**
     * Initialize conflict detection hooks
     */
    public static function init(): void {
        // Check for conflicts on admin pages
        add_action( 'admin_init', [ self::class, 'detect_conflict' ] );
        
        // Show admin notices
        add_action( 'admin_notices', [ self::class, 'show_conflict_notice' ] );
        
        // Handle free version activation - deactivate premium if it's active
        if ( defined( 'CONTACTINBOX_IS_FREE' ) && CONTACTINBOX_IS_FREE ) {
            // This fires when the FREE version is activated
            add_action( 'activated_plugin', [ self::class, 'on_free_activated' ], 5, 2 );
            // This fires when the PREMIUM version is activated (while free is active)
            add_action( 'activated_plugin', [ self::class, 'on_premium_activated' ], 10, 2 );
        }
    }
    
    /**
     * Detect if both free and premium versions are active
     */
    public static function detect_conflict(): void {
        if ( ! function_exists( 'is_plugin_active' ) ) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }
        
        $is_free = defined( 'CONTACTINBOX_IS_FREE' ) && CONTACTINBOX_IS_FREE;
        
        // If this is the free version and premium is active, deactivate free
        if ( $is_free && is_plugin_active( self::PREMIUM_PLUGIN ) ) {
            deactivate_plugins( plugin_basename( CONTACTINBOX_FILE ) );
            
            // Set transient to show notice after redirect
            set_transient( 'contactinbox_free_auto_deactivated', true, 60 );
            
            // Redirect to plugins page
            if ( isset( $_SERVER['REQUEST_URI'] ) ) {
                wp_safe_redirect( admin_url( 'plugins.php' ) );
                exit;
            }
        }
        
        // If this is the premium version and free is active, deactivate free
        if ( ! $is_free && is_plugin_active( self::FREE_PLUGIN ) ) {
            deactivate_plugins( self::FREE_PLUGIN );
            
            // Set transient to show notice
            set_transient( 'contactinbox_free_auto_deactivated', true, 60 );
        }
    }
    
    /**
     * Show admin notice when conflict is detected or auto-deactivation occurs
     */
    public static function show_conflict_notice(): void {
        // Check if we just auto-deactivated the free version
        if ( get_transient( 'contactinbox_free_auto_deactivated' ) ) {
            delete_transient( 'contactinbox_free_auto_deactivated' );
            
            $is_free = defined( 'CONTACTINBOX_IS_FREE' ) && CONTACTINBOX_IS_FREE;
            
            if ( ! $is_free ) {
                // Premium version talking to user
                ?>
                <div class="notice notice-success is-dismissible">
                    <p>
                        <strong>ContactIn Pro activated!</strong> 
                        The free version has been automatically deactivated to prevent conflicts. 
                        All your data, settings, and messages have been preserved.
                    </p>
                </div>
                <?php
            }
        }
        
        // Check if we just auto-deactivated the premium version
        if ( get_transient( 'contactinbox_premium_auto_deactivated' ) ) {
            delete_transient( 'contactinbox_premium_auto_deactivated' );
            
            $is_free = defined( 'CONTACTINBOX_IS_FREE' ) && CONTACTINBOX_IS_FREE;
            
            if ( $is_free ) {
                // Free version talking to user
                ?>
                <div class="notice notice-info is-dismissible">
                    <p>
                        <strong>ContactIn activated!</strong> 
                        The Pro version has been automatically deactivated to prevent conflicts. 
                        All your data, settings, and messages have been preserved. You can upgrade to Pro anytime.
                    </p>
                </div>
                <?php
            }
        }
    }
    
    /**
     * Handle when free plugin is activated while premium is active
     * 
     * @param string $plugin Path to the plugin file relative to the plugins directory
     * @param bool $network_wide Whether to enable the plugin for all sites in the network
     */
    public static function on_free_activated( string $plugin, bool $network_wide ): void {
        // Check if the FREE version was just activated
        if ( $plugin === self::FREE_PLUGIN ) {
            // Deactivate the premium version if it's active
            if ( ! function_exists( 'is_plugin_active' ) ) {
                require_once ABSPATH . 'wp-admin/includes/plugin.php';
            }
            
            if ( is_plugin_active( self::PREMIUM_PLUGIN ) ) {
                deactivate_plugins( self::PREMIUM_PLUGIN, true );
                set_transient( 'contactinbox_premium_auto_deactivated', true, 60 );
            }
        }
    }
    
    /**
     * Handle when premium plugin is activated while free is active
     * 
     * @param string $plugin Path to the plugin file relative to the plugins directory
     * @param bool $network_wide Whether to enable the plugin for all sites in the network
     */
    public static function on_premium_activated( string $plugin, bool $network_wide ): void {
        // Check if the premium plugin was just activated
        if ( $plugin === self::PREMIUM_PLUGIN ) {
            // Deactivate the free version
            if ( ! function_exists( 'is_plugin_active' ) ) {
                require_once ABSPATH . 'wp-admin/includes/plugin.php';
            }
            
            if ( is_plugin_active( self::FREE_PLUGIN ) ) {
                deactivate_plugins( self::FREE_PLUGIN, true );
                set_transient( 'contactinbox_free_auto_deactivated', true, 60 );
            }
        }
    }
    
    /**
     * Check if the other version is active
     * 
     * @return bool True if conflict exists
     */
    public static function has_conflict(): bool {
        if ( ! function_exists( 'is_plugin_active' ) ) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }
        
        $is_free = defined( 'CONTACTINBOX_IS_FREE' ) && CONTACTINBOX_IS_FREE;
        
        if ( $is_free ) {
            return is_plugin_active( self::PREMIUM_PLUGIN );
        }
        
        return is_plugin_active( self::FREE_PLUGIN );
    }
    
    /**
     * Get the name of the conflicting plugin
     * 
     * @return string|null Plugin name or null if no conflict
     */
    public static function get_conflicting_plugin(): ?string {
        if ( ! self::has_conflict() ) {
            return null;
        }
        
        $is_free = defined( 'CONTACTINBOX_IS_FREE' ) && CONTACTINBOX_IS_FREE;
        
        return $is_free ? 'ContactIn Pro' : 'ContactIn (Free)';
    }
}
