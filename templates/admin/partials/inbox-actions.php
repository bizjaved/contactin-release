<?php
if (!defined('ABSPATH')) exit;
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

use ContactInbox\Core\Config;
use ContactInbox\Admin\Helpers\InboxActionHelper;

// phpcs:disable WordPress.WP.I18n.NonSingularStringLiteralDomain, WordPress.NamingConventions.PrefixAllGlobals

$nonce     = wp_create_nonce(Config::INBOX_NONCE_ACTION);
$gdprNonce = wp_create_nonce(Config::GDPR_NONCE_ACTION);

// Get current context and available actions - pass the status parameter
$available_actions = InboxActionHelper::get_available_actions($current_status);

// Legacy variables for compatibility
$context = InboxActionHelper::get_current_context();
$is_archived = $context === 'archived';
$is_spam = $context === 'spam';
?>

<div class="cin-row-actions">
    <!-- View -->
    <?php if (in_array('view', $available_actions, true)) : ?>
    <button type="button" class="cin-btn cin-btn-icon cin-btn-primary cin-action-view contactin-view"
            data-id="<?php echo esc_attr($item->id); ?>"
            data-s="<?php echo esc_attr($search_term); ?>"
            data-status="<?php echo esc_attr($current_status); ?>"
            data-nonce="<?php echo esc_attr($nonce); ?>"
            aria-label="<?php esc_attr_e('View message details',  'contactin'); ?>"
            title="<?php esc_attr_e('View',  'contactin'); ?>">
        <span class="dashicons dashicons-visibility"></span>
    </button>
    <?php endif; ?>

    <!-- Toggle Read/Unread -->
    <?php if (in_array('toggle_status', $available_actions, true)) : ?>
    <button type="button" class="cin-btn cin-btn-icon cin-toggle-status <?php echo $item->status === 'read' ? 'cin-btn-secondary' : 'cin-btn-warning'; ?>"
            data-id="<?php echo esc_attr($item->id); ?>"
            data-s="<?php echo esc_attr($search_term); ?>"
            data-status="<?php echo esc_attr($current_status); ?>"
            data-nonce="<?php echo esc_attr($nonce); ?>"
            aria-label="<?php echo $item->status === 'read' ? esc_attr_e('Mark as Unread',  'contactin') : esc_attr_e('Mark as Read',  'contactin'); ?>"
            title="<?php echo $item->status === 'read' ? esc_attr_e('Mark as Unread',  'contactin') : esc_attr_e('Mark as Read',  'contactin'); ?>">
        <span class="dashicons <?php echo $item->status === 'read' ? 'dashicons-marker' : 'dashicons-yes-alt'; ?>"></span>
    </button>
    <?php endif; ?>

    <!-- Change Classification (Main tab only) -->
    <?php if (in_array('classification', $available_actions, true)) : ?>
    <button type="button" class="cin-btn cin-btn-icon cin-btn-secondary cin-action-classification"
            data-id="<?php echo esc_attr($item->id); ?>"
            data-s="<?php echo esc_attr($search_term); ?>"
            data-status="<?php echo esc_attr($current_status); ?>"
            data-current-category="<?php echo esc_attr($item->intent_category ?? 'unclassified'); ?>"
            aria-label="<?php esc_attr_e('Change classification',  'contactin'); ?>"
            title="<?php esc_attr_e('Change Classification',  'contactin'); ?>">
        <span class="dashicons dashicons-tag"></span>
    </button>
    <?php endif; ?>

    <!-- Archive (Main tab only) -->
    <?php if (in_array('archive', $available_actions, true)) : ?>
    <button type="button" class="cin-btn cin-btn-icon cin-btn-secondary cin-toggle-archive"
            data-id="<?php echo esc_attr($item->id); ?>"
            data-s="<?php echo esc_attr($search_term); ?>"
            data-status="<?php echo esc_attr($current_status); ?>"
            data-nonce="<?php echo esc_attr($nonce); ?>"
            data-action="archive"
            aria-label="<?php esc_attr_e('Archive message',  'contactin'); ?>"
            title="<?php esc_attr_e('Archive',  'contactin'); ?>">
        <span class="dashicons dashicons-archive"></span>
    </button>
    <?php endif; ?>

    <!-- Unarchive (Archive tab only) -->
    <?php if (in_array('unarchive', $available_actions, true)) : ?>
    <button type="button" class="cin-btn cin-btn-icon cin-btn-info cin-toggle-archive"
            data-id="<?php echo esc_attr($item->id); ?>"
            data-s="<?php echo esc_attr($search_term); ?>"
            data-status="<?php echo esc_attr($current_status); ?>"
            data-nonce="<?php echo esc_attr($nonce); ?>"
            data-action="unarchive"
            aria-label="<?php esc_attr_e('Restore message',  'contactin'); ?>"
            title="<?php esc_attr_e('Unarchive',  'contactin'); ?>">
        <span class="dashicons dashicons-undo"></span>
    </button>
    <?php endif; ?>

    <!-- Mark as Spam (Main tab only) -->
    <?php if (in_array('spam', $available_actions, true)) : ?>
    <button type="button" class="cin-btn cin-btn-icon cin-btn-danger cin-toggle-spam"
            data-id="<?php echo esc_attr($item->id); ?>"
            data-s="<?php echo esc_attr($search_term); ?>"
            data-status="<?php echo esc_attr($current_status); ?>"
            data-nonce="<?php echo esc_attr($nonce); ?>"
            aria-label="<?php esc_attr_e('Mark as spam',  'contactin'); ?>"
            title="<?php esc_attr_e('Mark as Spam',  'contactin'); ?>">
        <span class="dashicons dashicons-warning"></span>
    </button>
    <?php endif; ?>

    <!-- Not Spam (Spam tab only) -->
    <?php if (in_array('not_spam', $available_actions, true)) : ?>
    <button type="button" class="cin-btn cin-btn-icon cin-btn-secondary cin-toggle-spam"
            data-id="<?php echo esc_attr($item->id); ?>"
            data-s="<?php echo esc_attr($search_term); ?>"
            data-status="<?php echo esc_attr($current_status); ?>"
            data-nonce="<?php echo esc_attr($nonce); ?>"
            data-action="not_spam"
            aria-label="<?php esc_attr_e('Mark as not spam',  'contactin'); ?>"
            title="<?php esc_attr_e('Not Spam',  'contactin'); ?>">
        <span class="dashicons dashicons-yes"></span>
    </button>
    <?php endif; ?>

    <!-- Delete -->
    <?php if (in_array('delete', $available_actions, true)) : ?>
    <button type="button" class="cin-btn cin-btn-icon cin-action-delete contactin-delete cin-btn-danger"
            data-id="<?php echo esc_attr($item->id); ?>"
            data-s="<?php echo esc_attr($search_term); ?>"
            data-status="<?php echo esc_attr($current_status); ?>"
            data-nonce="<?php echo esc_attr($nonce); ?>"
            aria-label="<?php esc_attr_e('Delete this message permanently',  'contactin'); ?>"
            title="<?php esc_attr_e('Delete',  'contactin'); ?>">
        <span class="dashicons dashicons-trash"></span>
    </button>
    <?php endif; ?>
</div>
