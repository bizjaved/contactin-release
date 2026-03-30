<?php
namespace ContactInbox\Core;

use ContactInbox\Core\CRMAuth;
use ContactInbox\Core\Repositories\CRMErrorRepository;
use ContactInbox\Core\Repositories\CRMRepository;
use ContactInbox\Core\Repositories\SalesforceAttachmentRepository;
use ContactInbox\Core\Traits\CRMErrorClassifier;
use ContactInbox\Core\Traits\CRMDuplicateResolver;
use ContactInbox\Core\Traits\SalesforceAttachmentHandler;
use ContactInbox\Core\AttachmentHelper;
use ContactInbox\Core\Inbox as CoreInbox;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Handles outbound CRM integration calls.
 *
 * Logs to webhook_log for transport diagnostics and to crm_log for
 * end-to-end success/failure accounting tied to the originating message.
 */
final class CRMConnector
{
    use CRMErrorClassifier;
    use CRMDuplicateResolver;
    use SalesforceAttachmentHandler;

    private CRMErrorRepository $error_repo;
    private CRMRepository $crm_repo;
    private SalesforceAttachmentRepository $attachment_repo;
    private array $connection_config = [];

    public function __construct() {
        $this->error_repo = new CRMErrorRepository();
        $this->crm_repo = new CRMRepository();
        $this->attachment_repo = new SalesforceAttachmentRepository();
    }

    /**
     * Map HTTP codes to CRM semantic status
     */
    private static function map_http_to_crm_status(int $http_code): string
    {
        return match ($http_code) {
            200, 201, 202, 204 => 'delivered',
            401, 403            => 'invalid_token',
            429                 => 'rate_limited',
            400, 402, 404, 405, 406, 408, 409, 410 => 'rejected',
            default             => $http_code >= 500 ? 'server_error' : 'rejected',
        };
    }

    /**
     * Resolve outbound Salesforce timeout (seconds).
     */
    private function get_request_timeout_seconds(array $settings): int
    {
        $configured = (int) ($settings['request_timeout'] ?? 30);
        $filtered = (int) apply_filters('contactin_crm_request_timeout', $configured, $settings);
        return max(15, min(90, $filtered));
    }

    /**
     * Resolve timeout retry attempts for transport-level timeouts.
     */
    private function get_timeout_retry_attempts(array $settings): int
    {
        $configured = (int) ($settings['request_timeout_retries'] ?? 1);
        $filtered = (int) apply_filters('contactin_crm_timeout_retries', $configured, $settings);
        return max(0, min(3, $filtered));
    }

    /**
     * True when a WP transport error appears to be a timeout.
     */
    private function is_timeout_transport_error($response): bool
    {
        if (!is_wp_error($response)) {
            return false;
        }

        $message = strtolower((string) $response->get_error_message());
        return strpos($message, 'curl error 28') !== false
            || strpos($message, 'timed out') !== false
            || strpos($message, 'operation timed out') !== false
            || strpos($message, 'timeout') !== false;
    }

    /**
     * Execute Salesforce HTTP request with timeout-aware retries.
     *
     * Retries are only performed for transport timeout errors.
     * HTTP response codes are returned as-is to existing handlers.
     */
    private function request_salesforce(string $method, string $endpoint, array $args, array $settings)
    {
        $timeout = $this->get_request_timeout_seconds($settings);
        $max_retries = $this->get_timeout_retry_attempts($settings);

        $request_args = array_merge($args, [
            'timeout' => $timeout,
            'method' => strtoupper($method),
        ]);

        $attempt = 0;
        do {
            $response = wp_remote_request($endpoint, $request_args);
            if (!$this->is_timeout_transport_error($response)) {
                return $response;
            }

            if ($attempt >= $max_retries) {
                return $response;
            }

            $wait_us = 250000 * ($attempt + 1);
            usleep($wait_us);
            $attempt++;
        } while (true);
    }

