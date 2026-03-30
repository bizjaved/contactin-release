<?php
// phpcs:disable Generic.PHP.ForbiddenFunctions.Found, PluginCheck.CodeAnalysis.DiscouragedFunctions.load_plugin_textdomainFound, PluginCheck.CodeAnalysis.Heredoc.NotAllowed, PluginCheck.Security.DirectDB.UnescapedDBParameter, Squiz.PHP.DiscouragedFunctions.Discouraged, WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.NamingConventions.PrefixAllGlobals.DynamicHooknameFound, WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound, WordPress.PHP.DevelopmentFunctions.error_log_debug_backtrace, WordPress.WP.AlternativeFunctions.file_system_operations_fsockopen, WordPress.WP.AlternativeFunctions.file_system_operations_readfile, WordPress.WP.AlternativeFunctions.file_system_operations_rmdir, WordPress.WP.EnqueuedResourceParameters.MissingVersion, WordPress.WP.EnqueuedResources.NonEnqueuedScript, WordPress.WP.I18n.MissingArgDomain, WordPress.WP.I18n.UnorderedPlaceholdersPlural, WordPress.WP.I18n.UnorderedPlaceholdersSingle
/**
 * Salesforce Attachment Handler Trait
 * 
 * Handles uploading file attachments from WordPress to Salesforce Cases/Tasks
 * via ContentVersion and ContentDocumentLink APIs.
 *
 * @package ContactIn\Core\Traits
 */

declare(strict_types=1);

namespace ContactInbox\Core\Traits;

use ContactInbox\Core\Config;
use ContactInbox\Core\Logger;
use ContactInbox\Core\DB;

if (!defined('ABSPATH')) {
    exit;
}

trait SalesforceAttachmentHandler {
    
