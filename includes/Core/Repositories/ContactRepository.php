<?php
/**
 * Contact Repository
 *
 * Handles CRUD for contacts using wpdb (guarded, sanitized).
 */

declare(strict_types=1);

namespace ContactInbox\Core\Repositories;

use ContactInbox\Core\Contact;
use ContactInbox\Core\Config;
use ContactInbox\Core\PhoneUtils;

// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter

if (!defined('ABSPATH')) exit;

final class ContactRepository {
    private string $table_contacts;

    public function __construct() {
        global $wpdb;
        $this->table_contacts = $wpdb->prefix . Config::TABLE_CONTACTS;
    }

    public function get_by_id(int $id): ?Contact {
        global $wpdb;
        $row = $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM {$this->table_contacts} WHERE id = %d", $id)
        );
        return $row ? new Contact($row) : null;
    }

    public function find_by_email(string $email): ?Contact {
        global $wpdb;
        $email = sanitize_email($email);
        if ($email === '') {
            return null;
        }
        $row = $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM {$this->table_contacts} WHERE email = %s", $email)
        );
        return $row ? new Contact($row) : null;
    }

    public function find_by_phone(string $phone, string $default_country = '1'): ?Contact {
        global $wpdb;
        $normalized = PhoneUtils::normalize($phone, $default_country);
        if ($normalized === '') {
            return null;
        }
        $row = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$this->table_contacts} WHERE primary_phone = %s OR mobile_phone = %s OR home_phone = %s OR other_phone = %s LIMIT 1",
                $normalized,
                $normalized,
                $normalized,
                $normalized
            )
        );
        return $row ? new Contact($row) : null;
    }

    public function insert(array $data): int|false {
        global $wpdb;

        $prepared = [
            'salutation'    => isset($data['salutation']) ? sanitize_text_field($data['salutation']) : null,
            'name'          => sanitize_text_field($data['name'] ?? ''),
            'email'         => isset($data['email']) ? sanitize_email($data['email']) : null,
            'primary_phone' => isset($data['primary_phone']) ? sanitize_text_field($data['primary_phone']) : null,
            'mobile_phone'  => isset($data['mobile_phone']) ? sanitize_text_field($data['mobile_phone']) : null,
            'home_phone'    => isset($data['home_phone']) ? sanitize_text_field($data['home_phone']) : null,
            'other_phone'   => isset($data['other_phone']) ? sanitize_text_field($data['other_phone']) : null,
            'source'        => isset($data['source']) ? sanitize_text_field($data['source']) : null,
            'last_message_at' => isset($data['last_message_at']) ? sanitize_text_field($data['last_message_at']) : null,
        ];

        $format = ['%s','%s','%s','%s','%s','%s','%s','%s'];
        $result = $wpdb->insert($this->table_contacts, $prepared, $format);
        return $result ? (int) $wpdb->insert_id : false;
    }

    public function update(int $id, array $data): bool {
        global $wpdb;

        $update = [];
        if (isset($data['salutation'])) $update['salutation'] = sanitize_text_field($data['salutation']);
        if (isset($data['name'])) $update['name'] = sanitize_text_field($data['name']);
        if (isset($data['email'])) $update['email'] = sanitize_email($data['email']);
        if (isset($data['primary_phone'])) $update['primary_phone'] = sanitize_text_field($data['primary_phone']);
        if (isset($data['mobile_phone'])) $update['mobile_phone'] = sanitize_text_field($data['mobile_phone']);
        if (isset($data['home_phone'])) $update['home_phone'] = sanitize_text_field($data['home_phone']);
        if (isset($data['other_phone'])) $update['other_phone'] = sanitize_text_field($data['other_phone']);
        if (isset($data['source'])) $update['source'] = sanitize_text_field($data['source']);
        if (isset($data['crm_sync_status'])) $update['crm_sync_status'] = sanitize_text_field($data['crm_sync_status']);
        if (isset($data['last_message_at'])) $update['last_message_at'] = sanitize_text_field($data['last_message_at']);

        if (empty($update)) {
            return false;
        }

        $result = $wpdb->update(
            $this->table_contacts,
            $update,
            ['id' => $id],
            null,
            ['%d']
        );
        return $result !== false;
    }

    /**
     * Queue a contact for CRM deletion if it has synced messages
     *
     * This is a centralized helper method that checks if a contact has synced messages
     * and queues it for deletion in CRM systems. Used by both delete() and delete_with_messages().
     * 
     * CRITICAL: Always creates GDPR log entry for compliance, regardless of sync settings.
     * When sync is disabled, marks log as 'manual_required' for audit trail.     * 
     * CRM LOGGING BEHAVIOR (operation='contact_delete'):
     * - Sync disabled: status='skipped', note='CRM deletion sync disabled in settings'
     * - Dedup detected: status='completed', note='Skipped - already deleted from CRM (deduplication)'
     * - Normally queued: status='pending', note='Queued for CRM deletion'
     * - Queue push failed: status='failed', error_message contains details
     * - Processed by cron: status='delivered', response contains Salesforce result (see CronJobs.php)     *
     * @param int $contact_id Contact ID
     * @param object|null $contact Pre-fetched contact object (optional)
     * @return void
     */
    private function queue_contact_for_crm_deletion(int $contact_id, ?object $contact = null): void {
        global $wpdb;
        $table_messages = $wpdb->prefix . Config::TABLE_MESSAGES;
        $logger_class = 'ContactInbox\\Core\\Logger';
        $gdpr_repo_class = 'ContactInbox\\Core\\Repositories\\GDPRRepository';

        // Get contact if not provided
        if (!$contact) {
            $contact = $wpdb->get_row($wpdb->prepare(
                "SELECT id, email, name, crm_id FROM {$this->table_contacts} WHERE id = %d",
                $contact_id
            ));
        }

        if (!$contact) {
            return;
        }

        // Get message statistics
        $message_stats = $wpdb->get_row($wpdb->prepare(
            "SELECT 
                COUNT(*) as total,
                SUM(CASE WHEN crm_status IN (%s, %s, %s, %s) THEN 1 ELSE 0 END) as synced,
                SUM(CASE WHEN crm_status NOT IN (%s, %s, %s, %s) OR crm_status IS NULL THEN 1 ELSE 0 END) as unsynced
             FROM {$table_messages} 
             WHERE contact_id = %d",
            Config::CRM_SENT,
            'delivered',
            'success',
            'complete',
            Config::CRM_SENT,
            'delivered',
            'success',
            'complete',
            $contact_id
        ));

        $has_synced_messages = (int)($message_stats->synced ?? 0);
        $messages_synced = (int)($message_stats->synced ?? 0);
        $messages_unsynced = (int)($message_stats->unsynced ?? 0);

        // If no synced messages, no CRM action needed
        if ($has_synced_messages <= 0) {
            return;
        }

        // Get CRM settings
        $crm_settings = \ContactInbox\Core\CRMSettings::get_settings();
        $delete_sync_enabled = !empty($crm_settings['crm_delete_sync']);

        // ALWAYS create GDPR log entry for compliance (audit trail)
        if (class_exists($gdpr_repo_class)) {
            $gdpr_repo = new $gdpr_repo_class();
            
            // Determine initial CRM sync status
            $crm_sync_status = $delete_sync_enabled ? 'synced' : 'manual_required';
            $error_message = $delete_sync_enabled ? null : 'CRM deletion sync disabled; delete in Salesforce manually.';
            
            $log_data = [
                'contact_id' => $contact_id,
                'crm_id' => $contact->crm_id ?? null,
                'email' => $contact->email ?? '',
                'name' => $contact->name ?? '',
                'crm_sync_status' => $crm_sync_status,
                'messages_deleted' => 0, // Actual deletion happens in calling methods
                'messages_synced' => $messages_synced,
                'messages_unsynced' => $messages_unsynced,
                'files_deleted' => 0,
                'deletion_status' => 'completed',
                'error_message' => $error_message,
                'deleted_by' => get_current_user_id(),
                'deleted_at' => current_time('mysql'),
            ];
            
            $gdpr_repo->log_deletion($log_data);
            $log_id = $wpdb->insert_id;
            
            if (class_exists($logger_class)) {
                $logger_class::debug('GDPR deletion log created', [
                    'log_id' => $log_id,
                    'contact_id' => $contact_id,
                    'email' => $contact->email,
                    'crm_sync_status' => $crm_sync_status,
                    'delete_sync_enabled' => $delete_sync_enabled,
                ]);
            }
        }

        // If sync disabled, write CRM log entry and exit (GDPR log already created above)
        if (!$delete_sync_enabled) {
            // Always write CRM log for audit trail, even when sync disabled
            $crm_repo_class = 'ContactInbox\\Core\\Repositories\\CRMRepository';
            if (class_exists($crm_repo_class)) {
                $crm_repo = new $crm_repo_class();
                $crm_repo->insert_log([
                    'message_id' => 0,
                    'crm_system' => 'salesforce',
                    'operation' => 'contact_delete',
                    'crm_id' => $contact->crm_id ?? null,
                    'status' => 'skipped',
                    'response' => [
                        'contact_id' => $contact_id,
                        'crm_id' => $contact->crm_id ?? null,
                        'email' => $contact->email,
                        'name' => $contact->name,
                        'note' => 'CRM deletion sync disabled in settings',
                        'gdpr_log_id' => $log_id ?? null,
                        'messages_synced' => $messages_synced,
                    ],
                    'error_message' => null,
                ]);
            }

            if (class_exists($logger_class)) {
                $logger_class::info('CRM deletion sync disabled; GDPR log marked manual_required', [
                    'contact_id' => $contact_id,
                    'email' => $contact->email,
                    'messages_synced' => $messages_synced,
                ]);
            }
            return;
        }

        // Sync enabled: Queue for CRM deletion
        try {
            $queue_manager_class = 'ContactInbox\\Core\\QueueManager';

            if (!class_exists($queue_manager_class)) {
                return;
            }

            // Queue ONE deletion for the Salesforce Contact
            $payload = [
                'contact_id'  => $contact->crm_id ?? $contact->id,
                'crm_id'      => $contact->crm_id ?? null,
                'email'       => $contact->email,
                'name'        => $contact->name,
                'operation'   => 'crm_delete',
                'gdpr_log_id' => $log_id ?? null,
            ];

            $queue_message_id = isset($log_id) ? (string) $log_id : (string) $contact->id;

            $queue_id = $queue_manager_class::push(
                'crm_delete',
                $payload,
                $queue_message_id,
                2  // priority (high priority for deletions)
            );

            if (!is_wp_error($queue_id)) {
                $queue_status = 'pending';
                $queue_last_error = null;

                $queue_repo_class = 'ContactInbox\\Core\\Repositories\\QueueRepository';
                if (class_exists($queue_repo_class)) {
                    $queue_repo = new $queue_repo_class();
                    $queue_item = $queue_repo->get_by_id((int) $queue_id);
                    if (is_array($queue_item)) {
                        $queue_status = (string) ($queue_item['status'] ?? 'pending');
                        $queue_last_error = $queue_item['last_error'] ?? null;
                    }
                }

                if ($queue_status === 'completed') {
                    // Queue dedup detected already-deleted contact - write CRM log immediately
                    $crm_repo_class = 'ContactInbox\\Core\\Repositories\\CRMRepository';
                    if (class_exists($crm_repo_class)) {
                        $crm_repo = new $crm_repo_class();
                        $crm_repo->insert_log([
                            'message_id' => 0,
                            'crm_system' => 'salesforce',
                            'operation' => 'contact_delete',
                            'crm_id' => $contact->crm_id ?? null,
                            'status' => 'completed',
                            'response' => [
                                'contact_id' => $contact_id,
                                'crm_id' => $contact->crm_id ?? null,
                                'email' => $contact->email,
                                'name' => $contact->name,
                                'note' => 'Skipped - already deleted from CRM (deduplication)',
                                'gdpr_log_id' => $log_id ?? null,
                                'queue_id' => $queue_id,
                            ],
                            'error_message' => null,
                        ]);
                    }

                    if (isset($log_id) && class_exists($gdpr_repo_class)) {
                        $gdpr_repo = new $gdpr_repo_class();
                        $gdpr_repo->update_deletion_status(
                            (int) $log_id,
                            'completed',
                            is_string($queue_last_error) && $queue_last_error !== '' ? substr($queue_last_error, 0, 500) : null
                        );

                        if (is_string($queue_last_error) && strpos($queue_last_error, 'already deleted from CRM') !== false) {
                            $wpdb->update(
                                $wpdb->prefix . Config::TABLE_GDPR_DELETION_LOG,
                                ['crm_sync_status' => 'deleted'],
                                ['id' => (int) $log_id],
                                ['%s'],
                                ['%d']
                            );
                        }
                    }

                    if (class_exists($logger_class)) {
                        $logger_class::info('CRM deletion queue skipped by deduplication rules', [
                            'contact_id' => $contact_id,
                            'email' => $contact->email,
                            'queue_id' => $queue_id,
                            'queue_status' => $queue_status,
                            'queue_last_error' => $queue_last_error,
                            'gdpr_log_id' => $log_id ?? null,
                        ]);
                    }
                } else {
                    // Normal queueing - write CRM log with pending status
                    $crm_repo_class = 'ContactInbox\\Core\\Repositories\\CRMRepository';
                    if (class_exists($crm_repo_class)) {
                        $crm_repo = new $crm_repo_class();
                        $crm_repo->insert_log([
                            'message_id' => 0,
                            'crm_system' => 'salesforce',
                            'operation' => 'contact_delete',
                            'crm_id' => $contact->crm_id ?? null,
                            'status' => 'pending',
                            'response' => [
                                'contact_id' => $contact_id,
                                'crm_id' => $contact->crm_id ?? null,
                                'email' => $contact->email,
                                'name' => $contact->name,
                                'note' => 'Queued for CRM deletion',
                                'gdpr_log_id' => $log_id ?? null,
                                'queue_id' => $queue_id,
                            ],
                            'error_message' => null,
                        ]);
                    }

                    if (isset($log_id) && class_exists($gdpr_repo_class)) {
                        $gdpr_repo = new $gdpr_repo_class();
                        $gdpr_repo->mark_deletion_queued($log_id);
                    }

                    \ContactInbox\Core\QueueTrigger::maybe_trigger_crm_processor();
                }
            } else {
                // Queue push failed - write CRM log with error status
                $crm_repo_class = 'ContactInbox\\Core\\Repositories\\CRMRepository';
                if (class_exists($crm_repo_class)) {
                    $crm_repo = new $crm_repo_class();
                    $crm_repo->insert_log([
                        'message_id' => 0,
                        'crm_system' => 'salesforce',
                        'operation' => 'contact_delete',
                        'crm_id' => $contact->crm_id ?? null,
                        'status' => 'failed',
                        'response' => [
                            'contact_id' => $contact_id,
                            'crm_id' => $contact->crm_id ?? null,
                            'email' => $contact->email,
                            'name' => $contact->name,
                            'gdpr_log_id' => $log_id ?? null,
                        ],
                        'error_message' => 'Failed to queue CRM deletion: ' . $queue_id->get_error_message(),
                    ]);
                }

                if (isset($log_id) && class_exists($gdpr_repo_class)) {
                    $gdpr_repo = new $gdpr_repo_class();
                    $gdpr_repo->update_deletion_status(
                        (int) $log_id,
                        'completed',
                        substr('CRM delete queue push failed: ' . $queue_id->get_error_message(), 0, 500)
                    );
                }

                if (class_exists($logger_class)) {
                    $logger_class::error('Failed to queue contact for CRM deletion', [
                        'contact_id' => $contact_id,
                        'email' => $contact->email,
                        'queue_error' => $queue_id->get_error_message(),
                        'gdpr_log_id' => $log_id ?? null,
                    ]);
                }
            }

            if (class_exists($logger_class)) {
                $logger_class::debug('Contact queued for CRM deletion', [
                    'contact_id' => $contact_id,
                    'email' => $contact->email,
                    'synced_messages' => $has_synced_messages,
                    'queue_id' => is_wp_error($queue_id) ? 'ERROR' : $queue_id,
                    'gdpr_log_id' => $log_id ?? null,
                ]);
            }
        } catch (\Exception $e) {
            if (class_exists($logger_class)) {
                $logger_class::error('Failed to queue contact for CRM deletion', [
                    'contact_id' => $contact_id,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }

    /**
     * Delete a contact and queue for CRM deletion if it was synced
     *
     * This is the centralized contact deletion method that ensures ALL contact
     * deletions (regardless of whether messages are deleted) properly queue the
     * contact for deletion in CRM systems like Salesforce.
     *
     * @param int $id Contact ID to delete
     * @return bool True if contact was deleted successfully
     */
    public function delete(int $id): bool {
        global $wpdb;

        // Get contact info for CRM queueing before we delete it
        $contact = $wpdb->get_row($wpdb->prepare(
            "SELECT id, email, name, crm_id FROM {$this->table_contacts} WHERE id = %d",
            $id
        ));

        if (!$contact) {
            return false;
        }

        // Queue for CRM deletion if contact has synced messages
        $this->queue_contact_for_crm_deletion($id, $contact);

        // Delete the contact from database
        return (bool) $wpdb->delete($this->table_contacts, ['id' => $id], ['%d']);
    }

    public function count(string $search = ''): int {
        global $wpdb;
        $where = '';
        $params = [];
        if ($search !== '') {
            $like = '%' . $wpdb->esc_like($search) . '%';
            $where = "WHERE name LIKE %s OR email LIKE %s";
            $params = [$like, $like];
        }
        $sql = "SELECT COUNT(*) FROM {$this->table_contacts} {$where}";
        return (int) ($params ? $wpdb->get_var($wpdb->prepare($sql, ...$params)) : $wpdb->get_var($sql));
    }

    public function get_paginated(int $page = 1, int $per_page = 20, string $search = '', string $orderby = 'updated_at', string $order = 'DESC'): array {
        global $wpdb;
        $offset = max(0, ($page - 1) * $per_page);
        $order = strtoupper($order) === 'ASC' ? 'ASC' : 'DESC';
        $allowed_orderby = ['name','email','last_message_at','created_at','updated_at'];
        if (!in_array($orderby, $allowed_orderby, true)) {
            $orderby = 'updated_at';
        }

        $where = '';
        $params = [];
        if ($search !== '') {
            $like = '%' . $wpdb->esc_like($search) . '%';
            $where = "WHERE name LIKE %s OR email LIKE %s";
            $params = [$like, $like];
        }

        $sql = "SELECT * FROM {$this->table_contacts} {$where} ORDER BY {$orderby} {$order} LIMIT %d OFFSET %d";
        $params[] = $per_page;
        $params[] = $offset;

        $rows = $params ? $wpdb->get_results($wpdb->prepare($sql, ...$params)) : $wpdb->get_results($sql);
        return array_map(fn($row) => new Contact($row), $rows ?: []);
    }

    public function touch_last_message(int $id, string $when): bool {
        global $wpdb;
        $result = $wpdb->update(
            $this->table_contacts,
            ['last_message_at' => $when],
            ['id' => $id],
            ['%s'],
            ['%d']
        );
        return $result !== false;
    }

    /**
     * Update Salesforce Contact ID only if not already stored
     * 
     * After a successful CRM sync, store the Salesforce Contact ID if we don't already have it.
     * This prevents unnecessary database writes and ensures we capture the ID early for future lookups.
     *
     * @param int $contact_id Local contact ID
     * @param string $salesforce_id Salesforce Contact ID (typically 15 or 18 character alphanumeric)
     * @return bool True if updated or already had value, false on error
     */
    public function update_crm_id_if_missing(int $contact_id, string $salesforce_id): bool {
        global $wpdb;
        
        if (empty($salesforce_id)) {
            return false;
        }
        
        // Check if crm_id is already set
        $existing = $wpdb->get_var($wpdb->prepare(
            "SELECT crm_id FROM {$this->table_contacts} WHERE id = %d",
            $contact_id
        ));
        
        // If already has a value, no need to update
        if (!empty($existing)) {
            return true; // Already has the value
        }
        
        // Only update if crm_id is NULL/empty
        $result = $wpdb->update(
            $this->table_contacts,
            ['crm_id' => sanitize_text_field($salesforce_id)],
            ['id' => $contact_id],
            ['%s'],
            ['%d']
        );
        
        return $result !== false;
    }

    /**
     * Update Salesforce Contact ID if changed (handles Salesforce ID updates)
     * 
     * Salesforce IDs rarely change, but this handles the edge case where:
     * - ID changed in Salesforce
     * - Contact was migrated/recreated
     * - Data correction was needed
     *
     * @param int $contact_id Local contact ID
     * @param string $salesforce_id New Salesforce Contact ID
     * @return array ['updated' => bool, 'old_id' => ?string, 'new_id' => ?string]
     */
    public function update_crm_id_if_changed(int $contact_id, string $salesforce_id): array {
        global $wpdb;
        
        if (empty($salesforce_id)) {
            return ['updated' => false, 'old_id' => null, 'new_id' => null];
        }
        
        $sanitized_id = sanitize_text_field($salesforce_id);
        
        // Get current stored crm_id
        $existing = $wpdb->get_var($wpdb->prepare(
            "SELECT crm_id FROM {$this->table_contacts} WHERE id = %d",
            $contact_id
        ));
        
        // If ID is identical to what we already have, no update needed
        if (!empty($existing) && $existing === $sanitized_id) {
            return ['updated' => false, 'old_id' => $existing, 'new_id' => $sanitized_id];
        }
        
        // Update if different (including initial population from NULL)
        $result = $wpdb->update(
            $this->table_contacts,
            ['crm_id' => $sanitized_id],
            ['id' => $contact_id],
            ['%s'],
            ['%d']
        );
        
        if ($result !== false) {
            return ['updated' => true, 'old_id' => $existing, 'new_id' => $sanitized_id];
        }
        
        return ['updated' => false, 'old_id' => $existing, 'new_id' => $sanitized_id];
    }

    /**
     * Check if contact exists
     *
     * @param int $id Contact ID
     * @return bool True if contact exists
     */
    public function exists(int $id): bool {
        global $wpdb;
        $count = $wpdb->get_var(
            $wpdb->prepare("SELECT COUNT(*) FROM {$this->table_contacts} WHERE id = %d", $id)
        );
        return (int) $count > 0;
    }

    /**
     * Delete contact and all associated messages (including file attachments)
     *
     * Uses standardized message deletion that includes attachment file cleanup.
     * Follows the same pattern as single message deletion + bulk deletion.
     *
     * @param int $contact_id Contact ID to delete
     * @return array Returns ['contact_deleted' => bool, 'messages_deleted' => int, 'attachments_deleted' => int]
     */
    public function delete_with_messages(int $contact_id): array {
        global $wpdb;
        $table_messages = $wpdb->prefix . Config::TABLE_MESSAGES;
        $logger_class = 'ContactInbox\\Core\\Logger';

        // Get contact info for CRM deletion (only need to delete Contact once, not each message)
        $contact = $wpdb->get_row($wpdb->prepare(
            "SELECT id, email, name, crm_id FROM {$this->table_contacts} WHERE id = %d",
            $contact_id
        ));
        
        // Step 1: Get all messages for this contact to extract attachments BEFORE deletion
        $messages = $wpdb->get_results($wpdb->prepare(
            "SELECT id, attachment FROM {$table_messages} WHERE contact_id = %d",
            $contact_id
        ));
        
        $attachments_deleted = 0;
        $failed_attachments = [];

        // Step 2: Delete attachment files using standardized AttachmentHelper (with retry logic)
        if (!empty($messages)) {
            $attachment_helper_class = 'ContactInbox\\Core\\AttachmentHelper';
            
            if (class_exists($attachment_helper_class)) {
                $all_attachment_paths = [];
                
                // Extract all attachment paths from messages
                foreach ($messages as $msg) {
                    if (!empty($msg->attachment)) {
                        $paths = $attachment_helper_class::extract_file_paths($msg->attachment);
                        $all_attachment_paths = array_merge($all_attachment_paths, $paths);
                    }
                }
                
                // Delete files using robust method with retry logic
                if (!empty($all_attachment_paths)) {
                    $deletion_results = $attachment_helper_class::delete_files_safely($all_attachment_paths, 3);
                    $attachments_deleted = count($deletion_results['deleted'] ?? []);
                    $failed_attachments = $deletion_results['failed'] ?? [];
                    
                    if (!empty($failed_attachments)) {
                        if (class_exists($logger_class)) {
                            $logger_class::warning('Some attachment files could not be deleted during contact deletion', [
                                'contact_id' => $contact_id,
                                'total_files' => count($all_attachment_paths),
                                'failed' => $failed_attachments,
                            ]);
                        }
                    }
                }
            }
        }
        
        // Step 3: Queue contact for CRM deletion (using centralized method)
        $this->queue_contact_for_crm_deletion($contact_id, $contact);

        // Step 4: Delete messages from database
        $messages_result = $wpdb->delete($table_messages, ['contact_id' => $contact_id], ['%d']);
        $messages_deleted = $messages_result !== false ? (int) $messages_result : 0;

        // Step 5: Delete contact
        $contact_result = $wpdb->delete($this->table_contacts, ['id' => $contact_id], ['%d']);
        $contact_deleted = $contact_result !== false;

        return [
            'contact_deleted' => $contact_deleted,
            'messages_deleted' => $messages_deleted,
            'attachments_deleted' => $attachments_deleted,
        ];
    }
}
