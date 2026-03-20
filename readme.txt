=== ContactIn ===
Contributors: javedahsan
Plugin URI: https://contactinbox.app/
Tags: contact form, inbox, analytics, recaptcha, elementor
Requires at least: 6.4
Tested up to: 6.9
Requires PHP: 7.4
Stable tag: 1.0
License: GPLv3 or later
License URI: https://www.gnu.org/licenses/gpl-3.0.html

Smart contact forms with intent classification, secure inbox, email notifications, reCAPTCHA v3 spam protection, and analytics.

== Description ==

**ContactIn** provides essential contact form management with intent classification, a secure inbox, and analytics - all completely free.

= What is ContactIn? =

ContactIn is a smart contact form plugin with automatic intent categorization. Every message submitted is analyzed and categorized by intent (Sales, Support, Feedback, Complaints, or Questions). No setup required - it just works.

**What you get with the free version:**
* 🤖 Automatic message categorization by intent
* Confidence scores showing classification accuracy
* Keywords that triggered the categorization
* Secure inbox with search and filtering
* Basic analytics
* Email notifications via SMTP
* reCAPTCHA v3 spam protection

**Want more? Upgrade to Pro for:**
* Adaptive learning system (learns from your corrections)
* Advanced classification rules
* Salesforce CRM sync
* GDPR compliance features

### 🚀 Why Choose ContactIn?

ContactIn provides intelligent essentials:

* **🤖 Intent Classification** - Automatically categorize every message
* **Secure Inbox** - All submissions stored securely with search and filtering
* **Smart Analytics** - Track submissions, response times, and message types
* **Spam Protection** - reCAPTCHA v3, rate limiting, honeypot protection
* **Email Notifications** - SMTP configuration for reliable delivery
* **Developer-Friendly** - Extensive hooks/filters

### ✨ Core Features

**Form Builder & Integration**
* Simple shortcode: `[contact_inbox_form]`
* Native Gutenberg block support
* Elementor widget included
* Customizable fields and validation
* Mobile-responsive design

**Inbox Management**
* Centralized admin inbox for all submissions
* Intent-based message categorization visible in list
* Advanced search and filtering
* Bulk actions (mark read, archive, delete)
* Status tracking (unread, read, archived, spam)

**Analytics & Insights**
* Submission metrics dashboard
* Message type breakdown (Sales, Support, Feedback, etc.)
* Categorization statistics
* Response time tracking
* Basic trend analysis

**Message Classification**
* Automatic intent categorization (Sales, Support, Feedback, Complaints, Questions)
* Confidence scoring for accuracy
* Keyword tracking and highlighting
* Instant message organization

**Security & Spam Protection**
* Google reCAPTCHA v3 integration
* Intelligent spam filtering
* Rate limiting per IP/email
* Honeypot fields
* Duplicate submission detection

**Email Notifications**
* SMTP configuration (Gmail, SendGrid, Mailgun, etc.)
* Custom email templates
* Admin and user notifications

**Developer Features**
* Extensive action/filter hooks
* CLI commands (WP-CLI support)
* Logging and debugging tools

### 🎯 Free vs Pro Comparison

**Free Version Includes:**
* ✅ Intent Classification (automatic message categorization)
* ✅ Confidence scoring
* ✅ Keyword highlighting
* ✅ Secure inbox with search/filtering
* ✅ Email notifications
* ✅ reCAPTCHA v3
* ✅ Gutenberg & Elementor support
* ✅ Basic analytics

**Only in Pro Version:**
* 💎 Adaptive Learning System (learns from your corrections)
* 💎 Custom Classification Rules
* 💎 Salesforce CRM Sync
* 💎 Advanced file handling
* 💎 GDPR compliance tools
* 💎 Priority support

### 🚀 Upgrade to Pro

Get advanced intent learning, CRM integration, and enterprise features. **ContactIn Pro** adds powerful automation and professional capabilities.

