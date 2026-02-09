<?php
/**
 * Contact Edit Modal (Reusable)
 * 
 * Used on both Contact Detail page and Contacts list page.
 * Provides inline validation, live preview, and rich editing experience.
 */

use ContactInbox\Core\Config;

if (!defined('ABSPATH')) exit;
?>

<!-- Contact Edit Modal Drawer -->
<div id="cin-contact-edit-modal" class="cin-modal cin-modal-drawer">
    <div class="cin-modal-overlay"></div>
    <div class="cin-modal-container cin-modal-drawer-container">
        
        <!-- Modal Header -->
        <div class="cin-modal-header cin-modal-drawer-header">
            <div class="cin-modal-title-section">
                <h2 class="cin-modal-title"><?php esc_html_e('Edit Contact', Config::TEXTDOMAIN); ?></h2>
                <p class="cin-modal-subtitle cin-edit-contact-subtitle"></p>
            </div>
            <button type="button" class="cin-modal-close cin-btn-icon" aria-label="<?php esc_attr_e('Close', Config::TEXTDOMAIN); ?>">
                <span class="dashicons dashicons-no-alt"></span>
            </button>
        </div>

        <!-- Modal Body with Tabs -->
        <div class="cin-modal-body cin-modal-drawer-body">
            <form id="cin-edit-contact-form" class="cin-edit-contact-form">
                <input type="hidden" id="cin-edit-contact-id" name="contact_id" />
                <input type="hidden" name="nonce" id="cin-edit-contact-nonce" />

                <!-- MAIN TAB: Basic Information -->
                <div class="cin-form-section">
                    <div class="cin-form-section-header">
                        <h3><?php esc_html_e('Basic Information', Config::TEXTDOMAIN); ?></h3>
                    </div>
                    
                    <div class="cin-form-group">
                        <label for="cin-edit-salutation" class="cin-form-label">
                            <?php esc_html_e('Salutation', Config::TEXTDOMAIN); ?>
                            <span class="cin-form-hint"><?php esc_html_e('Optional', Config::TEXTDOMAIN); ?></span>
                        </label>
                        <input 
                            type="text" 
                            id="cin-edit-salutation" 
                            name="salutation" 
                            class="cin-form-input" 
                            placeholder="<?php esc_attr_e('e.g., Mr., Mrs., Dr.', Config::TEXTDOMAIN); ?>"
                            maxlength="50"
                        />
                        <div class="cin-form-error" role="alert"></div>
                    </div>

                    <div class="cin-form-group">
                        <label for="cin-edit-name" class="cin-form-label">
                            <?php esc_html_e('Full Name', Config::TEXTDOMAIN); ?>
                            <span class="cin-required">*</span>
                        </label>
                        <input 
                            type="text" 
                            id="cin-edit-name" 
                            name="name" 
                            class="cin-form-input" 
                            placeholder="<?php esc_attr_e('Full name', Config::TEXTDOMAIN); ?>"
                            maxlength="255"
                            required
                        />
                        <div class="cin-form-error" role="alert"></div>
                    </div>

                    <div class="cin-form-group">
                        <label for="cin-edit-email" class="cin-form-label">
                            <?php esc_html_e('Email Address', Config::TEXTDOMAIN); ?>
                            <span class="cin-form-hint"><?php esc_html_e('Optional', Config::TEXTDOMAIN); ?></span>
                        </label>
                        <input 
                            type="email" 
                            id="cin-edit-email" 
                            name="email" 
                            class="cin-form-input" 
                            placeholder="<?php esc_attr_e('email@example.com', Config::TEXTDOMAIN); ?>"
                            maxlength="255"
                        />
                        <p class="cin-form-help-text"><?php esc_html_e('Each contact must have a unique email address.', Config::TEXTDOMAIN); ?></p>
                        <div class="cin-form-error" role="alert"></div>
                    </div>
                </div>

                <!-- Phone Numbers Section -->
                <div class="cin-form-section">
                    <div class="cin-form-section-header">
                        <h3><?php esc_html_e('Phone Numbers', Config::TEXTDOMAIN); ?></h3>
                        <p class="cin-section-description"><?php esc_html_e('Add or update contact phone numbers', Config::TEXTDOMAIN); ?></p>
                    </div>

                    <div class="cin-form-group">
                        <label for="cin-edit-primary-phone" class="cin-form-label">
                            <?php esc_html_e('Primary Phone', Config::TEXTDOMAIN); ?>
                        </label>
                        <div class="cin-form-input-group">
                            <input 
                                type="tel" 
                                id="cin-edit-primary-phone" 
                                name="primary_phone" 
                                class="cin-form-input cin-phone-input" 
                                placeholder="<?php esc_attr_e('(555) 000-0000', Config::TEXTDOMAIN); ?>"
                                maxlength="30"
                                data-phone-type="primary"
                            />
                            <span class="cin-phone-preview" data-for="primary-phone"></span>
                        </div>
                        <div class="cin-form-error" role="alert"></div>
                    </div>

                    <div class="cin-form-group">
                        <label for="cin-edit-mobile-phone" class="cin-form-label">
                            <?php esc_html_e('Mobile Phone', Config::TEXTDOMAIN); ?>
                        </label>
                        <div class="cin-form-input-group">
                            <input 
                                type="tel" 
                                id="cin-edit-mobile-phone" 
                                name="mobile_phone" 
                                class="cin-form-input cin-phone-input" 
                                placeholder="<?php esc_attr_e('(555) 000-0000', Config::TEXTDOMAIN); ?>"
                                maxlength="30"
                                data-phone-type="mobile"
                            />
                            <span class="cin-phone-preview" data-for="mobile-phone"></span>
                        </div>
                        <div class="cin-form-error" role="alert"></div>
                    </div>

                    <div class="cin-form-group">
                        <label for="cin-edit-home-phone" class="cin-form-label">
                            <?php esc_html_e('Home Phone', Config::TEXTDOMAIN); ?>
                        </label>
                        <div class="cin-form-input-group">
                            <input 
                                type="tel" 
                                id="cin-edit-home-phone" 
                                name="home_phone" 
                                class="cin-form-input cin-phone-input" 
                                placeholder="<?php esc_attr_e('(555) 000-0000', Config::TEXTDOMAIN); ?>"
                                maxlength="30"
                                data-phone-type="home"
                            />
                            <span class="cin-phone-preview" data-for="home-phone"></span>
                        </div>
                        <div class="cin-form-error" role="alert"></div>
                    </div>

                    <div class="cin-form-group">
                        <label for="cin-edit-other-phone" class="cin-form-label">
                            <?php esc_html_e('Other Phone', Config::TEXTDOMAIN); ?>
                        </label>
                        <div class="cin-form-input-group">
                            <input 
                                type="tel" 
                                id="cin-edit-other-phone" 
                                name="other_phone" 
                                class="cin-form-input cin-phone-input" 
                                placeholder="<?php esc_attr_e('(555) 000-0000', Config::TEXTDOMAIN); ?>"
                                maxlength="30"
                                data-phone-type="other"
                            />
                            <span class="cin-phone-preview" data-for="other-phone"></span>
                        </div>
                        <div class="cin-form-error" role="alert"></div>
                    </div>
                </div>

                <!-- Dirty State Indicator -->
                <div class="cin-form-dirty-state" style="display: none;">
                    <p class="cin-unsaved-changes-notice">
                        <span class="dashicons dashicons-info"></span>
                        <?php esc_html_e('You have unsaved changes', Config::TEXTDOMAIN); ?>
                    </p>
                </div>
            </form>
        </div>

        <!-- Modal Footer -->
        <div class="cin-modal-footer cin-modal-drawer-footer">
            <div class="cin-footer-actions">
                <button type="button" class="cin-btn cin-btn-secondary cin-cancel-edit-btn">
                    <?php esc_html_e('Cancel', Config::TEXTDOMAIN); ?>
                </button>
                <button type="button" class="cin-btn cin-btn-primary cin-save-contact-btn" disabled>
                    <span class="cin-btn-icon">
                        <span class="dashicons dashicons-yes"></span>
                    </span>
                    <span class="cin-btn-text"><?php esc_html_e('Save Changes', Config::TEXTDOMAIN); ?></span>
                </button>
            </div>
        </div>

        <!-- Loading/Status Overlay -->
        <div class="cin-modal-loading-overlay" style="display: none !important; visibility: hidden !important; pointer-events: none !important;">
            <div class="cin-spinner"></div>
            <p><?php esc_html_e('Saving...', Config::TEXTDOMAIN); ?></p>
        </div>
    </div>
</div>
