<?php
/**
 * Intent Settings Trait
 *
 * Handles intent classification settings display and AJAX handlers
 *
 * @package ContactInbox\Admin\Traits
 */

declare(strict_types=1);

namespace ContactInbox\Admin\Traits;

use ContactInbox\Core\Config;
use ContactInbox\Core\IntentClassifier;

if (!defined('ABSPATH')) {
    exit;
}

trait IntentSettingsTrait {

    /**
     * Render intent classification settings section
     *
     * @param array $settings Current settings
     * @return void
     */
    public function render_intent_settings(array $settings): void {
        $intent_enabled = !empty($settings['intent_enable']);
        
        ?>
        <div class="contactin-settings-section">
            <h3><?php esc_html_e('Intent Classification', Config::TEXTDOMAIN); ?></h3>
            <p class="description">
                <?php esc_html_e('Automatically categorize incoming messages based on their content (e.g., Sales, Support, Feedback).', Config::TEXTDOMAIN); ?>
            </p>

            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label for="intent_enable">
                            <?php esc_html_e('Enable Intent Classification', Config::TEXTDOMAIN); ?>
                        </label>
                    </th>
                    <td>
                        <label class="contactin-toggle-switch">
                            <input type="checkbox" 
                                   id="intent_enable" 
                                   name="intent_enable" 
                                   value="1" 
                                   <?php checked($intent_enabled); ?>>
                            <span class="contactin-toggle-slider"></span>
                        </label>
                        <p class="description">
                            <?php esc_html_e('When enabled, messages will be automatically classified into categories like Sales, Support, Feedback, etc. View and manage classification statistics on the Maintenance page.', Config::TEXTDOMAIN); ?>
                        </p>
                    </td>
                </tr>
            </table>
        </div>
        <?php
    }

    /**
     * AJAX handler: Manually reclassify a message
     */
    public function ajax_reclassify_message(): void {
        check_ajax_referer(Config::INBOX_NONCE_ACTION, 'nonce');
        
        if (!current_user_can(Config::CAPABILITY)) {
            wp_send_json_error(['message' => __('Permission denied.', Config::TEXTDOMAIN)]);
        }

        $message_id = isset($_POST['message_id']) ? (int) $_POST['message_id'] : 0;
        $category = isset($_POST['category']) ? sanitize_text_field($_POST['category']) : '';

        if (!$message_id || !$category) {
            wp_send_json_error(['message' => __('Invalid parameters.', Config::TEXTDOMAIN)]);
        }

        $classifier = IntentClassifier::instance();
        $result = $classifier->reclassify($message_id, $category);

        if ($result) {
            wp_send_json_success([
                'message' => __('Message reclassified successfully.', Config::TEXTDOMAIN),
                'category' => $category,
                'label' => IntentClassifier::get_category_label($category),
            ]);
        } else {
            wp_send_json_error(['message' => __('Failed to reclassify message.', Config::TEXTDOMAIN)]);
        }
    }
}
