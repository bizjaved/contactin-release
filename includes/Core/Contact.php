<?php
namespace ContactInbox\Core;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Contact {
	public int $id;
	public ?string $salutation;
	public string $name;
	public ?string $email;
	public ?string $primary_phone;
	public ?string $mobile_phone;
	public ?string $home_phone;
	public ?string $other_phone;
	public ?string $source;
	public ?string $last_message_at;
	public ?int $last_message_id = null;
	public ?string $created_at;
	public ?string $updated_at;

	public function __construct( object $row ) {
		$this->id              = (int) ( $row->id ?? 0 );
		$this->salutation      = isset( $row->salutation ) ? (string) $row->salutation : null;
		$this->name            = (string) ( $row->name ?? '' );
		$this->email           = isset( $row->email ) ? (string) $row->email : null;
		$this->primary_phone   = isset( $row->primary_phone ) ? (string) $row->primary_phone : null;
		$this->mobile_phone    = isset( $row->mobile_phone ) ? (string) $row->mobile_phone : null;
		$this->home_phone      = isset( $row->home_phone ) ? (string) $row->home_phone : null;
		$this->other_phone     = isset( $row->other_phone ) ? (string) $row->other_phone : null;
		$this->source          = isset( $row->source ) ? (string) $row->source : null;
		$this->last_message_at = isset( $row->last_message_at ) ? (string) $row->last_message_at : null;
		$this->created_at      = isset( $row->created_at ) ? (string) $row->created_at : null;
		$this->updated_at      = isset( $row->updated_at ) ? (string) $row->updated_at : null;
	}
}
