<?php
/**
 * Salesforce CRM Integration Page
 * 
 * Dedicated, robust admin interface for Salesforce OAuth, field mapping, and testing.
 * Gold standard UX with comprehensive error handling and inline documentation.
 * 
 * @package ContactInbox\Admin\Pages
 */

namespace ContactInbox\Admin\Pages;

use ContactInbox\Core\Config;
use ContactInbox\Core\CRMSettings;
use ContactInbox\Core\CRMConnector;
use ContactInbox\Traits\Singleton;

if (!defined('ABSPATH')) {
    exit;
}

class SalesforceIntegration {
    use Singleton;
    
    public function __construct() {
        add_action('admin_menu', [$this, 'register']);
    }
    
    /**
     * Register the Salesforce Integration page.
     */
    public function register(): void {
        add_submenu_page(
            'contactinbox_main_menu',
            __('Salesforce Integration', 'contact-inbox'),
            __('Salesforce CRM', 'contact-inbox'),
            Config::CAPABILITY,
            'contactin-salesforce',
            [$this, 'render_page']
        );
    }

    /**
     * Render the Salesforce Integration page.
     */
    public function render_page(): void {
        $settings = CRMSettings::get_settings();
        $is_authorized = !empty($settings['access_token']);
        $mapping = $settings['mapping'] ?? [];
        $mapping_configured = is_array($mapping) && count(array_filter($mapping)) > 0;

        $checklist = [
            [
                'label' => __('Create a Connected App in Salesforce', 'contact-inbox'),
                'status' => 'info',
            ],
            [
                'label' => __('Authorize this site using OAuth', 'contact-inbox'),
                'status' => $is_authorized ? 'done' : 'pending',
            ],
            [
                'label' => __('Configure field mappings for Contacts/Tasks', 'contact-inbox'),
                'status' => $mapping_configured ? 'done' : 'pending',
            ],
            [
                'label' => __('Send a test submission and review logs', 'contact-inbox'),
                'status' => 'pending',
            ],
        ];

        ?>
        <div class="wrap contactin-sf-modern">
            <div class="sf-modern-header">
                <div class="sf-modern-title-block">
                    <h1><?php esc_html_e('Salesforce CRM Integration', 'contact-inbox'); ?></h1>
                    <p class="sf-modern-subtitle"><?php esc_html_e('Connect WordPress submissions to Salesforce, manage mappings, and monitor sync health from one dashboard.', 'contact-inbox'); ?></p>
                </div>
                <?php $this->render_header_info($is_authorized); ?>
            </div>

            <section class="sf-card sf-card-horizontal" aria-labelledby="sf-checklist-heading">
                <div class="sf-card-heading">
                    <h2 id="sf-checklist-heading" class="sf-card-title"><?php esc_html_e('Connection Checklist', 'contact-inbox'); ?></h2>
                    <p class="sf-card-subtitle"><?php esc_html_e('Track progress and finish the remaining steps to go live.', 'contact-inbox'); ?></p>
                </div>
                <ol class="sf-checklist">
                    <?php foreach ($checklist as $item) :
                        $status = $item['status'];
                        $class = 'sf-checklist-item status-' . $status;
                    ?>
                        <li class="<?php echo esc_attr($class); ?>">
                            <span class="sf-check-icon" aria-hidden="true"></span>
                            <span class="sf-check-text"><?php echo esc_html($item['label']); ?></span>
                        </li>
                    <?php endforeach; ?>
                </ol>
            </section>

            <div class="sf-modern-grid">
                <?php
                $this->render_connection_tab($settings, $is_authorized);
                $this->render_field_mapping_tab($settings);
                $this->render_testing_tab($settings, $is_authorized);
                $this->render_logs_tab();
                $this->render_help_tab();
                ?>
            </div>
        </div>

        <style>
            .contactin-sf-modern {
                margin-top: 18px;
                color: #111827;
            }
            .sf-modern-header {
                display: flex;
                justify-content: space-between;
                align-items: flex-start;
                gap: 24px;
                flex-wrap: wrap;
                margin-bottom: 24px;
            }
            .sf-modern-title-block h1 {
                margin: 0 0 8px;
                font-size: 26px;
            }
            .sf-modern-subtitle {
                margin: 0;
                max-width: 540px;
                color: #4b5563;
                font-size: 14px;
            }
            .sf-status-summary {
                display: flex;
                align-items: center;
                gap: 14px;
                padding: 16px 18px;
                border: 1px solid #d1d5db;
                border-radius: 10px;
                background: #f9fafb;
                min-width: 260px;
            }
            .sf-status-summary.status-connected {
                border-color: #86efac;
                background: #ecfdf5;
            }
            .sf-status-summary.status-disconnected {
                border-color: #fcd34d;
                background: #fffbeb;
            }
            .sf-status-badge {
                width: 14px;
                height: 14px;
                border-radius: 50%;
                background: #9ca3af;
            }
            .sf-status-summary.status-connected .sf-status-badge {
                background: #22c55e;
            }
            .sf-status-summary.status-disconnected .sf-status-badge {
                background: #f59e0b;
            }
            .sf-status-copy {
                display: flex;
                flex-direction: column;
                gap: 4px;
            }
            .sf-status-title {
                margin: 0;
                font-weight: 600;
                font-size: 15px;
            }
            .sf-status-description {
                margin: 0;
                font-size: 13px;
                color: #4b5563;
            }
            .sf-card {
                background: #ffffff;
                border: 1px solid #d1d5db;
                border-radius: 12px;
                padding: 24px;
                box-shadow: 0 1px 2px rgba(15, 23, 42, 0.08);
            }
            .sf-card-accent {
                border-color: #2563eb;
                box-shadow: 0 10px 30px rgba(37, 99, 235, 0.12);
            }
            .sf-card-horizontal {
                display: flex;
                flex-direction: column;
                gap: 18px;
                margin-bottom: 24px;
            }
            .sf-card-heading {
                display: flex;
                flex-direction: column;
                gap: 6px;
            }
            .sf-card-title {
                margin: 0;
                font-size: 18px;
                font-weight: 600;
            }
            .sf-card-subtitle {
                margin: 0;
                color: #6b7280;
                font-size: 13px;
            }
            .sf-checklist {
                list-style: none;
                margin: 0;
                padding: 0;
                display: grid;
                gap: 10px;
                grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            }
            .sf-checklist-item {
                display: flex;
                align-items: center;
                gap: 10px;
                padding: 12px 16px;
                border: 1px solid #e5e7eb;
                border-radius: 10px;
                background: #f9fafb;
                font-size: 14px;
            }
            .sf-check-text {
                flex: 1;
            }
            .sf-check-icon {
                width: 20px;
                height: 20px;
                border-radius: 999px;
                display: inline-flex;
                align-items: center;
                justify-content: center;
                font-size: 12px;
                font-weight: 600;
                color: #ffffff;
                background: #6b7280;
            }
            .sf-checklist-item.status-done {
                border-color: #86efac;
                background: #ecfdf5;
                color: #166534;
            }
            .sf-checklist-item.status-done .sf-check-icon {
                background: #22c55e;
            }
            .sf-checklist-item.status-pending {
                border-color: #fcd34d;
                background: #fffbeb;
                color: #92400e;
            }
            .sf-checklist-item.status-pending .sf-check-icon {
                background: #f59e0b;
            }
            .sf-checklist-item.status-info {
                border-color: #d1d5db;
                background: #f3f4f6;
                color: #1f2937;
            }
            .sf-checklist-item.status-info .sf-check-icon {
                background: #6b7280;
            }
            .sf-check-icon::before {
                content: '\2713';
            }
            .sf-modern-grid {
                display: grid;
                grid-template-columns: repeat(auto-fit, minmax(320px, 1fr));
                gap: 24px;
            }
            .sf-oauth-flow {
                display: flex;
                flex-direction: column;
                gap: 16px;
                margin: 18px 0;
            }
            .sf-section-intro {
                margin: 0;
                color: #4b5563;
                font-size: 13px;
            }
            .sf-oauth-step {
                display: flex;
                gap: 16px;
                align-items: flex-start;
                padding: 14px 16px;
                border: 1px solid #e5e7eb;
                border-radius: 10px;
                background: #f9fafb;
            }
            .sf-oauth-step-number {
                width: 28px;
                height: 28px;
                border-radius: 50%;
                background: #2563eb;
                color: #ffffff;
                display: inline-flex;
                align-items: center;
                justify-content: center;
                font-weight: 600;
                font-size: 13px;
            }
            .sf-oauth-step.is-complete .sf-oauth-step-number {
                background: #22c55e;
            }
            .sf-oauth-step-copy {
                display: flex;
                flex-direction: column;
                gap: 4px;
            }
            .sf-step-title {
                font-weight: 600;
                font-size: 14px;
                color: #1f2937;
            }
            .sf-step-hint {
                font-size: 12px;
                color: #6b7280;
            }
            .sf-help-box {
                border: 1px solid #bfdbfe;
                background: #eff6ff;
                border-radius: 10px;
                padding: 14px 16px;
                margin: 20px 0;
                font-size: 13px;
                color: #1d4ed8;
            }
            .sf-help-box strong {
                display: block;
                margin-bottom: 4px;
                color: #1d4ed8;
            }
            .sf-button-row {
                display: flex;
                align-items: center;
                gap: 12px;
                margin-top: 12px;
                flex-wrap: wrap;
            }
            .sf-inline-success {
                margin-top: 10px;
                font-size: 13px;
                color: #166534;
            }
            .sf-card-form {
                display: flex;
                flex-direction: column;
                gap: 18px;
            }
            .sf-field-mapping-table {
                width: 100%;
                border-collapse: collapse;
            }
            .sf-field-mapping-table th,
            .sf-field-mapping-table td {
                padding: 12px 14px;
                border-bottom: 1px solid #e5e7eb;
                text-align: left;
                font-size: 13px;
            }
            .sf-field-mapping-table thead th {
                background: #f3f4f6;
                font-weight: 600;
            }
            .sf-field-mapping-table input {
                width: 100%;
                max-width: 320px;
            }
            .sf-field-note {
                display: block;
                color: #6b7280;
                font-size: 12px;
            }
            .sf-form-section {
                display: flex;
                flex-direction: column;
                gap: 8px;
            }
            .sf-label {
                display: block;
                font-size: 13px;
                color: #374151;
                margin-bottom: 4px;
            }
            .sf-select {
                padding: 8px 10px;
                border: 1px solid #d1d5db;
                border-radius: 4px;
                font-size: 13px;
                background-color: #fff;
                max-width: 400px;
            }
            .sf-select:focus {
                outline: none;
                border-color: #3b82f6;
                box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
            }
            .sf-helper-text {
                margin: 8px 0 0;
                font-size: 12px;
                color: #6b7280;
                line-height: 1.4;
                font-style: italic;
            }
            .sf-list {
                margin: 8px 0;
                padding-left: 20px;
                list-style: disc;
            }
            .sf-list li {
                margin-bottom: 6px;
                line-height: 1.5;
                color: #374151;
                font-size: 13px;
            }
            .sf-checklist-plain {
                margin: 8px 0 0;
                padding-left: 18px;
                color: #1f2937;
                font-size: 13px;
            }
            .sf-stack {
                display: flex;
                flex-direction: column;
                gap: 8px;
                margin-top: 18px;
            }
            .sf-subheading {
                margin: 0;
                font-size: 15px;
                font-weight: 600;
                color: #111827;
            }
            .sf-text-muted {
                margin: 0;
                color: #6b7280;
                font-size: 13px;
            }
            .sf-test-result {
                font-weight: 600;
                color: #111827;
                min-width: 140px;
            }
            .sf-stepper {
                margin: 0;
                padding-left: 20px;
                color: #1f2937;
                font-size: 13px;
                display: grid;
                gap: 6px;
            }
            .sf-stepper li {
                margin-left: 4px;
            }
            .sf-compact th {
                width: 180px;
                font-weight: 500;
            }
            .sf-compact td {
                color: #1f2937;
            }
            .sf-table-scroll {
                overflow-x: auto;
            }
            .sf-table-empty {
                text-align: center;
                padding: 30px 10px;
                color: #6b7280;
                font-size: 13px;
            }
            .sf-status {
                font-weight: 600;
            }
            .sf-status.status-success {
                color: #15803d;
            }
            .sf-status.status-error {
                color: #b91c1c;
            }
            .sf-status.status-warning {
                color: #b45309;
            }
            .sf-status.status-muted {
                color: #4b5563;
            }
            .sf-reference-list {
                margin: 0;
                padding-left: 18px;
                color: #1f2937;
                font-size: 13px;
                display: grid;
                gap: 6px;
            }
            .sf-reference-list code {
                background: #f3f4f6;
                padding: 2px 6px;
                border-radius: 4px;
            }
            .button.button-large {
                padding: 8px 18px;
                font-size: 13px;
            }
            .sf-help-box-spaced {
                margin-top: 12px;
            }
            .sf-intent-intro {
                margin: 8px 0 0;
            }
            .sf-form-section-spaced {
                margin-top: 10px;
            }
            .sf-help-box-top-spacing {
                margin-top: 16px;
            }
            .sf-phone-strategy-list {
                margin: 8px 0 0;
                padding-left: 20px;
            }
            .sf-phone-note {
                margin: 8px 0 0;
                font-size: 12px;
                color: #6b7280;
            }
            .sf-documentation-link {
                margin-top: 16px;
            }
            @media (max-width: 782px) {
                .sf-modern-header {
                    flex-direction: column;
                }
                .sf-modern-grid {
                    grid-template-columns: 1fr;
                }
                .sf-card {
                    padding: 20px;
                }
                .sf-field-mapping-table th,
                .sf-field-mapping-table td {
                    padding: 10px 12px;
                }
            }
        </style>
        <?php
    }