    /**
     * Send payload to configured CRM endpoint with logging and retry logic.
     * PREMIUM FEATURE: CRM integration only in Pro version
     */
    public static function send(array $params, ?int $message_id = null)
    {
        // Gate: CRM sync is premium-only
        if (!\ContactInbox\Integration\FreemiusIntegration::can_use_premium_features()) {
            return new \WP_Error(
                'premium_only',
                __('CRM integration is only available in ContactIn Pro.',  'contactin')
            );
        }

        $instance = new self();
        
        try {
            return $instance->execute_send($params, $message_id);
        } catch (\Throwable $e) {
            // Catch any uncaught exceptions and ensure they're logged
            Logger::critical('CRM Connector fatal exception', [
                'message_id' => $message_id,
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
            ]);
            
            // Ensure log entry exists
            try {
                $instance->crm_repo->insert_log([
                    'message_id' => $message_id ?? 0,
                    'crm_system' => 'salesforce',
                    'operation' => 'sync',
                    'crm_id' => null,
                    'status' => 'failed',
                    'response' => [
                        'fatal_error' => $e->getMessage(),
                        'file' => $e->getFile(),
                        'line' => $e->getLine(),
                    ],
                    'error_message' => 'Fatal exception: ' . $e->getMessage(),
                ]);
            } catch (\Throwable $log_error) {
                // Even logging failed - at least we tried
                Logger::critical('Failed to log CRM error', [
                    'original_error' => $e->getMessage(),
                    'logging_error' => $log_error->getMessage(),
                ]);
            }
            
            return new \WP_Error(
                'crm_fatal_exception',
                'CRM sync failed with exception: ' . $e->getMessage()
            );
        }
    }

