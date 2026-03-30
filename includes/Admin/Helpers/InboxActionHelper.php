<?php
/**
 * Inbox Action Helper
 * 
 * Centralized logic for controlling action button visibility
 * across inbox rows, message modal, and bulk actions.
 * 
 * @package ContactIn\Admin\Helpers
 */

declare(strict_types=1);

namespace ContactInbox\Admin\Helpers;

use ContactInbox\Core\Config;

// phpcs:disable WordPress.WP.I18n.NonSingularStringLiteralDomain, WordPress.Security.ValidatedSanitizedInput, WordPress.Security.NonceVerification

if (!defined('ABSPATH')) {
    exit;
}

final class InboxActionHelper {
    
    /**
     * Get available actions based on current context
     * 
     * @param string $status Message status/filter ('spam', 'archived', 'all', etc.)
     * @return array List of available action keys
     */
    public static function get_available_actions(string $status = ''): array {
        // Determine context from status parameter
        if (empty($status)) {
            $status = $_REQUEST['status'] ?? '';
        }
        
        // Normalize status - handle both string names and Config constants
        $context = self::get_context_from_status($status);
        
        $settings = \ContactInbox\Core\Settings::get_settings();
        $has_classification = !empty($settings['intent_enable']);
        
        switch ($context) {
            case 'spam':
                return [
                    'view',
                    'toggle_status',
                    'not_spam',  // Special action for spam
                    'delete'
                ];
                
            case 'archived':
                return [
                    'view',
                    'toggle_status',
                    'unarchive',  // Special action for archive
                    'delete'
                ];
                
            case 'main':
            default:
                $actions = [
                    'view',
                    'toggle_status',
                ];
                
                // Add classification only in main tab if enabled
                if ($has_classification) {
                    $actions[] = 'classification';
                }
                
                $actions[] = 'archive';
                $actions[] = 'spam';
                $actions[] = 'delete';
                
                return $actions;
        }
    }
    
    /**
     * Get available bulk actions based on current context
     * 
     * @param string $status Message status/filter ('spam', 'archived', 'all', etc.)
     * @return array Associative array of bulk action values and labels
     */
    public static function get_bulk_actions(string $status = ''): array {
        // Determine context from status parameter or $_REQUEST
        if (empty($status)) {
            $status = $_REQUEST['status'] ?? '';
        }
        
        $context = self::get_context_from_status($status);
        
        switch ($context) {
            case 'spam':
                return [
                    'not_spam' => __('Not Spam',  'contactin'),
                    'delete'   => __('Delete',  'contactin'),
                ];
                
            case 'archived':
                return [
                    'unarchive' => __('Unarchive',  'contactin'),
                    'delete'    => __('Delete',  'contactin'),
                ];
                
            case 'main':
            default:
                return [
                    'read'      => __('Mark as Read',  'contactin'),
                    'unread'    => __('Mark as Unread',  'contactin'),
                    'archive'   => __('Archive',  'contactin'),
                    'spam'      => __('Mark as Spam',  'contactin'),
                    'delete'    => __('Delete',  'contactin'),
                ];
        }
    }
    
    /**
     * Determine current context from page parameters
     * 
     * @return string 'main', 'spam', or 'archived'
     */
    public static function get_current_context(): string {
        $page = $_REQUEST['page'] ?? '';
        $status = $_REQUEST['status'] ?? '';
        
        return self::get_context_from_status($status, $page);
    }
    
    /**
     * Convert status/page to context
     * 
     * @param string $status Status filter parameter (can be Config::STATUS_SPAM, Config::STATUS_ARCHIVED, or string names)
     * @param string $page Page parameter (optional)
     * @return string 'main', 'spam', or 'archived'
     */
    private static function get_context_from_status(string $status, string $page = ''): string {
        // Check by status first - handle both Config constants and string names
        if ($status === Config::STATUS_SPAM || $status === 'spam') {
            return 'spam';
        }
        
        if ($status === Config::STATUS_ARCHIVED || $status === 'archived') {
            return 'archived';
        }
        
        // Check by page parameter if status doesn't determine it
        if (empty($page)) {
            $page = $_REQUEST['page'] ?? '';
        }
        
        if ($page === Config::MENU_SPAM) {
            return 'spam';
        }
        
        if ($page === Config::MENU_ARCHIVED) {
            return 'archived';
        }
        
        return 'main';
    }
    
    /**
     * Check if action is available in current context
     * 
     * @param string $action Action key
     * @param string $context Context ('main', 'spam', 'archived')
     * @return bool
     */
    public static function is_action_available(string $action, string $context): bool {
        $available = self::get_available_actions($context);
        return in_array($action, $available, true);
    }
}
