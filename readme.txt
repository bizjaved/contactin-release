=== ContactIn ===
Contributors: javedahsan
Donate link: 
Plugin URI: https://contactinbox.app
Tags: contact form, crm, inbox, gdpr, spam
Requires at least: 6.4
Tested up to: 7.0
Requires PHP: 7.4
Stable tag: 1.1.0
License: GPLv3 or later
License URI: https://www.gnu.org/licenses/gpl-3.0.html

Smart contact inbox for WordPress — keyword-based intent routing, industry classification, analytics, GDPR controls, and queue-based reliability.

== Description ==

ContactIn turns WordPress contact forms into an organized communication hub. It centralizes every submission in a secure inbox, classifies intent using keyword-based patterns, automatically updates contacts, and gives teams the data, logs, and controls they need to respond faster.

Use one smart form to replace separate Sales, Support, and General Inquiry forms. Intent classification routes each message to the right workflow without forcing visitors to choose a form, while the inbox keeps status, notes, and full history in one place.

You get one workflow for capture, triage, response, and reporting:

* Form builder with shortcode, Gutenberg block, and Elementor widget
* Unified inbox that never drops a submission — search, filters, status pipeline, and notes
* Intent classification: keyword-based patterns across 19 industry-specific profiles
* Real-time analytics: submissions, trends, response performance, and delivery visibility
* Enterprise email deliverability: SMTP, SPF/DKIM/DMARC checks, HTML templates, TLS/SSL, queue with retries
* Multi-layer spam protection: reCAPTCHA v3, honeypot, rate limiting
* Queue reliability engine: deduplication (30-day window), dead-letter queue, GDPR log cross-reference, and automatic stuck-item recovery
* GDPR controls: consent, retention, export, and deletion workflows
* Automatic contact capture and profile updates (phone normalization, deduplication, CSV/JSON export)
* Safe uninstall and free/pro coexistence safeguards to prevent shared data loss

= The Problem We Solve =

When businesses receive contact form submissions, they face the same recurring issues:

* Messages scattered across email, spreadsheets, and internal chat
* No consistent way to prioritize sales vs support vs spam
* Slow response times and missed high-intent leads
* Manual data entry and inconsistent follow-up
* Limited visibility into which forms and campaigns perform best

ContactIn fixes this by capturing every message, auto-creating/updating contacts, classifying intent by industry, and giving teams real-time analytics, delivery logs, and inbox-focused deliverability controls.

= How It Works =

1. Capture leads from your form (shortcode, block, or Elementor widget).
2. Classify intent automatically using industry-specific keyword profiles.
3. Route and manage submissions in a unified inbox with filters, status, and notes.
4. Measure performance with analytics and delivery logs.
5. Monitor queue health and maintain GDPR compliance.

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

**Deliverability & Reliability**
* SMTP support (Gmail, SendGrid, Mailgun, AWS SES, Outlook, custom)
* Anti-spam headers and sender-domain checks
* SPF/DKIM/DMARC mismatch warnings
* Professional HTML email templates and TLS/SSL encryption
* Delivery queue with retries and delivery logs
* Async queue with retries, deduplication, dead-letter handling
* Queue maintenance tools and diagnostics
* Queue health monitoring for stalled processors/locks

**Queue Reliability & Deduplication**
* Idempotent queue engine: deduplication window extended to 30 days for deletion operations
* GDPR log cross-reference as a defense-in-depth layer to prevent re-processing already-deleted contacts
* Automatic recovery for stuck "processing" items (older than 10 minutes reset to pending)
* Dead-letter queue (DLQ) with per-item and bulk retry, idempotent retry button (safe to click multiple times)
* Before/after statistics and last retry timestamp displayed in Maintenance panel

**Operations, Logs & Maintenance**
* Email, CRM, REST, cron, and queue log tables for troubleshooting
* Background cleanup of stale logs, orphaned entries, and old records to keep the system tidy
* Safe activation/deactivation lifecycle handling
* Safe uninstall path designed for free/pro coexistence

**Security & Compliance**
* Google reCAPTCHA v3
* Honeypot and rate limiting
* Duplicate submission safeguards
* GDPR consent and retention controls
* Data export and deletion workflows

= What's Included =

This version includes a complete contact management solution:

* Multiple form profiles (unlimited — label, fields, messages, consent, reCAPTCHA override)
* Unified inbox + search/filter + bulk actions
* Keyword-based intent classification with 19 industry profiles
* Industry-specific business-type profiles
* Analytics dashboard (core metrics)
* Core spam protection (reCAPTCHA + honeypot + baseline throttling)
* SMTP + deliverability checks + queue reliability
* GDPR consent + retention + export + deletion workflows
* Contact auto-capture and profile updates with phone normalization


= Source Code and Build Assets =

The complete source code and development history are publicly available at:

* https://github.com/bizjaved/contactin

Build/export tooling used for release packaging is included in this plugin repository:

* `export-distribution.sh` (distribution packaging script)
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

= Is this plugin GDPR compliant? =

