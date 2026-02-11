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
        // (Note: Now also implemented at top level in contact-inbox.php for reliability)
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
            'screenshots' => $this->get_screenshots(),
            'documentation' => $this->get_documentation(),
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
                'caption' => 'Dashboard with submission metrics and trends',
            ],
            [
                'src' => CONTACTINBOX_URL . 'assets/screenshot-2.png',
                'caption' => 'Unified inbox with search and filtering',
            ],
            [
                'src' => CONTACTINBOX_URL . 'assets/screenshot-3.png',
                'caption' => 'Submission detail view with message metadata',
            ],
            [
                'src' => CONTACTINBOX_URL . 'assets/screenshot-4.png',
                'caption' => 'Advanced form settings with reCAPTCHA and spam protection',
            ],
        ];

        return $data;
    }

    private function get_description(): string {
        return '<p><strong>Contact Inbox</strong> transforms your WordPress site into a simple and effective contact management system with secure inbox management.</p>

<p>Built with performance and security in mind, Contact Inbox provides the essentials you need to manage customer communications:</p>

<ul>
<li><strong>Secure Message Inbox</strong> - Centralized hub for all contact form submissions with filtering</li>
<li><strong>Basic Analytics</strong> - Track submission trends and response times</li>
<li><strong>Smart Spam Protection</strong> - reCAPTCHA v3 and honeypot fields</li>
<li><strong>Email Notifications</strong> - SMTP support for reliable delivery</li>
<li><strong>Easy to Use</strong> - Simple shortcode, Gutenberg block, and Elementor widget</li>
</ul>

<h3>✨ Why Choose Contact Inbox?</h3>

<p><strong>Performance First:</strong> Optimized database queries ensure your site stays fast.</p>

<p><strong>Developer Friendly:</strong> Hooks and filters make customization easy, with clean, well-structured code.</p>

<p><strong>User Experience:</strong> Clean, intuitive interface with powerful search and filtering.</p>

<p><strong>Rock-Solid Reliability:</strong> Solid logging and careful error handling help keep operations stable.</p>';
    }

    private function get_features(): string {
        return '<div class="plugin-info-content">
        <h2>📋 Feature List</h2>
        
        <h4>Form Management</h4>
        <ul>
            <li>Simple shortcode: <code>[contact_inbox_form]</code></li>
            <li>Gutenberg block support</li>
            <li>Elementor widget integration</li>
            <li>Customizable form fields (name, email, phone, subject, message)</li>
            <li>Ajax submission with validation</li>
            <li>Mobile-responsive design</li>
        </ul>
        
        <h4>Inbox Management</h4>
        <ul>
            <li>Unified inbox for all submissions</li>
            <li>Search and filtering</li>
            <li>Bulk actions (archive, delete, mark as spam)</li>
            <li>Status tracking (unread, read, archived, spam)</li>
        </ul>
        
        <h4>Analytics & Reporting</h4>
        <ul>
            <li>Dashboard widgets</li>
            <li>Submission statistics</li>
            <li>Basic metrics tracking</li>
        </ul>
        
        <h4>Security & Spam Protection</h4>
        <ul>
            <li>reCAPTCHA v3 integration</li>
            <li>Honeypot fields</li>
            <li>Rate limiting per IP</li>
            <li>Nonce validation</li>
            <li>SQL injection protection</li>
            <li>XSS prevention</li>
        </ul>
        
        <h4>Email Notifications</h4>
        <ul>
            <li>SMTP configuration support</li>
            <li>Admin notifications</li>
            <li>User confirmation emails</li>
            <li>Custom email templates</li>
        </ul>
        
        <h3>🚀 Upgrade to Pro</h3>
        <p>Need more features? Learn about <strong>Contact Inbox Pro</strong> for additional capabilities and priority support.</p>
        </div>';
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

<h4>Does it work with page builders?</h4>
<p>Absolutely! Contact Inbox works seamlessly with Elementor, Gutenberg, and any other page builder through the shortcode.</p>

<h4>How does spam protection work?</h4>
<p>Multiple layers: reCAPTCHA v3, honeypot fields, and rate limiting work together to block spam effectively.</p>

<h4>Can I customize email notifications?</h4>
<p>Yes! Email templates are customizable, and you can configure SMTP for reliable email delivery.</p>

<h4>Does it support multi-site?</h4>
<p>Yes, Contact Inbox is fully compatible with WordPress Multisite installations.</p>

<h4>Is there developer documentation?</h4>
<p>Full developer documentation with hooks and filters is available on GitHub: <a href="https://github.com/bizjaved/contact-inbox-free" target="_blank">github.com/bizjaved/contact-inbox-free</a></p>

<h4>What features are in the Pro version?</h4>
<p>Pro offers additional capabilities and priority support. <a href="https://github.com/bizjaved/contact-inbox-pro" target="_blank">Learn more</a></p>';
    }

    private function get_changelog(): string {
        return '<h4>Version 1.0 - February 10, 2026</h4>
<ul>
<li><strong>New:</strong> Initial release</li>
<li><strong>New:</strong> Contact form with shortcode, Gutenberg block, and Elementor widget</li>
<li><strong>New:</strong> Unified inbox with filtering</li>
<li><strong>New:</strong> Basic analytics dashboard</li>
<li><strong>New:</strong> reCAPTCHA v3 spam protection</li>
<li><strong>New:</strong> SMTP email notifications</li>
<li><strong>New:</strong> Search and bulk actions</li>
</ul>

<p><a href="https://github.com/bizjaved/contact-inbox-free/blob/main/CHANGELOG.md" target="_blank">View full changelog on GitHub</a></p>';
    }

    private function get_screenshots(): string {
        return '<div class="plugin-info-screenshots">
<h3>Dashboard with Analytics</h3>
<p><img src="' . CONTACTINBOX_URL . 'assets/screenshot-1.png" alt="Dashboard with analytics and submission metrics" /></p>
<p>The main dashboard provides analytics with submission tracking and performance metrics at a glance.</p>

<h3>Unified Inbox with Filtering</h3>
<p><img src="' . CONTACTINBOX_URL . 'assets/screenshot-2.png" alt="Unified inbox with filtering" /></p>
<p>Manage all your contact form submissions in one place with search, filters, and bulk actions for efficient workflow.</p>

<h3>Contact Management</h3>
<p><img src="' . CONTACTINBOX_URL . 'assets/screenshot-3.png" alt="Contact management interface" /></p>
<p>View submission details with complete message history and internal notes.</p>

<h3>Form Settings & Configuration</h3>
<p><img src="' . CONTACTINBOX_URL . 'assets/screenshot-4.png" alt="Form settings with reCAPTCHA and spam protection" /></p>
<p>Easy-to-use settings panel with reCAPTCHA integration, spam protection, and notification customization.</p>
</div>';
    }

    private function get_documentation(): string {
        return '<div style="max-width: 900px; margin: 0 auto; padding: 20px;">

<h2 style="color: #0073aa; border-bottom: 3px solid #0073aa; padding-bottom: 10px; margin-bottom: 25px;">📚 Complete Documentation</h2>

<div style="background: #f0f6fc; border-left: 4px solid #0073aa; padding: 15px; margin-bottom: 30px;">
<p style="margin: 0;"><strong>👋 Welcome to Contact Inbox!</strong> This comprehensive guide will help you get the most out of your contact form and inbox management system.</p>
</div>

<!-- Quick Navigation -->
<div style="background: #fff; border: 1px solid #ddd; padding: 15px; margin-bottom: 30px;">
<h3 style="margin-top: 0;">📑 Quick Navigation</h3>
<ul style="columns: 2; -webkit-columns: 2; -moz-columns: 2; margin: 0;">
<li><a href="#getting-started">Getting Started</a></li>
<li><a href="#forms">Form Integration</a></li>
<li><a href="#inbox">Inbox Management</a></li>
<li><a href="#settings">Configuration</a></li>
<li><a href="#security">Security Setup</a></li>
<li><a href="#email">Email Delivery</a></li>
<li><a href="#analytics">Analytics</a></li>
<li><a href="#developers">Developer Resources</a></li>
<li><a href="#troubleshooting">Troubleshooting</a></li>
<li><a href="#support">Support</a></li>
</ul>
</div>

<!-- Getting Started -->
<h2 id="getting-started" style="color: #0073aa; margin-top: 40px;">🚀 Getting Started</h2>

<h3>Initial Setup (5 Minutes)</h3>
<ol style="line-height: 1.8;">
<li><strong>Activate Plugin:</strong> If not already active, go to Plugins → Installed Plugins</li>
<li><strong>Launch Setup Wizard:</strong> Click "Get Started" button in plugin row</li>
<li><strong>Follow Wizard Steps:</strong> Configure basic settings, email, and security</li>
<li><strong>Add Your First Form:</strong> Copy the shortcode from the wizard</li>
<li><strong>Test Submission:</strong> Submit a test message to verify everything works</li>
</ol>

<div style="background: #fffbcc; border-left: 4px solid #ffeb3b; padding: 12px; margin: 15px 0;">
<strong>💡 Pro Tip:</strong> Access the Get Started guide anytime from <strong>Contact Inbox → Get Started</strong> in your WordPress admin menu.
</div>

<!-- Form Integration -->
<h2 id="forms" style="color: #0073aa; margin-top: 40px;">📝 Form Integration Methods</h2>

<h3>Method 1: Shortcode (Recommended)</h3>
<p>The simplest way to add a contact form to any page, post, or widget area.</p>

<h4>Basic Usage</h4>
<pre style="background: #f5f5f5; padding: 15px; border-radius: 4px; overflow-x: auto;"><code>[contact_inbox_form]</code></pre>

<h4>With Custom Options</h4>
<pre style="background: #f5f5f5; padding: 15px; border-radius: 4px; overflow-x: auto;"><code>[contact_inbox_form 
    title="Get in Touch" 
    show_subject="true" 
    show_phone="true"
    submit_text="Send Message"
    redirect_url="https://yoursite.com/thank-you"
]</code></pre>

<h4>Available Shortcode Attributes</h4>
<table style="width: 100%; border-collapse: collapse; margin: 15px 0;">
<thead style="background: #f0f0f0;">
<tr>
<th style="padding: 10px; border: 1px solid #ddd; text-align: left;">Attribute</th>
<th style="padding: 10px; border: 1px solid #ddd; text-align: left;">Default</th>
<th style="padding: 10px; border: 1px solid #ddd; text-align: left;">Description</th>
</tr>
</thead>
<tbody>
<tr>
<td style="padding: 8px; border: 1px solid #ddd;"><code>title</code></td>
<td style="padding: 8px; border: 1px solid #ddd;">"Contact Us"</td>
<td style="padding: 8px; border: 1px solid #ddd;">Form heading text</td>
</tr>
<tr>
<td style="padding: 8px; border: 1px solid #ddd;"><code>show_subject</code></td>
<td style="padding: 8px; border: 1px solid #ddd;">true</td>
<td style="padding: 8px; border: 1px solid #ddd;">Show subject field</td>
</tr>
<tr>
<td style="padding: 8px; border: 1px solid #ddd;"><code>show_phone</code></td>
<td style="padding: 8px; border: 1px solid #ddd;">true</td>
<td style="padding: 8px; border: 1px solid #ddd;">Show phone number field</td>
</tr>
<tr>
<td style="padding: 8px; border: 1px solid #ddd;"><code>submit_text</code></td>
<td style="padding: 8px; border: 1px solid #ddd;">"Send Message"</td>
<td style="padding: 8px; border: 1px solid #ddd;">Submit button text</td>
</tr>
<tr>
<td style="padding: 8px; border: 1px solid #ddd;"><code>redirect_url</code></td>
<td style="padding: 8px; border: 1px solid #ddd;">-</td>
<td style="padding: 8px; border: 1px solid #ddd;">Redirect after submission</td>
</tr>
</tbody>
</table>

<h3>Method 2: Gutenberg Block</h3>
<ol style="line-height: 1.8;">
<li>Open the page/post editor in Gutenberg</li>
<li>Click the <strong>+</strong> button to add a new block</li>
<li>Search for <strong>"Contact Inbox"</strong></li>
<li>Click to insert the Contact Inbox Form block</li>
<li>Configure options in the block settings sidebar</li>
<li>Preview and publish</li>
</ol>

<h3>Method 3: Elementor Widget</h3>
<ol style="line-height: 1.8;">
<li>Edit your page with Elementor</li>
<li>Search for <strong>"Contact Inbox"</strong> in the widgets panel</li>
<li>Drag the widget to your desired location</li>
<li>Customize appearance, layout, and behavior in the widget settings</li>
<li>Style with Elementor\'s visual controls</li>
<li>Update the page</li>
</ol>

<!-- Inbox Management -->
<h2 id="inbox" style="color: #0073aa; margin-top: 40px;">📬 Inbox Management</h2>

<h3>Accessing Your Inbox</h3>
<p>Go to <strong>Contact Inbox → Inbox</strong> to view all submissions.</p>

<h3>Key Features</h3>

<h4>🔍 Search & Filter</h4>
<ul>
<li><strong>Search by:</strong> Name, email, subject, or message content</li>
<li><strong>Filter by:</strong> Status (unread, read, archived, spam)</li>
<li><strong>Sort by:</strong> Date, name, email, status</li>
<li><strong>Date range:</strong> Filter submissions by custom date range</li>
</ul>

<h4>⚡ Bulk Actions</h4>
<ul>
<li>Mark multiple submissions as read/unread</li>
<li>Archive submissions in bulk</li>
<li>Delete multiple entries at once</li>
<li>Move to spam folder</li>
</ul>

<h4>📊 Submission Details</h4>
<p>Click any submission to view:</p>
<ul>
<li>Complete message content</li>
<li>Sender information</li>
<li>Submission metadata (IP, user agent, timestamp)</li>
</ul>

<!-- Configuration -->
<h2 id="settings" style="color: #0073aa; margin-top: 40px;">⚙️ Configuration Guide</h2>

<h3>Settings Location</h3>
<p>Access all settings at <strong>Contact Inbox → Settings</strong></p>

<h3>Settings Tabs</h3>

<h4>1️⃣ General Settings</h4>
<ul>
<li>Form behavior and appearance</li>
<li>Default success/error messages</li>
<li>Field visibility defaults</li>
<li>Date and time formats</li>
</ul>

<h4>2️⃣ Email Notifications</h4>
<ul>
<li>Admin notification settings</li>
<li>User confirmation emails</li>
<li>Email templates</li>
<li>SMTP configuration</li>
</ul>

<h4>3️⃣ Security Settings</h4>
<ul>
<li>reCAPTCHA configuration</li>
<li>Spam protection rules</li>
<li>Rate limiting settings</li>
<li>IP blocking management</li>
</ul>

<h4>4️⃣ Privacy</h4>
<ul>
<li>Privacy policy integration</li>
<li>Data management options</li>
</ul>

<!-- Security -->
<h2 id="security" style="color: #0073aa; margin-top: 40px;">🔒 Security & Spam Protection</h2>

<h3>Setting Up Google reCAPTCHA v3</h3>
<ol style="line-height: 1.8;">
<li>Visit <a href="https://www.google.com/recaptcha/admin" target="_blank">Google reCAPTCHA Admin</a></li>
<li>Register your site and select <strong>reCAPTCHA v3</strong></li>
<li>Copy your <strong>Site Key</strong> and <strong>Secret Key</strong></li>
<li>Go to <strong>Contact Inbox → Settings → Security</strong></li>
<li>Paste keys and enable reCAPTCHA</li>
<li>Adjust spam threshold (recommended: 0.5)</li>
<li>Save settings</li>
</ol>

<h3>Additional Security Layers</h3>

<h4>Honeypot Protection</h4>
<p>Automatically enabled - invisible fields trap spam bots without bothering users.</p>

<h4>Rate Limiting</h4>
<p>Configure maximum submissions per IP address per time period:</p>
<ul>
<li>Default: 5 submissions per hour per IP</li>
<li>Prevents spam floods</li>
<li>Configurable in Settings → Security</li>
</ul>

<h4>IP Blocking</h4>
<p>Block specific IPs or IP ranges:</p>
<ul>
<li>Go to <strong>Contact Inbox → Settings → Security → Blocked IPs</strong></li>
<li>Add IPs one per line</li>
<li>Supports wildcards (e.g., 123.456.*.*)</li>
</ul>

<!-- Email Delivery -->
<h2 id="email" style="color: #0073aa; margin-top: 40px;">📧 Email Delivery Setup</h2>

<h3>SMTP Configuration (Recommended)</h3>
<p>For reliable email delivery, configure SMTP instead of using PHP mail():</p>

<h4>Popular SMTP Providers</h4>

<strong>Gmail Example:</strong>
<pre style="background: #f5f5f5; padding: 15px; border-radius: 4px;"><code>Host: smtp.gmail.com
Port: 587
Encryption: TLS
Username: your-email@gmail.com
Password: [App Password]</code></pre>

<strong>SendGrid Example:</strong>
<pre style="background: #f5f5f5; padding: 15px; border-radius: 4px;"><code>Host: smtp.sendgrid.net
Port: 587
Encryption: TLS
Username: apikey
Password: [Your SendGrid API Key]</code></pre>

<h3>Email Templates</h3>
<p>Customize email templates at <strong>Settings → Email → Templates</strong></p>

<h4>Available Variables:</h4>
<ul>
<li><code>{name}</code> - Sender\'s name</li>
<li><code>{email}</code> - Sender\'s email</li>
<li><code>{phone}</code> - Phone number</li>
<li><code>{subject}</code> - Message subject</li>
<li><code>{message}</code> - Message content</li>
<li><code>{site_name}</code> - Your site name</li>
<li><code>{site_url}</code> - Your site URL</li>
<li><code>{date}</code> - Submission date</li>
</ul>

<h3>Testing Email Delivery</h3>
<ol style="line-height: 1.8;">
<li>Go to <strong>Contact Inbox → Tools → Email Test</strong></li>
<li>Enter a test email address</li>
<li>Click <strong>Send Test Email</strong></li>
<li>Check email delivery and logs</li>
</ol>

<!-- Analytics -->
<h2 id="analytics" style="color: #0073aa; margin-top: 40px;">📊 Analytics & Reporting</h2>

<h3>Dashboard Widgets</h3>

<h4>Today\'s Snapshot</h4>
<p>Real-time metrics for today\'s activity:</p>
<ul>
<li>Total submissions</li>
<li>Average response time</li>
<li>Spam blocked</li>
</ul>

<h4>Submission Metrics</h4>
<p>Visual charts showing:</p>
<ul>
<li>Submission trends over time</li>
<li>Day-of-week patterns</li>
</ul>

<!-- Developer Resources -->
<h2 id="developers" style="color: #0073aa; margin-top: 40px;">👨‍💻 Developer Resources</h2>

<h3>Action Hooks</h3>
<pre style="background: #f5f5f5; padding: 15px; border-radius: 4px; overflow-x: auto;"><code>// After successful submission
add_action( \'contact_inbox_after_submission\', function( $submission_id, $data ) {
    // Your custom code
    error_log( "New submission: " . $submission_id );
}, 10, 2 );

