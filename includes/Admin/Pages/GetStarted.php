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

		wp_register_script(
			'contactin-get-started',
			false,
			array( 'jquery' ),
			CONTACTINBOX_VERSION,
			true
		);
		wp_enqueue_script( 'contactin-get-started' );

		$copied_text = wp_json_encode( esc_html__( 'Copied!', 'contactin' ) );
		$copy_text   = wp_json_encode( esc_html__( 'Copy', 'contactin' ) );

		wp_add_inline_script(
			'contactin-get-started',
			'jQuery(document).ready(function($){$(\'.cin-gs-copy-btn\').on(\'click\',function(){var btn=$(this);var text=btn.data(\'clipboard\');var textArea=document.createElement(\'textarea\');textArea.value=text;textArea.style.position=\'fixed\';textArea.style.left=\'-9999px\';document.body.appendChild(textArea);textArea.select();try{document.execCommand(\'copy\');btn.find(\'.cin-copy-text\').text(' . $copied_text . ');setTimeout(function(){btn.find(\'.cin-copy-text\').text(' . $copy_text . ');},2000);}catch(err){console.error(\'Failed to copy:\',err);}document.body.removeChild(textArea);});});',
			'after'
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