    /**
     * Execute CRM send with two-object architecture (Contact + Case/Task)
     */
    private function execute_send(array $params, ?int $message_id = null)
    {
        $settings = CRMSettings::get_settings();

        // Idempotency guard: if this message already logged a delivered sync, skip re-send
        if ($message_id && $this->crm_repo->has_successful_sync($message_id)) {
            Logger::info('CRM sync already delivered, skipping duplicate send', [
                'message_id' => $message_id,
            ]);

            return [
                'success'       => true,
                'crm_status'    => CRMStatus::DELIVERED,
                'http_code'     => 200,
                'response_time' => 0,
                'contact_id'    => null,
                'inquiry_id'    => null,
                'skipped'       => true,
            ];
        }

        if (empty($settings['instance_url'])) {
            $this->log_crm_result($message_id, 'failed', [
                'error_message' => 'Salesforce instance URL not configured.',
            ]);

            return new \WP_Error(
                'crm_no_instance',
                __('Salesforce instance URL not configured.',  'contactin')
            );
        }

        $base_url   = rtrim($settings['instance_url'], '/');
        $api_version = 'v58.0';

        $access_token = CRMAuth::get_access_token();
        if (is_wp_error($access_token)) {
            $this->log_crm_result($message_id, 'failed', [
                'error_message' => $access_token->get_error_message(),
            ]);

            return new \WP_Error('crm_auth_failed', $access_token->get_error_message());
        }

        $headers = [
            'Content-Type'  => 'application/json',
            'Authorization' => 'Bearer ' . $access_token,
        ];

        // Store connection config for attachment queuing
        $this->connection_config = [
            'settings' => $settings,
            'headers' => $headers,
            'base_url' => $base_url,
            'api_version' => $api_version,
        ];

        $start_time = microtime(true);

        $contact_result = $this->upsert_contact($params, $settings, $headers, $base_url, $api_version, $message_id);
        if (is_wp_error($contact_result)) {
            $this->log_crm_result($message_id, 'failed', [
                'error_message' => $contact_result->get_error_message(),
            ]);
            return $contact_result;
        }

        $contact_id = $contact_result['id'];

        $inquiry_result = $this->create_inquiry($params, $settings, $headers, $base_url, $api_version, $contact_id, $message_id);
        if (is_wp_error($inquiry_result)) {
            $this->log_crm_result($message_id, 'failed', [
                'error_message' => $inquiry_result->get_error_message(),
                'response'      => ['contact_id' => $contact_id],
            ]);
            return $inquiry_result;
        }

        $inquiry_id = $inquiry_result['id'];

        // Track queued attachments for logging
        $queued_files = 0;
        $queued_filenames = [];

        // Handle attachment upload if present and enabled - queue as separate item for transparency
        $attachment_sync_enabled = array_key_exists('attachment_sync', $settings) ? !empty($settings['attachment_sync']) : true;
        if (!empty($params['attachment']) && $attachment_sync_enabled && !empty($inquiry_id)) {
            // Only fetch the message if we actually have attachments to process
            $message = null;
            if (!empty($message_id)) {
                try {
                    $message = CoreInbox::instance()->get_message_by_id($message_id);
                } catch (\Exception $e) {
                    Logger::warning('Failed to fetch message for attachment', [
                        'message_id' => $message_id,
                        'error' => $e->getMessage(),
                    ]);
                }
            }
            
            // Convert to array and validate each attachment
            $attachments = is_array($params['attachment']) ? $params['attachment'] : [$params['attachment']];
            foreach ($attachments as $attachment_url_or_path) {
                // Skip empty attachment entries
                if (empty($attachment_url_or_path)) {
                    continue;
                }
                
                // Convert URL to filesystem path if needed
                $attachment_path = $attachment_url_or_path;
                if (strpos($attachment_url_or_path, 'http') === 0 || strpos($attachment_url_or_path, '/') === 0) {
                    // Looks like URL/relative path - convert to filesystem path
                    $converted = AttachmentHelper::url_to_path($attachment_url_or_path);
                    if (!empty($converted)) {
                        $attachment_path = $converted;
                    }
                }
                
                // Validate file exists before attempting to queue
                if (!is_file($attachment_path)) {
                    Logger::warning('Attachment file not found after path conversion, skipping queue', [
                        'original_path' => $attachment_url_or_path,
                        'converted_path' => $attachment_path,
                        'message_id' => $message_id,
                        'inquiry_id' => $inquiry_id,
                    ]);
                    continue; // Skip this file, don't count it as queued
                }
                
                // Queue the attachment with original attachment data for filename resolution
                $was_queued = $this->queue_attachment_upload(
                    $attachment_path,
                    $inquiry_id,
                    $message_id,
                    $message ? $message->attachment : null  // Pass original attachment JSON
                );
                
                // Only count if actually queued
                if ($was_queued === true) {
                    $queued_files++;
                    // Use original filename if available
                    $filename = $message 
                        ? AttachmentHelper::get_original_filename($message->attachment)
                        : basename($attachment_path);
                    if (empty($filename)) {
                        $filename = basename($attachment_path);
                    }
                    $queued_filenames[] = $filename;
                }
            }
            // Note: Attachments queued separately - will appear in queue dashboard if they fail
        } elseif (!empty($params['attachment']) && $attachment_sync_enabled && empty($inquiry_id)) {
            Logger::error('Cannot queue attachments: inquiry_id is empty', [
                'message_id' => $message_id,
            ]);
        }

        $total_time = (int)round((microtime(true) - $start_time) * 1000);

        $this->log_crm_result($message_id, CRMStatus::DELIVERED, [
            'crm_id'   => $inquiry_id,
            'response' => [
                'contact_id'        => $contact_id,
                'inquiry_id'        => $inquiry_id,
                'response_time_ms'  => $total_time,
                'files_queued'      => $queued_files,
                'queued_filenames'  => $queued_filenames,
            ],
        ]);

        return [
            'success'       => true,
            'crm_status'    => 'delivered',
            'http_code'     => 201,
            'response_time' => $total_time,
            'contact_id'    => $contact_id,
            'inquiry_id'    => $inquiry_id,
        ];
    }

    /**
     * Persist a normalized CRM result to crm_log without throwing.
     */
    private function log_crm_result(?int $message_id, string $status, array $context = []): void
    {
        try {
            $response = $context['response'] ?? [];
            $log_data = [
                'message_id'    => $message_id ?? 0,
                'crm_system'    => 'salesforce',
                'operation'     => 'sync',
                'crm_id'        => $context['crm_id'] ?? null,
                'status'        => $status,
                'response'      => $response,
                'error_message' => $context['error_message'] ?? null,
            ];

            // Extract file tracking data from response
            if (isset($response['files_queued'])) {
                $log_data['files_queued'] = (int)$response['files_queued'];
            }
            if (isset($response['queued_filenames']) && is_array($response['queued_filenames'])) {
                $log_data['queued_filenames'] = implode(', ', $response['queued_filenames']);
            }

            $this->crm_repo->insert_log($log_data);
        } catch (\Throwable $e) {
            Logger::error(
                'Failed to log CRM result',
                [
                    'status'      => $status,
                    'message_id'  => $message_id,
                    'error'       => $e->getMessage(),
                ]
            );
        }
    }

