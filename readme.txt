=== Contact Inbox ===
Contributors: Javed Ahsan
Donate link: https://github.com/sponsors/bizjaved
Tags: contact form, inbox, analytics, crm, salesforce, gdpr, recaptcha, smtp, elementor, gutenberg
Requires at least: 6.4
Tested up to: 6.7
Requires PHP: 7.4
Stable tag: 0.1.0
License: GPLv3 or later
License URI: https://www.gnu.org/licenses/gpl-3.0.html

Secure contact forms with inbox management, email notifications, reCAPTCHA v3, and basic analytics. Upgrade to Pro for CRM, GDPR, attachments, REST API, and advanced features.

== Description ==

**Contact Inbox** transforms your WordPress contact forms into a powerful communication hub with a secure inbox and essential analytics. Upgrade to Pro for CRM integration, GDPR tools, file attachments, REST API, and advanced reporting.

### 🚀 Why Choose Contact Inbox?

Contact Inbox goes beyond traditional contact forms by providing:

* **Secure Inbox** - Never lose a message. All submissions stored securely with full search and filtering
* **Real-Time Analytics** - Track submissions, conversion rates, response times, and user behavior
* **CRM Integration** - Automatic Salesforce sync with customizable field mapping
* **Enterprise Security** - reCAPTCHA v3, spam filtering, rate limiting, honeypot protection
* **GDPR Compliance** - Built-in consent management, data retention controls, and delete tokens
* **Developer-Friendly** - REST API, webhooks, extensive hooks/filters

### ✨ Core Features

**Form Builder & Integration**
* Simple shortcode: `[contact_inbox_form]`
* Native Gutenberg block support
* Elementor widget included
* Customizable fields and validation
* File attachment support with type/size limits
* Mobile-responsive design

**Inbox Management**
* Centralized admin inbox for all submissions
* Advanced search and filtering
* Bulk actions (mark read, archive, delete)
* Export to CSV/JSON
* Message threading and notes
* Status tracking (unread, read, archived, spam)

**Analytics & Insights**
* Submission metrics dashboard
* Conversion rate tracking
* Geographic distribution maps
* Device and browser statistics
* Intent classification (sales, support, feedback)
* Performance monitoring
* Email delivery tracking

**Security & Spam Protection**
* Google reCAPTCHA v3 integration
* Advanced spam filtering
* Rate limiting per IP/email
* Honeypot fields
* IP blacklisting
* Duplicate submission detection

**Email Notifications**
* SMTP configuration (Gmail, SendGrid, Mailgun, etc.)
* Custom email templates
* Admin and user notifications
* Queue system for reliable delivery
* Delivery status tracking
* Retry logic for failed sends

**CRM Integration**
* Salesforce automatic sync
* Custom field mapping
* Bi-directional updates
* Webhook notifications
* Queue-based processing
* Error handling and retry logic

**GDPR & Privacy**
* Consent checkbox management
* Data retention policies
* User data export
* One-click delete tokens
* Privacy policy integration
* Cookie-free operation option

**Developer Features**
* REST API with authentication
* Test token system for integrations
* Extensive action/filter hooks
* CLI commands (WP-CLI support)
* Webhook system
* Logging and debugging tools

### 📋 Perfect For

* **Business Websites** - Professional contact management
* **SaaS Platforms** - Lead capture with CRM integration
* **Support Teams** - Ticket-like inbox system
* **Marketing Agencies** - Multi-site analytics
* **E-commerce Stores** - Customer inquiry tracking
* **Developers** - Headless WordPress with REST API

### 🎯 Quick Start

1. Install and activate the plugin
2. Add `[contact_inbox_form]` to any page
3. Configure SMTP in Settings > Email
4. (Optional) Connect Salesforce in Settings > CRM
5. Start receiving and managing submissions!

### 🔗 External Services

This plugin may connect to external services (optional, user-configured):

* **Google reCAPTCHA** - Spam protection (requires API keys)
* **SMTP Servers** - Email delivery (Gmail, SendGrid, Mailgun, etc.)
* **Salesforce API** - CRM integration (requires OAuth credentials)

