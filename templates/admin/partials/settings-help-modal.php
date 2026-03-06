<?php
/**
 * Template: Settings Help Modal
 * File: templates/admin/partials/settings-help-modal.php
 * Description: Comprehensive help guide for admin settings
 */

if (!defined('ABSPATH')) exit;
use ContactInbox\Core\Config;
?>

<div id="cin-help-modal" class="cin-modal cin-modal-hidden" data-cin-help-modal="true">
    <div class="cin-modal-overlay"></div>
    <div class="cin-modal-content">
        <div class="cin-modal-header">
            <h2><?php esc_html_e('Contact Inbox - Settings Guide', 'contact-inbox'); ?></h2>
            <button type="button" class="cin-modal-close" aria-label="<?php esc_html_e('Close', 'contact-inbox'); ?>">
                <span aria-hidden="true">&times;</span>
            </button>
        </div>

        <div class="cin-modal-body">
            <!-- Quick Navigation -->
            <div class="cin-help-nav">
                <h3><?php esc_html_e('Quick Navigation', 'contact-inbox'); ?></h3>
                <ul>
                    <li><a href="#help-general" class="cin-help-link"><?php esc_html_e('General Settings', 'contact-inbox'); ?></a></li>
                    <li><a href="#help-recaptcha" class="cin-help-link">reCAPTCHA</a></li>
                    <li><a href="#help-smtp" class="cin-help-link">SMTP</a></li>
                    <li><a href="#help-notifications" class="cin-help-link"><?php esc_html_e('Notifications', 'contact-inbox'); ?></a></li>
                    <li><a href="#help-form" class="cin-help-link"><?php esc_html_e('Form', 'contact-inbox'); ?></a></li>
                    <li><a href="#help-advanced" class="cin-help-link"><?php esc_html_e('Advanced', 'contact-inbox'); ?></a></li>
                </ul>
            </div>

            <!-- General Settings Help -->
            <div id="help-general" class="cin-help-section">
                <h3><?php esc_html_e('📋 General Settings', 'contact-inbox'); ?></h3>
                
                <div class="cin-help-item">
                    <h4><?php esc_html_e('Privacy Policy URL', 'contact-inbox'); ?></h4>
                    <p><?php esc_html_e('Link to your website\'s privacy policy. This is required for GDPR compliance and data protection.', 'contact-inbox'); ?></p>
                    <ul>
                        <li><?php esc_html_e('Use your site\'s privacy policy page URL', 'contact-inbox'); ?></li>
                        <li><?php esc_html_e('Example: https://yoursite.com/privacy-policy', 'contact-inbox'); ?></li>
                        <li><?php esc_html_e('Leave blank to use WordPress default privacy page', 'contact-inbox'); ?></li>
                    </ul>
                </div>

                <div class="cin-help-item">
                    <h4><?php esc_html_e('Consent Text', 'contact-inbox'); ?></h4>
                    <p><?php esc_html_e('Message displayed to users regarding data processing. Inform them how their data will be used.', 'contact-inbox'); ?></p>
                    <ul>
                        <li><?php esc_html_e('Example: "I consent to having my name and email stored for contact purposes."', 'contact-inbox'); ?></li>
                        <li><?php esc_html_e('Make it clear and simple', 'contact-inbox'); ?></li>
                        <li><?php esc_html_e('Required for GDPR compliance', 'contact-inbox'); ?></li>
                    </ul>
                </div>

                <div class="cin-help-item">
                    <h4><?php esc_html_e('Success Message', 'contact-inbox'); ?></h4>
                    <p><?php esc_html_e('Message shown to users after they successfully submit a form.', 'contact-inbox'); ?></p>
                    <ul>
                        <li><?php esc_html_e('Be friendly and encouraging', 'contact-inbox'); ?></li>
                        <li><?php esc_html_e('Example: "Thank you! We\'ll get back to you shortly."', 'contact-inbox'); ?></li>
                    </ul>
                </div>

                <div class="cin-help-item">
                    <h4><?php esc_html_e('Confetti on Success', 'contact-inbox'); ?></h4>
                    <p><?php esc_html_e('Shows a fun confetti animation when users submit the form successfully. Great for user engagement!', 'contact-inbox'); ?></p>
                    <ul>
                        <li><?php esc_html_e('Check to enable confetti animation', 'contact-inbox'); ?></li>
                        <li><?php esc_html_e('Uncheck to keep forms simple and professional', 'contact-inbox'); ?></li>
                    </ul>
                </div>
            </div>

            <!-- reCAPTCHA Help -->
            <div id="help-recaptcha" class="cin-help-section">
                <h3><?php esc_html_e('🔒 reCAPTCHA Configuration', 'contact-inbox'); ?></h3>
                
                <div class="cin-help-item">
                    <h4><?php esc_html_e('What is reCAPTCHA v3?', 'contact-inbox'); ?></h4>
                    <p><?php esc_html_e('reCAPTCHA v3 protects your forms from spam and automated abuse by detecting bot behavior in the background—without annoying users with puzzles or checkboxes.', 'contact-inbox'); ?></p>
                </div>

                <div class="cin-help-item">
                    <h4><?php esc_html_e('How to Get API Keys', 'contact-inbox'); ?></h4>
                    <ol>
                        <li><?php esc_html_e('Visit: https://www.google.com/recaptcha/admin', 'contact-inbox'); ?></li>
                        <li><?php esc_html_e('Click "+" to create a new site', 'contact-inbox'); ?></li>
                        <li><?php esc_html_e('Choose reCAPTCHA v3', 'contact-inbox'); ?></li>
                        <li><?php esc_html_e('Add your domain', 'contact-inbox'); ?></li>
                        <li><?php esc_html_e('Copy Site Key and Secret Key', 'contact-inbox'); ?></li>
                    </ol>
                </div>

                <div class="cin-help-item">
                    <h4><?php esc_html_e('Site Key vs Secret Key', 'contact-inbox'); ?></h4>
                    <ul>
                        <li><strong><?php esc_html_e('Site Key:', 'contact-inbox'); ?></strong> <?php esc_html_e('Public key used in frontend code. Safe to expose.', 'contact-inbox'); ?></li>
                        <li><strong><?php esc_html_e('Secret Key:', 'contact-inbox'); ?></strong> <?php esc_html_e('Private key for backend verification. NEVER share publicly.', 'contact-inbox'); ?></li>
                    </ul>
                </div>

                <div class="cin-help-item">
                    <h4><?php esc_html_e('Enable reCAPTCHA', 'contact-inbox'); ?></h4>
                    <p><?php esc_html_e('Check this box to activate reCAPTCHA protection on all contact forms.', 'contact-inbox'); ?></p>
                    <ul>
                        <li><?php esc_html_e('Recommended: Always keep this enabled for security', 'contact-inbox'); ?></li>
                    </ul>
                </div>
            </div>

            <!-- SMTP Help -->
            <div id="help-smtp" class="cin-help-section">
                <h3><?php esc_html_e('📧 SMTP Configuration', 'contact-inbox'); ?></h3>
                
                <div class="cin-help-item">
                    <h4><?php esc_html_e('Why SMTP?', 'contact-inbox'); ?></h4>
                    <p><?php esc_html_e('SMTP (Simple Mail Transfer Protocol) ensures your emails are reliably delivered with proper authentication. Many hosting providers have mail delivery issues—SMTP solves this.', 'contact-inbox'); ?></p>
                </div>

                <div class="cin-help-item">
                    <h4><?php esc_html_e('Enable SMTP', 'contact-inbox'); ?></h4>
                    <p><?php esc_html_e('Check to use SMTP for sending emails instead of WordPress default mail function.', 'contact-inbox'); ?></p>
                </div>

                <div class="cin-help-item">
                    <h4><?php esc_html_e('Common SMTP Providers', 'contact-inbox'); ?></h4>
                    <table class="cin-help-table">
                        <tr>
                            <td><strong>Gmail</strong></td>
                            <td>smtp.gmail.com:587 (TLS)</td>
                        </tr>
                        <tr>
                            <td><strong>Outlook</strong></td>
                            <td>smtp-mail.outlook.com:587 (TLS)</td>
                        </tr>
                        <tr>
                            <td><strong>SendGrid</strong></td>
                            <td>smtp.sendgrid.net:587 (TLS)</td>
                        </tr>
                        <tr>
                            <td><strong>AWS SES</strong></td>
                            <td>email-smtp.[region].amazonaws.com:587 (TLS)</td>
                        </tr>
                    </table>
                </div>

                <div class="cin-help-item">
                    <h4><?php esc_html_e('SMTP Settings Explained', 'contact-inbox'); ?></h4>
                    <ul>
                        <li><strong><?php esc_html_e('Host:', 'contact-inbox'); ?></strong> <?php esc_html_e('SMTP server address (provided by your email service)', 'contact-inbox'); ?></li>
                        <li><strong><?php esc_html_e('Port:', 'contact-inbox'); ?></strong> <?php esc_html_e('Usually 587 (TLS) or 465 (SSL)', 'contact-inbox'); ?></li>
                        <li><strong><?php esc_html_e('Encryption:', 'contact-inbox'); ?></strong> <?php esc_html_e('None (not recommended), SSL, or TLS', 'contact-inbox'); ?></li>
                        <li><strong><?php esc_html_e('Username:', 'contact-inbox'); ?></strong> <?php esc_html_e('Your email address or account username', 'contact-inbox'); ?></li>
                        <li><strong><?php esc_html_e('Password:', 'contact-inbox'); ?></strong> <?php esc_html_e('Email account password or app-specific password', 'contact-inbox'); ?></li>
                    </ul>
                </div>

                <div class="cin-help-item">
                    <h4><?php esc_html_e('Test SMTP Connection', 'contact-inbox'); ?></h4>
                    <p><?php esc_html_e('Click "Test SMTP" to verify your settings before saving. This sends a test email to the admin email address.', 'contact-inbox'); ?></p>
                    <ul>
                        <li><?php esc_html_e('✅ Success message: Your SMTP is working correctly', 'contact-inbox'); ?></li>
                        <li><?php esc_html_e('❌ Error: Check your host, port, and credentials', 'contact-inbox'); ?></li>
                    </ul>
                </div>
            </div>

            <!-- Notifications Help -->
            <div id="help-notifications" class="cin-help-section">
                <h3><?php esc_html_e('🔔 Email Notifications', 'contact-inbox'); ?></h3>
                
                <div class="cin-help-item">
                    <h4><?php esc_html_e('Send Form Submission to Admin', 'contact-inbox'); ?></h4>
                    <p><?php esc_html_e('When checked, the admin receives an email notification every time someone submits the contact form.', 'contact-inbox'); ?></p>
                    <ul>
                        <li><?php esc_html_e('Recommended: Keep enabled to stay informed about new inquiries', 'contact-inbox'); ?></li>
                    </ul>
                </div>

                <div class="cin-help-item">
                    <h4><?php esc_html_e('Admin Email(s)', 'contact-inbox'); ?></h4>
                    <p><?php esc_html_e('Email address(es) where admin notifications are sent.', 'contact-inbox'); ?></p>
                    <ul>
                        <li><?php esc_html_e('Single email: contact@example.com', 'contact-inbox'); ?></li>
                        <li><?php esc_html_e('Multiple emails: contact@example.com, support@example.com, info@example.com', 'contact-inbox'); ?></li>
                        <li><?php esc_html_e('Separate with commas and spaces', 'contact-inbox'); ?></li>
                        <li><?php esc_html_e('Max 254 characters per email address', 'contact-inbox'); ?></li>
                    </ul>
                </div>

                <div class="cin-help-item">
                    <h4><?php esc_html_e('Send Copy to User', 'contact-inbox'); ?></h4>
                    <p><?php esc_html_e('When checked, users receive a confirmation email after submitting the form.', 'contact-inbox'); ?></p>
                    <ul>
                        <li><?php esc_html_e('Recommended: Keep enabled for better user experience', 'contact-inbox'); ?></li>
                        <li><?php esc_html_e('Users feel acknowledged and know their message was received', 'contact-inbox'); ?></li>
                    </ul>
                </div>
            </div>

            <!-- Form Help -->
            <div id="help-form" class="cin-help-section">
                <h3><?php esc_html_e('📝 Form Customisation', 'contact-inbox'); ?></h3>
                
                <div class="cin-help-item">
                    <h4><?php esc_html_e('Enable Subject Field', 'contact-inbox'); ?></h4>
                    <p><?php esc_html_e('Check to let users enter a subject for their inquiry.', 'contact-inbox'); ?></p>
                    <ul>
                        <li><?php esc_html_e('Recommended: Enable for better organization in inbox', 'contact-inbox'); ?></li>
                    </ul>
                </div>

                <div class="cin-help-item">
                    <h4><?php esc_html_e('Enable File Attachment', 'contact-inbox'); ?></h4>
                    <p><?php esc_html_e('Check to allow users to upload files (documents, images, etc.) with their inquiry.', 'contact-inbox'); ?></p>
                    <ul>
                        <li><?php esc_html_e('Configure allowed file types below', 'contact-inbox'); ?></li>
                        <li><?php esc_html_e('Set max file size to prevent abuse', 'contact-inbox'); ?></li>
                    </ul>
                </div>

                <div class="cin-help-item">
                    <h4><?php esc_html_e('Field Length Limits', 'contact-inbox'); ?></h4>
                    <p><?php esc_html_e('Control the minimum and maximum length of form fields to ensure quality submissions and prevent spam.', 'contact-inbox'); ?></p>
                    <ul>
                        <li><strong><?php esc_html_e('Max Chars:', 'contact-inbox'); ?></strong> <?php esc_html_e('Maximum characters allowed (prevents extremely long inputs)', 'contact-inbox'); ?></li>
                        <li><strong><?php esc_html_e('Min Words:', 'contact-inbox'); ?></strong> <?php esc_html_e('Minimum words required (prevents spam with single words)', 'contact-inbox'); ?></li>
                        <li><?php esc_html_e('Example: Min 2 words, Max 50 chars for name field', 'contact-inbox'); ?></li>
                    </ul>
                </div>

                <div class="cin-help-item">
                    <h4><?php esc_html_e('Allowed File Types', 'contact-inbox'); ?></h4>
                    <p><?php esc_html_e('Select which file types users can upload. Defaults include common safe formats.', 'contact-inbox'); ?></p>
                    <ul>
                        <li><?php esc_html_e('Hold Ctrl (Windows/Linux) or Command (Mac) to select multiple types', 'contact-inbox'); ?></li>
                        <li><?php esc_html_e('Popular types listed first', 'contact-inbox'); ?></li>
                        <li><?php esc_html_e('Recommendation: Only allow file types you actually need', 'contact-inbox'); ?></li>
                    </ul>
                </div>

                <div class="cin-help-item">
                    <h4><?php esc_html_e('Max File Size (MB)', 'contact-inbox'); ?></h4>
                    <p><?php esc_html_e('Prevents users from uploading extremely large files.', 'contact-inbox'); ?></p>
                    <ul>
                        <li><?php esc_html_e('Recommendation: 5-10 MB for most sites', 'contact-inbox'); ?></li>
                        <li><?php esc_html_e('Check your hosting provider\'s limits', 'contact-inbox'); ?></li>
                    </ul>
                </div>

                <div class="cin-help-item">
                    <h4><?php esc_html_e('Max Files per Submission', 'contact-inbox'); ?></h4>
                    <p><?php esc_html_e('Limits the number of files users can upload at once.', 'contact-inbox'); ?></p>
                    <ul>
                        <li><?php esc_html_e('Recommendation: 1-3 files per submission', 'contact-inbox'); ?></li>
                    </ul>
                </div>
            </div>

            <!-- Advanced Help -->
            <div id="help-advanced" class="cin-help-section">
                <h3><?php esc_html_e('⚙️ Advanced Settings', 'contact-inbox'); ?></h3>

                <div class="cin-help-item">
                    <h4><?php esc_html_e('REST API Service', 'contact-inbox'); ?></h4>
                    <p><?php esc_html_e('Enable this if you need programmatic access to submissions or want to push data into the hub from custom integrations. Disable it on sites that do not expose API credentials to reduce the attack surface.', 'contact-inbox'); ?></p>
                    <ul>
                        <li><?php esc_html_e('Requires authentication before any data is accepted.', 'contact-inbox'); ?></li>
                        <li><?php esc_html_e('Turn it off on sites that only use the built-in form.', 'contact-inbox'); ?></li>
                    </ul>
                </div>

                <div class="cin-help-item">
                    <h4><?php esc_html_e('Webhooks Service', 'contact-inbox'); ?></h4>
                    <p><?php esc_html_e('Allows external systems to push events or updates back into Contact Inbox without using the REST API. Useful for marketing automation, CRMs, or middleware platforms.', 'contact-inbox'); ?></p>
                    <ul>
                        <li><?php esc_html_e('Only enable if you trust the systems calling your webhook endpoint.', 'contact-inbox'); ?></li>
                        <li><?php esc_html_e('Rotate secrets regularly and monitor the Webhook Log for errors.', 'contact-inbox'); ?></li>
                    </ul>
                </div>

                <div class="cin-help-item">
                    <h4><?php esc_html_e('Log Retention & Cleanup', 'contact-inbox'); ?></h4>
                    <p><?php esc_html_e('Email, REST, and CRM logs can grow quickly. Use the retention fields to define how many days of history the system should keep before auto-purging older rows.', 'contact-inbox'); ?></p>
                    <ul>
                        <li><?php esc_html_e('Choose longer windows while debugging; shorten them after go-live to save disk space.', 'contact-inbox'); ?></li>
                        <li><?php esc_html_e('Retention relies on cron, so ensure background jobs are running.', 'contact-inbox'); ?></li>
                    </ul>
                </div>

                <div class="cin-help-item">
                    <h4><?php esc_html_e('Background Job Scheduling', 'contact-inbox'); ?></h4>
                    <p><?php esc_html_e('The Queue Processor keeps CRM, email, and webhook deliveries moving. Adjust the interval if you need faster retries or want to reduce load on small servers.', 'contact-inbox'); ?></p>
                    <ul>
                        <li><?php esc_html_e('"Run Now" triggers the job immediately—ideal after changing SMTP or CRM credentials.', 'contact-inbox'); ?></li>
                        <li><?php esc_html_e('Track real-time status in Dashboard → Background Jobs.', 'contact-inbox'); ?></li>
                        <li><?php esc_html_e('Use shorter intervals (1–2 minutes) for busy sites; hourly for low-volume staging installs.', 'contact-inbox'); ?></li>
                    </ul>
                </div>

                <div class="cin-help-item">
                    <h4><?php esc_html_e('Advanced Admin Tips', 'contact-inbox'); ?></h4>
                    <ul>
                        <li><?php esc_html_e('💾 Always click "Save Settings" before switching tabs or leaving the page.', 'contact-inbox'); ?></li>
                        <li><?php esc_html_e('🧪 Use "Test SMTP" whenever mail credentials change—do it before going live.', 'contact-inbox'); ?></li>
                        <li><?php esc_html_e('📋 Inbox shows every submission; use CRM & REST logs for delivery diagnostics.', 'contact-inbox'); ?></li>
                        <li><?php esc_html_e('📊 Analytics Dashboard highlights queue health, spam rejection trends, and cron alerts.', 'contact-inbox'); ?></li>
                        <li><?php esc_html_e('🔐 Pair reCAPTCHA with field length limits to cut automated spam.', 'contact-inbox'); ?></li>
                        <li><?php esc_html_e('🧹 Visit Maintenance → Attachment Cleanup to purge orphaned uploads regularly.', 'contact-inbox'); ?></li>
                    </ul>
                </div>
            </div>

            <!-- Common Issues -->
            <div class="cin-help-issues">
                <h3>❓ <?php esc_html_e('Common Issues & Solutions', 'contact-inbox'); ?></h3>
                
                <div class="cin-help-pro-tip-item">
                    <h4><?php esc_html_e('Emails not being sent?', 'contact-inbox'); ?></h4>
                    <p><?php esc_html_e('Enable SMTP and test the connection. Check that emails are not marked as spam.', 'contact-inbox'); ?></p>
                </div>

                <div class="cin-help-pro-tip-item">
                    <h4><?php esc_html_e('Getting too much spam?', 'contact-inbox'); ?></h4>
                    <p><?php esc_html_e('Increase field length requirements and enable reCAPTCHA v3 protection.', 'contact-inbox'); ?></p>
                </div>

                <div class="cin-help-pro-tip-item">
                    <h4><?php esc_html_e('Form not showing on page?', 'contact-inbox'); ?></h4>
                    <p><?php esc_html_e('Use the shortcode [contact-inbox] in the page editor to display the contact form.', 'contact-inbox'); ?></p>
                </div>

                <div class="cin-help-pro-tip-item">
                    <h4><?php esc_html_e('Users report file upload fails?', 'contact-inbox'); ?></h4>
                    <p><?php esc_html_e('Check max file size setting, server upload limits, and allowed file types.', 'contact-inbox'); ?></p>
                </div>
            </div>
        </div>

        <div class="cin-modal-footer">
            <p><?php esc_html_e('For more help, visit our documentation or contact support.', 'contact-inbox'); ?></p>
        </div>
    </div>
</div>
