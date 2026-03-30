<?php
/**
 * Email Template: GDPR Deletion Request
 * File: templates/emails/gdpr-deletion.php
 *
 * @var string $email        User email address
 * @var string $delete_link  GDPR deletion link
 * @var string $name         Sender name (optional)
 * @var string $subject      Message subject (optional)
 * @var string $message      Message content (optional)
 * @var string $phone        Phone number (optional)
 * @var string $submitted_at Submission date/time (optional)
 */

if (!defined('ABSPATH')) exit;
use ContactInbox\Core\Config;

// phpcs:disable WordPress.WP.I18n.NonSingularStringLiteralDomain, WordPress.Security.EscapeOutput

?>

<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title><?php _e('Your Data Deletion Link',  'contactin'); ?></title>
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
            background: #17a2b8; 
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
        .info-box { 
            background: #e8f4f8; 
            border-left: 4px solid #17a2b8; 
            padding: 15px 20px; 
            margin: 25px 0; 
            border-radius: 0 6px 6px 0; 
        }
        .warning-box { 
            background: #fff3cd; 
            border-left: 4px solid #ffc107; 
            padding: 15px 20px; 
            margin: 25px 0; 
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
            <h1><?php _e('Data Deletion Request',  'contactin'); ?></h1>
        </div>

        <!-- Body -->
        <div class="content">
            <p><?php _e('You (or someone) have requested to delete your data from our database.',  'contactin'); ?></p>

            <p><?php _e('Click the button below to permanently delete all data associated with your submission. This action cannot be undone.',  'contactin'); ?></p>

            <!-- What will be deleted -->
            <div class="info-box">
                <p style="margin: 0 0 12px; font-size: 14px;"><strong><?php _e('What will be deleted:',  'contactin'); ?></strong></p>
                
                <ul style="margin: 0; padding-left: 20px; font-size: 13px; line-height: 1.8;">
                    <li><?php _e('Your contact information (name, email, phone)',  'contactin'); ?></li>
                    <li><?php _e('Your message content',  'contactin'); ?></li>
                    <li><?php _e('Any uploaded files or attachments',  'contactin'); ?></li>
                    <li><?php _e('All related metadata and submission records',  'contactin'); ?></li>
                </ul>
            </div>

            <div class="warning-box">
                <p style="margin: 0; font-size: 13px;">
                    <strong><?php _e('⚠️ Important:',  'contactin'); ?></strong><br>
                    <?php _e('This link expires in 7 days for your security. Once you delete your data, it cannot be recovered.',  'contactin'); ?>
                </p>
            </div>

            <p style="text-align: center;">
                <a href="<?php echo esc_url($delete_link); ?>" class="btn" target="_blank">
                    <?php _e('Delete My Data',  'contactin'); ?>
                </a>
            </p>

            <p style="font-size: 13px; color: #666; margin-top: 30px;">
                <?php _e('If you did not request this deletion, you can safely ignore this email. Your data will remain secure.',  'contactin'); ?>
            </p>

            <p style="font-size: 14px; color: #666; margin-top: 20px;">
                — <?php bloginfo('name'); ?> <?php _e('Team',  'contactin'); ?>
            </p>
        </div>

        <!-- Footer -->
        <div class="footer">
            &copy; <?php echo gmdate('Y'); ?> <?php bloginfo('name'); ?>. 
            <?php _e('All rights reserved.',  'contactin'); ?><br>
            <a href="<?php echo esc_url(get_privacy_policy_url()); ?>">
                <?php _e('Privacy Policy',  'contactin'); ?>
            </a>
        </div>
    </div>
</body>
</html>
