=== ContactIn ===
Contributors: javedahsan
Donate link: 
Plugin URI: https://contactinbox.app
Tags: contact form, contact management, inbox, gdpr, spam
Requires at least: 6.4
Tested up to: 7.0
Requires PHP: 7.4
Stable tag: 1.1.0
License: GPLv3 or later
License URI: https://www.gnu.org/licenses/gpl-3.0.html

Turn your WordPress contact form into a response-ready inbox with intent routing, contact growth, analytics, and queue-backed delivery reliability.

== Description ==

ContactIn helps teams move from "message received" to "message handled" with less manual work and better visibility. Every submission lands in one inbox, contacts are updated automatically, and intent is classified with keyword rules tailored to your industry profile.

Instead of splitting traffic across multiple forms, you can run one smart form for sales, support, and general inquiries, then triage quickly inside a single workflow.

If your goal is to capture more leads, respond faster, and keep operations organized as volume grows, ContactIn is built for that.

You get one workflow for capture, triage, response, and reporting:

* Form builder with shortcode, Gutenberg block, and Elementor widget
* Unified inbox with fail-safe capture flow — search, filters, status pipeline, and notes
* Intent classification: keyword-based patterns across 19 industry-specific profiles
* Real-time analytics: submissions, trends, response performance, and delivery visibility
* Email deliverability toolkit: SMTP, SPF/DKIM/DMARC checks, HTML templates, TLS/SSL, queue with retries
* Multi-layer spam protection: reCAPTCHA v3, honeypot, rate limiting
* Queue reliability engine: deduplication (30-day window), dead-letter queue support, and automatic stuck-item recovery
* Consent capture support for compliance-oriented form workflows
* Automatic contact capture and profile updates (phone normalization, deduplication, CSV/JSON export)
* Safe lifecycle handling for activation, deactivation, and uninstall operations

= Why Teams Choose ContactIn =

* Faster first-response handling with clear inbox status, filters, and notes
* Cleaner lead pipeline with auto-captured contacts and deduplication support
* Better operational confidence with queue visibility, retries, and diagnostics
* Lower manual overhead by combining capture, routing, and reporting in one plugin
* Flexible deployment for agencies and multi-team workflows across many industries

= The Problem We Solve =

When businesses receive contact form submissions, they often hit the same growth blockers:

* Messages scattered across email, spreadsheets, and internal chat
* No consistent way to prioritize sales vs support vs spam
* Slow response times and missed high-intent leads
* Manual data entry and inconsistent follow-up
* Limited visibility into which forms and campaigns perform best

ContactIn addresses these blockers by centralizing submissions, improving triage speed, maintaining contact records, and giving your team clearer analytics and delivery visibility.

= How It Works =

1. Capture leads from your form (shortcode, block, or Elementor widget).
2. Classify intent automatically using industry-specific keyword profiles.
3. Route and manage submissions in a unified inbox with filters, status, and notes.
4. Measure performance with analytics and delivery logs.
5. Monitor queue health and maintain your submission pipeline.

= Who It’s For =

ContactIn is designed for teams that need faster response and clearer message routing:

* SaaS and software teams
* E-commerce and retail stores
* Service and consulting firms
* Healthcare and medical clinics
* Education and training providers
* Hospitality and travel businesses
* Banking and financial services
* Insurance teams
* Legal services and law firms
* Real estate teams
* Construction and home services
* Automotive and dealerships
* Logistics and courier services
* Telecom and ISP providers
* Supermarkets and grocery
* Travel agencies and tours
* Embassy and high commission services
* Quality agencies and certification bodies
* Agencies and multi-client operations

= Features =

**Form Builder & Frontend Integration**
* Shortcode: `[contactin_form]`
* Native Gutenberg block
* Elementor widget
* Configurable fields and validation
* Responsive form UI
* Per-profile settings (labels, messages, optional fields, consent)

**Unified Inbox & Contact Management**
* Centralized submission inbox with fail-safe capture (no lost messages)
* Search and filtering
* Bulk actions
* Status pipeline (unread, read, archived, spam)
* Threading and internal notes
* Automatic contact creation and updates (including phone changes)
* Phone number normalization and validation
* Duplicate submission detection and cleanup tools
* CSV/JSON exports
* Contact timeline context to support faster follow-up and cleaner handoff

**Intent Classification (Keyword-Based)**
* Categories: Sales, Support, Feedback, Complaints, Questions
* Keyword-based classification with custom rule support
* Business-type profiles to improve relevance by industry
* 19 industry profiles: Generic, SaaS, E-commerce, Service, Healthcare, Education, Hospitality, Banking, Insurance, Embassy, Quality Agency, Travel Agency, Supermarket, Legal, Logistics, Telecom, Automotive, Construction, Real Estate