    /**
     * Upsert Contact in Salesforce (create or update by email)
     */
    private function upsert_contact(array $params, array $settings, array $headers, string $base_url, string $api_version, ?int $message_id = null)
    {
        $contact_payload = CRMFieldMapper::map_contact_fields($params, $settings);

        if (isset($contact_payload['_error'])) {
            return new \WP_Error(
                'crm_mapping_error',
                $contact_payload['_error_message'] ?? __('Contact field mapping error.',  'contactin')
            );
        }

        // Salesforce upsert by Email endpoint does not allow the Email field in the body
        $contact_payload_to_send = $contact_payload;
        unset($contact_payload_to_send['Email']);
        
        // Remove internal markers used for strategy handling
        unset($contact_payload_to_send['_phone_action']);

        $encoded_email = rawurlencode($params['email']);
        $endpoint = "{$base_url}/services/data/{$api_version}/sobjects/Contact/Email/{$encoded_email}";

        $log_id = $this->crm_repo->insert_log([
            'message_id'    => $message_id ?? 0,
            'crm_system'    => 'salesforce',
            'operation'     => 'contact_upsert',
            'crm_id'        => null,
            'status'        => 'pending',
            'response'      => [
                'endpoint' => $endpoint,
                'payload'  => ['type' => 'Contact', 'data' => $contact_payload_to_send],
            ],
            'error_message' => null,
        ]);

        $response = $this->request_salesforce('PATCH', $endpoint, [
            'headers' => $headers,
            'body'    => json_encode($contact_payload_to_send),
        ], $settings);

        if (!is_wp_error($response) && wp_remote_retrieve_response_code($response) === 401) {
            $access_token = CRMAuth::get_access_token(true);
            if (!is_wp_error($access_token)) {
                $headers['Authorization'] = 'Bearer ' . $access_token;
                $response = $this->request_salesforce('PATCH', $endpoint, [
                    'headers' => $headers,
                    'body'    => json_encode($contact_payload_to_send),
                ], $settings);
            }
        }

        if (is_wp_error($response)) {
            $this->crm_repo->update_log($log_id, [
                'status'        => 'server_error',
                'response'      => [
                    'endpoint' => $endpoint,
                    'payload'  => ['type' => 'Contact', 'data' => $contact_payload_to_send],
                ],
                'error_message' => $response->get_error_message(),
            ]);
            return new \WP_Error('contact_upsert_failed', $response->get_error_message());
        }

        $http_code     = wp_remote_retrieve_response_code($response);
        $response_body = wp_remote_retrieve_body($response);
        $body_data     = json_decode($response_body, true);

        $status = in_array($http_code, [200, 201, 204], true) ? 'delivered' : 'rejected';

        // Handle HTTP 300: Multiple contacts found with same email
        if ($http_code === 300) {
            Logger::info('Multiple contacts found with same email, attempting name match', [
                'email' => $params['email'],
                'response' => $response_body,
            ]);

            $contact_id = $this->resolve_duplicate_contact(
                $params['email'],
                $params['name'] ?? '',
                $response_body,
                $base_url,
                $api_version,
                $headers
            );

            if (is_wp_error($contact_id)) {
                $this->crm_repo->update_log($log_id, [
                    'status'        => 'rejected',
                    'response'      => [
                        'endpoint' => $endpoint,
                        'payload'  => ['type' => 'Contact', 'data' => $contact_payload_to_send],
                        'response_body' => $response_body,
                    ],
                    'error_message' => 'Multiple contacts found, could not resolve duplicate',
                ]);
                return $contact_id;
            }

            $this->crm_repo->update_log($log_id, [
                'crm_id'        => $contact_id,
                'status'        => 'delivered',
                'response'      => [
                    'endpoint' => $endpoint,
                    'payload'  => ['type' => 'Contact', 'data' => $contact_payload_to_send],
                    'response_body' => "Resolved duplicate to: {$contact_id}",
                ],
                'error_message' => null,
            ]);
            return ['id' => $contact_id, 'created' => false, 'resolved_duplicate' => true];
        }

        $status = in_array($http_code, [200, 201, 204], true) ? 'delivered' : 'rejected';

        // Enhanced error message for field mapping issues
        $error_message = null;
        if (!in_array($http_code, [200, 201, 204], true) && !empty($body_data[0]['message'])) {
            $sf_error = $body_data[0]['message'];
            $error_message = $sf_error;
            
            // Detect field mapping errors and provide helpful hints
            if (stripos($sf_error, 'No such column') !== false || 
                stripos($sf_error, 'INVALID_FIELD') !== false) {
                $error_message .= ' [Field Mapping Error: Check CRM Integration → Field Mapping settings. This field doesn\'t exist in your Salesforce Contact object.]';
            }
        }

        $this->crm_repo->update_log($log_id, [
            'crm_id'        => $body_data['id'] ?? null,
            'status'        => $status,
            'response'      => [
                'endpoint' => $endpoint,
                'payload'  => ['type' => 'Contact', 'data' => $contact_payload_to_send],
                'response_body' => $response_body,
            ],
            'error_message' => $error_message,
        ]);

        if (!in_array($http_code, [200, 201, 204], true)) {
            $error_msg = $body_data[0]['message'] ?? $response_body;
            return new \WP_Error('contact_upsert_failed', "Contact upsert failed: {$error_msg}");
        }

        $contact_id = $body_data['id'] ?? null;
        if (!$contact_id) {
            $contact_id = $this->fetch_contact_id_by_email($params['email'], $base_url, $api_version, $headers);
            if (is_wp_error($contact_id)) {
                return $contact_id;
            }
        }

        return ['id' => $contact_id, 'created' => ($http_code === 201)];
    }

