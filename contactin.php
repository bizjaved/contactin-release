<?php
// phpcs:disable WordPress.WP.I18n.TextDomainMismatch, WordPress.PHP.DevelopmentFunctions.error_log_error_log, WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound, WordPress.DB.PreparedSQL.NotPrepared, Generic.PHP.ForbiddenFunctions.Found, PluginCheck.CodeAnalysis.DiscouragedFunctions.load_plugin_textdomainFound, PluginCheck.CodeAnalysis.Heredoc.NotAllowed, PluginCheck.Security.DirectDB.UnescapedDBParameter, Squiz.PHP.DiscouragedFunctions.Discouraged, WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.NamingConventions.PrefixAllGlobals.DynamicHooknameFound, WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound, WordPress.PHP.DevelopmentFunctions.error_log_debug_backtrace, WordPress.WP.AlternativeFunctions.file_system_operations_fsockopen, WordPress.WP.AlternativeFunctions.file_system_operations_readfile, WordPress.WP.AlternativeFunctions.file_system_operations_rmdir, WordPress.WP.EnqueuedResourceParameters.MissingVersion, WordPress.WP.EnqueuedResources.NonEnqueuedScript, WordPress.WP.I18n.MissingArgDomain, WordPress.WP.I18n.UnorderedPlaceholdersPlural, WordPress.WP.I18n.UnorderedPlaceholdersSingle
/**
 * Plugin Name:       ContactIn
 * Plugin URI:        https://contactinbox.app/
 * Description:       Smart contact inbox with keyword-based intent classification, unified inbox, analytics, GDPR controls, and queue-based reliability. SMTP deliverability, reCAPTCHA spam protection, contact auto-capture, 19 industry profiles. Elementor & Gutenberg ready. One shortcode: [contactin_form].
 * Version:           1.1.0
 * Requires PHP:      7.4
 * Requires at least: 6.4
 * Tested up to:      7.0
 * Author:            Javed Ahsan
 * Author URI:        https://linkedin.com/in/bizjaved
 * License:           GPL-3.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-3.0.html
 * Text Domain:       contactin
 * Domain Path:       /languages
 *
 * @package           ContactIn
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

if ( ! function_exists( 'contactin_fs' ) ) {
	// Create a helper function for easy SDK access.
	function contactin_fs() {
		global $contactin_fs;

		if ( ! isset( $contactin_fs ) ) {
			// Include Freemius SDK.
			require_once __DIR__ . '/vendor/freemius/wordpress-sdk/start.php';

			$contactin_fs = fs_dynamic_init(
				array(
					'id'               => '24327',
					'slug'             => 'contactin',
					'premium_slug'     => 'contactin-pro',
					'type'             => 'plugin',
					'public_key'       => 'pk_dc7a7dfca50227a8404ef8029f6eb',
					'is_premium'       => false,
					'has_addons'       => false,
					'has_paid_plans'   => true,
					'is_org_compliant' => true,
					'has_affiliation'  => 'selected',
					'trial'            => array(
						'days'               => 30,
						'is_require_payment' => false,
					),
					'menu'             => array(
						'slug'    => 'contactin-settings',
						'contact' => false,
						'support' => false,
						'parent'  => array(
							'slug' => 'contactin-analytics',
						),
					),
				)
			);
		}

		return $contactin_fs;
	}

	// Init Freemius.
	contactin_fs();
	// Signal that SDK was initiated.
	do_action( 'contactin_fs_loaded' );
}

// ========================================================================
// Freemius Uninstall Hook - Replaces uninstall.php
// ========================================================================
/**
 * Uninstall cleanup function for Freemius.
 *
 * This function is called by Freemius after the uninstall event is reported
 * to the server, ensuring proper tracking and cleanup.
 *
 * @return void
 */
