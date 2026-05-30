<?php
/**
 * Admin Page – Contacts
 */

declare(strict_types=1);

namespace ContactInbox\Admin\Pages;

use ContactInbox\Admin\Helpers\AdminRequest;
use ContactInbox\Traits\Singleton;
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
	use ContactEditAjaxHandler;
	use ContactDeletionHandler;

	private ContactRepository $contact_repo;
	private MessageRepository $message_repo;

	protected function __construct() {
		$this->contact_repo = new ContactRepository();
		$this->message_repo = new MessageRepository();
		add_action( 'wp_ajax_contactin_get_contact_message_count', array( $this, 'contactin_get_contact_message_count' ) );
		add_action( 'wp_ajax_contactin_delete_contact', array( $this, 'contactin_delete_contact' ) );
		$this->register_contact_edit_ajax();
	}

	public static function render(): void {
		if ( ! current_user_can( Config::CAPABILITY ) ) {
			wp_die( esc_html__( 'Permission denied.', 'contactin' ) );
		}
		self::instance()->display();
	}

	private function display(): void {
		if ( ! AdminRequest::is_query_authorized( array( 'contact_id' ) ) ) {
			wp_die( esc_html__( 'Invalid request.', 'contactin' ) );
		}

		$contact_id = AdminRequest::get_query_int( 'contact_id' );

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
			$contact_id,
			null,
			true
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
			$contact_id,
			null,
			true
		);

		// Get unread message count for this contact
		$unread_count = $this->message_repo->count( '', 'unread', $contact_id, null, true );

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
		if ( ! AdminRequest::is_query_authorized( array( 's', 'paged', 'per_page', 'orderby', 'order' ) ) ) {
			return array(
				'search'   => '',
				'paged'    => 1,
				'per_page' => 20,
				'orderby'  => 'updated_at',
				'order'    => 'DESC',
			);
		}

		$search   = AdminRequest::get_query_text( 's' );
		$paged    = max( 1, AdminRequest::get_query_int( 'paged', 1 ) );
		$per_page = AdminRequest::get_query_int( 'per_page', 20 );
		$per_page = in_array( $per_page, array( 20, 50, 100 ), true ) ? $per_page : 20;
		$orderby  = AdminRequest::get_query_key( 'orderby', 'updated_at' );
		$order    = strtoupper( AdminRequest::get_query_key( 'order', 'DESC' ) ) === 'ASC' ? 'ASC' : 'DESC';
		return compact( 'search', 'paged', 'per_page', 'orderby', 'order' );
	}

	private function sanitize_detail_filters(): array {
		if ( ! AdminRequest::is_query_authorized( array( 's', 'status', 'paged', 'per_page', 'orderby', 'order' ) ) ) {
			return array(
				'search'   => '',
				'status'   => 'all',
				'paged'    => 1,
				'per_page' => 20,
				'orderby'  => 'submitted_at',
				'order'    => 'DESC',
			);
		}

		$search   = AdminRequest::get_query_text( 's' );
		$status   = AdminRequest::get_query_key( 'status', 'all' );
		$paged    = max( 1, AdminRequest::get_query_int( 'paged', 1 ) );
		$per_page = AdminRequest::get_query_int( 'per_page', 20 );
		$per_page = in_array( $per_page, array( 10, 20, 50 ), true ) ? $per_page : 20;
		$orderby  = AdminRequest::get_query_key( 'orderby', 'submitted_at' );
		$order    = strtoupper( AdminRequest::get_query_key( 'order', 'DESC' ) ) === 'ASC' ? 'ASC' : 'DESC';
		return compact( 'search', 'status', 'paged', 'per_page', 'orderby', 'order' );
	}

}