    /**
     * Fetch Contact ID by email when upsert returns no body (HTTP 204 on update).
     */
    private function fetch_contact_id_by_email(string $email, string $base_url, string $api_version, array $headers)
    {
        $escaped_email = str_replace("'", "\\'", $email);
        $query = rawurlencode("SELECT Id FROM Contact WHERE Email = '{$escaped_email}' LIMIT 1");
        $query_url = "{$base_url}/services/data/{$api_version}/query?q={$query}";

        $response = $this->request_salesforce('GET', $query_url, [
            'headers' => $headers,
        ], []);

        if (is_wp_error($response)) {
            return new \WP_Error('contact_lookup_failed', $response->get_error_message());
        }

        $code = wp_remote_retrieve_response_code($response);
        $body = json_decode(wp_remote_retrieve_body($response), true);

        if ($code !== 200 || empty($body['records'][0]['Id'])) {
            $detail = $body['message'] ?? 'Contact lookup returned no results';
            return new \WP_Error('contact_lookup_failed', $detail);
        }

        return $body['records'][0]['Id'];
    }

    /**
     * Create Case or Task in Salesforce linked to Contact
     */
    private function create_inquiry(array $params, array $settings, array $headers, string $base_url, string $api_version, string $contact_id, ?int $message_id = null)
    {
        $object_type = $settings['inquiry_object_type'] ?? 'Case';

        $inquiry_payload = CRMFieldMapper::map_inquiry_fields($params, $settings, $contact_id, $object_type);

        if (isset($inquiry_payload['_error'])) {
            return new \WP_Error(
                'crm_mapping_error',
                $inquiry_payload['_error_message'] ?? __('Inquiry field mapping error.',  'contactin')
            );
        }

        $endpoint = "{$base_url}/services/data/{$api_version}/sobjects/{$object_type}/";

        $log_id = $this->crm_repo->insert_log([
            'message_id'    => $message_id ?? 0,
            'crm_system'    => 'salesforce',
            'operation'     => 'inquiry_create',
            'crm_id'        => null,
            'status'        => 'pending',
            'response'      => [
                'endpoint' => $endpoint,
                'payload'  => ['type' => $object_type, 'data' => $inquiry_payload],
            ],
            'error_message' => null,
        ]);

        $response = $this->request_salesforce('POST', $endpoint, [
            'headers' => $headers,
            'body'    => json_encode($inquiry_payload),
        ], $settings);

        if (!is_wp_error($response) && wp_remote_retrieve_response_code($response) === 401) {
            $access_token = CRMAuth::get_access_token(true);
            if (!is_wp_error($access_token)) {
                $headers['Authorization'] = 'Bearer ' . $access_token;
                $response = $this->request_salesforce('POST', $endpoint, [
                    'headers' => $headers,
                    'body'    => json_encode($inquiry_payload),
                ], $settings);
            }
        }

        if (is_wp_error($response)) {
            $this->crm_repo->update_log($log_id, [
                'status'        => 'server_error',
                'response'      => [
                    'endpoint' => $endpoint,
                    'payload'  => ['type' => $object_type, 'data' => $inquiry_payload],
                ],
                'error_message' => $response->get_error_message(),
            ]);
            return new \WP_Error('inquiry_create_failed', $response->get_error_message());
        }

        $http_code     = wp_remote_retrieve_response_code($response);
        $response_body = wp_remote_retrieve_body($response);
        $body_data     = json_decode($response_body, true);

        // Enhanced error message for field mapping issues
        $error_message = null;
        if ($http_code !== 201 && !empty($body_data[0]['message'])) {
            $sf_error = $body_data[0]['message'];
            $error_message = $sf_error;
            
            // Detect field mapping errors and provide helpful hints
            if (stripos($sf_error, 'No such column') !== false || 
                stripos($sf_error, 'INVALID_FIELD') !== false) {
                $error_message .= ' [Field Mapping Error: Check CRM Integration → Field Mapping settings. Remove any custom fields that don\'t exist in your Salesforce instance.]';
            }
        }

        $this->crm_repo->update_log($log_id, [
            'crm_id'        => $body_data['id'] ?? null,
            'status'        => ($http_code === 201) ? 'delivered' : 'rejected',
            'response'      => [
                'endpoint' => $endpoint,
                'payload'  => ['type' => $object_type, 'data' => $inquiry_payload],
                'response_body' => $response_body,
            ],
            'error_message' => $error_message,
        ]);

        if ($http_code !== 201) {
            $error_msg = $body_data[0]['message'] ?? $response_body;
            return new \WP_Error('inquiry_create_failed', "{$object_type} creation failed: {$error_msg}");
        }

        $inquiry_id = $body_data['id'] ?? null;
        if (!$inquiry_id) {
            return new \WP_Error('no_inquiry_id', "{$object_type} ID not returned from Salesforce");
        }

        return ['id' => $inquiry_id];
    }

