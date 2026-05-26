<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
use ContactInbox\Core\Config;

// phpcs:disable WordPress.WP.I18n.NonSingularStringLiteralDomain, WordPress.NamingConventions.PrefixAllGlobals, WordPress.Security.EscapeOutput, WordPress.WP.I18n.MissingTranslatorsComment, WordPress.WP.I18n.UnorderedPlaceholdersText, Generic.PHP.ForbiddenFunctions.Found, PluginCheck.CodeAnalysis.DiscouragedFunctions.load_plugin_textdomainFound, PluginCheck.CodeAnalysis.Heredoc.NotAllowed, PluginCheck.Security.DirectDB.UnescapedDBParameter, Squiz.PHP.DiscouragedFunctions.Discouraged, WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.NamingConventions.PrefixAllGlobals.DynamicHooknameFound, WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound, WordPress.PHP.DevelopmentFunctions.error_log_debug_backtrace, WordPress.WP.AlternativeFunctions.file_system_operations_fsockopen, WordPress.WP.AlternativeFunctions.file_system_operations_readfile, WordPress.WP.AlternativeFunctions.file_system_operations_rmdir, WordPress.WP.EnqueuedResourceParameters.MissingVersion, WordPress.WP.EnqueuedResources.NonEnqueuedScript, WordPress.WP.I18n.MissingArgDomain, WordPress.WP.I18n.UnorderedPlaceholdersPlural, WordPress.WP.I18n.UnorderedPlaceholdersSingle

extract( $args );
if ( ! isset( $settings ) || ! is_array( $settings ) ) {
	$settings = get_option( Config::OPTION_SETTINGS, array() );
}
$site_key         = $settings['recaptcha_site_key'] ?? '';
$enable_recaptcha = ! empty( $settings['recaptcha_enable'] ) && ! empty( $site_key );
$form_id          = $form_id ?? 'default';
$consent_text     = $consent_text
	?? ( $settings['consent_text'] ?? __( 'I consent to my data being used to respond to this message.', 'contactin' ) );
$privacy_url      = $privacy_url
	?? ( $settings['privacy_url'] ?? get_privacy_policy_url() );

// Random honeypot field name (unique per page load)
$honeypot_name = 'ci_hp_' . wp_generate_password( 12, false );

