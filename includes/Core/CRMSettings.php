<?php
namespace ContactInbox\Core;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles CRM settings registration and retrieval.
 */
final class CRMSettings {

	/**
	 * Register CRM settings with WordPress.
	 */
	public static function register(): void {
		register_setting(
			Config::SETTINGS_GROUP_CRM,
			Config::OPTION_CRM,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( self::class, 'sanitize' ),
				'default'           => array(),
			)
		);
	}

	/**
	 * Sanitize CRM settings before saving.
	 *
	 * @param array $settings
	 * @return array
	 */
	public static function sanitize( array $settings ): array {
		$sanitized = array(
			'crm_type'                   => sanitize_text_field( $settings['crm_type'] ?? '' ),
			'endpoint'                   => esc_url_raw( $settings['endpoint'] ?? '' ),
			'auth_token'                 => sanitize_text_field( $settings['auth_token'] ?? '' ),
			'refresh_token'              => sanitize_text_field( $settings['refresh_token'] ?? '' ),
			'instance_url'               => esc_url_raw( $settings['instance_url'] ?? '' ),
			'connected_app_name'         => sanitize_text_field( $settings['connected_app_name'] ?? '' ),
			'salesforce_consumer_key'    => sanitize_text_field( $settings['salesforce_consumer_key'] ?? '' ),
			'salesforce_consumer_secret' => sanitize_text_field( $settings['salesforce_consumer_secret'] ?? '' ),
			'environment'                => in_array( $settings['environment'] ?? 'production', array( 'production', 'sandbox' ), true )
				? $settings['environment']
				: 'production',
			'crm_enabled'                => ! empty( $settings['crm_enabled'] ),
			'oauth_enabled'              => ! empty( $settings['oauth_enabled'] ),
			'attachment_sync'            => ! empty( $settings['attachment_sync'] ),
			'prepend_intent_to_subject'  => ! empty( $settings['prepend_intent_to_subject'] ),
			'crm_delete_sync'            => ! empty( $settings['crm_delete_sync'] ),
			'max_attachment_size_mb'     => isset( $settings['max_attachment_size_mb'] )
				? absint( $settings['max_attachment_size_mb'] )
				: 50,
			'attachment_visibility'      => in_array(
				$settings['attachment_visibility'] ?? 'AllUsers',
				array( 'AllUsers', 'InternalUsers', 'SharedUsers' ),
				true
			) ? $settings['attachment_visibility'] : 'AllUsers',
			'name_field_order'           => in_array( $settings['name_field_order'] ?? 'first_last', array( 'first_last', 'last_first' ), true )
				? $settings['name_field_order']
				: 'first_last',
		);

		// Sanitize field mapping array
		if ( ! empty( $settings['mapping'] ) && is_array( $settings['mapping'] ) ) {
			$sanitized['mapping'] = array_map( 'sanitize_text_field', $settings['mapping'] );
		} else {
			$sanitized['mapping'] = array();
		}

		// Ensure name field mapping is auto-set
		$sanitized['mapping']['name'] = 'FirstName,LastName';

		// Separate mappings for two-object architecture
		// contact_mapping: Name, Email, Phone (Contact fields)
		$sanitized['contact_mapping'] = array(
			'email' => $sanitized['mapping']['email'] ?? 'Email',
			'phone' => $sanitized['mapping']['phone'] ?? 'Phone',
		);

		// inquiry_mapping: Subject, Message (Case/Task fields)
		// Note: Intent fields are optional - only include if user explicitly configured them
		// This prevents errors when Salesforce instance doesn't have custom intent fields
		$sanitized['inquiry_mapping'] = array(
			'subject' => $sanitized['mapping']['subject'] ?? 'Subject',
			'message' => $sanitized['mapping']['message'] ?? 'Description',
		);

		// Only add intent fields if explicitly set and not empty (custom Salesforce fields)
		if ( ! empty( $sanitized['mapping']['intent_category'] ) ) {
			$sanitized['inquiry_mapping']['intent_category'] = $sanitized['mapping']['intent_category'];
		}
		if ( ! empty( $sanitized['mapping']['intent_confidence'] ) ) {
			$sanitized['inquiry_mapping']['intent_confidence'] = $sanitized['mapping']['intent_confidence'];
		}

		// Phone handling strategy: how to treat new phone numbers from form submissions
		// - 'overwrite': Replace existing Phone field with new value
		// - 'secondary' (recommended default): Store in OtherPhone field instead of Phone field
		// - 'skip_if_exists': Only update Phone if contact has no phone yet
		$sanitized['phone_handling_strategy'] = in_array(
			$settings['phone_handling_strategy'] ?? 'secondary',
			array( 'overwrite', 'secondary', 'skip_if_exists' ),
			true
		) ? $settings['phone_handling_strategy'] : 'secondary';

		// Phone validation: whether to validate phone format before sending to Salesforce
		// - 'none': Accept any phone input (default, backwards compatible)
		// - 'basic': Check minimum length and format
		$sanitized['phone_validation'] = in_array(
			$settings['phone_validation'] ?? 'none',
			array( 'none', 'basic' ),
			true
		) ? $settings['phone_validation'] : 'none';

		return $sanitized;
	}

	/**
	 * Retrieve CRM settings.
	 *
	 * @return array
	 */
	public static function get_settings(): array {
		return get_option( Config::OPTION_CRM, array() );
	}

	/**
	 * Update CRM settings.
	 *
	 * @param array $settings
	 * @return bool
	 */
	public static function update_settings( array $settings ): bool {
		$sanitized = self::sanitize( $settings );
		return update_option( Config::OPTION_CRM, $sanitized );
	}
}