**Analytics & Reporting**
* Submission volume tracking
* Conversion and response metrics
* Geographic and device-level insights
* Performance monitoring
* Delivery and queue observability
* Dashboard widgets for daily operational visibility

**Deliverability & Reliability**
* SMTP support (Gmail, SendGrid, Mailgun, AWS SES, Outlook, custom)
* Anti-spam headers and sender-domain checks
* SPF/DKIM/DMARC mismatch warnings
* Professional HTML email templates and TLS/SSL encryption
* Delivery queue with retries and delivery logs
* Async queue with retries, deduplication, dead-letter handling
* Queue maintenance tools and diagnostics
* Queue health monitoring for stalled processors/locks
* Circuit-breaker and retry behavior designed to prevent silent message loss

**Queue Reliability & Deduplication**
* Idempotent queue engine: deduplication window extended to 30 days for maintenance operations
* Automatic recovery for stuck "processing" items (older than 10 minutes reset to pending)
* Dead-letter queue (DLQ) with per-item and bulk retry, idempotent retry button (safe to click multiple times)
* Before/after statistics and last retry timestamp displayed in Maintenance panel

**Operations, Logs & Maintenance**
* Email, cron, and queue log tables for troubleshooting
* Background cleanup of stale logs, orphaned entries, and old records to keep the system tidy
* Safe activation/deactivation lifecycle handling
* Safe uninstall path with cleanup controls

**Security & Compliance**
* Google reCAPTCHA v3
* Honeypot and rate limiting
* Duplicate submission safeguards
* Consent checkbox/capture support for compliance-oriented forms

= What's Included =

This version includes a complete contact management solution:

* Multiple form profiles (unlimited — label, fields, messages, consent, reCAPTCHA override)
* Unified inbox + search/filter + bulk actions
* Keyword-based intent classification with 19 industry profiles
* Industry-specific business-type profiles
* Analytics dashboard (core metrics)
* Core spam protection (reCAPTCHA + honeypot + baseline throttling)
* SMTP + deliverability checks + queue reliability
* Consent capture support for compliance-oriented forms
* Automatic data capture from every submission to continuously grow your contact list
* Contact auto-capture and profile updates with phone normalization

= ContactIn Pro =

ContactIn Pro is available for teams that need extended automation and deeper reporting.

Both Free and Pro versions capture every submission and help grow your contact list automatically.

For current Pro capabilities and support, visit: https://contactinbox.app/


= Source Code and Build Assets =

The complete source code and development history are publicly available at:

* https://github.com/bizjaved/contactin

Build/export tooling used for release packaging is included in this plugin repository:

* `composer.json` (PHP dependency and autoload configuration)

JavaScript and CSS assets shipped with the plugin are located in:

* `dist/js/`
* `dist/css/`

Readable source counterparts for custom minified assets are included in:

* `assets/src/js/` (maps to `dist/js/*.min.js`)
* `assets/src/css/` (maps to `dist/css/*.min.css`)

Exact one-to-one file mapping is documented in:

* `docs/ASSET_SOURCE_MAP.md`

Examples from the reported files:

* `dist/js/integration.min.js` -> `assets/src/js/integration.js`
* `dist/js/admin-settings.min.js` -> `assets/src/js/admin-settings.js`
* `dist/js/dashboard-render-helpers.min.js` -> `assets/src/js/dashboard-render-helpers.js`
* `dist/js/dashboard-sparkline.min.js` -> `assets/src/js/dashboard-sparkline.js`
* `dist/js/admin-email-log.min.js` -> `assets/src/js/admin-email-log.js`
* `dist/js/dashboard-widgets-live.min.js` -> `assets/src/js/dashboard-widgets-live.js`
* `dist/js/attachment-cleanup.min.js` -> `assets/src/js/attachment-cleanup.js`
* `dist/js/frontend.min.js` -> `assets/src/js/frontend.js`

Third-party bundled assets in this package include:

* Select2 (`dist/js/vendor/select2.min.js`, `dist/css/vendor/select2.min.css`)
* Chart.js (`dist/js/vendor/chart.min.js`)

Build tool commands to regenerate minified assets are documented in `docs/ASSET_SOURCE_MAP.md`.

All plugin PHP source is included in the package under `includes/` and `templates/`.

= Quick Start =

1. Install and activate ContactIn.
2. Add `[contactin_form]` to a page/post (or use block/widget).
3. Configure email delivery in Settings > Email (SMTP recommended).
4. Configure spam protection in Settings > Security.
5. Start capturing and organizing contact submissions.

== Installation ==

