<?php
/**
 * Email Template: SMTP Test Email
 * File: templates/emails/smtp-test.php
 *
 * This email is sent when admin clicks "Test SMTP" to verify
 * SMTP configuration and email deliverability.
 *
 * Variables (none needed for test email)
 */

if (!defined('ABSPATH')) exit;
use ContactInbox\Core\Config;

?>

<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php _e('SMTP Configuration Test', Config::TEXTDOMAIN); ?></title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            margin: 0;
            padding: 0;
            color: #333333;
        }
        .container {
            max-width: 600px;
            margin: 40px auto;
            background: #ffffff;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.15);
        }
        .header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: #ffffff;
            padding: 40px 25px;
            text-align: center;
        }
        .header h1 {
            margin: 0;
            font-size: 28px;
            font-weight: 700;
            letter-spacing: -0.5px;
        }
        .header p {
            margin: 10px 0 0;
            font-size: 14px;
            opacity: 0.95;
        }
        .content {
            padding: 35px 30px;
            line-height: 1.8;
        }
        .success-badge {
            display: inline-block;
            background: #28a745;
            color: #ffffff;
            padding: 8px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            margin-bottom: 20px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .content h2 {
            color: #667eea;
            font-size: 20px;
            margin: 25px 0 15px;
            padding-bottom: 10px;
            border-bottom: 2px solid #f0f0f0;
        }
        .content p {
            margin: 0 0 15px;
            font-size: 15px;
            color: #555555;
        }
        .info-box {
            background: #f8f9ff;
            border-left: 4px solid #667eea;
            padding: 15px 20px;
            margin: 20px 0;
            border-radius: 0 6px 6px 0;
        }
        .info-box strong {
            color: #667eea;
        }
        .checkmark {
            color: #28a745;
            font-weight: bold;
            font-size: 18px;
            margin-right: 8px;
        }
        .test-details {
            background: #f5f5f5;
            padding: 15px;
            border-radius: 6px;
            font-size: 13px;
            color: #666666;
            margin: 20px 0;
        }
        .test-details p {
            margin: 8px 0;
            font-family: 'Monaco', 'Menlo', 'Ubuntu Mono', monospace;
        }
        .next-steps {
            background: #fff3cd;
            border-left: 4px solid #ffc107;
            padding: 15px 20px;
            margin: 20px 0;
            border-radius: 0 6px 6px 0;
        }
        .next-steps strong {
            color: #856404;
        }
        .next-steps ol {
            margin: 10px 0;
            padding-left: 20px;
        }
        .next-steps li {
            margin: 8px 0;
            color: #856404;
        }
        .footer {
            background: #f8f9fa;
            padding: 25px;
            text-align: center;
            border-top: 1px solid #e0e0e0;
            font-size: 12px;
            color: #888888;
        }
        .footer p {
            margin: 8px 0;
        }
        .footer a {
            color: #667eea;
            text-decoration: none;
        }
        .footer a:hover {
            text-decoration: underline;
        }
        @media (max-width: 600px) {
            .container {
                margin: 15px;
                border-radius: 8px;
            }
            .header {
                padding: 30px 20px;
            }
            .header h1 {
                font-size: 24px;
            }
            .content {
                padding: 25px 20px;
            }
            .content h2 {
                font-size: 18px;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- Header -->
        <div class="header">
            <h1><?php _e('✓ SMTP Test Successful', 'contact-inbox-hub'); ?></h1>
            <p><?php _e('Your email configuration is working correctly', 'contact-inbox-hub'); ?></p>
        </div>

        <!-- Content -->
        <div class="content">
            <p><?php _e('Hello,', 'contact-inbox-hub'); ?></p>

            <p><?php _e('This is a test email from <strong>Contact Inbox</strong> plugin.', 'contact-inbox-hub'); ?></p>

            <div class="success-badge">
                <?php _e('Configuration Valid', 'contact-inbox-hub'); ?>
            </div>

            <h2><?php _e('What This Means', 'contact-inbox-hub'); ?></h2>
            <p>
                <span class="checkmark">✓</span> <?php _e('SMTP server is reachable and responding', 'contact-inbox-hub'); ?><br>
                <span class="checkmark">✓</span> <?php _e('Authentication credentials are correct', 'contact-inbox-hub'); ?><br>
                <span class="checkmark">✓</span> <?php _e('Email delivery is configured properly', 'contact-inbox-hub'); ?><br>
                <span class="checkmark">✓</span> <?php _e('Your contact form notifications will be sent', 'contact-inbox-hub'); ?>
            </p>

            <div class="test-details">
                <p><strong><?php _e('Test Timestamp:', 'contact-inbox-hub'); ?></strong> <?php echo date_i18n('Y-m-d H:i:s'); ?> (<?php echo wp_date('T'); ?>)</p>
                <p><strong><?php _e('Site URL:', 'contact-inbox-hub'); ?></strong> <?php echo esc_html(home_url()); ?></p>
                <p><strong><?php _e('Plugin:', 'contact-inbox-hub'); ?></strong> Contact Inbox v<?php echo esc_html(Config::VERSION); ?></p>
            </div>

            <h2><?php _e('Next Steps', 'contact-inbox-hub'); ?></h2>
            <div class="next-steps">
                <strong><?php _e('Your SMTP is ready! You can:', 'contact-inbox-hub'); ?></strong>
                <ol>
                    <li><?php _e('Enable admin notifications for new contact form submissions', 'contact-inbox-hub'); ?></li>
                    <li><?php _e('Enable user confirmation emails (auto-reply)', 'contact-inbox-hub'); ?></li>
                    <li><?php _e('Monitor email logs in the plugin dashboard', 'contact-inbox-hub'); ?></li>
                    <li><?php _e('Rest assured your contact forms will deliver emails reliably', 'contact-inbox-hub'); ?></li>
                </ol>
            </div>

            <div class="info-box">
                <strong><?php _e('Deliverability Tips:', 'contact-inbox-hub'); ?></strong><br>
                <small>
                    <?php _e('To ensure emails reach inboxes (not spam):', 'contact-inbox-hub'); ?><br>
                    • <?php _e('Set up SPF, DKIM, and DMARC records for your domain', 'contact-inbox-hub'); ?><br>
                    • <?php _e('Use a sender domain that matches your SMTP username domain', 'contact-inbox-hub'); ?><br>
                    • <?php _e('Keep email templates professional and avoid spam triggers', 'contact-inbox-hub'); ?><br>
                    • <?php _e('Monitor email logs for delivery issues', 'contact-inbox-hub'); ?>
                </small>
            </div>
        </div>

        <!-- Footer -->
        <div class="footer">
            <p><?php _e('This is a test email. You received it because you initiated an SMTP configuration test.', 'contact-inbox-hub'); ?></p>
            <p>
                <?php _e('Need help? Visit', 'contact-inbox-hub'); ?>
                <a href="https://github.com/bizjaved/contact-inbox-hub" target="_blank"><?php _e('our documentation', 'contact-inbox-hub'); ?></a>
            </p>
            <p style="margin-top: 15px; padding-top: 15px; border-top: 1px solid #e0e0e0; font-size: 11px;">
                <?php _e('Contact Inbox - Enterprise-Grade', 'contact-inbox-hub'); ?>
            </p>
        </div>
    </div>
</body>
</html>
