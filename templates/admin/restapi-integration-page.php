

<?php
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals
if ( ! defined( 'ABSPATH' ) ) exit;
use ContactInbox\Admin\Pages\RestApiIntegration;
use ContactInbox\Core\Config;
use ContactInbox\Core\Settings;


$active_tokens = RestApiIntegration::get_active_tokens();
$health        = RestApiIntegration::get_health_stats();
$settings      = Settings::get_settings();
$rest_enabled  = ! empty( $settings['restapi_enable'] );
$base_url      = rest_url( 'contactin/v1' );
$is_free       = defined('CONTACTINBOX_IS_FREE') && CONTACTINBOX_IS_FREE;
$display_rest_enabled = false;
?>

<div class="wrap contactin-restapi-integration">
    <?php if ( $is_free ) : ?>
        <div class="notice notice-info is-dismissible" style="margin: 20px 0 10px 0;">
            <p>
                <?php esc_html_e('Upgrade to Contact Inbox Pro to enable REST API access, generate API tokens, and integrate with external applications.', 'contact-inbox'); ?>
                <a href="#" class="button button-primary contactinbox-show-upgrade-modal" style="margin-left: 10px;"><?php esc_html_e('Upgrade to Pro', 'contact-inbox'); ?></a>
            </p>
        </div>
    <?php endif; ?>
    <div class="cin-settings-header-wrapper contactin-restapi-header">
        <h1 class="cin-settings-title">
            <?php esc_html_e( 'REST API Integration', 'contact-inbox' ); ?>
            <?php if ( $is_free ) : ?>
                <span style="margin-left: 10px; background: #dc3545; color: white; padding: 4px 8px; border-radius: 3px; font-size: 12px; font-weight: bold; vertical-align: middle;">PRO</span>
            <?php endif; ?>
        </h1>
        <button type="button"
            class="button button-secondary cin-settings-help-button<?php echo $is_free ? ' disabled contactinbox-show-upgrade-modal' : ''; ?>"
            <?php echo $is_free ? 'disabled aria-disabled="true" tabindex="-1"' : 'data-cin-help-open="cin-restapi-help-modal" aria-haspopup="dialog" aria-controls="cin-restapi-help-modal"'; ?> >
            <span class="cin-settings-help-icon" aria-hidden="true">ℹ️</span>
            <?php esc_html_e( 'Help', 'contact-inbox' ); ?>
        </button>
    </div>


    <?php $actions_disabled = ! $rest_enabled || $is_free; ?>



    <div class="contactin-restapi-columns">
        <div class="contactin-restapi-col contactin-restapi-col-left">
            <div class="postbox contactin-postbox">
                <div class="postbox-header">
                    <h2 class="hndle"><?php esc_html_e( 'Configuration', 'contact-inbox' ); ?></h2>
                </div>
                <div class="inside"><div class="contactin-content-wrap">
                    <table class="contactin-config-table">
                        <tr>
                            <td><?php esc_html_e( 'Base URL:', 'contact-inbox' ); ?></td>
                            <td>
                                <code><?php echo esc_html( $base_url ); ?></code>
                                <button type="button" id="contactin-copy-base-url" class="button button-small<?php echo $actions_disabled ? ' disabled' : ''; ?><?php echo $is_free ? ' contactinbox-show-upgrade-modal' : ''; ?>" data-base-url="<?php echo esc_attr( $base_url ); ?>" <?php echo $actions_disabled ? 'disabled aria-disabled="true" tabindex="-1"' : ''; ?> >
                                    <?php esc_html_e( 'Copy', 'contact-inbox' ); ?>
                                </button>
                            </td>
                        </tr>
                        <tr>
                            <td><?php esc_html_e( 'Auth:', 'contact-inbox' ); ?></td>
                            <td><?php esc_html_e( 'X-ContactIN-Test-Token header', 'contact-inbox' ); ?></td>
                        </tr>
                        <tr>
                            <td><?php esc_html_e( 'Rate Limit:', 'contact-inbox' ); ?></td>
                            <td>
                                <div class="cin-flex-center-gap-sm contactin-rate-limit-settings">
                                    <input type="number" id="rate_limit_value" name="rate_limit_value" 
                                        value="60" 
                                        min="1" max="9999" class="small-text" <?php echo ($is_free ? 'disabled' : ''); ?> />
                                    <select id="rate_limit_unit" name="rate_limit_unit" class="cin-per-page-select" <?php echo ($is_free ? 'disabled' : ''); ?> >
                                        <option value="minute"><?php esc_html_e( 'Per Minute', 'contact-inbox' ); ?></option>
                                        <option value="hour"><?php esc_html_e( 'Per Hour', 'contact-inbox' ); ?></option>
                                        <option value="day"><?php esc_html_e( 'Per Day', 'contact-inbox' ); ?></option>
                                    </select>
                                    <button type="button" id="contactin-save-rate-limits" class="button button-small <?php echo ($is_free ? 'disabled contactinbox-show-upgrade-modal' : ''); ?>" <?php echo ($is_free ? 'disabled' : ''); ?> >
                                        <?php esc_html_e( 'Save', 'contact-inbox' ); ?>
                                        <?php if ( $is_free ) : ?>
                                            <span style="margin-left: 4px; background: #dc3545; color: white; padding: 1px 4px; border-radius: 2px; font-size: 9px; font-weight: bold;">PRO</span>
                                        <?php endif; ?>
                                    </button>
                                </div>
                                <span id="contactin-rate-limits-message" class="cin-hidden contactin-rate-limit-message"></span>
                                <p class="description cin-mt-md">
                                    <?php esc_html_e( 'Rate limits are applied per IP address. Configure limits for different time periods.', 'contact-inbox' ); ?>
                                </p>
                            </td>
                        </tr>
                    </table>
                    <div class="contactin-info-box blue">
                        <h4><?php esc_html_e( 'Available Endpoints', 'contact-inbox' ); ?></h4>
                        <ul>
                            <li><code>POST /messages/submit</code> <?php esc_html_e( 'Submit contact message (JSON or multipart)', 'contact-inbox' ); ?></li>
                            <li><code>GET /messages/search</code> <?php esc_html_e( 'Search messages (q, page, per_page)', 'contact-inbox' ); ?></li>
                            <li><code>GET /messages/read</code> <?php esc_html_e( 'Get message details (id)', 'contact-inbox' ); ?></li>
                            <li><code>POST /messages/status</code> <?php esc_html_e( 'Update message status (id, status)', 'contact-inbox' ); ?></li>
                            <li><code>POST /messages/gdpr</code> <?php esc_html_e( 'Request GDPR delete token (email or token)', 'contact-inbox' ); ?></li>
                            <li><code>DELETE /messages/delete/:token</code> <?php esc_html_e( 'Delete via GDPR token', 'contact-inbox' ); ?></li>
                            <li><code>DELETE /messages/bulk-delete</code> <?php esc_html_e( 'Bulk delete messages (ids[])', 'contact-inbox' ); ?></li>
                        </ul>
                    </div>
                    <div class="contactin-info-box warning">
                        <strong><?php esc_html_e( 'Quick Links', 'contact-inbox' ); ?></strong>
                        <p>
                            <a href="#contactin-test-connection" class="button button-small<?php echo $actions_disabled ? ' disabled' : ''; ?>" <?php echo $actions_disabled ? 'tabindex=\"-1\" aria-disabled=\"true\"' : ''; ?> >
                                <?php esc_html_e( 'Test Connection', 'contact-inbox' ); ?>
                            </a>
                            <a href="<?php echo $is_free ? '#' : esc_url( admin_url( 'admin.php?page=' . Config::MENU_REST_LOG ) ); ?>" class="button button-small<?php echo $is_free ? ' disabled contactinbox-show-upgrade-modal' : ''; ?>" <?php echo $is_free ? 'tabindex="-1" aria-disabled="true"' : ''; ?> >
                                <?php esc_html_e( 'View Logs', 'contact-inbox' ); ?>
                                <?php if ( $is_free ) : ?>
                                    <span style="margin-left: 4px; background: #dc3545; color: white; padding: 1px 4px; border-radius: 2px; font-size: 9px; font-weight: bold;">PRO</span>
                                <?php endif; ?>
                            </a>
                        </p>
                    </div>
                    <div class="cin-mt-2xl">
                        <button type="button" id="contactin-generate-token-btn" class="button button-primary<?php echo $actions_disabled ? ' disabled' : ''; ?> <?php echo ($is_free ? 'contactinbox-show-upgrade-modal' : ''); ?>" onclick="<?php echo ($is_free ? '' : 'ContactINIntegration.showGenerateTokenForm()'); ?>" <?php echo $actions_disabled ? 'disabled' : ''; ?> >
                            + Generate New Token
                            <?php if ( $is_free ) : ?>
                                <span style="margin-left: 6px; background: #dc3545; color: white; padding: 2px 5px; border-radius: 2px; font-size: 10px; font-weight: bold;">PRO</span>
                            <?php endif; ?>
                        </button>
                    </div>
                </div></div>
            </div>
        </div>
        <div class="contactin-restapi-col contactin-restapi-col-right">
            <div class="postbox contactin-postbox">
                <div class="postbox-header">
                    <h2 class="hndle"><?php esc_html_e( 'Health & Usage', 'contact-inbox' ); ?></h2>
                </div>
                <div class="inside"><div class="contactin-content-wrap">
                    <div class="contactin-status-row">
                        <div class="inner-flex cin-flex-between">
                            <div class="status-left">
                                <span id="contactin-restapi-status-label" class="cin-status-label <?php echo $display_rest_enabled ? 'enabled' : 'disabled'; ?>">
                                    <?php echo $display_rest_enabled ? esc_html__('Service Enabled', 'contact-inbox') : esc_html__('Service Disabled', 'contact-inbox'); ?>
                                </span>
                            </div>
                            <div class="status-right">
                                <button type="button" id="contactin-toggle-restapi" class="button button-small<?php echo $display_rest_enabled ? ' enabled' : ''; ?> <?php echo ($is_free ? 'disabled contactinbox-show-upgrade-modal' : ''); ?>" data-enabled="<?php echo $display_rest_enabled ? '1' : '0'; ?>" <?php echo ($is_free ? 'disabled' : ''); ?> >
                                    <?php echo $display_rest_enabled ? esc_html__('Disable REST API Service', 'contact-inbox') : esc_html__('Enable REST API Service', 'contact-inbox'); ?>
                                    <?php if ( $is_free ) : ?>
                                        <span style="margin-left: 4px; background: #dc3545; color: white; padding: 1px 4px; border-radius: 2px; font-size: 9px; font-weight: bold;">PRO</span>
                                    <?php endif; ?>
                                </button>
                            </div>
                        </div>
                    </div>
                    <div class="contactin-info-box warning cin-mt-lg">
                        <strong>⚠️ <?php esc_html_e( 'Important:', 'contact-inbox' ); ?></strong>
                        <?php esc_html_e( 'The REST API service is required for file attachment uploads to work. Disabling it will also disable the file attachment feature.', 'contact-inbox' ); ?>
                    </div>
                    <table class="contactin-health-stats">
                        <tr>
                            <td><?php esc_html_e( 'Last Activity:', 'contact-inbox' ); ?></td>
                            <td>
                                <?php
                                if ( $health['last_call_time'] ) {
                                    echo esc_html( human_time_diff( strtotime( $health['last_call_time'] ), current_time('timestamp') ) . ' ago' );
                                } else {
                                    esc_html_e( 'Never', 'contact-inbox' );
                                }
                                ?>
                            </td>
                        </tr>
                        <tr>
                            <td><?php esc_html_e( 'Calls (24h):', 'contact-inbox' ); ?></td>
                            <td>
                                <strong><?php echo esc_html( $health['calls_24h'] ); ?></strong> (<?php echo esc_html( $health['success_rate_24h'] ); ?>% success)
                            </td>
                        </tr>
                        <tr>
                            <td><?php esc_html_e( 'Calls (7d):', 'contact-inbox' ); ?></td>
                            <td>
                                <strong><?php echo esc_html( $health['calls_7d'] ); ?></strong> (<?php echo esc_html( $health['success_rate_7d'] ); ?>% success)
                            </td>
                        </tr>
                    </table>
                </div></div>
            </div>
            <div id="contactin-test-connection" class="postbox contactin-postbox contactin-test-section">
                <div class="postbox-header">
                    <h2 class="hndle">Test API Connection</h2>
                </div>
                <div class="inside"><div class="contactin-content-wrap">
                    <form id="contactin-test-api-form" class="contactin-test-form">
                        <div class="form-group">
                            <label for="test_salutation">Salutation (optional):</label>
                            <select id="test_salutation" name="salutation" class="regular-text" <?php echo ($is_free ? 'disabled' : ''); ?> >
                                <option value=""><?php esc_html_e( 'Select salutation', 'contact-inbox' ); ?></option>
                                <option value="Mr"><?php esc_html_e( 'Mr', 'contact-inbox' ); ?></option>
                                <option value="Ms"><?php esc_html_e( 'Ms', 'contact-inbox' ); ?></option>
                                <option value="Mrs"><?php esc_html_e( 'Mrs', 'contact-inbox' ); ?></option>
                                <option value="Miss"><?php esc_html_e( 'Miss', 'contact-inbox' ); ?></option>
                                <option value="Dr"><?php esc_html_e( 'Dr', 'contact-inbox' ); ?></option>
                                <option value="Prof"><?php esc_html_e( 'Prof', 'contact-inbox' ); ?></option>
                                <option value="Mx"><?php esc_html_e( 'Mx', 'contact-inbox' ); ?></option>
                                <option value="Other"><?php esc_html_e( 'Other', 'contact-inbox' ); ?></option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="test_name">Name:</label>
                            <input type="text" id="test_name" name="name" class="regular-text" required placeholder="Test User" <?php echo ($is_free ? 'disabled' : ''); ?> >
                        </div>
                        <div class="form-group">
                            <label for="test_email">Email:</label>
                            <input type="email" id="test_email" name="email" class="regular-text" required placeholder="test@example.com" <?php echo ($is_free ? 'disabled' : ''); ?> >
                        </div>
                        <div class="form-group">
                            <label for="test_subject">Subject (optional):</label>
                            <input type="text" id="test_subject" name="subject" class="regular-text" placeholder="Test subject" <?php echo ($is_free ? 'disabled' : ''); ?> >
                        </div>
                        <div class="form-group">
                            <label for="test_message">Message:</label>
                            <textarea id="test_message" name="message" class="large-text" rows="4" required placeholder="This is a test message from the REST API" <?php echo ($is_free ? 'disabled' : ''); ?> ></textarea>
                        </div>
                        <div class="form-group">
                            <label for="test_attachment">Attachment (optional):</label>
                            <input type="file" id="test_attachment" name="attachment" class="regular-text" <?php echo ($is_free ? 'disabled' : ''); ?> />
                            <p class="description cin-mt-sm">You can attach a file to test REST API uploads.</p>
                        </div>
                        <div class="form-group">
                            <button type="button" id="test_submit" class="button button-primary<?php echo $actions_disabled ? ' disabled' : ''; ?> <?php echo ($is_free ? 'contactinbox-show-upgrade-modal' : ''); ?>" onclick="<?php echo ($is_free ? '' : 'ContactINIntegration.testConnection()'); ?>" <?php echo $actions_disabled ? 'disabled' : ''; ?> >
                                Send Test Request
                                <?php if ( $is_free ) : ?>
                                    <span style="margin-left: 6px; background: #dc3545; color: white; padding: 2px 5px; border-radius: 2px; font-size: 10px; font-weight: bold;">PRO</span>
                                <?php endif; ?>
                            </button>
                            <span id="test_loading" class="cin-hidden cin-ml-lg">
                                <span class="spinner is-active contactin-float-none"></span>
                                Testing...
                            </span>
                        </div>
                    </form>
                    <div id="test_results" class="contactin-test-results cin-hidden">
                        <div id="test_result_content"></div>
                    </div>
                </div></div>
            </div>
        </div>
    </div>