1. Upload the plugin to `/wp-content/plugins/contactin/` or install from Plugins > Add New.
2. Activate the plugin from the Plugins page.
3. Add `[contactin_form]` to any page or post.
4. Configure settings under ContactIn > Settings.

**Requirements**

* WordPress 6.4+
* PHP 7.4+
* MySQL 5.6+ or MariaDB 10.0+
* HTTPS recommended for reCAPTCHA

== Frequently Asked Questions ==

= How do I add the contact form to my site? =

Use `[contactin_form]`, the Gutenberg block, or the Elementor widget.

= Is Intent Classification available? =

Yes. This version includes keyword-based intent classification. You can also define custom keyword patterns for your industry.

= Which industries are supported by business-type profiles? =

Profiles include Generic, SaaS, E-commerce, Service, Healthcare, Education, Hospitality, Banking, Insurance, Embassy, Quality Agency, Travel Agency, Supermarket, Legal, Logistics, Telecom, Automotive, Construction, and Real Estate.

= Does this plugin include GDPR support? =

It includes consent checkbox/capture support on forms. Additional GDPR lifecycle tooling (for example retention policies and dedicated deletion workflows) is not included in this version.

= Does it support safe uninstall handling? =

Yes. The plugin includes safeguards for activation/deactivation/uninstall workflows so cleanup is predictable and data is handled safely.

= What spam protection layers are included? =

This version includes reCAPTCHA v3, honeypot checks, and baseline throttling safeguards.

= Can I export submissions? =

Yes, you can export data in CSV/JSON formats.

= Where can I review operational logs? =

The plugin maintains operational logging for queue, email, and cron activity to help diagnose delivery issues.

= Where can I get support? =

Use the official support page: https://contactinbox.app/

== Changelog ==

= 1.1.0 - 2026-05-27 =
* Removed deprecated/unused integration helpers and stale feature paths.
* Simplified contacts-page actions by removing an unused data-management action button from that screen.
* Updated readme to align with current feature set and stronger product positioning.

= 1.0.9 - 2026-03-27 =
* Added global Require Phone and Require Subject controls across form profiles.
* Improved profile creation/editing workflows in Gutenberg and Elementor.
* Added shared profile core module for better editor consistency.
* Improved classifier behavior and validation robustness.
* Resolved admin UX and plugin-check compatibility issues.

= 1.0.1 - 2026-03-14 =
* Strengthened server-side form validation and field enforcement.
* Improved setup and admin UX consistency.

== Upgrade Notice ==

= 1.1.0 =
Readme and feature-surface cleanup release. Deprecated/unused integration paths were removed, and active capabilities are now documented more clearly.

= 1.0.9 =
Major profile and validation improvements with stronger editor workflows and classifier reliability.

== External Services ==

This plugin may connect to the following external services depending on your configuration. No data is sent to any service without your explicit setup.

**1. SMTP provider (user-configured, optional)**
This plugin can send notification emails via an external SMTP server that you configure. Supported providers include Gmail, SendGrid, Mailgun, AWS SES, Outlook, and any custom SMTP server. Data sent is limited to the email content (sender, recipient, subject, body). This only activates if you enable and configure SMTP in Settings → Email. Consult your chosen provider's own privacy and terms documentation.

**2. Google reCAPTCHA (optional)**
Used for: Spam protection on the contact form front-end.
Data sent: Browser/device fingerprint data transmitted to Google servers.
Conditions: Only active when reCAPTCHA is enabled in Settings.
Privacy Policy: https://policies.google.com/privacy
Terms: https://www.google.com/recaptcha/about/

**3. Salesforce CRM (optional)**
Used for: Sending contact, case/task, and related attachment sync requests when Salesforce CRM integration is configured.
Data sent: Contact form fields you map into Salesforce, message metadata needed for sync status, and optional attachment content when file sync is enabled.
Conditions: Only active when a site administrator configures Salesforce CRM integration and enables CRM sync features.
Privacy Policy: https://www.salesforce.com/company/privacy/
Terms: https://www.salesforce.com/company/legal/agreements/

== Privacy & Data Collection ==

**Data collected:**
* Form submission fields entered by users
* IP/user agent data for anti-spam and security operations
* Submission timestamps and routing metadata

**Data storage:**
* Stored in your WordPress database
* Sent externally only to services you configure (e.g., reCAPTCHA, SMTP, Salesforce CRM)

**Optional external services:**
* Google reCAPTCHA
* Your SMTP provider
* Salesforce CRM

reCAPTCHA policy links:
* Privacy Policy: https://policies.google.com/privacy
* Terms of Service: https://policies.google.com/terms

== Documentation ==

* Website: https://contactinbox.app/
* Docs: https://contactinbox.app/
* Support: https://contactinbox.app/

== Credits ==

Developed by Javed Ahsan.
