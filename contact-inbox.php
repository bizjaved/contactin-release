<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

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

require_once __DIR__ . '/contactin.php';
