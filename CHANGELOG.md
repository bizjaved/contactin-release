# Changelog

All notable changes to Contact Inbox are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

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

---

[1.0]: https://github.com/bizjaved/contact-inbox-free/releases/tag/v1.0