Yes. You get consent and retention controls, data export, and deletion workflows to help you meet GDPR requirements.

= Does it support safe uninstall and free/pro coexistence? =

Yes. The plugin includes safeguards for activation/deactivation/uninstall workflows so shared data is preserved when one version is removed and full cleanup runs only when appropriate.

= What spam protection layers are included? =

This version includes reCAPTCHA v3, honeypot checks, and baseline throttling safeguards.

= Can I export submissions? =

Yes, you can export data in CSV/JSON formats.

= Where can I review operational logs? =

The plugin maintains operational logging for queue, email, and cron activity to help diagnose delivery issues.

= Where can I get support? =

Use the official support page: https://contactinbox.app/

== Changelog ==

= 1.0.9 - 2026-03-27 =
* Feat: global Require Phone / Require Subject settings — enforce phone and subject fields across all form profiles
* Feat: profile creation wizard prompts for a human-readable name and auto-generates an editable slug
* Feat: inline profile create & edit workflow inside Gutenberg block inspector and Elementor widget panel
* Feat: block profile picker with guided overrides, save-as-profile, and auto-save profile edits without a separate save button
* Feat: CinProfileCore shared JS module — centralises profile logic across Gutenberg and Elementor
* Feat: global attachment ceiling — site-wide storage cap independent of per-profile limits
* Feat: Get Started card surfaces AI intent classification guidance in onboarding
* Fix: intent classifier — 10 bugs corrected across keyword matching, scoring, and profile dispatch
* Fix: form profile validation and form_id propagation regressions
* Fix: admin settings JS/CSS architecture conflicts
* Fix: attachment disable confirmation button (Yes) was non-functional
* Fix: wp-pointer not enqueued on settings page
* Fix: Plugin Check warnings resolved for WordPress.org submission
* Refactor: Gutenberg block is now a pure profile selector; auto-override bug fixed
* Refactor: Elementor widget is now a pure profile selector; Backbone model API and mount-div search corrected
* Refactor: show_phone renamed to enable_phone for consistency

= 1.0.8 - 2026-03-25 =
* Docs: updated plugin header description, readme tagline, features list, and FAQ to reflect ML intent classification, queue deduplication engine, attachment sync guard, and premium cron self-healing added in recent releases
* Docs: added versioned Upgrade Notice entries for 1.0.7 and 1.0.8
* Docs: added two new FAQ entries covering CRM delete deduplication and premium cron self-healing after license renewal

= 1.0.7 - 2026-03-24 =
* Fix: on_freemius_init() no longer overwrites the stored Freemius instance with a non-premium (free-slug) instance; prevents are_all_features_enabled() returning false on sites where both free and pro slugs fire their init hooks
* Fix: removed enforce_non_premium_restrictions() call from on_freemius_init() — Freemius license state is not fully resolved at init time, causing crons to be incorrectly cleared on premium sites; enforcement now happens only via on_license_change() and the hourly admin_init self-heal
* Fix: get_license_state() now shows 'License expired' instead of 'Free plan active' on pro-build installs where the plugin folder has a non-standard name — uses Freemius SDK is_premium() as a reliable fallback
* Fix: added fs_after_license_change_contactin hook so subscription cancelled/resumed events fired on the free slug are also handled correctly
* Fix: maybe_heal_premium_workloads() now unconditionally clears the stale contactin_non_premium_restrictions_applied transient when premium is confirmed active, not only when crons are missing
* Fix: added self-healing admin_init check — if Freemius reports an active premium license but premium crons are missing (e.g. due to a license renewal while an older version was active), they are automatically re-scheduled; throttled to once per hour via transient

= 1.0.6 - 2026-03-23 =
* Fix: replaced Config::TEXTDOMAIN constant with string literal 'contactin' in AnalyticsDashboardAssets i18n calls (WordPress Plugin Check error)
* Fix: sanitize and wp_unslash() $_SERVER['HTTP_HOST'] / $_SERVER['SERVER_NAME'] in is_live_environment() (WordPress Plugin Check warning)
* Fix: FreemiusIntegration::initialize() was never called — all license-lifecycle hooks (fs_after_license_change, fs_after_premium_version_activation, fs_after_init) were silently not registered; fixed by calling it at the top of Plugin::init()
* Fix: contactinbox_fs() in freemius-bootstrap.php was not guarded with function_exists, risking a PHP fatal error if the file was ever included after contactin.php

= 1.0.5 - 2026-03-23 =
* Security: replaced __() with esc_html__() in wp_die() calls across OAuthCallbackHandler, GDPRHandler, InboxExportImport, GDPR, Contacts, PluginDetails, and GDPRLog (18 occurrences)
* Security: replaced bare json_encode() with wp_json_encode() in IntentClassifier checksum verification and reclassify() DB write