    /**
     * Public method to retry a previously failed attachment upload.
     * 
     * This is called by the queue processor (CronJobs::process_attachment_retry)
     * to handle failed attachment uploads with automatic exponential backoff.
     * 
     * Unlike handle_attachment_sync(), this does NOT queue failures - it returns
     * the result directly to the queue manager to handle retry logic.
     *
     * @param string $attachment_path Path to local file
     * @param string $case_id Salesforce Case/Task ID
     * @param array $settings CRM settings
     * @param array $headers Authorization headers
     * @param string $base_url Salesforce instance URL
     * @param string $api_version Salesforce API version
     * @param int|null $message_id Message ID for logging
     * @param int $log_id Attachment log ID (for tracking)
     *
     * @return array|WP_Error Array with success info or WP_Error with detailed failure reason
     */
    public function upload_attachment_to_case_retry(
        string $attachment_path,
        string $case_id,
        array $settings,
        array $headers,
        string $base_url,
        string $api_version,
        ?int $message_id = null,
        int $log_id = 0,
        string $filename = ''
    ) {
        // Use provided filename or fall back to basename
        if (empty($filename)) {
            $filename = basename($attachment_path);
        }
        
        // Log the attachment upload attempt to CRM log for visibility
        $crm_log_id = $this->crm_repo->insert_log([
            'message_id'    => $message_id ?? 0,
            'crm_system'    => 'salesforce',
            'operation'     => 'file_sync',
            'crm_id'        => $case_id,
            'status'        => 'pending',
            'response'      => [
                'filename' => $filename,
                'case_id' => $case_id,
                'attachment_log_id' => $log_id,
            ],
            'error_message' => null,
        ]);

        // Call the trait's private method through the class instance
        $result = $this->upload_attachment_to_case(
            $attachment_path,
            $case_id,
            $settings,
            $headers,
            $base_url,
            $api_version,
            $message_id,
            $filename  // Pass the original filename
        );

        // If successful, update both attachment log and CRM log
        if (!is_wp_error($result) && $log_id > 0) {
            $this->attachment_repo->update_status(
                $log_id,
                'delivered',
                $result['content_version_id'] ?? null,
                $result['content_document_id'] ?? null,
                null
            );

            // Update CRM log with success
            $this->crm_repo->update_log($crm_log_id, [
                'status' => 'delivered',
                'response' => [
                    'filename' => $filename,
                    'case_id' => $case_id,
                    'attachment_log_id' => $log_id,
                    'content_version_id' => $result['content_version_id'] ?? null,
                    'content_document_id' => $result['content_document_id'] ?? null,
                    'file_size' => $result['file_size'] ?? null,
                ],
                'error_message' => null,
            ]);

            Logger::info('Attachment retry successful - logs updated', [
                'log_id' => $log_id,
                'crm_log_id' => $crm_log_id,
                'message_id' => $message_id,
                'content_version_id' => $result['content_version_id'] ?? null,
            ]);
        } elseif (is_wp_error($result)) {
            // Update CRM log with failure
            $this->crm_repo->update_log($crm_log_id, [
                'status' => 'failed',
                'response' => [
                    'filename' => $filename,
                    'case_id' => $case_id,
                    'attachment_log_id' => $log_id,
                ],
                'error_message' => $result->get_error_message(),
            ]);
            
            Logger::warning('Attachment upload failed', [
                'log_id' => $log_id,
                'crm_log_id' => $crm_log_id,
                'message_id' => $message_id,
                'error' => $result->get_error_message(),
            ]);
        }

        return $result;
    }