    /**
     * Render header info card.
     */
    private function render_header_info(bool $is_authorized): void {
        $status_class = $is_authorized ? 'status-connected' : 'status-disconnected';
        $status_title = $is_authorized
            ? __('Connected to Salesforce', 'contact-inbox')
            : __('Connection Required', 'contact-inbox');
        $status_description = $is_authorized
            ? __('Your WordPress site is authorized with Salesforce. Syncs run automatically.', 'contact-inbox')
            : __('Authorize this site with Salesforce to begin syncing form submissions.', 'contact-inbox');

        ?>
        <div class="sf-status-summary <?php echo esc_attr($status_class); ?>">
            <span class="sf-status-badge" aria-hidden="true"></span>
            <div class="sf-status-copy">
                <p class="sf-status-title"><?php echo esc_html($status_title); ?></p>
                <p class="sf-status-description"><?php echo esc_html($status_description); ?></p>
            </div>
        </div>
        <?php
    }

    /**
     * Render Connection Tab.
     */
    private function render_connection_tab(array $settings, bool $is_authorized): void {
        $callback_url = admin_url('admin-ajax.php');
        ?>
        <article class="sf-card sf-card-accent" aria-labelledby="sf-card-connection">
            <header class="sf-card-heading">
                <h2 id="sf-card-connection" class="sf-card-title"><?php esc_html_e('OAuth Connection', 'contact-inbox'); ?></h2>
                <p class="sf-card-subtitle"><?php esc_html_e('Authorize your Salesforce org so Secure Contact can sync submissions in real time.', 'contact-inbox'); ?></p>
            </header>

            <div class="sf-oauth-flow">
                <p class="sf-section-intro"><?php esc_html_e('Follow these steps to authorize Salesforce:', 'contact-inbox'); ?></p>

                <div class="sf-oauth-step <?php echo $is_authorized ? 'is-complete' : ''; ?>">
                    <span class="sf-oauth-step-number">1</span>
                    <div class="sf-oauth-step-copy">
                        <span class="sf-step-title"><?php esc_html_e('Create Connected App in Salesforce', 'contact-inbox'); ?></span>
                        <span class="sf-step-hint"><?php esc_html_e('Setup → Apps → App Manager → New Connected App', 'contact-inbox'); ?></span>
                    </div>
                </div>

                <div class="sf-oauth-step <?php echo $is_authorized ? 'is-complete' : ''; ?>">
                    <span class="sf-oauth-step-number">2</span>
                    <div class="sf-oauth-step-copy">
                        <span class="sf-step-title"><?php esc_html_e('Configure Callback URL', 'contact-inbox'); ?></span>
                        <span class="sf-step-hint"><?php echo sprintf(esc_html__('Callback URL: %s', 'contact-inbox'), esc_html($callback_url)); ?></span>
                    </div>
                </div>

                <div class="sf-oauth-step <?php echo $is_authorized ? 'is-complete' : ''; ?>">
                    <span class="sf-oauth-step-number">3</span>
                    <div class="sf-oauth-step-copy">
                        <span class="sf-step-title"><?php esc_html_e('Launch the Salesforce authorization flow', 'contact-inbox'); ?></span>
                        <span class="sf-step-hint"><?php esc_html_e('Approve access when prompted. You will be redirected back here on success.', 'contact-inbox'); ?></span>
                    </div>
                </div>
            </div>

            <div class="sf-help-box">
                <strong><?php esc_html_e('OAuth Security', 'contact-inbox'); ?></strong>
                <p><?php esc_html_e('We use OAuth 2.0 with PKCE for secure authentication. Your credentials are never stored directly.', 'contact-inbox'); ?></p>
            </div>

            <div class="sf-button-row">
                <button type="button" id="cin-oauth-btn" class="button button-primary button-large">
                    <?php echo $is_authorized ? esc_html__('Re-authorize with Salesforce', 'contact-inbox') : esc_html__('Authorize with Salesforce', 'contact-inbox'); ?>
                </button>
            </div>

            <?php if ($is_authorized): ?>
                <p class="sf-inline-success"><?php esc_html_e('Authorization is active. You can re-authorize at any time.', 'contact-inbox'); ?></p>
            <?php endif; ?>
        </article>
        <?php
    }

