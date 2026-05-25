<?php
/**
 * ContactIn – CRM Integration Admin Page (UI-only)
 *
 * @package ContactIn\Admin\Pages
 */

declare(strict_types=1);

namespace ContactInbox\Admin\Pages;

use ContactInbox\Core\Config;

// phpcs:disable WordPress.WP.I18n.NonSingularStringLiteralDomain

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class CRMSettingsPage {
	/**
	 * Render the CRM Integration page as frontend-only shell.
	 */
	public static function render(): void {
		if ( ! current_user_can( Config::CAPABILITY ) ) {
			wp_die( esc_html__( 'Permission denied.', 'contactin' ) );
		}

		self::render_frontend_only_page();
	}

	/**
	 * Display the CRM Integration page content with tabs.
	 */
	private static function render_frontend_only_page(): void {
		$settings = array();

		$tabs = array(
			'salesforce' => __( 'Salesforce', 'contactin' ),
			'hubspot'    => __( 'HubSpot (Coming Soon)', 'contactin' ),
			'zoho'       => __( 'Zoho (Coming Soon)', 'contactin' ),
		);

		$active_tab = $_GET['tab'] ?? 'salesforce';
		if ( ! array_key_exists( $active_tab, $tabs ) ) {
			$active_tab = 'salesforce';
		}

		echo '<h2 class="nav-tab-wrapper">';
		foreach ( $tabs as $tab => $label ) {
			$class = ( $tab === $active_tab ) ? ' nav-tab-active' : '';
			$url   = add_query_arg( 'tab', $tab, menu_page_url( 'contactin-crm', false ) );
			echo '<a href="' . esc_url( $url ) . '" class="nav-tab' . esc_attr( $class ) . '">' . esc_html( $label ) . '</a>';
		}
		echo '</h2>';

		if ( $active_tab === 'salesforce' ) {
			$template = CONTACTINBOX_PATH . Config::TEMPLATE_ADMIN . 'crm-settings-page.php';
			if ( file_exists( $template ) ) {
				include $template;
			} else {
				echo '<div class="notice notice-error"><p>'
					. esc_html__( 'Salesforce settings template not found.', 'contactin' )
					. '</p></div>';
			}
		} else {
			echo '<div class="notice notice-info"><p>'
				. esc_html__( 'This integration is coming soon.', 'contactin' )
				. '</p></div>';
		}
	}
}
