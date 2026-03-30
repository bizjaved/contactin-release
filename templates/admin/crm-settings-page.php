<?php
if (!defined('ABSPATH')) exit;
/**
 * Template: CRM Settings Page
 *
 * @var array $settings Current CRM settings passed from the controller.
 */

use ContactInbox\Core\Config;
use ContactInbox\Core\Settings;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// phpcs:disable WordPress.WP.I18n.NonSingularStringLiteralDomain, WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound, WordPress.Security.NonceVerification.Recommended, WordPress.Security.ValidatedSanitizedInput.MissingUnslash, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized

// Define default field mappings per CRM type
$crm_defaults = [
    'salesforce' => [
        'email'   => 'Email',
        'phone'   => 'Phone',
        'subject' => 'Subject',  // ✅ Correct: Salesforce Case uses 'Subject' field
        'message' => 'Description',
        'salutation' => 'Salutation',
        'source' => 'Web',
        'intent_category' => '',  // ✅ Custom field - leave empty by default
        'intent_confidence' => '',  // ✅ Custom field - leave empty by default
    ],
    'hubspot' => [
        'email'   => 'email',
        'phone'   => 'phone',
        'subject' => 'hs_lead_status',
        'message' => 'notes_next_activity',
        'salutation' => '',
        'source' => '',
        'intent_category' => '',
        'intent_confidence' => '',
    ],
    'zoho' => [
        'email'   => 'Email',
        'phone'   => 'Phone',
        'subject' => 'Subject',
        'message' => 'Description',
        'salutation' => '',
        'source' => '',
        'intent_category' => '',
        'intent_confidence' => '',
    ],
    'custom' => [
        'email'   => '',
        'phone'   => '',
        'subject' => '',
        'message' => '',
        'salutation' => '',
        'source' => '',
        'intent_category' => '',
        'intent_confidence' => '',
    ],
];

$current_crm = $settings['crm_type'] ?? 'salesforce';
$defaults = $crm_defaults[$current_crm] ?? $crm_defaults['salesforce'];
$core_settings = Settings::get_settings();

// Check the active tab
$active_tab = $_GET['tab'] ?? 'salesforce';
?>