    /**
     * Upload attachment to Salesforce Case/Task via ContentVersion API.
     *
     * Two-step process:
     * 1. Create ContentVersion (the file)
     * 2. Create ContentDocumentLink (link file to Case)
     *
     * Non-blocking: if attachment upload fails, sync continues.
     *
     * @param string $attachment_path Path to local file
     * @param string $case_id Salesforce Case/Task ID to link file to
     * @param array $settings CRM settings containing max_attachment_size_mb, etc.
     * @param array $headers Authorization headers for Salesforce API
     * @param string $base_url Salesforce instance URL
     * @param string $api_version Salesforce API version (e.g., 'v58.0')
     * @param int|null $message_id Message ID for logging
     * @param string $filename Original filename (if empty, basename is used)
     *
     * @return array|WP_Error Array with success info or WP_Error
     */
    protected function upload_attachment_to_case(
        string $attachment_path,
        string $case_id,
        array $settings,
        array $headers,
        string $base_url,
        string $api_version,
        ?int $message_id = null,
        string $filename = ''
    ) {
        // Step 1: Validate file exists
        if (!is_file($attachment_path)) {
            Logger::warning(
                'Attachment file not found for Salesforce upload',
                [
                    'message_id' => $message_id,
                    'path' => $attachment_path,
                    'case_id' => $case_id,
                ]
            );
            return new \WP_Error(
                'attachment_not_found',
                sprintf('Attachment file not found: %s', $attachment_path)
            );
        }

        // Step 2: Check file size
        $file_size = filesize($attachment_path);
        $max_size_bytes = ($settings['max_attachment_size_mb'] ?? 50) * 1024 * 1024;
        
        if ($file_size > $max_size_bytes) {
            Logger::warning(
                'Attachment file too large for Salesforce upload',
                [
                    'message_id' => $message_id,
                    'file_size' => $file_size,
                    'max_size' => $max_size_bytes,
                    'case_id' => $case_id,
                ]
            );
            return new \WP_Error(
                'attachment_too_large',
                sprintf(
                    'File too large (%s). Max: %dMB',
                    size_format($file_size),
                    $settings['max_attachment_size_mb'] ?? 50
                )
            );
        }

        // Step 3: Read file content
        $file_content = file_get_contents($attachment_path);
        if ($file_content === false) {
            Logger::error(
                'Failed to read attachment file for Salesforce upload',
                [
                    'message_id' => $message_id,
                    'path' => $attachment_path,
                ]
            );
            return new \WP_Error(
                'attachment_read_failed',
                'Failed to read attachment file'
            );
        }

        // Step 4: Get filename and MIME type
        // Use provided filename or fall back to basename of path
        if (empty($filename)) {
            $filename = basename($attachment_path);
        }
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime_type = $finfo ? finfo_file($finfo, $attachment_path) : 'application/octet-stream';
        if ($finfo) {
            finfo_close($finfo);
        }

        // Step 5: Create ContentVersion (upload file to Salesforce)
        $content_version_result = $this->create_content_version(
            $filename,
            $file_content,
            $settings,
            $headers,
            $base_url,
            $api_version,
            $message_id
        );

        if (is_wp_error($content_version_result)) {
            Logger::warning(
                'ContentVersion creation failed',
                [
                    'message_id' => $message_id,
                    'error' => $content_version_result->get_error_message(),
                ]
            );
            return $content_version_result;
        }

        $content_version_id = $content_version_result['id'];
        $content_document_id = $content_version_result['content_document_id'];

        // Step 6: Create ContentDocumentLink (link file to Case)
        $link_result = $this->create_content_document_link(
            $content_document_id,
            $case_id,
            $settings,
            $headers,
            $base_url,
            $api_version,
            $message_id
        );

        if (is_wp_error($link_result)) {
            Logger::warning(
                'ContentDocumentLink creation failed - file uploaded but not linked',
                [
                    'message_id' => $message_id,
                    'error' => $link_result->get_error_message(),
                ]
            );
            // Don't fail - file is uploaded even if link fails
        }

        Logger::info(
            'Attachment uploaded to Salesforce',
            [
                'message_id' => $message_id,
                'case_id' => $case_id,
                'content_version_id' => $content_version_id,
                'content_document_id' => $content_document_id,
                'filename' => $filename,
                'file_size' => $file_size,
            ]
        );

        return [
            'success' => true,
            'content_version_id' => $content_version_id,
            'content_document_id' => $content_document_id,
            'filename' => $filename,
            'file_size' => $file_size,
            'case_id' => $case_id,
        ];
    }

