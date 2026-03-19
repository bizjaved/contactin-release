<?php
// phpcs:disable WordPress.Security.EscapeOutput.OutputNotEscaped
/**
 * Plugin Details Page
 * 
 * Displays plugin information in a modal-friendly format
 * 
 * @package ContactInbox\Admin\Pages
 */

declare(strict_types=1);

namespace ContactInbox\Admin\Pages;

use ContactInbox\Core\Config;
use ContactInbox\Traits\Singleton;

if (!defined('ABSPATH')) {
    exit;
}

final class PluginDetails {
    use Singleton;

    protected function __construct() {
        // Constructor is now empty
        // AJAX handler is registered in contact-inbox.php (main plugin file)
        // so it works even when plugin is deactivated
    }

    /**
     * Static method to render plugin details - works when called directly
     */
    public static function render_details_static(): void {
        // Call the instance method
        self::instance()->render_details();
    }

    /**
     * Render plugin details page
     */
    public function render_details(): void {
        // Set proper headers for iframe content
        header('Content-Type: text/html; charset=utf-8');
        header('X-Frame-Options: SAMEORIGIN');
        
        // No nonce check needed - this is public plugin information
        // Capability check - any logged-in user who can access admin can see plugin details
        if (!is_admin()) {
            status_header(403);
            wp_die(__('Invalid request.', 'contact-inbox'));
        }
        
        ?>
        <!DOCTYPE html>
        <html <?php language_attributes(); ?>>
        <head>
            <meta charset="<?php bloginfo('charset'); ?>">
            <meta name="viewport" content="width=device-width, initial-scale=1">
            <title><?php echo esc_html__('ContactIn - Plugin Details', 'contact-inbox'); ?></title>
            <?php
            wp_enqueue_style('dashicons');
            wp_register_style('contactin-plugin-details-inline', false, ['dashicons'], Config::VERSION);
            wp_enqueue_style('contactin-plugin-details-inline');
            wp_register_script('contactin-plugin-details-inline', '', [], Config::VERSION, true);
            wp_enqueue_script('contactin-plugin-details-inline');
            wp_print_styles(['dashicons', 'contactin-plugin-details-inline']);
            ?>
            <?php ob_start(); ?>
                * {
                    box-sizing: border-box;
                    margin: 0;
                    padding: 0;
                }
                html, body {
                    height: 100%;
                    margin: 0;
                    padding: 0;
                }
                body {
                    display: flex;
                    flex-direction: column;
                    background: #fff;
                    font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Oxygen-Sans, Ubuntu, Cantarell, "Helvetica Neue", sans-serif;
                    line-height: 1.6;
                    color: #32373c;
                }
                #plugin-information-scrollable {
                    flex: 1;
                    min-height: 0;
                    display: flex;
                    flex-direction: column;
                    overflow-y: auto;
                    overflow-x: hidden;
                }
                .plugin-banner {
                    flex-shrink: 0;
                }
                .plugin-meta {
                    flex-shrink: 0;
                }
                #plugin-information-title {
                    flex-shrink: 0;
                }
                .plugin-info-wrapper {
                    flex: 1;
                    min-height: 0;
                    display: grid;
                    grid-template-columns: 1fr 250px;
                    gap: 0;
                    overflow: visible;
                }
                .plugin-info-main {
                    background: #fff;
                    min-height: 0;
                    overflow: visible;
                }
                .plugin-info-sidebar {
                    background: #f6f7f7;
                    border-left: 1px solid #dcdcde;
                    padding: 30px 20px;
                    min-height: 0;
                    overflow: visible;
                }
                .sidebar-section {
                    margin-bottom: 32px;
                }
                .sidebar-section:last-child {
                    margin-bottom: 0;
                }
                .stats-section {
                    display: grid;
                    grid-template-columns: 1fr 1fr;
                    gap: 16px;
                    padding: 16px;
                    background: #fff;
                    border-radius: 6px;
                    border: 1px solid #dcdcde;
                    margin-bottom: 20px !important;
                }
                .stat-item {
                    display: flex;
                    flex-direction: column;
                    align-items: center;
                    text-align: center;
                }
                .stat-label {
                    font-size: 12px;
                    color: #646970;
                    text-transform: uppercase;
                    font-weight: 600;
                    letter-spacing: 0.5px;
                    margin-bottom: 6px;
                }
                .stat-value {
                    font-size: 18px;
                    font-weight: 700;
                    color: #667eea;
                }
                .coming-soon-section {
                    padding: 16px;
                    background: linear-gradient(135deg, #fff3cd 0%, #fffbea 100%);
                    border: 1px solid #ffeeba;
                    border-radius: 6px;
                    margin-bottom: 20px !important;
                }
                .first-release-section {
                    padding: 16px;
                    background: linear-gradient(135deg, #e8f5e9 0%, #f1f8e9 100%);
                    border: 1px solid #c8e6c9;
                    border-radius: 6px;
                    margin-bottom: 20px !important;
                }
                .coming-soon-badge {
                    display: flex;
                    align-items: center;
                    gap: 8px;
                    font-size: 14px;
                    font-weight: 600;
                    color: #856404;
                    margin-bottom: 12px;
                }
                .release-badge {
                    display: flex;
                    align-items: center;
                    gap: 8px;
                    font-size: 14px;
                    font-weight: 600;
                    color: #2e7d32;
                    margin-bottom: 12px;
                }
                .coming-soon-badge .dashicons {
                    font-size: 20px;
                    width: 20px;
                    height: 20px;
                    color: #ff9800;
                }
                .release-badge .dashicons {
                    font-size: 20px;
                    width: 20px;
                    height: 20px;
                    color: #ffb900;
                }
                .coming-soon-section p {
                    margin: 0 !important;
                    font-size: 13px;
                    color: #856404;
                    line-height: 1.5;
                }
                .first-release-section p {
                    margin: 0 !important;
                    font-size: 13px;
                    color: #2e7d32;
                    line-height: 1.5;
                }
                .sidebar-section h3 {
                    font-size: 12px;
                    text-transform: uppercase;
                    letter-spacing: 0.5px;
                    font-weight: 600;
                    color: #646970;
                    margin: 0 0 12px 0;
                }
                .sidebar-section p {
                    margin: 0;
                    font-size: 13px;
                    line-height: 1.6;
                    color: #50575e;
                }
                .rating-stars {
                    color: #ffb900;
                    font-size: 18px;
                    line-height: 1;
                    margin: 8px 0;
                }
                .install-button {
                    width: 100%;
                    padding: 12px;
                    background: #667eea;
                    color: #fff;
                    border: none;
                    border-radius: 6px;
                    font-size: 15px;
                    font-weight: 500;
                    cursor: pointer;
                    transition: all 0.2s;
                    margin-top: 12px;
                }
                .install-button:hover {
                    background: #5568d3;
                }
                .sidebar-link {
                    display: block;
                    color: #2271b1;
                    text-decoration: none;
                    font-size: 13px;
                    margin: 8px 0;
                    transition: all 0.2s;
                }
                .sidebar-link:hover {
                    color: #135e96;
                }
                .sidebar-link .dashicons {
                    margin-right: 6px;
                    font-size: 14px;
                    width: 14px;
                    height: 14px;
                    vertical-align: middle;
                }
                .requirement-item {
                    padding: 8px 0;
                    font-size: 13px;
                    color: #50575e;
                    border-bottom: 1px solid #e0e0e0;
                }
                .requirement-item:last-child {
                    border-bottom: none;
                }
                .requirement-item strong {
                    display: block;
                    color: #1e1e1e;
                    margin-bottom: 2px;
                }
                @media (max-width: 768px) {
                    .plugin-info-wrapper {
                        grid-template-columns: 1fr;
                    }
                    .plugin-info-sidebar {
                        border-left: none;
                        border-top: 1px solid #dcdcde;
                        padding: 20px;
                        display: grid;
                        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
                        gap: 20px;
                    }
                    .sidebar-section {
                        margin-bottom: 0;
                    }
                }
                .plugin-banner-hero {
                    position: relative;
                    height: 400px;
                    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    color: #fff;
                    overflow: hidden;
                }
                .plugin-banner-hero::before {
                    content: '';
                    position: absolute;
                    top: 0;
                    left: 0;
                    right: 0;
                    bottom: 0;
                    background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 1200 400"><path d="M0,150 C150,200 350,50 600,120 C750,160 900,250 1200,180 L1200,400 L0,400 Z" fill="rgba(255,255,255,0.1)"/></svg>') no-repeat bottom;
                    background-size: cover;
                }
                .banner-image-placeholder {
                    position: absolute;
                    top: 0;
                    left: 0;
                    right: 0;
                    bottom: 0;
                    background-color: rgba(255, 255, 255, 0.05);
                    border: 2px dashed rgba(255, 255, 255, 0.3);
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    font-size: 14px;
                    color: rgba(255, 255, 255, 0.6);
                    z-index: 0;
                }
                .banner-content {
                    text-align: center;
                    position: relative;
                    z-index: 2;
                    padding: 40px;
                }
                .plugin-banner-hero h1 {
                    font-size: 48px;
                    font-weight: 700;
                    margin: 0 0 16px 0;
                    text-shadow: 0 4px 8px rgba(0,0,0,0.2);
                    letter-spacing: -0.5px;
                }
                .banner-content .tagline {
                    font-size: 22px;
                    opacity: 0.95;
                    font-weight: 400;
                    text-shadow: 0 2px 4px rgba(0,0,0,0.1);
                    margin: 0;
                }
                #plugin-information-title {
                    padding: 0;
                    background: #fff;
                    border-bottom: 1px solid #dcdcde;
                }
                .plugin-info-tabs {
                    display: flex;
                    gap: 0;
                    list-style: none;
                    margin: 0;
                    padding: 0 26px;
                    background: #fff;
                    border-bottom: 1px solid #dcdcde;
                }
                .plugin-info-tabs li {
                    margin: 0;
                }
                .plugin-info-tabs a {
                    display: block;
                    padding: 16px 24px;
                    text-decoration: none;
                    color: #50575e;
                    font-weight: 500;
                    border-bottom: 3px solid transparent;
                    transition: all 0.2s;
                }
                .plugin-info-tabs a:hover {
                    color: #2271b1;
                    background: #f6f7f7;
                }
                .plugin-info-tabs a.active {
                    color: #2271b1;
                    border-bottom-color: #2271b1;
                }
                #plugin-information-content {
                    padding: 30px 26px;
                }
                h2 {
                    font-size: 22px;
                    font-weight: 600;
                    margin: 30px 0 20px 0;
                    color: #1e1e1e;
                    display: flex;
                    align-items: center;
                    gap: 10px;
                }
                h2:first-child {
                    margin-top: 0;
                }
                h2 .dashicons {
                    color: #667eea;
                    font-size: 28px;
                    width: 28px;
                    height: 28px;
                }
                h3 {
                    font-size: 18px;
                    font-weight: 600;
                    margin: 24px 0 12px 0;
                    color: #1e1e1e;
                }
                p {
                    line-height: 1.8;
                    margin: 0 0 16px 0;
                    color: #50575e;
                }
                .feature-grid {
                    display: grid;
                    grid-template-columns: repeat(auto-fit, minmax(320px, 1fr));
                    gap: 16px;
                    margin: 20px 0;
                }
                .feature-card {
                    padding: 20px;
                    background: #f9f9f9;
                    border-left: 4px solid #667eea;
                    border-radius: 4px;
                    transition: all 0.2s;
                }
                .feature-card:hover {
                    background: #f0f0f1;
                    transform: translateX(4px);
                }
                .feature-card h4 {
                    font-size: 16px;
                    font-weight: 600;
                    margin: 0 0 8px 0;
                    color: #1e1e1e;
                    display: flex;
                    align-items: center;
                    gap: 8px;
                }
                .feature-card h4 .dashicons {
                    color: #46b450;
                    font-size: 20px;
                    width: 20px;
                    height: 20px;
                }
                .feature-card p {
                    margin: 0;
                    font-size: 14px;
                    line-height: 1.6;
                    color: #646970;
                }
                .install-step {
                    padding: 20px;
                    background: #f6f7f7;
                    border-radius: 8px;
                    margin: 16px 0;
                    border: 1px solid #dcdcde;
                }
                .install-step h4 {
                    margin: 0 0 12px 0;
                    color: #1e1e1e;
                    font-size: 16px;
                    display: flex;
                    align-items: center;
                    gap: 10px;
                }
                .step-number {
                    display: inline-flex;
                    align-items: center;
                    justify-content: center;
                    width: 32px;
                    height: 32px;
                    background: #667eea;
                    color: #fff;
                    border-radius: 50%;
                    font-weight: 600;
                    font-size: 16px;
                }
                code {
                    background: #23282d;
                    color: #50fa7b;
                    padding: 3px 8px;
                    border-radius: 4px;
                    font-family: 'Monaco', 'Courier New', monospace;
                    font-size: 13px;
                }
                .code-block {
                    background: #23282d;
                    color: #f8f8f2;
                    padding: 20px;
                    border-radius: 6px;
                    overflow-x: auto;
                    margin: 16px 0;
                    border: 1px solid #1e1e1e;
                }
                .code-block code {
                    background: transparent;
                    padding: 0;
                    color: inherit;
                }
                .faq-item {
                    margin: 24px 0;
                    padding: 20px;
                    background: #f9f9f9;
                    border-radius: 6px;
                    border-left: 3px solid #667eea;
                }
                .faq-question {
                    font-size: 16px;
                    font-weight: 600;
                    color: #1e1e1e;
                    margin-bottom: 12px;
                    display: flex;
                    align-items: flex-start;
                    gap: 8px;
                }
                .faq-question::before {
                    content: "Q:";
                    color: #667eea;
                    font-weight: 700;
                    flex-shrink: 0;
                }
                .faq-answer {
                    color: #50575e;
                    line-height: 1.7;
                    padding-left: 28px;
                }
                .changelog-entry {
                    margin: 24px 0;
                    padding: 20px;
                    background: #fff;
                    border: 1px solid #dcdcde;
                    border-radius: 6px;
                }
                .changelog-version {
                    font-size: 18px;
                    font-weight: 600;
                    color: #667eea;
                    margin-bottom: 8px;
                }
                .changelog-date {
                    font-size: 13px;
                    color: #646970;
                    margin-bottom: 12px;
                }
                .changelog-entry ul {
                    margin: 12px 0;
                    padding-left: 24px;
                }
                .changelog-entry li {
                    margin: 8px 0;
                    line-height: 1.6;
                }
                .screenshot-item {
                    margin: 30px 0;
                }
                .screenshot-img {
                    width: 100%;
                    height: auto;
                    border: 1px solid #dcdcde;
                    border-radius: 6px;
                    margin-bottom: 12px;
                    background: #f0f0f1;
                    min-height: 400px;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    color: #646970;
                    font-size: 16px;
                }
                .screenshot-caption {
                    font-size: 14px;
                    color: #646970;
                    font-style: italic;
                    text-align: center;
                }
                .button-group {
                    display: flex;
                    gap: 12px;
                    margin: 30px 0;
                    flex-wrap: wrap;
                }
                .btn {
                    display: inline-flex;
                    align-items: center;
                    gap: 8px;
                    padding: 12px 24px;
                    border-radius: 6px;
                    text-decoration: none;
                    font-weight: 500;
                    font-size: 15px;
                    transition: all 0.2s;
                    border: none;
                    cursor: pointer;
                }
                .btn-primary {
                    background: #667eea;
                    color: #fff;
                }
                .btn-primary:hover {
                    background: #5568d3;
                    transform: translateY(-2px);
                    box-shadow: 0 4px 12px rgba(102, 126, 234, 0.4);
                }
                .btn-secondary {
                    background: #f0f0f1;
                    color: #1e1e1e;
                    border: 1px solid #dcdcde;
                }
                .btn-secondary:hover {
                    background: #dcdcde;
                }
                .btn .dashicons {
                    font-size: 18px;
                    width: 18px;
                    height: 18px;
                }
                ul, ol {
                    margin: 16px 0;
                    padding-left: 28px;
                }
                li {
                    margin: 8px 0;
                    line-height: 1.7;
                }
                .tab-content {
                    display: none;
                }
                .tab-content.active {
                    display: block;
                    animation: fadeIn 0.3s;
                }
                @keyframes fadeIn {
                    from { opacity: 0; transform: translateY(10px); }
                    to { opacity: 1; transform: translateY(0); }
                }
                .plugin-meta {
                    display: flex;
                    gap: 30px;
                    padding: 20px 26px;
                    background: #f6f7f7;
                    border-bottom: 1px solid #dcdcde;
                    flex-wrap: wrap;
                }
                .meta-item {
                    display: flex;
                    flex-direction: column;
                    gap: 4px;
                }
                .meta-label {
                    font-size: 12px;
                    color: #646970;
                    text-transform: uppercase;
                    font-weight: 600;
                    letter-spacing: 0.5px;
                }
                .meta-value {
                    font-size: 15px;
                    color: #1e1e1e;
                    font-weight: 500;
                }
                @media (max-width: 782px) {
                    .plugin-banner {
                        height: 200px;
                    }
                    .banner-content h1 {
                        font-size: 24px;
                    }
                    .plugin-info-tabs {
                        overflow-x: auto;
                        padding: 0 16px;
                    }
                    .plugin-info-tabs a {
                        padding: 14px 16px;
                        font-size: 14px;
                        white-space: nowrap;
                    }
                    #plugin-information-content {
                        padding: 20px 16px;
                    }
                    .feature-grid {
                        grid-template-columns: 1fr;
                    }
                    .plugin-meta {
                        padding: 16px;
                    }
                }
            <?php
            $plugin_details_inline_css = trim((string) ob_get_clean());
            wp_add_inline_style('contactin-plugin-details-inline', $plugin_details_inline_css);
            ?>
        </head>
        <body>
            <div id="plugin-information-scrollable">
                <!-- Hero Banner Section -->
                <div class="plugin-banner-hero">
                    <div class="banner-image-placeholder">
                        <?php echo esc_html__('[Plugin Hero Image - Manually Add]', 'contact-inbox'); ?>
                    </div>
                    <div class="banner-content">
                        <h1><?php echo esc_html__('ContactIn', 'contact-inbox'); ?></h1>
                        <p class="tagline"><?php echo esc_html__('Secure Contact Forms & Inbox Management', 'contact-inbox'); ?></p>
                    </div>
                </div>

                <!-- Navigation Tabs -->
                <div id="plugin-information-title">
                    <ul class="plugin-info-tabs">
                        <li><a href="#tab-description" class="active"><?php echo esc_html__('Description', 'contact-inbox'); ?></a></li>
                        <li><a href="#tab-installation"><?php echo esc_html__('Installation', 'contact-inbox'); ?></a></li>
                        <li><a href="#tab-faq"><?php echo esc_html__('FAQ', 'contact-inbox'); ?></a></li>
                        <li><a href="#tab-changelog"><?php echo esc_html__('Changelog', 'contact-inbox'); ?></a></li>
                        <li><a href="#tab-screenshots"><?php echo esc_html__('Screenshots', 'contact-inbox'); ?></a></li>
                        <li><a href="#tab-documentation"><?php echo esc_html__('Documentation', 'contact-inbox'); ?></a></li>
                    </ul>
                </div>

                <div class="plugin-info-wrapper">
                    <div class="plugin-info-main">
                        <div id="plugin-information-content">
                            
                            <!-- Description Tab -->
                            <div id="tab-description" class="tab-content active"><?php echo $this->get_description_tab(); ?></div>
                            
                            <!-- Installation Tab -->
                            <div id="tab-installation" class="tab-content"><?php echo $this->get_installation_tab(); ?></div>
                            
                            <!-- FAQ Tab -->
                            <div id="tab-faq" class="tab-content"><?php echo $this->get_faq_tab(); ?></div>
                            
                            <!-- Changelog Tab -->
                            <div id="tab-changelog" class="tab-content"><?php echo $this->get_changelog_tab(); ?></div>
                            
                            <!-- Screenshots Tab -->
                            <div id="tab-screenshots" class="tab-content"><?php echo $this->get_screenshots_tab(); ?></div>

                            <!-- Documentation Tab -->
                            <div id="tab-documentation" class="tab-content"><?php echo $this->get_readme_tab(); ?></div>

                        </div>
                    </div>

                    <div class="plugin-info-sidebar">
                        <!-- Free Version Badge -->
                        <div class="sidebar-section first-release-section">
                            <div class="release-badge">
                                <span class="dashicons dashicons-star-filled"></span>
                                <?php echo esc_html__('Free Version', 'contact-inbox'); ?>
                            </div>
                            <p><?php echo esc_html__('Get started with secure contact forms, intent classification, inbox management, and analytics. Upgrade to Pro for advanced automation and integrations.', 'contact-inbox'); ?></p>
                        </div>

                        <!-- Upgrade CTA -->
                        <div class="sidebar-section">
                            <h3><?php echo esc_html__('Unlock Pro Features', 'contact-inbox'); ?></h3>
                            <ul style="margin: 10px 0; padding-left: 20px;">
                                <li><?php echo esc_html__('Adaptive Learning', 'contact-inbox'); ?></li>
                                <li><?php echo esc_html__('Salesforce CRM Sync', 'contact-inbox'); ?></li>
                                <li><?php echo esc_html__('Priority Support', 'contact-inbox'); ?></li>
                                <li><?php echo esc_html__('More Integrations', 'contact-inbox'); ?></li>
                            </ul>
                            <a href="<?php echo esc_url('https://contactinbox.app/'); ?>" target="_blank" rel="noopener noreferrer" class="btn btn-primary" style="display: inline-block; margin-top: 12px;">
                                <?php echo esc_html__('Upgrade to Pro', 'contact-inbox'); ?>
                            </a>
                        </div>

                        <!-- Rating -->
                        <div class="sidebar-section">
                            <h3><?php echo esc_html__('Rating', 'contact-inbox'); ?></h3>
                            <div class="rating-stars">★★★★★</div>
                            <p><?php echo esc_html__('Built for reliable contact management and daily business workflows.', 'contact-inbox'); ?></p>
                        </div>

                        <!-- Version Info -->
                        <div class="sidebar-section">
                            <h3><?php echo esc_html__('Version Details', 'contact-inbox'); ?></h3>
                            <div class="requirement-item">
                                <strong><?php echo esc_html__('Current Version', 'contact-inbox'); ?></strong>
                                <?php echo esc_html(CONTACTINBOX_VERSION); ?>
                            </div>
                            <div class="requirement-item">
                                <strong><?php echo esc_html__('Last Updated', 'contact-inbox'); ?></strong>
                                <?php echo esc_html__('February 2026', 'contact-inbox'); ?>
                            </div>
                            <div class="requirement-item">
                                <strong><?php echo esc_html__('Author', 'contact-inbox'); ?></strong>
                                Javed Ahsan
                            </div>
                        </div>

                        <!-- Requirements -->
                        <div class="sidebar-section">
                            <h3><?php echo esc_html__('Requirements', 'contact-inbox'); ?></h3>
                            <div class="requirement-item">
                                <strong><?php echo esc_html__('WordPress', 'contact-inbox'); ?></strong>
                                6.4 or higher
                            </div>
                            <div class="requirement-item">
                                <strong><?php echo esc_html__('PHP', 'contact-inbox'); ?></strong>
                                7.4 or higher
                            </div>
                            <div class="requirement-item">
                                <strong><?php echo esc_html__('SSL', 'contact-inbox'); ?></strong>
                                <?php echo esc_html__('Recommended', 'contact-inbox'); ?>
                            </div>
                        </div>

                        <!-- Links -->
                        <div class="sidebar-section">
                            <h3><?php echo esc_html__('Support', 'contact-inbox'); ?></h3>
                            <p><?php echo esc_html__('Need help or want to report an issue? Visit our GitHub support channel.', 'contact-inbox'); ?></p>
                            <a href="<?php echo esc_url('https://github.com/bizjaved/contact-inbox/issues'); ?>" target="_blank" rel="noopener noreferrer" class="sidebar-link">
                                <span class="dashicons dashicons-sos"></span>
                                <?php echo esc_html__('Contact Support', 'contact-inbox'); ?>
                            </a>
                        </div>

                        <!-- License -->
                        <div class="sidebar-section">
                            <h3><?php echo esc_html__('License', 'contact-inbox'); ?></h3>
                            <p><?php echo esc_html__('GPL-3.0 or later. 100% free and open source.', 'contact-inbox'); ?></p>
                        </div>
                    </div>
                </div>
            </div>

            <?php ob_start(); ?>
            document.addEventListener('DOMContentLoaded', function() {
                var tabs = document.querySelectorAll('.plugin-info-tabs a');
                var contents = document.querySelectorAll('.tab-content');
                var tabLinks = document.querySelectorAll('a[href^="#tab-"]');
                
                function activateTab(target) {
                    if (!target) {
                        return;
                    }

                    tabs.forEach(function(t) {
                        t.classList.remove('active');
                    });
                    contents.forEach(function(c) {
                        c.classList.remove('active');
                    });

                    tabs.forEach(function(t) {
                        if (t.getAttribute('href') === target) {
                            t.classList.add('active');
                        }
                    });

                    var targetElement = document.querySelector(target);
                    if (targetElement) {
                        targetElement.classList.add('active');
                        document.getElementById('plugin-information-scrollable').scrollTop = 0;
                    }
                }

                tabs.forEach(function(tab) {
                    tab.addEventListener('click', function(e) {
                        e.preventDefault();
                        activateTab(this.getAttribute('href'));
                    });
                });

                tabLinks.forEach(function(link) {
                    if (link.closest('.plugin-info-tabs')) {
                        return;
                    }
                    link.addEventListener('click', function(e) {
                        e.preventDefault();
                        activateTab(this.getAttribute('href'));
                    });
                });
            });
            <?php
            $plugin_details_inline_js = trim((string) ob_get_clean());
            wp_add_inline_script('contactin-plugin-details-inline', $plugin_details_inline_js);
            wp_print_footer_scripts();
            ?>
        </body>
        </html>
        <?php
        exit;
    }

    private function get_description_tab(): string {
        ob_start();
        ?>
        <h2><span class="dashicons dashicons-info-outline"></span><?php echo esc_html__('What is Contact Inbox?', 'contact-inbox'); ?></h2>
        <p><?php echo esc_html__('Contact Inbox helps you collect, organize, and respond to messages from one secure place. It combines contact forms, an inbox view, intent classification, and practical analytics for teams that want a clear view of incoming communication.', 'contact-inbox'); ?></p>

        <h2><span class="dashicons dashicons-star-filled"></span><?php echo esc_html__('Key Features', 'contact-inbox'); ?></h2>
        <div class="feature-grid">
            <div class="feature-card">
                <h4><span class="dashicons dashicons-yes-alt"></span><?php echo esc_html__('Secure Inbox', 'contact-inbox'); ?></h4>
                <p><?php echo esc_html__('Never lose a submission again. Centralized message management with search, filtering, bulk actions, spam protection, and archived messages.', 'contact-inbox'); ?></p>
            </div>
            <div class="feature-card">
                <h4><span class="dashicons dashicons-yes-alt"></span><?php echo esc_html__('Real-Time Analytics', 'contact-inbox'); ?></h4>
                <p><?php echo esc_html__('Track submissions, conversion rates, response times, peak traffic hours, and user behavior patterns with beautiful dashboards.', 'contact-inbox'); ?></p>
            </div>
            <div class="feature-card">
                <h4><span class="dashicons dashicons-yes-alt"></span><?php echo esc_html__('Intent Classification', 'contact-inbox'); ?></h4>
                <p><?php echo esc_html__('Automatically classifies messages by intent (such as Sales, Support, and Feedback) to help your team prioritize faster.', 'contact-inbox'); ?></p>
            </div>
            <div class="feature-card">
                <h4><span class="dashicons dashicons-yes-alt"></span><?php echo esc_html__('Advanced Security', 'contact-inbox'); ?></h4>
                <p><?php echo esc_html__('Google reCAPTCHA v3, intelligent spam filtering, rate limiting, honeypot protection, and IP blocking capabilities.', 'contact-inbox'); ?></p>
            </div>
            <div class="feature-card">
                <h4><span class="dashicons dashicons-yes-alt"></span><?php echo esc_html__('Privacy-Friendly Forms', 'contact-inbox'); ?></h4>
                <p><?php echo esc_html__('Includes consent-friendly form options and privacy policy linking. Advanced compliance workflows are available in Contact Inbox Pro.', 'contact-inbox'); ?></p>
            </div>
            <div class="feature-card">
                <h4><span class="dashicons dashicons-yes-alt"></span><?php echo esc_html__('Email Notifications', 'contact-inbox'); ?></h4>
                <p><?php echo esc_html__('SMTP configuration with delivery tracking, retry mechanisms, queue management, and detailed logging.', 'contact-inbox'); ?></p>
            </div>
            <div class="feature-card">
                <h4><span class="dashicons dashicons-yes-alt"></span><?php echo esc_html__('Developer-Friendly', 'contact-inbox'); ?></h4>
                <p><?php echo esc_html__('Extensive hooks and filters make it easy to customize behavior and integrate with your existing workflows.', 'contact-inbox'); ?></p>
            </div>
            <div class="feature-card">
                <h4><span class="dashicons dashicons-yes-alt"></span><?php echo esc_html__('Page Builder Support', 'contact-inbox'); ?></h4>
                <p><?php echo esc_html__('Native Gutenberg blocks, Elementor widgets, and simple shortcode integration for maximum flexibility.', 'contact-inbox'); ?></p>
            </div>
        </div>

        <h2><span class="dashicons dashicons-businessperson"></span><?php echo esc_html__('Perfect For', 'contact-inbox'); ?></h2>
        <ul>
            <li><strong><?php echo esc_html__('Business Websites', 'contact-inbox'); ?></strong> - <?php echo esc_html__('Professional contact management with lead tracking', 'contact-inbox'); ?></li>
            <li><strong><?php echo esc_html__('SaaS Platforms', 'contact-inbox'); ?></strong> - <?php echo esc_html__('Lead capture with searchable, organized submissions', 'contact-inbox'); ?></li>
            <li><strong><?php echo esc_html__('Support Teams', 'contact-inbox'); ?></strong> - <?php echo esc_html__('Ticket-like inbox system for customer support', 'contact-inbox'); ?></li>
            <li><strong><?php echo esc_html__('Marketing Teams', 'contact-inbox'); ?></strong> - <?php echo esc_html__('Conversion tracking and campaign performance analysis', 'contact-inbox'); ?></li>
            <li><strong><?php echo esc_html__('Developers', 'contact-inbox'); ?></strong> - <?php echo esc_html__('Custom workflows using hooks, filters, and extensible architecture', 'contact-inbox'); ?></li>
        </ul>

        <div class="button-group">
            <a href="<?php echo esc_url(admin_url('admin.php?page=contactin-get-started')); ?>" target="_parent" class="btn btn-primary">
                <span class="dashicons dashicons-welcome-learn-more"></span>
                <?php echo esc_html__('Get Started', 'contact-inbox'); ?>
            </a>
            <a href="#tab-documentation" class="btn btn-secondary" aria-label="<?php echo esc_attr__('View plugin documentation', 'contact-inbox'); ?>">
                <span class="dashicons dashicons-book"></span>
                <?php echo esc_html__('Documentation', 'contact-inbox'); ?>
            </a>
            <a href="<?php echo esc_url('https://github.com/bizjaved/contact-inbox/issues'); ?>" target="_blank" rel="noopener noreferrer" class="btn btn-secondary">
                <span class="dashicons dashicons-sos"></span>
                <?php echo esc_html__('Support', 'contact-inbox'); ?>
            </a>
        </div>
        <?php
        return ob_get_clean();
    }

    private function get_installation_tab(): string {
        ob_start();
        ?>
        <h2><?php echo esc_html__('Installation Instructions', 'contact-inbox'); ?></h2>
        
        <div class="install-step">
            <h4><span class="step-number">1</span><?php echo esc_html__('Activate the Plugin', 'contact-inbox'); ?></h4>
            <p><?php echo esc_html__('The plugin should already be activated. If not, go to Plugins → Installed Plugins and activate Contact Inbox.', 'contact-inbox'); ?></p>
        </div>

        <div class="install-step">
            <h4><span class="step-number">2</span><?php echo esc_html__('Add Contact Form to Your Page', 'contact-inbox'); ?></h4>
            <p><?php echo esc_html__('Use the shortcode to display the contact form anywhere on your site:', 'contact-inbox'); ?></p>
            <div class="code-block">
                <code>[contact_inbox_form]</code>
            </div>
            <p><?php echo esc_html__('Or use the Gutenberg block "Contact Inbox Form" or Elementor widget for visual building.', 'contact-inbox'); ?></p>
        </div>

        <div class="install-step">
            <h4><span class="step-number">3</span><?php echo esc_html__('Configure Settings', 'contact-inbox'); ?></h4>
            <p><?php echo esc_html__('Navigate to Contact Inbox → Settings to configure:', 'contact-inbox'); ?></p>
            <ul>
                <li><?php echo esc_html__('Email notifications and SMTP settings', 'contact-inbox'); ?></li>
                <li><?php echo esc_html__('Form fields (enable subject line, salutation, file uploads)', 'contact-inbox'); ?></li>
                <li><?php echo esc_html__('reCAPTCHA v3 for spam protection', 'contact-inbox'); ?></li>
                <li><?php echo esc_html__('Consent and privacy-friendly form options', 'contact-inbox'); ?></li>
            </ul>
        </div>

        <div class="install-step">
            <h4><span class="step-number">4</span><?php echo esc_html__('Optional: CRM Integration', 'contact-inbox'); ?></h4>
            <p><?php echo esc_html__('Salesforce CRM sync is available in Contact Inbox Pro from Contact Inbox → CRM Settings.', 'contact-inbox'); ?></p>
        </div>

        <h3><?php echo esc_html__('Advanced (Pro): REST API Usage', 'contact-inbox'); ?></h3>
        <p><?php echo esc_html__('For headless WordPress or custom integrations, REST API access is available in Contact Inbox Pro:', 'contact-inbox'); ?></p>
        <div class="code-block">
            <code>POST /wp-json/contactinbox/v1/submit<br>
{<br>
&nbsp;&nbsp;"name": "John Doe",<br>
&nbsp;&nbsp;"email": "john@example.com",<br>
&nbsp;&nbsp;"message": "Hello!"<br>
}</code>
        </div>
        <p><?php echo esc_html__('See full API details on our documentation site.', 'contact-inbox'); ?></p>
        <?php
        return ob_get_clean();
    }

    private function get_faq_tab(): string {
        ob_start();
        ?>
        <h2><?php echo esc_html__('Frequently Asked Questions', 'contact-inbox'); ?></h2>

        <div class="faq-item">
            <div class="faq-question"><?php echo esc_html__('How do I add the contact form to my website?', 'contact-inbox'); ?></div>
            <div class="faq-answer">
                <p><?php echo esc_html__('Simply use the shortcode [contact_inbox_form] on any page or post. You can also use the native Gutenberg block "Contact Inbox Form" or the Elementor widget for drag-and-drop integration.', 'contact-inbox'); ?></p>
            </div>
        </div>

        <div class="faq-item">
            <div class="faq-question"><?php echo esc_html__('Where are form submissions stored?', 'contact-inbox'); ?></div>
            <div class="faq-answer">
                <p><?php echo esc_html__('All submissions are securely stored in your WordPress database and accessible via Contact Inbox → Inbox. Data is only sent to external services when optional integrations are enabled/configured (such as reCAPTCHA, SMTP, webhooks, or CRM in Pro).', 'contact-inbox'); ?></p>
            </div>
        </div>

        <div class="faq-item">
            <div class="faq-question"><?php echo esc_html__('Does it work with Salesforce CRM?', 'contact-inbox'); ?></div>
            <div class="faq-answer">
                <p><?php echo esc_html__('Salesforce integration is available in Contact Inbox Pro, including OAuth authentication, customizable field mapping, automatic sync, and detailed error logging. Configure it at Contact Inbox → CRM Settings.', 'contact-inbox'); ?></p>
            </div>
        </div>

        <div class="faq-item">
            <div class="faq-question"><?php echo esc_html__('Is it GDPR compliant?', 'contact-inbox'); ?></div>
            <div class="faq-answer">
                <p><?php echo esc_html__('The free version includes privacy-friendly form controls (like consent options). Advanced GDPR workflows, retention controls, and compliance tooling are available in Contact Inbox Pro.', 'contact-inbox'); ?></p>
            </div>
        </div>

        <div class="faq-item">
            <div class="faq-question"><?php echo esc_html__('Can I use it for a headless WordPress site?', 'contact-inbox'); ?></div>
            <div class="faq-answer">
                <p><?php echo esc_html__('Headless workflows are supported via Contact Inbox Pro with a full REST API. You can submit forms via API, retrieve submissions, trigger webhooks, and manage operations programmatically. Full documentation is available under Contact Inbox → REST API.', 'contact-inbox'); ?></p>
            </div>
        </div>

        <div class="faq-item">
            <div class="faq-question"><?php echo esc_html__('How does spam protection work?', 'contact-inbox'); ?></div>
            <div class="faq-answer">
                <p><?php echo esc_html__('Multiple layers: Google reCAPTCHA v3 (invisible), spam filtering, honeypot fields, and rate limiting. Suspicious submissions are automatically handled in the inbox.', 'contact-inbox'); ?></p>
            </div>
        </div>

        <div class="faq-item">
            <div class="faq-question"><?php echo esc_html__('Can users upload files through the form?', 'contact-inbox'); ?></div>
            <div class="faq-answer">
                <p><?php echo esc_html__('Yes. Enable file attachments in Contact Inbox → Settings. Files are securely stored in wp-content/uploads/contactin-attachments/ with automatic cleanup support.', 'contact-inbox'); ?></p>
            </div>
        </div>

        <div class="faq-item">
            <div class="faq-question"><?php echo esc_html__('Does it support SMTP for email notifications?', 'contact-inbox'); ?></div>
            <div class="faq-answer">
                <p><?php echo esc_html__('Yes. Configure SMTP settings at Contact Inbox → Settings → Email. The plugin includes delivery tracking, queue management, retry mechanisms, and detailed email logs.', 'contact-inbox'); ?></p>
            </div>
        </div>

        <div class="faq-item">
            <div class="faq-question"><?php echo esc_html__('What analytics are included?', 'contact-inbox'); ?></div>
            <div class="faq-answer">
                <p><?php echo esc_html__('Analytics include submission trends, intent breakdown, inbox activity, and key performance snapshots to help you track communication volume and quality.', 'contact-inbox'); ?></p>
            </div>
        </div>

        <div class="faq-item">
            <div class="faq-question"><?php echo esc_html__('Where can I get support?', 'contact-inbox'); ?></div>
            <div class="faq-answer">
                <p><?php echo esc_html__('Visit our GitHub repository:', 'contact-inbox'); ?> <a href="https://github.com/bizjaved/contact-inbox/issues" target="_blank" rel="noopener noreferrer">github.com/bizjaved/contact-inbox/issues</a></p>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

    private function get_changelog_tab(): string {
        ob_start();
        ?>
        <h2><?php echo esc_html__('Changelog', 'contact-inbox'); ?></h2>

        <div class="changelog-entry">
            <div class="changelog-version">Version 1.0</div>
            <div class="changelog-date">February 2026</div>
            <ul>
                <li><strong><?php echo esc_html__('Initial Release', 'contact-inbox'); ?></strong></li>
                <li>✨ Complete inbox management system with search and filtering</li>
                <li>📊 Real-time analytics dashboard</li>
                <li>🤖 Intent classification for incoming submissions</li>
                <li>🔒 Google reCAPTCHA v3 and advanced spam filtering</li>
                <li>📧 SMTP email configuration with delivery tracking</li>
                <li>🛡️ Privacy-friendly form options and consent support</li>
                <li>🧩 Extensible hooks and filters for developers</li>
                <li>📱 Responsive design and mobile optimization</li>
                <li>🎨 Gutenberg block and Elementor widget support</li>
                <li>📎 File attachment support with secure upload handling</li>
                <li>⚡ Queue management for reliable email processing</li>
                <li>📈 Performance optimization and caching</li>
                <li>🔍 Advanced search and bulk operations</li>
                <li>🌍 Translation ready (i18n)</li>
            </ul>
        </div>

        <div class="changelog-entry">
            <div class="changelog-version">Coming Soon</div>
            <div class="changelog-date">Future Updates</div>
            <ul>
                <li>🔗 Additional CRM integrations (HubSpot, Zoho, Pipedrive)</li>
                <li>💬 Two-way email communication from inbox</li>
                <li>👥 Team collaboration features and assignments</li>
                <li>🏷️ Custom tags and categories</li>
                <li>📝 Form builder with conditional logic</li>
                <li>🔔 Browser push notifications</li>
                <li>📊 Advanced reporting and export options</li>
                <li>🤖 AI-powered spam detection</li>
                <li>🌐 Multi-language form support</li>
            </ul>
        </div>
        <?php
        return ob_get_clean();
    }

    private function get_screenshots_tab(): string {
        ob_start();
        ?>
        <h2><?php echo esc_html__('Screenshots', 'contact-inbox'); ?></h2>

        <div class="screenshot-item">
            <div class="screenshot-img">
                <span class="dashicons dashicons-email-alt" style="font-size: 64px; opacity: 0.3;"></span>
            </div>
            <div class="screenshot-caption"><?php echo esc_html__('1. Unified Inbox - Centralized message management with search, filtering, and bulk actions', 'contact-inbox'); ?></div>
        </div>

        <div class="screenshot-item">
            <div class="screenshot-img">
                <span class="dashicons dashicons-chart-line" style="font-size: 64px; opacity: 0.3;"></span>
            </div>
            <div class="screenshot-caption"><?php echo esc_html__('2. Analytics Dashboard - Real-time submission trends, conversion rates, and performance metrics', 'contact-inbox'); ?></div>
        </div>

        <div class="screenshot-item">
            <div class="screenshot-img">
                <span class="dashicons dashicons-admin-settings" style="font-size: 64px; opacity: 0.3;"></span>
            </div>
            <div class="screenshot-caption"><?php echo esc_html__('3. Settings Panel - Configure email notifications, SMTP, form fields, and security options', 'contact-inbox'); ?></div>
        </div>

        <div class="screenshot-item">
            <div class="screenshot-img">
                <span class="dashicons dashicons-cloud" style="font-size: 64px; opacity: 0.3;"></span>
            </div>
            <div class="screenshot-caption"><?php echo esc_html__('4. Intent Insights - Message categorization and confidence scoring for faster triage', 'contact-inbox'); ?></div>
        </div>

        <div class="screenshot-item">
            <div class="screenshot-img">
                <span class="dashicons dashicons-feedback" style="font-size: 64px; opacity: 0.3;"></span>
            </div>
            <div class="screenshot-caption"><?php echo esc_html__('5. Contact Form - Clean, responsive design with reCAPTCHA v3 and privacy-friendly options', 'contact-inbox'); ?></div>
        </div>

        <div class="screenshot-item">
            <div class="screenshot-img">
                <span class="dashicons dashicons-rest-api" style="font-size: 64px; opacity: 0.3;"></span>
            </div>
            <div class="screenshot-caption"><?php echo esc_html__('6. Documentation - Setup guidance, FAQs, and developer extension points', 'contact-inbox'); ?></div>
        </div>
        <?php
        return ob_get_clean();
    }

    private function get_readme_tab(): string {
        ob_start();
        
        // Get the plugin directory path - go up 4 levels from /includes/Admin/Pages/PluginDetails.php to plugin root
        $plugin_dir = dirname(dirname(dirname(dirname(__FILE__))));
        $readme_file = $plugin_dir . '/readme.txt';
        
        if (!file_exists($readme_file)) {
            ?>
            <h2><?php echo esc_html__('Documentation', 'contact-inbox'); ?></h2>
            <p><?php echo esc_html__('README file not found at: ', 'contact-inbox'); echo esc_html($readme_file); ?></p>
            <?php
            return ob_get_clean();
        }
        
        $readme_content = file_get_contents($readme_file);
        
        // Parse readme sections
        $lines = explode("\n", $readme_content);
        $current_section = '';
        $is_in_list = false;
        $is_in_code = false;
        
        foreach ($lines as $line) {
            $line = rtrim($line);
            
            // Skip empty lines at start
            if (empty($line) && !$current_section) {
                continue;
            }
            
            // Handle headers (= and ==)
            if (preg_match('/^==\s+(.+?)\s+==/', $line, $matches)) {
                if ($is_in_list) {
                    echo '</ul>';
                    $is_in_list = false;
                }
                if ($is_in_code) {
                    echo '</pre>';
                    $is_in_code = false;
                }
                ?>
                <h2><?php echo esc_html($matches[1]); ?></h2>
                <?php
                continue;
            }
            
            if (preg_match('/^=\s+(.+?)\s+=$/', $line, $matches)) {
                if ($is_in_list) {
                    echo '</ul>';
                    $is_in_list = false;
                }
                if ($is_in_code) {
                    echo '</pre>';
                    $is_in_code = false;
                }
                ?>
                <h3><?php echo esc_html($matches[1]); ?></h3>
                <?php
                continue;
            }
            
            // Handle lists
            if (preg_match('/^\*\s+(.+)/', $line, $matches)) {
                if (!$is_in_list) {
                    echo '<ul>';
                    $is_in_list = true;
                }
                ?>
                <li><?php echo wp_kses_post($matches[1]); ?></li>
                <?php
                continue;
            }
            
            if ($is_in_list && !preg_match('/^\*/', $line)) {
                echo '</ul>';
                $is_in_list = false;
            }
            
            // Handle code blocks
            if (preg_match('/^`{3}/', $line)) {
                if ($is_in_code) {
                    echo '</pre>';
                    $is_in_code = false;
                } else {
                    ?>
                    <pre class="code-block"><code>
                    <?php
                    $is_in_code = true;
                }
                continue;
            }
            
            if ($is_in_code) {
                echo esc_html($line) . "\n";
                continue;
            }
            
            // Skip empty lines
            if (empty($line)) {
                continue;
            }
            
            // Regular paragraph
            if (!preg_match('/^\s{2,}/', $line)) {
                ?>
                <p><?php echo wp_kses_post($line); ?></p>
                <?php
            }
        }
        
        if ($is_in_list) {
            echo '</ul>';
        }
        if ($is_in_code) {
            echo '</code></pre>';
        }
        
        return ob_get_clean();
    }}