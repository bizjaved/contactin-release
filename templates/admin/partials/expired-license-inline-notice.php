<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
/**
 * Expired License Bottom Modal Notice
 *
 * Persistent bottom-docked modal-style renewal prompt.
 *
 * @package ContactIn/Admin
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$contactinbox_is_expired_license_state = \ContactInbox\Integration\FreemiusIntegration::is_expired_license_state();
$contactinbox_renew_url                = \ContactInbox\Integration\FreemiusIntegration::get_non_premium_primary_url();
$contactinbox_primary_label            = \ContactInbox\Integration\FreemiusIntegration::get_non_premium_primary_label();
$contactinbox_account_url              = \ContactInbox\Integration\FreemiusIntegration::get_account_url();
$contactinbox_mode                     = isset( $GLOBALS['cin_expired_license_mode'] ) ? (string) $GLOBALS['cin_expired_license_mode'] : 'both';
?>

<?php if ( 'bottom' !== $contactinbox_mode ) : ?>
<div id="cin-expired-license-top-notice" class="notice notice-warning is-dismissible cin-expired-license-top-notice">
	<p class="cin-expired-license-top-notice__text">
		<strong>
			<?php
			echo esc_html(
				$contactinbox_is_expired_license_state
					? __( 'License expired:', 'contactin' )
					: __( 'Free plan active:', 'contactin' )
			);
			?>
		</strong>
		<?php
		echo esc_html(
			$contactinbox_is_expired_license_state
				? __( 'Premium features are paused until you renew your ContactIn Pro license.', 'contactin' )
				: __( 'Premium features are paused on the Free plan. Upgrade to unlock them.', 'contactin' )
		);
		?>
	</p>
	<button type="button" class="notice-dismiss cin-expired-license-top-dismiss">
		<span class="screen-reader-text"><?php esc_html_e( 'Dismiss this notice.', 'contactin' ); ?></span>
	</button>
</div>
<?php endif; ?>

<?php if ( 'top' !== $contactinbox_mode ) : ?>
<div id="cin-expired-license-bottom-modal" class="cin-expired-license-bottom-modal">
	<div class="card expired-license-box cin-expired-license-box">
		<div class="cin-expired-license-box__layout">
			<span class="cin-expired-license-box__icon">🔐</span>
			<div class="cin-expired-license-box__content">
				<h3 class="cin-expired-license-box__title">
					<?php
					echo esc_html(
						$contactinbox_is_expired_license_state
							? __( 'Renew ContactIn Pro to restore premium features', 'contactin' )
							: __( 'Upgrade ContactIn Pro to unlock premium features', 'contactin' )
					);
					?>
				</h3>
				<p class="cin-expired-license-box__description">
					<?php
					echo esc_html(
						$contactinbox_is_expired_license_state
							? __( 'Your license is not currently active. Core inbox functionality stays available, and renewing will restore all premium tools immediately.', 'contactin' )
							: __( 'You are using the Free plan. Core inbox functionality stays available, and upgrading will unlock all premium tools immediately.', 'contactin' )
					);
					?>
				</p>

				<div class="cin-expired-license-box__features">
					<strong class="cin-expired-license-box__features-title">
						<?php
						echo esc_html(
							$contactinbox_is_expired_license_state
								? __( 'Unavailable until renewal:', 'contactin' )
								: __( 'Unavailable on Free plan:', 'contactin' )
						);
						?>
					</strong>
					<ul class="cin-expired-license-box__features-list">
						<li><?php esc_html_e( 'CRM integration and CRM logs', 'contactin' ); ?></li>
						<li><?php esc_html_e( 'REST API integration and REST API logs', 'contactin' ); ?></li>
						<li><?php esc_html_e( 'GDPR deletion automation', 'contactin' ); ?></li>
						<li><?php esc_html_e( 'Attachment uploads and premium CSV exports', 'contactin' ); ?></li>
						<li><?php esc_html_e( 'AI classifier automation and learning features', 'contactin' ); ?></li>
					</ul>
				</div>

				<a href="<?php echo esc_url( $contactinbox_renew_url ); ?>" class="button button-primary cin-expired-license-box__renew-btn">
					<span class="cin-expired-license-box__btn-icon">🔄</span>
					<?php echo esc_html( $contactinbox_primary_label ); ?>
				</a>

				<a href="<?php echo esc_url( $contactinbox_account_url ); ?>" class="button button-secondary cin-expired-license-box__account-btn">
					<span class="cin-expired-license-box__btn-icon">👤</span>
					<?php esc_html_e( 'Open Account', 'contactin' ); ?>
				</a>
			</div>
		</div>
	</div>
</div>
<?php endif; ?>

<?php if ( 'bottom' !== $contactinbox_mode ) : ?>
<script>
	(function($) {
		'use strict';

		$(document).on('click', '#cin-expired-license-top-notice .cin-expired-license-top-dismiss', function() {
			$('#cin-expired-license-top-notice').remove();
		});
	})(jQuery);
</script>
<?php endif; ?>