    /**
     * Queue attachment upload for separate processing (New Design - Gold Standard).
     * 
     * Instead of uploading inline, we queue it as a separate item so:
     * 1. Failures are transparent in queue dashboard
     * 2. Automatic retry with exponential backoff
     * 3. Manual retry capability from maintenance page
     * 4. Doesn't block main CRM sync if attachment is slow/fails
     * 
     * @param string $attachment_path Full path to attachment file
     * @param string $case_id Salesforce Case ID to attach to
     * @param int|null $message_id Related message ID for tracking
     * @param mixed $attachment_data Original attachment JSON data (for resolving filename)
     * @return bool True if successfully queued, false otherwise
     */
    private function queue_attachment_upload(
        string $attachment_path,
        string $case_id,
        ?int $message_id = null,
        $attachment_data = null
    ): bool {
        // Validate file exists
        if (!is_file($attachment_path)) {
            Logger::error('Attachment file not found for queuing', [
                'path' => $attachment_path,
                'case_id' => $case_id,
                'message_id' => $message_id,
            ]);
            return false;
        }

        // Resolve original filename using AttachmentHelper if data provided
        // Otherwise fall back to basename (backward compatibility)
        $filename = $attachment_data 
            ? AttachmentHelper::get_original_filename($attachment_data)
            : '';
        
        if (empty($filename)) {
            $filename = basename($attachment_path);
        }

        $file_size = filesize($attachment_path);

        // Create initial log entry with 'queued' status
        $log_id = $this->attachment_repo->log_attachment(
            $message_id ?? 0,
            $filename,
            $file_size,
            $case_id,
            null,
            null,
            'queued',
            null
        );

        if ($log_id <= 0) {
            Logger::warning('Attachment log insert failed, queuing without log', [
                'case_id' => $case_id,
                'filename' => $filename,
                'message_id' => $message_id,
            ]);
        }

        $dedupe_key = ($log_id > 0)
            ? 'attachment_' . $log_id
            : 'attachment_fallback_' . substr(sha1($case_id . '|' . (string)$message_id . '|' . $attachment_path), 0, 12);

        // Push to queue for background processing with all necessary connection config
        $queue_id = \ContactInbox\Core\QueueManager::push(
            'attachment_retry',
            [
                'attachment_path' => $attachment_path,
                'case_id' => $case_id,
                'message_id' => $message_id,
                'log_id' => $log_id,
                'filename' => $filename,
                // Include API connection config needed by queue processor
                'settings' => $this->connection_config['settings'] ?? [],
                'headers' => $this->connection_config['headers'] ?? [],
                'base_url' => $this->connection_config['base_url'] ?? '',
                'api_version' => $this->connection_config['api_version'] ?? '',
            ],
            $dedupe_key,
            3 // Priority 3 - high priority (right after CRM sync priority 2)
        );

        if (is_wp_error($queue_id)) {
            Logger::error('Failed to queue attachment upload', [
                'error' => $queue_id->get_error_message(),
                'case_id' => $case_id,
                'filename' => $filename,
            ]);
            
            // Update log to failed if it was created
            if ($log_id > 0) {
                $this->attachment_repo->update_status(
                    $log_id,
                    'failed',
                    null,
                    null,
                    'Failed to queue: ' . $queue_id->get_error_message()
                );
            }
            return false;
        }
        
        Logger::info('Attachment queued for upload', [
            'queue_id' => $queue_id,
            'log_id' => $log_id,
            'case_id' => $case_id,
            'filename' => $filename,
            'message_id' => $message_id,
        ]);
        
        return true;
    }

