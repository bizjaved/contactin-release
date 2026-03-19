# Changelog

All notable changes to Contact Inbox are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## Historical Note

This plugin started development under the folder/repository name **Secure ContactUs Hub** (`secure-contactus-hub`) on **2025-11-01**.
The product was later refocused/rebranded as **Contact Inbox**, with versioning reset for the public free release line.

## [Unreleased]

### Added

- Legacy changelog entries migrated from the pre-rename line:
  - `secure-contactus-hub 1.0.0` (2025-11-01)
  - `secure-contactus-hub 1.5.0` (2026-01-17)
- Historical note documenting project origin and rebrand/version-reset context.

### Changed

- Changelog chronology clarified to preserve development history before Contact Inbox `1.0`.
- WordPress.org compliance disclosures expanded in `readme.txt` for optional external services, including data sent, transmission timing, and policy/terms links.
- Privacy messaging aligned in admin FAQ copy to clarify that outbound transmission occurs only when optional integrations are enabled/configured.
- Webhook outbound delivery defaults updated to opt-in (`webhooks_enable` now disabled by default for new installs).
- Plugin display name updated to `ContactIn` to improve distinctiveness in WordPress.org directory review.

## [1.0] - 2026-02-10

### Initial Release

Contact Inbox - the first release of a focused contact form and inbox management system for WordPress.

### Added

#### Form Features
- Simple shortcode implementation: `[contact_inbox_form]`
- Gutenberg block support
- Elementor widget integration
- Customizable form fields (name, email, phone, subject, message)
- Client-side and server-side validation
- AJAX form submission
- Mobile-responsive form design
- Honeypot spam protection fields
- Form submission rate limiting
- Custom success/error messages

#### Inbox Management
- Centralized admin inbox for all submissions
- Search and filtering
- Bulk actions (mark as read, archive, delete, mark as spam)
- Submission status tracking (unread, read, archived, spam)
- Pagination and sortable columns
- Duplicate submission detection

#### Analytics & Dashboard
- Basic analytics dashboard
- Today's Snapshot widget
- Submission Metrics widget
- Date range filtering
- Visual charts and graphs

#### Security & Spam Protection
- Google reCAPTCHA v3 integration
- Spam filtering
- IP-based rate limiting
- Honeypot field implementation
- Nonce validation
- SQL injection protection
- XSS (Cross-Site Scripting) prevention

#### Email System
- SMTP configuration support (Gmail, SendGrid, Mailgun, etc.)
- Custom email templates with variables
- Admin notification emails
- User confirmation emails (optional)
- HTML and plain text email support

#### Developer Features
- Action hooks for extensibility
- Filter hooks for customization
- WP-CLI commands
- Logging and debug tools
- WordPress Coding Standards compliance

#### Admin Interface
- Get Started onboarding page
- Settings panel with tabbed sections
- System status page
- Email testing functionality

#### Internationalization
- Translation-ready with .pot file
- Text domain: `contact-inbox`
- RTL language support

#### Testing & Quality Assurance
- Core functionality tests
- Security checks
- Browser compatibility testing
- Mobile responsiveness testing

### Technical Details

- **Minimum Requirements:**
  - WordPress 6.4 or higher
  - PHP 7.4 or higher
  - MySQL 5.7+ or MariaDB 10.2+

- **Tested Up To:**
  - WordPress 6.9.1
  - PHP 8.3

- **Database Tables Created:**
  - `{prefix}_contactinbox_submissions`
  - `{prefix}_contactinbox_email_log`

### Documentation

- Inline code documentation
- Example integration files in `/examples`
- README and WordPress plugin guidelines compliance

## [secure-contactus-hub 1.5.0] - 2026-01-17

### Legacy Release (Pre-rename)

Final documented release before the Contact Inbox rename/version reset.

### Added

- CRM integrations (Salesforce, HubSpot)
- Analytics dashboard with performance metrics
- REST API with authentication
- Advanced rate limiting and security hardening
- Webhook support for custom integrations
- Improved admin UX with bulk actions

### Fixed

- Email duplication in queue processing
- SMTP/mailbox handling improvements
- Admin notification reliability improvements
- Maintenance control fixes

## [secure-contactus-hub 1.0.0] - 2025-11-01

### Legacy Initial Release (Pre-rename)

First public baseline under the original plugin name.

### Added

- Basic contact form with shortcode (`[secure_contactus]`)
- Honeypot spam protection and rate limiting
- Admin inbox and settings panel
- AJAX submission flow

---

[1.0]: https://github.com/bizjaved/contact-inbox/releases/tag/v1.0
[secure-contactus-hub 1.5.0]: https://github.com/bizjaved/secure-contactus-hub
[secure-contactus-hub 1.0.0]: https://github.com/bizjaved/secure-contactus-hub
