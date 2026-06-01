<?php
/**
 * Intent Settings Trait
 *
 * Handles intent classification settings display and AJAX handlers
 *
 * @package ContactIn\Admin\Traits
 */

declare(strict_types=1);

namespace ContactInbox\Admin\Traits;

use ContactInbox\Core\Config;
use ContactInbox\Core\IntentClassifier;
use ContactInbox\Core\BusinessPatterns;

// phpcs:disable WordPress.WP.I18n.NonSingularStringLiteralDomain, WordPress.Security.ValidatedSanitizedInput.MissingUnslash

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

trait IntentSettingsTrait {

	/**
	 * Render intent classification settings section
	 *
	 * @param array $settings Current settings
	 * @return void
	 */
	public function render_intent_settings( array $settings ): void {
		$intent_enabled = ! empty( $settings['intent_enable'] );
		$business_type  = get_option( 'contactin_business_type', 'generic' );
		$business_types = BusinessPatterns::get_business_types();

		?>
		<table class="form-table">
			<tr>
				<th scope="row">
					<label for="intent_enable">
						<?php esc_html_e( 'Enable Intent Classification', 'contactin' ); ?>
					</label>
				</th>
				<td>
					<label class="contactin-toggle-switch">
						<input type="checkbox" 
								id="intent_enable" 
								name="intent_enable" 
								value="1" 
								data-search="enable intent classification ai automatic"
								<?php checked( $intent_enabled ); ?>>
						<span class="contactin-toggle-slider"></span>
					</label>
					<p class="description">
						<?php esc_html_e( 'When enabled, messages will be automatically classified into categories like Sales, Support, Feedback, Complaints, Questions, and Spam.', 'contactin' ); ?>
					</p>
					<p class="description" style="margin-top: 8px; padding: 10px; background: #f5f5f5; border-radius: 3px; border-left: 3px solid #2271b1;">
						<strong><?php esc_html_e( 'How it works:', 'contactin' ); ?></strong><br>
						<?php esc_html_e( 'Messages are automatically classified into Sales, Support, Feedback, Complaints, Questions, or Spam. This version does not learn from your manual inbox category changes; that learning behavior is available in ContactIn Pro.', 'contactin' ); ?>
					</p>
				</td>
			</tr>

			<tr>
				<th scope="row">
					<label for="business_type">
						<?php esc_html_e( 'Business Type', 'contactin' ); ?>
					</label>
				</th>
				<td>
					<select id="business_type" 
							name="business_type" 
							class="regular-text"
							data-search="business type ai classifier industry category"
							<?php disabled( ! $intent_enabled ); ?>>
						<?php foreach ( $business_types as $type => $label ) : ?>
							<option value="<?php echo esc_attr( $type ); ?>" 
									<?php selected( $business_type, $type ); ?>>
								<?php echo esc_html( $label ); ?>
							</option>
						<?php endforeach; ?>
					</select>
					<p class="description">
						<?php esc_html_e( 'Select your industry type to use industry-specific keywords for better classification accuracy. Keywords are updated with each software release.', 'contactin' ); ?>
					</p>
					<p class="description" id="business_type_note" style="margin-top: 8px; font-style: italic; color: #2271b1;">
						<span id="business_change_note"></span>
					</p>
				</td>
			</tr>
		</table>

		<?php
		static $intent_settings_script_enqueued = false;
		if ( ! $intent_settings_script_enqueued ) {
			$intent_settings_script_enqueued = true;
			$business_type_updated_label     = wp_json_encode( esc_html__( 'Business type updated to', 'contactin' ) );
			wp_add_inline_script(
				'contactin-admin-settings',
				'(function($){$(\'#intent_enable\').on(\'change\',function(){var enabled=this.checked;$(\'#business_type\').prop(\'disabled\',!enabled);});$(\'#business_type\').on(\'change\',function(){var label=$(this).find(\'option:selected\').text();$(\'#business_change_note\').html(\'\\u2713 \'+ ' . $business_type_updated_label . ' + \": <strong>\" + label + \"</strong>\");});})(jQuery);',
				'after'
			);
		}
		?>
		<?php
	}

	/**
	 * AJAX handler: Manually reclassify a message
	 */
	public function ajax_reclassify_message(): void {
		check_ajax_referer( Config::INBOX_NONCE_ACTION, 'nonce' );

		if ( ! current_user_can( Config::CAPABILITY ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'contactin' ) ) );
		}

		$message_id = isset( $_POST['message_id'] ) ? absint( wp_unslash( $_POST['message_id'] ) ) : 0;
		$category   = isset( $_POST['category'] ) ? sanitize_text_field( wp_unslash( $_POST['category'] ) ) : '';

		if ( ! $message_id || ! $category ) {
			wp_send_json_error( array( 'message' => __( 'Invalid parameters.', 'contactin' ) ) );
		}

		$classifier = IntentClassifier::instance();
		$result     = $classifier->reclassify( $message_id, $category );

		if ( $result ) {
			wp_send_json_success(
				array(
					'message'          => __( 'Message reclassified successfully.', 'contactin' ),
					'category'         => $category,
					'label'            => IntentClassifier::get_category_label( $category ),
					'learning_enabled' => false,
				)
			);
		} else {
			wp_send_json_error( array( 'message' => __( 'Failed to reclassify message.', 'contactin' ) ) );
		}
	}
}


