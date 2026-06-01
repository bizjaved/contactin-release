=== ContactIn ===
Contributors: javedahsan
Donate link: 
Plugin URI: https://contactinbox.app
Tags: contact form, contact management, inbox, gdpr, spam
Requires at least: 6.4
Tested up to: 7.0
Requires PHP: 7.4
Stable tag: 1.1.2
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

Public source snapshots and release packages for this plugin are available at:

* https://github.com/bizjaved/contactin-release
* https://github.com/bizjaved/contactin-release/releases

This plugin includes human-readable source for distributed minified assets.

Distributed assets:

* dist/js/
* dist/css/

Readable source directories available in the public repository:

* assets/src/js/ (source for dist/js/*.min.js)
* assets/src/css/ (source for dist/css/*.min.css)

Custom JavaScript minified -> source mapping:

* dist/js/admin-email-log.min.js -> assets/src/js/admin-email-log.js
* dist/js/admin-global.min.js -> assets/src/js/admin-global.js
* dist/js/admin-inbox.min.js -> assets/src/js/admin-inbox.js
* dist/js/admin-settings.min.js -> assets/src/js/admin-settings.js
* dist/js/attachment-cleanup.min.js -> assets/src/js/attachment-cleanup.js
* dist/js/confetti.min.js -> assets/src/js/confetti.js
* dist/js/dashboard-analytics.min.js -> assets/src/js/dashboard-analytics.js
* dist/js/dashboard-chart-renderer.min.js -> assets/src/js/dashboard-chart-renderer.js
* dist/js/dashboard-date-utils.min.js -> assets/src/js/dashboard-date-utils.js
* dist/js/dashboard-render-helpers.min.js -> assets/src/js/dashboard-render-helpers.js
* dist/js/dashboard-sparkline.min.js -> assets/src/js/dashboard-sparkline.js
* dist/js/dashboard-tabs.min.js -> assets/src/js/dashboard-tabs.js
* dist/js/dashboard-widgets-live.min.js -> assets/src/js/dashboard-widgets-live.js
* dist/js/elementor-editor.min.js -> assets/src/js/elementor-editor.js
* dist/js/frontend.min.js -> assets/src/js/frontend.js
* dist/js/gutenberg-block.min.js -> assets/src/js/gutenberg-block.js
* dist/js/maintenance.min.js -> assets/src/js/maintenance.js
* dist/js/sf-attachment-settings.min.js -> assets/src/js/sf-attachment-settings.js

Custom CSS minified -> source mapping:

* dist/css/admin-global.min.css -> assets/src/css/admin-global.css
* dist/css/admin-inbox.min.css -> assets/src/css/admin-inbox.css
* dist/css/admin-inbox-old.min.css -> assets/src/css/admin-inbox-old.css
* dist/css/admin-settings.min.css -> assets/src/css/admin-settings.css
* dist/css/attachment-cleanup.min.css -> assets/src/css/attachment-cleanup.css
* dist/css/contact-detail.min.css -> assets/src/css/contact-detail.css
* dist/css/contact-detail-tabs.min.css -> assets/src/css/contact-detail-tabs.css
* dist/css/contact-edit-modal.min.css -> assets/src/css/contact-edit-modal.css
* dist/css/crm-log.min.css -> assets/src/css/crm-log.css
* dist/css/dashboard-analytics.min.css -> assets/src/css/dashboard-analytics.css
* dist/css/dashboard-widgets.min.css -> assets/src/css/dashboard-widgets.css
* dist/css/elementor-editor.min.css -> assets/src/css/elementor-editor.css
* dist/css/frontend.min.css -> assets/src/css/frontend.css
* dist/css/gutenberg-editor.min.css -> assets/src/css/gutenberg-editor.css
* dist/css/inbox-consolidated.min.css -> assets/src/css/inbox-consolidated.css
* dist/css/logs.min.css -> assets/src/css/logs.css
* dist/css/maintenance.min.css -> assets/src/css/maintenance.css
* dist/css/sf-attachment-settings.min.css -> assets/src/css/sf-attachment-settings.css
* dist/css/tests.min.css -> assets/src/css/tests.css

Third-party bundled libraries and public sources:

* Chart.js v4.5.1 (bundled as dist/js/vendor/chart.min.js)
	Source: https://github.com/chartjs/Chart.js
	License: MIT
* Select2 v4.1.0-rc.0 (bundled as dist/js/vendor/select2.min.js and dist/css/vendor/select2.min.css)
	Source: https://github.com/select2/select2
	License: MIT

Additional distributed JS/CSS that are already human-readable (not minified/compressed):

* dist/js/admin-settings-attachment-restapi.js
* dist/js/admin-settings-autosave.js
* dist/js/contactin-profile-core.js
* dist/js/contact-deletion.js
* dist/js/contact-detail-tabs.js
* dist/js/contact-edit-modal.js
* dist/js/gdpr-frontend.js
* dist/js/gdpr.js
* dist/css/form-error-modal.css
* dist/css/gdpr-frontend.css
* dist/css/get-started.css
* dist/css/intent-classification.css

How to rebuild generated/minified assets (from plugin root):

1. Install build tools (if needed):
	npm install --no-save terser clean-css-cli

2. Rebuild JavaScript minified assets from source:
	for f in assets/src/js/*.js; do \
	  npx terser "$f" -c -m -o "dist/js/$(basename "${f%.js}").min.js"; \
	done

3. Rebuild CSS minified assets from source:
	for f in assets/src/css/*.css; do \
	  npx cleancss -o "dist/css/$(basename "${f%.css}").min.css" "$f"; \
	done

4. Rebuild one specific file examples:
	JS: npx terser assets/src/js/dashboard-widgets-live.js -c -m -o dist/js/dashboard-widgets-live.min.js
	CSS: npx cleancss -o dist/css/dashboard-analytics.min.css assets/src/css/dashboard-analytics.css

Build prerequisites:

* Node.js + npm (for terser / clean-css-cli)
* Composer for PHP autoload/dependency management (see composer.json)

Public source code locations (required for review/forking):

* Main repository (public): https://github.com/bizjaved/contactin-release
* Tagged releases: https://github.com/bizjaved/contactin-release/releases
* JavaScript source tree: https://github.com/bizjaved/contactin-release/tree/main/assets/src/js
* CSS source tree: https://github.com/bizjaved/contactin-release/tree/main/assets/src/css

Review note for WordPress.org: every compressed asset in dist/js/*.min.js and dist/css/*.min.css is generated from files in assets/src/js and assets/src/css, and those source files are publicly accessible in the repository links above.

All plugin PHP source is included in this package under includes/ and templates/.

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

== Screenshots ==

1. Frontend contact form experience with profile-aware fields and consent support.
2. Unified inbox for incoming submissions with search, filters, and status pipeline.
3. Contact detail view with message context, metadata, and internal workflow actions.
4. Settings dashboard for SMTP, security, and operational configuration.
5. Analytics dashboard for submission trends and response visibility.
6. Queue and maintenance view for retries, diagnostics, and reliability monitoring.

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

= 1.1.2 - 2026-06-01 =
* Updated plugin-information modal templates to keep content scoped to the free ContactIn plugin.
* Removed Pro-specific wording from modal FAQ/features content and corrected installation path to /wp-content/plugins/contactin.
* Pointed plugin-information modal documentation/changelog links to WordPress.org free plugin pages.

= 1.1.1 - 2026-06-01 =
* Normalized plugin-information modal identity to always display the free plugin name as ContactIn.
* Prevented pro-slug/pro-name leakage in fallback plugin-information responses.

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

= 1.1.2 =
Refines plugin-information modal content to free-plugin-only messaging and updates modal documentation links for WordPress.org users.

= 1.1.1 =
Fixes plugin-information modal naming to consistently show ContactIn for the free plugin.

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