<div class="wrap">
    <?php if ($active_tab === 'salesforce') : ?>
        <div class="crm-page-header">
            <h1><?php esc_html_e('Salesforce Integration',  'contactin'); ?></h1>
            <button type="button" class="button button-secondary"
                    data-cin-help-open="cin-crm-help-modal"
                    aria-haspopup="dialog"
                    aria-controls="cin-crm-help-modal">
                <span class="crm-help-icon">ℹ️</span><?php esc_html_e('Help',  'contactin'); ?>
            </button>
        </div>

        <!-- Global CRM Settings Notice Area -->
        <div id="cin-crm-settings-notice" class="notice cin-hidden">
            <button type="button" class="notice-dismiss cin-notice-dismiss" aria-label="<?php esc_attr_e('Dismiss this notice.',  'contactin'); ?>">
                <span class="screen-reader-text"><?php esc_html_e('Dismiss this notice.',  'contactin'); ?></span>
            </button>
            <p id="cin-crm-notice-message"></p>
        </div>

        <!-- Dynamic GDPR warning shown only when disabling delete sync -->
        <div id="cin-gdpr-deletion-warning" class="notice notice-warning" style="display: none;">
            <button type="button" class="notice-dismiss" onclick="this.parentElement.style.display='none';">
                <span class="screen-reader-text"><?php esc_html_e('Dismiss this notice.',  'contactin'); ?></span>
            </button>
            <p>
                <strong><?php esc_html_e('⚠️ GDPR Compliance Warning:',  'contactin'); ?></strong>
                <?php esc_html_e('CRM deletion sync has been disabled. Data synced to Salesforce must now be deleted manually when processing GDPR deletion requests. Check the GDPR Deletion Log for items marked "manual_required".',  'contactin'); ?>
            </p>
        </div>

        <form method="post" id="crm-settings-form">
            <input type="hidden" name="action" value="ci_save_crm_settings">
            <?php wp_nonce_field(Config::CRM_SETTINGS_NONCE_ACTION, 'nonce'); ?>

            <div class="crm-settings-container">
                <div class="crm-settings-grid">

                    <!-- LEFT COLUMN -->
                    <div class="crm-left-column">

                    <!-- INTEGRATION ACTIVATION -->                   
                    <div class="contactin-status-row">
                        <div class="inner-flex cin-flex-between">
                            <div class="status-left">
                                <span id="cin-crm-status-label" class="cin-status-label <?php echo !empty($settings['crm_enabled']) ? 'enabled' : 'disabled'; ?>">
                                    <?php echo !empty($settings['crm_enabled']) ? esc_html__('Service Enabled',  'contactin') : esc_html__('Service Disabled',  'contactin'); ?>
                                </span>
                            </div>
                            <div class="status-right">
                                <button type="button" id="cin-toggle-crm-service" class="button button-small<?php echo !empty($settings['crm_enabled']) ? ' enabled' : ''; ?>" data-enabled="<?php echo !empty($settings['crm_enabled']) ? '1' : '0'; ?>">
                                <?php echo !empty($settings['crm_enabled']) ? esc_html__('Disable Salesforce Sync',  'contactin') : esc_html__('Enable Salesforce Sync',  'contactin'); ?>
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- NAME FIELD CONFIGURATION -->
                    <h2><?php esc_html_e('Name Field Configuration',  'contactin'); ?></h2>

                    <table class="form-table" role="presentation">
                        <tr>
                            <th scope="row">
                                <label for="name_field_order"><?php esc_html_e('Regional Name Format',  'contactin'); ?></label>
                            </th>
                            <td>
                                <select id="name_field_order" name="<?php echo esc_attr(Config::OPTION_CRM); ?>[name_field_order]">
                                    <option value="first_last" <?php selected($settings['name_field_order'] ?? 'first_last', 'first_last'); ?>>
                                        <?php esc_html_e('First Name, Last Name (Western)',  'contactin'); ?>
                                    </option>
                                    <option value="last_first" <?php selected($settings['name_field_order'] ?? 'first_last', 'last_first'); ?>>
                                        <?php esc_html_e('Last Name, First Name (Asian & Other)',  'contactin'); ?>
                                    </option>
                                </select>
                                <p class="description">
                                    <strong><?php esc_html_e('Examples:',  'contactin'); ?></strong><br>
                                    <?php esc_html_e('Western: "John Doe" → FirstName="John", LastName="Doe"',  'contactin'); ?><br>
                                    <?php esc_html_e('Asian: "李 明" → FirstName="明", LastName="李"',  'contactin'); ?>
                                </p>
                            </td>
                        </tr>
                    </table>
            <div class="warning-box">
                <p>
                    <strong><?php esc_html_e('Important:',  'contactin'); ?></strong>
                    <?php esc_html_e('Contact names must contain at least 2 words (e.g., "John Doe"). The plugin automatically splits names into FirstName and LastName.',  'contactin'); ?>
                </p>
            </div>

                    <!-- CONTACT FIELDS MAPPING -->
                    <h2><?php esc_html_e('Contact Fields (Person Information)',  'contactin'); ?></h2>
                    <p><?php esc_html_e('Map form fields to Salesforce Contact object. Contacts are upserted by email (create new or update existing).',  'contactin'); ?></p>

                    <table class="crm-mapping-table">
                        <thead>
                            <tr>
                                <th><?php esc_html_e('Plugin Field',  'contactin'); ?></th>
                                <th><?php esc_html_e('Salesforce Field',  'contactin'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            $plugin_fields = ['email', 'phone', 'subject', 'message', 'salutation', 'source', 'intent_category', 'intent_confidence'];
                            $plugin_labels = [
                                'email'   => 'Email Address',
                                'phone'   => 'Phone Number',
                                'subject' => 'Subject/Title',
                                'message' => 'Message/Description',
                                'salutation' => 'Salutation',
                                'source' => 'Source',
                                'intent_category' => 'Intent Category',
                                'intent_confidence' => 'Intent Confidence',
                            ];
                            
                            foreach ($plugin_fields as $field) :
                                $current_value = $settings['mapping'][$field] ?? '';
                                $default_value = $defaults[$field] ?? '';
                                $display_value = !empty($current_value) ? $current_value : $default_value;
                                $is_intent_field = in_array($field, ['intent_category', 'intent_confidence']);
                            ?>
                                <tr>
                                    <td><strong><?php echo esc_html($plugin_labels[$field] ?? ucfirst($field)); ?></strong></td>
                                    <td>
                                        <div class="crm-field-mapping">
                                            <input type="text"
                                                   id="crm_mapping_<?php echo esc_attr($field); ?>"
                                                   name="<?php echo esc_attr(Config::OPTION_CRM); ?>[mapping][<?php echo esc_attr($field); ?>]"
                                                   value="<?php echo esc_attr($display_value); ?>"
                                                   class="regular-text crm-field-input crm-half-width"
                                                   placeholder="<?php echo esc_attr($default_value ?: __('Optional - leave empty if field doesn\'t exist',  'contactin')); ?>" />
                                            <?php if ($is_intent_field): ?>
                                                <p class="description cin-text-muted">
                                                    <?php esc_html_e('Optional: Only fill if this custom field exists in your Salesforce org.',  'contactin'); ?>
                                                </p>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>

                    <!-- ATTACHMENT SYNC -->
                    <h2><?php esc_html_e('File Attachment Sync',  'contactin'); ?></h2>
                    <table class="form-table" role="presentation">
                        <tr>
                            <th scope="row">
                                <label for="attachment-sync-enable">
                                    <?php esc_html_e('Sync Attachments',  'contactin'); ?>
                                </label>
                            </th>
                            <td>
                                <label class="cin-inline-checkbox">
                                    <input type="checkbox"
                                           id="attachment-sync-enable"
                                           name="<?php echo esc_attr(Config::OPTION_CRM); ?>[attachment_sync]"
                                           value="1"
                                           <?php checked(!empty($settings['attachment_sync']), true); ?> />
                                    <strong><?php esc_html_e('Upload form attachments to Salesforce',  'contactin'); ?></strong>
                                </label>
                                <p class="description">
                                    <?php esc_html_e('When enabled, files uploaded with submissions are queued and attached to the Salesforce Case/Task record.',  'contactin'); ?>
                                </p>
                            </td>
                        </tr>
                    </table>

                    <!-- CRM DELETION SYNC -->
                    <h2><?php esc_html_e('CRM Deletion Sync',  'contactin'); ?></h2>
                    <table class="form-table" role="presentation">
                        <tr>
                            <th scope="row">
                                <label for="crm-delete-sync-enable">
                                    <?php esc_html_e('Sync Deletions',  'contactin'); ?>
                                </label>
                            </th>
                            <td>
                                <label class="cin-inline-checkbox cin-deletion-sync-label" style="position: relative;">
                                    <input type="checkbox"
                                           id="crm-delete-sync-enable"
                                           name="<?php echo esc_attr(Config::OPTION_CRM); ?>[crm_delete_sync]"
                                           value="1"
                                           <?php checked(!empty($settings['crm_delete_sync']), true); ?>
                                           data-initial-value="<?php echo !empty($settings['crm_delete_sync']) ? '1' : '0'; ?>" />
                                    <?php esc_html_e('Delete CRM records when contacts are deleted',  'contactin'); ?>
                                    
                                    <!-- Hover Tooltip Modal -->
                                    <div class="cin-deletion-caution-tooltip" role="tooltip">
                                        <div class="cin-tooltip-arrow"></div>
                                        <div class="cin-tooltip-content">
                                            <strong style="color: #d63638; display: block; margin-bottom: 8px;">⚠️ <?php esc_html_e('Caution:',  'contactin'); ?></strong>
                                            <p style="margin: 0; line-height: 1.5;">
                                                <?php esc_html_e('If you disable this option, you will be responsible for manually deleting synced contacts from Salesforce when processing GDPR deletion requests. All such deletions will be logged in the GDPR Deletion Log with "manual_required" status.',  'contactin'); ?>
                                            </p>
                                        </div>
                                    </div>
                                </label>
                                <p class="description">
                                    <?php esc_html_e('When enabled, GDPR deletions will also delete related Salesforce records (Contact and restricted child records).',  'contactin'); ?>
                                </p>
                            </td>
                        </tr>
                    </table>

                    <!-- INTENT CATEGORY OPTIONS -->
                    <h2><?php esc_html_e('Intent Classification Options',  'contactin'); ?></h2>
                    <table class="form-table" role="presentation">
                        <tr>
                            <th scope="row">
                                <label for="prepend-intent-to-subject">
                                    <?php esc_html_e('Include Intent in Subject',  'contactin'); ?>
                                </label>
                            </th>
                            <td>
                                <label class="cin-inline-checkbox">
                                    <input type="checkbox"
                                           id="prepend-intent-to-subject"
                                           name="<?php echo esc_attr(Config::OPTION_CRM); ?>[prepend_intent_to_subject]"
                                           value="1"
                                           <?php checked(!empty($settings['prepend_intent_to_subject']), true); ?> />
                                    <strong><?php esc_html_e('Prepend intent category to Case/Task subject',  'contactin'); ?></strong>
                                </label>
                                <p class="description">
                                    <?php esc_html_e('Example: "Looking to buy a private jet" becomes "Sales: Looking to buy a private jet"',  'contactin'); ?><br>
                                    <?php esc_html_e('Useful when you don\'t have custom intent fields and want to see classification in the subject line.',  'contactin'); ?>
                                </p>
                            </td>
                        </tr>
                    </table>

                    <div class="cin-button-section">
                        <div class="cin-button-wrapper">
                            <button type="submit" id="crm-save-btn" class="button button-primary button-large">
                                <span id="crm-save-btn-text"><?php esc_html_e('Save Integration Settings',  'contactin'); ?></span>
                            </button>
                            <div class="cin-progress-bar cin-hidden">
                                <div class="cin-progress-fill"></div>
                            </div>
                        </div>
                    </div>

                </div>

                <!-- RIGHT COLUMN -->
                <div class="crm-right-column">

                    <!-- OAUTH CLIENT CONFIGURATION -->
                    <h2><?php esc_html_e('Salesforce OAuth Configuration',  'contactin'); ?></h2>
                    <p><?php esc_html_e('Enter your Salesforce Connected App OAuth credentials.',  'contactin'); ?></p>

                    <!-- CALLBACK URL -->
                    <div class="notice notice-info inline cin-my-md cin-border-left-primary cin-p-lg">
                        <p>
                            <strong><?php esc_html_e('🔗 Callback URL (Redirect URI)',  'contactin'); ?></strong><br>
                            <?php esc_html_e('When setting up your Salesforce Connected App, use this callback URL as your Redirect URI:',  'contactin'); ?><br><br>
                            <code class="crm-callback-url cin-code-block">
                                <?php echo esc_html(admin_url('admin-ajax.php')); ?>
                            </code>
                        </p>
                        <p class="cin-mt-md cin-font-sm">
                            <button type="button" class="button button-small" onclick="copyToClipboard(this, '<?php echo esc_attr(admin_url('admin-ajax.php')); ?>')">
                                <?php esc_html_e('Copy URL',  'contactin'); ?>
                            </button>
                        </p>
                    </div>

                    <table class="form-table" role="presentation">
                        <tr>
                            <th scope="row">
                                <label for="salesforce_consumer_key"><?php esc_html_e('Consumer Key',  'contactin'); ?></label>
                            </th>
                            <td>
                                <input type="text" 
                                       id="salesforce_consumer_key" 
                                       name="<?php echo esc_attr(Config::OPTION_CRM); ?>[salesforce_consumer_key]" 
                                       value="<?php echo esc_attr($settings['salesforce_consumer_key'] ?? ''); ?>" 
                                       class="regular-text"
                                       placeholder="3MVG9Tz8_EV3sSHg..." />
                                <p class="description">
                                    <?php esc_html_e('OAuth Client ID from your Salesforce Connected App (Setup → Apps → App Manager → OAuth Settings).',  'contactin'); ?>
                                </p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">
                                <label for="salesforce_consumer_secret"><?php esc_html_e('Consumer Secret',  'contactin'); ?></label>
                            </th>
                            <td>
                                <input type="password" 
                                       id="salesforce_consumer_secret" 
                                       name="<?php echo esc_attr(Config::OPTION_CRM); ?>[salesforce_consumer_secret]" 
                                       value="<?php echo esc_attr($settings['salesforce_consumer_secret'] ?? ''); ?>" 
                                       class="regular-text"
                                       placeholder="••••••••••••••••" />
                                <p class="description">
                                    <?php esc_html_e('OAuth Client Secret (optional, but recommended for enhanced security).',  'contactin'); ?>
                                </p>
                            </td>
                        </tr>
                    </table>

                    <!-- OAUTH CONNECTION -->
                    <h2><?php esc_html_e('Salesforce OAuth Connection',  'contactin'); ?></h2>
                    
                    <?php if (!empty($settings['oauth_enabled']) && !empty($settings['auth_token'])): ?>
                        <div class="notice notice-success inline cin-border-left-success cin-p-lg">
                            <p class="cin-flex-center cin-m-0">
                                <span class="cin-mr-md cin-success-icon">✓</span>
                                <strong><?php esc_html_e('Connected to Salesforce',  'contactin'); ?></strong>
                            </p>
                        </div>
                        <table class="form-table" role="presentation" cin-mt-lg>
                            <?php if (!empty($settings['instance_url'])): ?>
                                <tr>
                                    <th scope="row"><?php esc_html_e('Instance',  'contactin'); ?></th>
                                    <td>
                                        <code class="cin-code-inline cin-p-sm">
                                            <?php echo esc_html($settings['instance_url']); ?>
                                        </code>
                                    </td>
                                </tr>
                            <?php endif; ?>
                            <?php if (!empty($settings['connected_app_name'])): ?>
                                <tr>
                                    <th scope="row"><?php esc_html_e('Connected App',  'contactin'); ?></th>
                                    <td>
                                        <strong><?php echo esc_html($settings['connected_app_name']); ?></strong>
                                    </td>
                                </tr>
                            <?php endif; ?>
                            <?php if (!empty($settings['org_id'])): ?>
                                <tr>
                                    <th scope="row"><?php esc_html_e('Organization ID',  'contactin'); ?></th>
                                    <td>
                                        <code class="cin-code-inline cin-p-sm">
                                            <?php echo esc_html($settings['org_id']); ?>
                                        </code>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </table>
                        <p class="cin-mt-lg">
                            <button type="button" id="cin-disconnect-crm-btn" class="button button-secondary">
                                <?php esc_html_e('Disconnect from Salesforce',  'contactin'); ?>
                            </button>
                        </p>
                    <?php else: ?>
                        <div class="notice notice-warning inline cin-border-left-warning cin-p-lg cin-mb-lg">
                            <p class="cin-m-0">
                                <?php esc_html_e('Your Salesforce account is not connected. Click the button below to authorize this plugin using OAuth 2.0.',  'contactin'); ?>
                            </p>
                        </div>
                        <p>
                            <button type="button" id="cin-connect-crm-btn" class="button button-primary button-large">
                                <span class="cin-mr-md">→</span><?php esc_html_e('Connect to Salesforce',  'contactin'); ?>
                            </button>
                        </p>
                        <?php if (isset($_GET['oauth']) && $_GET['oauth'] === 'success'): ?>
                            <div class="notice notice-success inline cin-mt-lg">
                                <p><?php esc_html_e('✓ Successfully connected to Salesforce!',  'contactin'); ?></p>
                            </div>
                        <?php endif; ?>
                    <?php endif; ?>

                    <!-- SALESFORCE API ENDPOINT (Auto-configured) -->
                    <?php if (!empty($settings['oauth_enabled']) && !empty($settings['endpoint'])): ?>
                        <h2><?php esc_html_e('Salesforce API Configuration',  'contactin'); ?></h2>
                        <table class="form-table" role="presentation">
                            <tr>
                                <th scope="row">
                                    <label><?php esc_html_e('API Endpoint',  'contactin'); ?></label>
                                </th>
                                <td>
                                    <code class="cin-endpoint-code">
                                        <?php echo esc_html($settings['endpoint']); ?>
                                    </code>
                                    <p class="description">
                                        <?php esc_html_e('Automatically configured from OAuth connection. This endpoint is used to create Salesforce Contacts.',  'contactin'); ?>
                                    </p>
                                </td>
                            </tr>
                        </table>
                    <?php endif; ?>

                    <!-- FIELD MAPPING -->
                    <h2><?php esc_html_e('CRM Field Mapping Strategy',  'contactin'); ?></h2>
                    
                    <div class="notice notice-info inline cin-my-md">
                        <p>
                            <strong><?php esc_html_e('🎯 Two-Object Architecture',  'contactin'); ?></strong><br>
                            <?php esc_html_e('For optimal CRM data management, submissions are synced as:',  'contactin'); ?><br>
                            • <strong><?php esc_html_e('Contact',  'contactin'); ?></strong> - <?php esc_html_e('Person information (upserted by email - no duplicates)',  'contactin'); ?><br>
                            • <strong><?php esc_html_e('Case/Task',  'contactin'); ?></strong> - <?php esc_html_e('Inquiry/message (new record each time, linked to Contact)',  'contactin'); ?>
                        </p>
                    </div>

                    <!-- SALESFORCE FIELD REFERENCE -->
                    <h2><?php esc_html_e('Salesforce Contact Fields Reference',  'contactin'); ?></h2>
                    <div class="settings-table">
                        <p><?php esc_html_e('Default Contact mapping (used when no custom mapping is provided). Contacts are upserted by Email to avoid duplicates.',  'contactin'); ?></p>
                        <table>
                            <tr>
                                <th><?php esc_html_e('Plugin Field',  'contactin'); ?></th>
                                <th><?php esc_html_e('Salesforce Contact Field',  'contactin'); ?></th>
                            </tr>
                            <tr>
                                <td><strong><?php esc_html_e('Name',  'contactin'); ?></strong></td>
                                <td><code>FirstName</code>, <code>LastName</code> (<?php esc_html_e('auto-split',  'contactin'); ?>)</td>
                            </tr>
                            <tr>
                                <td><strong><?php esc_html_e('Email',  'contactin'); ?></strong></td>
                                <td><code>Email</code> (<?php esc_html_e('external ID for upsert',  'contactin'); ?>)</td>
                            </tr>
                            <tr>
                                <td><strong><?php esc_html_e('Phone',  'contactin'); ?></strong></td>
                                <td><code>Phone</code></td>
                            </tr>
                        </table>
                        <p class="cin-mt-md">
                            <?php esc_html_e('Inquiry details (Subject, Message) are stored on Case/Task and linked to the Contact.',  'contactin'); ?><br>
                            <?php esc_html_e('Defaults: Subject → Subject, Message → Description on the chosen Case/Task object.',  'contactin'); ?><br>
                            <strong><?php esc_html_e('Optional Custom Fields:',  'contactin'); ?></strong> <?php esc_html_e('If your Salesforce instance has custom intent fields (Message_Intent__c, Intent_Confidence__c), you can map them above. Leave empty if these fields don\'t exist in your org.',  'contactin'); ?>
                        </p>
                    </div>

                </div>

            </div>

            </div>

        </form>
    <?php else : ?>
        <h1><?php esc_html_e('Integration Coming Soon',  'contactin'); ?></h1>
        <p><?php esc_html_e('This CRM integration is under development and will be available soon.',  'contactin'); ?></p>
    <?php endif; ?>
</div>

<style>
    /* Hidden utility class */
    .cin-hidden {
        display: none !important;
    }
    
    /* Field Mapping Helper Text */
    .crm-field-mapping {
        display: block;
        width: 100%;
    }
    
    .crm-field-mapping .description {
        display: block !important;
        margin-top: 8px !important;
        margin-bottom: 0 !important;
        font-size: 13px !important;
        font-style: italic;
        line-height: 1.5;
        visibility: visible !important;
    }
    
    .crm-field-mapping .cin-text-muted {
        color: #646970 !important;
        opacity: 1 !important;
    }
    
    /* CRM Save Button States */
    #crm-save-btn {
        transition: all 0.3s ease;
    }
    
    /* Button Section Divider */
    .cin-button-section {
        margin-top: 30px;
        padding-top: 15px;
        border-top: 1px solid #ccc;
    }
    
    /* Button Wrapper with Progress Bar */
    .cin-button-wrapper {
        position: relative;
        display: inline-block;
    }
    
    /* Progress Bar Container */
    .cin-progress-bar {
        position: absolute;
        bottom: -8px;
        left: 0;
        right: 0;
        height: 4px;
        background-color: rgba(0, 115, 170, 0.1);
        border-radius: 2px;
        overflow: hidden;
    }
    
    /* Progress Bar Fill */
    .cin-progress-fill {
        height: 100%;
        width: 0;
        background: linear-gradient(90deg, #0073aa, #00a0d2);
        border-radius: 2px;
        transition: width 0.5s ease;
        position: relative;
        overflow: hidden;
    }
    
    /* Animated shimmer effect */
    .cin-progress-fill::after {
        content: '';
        position: absolute;
        top: 0;
        left: -100%;
        width: 100%;
        height: 100%;
        background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.4), transparent);
        animation: shimmer 1.5s infinite;
    }
    
    @keyframes shimmer {
        to { left: 100%; }
    }
    
    /* Success Checkmark Icon */
    .cin-success-icon {
        font-size: 20px;
    }
    
    /* Endpoint Code Display */
    .cin-endpoint-code {
        display: block;
        padding: 8px;
        background: #f0f0f1;
        border-radius: 3px;
    }
    
    /* Loading State */
    #crm-save-btn.cin-btn-loading {
        background-color: #f0f6fc;
        border-color: #0073aa;
        color: #0073aa;
        padding-left: 30px;
        position: relative;
    }
    
    #crm-save-btn.cin-btn-loading::before {
        content: '';
        position: absolute;
        left: 10px;
        top: 50%;
        transform: translateY(-50%);
        display: inline-block;
        width: 14px;
        height: 14px;
        border: 2px solid rgba(0, 115, 170, 0.3);
        border-top-color: #0073aa;
        border-radius: 50%;
        animation: spin 0.6s linear infinite;
    }
    
    /* Success State */
    #crm-save-btn.cin-btn-success {
        background-color: #f0f6f0;
        border-color: #00a32a;
        color: #00a32a;
        padding-left: 30px;
        position: relative;
    }
    
    #crm-save-btn.cin-btn-success::before {
        content: '✓';
        position: absolute;
        left: 10px;
        top: 50%;
        transform: translateY(-50%);
        font-weight: bold;
        font-size: 16px;
    }
    
    /* Error State */
    #crm-save-btn.cin-btn-error {
        background-color: #fdeef0;
        border-color: #dc3545;
        color: #dc3545;
        padding-left: 30px;
        position: relative;
    }
    
    #crm-save-btn.cin-btn-error::before {
        content: '✕';
        position: absolute;
        left: 10px;
        top: 50%;
        transform: translateY(-50%);
        font-weight: bold;
        font-size: 16px;
    }
    
    #crm-save-btn:disabled {
        opacity: 1;
    }
    
    @keyframes spin {
        to { transform: translateY(-50%) rotate(360deg); }
    }
    
    /* Deletion Sync Tooltip Styles */
    .cin-deletion-sync-label {
        display: inline-flex;
        align-items: center;
        gap: 8px;
    }
    
    .cin-info-icon {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 20px;
        height: 20px;
        cursor: help;
        font-size: 16px;
        opacity: 0.7;
        transition: opacity 0.2s;
    }
    
    .cin-info-icon:hover {
        opacity: 1;
    }
    
    .cin-deletion-caution-tooltip {
        position: absolute;
        bottom: 100%;
        left: 0;
        margin-bottom: 10px;
        width: 400px;
        max-width: 90vw;
        background: #fff;
        border: 1px solid #d63638;
        border-radius: 4px;
        box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        padding: 16px;
        z-index: 1000;
        opacity: 0;
        visibility: hidden;
        transform: translateY(10px);
        transition: opacity 0.3s, transform 0.3s, visibility 0.3s;
        pointer-events: none;
    }
    
    .cin-deletion-sync-label:hover .cin-deletion-caution-tooltip,
    .cin-info-icon:hover ~ .cin-deletion-caution-tooltip {
        opacity: 1;
        visibility: visible;
        transform: translateY(0);
    }
    
    .cin-tooltip-arrow {
        position: absolute;
        bottom: -8px;
        left: 20px;
        width: 0;
        height: 0;
        border-left: 8px solid transparent;
        border-right: 8px solid transparent;
        border-top: 8px solid #d63638;
    }
    
    .cin-tooltip-arrow::before {
        content: '';
        position: absolute;
        bottom: 1px;
        left: -7px;
        width: 0;
        height: 0;
        border-left: 7px solid transparent;
        border-right: 7px solid transparent;
        border-top: 7px solid #fff;
    }
    
    .cin-tooltip-content {
        color: #333;
        font-size: 13px;
    }
