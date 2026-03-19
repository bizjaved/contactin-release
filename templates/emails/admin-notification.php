<?php
// phpcs:disable WordPress.Security.EscapeOutput.OutputNotEscaped, WordPress.DateTime.RestrictedFunctions.date_date
/**
 * Email Template: Admin Notification
 * File: templates/emails/admin-notification.php
 *
 * @var string $name      Sender name
 * @var string $email     Sender email
 * @var string $phone     Sender phone (optional)
 * @var string $message   Message content
 * @var string $ip        IP address
 * @var string $date      Submission date
 * @var string $inbox_link Admin inbox URL
 */

if (!defined('ABSPATH')) exit;
use ContactInbox\Core\Config;

?>

<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title><?php esc_html_e('New Contact Form Message', 'contact-inbox'); ?></title>
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; background: #f6f6f6; margin: 0; padding: 0; }
        .container { max-width: 600px; margin: 30px auto; background: #ffffff; border-radius: 12px; overflow: hidden; box-shadow: 0 4px 20px rgba(0,0,0,0.1); }
        .header { background: #0073aa; color: #ffffff; padding: 25px; text-align: center; }
        .header h1 { margin: 0; font-size: 24px; font-weight: 600; }
        .content { padding: 30px; line-height: 1.7; color: #333333; }
        .content p { margin: 0 0 15px; }
        .label { font-weight: bold; color: #0073aa; }
        .message-box { background: #f9f9f9; border-left: 4px solid #0073aa; padding: 15px; margin: 20px 0; font-style: italic; }
        .footer { background: #f1f1f1; padding: 20px; text-align: center; font-size: 12px; color: #666666; }
        .btn { display: inline-block; background: #00a32a; color: #ffffff; padding: 10px 20px; text-decoration: none; border-radius: 6px; font-weight: bold; margin-top: 15px; }
        .btn:hover { background: #008a24; }
        @media (max-width: 600px) {
            .container { margin: 15px; border-radius: 8px; }
            .content { padding: 20px; }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1><?php esc_html_e('New Message Received', 'contact-inbox'); ?></h1>
        </div>
        <div class="content">
            <p><?php esc_html_e('You have a new message from your contact form:', 'contact-inbox'); ?></p>

            <p><span class="label"><?php esc_html_e('From:', 'contact-inbox'); ?></span> <?php echo esc_html($name); ?> &lt;<?php echo esc_html($email); ?>&gt;</p>

            <?php if (!empty($phone)): ?>
                <p><span class="label"><?php esc_html_e('Phone:', 'contact-inbox'); ?></span> <?php echo esc_html($phone); ?></p>
            <?php endif; ?>

            <p><span class="label"><?php esc_html_e('Submitted:', 'contact-inbox'); ?></span> <?php echo esc_html($date); ?></p>
            <p><span class="label"><?php esc_html_e('IP Address:', 'contact-inbox'); ?></span> <?php echo esc_html($ip); ?></p>

            <div class="message-box">
                <?php echo nl2br(esc_html($message)); ?>
            </div>

            <p>
                <a href="<?php echo esc_url($inbox_link); ?>" class="btn" target="_blank">
                    <?php esc_html_e('Visit Inbox', 'contact-inbox'); ?>
                </a>
            </p>

            <p style="font-size:12px;color:#999;margin-top:25px;">
                <?php esc_html_e('This message was sent via ContactIn.', 'contact-inbox'); ?>
            </p>
        </div>
        <div class="footer">
            &copy; <?php echo date('Y'); ?> <?php bloginfo('name'); ?>. <?php esc_html_e('All rights reserved.', 'contact-inbox'); ?>
        </div>
    </div>
</body>
</html>