    /**
     * Handle attachment upload to Salesforce (non-blocking, with queue-based retry support).
     * 
     * DEPRECATED: This method is kept for backwards compatibility but attachments
     * are now queued immediately via queue_attachment_upload() for better transparency.
     * 
     * Failed uploads are queued for automatic retry using the existing queue system
     * with exponential backoff. This prevents temporary API failures from blocking the sync.
     */
    private function handle_attachment_sync(
        string $attachment_path,
        string $case_id,
        array $settings,
        array $headers,
        string $base_url,
        string $api_version,
        ?int $message_id = null
    ): void {
        // Log the attempt
        $filename = basename($attachment_path);
        $file_size = is_file($attachment_path) ? filesize($attachment_path) : 0;

        // Create initial log entry
        $log_id = $this->attachment_repo->log_attachment(
            $message_id ?? 0,
            $filename,
            $file_size,
            $case_id,
            null,
            null,
            'pending',
            null
        );

        // Attempt upload
        $result = $this->upload_attachment_to_case(
            $attachment_path,
            $case_id,
            $settings,
            $headers,
            $base_url,
            $api_version,
            $message_id,
            $filename  // Pass the filename
        );

        // Handle result
        if (is_wp_error($result)) {
            $error_msg = $result->get_error_message();
            
            // Mark as failed in attachment log
            $this->attachment_repo->update_status(
                $log_id,
                'failed',
                null,
                null,
                $error_msg
            );
            
            // Queue for retry using existing queue system
            \ContactInbox\Core\QueueManager::push(
                'attachment_retry',
                [
                    'log_id' => $log_id,
                    'message_id' => $message_id,
                    'attachment_path' => $attachment_path,
                    'case_id' => $case_id,
                    'filename' => $filename,
                    'file_size' => $file_size,
                    'settings' => $settings,
                    'headers' => $headers,
                    'base_url' => $base_url,
                    'api_version' => $api_version,
                    'last_error' => $error_msg,
                ],
                'attachment_' . $log_id,
                4  // Low priority - process after email/CRM
            );
            
            Logger::warning(
                'Attachment upload failed - queued for retry',
                [
                    'log_id' => $log_id,
                    'message_id' => $message_id,
                    'filename' => $filename,
                    'case_id' => $case_id,
                    'error' => $error_msg,
                ]
            );
        } else {
            // Success
            $this->attachment_repo->update_status(
                $log_id,
                'delivered',
                $result['content_version_id'],
                $result['content_document_id'],
                null
            );
            
            Logger::info(
                'Attachment successfully uploaded to Salesforce',
                [
                    'log_id' => $log_id,
                    'message_id' => $message_id,
                    'filename' => $filename,
                    'content_version_id' => $result['content_version_id'],
                    'content_document_id' => $result['content_document_id'],
                ]
            );
        }
    }
}
