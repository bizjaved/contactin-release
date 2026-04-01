<?php
declare(strict_types=1);

namespace ContactInbox\Core;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * CRM queue orchestration helpers.
 */
final class CRMQueueService {
	/**
	 * Queue a CRM sync for the given message.
	 *
	 * @param int  $message_id
	 * @param int  $priority
	 * @param bool $reset_status
	 * @return bool
	 */
	public static function queue_message_sync( int $message_id, int $priority = 3, bool $reset_status = false ): bool {
		if ( $message_id <= 0 ) {
			Logger::warning( 'CRM queue attempted with invalid message_id', array( 'message_id' => $message_id ) );
			return false;
		}

		$crm_settings = CRMSettings::get_settings();
		if ( empty( $crm_settings['crm_enabled'] ) ) {
			Logger::debug( 'CRM sync skipped - CRM not enabled', array( 'message_id' => $message_id ) );
			return false;
		}

		$payload = CRMQueuePayloadBuilder::build_from_message_id( $message_id );
		if ( ! $payload ) {
			Logger::warning(
				'CRM queue payload build failed',
				array(
					'message_id'    => $message_id,
					'likely_reason' => 'Message not found or missing critical fields',
				)
			);
			return false;
		}

		// Log attachment presence for debugging
		if ( ! empty( $payload['attachment'] ) ) {
			Logger::debug(
				'CRM queue payload includes attachment',
				array(
					'message_id'       => $message_id,
					'attachment_type'  => is_array( $payload['attachment'] ) ? 'array' : 'string',
					'attachment_count' => is_array( $payload['attachment'] ) ? count( $payload['attachment'] ) : 1,
				)
			);
		} else {
			Logger::debug( 'CRM queue payload - no attachment', array( 'message_id' => $message_id ) );
		}

		$queue_id = QueueManager::push( 'crm', $payload, (string) $message_id, $priority );
		if ( is_wp_error( $queue_id ) ) {
			Logger::error(
				'Failed to queue CRM sync',
				array(
					'message_id'   => $message_id,
					'error'        => $queue_id->get_error_message(),
					'payload_keys' => array_keys( $payload ),
				)
			);
			return false;
		}

		if ( $reset_status ) {
			DB::instance()->update_message_status( $message_id, 'crm', Config::CRM_PENDING );
		}

		Logger::info(
			'CRM sync queued successfully',
			array(
				'message_id'     => $message_id,
				'queue_id'       => $queue_id,
				'priority'       => $priority,
				'has_attachment' => ! empty( $payload['attachment'] ),
			)
		);

		return true;
	}
}
