<?php
/**
 * Template: Inbox Help Modal
 * File: templates/admin/partials/inbox-help-modal.php
 * Description: Comprehensive help guide for inbox management
 */

if (!defined('ABSPATH')) exit;
use ContactInbox\Core\Config;
?>

<div id="cin-inbox-help-modal" class="cin-modal cin-modal-hidden" data-cin-help-modal="true">
    <div class="cin-modal-overlay"></div>
    <div class="cin-modal-content">
        <div class="cin-modal-header">
            <h2><?php esc_html_e('ContactIn - Inbox Guide', 'contact-inbox'); ?></h2>
            <button type="button" class="cin-modal-close" aria-label="<?php esc_html_e('Close', 'contact-inbox'); ?>">
                <span aria-hidden="true">&times;</span>
            </button>
        </div>

        <div class="cin-modal-body">
            <!-- Quick Navigation -->
            <div class="cin-help-nav">
                <h3><?php esc_html_e('Quick Navigation', 'contact-inbox'); ?></h3>
                <ul>
                    <li><a href="#inbox-overview" class="cin-help-link"><?php esc_html_e('Overview', 'contact-inbox'); ?></a></li>
                    <li><a href="#inbox-search" class="cin-help-link"><?php esc_html_e('Search & Filtering', 'contact-inbox'); ?></a></li>
                    <li><a href="#inbox-bulk-actions" class="cin-help-link"><?php esc_html_e('Bulk Actions', 'contact-inbox'); ?></a></li>
                    <li><a href="#inbox-messages" class="cin-help-link"><?php esc_html_e('Managing Messages', 'contact-inbox'); ?></a></li>
                    <li><a href="#inbox-status" class="cin-help-link"><?php esc_html_e('Message Status', 'contact-inbox'); ?></a></li>
                    <li><a href="#inbox-gdpr" class="cin-help-link"><?php esc_html_e('GDPR & Privacy', 'contact-inbox'); ?></a></li>
                </ul>
            </div>

            <!-- Overview -->
            <div id="inbox-overview" class="cin-help-section">
                <h3><?php esc_html_e('📬 Inbox Overview', 'contact-inbox'); ?></h3>
                
                <div class="cin-help-item">
                    <h4><?php esc_html_e('What is the Inbox?', 'contact-inbox'); ?></h4>
                    <p><?php esc_html_e('The Inbox is where all contact form submissions are stored. Every message your visitors submit through the contact form appears here, making it easy to manage and respond to inquiries.', 'contact-inbox'); ?></p>
                </div>

                <div class="cin-help-item">
                    <h4><?php esc_html_e('Key Information Displayed', 'contact-inbox'); ?></h4>
                    <ul>
                        <li><strong><?php esc_html_e('From:', 'contact-inbox'); ?></strong> <?php esc_html_e('Visitor\'s name and email address', 'contact-inbox'); ?></li>
                        <li><strong><?php esc_html_e('Subject:', 'contact-inbox'); ?></strong> <?php esc_html_e('The subject line of their message', 'contact-inbox'); ?></li>
                        <li><strong><?php esc_html_e('Message:', 'contact-inbox'); ?></strong> <?php esc_html_e('Preview of the message content', 'contact-inbox'); ?></li>
                        <li><strong><?php esc_html_e('Date:', 'contact-inbox'); ?></strong> <?php esc_html_e('When the message was received', 'contact-inbox'); ?></li>
                        <li><strong><?php esc_html_e('Status:', 'contact-inbox'); ?></strong> <?php esc_html_e('Whether you\'ve read the message or not', 'contact-inbox'); ?></li>
                    </ul>
                </div>

                <div class="cin-help-item">
                    <h4><?php esc_html_e('Total Messages Counter', 'contact-inbox'); ?></h4>
                    <p><?php esc_html_e('At the top right, see the total number of messages in your inbox. This count updates as new submissions arrive.', 'contact-inbox'); ?></p>
                </div>
            </div>

            <!-- Search & Filtering -->
            <div id="inbox-search" class="cin-help-section">
                <h3><?php esc_html_e('🔍 Search & Filtering', 'contact-inbox'); ?></h3>
                
                <div class="cin-help-item">
                    <h4><?php esc_html_e('Search by Keyword', 'contact-inbox'); ?></h4>
                    <p><?php esc_html_e('The search box at the top lets you find specific messages quickly.', 'contact-inbox'); ?></p>
                    <ul>
                        <li><?php esc_html_e('Search by visitor name, email, subject, or message content', 'contact-inbox'); ?></li>
                        <li><?php esc_html_e('Example: Search "John" to find all messages from John', 'contact-inbox'); ?></li>
                        <li><?php esc_html_e('Search is case-insensitive', 'contact-inbox'); ?></li>
                    </ul>
                </div>

                <div class="cin-help-item">
                    <h4><?php esc_html_e('Filter by Status', 'contact-inbox'); ?></h4>
                    <p><?php esc_html_e('Use the Status dropdown to view messages by read/unread status.', 'contact-inbox'); ?></p>
                    <ul>
                        <li><strong><?php esc_html_e('All:', 'contact-inbox'); ?></strong> <?php esc_html_e('Show all messages regardless of read status', 'contact-inbox'); ?></li>
                        <li><strong><?php esc_html_e('Read:', 'contact-inbox'); ?></strong> <?php esc_html_e('Show only messages you\'ve already read', 'contact-inbox'); ?></li>
                        <li><strong><?php esc_html_e('Unread:', 'contact-inbox'); ?></strong> <?php esc_html_e('Show only new, unread messages', 'contact-inbox'); ?></li>
                    </ul>
                </div>

                <div class="cin-help-item">
                    <h4><?php esc_html_e('Clear Filters', 'contact-inbox'); ?></h4>
                    <p><?php esc_html_e('Click "Clear" to remove all active filters and see the full inbox again.', 'contact-inbox'); ?></p>
                </div>

                <div class="cin-help-item">
                    <h4><?php esc_html_e('Show Entries', 'contact-inbox'); ?></h4>
                    <p><?php esc_html_e('Control how many messages appear per page: 20, 50, or 100 messages at a time.', 'contact-inbox'); ?></p>
                    <ul>
                        <li><?php esc_html_e('Choose based on your screen size and preference', 'contact-inbox'); ?></li>
                        <li><?php esc_html_e('More entries per page = more scrolling but fewer page loads', 'contact-inbox'); ?></li>
                    </ul>
                </div>
            </div>

            <!-- Bulk Actions -->
            <div id="inbox-bulk-actions" class="cin-help-section">
                <h3><?php esc_html_e('⚡ Bulk Actions', 'contact-inbox'); ?></h3>
                
                <div class="cin-help-item">
                    <h4><?php esc_html_e('What are Bulk Actions?', 'contact-inbox'); ?></h4>
                    <p><?php esc_html_e('Bulk actions let you perform operations on multiple messages at once, saving time when managing many submissions.', 'contact-inbox'); ?></p>
                </div>

                <div class="cin-help-item">
                    <h4><?php esc_html_e('How to Use', 'contact-inbox'); ?></h4>
                    <ol>
                        <li><?php esc_html_e('Check the checkbox next to each message you want to modify', 'contact-inbox'); ?></li>
                        <li><?php esc_html_e('Or check the checkbox in the header to select ALL visible messages', 'contact-inbox'); ?></li>
                        <li><?php esc_html_e('Select an action from the "Bulk actions" dropdown', 'contact-inbox'); ?></li>
                        <li><?php esc_html_e('Click "Apply" to execute the action', 'contact-inbox'); ?></li>
                    </ol>
                </div>

                <div class="cin-help-item">
                    <h4><?php esc_html_e('Available Bulk Actions', 'contact-inbox'); ?></h4>
                    <ul>
                        <li><strong><?php esc_html_e('Mark as Read:', 'contact-inbox'); ?></strong> <?php esc_html_e('Set selected messages to read status', 'contact-inbox'); ?></li>
                        <li><strong><?php esc_html_e('Mark as Unread:', 'contact-inbox'); ?></strong> <?php esc_html_e('Set selected messages to unread status', 'contact-inbox'); ?></li>
                        <li><strong><?php esc_html_e('Delete Permanently:', 'contact-inbox'); ?></strong> <?php esc_html_e('Remove selected messages from the inbox (cannot be undone)', 'contact-inbox'); ?></li>
                    </ul>
                </div>

                <div class="cin-help-warning">
                    <h4>⚠️ <?php esc_html_e('Warning', 'contact-inbox'); ?></h4>
                    <p><?php esc_html_e('Deleted messages cannot be recovered. Make sure you want to delete before applying the action.', 'contact-inbox'); ?></p>
                </div>
            </div>

            <!-- Managing Messages -->
            <div id="inbox-messages" class="cin-help-section">
                <h3><?php esc_html_e('💬 Managing Individual Messages', 'contact-inbox'); ?></h3>
                
                <div class="cin-help-item">
                    <h4><?php esc_html_e('Quick Actions (Row Actions)', 'contact-inbox'); ?></h4>
                    <p><?php esc_html_e('Each message has action buttons on the right side:', 'contact-inbox'); ?></p>
                </div>

                <div class="cin-help-item">
                    <h4>👁️ <?php esc_html_e('View Button', 'contact-inbox'); ?></h4>
                    <p><?php esc_html_e('Click "View" to open the full message in a modal. See the complete message, visitor details, and attachments.', 'contact-inbox'); ?></p>
                    <ul>
                        <li><?php esc_html_e('Opens in a popup without leaving the inbox page', 'contact-inbox'); ?></li>
                        <li><?php esc_html_e('Shows all message details including attachments', 'contact-inbox'); ?></li>
                        <li><?php esc_html_e('Contains contact information for quick reply', 'contact-inbox'); ?></li>
                    </ul>
                </div>

                <div class="cin-help-item">
                    <h4>📌 <?php esc_html_e('Toggle Read/Unread Button', 'contact-inbox'); ?></h4>
                    <p><?php esc_html_e('Quick button to mark a message as read or unread.', 'contact-inbox'); ?></p>
                    <ul>
                        <li><?php esc_html_e('Checkmark icon = message is read', 'contact-inbox'); ?></li>
                        <li><?php esc_html_e('Plus icon = message is unread', 'contact-inbox'); ?></li>
                        <li><?php esc_html_e('Click to toggle status instantly', 'contact-inbox'); ?></li>
                    </ul>
                </div>

                <div class="cin-help-item">
                    <h4>🗑️ <?php esc_html_e('Delete Button', 'contact-inbox'); ?></h4>
                    <p><?php esc_html_e('Permanently delete a single message from the inbox.', 'contact-inbox'); ?></p>
                    <ul>
                        <li><?php esc_html_e('Red trash icon', 'contact-inbox'); ?></li>
                        <li><?php esc_html_e('Action is permanent - cannot be undone', 'contact-inbox'); ?></li>
                        <li><?php esc_html_e('Use when you no longer need the message', 'contact-inbox'); ?></li>
                    </ul>
                </div>

                <div class="cin-help-item">
                    <h4>🔐 <?php esc_html_e('GDPR Delete Link Button', 'contact-inbox'); ?></h4>
                    <p><?php esc_html_e('Generate a link to help users delete their own data (GDPR compliance).', 'contact-inbox'); ?></p>
                    <ul>
                        <li><?php esc_html_e('Privacy shield icon', 'contact-inbox'); ?></li>
                        <li><?php esc_html_e('Share the generated link with the user', 'contact-inbox'); ?></li>
                        <li><?php esc_html_e('User can securely delete their submission data', 'contact-inbox'); ?></li>
                    </ul>
                </div>
            </div>

            <!-- Message Status -->
            <div id="inbox-status" class="cin-help-section">
                <h3><?php esc_html_e('📊 Understanding Message Status', 'contact-inbox'); ?></h3>
                
                <div class="cin-help-item">
                    <h4><?php esc_html_e('Read vs Unread', 'contact-inbox'); ?></h4>
                    <p><?php esc_html_e('Messages are marked as read when you open them. This helps you track which messages you\'ve reviewed.', 'contact-inbox'); ?></p>
                    <ul>
                        <li><strong><?php esc_html_e('Unread (Bold):', 'contact-inbox'); ?></strong> <?php esc_html_e('New message you haven\'t reviewed yet', 'contact-inbox'); ?></li>
                        <li><strong><?php esc_html_e('Read (Normal):', 'contact-inbox'); ?></strong> <?php esc_html_e('Message you\'ve already opened and viewed', 'contact-inbox'); ?></li>
                    </ul>
                </div>

                <div class="cin-help-item">
                    <h4><?php esc_html_e('Visual Indicators', 'contact-inbox'); ?></h4>
                    <p><?php esc_html_e('The status column shows at a glance if a message is new or already reviewed:', 'contact-inbox'); ?></p>
                    <ul>
                        <li><?php esc_html_e('Unread messages often appear in bold or with a highlight', 'contact-inbox'); ?></li>
                        <li><?php esc_html_e('Read messages appear in normal text weight', 'contact-inbox'); ?></li>
                    </ul>
                </div>

                <div class="cin-help-item">
                    <h4><?php esc_html_e('Why Track Status?', 'contact-inbox'); ?></h4>
                    <p><?php esc_html_e('Status tracking helps you:', 'contact-inbox'); ?></p>
                    <ul>
                        <li><?php esc_html_e('Know which messages need attention', 'contact-inbox'); ?></li>
                        <li><?php esc_html_e('Avoid reviewing the same message twice', 'contact-inbox'); ?></li>
                        <li><?php esc_html_e('Filter to see only unread (new) messages', 'contact-inbox'); ?></li>
                    </ul>
                </div>
            </div>

            <!-- GDPR & Privacy -->
            <div id="inbox-gdpr" class="cin-help-section">
                <h3><?php esc_html_e('🔒 GDPR & Privacy', 'contact-inbox'); ?></h3>
                
                <div class="cin-help-item">
                    <h4><?php esc_html_e('What is GDPR Compliance in the Inbox?', 'contact-inbox'); ?></h4>
                    <p><?php esc_html_e('GDPR (General Data Protection Regulation) requires that users can request deletion of their personal data. The Inbox provides tools to help manage these requests.', 'contact-inbox'); ?></p>
                </div>

                <div class="cin-help-item">
                    <h4><?php esc_html_e('GDPR Delete Link', 'contact-inbox'); ?></h4>
                    <p><?php esc_html_e('When a user requests to delete their data:', 'contact-inbox'); ?></p>
                    <ol>
                        <li><?php esc_html_e('Find their message in the inbox', 'contact-inbox'); ?></li>
                        <li><?php esc_html_e('Click the "GDPR Delete Link" button (privacy shield icon)', 'contact-inbox'); ?></li>
                        <li><?php esc_html_e('A unique link will be generated', 'contact-inbox'); ?></li>
                        <li><?php esc_html_e('Send this link to the user', 'contact-inbox'); ?></li>
                        <li><?php esc_html_e('User clicks the link and confirms deletion', 'contact-inbox'); ?></li>
                        <li><?php esc_html_e('Their data is permanently removed from your inbox', 'contact-inbox'); ?></li>
                    </ol>
                </div>

                <div class="cin-help-item">
                    <h4><?php esc_html_e('Data Security', 'contact-inbox'); ?></h4>
                    <ul>
                        <li><?php esc_html_e('All messages are encrypted and securely stored', 'contact-inbox'); ?></li>
                        <li><?php esc_html_e('Only you (admin) can view submitted messages', 'contact-inbox'); ?></li>
                        <li><?php esc_html_e('User data is never sold or shared with third parties', 'contact-inbox'); ?></li>
                        <li><?php esc_html_e('Deleted messages cannot be recovered', 'contact-inbox'); ?></li>
                    </ul>
                </div>
            </div>

            <!-- Pro Tips -->
            <div class="cin-help-pro-tips">
                <h3>💡 <?php esc_html_e('Pro Tips & Best Practices', 'contact-inbox'); ?></h3>
                
                <div class="cin-help-pro-tip-item">
                    <h4>📋 <?php esc_html_e('Organize Your Inbox', 'contact-inbox'); ?></h4>
                    <p><?php esc_html_e('Regularly mark messages as read after reviewing them. Use the filter to see only unread messages to focus on new inquiries.', 'contact-inbox'); ?></p>
                </div>

                <div class="cin-help-pro-tip-item">
                    <h4>⏰ <?php esc_html_e('Respond Quickly', 'contact-inbox'); ?></h4>
                    <p><?php esc_html_e('When you receive a new message, aim to respond within 24 hours. Quick responses improve user satisfaction.', 'contact-inbox'); ?></p>
                </div>

                <div class="cin-help-pro-tip-item">
                    <h4>🗑️ <?php esc_html_e('Clean Up Regularly', 'contact-inbox'); ?></h4>
                    <p><?php esc_html_e('Delete old messages you no longer need to keep your inbox lean and fast.', 'contact-inbox'); ?></p>
                </div>

                <div class="cin-help-pro-tip-item">
                    <h4>🔍 <?php esc_html_e('Use Search Effectively', 'contact-inbox'); ?></h4>
                    <p><?php esc_html_e('Search by email or name to quickly find messages from specific users.', 'contact-inbox'); ?></p>
                </div>

                <div class="cin-help-pro-tip-item">
                    <h4>👥 <?php esc_html_e('Handle GDPR Requests Promptly', 'contact-inbox'); ?></h4>
                    <p><?php esc_html_e('GDPR requires responding to deletion requests within 30 days. Use the GDPR Delete Link to honor requests quickly.', 'contact-inbox'); ?></p>
                </div>
            </div>
        </div>

        <div class="cin-modal-footer">
            <p><?php esc_html_e('For more help, visit our documentation or contact support.', 'contact-inbox'); ?></p>
        </div>
    </div>
</div>
