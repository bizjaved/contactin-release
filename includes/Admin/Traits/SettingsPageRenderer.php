<?php
declare(strict_types=1);

namespace ContactInbox\Admin\Traits;

use ContactInbox\Core\Config;
use ContactInbox\Core\Settings as CoreSettings;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

trait SettingsPageRenderer {

	/**
	 * Render the Settings admin page.
	 */
	public function display_page(): void {
		// Ensure settings are registered before rendering
		$this->register_settings();

		$settings = CoreSettings::get_settings();
		$template = Config::PATH . Config::TEMPLATE_ADMIN . 'settings-page.php';

		if ( is_readable( $template ) ) {
			// Pass settings and $this to the template via extract so traits are available
			include $template;
		} else {
			printf(
				'<div class="notice notice-error"><p>%s</p></div>',
				esc_html__( 'Settings template not found.', 'contactin' )
			);
		}
	}

	/**
	 * Register plugin settings with WordPress.
	 */
	public function register_settings(): void {
		CoreSettings::register();
	}
}
