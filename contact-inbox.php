<?php
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

/**
 * Plugin Name:       ContactIn
 * Plugin URI:        https://contactinbox.app/
 * Description:       Smart contact forms with AI intent classification, secure inbox management, intelligent message categorization, email notifications, reCAPTCHA v3 spam protection, and basic analytics. Full Elementor & Gutenberg support. Use shortcode: [contact_inbox_form]. Upgrade to Pro for adaptive learning, CRM sync, GDPR compliance, and advanced features.
 * Version:           1.0
 * Requires PHP:      7.4
 * Requires at least: 6.4
 * Tested up to:      6.9
 * Author:            Javed Ahsan
 * Author URI:        https://linkedin.com/in/bizjaved
 * License:           GPL-3.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-3.0.html
 * Text Domain:       contact-inbox
 * Domain Path:       /languages
 *
 * @package           ContactInbox
 */

if ( ! function_exists( 'contactinbox_fs' ) ) {
    // Create a helper function for easy SDK access.
    function contactinbox_fs() {
        global $contactinbox_fs;

        if ( ! isset( $contactinbox_fs ) ) {
            // Include Freemius SDK.
            require_once dirname( __FILE__ ) . '/vendor/freemius/wordpress-sdk/start.php';

            $contactinbox_fs = fs_dynamic_init( array(
                'id'                  => '24327',
                'slug'                => 'contact-inbox',
                'premium_slug'        => 'contact-inbox-pro',
                'type'                => 'plugin',
                'public_key'          => 'pk_dc7a7dfca50227a8404ef8029f6eb',
                'is_premium'          => false,  // Free version base - upgradable to Pro
                'premium_suffix'      => 'Pro',
                'has_premium_version' => true,
                'has_addons'          => false,
                'has_paid_plans'      => true,
				// Enable opt-in for free users
                'is_org_compliant'    => true,   // WordPress.org compliant
                'opt_in_moderation'   => false,  // Show opt-in dialog immediately
                'anonymous_mode'      => false,  // Require opt-in (not anonymous)
                'menu'                => array(
                    'slug'           => 'contactinbox-settings',
                    'contact'        => false,
                    'support'        => false,
                    'parent'         => array(
                        'slug' => 'contactin-analytics',
                    ),
                ),
            ) );
        }

        return $contactinbox_fs;
    }

    // Init Freemius.
    contactinbox_fs();
    // Signal that SDK was initiated.
    do_action( 'contactinbox_fs_loaded' );
}

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

// ========================================================================
// 1. Define Plugin Constants.
// ========================================================================
if ( ! defined( 'CONTACTINBOX_FILE' ) ) {
	define( 'CONTACTINBOX_FILE', __FILE__ );
}
if ( ! defined( 'CONTACTINBOX_PATH' ) ) {
	define( 'CONTACTINBOX_PATH', plugin_dir_path( __FILE__ ) );
}
if ( ! defined( 'CONTACTINBOX_URL' ) ) {
	define( 'CONTACTINBOX_URL', plugin_dir_url( __FILE__ ) );
}
if ( ! defined( 'CONTACTINBOX_BASENAME' ) ) {
	define( 'CONTACTINBOX_BASENAME', plugin_basename( __FILE__ ) );
}
if ( ! defined( 'CONTACTINBOX_VERSION' ) ) {
	define( 'CONTACTINBOX_VERSION', '1.0' );
}
if ( ! defined( 'CONTACTINBOX_IS_FREE' ) ) {
	define( 'CONTACTINBOX_IS_FREE', true ); // Free version identifier
}
if ( ! defined( 'NONCE_ACTION' ) ) {
	define( 'NONCE_ACTION', 'cin_admin_nonce' );
}

