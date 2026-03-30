<?php
/**
 * Template: Inbox Help Modal
 * File: templates/admin/partials/inbox-help-modal.php
 * Description: Comprehensive help guide for inbox management
 */

if (!defined('ABSPATH')) exit;
use ContactInbox\Core\Config;

// phpcs:disable WordPress.Security.EscapeOutput.UnsafePrintingFunction, WordPress.WP.I18n.NonSingularStringLiteralDomain
?>

<div id="cin-inbox-help-modal" class="cin-modal cin-modal-hidden" data-cin-help-modal="true">
    <div class="cin-modal-overlay"></div>
    <div class="cin-modal-content">
        <div class="cin-modal-header">
            <h2><?php _e('ContactIn - Inbox Guide',  'contactin'); ?></h2>
            <button type="button" class="cin-modal-close" aria-label="<?php _e('Close',  'contactin'); ?>">
                <span aria-hidden="true">&times;</span>
            </button>
        </div>

        <div class="cin-modal-body">
            <!-- Quick Navigation -->
            <div class="cin-help-nav">
                <h3><?php _e('Quick Navigation',  'contactin'); ?></h3>
                <ul>
                    <li><a href="#inbox-overview" class="cin-help-link"><?php _e('Overview',  'contactin'); ?></a></li>
                    <li><a href="#inbox-search" class="cin-help-link"><?php _e('Search & Filtering',  'contactin'); ?></a></li>
                    <li><a href="#inbox-bulk-actions" class="cin-help-link"><?php _e('Bulk Actions',  'contactin'); ?></a></li>
                    <li><a href="#inbox-messages" class="cin-help-link"><?php _e('Managing Messages',  'contactin'); ?></a></li>
                    <li><a href="#inbox-status" class="cin-help-link"><?php _e('Message Status',  'contactin'); ?></a></li>
                    <li><a href="#inbox-gdpr" class="cin-help-link"><?php _e('GDPR & Privacy',  'contactin'); ?></a></li>
                </ul>
            </div>

            <!-- Overview -->
            <div id="inbox-overview" class="cin-help-section">
                <h3><?php _e('📬 Inbox Overview',  'contactin'); ?></h3>
                
                <div class="cin-help-item">
                    <h4><?php _e('What is the Inbox?',  'contactin'); ?></h4>
                    <p><?php _e('The Inbox is where all contact form submissions are stored. Every message your visitors submit through the contact form appears here, making it easy to manage and respond to inquiries.',  'contactin'); ?></p>
                </div>

                <div class="cin-help-item">
                    <h4><?php _e('Key Information Displayed',  'contactin'); ?></h4>
                    <ul>
                        <li><strong><?php _e('From:',  'contactin'); ?></strong> <?php _e('Visitor\'s name and email address',  'contactin'); ?></li>
                        <li><strong><?php _e('Subject:',  'contactin'); ?></strong> <?php _e('The subject line of their message',  'contactin'); ?></li>
                        <li><strong><?php _e('Message:',  'contactin'); ?></strong> <?php _e('Preview of the message content',  'contactin'); ?></li>
                        <li><strong><?php _e('Date:',  'contactin'); ?></strong> <?php _e('When the message was received',  'contactin'); ?></li>
                        <li><strong><?php _e('Status:',  'contactin'); ?></strong> <?php _e('Whether you\'ve read the message or not',  'contactin'); ?></li>
                    </ul>
                </div>

                <div class="cin-help-item">
                    <h4><?php _e('Total Messages Counter',  'contactin'); ?></h4>
                    <p><?php _e('At the top right, see the total number of messages in your inbox. This count updates as new submissions arrive.',  'contactin'); ?></p>
                </div>
            </div>

            <!-- Search & Filtering -->
            <div id="inbox-search" class="cin-help-section">
                <h3><?php _e('🔍 Search & Filtering',  'contactin'); ?></h3>
                
                <div class="cin-help-item">
                    <h4><?php _e('Search by Keyword',  'contactin'); ?></h4>
                    <p><?php _e('The search box at the top lets you find specific messages quickly.',  'contactin'); ?></p>
                    <ul>
                        <li><?php _e('Search by visitor name, email, subject, or message content',  'contactin'); ?></li>
                        <li><?php _e('Example: Search "John" to find all messages from John',  'contactin'); ?></li>
                        <li><?php _e('Search is case-insensitive',  'contactin'); ?></li>
                    </ul>
                </div>

                <div class="cin-help-item">
                    <h4><?php _e('Filter by Status',  'contactin'); ?></h4>
                    <p><?php _e('Use the Status dropdown to view messages by read/unread status.',  'contactin'); ?></p>
                    <ul>
                        <li><strong><?php _e('All:',  'contactin'); ?></strong> <?php _e('Show all messages regardless of read status',  'contactin'); ?></li>
                        <li><strong><?php _e('Read:',  'contactin'); ?></strong> <?php _e('Show only messages you\'ve already read',  'contactin'); ?></li>
                        <li><strong><?php _e('Unread:',  'contactin'); ?></strong> <?php _e('Show only new, unread messages',  'contactin'); ?></li>
                    </ul>
                </div>

                <div class="cin-help-item">
                    <h4><?php _e('Clear Filters',  'contactin'); ?></h4>
                    <p><?php _e('Click "Clear" to remove all active filters and see the full inbox again.',  'contactin'); ?></p>
                </div>

                <div class="cin-help-item">
                    <h4><?php _e('Show Entries',  'contactin'); ?></h4>
                    <p><?php _e('Control how many messages appear per page: 20, 50, or 100 messages at a time.',  'contactin'); ?></p>
                    <ul>
                        <li><?php _e('Choose based on your screen size and preference',  'contactin'); ?></li>
                        <li><?php _e('More entries per page = more scrolling but fewer page loads',  'contactin'); ?></li>
                    </ul>
                </div>
            </div>

            <!-- Bulk Actions -->
            <div id="inbox-bulk-actions" class="cin-help-section">
                <h3><?php _e('⚡ Bulk Actions',  'contactin'); ?></h3>
                
                <div class="cin-help-item">
                    <h4><?php _e('What are Bulk Actions?',  'contactin'); ?></h4>
                    <p><?php _e('Bulk actions let you perform operations on multiple messages at once, saving time when managing many submissions.',  'contactin'); ?></p>
                </div>

                <div class="cin-help-item">
                    <h4><?php _e('How to Use',  'contactin'); ?></h4>
                    <ol>
                        <li><?php _e('Check the checkbox next to each message you want to modify',  'contactin'); ?></li>
                        <li><?php _e('Or check the checkbox in the header to select ALL visible messages',  'contactin'); ?></li>
                        <li><?php _e('Select an action from the "Bulk actions" dropdown',  'contactin'); ?></li>
                        <li><?php _e('Click "Apply" to execute the action',  'contactin'); ?></li>
                    </ol>
                </div>

                <div class="cin-help-item">
                    <h4><?php _e('Available Bulk Actions',  'contactin'); ?></h4>
                    <ul>
                        <li><strong><?php _e('Mark as Read:',  'contactin'); ?></strong> <?php _e('Set selected messages to read status',  'contactin'); ?></li>
                        <li><strong><?php _e('Mark as Unread:',  'contactin'); ?></strong> <?php _e('Set selected messages to unread status',  'contactin'); ?></li>
                        <li><strong><?php _e('Delete Permanently:',  'contactin'); ?></strong> <?php _e('Remove selected messages from the inbox (cannot be undone)',  'contactin'); ?></li>
                    </ul>
                </div>

                <div class="cin-help-warning">
                    <h4>⚠️ <?php _e('Warning',  'contactin'); ?></h4>
                    <p><?php _e('Deleted messages cannot be recovered. Make sure you want to delete before applying the action.',  'contactin'); ?></p>
                </div>
            </div>

            <!-- Managing Messages -->
            <div id="inbox-messages" class="cin-help-section">
                <h3><?php _e('💬 Managing Individual Messages',  'contactin'); ?></h3>
                
                <div class="cin-help-item">
                    <h4><?php _e('Quick Actions (Row Actions)',  'contactin'); ?></h4>
                    <p><?php _e('Each message has action buttons on the right side:',  'contactin'); ?></p>
                </div>

                <div class="cin-help-item">
                    <h4>👁️ <?php _e('View Button',  'contactin'); ?></h4>
                    <p><?php _e('Click "View" to open the full message in a modal. See the complete message, visitor details, and attachments.',  'contactin'); ?></p>
                    <ul>
                        <li><?php _e('Opens in a popup without leaving the inbox page',  'contactin'); ?></li>
                        <li><?php _e('Shows all message details including attachments',  'contactin'); ?></li>
                        <li><?php _e('Contains contact information for quick reply',  'contactin'); ?></li>
                    </ul>
                </div>

                <div class="cin-help-item">
                    <h4>📌 <?php _e('Toggle Read/Unread Button',  'contactin'); ?></h4>
                    <p><?php _e('Quick button to mark a message as read or unread.',  'contactin'); ?></p>
                    <ul>
                        <li><?php _e('Checkmark icon = message is read',  'contactin'); ?></li>
                        <li><?php _e('Plus icon = message is unread',  'contactin'); ?></li>
                        <li><?php _e('Click to toggle status instantly',  'contactin'); ?></li>
                    </ul>
                </div>

                <div class="cin-help-item">
                    <h4>🗑️ <?php _e('Delete Button',  'contactin'); ?></h4>
                    <p><?php _e('Permanently delete a single message from the inbox.',  'contactin'); ?></p>
                    <ul>
                        <li><?php _e('Red trash icon',  'contactin'); ?></li>
                        <li><?php _e('Action is permanent - cannot be undone',  'contactin'); ?></li>
                        <li><?php _e('Use when you no longer need the message',  'contactin'); ?></li>
                    </ul>
                </div>

                <div class="cin-help-item">
                    <h4>🔐 <?php _e('GDPR Delete Link Button',  'contactin'); ?></h4>
                    <p><?php _e('Generate a link to help users delete their own data (GDPR compliance).',  'contactin'); ?></p>
                    <ul>
                        <li><?php _e('Privacy shield icon',  'contactin'); ?></li>
                        <li><?php _e('Share the generated link with the user',  'contactin'); ?></li>
                        <li><?php _e('User can securely delete their submission data',  'contactin'); ?></li>
                    </ul>
                </div>
            </div>

            <!-- Message Status -->
            <div id="inbox-status" class="cin-help-section">
                <h3><?php _e('📊 Understanding Message Status',  'contactin'); ?></h3>
                
                <div class="cin-help-item">
                    <h4><?php _e('Read vs Unread',  'contactin'); ?></h4>
                    <p><?php _e('Messages are marked as read when you open them. This helps you track which messages you\'ve reviewed.',  'contactin'); ?></p>
                    <ul>
                        <li><strong><?php _e('Unread (Bold):',  'contactin'); ?></strong> <?php _e('New message you haven\'t reviewed yet',  'contactin'); ?></li>
                        <li><strong><?php _e('Read (Normal):',  'contactin'); ?></strong> <?php _e('Message you\'ve already opened and viewed',  'contactin'); ?></li>
                    </ul>
                </div>

                <div class="cin-help-item">
                    <h4><?php _e('Visual Indicators',  'contactin'); ?></h4>
                    <p><?php _e('The status column shows at a glance if a message is new or already reviewed:',  'contactin'); ?></p>
                    <ul>
                        <li><?php _e('Unread messages often appear in bold or with a highlight',  'contactin'); ?></li>
                        <li><?php _e('Read messages appear in normal text weight',  'contactin'); ?></li>
                    </ul>
                </div>

                <div class="cin-help-item">
                    <h4><?php _e('Why Track Status?',  'contactin'); ?></h4>
                    <p><?php _e('Status tracking helps you:',  'contactin'); ?></p>
                    <ul>
                        <li><?php _e('Know which messages need attention',  'contactin'); ?></li>
                        <li><?php _e('Avoid reviewing the same message twice',  'contactin'); ?></li>
                        <li><?php _e('Filter to see only unread (new) messages',  'contactin'); ?></li>
                    </ul>
                </div>
            </div>

            <!-- GDPR & Privacy -->
            <div id="inbox-gdpr" class="cin-help-section">
                <h3><?php _e('🔒 GDPR & Privacy',  'contactin'); ?></h3>
                
                <div class="cin-help-item">
                    <h4><?php _e('What is GDPR Compliance in the Inbox?',  'contactin'); ?></h4>
                    <p><?php _e('GDPR (General Data Protection Regulation) requires that users can request deletion of their personal data. The Inbox provides tools to help manage these requests.',  'contactin'); ?></p>
                </div>

                <div class="cin-help-item">
                    <h4><?php _e('GDPR Delete Link',  'contactin'); ?></h4>
                    <p><?php _e('When a user requests to delete their data:',  'contactin'); ?></p>
                    <ol>
                        <li><?php _e('Find their message in the inbox',  'contactin'); ?></li>
                        <li><?php _e('Click the "GDPR Delete Link" button (privacy shield icon)',  'contactin'); ?></li>
                        <li><?php _e('A unique link will be generated',  'contactin'); ?></li>
                        <li><?php _e('Send this link to the user',  'contactin'); ?></li>
                        <li><?php _e('User clicks the link and confirms deletion',  'contactin'); ?></li>
                        <li><?php _e('Their data is permanently removed from your inbox',  'contactin'); ?></li>
                    </ol>
                </div>

                <div class="cin-help-item">
                    <h4><?php _e('Data Security',  'contactin'); ?></h4>
                    <ul>
                        <li><?php _e('All messages are encrypted and securely stored',  'contactin'); ?></li>
                        <li><?php _e('Only you (admin) can view submitted messages',  'contactin'); ?></li>
                        <li><?php _e('User data is never sold or shared with third parties',  'contactin'); ?></li>
                        <li><?php _e('Deleted messages cannot be recovered',  'contactin'); ?></li>
                    </ul>
                </div>
            </div>

            <!-- Pro Tips -->
            <div class="cin-help-pro-tips">
                <h3>💡 <?php _e('Pro Tips & Best Practices',  'contactin'); ?></h3>
                
                <div class="cin-help-pro-tip-item">
                    <h4>📋 <?php _e('Organize Your Inbox',  'contactin'); ?></h4>
                    <p><?php _e('Regularly mark messages as read after reviewing them. Use the filter to see only unread messages to focus on new inquiries.',  'contactin'); ?></p>
                </div>

                <div class="cin-help-pro-tip-item">
                    <h4>⏰ <?php _e('Respond Quickly',  'contactin'); ?></h4>
                    <p><?php _e('When you receive a new message, aim to respond within 24 hours. Quick responses improve user satisfaction.',  'contactin'); ?></p>
                </div>

                <div class="cin-help-pro-tip-item">
                    <h4>🗑️ <?php _e('Clean Up Regularly',  'contactin'); ?></h4>
                    <p><?php _e('Delete old messages you no longer need to keep your inbox lean and fast.',  'contactin'); ?></p>
                </div>

                <div class="cin-help-pro-tip-item">
                    <h4>🔍 <?php _e('Use Search Effectively',  'contactin'); ?></h4>
                    <p><?php _e('Search by email or name to quickly find messages from specific users.',  'contactin'); ?></p>
                </div>

                <div class="cin-help-pro-tip-item">
                    <h4>👥 <?php _e('Handle GDPR Requests Promptly',  'contactin'); ?></h4>
                    <p><?php _e('GDPR requires responding to deletion requests within 30 days. Use the GDPR Delete Link to honor requests quickly.',  'contactin'); ?></p>
                </div>
            </div>
        </div>

        <div class="cin-modal-footer">
            <p><?php _e('For more help, visit our documentation or contact support.',  'contactin'); ?></p>
        </div>
    </div>
</div>
