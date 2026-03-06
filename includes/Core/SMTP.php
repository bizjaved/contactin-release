<?php
// phpcs:disable WordPress.WP.I18n.NonSingularStringLiteralText
declare(strict_types=1);

/**
 * Core – SMTP Email Handling with Logging and Templates
 * Fully typed, secure, universal, Enterprise-Grade
 *
 * @package ContactInbox\Core
 */

namespace ContactInbox\Core;

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
use ContactInbox\Core\Config;
use ContactInbox\Core\DB;
use ContactInbox\Core\EmailLog;
use ContactInbox\Core\QueueMonitor;
use ContactInbox\Core\Settings;
use ContactInbox\Traits\Singleton;


if (!defined('ABSPATH')) {
    exit;
}

final class SMTP {
    use Singleton;

    /**
     * Track the last SMTP error for debugging/log persistence.
     */
    private static string $last_error = '';

    private function __construct() {
        add_action('phpmailer_init', [$this, 'configure_phpmailer']);
        add_action(\ContactInbox\Core\Config::CRON_SMTP_TEST, [$this, 'handle_smtp_test'], 10, 2);
        
        // Email notification hooks
        add_action('contactin_send_admin_notification', [self::class, 'send_admin_notification']);
        add_action('contactin_send_user_confirmation', [self::class, 'send_user_confirmation']);
    }

    // =========================================================================
    // PHPMailer Loader & Config
    // =========================================================================

    private static function ensure_phpmailer_loaded(): void {
        if (!class_exists(PHPMailer::class)) {
            require_once ABSPATH . WPINC . '/PHPMailer/PHPMailer.php';
            require_once ABSPATH . WPINC . '/PHPMailer/SMTP.php';
            require_once ABSPATH . WPINC . '/PHPMailer/Exception.php';
        }
    }

    /**
     * Normalize one or more recipients into a list of valid email addresses.
     *
     * @param mixed $recipients String with comma/semicolon separators or array of addresses.
     * @return array<string>
     */
    private static function normalize_recipients($recipients): array {
        if (is_string($recipients)) {
            $candidates = preg_split('/[;,\r\n]+/', $recipients) ?: [];
        } elseif (is_array($recipients)) {
            $candidates = $recipients;
        } elseif (is_object($recipients) && method_exists($recipients, '__toString')) {
            $candidates = preg_split('/[;,\r\n]+/', (string) $recipients) ?: [];
        } else {
            $candidates = [];
        }

        $emails = [];
        foreach ($candidates as $candidate) {
            if ($candidate === null) {
                continue;
            }
            $candidate = trim((string) $candidate);
            if ($candidate === '') {
                continue;
            }
            $sanitized = sanitize_email($candidate);
            if ($sanitized && is_email($sanitized)) {
                $emails[$sanitized] = $sanitized;
            }
        }

        return array_values($emails);
    }

    /**
     * Apply stored SMTP settings to a PHPMailer instance with robust TLS handling.
     */
    private static function apply_smtp_settings(PHPMailer $mailer, array $settings): void {
        self::ensure_phpmailer_loaded();

        $mailer->isSMTP();

        $mailer->Host = trim((string) ($settings['smtp_host'] ?? ''));
        $port = (int) ($settings['smtp_port'] ?? 0);
        if ($port > 0) {
            $mailer->Port = $port;
        }

        $username      = trim((string) ($settings['smtp_user'] ?? ''));
        $stored_pass   = (string) ($settings['smtp_pass'] ?? '');
        $password      = $stored_pass !== '' ? Settings::decrypt_password($stored_pass) : '';

        $mailer->SMTPAuth = ($username !== '' || $password !== '');
        $mailer->Username = $username;
        $mailer->Password = $password;

        $encryption = strtolower((string) ($settings['smtp_encryption'] ?? ''));
        switch ($encryption) {
            case 'ssl':
            case 'smtps':
            case '465':
                $mailer->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
                $mailer->SMTPAutoTLS = false;
                if (empty($mailer->Port)) {
                    $mailer->Port = 465;
                }
                break;
            case 'tls':
            case 'starttls':
            case 'auto':
                $mailer->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
                $mailer->SMTPAutoTLS = true;
                if (empty($mailer->Port)) {
                    $mailer->Port = 587;
                }
                break;
            case 'none':
            case '':
            default:
                $mailer->SMTPSecure = '';
                $mailer->SMTPAutoTLS = false;
                if (empty($mailer->Port)) {
                    $mailer->Port = 25;
                }
                break;
        }

        $from_email_setting = trim((string) ($settings['smtp_from_email'] ?? ''));
        $from_email = sanitize_email($from_email_setting);
        if ($from_email === '' && $username !== '') {
            $fallback_email = sanitize_email($username);
            if ($fallback_email !== '') {
                $from_email = $fallback_email;
                Logger::warning('SMTP sender email missing; falling back to SMTP username', [
                    'smtp_user' => $username,
                ]);
            }
        }

        $from_name = trim((string) ($settings['smtp_from_name'] ?? get_bloginfo('name')));

        if ($from_email !== '' && $username !== '') {
            $from_domain = self::extract_domain($from_email);
            $user_domain = self::extract_domain($username);
            if ($from_domain !== '' && $user_domain !== '' && $from_domain !== $user_domain) {
                Logger::warning('SMTP sender domain differs from SMTP username domain', [
                    'from_email' => $from_email,
                    'smtp_user'  => $username,
                ]);
            }
        }

        if ($from_email !== '' && \is_email($from_email)) {
            $mailer->setFrom($from_email, $from_name !== '' ? $from_name : $from_email, false);
            $mailer->Sender = $from_email;
        }
    }