// Template paths.
if ( ! defined( 'CONTACTINBOX_TEMPLATES' ) ) {
	define( 'CONTACTINBOX_TEMPLATES', CONTACTINBOX_PATH . 'templates/' );
}
if ( ! defined( 'CONTACTINBOX_ADMIN_TEMPLATES' ) ) {
	define( 'CONTACTINBOX_ADMIN_TEMPLATES', CONTACTINBOX_TEMPLATES . 'admin/' );
}
if ( ! defined( 'CONTACTINBOX_ADMIN_PARTIALS' ) ) {
	define( 'CONTACTINBOX_ADMIN_PARTIALS', CONTACTINBOX_ADMIN_TEMPLATES . 'partials/' );
}
if ( ! defined( 'CONTACTINBOX_FRONTEND_TEMPLATES' ) ) {
	define( 'CONTACTINBOX_FRONTEND_TEMPLATES', CONTACTINBOX_TEMPLATES . 'frontend/' );
}
if ( ! defined( 'CONTACTINBOX_EMAIL_TEMPLATES' ) ) {
	define( 'CONTACTINBOX_EMAIL_TEMPLATES', CONTACTINBOX_TEMPLATES . 'emails/' );
}
if ( ! defined( 'CONTACTINBOX_GDPR_TEMPLATES' ) ) {
	define( 'CONTACTINBOX_GDPR_TEMPLATES', CONTACTINBOX_TEMPLATES . 'gdpr/' );
}

// Upload paths for attachments.
$upload_dir = wp_upload_dir();
if ( ! defined( 'CONTACTINBOX_UPLOADS_PATH' ) ) {
	define( 'CONTACTINBOX_UPLOADS_PATH', $upload_dir['basedir'] . '/contactin-attachments/' );
}
if ( ! defined( 'CONTACTINBOX_UPLOADS_URL' ) ) {
	define( 'CONTACTINBOX_UPLOADS_URL', $upload_dir['baseurl'] . '/contactin-attachments/' );
}

// ========================================================================
// Plugin Information Handler - Register Filter for "View Details" Modal
// ========================================================================
add_filter( 'plugins_api', function( $result, $action, $args ) {
	if ( $action !== 'plugin_information' ) {
		return $result;
	}

	return \ContactInbox\Admin\PluginInfo::instance()->plugin_info( $result, $action, $args );
}, 999, 3 );

// Legacy plugin-details AJAX actions are intentionally redirected to native plugin-install modal.
foreach ( [ 'contactin_free_plugin_details', 'contactin_pro_plugin_details', 'contactin_plugin_details' ] as $legacy_details_action ) {
	add_action( 'wp_ajax_' . $legacy_details_action, function() {
		wp_safe_redirect(
			admin_url( 'plugin-install.php?fs_allow_updater_and_dialog=true&tab=plugin-information&plugin=contact-inbox&TB_iframe=true&width=772&height=591' )
		);
		exit;
	} );
}

// ========================================================================
// 1. Early Bootstrap - Load PSR-4 Autoloader
// ========================================================================
$autoloader = __DIR__ . '/includes/Core/Autoloader.php';
if ( ! file_exists( $autoloader ) ) {
	add_action(
		'admin_notices',
		function () {
			?>
		<div class="notice notice-error is-dismissible">
			<p>
				<strong><?php esc_html_e( 'Contact Inbox:', 'contact-inbox' ); ?></strong>
				<?php esc_html_e( ' Critical error: Autoloader file missing. Plugin cannot load.', 'contact-inbox' ); ?>
			</p>
		</div>
			<?php
		}
	);
	return;
}
require_once $autoloader;

// ========================================================================
// 2. Activation / Deactivation Hooks (Uninstall handled by uninstall.php)
// ========================================================================
use ContactInbox\Plugin;
use ContactInbox\Lifecycle;

if ( is_admin() ) {
	require_once CONTACTINBOX_PATH . 'includes/Core/ContactIN_Inbox_Table.php';
}

register_activation_hook(
	CONTACTINBOX_FILE,
	function() {
		Lifecycle::activate();
		// Set option to redirect to Get Started page on first activation
		update_option( 'contactinbox_show_welcome_redirect', true );
	}
);
register_deactivation_hook(
	CONTACTINBOX_FILE,
	array( Lifecycle::class, 'deactivate' )
);
// Note: Uninstall handled by uninstall.php file, not register_uninstall_hook()