function contactin_fs_uninstall_cleanup() {
	// Define plugin constants required by Config class
	$plugin_file = __FILE__;
	if ( ! defined( 'CONTACTINBOX_FILE' ) ) {
		define( 'CONTACTINBOX_FILE', $plugin_file );
	}
	if ( ! defined( 'CONTACTINBOX_PATH' ) ) {
		define( 'CONTACTINBOX_PATH', plugin_dir_path( $plugin_file ) );
	}
	if ( ! defined( 'CONTACTINBOX_URL' ) ) {
		define( 'CONTACTINBOX_URL', plugin_dir_url( $plugin_file ) );
	}
	if ( ! defined( 'CONTACTINBOX_VERSION' ) ) {
		define( 'CONTACTINBOX_VERSION', '1.0.9' );
	}
	if ( ! defined( 'CONTACTINBOX_UPLOADS_PATH' ) && function_exists( 'wp_upload_dir' ) ) {
		$upload_dir = wp_upload_dir();
		define( 'CONTACTINBOX_UPLOADS_PATH', $upload_dir['basedir'] . '/contactin-attachments/' );
	}
	if ( ! defined( 'CONTACTINBOX_UPLOADS_URL' ) && function_exists( 'wp_upload_dir' ) ) {
		$upload_dir = wp_upload_dir();
		define( 'CONTACTINBOX_UPLOADS_URL', $upload_dir['baseurl'] . '/contactin-attachments/' );
	}

	// Load autoloader and dependencies
	require_once __DIR__ . '/includes/Core/Autoloader.php';
	require_once __DIR__ . '/includes/Core/TableDefinitions.php';
	require_once __DIR__ . '/includes/Core/SafeUninstallHandler.php';

	// Call the safe uninstall handler
	try {
		// Mark that uninstall is in progress for this version
		\ContactInbox\Core\SafeUninstallHandler::beforeUninstall( true );

		// Perform safe uninstall (pro version)
		\ContactInbox\Core\SafeUninstallHandler::uninstall( true );

		// Verify database integrity
		$integrity = \ContactInbox\Core\SafeUninstallHandler::verifyIntegrity();

		if ( $integrity['status'] !== 'ok' ) {
			error_log( '[ContactIn] Uninstall completed with warnings: ' . wp_json_encode( $integrity['missing_tables'] ) );
		}
	} catch ( \Throwable $e ) {
		// Log the error but don't let it prevent uninstallation
		error_log( '[ContactIn] Fatal error during uninstall: ' . $e->getMessage() );
		error_log( '[ContactIn] Error trace: ' . $e->getTraceAsString() );
	}
}

// Hook the uninstall function to Freemius after_uninstall action
contactin_fs()->add_action( 'after_uninstall', 'contactin_fs_uninstall_cleanup' );

// ========================================================================
// 1. Define Plugin Constants.
// ========================================================================
define( 'CONTACTINBOX_FILE', __FILE__ );
define( 'CONTACTINBOX_PATH', plugin_dir_path( __FILE__ ) );
define( 'CONTACTINBOX_URL', plugin_dir_url( __FILE__ ) );
define( 'CONTACTINBOX_BASENAME', plugin_basename( __FILE__ ) );
define( 'CONTACTINBOX_VERSION', '1.0.9' );
define( 'CONTACTINBOX_IS_FREE', true ); // set by generate-free.sh

// Template paths.
define( 'CONTACTINBOX_TEMPLATES', CONTACTINBOX_PATH . 'templates/' );
define( 'CONTACTINBOX_ADMIN_TEMPLATES', CONTACTINBOX_TEMPLATES . 'admin/' );
define( 'CONTACTINBOX_ADMIN_PARTIALS', CONTACTINBOX_ADMIN_TEMPLATES . 'partials/' );
define( 'CONTACTINBOX_FRONTEND_TEMPLATES', CONTACTINBOX_TEMPLATES . 'frontend/' );
define( 'CONTACTINBOX_EMAIL_TEMPLATES', CONTACTINBOX_TEMPLATES . 'emails/' );
define( 'CONTACTINBOX_GDPR_TEMPLATES', CONTACTINBOX_TEMPLATES . 'gdpr/' );