= 1.0.4 - 2026-03-23 =
* Fix: CRON_RECLASSIFY_UNCLASSIFIED was silently re-scheduled for non-premium users by the cron health check — moved into the premium gate in CronJobs
* Fix: restore_premium_workloads() now reads stored interval options instead of using a hardcoded schedule name, and also clears the cron health throttle transient so recovery runs immediately
* Fix: is_live_environment() was hardcoded to false (sandbox mode) — now auto-detects localhost/.local/.test/.dev and IP-only hosts as non-live
* Fix: added fs_after_premium_version_activation hook and on_premium_activation() handler as a safety net for fresh pro-build installs where fs_after_license_change does not fire
* Fix: corrected contactinbox_fs() return type from \FS_Site|null to object|null

= 1.0.3 - 2026-03-23 =
* Fix: premium features and crons no longer remain disabled after a license is renewed or reactivated following expiry
* Fix: CRM, attachment, and CRM-delete queue items neutralized during expiry are now automatically reset to pending on license renewal so they are retried

= 1.0.2 - 2026-03-23 =
* Security: replaced json_encode() with wp_json_encode() for JS HTML injection in REST API modal
* Security: fixed esc_url_raw() used as output escaper — replaced with esc_url() in CSS/HTML contexts
* Security: wrapped unescaped __() calls with esc_html__() in wp_die() and wp_send_json_error()
* i18n: replaced class constants as gettext text parameters with string literals (InboxAssets, SMTP, FormService, CRMStatus, templates)
* i18n: rewrote CRMStatus::label() to use per-status string literals instead of __($variable)
* Fix: replaced hardcoded wp-admin/admin-ajax.php URL in CRM help modal with dynamic admin_url()
* Fix: added missing GDPR_SUCCESS_DEFAULT constant to Config
* Cleanup: removed sensitive .bak and .backup files from distribution

= 1.0.1 - 2026-03-14 =
* Fixed server-side form validation so AJAX submissions now respect configured field rules
* Enforced required `subject` validation when the subject field is enabled
* Enforced configured name, subject, and message word-count and character limits on submission
* Expired-license flow now prioritizes renewal actions over upgrade/trial prompts
* Improved expired-license admin UX with clearer renewal call-to-action
* Freemius account and pricing pages are left to native SDK behavior to avoid access conflicts

= 1.0 - 2026-02-13 =
* Rebrand from Secure ContactUS Hub to ContactIn
* Unified inbox with search/filter and bulk operations
* Automatic contact capture and profile updates (including new phone numbers), with normalization and export
* Multi-industry business-type classifier profiles
* Analytics dashboard and reporting foundation
* Salesforce CRM integration and queue reliability layer
* GDPR controls and deletion workflow support
* Deliverability improvements and SPF/DKIM/DMARC warnings
* Gutenberg + Elementor + shortcode support

= 0.1.0 - 2026-02-08 =
* Initial release with core form, inbox, and analytics capabilities

== Upgrade Notice ==

= 1.0.9 =
Form profiles overhaul with inline editor in Gutenberg and Elementor, global phone/subject enforcement, attachment ceiling, and 10 intent classifier bug fixes. Recommended for all users.

= 1.0.8 =
Documentation update to accurately reflect all features added since v1.0. No code changes.

= 1.0.7 =
Critical fixes for Freemius license-state detection and premium cron self-healing. Recommended for all users, especially after a license renewal or on sites where both free and pro slugs are active.

= 1.0.1 =
Server-side form validation enforcement and Freemius expired-license UX improvements.

== External Services ==

This plugin may connect to the following external services depending on your configuration. No data is sent to any service without your explicit setup.

**1. Freemius (license management & updates)**
Used for: Delivering plugin updates and managing license activation. Diagnostic and usage tracking is controlled via the Freemius opt-in consent flow and can be disabled by opting out.
Privacy Policy: https://freemius.com/privacy/
Terms of Use: https://freemius.com/terms/

**2. SMTP provider (user-configured, optional)**
This plugin can send notification emails via an external SMTP server that you configure. Supported providers include Gmail, SendGrid, Mailgun, AWS SES, Outlook, and any custom SMTP server. Data sent is limited to the email content (sender, recipient, subject, body). This only activates if you enable and configure SMTP in Settings → Email. Consult your chosen provider's own privacy and terms documentation.

**3. Google reCAPTCHA (optional)**
Used for: Spam protection on the contact form front-end.
Data sent: Browser/device fingerprint data transmitted to Google servers.
Conditions: Only active when reCAPTCHA is enabled in Settings.
Privacy Policy: https://policies.google.com/privacy
Terms: https://www.google.com/recaptcha/about/

== Privacy & Data Collection ==

**Data collected:**
* Form submission fields entered by users
* IP/user agent data for anti-spam and security operations
* Submission timestamps and routing metadata

**Data storage:**
* Stored in your WordPress database
* Sent externally only to services you configure (e.g., reCAPTCHA, SMTP)

**Optional external services:**
* Google reCAPTCHA
* Your SMTP provider

reCAPTCHA policy links:
* Privacy Policy: https://policies.google.com/privacy
* Terms of Service: https://policies.google.com/terms

== Documentation ==

* Website: https://contactinbox.app/
* Docs: https://contactinbox.app/
* Support: https://contactinbox.app/

== Credits ==

Developed by Javed Ahsan.
