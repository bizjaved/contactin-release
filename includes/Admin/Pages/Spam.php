<?php
/**
 * Admin Page – Spam (Deprecated)
 *
 * This page has been consolidated into the Unified Inbox.
 * Keep for backward compatibility and redirect to Unified Inbox spam tab.
 *
 * @package ContactIn\Admin\Pages
 */

declare(strict_types=1);

namespace ContactInbox\Admin\Pages;

use ContactInbox\Core\Config;
use ContactInbox\Traits\Singleton;

if (!defined('ABSPATH')) {
    exit;
}

final class Spam {
    use Singleton;

    /**
     * Render spam page.
     */
    public static function render(): void {
        if (!current_user_can(Config::CAPABILITY)) {
            wp_die(esc_html__('You do not have sufficient permissions to access this page.',  'contactin'));
        }

        $url = add_query_arg(
            [
                'page'   => Config::MENU_INBOX_UNIFIED,
                'folder' => 'spam',
            ],
            admin_url('admin.php')
        );

        wp_safe_redirect($url);
        exit;
    }
}
