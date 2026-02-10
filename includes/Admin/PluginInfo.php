<?php
namespace ContactInbox\Admin;

use ContactInbox\Traits\Singleton;
use ContactInbox\Core\Config;

/**
 * Plugin Information Handler
 * Provides custom plugin details for "View Details" link in plugins page
 */
final class PluginInfo {
    use Singleton;

    private string $plugin_slug = 'contact-inbox-free';

    protected function __construct() {
        $this->init();
    }

    protected function init(): void {
        // Hook into plugins_api to provide custom plugin information
        // Must be priority 10 or higher to run before WordPress default behavior
        add_filter('plugins_api', [$this, 'plugin_info'], 10, 3);
    }

    /**
     * Provide custom plugin information
     */
    public function plugin_info($result, $action, $args) {
        // Only handle our plugin and plugin_information action
        if ($action !== 'plugin_information') {
            return $result;
        }

        if (empty($args->slug) || $args->slug !== $this->plugin_slug) {
            return $result;
        }

        // Return our custom plugin data - this prevents WP from making HTTP call to WP.org
        return $this->get_plugin_data();
    }

    /**
     * Build plugin data object
     */
    private function get_plugin_data(): object {
        $data = new \stdClass();

        $data->name = 'Contact Inbox';
        $data->slug = $this->plugin_slug;
        $data->plugin = 'contact-inbox-free/contact-inbox.php';
        $data->version = CONTACTINBOX_VERSION;
        $data->author = 'Javed Ahsan';
        $data->author_profile = 'https://linkedin.com/in/bizjaved';
        $data->homepage = 'https://github.com/bizjaved/contact-inbox-free';
        $data->download_link = '';
        $data->donate_link = '';
        $data->requires = '6.4';
        $data->tested = '6.9.1';
        $data->requires_php = '7.4';
        $data->last_updated = date('Y-m-d');

        // Sections
        $data->sections = [
            'description' => $this->get_description(),
            'installation' => $this->get_installation(),
            'faq' => $this->get_faq(),
            'changelog' => $this->get_changelog(),
        ];

        // Banners
        $data->banners = [
            'low' => CONTACTINBOX_URL . 'assets/banner-772x250.jpg',
            'high' => CONTACTINBOX_URL . 'assets/banner-1544x500.jpg',
        ];

        // Icons
        $data->icons = [
            '1x' => CONTACTINBOX_URL . 'assets/icon-128x128.png',
            '2x' => CONTACTINBOX_URL . 'assets/icon-256x256.png',
        ];

        // Screenshots
        $data->screenshots = [
            [
                'src' => CONTACTINBOX_URL . 'assets/screenshot-1.png',
                'caption' => 'Dashboard with real-time analytics',
            ],
            [
                'src' => CONTACTINBOX_URL . 'assets/screenshot-2.png',
                'caption' => 'Unified inbox with smart filtering',
            ],
            [
                'src' => CONTACTINBOX_URL . 'assets/screenshot-3.png',
                'caption' => 'Contact management interface',
            ],
            [
                'src' => CONTACTINBOX_URL . 'assets/screenshot-4.png',
                'caption' => 'Form settings with spam protection',
            ],
        ];

        return $data;
    }

    private function get_description(): string {
        return '<p><strong>Contact Inbox</strong> is a powerful contact form and inbox management plugin that helps you organize and respond to customer inquiries efficiently. Built with performance and user experience in mind.</p>

<h3>Core Features</h3>
<ul>
<li><strong>Secure Message Inbox</strong> - Centralized hub for all contact form submissions</li>
<li><strong>Real-Time Analytics</strong> - Track submission trends and monitor performance</li>
<li><strong>GDPR Compliant</strong> - Built-in privacy tools with automatic deletion tokens</li>
<li><strong>Spam Protection</strong> - reCAPTCHA v3 and honeypot fields</li>
<li><strong>Easy to Use</strong> - Simple shortcode, Gutenberg block, and Elementor widget</li>
</ul>

<h3>🎯 Upgrade to Pro</h3>
<p>Need enterprise features? <strong>Contact Inbox Pro</strong> includes:</p>
<ul>
<li>Salesforce CRM Integration</li>
<li>REST API & Webhooks</li>
<li>SMS Notifications</li>
<li>Advanced Intent Classification</li>
<li>Email Automation</li>
<li>Priority Support</li>
</ul>

<p><a href="https://github.com/bizjaved/contact-inbox-free" target="_blank"><strong>Learn more about Contact Inbox Pro →</strong></a></p>';
    }

