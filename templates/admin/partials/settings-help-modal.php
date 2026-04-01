<?php
/**
 * Template: Settings Help Modal
 * File: templates/admin/partials/settings-help-modal.php
 * Description: Comprehensive help guide for admin settings
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
use ContactInbox\Core\Config;

// phpcs:disable WordPress.Security.EscapeOutput.UnsafePrintingFunction, WordPress.WP.I18n.NonSingularStringLiteralDomain
?>

<div id="cin-help-modal" class="cin-modal cin-modal-hidden" data-cin-help-modal="true">
	<div class="cin-modal-overlay"></div>
	<div class="cin-modal-content">
		<div class="cin-modal-header">
			<h2><?php _e( 'ContactIn - Settings Guide', 'contactin' ); ?></h2>
			<button type="button" class="cin-modal-close" aria-label="<?php _e( 'Close', 'contactin' ); ?>">
				<span aria-hidden="true">&times;</span>
			</button>
		</div>

		<div class="cin-modal-body">
			<!-- Quick Navigation -->
			<div class="cin-help-nav">
				<h3><?php _e( 'Quick Navigation', 'contactin' ); ?></h3>
				<ul>
					<li><a href="#help-general" class="cin-help-link"><?php _e( 'General Settings', 'contactin' ); ?></a></li>
					<li><a href="#help-recaptcha" class="cin-help-link">reCAPTCHA</a></li>
					<li><a href="#help-smtp" class="cin-help-link">SMTP</a></li>
					<li><a href="#help-notifications" class="cin-help-link"><?php _e( 'Notifications', 'contactin' ); ?></a></li>
					<li><a href="#help-form" class="cin-help-link"><?php _e( 'Form', 'contactin' ); ?></a></li>
					<li><a href="#help-advanced" class="cin-help-link"><?php _e( 'Advanced', 'contactin' ); ?></a></li>
				</ul>
			</div>

			<!-- General Settings Help -->
			<div id="help-general" class="cin-help-section">
				<h3><?php _e( '📋 General Settings', 'contactin' ); ?></h3>
				
				<div class="cin-help-item">
					<h4><?php _e( 'Privacy Policy URL', 'contactin' ); ?></h4>
					<p><?php _e( 'Link to your website\'s privacy policy. This is required for GDPR compliance and data protection.', 'contactin' ); ?></p>
					<ul>
						<li><?php _e( 'Use your site\'s privacy policy page URL', 'contactin' ); ?></li>
						<li><?php _e( 'Example: https://yoursite.com/privacy-policy', 'contactin' ); ?></li>
						<li><?php _e( 'Leave blank to use WordPress default privacy page', 'contactin' ); ?></li>
					</ul>
				</div>

				<div class="cin-help-item">
					<h4><?php _e( 'Consent Text', 'contactin' ); ?></h4>
					<p><?php _e( 'Message displayed to users regarding data processing. Inform them how their data will be used.', 'contactin' ); ?></p>
					<ul>
						<li><?php _e( 'Example: "I consent to having my name and email stored for contact purposes."', 'contactin' ); ?></li>
						<li><?php _e( 'Make it clear and simple', 'contactin' ); ?></li>
						<li><?php _e( 'Required for GDPR compliance', 'contactin' ); ?></li>
					</ul>
				</div>

				<div class="cin-help-item">
					<h4><?php _e( 'Success Message', 'contactin' ); ?></h4>
					<p><?php _e( 'Message shown to users after they successfully submit a form.', 'contactin' ); ?></p>
					<ul>
						<li><?php _e( 'Be friendly and encouraging', 'contactin' ); ?></li>
						<li><?php _e( 'Example: "Thank you! We\'ll get back to you shortly."', 'contactin' ); ?></li>
					</ul>
				</div>

				<div class="cin-help-item">
					<h4><?php _e( 'Confetti on Success', 'contactin' ); ?></h4>
					<p><?php _e( 'Shows a fun confetti animation when users submit the form successfully. Great for user engagement!', 'contactin' ); ?></p>
					<ul>
						<li><?php _e( 'Check to enable confetti animation', 'contactin' ); ?></li>
						<li><?php _e( 'Uncheck to keep forms simple and professional', 'contactin' ); ?></li>
					</ul>
				</div>
			</div>

			<!-- reCAPTCHA Help -->
			<div id="help-recaptcha" class="cin-help-section">
				<h3><?php _e( '🔒 reCAPTCHA Configuration', 'contactin' ); ?></h3>
				
				<div class="cin-help-item">
					<h4><?php _e( 'What is reCAPTCHA v3?', 'contactin' ); ?></h4>
					<p><?php _e( 'reCAPTCHA v3 protects your forms from spam and automated abuse by detecting bot behavior in the background—without annoying users with puzzles or checkboxes.', 'contactin' ); ?></p>
				</div>

				<div class="cin-help-item">
					<h4><?php _e( 'How to Get API Keys', 'contactin' ); ?></h4>
					<ol>
						<li><?php _e( 'Visit: https://www.google.com/recaptcha/admin', 'contactin' ); ?></li>
						<li><?php _e( 'Click "+" to create a new site', 'contactin' ); ?></li>
						<li><?php _e( 'Choose reCAPTCHA v3', 'contactin' ); ?></li>
						<li><?php _e( 'Add your domain', 'contactin' ); ?></li>
						<li><?php _e( 'Copy Site Key and Secret Key', 'contactin' ); ?></li>
					</ol>
				</div>

				<div class="cin-help-item">
					<h4><?php _e( 'Site Key vs Secret Key', 'contactin' ); ?></h4>
					<ul>
						<li><strong><?php _e( 'Site Key:', 'contactin' ); ?></strong> <?php _e( 'Public key used in frontend code. Safe to expose.', 'contactin' ); ?></li>
						<li><strong><?php _e( 'Secret Key:', 'contactin' ); ?></strong> <?php _e( 'Private key for backend verification. NEVER share publicly.', 'contactin' ); ?></li>
					</ul>
				</div>

				<div class="cin-help-item">
					<h4><?php _e( 'Enable reCAPTCHA', 'contactin' ); ?></h4>
					<p><?php _e( 'Check this box to activate reCAPTCHA protection on all contact forms.', 'contactin' ); ?></p>
					<ul>
						<li><?php _e( 'Recommended: Always keep this enabled for security', 'contactin' ); ?></li>
					</ul>
				</div>
			</div>

			<!-- SMTP Help -->
			<div id="help-smtp" class="cin-help-section">
				<h3><?php _e( '📧 SMTP Configuration', 'contactin' ); ?></h3>
				
				<div class="cin-help-item">
					<h4><?php _e( 'Why SMTP?', 'contactin' ); ?></h4>
					<p><?php _e( 'SMTP (Simple Mail Transfer Protocol) ensures your emails are reliably delivered with proper authentication. Many hosting providers have mail delivery issues—SMTP solves this.', 'contactin' ); ?></p>
				</div>

				<div class="cin-help-item">
					<h4><?php _e( 'Enable SMTP', 'contactin' ); ?></h4>
					<p><?php _e( 'Check to use SMTP for sending emails instead of WordPress default mail function.', 'contactin' ); ?></p>
				</div>

				<div class="cin-help-item">
					<h4><?php _e( 'Common SMTP Providers', 'contactin' ); ?></h4>
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
					<h4><?php _e( 'SMTP Settings Explained', 'contactin' ); ?></h4>
					<ul>
						<li><strong><?php _e( 'Host:', 'contactin' ); ?></strong> <?php _e( 'SMTP server address (provided by your email service)', 'contactin' ); ?></li>
						<li><strong><?php _e( 'Port:', 'contactin' ); ?></strong> <?php _e( 'Usually 587 (TLS) or 465 (SSL)', 'contactin' ); ?></li>
						<li><strong><?php _e( 'Encryption:', 'contactin' ); ?></strong> <?php _e( 'None (not recommended), SSL, or TLS', 'contactin' ); ?></li>
						<li><strong><?php _e( 'Username:', 'contactin' ); ?></strong> <?php _e( 'Your email address or account username', 'contactin' ); ?></li>
						<li><strong><?php _e( 'Password:', 'contactin' ); ?></strong> <?php _e( 'Email account password or app-specific password', 'contactin' ); ?></li>
					</ul>
				</div>

				<div class="cin-help-item">
					<h4><?php _e( 'Test SMTP Connection', 'contactin' ); ?></h4>
					<p><?php _e( 'Click "Test SMTP" to verify your settings before saving. This sends a test email to the admin email address.', 'contactin' ); ?></p>
					<ul>
						<li><?php _e( '✅ Success message: Your SMTP is working correctly', 'contactin' ); ?></li>
						<li><?php _e( '❌ Error: Check your host, port, and credentials', 'contactin' ); ?></li>
					</ul>
				</div>
			</div>

			<!-- Notifications Help -->
			<div id="help-notifications" class="cin-help-section">
				<h3><?php _e( '🔔 Email Notifications', 'contactin' ); ?></h3>
				
				<div class="cin-help-item">
					<h4><?php _e( 'Send Form Submission to Admin', 'contactin' ); ?></h4>
					<p><?php _e( 'When checked, the admin receives an email notification every time someone submits the contact form.', 'contactin' ); ?></p>
					<ul>
						<li><?php _e( 'Recommended: Keep enabled to stay informed about new inquiries', 'contactin' ); ?></li>
					</ul>
				</div>

				<div class="cin-help-item">
					<h4><?php _e( 'Admin Email(s)', 'contactin' ); ?></h4>
					<p><?php _e( 'Email address(es) where admin notifications are sent.', 'contactin' ); ?></p>
					<ul>
						<li><?php _e( 'Single email: contact@example.com', 'contactin' ); ?></li>
						<li><?php _e( 'Multiple emails: contact@example.com, support@example.com, info@example.com', 'contactin' ); ?></li>
						<li><?php _e( 'Separate with commas and spaces', 'contactin' ); ?></li>
						<li><?php _e( 'Max 254 characters per email address', 'contactin' ); ?></li>
					</ul>
				</div>

				<div class="cin-help-item">
					<h4><?php _e( 'Send Copy to User', 'contactin' ); ?></h4>
					<p><?php _e( 'When checked, users receive a confirmation email after submitting the form.', 'contactin' ); ?></p>
					<ul>
						<li><?php _e( 'Recommended: Keep enabled for better user experience', 'contactin' ); ?></li>
						<li><?php _e( 'Users feel acknowledged and know their message was received', 'contactin' ); ?></li>
					</ul>
				</div>
			</div>

			<!-- Form Help -->
			<div id="help-form" class="cin-help-section">
				<h3><?php _e( '📝 Form Customisation', 'contactin' ); ?></h3>
				
				<div class="cin-help-item">
					<h4><?php _e( 'Enable Subject Field', 'contactin' ); ?></h4>
					<p><?php _e( 'Check to let users enter a subject for their inquiry.', 'contactin' ); ?></p>
					<ul>
						<li><?php _e( 'Recommended: Enable for better organization in inbox', 'contactin' ); ?></li>
					</ul>
				</div>

				<div class="cin-help-item">
					<h4><?php _e( 'Enable File Attachment', 'contactin' ); ?></h4>
					<p><?php _e( 'Check to allow users to upload files (documents, images, etc.) with their inquiry.', 'contactin' ); ?></p>
					<ul>
						<li><?php _e( 'Configure allowed file types below', 'contactin' ); ?></li>
						<li><?php _e( 'Set max file size to prevent abuse', 'contactin' ); ?></li>
					</ul>
				</div>

				<div class="cin-help-item">
					<h4><?php _e( 'Field Length Limits', 'contactin' ); ?></h4>
					<p><?php _e( 'Control the minimum and maximum length of form fields to ensure quality submissions and prevent spam.', 'contactin' ); ?></p>
					<ul>
						<li><strong><?php _e( 'Max Chars:', 'contactin' ); ?></strong> <?php _e( 'Maximum characters allowed (prevents extremely long inputs)', 'contactin' ); ?></li>
						<li><strong><?php _e( 'Min Words:', 'contactin' ); ?></strong> <?php _e( 'Minimum words required (prevents spam with single words)', 'contactin' ); ?></li>
						<li><?php _e( 'Example: Min 2 words, Max 50 chars for name field', 'contactin' ); ?></li>
					</ul>
				</div>

				<div class="cin-help-item">
					<h4><?php _e( 'Allowed File Types', 'contactin' ); ?></h4>
					<p><?php _e( 'Select which file types users can upload. Defaults include common safe formats.', 'contactin' ); ?></p>
					<ul>
						<li><?php _e( 'Hold Ctrl (Windows/Linux) or Command (Mac) to select multiple types', 'contactin' ); ?></li>
						<li><?php _e( 'Popular types listed first', 'contactin' ); ?></li>
						<li><?php _e( 'Recommendation: Only allow file types you actually need', 'contactin' ); ?></li>
					</ul>
				</div>

				<div class="cin-help-item">
					<h4><?php _e( 'Max File Size (MB)', 'contactin' ); ?></h4>
					<p><?php _e( 'Prevents users from uploading extremely large files.', 'contactin' ); ?></p>
					<ul>
						<li><?php _e( 'Recommendation: 5-10 MB for most sites', 'contactin' ); ?></li>
						<li><?php _e( 'Check your hosting provider\'s limits', 'contactin' ); ?></li>
					</ul>
				</div>

				<div class="cin-help-item">
					<h4><?php _e( 'Max Files per Submission', 'contactin' ); ?></h4>
					<p><?php _e( 'Limits the number of files users can upload at once.', 'contactin' ); ?></p>
					<ul>
						<li><?php _e( 'Recommendation: 1-3 files per submission', 'contactin' ); ?></li>
					</ul>
				</div>
			</div>

			<!-- Advanced Help -->
			<div id="help-advanced" class="cin-help-section">
				<h3><?php _e( '⚙️ Advanced Settings', 'contactin' ); ?></h3>

				<div class="cin-help-item">
					<h4><?php _e( 'REST API Service', 'contactin' ); ?></h4>
					<p><?php _e( 'Enable this if you need programmatic access to submissions or want to push data into the hub from custom integrations. Disable it on sites that do not expose API credentials to reduce the attack surface.', 'contactin' ); ?></p>
					<ul>
						<li><?php _e( 'Requires authentication before any data is accepted.', 'contactin' ); ?></li>
						<li><?php _e( 'Turn it off on sites that only use the built-in form.', 'contactin' ); ?></li>
					</ul>
				</div>

				<div class="cin-help-item">
					<h4><?php _e( 'Webhooks Service', 'contactin' ); ?></h4>
					<p><?php _e( 'Allows external systems to push events or updates back into ContactIn without using the REST API. Useful for marketing automation, CRMs, or middleware platforms.', 'contactin' ); ?></p>
					<ul>
						<li><?php _e( 'Only enable if you trust the systems calling your webhook endpoint.', 'contactin' ); ?></li>
						<li><?php _e( 'Rotate secrets regularly and monitor the Webhook Log for errors.', 'contactin' ); ?></li>
					</ul>
				</div>

				<div class="cin-help-item">
					<h4><?php _e( 'Log Retention & Cleanup', 'contactin' ); ?></h4>
					<p><?php _e( 'Email, REST, and CRM logs can grow quickly. Use the retention fields to define how many days of history the system should keep before auto-purging older rows.', 'contactin' ); ?></p>
					<ul>
						<li><?php _e( 'Choose longer windows while debugging; shorten them after go-live to save disk space.', 'contactin' ); ?></li>
						<li><?php _e( 'Retention relies on cron, so ensure background jobs are running.', 'contactin' ); ?></li>
					</ul>
				</div>

				<div class="cin-help-item">
					<h4><?php _e( 'Background Job Scheduling', 'contactin' ); ?></h4>
					<p><?php _e( 'The Queue Processor keeps CRM, email, and webhook deliveries moving. Adjust the interval if you need faster retries or want to reduce load on small servers.', 'contactin' ); ?></p>
					<ul>
						<li><?php _e( '"Run Now" triggers the job immediately—ideal after changing SMTP or CRM credentials.', 'contactin' ); ?></li>
						<li><?php _e( 'Track real-time status in Dashboard → Background Jobs.', 'contactin' ); ?></li>
						<li><?php _e( 'Use shorter intervals (1–2 minutes) for busy sites; hourly for low-volume staging installs.', 'contactin' ); ?></li>
					</ul>
				</div>

				<div class="cin-help-item">
					<h4><?php _e( 'Advanced Admin Tips', 'contactin' ); ?></h4>
					<ul>
						<li><?php _e( '💾 Always click "Save Settings" before switching tabs or leaving the page.', 'contactin' ); ?></li>
						<li><?php _e( '🧪 Use "Test SMTP" whenever mail credentials change—do it before going live.', 'contactin' ); ?></li>
						<li><?php _e( '📋 Inbox shows every submission; use CRM & REST logs for delivery diagnostics.', 'contactin' ); ?></li>
						<li><?php _e( '📊 Analytics Dashboard highlights queue health, spam rejection trends, and cron alerts.', 'contactin' ); ?></li>
						<li><?php _e( '🔐 Pair reCAPTCHA with field length limits to cut automated spam.', 'contactin' ); ?></li>
						<li><?php _e( '🧹 Visit Maintenance → Attachment Cleanup to purge orphaned uploads regularly.', 'contactin' ); ?></li>
					</ul>
				</div>
			</div>

			<!-- Common Issues -->
			<div class="cin-help-issues">
				<h3>❓ <?php _e( 'Common Issues & Solutions', 'contactin' ); ?></h3>
				
				<div class="cin-help-pro-tip-item">
					<h4><?php _e( 'Emails not being sent?', 'contactin' ); ?></h4>
					<p><?php _e( 'Enable SMTP and test the connection. Check that emails are not marked as spam.', 'contactin' ); ?></p>
				</div>

				<div class="cin-help-pro-tip-item">
					<h4><?php _e( 'Getting too much spam?', 'contactin' ); ?></h4>
					<p><?php _e( 'Increase field length requirements and enable reCAPTCHA v3 protection.', 'contactin' ); ?></p>
				</div>

				<div class="cin-help-pro-tip-item">
					<h4><?php _e( 'Form not showing on page?', 'contactin' ); ?></h4>
					<p><?php _e( 'Use the shortcode [contactin] in the page editor to display the contact form.', 'contactin' ); ?></p>
				</div>

				<div class="cin-help-pro-tip-item">
					<h4><?php _e( 'Users report file upload fails?', 'contactin' ); ?></h4>
					<p><?php _e( 'Check max file size setting, server upload limits, and allowed file types.', 'contactin' ); ?></p>
				</div>
			</div>
		</div>

		<div class="cin-modal-footer">
			<p><?php _e( 'For more help, visit our documentation or contact support.', 'contactin' ); ?></p>
		</div>
	</div>
</div>
