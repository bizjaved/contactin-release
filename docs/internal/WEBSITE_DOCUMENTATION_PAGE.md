# Contact Inbox Documentation (Website Page)

Use this as your website documentation page content.

## Recommended WordPress Components (Gutenberg)

- **Group** block for each major section
- **Heading** blocks (H2/H3) for structure
- **Paragraph** blocks for explanations
- **List** blocks for features and steps
- **Table** block for Free vs Pro comparison
- **Details** block for FAQs (accordion style)
- **Buttons** block for CTA links (Upgrade, Support, Docs)
- **Code** block for shortcode snippets
- **Table of Contents** block (if your theme supports it, or via plugin)

---

## Hero / Intro

**Contact Inbox** is a smart WordPress contact form plugin with built-in intent classification, secure inbox management, spam protection, and analytics.

### Who it is for

- Business websites
- Agencies and freelancers
- Blogs and content sites
- Teams needing organized contact workflows

---

## Key Features

### Smart Contact Form

- Add form anywhere using shortcode: `[contact_inbox_form]`
- Gutenberg block support
- Elementor widget support
- Mobile-friendly and AJAX submission

### Intent Classification

- Automatically categorizes messages by intent:
  - Sales
  - Support
  - Feedback
  - Complaints
  - Questions
- Confidence scoring for transparency
- Keyword highlighting for context

### Secure Inbox

- Centralized admin inbox for all submissions
- Search and filtering
- Status management: unread, read, archived, spam
- Bulk actions for faster workflow

### Security & Spam Protection

- reCAPTCHA v3 integration
- Honeypot protection
- Rate limiting
- Duplicate submission detection

### Notifications & Email

- SMTP support (Gmail, SendGrid, Mailgun, etc.)
- Admin notifications
- User acknowledgment emails
- Customizable templates

### Analytics

- Submission overview
- Intent distribution insights
- Basic trends and performance tracking

---

## Installation

### Method 1: WordPress Dashboard

1. Go to **Plugins → Add New**
2. Search for **Contact Inbox**
3. Click **Install Now** and **Activate**

### Method 2: Manual Upload

1. Upload plugin to `/wp-content/plugins/contact-inbox/`
2. Activate from **Plugins** screen
3. Open **Contact Inbox** settings

### Requirements

- WordPress 6.4+
- PHP 7.4+
- MySQL 5.6+ or MariaDB 10.0+

---

## Quick Start

1. Create or edit a page
2. Insert shortcode `[contact_inbox_form]` (or use Gutenberg block)
3. Configure SMTP (optional)
4. Configure reCAPTCHA v3 (optional)
5. Test submission and verify inbox delivery

---

## Shortcodes

### Main Form

`[contact_inbox_form]`

---

## Free vs Pro

| Feature | Free | Pro |
|---|---|---|
| Intent classification | ✅ | ✅ |
| Confidence scores | ✅ | ✅ |
| Keyword highlighting | ✅ | ✅ |
| Secure inbox + filters | ✅ | ✅ |
| Email notifications | ✅ | ✅ |
| reCAPTCHA v3 | ✅ | ✅ |
| Gutenberg + Elementor | ✅ | ✅ |
| Adaptive learning | ❌ | ✅ |
| Custom classification rules | ❌ | ✅ |
| Salesforce CRM sync | ❌ | ✅ |
| Advanced file handling | ❌ | ✅ |
| GDPR tools | ❌ | ✅ |

---

## FAQ

### How do I add the form?

Use `[contact_inbox_form]` in any page/post, or insert the Contact Inbox Gutenberg block.

### Does it work with Elementor?

Yes. A native Elementor widget is included.

### How does spam protection work?

The plugin uses layered protection: reCAPTCHA v3, honeypot, rate limiting, and duplicate detection.

### Can I use SMTP?

Yes. You can connect SMTP providers such as Gmail, SendGrid, or Mailgun.

---

## Support & Links

- GitHub: https://github.com/bizjaved/contact-inbox
- Issues: https://github.com/bizjaved/contact-inbox/issues
- Pro Version: https://contactinbox.app/

---

## Suggested Page Settings (Website)

- Slug: `contact-inbox-docs`
- Template: Default page template
- Add this page to main navigation under **Documentation**
- Enable search indexing for this page