    public function configure_phpmailer(PHPMailer $phpmailer): void {
        $settings = get_option(Config::OPTION_SETTINGS, []);
        if (empty($settings['smtp_enable'])) {
            return;
        }

        self::apply_smtp_settings($phpmailer, $settings);
    }

    // =========================================================================
    // Core Send Method
    // =========================================================================

    public static function send(
        string $to,
        string $subject,
        string $message,
        array $headers = [],
        array $context = [],
        bool $suppress_log = false
        ): bool {
            self::ensure_phpmailer_loaded();

        $settings = get_option(Config::OPTION_SETTINGS, []);
        if (isset($context['settings_override']) && is_array($context['settings_override'])) {
            $settings = array_merge($settings, $context['settings_override']);
            unset($context['settings_override']);
        }

        self::$last_error = '';

        $recipients = self::normalize_recipients($to);
        if (empty($recipients)) {
            self::$last_error = 'No valid recipient email provided';
            if (!$suppress_log) {
                $original = trim((string) $to);
                DB::instance()->insert_email_log([
                    'recipient'     => $original !== '' ? $original : '(empty)',
                    'subject'       => $subject,
                    'status'        => DB::EMAIL_FAILED,
                    'error_message' => 'No valid recipient email provided',
                    'type'          => $context['type'] ?? EmailLog::TYPE_CONTACT_FORM,
                ]);
            }
            Logger::warning('SMTP send aborted: no valid recipient', [
                'original_recipient' => (string) $to,
                'subject'            => $subject,
                'context'            => $context['type'] ?? EmailLog::TYPE_CONTACT_FORM,
            ]);
            return false;
        }
        $recipient_list = implode(', ', $recipients);

        Logger::debug('SMTP send start', [
            'recipients' => $recipient_list,
            'subject'    => $subject,
            'context'    => $context['type'] ?? EmailLog::TYPE_CONTACT_FORM,
            'transport'  => empty($settings['smtp_enable']) ? 'wp_mail' : 'smtp',
        ]);

        // Fallback to wp_mail if SMTP disabled
        if (empty($settings['smtp_enable'])) {
            $result = wp_mail($recipients, $subject, $message, $headers);
            self::$last_error = $result ? '' : 'wp_mail returned false';
            if (!$suppress_log) {
                DB::instance()->insert_email_log([
                    'recipient'     => $recipient_list,
                    'subject'       => $subject,
                    'status'        => $result ? DB::EMAIL_SENT : DB::EMAIL_FAILED,
                    'error_message' => $result ? '' : 'send failed',
                    'type'          => $context['type'] ?? EmailLog::TYPE_CONTACT_FORM,
                ]);
            }
            Logger::log($result ? Logger::INFO : Logger::ERROR, 'wp_mail dispatch result', [
                'recipients' => $recipient_list,
                'subject'    => $subject,
                'context'    => $context['type'] ?? EmailLog::TYPE_CONTACT_FORM,
                'status'     => $result ? 'sent' : 'failed',
                'error'      => self::$last_error,
            ]);
            return $result;
        }

        // PHPMailer SMTP
        $mailer = new PHPMailer(true);
        try {
            $debug_level = (int) apply_filters(
                'contactin_smtp_debug_level',
                defined('CONTACTIN_SMTP_DEBUG') ? (int) constant('CONTACTIN_SMTP_DEBUG') : 0
            );
            if ($debug_level > 0) {
                $mailer->SMTPDebug = $debug_level;
                $mailer->Debugoutput = static function ($str, $level): void {
                    Logger::debug('SMTP debug output', [
                        'level'   => $level,
                        'message' => trim((string) $str),
                    ]);
                };
            }

            self::apply_smtp_settings($mailer, $settings);
            foreach ($recipients as $address) {
                $mailer->addAddress($address);
            }
            $mailer->Subject = $subject;
            $mailer->Body    = $message;
            $mailer->isHTML(true);

            $mailer->send();

            if (!$suppress_log) {
                DB::instance()->insert_email_log([
                    'recipient'     => $recipient_list,
                    'subject'       => $subject,
                    'status'        => DB::EMAIL_SENT,
                    'error_message' => '',
                    'type'          => $context['type'] ?? EmailLog::TYPE_CONTACT_FORM,
                ]);
            }

            self::$last_error = '';
            Logger::info('SMTP send succeeded', [
                'recipients' => $recipient_list,
                'subject'    => $subject,
                'context'    => $context['type'] ?? EmailLog::TYPE_CONTACT_FORM,
            ]);
            return true;
        } catch (Exception $e) {
            $error = $mailer->ErrorInfo ?: $e->getMessage();
            self::$last_error = $error;

            if (!$suppress_log) {
                DB::instance()->insert_email_log([
                    'recipient'     => $recipient_list,
                    'subject'       => $subject,
                    'status'        => Config::EMAIL_FAILED,
                    'error_message' => $error,
                    'type'          => $context['type'] ?? EmailLog::TYPE_CONTACT_FORM,
                ]);
            }

            Logger::error('SMTP send failed', [
                'recipients' => $recipient_list,
                'subject'    => $subject,
                'context'    => $context['type'] ?? EmailLog::TYPE_CONTACT_FORM,
                'error'      => $error,
            ]);

            return false;
        }
    }

