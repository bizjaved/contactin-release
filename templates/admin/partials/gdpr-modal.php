<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use ContactInbox\Core\Config;

// phpcs:disable WordPress.WP.I18n.NonSingularStringLiteralDomain
?>

<!-- GDPR Modal -->
<div id="cin-gdpr-modal" class="cin-gdpr-success">
	<div class="cin-gdpr-backdrop"></div>
	<div class="cin-gdpr-wrap">
		<h3><?php esc_html_e( 'GDPR Deletion Link', 'contactin' ); ?></h3>

		<!-- Email recipient info -->
		<p class="gdpr-modal-info">
			<?php esc_html_e( 'Link generated for:', 'contactin' ); ?>
			<strong class="gdpr-email"></strong><br>
			<?php esc_html_e( 'Expires in', 'contactin' ); ?>
			<span class="gdpr-expiry"></span>
		</p>

		<!-- Link field -->
		<input type="text" id="gdpr-link-input" value="" readonly onclick="this.select()">

		<!-- Action buttons -->
		<div class="cin-gdpr-actions">
			<button type="button" id="gdpr-copy-btn" class="button button-secondary contactin-view">
				<?php esc_html_e( 'Copy Link', 'contactin' ); ?>
			</button>
			<button type="button" id="gdpr-send-email-btn" class="button button-primary" data-message-id="" data-link="">
				<span class="btn-text"><?php esc_html_e( 'Send Email', 'contactin' ); ?></span>
				<span class="btn-spinner" style="display:none;">
					<span class="spinner is-active" style="float:none;margin:0;"></span>
				</span>
			</button>
			<!-- Status/guidance always below buttons -->
			<div class="gdpr-guidance">
				<span id="gdpr-status"></span>
			</div>
		</div>
	</div>
</div>