// Upload paths for attachments.
$upload_dir = wp_upload_dir();
define( 'CONTACTINBOX_UPLOADS_PATH', $upload_dir['basedir'] . '/contactin-attachments/' );
define( 'CONTACTINBOX_UPLOADS_URL', $upload_dir['baseurl'] . '/contactin-attachments/' );

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
				<strong><?php esc_html_e( 'ContactIn:', 'contactin' ); ?></strong>
				<?php esc_html_e( ' Critical error: Autoloader file missing. Plugin cannot load.', 'contactin' ); ?>
			</p>
		</div>
			<?php
		}
	);
	return;
}
require_once $autoloader;

// ========================================================================
// 2. REST API Callback Wrappers (top-level functions for WordPress).
// ========================================================================
/**
 * REST API submit callback.
 *
 * @param \WP_REST_Request $request The request object.
 * @return mixed
 */
function contactin_rest_submit( \WP_REST_Request $request ) {
	return \ContactInbox\Admin\Controllers\RestController::submit( $request );
}

// ========================================================================
// 2b. Plugin Details / "View Details" Link Handler
// ========================================================================
/**
 * Provide custom plugin information for the "View Details" modal.
 * This allows us to display our own plugin description even though
 * the plugin is not on the WordPress.org repository.
 */
add_filter(
	'plugins_api',
	function ( $result, $action, $args ) {
		if ( $action !== 'plugin_information' ) {
			return $result;
		}

		// Use PluginInfo class to build and return plugin data
		return \ContactInbox\Admin\PluginInfo::instance()->plugin_info( $result, $action, $args );
	},
	999,
	3
);

// Freemius modal flow can use fs_plugins_api instead of plugins_api.
add_filter(
	'fs_plugins_api',
	function ( $result, $action, $args ) {
		if ( $action !== 'plugin_information' ) {
			return $result;
		}

		return \ContactInbox\Admin\PluginInfo::instance()->plugin_info( $result, $action, $args );
	},
	999,
	3
);

// Canonical View Details link using WordPress plugin-information modal.
add_filter(
	'plugin_row_meta',
	function ( array $links, string $file ): array {
		if ( $file !== CONTACTINBOX_BASENAME ) {
			return $links;
		}

		$plugin_details_url = admin_url( 'plugin-install.php?fs_allow_updater_and_dialog=true&tab=plugin-information&plugin=contactin&TB_iframe=true&width=772&height=591' );

		foreach ( $links as $index => $link ) {
			if ( ! is_string( $link ) ) {
				continue;
			}

			if (
			strpos( $link, 'admin-ajax.php?action=contactin_free_plugin_details' ) !== false
			|| strpos( $link, 'admin-ajax.php?action=contactin_pro_plugin_details' ) !== false
			|| strpos( $link, 'admin-ajax.php?action=contactin_plugin_details' ) !== false
			) {
				$links[ $index ] = preg_replace(
					'#https?://[^"\']*/wp-admin/admin-ajax\.php\?action=contactin(?:_free|_pro)?_plugin_details(?:&amp;|&)[^"\']*#i',
					esc_url( $plugin_details_url ),
					$link
				);
			}
		}

		$links = array_filter(
			$links,
			function ( string $link ): bool {
				if ( strpos( $link, 'Visit plugin site' ) !== false ) {
					return false;
				}
				if ( stripos( $link, 'view details' ) !== false ) {
					return false;
				}
				if ( strpos( $link, 'plugin-information' ) !== false ) {
					return false;
				}
				return true;
			}
		);

		$links['view-details'] = sprintf(
			'<a href="%s" class="thickbox" aria-label="%s" data-title="%s">%s</a>',
			esc_url( $plugin_details_url ),
			esc_attr__( 'More information about ContactIn', 'contactin' ),
			esc_attr__( 'ContactIn', 'contactin' ),
			esc_html__( 'View details', 'contactin' )
		);

		return $links;
	},
	999,
	2
);