</div>

<div id="contactin-restapi-disable-modal" class="cin-modal cin-modal-hidden">
    <div class="cin-modal-overlay"></div>
    <div class="cin-modal-content">
        <div class="cin-modal-header">
            <h2 class="cin-modal-title"><?php esc_html_e( 'Disable REST API?', 'contact-inbox' ); ?></h2>
            <button type="button" class="cin-modal-close" aria-label="<?php esc_attr_e( 'Close', 'contact-inbox' ); ?>">×</button>
        </div>
        <div class="cin-modal-body">
            <p id="contactin-restapi-disable-message">
                <?php esc_html_e( 'The REST API service is required for file attachment uploads. Disabling it will prevent the file upload feature from working. Do you want to keep it enabled, or disable the REST API service anyway?', 'contact-inbox' ); ?>
            </p>
        </div>
        <div class="cin-modal-footer cin-confirm-actions">
            <button type="button" class="button button-secondary cin-modal-close" id="contactin-restapi-disable-cancel">
                <?php esc_html_e( 'Keep Enabled', 'contact-inbox' ); ?>
            </button>
            <button type="button" class="button button-primary" id="contactin-restapi-disable-confirm">
                <?php esc_html_e( 'Disable Anyway', 'contact-inbox' ); ?>
            </button>
        </div>
    </div>
</div>

<?php load_template( CONTACTINBOX_PATH . \ContactInbox\Core\Config::TEMPLATE_ADMIN_PART . 'restapi-help-modal.php' ); ?>

<?php
// Load upgrade modal in free version
if ( defined('CONTACTINBOX_IS_FREE') && CONTACTINBOX_IS_FREE ) {
    load_template( CONTACTINBOX_PATH . \ContactInbox\Core\Config::TEMPLATE_ADMIN_PART . 'upgrade-modal.php' );
}
?>