// Before email is sent
add_action( \'contact_inbox_before_email\', function( $submission_data ) {
    // Modify or log email data
}, 10, 1 );

// When spam is detected
add_action( \'contact_inbox_spam_detected\', function( $submission_id, $reason ) {
    // Handle spam detection
}, 10, 2 );
}</code></pre>

<h3>Filter Hooks</h3>
<pre style="background: #f5f5f5; padding: 15px; border-radius: 4px; overflow-x: auto;"><code>// Modify form fields
add_filter( \'contact_inbox_form_fields\', function( $fields ) {
    $fields[\'custom_field\'] = [
        \'type\' => \'text\',
        \'label\' => \'Custom Field\',
        \'required\' => false
    ];
    return $fields;
} );

// Customize email content
add_filter( \'contact_inbox_email_content\', function( $content, $submission ) {
    $content .= "\n\nCustom footer text";
    return $content;
}, 10, 2 );

// Modify validation rules
add_filter( \'contact_inbox_validation_rules\', function( $rules ) {
    $rules[\'email\'][\'custom_check\'] = \'my_email_validator\';
    return $rules;
} );

// Customize spam threshold
add_filter( \'contact_inbox_spam_threshold\', function( $threshold ) {
    return 0.3; // More strict
} );</code></pre>

<h3>WP-CLI Commands</h3>
<pre style="background: #f5f5f5; padding: 15px; border-radius: 4px;"><code># Clean up old data
wp contact-inbox cleanup --days=90 --dry-run

