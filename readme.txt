=== Contact Inbox ===
Contributors: Javed Ahsan
Donate link: https://github.com/sponsors/bizjaved
Tags: contact form, inbox, analytics, recaptcha, smtp, elementor, gutenberg
Requires at least: 6.4
Tested up to: 6.7
Requires PHP: 7.4
Stable tag: 1.0
License: GPLv3 or later
License URI: https://www.gnu.org/licenses/gpl-3.0.html

Simple contact forms with secure inbox management, email notifications, reCAPTCHA v3, and basic analytics.

== Description ==

**Contact Inbox** provides essential contact form management with a secure inbox and basic analytics.

### 🚀 Why Choose Contact Inbox?

Contact Inbox provides the essentials:

* **Secure Inbox** - All submissions stored securely with search and filtering
* **Basic Analytics** - Track submissions and response times
* **Spam Protection** - reCAPTCHA v3, rate limiting, honeypot protection
* **Email Notifications** - SMTP configuration for reliable delivery
* **Developer-Friendly** - Extensive hooks/filters and WP-CLI commands

### ✨ Core Features

**Form Builder & Integration**
* Simple shortcode: `[contact_inbox_form]`
* Native Gutenberg block support
* Elementor widget included
* Customizable fields and validation
* Mobile-responsive design

**Inbox Management**
* Centralized admin inbox for all submissions
* Search and filtering
* Bulk actions (mark read, archive, delete)
* Status tracking (unread, read, archived, spam)

**Analytics & Insights**
* Basic submission metrics dashboard
* Basic statistics and trends

**Security & Spam Protection**
* Google reCAPTCHA v3 integration
* Spam filtering
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

### 🚀 Upgrade to Pro

Want more features? Learn about **Contact Inbox Pro** for additional capabilities and priority support.

[Learn More About Pro](https://github.com/bizjaved/contact-inbox-pro)

### 📋 Perfect For

* **Business Websites** - Professional contact management
* **Small Businesses** - Simple inbox for customer inquiries
* **Bloggers** - Reader contact forms
* **Freelancers** - Client communication
* **Developers** - Extensible with hooks and filters

### 🎯 Quick Start

1. Install and activate the plugin
2. Add `[contact_inbox_form]` to any page
3. Configure SMTP in Settings > Email (optional)
4. Set up reCAPTCHA in Settings > Security (optional)
5. Start receiving and managing submissions!

### 🔗 External Services

This plugin may connect to external services (optional, user-configured):

* **Google reCAPTCHA** - Spam protection (requires API keys)
* **SMTP Servers** - Email delivery (Gmail, SendGrid, Mailgun, etc.)

[Privacy Policy](https://www.google.com/recaptcha/about/)
[Terms of Service](https://policies.google.com/terms)

### 📚 Documentation & Support

* [GitHub Repository](https://github.com/bizjaved/contact-inbox-free)
* [Documentation](https://github.com/bizjaved/contact-inbox-free#readme)
* [Issue Tracker](https://github.com/bizjaved/contact-inbox-free/issues)

== Installation ==

### Automatic Installation

1. Go to WordPress Admin > Plugins > Add New
2. Search for "Contact Inbox"
3. Click "Install Now" and then "Activate"
4. Configure via Contact Inbox > Settings

### Manual Installation

1. Download the plugin zip file
2. Upload to `/wp-content/plugins/contact-inbox-free/`
3. Activate through the 'Plugins' menu
4. Configure via Contact Inbox > Settings

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

Use the shortcode `[contact_inbox_form]` on any page or post. You can also use the Gutenberg "Contact Inbox Form" block or Elementor widget.

= Does it work with page builders? =

Yes! Includes native support for Elementor (widget) and Gutenberg (block). The shortcode works with any page builder.

= How does spam protection work? =

Multiple layers: Google reCAPTCHA v3, honeypot fields, rate limiting, and duplicate detection work together to block spam effectively.

= Can I customize email templates? =

Yes! Email templates are fully customizable via Settings > Email > Templates. Supports merge tags for dynamic content.

= Does it work on shared hosting? =

Yes! Optimized for shared hosting with no special server configuration required. Uses WordPress standards and efficient database queries.

= What features are in the Pro version? =

Pro offers additional capabilities and priority support. Visit [Contact Inbox Pro](https://github.com/bizjaved/contact-inbox-pro) for details.

= How do I get support? =

Visit the [GitHub repository](https://github.com/bizjaved/contact-inbox-free) to report issues or request features.

== Screenshots ==

1. **Public Contact Form** - Clean, responsive form with validation
2. **Admin Inbox** - Centralized view of all submissions with filtering
3. **Analytics Dashboard** - Basic metrics and insights
4. **Submission Detail** - Complete message view with actions
5. **Settings Panel** - Configuration options
6. **Email Templates** - Customizable notification templates

== Changelog ==

= 1.0 - 2026-02-10 =

**🎉 Initial Release**

**Form Features**
* Simple shortcode: `[contact_inbox_form]`
* Gutenberg block support
* Elementor widget integration
* Customizable form fields (name, email, phone, subject, message)
* AJAX form submission
* Mobile-responsive design

**Inbox Management**
* Centralized admin inbox
* Search and filtering
* Bulk actions (mark as read, archive, delete, mark as spam)
* Status tracking (unread, read, archived, spam)

**Analytics Dashboard**
* Today's Snapshot widget
* Basic submission metrics
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
Initial release of Contact Inbox. Transform your WordPress contact forms into a powerful communication management system with secure inbox, analytics, and reliable email delivery.

== Privacy & Data Collection ==

**What Data We Collect:**
* Contact form submissions (name, email, message as submitted by users)
* IP address and user agent for spam prevention
* Submission timestamps and form IDs for analytics

**Where Data is Stored:**
* All data stored in your WordPress database
* No data sent to external servers except configured services (SMTP, reCAPTCHA)

**Data Retention:**
* Data is retained in your WordPress database until you delete it
* You can delete submissions from the admin inbox

**External Services (Optional):**
* Google reCAPTCHA (for spam protection)
* Your configured SMTP server (for email delivery)

== Credits ==

Developed by [Javed Ahsan](https://github.com/bizjaved)

Special thanks to:
* WordPress community for inspiration
* Chart.js for analytics visualization
* Contributors and testers

For support and contributions, visit [GitHub](https://github.com/bizjaved/contact-inbox-free)