[Upgrade to Pro](https://contactinbox.app/)

### 📋 Perfect For

* **Business Websites** - Professional contact management with intelligent message prioritization
* **Small Businesses** - Smart inbox that auto-organizes customer inquiries
* **Bloggers** - Reader contact forms with automatic categorization
* **Freelancers** - Client communication with AI-powered organization
* **Agencies** - Manage submissions across clients with intelligent filtering
* **Developers** - Extensible with hooks, filters, and REST API

### 🎯 Quick Start

1. Install and activate the plugin
2. Add `[contact_inbox_form]` to any page or use the Gutenberg block
3. Configure SMTP in Settings > Email (optional)
4. Set up reCAPTCHA in Settings > Security (optional)
5. Start receiving and managing submissions

### 🔗 External Services

This plugin may connect to external services (optional, user-configured):

* **Google reCAPTCHA v3** (optional) - Used only when enabled in plugin settings for spam protection.
	* Data sent: reCAPTCHA token, visitor IP address.
	* When sent: during form submission verification.
	* Privacy Policy: [https://www.google.com/recaptcha/about/](https://www.google.com/recaptcha/about/)
	* Terms of Service: [https://policies.google.com/terms](https://policies.google.com/terms)

* **SMTP Provider** (optional) - Used only if you enable SMTP for outgoing email delivery.
	* Data sent: outgoing notification email content and recipient/sender metadata.
	* When sent: after a form submission triggers email notifications.
	* Privacy/Terms: depend on the SMTP provider you configure.

* **Freemius SDK** (optional account/opt-in) - Used for licensing, upgrade handling, and update/insight services.
	* Data sent: site URL, plugin version, WordPress/PHP environment details, and account data if you opt in.
	* When sent: after explicit opt-in/account connection and during SDK operations.
	* Privacy Policy: [https://freemius.com/privacy/](https://freemius.com/privacy/)
	* Terms of Service: [https://freemius.com/terms/](https://freemius.com/terms/)

* **Webhook Endpoints** (optional, user-defined) - Disabled by default; used only when you configure and enable webhook URLs.
	* Data sent: submission payload fields configured by the plugin.
	* When sent: after successful submission processing.
	* Privacy/Terms: depend on each destination service you configure.

### 📚 Documentation & Support

* [GitHub Repository](https://github.com/bizjaved/contact-inbox)
* [Documentation](https://github.com/bizjaved/contact-inbox#readme)
* [Issue Tracker](https://github.com/bizjaved/contact-inbox/issues)

== Installation ==

### Automatic Installation

1. Go to WordPress Admin > Plugins > Add New
2. Search for "ContactIn"
3. Click "Install Now" and then "Activate"
4. Configure via ContactIn > Settings

### Manual Installation

1. Download the plugin zip file
2. Upload to `/wp-content/plugins/contact-inbox/`
3. Activate through the 'Plugins' menu
4. Configure via ContactIn > Settings

### After Installation

1. **Add Form**: Insert `[contact_inbox_form]` on any page
2. **Configure SMTP** (optional): Settings > Email > SMTP Settings
3. **Set Up reCAPTCHA** (optional): Settings > Security > reCAPTCHA

### Requirements

* WordPress 6.4+
* PHP 7.4+
* MySQL 5.6+ or MariaDB 10.0+
* HTTPS recommended for reCAPTCHA

== Frequently Asked Questions ==

= How do I add the contact form to my site? =

Use the shortcode `[contact_inbox_form]` on any page or post. You can also use the Gutenberg "ContactIn Form" block or Elementor widget.

= Does it work with page builders? =

Yes! Includes native support for Elementor (widget) and Gutenberg (block). The shortcode works with any page builder.

= How does spam protection work? =

Multiple layers: Google reCAPTCHA v3, honeypot fields, rate limiting, and duplicate detection work together to block spam effectively.

= Can I customize email templates? =

Yes! Email templates are fully customizable via Settings > Email > Templates. Supports merge tags for dynamic content.

= Does it work on shared hosting? =

Yes! Optimized for shared hosting with no special server configuration required. Uses WordPress standards and efficient database queries.

= Does ContactIn have AI features? =

Yes! Every message submitted to your contact form is automatically analyzed and categorized by intent using machine learning. Messages are tagged as Sales inquiries, Support requests, Feedback, Complaints, or Questions. This happens automatically with no setup required. Confidence scores show you how accurate each categorization is.

= What's the difference between the free and Pro intent classification? =

**Free Version:**
* Automatic intent classification on every message
* Confidence scoring
* Keyword highlighting

**Pro Version Adds:**
* Adaptive learning system (learns from your corrections to improve over time)
* Custom classification rules for your business
* More advanced analytics

= What features are in the Pro version? =

Pro offers Adaptive Learning for message classification, Salesforce CRM sync, GDPR compliance tools, advanced file handling, SMS notifications, and priority support. Visit [ContactIn Pro](https://contactinbox.app/) for details.

= How do I get support? =

Visit the [GitHub repository](https://github.com/bizjaved/contact-inbox) to report issues or request features.

== External Services ==

This plugin communicates with the following external services under the conditions described below.

= 1. Google reCAPTCHA =

**What it is:** Google reCAPTCHA is a free anti-spam service provided by Google that helps protect contact forms from automated bot submissions.

**What data is sent and when:** When the optional reCAPTCHA integration is enabled by the site administrator, a verification request is sent to Google's servers every time a user submits the contact form. The request includes the reCAPTCHA response token generated in the visitor's browser and the site's secret key. No personally identifiable form data (name, email, message) is included in this request.

**Condition:** Only sent when reCAPTCHA is enabled in the plugin settings.

* Service provider: Google LLC
* Terms of Service: https://policies.google.com/terms
* Privacy Policy: https://policies.google.com/privacy
* API endpoint: https://www.google.com/recaptcha/api/siteverify

= 2. SMTP Email Server (User-Configured) =

**What it is:** The plugin can send email notifications (new submission alerts to admins, and confirmation copies to form submitters) via an SMTP server of the site administrator's choice. Common providers include Gmail, Outlook, SendGrid, and Amazon SES, but any SMTP-compatible server can be used.

**What data is sent and when:** When the SMTP feature is enabled and a contact form is submitted, the plugin sends an email through the configured SMTP server. The email contains the form submission data (such as the submitter's name, email address, and message). Emails are only transmitted when SMTP is enabled in the plugin settings.

**Condition:** Only sent when SMTP is enabled and a form is submitted.

* The SMTP host, credentials, and any applicable terms of service and privacy policy are determined solely by the provider chosen by the site administrator. Refer to your chosen provider's documentation.

= 3. Freemius =

**What it is:** Freemius is a software licensing, deployment, and analytics platform used to manage plugin licensing, deliver updates, and (with user consent) collect opt-in diagnostic and usage data.

**What data is sent and when:** Freemius collects plugin activation and deactivation events, WordPress environment information (PHP version, WordPress version, active plugins), and — only if the site administrator explicitly opts in — basic site and administrator information (site URL, admin email, admin name). If the administrator opts out or chooses to remain anonymous, only minimal non-identifying data is transmitted.

**Condition:** Licensing and update checks occur on plugin activation and on a periodic schedule. Diagnostic data is only sent with explicit opt-in consent from the administrator.

* Service provider: Freemius Inc.
* Terms of Service: https://freemius.com/terms/
* Privacy Policy: https://freemius.com/privacy/

= 4. Salesforce CRM (Pro Feature) =

**What it is:** Salesforce is a customer relationship management (CRM) platform. The Pro version of this plugin can optionally sync contact form submissions to a connected Salesforce account.

**What data is sent and when:** When Salesforce integration is configured and enabled in the Pro version, contact record data (name, email address, phone number, and other mapped fields from form submissions) is transmitted to the Salesforce REST API to create or update Contact records. GDPR-triggered deletion requests also send DELETE requests to remove the corresponding Salesforce Contact record.

**Condition:** Only sent when the Salesforce CRM integration is configured and enabled in the Pro plugin settings. This feature is not present in the free version.

* Service provider: Salesforce, Inc.
* Terms of Service: https://www.salesforce.com/company/legal/agreements/
* Privacy Policy: https://www.salesforce.com/company/privacy/

= 5. Outgoing Webhooks (User-Configured) =

**What it is:** The plugin supports sending form submission data to external webhook URLs configured by the site administrator. This allows integration with third-party automation services (e.g., Zapier, Make, or custom endpoints).

**What data is sent and when:** When a webhook URL is configured and a contact form is submitted, the plugin sends a JSON payload containing the form submission data (name, email, message, and other submitted fields) to the configured URL. Delivery is attempted asynchronously via a background queue.

**Condition:** Only sent when at least one webhook URL has been configured by the site administrator.

* The terms of service and privacy policy applicable to webhook delivery are determined by the third-party service chosen by the site administrator.

== Screenshots ==

1. Unified Inbox — Centralized message management with search, filtering, and bulk actions.
2. Contacts — Auto-created contact profiles with history, updates, and export-ready records.
3. Dashboard (Submissions) — Real-time submission trends, channel insights, and conversion signals.
4. Dashboard (System Performance) — Queue, delivery, and processing health metrics for operational visibility.
5. Maintenance & Operations — Cleanup, diagnostics, and reliability tools for long-term stability.
6. Salesforce Integration — OAuth connection, field mapping, and automated CRM synchronization.

== Changelog ==

= 1.0 - 2026-02-10 =

**🎉 Initial Release**

**🤖 AI Features**
* Automatic intent classification for all messages
* Machine learning categorization (Sales, Support, Feedback, Complaints, Questions)
* Confidence scoring for accuracy
* Keyword highlighting for transparency

**Form Features**
* Simple shortcode: `[contact_inbox_form]`
* Gutenberg block support
* Elementor widget integration
* Customizable form fields (name, email, phone, subject, message)
* AJAX form submission
* Mobile-responsive design

**Inbox Management**
* Centralized admin inbox with AI categorization
* Search and filtering by intent type
* Bulk actions (mark as read, archive, delete, mark as spam)
* Status tracking (unread, read, archived, spam)

**Analytics Dashboard**
* Today's Snapshot widget with message breakdown
* AI-powered intent statistics
* Visual charts
* Date range filtering

**Security & Spam Protection**
* Google reCAPTCHA v3 integration
* Spam filtering
* IP-based rate limiting
* Honeypot fields
* Nonce validation
* SQL injection protection
* XSS prevention

**Email System**
* SMTP configuration (Gmail, SendGrid, Mailgun, etc.)
* Custom email templates
* Admin and user notifications
* HTML and plain text support

**Developer Features**
* Extensive action hooks
* Filter hooks for customization
* WP-CLI commands
* Comprehensive logging
* Debug mode
* WordPress Coding Standards compliant

**Admin Interface**
* Get Started onboarding page
* Settings panel with tabs
* System status page
* Email testing functionality

**Internationalization**
* Translation-ready with .pot file
* Text domain: contact-inbox
* RTL language support

**Testing & Quality**
* WordPress 6.4 - 6.9.1 tested
* PHP 7.4 - 8.3 compatible

== Upgrade Notice ==

= 1.0 =
Initial release of ContactIn. Transform your WordPress contact forms into a powerful communication management system with secure inbox, analytics, and reliable email delivery.

== Privacy & Data Collection ==

**What Data We Collect:**
* Contact form submissions (name, email, message as submitted by users)
* IP address and user agent for spam prevention
* Submission timestamps and form IDs for analytics

**Where Data is Stored:**
* All data stored in your WordPress database
* No data is sent to external services unless features are enabled/configured by you
* Optional external services can include reCAPTCHA, SMTP provider, Freemius (opt-in), and configured webhooks

**Optional Account & Opt-in:**
* On plugin activation, an opt-in dialog appears to create a free Freemius account
* Account is completely optional - the plugin works fully without it
* If you create an account, we collect: name, email, website URL
* This data is stored securely by Freemius (our licensing and distribution partner)
* You can delete your account anytime from Freemius dashboard
* Freemius Privacy Policy: [https://freemius.com/privacy/](https://freemius.com/privacy/)
* Freemius Terms: [https://freemius.com/terms/](https://freemius.com/terms/)

**Data Retention:**
* Data is retained in your WordPress database until you delete it
* You can delete submissions from the admin inbox
* Freemius account data can be deleted by you anytime

**External Services (Optional):**
* Google reCAPTCHA (for spam protection)
* Your configured SMTP server (for email delivery)
* User-configured webhook destinations (for outbound automation)
* Freemius (only if you create an account - optional)

== Credits ==

Developed by [Javed Ahsan](https://linkedin.com/in/bizjaved)

Special thanks to:
* WordPress community for inspiration
* Chart.js for analytics visualization
* Contributors and testers

For support and contributions, visit [GitHub](https://github.com/bizjaved/contact-inbox)