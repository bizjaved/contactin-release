<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
use ContactInbox\Core\Config;

extract( $args );
$settings         = get_option( Config::OPTION_SETTINGS, [] );
$site_key         = $settings['recaptcha_site_key'] ?? '';
$enable_recaptcha = ! empty( $settings['recaptcha_enable'] ) && ! empty( $site_key );
$form_id          = $form_id ?? 'default';
$consent_text     = $consent_text
  ?? ( $settings['consent_text'] ?? __( 'I consent to my data being used to respond to this message.', Config::TEXTDOMAIN ) );
$privacy_url      = $privacy_url
  ?? ( $settings['privacy_url'] ?? get_privacy_policy_url() );

// Random honeypot field name (unique per page load)
$honeypot_name = 'ci_hp_' . wp_generate_password( 12, false );

// Pull limits from settings
$max_name     = absint( $settings['max_name_chars'] ?? 100 );
$max_subject  = absint( $settings['max_subject_chars'] ?? 150 );
$max_message  = absint( $settings['max_message_chars'] ?? 2000 );
$min_name     = absint( $settings['min_name_words'] ?? 2 );
$min_subject  = absint( $settings['min_subject_words'] ?? 3 );
$min_message  = absint( $settings['min_message_words'] ?? 5 );
$file_types   = $settings['allowed_file_types'] ?? 'jpg,png,gif,pdf,doc,docx';
$file_size_mb = absint( $settings['max_file_size'] ?? 2 );

// Build accept attribute from allowed types
$accept_attr = implode( ',', array_map( function( $ext ) {
    return '.' . $ext;
}, array_map( 'trim', explode( ',', strtolower( $file_types ) ) ) ) );
?>
<div class="cin-form-wrapper" data-form-id="<?php echo esc_attr( $form_id ); ?>">
  <form id="contactin-form" class="cin-contact-form" enctype="multipart/form-data">
    <input type="hidden" name="form_id" value="<?php echo esc_attr( $form_id ); ?>">

    <!-- Honeypot -->
    <div class="cin-honeypot" aria-hidden="true">
      <input type="text" name="<?php echo esc_attr( $honeypot_name ); ?>" tabindex="-1" autocomplete="off">
    </div>

    <!-- Salutation -->
    <?php if ( ! empty( $settings['form_enable_salutation'] ) ) : ?>
    <div class="cin-field cin-salutation">
      <label><?php esc_html_e( 'Salutation', Config::TEXTDOMAIN ); ?></label>
      <select name="salutation">
        <option value=""><?php esc_html_e( 'Select', Config::TEXTDOMAIN ); ?></option>
        <option value="Mr"><?php esc_html_e( 'Mr', Config::TEXTDOMAIN ); ?></option>
        <option value="Ms"><?php esc_html_e( 'Ms', Config::TEXTDOMAIN ); ?></option>
        <option value="Mrs"><?php esc_html_e( 'Mrs', Config::TEXTDOMAIN ); ?></option>
        <option value="Dr"><?php esc_html_e( 'Dr', Config::TEXTDOMAIN ); ?></option>
        <option value="Mx"><?php esc_html_e( 'Mx', Config::TEXTDOMAIN ); ?></option>
      </select>
    </div>
    <?php endif; ?>

    <!-- Name -->
    <div class="cin-field">
      <label><?php esc_html_e( 'Name', Config::TEXTDOMAIN ); ?> <span class="required">*</span></label>
      <input type="text" name="name" required maxlength="<?php echo esc_attr( $max_name ); ?>" data-min-words="<?php echo esc_attr( $min_name ); ?>">
      <div class="cin-field-meta">
        <span class="description"><?php printf( esc_html__( 'Max %d characters, min %d words', Config::TEXTDOMAIN ), $max_name, $min_name ); ?></span>
        <span class="cin-char-counter" data-max="<?php echo esc_attr( $max_name ); ?>">0/<?php echo esc_html( $max_name ); ?></span>
      </div>
    </div>

    <!-- Email -->
    <div class="cin-field">
      <label><?php esc_html_e( 'Email', Config::TEXTDOMAIN ); ?> <span class="required">*</span></label>
      <input type="email" name="email" required>
    </div>

    <!-- Phone -->
    <div class="cin-field">
      <label><?php esc_html_e( 'Phone', Config::TEXTDOMAIN ); ?></label>
      <input type="tel" name="phone">
    </div>

    <!-- Subject -->
    <?php if ( ! empty( $settings['form_enable_subject'] ) ) : ?>
    <div class="cin-field">
      <label><?php esc_html_e( 'Subject', Config::TEXTDOMAIN ); ?> <span class="required">*</span></label>
      <input type="text" name="subject" required maxlength="<?php echo esc_attr( $max_subject ); ?>" data-min-words="<?php echo esc_attr( $min_subject ); ?>">
      <div class="cin-field-meta">
        <span class="description"><?php printf( esc_html__( 'Max %d characters, min %d words', Config::TEXTDOMAIN ), $max_subject, $min_subject ); ?></span>
        <span class="cin-char-counter" data-max="<?php echo esc_attr( $max_subject ); ?>">0/<?php echo esc_html( $max_subject ); ?></span>
      </div>
    </div>
    <?php endif; ?>

    <!-- Message -->
    <div class="cin-field">
      <label><?php esc_html_e( 'Message', Config::TEXTDOMAIN ); ?> <span class="required">*</span></label>
      <textarea name="message" rows="5" required maxlength="<?php echo esc_attr( $max_message ); ?>" data-min-words="<?php echo esc_attr( $min_message ); ?>"></textarea>
      <div class="cin-field-meta">
        <span class="description"><?php printf( esc_html__( 'Max %d characters, min %d words', Config::TEXTDOMAIN ), $max_message, $min_message ); ?></span>
        <span class="cin-char-counter" data-max="<?php echo esc_attr( $max_message ); ?>">0/<?php echo esc_html( $max_message ); ?></span>
      </div>
    </div>

    <!-- Consent -->
    <div class="cin-field cin-consent">
      <label>
        <input type="checkbox" name="consent" value="1" required>
        <?php echo esc_html( $consent_text ); ?>
        <span class="required">*</span>
        <?php if ( ! empty( $privacy_url ) ) : ?>
          <span class="cin-privacy-row">
            <a class="cin-privacy-link" href="<?php echo esc_url( $privacy_url ); ?>" target="_blank" rel="noopener noreferrer">
              <?php esc_html_e( 'Privacy Policy', Config::TEXTDOMAIN ); ?>
            </a>
          </span>
        <?php endif; ?>
      </label>
    </div>

    <!-- Submit -->
    <div class="cin-field cin-submit">
      <button type="submit" class="cin-submit-btn">
        <?php esc_html_e( 'Send Message', Config::TEXTDOMAIN ); ?>
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