# View statistics
wp contact-inbox stats --period=week
</code></pre>

<h3>Code Examples</h3>
<p>Find complete examples in the <code>/examples</code> directory for various integration scenarios.</p>

<!-- Troubleshooting -->
<h2 id="troubleshooting" style="color: #0073aa; margin-top: 40px;">🔧 Troubleshooting Guide</h2>

<h3>Forms Not Submitting</h3>
<div style="background: #fff3cd; border-left: 4px solid #ffc107; padding: 12px; margin: 15px 0;">
<strong>Symptoms:</strong> Form doesn\'t submit or shows generic error
</div>
<p><strong>Solutions:</strong></p>
<ul>
<li>✅ Check JavaScript console (F12) for errors</li>
<li>✅ Verify JavaScript is enabled in browser</li>
<li>✅ Check if reCAPTCHA keys are correct</li>
<li>✅ Review <strong>Contact Inbox → System Status</strong></li>
<li>✅ Temporarily disable other plugins to check for conflicts</li>
<li>✅ Clear browser cache and try again</li>
<li>✅ Check if theme is loading jQuery properly</li>
</ul>

<h3>Email Notifications Not Received</h3>
<div style="background: #fff3cd; border-left: 4px solid #ffc107; padding: 12px; margin: 15px 0;">
<strong>Symptoms:</strong> Submissions save but no email arrives
</div>
<p><strong>Solutions:</strong></p>
<ul>
<li>✅ Check spam/junk folder</li>
<li>✅ Verify email address in <strong>Settings → Email</strong></li>
<li>✅ Test email with <strong>Tools → Email Test</strong></li>
<li>✅ Configure SMTP (PHP mail() is unreliable)</li>
<li>✅ Check email logs at <strong>Contact Inbox → Email Logs</strong></li>
<li>✅ Verify SPF/DKIM records for your domain</li>
<li>✅ Consider using SendGrid, Mailgun, or similar service</li>
</ul>

