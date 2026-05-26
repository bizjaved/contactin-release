<?php
/**
 * Contact Deletion Handler Trait
 *
 * Handles contact deletion via AJAX with confirmation for associated messages.
 *
 * @package ContactIn\Admin\Traits
 */

declare(strict_types=1);

namespace ContactInbox\Admin\Traits;

use ContactInbox\Core\Config;
use ContactInbox\Core\Repositories\ContactRepository;
use ContactInbox\Core\Repositories\MessageRepository;

// phpcs:disable WordPress.WP.I18n.NonSingularStringLiteralDomain, WordPress.WP.I18n.MissingTranslatorsComment, WordPress.Security.ValidatedSanitizedInput.MissingUnslash, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized, WordPress.Security.NonceVerification.Recommended, Generic.PHP.ForbiddenFunctions.Found, PluginCheck.CodeAnalysis.DiscouragedFunctions.load_plugin_textdomainFound, PluginCheck.CodeAnalysis.Heredoc.NotAllowed, PluginCheck.Security.DirectDB.UnescapedDBParameter, Squiz.PHP.DiscouragedFunctions.Discouraged, WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.NamingConventions.PrefixAllGlobals.DynamicHooknameFound, WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound, WordPress.PHP.DevelopmentFunctions.error_log_debug_backtrace, WordPress.WP.AlternativeFunctions.file_system_operations_fsockopen, WordPress.WP.AlternativeFunctions.file_system_operations_readfile, WordPress.WP.AlternativeFunctions.file_system_operations_rmdir, WordPress.WP.EnqueuedResourceParameters.MissingVersion, WordPress.WP.EnqueuedResources.NonEnqueuedScript, WordPress.WP.I18n.MissingArgDomain, WordPress.WP.I18n.UnorderedPlaceholdersPlural, WordPress.WP.I18n.UnorderedPlaceholdersSingle

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

trait ContactDeletionHandler {

	/**
	 * AJAX handler: Get contact message count before deletion
	 */
	public function ci_get_contact_message_count(): void {
		$this->disable_error_output();

		// Security: nonce
		if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'ci_contact_deletion' ) ) {
			wp_send_json_error( array( 'message' => __( 'Security check failed.', 'contactin' ) ) );
		}

		// Security: capability
		if ( ! current_user_can( Config::CAPABILITY ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'contactin' ) ) );
		}

		// Validate input
		$contact_id = absint( wp_unslash( $_POST['contact_id'] ?? 0 ) );
		if ( ! $contact_id ) {
			wp_send_json_error( array( 'message' => __( 'Invalid contact ID.', 'contactin' ) ) );
		}

		// Check if contact exists
		$contact_repo = new ContactRepository();
		if ( ! $contact_repo->exists( $contact_id ) ) {
			wp_send_json_error( array( 'message' => __( 'Contact not found.', 'contactin' ) ) );
		}

		// Count associated messages
		$message_repo  = new MessageRepository();
		$message_count = $message_repo->count_by_contact( $contact_id );

		wp_send_json_success(
			array(
				'contact_id'    => $contact_id,
				'message_count' => $message_count,
			)
		);
	}

	/**
	 * AJAX handler: Delete contact (with optional messages)
	 */
	public function ci_delete_contact(): void {
		$this->disable_error_output();

		// Security: nonce
		if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'ci_contact_deletion' ) ) {
			wp_send_json_error( array( 'message' => __( 'Security check failed.', 'contactin' ) ) );
		}

		// Security: capability
		if ( ! current_user_can( Config::CAPABILITY ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'contactin' ) ) );
		}

		// Validate input
		$contact_id      = absint( wp_unslash( $_POST['contact_id'] ?? 0 ) );
		$delete_messages = isset( $_POST['delete_messages'] ) ? (bool) $_POST['delete_messages'] : false;

		if ( ! $contact_id ) {
			wp_send_json_error( array( 'message' => __( 'Invalid contact ID.', 'contactin' ) ) );
		}

		// Check if contact exists
		$contact_repo = new ContactRepository();
		$contact      = $contact_repo->get_by_id( $contact_id );
		if ( ! $contact ) {
			wp_send_json_error( array( 'message' => __( 'Contact not found.', 'contactin' ) ) );
		}

		try {
			if ( $delete_messages ) {
				// Delete contact with all associated messages
				$result = $contact_repo->delete_with_messages( $contact_id );

				if ( ! $result['contact_deleted'] ) {
					wp_send_json_error(
						array(
							'message' => __( 'Failed to delete contact.', 'contactin' ),
						)
					);
				}

				$message = sprintf(
					__( 'Contact and %d message(s) deleted permanently.', 'contactin' ),
					$result['messages_deleted']
				);

				// Add attachment cleanup info if files were deleted
				if ( $result['attachments_deleted'] > 0 ) {
					$message .= ' ' . sprintf(
						__( '%d attachment file(s) cleaned up.', 'contactin' ),
						$result['attachments_deleted']
					);
				}

				wp_send_json_success(
					array(
						'message'             => $message,
						'contact_id'          => $contact_id,
						'messages_deleted'    => $result['messages_deleted'],
						'attachments_deleted' => $result['attachments_deleted'],
					)
				);
			} else {
				// Delete only the contact, keep messages
				$deleted = $contact_repo->delete( $contact_id );

				if ( ! $deleted ) {
					wp_send_json_error(
						array(
							'message' => __( 'Failed to delete contact.', 'contactin' ),
						)
					);
				}

				wp_send_json_success(
					array(
						'message'          => __( 'Contact deleted. Associated messages were preserved.', 'contactin' ),
						'contact_id'       => $contact_id,
						'messages_deleted' => 0,
					)
				);
			}
		} catch ( \Exception $e ) {
			do_action( 'contactinbox_error_log', 'Contact deletion error: ' . $e->getMessage() );
			wp_send_json_error(
				array(
					'message' => __( 'An error occurred while deleting the contact.', 'contactin' ),
				)
			);
		}
	}

	/**
	 * Disable error output to avoid breaking JSON responses
	 */
	private function disable_error_output(): void {
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			ini_set( 'display_errors', '0' ); // phpcs:ignore WordPress.PHP.IniSet.display_errors_Disallowed -- Intentional: suppress PHP errors in AJAX output for clean JSON response.
		}
	}
}