// Legacy plugin-details AJAX actions are intentionally redirected to native plugin-install modal.
foreach ( array( 'contactin_free_plugin_details', 'contactin_pro_plugin_details', 'contactin_plugin_details' ) as $legacy_details_action ) {
	add_action(
		'wp_ajax_' . $legacy_details_action,
		function () {
			wp_safe_redirect(
				admin_url( 'plugin-install.php?fs_allow_updater_and_dialog=true&tab=plugin-information&plugin=contactin&TB_iframe=true&width=772&height=591' )
			);
			exit;
		}
	);
}

// ========================================================================
// 3. Activation / Deactivation Hooks (Uninstall handled by Freemius after_uninstall)
// ========================================================================
use ContactInbox\Plugin;
use ContactInbox\Lifecycle;
use ContactInbox\Core\Config;

if ( is_admin() ) {
	require_once CONTACTINBOX_PATH . 'includes/Core/ContactIN_Inbox_Table.php';
}

// Note: Plugin row meta and plugin-information filters are registered in this file.

register_activation_hook(
	CONTACTINBOX_FILE,
	array( Lifecycle::class, 'activate' )
);
register_deactivation_hook(
	CONTACTINBOX_FILE,
	array( Lifecycle::class, 'deactivate' )
);
// Note: Uninstall logic is now hooked to Freemius after_uninstall action via contactin_fs_uninstall_cleanup()

// ========================================================================
// 4. Legacy AJAX Handlers (TODO: Move to Settings class)
// ========================================================================
add_action(
	'wp_ajax_contactin_toggle_subject',
	function () {
		if ( ! current_user_can( 'manage_options' ) || ! check_ajax_referer( 'contactinbox_settings_nonce', 'nonce', false ) ) {
			wp_send_json_error( array( 'message' => 'Permission denied.' ) );
		}
		$enabled = isset( $_POST['enabled'] ) && $_POST['enabled'] == '1';
		$ok      = \ContactInbox\Core\Repositories\SettingsRepository::set_subject_enabled( $enabled );
		wp_send_json_success(
			array(
				'message' => $ok ? 'Subject field updated.' : 'Failed to update.',
				'enabled' => $enabled,
			)
		);
	}
);

add_action(
	'wp_ajax_contactin_toggle_attachment',
	function () {
		if ( ! current_user_can( 'manage_options' ) || ! check_ajax_referer( 'contactinbox_settings_nonce', 'nonce', false ) ) {
			wp_send_json_error( array( 'message' => 'Permission denied.' ) );
		}
		$enabled = isset( $_POST['enabled'] ) && $_POST['enabled'] == '1';
		$ok      = \ContactInbox\Core\Repositories\SettingsRepository::set_attachment_enabled( $enabled );
		wp_send_json_success(
			array(
				'message' => $ok ? 'Attachment field updated.' : 'Failed to update.',
				'enabled' => $enabled,
			)
		);
	}
);

// ========================================================================
// 5. Main Plugin Bootstrap.
// ========================================================================

// Load text domain on init priority 0 to satisfy WordPress 6.7+ timing
// requirements and avoid early JIT translation notices.
add_action(
	'init',
	function () {
		load_plugin_textdomain( 'contactin', false, dirname( CONTACTINBOX_BASENAME ) . '/languages' );
	},
	0
);

add_action(
	'init',
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
							esc_html__( 'ContactIn requires PHP 7.4 or higher. You are running PHP %s.', 'contactin' ),
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
							<strong><?php esc_html_e( 'ContactIn:', 'contactin' ); ?></strong>
							<?php esc_html_e( ' Main plugin class not found. Please reinstall the plugin.', 'contactin' ); ?>
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

		// Register REST API routes (with centralized logging middleware).
		// PREMIUM FEATURE: REST API only available in Pro version
		if ( \ContactInbox\Integration\FreemiusIntegration::can_use_premium_features() ) {
			\ContactInbox\Integrations\RestApiRoutes::init();
		}

		// Fire global hook.
		do_action( 'contactin_loaded' );
	},
	1
);
