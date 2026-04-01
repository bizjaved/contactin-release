<?php
/**
 * Admin Page – Contacts
 */

declare(strict_types=1);

namespace ContactInbox\Admin\Pages;

use ContactInbox\Traits\Singleton;
use ContactInbox\Admin\Traits\ExportHelper;
use ContactInbox\Admin\Traits\ContactEditAjaxHandler;
use ContactInbox\Admin\Traits\ContactDeletionHandler;
use ContactInbox\Core\Config;
use ContactInbox\Core\Repositories\ContactRepository;
use ContactInbox\Core\Repositories\MessageRepository;
use ContactInbox\Core\PhoneUtils;

// phpcs:disable WordPress.WP.I18n.NonSingularStringLiteralDomain, WordPress.WP.I18n.MissingTranslatorsComment, WordPress.Security.ValidatedSanitizedInput, WordPress.Security.NonceVerification, WordPress.Security.EscapeOutput.OutputNotEscaped, WordPress.WP.I18n.UnorderedPlaceholdersText

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Contacts {
	use Singleton;
	use ExportHelper;
	use ContactEditAjaxHandler;
	use ContactDeletionHandler;

	private ContactRepository $contact_repo;
	private MessageRepository $message_repo;

	protected function __construct() {
		$this->contact_repo = new ContactRepository();
		$this->message_repo = new MessageRepository();
		add_action( 'wp_ajax_contactinbox_contacts_export', array( $this, 'export_csv' ) );
		add_action( 'wp_ajax_contactinbox_contacts_export_info', array( $this, 'export_info' ) );
		add_action( 'wp_ajax_ci_get_contact_message_count', array( $this, 'ci_get_contact_message_count' ) );
		add_action( 'wp_ajax_ci_delete_contact', array( $this, 'ci_delete_contact' ) );
		$this->register_contact_edit_ajax();
	}

	public static function render(): void {
		if ( ! current_user_can( Config::CAPABILITY ) ) {
			wp_die( esc_html__( 'Permission denied.', 'contactin' ) );
		}
		self::instance()->display();
	}

	private function display(): void {
		$contact_id = absint( $_GET['contact_id'] ?? 0 );
		if ( $contact_id > 0 ) {
			$this->display_contact_detail( $contact_id );
			return;
		}

		$this->display_contact_list();
	}

	private function display_contact_list(): void {
		$filters = $this->sanitize_filters();

		$total    = $this->contact_repo->count( $filters['search'] );
		$contacts = $this->contact_repo->get_paginated(
			$filters['paged'],
			$filters['per_page'],
			$filters['search'],
			$filters['orderby'],
			$filters['order']
		);

		foreach ( $contacts as $contact ) {
			$contact->last_message_id = $this->message_repo->get_latest_message_id_by_contact( $contact->id );
		}

		$template = CONTACTINBOX_PATH . Config::TEMPLATE_ADMIN . 'contacts-page.php';
		if ( ! file_exists( $template ) ) {
			wp_die( esc_html__( 'Contacts template not found.', 'contactin' ) );
		}

		$pages            = max( 1, (int) ceil( $total / $filters['per_page'] ) );
		$filters['paged'] = max( 1, min( $filters['paged'], $pages ) );

		// Make available in template scope
		$search        = $filters['search'];
		$paged         = $filters['paged'];
		$per_page      = $filters['per_page'];
		$orderby       = $filters['orderby'];
		$order         = $filters['order'];
		$total_items   = $total;
		$contacts_list = $contacts;
		$pages_count   = $pages;

		include $template;
	}

	private function display_contact_detail( int $contact_id ): void {
		$contact = $this->contact_repo->get_by_id( $contact_id );

		if ( ! $contact ) {
			wp_die( esc_html__( 'Contact not found.', 'contactin' ) );
		}

		$filters = $this->sanitize_detail_filters();

		$total_messages   = $this->message_repo->count(
			$filters['search'],
			$filters['status'],
			$contact_id
		);
		$pages            = max( 1, (int) ceil( $total_messages / $filters['per_page'] ) );
		$filters['paged'] = max( 1, min( $filters['paged'], $pages ) );

		$messages = $this->message_repo->get_paginated(
			$filters['paged'],
			$filters['per_page'],
			$filters['search'],
			$filters['status'],
			$filters['orderby'],
			$filters['order'],
			$contact_id
		);

		// Get unread message count for this contact
		$unread_count = $this->message_repo->count( '', 'unread', $contact_id );

		$template = CONTACTINBOX_PATH . Config::TEMPLATE_ADMIN . 'contact-detail.php';
		if ( ! file_exists( $template ) ) {
			wp_die( esc_html__( 'Contact detail template not found.', 'contactin' ) );
		}

		$contact_item  = $contact;
		$messages_list = $messages;
		$paged         = $filters['paged'];
		$per_page      = $filters['per_page'];
		$pages_count   = $pages;
		$total_items   = $total_messages;
		$search        = $filters['search'];
		$status        = $filters['status'];
		$orderby       = $filters['orderby'];
		$order         = $filters['order'];

		include $template;
	}

	private function sanitize_filters(): array {
		$search   = sanitize_text_field( $_GET['s'] ?? '' );
		$paged    = max( 1, absint( $_GET['paged'] ?? 1 ) );
		$per_page = absint( $_GET['per_page'] ?? 20 );
		$per_page = in_array( $per_page, array( 20, 50, 100 ), true ) ? $per_page : 20;
		$orderby  = sanitize_key( $_GET['orderby'] ?? 'updated_at' );
		$order    = strtoupper( sanitize_key( $_GET['order'] ?? 'DESC' ) ) === 'ASC' ? 'ASC' : 'DESC';
		return compact( 'search', 'paged', 'per_page', 'orderby', 'order' );
	}

	private function sanitize_detail_filters(): array {
		$search   = sanitize_text_field( $_GET['s'] ?? '' );
		$status   = sanitize_key( $_GET['status'] ?? 'all' );
		$paged    = max( 1, absint( $_GET['paged'] ?? 1 ) );
		$per_page = absint( $_GET['per_page'] ?? 20 );
		$per_page = in_array( $per_page, array( 10, 20, 50 ), true ) ? $per_page : 20;
		$orderby  = sanitize_key( $_GET['orderby'] ?? 'submitted_at' );
		$order    = strtoupper( sanitize_key( $_GET['order'] ?? 'DESC' ) ) === 'ASC' ? 'ASC' : 'DESC';
		return compact( 'search', 'status', 'paged', 'per_page', 'orderby', 'order' );
	}

	/**
	 * AJAX handler: Export contacts to CSV with batching support.
	 */
	public function export_csv(): void {
		// Feature gating: CSV export is a premium feature
		if ( ! \ContactInbox\Integration\FreemiusIntegration::can_use_premium_features() ) {
			wp_die( esc_html__( 'This feature requires a Pro license.', 'contactin' ), '', 403 );
		}

		// Security: nonce
		if ( ! isset( $_REQUEST['_wpnonce'] ) || ! wp_verify_nonce( $_REQUEST['_wpnonce'], 'contactinbox_contacts_export' ) ) {
			wp_die( esc_html__( 'Security check failed.', 'contactin' ), '', 403 );
		}

		// Security: capability
		if ( ! current_user_can( Config::CAPABILITY ) ) {
			wp_die( esc_html__( 'Permission denied.', 'contactin' ), '', 403 );
		}

		// Get filters and batching parameters
		$search  = sanitize_text_field( $_GET['s'] ?? '' );
		$orderby = sanitize_key( $_GET['orderby'] ?? 'updated_at' );
		$order   = strtoupper( sanitize_key( $_GET['order'] ?? 'DESC' ) ) === 'ASC' ? 'ASC' : 'DESC';

		// Batching parameters
		$limit         = isset( $_GET['limit'] ) ? max( 1, min( absint( $_GET['limit'] ), 1000 ) ) : 1000;
		$batch         = isset( $_GET['batch'] ) ? max( 1, absint( $_GET['batch'] ) ) : 1;
		$total_batches = absint( $_GET['total_batches'] ?? 0 );

		// Get contacts for this batch
		$contacts = $this->contact_repo->get_paginated( $batch, $limit, $search, $orderby, $order );

		if ( empty( $contacts ) ) {
			wp_die( esc_html__( 'No contacts to export.', 'contactin' ) );
		}

		// Prepare CSV data
		$rows = array();
		foreach ( $contacts as $contact ) {
			// Format each phone type separately
			$primary_phone = $contact->primary_phone
				? PhoneUtils::format( $contact->primary_phone, 'international' )
				: '';
			$mobile_phone  = $contact->mobile_phone
				? PhoneUtils::format( $contact->mobile_phone, 'international' )
				: '';
			$home_phone    = $contact->home_phone
				? PhoneUtils::format( $contact->home_phone, 'international' )
				: '';
			$other_phone   = $contact->other_phone
				? PhoneUtils::format( $contact->other_phone, 'international' )
				: '';

			// Convert last activity to WordPress timezone
			$last_activity = $contact->last_message_at
				? get_date_from_gmt( $contact->last_message_at, 'Y-m-d H:i:s' )
				: '';

			$rows[] = array(
				$contact->id,
				$contact->salutation ?? '',
				$contact->name,
				$contact->email ?? '',
				$primary_phone,
				$mobile_phone,
				$home_phone,
				$other_phone,
				$contact->source ?? '',
				$last_activity,
				$contact->created_at ? get_date_from_gmt( $contact->created_at, 'Y-m-d H:i:s' ) : '',
				$contact->updated_at ? get_date_from_gmt( $contact->updated_at, 'Y-m-d H:i:s' ) : '',
			);
		}

		// Define headers
		$headers = array(
			__( 'ID', 'contactin' ),
			__( 'Salutation', 'contactin' ),
			__( 'Name', 'contactin' ),
			__( 'Email', 'contactin' ),
			__( 'Primary Phone', 'contactin' ),
			__( 'Mobile Phone', 'contactin' ),
			__( 'Home Phone', 'contactin' ),
			__( 'Other Phone', 'contactin' ),
			__( 'Source', 'contactin' ),
			__( 'Last Activity', 'contactin' ),
			__( 'Created', 'contactin' ),
			__( 'Updated', 'contactin' ),
		);

		// Build CSV using ExportHelper trait
		$csv = $this->build_csv_data( $rows, $headers );

		if ( ! $csv ) {
			wp_die( esc_html__( 'Failed to generate CSV data.', 'contactin' ) );
		}

		// Generate filename with batch info
		$filename = $this->get_export_filename( 'contacts', $batch, $total_batches );

		// Send CSV download
		$this->send_csv_download( $csv, $filename );
	}

	/**
	 * AJAX handler: Export info (total, batches, limit) for client-side orchestration.
	 */
	public function export_info(): void {
		// Feature gating: CSV export is a premium feature
		if ( ! \ContactInbox\Integration\FreemiusIntegration::can_use_premium_features() ) {
			wp_send_json_error( array( 'message' => __( 'This feature requires a Pro license.', 'contactin' ) ) );
		}

		// Security: nonce
		if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( $_POST['nonce'], 'contactinbox_contacts_export' ) ) {
			wp_send_json_error( array( 'message' => __( 'Security check failed.', 'contactin' ) ) );
		}

		// Security: capability
		if ( ! current_user_can( Config::CAPABILITY ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'contactin' ) ) );
		}

		// Get filters
		$search = sanitize_text_field( $_POST['s'] ?? '' );

		// Get total count
		$total   = $this->contact_repo->count( $search );
		$limit   = 1000; // Max records per batch
		$batches = (int) ceil( max( 0, $total ) / $limit );

		wp_send_json_success(
			array(
				'total'   => $total,
				'limit'   => $limit,
				'batches' => $batches,
				'message' => sprintf( __( 'Found %d contacts. Export limit: %d per file.', 'contactin' ), $total, $limit ),
			)
		);
	}
}
