<?php
/**
 * Template: Admin Settings Page
 * File: templates/admin/settings-page.php
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
use ContactInbox\Core\Config;
use ContactInbox\Core\Settings;

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals, WordPress.Security.EscapeOutput, WordPress.WP.I18n.NonSingularStringLiteralDomain, WordPress.WP.I18n.MissingTranslatorsComment, WordPress.WP.I18n.UnorderedPlaceholdersText, WordPress.WP.I18n.TextDomainMismatch

// Define plugin path and URL constants
if ( ! defined( 'SCH_PATH' ) ) {
	define( 'SCH_PATH', plugin_dir_path( __FILE__ ) );
}

// Use the centralized option name from Config for consistency
$settings                 = get_option( Config::OPTION_SETTINGS, array() );
$defaults                 = Settings::instance()->get_default_settings();

$smtp_from_email = isset( $settings['smtp_from_email'] ) && $settings['smtp_from_email'] !== ''
	? sanitize_email( $settings['smtp_from_email'] )
	: '';
$smtp_from_name  = isset( $settings['smtp_from_name'] ) ? sanitize_text_field( $settings['smtp_from_name'] ) : '';
$smtp_user       = isset( $settings['smtp_user'] ) ? sanitize_text_field( $settings['smtp_user'] ) : '';
$admin_fallback  = get_option( 'admin_email' );
$settings_admin  = isset( $settings['admin_email'] ) && $settings['admin_email'] !== '' ? $settings['admin_email'] : $admin_fallback;

$extract_domain = static function ( ?string $email ): string {
	$email = $email ? trim( $email ) : '';
	if ( $email === '' || strpos( $email, '@' ) === false ) {
		return '';
	}
	$parts = explode( '@', $email );
	return strtolower( array_pop( $parts ) );
};

$smtp_domain                 = $extract_domain( $smtp_from_email !== '' ? $smtp_from_email : $smtp_user );
$admin_domain                = $extract_domain( $settings_admin );
$smtp_domain_display         = $smtp_domain !== '' ? $smtp_domain : $extract_domain( $smtp_from_email ?: $smtp_user );
$should_warn_sender_mismatch = ! empty( $smtp_domain ) && ! empty( $admin_domain ) && $smtp_domain !== $admin_domain;
?>


<div class="wrap">
	<?php /* Tab visibility, modal, and utility CSS is in dist/css/admin-settings.min.css */ ?>
	<div class="cin-settings-header-wrapper">
		<h1 class="cin-settings-title"><?php _e( 'Settings', 'contactin' ); ?></h1>
		<button type="button" class="button button-secondary cin-settings-help-button" data-cin-help-open="cin-help-modal" aria-haspopup="dialog" aria-controls="cin-help-modal">
			<span class="cin-settings-help-icon">ℹ️</span><?php _e( 'Help', 'contactin' ); ?>
		</button>
	</div>

	<!-- Global Settings Notice Area -->
	<div id="cin-global-settings-notice" class="notice cin-hidden">
		<button type="button" class="notice-dismiss cin-notice-dismiss" aria-label="<?php esc_attr_e( 'Dismiss this notice.', 'contactin' ); ?>">
			<span class="screen-reader-text"><?php _e( 'Dismiss this notice.', 'contactin' ); ?></span>
		</button>
		<p id="cin-notice-message"></p>
	</div>

	<form id="contactin-settings-form" method="post">
		<input type="hidden" name="action" value="<?php echo esc_attr( Config::AJAX_SAVE_SETTINGS ); ?>">
		<?php wp_nonce_field( Config::SETTINGS_NONCE_ACTION, 'nonce' ); ?>

		<!-- Search Box -->
		<div class="cin-settings-search-box">
			<span class="cin-settings-search-icon">🔍</span>
			<input type="search" id="cin-settings-search" class="cin-settings-search-input" placeholder="<?php _e( 'Search settings by name or keyword...', 'contactin' ); ?>" aria-label="<?php esc_attr_e( 'Search settings', 'contactin' ); ?>" />
			<span id="cin-search-results" class="cin-hidden cin-settings-search-results"><?php _e( 'No matches', 'contactin' ); ?></span>
			<button type="button" id="cin-clear-search" class="cin-hidden button button-small cin-settings-clear-btn" aria-label="<?php esc_attr_e( 'Clear search', 'contactin' ); ?>">✕</button>
		</div>

		<!-- Tab Navigation -->
		<div class="nav-tab-wrapper">
			<a href="#cin-tab-general" class="nav-tab nav-tab-active" data-tab="general"><?php _e( 'General', 'contactin' ); ?></a>
			<a href="#cin-tab-security" class="nav-tab" data-tab="security"><?php _e( 'Security', 'contactin' ); ?></a>
			<a href="#cin-tab-smtp" class="nav-tab" data-tab="smtp">SMTP</a>
			<a href="#cin-tab-notifications" class="nav-tab" data-tab="notifications"><?php _e( 'Notifications', 'contactin' ); ?></a>
			<a href="#cin-tab-form" class="nav-tab" data-tab="form"><?php _e( 'Form', 'contactin' ); ?></a>
			<a href="#cin-tab-forms" class="nav-tab" data-tab="forms"><?php _e( 'Form Profiles', 'contactin' ); ?></a>
			<a href="#cin-tab-advanced" class="nav-tab" data-tab="advanced"><?php _e( 'Advanced', 'contactin' ); ?></a>
			<a href="#cin-tab-ai-classifier" class="nav-tab" data-tab="ai-classifier">🤖 <?php _e( 'AI Classifier', 'contactin' ); ?></a>
		</div>


		<!-- Tab: General -->
		<div id="cin-tab-general" class="cin-tab-content is-active">
			<h3><?php _e( 'General Settings', 'contactin' ); ?></h3>
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row"><label for="privacy_url"><?php _e( 'Privacy Policy URL', 'contactin' ); ?></label></th>
					<td><input name="privacy_url" type="url" id="privacy_url" value="<?php echo esc_url( $settings['privacy_url'] ?? get_privacy_policy_url() ); ?>" class="large-text" data-search="privacy policy url" /></td>
				</tr>
				<tr>
					<th scope="row"><label for="consent_text"><?php _e( 'Consent Text', 'contactin' ); ?></label></th>
					<td><textarea name="consent_text" id="consent_text" rows="3" class="large-text" data-search="consent text"><?php echo esc_textarea( $settings['consent_text'] ?? 'I consent to data processing as per Privacy Policy.' ); ?></textarea></td>
				</tr>
				<tr>
					<th scope="row"><label for="success_message"><?php _e( 'Success Message', 'contactin' ); ?></label></th>
					<td><textarea name="success_message" id="success_message" rows="3" class="large-text" data-search="success message"><?php echo esc_textarea( $settings['success_message'] ?? 'Thank you! Your message has been sent.' ); ?></textarea></td>
				</tr>
				<tr>
					<th scope="row"><?php _e( 'Confetti on Success', 'contactin' ); ?></th>
					<td>
						<fieldset>
							<legend class="screen-reader-text"><span><?php _e( 'Confetti on Success', 'contactin' ); ?></span></legend>
							<input type="hidden" name="confetti_enable" value="0" />
							<div class="cin-flex-center-gap">
								<input id="confetti-enable-checkbox" name="confetti_enable" type="checkbox" value="1" <?php checked( ! empty( $settings['confetti_enable'] ) ); ?> data-search="confetti" class="cin-cursor-pointer" style="width: 20px; height: 20px; min-width: 20px; min-height: 20px; pointer-events: auto !important; opacity: 1 !important; visibility: visible !important; accent-color: #2271b1; position: relative; z-index: 1000;" />
								<label for="confetti-enable-checkbox" class="cin-cursor-pointer cin-m-0"><?php _e( 'Show confetti animation', 'contactin' ); ?></label>
							</div>
							<!-- Confetti checkbox JS moved to page footer for best practice -->
						</fieldset>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php _e( 'Enable GDPR Data Deletion', 'contactin' ); ?></th>
					<td>
						<fieldset>
							<legend class="screen-reader-text"><span><?php _e( 'Enable GDPR Data Deletion', 'contactin' ); ?></span></legend>
							<input type="hidden" name="gdpr_enable" value="0" />
							<div class="cin-flex-center-gap">
								<input id="gdpr-enable-checkbox" name="gdpr_enable" type="checkbox" value="1" <?php checked( ! empty( $settings['gdpr_enable'] ) ); ?> data-search="gdpr deletion" class="cin-cursor-pointer" style="width: 20px; height: 20px; min-width: 20px; min-height: 20px; pointer-events: auto !important; opacity: 1 !important; visibility: visible !important; accent-color: #2271b1; position: relative; z-index: 1000;" />
								<label for="gdpr-enable-checkbox" class="cin-cursor-pointer cin-m-0"><?php _e( 'Show GDPR deletion link in success message', 'contactin' ); ?></label>
							</div>
							<p class="description"><?php _e( 'When enabled, users receive a link to delete their submission data directly from the success message.', 'contactin' ); ?></p>
						</fieldset>
					</td>
				</tr>
			</table>
		</div>

		<!-- Tab: Security -->
		<div id="cin-tab-security" class="cin-tab-content">
			<h3><?php _e( 'Security Settings', 'contactin' ); ?></h3>

			<h4><?php _e( 'reCAPTCHA v3', 'contactin' ); ?></h4>
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row"><label for="recaptcha_site_key"><?php _e( 'Site Key', 'contactin' ); ?></label></th>
					<td><input name="recaptcha_site_key" type="text" id="recaptcha_site_key" value="<?php echo esc_attr( $settings['recaptcha_site_key'] ?? '' ); ?>" class="large-text"  data-search="recaptcha site key" /></td>
				</tr>
				<tr>
					<th scope="row"><label for="recaptcha_secret_key"><?php _e( 'Secret Key', 'contactin' ); ?></label></th>
					<td><input name="recaptcha_secret_key" type="text" id="recaptcha_secret_key" value="<?php echo esc_attr( $settings['recaptcha_secret_key'] ?? '' ); ?>" class="large-text"  data-search="recaptcha secret key" /></td>
				</tr>
				<tr>
					<th scope="row"><?php _e( 'Enable reCAPTCHA', 'contactin' ); ?></th>
					<td>
						<fieldset>
							<legend class="screen-reader-text"><span><?php _e( 'Enable reCAPTCHA', 'contactin' ); ?></span></legend>
							<input type="hidden" name="recaptcha_enable" value="0" />
							<label><input name="recaptcha_enable" type="checkbox" value="1" <?php checked( ! empty( $settings['recaptcha_enable'] ) ); ?> data-search="enable recaptcha" /> <?php _e( 'Enable', 'contactin' ); ?></label>
						</fieldset>
					</td>
				</tr>
			</table>

			<h4 style="margin-top: 24px;"><?php _e( 'Rate Limiting', 'contactin' ); ?></h4>
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row"><label for="rate_limit_per_minute"><?php _e( 'Requests Per Minute', 'contactin' ); ?></label></th>
					<td>
						<input name="rate_limit_per_minute" type="number" id="rate_limit_per_minute"
							value="<?php echo esc_attr( $settings['rate_limit_per_minute'] ?? $defaults['rate_limit_per_minute'] ); ?>"
							min="1" data-search="rate limit minute security" />
						<p class="description"><?php printf( __( 'Recommended: %d', 'contactin' ), (int) ( $defaults['rate_limit_per_minute'] ?? Config::RATE_LIMIT_PER_MINUTE ) ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="rate_limit_per_hour"><?php _e( 'Requests Per Hour', 'contactin' ); ?></label></th>
					<td>
						<input name="rate_limit_per_hour" type="number" id="rate_limit_per_hour"
							value="<?php echo esc_attr( $settings['rate_limit_per_hour'] ?? $defaults['rate_limit_per_hour'] ); ?>"
							min="1" data-search="rate limit hour security" />
						<p class="description"><?php printf( __( 'Recommended: %d', 'contactin' ), (int) ( $defaults['rate_limit_per_hour'] ?? Config::RATE_LIMIT_PER_HOUR ) ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="rate_limit_per_day"><?php _e( 'Requests Per Day', 'contactin' ); ?></label></th>
					<td>
						<input name="rate_limit_per_day" type="number" id="rate_limit_per_day"
							value="<?php echo esc_attr( $settings['rate_limit_per_day'] ?? $defaults['rate_limit_per_day'] ); ?>"
							min="1" data-search="rate limit day security" />
						<p class="description"><?php printf( __( 'Recommended: %d', 'contactin' ), (int) ( $defaults['rate_limit_per_day'] ?? Config::RATE_LIMIT_PER_DAY ) ); ?></p>
					</td>
				</tr>
			</table>

			<h4 style="margin-top: 24px;"><?php _e( 'IP Controls', 'contactin' ); ?></h4>
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row"><?php _e( 'Enable Allowlist Mode', 'contactin' ); ?></th>
					<td>
						<fieldset>
							<legend class="screen-reader-text"><span><?php _e( 'Enable Allowlist Mode', 'contactin' ); ?></span></legend>
							<input type="hidden" name="ip_allowlist_enable" value="0" />
							<label><input name="ip_allowlist_enable" type="checkbox" value="1" <?php checked( ! empty( $settings['ip_allowlist_enable'] ) ); ?> data-search="ip allowlist security" /> <?php _e( 'Only allow listed IP addresses', 'contactin' ); ?></label>
						</fieldset>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="ip_allowlist"><?php _e( 'IP Allowlist', 'contactin' ); ?></label></th>
					<td>
						<textarea name="ip_allowlist" id="ip_allowlist" rows="5" class="large-text code" data-search="ip allowlist addresses security"><?php echo esc_textarea( $settings['ip_allowlist'] ?? '' ); ?></textarea>
						<p class="description"><?php _e( 'Enter one IP per line (IPv4/IPv6). When allowlist mode is enabled, only these IPs can submit.', 'contactin' ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="ip_blacklist"><?php _e( 'IP Blocklist', 'contactin' ); ?></label></th>
					<td>
						<textarea name="ip_blacklist" id="ip_blacklist" rows="5" class="large-text code" data-search="ip blocklist blacklist addresses security"><?php echo esc_textarea( $settings['ip_blacklist'] ?? '' ); ?></textarea>
						<p class="description"><?php _e( 'Enter one IP per line (IPv4/IPv6). Listed IPs are always blocked.', 'contactin' ); ?></p>
					</td>
				</tr>
			</table>
		</div>

		<!-- Tab: SMTP -->
		<div id="cin-tab-smtp" class="cin-tab-content">
			<h3><?php _e( 'SMTP Configuration', 'contactin' ); ?></h3>
			<?php if ( ! empty( $settings['smtp_enable'] ) ) : ?>
				<div class="notice notice-warning is-dismissible" style="margin:12px 0;<?php echo $should_warn_sender_mismatch ? '' : ' display:none;'; ?>" id="cin-smtp-domain-warning" data-notice-id="smtp-domain-mismatch">
					<p>
						<strong><?php esc_html_e( 'Sender domain mismatch detected.', 'contactin' ); ?></strong>
						<?php
						printf(
							esc_html__( 'The configured sender domain (%1$s) differs from the WordPress admin domain (%2$s). Ensure the "From" address belongs to the authenticated SMTP domain to pass SPF, DKIM, and DMARC.', 'contactin' ),
							esc_html( $smtp_domain_display ?: '—' ),
							esc_html( $admin_domain ?: '—' )
						);
						?>
					</p>
					<button type="button" class="notice-dismiss cin-notice-dismiss"><span class="screen-reader-text"><?php _e( 'Dismiss this notice.', 'contactin' ); ?></span></button>
				</div>
			<?php endif; ?>
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row"><label for="smtp-enable-btn"><?php _e( 'Enable SMTP', 'contactin' ); ?></label></th>
					<td>
						<input type="hidden" name="smtp_enable" id="smtp-enable-hidden" value="<?php echo ! empty( $settings['smtp_enable'] ) ? '1' : '0'; ?>" data-search="enable smtp mail email" />
						<button type="button" id="smtp-enable-btn" class="button button-small<?php echo ! empty( $settings['smtp_enable'] ) ? ' enabled' : ''; ?>" data-enabled="<?php echo ! empty( $settings['smtp_enable'] ) ? '1' : '0'; ?>">
							<?php echo ! empty( $settings['smtp_enable'] ) ? esc_html__( 'Disable SMTP', 'contactin' ) : esc_html__( 'Enable SMTP', 'contactin' ); ?>
						</button>
						<span id="contactin-smtp-status-label" class="<?php echo ! empty( $settings['smtp_enable'] ) ? 'enabled' : 'disabled'; ?> cin-ml-lg">
							<?php echo ! empty( $settings['smtp_enable'] ) ? esc_html__( 'Enabled', 'contactin' ) : esc_html__( 'Disabled', 'contactin' ); ?>
						</span>
						<span class="description"><?php _e( 'Send emails via SMTP instead of WordPress default mail.', 'contactin' ); ?></span>
					</td>
				</tr>
				<tr class="smtp-dependent-field">
					<th scope="row"><label for="smtp_host"><?php _e( 'Host', 'contactin' ); ?></label></th>
					<td><input name="smtp_host" type="text" id="smtp_host" value="<?php echo esc_attr( $settings['smtp_host'] ?? '' ); ?>" class="large-text"  placeholder="smtp.gmail.com" data-search="smtp host" <?php disabled( empty( $settings['smtp_enable'] ) ); ?> /></td>
				</tr>
				<tr class="smtp-dependent-field">
					<th scope="row"><label for="smtp_port"><?php _e( 'Port', 'contactin' ); ?></label></th>
					<td><input name="smtp_port" type="number" id="smtp_port" value="<?php echo esc_attr( $settings['smtp_port'] ?? '587' ); ?>"  data-search="smtp port" <?php disabled( empty( $settings['smtp_enable'] ) ); ?> /></td>
				</tr>
				<tr class="smtp-dependent-field">
					<th scope="row"><label for="smtp_encryption"><?php _e( 'Encryption', 'contactin' ); ?></label></th>
					<td>
						<select name="smtp_encryption" id="smtp_encryption"  data-search="smtp encryption" <?php disabled( empty( $settings['smtp_enable'] ) ); ?>>
							<option value="none" <?php selected( $settings['smtp_encryption'] ?? '', 'none' ); ?>><?php _e( 'None', 'contactin' ); ?></option>
							<option value="ssl" <?php selected( $settings['smtp_encryption'] ?? '', 'ssl' ); ?>>SSL</option>
							<option value="tls" <?php selected( $settings['smtp_encryption'] ?? '', 'tls' ); ?>>TLS</option>
						</select>
					</td>
				</tr>
				<tr class="smtp-dependent-field">
					<th scope="row"><label for="smtp_user"><?php _e( 'Username', 'contactin' ); ?></label></th>
					<td><input name="smtp_user" type="text" id="smtp_user" value="<?php echo esc_attr( $settings['smtp_user'] ?? '' ); ?>" class="large-text"  autocomplete="username" data-search="smtp username" <?php disabled( empty( $settings['smtp_enable'] ) ); ?> /></td>
				</tr>
				<tr class="smtp-dependent-field">
					<th scope="row"><label for="smtp_pass"><?php _e( 'Password', 'contactin' ); ?></label></th>
					<td>
						<input name="smtp_pass" type="password" id="smtp_pass" value="" class="large-text"  
							placeholder="<?php echo ! empty( $settings['smtp_pass'] ) ? esc_attr( __( 'Existing password set (leave blank to keep)', 'contactin' ) ) : esc_attr( __( 'Enter SMTP password', 'contactin' ) ); ?>" 
							autocomplete="current-password" data-search="smtp password" <?php disabled( empty( $settings['smtp_enable'] ) ); ?> />
						<p class="description">
							<?php
							if ( ! empty( $settings['smtp_pass'] ) ) {
								_e( 'Password is already saved. Leave blank to keep existing password, or enter a new one to change it.', 'contactin' );
							} else {
								_e( 'Enter the SMTP password for authentication.', 'contactin' );
							}
							?>
						</p>
					</td>
				</tr>
				<tr class="smtp-dependent-field">
					<th scope="row"><label for="smtp_from_email"><?php _e( 'Sender Email Address', 'contactin' ); ?></label></th>
					<td>
						<input name="smtp_from_email" type="email" id="smtp_from_email" value="<?php echo esc_attr( $smtp_from_email ); ?>" class="large-text" autocomplete="off" data-search="smtp sender email" <?php disabled( empty( $settings['smtp_enable'] ) ); ?> />
						<p class="description">
							<?php _e( 'Must be a mailbox you own on the authenticated SMTP domain. This becomes the visible From address.', 'contactin' ); ?>
						</p>
					</td>
				</tr>
				<tr class="smtp-dependent-field">
					<th scope="row"><label for="smtp_from_name"><?php _e( 'Sender Name', 'contactin' ); ?></label></th>
					<td>
						<input name="smtp_from_name" type="text" id="smtp_from_name" value="<?php echo esc_attr( $smtp_from_name ); ?>" class="large-text" autocomplete="off" data-search="smtp sender name" <?php disabled( empty( $settings['smtp_enable'] ) ); ?> />
						<p class="description">
							<?php _e( 'Shown alongside the sender email. Defaults to the site name if left blank.', 'contactin' ); ?>
						</p>
					</td>
				</tr>
			</table>
			<div style="margin-top: 10px; padding-top: 8px;">
				<button id="contactin-test-smtp" class="button button-secondary" type="button">
					<span class="btn-text"><?php _e( 'Test SMTP', 'contactin' ); ?></span>
					<span class="spinner" style="display:none;"></span>
				</button>
				<span id="contactin-smtp-result" style="font-weight:bold;margin-left:10px;"></span>
			</div>
		</div>

		<!-- Tab: Notifications -->
		<div id="cin-tab-notifications" class="cin-tab-content">
			<h3><?php _e( 'Email Notifications', 'contactin' ); ?></h3>
			<?php if ( empty( $settings['smtp_enable'] ) ) : ?>
				<div class="notice notice-warning is-dismissible" style="margin:12px 0;" id="cin-smtp-disabled-warning" data-notice-id="smtp-disabled-notifications">
					<p>
						<strong><?php esc_html_e( 'SMTP is disabled.', 'contactin' ); ?></strong>
						<?php esc_html_e( 'Enable SMTP in the SMTP tab to activate email notifications.', 'contactin' ); ?>
					</p>
					<button type="button" class="notice-dismiss cin-notice-dismiss"><span class="screen-reader-text"><?php _e( 'Dismiss this notice.', 'contactin' ); ?></span></button>
				</div>
			<?php endif; ?>
			<table class="form-table" role="presentation">
				<tr class="smtp-notification-field">
					<th scope="row"><?php _e( 'Send Form Submission to Admin', 'contactin' ); ?></th>
					<td>
						<fieldset>
							<legend class="screen-reader-text"><span><?php _e( 'Send Form Submission to Admin', 'contactin' ); ?></span></legend>
							<input type="hidden" name="send_admin_notification" value="0" />
							<label><input name="send_admin_notification" type="checkbox" value="1" <?php checked( ! empty( $settings['send_admin_notification'] ) && ! empty( $settings['smtp_enable'] ) ); ?> data-search="send admin notification" <?php disabled( empty( $settings['smtp_enable'] ) ); ?> /> <?php _e( 'Send notification email to admin when a new message is received.', 'contactin' ); ?></label>
						</fieldset>
					</td>
				</tr>
				<tr class="smtp-notification-field">
					<th scope="row"><label for="admin_email"><?php _e( 'Admin Email(s)', 'contactin' ); ?></label></th>
					<td>
						<input name="admin_email" type="text" id="admin_email"
							value="<?php echo esc_attr( $settings['admin_email'] ?? get_option( 'admin_email' ) ); ?>"
							class="large-text"  maxlength="254" data-search="admin email" <?php disabled( empty( $settings['smtp_enable'] ) ); ?> />
						<p class="description">
							<?php _e( 'Comma-separated for multiple emails. Each must be valid and no longer than 254 characters.', 'contactin' ); ?>
						</p>
					</td>
				</tr>
				<tr class="smtp-notification-field">
					<th scope="row"><?php _e( 'Send Copy to User', 'contactin' ); ?></th>
					<td>
						<fieldset>
							<legend class="screen-reader-text"><span><?php _e( 'Send Copy to User', 'contactin' ); ?></span></legend>
							<input type="hidden" name="send_user_copy" value="0" />
							<label><input name="send_user_copy" type="checkbox" value="1" <?php checked( ! empty( $settings['send_user_copy'] ) && ! empty( $settings['smtp_enable'] ) ); ?> data-search="send user copy" <?php disabled( empty( $settings['smtp_enable'] ) ); ?> /> <?php _e( 'Send confirmation email to user', 'contactin' ); ?></label>
						</fieldset>
					</td>
				</tr>
			</table>
		</div>

		<!-- Tab: Form -->
		<div id="cin-tab-form" class="cin-tab-content">
			<h3><?php _e( 'Global Form Settings', 'contactin' ); ?></h3>
			<p class="description" style="margin-bottom:16px;font-size:13px;"><?php _e( 'These settings apply globally to every contact form on this site. Security-critical options (such as file uploads) cannot be overridden at the Form Profile level — they act as a site-wide lock.', 'contactin' ); ?></p>

			<table class="form-table" role="presentation">
				<tr>
					<th scope="row"><label for="form-enable-subject-btn"><?php _e( 'Subject Field', 'contactin' ); ?></label></th>
					<td>
						<input type="hidden" name="form_enable_subject" id="form-enable-subject-hidden" value="<?php echo ! empty( $settings['form_enable_subject'] ) ? '1' : '0'; ?>" data-search="enable subject field form" />
						<button type="button" id="form-enable-subject-btn" class="button button-small<?php echo ! empty( $settings['form_enable_subject'] ) ? ' enabled' : ''; ?>" data-enabled="<?php echo ! empty( $settings['form_enable_subject'] ) ? '1' : '0'; ?>">
							<?php echo ! empty( $settings['form_enable_subject'] ) ? esc_html__( 'Disable Subject Field', 'contactin' ) : esc_html__( 'Enable Subject Field', 'contactin' ); ?>
						</button>
						<span id="contactin-subject-status-label" class="<?php echo ! empty( $settings['form_enable_subject'] ) ? 'enabled' : 'disabled'; ?>" style="margin-left:10px;">
							<?php echo ! empty( $settings['form_enable_subject'] ) ? esc_html__( 'Enabled', 'contactin' ) : esc_html__( 'Disabled', 'contactin' ); ?>
						</span>
						<span class="description"><?php _e( 'Allow users to enter a subject.', 'contactin' ); ?></span>
					</td>
				</tr>

				<tr>
					<th scope="row"><label for="form_require_subject"><?php _e( 'Require Subject', 'contactin' ); ?></label></th>
					<td>
						<label>
							<input type="checkbox" name="form_require_subject" id="form_require_subject" value="1" data-search="require subject field mandatory"
								<?php checked( ! empty( $settings['form_require_subject'] ) ); ?> />
							<?php _e( 'Make the Subject field mandatory', 'contactin' ); ?>
						</label>
						<p class="description"><?php _e( 'Only applies when the Subject field is enabled. Individual Form Profiles can override this.', 'contactin' ); ?></p>
					</td>
				</tr>

				<tr>
					<th scope="row"><label for="form_require_phone"><?php _e( 'Require Phone', 'contactin' ); ?></label></th>
					<td>
						<label>
							<input type="checkbox" name="form_require_phone" id="form_require_phone" value="1" data-search="require phone field mandatory"
								<?php checked( ! empty( $settings['form_require_phone'] ) ); ?> />
							<?php _e( 'Make the Phone field mandatory', 'contactin' ); ?>
						</label>
						<p class="description"><?php _e( 'Individual Form Profiles can override this per form.', 'contactin' ); ?></p>
					</td>
				</tr>

				<tr>
					<th scope="row"><label for="form-enable-salutation-btn"><?php _e( 'Salutation Field', 'contactin' ); ?></label></th>
					<td>
						<input type="hidden" name="form_enable_salutation" id="form-enable-salutation-hidden" value="<?php echo ! empty( $settings['form_enable_salutation'] ) ? '1' : '0'; ?>" data-search="enable salutation greeting title field form" />
						<button type="button" id="form-enable-salutation-btn" class="button button-small<?php echo ! empty( $settings['form_enable_salutation'] ) ? ' enabled' : ''; ?>" data-enabled="<?php echo ! empty( $settings['form_enable_salutation'] ) ? '1' : '0'; ?>">
							<?php echo ! empty( $settings['form_enable_salutation'] ) ? esc_html__( 'Disable Salutation Field', 'contactin' ) : esc_html__( 'Enable Salutation Field', 'contactin' ); ?>
						</button>
						<span id="contactin-salutation-status-label" class="<?php echo ! empty( $settings['form_enable_salutation'] ) ? 'enabled' : 'disabled'; ?>" style="margin-left:10px;">
							<?php echo ! empty( $settings['form_enable_salutation'] ) ? esc_html__( 'Enabled', 'contactin' ) : esc_html__( 'Disabled', 'contactin' ); ?>
						</span>
						<span class="description"><?php _e( 'Allow users to select a salutation (Mr/Ms/Mrs/Dr/etc.).', 'contactin' ); ?></span>
					</td>
				</tr>

				<tr>
					<th scope="row"><label for="form-enable-attachment-btn"><?php _e( 'File Attachment', 'contactin' ); ?></label></th>
					<td>
						<input type="hidden" name="form_enable_attachment" value="0" />
						<label for="form-enable-attachment-checkbox" class="cin-flex-center-gap cin-cursor-pointer">
							<input id="form-enable-attachment-checkbox" name="form_enable_attachment" type="checkbox" value="1" <?php checked( ! empty( $settings['form_enable_attachment'] ) ); ?> data-search="enable file attachment upload form" class="cin-cursor-pointer" style="width: 20px; height: 20px; min-width: 20px; min-height: 20px; accent-color: #2271b1;" />
							<span id="contactin-attachment-status-label" class="<?php echo ! empty( $settings['form_enable_attachment'] ) ? 'enabled' : 'disabled'; ?>">
								<?php echo ! empty( $settings['form_enable_attachment'] ) ? esc_html__( 'Enabled', 'contactin' ) : esc_html__( 'Disabled', 'contactin' ); ?>
							</span>
						</label>
						<p class="description" style="margin-top:6px;"><?php _e( 'Globally enable or disable file uploads across all contact forms.', 'contactin' ); ?><br>
						<strong><?php _e( 'When disabled here, no Form Profile or shortcode can override it.', 'contactin' ); ?></strong> <?php _e( 'This is a hard security lock — use it intentionally.', 'contactin' ); ?></p>
					</td>
				</tr>

				<tr>
					<th scope="row"><label for="max_name_chars"><?php _e( 'Name Field Length (Max Chars)', 'contactin' ); ?></label></th>
					<td>
						<input name="max_name_chars" type="number" id="max_name_chars"
							value="<?php echo esc_attr( $settings['max_name_chars'] ?? $defaults['max_name_chars'] ); ?>"
							min="1" data-search="name field length max chars" />
						<p class="description"><?php printf( __( 'Default: %d characters', 'contactin' ), $defaults['max_name_chars'] ); ?></p>

						<div id="cin-min-words-error" class="cin-hidden cin-color-error cin-mt-md" style="font-weight: bold;">
							<?php _e( 'Error: Minimum words must be at least 2', 'contactin' ); ?>
						</div>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="min_name_words"><?php _e( 'Name Field Min Words', 'contactin' ); ?></label></th>
					<td>
						<input name="min_name_words" type="number" id="min_name_words"
							value="<?php echo esc_attr( $settings['min_name_words'] ?? $defaults['min_name_words'] ); ?>"
							min="2" data-search="name field min words minimum" />
						<p class="description"><?php printf( __( 'Default: %d words. Must be at least 2.', 'contactin' ), $defaults['min_name_words'] ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="max_subject_chars"><?php _e( 'Subject Field Length (Max Chars)', 'contactin' ); ?></label></th>
					<td>
						<input name="max_subject_chars" type="number" id="max_subject_chars"
							value="<?php echo esc_attr( $settings['max_subject_chars'] ?? $defaults['max_subject_chars'] ); ?>"
							min="1" data-search="subject field length max chars" />
						<p class="description"><?php printf( __( 'Default: %d characters', 'contactin' ), $defaults['max_subject_chars'] ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="min_subject_words"><?php _e( 'Subject Field Min Words', 'contactin' ); ?></label></th>
					<td>
						<input name="min_subject_words" type="number" id="min_subject_words"
							value="<?php echo esc_attr( $settings['min_subject_words'] ?? $defaults['min_subject_words'] ); ?>"
							min="1" data-search="subject field min words" />
						<p class="description"><?php printf( __( 'Default: %d words', 'contactin' ), $defaults['min_subject_words'] ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="max_message_chars"><?php _e( 'Message Field Length (Max Chars)', 'contactin' ); ?></label></th>
					<td>
						<input name="max_message_chars" type="number" id="max_message_chars"
							value="<?php echo esc_attr( $settings['max_message_chars'] ?? $defaults['max_message_chars'] ); ?>"
							min="1" data-search="message field length max chars" />
						<p class="description"><?php printf( __( 'Default: %d characters', 'contactin' ), $defaults['max_message_chars'] ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="min_message_words"><?php _e( 'Message Field Min Words', 'contactin' ); ?></label></th>
					<td>
						<input name="min_message_words" type="number" id="min_message_words"
							value="<?php echo esc_attr( $settings['min_message_words'] ?? $defaults['min_message_words'] ); ?>"
							min="1" data-search="message field min words" />
						<p class="description"><?php printf( __( 'Default: %d words', 'contactin' ), $defaults['min_message_words'] ); ?></p>
					</td>
				</tr>

				<!-- File Upload Restrictions -->
				<?php
					$all_mimes = get_allowed_mime_types();
					$popular   = array( 'jpg', 'jpeg', 'png', 'gif', 'pdf', 'doc', 'docx', 'xls', 'xlsx', 'zip' );

					// Build a clean extension list with individual extensions as keys (not MIME keys)
					$extension_map = array();
				foreach ( $all_mimes as $mime_key => $mime_type ) {
					$exts = explode( '|', $mime_key );
					foreach ( $exts as $ext ) {
						$ext = strtolower( trim( $ext ) );
						if ( $ext && ! isset( $extension_map[ $ext ] ) ) {
							$extension_map[ $ext ] = $mime_type;
						}
					}
				}

					// Separate popular and other extensions, maintaining popular order
					$popular_exts = array();
					$other_exts   = array();
				foreach ( $popular as $popext ) {
					if ( isset( $extension_map[ $popext ] ) ) {
						$popular_exts[ $popext ] = $extension_map[ $popext ];
					}
				}
				foreach ( $extension_map as $ext => $mime ) {
					if ( ! isset( $popular_exts[ $ext ] ) ) {
						$other_exts[ $ext ] = $mime;
					}
				}

					// Get saved allowed file types
					$types_string  = ! empty( $settings['allowed_file_types'] ) ? $settings['allowed_file_types'] : $defaults['allowed_file_types'];
					$current_types = array_filter( array_map( 'trim', explode( ',', $types_string ) ) );
				?>
				<tr>
					<th scope="row"><label for="allowed_file_types"><?php _e( 'Allowed File Types', 'contactin' ); ?></label></th>
					<td>
						<div style="margin-bottom: 10px;">
							<button type="button" id="cin-select-recommended-types" class="button button-secondary" style="margin-right: 5px;">
								<?php _e( 'Select Recommended Types', 'contactin' ); ?>
							</button>
							<button type="button" id="cin-clear-file-types" class="button button-secondary">
								<?php _e( 'Clear All', 'contactin' ); ?>
							</button>
						</div>
						<select name="allowed_file_types[]" id="allowed_file_types" multiple size="10" class="regular-text" data-search="allowed file types">
							<?php foreach ( $popular_exts + $other_exts as $ext => $mime ) : ?>
								<option value="<?php echo esc_attr( $ext ); ?>"
									<?php selected( in_array( $ext, $current_types, true ) ); ?>>
									<?php echo strtoupper( $ext ); ?> (<?php echo esc_html( $mime ); ?>)
								</option>
							<?php endforeach; ?>
						</select>
						<p class="description">
							<?php _e( 'Hold Ctrl/Command to select multiple types. Popular types are listed first.', 'contactin' ); ?>
						</p>
					</td>
				</tr>

				<tr>
					<th scope="row"><label for="max_file_size"><?php _e( 'Max File Size (MB)', 'contactin' ); ?></label></th>
					<td>
						<input name="max_file_size" type="number" id="max_file_size"
							value="<?php echo esc_attr( $settings['max_file_size'] ?? $defaults['max_file_size'] ); ?>"
							min="1" data-search="max file size" />
						<p class="description">
							<?php printf( __( 'Default safe size: %d MB', 'contactin' ), $defaults['max_file_size'] ); ?>
						</p>
					</td>
				</tr>
			</table>
		</div>

		<!-- Tab: Form Profiles -->
		<div id="cin-tab-forms" class="cin-tab-content">
			<h3><?php _e( 'Form Profiles', 'contactin' ); ?></h3>
			<p class="description"><?php _e( 'Create named form configurations. Assign a profile to any block or shortcode using the Form Profile selector. All submissions from every profile still land in the same inbox.', 'contactin' ); ?></p>

			<div id="cin-profiles-wrap" style="margin-top:18px;">
				<table class="widefat striped" id="cin-profiles-table">
					<thead>
						<tr>
							<th style="width:140px"><?php _e( 'Slug', 'contactin' ); ?></th>
							<th><?php _e( 'Label', 'contactin' ); ?></th>
							<th style="width:80px"><?php _e( 'Phone', 'contactin' ); ?></th>
							<th style="width:80px"><?php _e( 'Subject', 'contactin' ); ?></th>
							<th style="width:80px"><?php _e( 'Consent', 'contactin' ); ?></th>
							<th style="width:110px"><?php _e( 'Notify Email', 'contactin' ); ?></th>
							<th style="width:120px"></th>
						</tr>
					</thead>
					<tbody id="cin-profiles-tbody">
						<tr><td colspan="7" style="text-align:center;padding:20px;"><?php _e( 'Loading…', 'contactin' ); ?></td></tr>
					</tbody>
				</table>
				<p style="margin-top:12px;">
					<button type="button" class="button button-primary" id="cin-add-profile-btn" style="position:relative;z-index:200;pointer-events:auto;"><?php _e( '+ New Profile', 'contactin' ); ?></button>
				</p>
			</div>

			<!-- Profile Editor Popup Modal -->
			<div id="cin-profile-modal" class="cin-modal cin-modal-hidden" role="dialog" aria-modal="true" aria-labelledby="cin-editor-title">
				<div class="cin-modal-overlay"></div>
				<div class="cin-modal-content" style="max-width:680px;">
					<div class="cin-modal-header">
						<h2 class="cin-modal-title" id="cin-editor-title"><?php _e( 'New Profile', 'contactin' ); ?></h2>
						<button type="button" class="cin-modal-close" aria-label="<?php esc_attr_e( 'Close', 'contactin' ); ?>">&#x2715;</button>
					</div>
					<div class="cin-modal-body">
				<table class="form-table" role="presentation" style="margin-top:0;">
					<tr>
						<th><label for="cin-p-label"><?php _e( 'Profile Name', 'contactin' ); ?></label></th>
						<td><input type="text" id="cin-p-label" class="regular-text" placeholder="<?php esc_attr_e( 'e.g. Support Form', 'contactin' ); ?>" /></td>
					</tr>
					<tr id="cin-slug-row">
						<th><label for="cin-p-slug"><?php _e( 'Slug', 'contactin' ); ?></label></th>
						<td>
							<span id="cin-slug-display">
								<code id="cin-slug-preview" style="font-size:13px;background:#f0f0f1;padding:2px 8px;border-radius:3px;font-family:monospace;">&mdash;</code>
								<a href="#" id="cin-slug-edit-link" style="margin-left:8px;font-size:12px;"><?php _e( 'Edit', 'contactin' ); ?></a>
							</span>
							<span id="cin-slug-input-wrap" style="display:none;">
								<input type="text" id="cin-p-slug" class="regular-text" pattern="[a-z0-9_-]+" placeholder="e.g. support" style="width:200px;" />
								<a href="#" id="cin-slug-auto-link" style="margin-left:8px;font-size:12px;"><?php _e( 'Auto-generate', 'contactin' ); ?></a>
								<span id="cin-slug-error" style="color:#dc3232;margin-left:8px;font-size:12px;display:none;"></span>
							</span>
							<p class="description" id="cin-slug-desc-new"><?php _e( 'Auto-generated from profile name. Lowercase letters, numbers, hyphens, underscores. Cannot be changed after creation.', 'contactin' ); ?></p>
							<p class="description" id="cin-slug-desc-edit" style="display:none;"><?php _e( 'Slug cannot be changed after creation.', 'contactin' ); ?></p>
						</td>
					</tr>
					<tr>
						<th><?php _e( 'Fields', 'contactin' ); ?></th>
						<td>
							<label><input type="checkbox" id="cin-p-show-phone" checked /> <?php _e( 'Show Phone field', 'contactin' ); ?></label><br>
							<label><input type="checkbox" id="cin-p-require-phone" /> <?php _e( 'Require Phone', 'contactin' ); ?></label><br>
							<label><input type="checkbox" id="cin-p-show-salutation" /> <?php _e( 'Show Salutation', 'contactin' ); ?></label><br>
							<label><input type="checkbox" id="cin-p-show-subject" /> <?php _e( 'Show Subject line', 'contactin' ); ?></label><br>
							<label><input type="checkbox" id="cin-p-require-subject" /> <?php _e( 'Require Subject', 'contactin' ); ?></label><br>
							<label><input type="checkbox" id="cin-p-show-consent" checked /> <?php _e( 'Show Consent checkbox', 'contactin' ); ?></label>
						</td>
					</tr>
					<tr>
						<th><label for="cin-p-recaptcha"><?php _e( 'reCAPTCHA', 'contactin' ); ?></label></th>
						<td>
							<select id="cin-p-recaptcha">
								<option value="auto"><?php _e( 'Auto (follow global setting)', 'contactin' ); ?></option>
								<option value="on"><?php _e( 'Force On', 'contactin' ); ?></option>
								<option value="off"><?php _e( 'Force Off', 'contactin' ); ?></option>
							</select>
						</td>
					</tr>
					<tr>
						<th><label for="cin-p-confetti"><?php _e( 'Confetti', 'contactin' ); ?></label></th>
						<td>
							<select id="cin-p-confetti">
								<option value="auto"><?php _e( 'Auto (follow global setting)', 'contactin' ); ?></option>
								<option value="on"><?php _e( 'Force On', 'contactin' ); ?></option>
								<option value="off"><?php _e( 'Force Off', 'contactin' ); ?></option>
							</select>
						</td>
					</tr>
					<tr>
						<th><label for="cin-p-notify-email"><?php _e( 'Notification Email', 'contactin' ); ?></label></th>
						<td>
							<input type="email" id="cin-p-notify-email" class="regular-text" placeholder="<?php esc_attr_e( 'Leave empty to use global admin email', 'contactin' ); ?>" />
						</td>
					</tr>
					<tr>
						<th><label for="cin-p-success-message"><?php _e( 'Success Message', 'contactin' ); ?></label></th>
						<td>
							<input type="text" id="cin-p-success-message" class="large-text" placeholder="<?php _e( 'Leave empty to use global success message', 'contactin' ); ?>" />
						</td>
					</tr>
					<tr>
						<th><label for="cin-p-consent-text"><?php _e( 'Consent Text', 'contactin' ); ?></label></th>
						<td>
							<textarea id="cin-p-consent-text" class="large-text" rows="2" placeholder="<?php _e( 'Leave empty to use global consent text', 'contactin' ); ?>"></textarea>
						</td>
					</tr>
				</table>
					</div><!-- /cin-modal-body -->
					<div id="cin-profile-pro-notice" style="display:none;margin:0 20px 12px;padding:10px 14px;background:#fff8e1;border-left:4px solid #f0a500;border-radius:2px;font-size:13px;line-height:1.5;"></div>
					<div class="cin-modal-footer" style="padding:16px 20px;border-top:1px solid #ddd;display:flex;align-items:center;gap:8px;flex-shrink:0;">
						<button type="button" class="button button-primary" id="cin-save-profile-btn"><?php _e( 'Save Profile', 'contactin' ); ?></button>
						<button type="button" class="button" id="cin-cancel-profile-btn"><?php _e( 'Cancel', 'contactin' ); ?></button>
						<span id="cin-profile-msg" style="margin-left:8px;display:none;"></span>
					</div>
				</div><!-- /cin-modal-content -->
			</div><!-- /cin-profile-modal -->
		</div><!-- /cin-tab-forms -->

		<!-- Tab: Advanced -->
		<div id="cin-tab-advanced" class="cin-tab-content">
			<h3><?php _e( 'Advanced Settings', 'contactin' ); ?></h3>

			<table class="form-table" role="presentation">
				<tr>
					<th scope="row"><label for="email_log_retention_days"><?php _e( 'Email Log Retention (days)', 'contactin' ); ?></label></th>
					<td>
						<input name="email_log_retention_days" type="number" id="email_log_retention_days"
							value="<?php echo esc_attr( $settings['email_log_retention_days'] ?? 90 ); ?>"
							min="1" data-search="email log retention" />
						<p class="description"><?php _e( 'Number of days to keep email logs before automatic cleanup.', 'contactin' ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="gdpr_log_retention_days"><?php _e( 'GDPR Log Retention (days)', 'contactin' ); ?></label></th>
					<td>
						<input name="gdpr_log_retention_days" type="number" id="gdpr_log_retention_days"
							value="<?php echo esc_attr( $settings['gdpr_log_retention_days'] ?? 90 ); ?>"
							min="1" data-search="gdpr log retention" />
						<p class="description"><?php _e( 'Number of days to keep GDPR deletion logs before pruning.', 'contactin' ); ?></p>
					</td>
				</tr>
			</table>

			<?php
			$queue_event_email    = Config::CRON_PROCESS_EMAIL;
			$queue_next_run_email = wp_next_scheduled( $queue_event_email );

			$current_interval_email = get_option( 'contactin_queue_interval', 'contactin_fifteen_minutes' );

			$cron_array = _get_cron_array();
			foreach ( $cron_array as $timestamp => $cron ) {
				if ( isset( $cron[ $queue_event_email ] ) ) {
					foreach ( $cron[ $queue_event_email ] as $data ) {
						if ( ! empty( $data['schedule'] ) ) {
							$current_interval_email = $data['schedule'];
							break 2;
						}
					}
				}
			}

			$schedules         = wp_get_schedules();
			$allowed_intervals = array(
				'contactin_one_minute'      => __( 'Every 1 minute', 'contactin' ),
				'contactin_two_minutes'     => __( 'Every 2 minutes', 'contactin' ),
				'contactin_five_minutes'    => __( 'Every 5 minutes', 'contactin' ),
				'contactin_fifteen_minutes' => __( 'Every 15 minutes', 'contactin' ),
				'hourly'                    => __( 'Hourly', 'contactin' ),
			);
			?>

			<h4 style="margin-top:24px;">&raquo; <?php _e( 'Background Job Scheduling', 'contactin' ); ?></h4>
			<p class="description" style="margin-bottom:10px;">
				<?php _e( 'Adjust how often the email queue processor runs. Use Dashboard → Background Jobs to monitor executions and health.', 'contactin' ); ?>
			</p>

			<table class="wp-list-table widefat fixed striped" style="margin-top: 10px;">
				<thead>
					<tr>
						<th style="width: 30%;"><?php _e( 'Job', 'contactin' ); ?></th>
						<th style="width: 30%;"><?php _e( 'Schedule', 'contactin' ); ?></th>
						<th style="width: 25%;"><?php _e( 'Next Run', 'contactin' ); ?></th>
						<th style="width: 15%;"><?php _e( 'Actions', 'contactin' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<tr>
						<td><strong><?php _e( 'Email Processor', 'contactin' ); ?></strong><br><small><?php echo esc_html( $queue_event_email ); ?></small></td>
						<td>
							<select name="queue_cron_interval" id="queue_cron_interval" class="cron-interval-select" data-search="email processor cron schedule background job interval" data-event="<?php echo esc_attr( $queue_event_email ); ?>" data-old="<?php echo esc_attr( $current_interval_email ); ?>">
								<?php foreach ( $allowed_intervals as $key => $label ) : ?>
									<?php
									if ( ! isset( $schedules[ $key ] ) ) {
										continue; }
									?>
									<option value="<?php echo esc_attr( $key ); ?>" <?php selected( $current_interval_email, $key ); ?>><?php echo esc_html( $label ); ?></option>
								<?php endforeach; ?>
							</select>
						</td>
						<td><?php echo $queue_next_run_email ? date_i18n( 'Y-m-d H:i:s', $queue_next_run_email ) : __( 'Not scheduled', 'contactin' ); ?></td>
						<td>
							<button type="button" class="button button-small run-cron-now" data-event="<?php echo esc_attr( $queue_event_email ); ?>"><?php _e( 'Run Now', 'contactin' ); ?></button>
						</td>
					</tr>
				</tbody>
			</table>

			<div class="notice notice-warning inline" style="margin:12px 0;">
				<p>
					<?php _e( 'Running jobs more frequently than every 15 minutes can add load. Form submissions already trigger immediate one-off runs to avoid notification delays, so 15 minutes is recommended for the recurring schedule.', 'contactin' ); ?>
				</p>
			</div>

			<div class="notice notice-info inline" style="margin:12px 0;">
				<p>
					<?php _e( 'Background Jobs monitoring lives in Dashboard → Background Jobs.', 'contactin' ); ?>
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=contactin-analytics#tab-cron' ) ); ?>"><?php _e( 'Open Background Jobs', 'contactin' ); ?></a>
				</p>
			</div>
		</div>

		<!-- Tab: AI Classifier -->
		<div id="cin-tab-ai-classifier" class="cin-tab-content">
			<h3><?php _e( 'AI Intent Classifier', 'contactin' ); ?></h3>
			<p class="description">
				<?php _e( 'Configure automatic message classification and intent detection using business-specific keywords.', 'contactin' ); ?>
			</p>

			<?php $this->render_intent_settings( $settings ); ?>
		</div>

		<div class="cin-settings-save-wrapper" style="margin-top: 30px; padding-top: 15px; border-top: 1px solid #ccc; position: relative; z-index: 1001;">
			<button type="submit" id="contactin-save-button" class="button button-primary" data-state="default">
				<span class="cin-btn-text"><?php _e( 'Save Settings', 'contactin' ); ?></span>
				<span class="cin-btn-spinner" style="display:none;margin-left:6px;">
					<span class="spinner" style="display:inline-block;vertical-align:middle;"></span>
				</span>
				<span class="cin-btn-saved" style="display:none;margin-left:6px;">✓ <?php _e( 'Saved', 'contactin' ); ?></span>
			</button>
		</div>
	</form>

	<!-- Support Boxes Row -->
	<?php \ContactInbox\Admin\SupportBoxesManager::render_support_boxes( 'settings' ); ?>

	<!-- Footer Info -->
	<div style="margin-top: 40px; padding-top: 20px; border-top: 1px solid #e0e0e0; text-align: center; color: #666; font-size: 12px;">
		<p>ContactIn v<?php echo esc_html( Config::VERSION ); ?> • Enterprise-Grade</p>
	</div>

	<!-- Generic confirm modal — gold-standard for security-critical / destructive actions.
		Uses the existing .cin-modal infrastructure from admin-global.min.css. -->
	<div id="cin-confirm-modal" class="cin-modal cin-modal-hidden" role="dialog" aria-modal="true" aria-labelledby="cin-confirm-modal-title">
		<div class="cin-modal-content" style="max-width:500px;">
			<div class="cin-modal-header">
				<h2 class="cin-modal-title" id="cin-confirm-modal-title" style="display:flex;align-items:center;gap:8px;flex-wrap:wrap;">
					<span id="cin-confirm-modal-badge" style="display:none;font-size:10px;font-weight:700;padding:2px 7px;border-radius:3px;color:#fff;letter-spacing:0.4px;text-transform:uppercase;"></span>
					<span id="cin-confirm-modal-heading"></span>
				</h2>
				<button type="button" class="cin-modal-close" id="cin-confirm-modal-x" aria-label="<?php esc_attr_e( 'Close', 'contactin' ); ?>">&#x00D7;</button>
			</div>
			<div class="cin-modal-body" id="cin-confirm-modal-body" style="padding:20px 20px 4px;line-height:1.65;font-size:13.5px;"></div>
			<div class="cin-modal-footer" style="padding:16px 20px;border-top:1px solid #ddd;display:flex;gap:10px;justify-content:flex-end;">
				<button type="button" class="button button-secondary" id="cin-confirm-modal-cancel"></button>
				<button type="button" class="button" id="cin-confirm-modal-confirm"></button>
			</div>
		</div>
	</div>
</div>
<!-- Help Modal -->
<?php require plugin_dir_path( __FILE__ ) . 'partials/settings-help-modal.php'; ?>

</div>