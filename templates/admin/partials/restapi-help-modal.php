<?php
/**
 * Template: REST API Help Modal
 */

if (!defined('ABSPATH')) exit;
use ContactInbox\Core\Config;

$rest_base_url = rest_url('contactin/v1');
$rest_submit_example = trailingslashit($rest_base_url) . 'submit';
?>

<div id="cin-restapi-help-modal" class="cin-modal cin-modal-hidden" data-cin-help-modal="true">
    <div class="cin-modal-overlay"></div>
    <div class="cin-modal-content">
        <div class="cin-modal-header">
            <h2><?php esc_html_e('Contact Inbox - REST API Guide', 'contact-inbox'); ?></h2>
            <button type="button" class="cin-modal-close" aria-label="<?php esc_html_e('Close', 'contact-inbox'); ?>">
                <span aria-hidden="true">&times;</span>
            </button>
        </div>

        <div class="cin-modal-body">
            <!-- Quick Navigation -->
            <div class="cin-help-nav">
                <h3><?php esc_html_e('Quick Navigation', 'contact-inbox'); ?></h3>
                <ul>
                    <li><a href="#rest-help-overview" class="cin-help-link"><?php esc_html_e('Overview', 'contact-inbox'); ?></a></li>
                    <li><a href="#rest-help-service" class="cin-help-link"><?php esc_html_e('Service Toggle', 'contact-inbox'); ?></a></li>
                    <li><a href="#rest-help-tokens" class="cin-help-link"><?php esc_html_e('Token Management', 'contact-inbox'); ?></a></li>
                    <li><a href="#rest-help-endpoints" class="cin-help-link"><?php esc_html_e('Base URL & Endpoints', 'contact-inbox'); ?></a></li>
                    <li><a href="#rest-help-testing" class="cin-help-link"><?php esc_html_e('Testing & Monitoring', 'contact-inbox'); ?></a></li>
                    <li><a href="#rest-help-troubleshoot" class="cin-help-link"><?php esc_html_e('Troubleshooting', 'contact-inbox'); ?></a></li>
                    <li><a href="#rest-help-security" class="cin-help-link"><?php esc_html_e('Security Best Practices', 'contact-inbox'); ?></a></li>
                </ul>
            </div>

            <!-- API Endpoints -->
            <div id="rest-help-endpoints" class="cin-help-section">
                <h3><?php esc_html_e('📡 Base URL & Endpoints', 'contact-inbox'); ?></h3>

                <div class="cin-help-item">
                    <p><?php esc_html_e('All endpoints are prefixed with your WordPress REST API base:', 'contact-inbox'); ?></p>
                    <code><?php echo esc_html($rest_base_url); ?></code>
                </div>

                <div class="cin-help-item">
                    <h4><?php esc_html_e('Available Endpoints', 'contact-inbox'); ?></h4>
                    <table class="wp-list-table widefat fixed striped">
                        <thead>
                            <tr>
                                <th><?php esc_html_e('Method', 'contact-inbox'); ?></th>
                                <th><?php esc_html_e('Path', 'contact-inbox'); ?></th>
                                <th><?php esc_html_e('Description', 'contact-inbox'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td>POST</td>
                                <td>/submit</td>
                                <td><?php esc_html_e('Create a new inbox message (name, email, subject, message, attachments).', 'contact-inbox'); ?></td>
                            </tr>
                            <tr>
                                <td>GET</td>
                                <td>/search</td>
                                <td><?php esc_html_e('Filter submissions by keywords, tags, or date range.', 'contact-inbox'); ?></td>
                            </tr>
                            <tr>
                                <td>GET</td>
                                <td>/status/:id</td>
                                <td><?php esc_html_e('Get current status of a specific message.', 'contact-inbox'); ?></td>
                            </tr>
                            <tr>
                                <td>PUT</td>
                                <td>/status/:id</td>
                                <td><?php esc_html_e('Change status values like Open, Responded, Archived.', 'contact-inbox'); ?></td>
                            </tr>
                            <tr>
                                <td>DELETE</td>
                                <td>/delete/:id</td>
                                <td><?php esc_html_e('Remove a message (obeys WordPress capability checks).', 'contact-inbox'); ?></td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Testing & Monitoring -->
                <div id="rest-help-testing" class="cin-help-section">
                    <h3><?php esc_html_e('🧪 Testing & Monitoring', 'contact-inbox'); ?></h3>

                    <div class="cin-help-item">
                        <h4><?php esc_html_e('Use the built-in Test API form', 'contact-inbox'); ?></h4>
                        <ul>
                            <li><?php esc_html_e('Sends a POST /submit request using the currently enabled service.', 'contact-inbox'); ?></li>
                            <li><?php esc_html_e('Shows live status codes and response payloads so you can copy/paste errors.', 'contact-inbox'); ?></li>
                            <li><?php esc_html_e('Great for verifying that new tokens and rate limits behave before updating production apps.', 'contact-inbox'); ?></li>
                        </ul>
                    </div>

                    <div class="cin-help-item">
                        <h4><?php esc_html_e('Monitor health metrics', 'contact-inbox'); ?></h4>
                        <ul>
                            <li><?php esc_html_e('Calls (24h/7d) update every time the log table records a request.', 'contact-inbox'); ?></li>
                            <li><?php esc_html_e('Success rate compares HTTP 2xx vs total calls; sudden drops usually mean auth failures or schema changes.', 'contact-inbox'); ?></li>
                            <li><?php esc_html_e('Click "View Logs" to drill into individual requests, payloads, and error stacks.', 'contact-inbox'); ?></li>
                        </ul>
                    </div>

                    <div class="cin-help-item">
                        <h4><?php esc_html_e('Rate limiting', 'contact-inbox'); ?></h4>
                        <p><?php esc_html_e('Each site allows 60 requests per minute per WordPress instance. If you exceed that, the API returns HTTP 429 with a Retry-After header.', 'contact-inbox'); ?></p>
                    </div>
                </div>

            <!-- Troubleshooting -->
            <div id="rest-help-troubleshoot" class="cin-help-section">
                <h3><?php esc_html_e('🧯 Troubleshooting', 'contact-inbox'); ?></h3>

                <div class="cin-help-item">
                    <h4><?php esc_html_e('401 Unauthorized', 'contact-inbox'); ?></h4>
                    <p><?php esc_html_e('Token is missing, revoked, or tied to a disabled service.', 'contact-inbox'); ?></p>
                    <ul>
                        <li><?php esc_html_e('Confirm the header name is exactly X-ContactIN-Test-Token.', 'contact-inbox'); ?></li>
                        <li><?php esc_html_e('Check whether the token still appears under Active Tokens.', 'contact-inbox'); ?></li>
                        <li><?php esc_html_e('Ensure the REST API service toggle is enabled.', 'contact-inbox'); ?></li>
                    </ul>
                </div>

                <div class="cin-help-item">
                    <h4><?php esc_html_e('403 Forbidden', 'contact-inbox'); ?></h4>
                    <p><?php esc_html_e('The token exists but the requested route is not in its whitelist.', 'contact-inbox'); ?></p>
                    <ul>
                        <li><?php esc_html_e('Edit the token and include the missing endpoint, or create a new token scoped for that route.', 'contact-inbox'); ?></li>
                    </ul>
                </div>

                <div class="cin-help-item">
                    <h4><?php esc_html_e('404 Not Found', 'contact-inbox'); ?></h4>
                    <p><?php esc_html_e('Usually caused by incorrect base URLs or permalink issues.', 'contact-inbox'); ?></p>
                    <ul>
                        <li><?php esc_html_e('Make sure requests hit /wp-json/contactin/v1/... exactly.', 'contact-inbox'); ?></li>
                        <li><?php esc_html_e('If you recently changed domains, resave WordPress permalinks and re-copy the Base URL.', 'contact-inbox'); ?></li>
                    </ul>
                </div>

                <div class="cin-help-item">
                    <h4><?php esc_html_e('429 Too Many Requests', 'contact-inbox'); ?></h4>
                    <p><?php esc_html_e('The minute-level rate limit was reached.', 'contact-inbox'); ?></p>
                    <ul>
                        <li><?php esc_html_e('Throttle clients to fewer than 60 calls per minute or stagger bursts.', 'contact-inbox'); ?></li>
                        <li><?php esc_html_e('Retry after the number of seconds specified in the Retry-After header.', 'contact-inbox'); ?></li>
                    </ul>
                </div>

                <div class="cin-help-item">
                    <h4><?php esc_html_e('5xx Server Errors', 'contact-inbox'); ?></h4>
                    <p><?php esc_html_e('Indicates WordPress or PHP threw an error before returning a response.', 'contact-inbox'); ?></p>
                    <ul>
                        <li><?php esc_html_e('Open the REST Log entry to view the captured stack trace.', 'contact-inbox'); ?></li>
                        <li><?php esc_html_e('Check WordPress debug.log for plugin/theme conflicts.', 'contact-inbox'); ?></li>
                    </ul>
                </div>
            </div>

            <!-- Security -->
            <div id="rest-help-security" class="cin-help-section">
                <h3><?php esc_html_e('🛡️ Security Best Practices', 'contact-inbox'); ?></h3>

                <div class="cin-help-item">
                    <ul>
                        <li><?php esc_html_e('Rotate tokens quarterly and immediately after off-boarding vendors.', 'contact-inbox'); ?></li>
                        <li><?php esc_html_e('Store tokens in a secrets manager or environment variable, never inside client-side code.', 'contact-inbox'); ?></li>
                        <li><?php esc_html_e('Use separate tokens for Production, Staging, and QA so logs stay isolated.', 'contact-inbox'); ?></li>
                        <li><?php esc_html_e('Limit access by IP using your CDN, WAF, or server firewall when possible.', 'contact-inbox'); ?></li>
                        <li><?php esc_html_e('Disable the REST API service when not actively ingesting data to shrink the attack surface.', 'contact-inbox'); ?></li>
                        <li><?php esc_html_e('Keep WordPress, this plugin, and PHP patched to ensure the REST routes inherit the latest security fixes.', 'contact-inbox'); ?></li>
                    </ul>
                </div>
            </div>
        </div>

        <div class="cin-modal-footer">
            <p><?php esc_html_e('Need more help? Open a support ticket or review the REST log for detailed payloads.', 'contact-inbox'); ?></p>
        </div>
    </div>
</div>