<h3>Database Errors</h3>
<div style="background: #fff3cd; border-left: 4px solid #ffc107; padding: 12px; margin: 15px 0;">
<strong>Symptoms:</strong> Errors mentioning database or SQL
</div>
<p><strong>Solutions:</strong></p>
<ul>
<li>✅ Run <strong>Tools → Database Check</strong></li>
<li>✅ Verify database user permissions</li>
<li>✅ Check wp-config.php credentials</li>
<li>✅ Run <strong>Tools → Database Optimizer</strong></li>
<li>✅ Deactivate and reactivate plugin (recreates tables)</li>
<li>✅ Check WordPress debug log for details</li>
</ul>

<h3>reCAPTCHA Issues</h3>
<div style="background: #fff3cd; border-left: 4px solid #ffc107; padding: 12px; margin: 15px 0;">
<strong>Symptoms:</strong> reCAPTCHA errors or not showing
</div>
<p><strong>Solutions:</strong></p>
<ul>
<li>✅ Verify you\'re using reCAPTCHA <strong>v3</strong> keys (not v2)</li>
<li>✅ Domain must be added in Google reCAPTCHA admin</li>
<li>✅ Site requires SSL/HTTPS for production</li>
<li>✅ Check browser console for reCAPTCHA errors</li>
<li>✅ Clear site cache and browser cache</li>
</ul>

