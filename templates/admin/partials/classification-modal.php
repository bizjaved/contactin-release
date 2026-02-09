<?php
/**
 * Change Classification Modal
 *
 * Modal for changing message classification/intent
 * Uses global contactin-modal CSS classes from admin-inbox.min.css
 * 
 * @package ContactInbox\Admin
 */

use ContactInbox\Core\Config;

if ( ! defined( 'ABSPATH' ) ) exit;

$nonce = wp_create_nonce( Config::INBOX_NONCE_ACTION );
$settings = \ContactInbox\Core\Settings::get_settings();
?>

<?php if ( !empty($settings['intent_enable']) ) : ?>
<!-- Classification Modal - Uses global contactin-modal classes -->
<div id="cin-classification-modal" class="contactin-modal">
    <div class="contactin-modal-backdrop"></div>
    <div class="contactin-modal-content">
        <!-- Header -->
        <div class="contactin-modal-header">
            <div style="display: flex; justify-content: space-between; align-items: center; width: 100%;">
                <h2 class="cin-modal-title" style="margin: 0; flex: 1; font-size: 16px;"><?php esc_html_e( 'Change Classification', Config::TEXTDOMAIN ); ?></h2>
                <button type="button" class="cin-modal-close" aria-label="<?php esc_attr_e( 'Close', Config::TEXTDOMAIN ); ?>" style="background: none; border: none; cursor: pointer; padding: 0; display: flex; align-items: center; justify-content: center;">
                    <span class="dashicons dashicons-no" style="font-size: 20px; width: 20px; height: 20px;"></span>
                </button>
            </div>
        </div>

        <!-- Body -->
        <div class="contactin-modal-body">
            <input type="hidden" id="cin-classification-message-id" value="">
            <input type="hidden" id="cin-classification-nonce" value="<?php echo esc_attr( $nonce ); ?>">

            <div class="cin-classification-grid">
                <?php
                $categories = \ContactInbox\Core\IntentClassifier::get_categories();
                foreach ( $categories as $cat_key => $cat_label ) :
                    // Skip spam category - use spam tab for spam handling
                    if ( $cat_key === 'spam' ) {
                        continue;
                    }
                    $cat_color = \ContactInbox\Core\IntentClassifier::get_category_color( $cat_key );
                ?>
                    <button type="button" class="cin-classification-btn cin-intent-<?php echo esc_attr( $cat_color ); ?>" 
                            data-category="<?php echo esc_attr( $cat_key ); ?>"
                            data-color="<?php echo esc_attr( $cat_color ); ?>"
                            title="<?php echo esc_attr( $cat_label ); ?>">
                        <span class="dashicons dashicons-tag"></span>
                        <span class="cin-category-label"><?php echo esc_html( $cat_label ); ?></span>
                    </button>
                <?php endforeach; ?>
            </div>

            <p class="cin-classification-hint">
                <small><?php esc_html_e( 'Select a classification to update this message.', Config::TEXTDOMAIN ); ?></small>
            </p>
        </div>

        <!-- Footer -->
        <div class="contactin-modal-footer">
            <div class="cin-footer-actions">
                <button type="button" class="button button-secondary cin-modal-cancel">
                    <?php esc_html_e( 'Cancel', Config::TEXTDOMAIN ); ?>
                </button>
            </div>
        </div>
    </div>
</div>

<style>
/* Classification Modal Grid Layout */
.cin-classification-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(120px, 1fr));
    gap: 12px;
    margin-bottom: 20px;
}

#cin-classification-modal .contactin-modal-header {
    padding: 16px 20px;
}

#cin-classification-modal .contactin-modal-body {
    padding: 24px 20px;
    flex: 1 1 auto;
    overflow-y: auto;
}

#cin-classification-modal .contactin-modal-footer {
    padding: 16px 20px;
}

.cin-classification-btn {
    padding: 12px 16px;
    border: 2px solid #c3c4c7;
    border-radius: 4px;
    background: #f6f7f7;
    color: #2c3338;
    font-weight: 500;
    cursor: pointer;
    transition: all 0.2s ease;
    text-align: center;
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 6px;
    font-size: 13px;
}

.cin-classification-btn .dashicons {
    font-size: 20px;
    width: 20px;
    height: 20px;
}

.cin-classification-btn:hover:not(.cin-current) {
    border-color: #8c8f94;
    background: #fff;
    transform: translateY(-2px);
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
}

/* Current Classification - Light blue background */
.cin-classification-btn.cin-current {
    border-color: #2271b1;
    background: #e7f3ff;
    color: #2271b1;
    font-weight: 600;
    box-shadow: 0 2px 8px rgba(34, 113, 177, 0.2);
    transform: scale(1.02);
}

.cin-classification-btn.cin-current .dashicons {
    color: #2271b1;
}

/* Selected (Being Saved) - Dark blue background */
.cin-classification-btn.cin-selected {
    border-color: #135e96;
    background: #135e96;
    color: #fff;
    font-weight: 600;
    transform: scale(1.02);
}

.cin-classification-btn.cin-selected .dashicons {
    color: #fff;
}

/* Processing state - fade other buttons */
#cin-classification-modal.processing .cin-classification-btn:not(.cin-selected) {
    opacity: 0.5;
    cursor: not-allowed;
    pointer-events: none;
}

#cin-classification-modal.processing .cin-classification-btn.cin-selected {
    pointer-events: none;
}

.cin-category-label {
    display: block;
    font-size: 13px;
    line-height: 1.4;
    word-break: break-word;
}

.cin-classification-hint {
    color: #646970;
    margin: 10px 0 0 0;
    text-align: center;
    font-size: 12px;
}

/* Override display for global modal classes */
#cin-classification-modal {
    display: none !important;
    visibility: hidden !important;
    pointer-events: none !important;
    position: fixed;
    inset: 0;
    z-index: 10005;
    justify-content: center;
    align-items: flex-start;
    padding: 40px 20px 20px;
}

#cin-classification-modal.is-active {
    display: flex !important;
    visibility: visible !important;
    pointer-events: auto !important;
}

.cin-modal-title {
    font-size: 16px;
    font-weight: 600;
    color: #1d2327;
}

.cin-modal-close {
    color: #646970;
    transition: color 0.15s ease;
}

.cin-modal-close:hover {
    color: #1d2327;
}

.cin-footer-actions {
    width: 100%;
    display: flex;
    justify-content: flex-end;
    gap: 8px;
}
</style>
<?php endif; ?>