    /**
     * Retrieve the most recent SMTP error message.
     */
    public static function get_last_error(): string {
        return self::$last_error;
    }

    private static function extract_domain(string $email): string {
        $email = trim($email);
        if ($email === '' || strpos($email, '@') === false) {
            return '';
        }

        $parts = explode('@', $email);
        $domain = strtolower(array_pop($parts));

        return is_string($domain) ? $domain : '';
    }

    // =========================================================================
    // Template Renderer
    // =========================================================================

    private static function render_email(string $template, array $vars = []): string {
        $file = CONTACTINBOX_PATH . $template;
        if (!file_exists($file)) {
            return '';
        }

        extract($vars, EXTR_SKIP);
        ob_start();
        include $file;
        return ob_get_clean();
    }

    // =========================================================================
    // Admin & User Notifications
    // =========================================================================

    /**
     * Build proper email headers for spam prevention
     * 
     * Headers that help deliverability:
     * - Reply-To: Allows recipients to reply directly
     * - List-Unsubscribe: Shows email is legitimate
     * - X-Priority: Marks importance
     * - X-Mailer: Identifies sender (helps with reputation)
     * - Bounces-To: Technical header for bounce handling
     * 
     * @param string $from_email The sender email address
     * @param string $reply_to Optional reply-to address
     * @return array Email headers
     */
    private static function build_email_headers(string $from_email = '', string $reply_to = ''): array {
        $headers = [
            'Content-Type: text/html; charset=UTF-8',
            'X-Mailer: Contact Inbox/1.0',
        ];

        // Add Reply-To header (helps with deliverability and user experience)
        if ($reply_to !== '' && is_email($reply_to)) {
            $headers[] = 'Reply-To: <' . $reply_to . '>';
        } elseif ($from_email !== '' && is_email($from_email)) {
            $headers[] = 'Reply-To: <' . $from_email . '>';
        }

        // Add List-Unsubscribe header (signals legitimate email)
        $site_url = home_url();
        if ($site_url !== '') {
            $headers[] = 'List-Unsubscribe: <' . home_url() . '>';
        }

        return $headers;
    }