    /**
     * Create ContentVersion in Salesforce (upload the actual file).
     *
     * @return array|WP_Error Array with 'id' and 'content_document_id' or error
     */
    protected function create_content_version(
        string $filename,
        string $file_content,
        array $settings,
        array $headers,
        string $base_url,
        string $api_version,
        ?int $message_id = null
    ) {
        $file_content_b64 = base64_encode($file_content);

        // Extract title from filename (remove extension)
        $title = preg_replace('/\.[^.]*$/', '', $filename);

        $payload = [
            'Title' => $title,
            'VersionData' => $file_content_b64,
            'PathOnClient' => '/' . $filename,
            'ContentLocation' => 'S', // Store in Salesforce library
        ];

        $endpoint = "{$base_url}/services/data/{$api_version}/sobjects/ContentVersion";

        $response = wp_remote_post($endpoint, [
            'method' => 'POST',
            'headers' => $headers,
            'body' => json_encode($payload),
            'timeout' => 90,  // Increased from 60 to 90 seconds for large files
            'sslverify' => apply_filters('https_local_over_ssl', false),  // Handle SSL issues
        ]);

        if (is_wp_error($response)) {
            Logger::error(
                'ContentVersion API request failed',
                [
                    'message_id' => $message_id,
                    'error_code' => $response->get_error_code(),
                    'error_message' => $response->get_error_message(),
                ]
            );
            return new \WP_Error(
                'content_version_create_failed',
                'Salesforce API request failed: ' . $response->get_error_message()
            );
        }

        $http_code = wp_remote_retrieve_response_code($response);
        if (!in_array($http_code, [200, 201, 204], true)) {
            $body = json_decode(wp_remote_retrieve_body($response), true);
            $error_msg = is_array($body) && isset($body[0]['message']) 
                ? $body[0]['message'] 
                : wp_remote_retrieve_body($response);

            Logger::warning(
                'ContentVersion creation failed',
                [
                    'message_id' => $message_id,
                    'http_code' => $http_code,
                    'response_body' => substr($error_msg, 0, 500),
                ]
            );

            return new \WP_Error(
                'content_version_failed',
                "Salesforce ContentVersion creation failed (HTTP {$http_code}): {$error_msg}"
            );
        }

        $body_data = json_decode(wp_remote_retrieve_body($response), true);
        $content_version_id = $body_data['id'] ?? null;

        if (empty($content_version_id)) {
            Logger::error(
                'ContentVersion ID missing from response',
                [
                    'message_id' => $message_id,
                    'response' => $body_data,
                ]
            );
            return new \WP_Error(
                'content_version_id_missing',
                'Salesforce did not return ContentVersion ID'
            );
        }

        // Step: Query for ContentDocumentId
        // Salesforce needs a moment to index the record (increased from 1 to 2 seconds for reliability)
        sleep(2);

        $content_document_id = $this->fetch_content_document_id(
            $content_version_id,
            $headers,
            $base_url,
            $api_version,
            $message_id
        );

        if (is_wp_error($content_document_id)) {
            return $content_document_id;
        }

        return [
            'id' => $content_version_id,
            'content_document_id' => $content_document_id,
        ];
    }

    /**
     * Query for ContentDocumentId from ContentVersion ID with retry logic.
     *
     * @return string|WP_Error ContentDocumentId or error
     */
    protected function fetch_content_document_id(
        string $content_version_id,
        array $headers,
        string $base_url,
        string $api_version,
        ?int $message_id = null
    ) {
        $soql = "SELECT ContentDocumentId FROM ContentVersion WHERE Id = '{$content_version_id}' LIMIT 1";
        $query_url = "{$base_url}/services/data/{$api_version}/query?q=" . urlencode($soql);

        // Retry logic: Salesforce might need time to index
        $max_attempts = 3;
        $attempt = 0;
        
        while ($attempt < $max_attempts) {
            $attempt++;
            
            $response = wp_remote_get($query_url, [
                'headers' => $headers,
                'timeout' => 45,
                'sslverify' => apply_filters('https_local_over_ssl', false),
            ]);

            if (is_wp_error($response)) {
                Logger::warning(
                    'Failed to query ContentDocumentId (attempt ' . $attempt . '/' . $max_attempts . ')',
                    [
                        'message_id' => $message_id,
                        'error_code' => $response->get_error_code(),
                        'error_message' => $response->get_error_message(),
                        'attempt' => $attempt,
                    ]
                );
                
                // Wait before retry
                if ($attempt < $max_attempts) {
                    sleep($attempt);  // 1 second, 2 seconds, etc.
                    continue;
                }
                
                return new \WP_Error(
                    'query_failed',
                    'Could not query ContentDocumentId after ' . $max_attempts . ' attempts: ' . $response->get_error_message()
                );
            }

            $http_code = wp_remote_retrieve_response_code($response);
            if ($http_code !== 200) {
                $body = json_decode(wp_remote_retrieve_body($response), true);
                $error_msg = is_array($body) && isset($body['message']) 
                    ? $body['message'] 
                    : wp_remote_retrieve_body($response);

                Logger::warning(
                    'ContentDocumentId query failed (attempt ' . $attempt . '/' . $max_attempts . ')',
                    [
                        'message_id' => $message_id,
                        'http_code' => $http_code,
                        'error' => substr($error_msg, 0, 500),
                        'attempt' => $attempt,
                    ]
                );
                
                // Wait before retry
                if ($attempt < $max_attempts) {
                    sleep($attempt);
                    continue;
                }
                
                return new \WP_Error('query_failed', "Query failed (HTTP {$http_code}): {$error_msg}");
            }

            // Success - parse result
            $body_data = json_decode(wp_remote_retrieve_body($response), true);
            $content_document_id = $body_data['records'][0]['ContentDocumentId'] ?? null;

            if (empty($content_document_id)) {
                Logger::warning(
                    'ContentDocumentId not found in query results (attempt ' . $attempt . '/' . $max_attempts . ')',
                    [
                        'message_id' => $message_id,
                        'content_version_id' => $content_version_id,
                        'record_count' => count($body_data['records'] ?? []),
                        'attempt' => $attempt,
                    ]
                );
                
                // Record not yet indexed - retry
                if ($attempt < $max_attempts) {
                    sleep($attempt);
                    continue;
                }
                
                return new \WP_Error(
                    'content_document_id_missing',
                    'ContentDocumentId not found after ' . $max_attempts . ' attempts'
                );
            }

            Logger::debug('ContentDocumentId retrieved', [
                'message_id' => $message_id,
                'content_version_id' => $content_version_id,
                'content_document_id' => $content_document_id,
                'attempt' => $attempt,
            ]);

            return $content_document_id;
        }
        
        return new \WP_Error('query_failed', 'Query failed after all retry attempts');
    }

