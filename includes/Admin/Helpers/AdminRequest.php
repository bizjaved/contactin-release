<?php
declare(strict_types=1);

namespace ContactInbox\Admin\Helpers;

use ContactInbox\Core\Config;

// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Shared helper centralizes nonce-aware request access; callers must gate request data via has_valid_nonce() or is_query_authorized().

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class AdminRequest {
	private const NONCE_PARAM = '_wpnonce';

	public static function append_nonce( array $args = array(), string $action = Config::NONCE_ACTION, string $param = self::NONCE_PARAM ): array {
		$args[ $param ] = wp_create_nonce( $action );
		return $args;
	}

	public static function has_valid_nonce( array $actions = array( Config::NONCE_ACTION ), string $param = self::NONCE_PARAM ): bool {
		$nonce = isset( $_REQUEST[ $param ] )
			? sanitize_text_field( wp_unslash( (string) $_REQUEST[ $param ] ) )
			: '';

		if ( '' === $nonce ) {
			return false;
		}

		foreach ( $actions as $action ) {
			if ( wp_verify_nonce( $nonce, $action ) ) {
				return true;
			}
		}

		return false;
	}

	public static function is_query_authorized( array $keys, array $actions = array( Config::NONCE_ACTION ), string $param = self::NONCE_PARAM ): bool {
		foreach ( $keys as $key ) {
			if ( isset( $_GET[ $key ] ) || isset( $_REQUEST[ $key ] ) ) {
				return self::has_valid_nonce( $actions, $param );
			}
		}

		return true;
	}

	public static function get_query_text( string $key, string $default = '' ): string {
		return isset( $_GET[ $key ] )
			? sanitize_text_field( wp_unslash( (string) $_GET[ $key ] ) )
			: $default;
	}

	public static function get_query_key( string $key, string $default = '' ): string {
		return isset( $_GET[ $key ] )
			? sanitize_key( wp_unslash( (string) $_GET[ $key ] ) )
			: $default;
	}

	public static function get_query_int( string $key, int $default = 0 ): int {
		return isset( $_GET[ $key ] ) ? absint( $_GET[ $key ] ) : $default;
	}

	public static function get_request_text( string $key, string $default = '' ): string {
		return isset( $_REQUEST[ $key ] )
			? sanitize_text_field( wp_unslash( (string) $_REQUEST[ $key ] ) )
			: $default;
	}

	public static function get_request_key( string $key, string $default = '' ): string {
		return isset( $_REQUEST[ $key ] )
			? sanitize_key( wp_unslash( (string) $_REQUEST[ $key ] ) )
			: $default;
	}

	public static function get_plugin_page_slug(): string {
		global $plugin_page;

		if ( is_string( $plugin_page ) && '' !== $plugin_page ) {
			return sanitize_key( $plugin_page );
		}

		if ( function_exists( 'get_current_screen' ) ) {
			$screen = get_current_screen();
			if ( $screen && ! empty( $screen->id ) ) {
				$parts = explode( '_page_', (string) $screen->id, 2 );
				if ( isset( $parts[1] ) && '' !== $parts[1] ) {
					return sanitize_key( $parts[1] );
				}

				return sanitize_key( (string) $screen->id );
			}
		}

		return '';
	}
}