 <?php
/**
 * Email Template: User Confirmation (Auto-Reply)
 * File: templates/emails/user-confirmation.php
 *
 * @var string $name           Sender name
 * @var string $email          Sender email
 * @var string $subject        Message subject (if enabled)
 * @var bool   $subject_enabled Whether subject field is enabled
 * @var string $message        Message content
 * @var string $phone          Sender phone (optional)
 * @var string $submitted_at   Submission date/time
 * @var string $delete_link    GDPR deletion link
 */

if (!defined('ABSPATH')) exit;
use ContactInbox\Core\Config;

// phpcs:disable WordPress.WP.I18n.TextDomainMismatch, WordPress.WP.I18n.NonSingularStringLiteralDomain, WordPress.WP.I18n.MissingTranslatorsComment, WordPress.Security.EscapeOutput.OutputNotEscaped

?>

<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title><?php esc_html_e('Thank You for Your Message',  'contactin'); ?></title>
    <style>
        body { 
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; 
            background: #f6f6f6; 
            margin: 0; 
            padding: 0; 
            color: #333333;
        }
        .container { 
            max-width: 600px; 
            margin: 30px auto; 
            background: #ffffff; 
            border-radius: 12px; 
            overflow: hidden; 
            box-shadow: 0 4px 20px rgba(0,0,0,0.1); 
        }
        .header { 
            background: #28a745; 
            color: #ffffff; 
            padding: 30px 25px; 
            text-align: center; 
        }
        .header h1 { 
            margin: 0; 
            font-size: 26px; 
            font-weight: 600; 
        }
        .content { 
            padding: 35px; 
            line-height: 1.7; 
        }
        .content p { 
            margin: 0 0 18px; 
            font-size: 16px; 
        }
        .highlight { 
            background: #e8f5e8; 
            border-left: 4px solid #28a745; 
            padding: 15px 20px; 
            margin: 25px 0; 
            font-style: italic; 
            border-radius: 0 6px 6px 0; 
        }
        .btn { 
            display: inline-block; 
            background: #dc3545; 
            color: #ffffff; 
            padding: 12px 24px; 
            text-decoration: none; 
            border-radius: 6px; 
            font-weight: bold; 
            margin: 20px 0 10px; 
            font-size: 15px; 
        }
        .btn:hover { 
            background: #c82333; 
        }
        .footer { 
            background: #f1f1f1; 
            padding: 20px; 
            text-align: center; 
            font-size: 12px; 
            color: #777777; 
        }
        .footer a { 
            color: #0073aa; 
            text-decoration: none; 
        }
        @media (max-width: 600px) {
            .container { margin: 15px; border-radius: 8px; }
            .content { padding: 25px; }
            .header h1 { font-size: 22px; }
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- Header -->
        <div class="header">
            <h1><?php esc_html_e('Message Received!',  'contactin'); ?></h1>
        </div>

        <!-- Body -->
        <div class="content">
            <p><?php printf( __('Hi %s,',  'contactin'), esc_html($name) ); ?></p>

            <p><?php esc_html_e('Thank you for reaching out! We have successfully received your message and will get back to you as soon as possible.',  'contactin'); ?></p>

            <!-- Confirmation Notice -->
            <div style="background: #f9f9f9; border-left: 4px solid #28a745; padding: 15px 20px; margin: 20px 0; border-radius: 0 6px 6px 0;">
                <p style="margin: 0; font-size: 14px; line-height: 1.6;">
                    ✓ <?php esc_html_e('Your submission has been received and recorded in our system.',  'contactin'); ?>
                    <?php if (!empty($submitted_at)): ?>
                        <br><span style="font-size: 12px; color: #666;"><?php printf(__('Submitted on: %s',  'contactin'), esc_html($submitted_at)); ?></span>
                    <?php endif; ?>
                </p>
            </div>

            <div class="highlight">
                <?php esc_html_e('Your privacy matters. You can delete all data related to this submission (including any uploaded files) at any time using the link below.',  'contactin'); ?>
            </div>

            <p style="text-align: center;">
                <a href="<?php echo esc_url($delete_link); ?>" class="btn" target="_blank">
                    <?php esc_html_e('Delete My Data (GDPR)',  'contactin'); ?>
                </a>
            </p>

            <p><?php esc_html_e('We usually respond within 24–48 hours.',  'contactin'); ?></p>

            <p style="font-size: 14px; color: #666; margin-top: 30px;">
                — <?php bloginfo('name'); ?> <?php esc_html_e('Team',  'contactin'); ?>
            </p>
        </div>

        <!-- Footer -->
        <div class="footer">
            &copy; <?php echo esc_html( gmdate('Y') ); ?> <?php bloginfo('name'); ?>. 
            <?php esc_html_e('All rights reserved.',  'contactin'); ?><br>
            <a href="<?php echo esc_url(get_privacy_policy_url()); ?>">
                <?php esc_html_e('Privacy Policy',  'contactin'); ?>
            </a>
        </div>
    </div>
</body>
</html>