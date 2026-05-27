<?php
declare(strict_types=1);

namespace ContactInbox\Admin\Pages;

use ContactInbox\Traits\Singleton;
use ContactInbox\Core\Config;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Get Started / Onboarding Page
 */
final class GetStarted {
	use Singleton;

	protected function __construct() {
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
	}

	public function enqueue_assets( $hook ): void {
		if ( $hook !== 'admin_page_contactin-get-started' ) {
			return;
		}

		wp_enqueue_style(
			'contactin-get-started',
			CONTACTINBOX_URL . 'dist/css/get-started.css',
			array(),
			CONTACTINBOX_VERSION
		);
	}

	public static function render(): void {
		if ( ! current_user_can( Config::CAPABILITY ) ) {
			wp_die( esc_html__( 'Permission denied.', 'contactin' ) );
		}
		self::instance()->display();
	}

	private function display(): void {
		$admin_url    = admin_url( 'admin.php' );
		$settings_url = add_query_arg( 'page', Config::MENU_SETTINGS, $admin_url );
		$inbox_url    = add_query_arg( 'page', Config::MENU_INBOX_UNIFIED, $admin_url );
		$upgrade_url  = apply_filters(
			'contactin_upgrade_url',
			add_query_arg(
				array(
					'utm_source'   => 'wp_admin',
					'utm_medium'   => 'get_started',
					'utm_campaign' => 'contactin_free_to_pro',
					'utm_content'  => 'upgrade_section',
				),
				'https://contactinbox.app/'
			)
		);

		require_once CONTACTINBOX_ADMIN_TEMPLATES . 'get-started-page.php';
	}
}