    /**
     * Create ContentDocumentLink to link file to Case/Task with retry logic.
     *
     * @return array|WP_Error Array with success info or error
     */
    protected function create_content_document_link(
        string $content_document_id,
        string $case_id,
        array $settings,
        array $headers,
        string $base_url,
        string $api_version,
        ?int $message_id = null
    ) {
        $visibility = $settings['attachment_visibility'] ?? 'AllUsers';

        $payload = [
            'ContentDocumentId' => $content_document_id,
            'LinkedEntityId' => $case_id,
            'ShareType' => 'V', // Viewer permission
            'Visibility' => $visibility,
        ];

        $endpoint = "{$base_url}/services/data/{$api_version}/sobjects/ContentDocumentLink";

        $response = wp_remote_post($endpoint, [
            'method' => 'POST',
            'headers' => $headers,
            'body' => json_encode($payload),
            'timeout' => 45,
            'sslverify' => apply_filters('https_local_over_ssl', false),
        ]);

        if (is_wp_error($response)) {
            Logger::warning(
                'ContentDocumentLink API request failed',
                [
                    'message_id' => $message_id,
                    'case_id' => $case_id,
                    'content_document_id' => $content_document_id,
                    'error_code' => $response->get_error_code(),
                    'error_message' => $response->get_error_message(),
                ]
            );
            return new \WP_Error(
                'content_document_link_failed',
                'API request failed: ' . $response->get_error_message()
            );
        }

        $http_code = wp_remote_retrieve_response_code($response);
        if (!in_array($http_code, [200, 201, 204], true)) {
            $body = json_decode(wp_remote_retrieve_body($response), true);
            $error_msg = is_array($body) && isset($body[0]['message']) 
                ? $body[0]['message'] 
                : wp_remote_retrieve_body($response);

            Logger::warning(
                'ContentDocumentLink creation failed',
                [
                    'message_id' => $message_id,
                    'case_id' => $case_id,
                    'http_code' => $http_code,
                    'error_msg' => substr($error_msg, 0, 500),
                ]
            );

            return new \WP_Error(
                'content_document_link_failed',
                "ContentDocumentLink creation failed (HTTP {$http_code}): {$error_msg}"
            );
        }

        Logger::debug('ContentDocumentLink created successfully', [
            'message_id' => $message_id,
            'case_id' => $case_id,
            'content_document_id' => $content_document_id,
        ]);

        return ['success' => true];
    }
}