</style>

<script>
(function() {
    'use strict';

    // ===== BUTTON STATE MANAGEMENT UTILITY =====
    function setButtonState(btn, state, text, progress) {
        const btnText = btn.querySelector('#crm-save-btn-text');
        const progressBar = btn.parentElement.querySelector('.cin-progress-bar');
        const progressFill = progressBar ? progressBar.querySelector('.cin-progress-fill') : null;
        
        btn.classList.remove('cin-btn-loading', 'cin-btn-success', 'cin-btn-error');
        
        if (state === 'loading') {
            btn.disabled = true;
            btn.classList.add('cin-btn-loading');
            btn.setAttribute('data-original-text', btnText.textContent);
            btnText.textContent = text || 'Processing...';
            
            // Show and animate progress bar
            if (progressBar && progressFill) {
                progressBar.classList.remove('cin-hidden');
                progressFill.style.width = (progress || 0) + '%';
            }
        } else if (state === 'success') {
            btn.disabled = true;
            btn.classList.add('cin-btn-success');
            btnText.textContent = text || 'Success!';
            
            // Complete progress bar
            if (progressBar && progressFill) {
                progressFill.style.width = '100%';
                progressFill.style.background = 'linear-gradient(90deg, #00a32a, #46b450)';
            }
        } else if (state === 'error') {
            btn.disabled = true;
            btn.classList.add('cin-btn-error');
            btnText.textContent = text || 'Failed';
            
            // Error progress bar
            if (progressBar && progressFill) {
                progressFill.style.background = 'linear-gradient(90deg, #dc3545, #f86c6b)';
            }
        } else if (state === 'reset') {
            btn.disabled = false;
            btnText.textContent = text || btn.getAttribute('data-original-text') || 'Save Integration Settings';
            btn.removeAttribute('data-original-text');
            
            // Hide progress bar
            if (progressBar && progressFill) {
                setTimeout(() => {
                    progressBar.classList.add('cin-hidden');
                    progressFill.style.width = '0';
                    progressFill.style.background = 'linear-gradient(90deg, #0073aa, #00a0d2)';
                }, 300);
            }
        }
    }

    /**
     * Show notification message in the CRM notice area
     * @param {string} message - The message to display
     * @param {string} type - The notification type: 'success', 'error', or 'warning'
     * @param {number} duration - Auto-hide duration in milliseconds (0 = manual dismiss only)
     */
    function showCRMNotification(message, type, duration) {
        type = type || 'success';
        duration = duration || 5000;
        
        const $notice = document.getElementById('cin-crm-settings-notice');
        const $message = document.getElementById('cin-crm-notice-message');
        
        if (!$notice || !$message) return;
        
        // Update message and state
        $message.innerHTML = message;
        $notice.classList.remove('notice-success', 'notice-error', 'notice-warning', 'cin-hidden');
        $notice.classList.add('notice-' + type, 'cin-show');
        
        // Auto-dismiss if duration specified
        if (duration > 0) {
            // Clear any existing timeout
            if ($notice._dismissTimeout) {
                clearTimeout($notice._dismissTimeout);
            }
            
            // Set new timeout
            const timeout = setTimeout(function() {
                $notice.classList.remove('cin-show');
                $notice.classList.add('cin-hidden');
            }, duration);
            
            $notice._dismissTimeout = timeout;
        }
        
        // Scroll to notification for visibility
        $notice.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
    }

    // Handle manual dismiss of CRM notice
    document.addEventListener('click', function(e) {
        if (e.target.closest('#cin-crm-settings-notice .notice-dismiss')) {
            const $notice = document.getElementById('cin-crm-settings-notice');
            if ($notice) {
                if ($notice._dismissTimeout) {
                    clearTimeout($notice._dismissTimeout);
                }
                $notice.classList.remove('cin-show');
                $notice.classList.add('cin-hidden');
            }
        }
    });

    // Handle CRM settings form submission with button states
    const form = document.getElementById('crm-settings-form');
    const btn = document.getElementById('crm-save-btn');
    
    if (!form || !btn) return;
    
    // Track submission state
    let isSubmitting = false;
    
    // Guard against multiple event listener attachments
    if (form._crmSubmitHandlerAttached) return;
    form._crmSubmitHandlerAttached = true;
    
    form.addEventListener('submit', function(e) {
        e.preventDefault();
        
        // Prevent multiple simultaneous submissions
        if (isSubmitting) return;
        isSubmitting = true;
        
        // Set button to loading state with stage message and progress
        setButtonState(btn, 'loading', 'Validating Settings...', 10);
        
        // Prepare form data
        const formData = new FormData(form);
        const data = new URLSearchParams(formData);
        
        // Animate progress stages
        setTimeout(() => {
            setButtonState(btn, 'loading', 'Saving to Database...', 40);
        }, 600);
        
        setTimeout(() => {
            setButtonState(btn, 'loading', 'Syncing Configuration...', 70);
        }, 1200);
        
        setTimeout(() => {
            setButtonState(btn, 'loading', 'Finalizing...', 90);
        }, 1800);
        
        // Send AJAX request
        fetch(ajaxurl, {
            method: 'POST',
            body: data,
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            }
        })
        .then(response => response.json())
        .then(response => {
            if (response.success) {
                // Show success state
                setButtonState(btn, 'success', 'Settings Saved! ✓');
                
                // Check if deletion sync was disabled
                if (response.data && response.data.deletion_sync_disabled) {
                    // Show GDPR deletion warning
                    const warningEl = document.getElementById('cin-gdpr-deletion-warning');
                    if (warningEl) {
                        warningEl.style.display = 'block';
                        // Scroll to warning
                        warningEl.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
                    }
                    // Update the data-initial-value to reflect saved state
                    const checkbox = document.getElementById('crm-delete-sync-enable');
                    if (checkbox) {
                        checkbox.setAttribute('data-initial-value', checkbox.checked ? '1' : '0');
                    }
                }
                
                // Reset button to default state after 3 seconds
                setTimeout(function() {
                    setButtonState(btn, 'reset', '<?php echo esc_js(__('Save Integration Settings',  'contactin')); ?>');
                    isSubmitting = false;
                }, 3000);
            } else {
                // Show error state
                setButtonState(btn, 'error', 'Save Failed');
                
                // Reset button after 3 seconds
                setTimeout(() => {
                    setButtonState(btn, 'reset', '<?php echo esc_js(__('Save Integration Settings',  'contactin')); ?>');
                    isSubmitting = false;
                }, 3000);
            }
        })
        .catch(error => {
            // Show error state
            setButtonState(btn, 'error', 'Network Error');
            
            // Reset button after 3 seconds
            setTimeout(() => {
                setButtonState(btn, 'reset', '<?php echo esc_js(__('Save Integration Settings',  'contactin')); ?>');
                isSubmitting = false;
            }, 3000);
        });
    });
})();
</script>

<?php
// Load the CRM help modal
load_template( CONTACTINBOX_PATH . \ContactInbox\Core\Config::TEMPLATE_ADMIN_PART . 'crm-help-modal.php' );
?>