    public static function send_admin_notification(
        array $data,
        string $recipient = '',
        string $subject = ''
    ): bool {
        $settings = get_option(Config::OPTION_SETTINGS, []);
        if (empty($settings[Config::SETTING_SEND_ADMIN_NOTIFICATION])) {
            return true;
        }

        $inbox_link = !empty($data['inbox_link'])
            ? (string) $data['inbox_link']
            : \admin_url(sprintf('admin.php?page=%s', Config::MENU_INBOX));

        $vars = [
            'name'        => $data['name'],
            'salutation'  => $data['salutation'] ?? '',
            'email'       => $data['email'],
            'phone'       => $data['phone'] ?? '',
            'message'     => $data['message'],
            'ip'          => $data['ip'],
            'date'        => $data['date'],
            'inbox_link'  => $inbox_link,
        ];
        // Render template or fallback plain text
        $body = self::render_email(Config::TEMPLATE_EMAIL . 'admin-notification.php', $vars);
        if ($body === '') {
            $body = "New message from {$data['name']} ({$data['email']})\n\n{$data['message']}\n\nInbox: {$inbox_link}";
        }

        $admin_email   = $recipient ?: ($settings[Config::SETTING_ADMIN_EMAIL] ?? get_option('admin_email'));
        $final_subject = $subject !== '' ? $subject : __(EmailLog::SUBJECT_CONTACT_FORM, 'contact-inbox');

        // Get from email for Reply-To header
        $from_email = sanitize_email($settings['smtp_from_email'] ?? '');
        $headers = self::build_email_headers($from_email, $data['email'] ?? '');

        // Call send() and return result for proper status handling
        return self::send(
            $admin_email,
            $final_subject,
            $body,
            $headers,
            ['type' => EmailLog::TYPE_CONTACT_FORM]
        );
    }

    public static function send_user_confirmation(array $data, string $subject = ''): bool {
        $settings      = get_option(Config::OPTION_SETTINGS, []);
        $final_subject = $subject !== '' 
            ? $subject 
            : __(EmailLog::SUBJECT_USER_CONFIRM, 'contact-inbox');

        if (empty($settings[Config::SETTING_SEND_USER_COPY])) {
            return true;
        }

        $settings_form = get_option(Config::OPTION_SETTINGS, []);
        $subject_enabled = !empty($settings_form['form_enable_subject']);
        
        $vars = [
            'name'        => $data['name'],
            'salutation'  => $data['salutation'] ?? '',
            'email'       => $data['email'],
            'subject'     => $data['subject'] ?? '',
            'subject_enabled' => $subject_enabled,
            'message'     => $data['message'] ?? '',
            'phone'       => $data['phone'] ?? '',
            'submitted_at' => $data['submitted_at'] ?? '',
            'delete_link' => $data['delete_link'],
        ];

        // Render template or fallback plain text
        $body = self::render_email(Config::TEMPLATE_EMAIL . 'user-confirmation.php', $vars);
        if ($body === '') {
            $body = "Hi {$data['name']},\n\nThank you! We received your message.\n\n"
                . "Delete your data anytime: {$data['delete_link']}";
        }

        // Get from email for Reply-To header
        $from_email = sanitize_email($settings['smtp_from_email'] ?? '');
        $headers = self::build_email_headers($from_email, '');

        // Call send() and return result for proper status handling
        return self::send(
            $data['email'],
            $final_subject,
            $body,
            $headers,
            ['type' => EmailLog::TYPE_USER_CONFIRM]
        );
    }

