<?php
declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
/**
 * Freemius SDK Bootstrap
 *
 * Loads and initializes the Freemius SDK for plugin licensing and updates.
 * This file should be loaded very early in the plugin initialization process.
 *
 * @package ContactIn
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Load Freemius SDK if available
 *
 * Loads the Freemius SDK from vendor/freemius/wordpress-sdk
 *
 * @return bool True if SDK loaded, false otherwise
 */
function contactinbox_load_freemius(): bool {
	// Paths
	$plugin_path               = dirname( __DIR__ );
	$freemius_sdk_path         = $plugin_path . '/vendor/freemius/wordpress-sdk/start.php';
	$freemius_integration_path = $plugin_path . '/includes/Integration/FreemiusIntegration.php';

	// Load Freemius SDK
	if ( file_exists( $freemius_sdk_path ) ) {
		require_once $freemius_sdk_path;
	}

	// Load our Freemius integration class
	if ( file_exists( $freemius_integration_path ) ) {
		require_once $freemius_integration_path;

		// Initialize Freemius through our integration class
		\ContactInbox\Integration\FreemiusIntegration::initialize();

		return true;
	}

	return false;
}

/**
 * Get Freemius instance helper function
 *
 * @return object|null
 */
if ( ! function_exists( 'contactin_fs' ) ) {
	function contactin_fs(): ?object {
		return \ContactInbox\Integration\FreemiusIntegration::get_instance();
	}
}

// Load Freemius early (before plugin bootstrap)
// NOTE: FreemiusIntegration::initialize() is now called from Plugin::init()
// on plugins_loaded. This action is kept as a safety net in case this file
// is ever included standalone.
add_action( 'plugins_loaded', 'contactinbox_load_freemius', 1 );
