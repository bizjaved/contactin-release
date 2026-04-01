<?php
/**
 * ProfileManagerCore — registers the shared `cin-profile-core` script handle
 * and localises `cinProfileCore` once for both Gutenberg and Elementor blocks.
 *
 * Loaded at init priority 1 so the handle exists before GutenbergBlock (priority 5)
 * adds it as a dependency.
 *
 * @package ContactInbox\Admin
 */

namespace ContactInbox\Admin;

use ContactInbox\Core\Config;
use ContactInbox\Core\FormProfiles;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class ProfileManagerCore {

	/**
	 * Register WordPress hooks.
	 */
	public static function register_hooks(): void {
		add_action( 'init', array( __CLASS__, 'register_script' ), 1 );
	}

	/**
	 * Register the `cin-profile-core` script and localise shared data.
	 * Called on 'init' at priority 1 (before GutenbergBlock at priority 5).
	 */
	public static function register_script(): void {
		$path = CONTACTINBOX_PATH . 'dist/js/cin-profile-core.js';
		$url  = CONTACTINBOX_URL . 'dist/js/cin-profile-core.js';

		if ( ! file_exists( $path ) ) {
			return;
		}

		wp_register_script(
			'cin-profile-core',
			$url,
			array( 'jquery' ),
			(string) filemtime( $path ),
			true
		);

		wp_localize_script(
			'cin-profile-core',
			'cinProfileCore',
			array(
				'nonce'                   => wp_create_nonce( Config::SETTINGS_NONCE_ACTION ),
				'ajaxurl'                 => admin_url( 'admin-ajax.php' ),
				'settingsUrl'             => admin_url( 'admin.php?page=' . Config::MENU_SETTINGS . '#cin-tab-forms' ),
				'formProfiles'            => FormProfiles::options_list(),
				'profilesData'            => FormProfiles::all(),
				'isPremium'               => \ContactInbox\Integration\FreemiusIntegration::can_use_premium_features(),
				// Whether the global File Attachment switch is ON (and the license allows it).
				// Used by the block editor to apply the global ceiling on the per-profile toggle.
				'globalAttachmentEnabled' => ( function () {
					$s = \ContactInbox\Core\Settings::get_settings();
					return ! empty( $s['form_enable_attachment'] )
						&& \ContactInbox\Integration\FreemiusIntegration::can_use_premium_features();
				} )(),
				'upgradeUrl'              => \ContactInbox\Integration\FreemiusIntegration::get_upgrade_url( 'profile_pro_fields' ),
				'i18n'                    => array(
					'profileInfo'        => __( 'Profiles define fields, routing, and behaviour. All submissions share one inbox.', 'contactin' ),
					'editProfile'        => __( 'Edit this profile', 'contactin' ),
					'allProfiles'        => __( 'All profiles', 'contactin' ),
					'createNew'          => __( '+ Create new profile', 'contactin' ),
					'newProfile'         => __( 'New profile', 'contactin' ),
					'editPrefix'         => __( 'Edit: ', 'contactin' ),
					'slugLabel'          => __( 'Slug (ID)', 'contactin' ),
					'profileName'        => __( 'Profile name', 'contactin' ),
					'namePlaceholder'    => __( 'e.g. Sales Enquiry', 'contactin' ),
					'phoneField'         => __( 'Phone field', 'contactin' ),
					'requirePhone'       => __( '↳ Require phone', 'contactin' ),
					'salutation'         => __( 'Salutation dropdown', 'contactin' ),
					'subjectField'       => __( 'Subject field', 'contactin' ),
					'fileAttachment'     => __( 'File attachment', 'contactin' ),
					'privacyConsent'     => __( 'Privacy consent checkbox', 'contactin' ),
					'consentText'        => __( 'Consent text', 'contactin' ),
					'consentPlaceholder' => __( 'Leave empty to use global setting.', 'contactin' ),
					'recaptcha'          => __( 'reCAPTCHA', 'contactin' ),
					'confetti'           => __( 'Confetti on success', 'contactin' ),
					'successMsg'         => __( 'Success message', 'contactin' ),
					'globalFallback'     => __( 'Leave empty to use global setting.', 'contactin' ),
					'notifyEmail'        => __( 'Notification email', 'contactin' ),
					'emailPlaceholder'   => __( 'Leave empty to use global admin email.', 'contactin' ),
					'secFields'          => __( 'Fields', 'contactin' ),
					'secBehaviour'       => __( 'Behaviour', 'contactin' ),
					'secRouting'         => __( 'Routing', 'contactin' ),
					'optAuto'            => __( 'Auto', 'contactin' ),
					'optOn'              => __( 'On', 'contactin' ),
					'optOff'             => __( 'Off', 'contactin' ),
					'saving'             => __( 'Saving…', 'contactin' ),
					'saved'              => __( 'Saved', 'contactin' ),
					'saveFailed'         => __( 'Save failed', 'contactin' ),
					'done'               => __( '← Done', 'contactin' ),
					'createApply'        => __( 'Create & apply', 'contactin' ),
					'cancel'             => __( 'Cancel', 'contactin' ),
					'nameRequired'       => __( 'Name is required.', 'contactin' ),
					'requestFailed'      => __( 'Request failed. Please try again.', 'contactin' ),
					'couldNotSave'       => __( 'Could not save profile.', 'contactin' ),
					// Pro-gating labels
					'proLabel'           => __( 'PRO', 'contactin' ),
					'proFieldsIgnored'   => __( 'These fields require Pro and were not saved:', 'contactin' ),
					'proUpgradeLink'     => __( 'Upgrade to Pro \u2192', 'contactin' ),
					'proAttachment'      => __( 'File attachment (Pro)', 'contactin' ),
					'proNotifyEmail'     => __( 'Notification email (Pro)', 'contactin' ),
					// Global-lock guidance shown in the block editor when global attachment is OFF
					'attachGlobalOff'    => __( 'File attachment is disabled in Global Form Settings. Enable it there first.', 'contactin' ),
					'attachGoToSettings' => __( 'Go to Settings →', 'contactin' ),
					'checkAgain'         => __( 'Check again', 'contactin' ),
					'checking'           => __( 'Checking…', 'contactin' ),
				),
			)
		);
	}
}