    // =========================================================================
    // SMTP Test
    // =========================================================================
    public static function test_smtp(string $to = '', array $override_settings = [], bool $suppress_log = false): array {
        self::ensure_phpmailer_loaded();
        $settings = array_merge(get_option(Config::OPTION_SETTINGS, []), $override_settings);
        $settings['smtp_enable'] = 1;

        $recipients = self::normalize_recipients($to);
        if (empty($recipients)) {
            $fallback = $settings['admin_email'] ?? get_option('admin_email');
            $recipients = self::normalize_recipients((string) $fallback);
        }

        if (empty($recipients)) {
            self::$last_error = 'No valid recipient email provided';
            return [
                'success' => false,
                'message' => 'SMTP test aborted: no valid recipient',
                'error'   => self::$last_error,
            ];
        }

        $recipient_list = implode(', ', $recipients);

        $subject = EmailLog::SUBJECT_SMTP_TEST;
        
        // Use HTML template for test email
        $body = self::render_email(Config::TEMPLATE_EMAIL . 'smtp-test.php', []);
        if ($body === '') {
            // Fallback plain text if template fails
            $body = "SMTP Test Email\n\n"
                . "This is a test email from Contact Inbox plugin.\n\n"
                . "If you received this, your SMTP configuration is working correctly.\n\n"
                . "Test Time: " . current_time('mysql') . "\n"
                . "Site: " . home_url();
        }

        // Get from email for Reply-To header
        $from_email = sanitize_email($settings['smtp_from_email'] ?? '');
        $headers = self::build_email_headers($from_email, '');

        $result = self::send(
            implode(',', $recipients),
            $subject,
            $body,
            $headers,
            [
                'type' => EmailLog::TYPE_SMTP_TEST,
                'settings_override' => $settings,
            ],
            $suppress_log
        );

        $error = self::get_last_error();
        $message_text = $result
            ? "SMTP test email sent successfully to {$recipient_list}"
            : ($error !== '' ? $error : "SMTP test failed to {$recipient_list}");

        return [
            'success' => $result,
            'message' => $message_text,
            'error'   => $error,
        ];
    }

    // =========================================================================
    // GDPR Email
    // =========================================================================

    /**
     * Send GDPR deletion email.
     *
     * @param string $to Recipient email
     * @param string $delete_link Deletion link
     * @param array $message_data Message metadata for template
     */
    public static function send_gdpr_email(string $to, string $delete_link, array $message_data = []): bool {
        $start_time = microtime(true);
        $recipients = self::normalize_recipients($to);
        $recipient_list = !empty($recipients) ? implode(',', $recipients) : $to;
        $error = '';

        if (empty($recipients)) {
            $original = trim($to);
            DB::instance()->insert_email_log([
                'recipient'     => $original !== '' ? $original : '(empty)',
                'subject'       => EmailLog::SUBJECT_GDPR,
                'status'        => DB::EMAIL_FAILED,
                'error_message' => 'No valid recipient email provided',
            ]);

            $duration_ms = intval((microtime(true) - $start_time) * 1000);
            QueueMonitor::record_operation('gdpr_email', false, $duration_ms, 'No valid recipient email provided');
            return false;
        }

        $subject = __(EmailLog::SUBJECT_GDPR, 'contact-inbox');
        
        // Prepare template variables with message details
        $vars = [
            'email'        => $recipient_list,
            'delete_link'  => $delete_link,
            'name'         => $message_data['name'] ?? '',
            'subject'      => $message_data['subject'] ?? '',
            'message'      => $message_data['message'] ?? '',
            'phone'        => $message_data['phone'] ?? '',
            'submitted_at' => $message_data['submitted_at'] ?? '',
        ];

        // Render template or fallback to plain text
        $body = self::render_email(Config::TEMPLATE_EMAIL . 'gdpr-deletion.php', $vars);
        if ($body === '') {
            $body = "Hello,\n\nHere is your data deletion link:\n\n{$delete_link}\n\nThis link expires in 7 days.\n\nBest regards,\n" . get_bloginfo('name');
        }

        // Get from email for headers
        $settings = get_option(Config::OPTION_SETTINGS, []);
        $from_email = sanitize_email($settings['smtp_from_email'] ?? '');
        $headers = self::build_email_headers($from_email, '');

        $result = self::send($recipient_list, $subject, $body, $headers, ['type' => EmailLog::TYPE_GDPR]);

        global $phpmailer;
        $error = isset($phpmailer) ? $phpmailer->ErrorInfo : '';

        $duration_ms = intval((microtime(true) - $start_time) * 1000);
        QueueMonitor::record_operation('gdpr_email', $result, $duration_ms, $error);

        return $result;
    }

    public function handle_smtp_test(string $to, array $override, string $key = ''): void {
        $result = self::test_smtp($to, $override);
        if ($key) {
            set_transient($key, $result, MINUTE_IN_SECONDS * 10);
        }
    }

}