    /**
     * Render Field Mapping Tab.
     */
    private function render_field_mapping_tab(array $settings): void {
        $mapping = $settings['mapping'] ?? [];
        $plugin_fields = ['name', 'email', 'phone', 'subject', 'message', 'intent_category', 'intent_confidence'];
        $field_labels = [
            'name' => __('Name', 'contact-inbox'),
            'email' => __('Email', 'contact-inbox'),
            'phone' => __('Phone', 'contact-inbox'),
            'subject' => __('Subject', 'contact-inbox'),
            'message' => __('Message', 'contact-inbox'),
            'intent_category' => __('Intent Category', 'contact-inbox'),
            'intent_confidence' => __('Intent Confidence', 'contact-inbox'),
        ];
        ?>
        <article class="sf-card" aria-labelledby="sf-card-mapping">
            <header class="sf-card-heading">
                <h2 id="sf-card-mapping" class="sf-card-title"><?php esc_html_e('Field Mapping', 'contact-inbox'); ?></h2>
                <p class="sf-card-subtitle"><?php esc_html_e('Map your form fields to Salesforce Contact and Case/Task properties.', 'contact-inbox'); ?></p>
            </header>

            <form method="post" action="options.php" class="sf-card-form">
                <?php settings_fields(Config::SETTINGS_GROUP_CRM); ?>

                <table class="sf-field-mapping-table">
                    <thead>
                        <tr>
                            <th><?php esc_html_e('Form Field', 'contact-inbox'); ?></th>
                            <th><?php esc_html_e('Salesforce Field', 'contact-inbox'); ?></th>
                            <th><?php esc_html_e('Notes', 'contact-inbox'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($plugin_fields as $field): ?>
                            <tr>
                                <td><strong><?php echo esc_html($field_labels[$field] ?? ucfirst($field)); ?></strong></td>
                                <td>
                                    <input type="text"
                                           name="<?php echo esc_attr(Config::OPTION_CRM); ?>[mapping][<?php echo esc_attr($field); ?>]"
                                           value="<?php echo esc_attr($mapping[$field] ?? ''); ?>"
                                           placeholder="<?php 
                                               if ($field === 'name') {
                                                   esc_attr_e('FirstName,LastName', 'contact-inbox');
                                               } elseif ($field === 'email') {
                                                   esc_attr_e('Email', 'contact-inbox');
                                                } elseif ($field === 'intent_category') {
                                                    esc_attr_e('Message_Intent__c', 'contact-inbox');
                                                } elseif ($field === 'intent_confidence') {
                                                    esc_attr_e('Intent_Confidence__c', 'contact-inbox');
                                               } else {
                                                   esc_attr_e('CRM field name', 'contact-inbox');
                                               }
                                           ?>" />
                                </td>
                                <td>
                                    <span class="sf-field-note">
                                        <?php 
                                        switch ($field) {
                                            case 'name':
                                                esc_html_e('Splits "John Doe" into FirstName and LastName', 'contact-inbox');
                                                break;
                                            case 'email':
                                                esc_html_e('Required field', 'contact-inbox');
                                                break;
                                            case 'phone':
                                                esc_html_e('Optional field', 'contact-inbox');
                                                break;
                                            case 'subject':
                                                esc_html_e('Maps to Title field', 'contact-inbox');
                                                break;
                                            case 'message':
                                                esc_html_e('Maps to Description field', 'contact-inbox');
                                                break;
                                            case 'intent_category':
                                                esc_html_e('Intent category from classification (Case/Task custom field)', 'contact-inbox');
                                                break;
                                            case 'intent_confidence':
                                                esc_html_e('Classification confidence percentage (Case/Task custom field)', 'contact-inbox');
                                                break;
                                        }
                                        ?>
                                    </span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>

                <div class="sf-help-box sf-help-box-spaced">
                    <strong><?php esc_html_e('Intent Classification Mapping', 'contact-inbox'); ?></strong>
                    <p class="sf-intent-intro">
                        <?php esc_html_e('Map classification data to custom Case/Task fields in Salesforce (optional).', 'contact-inbox'); ?>
                    </p>
                    <div class="sf-form-section sf-form-section-spaced">
                        <label class="sf-label" for="sf-intent-category">
                            <strong><?php esc_html_e('Intent Category Field', 'contact-inbox'); ?></strong>
                        </label>
                        <input type="text"
                               id="sf-intent-category"
                               name="<?php echo esc_attr(Config::OPTION_CRM); ?>[mapping][intent_category]"
                               value="<?php echo esc_attr($mapping['intent_category'] ?? ''); ?>"
                               placeholder="<?php esc_attr_e('Message_Intent__c', 'contact-inbox'); ?>"
                               class="sf-select" />
                        <p class="sf-helper-text">
                            <?php esc_html_e('Stores the intent category (sales, support, feedback, complaint, question, spam).', 'contact-inbox'); ?>
                        </p>
                    </div>
                    <div class="sf-form-section">
                        <label class="sf-label" for="sf-intent-confidence">
                            <strong><?php esc_html_e('Intent Confidence Field', 'contact-inbox'); ?></strong>
                        </label>
                        <input type="text"
                               id="sf-intent-confidence"
                               name="<?php echo esc_attr(Config::OPTION_CRM); ?>[mapping][intent_confidence]"
                               value="<?php echo esc_attr($mapping['intent_confidence'] ?? ''); ?>"
                               placeholder="<?php esc_attr_e('Intent_Confidence__c', 'contact-inbox'); ?>"
                               class="sf-select" />
                        <p class="sf-helper-text">
                            <?php esc_html_e('Stores the confidence percentage for the classification.', 'contact-inbox'); ?>
                        </p>
                    </div>
                </div>

                <div class="sf-help-box">
                    <strong><?php esc_html_e('Name Field Handling', 'contact-inbox'); ?></strong>
                    <p><?php esc_html_e('Two-word names like "John Doe" are automatically split into FirstName and LastName. Single-word names populate only FirstName.', 'contact-inbox'); ?></p>
                </div>

                <div class="sf-help-box">
                    <strong><?php esc_html_e('Phone Number Handling', 'contact-inbox'); ?></strong>
                    <p><?php esc_html_e('Choose how to handle phone numbers when users submit updated numbers:', 'contact-inbox'); ?></p>
                    <ul class="sf-list">
                        <li>
                            <strong><?php esc_html_e('Keep Secondary (Recommended):', 'contact-inbox'); ?></strong>
                            <?php esc_html_e('Store new phones in OtherPhone field to preserve the original Phone. Best for maintaining complete contact history.', 'contact-inbox'); ?>
                        </li>
                        <li>
                            <strong><?php esc_html_e('Overwrite:', 'contact-inbox'); ?></strong>
                            <?php esc_html_e('Replace the existing Phone field with the new number. Use only if latest info is critical.', 'contact-inbox'); ?>
                        </li>
                        <li>
                            <strong><?php esc_html_e('Skip if Exists:', 'contact-inbox'); ?></strong>
                            <?php esc_html_e('Only update Phone if the contact has no phone number yet. Best for data quality compliance.', 'contact-inbox'); ?>
                        </li>
                    </ul>
                </div>

                <div class="sf-form-section">
                    <label for="phone-handling" class="sf-label">
                        <strong><?php esc_html_e('When a user submits a different phone number:', 'contact-inbox'); ?></strong>
                    </label>
                    <select name="<?php echo esc_attr(Config::OPTION_CRM); ?>[phone_handling_strategy]"
                            id="phone-handling"
                            class="sf-select">
                        <?php
                        $current_strategy = $settings['phone_handling_strategy'] ?? 'secondary';
                        $strategies = [
                            'secondary'     => __('Keep Secondary — Store in OtherPhone field (Recommended)', 'contact-inbox'),
                            'overwrite'     => __('Overwrite the Phone field', 'contact-inbox'),
                            'skip_if_exists' => __('Only update if Phone is empty', 'contact-inbox'),
                        ];
                        foreach ($strategies as $value => $label):
                        ?>
                            <option value="<?php echo esc_attr($value); ?>" <?php selected($current_strategy, $value); ?>>
                                <?php echo esc_html($label); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <p class="sf-helper-text">
                        <?php esc_html_e('The recommended strategy is "Keep Secondary" which preserves the original phone number while storing new submissions in the OtherPhone field, maintaining a complete contact history.', 'contact-inbox'); ?>
                    </p>
                </div>

                <div class="sf-help-box">
                    <strong><?php esc_html_e('File Attachment Sync', 'contact-inbox'); ?></strong>
                    <p><?php esc_html_e('Automatically upload file attachments from form submissions to the corresponding Salesforce Case/Task record.', 'contact-inbox'); ?></p>
                </div>

                <div class="sf-form-section">
                    <label for="attachment-sync-enable" class="sf-label">
                        <input type="checkbox"
                               id="attachment-sync-enable"
                               name="<?php echo esc_attr(Config::OPTION_CRM); ?>[attachment_sync]"
                               value="1"
                               <?php checked(!empty($settings['attachment_sync']), true); ?> />
                        <strong><?php esc_html_e('Sync attachments to Salesforce Cases', 'contact-inbox'); ?></strong>
                    </label>
                    <p class="sf-helper-text">
                        <?php esc_html_e('When enabled, files uploaded with form submissions will automatically attach to the Case/Task record in Salesforce.', 'contact-inbox'); ?>
                    </p>
                </div>

                <div class="sf-form-section">
                    <label for="max-attachment-size" class="sf-label">
                        <strong><?php esc_html_e('Max Attachment Size (MB)', 'contact-inbox'); ?></strong>
                    </label>
                    <input type="number"
                           id="max-attachment-size"
                           name="<?php echo esc_attr(Config::OPTION_CRM); ?>[max_attachment_size_mb]"
                           value="<?php echo esc_attr($settings['max_attachment_size_mb'] ?? 50); ?>"
                           min="1"
                           max="5000"
                           class="sf-select" />
                    <p class="sf-helper-text">
                        <?php esc_html_e('Salesforce supports up to 5GB per file. We recommend 50MB as a practical limit for most use cases.', 'contact-inbox'); ?>
                    </p>
                </div>

                <div class="sf-form-section">
                    <label for="attachment-visibility" class="sf-label">
                        <strong><?php esc_html_e('Attachment Visibility', 'contact-inbox'); ?></strong>
                    </label>
                    <select id="attachment-visibility"
                            name="<?php echo esc_attr(Config::OPTION_CRM); ?>[attachment_visibility]"
                            class="sf-select">
                        <?php
                        $current_visibility = $settings['attachment_visibility'] ?? 'AllUsers';
                        $visibility_options = [
                            'AllUsers' => __('All Users', 'contact-inbox'),
                            'InternalUsers' => __('Internal Users Only', 'contact-inbox'),
                        ];
                        foreach ($visibility_options as $value => $label):
                        ?>
                            <option value="<?php echo esc_attr($value); ?>" <?php selected($current_visibility, $value); ?>>
                                <?php echo esc_html($label); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <p class="sf-helper-text">
                        <?php esc_html_e('Controls who in your Salesforce org can see attached files.', 'contact-inbox'); ?>
                    </p>
                </div>

                <div class="sf-button-row">
                    <?php submit_button(__('Save Settings', 'contact-inbox'), 'primary', '', false); ?>
                </div>
            </form>
        </article>
        <?php
    }

    /**
     * Render Testing Tab.
     */
    private function render_testing_tab(array $settings, bool $is_authorized): void {
        ?>
        <article class="sf-card" aria-labelledby="sf-card-testing">
            <header class="sf-card-heading">
                <h2 id="sf-card-testing" class="sf-card-title"><?php esc_html_e('Test and Debug', 'contact-inbox'); ?></h2>
                <p class="sf-card-subtitle"><?php esc_html_e('Validate your Salesforce connection before going live.', 'contact-inbox'); ?></p>
            </header>

            <div class="sf-help-box">
                <strong><?php esc_html_e('Before testing', 'contact-inbox'); ?></strong>
                <p><?php esc_html_e('Make sure you have:', 'contact-inbox'); ?></p>
                <ul class="sf-checklist-plain">
                    <li><?php esc_html_e('Authorized with Salesforce using the OAuth card', 'contact-inbox'); ?></li>
                    <li><?php esc_html_e('Configured field mapping for all required fields', 'contact-inbox'); ?></li>
                    <li><?php esc_html_e('Set the correct Salesforce instance URL', 'contact-inbox'); ?></li>
                </ul>
            </div>

            <section class="sf-stack">
                <h3 class="sf-subheading"><?php esc_html_e('API Connection Test', 'contact-inbox'); ?></h3>
                <p class="sf-text-muted"><?php esc_html_e('Run a real-time check to confirm WordPress can reach the Salesforce API.', 'contact-inbox'); ?></p>
                <div class="sf-button-row">
                    <button type="button" id="cin-test-crm-btn" class="button button-secondary button-large">
                        <?php esc_html_e('Test Connection', 'contact-inbox'); ?>
                    </button>
                    <span id="cin-test-result" class="sf-test-result" style="display:none;"></span>
                </div>
            </section>

            <section class="sf-stack">
                <h3 class="sf-subheading"><?php esc_html_e('Form Submission Test', 'contact-inbox'); ?></h3>
                <p class="sf-text-muted"><?php esc_html_e('Submit a test entry and confirm it lands in Salesforce.', 'contact-inbox'); ?></p>
                <ol class="sf-stepper">
                    <li><?php esc_html_e('Open your public contact form.', 'contact-inbox'); ?></li>
                    <li><?php esc_html_e('Submit the form with test data.', 'contact-inbox'); ?></li>
                    <li><?php esc_html_e('Inspect Salesforce Contacts for the new record.', 'contact-inbox'); ?></li>
                    <li><?php esc_html_e('Review the Sync Logs card for trace details.', 'contact-inbox'); ?></li>
                </ol>
            </section>

            <section class="sf-stack">
                <h3 class="sf-subheading"><?php esc_html_e('Debug Snapshot', 'contact-inbox'); ?></h3>
                <table class="sf-field-mapping-table sf-compact">
                    <tbody>
                        <?php
                        $debug_info = [
                            __('Salesforce Instance', 'contact-inbox') => $settings['instance_url'] ?? __('Not configured', 'contact-inbox'),
                            __('Authorization Status', 'contact-inbox') => $is_authorized ? __('Connected', 'contact-inbox') : __('Not connected', 'contact-inbox'),
                            __('Field Mapping', 'contact-inbox') => empty($settings['mapping'])
                                ? __('Not configured', 'contact-inbox')
                                : sprintf(esc_html__('%d fields mapped', 'contact-inbox'), count($settings['mapping'])),
                        ];
                        foreach ($debug_info as $label => $value):
                        ?>
                            <tr>
                                <th scope="row"><?php echo esc_html($label); ?></th>
                                <td><?php echo esc_html($value); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </section>
        </article>
        <?php
    }

    /**
     * Render Logs Tab.
     */
    private function render_logs_tab(): void {
        ?>
        <article class="sf-card" aria-labelledby="sf-card-logs">
            <header class="sf-card-heading">
                <h2 id="sf-card-logs" class="sf-card-title"><?php esc_html_e('Sync Logs', 'contact-inbox'); ?></h2>
                <p class="sf-card-subtitle"><?php esc_html_e('Inspect recent Salesforce sync activity and troubleshoot failures.', 'contact-inbox'); ?></p>
            </header>

            <div class="sf-table-scroll" id="sf-logs-container">
                <table class="sf-field-mapping-table">
                    <thead>
                        <tr>
                            <th><?php esc_html_e('Timestamp', 'contact-inbox'); ?></th>
                            <th><?php esc_html_e('Contact', 'contact-inbox'); ?></th>
                            <th><?php esc_html_e('Status', 'contact-inbox'); ?></th>
                            <th><?php esc_html_e('HTTP Code', 'contact-inbox'); ?></th>
                            <th><?php esc_html_e('Details', 'contact-inbox'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        // Pro feature - CRM is not available in free version
                        $logs       = [];

                        if (empty($logs)) :
                        ?>
                            <tr>
                                <td colspan="5" class="sf-table-empty">
                                    <?php esc_html_e('No sync logs yet. Submit a contact form to see logs here.', 'contact-inbox'); ?>
                                </td>
                            </tr>
                        <?php
                        else:
                            foreach ($logs as $log):
                                $status_key = $log['error_type'] ?? 'unknown';
                                $status_class = match ($status_key) {
                                    'delivered' => 'status-success',
                                    'rejected' => 'status-error',
                                    'pending' => 'status-warning',
                                    default => 'status-muted',
                                };
                                $http_code_display = isset($log['http_code']) && $log['http_code'] !== ''
                                    ? (int) $log['http_code']
                                    : __('N/A', 'contact-inbox');
                                $contact_ref = $log['endpoint'] ?? ($log['message_id'] ?? '');
                                ?>
                                <tr>
                                    <td><?php echo esc_html($log['created_at'] ?? 'N/A'); ?></td>
                                    <td><?php echo esc_html(substr((string) $contact_ref, 0, 50)); ?></td>
                                    <td class="sf-status <?php echo esc_attr($status_class); ?>">
                                        <?php echo esc_html(ucfirst($status_key)); ?>
                                    </td>
                                    <td><?php echo esc_html(sprintf(__('HTTP %s', 'contact-inbox'), $http_code_display)); ?></td>
                                    <td>
                                        <?php if (!empty($log['error_message'])) : ?>
                                            <span title="<?php echo esc_attr($log['error_message']); ?>">
                                                <?php echo esc_html(substr($log['error_message'], 0, 60)); ?><?php esc_html_e('...', 'contact-inbox'); ?>
                                            </span>
                                        <?php else : ?>
                                            <?php esc_html_e('Success', 'contact-inbox'); ?>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php
                            endforeach;
                        endif;
                        ?>
                    </tbody>
                </table>
            </div>
        </article>
        <?php
    }

    /**
     * Render Help Tab.
     */
    private function render_help_tab(): void {
        ?>
        <article class="sf-card" aria-labelledby="sf-card-help">
            <header class="sf-card-heading">
                <h2 id="sf-card-help" class="sf-card-title"><?php esc_html_e('Help and Documentation', 'contact-inbox'); ?></h2>
                <p class="sf-card-subtitle"><?php esc_html_e('Quick tips and references for your Salesforce integration.', 'contact-inbox'); ?></p>
            </header>

            <section class="sf-stack">
                <h3 class="sf-subheading"><?php esc_html_e('Getting started', 'contact-inbox'); ?></h3>
                <ol class="sf-stepper">
                    <li><?php esc_html_e('Create a Connected App in Salesforce.', 'contact-inbox'); ?></li>
                    <li><?php esc_html_e('Authorize this WordPress site using OAuth.', 'contact-inbox'); ?></li>
                    <li><?php esc_html_e('Map your form fields to Salesforce Contact fields.', 'contact-inbox'); ?></li>
                    <li><?php esc_html_e('Run the API Connection test.', 'contact-inbox'); ?></li>
                    <li><?php esc_html_e('Submit a live form and verify the sync.', 'contact-inbox'); ?></li>
                </ol>
            </section>

            <section class="sf-stack">
                <h3 class="sf-subheading"><?php esc_html_e('Common issues', 'contact-inbox'); ?></h3>

                <div class="sf-help-box">
                    <strong><?php esc_html_e('OAuth state mismatch', 'contact-inbox'); ?></strong>
                    <p><?php esc_html_e('Complete the Salesforce authorization within 30 minutes. If it times out, restart the process from the OAuth card.', 'contact-inbox'); ?></p>
                </div>

                <div class="sf-help-box">
                    <strong><?php esc_html_e('Field mapping errors', 'contact-inbox'); ?></strong>
                    <p><?php esc_html_e('Use the exact Salesforce field API names (for example, FirstName). Review them under Salesforce Setup → Object Manager → Contact.', 'contact-inbox'); ?></p>
                </div>

                <div class="sf-help-box">
                    <strong><?php esc_html_e('401 Unauthorized', 'contact-inbox'); ?></strong>
                    <p><?php esc_html_e('The access token expired. Re-run the OAuth authorization from the Connection card.', 'contact-inbox'); ?></p>
                </div>
            </section>

            <section class="sf-stack">
                <h3 class="sf-subheading"><?php esc_html_e('Salesforce Contact field reference', 'contact-inbox'); ?></h3>
                <p class="sf-text-muted"><?php esc_html_e('How sync works: we upsert the Contact by Email, then create a linked Case/Task for the inquiry. Contact mappings apply to the Contact record, and inquiry mappings apply to the Case/Task.', 'contact-inbox'); ?></p>
                <ul class="sf-reference-list">
                    <li>
                        <code>FirstName</code>, <code>LastName</code>
                        <?php esc_html_e('- Split from the full name field. Example: "John Doe" → FirstName="John", LastName="Doe". Regional ordering is supported via the name_field_order setting.', 'contact-inbox'); ?>
                    </li>
                    <li>
                        <code>Email</code>
                        <?php esc_html_e('- Required. Used to deduplicate Contacts and perform the upsert.', 'contact-inbox'); ?>
                    </li>
                    <li>
                        <code>Phone</code>, <code>OtherPhone</code>
                        <?php esc_html_e('- Phone is synced using your selected strategy. OtherPhone is used when keeping the original number for existing Contacts.', 'contact-inbox'); ?>
                    </li>
                    <li>
                        <code>Subject</code>, <code>Description</code>
                        <?php esc_html_e('- Mapped to the Case/Task (inquiry) record. Subject uses the form subject, Description stores the message body.', 'contact-inbox'); ?>
                    </li>
                    <li>
                        <code>Message_Intent__c</code>, <code>Intent_Confidence__c</code>
                        <?php esc_html_e('- Optional custom Case/Task fields for intent classification. Map them in the Field Mapping section to send category and confidence.', 'contact-inbox'); ?>
                    </li>
                    <li>
                        <code>LeadSource</code>
                        <?php esc_html_e('- Optional Contact field used to track the source of the submission (e.g., Website).', 'contact-inbox'); ?>
                    </li>
                </ul>

                <div class="sf-help-box sf-help-box-top-spacing">
                    <strong><?php esc_html_e('Smart Phone Handling', 'contact-inbox'); ?></strong>
                    <p class="sf-intent-intro">
                        <?php esc_html_e('The plugin automatically detects whether a contact is new or existing when applying phone strategies. For "Keep Secondary" strategy:', 'contact-inbox'); ?>
                    </p>
                    <ul class="sf-phone-strategy-list">
                        <li><?php esc_html_e('New contacts: Phone field is populated (primary number)', 'contact-inbox'); ?></li>
                        <li><?php esc_html_e('Existing contacts: Original Phone preserved, new number goes to OtherPhone (maintains history)', 'contact-inbox'); ?></li>
                    </ul>
                    <p class="sf-phone-note">
                        <?php esc_html_e('This ensures that you never lose the original contact phone while still capturing updated information.', 'contact-inbox'); ?>
                    </p>
                </div>

                <p class="sf-text-muted sf-documentation-link">
                    <?php
                    echo sprintf(
                        esc_html__('For complete field documentation, see: %s', 'contact-inbox'),
                        '<a href="https://developer.salesforce.com/docs/atlas.en-us.object_reference.meta/object_reference/sforce_api_objects_contact.htm" target="_blank" rel="noopener noreferrer">Salesforce Contact Object Reference</a>'
                    );
                    ?>
                </p>
            </section>
        </article>
        <?php
    }
}
