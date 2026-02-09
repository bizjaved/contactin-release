<?php
/**
 * Plugin Name:       Contact Inbox
 * Plugin URI:        https://wordpress.org/plugins/contact-inbox/
 * Description:       Simple and secure contact form with inbox management, email notifications, reCAPTCHA v3 spam protection, and basic analytics. Full Elementor & Gutenberg support. Use shortcode: [contact_inbox_form]. Upgrade to Pro for GDPR compliance, CRM sync, file attachments, REST API, and advanced features.
 * Version:           0.1.0
 * Requires PHP:      7.4
 * Requires at least: 6.4
 * Tested up to:      6.7
 * Author:            Javed Ahsan
 * Author URI:        https://linkein.com/in/bizjaved
 * License:           GPL-3.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-3.0.html
 * Text Domain:       contact-inbox
 * Domain Path:       /languages
 * Update URI:        false
 *
 * @package           ContactInbox
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

// ========================================================================
// 1. Define Plugin Constants.
// ========================================================================
define( 'CONTACTINBOX_FILE', __FILE__ );
define( 'CONTACTINBOX_PATH', plugin_dir_path( __FILE__ ) );
define( 'CONTACTINBOX_URL', plugin_dir_url( __FILE__ ) );
define( 'CONTACTINBOX_BASENAME', plugin_basename( __FILE__ ) );
define( 'CONTACTINBOX_VERSION', '0.1.0' );
define( 'CONTACTINBOX_IS_FREE', true ); // Free version identifier
const NONCE_ACTION = 'cin_admin_nonce';

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
	array( Lifecycle::class, 'activate' )
);
register_deactivation_hook(
	CONTACTINBOX_FILE,
	array( Lifecycle::class, 'deactivate' )
);
// Note: Uninstall handled by uninstall.php file, not register_uninstall_hook()

// ========================================================================
// 4. Legacy AJAX Handlers (TODO: Move to Settings class)
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

if ( ! defined('CONTACTINBOX_IS_FREE') || ! CONTACTINBOX_IS_FREE ) {
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
}

// ========================================================================
// 5. Main Plugin Bootstrap.
// ========================================================================
add_action(
	'plugins_loaded',
	function () {

		// Load text domain.
		load_plugin_textdomain(
			'contact-inbox',
			false,
			dirname( CONTACTINBOX_BASENAME ) . '/languages'
		);

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
