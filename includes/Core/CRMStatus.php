<?php
namespace ContactInbox\Core;

// phpcs:disable WordPress.WP.I18n.NonSingularStringLiteralDomain, WordPress.WP.I18n.NonSingularStringLiteralText

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class CRMStatus {

	public const DELIVERED         = 'delivered';
	public const PENDING           = 'pending';
	public const FAILED            = 'failed';
	public const REJECTED          = 'rejected';
	public const SERVER_ERROR      = 'server_error';
	public const CONNECTION_FAILED = 'connection_failed';
	public const INVALID_TOKEN     = 'invalid_token';
	public const RATE_LIMITED      = 'rate_limited';
	public const TIMEOUT           = 'timeout';

	private const LEGACY_MAP = array(
		'success'   => self::DELIVERED,
		'synced'    => self::DELIVERED,
		'delivered' => self::DELIVERED,
		'complete'  => self::DELIVERED,
		'error'     => self::FAILED,
		'failure'   => self::FAILED,
		''          => self::PENDING,
	);

	private const STATUS_LABELS = array(
		self::DELIVERED         => 'Delivered',
		self::PENDING           => 'Pending',
		self::FAILED            => 'Failed',
		self::REJECTED          => 'Rejected',
		self::SERVER_ERROR      => 'Server Error',
		self::CONNECTION_FAILED => 'Connection Failed',
		self::INVALID_TOKEN     => 'Invalid Token',
		self::RATE_LIMITED      => 'Rate Limited',
		self::TIMEOUT           => 'Timeout',
	);

	private const STATUS_COLORS = array(
		self::DELIVERED         => '#46b450',
		self::PENDING           => '#ffc107',
		self::FAILED            => '#dc3545',
		self::REJECTED          => '#dc3545',
		self::SERVER_ERROR      => '#dc3545',
		self::CONNECTION_FAILED => '#dc3545',
		self::INVALID_TOKEN     => '#dc3545',
		self::RATE_LIMITED      => '#ff9800',
		self::TIMEOUT           => '#ff6b6b',
	);

	private const SUCCESS_STATUSES = array(
		self::DELIVERED,
	);

	private const FAILURE_STATUSES = array(
		self::FAILED,
		self::REJECTED,
		self::SERVER_ERROR,
		self::CONNECTION_FAILED,
		self::INVALID_TOKEN,
		self::RATE_LIMITED,
		self::TIMEOUT,
	);

	private const FILTER_SYNONYMS = array(
		self::DELIVERED => array( 'delivered', 'success', 'synced', 'complete' ),
		self::FAILED    => array( 'failed', 'error', 'failure' ),
	);

	public static function normalize( ?string $status ): string {
		$normalized = strtolower( trim( (string) $status ) );
		if ( isset( self::LEGACY_MAP[ $normalized ] ) ) {
			return self::LEGACY_MAP[ $normalized ];
		}
		return $normalized !== '' ? $normalized : self::PENDING;
	}

	public static function expand_filter( string $status ): array {
		if ( $status === 'all' || $status === '' ) {
			return array();
		}
		$normalized = self::normalize( $status );
		$values     = array( $normalized );
		if ( isset( self::FILTER_SYNONYMS[ $normalized ] ) ) {
			$values = array_unique( array_merge( $values, self::FILTER_SYNONYMS[ $normalized ] ) );
		}
		return $values;
	}

	public static function get_filter_options(): array {
		return array(
			array(
				'value' => 'all',
				'label' => __( 'All Statuses', 'contactin' ),
			),
			array(
				'value' => self::DELIVERED,
				'label' => __( 'Delivered', 'contactin' ),
			),
			array(
				'value' => self::PENDING,
				'label' => __( 'Pending', 'contactin' ),
			),
			array(
				'value' => self::FAILED,
				'label' => __( 'Failed', 'contactin' ),
			),
			array(
				'value' => self::REJECTED,
				'label' => __( 'Rejected', 'contactin' ),
			),
			array(
				'value' => self::SERVER_ERROR,
				'label' => __( 'Server Error', 'contactin' ),
			),
			array(
				'value' => self::CONNECTION_FAILED,
				'label' => __( 'Connection Failed', 'contactin' ),
			),
			array(
				'value' => self::INVALID_TOKEN,
				'label' => __( 'Invalid Token', 'contactin' ),
			),
			array(
				'value' => self::RATE_LIMITED,
				'label' => __( 'Rate Limited', 'contactin' ),
			),
			array(
				'value' => self::TIMEOUT,
				'label' => __( 'Timeout', 'contactin' ),
			),
		);
	}

	public static function get_colors(): array {
		return self::STATUS_COLORS;
	}

	public static function get_success_statuses(): array {
		return self::SUCCESS_STATUSES;
	}

	public static function get_failure_statuses(): array {
		return self::FAILURE_STATUSES;
	}

	public static function is_success( ?string $status ): bool {
		return in_array( self::normalize( $status ), self::SUCCESS_STATUSES, true );
	}

	public static function is_failure( ?string $status ): bool {
		return in_array( self::normalize( $status ), self::FAILURE_STATUSES, true );
	}

	public static function label( ?string $status ): string {
		$normalized = self::normalize( $status );
		switch ( $normalized ) {
			case self::DELIVERED:
				return __( 'Delivered', 'contactin' );
			case self::PENDING:
				return __( 'Pending', 'contactin' );
			case self::FAILED:
				return __( 'Failed', 'contactin' );
			case self::REJECTED:
				return __( 'Rejected', 'contactin' );
			case self::SERVER_ERROR:
				return __( 'Server Error', 'contactin' );
			case self::CONNECTION_FAILED:
				return __( 'Connection Failed', 'contactin' );
			case self::INVALID_TOKEN:
				return __( 'Invalid Token', 'contactin' );
			case self::RATE_LIMITED:
				return __( 'Rate Limited', 'contactin' );
			case self::TIMEOUT:
				return __( 'Timeout', 'contactin' );
			default:
				return ucfirst( $normalized );
		}
	}
}
