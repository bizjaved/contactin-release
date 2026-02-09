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
            <h2><?php _e('Contact Inbox - Settings Guide', Config::TEXTDOMAIN); ?></h2>
            <button type="button" class="cin-modal-close" aria-label="<?php _e('Close', Config::TEXTDOMAIN); ?>">
                <span aria-hidden="true">&times;</span>
            </button>
        </div>

        <div class="cin-modal-body">
            <!-- Quick Navigation -->
            <div class="cin-help-nav">
                <h3><?php _e('Quick Navigation', Config::TEXTDOMAIN); ?></h3>
                <ul>
                    <li><a href="#help-general" class="cin-help-link"><?php _e('General Settings', Config::TEXTDOMAIN); ?></a></li>
                    <li><a href="#help-recaptcha" class="cin-help-link">reCAPTCHA</a></li>
                    <li><a href="#help-smtp" class="cin-help-link">SMTP</a></li>
                    <li><a href="#help-notifications" class="cin-help-link"><?php _e('Notifications', Config::TEXTDOMAIN); ?></a></li>
                    <li><a href="#help-form" class="cin-help-link"><?php _e('Form', Config::TEXTDOMAIN); ?></a></li>
                    <li><a href="#help-advanced" class="cin-help-link"><?php _e('Advanced', Config::TEXTDOMAIN); ?></a></li>
                </ul>
            </div>

            <!-- General Settings Help -->
            <div id="help-general" class="cin-help-section">
                <h3><?php _e('📋 General Settings', Config::TEXTDOMAIN); ?></h3>
                
                <div class="cin-help-item">
                    <h4><?php _e('Privacy Policy URL', Config::TEXTDOMAIN); ?></h4>
                    <p><?php _e('Link to your website\'s privacy policy. This is required for GDPR compliance and data protection.', Config::TEXTDOMAIN); ?></p>
                    <ul>
                        <li><?php _e('Use your site\'s privacy policy page URL', Config::TEXTDOMAIN); ?></li>
                        <li><?php _e('Example: https://yoursite.com/privacy-policy', Config::TEXTDOMAIN); ?></li>
                        <li><?php _e('Leave blank to use WordPress default privacy page', Config::TEXTDOMAIN); ?></li>
                    </ul>
                </div>

                <div class="cin-help-item">
                    <h4><?php _e('Consent Text', Config::TEXTDOMAIN); ?></h4>
                    <p><?php _e('Message displayed to users regarding data processing. Inform them how their data will be used.', Config::TEXTDOMAIN); ?></p>
                    <ul>
                        <li><?php _e('Example: "I consent to having my name and email stored for contact purposes."', Config::TEXTDOMAIN); ?></li>
                        <li><?php _e('Make it clear and simple', Config::TEXTDOMAIN); ?></li>
                        <li><?php _e('Required for GDPR compliance', Config::TEXTDOMAIN); ?></li>
                    </ul>
                </div>

                <div class="cin-help-item">
                    <h4><?php _e('Success Message', Config::TEXTDOMAIN); ?></h4>
                    <p><?php _e('Message shown to users after they successfully submit a form.', Config::TEXTDOMAIN); ?></p>
                    <ul>
                        <li><?php _e('Be friendly and encouraging', Config::TEXTDOMAIN); ?></li>
                        <li><?php _e('Example: "Thank you! We\'ll get back to you shortly."', Config::TEXTDOMAIN); ?></li>
                    </ul>
                </div>

                <div class="cin-help-item">
                    <h4><?php _e('Confetti on Success', Config::TEXTDOMAIN); ?></h4>
                    <p><?php _e('Shows a fun confetti animation when users submit the form successfully. Great for user engagement!', Config::TEXTDOMAIN); ?></p>
                    <ul>
                        <li><?php _e('Check to enable confetti animation', Config::TEXTDOMAIN); ?></li>
                        <li><?php _e('Uncheck to keep forms simple and professional', Config::TEXTDOMAIN); ?></li>
                    </ul>
                </div>
            </div>

            <!-- reCAPTCHA Help -->
            <div id="help-recaptcha" class="cin-help-section">
                <h3><?php _e('🔒 reCAPTCHA Configuration', Config::TEXTDOMAIN); ?></h3>
                
                <div class="cin-help-item">
                    <h4><?php _e('What is reCAPTCHA v3?', Config::TEXTDOMAIN); ?></h4>
                    <p><?php _e('reCAPTCHA v3 protects your forms from spam and automated abuse by detecting bot behavior in the background—without annoying users with puzzles or checkboxes.', Config::TEXTDOMAIN); ?></p>
                </div>

                <div class="cin-help-item">
                    <h4><?php _e('How to Get API Keys', Config::TEXTDOMAIN); ?></h4>
                    <ol>
                        <li><?php _e('Visit: https://www.google.com/recaptcha/admin', Config::TEXTDOMAIN); ?></li>
                        <li><?php _e('Click "+" to create a new site', Config::TEXTDOMAIN); ?></li>
                        <li><?php _e('Choose reCAPTCHA v3', Config::TEXTDOMAIN); ?></li>
                        <li><?php _e('Add your domain', Config::TEXTDOMAIN); ?></li>
                        <li><?php _e('Copy Site Key and Secret Key', Config::TEXTDOMAIN); ?></li>
                    </ol>
                </div>

                <div class="cin-help-item">
                    <h4><?php _e('Site Key vs Secret Key', Config::TEXTDOMAIN); ?></h4>
                    <ul>
                        <li><strong><?php _e('Site Key:', Config::TEXTDOMAIN); ?></strong> <?php _e('Public key used in frontend code. Safe to expose.', Config::TEXTDOMAIN); ?></li>
                        <li><strong><?php _e('Secret Key:', Config::TEXTDOMAIN); ?></strong> <?php _e('Private key for backend verification. NEVER share publicly.', Config::TEXTDOMAIN); ?></li>
                    </ul>
                </div>

                <div class="cin-help-item">
                    <h4><?php _e('Enable reCAPTCHA', Config::TEXTDOMAIN); ?></h4>
                    <p><?php _e('Check this box to activate reCAPTCHA protection on all contact forms.', Config::TEXTDOMAIN); ?></p>
                    <ul>
                        <li><?php _e('Recommended: Always keep this enabled for security', Config::TEXTDOMAIN); ?></li>
                    </ul>
                </div>
            </div>

            <!-- SMTP Help -->
            <div id="help-smtp" class="cin-help-section">
                <h3><?php _e('📧 SMTP Configuration', Config::TEXTDOMAIN); ?></h3>
                
                <div class="cin-help-item">
                    <h4><?php _e('Why SMTP?', Config::TEXTDOMAIN); ?></h4>
                    <p><?php _e('SMTP (Simple Mail Transfer Protocol) ensures your emails are reliably delivered with proper authentication. Many hosting providers have mail delivery issues—SMTP solves this.', Config::TEXTDOMAIN); ?></p>
                </div>

                <div class="cin-help-item">
                    <h4><?php _e('Enable SMTP', Config::TEXTDOMAIN); ?></h4>
                    <p><?php _e('Check to use SMTP for sending emails instead of WordPress default mail function.', Config::TEXTDOMAIN); ?></p>
                </div>

                <div class="cin-help-item">
                    <h4><?php _e('Common SMTP Providers', Config::TEXTDOMAIN); ?></h4>
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
                    <h4><?php _e('SMTP Settings Explained', Config::TEXTDOMAIN); ?></h4>
                    <ul>
                        <li><strong><?php _e('Host:', Config::TEXTDOMAIN); ?></strong> <?php _e('SMTP server address (provided by your email service)', Config::TEXTDOMAIN); ?></li>
                        <li><strong><?php _e('Port:', Config::TEXTDOMAIN); ?></strong> <?php _e('Usually 587 (TLS) or 465 (SSL)', Config::TEXTDOMAIN); ?></li>
                        <li><strong><?php _e('Encryption:', Config::TEXTDOMAIN); ?></strong> <?php _e('None (not recommended), SSL, or TLS', Config::TEXTDOMAIN); ?></li>
                        <li><strong><?php _e('Username:', Config::TEXTDOMAIN); ?></strong> <?php _e('Your email address or account username', Config::TEXTDOMAIN); ?></li>
                        <li><strong><?php _e('Password:', Config::TEXTDOMAIN); ?></strong> <?php _e('Email account password or app-specific password', Config::TEXTDOMAIN); ?></li>
                    </ul>
                </div>

                <div class="cin-help-item">
                    <h4><?php _e('Test SMTP Connection', Config::TEXTDOMAIN); ?></h4>
                    <p><?php _e('Click "Test SMTP" to verify your settings before saving. This sends a test email to the admin email address.', Config::TEXTDOMAIN); ?></p>
                    <ul>
                        <li><?php _e('✅ Success message: Your SMTP is working correctly', Config::TEXTDOMAIN); ?></li>
                        <li><?php _e('❌ Error: Check your host, port, and credentials', Config::TEXTDOMAIN); ?></li>
                    </ul>
                </div>
            </div>

            <!-- Notifications Help -->
            <div id="help-notifications" class="cin-help-section">
                <h3><?php _e('🔔 Email Notifications', Config::TEXTDOMAIN); ?></h3>
                
                <div class="cin-help-item">
                    <h4><?php _e('Send Form Submission to Admin', Config::TEXTDOMAIN); ?></h4>
                    <p><?php _e('When checked, the admin receives an email notification every time someone submits the contact form.', Config::TEXTDOMAIN); ?></p>
                    <ul>
                        <li><?php _e('Recommended: Keep enabled to stay informed about new inquiries', Config::TEXTDOMAIN); ?></li>
                    </ul>
                </div>

                <div class="cin-help-item">
                    <h4><?php _e('Admin Email(s)', Config::TEXTDOMAIN); ?></h4>
                    <p><?php _e('Email address(es) where admin notifications are sent.', Config::TEXTDOMAIN); ?></p>
                    <ul>
                        <li><?php _e('Single email: contact@example.com', Config::TEXTDOMAIN); ?></li>
                        <li><?php _e('Multiple emails: contact@example.com, support@example.com, info@example.com', Config::TEXTDOMAIN); ?></li>
                        <li><?php _e('Separate with commas and spaces', Config::TEXTDOMAIN); ?></li>
                        <li><?php _e('Max 254 characters per email address', Config::TEXTDOMAIN); ?></li>
                    </ul>
                </div>

                <div class="cin-help-item">
                    <h4><?php _e('Send Copy to User', Config::TEXTDOMAIN); ?></h4>
                    <p><?php _e('When checked, users receive a confirmation email after submitting the form.', Config::TEXTDOMAIN); ?></p>
                    <ul>
                        <li><?php _e('Recommended: Keep enabled for better user experience', Config::TEXTDOMAIN); ?></li>
                        <li><?php _e('Users feel acknowledged and know their message was received', Config::TEXTDOMAIN); ?></li>
                    </ul>
                </div>
            </div>

            <!-- Form Help -->
            <div id="help-form" class="cin-help-section">
                <h3><?php _e('📝 Form Customisation', Config::TEXTDOMAIN); ?></h3>
                
                <div class="cin-help-item">
                    <h4><?php _e('Enable Subject Field', Config::TEXTDOMAIN); ?></h4>
                    <p><?php _e('Check to let users enter a subject for their inquiry.', Config::TEXTDOMAIN); ?></p>
                    <ul>
                        <li><?php _e('Recommended: Enable for better organization in inbox', Config::TEXTDOMAIN); ?></li>
                    </ul>
                </div>

                <div class="cin-help-item">
                    <h4><?php _e('Enable File Attachment', Config::TEXTDOMAIN); ?></h4>
                    <p><?php _e('Check to allow users to upload files (documents, images, etc.) with their inquiry.', Config::TEXTDOMAIN); ?></p>
                    <ul>
                        <li><?php _e('Configure allowed file types below', Config::TEXTDOMAIN); ?></li>
                        <li><?php _e('Set max file size to prevent abuse', Config::TEXTDOMAIN); ?></li>
                    </ul>
                </div>

                <div class="cin-help-item">
                    <h4><?php _e('Field Length Limits', Config::TEXTDOMAIN); ?></h4>
                    <p><?php _e('Control the minimum and maximum length of form fields to ensure quality submissions and prevent spam.', Config::TEXTDOMAIN); ?></p>
                    <ul>
                        <li><strong><?php _e('Max Chars:', Config::TEXTDOMAIN); ?></strong> <?php _e('Maximum characters allowed (prevents extremely long inputs)', Config::TEXTDOMAIN); ?></li>
                        <li><strong><?php _e('Min Words:', Config::TEXTDOMAIN); ?></strong> <?php _e('Minimum words required (prevents spam with single words)', Config::TEXTDOMAIN); ?></li>
                        <li><?php _e('Example: Min 2 words, Max 50 chars for name field', Config::TEXTDOMAIN); ?></li>
                    </ul>
                </div>

                <div class="cin-help-item">
                    <h4><?php _e('Allowed File Types', Config::TEXTDOMAIN); ?></h4>
                    <p><?php _e('Select which file types users can upload. Defaults include common safe formats.', Config::TEXTDOMAIN); ?></p>
                    <ul>
                        <li><?php _e('Hold Ctrl (Windows/Linux) or Command (Mac) to select multiple types', Config::TEXTDOMAIN); ?></li>
                        <li><?php _e('Popular types listed first', Config::TEXTDOMAIN); ?></li>
                        <li><?php _e('Recommendation: Only allow file types you actually need', Config::TEXTDOMAIN); ?></li>
                    </ul>
                </div>

                <div class="cin-help-item">
                    <h4><?php _e('Max File Size (MB)', Config::TEXTDOMAIN); ?></h4>
                    <p><?php _e('Prevents users from uploading extremely large files.', Config::TEXTDOMAIN); ?></p>
                    <ul>
                        <li><?php _e('Recommendation: 5-10 MB for most sites', Config::TEXTDOMAIN); ?></li>
                        <li><?php _e('Check your hosting provider\'s limits', Config::TEXTDOMAIN); ?></li>
                    </ul>
                </div>

                <div class="cin-help-item">
                    <h4><?php _e('Max Files per Submission', Config::TEXTDOMAIN); ?></h4>
                    <p><?php _e('Limits the number of files users can upload at once.', Config::TEXTDOMAIN); ?></p>
                    <ul>
                        <li><?php _e('Recommendation: 1-3 files per submission', Config::TEXTDOMAIN); ?></li>
                    </ul>
                </div>
            </div>

            <!-- Advanced Help -->
            <div id="help-advanced" class="cin-help-section">
                <h3><?php _e('⚙️ Advanced Settings', Config::TEXTDOMAIN); ?></h3>

                <div class="cin-help-item">
                    <h4><?php _e('REST API Service', Config::TEXTDOMAIN); ?></h4>
                    <p><?php _e('Enable this if you need programmatic access to submissions or want to push data into the hub from custom integrations. Disable it on sites that do not expose API credentials to reduce the attack surface.', Config::TEXTDOMAIN); ?></p>
                    <ul>
                        <li><?php _e('Requires authentication before any data is accepted.', Config::TEXTDOMAIN); ?></li>
                        <li><?php _e('Turn it off on sites that only use the built-in form.', Config::TEXTDOMAIN); ?></li>
                    </ul>
                </div>

                <div class="cin-help-item">
                    <h4><?php _e('Webhooks Service', Config::TEXTDOMAIN); ?></h4>
                    <p><?php _e('Allows external systems to push events or updates back into Contact Inbox without using the REST API. Useful for marketing automation, CRMs, or middleware platforms.', Config::TEXTDOMAIN); ?></p>
                    <ul>
                        <li><?php _e('Only enable if you trust the systems calling your webhook endpoint.', Config::TEXTDOMAIN); ?></li>
                        <li><?php _e('Rotate secrets regularly and monitor the Webhook Log for errors.', Config::TEXTDOMAIN); ?></li>
                    </ul>
                </div>

                <div class="cin-help-item">
                    <h4><?php _e('Log Retention & Cleanup', Config::TEXTDOMAIN); ?></h4>
                    <p><?php _e('Email, REST, and CRM logs can grow quickly. Use the retention fields to define how many days of history the system should keep before auto-purging older rows.', Config::TEXTDOMAIN); ?></p>
                    <ul>
                        <li><?php _e('Choose longer windows while debugging; shorten them after go-live to save disk space.', Config::TEXTDOMAIN); ?></li>
                        <li><?php _e('Retention relies on cron, so ensure background jobs are running.', Config::TEXTDOMAIN); ?></li>
                    </ul>
                </div>

                <div class="cin-help-item">
                    <h4><?php _e('Background Job Scheduling', Config::TEXTDOMAIN); ?></h4>
                    <p><?php _e('The Queue Processor keeps CRM, email, and webhook deliveries moving. Adjust the interval if you need faster retries or want to reduce load on small servers.', Config::TEXTDOMAIN); ?></p>
                    <ul>
                        <li><?php _e('"Run Now" triggers the job immediately—ideal after changing SMTP or CRM credentials.', Config::TEXTDOMAIN); ?></li>
                        <li><?php _e('Track real-time status in Dashboard → Background Jobs.', Config::TEXTDOMAIN); ?></li>
                        <li><?php _e('Use shorter intervals (1–2 minutes) for busy sites; hourly for low-volume staging installs.', Config::TEXTDOMAIN); ?></li>
                    </ul>
                </div>

                <div class="cin-help-item">
                    <h4><?php _e('Advanced Admin Tips', Config::TEXTDOMAIN); ?></h4>
                    <ul>
                        <li><?php _e('💾 Always click "Save Settings" before switching tabs or leaving the page.', Config::TEXTDOMAIN); ?></li>
                        <li><?php _e('🧪 Use "Test SMTP" whenever mail credentials change—do it before going live.', Config::TEXTDOMAIN); ?></li>
                        <li><?php _e('📋 Inbox shows every submission; use CRM & REST logs for delivery diagnostics.', Config::TEXTDOMAIN); ?></li>
                        <li><?php _e('📊 Analytics Dashboard highlights queue health, spam rejection trends, and cron alerts.', Config::TEXTDOMAIN); ?></li>
                        <li><?php _e('🔐 Pair reCAPTCHA with field length limits to cut automated spam.', Config::TEXTDOMAIN); ?></li>
                        <li><?php _e('🧹 Visit Maintenance → Attachment Cleanup to purge orphaned uploads regularly.', Config::TEXTDOMAIN); ?></li>
                    </ul>
                </div>
            </div>

            <!-- Common Issues -->
            <div class="cin-help-issues">
                <h3>❓ <?php _e('Common Issues & Solutions', Config::TEXTDOMAIN); ?></h3>
                
                <div class="cin-help-pro-tip-item">
                    <h4><?php _e('Emails not being sent?', Config::TEXTDOMAIN); ?></h4>
                    <p><?php _e('Enable SMTP and test the connection. Check that emails are not marked as spam.', Config::TEXTDOMAIN); ?></p>
                </div>

                <div class="cin-help-pro-tip-item">
                    <h4><?php _e('Getting too much spam?', Config::TEXTDOMAIN); ?></h4>
                    <p><?php _e('Increase field length requirements and enable reCAPTCHA v3 protection.', Config::TEXTDOMAIN); ?></p>
                </div>

                <div class="cin-help-pro-tip-item">
                    <h4><?php _e('Form not showing on page?', Config::TEXTDOMAIN); ?></h4>
                    <p><?php _e('Use the shortcode [contact-inbox] in the page editor to display the contact form.', Config::TEXTDOMAIN); ?></p>
                </div>

                <div class="cin-help-pro-tip-item">
                    <h4><?php _e('Users report file upload fails?', Config::TEXTDOMAIN); ?></h4>
                    <p><?php _e('Check max file size setting, server upload limits, and allowed file types.', Config::TEXTDOMAIN); ?></p>
                </div>
            </div>
        </div>

        <div class="cin-modal-footer">
            <p><?php _e('For more help, visit our documentation or contact support.', Config::TEXTDOMAIN); ?></p>
        </div>
    </div>
</div>
