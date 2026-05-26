<?php
namespace ContactInbox\Core;

use ContactInbox\Traits\Singleton;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
final class Inbox {
	use Singleton;

	public function get_all_message_ids( string $search = '', string $status = 'all', ?int $contact_id = null ): array {
		return DB::instance()->get_all_message_ids( $search, $status, $contact_id );
	}

	public function get_message_by_id( int $id ): ?Message {
		return DB::instance()->get_message_by_id( $id );
	}

	public function toggle_status( int $id ): string|false {
		return DB::instance()->toggle_status( $id );
	}

	public function bulk_update_status( array $ids, string $new_status ): int {
		return DB::instance()->bulk_update_status( $ids, $new_status );
	}

	public function bulk_update_archive( array $ids, bool $archived ): int {
		return DB::instance()->bulk_update_archive( $ids, $archived );
	}

	public function bulk_delete( array $ids ): int {
		return DB::instance()->bulk_delete( $ids );
	}

	public function bulk_clear_spam( array $ids ): int {
		return DB::instance()->bulk_clear_spam( $ids );
	}

	public function bulk_mark_spam( array $ids ): int {
		return DB::instance()->bulk_mark_spam( $ids );
	}

	public function mark_spam( int $message_id ): bool {
		return DB::instance()->mark_spam( $message_id );
	}

	public function clear_spam( int $message_id ): bool {
		return DB::instance()->clear_spam( $message_id );
	}

	public function delete_all_spam(): int {
		return DB::instance()->delete_all_spam();
	}

	public function delete_all_archived(): int {
		return DB::instance()->delete_all_archived();
	}

	public function delete_message( int $id ): bool {
		return DB::instance()->delete_message( $id );
	}

	public function get_messages_for_export( string $search = '', string $status = 'all', int $limit = Config::EXPORT_LIMIT, int $offset = 0, ?int $contact_id = null, ?string $intent = null ): array {
		return DB::instance()->get_messages_for_export( $search, $status, $limit, $offset, $contact_id, $intent );
	}

	public function get_total_messages( string $search = '', string $status = 'all', ?int $contact_id = null, ?string $intent = null ): int {
		return DB::instance()->get_total_messages( $search, $status, $contact_id, $intent );
	}

	/**
	 * Get messages (hydrated Message entities).
	 */
	public function get_messages( string $search = '', string $status = 'all' ): array {
		return DB::instance()->get_messages( 1, $search, $status, Config::INBOX_PER_PAGE );
	}

	public function update_status( int $id, string $status ): bool {
		return DB::instance()->update_status( $id, $status );
	}

	public function toggle_archive( int $id, bool $archived ): bool {
		return DB::instance()->toggle_archive( $id, $archived );
	}
}
