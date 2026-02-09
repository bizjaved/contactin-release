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
            <h2><?php _e('Contact Inbox - REST API Guide', Config::TEXTDOMAIN); ?></h2>
            <button type="button" class="cin-modal-close" aria-label="<?php _e('Close', Config::TEXTDOMAIN); ?>">
                <span aria-hidden="true">&times;</span>
            </button>
        </div>

        <div class="cin-modal-body">
            <!-- Quick Navigation -->
            <div class="cin-help-nav">
                <h3><?php _e('Quick Navigation', Config::TEXTDOMAIN); ?></h3>
                <ul>
                    <li><a href="#rest-help-overview" class="cin-help-link"><?php _e('Overview', Config::TEXTDOMAIN); ?></a></li>
                    <li><a href="#rest-help-service" class="cin-help-link"><?php _e('Service Toggle', Config::TEXTDOMAIN); ?></a></li>
                    <li><a href="#rest-help-tokens" class="cin-help-link"><?php _e('Token Management', Config::TEXTDOMAIN); ?></a></li>
                    <li><a href="#rest-help-endpoints" class="cin-help-link"><?php _e('Base URL & Endpoints', Config::TEXTDOMAIN); ?></a></li>
                    <li><a href="#rest-help-testing" class="cin-help-link"><?php _e('Testing & Monitoring', Config::TEXTDOMAIN); ?></a></li>
                    <li><a href="#rest-help-troubleshoot" class="cin-help-link"><?php _e('Troubleshooting', Config::TEXTDOMAIN); ?></a></li>
                    <li><a href="#rest-help-security" class="cin-help-link"><?php _e('Security Best Practices', Config::TEXTDOMAIN); ?></a></li>
                </ul>
            </div>

            <!-- API Endpoints -->
            <div id="rest-help-endpoints" class="cin-help-section">
                <h3><?php _e('📡 Base URL & Endpoints', Config::TEXTDOMAIN); ?></h3>

                <div class="cin-help-item">
                    <p><?php _e('All endpoints are prefixed with your WordPress REST API base:', Config::TEXTDOMAIN); ?></p>
                    <code><?php echo esc_html($rest_base_url); ?></code>
                </div>

                <div class="cin-help-item">
                    <h4><?php _e('Available Endpoints', Config::TEXTDOMAIN); ?></h4>
                    <table class="wp-list-table widefat fixed striped">
                        <thead>
                            <tr>
                                <th><?php _e('Method', Config::TEXTDOMAIN); ?></th>
                                <th><?php _e('Path', Config::TEXTDOMAIN); ?></th>
                                <th><?php _e('Description', Config::TEXTDOMAIN); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td>POST</td>
                                <td>/submit</td>
                                <td><?php _e('Create a new inbox message (name, email, subject, message, attachments).', Config::TEXTDOMAIN); ?></td>
                            </tr>
                            <tr>
                                <td>GET</td>
                                <td>/search</td>
                                <td><?php _e('Filter submissions by keywords, tags, or date range.', Config::TEXTDOMAIN); ?></td>
                            </tr>
                            <tr>
                                <td>GET</td>
                                <td>/status/:id</td>
                                <td><?php _e('Get current status of a specific message.', Config::TEXTDOMAIN); ?></td>
                            </tr>
                            <tr>
                                <td>PUT</td>
                                <td>/status/:id</td>
                                <td><?php _e('Change status values like Open, Responded, Archived.', Config::TEXTDOMAIN); ?></td>
                            </tr>
                            <tr>
                                <td>DELETE</td>
                                <td>/delete/:id</td>
                                <td><?php _e('Remove a message (obeys WordPress capability checks).', Config::TEXTDOMAIN); ?></td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Testing & Monitoring -->
                <div id="rest-help-testing" class="cin-help-section">
                    <h3><?php _e('🧪 Testing & Monitoring', Config::TEXTDOMAIN); ?></h3>

                    <div class="cin-help-item">
                        <h4><?php _e('Use the built-in Test API form', Config::TEXTDOMAIN); ?></h4>
                        <ul>
                            <li><?php _e('Sends a POST /submit request using the currently enabled service.', Config::TEXTDOMAIN); ?></li>
                            <li><?php _e('Shows live status codes and response payloads so you can copy/paste errors.', Config::TEXTDOMAIN); ?></li>
                            <li><?php _e('Great for verifying that new tokens and rate limits behave before updating production apps.', Config::TEXTDOMAIN); ?></li>
                        </ul>
                    </div>

                    <div class="cin-help-item">
                        <h4><?php _e('Monitor health metrics', Config::TEXTDOMAIN); ?></h4>
                        <ul>
                            <li><?php _e('Calls (24h/7d) update every time the log table records a request.', Config::TEXTDOMAIN); ?></li>
                            <li><?php _e('Success rate compares HTTP 2xx vs total calls; sudden drops usually mean auth failures or schema changes.', Config::TEXTDOMAIN); ?></li>
                            <li><?php _e('Click "View Logs" to drill into individual requests, payloads, and error stacks.', Config::TEXTDOMAIN); ?></li>
                        </ul>
                    </div>

                    <div class="cin-help-item">
                        <h4><?php _e('Rate limiting', Config::TEXTDOMAIN); ?></h4>
                        <p><?php _e('Each site allows 60 requests per minute per WordPress instance. If you exceed that, the API returns HTTP 429 with a Retry-After header.', Config::TEXTDOMAIN); ?></p>
                    </div>
                </div>

            <!-- Troubleshooting -->
            <div id="rest-help-troubleshoot" class="cin-help-section">
                <h3><?php _e('🧯 Troubleshooting', Config::TEXTDOMAIN); ?></h3>

                <div class="cin-help-item">
                    <h4><?php _e('401 Unauthorized', Config::TEXTDOMAIN); ?></h4>
                    <p><?php _e('Token is missing, revoked, or tied to a disabled service.', Config::TEXTDOMAIN); ?></p>
                    <ul>
                        <li><?php _e('Confirm the header name is exactly X-ContactIN-Test-Token.', Config::TEXTDOMAIN); ?></li>
                        <li><?php _e('Check whether the token still appears under Active Tokens.', Config::TEXTDOMAIN); ?></li>
                        <li><?php _e('Ensure the REST API service toggle is enabled.', Config::TEXTDOMAIN); ?></li>
                    </ul>
                </div>

                <div class="cin-help-item">
                    <h4><?php _e('403 Forbidden', Config::TEXTDOMAIN); ?></h4>
                    <p><?php _e('The token exists but the requested route is not in its whitelist.', Config::TEXTDOMAIN); ?></p>
                    <ul>
                        <li><?php _e('Edit the token and include the missing endpoint, or create a new token scoped for that route.', Config::TEXTDOMAIN); ?></li>
                    </ul>
                </div>

                <div class="cin-help-item">
                    <h4><?php _e('404 Not Found', Config::TEXTDOMAIN); ?></h4>
                    <p><?php _e('Usually caused by incorrect base URLs or permalink issues.', Config::TEXTDOMAIN); ?></p>
                    <ul>
                        <li><?php _e('Make sure requests hit /wp-json/contactin/v1/... exactly.', Config::TEXTDOMAIN); ?></li>
                        <li><?php _e('If you recently changed domains, resave WordPress permalinks and re-copy the Base URL.', Config::TEXTDOMAIN); ?></li>
                    </ul>
                </div>

                <div class="cin-help-item">
                    <h4><?php _e('429 Too Many Requests', Config::TEXTDOMAIN); ?></h4>
                    <p><?php _e('The minute-level rate limit was reached.', Config::TEXTDOMAIN); ?></p>
                    <ul>
                        <li><?php _e('Throttle clients to fewer than 60 calls per minute or stagger bursts.', Config::TEXTDOMAIN); ?></li>
                        <li><?php _e('Retry after the number of seconds specified in the Retry-After header.', Config::TEXTDOMAIN); ?></li>
                    </ul>
                </div>

                <div class="cin-help-item">
                    <h4><?php _e('5xx Server Errors', Config::TEXTDOMAIN); ?></h4>
                    <p><?php _e('Indicates WordPress or PHP threw an error before returning a response.', Config::TEXTDOMAIN); ?></p>
                    <ul>
                        <li><?php _e('Open the REST Log entry to view the captured stack trace.', Config::TEXTDOMAIN); ?></li>
                        <li><?php _e('Check WordPress debug.log for plugin/theme conflicts.', Config::TEXTDOMAIN); ?></li>
                    </ul>
                </div>
            </div>

            <!-- Security -->
            <div id="rest-help-security" class="cin-help-section">
                <h3><?php _e('🛡️ Security Best Practices', Config::TEXTDOMAIN); ?></h3>

                <div class="cin-help-item">
                    <ul>
                        <li><?php _e('Rotate tokens quarterly and immediately after off-boarding vendors.', Config::TEXTDOMAIN); ?></li>
                        <li><?php _e('Store tokens in a secrets manager or environment variable, never inside client-side code.', Config::TEXTDOMAIN); ?></li>
                        <li><?php _e('Use separate tokens for Production, Staging, and QA so logs stay isolated.', Config::TEXTDOMAIN); ?></li>
                        <li><?php _e('Limit access by IP using your CDN, WAF, or server firewall when possible.', Config::TEXTDOMAIN); ?></li>
                        <li><?php _e('Disable the REST API service when not actively ingesting data to shrink the attack surface.', Config::TEXTDOMAIN); ?></li>
                        <li><?php _e('Keep WordPress, this plugin, and PHP patched to ensure the REST routes inherit the latest security fixes.', Config::TEXTDOMAIN); ?></li>
                    </ul>
                </div>
            </div>
        </div>

        <div class="cin-modal-footer">
            <p><?php _e('Need more help? Open a support ticket or review the REST log for detailed payloads.', Config::TEXTDOMAIN); ?></p>
        </div>
    </div>
</div>