// Pull limits from settings
$max_name    = absint( $settings['max_name_chars'] ?? 100 );
$max_subject = absint( $settings['max_subject_chars'] ?? 150 );
$max_message = absint( $settings['max_message_chars'] ?? 2000 );
$min_name    = absint( $settings['min_name_words'] ?? 2 );
$min_subject = absint( $settings['min_subject_words'] ?? 3 );
$min_message = absint( $settings['min_message_words'] ?? 5 );
?>
<div class="cin-form-wrapper" data-form-id="<?php echo esc_attr( $form_id ); ?>">
	<form id="contactin-form" class="cin-contact-form" enctype="multipart/form-data">
	<input type="hidden" name="form_id" value="<?php echo esc_attr( $form_id ); ?>">

	<!-- Timing anti-bot fields: load time + HMAC to prevent tampering -->
	<?php
	$_ci_load_time  = time();
	$_ci_load_token = hash_hmac( 'sha256', (string) $_ci_load_time, wp_salt( 'auth' ) );
	?>
	<input type="hidden" name="ci_form_load_time"  value="<?php echo esc_attr( $_ci_load_time ); ?>">
	<input type="hidden" name="ci_form_load_token" value="<?php echo esc_attr( $_ci_load_token ); ?>">
	<?php
	$_cin_vf = array(
		'es' => (int) ! empty( $settings['form_enable_subject'] ),
		'rs' => (int) ( ! isset( $settings['form_require_subject'] ) || $settings['form_require_subject'] ),
		'rp' => (int) ! empty( $settings['require_phone'] ),
		'cr' => (int) ! empty( $settings['consent_required'] ),
	);
	$_cin_vf_json = wp_json_encode( $_cin_vf );
	$_cin_vf_mac  = hash_hmac( 'sha256', $_cin_vf_json, wp_salt( 'auth' ) );
	?>
	<input type="hidden" name="cin_vf"     value="<?php echo esc_attr( $_cin_vf_json ); ?>">
	<input type="hidden" name="cin_vf_mac" value="<?php echo esc_attr( $_cin_vf_mac ); ?>">

	<!-- Honeypot -->
	<div class="cin-honeypot" aria-hidden="true">
		<input type="text" name="<?php echo esc_attr( $honeypot_name ); ?>" tabindex="-1" autocomplete="off">
	</div>

	<!-- Salutation -->
	<?php if ( isset( $enable_salutation ) ? $enable_salutation : ! empty( $settings['form_enable_salutation'] ) ) : ?>
	<div class="cin-field cin-salutation">
		<label><?php esc_html_e( 'Salutation', 'contactin' ); ?></label>
		<select name="salutation">
		<option value=""><?php esc_html_e( 'Select', 'contactin' ); ?></option>
		<option value="Mr"><?php esc_html_e( 'Mr', 'contactin' ); ?></option>
		<option value="Ms"><?php esc_html_e( 'Ms', 'contactin' ); ?></option>
		<option value="Mrs"><?php esc_html_e( 'Mrs', 'contactin' ); ?></option>
		<option value="Dr"><?php esc_html_e( 'Dr', 'contactin' ); ?></option>
		<option value="Mx"><?php esc_html_e( 'Mx', 'contactin' ); ?></option>
		</select>
	</div>
	<?php endif; ?>

	<!-- Name -->
	<div class="cin-field">
		<label><?php esc_html_e( 'Name', 'contactin' ); ?> <span class="required">*</span></label>
		<input type="text" name="name" required maxlength="<?php echo esc_attr( $max_name ); ?>" data-min-words="<?php echo esc_attr( $min_name ); ?>">
		<div class="cin-field-meta">
		<span class="description"><?php printf( esc_html__( 'Max %d characters, min %d words', 'contactin' ), $max_name, $min_name ); ?></span>
		<span class="cin-char-counter" data-max="<?php echo esc_attr( $max_name ); ?>">0/<?php echo esc_html( $max_name ); ?></span>
		</div>
	</div>

	<!-- Email -->
	<div class="cin-field">
		<label><?php esc_html_e( 'Email', 'contactin' ); ?> <span class="required">*</span></label>
		<input type="email" name="email" required>
	</div>

	<!-- Phone -->
	<?php if ( $enable_phone ?? true ) : ?>
		<?php $phone_required = $require_phone ?? false; ?>
	<div class="cin-field">
		<label>
		<?php esc_html_e( 'Phone', 'contactin' ); ?>
		<?php
		if ( $phone_required ) :
			?>
			<span class="required">*</span><?php endif; ?>
		</label>
		<input type="tel" name="phone"<?php echo $phone_required ? ' required' : ''; ?>>
	</div>
	<?php endif; ?>

	<!-- Subject -->
	<?php if ( isset( $enable_subject ) ? $enable_subject : ! empty( $settings['form_enable_subject'] ) ) : ?>
		<?php $require_subject_flag = isset( $settings['form_require_subject'] ) ? (bool) $settings['form_require_subject'] : true; ?>
	<div class="cin-field">
		<label>
		<?php esc_html_e( 'Subject', 'contactin' ); ?>
		<?php
		if ( $require_subject_flag ) :
			?>
			<span class="required">*</span><?php endif; ?>
		</label>
		<input type="text" name="subject"<?php echo $require_subject_flag ? ' required' : ''; ?> maxlength="<?php echo esc_attr( $max_subject ); ?>" data-min-words="<?php echo esc_attr( $min_subject ); ?>">
		<div class="cin-field-meta">
		<span class="description"><?php printf( esc_html__( 'Max %d characters, min %d words', 'contactin' ), $max_subject, $min_subject ); ?></span>
		<span class="cin-char-counter" data-max="<?php echo esc_attr( $max_subject ); ?>">0/<?php echo esc_html( $max_subject ); ?></span>
		</div>
	</div>
	<?php endif; ?>

	<!-- Message -->
	<div class="cin-field">
		<label><?php esc_html_e( 'Message', 'contactin' ); ?> <span class="required">*</span></label>
		<textarea name="message" rows="5" required maxlength="<?php echo esc_attr( $max_message ); ?>" data-min-words="<?php echo esc_attr( $min_message ); ?>"></textarea>
		<div class="cin-field-meta">
		<span class="description"><?php printf( esc_html__( 'Max %d characters, min %d words', 'contactin' ), $max_message, $min_message ); ?></span>
		<span class="cin-char-counter" data-max="<?php echo esc_attr( $max_message ); ?>">0/<?php echo esc_html( $max_message ); ?></span>
		</div>
	</div>

	<!-- Consent -->
	<?php if ( $enable_consent ?? true ) : ?>
	<div class="cin-field cin-consent">
		<label>
		<input type="checkbox" name="consent" value="1" required>
		<?php echo esc_html( $consent_text ); ?>
		<span class="required">*</span>
		<?php if ( ! empty( $privacy_url ) ) : ?>
			<span class="cin-privacy-row">
			<a class="cin-privacy-link" href="<?php echo esc_url( $privacy_url ); ?>" target="_blank" rel="noopener noreferrer">
				<?php esc_html_e( 'Privacy Policy', 'contactin' ); ?>
			</a>
			</span>
		<?php endif; ?>
		</label>
	</div>
	<?php endif; ?>

	<!-- Submit -->
	<div class="cin-field cin-submit">
		<button type="submit" class="cin-submit-btn">
		<?php esc_html_e( 'Send Message', 'contactin' ); ?>
		<span class="cin-spinner"></span>
		</button>
	</div>
	</form>
	<div class="cin-response"></div>

	<!-- Error Modal Overlay -->
	<div id="cin-error-modal" class="cin-error-modal-overlay cin-hidden" role="alertdialog" aria-modal="true" aria-labelledby="cin-error-modal-title">
	<div class="cin-error-modal-backdrop"></div>
	<div class="cin-error-modal-content">
		<button class="cin-error-modal-close" aria-label="Close error message" title="Close (Esc)">
		<span class="cin-close-icon">×</span>
		</button>
		<div id="cin-error-modal-body"></div>
	</div>
	</div>
</div>

<?php if ( $enable_recaptcha ) : ?>
	<script src="https://www.google.com/recaptcha/api.js?render=<?php echo esc_attr( $site_key ); ?>"></script>
<?php endif; ?>