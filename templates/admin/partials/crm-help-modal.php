<?php
/**
 * Template: Salesforce CRM Integration Help Modal
 * File: templates/admin/partials/crm-help-modal.php
 * Description: Comprehensive help guide for Salesforce CRM integration setup
 */

if (!defined('ABSPATH')) exit;
use ContactInbox\Core\Config;

$contactin_ajax_callback_url = admin_url('admin-ajax.php');
?>

<div id="cin-crm-help-modal" class="cin-modal cin-modal-hidden" data-cin-help-modal="true">
    <div class="cin-modal-overlay"></div>
    <div class="cin-modal-content">
        <div class="cin-modal-header">
            <h2><?php esc_html_e('Salesforce CRM Integration Guide', 'contact-inbox'); ?></h2>
            <button type="button" class="cin-modal-close" aria-label="<?php esc_html_e('Close', 'contact-inbox'); ?>">
                <span aria-hidden="true">&times;</span>
            </button>
        </div>

        <div class="cin-modal-body">
            <!-- Quick Navigation -->
            <div class="cin-help-nav">
                <h3><?php esc_html_e('Quick Navigation', 'contact-inbox'); ?></h3>
                <ul>
                    <li><a href="#crm-overview" class="cin-help-link"><?php esc_html_e('Overview', 'contact-inbox'); ?></a></li>
                    <li><a href="#crm-setup" class="cin-help-link"><?php esc_html_e('Salesforce Setup', 'contact-inbox'); ?></a></li>
                    <li><a href="#crm-config" class="cin-help-link"><?php esc_html_e('Plugin Configuration', 'contact-inbox'); ?></a></li>
                    <li><a href="#crm-testing" class="cin-help-link"><?php esc_html_e('Testing', 'contact-inbox'); ?></a></li>
                    <li><a href="#crm-fields" class="cin-help-link"><?php esc_html_e('Field Mapping', 'contact-inbox'); ?></a></li>
                    <li><a href="#crm-troubleshoot" class="cin-help-link"><?php esc_html_e('Troubleshooting', 'contact-inbox'); ?></a></li>
                </ul>
            </div>

            <!-- Overview -->
            <div id="crm-overview" class="cin-help-section">
                <h3><?php esc_html_e('🔗 Salesforce Integration Overview', 'contact-inbox'); ?></h3>
                
                <div class="cin-help-item">
                    <h4><?php esc_html_e('What is Salesforce Integration?', 'contact-inbox'); ?></h4>
                    <p><?php esc_html_e('Contact Inbox sends every submission through an asynchronous queue that upserts the Contact (deduped by email) and creates a linked Case/Task record containing the actual inquiry. That means no duplicates, reliable retries, and a complete conversation trail inside Salesforce.', 'contact-inbox'); ?></p>
                </div>

                <div class="cin-help-item">
                    <h4><?php esc_html_e('Key Benefits', 'contact-inbox'); ?></h4>
                    <ul>
                        <li><strong><?php esc_html_e('Real-time Sync:', 'contact-inbox'); ?></strong> <?php esc_html_e('Contacts appear in Salesforce immediately after form submission', 'contact-inbox'); ?></li>
                        <li><strong><?php esc_html_e('Automatic Name Splitting:', 'contact-inbox'); ?></strong> <?php esc_html_e('Names are intelligently split into FirstName and LastName', 'contact-inbox'); ?></li>
                        <li><strong><?php esc_html_e('Customizable Mapping:', 'contact-inbox'); ?></strong> <?php esc_html_e('Map form fields to any Salesforce Contact (or custom) field', 'contact-inbox'); ?></li>
                        <li><strong><?php esc_html_e('Queue & Retry Protection:', 'contact-inbox'); ?></strong> <?php esc_html_e('A background queue handles delivery, so users never see Salesforce errors and failures auto‑retry.', 'contact-inbox'); ?></li>
                        <li><strong><?php esc_html_e('Regional Support:', 'contact-inbox'); ?></strong> <?php esc_html_e('Supports both Western (First Last) and Asian (Last First) name formats', 'contact-inbox'); ?></li>
                    </ul>
                </div>
            </div>

            <!-- Salesforce Setup -->
            <div id="crm-setup" class="cin-help-section">
                <h3><?php esc_html_e('⚙️ Setting Up Salesforce', 'contact-inbox'); ?></h3>
                
                <div class="cin-help-item">
                    <h4><?php esc_html_e('Step 1: Create a Connected App', 'contact-inbox'); ?></h4>
                    <ol>
                        <li><?php esc_html_e('Log in to Salesforce as an Admin', 'contact-inbox'); ?></li>
                        <li><?php esc_html_e('Click the gear icon (⚙️) → Setup', 'contact-inbox'); ?></li>
                        <li><?php esc_html_e('Navigate to: Apps & Integrations → App Manager', 'contact-inbox'); ?></li>
                        <li><?php esc_html_e('Click "New Connected App"', 'contact-inbox'); ?></li>
                        <li><?php esc_html_e('Fill in the form:', 'contact-inbox'); ?>
                            <ul>
                                <li><strong><?php esc_html_e('App Name:', 'contact-inbox'); ?></strong> WordPress ContactForm</li>
                                <li><strong><?php esc_html_e('Enable OAuth:', 'contact-inbox'); ?></strong> Check this box</li>
                                <li>
                                    <strong><?php esc_html_e('Callback URL:', 'contact-inbox'); ?></strong>
                                    <?php
                                    printf(
                                        esc_html__('Use this exact callback URL: %s', 'contact-inbox'),
                                        esc_html($contactin_ajax_callback_url)
                                    );
                                    ?>
                                </li>
                                <li><strong><?php esc_html_e('Scopes:', 'contact-inbox'); ?></strong> Add: full, api, id, profile, email, address, phone</li>
                            </ul>
                        </li>
                        <li><?php esc_html_e('Click Save', 'contact-inbox'); ?></li>
                    </ol>
                </div>

                <div class="cin-help-item">
                    <h4><?php esc_html_e('Step 2: Get Your OAuth Credentials', 'contact-inbox'); ?></h4>
                    <ol>
                        <li><?php esc_html_e('Find your Created Connected App in the App Manager', 'contact-inbox'); ?></li>
                        <li><?php esc_html_e('Click on the app name', 'contact-inbox'); ?></li>
                        <li><?php esc_html_e('Scroll to "API (Enable OAuth Settings)"', 'contact-inbox'); ?></li>
                        <li><?php esc_html_e('Click "Reveal" next to Consumer Key and Consumer Secret', 'contact-inbox'); ?></li>
                        <li><?php esc_html_e('Save these credentials - you\'ll need them for authorization', 'contact-inbox'); ?></li>
                    </ol>
                </div>

                <div class="cin-help-item">
                    <h4><?php esc_html_e('Step 3: Capture Your Instance URL', 'contact-inbox'); ?></h4>
                    <ol>
                        <li><?php esc_html_e('From Setup, navigate to Company Information or copy the base URL from any Salesforce tab.', 'contact-inbox'); ?></li>
                        <li><?php esc_html_e('You only need the instance (e.g., https://acme-dev-ed.my.salesforce.com).', 'contact-inbox'); ?></li>
                        <li><?php esc_html_e('The plugin builds the REST endpoint automatically after OAuth completes—no need to paste API paths manually.', 'contact-inbox'); ?></li>
                    </ol>
                </div>
            </div>

            <!-- Plugin Configuration -->
            <div id="crm-config" class="cin-help-section">
                <h3><?php esc_html_e('🔧 Plugin Configuration', 'contact-inbox'); ?></h3>
                
                <div class="cin-help-item">
                    <h4><?php esc_html_e('Enable Integration', 'contact-inbox'); ?></h4>
                    <p><?php esc_html_e('Check "Enable Salesforce Integration" to activate contact syncing. When disabled, form submissions won\'t sync to Salesforce but will still be saved locally.', 'contact-inbox'); ?></p>
                </div>

                <div class="cin-help-item">
                    <h4><?php esc_html_e('Connected App Credentials', 'contact-inbox'); ?></h4>
                    <p><?php esc_html_e('Paste the Consumer Key and Consumer Secret from your Salesforce Connected App. These stay in WordPress and are used whenever you refresh the OAuth session.', 'contact-inbox'); ?></p>
                    <ul>
                        <li><?php esc_html_e('Keep credentials for Production and Sandbox separate to avoid cross‑posting data.', 'contact-inbox'); ?></li>
                        <li><?php esc_html_e('Rotate the Secret if someone leaves your team.', 'contact-inbox'); ?></li>
                    </ul>
                </div>

                <div class="cin-help-item">
                    <h4><?php esc_html_e('Callback URL Reminder', 'contact-inbox'); ?></h4>
                    <p>
                        <?php
                        printf(
                            esc_html__('Salesforce must be told where to send users after they approve the connection. Use the callback URL shown on the settings page (%s).', 'contact-inbox'),
                            esc_html($contactin_ajax_callback_url)
                        );
                        ?>
                    </p>
                    <p><?php esc_html_e('Paste that exact URL into your Connected App → OAuth Policies → Callback URLs.', 'contact-inbox'); ?></p>
                </div>

                <div class="cin-help-item">
                    <h4><?php esc_html_e('OAuth Authorization Flow', 'contact-inbox'); ?></h4>
                    <p><?php esc_html_e('Click "Connect to Salesforce" to start OAuth. After signing in and granting access, the plugin stores your refresh/access tokens, instance URL, org ID, and connected-app label automatically.', 'contact-inbox'); ?></p>
                    <ul>
                        <li><?php esc_html_e('Use the "Disconnect" button to revoke access or switch orgs.', 'contact-inbox'); ?></li>
                        <li><?php esc_html_e('Tokens are securely stored in the WordPress options table—no need to paste them manually.', 'contact-inbox'); ?></li>
                    </ul>
                </div>

                <div class="cin-help-item">
                    <h4><?php esc_html_e('Instance & Endpoint Auto-Detection', 'contact-inbox'); ?></h4>
                    <p><?php esc_html_e('Once OAuth succeeds, the plugin builds the correct REST endpoint for Contacts and remembers your Salesforce instance. You can view it under “Salesforce API Configuration”.', 'contact-inbox'); ?></p>
                    <ul>
                        <li><?php esc_html_e('No manual endpoint entries are required anymore.', 'contact-inbox'); ?></li>
                        <li><?php esc_html_e('If the instance changes (e.g., sandbox refresh), disconnect and reconnect.', 'contact-inbox'); ?></li>
                    </ul>
                </div>

                <div class="cin-help-item">
                    <h4><?php esc_html_e('Delivery Queue & Logs', 'contact-inbox'); ?></h4>
                    <p><?php esc_html_e('Submissions are inserted into the CRM queue and processed by the Queue Processor cron job. Review status, retries, and payloads under Analytics → Salesforce CRM tab or the dedicated CRM Log page.', 'contact-inbox'); ?></p>
                    <ul>
                        <li><?php esc_html_e('Use the new Operation filter on the CRM Log to isolate Contact vs Case attempts.', 'contact-inbox'); ?></li>
                        <li><?php esc_html_e('File attachments are queued separately with automatic retry logic and exponential backoff.', 'contact-inbox'); ?></li>
                        <li><?php esc_html_e('Manual CRM processing on the Maintenance page runs asynchronously with real-time progress indication.', 'contact-inbox'); ?></li>
                        <li><?php esc_html_e('If you pause the integration, queued items will resume once it is re-enabled.', 'contact-inbox'); ?></li>
                    </ul>
                </div>
            </div>

            <!-- Testing -->
            <div id="crm-testing" class="cin-help-section">
                <h3><?php esc_html_e('✅ Testing Your Integration', 'contact-inbox'); ?></h3>
                
                <div class="cin-help-item">
                    <h4><?php esc_html_e('Test Connection Button', 'contact-inbox'); ?></h4>
                    <p><?php esc_html_e('After saving your Connected App credentials and completing OAuth, click "Test Connection". This verifies:', 'contact-inbox'); ?></p>
                    <ul>
                        <li><?php esc_html_e('The stored refresh/access tokens are valid and not revoked', 'contact-inbox'); ?></li>
                        <li><?php esc_html_e('The detected instance URL is reachable from your WordPress host', 'contact-inbox'); ?></li>
                        <li><?php esc_html_e('Salesforce REST resources respond with the expected metadata (Contacts, Cases)', 'contact-inbox'); ?></li>
                    </ul>
                    <p><strong><?php esc_html_e('Success:', 'contact-inbox'); ?></strong> <span class="cin-help-status-success">✅ "Connection successful!"</span></p>
                    <p><strong><?php esc_html_e('Failure:', 'contact-inbox'); ?></strong> <?php esc_html_e('Check error message and review Troubleshooting section', 'contact-inbox'); ?></p>
                </div>

                <div class="cin-help-item">
                    <h4><?php esc_html_e('Test Form Submission', 'contact-inbox'); ?></h4>
                    <ol>
                        <li><?php esc_html_e('Save your plugin configuration first', 'contact-inbox'); ?></li>
                        <li><?php esc_html_e('Go to your website and find the contact form', 'contact-inbox'); ?></li>
                        <li><?php esc_html_e('Submit a test message with these details:', 'contact-inbox'); ?>
                            <ul>
                                <li><strong><?php esc_html_e('Name:', 'contact-inbox'); ?></strong> "John Doe" (must have 2+ words)</li>
                                <li><strong><?php esc_html_e('Email:', 'contact-inbox'); ?></strong> your-email@example.com</li>
                                <li><strong><?php esc_html_e('Phone:', 'contact-inbox'); ?></strong> +1-555-0123</li>
                                <li><strong><?php esc_html_e('Subject:', 'contact-inbox'); ?></strong> "Test Contact"</li>
                                <li><strong><?php esc_html_e('Message:', 'contact-inbox'); ?></strong> "Testing Salesforce integration"</li>
                            </ul>
                        </li>
                        <li><?php esc_html_e('Wait 10-30 seconds for async processing', 'contact-inbox'); ?></li>
                        <li><?php esc_html_e('Check Salesforce: Contacts & Cases tabs → Look for "John Doe" and the related Case', 'contact-inbox'); ?></li>
                        <li><?php esc_html_e('Verify Contact, Case, and custom field mappings are populated correctly', 'contact-inbox'); ?></li>
                    </ol>
                </div>
            </div>

            <!-- Field Mapping -->
            <div id="crm-fields" class="cin-help-section">
                <h3><?php esc_html_e('🔀 Field Mapping Reference', 'contact-inbox'); ?></h3>
                
                <div class="cin-help-item">
                    <h4><?php esc_html_e('Default Field Mappings', 'contact-inbox'); ?></h4>
                    <table class="cin-help-table">
                        <tr>
                            <th><?php esc_html_e('Form Field', 'contact-inbox'); ?></th>
                            <th><?php esc_html_e('Salesforce Field', 'contact-inbox'); ?></th>
                            <th><?php esc_html_e('Description', 'contact-inbox'); ?></th>
                        </tr>
                        <tr>
                            <td><strong><?php esc_html_e('Email', 'contact-inbox'); ?></strong></td>
                            <td><code>Email</code></td>
                            <td><?php esc_html_e('Contact email address', 'contact-inbox'); ?></td>
                        </tr>
                        <tr>
                            <td><strong><?php esc_html_e('Phone', 'contact-inbox'); ?></strong></td>
                            <td><code>Phone</code></td>
                            <td><?php esc_html_e('Contact phone number', 'contact-inbox'); ?></td>
                        </tr>
                        <tr>
                            <td><strong><?php esc_html_e('Subject', 'contact-inbox'); ?></strong></td>
                            <td><code>Title</code></td>
                            <td><?php esc_html_e('Contact title/position', 'contact-inbox'); ?></td>
                        </tr>
                        <tr>
                            <td><strong><?php esc_html_e('Message', 'contact-inbox'); ?></strong></td>
                            <td><code>Description</code></td>
                            <td><?php esc_html_e('Contact notes/description', 'contact-inbox'); ?></td>
                        </tr>
                        <tr>
                            <td><strong><?php esc_html_e('Name', 'contact-inbox'); ?></strong></td>
                            <td><code>FirstName, LastName</code></td>
                            <td><?php esc_html_e('Auto-split based on regional format', 'contact-inbox'); ?></td>
                        </tr>
                    </table>
                </div>

                <div class="cin-help-item">
                    <h4><?php esc_html_e('Regional Name Format', 'contact-inbox'); ?></h4>
                    <p><?php esc_html_e('The plugin automatically splits contact names into FirstName and LastName based on your selected format:', 'contact-inbox'); ?></p>
                    <ul>
                        <li><strong><?php esc_html_e('Western (First Last):', 'contact-inbox'); ?></strong> "John Doe" → FirstName="John", LastName="Doe"</li>
                        <li><strong><?php esc_html_e('Asian & Other (Last First):', 'contact-inbox'); ?></strong> "李 明" → FirstName="明", LastName="李"</li>
                    </ul>
                    <p><?php esc_html_e('Note: Contact names must contain at least 2 words to be split correctly.', 'contact-inbox'); ?></p>
                </div>

                <div class="cin-help-item">
                    <h4><?php esc_html_e('Custom Salesforce Fields', 'contact-inbox'); ?></h4>
                    <p><?php esc_html_e('If you have custom fields in Salesforce, you can map to them. Enter the field\'s API name (usually ends with __c):', 'contact-inbox'); ?></p>
                    <ul>
                        <li><?php esc_html_e('Example: Email → <code>Custom_Email__c</code>', 'contact-inbox'); ?></li>
                        <li><?php esc_html_e('Example: Subject → <code>Request_Type__c</code>', 'contact-inbox'); ?></li>
                    </ul>
                </div>
            </div>

            <!-- Troubleshooting -->
            <div id="crm-troubleshoot" class="cin-help-section">
                <h3><?php esc_html_e('🔍 Troubleshooting', 'contact-inbox'); ?></h3>
                
                <div class="cin-help-item">
                    <h4><?php esc_html_e('❌ "Connection failed - Invalid session"', 'contact-inbox'); ?></h4>
                    <p><strong><?php esc_html_e('Cause:', 'contact-inbox'); ?></strong> <?php esc_html_e('The stored OAuth refresh token was revoked, expired, or tied to a different org.', 'contact-inbox'); ?></p>
                    <p><strong><?php esc_html_e('Solution:', 'contact-inbox'); ?></strong></p>
                    <ol>
                        <li><?php esc_html_e('Click "Connect to Salesforce" and sign in again with the correct user/org.', 'contact-inbox'); ?></li>
                        <li><?php esc_html_e('Verify the Connected App still has the required OAuth scopes (full, api, id, profile, email).', 'contact-inbox'); ?></li>
                        <li><?php esc_html_e('If the user was deactivated, reconnect using an active integration user.', 'contact-inbox'); ?></li>
                    </ol>
                </div>

                <div class="cin-help-item">
                    <h4><?php esc_html_e('❌ "Connection failed - 401 Unauthorized"', 'contact-inbox'); ?></h4>
                    <p><strong><?php esc_html_e('Cause:', 'contact-inbox'); ?></strong> <?php esc_html_e('Consumer Key/Secret mismatch or the OAuth client no longer has API access.', 'contact-inbox'); ?></p>
                    <p><strong><?php esc_html_e('Solution:', 'contact-inbox'); ?></strong></p>
                    <ol>
                        <li><?php esc_html_e('Confirm the Consumer Key and Secret in WordPress match the Connected App.', 'contact-inbox'); ?></li>
                        <li><?php esc_html_e('Ensure the Salesforce user profile has the "API Enabled" permission.', 'contact-inbox'); ?></li>
                        <li><?php esc_html_e('Reconnect via OAuth and repeat the Test Connection check.', 'contact-inbox'); ?></li>
                    </ol>
                </div>

                <div class="cin-help-item">
                    <h4><?php esc_html_e('❌ "Connection failed - 404 Not Found"', 'contact-inbox'); ?></h4>
                    <p><strong><?php esc_html_e('Cause:', 'contact-inbox'); ?></strong> <?php esc_html_e('Salesforce moved your org to a different instance or the target object (Contact/Case) is unavailable for the integration user.', 'contact-inbox'); ?></p>
                    <p><strong><?php esc_html_e('Solution:', 'contact-inbox'); ?></strong></p>
                    <ol>
                        <li><?php esc_html_e('Disconnect and reconnect so the plugin can detect the new instance URL.', 'contact-inbox'); ?></li>
                        <li><?php esc_html_e('Verify the integration user can access the Contact and Case objects via API.', 'contact-inbox'); ?></li>
                        <li><?php esc_html_e('Confirm the Connected App has not restricted IP ranges or login hours.', 'contact-inbox'); ?></li>
                    </ol>
                </div>

                <div class="cin-help-item">
                    <h4><?php esc_html_e('❌ "Contacts not syncing to Salesforce"', 'contact-inbox'); ?></h4>
                    <p><strong><?php esc_html_e('Cause:', 'contact-inbox'); ?></strong> <?php esc_html_e('Multiple possible reasons', 'contact-inbox'); ?></p>
                    <p><strong><?php esc_html_e('Solution:', 'contact-inbox'); ?></strong></p>
                    <ol>
                        <li><?php esc_html_e('Verify "Enable Salesforce Integration" is checked', 'contact-inbox'); ?></li>
                        <li><?php esc_html_e('Open Analytics → Salesforce CRM or the CRM Log page to ensure queue items are processing (Status = Delivered)', 'contact-inbox'); ?></li>
                        <li><?php esc_html_e('Run the built-in Queue Processor cron job manually if you use basic hosting without WP-Cron traffic.', 'contact-inbox'); ?></li>
                        <li><?php esc_html_e('Check that contact names contain at least 2 words for proper splitting.', 'contact-inbox'); ?></li>
                        <li><?php esc_html_e('Search Salesforce by email address instead of name to avoid duplicate matches.', 'contact-inbox'); ?></li>
                        <li><?php esc_html_e('If entries remain in "Failed" status, open the log to view Salesforce error responses.', 'contact-inbox'); ?></li>
                    </ol>
                </div>

                <div class="cin-help-item">
                    <h4><?php esc_html_e('❌ "Contact name not splitting correctly"', 'contact-inbox'); ?></h4>
                    <p><strong><?php esc_html_e('Cause:', 'contact-inbox'); ?></strong> <?php esc_html_e('Name doesn\'t have enough words or wrong regional format', 'contact-inbox'); ?></p>
                    <p><strong><?php esc_html_e('Solution:', 'contact-inbox'); ?></strong></p>
                    <ol>
                        <li><?php esc_html_e('Ensure name has at least 2 words (e.g., "John Doe", not just "John")', 'contact-inbox'); ?></li>
                        <li><?php esc_html_e('Check "Regional Name Format" matches your name format', 'contact-inbox'); ?></li>
                        <li><?php esc_html_e('Test with a simple name like "John Doe"', 'contact-inbox'); ?></li>
                    </ol>
                </div>
            </div>

            <!-- Security Notice -->
            <div class="cin-help-item cin-help-highlight">
                <h4><?php esc_html_e('🔒 Security Best Practices', 'contact-inbox'); ?></h4>
                <ul>
                    <li><?php esc_html_e('Store the Consumer Key/Secret in a password manager—treat them like API keys.', 'contact-inbox'); ?></li>
                    <li><?php esc_html_e('Limit OAuth scopes to the ones listed here and review them quarterly.', 'contact-inbox'); ?></li>
                    <li><?php esc_html_e('Rotate the Consumer Secret and reconnect when admins leave your organization.', 'contact-inbox'); ?></li>
                    <li><?php esc_html_e('Use separate Connected Apps (and integration users) for Development, Staging, and Production.', 'contact-inbox'); ?></li>
                    <li><?php esc_html_e('Monitor Salesforce Setup → Security → Connected Apps OAuth Usage for unexpected logins.', 'contact-inbox'); ?></li>
                </ul>
            </div>
        </div>

        <div class="cin-modal-footer">
            <button type="button" class="button button-primary cin-modal-dismiss">
                <?php esc_html_e('Close', 'contact-inbox'); ?>
            </button>
        </div>
    </div>
</div>
