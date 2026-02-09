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
            <h2><?php _e('Contact Inbox - Inbox Guide', Config::TEXTDOMAIN); ?></h2>
            <button type="button" class="cin-modal-close" aria-label="<?php _e('Close', Config::TEXTDOMAIN); ?>">
                <span aria-hidden="true">&times;</span>
            </button>
        </div>

        <div class="cin-modal-body">
            <!-- Quick Navigation -->
            <div class="cin-help-nav">
                <h3><?php _e('Quick Navigation', Config::TEXTDOMAIN); ?></h3>
                <ul>
                    <li><a href="#inbox-overview" class="cin-help-link"><?php _e('Overview', Config::TEXTDOMAIN); ?></a></li>
                    <li><a href="#inbox-search" class="cin-help-link"><?php _e('Search & Filtering', Config::TEXTDOMAIN); ?></a></li>
                    <li><a href="#inbox-bulk-actions" class="cin-help-link"><?php _e('Bulk Actions', Config::TEXTDOMAIN); ?></a></li>
                    <li><a href="#inbox-messages" class="cin-help-link"><?php _e('Managing Messages', Config::TEXTDOMAIN); ?></a></li>
                    <li><a href="#inbox-status" class="cin-help-link"><?php _e('Message Status', Config::TEXTDOMAIN); ?></a></li>
                    <li><a href="#inbox-gdpr" class="cin-help-link"><?php _e('GDPR & Privacy', Config::TEXTDOMAIN); ?></a></li>
                </ul>
            </div>

            <!-- Overview -->
            <div id="inbox-overview" class="cin-help-section">
                <h3><?php _e('📬 Inbox Overview', Config::TEXTDOMAIN); ?></h3>
                
                <div class="cin-help-item">
                    <h4><?php _e('What is the Inbox?', Config::TEXTDOMAIN); ?></h4>
                    <p><?php _e('The Inbox is where all contact form submissions are stored. Every message your visitors submit through the contact form appears here, making it easy to manage and respond to inquiries.', Config::TEXTDOMAIN); ?></p>
                </div>

                <div class="cin-help-item">
                    <h4><?php _e('Key Information Displayed', Config::TEXTDOMAIN); ?></h4>
                    <ul>
                        <li><strong><?php _e('From:', Config::TEXTDOMAIN); ?></strong> <?php _e('Visitor\'s name and email address', Config::TEXTDOMAIN); ?></li>
                        <li><strong><?php _e('Subject:', Config::TEXTDOMAIN); ?></strong> <?php _e('The subject line of their message', Config::TEXTDOMAIN); ?></li>
                        <li><strong><?php _e('Message:', Config::TEXTDOMAIN); ?></strong> <?php _e('Preview of the message content', Config::TEXTDOMAIN); ?></li>
                        <li><strong><?php _e('Date:', Config::TEXTDOMAIN); ?></strong> <?php _e('When the message was received', Config::TEXTDOMAIN); ?></li>
                        <li><strong><?php _e('Status:', Config::TEXTDOMAIN); ?></strong> <?php _e('Whether you\'ve read the message or not', Config::TEXTDOMAIN); ?></li>
                    </ul>
                </div>

                <div class="cin-help-item">
                    <h4><?php _e('Total Messages Counter', Config::TEXTDOMAIN); ?></h4>
                    <p><?php _e('At the top right, see the total number of messages in your inbox. This count updates as new submissions arrive.', Config::TEXTDOMAIN); ?></p>
                </div>
            </div>

            <!-- Search & Filtering -->
            <div id="inbox-search" class="cin-help-section">
                <h3><?php _e('🔍 Search & Filtering', Config::TEXTDOMAIN); ?></h3>
                
                <div class="cin-help-item">
                    <h4><?php _e('Search by Keyword', Config::TEXTDOMAIN); ?></h4>
                    <p><?php _e('The search box at the top lets you find specific messages quickly.', Config::TEXTDOMAIN); ?></p>
                    <ul>
                        <li><?php _e('Search by visitor name, email, subject, or message content', Config::TEXTDOMAIN); ?></li>
                        <li><?php _e('Example: Search "John" to find all messages from John', Config::TEXTDOMAIN); ?></li>
                        <li><?php _e('Search is case-insensitive', Config::TEXTDOMAIN); ?></li>
                    </ul>
                </div>

                <div class="cin-help-item">
                    <h4><?php _e('Filter by Status', Config::TEXTDOMAIN); ?></h4>
                    <p><?php _e('Use the Status dropdown to view messages by read/unread status.', Config::TEXTDOMAIN); ?></p>
                    <ul>
                        <li><strong><?php _e('All:', Config::TEXTDOMAIN); ?></strong> <?php _e('Show all messages regardless of read status', Config::TEXTDOMAIN); ?></li>
                        <li><strong><?php _e('Read:', Config::TEXTDOMAIN); ?></strong> <?php _e('Show only messages you\'ve already read', Config::TEXTDOMAIN); ?></li>
                        <li><strong><?php _e('Unread:', Config::TEXTDOMAIN); ?></strong> <?php _e('Show only new, unread messages', Config::TEXTDOMAIN); ?></li>
                    </ul>
                </div>

                <div class="cin-help-item">
                    <h4><?php _e('Clear Filters', Config::TEXTDOMAIN); ?></h4>
                    <p><?php _e('Click "Clear" to remove all active filters and see the full inbox again.', Config::TEXTDOMAIN); ?></p>
                </div>

                <div class="cin-help-item">
                    <h4><?php _e('Show Entries', Config::TEXTDOMAIN); ?></h4>
                    <p><?php _e('Control how many messages appear per page: 20, 50, or 100 messages at a time.', Config::TEXTDOMAIN); ?></p>
                    <ul>
                        <li><?php _e('Choose based on your screen size and preference', Config::TEXTDOMAIN); ?></li>
                        <li><?php _e('More entries per page = more scrolling but fewer page loads', Config::TEXTDOMAIN); ?></li>
                    </ul>
                </div>
            </div>

            <!-- Bulk Actions -->
            <div id="inbox-bulk-actions" class="cin-help-section">
                <h3><?php _e('⚡ Bulk Actions', Config::TEXTDOMAIN); ?></h3>
                
                <div class="cin-help-item">
                    <h4><?php _e('What are Bulk Actions?', Config::TEXTDOMAIN); ?></h4>
                    <p><?php _e('Bulk actions let you perform operations on multiple messages at once, saving time when managing many submissions.', Config::TEXTDOMAIN); ?></p>
                </div>

                <div class="cin-help-item">
                    <h4><?php _e('How to Use', Config::TEXTDOMAIN); ?></h4>
                    <ol>
                        <li><?php _e('Check the checkbox next to each message you want to modify', Config::TEXTDOMAIN); ?></li>
                        <li><?php _e('Or check the checkbox in the header to select ALL visible messages', Config::TEXTDOMAIN); ?></li>
                        <li><?php _e('Select an action from the "Bulk actions" dropdown', Config::TEXTDOMAIN); ?></li>
                        <li><?php _e('Click "Apply" to execute the action', Config::TEXTDOMAIN); ?></li>
                    </ol>
                </div>

                <div class="cin-help-item">
                    <h4><?php _e('Available Bulk Actions', Config::TEXTDOMAIN); ?></h4>
                    <ul>
                        <li><strong><?php _e('Mark as Read:', Config::TEXTDOMAIN); ?></strong> <?php _e('Set selected messages to read status', Config::TEXTDOMAIN); ?></li>
                        <li><strong><?php _e('Mark as Unread:', Config::TEXTDOMAIN); ?></strong> <?php _e('Set selected messages to unread status', Config::TEXTDOMAIN); ?></li>
                        <li><strong><?php _e('Delete Permanently:', Config::TEXTDOMAIN); ?></strong> <?php _e('Remove selected messages from the inbox (cannot be undone)', Config::TEXTDOMAIN); ?></li>
                    </ul>
                </div>

                <div class="cin-help-warning">
                    <h4>⚠️ <?php _e('Warning', Config::TEXTDOMAIN); ?></h4>
                    <p><?php _e('Deleted messages cannot be recovered. Make sure you want to delete before applying the action.', Config::TEXTDOMAIN); ?></p>
                </div>
            </div>

            <!-- Managing Messages -->
            <div id="inbox-messages" class="cin-help-section">
                <h3><?php _e('💬 Managing Individual Messages', Config::TEXTDOMAIN); ?></h3>
                
                <div class="cin-help-item">
                    <h4><?php _e('Quick Actions (Row Actions)', Config::TEXTDOMAIN); ?></h4>
                    <p><?php _e('Each message has action buttons on the right side:', Config::TEXTDOMAIN); ?></p>
                </div>

                <div class="cin-help-item">
                    <h4>👁️ <?php _e('View Button', Config::TEXTDOMAIN); ?></h4>
                    <p><?php _e('Click "View" to open the full message in a modal. See the complete message, visitor details, and attachments.', Config::TEXTDOMAIN); ?></p>
                    <ul>
                        <li><?php _e('Opens in a popup without leaving the inbox page', Config::TEXTDOMAIN); ?></li>
                        <li><?php _e('Shows all message details including attachments', Config::TEXTDOMAIN); ?></li>
                        <li><?php _e('Contains contact information for quick reply', Config::TEXTDOMAIN); ?></li>
                    </ul>
                </div>

                <div class="cin-help-item">
                    <h4>📌 <?php _e('Toggle Read/Unread Button', Config::TEXTDOMAIN); ?></h4>
                    <p><?php _e('Quick button to mark a message as read or unread.', Config::TEXTDOMAIN); ?></p>
                    <ul>
                        <li><?php _e('Checkmark icon = message is read', Config::TEXTDOMAIN); ?></li>
                        <li><?php _e('Plus icon = message is unread', Config::TEXTDOMAIN); ?></li>
                        <li><?php _e('Click to toggle status instantly', Config::TEXTDOMAIN); ?></li>
                    </ul>
                </div>

                <div class="cin-help-item">
                    <h4>🗑️ <?php _e('Delete Button', Config::TEXTDOMAIN); ?></h4>
                    <p><?php _e('Permanently delete a single message from the inbox.', Config::TEXTDOMAIN); ?></p>
                    <ul>
                        <li><?php _e('Red trash icon', Config::TEXTDOMAIN); ?></li>
                        <li><?php _e('Action is permanent - cannot be undone', Config::TEXTDOMAIN); ?></li>
                        <li><?php _e('Use when you no longer need the message', Config::TEXTDOMAIN); ?></li>
                    </ul>
                </div>

                <div class="cin-help-item">
                    <h4>🔐 <?php _e('GDPR Delete Link Button', Config::TEXTDOMAIN); ?></h4>
                    <p><?php _e('Generate a link to help users delete their own data (GDPR compliance).', Config::TEXTDOMAIN); ?></p>
                    <ul>
                        <li><?php _e('Privacy shield icon', Config::TEXTDOMAIN); ?></li>
                        <li><?php _e('Share the generated link with the user', Config::TEXTDOMAIN); ?></li>
                        <li><?php _e('User can securely delete their submission data', Config::TEXTDOMAIN); ?></li>
                    </ul>
                </div>
            </div>

            <!-- Message Status -->
            <div id="inbox-status" class="cin-help-section">
                <h3><?php _e('📊 Understanding Message Status', Config::TEXTDOMAIN); ?></h3>
                
                <div class="cin-help-item">
                    <h4><?php _e('Read vs Unread', Config::TEXTDOMAIN); ?></h4>
                    <p><?php _e('Messages are marked as read when you open them. This helps you track which messages you\'ve reviewed.', Config::TEXTDOMAIN); ?></p>
                    <ul>
                        <li><strong><?php _e('Unread (Bold):', Config::TEXTDOMAIN); ?></strong> <?php _e('New message you haven\'t reviewed yet', Config::TEXTDOMAIN); ?></li>
                        <li><strong><?php _e('Read (Normal):', Config::TEXTDOMAIN); ?></strong> <?php _e('Message you\'ve already opened and viewed', Config::TEXTDOMAIN); ?></li>
                    </ul>
                </div>

                <div class="cin-help-item">
                    <h4><?php _e('Visual Indicators', Config::TEXTDOMAIN); ?></h4>
                    <p><?php _e('The status column shows at a glance if a message is new or already reviewed:', Config::TEXTDOMAIN); ?></p>
                    <ul>
                        <li><?php _e('Unread messages often appear in bold or with a highlight', Config::TEXTDOMAIN); ?></li>
                        <li><?php _e('Read messages appear in normal text weight', Config::TEXTDOMAIN); ?></li>
                    </ul>
                </div>

                <div class="cin-help-item">
                    <h4><?php _e('Why Track Status?', Config::TEXTDOMAIN); ?></h4>
                    <p><?php _e('Status tracking helps you:', Config::TEXTDOMAIN); ?></p>
                    <ul>
                        <li><?php _e('Know which messages need attention', Config::TEXTDOMAIN); ?></li>
                        <li><?php _e('Avoid reviewing the same message twice', Config::TEXTDOMAIN); ?></li>
                        <li><?php _e('Filter to see only unread (new) messages', Config::TEXTDOMAIN); ?></li>
                    </ul>
                </div>
            </div>

            <!-- GDPR & Privacy -->
            <div id="inbox-gdpr" class="cin-help-section">
                <h3><?php _e('🔒 GDPR & Privacy', Config::TEXTDOMAIN); ?></h3>
                
                <div class="cin-help-item">
                    <h4><?php _e('What is GDPR Compliance in the Inbox?', Config::TEXTDOMAIN); ?></h4>
                    <p><?php _e('GDPR (General Data Protection Regulation) requires that users can request deletion of their personal data. The Inbox provides tools to help manage these requests.', Config::TEXTDOMAIN); ?></p>
                </div>

                <div class="cin-help-item">
                    <h4><?php _e('GDPR Delete Link', Config::TEXTDOMAIN); ?></h4>
                    <p><?php _e('When a user requests to delete their data:', Config::TEXTDOMAIN); ?></p>
                    <ol>
                        <li><?php _e('Find their message in the inbox', Config::TEXTDOMAIN); ?></li>
                        <li><?php _e('Click the "GDPR Delete Link" button (privacy shield icon)', Config::TEXTDOMAIN); ?></li>
                        <li><?php _e('A unique link will be generated', Config::TEXTDOMAIN); ?></li>
                        <li><?php _e('Send this link to the user', Config::TEXTDOMAIN); ?></li>
                        <li><?php _e('User clicks the link and confirms deletion', Config::TEXTDOMAIN); ?></li>
                        <li><?php _e('Their data is permanently removed from your inbox', Config::TEXTDOMAIN); ?></li>
                    </ol>
                </div>

                <div class="cin-help-item">
                    <h4><?php _e('Data Security', Config::TEXTDOMAIN); ?></h4>
                    <ul>
                        <li><?php _e('All messages are encrypted and securely stored', Config::TEXTDOMAIN); ?></li>
                        <li><?php _e('Only you (admin) can view submitted messages', Config::TEXTDOMAIN); ?></li>
                        <li><?php _e('User data is never sold or shared with third parties', Config::TEXTDOMAIN); ?></li>
                        <li><?php _e('Deleted messages cannot be recovered', Config::TEXTDOMAIN); ?></li>
                    </ul>
                </div>
            </div>

            <!-- Pro Tips -->
            <div class="cin-help-pro-tips">
                <h3>💡 <?php _e('Pro Tips & Best Practices', Config::TEXTDOMAIN); ?></h3>
                
                <div class="cin-help-pro-tip-item">
                    <h4>📋 <?php _e('Organize Your Inbox', Config::TEXTDOMAIN); ?></h4>
                    <p><?php _e('Regularly mark messages as read after reviewing them. Use the filter to see only unread messages to focus on new inquiries.', Config::TEXTDOMAIN); ?></p>
                </div>

                <div class="cin-help-pro-tip-item">
                    <h4>⏰ <?php _e('Respond Quickly', Config::TEXTDOMAIN); ?></h4>
                    <p><?php _e('When you receive a new message, aim to respond within 24 hours. Quick responses improve user satisfaction.', Config::TEXTDOMAIN); ?></p>
                </div>

                <div class="cin-help-pro-tip-item">
                    <h4>🗑️ <?php _e('Clean Up Regularly', Config::TEXTDOMAIN); ?></h4>
                    <p><?php _e('Delete old messages you no longer need to keep your inbox lean and fast.', Config::TEXTDOMAIN); ?></p>
                </div>

                <div class="cin-help-pro-tip-item">
                    <h4>🔍 <?php _e('Use Search Effectively', Config::TEXTDOMAIN); ?></h4>
                    <p><?php _e('Search by email or name to quickly find messages from specific users.', Config::TEXTDOMAIN); ?></p>
                </div>

                <div class="cin-help-pro-tip-item">
                    <h4>👥 <?php _e('Handle GDPR Requests Promptly', Config::TEXTDOMAIN); ?></h4>
                    <p><?php _e('GDPR requires responding to deletion requests within 30 days. Use the GDPR Delete Link to honor requests quickly.', Config::TEXTDOMAIN); ?></p>
                </div>
            </div>
        </div>

        <div class="cin-modal-footer">
            <p><?php _e('For more help, visit our documentation or contact support.', Config::TEXTDOMAIN); ?></p>
        </div>
    </div>
</div>
