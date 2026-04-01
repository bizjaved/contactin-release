<?php
/**
 * Core – Email Log service (pure logic, no rendering)
 *
 * @package ContactIn\Admin\Core
 * @since   1.0.0
 */

namespace ContactInbox\Core;

use ContactInbox\Core\DB;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
final class EmailLog {

	public const TYPE_CONTACT_FORM = 'contact_form';
	public const TYPE_USER_CONFIRM = 'user_confirmation';
	public const TYPE_GDPR         = 'gdpr';
	public const TYPE_SMTP_TEST    = 'smtp_test';

	public const SUBJECT_CONTACT_FORM = 'New Contact Form Submission';
	public const SUBJECT_USER_CONFIRM = 'We Received Your Message';
	public const SUBJECT_GDPR         = 'Your Data Deletion Link';
	public const SUBJECT_SMTP_TEST    = 'SMTP Test Mail';

	public const STATUS_SENT    = 'sent';
	public const STATUS_FAILED  = 'failed';
	public const STATUS_PENDING = 'pending';

	public static function get_logs(
		int $limit,
		int $offset,
		string $status = '',
		?string $orderby = null,
		?string $order = null
	): array {
		return DB::instance()->get_email_logs(
			$limit,
			$offset,
			$status,
			$orderby ?? 'created_at',
			$order ?? 'DESC'
		);
	}

	public static function get_type_labels(): array {
		return array(
			self::TYPE_CONTACT_FORM => __( 'Contact Us Form submission to Admin', 'contactin' ),
			self::TYPE_USER_CONFIRM => __( 'Contact Us Form submission to User', 'contactin' ),
			self::TYPE_GDPR         => __( 'GDPR Link to User', 'contactin' ),
			self::TYPE_SMTP_TEST    => __( 'SMTP Test to Admin', 'contactin' ),
		);
	}

	public static function count_logs( string $status = '' ): int {
		return (int) DB::instance()->count_email_logs( $status );
	}

	public static function delete_logs( array $ids ): int {
		return (int) DB::instance()->delete_email_logs( $ids );
	}

	public static function prune_logs( int $days ): int {
		return (int) DB::instance()->prune_email_logs( $days );
	}

	private static function sort_logs( array $rows, string $orderby, string $order ): array {
		$allowed = array( 'created_at', 'recipient', 'subject', 'status' );
		if ( ! in_array( $orderby, $allowed, true ) ) {
			$orderby = 'created_at';
		}
		$order = strtoupper( (string) $order ) === 'ASC' ? 'ASC' : 'DESC';

		usort(
			$rows,
			static function ( $a, $b ) use ( $orderby, $order ) {
				$va = $a[ $orderby ] ?? '';
				$vb = $b[ $orderby ] ?? '';
				if ( $orderby === 'created_at' ) {
					$ta = strtotime( (string) $va ) ?: 0;
					$tb = strtotime( (string) $vb ) ?: 0;
					return $order === 'ASC' ? $ta <=> $tb : $tb <=> $ta;
				}
				$cmp = strcmp( (string) $va, (string) $vb );
				return $order === 'ASC' ? $cmp : -$cmp;
			}
		);
		return $rows;
	}
}
