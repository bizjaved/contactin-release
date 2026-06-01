<?php
namespace ContactInbox\Integrations;

use ContactInbox\Traits\Singleton;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class IntegrationsBootstrap {
	use Singleton;

	public function boot(): void {
		add_action( 'elementor/widgets/register', array( $this, 'register_elementor_widget' ) );
		add_action( 'init', array( GutenbergBlock::class, 'register_block' ), 5 );
	}

	public function register_elementor_widget( $widgets_manager ): void {
		if ( ! class_exists( ElementorWidget::class ) || ! is_object( $widgets_manager ) ) {
			return;
		}

		$widget = new ElementorWidget();

		// Elementor API compatibility: newer managers use register(), older use register_widget_type().
		if ( method_exists( $widgets_manager, 'register' ) ) {
			$widgets_manager->register( $widget );
			return;
		}

		if ( method_exists( $widgets_manager, 'register_widget_type' ) ) {
			$widgets_manager->register_widget_type( $widget );
		}
	}
}