    private function get_features(): string {
        return '<h2>📋 Complete Feature List</h2><h4>Form Management</h4><ul><li>Simple shortcode: <code>[contact_inbox_form]</code></li><li>Gutenberg block support</li><li>Elementor widget integration</li><li>Customizable form fields (name, email, phone, subject, message)</li><li>File attachments with MIME type validation</li><li>Ajax submission with validation</li><li>Conditional field visibility</li><li>Multi-step forms (coming soon)</li></ul><h4>Inbox & Contact Management</h4><ul><li>Unified inbox with tabs (Main, Spam, Archived)</li><li>Advanced search and filtering</li><li>Bulk actions (archive, delete, mark as spam)</li><li>Intent classification (sales, support, general)</li><li>Contact database with duplicate detection</li><li>Phone number normalization and validation</li><li>Custom contact fields</li><li>Export contacts (CSV, JSON)</li></ul><h4>Analytics & Reporting</h4><ul><li>Real-time dashboard widgets</li><li>Submission trends and statistics</li><li>Intent distribution analysis</li><li>Response time tracking</li><li>Spam detection metrics</li><li>Performance monitoring</li><li>Custom date ranges</li><li>Exportable reports</li></ul><h4>Security & Spam Protection</h4><ul><li>reCAPTCHA v3 integration</li><li>Honeypot fields</li><li>Rate limiting per IP</li><li>Blacklist/whitelist management</li><li>AI-powered spam classification</li><li>Nonce validation</li><li>SQL injection protection</li><li>XSS prevention</li></ul><h4>GDPR & Privacy</h4><ul><li>One-click data deletion tokens</li><li>Automatic deletion of old submissions</li><li>Consent checkbox management</li><li>Privacy policy links</li><li>GDPR deletion log</li><li>Data export for users</li><li>Right to be forgotten compliance</li></ul>';
    }

    private function get_installation(): string {
        return '<ol>
<li>Upload the plugin files to <code>/wp-content/plugins/contact-inbox-free</code></li>
<li>Activate the plugin through the Plugins menu in WordPress</li>
<li>Click "Get Started" from the plugin action links for a quick walkthrough</li>
<li>Add the shortcode <code>[contact_inbox_form]</code> to any page or post</li>
<li>Configure your settings under Contact Inbox → Settings</li>
</ol>

<h3>Quick Start</h3>
<p><strong>Basic Contact Form:</strong></p>
<pre><code>[contact_inbox_form]</code></pre>

<p><strong>Custom Form with Title:</strong></p>
<pre><code>[contact_inbox_form title="Get in Touch"]</code></pre>

<p><strong>Form with Subject Field Disabled:</strong></p>
<pre><code>[contact_inbox_form show_subject="false"]</code></pre>

<h3>Requirements</h3>
<ul>
<li>WordPress 6.4 or higher (tested up to 6.9.1)</li>
<li>PHP 7.4 or higher</li>
<li>MySQL 5.7 or higher / MariaDB 10.2 or higher</li>
<li>SSL certificate recommended for reCAPTCHA</li>
</ul>';
    }

    private function get_faq(): string {
        return '<h4>How do I add a contact form to my page?</h4>
<p>Simply add the shortcode <code>[contact_inbox_form]</code> to any page, post, or widget. You can also use the Gutenberg block or Elementor widget.</p>

<h4>Is this plugin GDPR compliant?</h4>
<p>Yes! Contact Inbox includes GDPR compliance tools like one-click deletion tokens, automatic data cleanup, consent management, and detailed deletion logs.</p>

<h4>Does it work with page builders?</h4>
<p>Absolutely! Contact Inbox works seamlessly with Elementor, Gutenberg, and any other page builder through the shortcode.</p>

<h4>Can I connect it to my CRM?</h4>
<p>Yes! The Pro version includes Salesforce CRM integration with automatic lead creation and custom field mapping. REST API webhooks are also available for custom integrations.</p>

<h4>How does spam protection work?</h4>
<p>Multiple layers: reCAPTCHA v3, honeypot fields, rate limiting, IP blacklisting, and AI-powered intent classification work together to block 99% of spam.</p>

<h4>Can I export my contacts?</h4>
<p>Yes, you can export all contacts and submissions to CSV or JSON format from the Contacts page.</p>

<h4>What file types are supported for attachments?</h4>
<p>By default: JPG, JPEG, PNG, GIF, BMP, PDF, DOC, DOCX, XLS, XLSX, TXT, CSV. You can customize allowed types in settings.</p>

<h4>Does it support multi-site?</h4>
<p>Yes, Contact Inbox is fully compatible with WordPress Multisite installations.</p>

<h4>Is there developer documentation?</h4>
<p>Full developer documentation with hooks, filters, and API examples is available on GitHub: <a href="https://github.com/bizjaved/contact-inbox-free" target="_blank">github.com/bizjaved/contact-inbox-free</a></p>';
    }

    private function get_changelog(): string {
        return '<h4>Version 0.1.0 - February 10, 2026</h4>
<ul>
<li><strong>New:</strong> Initial release</li>
<li><strong>New:</strong> Unified inbox with smart filtering</li>
<li><strong>New:</strong> Real-time analytics dashboard</li>
<li><strong>New:</strong> Salesforce CRM integration</li>
<li><strong>New:</strong> GDPR compliance tools</li>
<li><strong>New:</strong> reCAPTCHA v3 spam protection</li>
<li><strong>New:</strong> Intent classification system</li>
<li><strong>New:</strong> Phone field validation</li>
<li><strong>New:</strong> File attachment support</li>
<li><strong>New:</strong> Email automation with templates</li>
<li><strong>New:</strong> REST API endpoints</li>
<li><strong>New:</strong> Webhook notifications</li>
<li><strong>New:</strong> Contact management database</li>
<li><strong>New:</strong> Bulk actions for inbox</li>
<li><strong>New:</strong> Custom form fields</li>
<li><strong>New:</strong> Gutenberg block support</li>
<li><strong>New:</strong> Elementor widget</li>
<li><strong>New:</strong> Get Started onboarding page</li>
</ul>

<p><a href="https://github.com/bizjaved/contact-inbox-free/blob/main/CHANGELOG.md" target="_blank">View full changelog on GitHub</a></p>';
    }
}
