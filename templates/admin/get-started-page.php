<?php
/**
 * Template: Get Started / Onboarding Page
 */

use ContactInbox\Core\Config;

if (!defined('ABSPATH')) exit;
?>
<div class="wrap cin-get-started-wrap">
    <div class="cin-get-started-header">
        <div class="cin-gs-header-content">
            <h1><?php esc_html_e('Welcome to Contact Inbox Pro! 🎉', Config::TEXTDOMAIN); ?></h1>
            <p class="cin-gs-subtitle"><?php esc_html_e('Let\'s get you set up in just 2 simple steps', Config::TEXTDOMAIN); ?></p>
        </div>
    </div>

    <div class="cin-get-started-container">
        
        <!-- Card 1: Setup Contact Form -->
        <div class="cin-gs-card">
            <div class="cin-gs-card-header">
                <div class="cin-gs-card-icon cin-gs-icon-form">
                    <span class="dashicons dashicons-feedback"></span>
                </div>
                <div>
                    <h2><?php esc_html_e('1. Add Contact Form to a Page', Config::TEXTDOMAIN); ?></h2>
                    <p class="cin-gs-card-desc"><?php esc_html_e('Display the contact form on any page or post using a shortcode', Config::TEXTDOMAIN); ?></p>
                </div>
            </div>
            
            <div class="cin-gs-card-content">
                <div class="cin-gs-step">
                    <div class="cin-gs-step-number">1</div>
                    <div class="cin-gs-step-content">
                        <h3><?php esc_html_e('Create or edit a page', Config::TEXTDOMAIN); ?></h3>
                        <p><?php esc_html_e('Go to Pages → Add New or edit an existing page where you want the contact form', Config::TEXTDOMAIN); ?></p>
                    </div>
                </div>

                <div class="cin-gs-step">
                    <div class="cin-gs-step-number">2</div>
                    <div class="cin-gs-step-content">
                        <h3><?php esc_html_e('Add the shortcode', Config::TEXTDOMAIN); ?></h3>
                        <p><?php esc_html_e('Paste this shortcode where you want the form to appear:', Config::TEXTDOMAIN); ?></p>
                        <div class="cin-gs-shortcode-box">
                            <code>[contact_inbox_form]</code>
                            <button type="button" class="cin-gs-copy-btn" data-clipboard="[contact_inbox_form]">
                                <span class="dashicons dashicons-admin-page"></span>
                                <span class="cin-copy-text"><?php esc_html_e('Copy', Config::TEXTDOMAIN); ?></span>
                            </button>
                        </div>
                    </div>
                </div>

                <div class="cin-gs-step">
                    <div class="cin-gs-step-number">3</div>
                    <div class="cin-gs-step-content">
                        <h3><?php esc_html_e('Publish and view', Config::TEXTDOMAIN); ?></h3>
                        <p><?php esc_html_e('Save/publish your page and visit it to see your contact form in action!', Config::TEXTDOMAIN); ?></p>
                    </div>
                </div>

                <div class="cin-gs-card-footer">
                    <a href="<?php echo esc_url(admin_url('post-new.php?post_type=page')); ?>" class="cin-gs-btn cin-gs-btn-primary">
                        <span class="dashicons dashicons-plus-alt"></span>
                        <?php esc_html_e('Create New Page', Config::TEXTDOMAIN); ?>
                    </a>
                </div>
            </div>
        </div>

        <!-- Card 2: Plugin Settings -->
        <div class="cin-gs-card">
            <div class="cin-gs-card-header">
                <div class="cin-gs-card-icon cin-gs-icon-settings">
                    <span class="dashicons dashicons-admin-settings"></span>
                </div>
                <div>
                    <h2><?php esc_html_e('2. Configure Plugin Settings', Config::TEXTDOMAIN); ?></h2>
                    <p class="cin-gs-card-desc"><?php esc_html_e('Customize your contact form and email notifications', Config::TEXTDOMAIN); ?></p>
                </div>
            </div>
            
            <div class="cin-gs-card-content">
                <div class="cin-gs-feature-list">
                    <div class="cin-gs-feature">
                        <span class="dashicons dashicons-yes-alt"></span>
                        <div>
                            <strong><?php esc_html_e('Email Configuration', Config::TEXTDOMAIN); ?></strong>
                            <p><?php esc_html_e('Set up SMTP server for reliable email delivery', Config::TEXTDOMAIN); ?></p>
                        </div>
                    </div>

                    <div class="cin-gs-feature">
                        <span class="dashicons dashicons-yes-alt"></span>
                        <div>
                            <strong><?php esc_html_e('Form Customization', Config::TEXTDOMAIN); ?></strong>
                            <p><?php esc_html_e('Enable subject field, salutation, and file attachments', Config::TEXTDOMAIN); ?></p>
                        </div>
                    </div>

                    <div class="cin-gs-feature">
                        <span class="dashicons dashicons-yes-alt"></span>
                        <div>
                            <strong><?php esc_html_e('Spam Protection', Config::TEXTDOMAIN); ?></strong>
                            <p><?php esc_html_e('Configure reCAPTCHA v3 to prevent spam submissions', Config::TEXTDOMAIN); ?></p>
                        </div>
                    </div>

                    <div class="cin-gs-feature">
                        <span class="dashicons dashicons-yes-alt"></span>
                        <div>
                            <strong><?php esc_html_e('Privacy & GDPR', Config::TEXTDOMAIN); ?></strong>
                            <p><?php esc_html_e('Customize consent text and privacy policy link', Config::TEXTDOMAIN); ?></p>
                        </div>
                    </div>
                </div>

                <div class="cin-gs-card-footer">
                    <a href="<?php echo esc_url($settings_url); ?>" class="cin-gs-btn cin-gs-btn-primary">
                        <span class="dashicons dashicons-admin-settings"></span>
                        <?php esc_html_e('Go to Settings', Config::TEXTDOMAIN); ?>
                    </a>
                </div>
            </div>
        </div>

        <!-- Quick Links -->
        <div class="cin-gs-quick-links">
            <h3><?php esc_html_e('Quick Links', Config::TEXTDOMAIN); ?></h3>
            <div class="cin-gs-links-grid">
                <a href="<?php echo esc_url($inbox_url); ?>" class="cin-gs-link">
                    <span class="dashicons dashicons-email-alt"></span>
                    <?php esc_html_e('View Inbox', Config::TEXTDOMAIN); ?>
                </a>
                <a href="<?php echo esc_url(add_query_arg('page', Config::MENU_CONTACTS, $admin_url)); ?>" class="cin-gs-link">
                    <span class="dashicons dashicons-groups"></span>
                    <?php esc_html_e('Manage Contacts', Config::TEXTDOMAIN); ?>
                </a>
                <a href="<?php echo esc_url(add_query_arg('page', 'contactin-analytics', $admin_url)); ?>" class="cin-gs-link">
                    <span class="dashicons dashicons-chart-line"></span>
                    <?php esc_html_e('View Analytics', Config::TEXTDOMAIN); ?>
                </a>
                <a href="<?php echo esc_url(add_query_arg('page', Config::MENU_EMAIL_LOG, $admin_url)); ?>" class="cin-gs-link">
                    <span class="dashicons dashicons-email"></span>
                    <?php esc_html_e('Email Log', Config::TEXTDOMAIN); ?>
                </a>
            </div>
        </div>

    </div>
</div>

<script>
jQuery(document).ready(function($) {
    // Copy shortcode to clipboard
    $('.cin-gs-copy-btn').on('click', function() {
        var btn = $(this);
        var text = btn.data('clipboard');
        var textArea = document.createElement('textarea');
        textArea.value = text;
        textArea.style.position = 'fixed';
        textArea.style.left = '-9999px';
        document.body.appendChild(textArea);
        textArea.select();
        
        try {
            document.execCommand('copy');
            btn.find('.cin-copy-text').text('<?php esc_html_e('Copied!', Config::TEXTDOMAIN); ?>');
            setTimeout(function() {
                btn.find('.cin-copy-text').text('<?php esc_html_e('Copy', Config::TEXTDOMAIN); ?>');
            }, 2000);
        } catch (err) {
            console.error('Failed to copy:', err);
        }
        
        document.body.removeChild(textArea);
    });
});
</script>
