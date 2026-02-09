<?php
/**
 * Template: Salesforce CRM Integration Help Modal
 * File: templates/admin/partials/crm-help-modal.php
 * Description: Comprehensive help guide for Salesforce CRM integration setup
 */

if (!defined('ABSPATH')) exit;
use ContactInbox\Core\Config;
?>

<div id="cin-crm-help-modal" class="cin-modal cin-modal-hidden" data-cin-help-modal="true">
    <div class="cin-modal-overlay"></div>
    <div class="cin-modal-content">
        <div class="cin-modal-header">
            <h2><?php _e('Salesforce CRM Integration Guide', Config::TEXTDOMAIN); ?></h2>
            <button type="button" class="cin-modal-close" aria-label="<?php _e('Close', Config::TEXTDOMAIN); ?>">
                <span aria-hidden="true">&times;</span>
            </button>
        </div>

        <div class="cin-modal-body">
            <!-- Quick Navigation -->
            <div class="cin-help-nav">
                <h3><?php _e('Quick Navigation', Config::TEXTDOMAIN); ?></h3>
                <ul>
                    <li><a href="#crm-overview" class="cin-help-link"><?php _e('Overview', Config::TEXTDOMAIN); ?></a></li>
                    <li><a href="#crm-setup" class="cin-help-link"><?php _e('Salesforce Setup', Config::TEXTDOMAIN); ?></a></li>
                    <li><a href="#crm-config" class="cin-help-link"><?php _e('Plugin Configuration', Config::TEXTDOMAIN); ?></a></li>
                    <li><a href="#crm-testing" class="cin-help-link"><?php _e('Testing', Config::TEXTDOMAIN); ?></a></li>
                    <li><a href="#crm-fields" class="cin-help-link"><?php _e('Field Mapping', Config::TEXTDOMAIN); ?></a></li>
                    <li><a href="#crm-troubleshoot" class="cin-help-link"><?php _e('Troubleshooting', Config::TEXTDOMAIN); ?></a></li>
                </ul>
            </div>

            <!-- Overview -->
            <div id="crm-overview" class="cin-help-section">
                <h3><?php _e('🔗 Salesforce Integration Overview', Config::TEXTDOMAIN); ?></h3>
                
                <div class="cin-help-item">
                    <h4><?php _e('What is Salesforce Integration?', Config::TEXTDOMAIN); ?></h4>
                    <p><?php _e('Contact Inbox sends every submission through an asynchronous queue that upserts the Contact (deduped by email) and creates a linked Case/Task record containing the actual inquiry. That means no duplicates, reliable retries, and a complete conversation trail inside Salesforce.', Config::TEXTDOMAIN); ?></p>
                </div>

                <div class="cin-help-item">
                    <h4><?php _e('Key Benefits', Config::TEXTDOMAIN); ?></h4>
                    <ul>
                        <li><strong><?php _e('Real-time Sync:', Config::TEXTDOMAIN); ?></strong> <?php _e('Contacts appear in Salesforce immediately after form submission', Config::TEXTDOMAIN); ?></li>
                        <li><strong><?php _e('Automatic Name Splitting:', Config::TEXTDOMAIN); ?></strong> <?php _e('Names are intelligently split into FirstName and LastName', Config::TEXTDOMAIN); ?></li>
                        <li><strong><?php _e('Customizable Mapping:', Config::TEXTDOMAIN); ?></strong> <?php _e('Map form fields to any Salesforce Contact (or custom) field', Config::TEXTDOMAIN); ?></li>
                        <li><strong><?php _e('Queue & Retry Protection:', Config::TEXTDOMAIN); ?></strong> <?php _e('A background queue handles delivery, so users never see Salesforce errors and failures auto‑retry.', Config::TEXTDOMAIN); ?></li>
                        <li><strong><?php _e('Regional Support:', Config::TEXTDOMAIN); ?></strong> <?php _e('Supports both Western (First Last) and Asian (Last First) name formats', Config::TEXTDOMAIN); ?></li>
                    </ul>
                </div>
            </div>

            <!-- Salesforce Setup -->
            <div id="crm-setup" class="cin-help-section">
                <h3><?php _e('⚙️ Setting Up Salesforce', Config::TEXTDOMAIN); ?></h3>
                
                <div class="cin-help-item">
                    <h4><?php _e('Step 1: Create a Connected App', Config::TEXTDOMAIN); ?></h4>
                    <ol>
                        <li><?php _e('Log in to Salesforce as an Admin', Config::TEXTDOMAIN); ?></li>
                        <li><?php _e('Click the gear icon (⚙️) → Setup', Config::TEXTDOMAIN); ?></li>
                        <li><?php _e('Navigate to: Apps & Integrations → App Manager', Config::TEXTDOMAIN); ?></li>
                        <li><?php _e('Click "New Connected App"', Config::TEXTDOMAIN); ?></li>
                        <li><?php _e('Fill in the form:', Config::TEXTDOMAIN); ?>
                            <ul>
                                <li><strong><?php _e('App Name:', Config::TEXTDOMAIN); ?></strong> WordPress ContactForm</li>
                                <li><strong><?php _e('Enable OAuth:', Config::TEXTDOMAIN); ?></strong> Check this box</li>
                                <li><strong><?php _e('Callback URL:', Config::TEXTDOMAIN); ?></strong> <?php _e('Your WordPress site URL + /wp-admin/admin-ajax.php', Config::TEXTDOMAIN); ?></li>
                                <li><strong><?php _e('Scopes:', Config::TEXTDOMAIN); ?></strong> Add: full, api, id, profile, email, address, phone</li>
                            </ul>
                        </li>
                        <li><?php _e('Click Save', Config::TEXTDOMAIN); ?></li>
                    </ol>
                </div>

                <div class="cin-help-item">
                    <h4><?php _e('Step 2: Get Your OAuth Credentials', Config::TEXTDOMAIN); ?></h4>
                    <ol>
                        <li><?php _e('Find your Created Connected App in the App Manager', Config::TEXTDOMAIN); ?></li>
                        <li><?php _e('Click on the app name', Config::TEXTDOMAIN); ?></li>
                        <li><?php _e('Scroll to "API (Enable OAuth Settings)"', Config::TEXTDOMAIN); ?></li>
                        <li><?php _e('Click "Reveal" next to Consumer Key and Consumer Secret', Config::TEXTDOMAIN); ?></li>
                        <li><?php _e('Save these credentials - you\'ll need them for authorization', Config::TEXTDOMAIN); ?></li>
                    </ol>
                </div>

                <div class="cin-help-item">
                    <h4><?php _e('Step 3: Capture Your Instance URL', Config::TEXTDOMAIN); ?></h4>
                    <ol>
                        <li><?php _e('From Setup, navigate to Company Information or copy the base URL from any Salesforce tab.', Config::TEXTDOMAIN); ?></li>
                        <li><?php _e('You only need the instance (e.g., https://acme-dev-ed.my.salesforce.com).', Config::TEXTDOMAIN); ?></li>
                        <li><?php _e('The plugin builds the REST endpoint automatically after OAuth completes—no need to paste API paths manually.', Config::TEXTDOMAIN); ?></li>
                    </ol>
                </div>
            </div>

            <!-- Plugin Configuration -->
            <div id="crm-config" class="cin-help-section">
                <h3><?php _e('🔧 Plugin Configuration', Config::TEXTDOMAIN); ?></h3>
                
                <div class="cin-help-item">
                    <h4><?php _e('Enable Integration', Config::TEXTDOMAIN); ?></h4>
                    <p><?php _e('Check "Enable Salesforce Integration" to activate contact syncing. When disabled, form submissions won\'t sync to Salesforce but will still be saved locally.', Config::TEXTDOMAIN); ?></p>
                </div>

                <div class="cin-help-item">
                    <h4><?php _e('Connected App Credentials', Config::TEXTDOMAIN); ?></h4>
                    <p><?php _e('Paste the Consumer Key and Consumer Secret from your Salesforce Connected App. These stay in WordPress and are used whenever you refresh the OAuth session.', Config::TEXTDOMAIN); ?></p>
                    <ul>
                        <li><?php _e('Keep credentials for Production and Sandbox separate to avoid cross‑posting data.', Config::TEXTDOMAIN); ?></li>
                        <li><?php _e('Rotate the Secret if someone leaves your team.', Config::TEXTDOMAIN); ?></li>
                    </ul>
                </div>

                <div class="cin-help-item">
                    <h4><?php _e('Callback URL Reminder', Config::TEXTDOMAIN); ?></h4>
                    <p><?php _e('Salesforce must be told where to send users after they approve the connection. Use the callback URL shown on the settings page (wp-admin/admin-ajax.php).', Config::TEXTDOMAIN); ?></p>
                    <p><?php _e('Paste that exact URL into your Connected App → OAuth Policies → Callback URLs.', Config::TEXTDOMAIN); ?></p>
                </div>

                <div class="cin-help-item">
                    <h4><?php _e('OAuth Authorization Flow', Config::TEXTDOMAIN); ?></h4>
                    <p><?php _e('Click "Connect to Salesforce" to start OAuth. After signing in and granting access, the plugin stores your refresh/access tokens, instance URL, org ID, and connected-app label automatically.', Config::TEXTDOMAIN); ?></p>
                    <ul>
                        <li><?php _e('Use the "Disconnect" button to revoke access or switch orgs.', Config::TEXTDOMAIN); ?></li>
                        <li><?php _e('Tokens are securely stored in the WordPress options table—no need to paste them manually.', Config::TEXTDOMAIN); ?></li>
                    </ul>
                </div>

                <div class="cin-help-item">
                    <h4><?php _e('Instance & Endpoint Auto-Detection', Config::TEXTDOMAIN); ?></h4>
                    <p><?php _e('Once OAuth succeeds, the plugin builds the correct REST endpoint for Contacts and remembers your Salesforce instance. You can view it under “Salesforce API Configuration”.', Config::TEXTDOMAIN); ?></p>
                    <ul>
                        <li><?php _e('No manual endpoint entries are required anymore.', Config::TEXTDOMAIN); ?></li>
                        <li><?php _e('If the instance changes (e.g., sandbox refresh), disconnect and reconnect.', Config::TEXTDOMAIN); ?></li>
                    </ul>
                </div>

                <div class="cin-help-item">
                    <h4><?php _e('Delivery Queue & Logs', Config::TEXTDOMAIN); ?></h4>
                    <p><?php _e('Submissions are inserted into the CRM queue and processed by the Queue Processor cron job. Review status, retries, and payloads under Analytics → Salesforce CRM tab or the dedicated CRM Log page.', Config::TEXTDOMAIN); ?></p>
                    <ul>
                        <li><?php _e('Use the new Operation filter on the CRM Log to isolate Contact vs Case attempts.', Config::TEXTDOMAIN); ?></li>
                        <li><?php _e('File attachments are queued separately with automatic retry logic and exponential backoff.', Config::TEXTDOMAIN); ?></li>
                        <li><?php _e('Manual CRM processing on the Maintenance page runs asynchronously with real-time progress indication.', Config::TEXTDOMAIN); ?></li>
                        <li><?php _e('If you pause the integration, queued items will resume once it is re-enabled.', Config::TEXTDOMAIN); ?></li>
                    </ul>
                </div>
            </div>

            <!-- Testing -->
            <div id="crm-testing" class="cin-help-section">
                <h3><?php _e('✅ Testing Your Integration', Config::TEXTDOMAIN); ?></h3>
                
                <div class="cin-help-item">
                    <h4><?php _e('Test Connection Button', Config::TEXTDOMAIN); ?></h4>
                    <p><?php _e('After saving your Connected App credentials and completing OAuth, click "Test Connection". This verifies:', Config::TEXTDOMAIN); ?></p>
                    <ul>
                        <li><?php _e('The stored refresh/access tokens are valid and not revoked', Config::TEXTDOMAIN); ?></li>
                        <li><?php _e('The detected instance URL is reachable from your WordPress host', Config::TEXTDOMAIN); ?></li>
                        <li><?php _e('Salesforce REST resources respond with the expected metadata (Contacts, Cases)', Config::TEXTDOMAIN); ?></li>
                    </ul>
                    <p><strong><?php _e('Success:', Config::TEXTDOMAIN); ?></strong> <span class="cin-help-status-success">✅ "Connection successful!"</span></p>
                    <p><strong><?php _e('Failure:', Config::TEXTDOMAIN); ?></strong> <?php _e('Check error message and review Troubleshooting section', Config::TEXTDOMAIN); ?></p>
                </div>

                <div class="cin-help-item">
                    <h4><?php _e('Test Form Submission', Config::TEXTDOMAIN); ?></h4>
                    <ol>
                        <li><?php _e('Save your plugin configuration first', Config::TEXTDOMAIN); ?></li>
                        <li><?php _e('Go to your website and find the contact form', Config::TEXTDOMAIN); ?></li>
                        <li><?php _e('Submit a test message with these details:', Config::TEXTDOMAIN); ?>
                            <ul>
                                <li><strong><?php _e('Name:', Config::TEXTDOMAIN); ?></strong> "John Doe" (must have 2+ words)</li>
                                <li><strong><?php _e('Email:', Config::TEXTDOMAIN); ?></strong> your-email@example.com</li>
                                <li><strong><?php _e('Phone:', Config::TEXTDOMAIN); ?></strong> +1-555-0123</li>
                                <li><strong><?php _e('Subject:', Config::TEXTDOMAIN); ?></strong> "Test Contact"</li>
                                <li><strong><?php _e('Message:', Config::TEXTDOMAIN); ?></strong> "Testing Salesforce integration"</li>
                            </ul>
                        </li>
                        <li><?php _e('Wait 10-30 seconds for async processing', Config::TEXTDOMAIN); ?></li>
                        <li><?php _e('Check Salesforce: Contacts & Cases tabs → Look for "John Doe" and the related Case', Config::TEXTDOMAIN); ?></li>
                        <li><?php _e('Verify Contact, Case, and custom field mappings are populated correctly', Config::TEXTDOMAIN); ?></li>
                    </ol>
                </div>
            </div>

            <!-- Field Mapping -->
            <div id="crm-fields" class="cin-help-section">
                <h3><?php _e('🔀 Field Mapping Reference', Config::TEXTDOMAIN); ?></h3>
                
                <div class="cin-help-item">
                    <h4><?php _e('Default Field Mappings', Config::TEXTDOMAIN); ?></h4>
                    <table class="cin-help-table">
                        <tr>
                            <th><?php _e('Form Field', Config::TEXTDOMAIN); ?></th>
                            <th><?php _e('Salesforce Field', Config::TEXTDOMAIN); ?></th>
                            <th><?php _e('Description', Config::TEXTDOMAIN); ?></th>
                        </tr>
                        <tr>
                            <td><strong><?php _e('Email', Config::TEXTDOMAIN); ?></strong></td>
                            <td><code>Email</code></td>
                            <td><?php _e('Contact email address', Config::TEXTDOMAIN); ?></td>
                        </tr>
                        <tr>
                            <td><strong><?php _e('Phone', Config::TEXTDOMAIN); ?></strong></td>
                            <td><code>Phone</code></td>
                            <td><?php _e('Contact phone number', Config::TEXTDOMAIN); ?></td>
                        </tr>
                        <tr>
                            <td><strong><?php _e('Subject', Config::TEXTDOMAIN); ?></strong></td>
                            <td><code>Title</code></td>
                            <td><?php _e('Contact title/position', Config::TEXTDOMAIN); ?></td>
                        </tr>
                        <tr>
                            <td><strong><?php _e('Message', Config::TEXTDOMAIN); ?></strong></td>
                            <td><code>Description</code></td>
                            <td><?php _e('Contact notes/description', Config::TEXTDOMAIN); ?></td>
                        </tr>
                        <tr>
                            <td><strong><?php _e('Name', Config::TEXTDOMAIN); ?></strong></td>
                            <td><code>FirstName, LastName</code></td>
                            <td><?php _e('Auto-split based on regional format', Config::TEXTDOMAIN); ?></td>
                        </tr>
                    </table>
                </div>

                <div class="cin-help-item">
                    <h4><?php _e('Regional Name Format', Config::TEXTDOMAIN); ?></h4>
                    <p><?php _e('The plugin automatically splits contact names into FirstName and LastName based on your selected format:', Config::TEXTDOMAIN); ?></p>
                    <ul>
                        <li><strong><?php _e('Western (First Last):', Config::TEXTDOMAIN); ?></strong> "John Doe" → FirstName="John", LastName="Doe"</li>
                        <li><strong><?php _e('Asian & Other (Last First):', Config::TEXTDOMAIN); ?></strong> "李 明" → FirstName="明", LastName="李"</li>
                    </ul>
                    <p><?php _e('Note: Contact names must contain at least 2 words to be split correctly.', Config::TEXTDOMAIN); ?></p>
                </div>

                <div class="cin-help-item">
                    <h4><?php _e('Custom Salesforce Fields', Config::TEXTDOMAIN); ?></h4>
                    <p><?php _e('If you have custom fields in Salesforce, you can map to them. Enter the field\'s API name (usually ends with __c):', Config::TEXTDOMAIN); ?></p>
                    <ul>
                        <li><?php _e('Example: Email → <code>Custom_Email__c</code>', Config::TEXTDOMAIN); ?></li>
                        <li><?php _e('Example: Subject → <code>Request_Type__c</code>', Config::TEXTDOMAIN); ?></li>
                    </ul>
                </div>
            </div>

            <!-- Troubleshooting -->
            <div id="crm-troubleshoot" class="cin-help-section">
                <h3><?php _e('🔍 Troubleshooting', Config::TEXTDOMAIN); ?></h3>
                
                <div class="cin-help-item">
                    <h4><?php _e('❌ "Connection failed - Invalid session"', Config::TEXTDOMAIN); ?></h4>
                    <p><strong><?php _e('Cause:', Config::TEXTDOMAIN); ?></strong> <?php _e('The stored OAuth refresh token was revoked, expired, or tied to a different org.', Config::TEXTDOMAIN); ?></p>
                    <p><strong><?php _e('Solution:', Config::TEXTDOMAIN); ?></strong></p>
                    <ol>
                        <li><?php _e('Click "Connect to Salesforce" and sign in again with the correct user/org.', Config::TEXTDOMAIN); ?></li>
                        <li><?php _e('Verify the Connected App still has the required OAuth scopes (full, api, id, profile, email).', Config::TEXTDOMAIN); ?></li>
                        <li><?php _e('If the user was deactivated, reconnect using an active integration user.', Config::TEXTDOMAIN); ?></li>
                    </ol>
                </div>

                <div class="cin-help-item">
                    <h4><?php _e('❌ "Connection failed - 401 Unauthorized"', Config::TEXTDOMAIN); ?></h4>
                    <p><strong><?php _e('Cause:', Config::TEXTDOMAIN); ?></strong> <?php _e('Consumer Key/Secret mismatch or the OAuth client no longer has API access.', Config::TEXTDOMAIN); ?></p>
                    <p><strong><?php _e('Solution:', Config::TEXTDOMAIN); ?></strong></p>
                    <ol>
                        <li><?php _e('Confirm the Consumer Key and Secret in WordPress match the Connected App.', Config::TEXTDOMAIN); ?></li>
                        <li><?php _e('Ensure the Salesforce user profile has the "API Enabled" permission.', Config::TEXTDOMAIN); ?></li>
                        <li><?php _e('Reconnect via OAuth and repeat the Test Connection check.', Config::TEXTDOMAIN); ?></li>
                    </ol>
                </div>

                <div class="cin-help-item">
                    <h4><?php _e('❌ "Connection failed - 404 Not Found"', Config::TEXTDOMAIN); ?></h4>
                    <p><strong><?php _e('Cause:', Config::TEXTDOMAIN); ?></strong> <?php _e('Salesforce moved your org to a different instance or the target object (Contact/Case) is unavailable for the integration user.', Config::TEXTDOMAIN); ?></p>
                    <p><strong><?php _e('Solution:', Config::TEXTDOMAIN); ?></strong></p>
                    <ol>
                        <li><?php _e('Disconnect and reconnect so the plugin can detect the new instance URL.', Config::TEXTDOMAIN); ?></li>
                        <li><?php _e('Verify the integration user can access the Contact and Case objects via API.', Config::TEXTDOMAIN); ?></li>
                        <li><?php _e('Confirm the Connected App has not restricted IP ranges or login hours.', Config::TEXTDOMAIN); ?></li>
                    </ol>
                </div>

                <div class="cin-help-item">
                    <h4><?php _e('❌ "Contacts not syncing to Salesforce"', Config::TEXTDOMAIN); ?></h4>
                    <p><strong><?php _e('Cause:', Config::TEXTDOMAIN); ?></strong> <?php _e('Multiple possible reasons', Config::TEXTDOMAIN); ?></p>
                    <p><strong><?php _e('Solution:', Config::TEXTDOMAIN); ?></strong></p>
                    <ol>
                        <li><?php _e('Verify "Enable Salesforce Integration" is checked', Config::TEXTDOMAIN); ?></li>
                        <li><?php _e('Open Analytics → Salesforce CRM or the CRM Log page to ensure queue items are processing (Status = Delivered)', Config::TEXTDOMAIN); ?></li>
                        <li><?php _e('Run the built-in Queue Processor cron job manually if you use basic hosting without WP-Cron traffic.', Config::TEXTDOMAIN); ?></li>
                        <li><?php _e('Check that contact names contain at least 2 words for proper splitting.', Config::TEXTDOMAIN); ?></li>
                        <li><?php _e('Search Salesforce by email address instead of name to avoid duplicate matches.', Config::TEXTDOMAIN); ?></li>
                        <li><?php _e('If entries remain in "Failed" status, open the log to view Salesforce error responses.', Config::TEXTDOMAIN); ?></li>
                    </ol>
                </div>

                <div class="cin-help-item">
                    <h4><?php _e('❌ "Contact name not splitting correctly"', Config::TEXTDOMAIN); ?></h4>
                    <p><strong><?php _e('Cause:', Config::TEXTDOMAIN); ?></strong> <?php _e('Name doesn\'t have enough words or wrong regional format', Config::TEXTDOMAIN); ?></p>
                    <p><strong><?php _e('Solution:', Config::TEXTDOMAIN); ?></strong></p>
                    <ol>
                        <li><?php _e('Ensure name has at least 2 words (e.g., "John Doe", not just "John")', Config::TEXTDOMAIN); ?></li>
                        <li><?php _e('Check "Regional Name Format" matches your name format', Config::TEXTDOMAIN); ?></li>
                        <li><?php _e('Test with a simple name like "John Doe"', Config::TEXTDOMAIN); ?></li>
                    </ol>
                </div>
            </div>

            <!-- Security Notice -->
            <div class="cin-help-item cin-help-highlight">
                <h4><?php _e('🔒 Security Best Practices', Config::TEXTDOMAIN); ?></h4>
                <ul>
                    <li><?php _e('Store the Consumer Key/Secret in a password manager—treat them like API keys.', Config::TEXTDOMAIN); ?></li>
                    <li><?php _e('Limit OAuth scopes to the ones listed here and review them quarterly.', Config::TEXTDOMAIN); ?></li>
                    <li><?php _e('Rotate the Consumer Secret and reconnect when admins leave your organization.', Config::TEXTDOMAIN); ?></li>
                    <li><?php _e('Use separate Connected Apps (and integration users) for Development, Staging, and Production.', Config::TEXTDOMAIN); ?></li>
                    <li><?php _e('Monitor Salesforce Setup → Security → Connected Apps OAuth Usage for unexpected logins.', Config::TEXTDOMAIN); ?></li>
                </ul>
            </div>
        </div>

        <div class="cin-modal-footer">
            <button type="button" class="button button-primary cin-modal-dismiss">
                <?php _e('Close', Config::TEXTDOMAIN); ?>
            </button>
        </div>
    </div>
</div>