<h3>Performance Issues</h3>
<div style="background: #fff3cd; border-left: 4px solid #ffc107; padding: 12px; margin: 15px 0;">
<strong>Symptoms:</strong> Slow admin pages or dashboard
</div>
<p><strong>Solutions:</strong></p>
<ul>
<li>✅ Enable object caching (Redis, Memcached)</li>
<li>✅ Increase PHP memory limit if needed</li>
<li>✅ Optimize database tables</li>
</ul>

<h3>Diagnostic Tools</h3>
<p>Use built-in diagnostic tools:</p>
<ul>
<li><strong>System Status:</strong> Contact Inbox → System Status</li>
<li><strong>Email Test:</strong> Tools → Email Test</li>
<li><strong>Debug Logs:</strong> Enable WordPress debug logging</li>
</ul>

<!-- Support -->
<h2 id="support" style="color: #0073aa; margin-top: 40px;">🆘 Support & Resources</h2>

<h3>Documentation Links</h3>
<ul style="line-height: 1.8;">
<li>📖 <a href="https://github.com/bizjaved/contact-inbox-free" target="_blank">GitHub Repository</a></li>
<li>📖 <a href="https://github.com/bizjaved/contact-inbox-free/wiki" target="_blank">Wiki Documentation</a></li>
<li>🐛 <a href="https://github.com/bizjaved/contact-inbox-free/issues" target="_blank">Issue Tracker</a></li>
<li>💬 <a href="https://github.com/bizjaved/contact-inbox-free/discussions" target="_blank">Community Discussions</a></li>
<li>📝 <a href="https://github.com/bizjaved/contact-inbox-free/blob/main/CHANGELOG.md" target="_blank">Changelog</a></li>
</ul>