// Handle plugin conflicts after this plugin is activated
add_action( 'activated_plugin', function( $plugin, $network_wide ) {
	$free_plugin = CONTACTINBOX_BASENAME;
	$pro_plugin = 'contact-inbox-pro/contact-inbox.php';
	
	// Only act when FREE is being activated
	if ( $plugin === $free_plugin ) {
		// Clear cache to get fresh value
		wp_cache_delete( 'active_plugins', 'options' );
		$active_plugins = get_option( 'active_plugins', [] );
		
		// If both are active, remove PRO
		if ( in_array( $pro_plugin, $active_plugins, true ) && in_array( $free_plugin, $active_plugins, true ) ) {
			$active_plugins = array_diff( $active_plugins, [ $pro_plugin ] );
			update_option( 'active_plugins', array_values( $active_plugins ) );
			set_transient( 'contactin_pro_deactivated_by_free', true, 60 );
		}
	}
}, 10, 2 );

// ========================================================================
// 4. Legacy AJAX Handlers (TODO: Move to Settings class)
// ========================================================================

/**
 * Redirect to Get Started page after plugin activation.
 * Inspired by Starter Templates plugin pattern.
 *
 * @since 0.1.0
 */
add_action( 'admin_init', function() {
	if ( ! get_option( 'contactinbox_show_welcome_redirect', false ) ) {
		return;
	}

	delete_option( 'contactinbox_show_welcome_redirect' );

	// Don't redirect if doing AJAX, is CLI, or during bulk activation
	if ( defined( 'DOING_AJAX' ) && DOING_AJAX ) {
		return;
	}
	if ( defined( 'WP_CLI' ) && WP_CLI ) {
		return;
	}
	if ( isset( $_GET['activate-multi'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		return;
	}

	// Redirect to Get Started page
	wp_safe_redirect( admin_url( 'admin.php?page=contactin-get-started' ) );
	exit();
}, 5 );

// ========================================================================
// 5. AJAX Handlers
// ========================================================================
add_action('wp_ajax_contactin_toggle_subject', function() {
	if (!current_user_can('manage_options') || !check_ajax_referer('contactinbox_settings_nonce', 'nonce', false)) {
		wp_send_json_error(['message' => 'Permission denied.']);
	}
	$enabled = isset($_POST['enabled']) && $_POST['enabled'] == '1';
	$ok = \ContactInbox\Core\Repositories\SettingsRepository::set_subject_enabled($enabled);
	wp_send_json_success([
		'message' => $ok ? 'Subject field updated.' : 'Failed to update.',
		'enabled' => $enabled
	]);
});

add_action('wp_ajax_contactin_toggle_attachment', function() {
	if (!current_user_can('manage_options') || !check_ajax_referer('contactinbox_settings_nonce', 'nonce', false)) {
		wp_send_json_error(['message' => 'Permission denied.']);
	}
	$enabled = isset($_POST['enabled']) && $_POST['enabled'] == '1';
	$ok = \ContactInbox\Core\Repositories\SettingsRepository::set_attachment_enabled($enabled);
	wp_send_json_success([
		'message' => $ok ? 'Attachment field updated.' : 'Failed to update.',
		'enabled' => $enabled
	]);
});

// ========================================================================
// 5. Main Plugin Bootstrap.
// ========================================================================
add_action(
	'plugins_loaded',
	function () {
		// PHP version check.
		if ( version_compare( PHP_VERSION, '7.4', '<' ) ) {
			add_action(
				'admin_notices',
				function () {
					?>
			<div class="notice notice-error">
				<p>
						<?php
						printf(
							/* translators: %s = current PHP version */
							esc_html__( 'Contact Inbox requires PHP 7.4 or higher. You are running PHP %s.', 'contact-inbox' ),
							esc_html( PHP_VERSION )
						);
						?>
				</p>
			</div>
					<?php
				}
			);
			return;
		}

		// Main class existence check.
		if ( ! class_exists( Plugin::class ) ) {
			if ( is_admin() ) {
				add_action(
					'admin_notices',
					function () {
						?>
				<div class="notice notice-error">
					<p>
							<strong><?php esc_html_e( 'Contact Inbox:', 'contact-inbox' ); ?></strong>
							<?php esc_html_e( ' Main plugin class not found. Please reinstall the plugin.', 'contact-inbox' ); ?>
					</p>
				</div>
						<?php
					}
				);
			}
			return;
		}

		// Boot the plugin orchestrator.
		Plugin::instance()->init();

		// Fire global hook.
		do_action( 'contactin_loaded' );
	}
);
