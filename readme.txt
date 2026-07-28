=== Contact Form Builder, Lead Capture & Inbox CRM - ContactIn ===
Contributors: javedahsan, bizjaved
Donate link: 
Plugin URI: https://contactinbox.app
Tags: contact form entries, inbox crm, lead management, dead letter queue, salesforce form
Requires at least: 6.4
Tested up to: 7.0
Requires PHP: 7.4
Stable tag: 1.1.4
License: GPLv3 or later
License URI: https://www.gnu.org/licenses/gpl-3.0.html

WordPress contact form entries plugin featuring a unified lead inbox, intent classification, dead-letter queue deliverability, and database storage.

== Description ==

ContactIn is a modern WordPress contact form entries plugin and lead management inbox. It helps teams move from "message received" to "message handled" with zero lost leads, automated intent routing, and full delivery visibility.

Standard contact form builders only send emails—which often get lost in spam or bounce silently due to Gmail and Yahoo DMARC/DKIM policies. ContactIn combines a contact form builder, database entry storage, unified inbox CRM, and a fail-safe delivery queue in one unified system.

If you need to store contact form entries in your WordPress database, triage incoming inquiries, and guarantee delivery with SMTP retries and a Dead-Letter Queue (DLQ), ContactIn is built specifically for your workflow.

## Why ContactIn Pro vs Traditional Form Builders

Most WordPress form plugins stop working once a user clicks "Submit"—they rely on basic PHP mail, offering no lead triage, no status pipeline, and no protection against silent email delivery failure.

### ContactIn Pro vs Contact Form 7 (CF7)

| Feature | Contact Form 7 (CF7) | ContactIn Pro |
| :--- | :--- | :--- |
| **Primary Focus** | Form Builder | Form Builder + Centralized Inquiry Inbox |
| **Database Entry Storage** | Requires third-party add-on | Native, Fail-Safe WordPress Database Storage |
| **Email Deliverability Engine** | Standard WP Mail | SMTP, Async Retry Queue + Dead Letter Queue (DLQ) |
| **Lead Triage & Status** | No built-in inbox workflow | Unified Status Pipeline (Unread, Read, Archived, Spam) |
| **Intent Classification** | Manual sorting | Automatic keyword-based Intent Classification |
| **Salesforce WordPress Sync** | Not native | Built-in Salesforce WordPress Sync options |

### ContactIn Pro vs WPForms

| Feature | WPForms | ContactIn Pro |
| :--- | :--- | :--- |
| **Primary Focus** | Form Builder + Add-on ecosystem | Form Builder + Centralized Inquiry Inbox |
| **Database Entry Storage** | Available via forms entries model | Native, Fail-Safe WordPress Database Storage |
| **Email Deliverability Engine** | Depends on site mail setup | SMTP, Async Retry Queue + Dead Letter Queue (DLQ) |
| **Lead Triage & Status** | Limited status pipeline controls | Unified Status Pipeline (Unread, Read, Archived, Spam) |
| **Intent Classification** | Manual routing | Automatic keyword-based Intent Classification |
| **Salesforce WordPress Sync** | Typically extension-dependent | Built-in Salesforce WordPress Sync options |

## Technical Keyword Anchors

* **Dead Letter Queue (DLQ):** Queue safety layer that stores failed outbound email jobs for retry and diagnostics.
* **Intent Classification:** Rule-based classification engine that labels incoming submissions by inquiry type.
* **Database Entry Storage:** Native WordPress database persistence for every submission before delivery attempts.
* **Salesforce WordPress Sync:** Optional CRM synchronization workflow for contacts, records, and mapped submission fields.

### Key Capabilities

* **Fail-Safe Contact Form Database Capture:** Never lose a lead. Submissions are safely written to your database before email routing triggers.
* **Unified Inbox & Lead Triage:** Manage sales inquiries, support tickets, and client contacts in a single status pipeline with notes and search.
* **Dead-Letter Queue (DLQ) & Delivery Logs:** Complete deliverability toolkit with SMTP checks, SPF/DKIM/DMARC warnings, async retries, and stuck-item recovery.
* **Automatic Intent Classification:** Keyword-based classification across 19 industry-specific profiles (SaaS, Real Estate, E-commerce, Legal, Healthcare, etc.).
* **Salesforce & CRM Integration:** Sync WordPress contact form entries directly to Salesforce and auto-capture contacts with phone normalization.
* **Multi-Layer Anti-Spam:** reCAPTCHA v3, honeypot fields, duplicate detection, and rate limiting.
* **Shortcode, Gutenberg & Elementor:** Works with `[contactin_form]`, native Gutenberg blocks, and Elementor widgets.

= The Problem We Solve =

When businesses receive contact form submissions, they often hit the same growth blockers:

* **Lost Emails & Deliverability Failures:** Messages land in spam or bounce without warning.
* **Scattered Lead Data:** Inquiries scattered across team email, spreadsheets, and external apps.
* **Slow Response Times:** No consistent way to prioritize high-intent sales inquiries vs general support.
* **Bloated Third-Party SaaS Fees:** Paying $50–$200/month for external CRM tools when a native WordPress inbox works better.

ContactIn addresses these blockers by centralizing submissions, improving triage speed, maintaining contact records, and giving your team clearer analytics and delivery visibility.

= How It Works =

1. **Capture:** Add forms via shortcode `[contactin_form]`, Gutenberg block, or Elementor widget.
2. **Protect:** Submissions pass through reCAPTCHA v3 and honeypot before landing in your database.
3. **Classify:** AI/Keyword rules route inquiries by intent (Sales, Support, Complaints, Urgent).
4. **Triage:** Manage, assign, and respond from the centralized inbox CRM.
5. **Guarantee:** Async queue engine retries failed emails and logs items to the Dead-Letter Queue (DLQ).