<h3>Getting Help</h3>
<ol style="line-height: 1.8;">
<li><strong>Search Documentation:</strong> Check this guide and wiki first</li>
<li><strong>Review FAQ:</strong> See the FAQ tab for common questions</li>
<li><strong>Check GitHub Issues:</strong> Search existing issues for solutions</li>
<li><strong>System Status:</strong> Run diagnostic tools in WordPress admin</li>
<li><strong>Create Issue:</strong> Submit detailed bug report on GitHub</li>
</ol>

<h3>Contributing</h3>
<p>Want to contribute? We welcome:</p>
<ul>
<li>🐛 Bug reports with detailed reproduction steps</li>
<li>💡 Feature requests and ideas</li>
<li>🔧 Pull requests for bug fixes or features</li>
<li>📝 Documentation improvements</li>
<li>🌍 Translations in your language</li>
<li>⭐ Stars on GitHub</li>
</ul>

<h3>Stay Updated</h3>
<ul>
<li>⭐ Star the <a href="https://github.com/bizjaved/contact-inbox-free" target="_blank">GitHub repo</a></li>
<li>👀 Watch for new releases</li>
<li>📢 Follow development updates</li>
<li>🔔 Subscribe to release notifications</li>
</ul>

<div style="background: #e7f3ff; border: 1px solid #2196f3; padding: 20px; margin: 30px 0; text-align: center; border-radius: 4px;">
<h3 style="margin-top: 0; color: #1976d2;">Need More Features?</h3>
<p>Learn about <strong>Contact Inbox Pro</strong> for additional capabilities and priority support.</p>
<p style="margin-bottom: 0;"><a href="https://github.com/bizjaved/contact-inbox-pro" target="_blank" style="display: inline-block; background: #2196f3; color: white; padding: 12px 30px; text-decoration: none; border-radius: 4px; font-weight: bold; margin-top: 10px;">Learn More About Pro →</a></p>
</div>

<hr style="margin: 40px 0; border: none; border-top: 1px solid #ddd;" />

<p style="text-align: center; color: #666; font-size: 0.9em;">
<strong>Developed with ❤️ by <a href="https://linkedin.com/in/bizjaved" target="_blank">Javed Ahsan</a></strong><br />
Contact Inbox v' . CONTACTINBOX_VERSION . ' | <a href="https://github.com/bizjaved/contact-inbox-free/blob/main/LICENSE" target="_blank">GPL-3.0 License</a>
</p>

</div>';
    }
}