[Privacy Policy](https://www.google.com/recaptcha/about/)
[Terms of Service](https://policies.google.com/terms)

### 📚 Documentation & Support

* [GitHub Repository](https://github.com/bizjaved/contact-inbox-pro)
* [Documentation](https://github.com/bizjaved/contact-inbox-pro#readme)
* [Issue Tracker](https://github.com/bizjaved/contact-inbox-pro/issues)

== Installation ==

### Automatic Installation

1. Go to WordPress Admin > Plugins > Add New
2. Search for "Contact Inbox Pro"
3. Click "Install Now" and then "Activate"
4. Configure via Contact Inbox > Settings

### Manual Installation

1. Download the plugin zip file
2. Upload to `/wp-content/plugins/contact-inbox-pro/`
3. Activate through the 'Plugins' menu
4. Configure via Contact Inbox > Settings

### After Installation

1. **Add Form**: Insert `[contact_inbox_form]` on any page
2. **Configure SMTP**: Settings > Email > SMTP Settings
3. **Set Up reCAPTCHA** (optional): Settings > Security > reCAPTCHA
4. **Connect CRM** (optional): Settings > Integrations > Salesforce

### Requirements

* WordPress 6.4+
* PHP 7.4+
* MySQL 5.6+ or MariaDB 10.0+
* HTTPS recommended for reCAPTCHA

== Frequently Asked Questions ==

= How do I add the contact form to my site? =

Use the shortcode `[contact_inbox_form]` on any page or post. You can also use the Gutenberg "Contact Inbox Form" block or Elementor widget.

= Is this plugin GDPR compliant? =

Yes! The plugin includes GDPR features like consent checkboxes, data retention controls, user data export/deletion tools, and delete tokens for user privacy requests.

= Can I integrate with my CRM? =

Yes! Currently supports Salesforce with automatic sync, custom field mapping, and bi-directional updates. Connect via Settings > Integrations > Salesforce.

= Does it work with page builders? =

Yes! Includes native support for Elementor (widget) and Gutenberg (block). The shortcode works with any page builder.

= How does spam protection work? =

Multiple layers: Google reCAPTCHA v3, honeypot fields, rate limiting, duplicate detection, IP blacklisting, and machine learning-based spam classification.

= Can I customize email templates? =

Yes! Email templates are fully customizable via Settings > Email > Templates. Supports merge tags for dynamic content.

= Does it work on shared hosting? =

Yes! Optimized for shared hosting with no special server configuration required. Uses WordPress standards and efficient database queries.

= Can I export submission data? =

Yes! Export to CSV or JSON format. Available per-message or bulk export from the inbox view.

= Is there a REST API? =

Yes! Full REST API with authentication for external integrations. Includes test token system for development. Documentation in Settings > Integrations > REST API.

= How do I get support? =

Visit the [GitHub repository](https://github.com/bizjaved/contact-inbox-pro) to report issues or request features.

== Screenshots ==

1. **Public Contact Form** - Clean, responsive form with real-time validation
2. **Admin Inbox** - Centralized view of all submissions with filtering
3. **Analytics Dashboard** - Comprehensive metrics and insights
4. **Submission Detail** - Complete message view with actions
5. **Settings Panel** - Comprehensive configuration options
6. **CRM Integration** - Salesforce connection and field mapping
7. **Email Templates** - Customizable notification templates
8. **REST API Testing** - Built-in tools for API validation

== Changelog ==

= 0.1.0 - 2026-02-08 =
* Initial release
* Contact form with shortcode, Gutenberg block, and Elementor widget
* Secure admin inbox with search and filtering
* Analytics dashboard with submission metrics
* Salesforce CRM integration
* Google reCAPTCHA v3 support
* SMTP email delivery with queue system
* GDPR compliance features
* REST API with authentication
* Spam filtering and security features
* File attachment support
* Multi-language ready (i18n)

== Upgrade Notice ==

= 0.1.0 =
Initial release of Contact Inbox Pro. Enjoy enterprise-grade contact form management!

== Privacy & Data Collection ==

**What Data We Collect:**
* Contact form submissions (name, email, message as submitted by users)
* IP address and user agent for spam prevention
* Submission timestamps and form IDs for analytics

**Where Data is Stored:**
* All data stored in your WordPress database
* No data sent to external servers except configured services (SMTP, CRM, reCAPTCHA)

**Data Retention:**
* Configurable retention periods (default: 90 days)
* Automatic cleanup via scheduled tasks
* Manual delete and GDPR export available

**External Services (Optional):**
* Google reCAPTCHA (for spam protection)
* Your configured SMTP server (for email delivery)
* Salesforce (if CRM integration enabled)

== Credits ==

Developed by [Javed Ahsan](https://github.com/bizjaved)

Special thanks to:
* WordPress community for inspiration
* Chart.js for analytics visualization
* Contributors and testers

For support and contributions, visit [GitHub](https://github.com/bizjaved/contact-inbox-pro)