= Who It’s For =

ContactIn is designed for teams that need faster response times, centralized lead management, and delivery assurance:

* SaaS and B2B Software Companies
* Real Estate Agencies & Brokers
* E-Commerce Stores & Marketplaces
* Legal Firms & Professional Services
* Healthcare & Medical Clinics
* Marketing Agencies & Multi-Client Operators
* Logistics, Construction, and Service Providers

= Features =

**Form Builder & Frontend Integration**
* Shortcode: `[contactin_form]`
* Native Gutenberg block & Elementor widget
* Configurable fields, custom validation, and responsive UI
* Per-profile settings (custom labels, success messages, consent checkboxes)

**Unified Inbox & Lead Management CRM**
* Centralized submission inbox with zero-loss capture
* Instant search, filtering, and bulk status updates
* Lead pipeline status: Unread, Read, Archived, Spam
* Threading and internal team notes
* Automatic contact creation and timeline updates
* Phone number normalization and validation
* Duplicate submission detection and cleanup tools
* CSV and JSON data export capabilities

**Intent Classification Engine**
* Intent Categories: Sales, Support, Feedback, Complaints, Questions
* Custom keyword rule support per industry
* 19 Business-Type Profiles: Generic, SaaS, E-commerce, Service, Healthcare, Education, Hospitality, Banking, Insurance, Embassy, Quality Agency, Travel Agency, Supermarket, Legal, Logistics, Telecom, Automotive, Construction, Real Estate

**Deliverability, Queue & Dead-Letter Queue (DLQ)**
* Native SMTP configuration (Gmail, SendGrid, Mailgun, AWS SES, Outlook, custom)
* SPF/DKIM/DMARC alignment check warnings
* Async queue with retry logic, deduplication window, and DLQ handling
* Idempotent retry engine with 30-day maintenance window
* Stalled processor recovery (automatically resets stuck "processing" items older than 10 minutes)
* Real-time delivery logs and queue health observability

**Security & Compliance**
* Google reCAPTCHA v3 integration
* Honeypot anti-spam and rate-limiting rules
* GDPR consent checkbox support for compliance-oriented workflows

= What's Included =

This free version includes a complete inquiry management solution:

* Unlimited form profiles with custom fields and overrides
* Unified contact form inbox + search/filter + bulk actions
* Keyword-based intent classification with 19 industry profiles
* Full deliverability suite: SMTP checks + delivery logs + queue retries
* Automatic contact database creation and profile updates

= ContactIn Pro =

For teams requiring advanced workflow automations, deep reporting, and multi-team integrations, learn more at: https://contactinbox.app/

= Source Code and Build Assets =

Public source snapshots and release packages for this plugin are available at:

* https://github.com/bizjaved/contactin-release
* https://github.com/bizjaved/contactin-release/releases

This plugin includes human-readable source for distributed minified assets in `assets/src/js/` and `assets/src/css/`.

= Quick Start =

1. Install and activate ContactIn.
2. Add `[contactin_form]` to any page or post (or use Gutenberg/Elementor).
3. Configure email delivery in **ContactIn > Settings > Email** (SMTP recommended).
4. Configure anti-spam under **ContactIn > Settings > Security**.
5. Manage all incoming submissions from **ContactIn > Inbox**.

== Installation ==

1. Upload the plugin folder to `/wp-content/plugins/contactin/` or install via **Plugins > Add New**.
2. Activate the plugin.
3. Place `[contactin_form]` on your desired page.
4. Configure settings under **ContactIn > Settings**.

**Requirements**
* WordPress 6.4+
* PHP 7.4+
* MySQL 5.6+ or MariaDB 10.0+

== Frequently Asked Questions ==

= Is this a replacement for Contact Form 7 database plugins like CFDB7? =

Yes. ContactIn replaces outdated CF7 database add-ons with a modern, built-in inquiry inbox, intent classification, and queue retry engine.

= Can ContactIn store contact form submissions in the WordPress database? =

Yes. Every form submission is stored securely in your WordPress database before email notification triggers, preventing lost leads.

= How does the Dead-Letter Queue (DLQ) work? =

If an email notification fails to send (due to SMTP downtime or server errors), it is placed in the Dead-Letter Queue (DLQ). You can view diagnostic logs and trigger manual or automatic retries directly from the maintenance panel.

= Does ContactIn integrate with Salesforce? =

Yes. ContactIn supports syncing contact form submissions, fields, and attachments to Salesforce CRM.

= Which industries are supported by Intent Classification? =

Includes 19 tailored profiles: Generic, SaaS, E-commerce, Service, Healthcare, Education, Hospitality, Banking, Insurance, Embassy, Quality Agency, Travel Agency, Supermarket, Legal, Logistics, Telecom, Automotive, Construction, and Real Estate.

= Is ContactIn GDPR compliant? =

It includes consent checkbox support, field normalization, and local database storage control.

== External Services ==

This plugin can connect to external services depending on your settings:

1. **SMTP Provider (Optional):** Sends notification emails via your configured SMTP server (Gmail, SendGrid, Mailgun, AWS SES, etc.).
2. **Google reCAPTCHA v3 (Optional):** Evaluates submission traffic for anti-spam scoring (https://policies.google.com/privacy).
3. **Salesforce CRM (Optional):** Syncs lead submissions and attachments to your Salesforce instance when enabled by an admin (https://www.salesforce.com/company/privacy/).

== Documentation ==

* Website: https://contactinbox.app/
* Support: https://contactinbox.app/

== Credits ==

Developed by Javed Ahsan.