<?php
namespace ContactInbox\Core;

class Message {
    public int $id;
    public ?string $salutation;
    public string $name;
    public string $email;
    public ?int $contact_id;
    public string $subject;
    public string $message;
    public string $status;
    public bool $is_archived;
    public string $submitted_at;
    public ?string $ip_address;
    public ?string $attachment;
    public ?string $gdpr_link;
    public ?string $gdpr_expires_at;
    public ?float $recaptcha_score;

    // Multi-phone support (future-proof, not yet in DB)
    public ?string $phone;
    public ?string $mobile_phone;
    public ?string $home_phone;
    public ?string $other_phone;

    // Phase 2: Message-centric processing status (Queue Redesign)
    public ?string $admin_email_status;
    public ?string $user_email_status;
    public ?string $crm_status;
    public ?string $admin_email_sent_at;
    public ?string $user_email_sent_at;
    public ?string $crm_synced_at;
    public ?string $admin_email_error;
    public ?string $user_email_error;
    public ?string $crm_error;
    public int $admin_email_retries;
    public int $user_email_retries;
    public int $crm_retries;

    // Additional status fields used in admin modal
    public ?string $email_status = null;
    public ?string $email_error_message = null;

    // Intent Classification
    public string $intent_category;
    public ?float $intent_confidence;
    public ?string $intent_keywords;
    public ?string $intent_classified_at;

    public function __construct(object $row) {
        $this->id           = (int) ($row->id ?? 0);
        $this->salutation   = $row->salutation ?? null;
        $this->name         = (string) ($row->name ?? '');
        $this->contact_id   = isset($row->contact_id) ? (int) $row->contact_id : null;
        $this->email        = (string) ($row->email ?? '');
        $this->subject      = (string) ($row->subject ?? '');
        $this->message      = (string) ($row->message ?? '');
        $this->status       = (string) ($row->status ?? 'unread');
        $this->is_archived  = (bool) ($row->is_archived ?? false);
        $this->submitted_at = (string) ($row->submitted_at ?? '');
        $this->ip_address   = $row->ip_address ?? null;
        $this->attachment   = $row->attachment ?? null;
        $this->gdpr_link    = $row->gdpr_link ?? null;
        $this->gdpr_expires_at = $row->gdpr_expires_at ?? null;
        $this->recaptcha_score = isset($row->recaptcha_score) ? (float) $row->recaptcha_score : null;

        // Multi-phone support (future-proof, not yet in DB)
        $this->phone        = $row->phone ?? null;
        $this->mobile_phone = $row->mobile_phone ?? null;
        $this->home_phone   = $row->home_phone ?? null;
        $this->other_phone  = $row->other_phone ?? null;

        // Phase 2: Load message processing statuses
        $this->admin_email_status = $row->admin_email_status ?? null;
        $this->user_email_status = $row->user_email_status ?? null;
        $this->crm_status = $row->crm_status ?? null;
        $this->admin_email_sent_at = $row->admin_email_sent_at ?? null;
        $this->user_email_sent_at = $row->user_email_sent_at ?? null;
        $this->crm_synced_at = $row->crm_synced_at ?? null;
        $this->admin_email_error = $row->admin_email_error ?? null;
        $this->user_email_error = $row->user_email_error ?? null;
        $this->crm_error = $row->crm_error ?? null;
        $this->admin_email_retries = (int) ($row->admin_email_retries ?? 0);
        $this->user_email_retries = (int) ($row->user_email_retries ?? 0);
        $this->crm_retries = (int) ($row->crm_retries ?? 0);

        // Intent Classification
        $this->intent_category = (string) ($row->intent_category ?? 'unclassified');
        $this->intent_confidence = isset($row->intent_confidence) ? (float) $row->intent_confidence : null;
        $this->intent_keywords = $row->intent_keywords ?? null;
        $this->intent_classified_at = $row->intent_classified_at ?? null;
    }

    public function get_display_name(): string {
        return NameFormatter::display($this->salutation ?? '', $this->name ?? '');
    }

    /**
     * Return normalized attachment info for templates.
     */
    public function get_attachment(): ?array {
        if (empty($this->attachment)) {
            return null;
        }

        // Try to decode as JSON first (new format with attachment_info)
        $decoded = json_decode($this->attachment, true);
        
        if (is_array($decoded) && !empty($decoded['path'])) {
            // JSON format: return decoded array with name and size from the data
            $name = $decoded['name'] ?? basename($decoded['path']);
            $size = $decoded['size'] ?? '';
            return [
                'url'  => wp_nonce_url(
                    admin_url('admin-ajax.php?action=ci_download_attachment&id=' . $this->id),
                    Config::NONCE_ACTION,
                    'nonce'
                ),
                'name' => $name,
                'size' => $size,
                'path' => $decoded['path'],
            ];
        }
        
        // Fallback to old format: treat as plain path string
        $name = basename($this->attachment);
        return [
            'url'  => wp_nonce_url(
                admin_url('admin-ajax.php?action=ci_download_attachment&id=' . $this->id),
                Config::NONCE_ACTION,
                'nonce'
            ),
            'name' => $name,
            'size' => '',
            'path' => $this->attachment,
        ];
    }

}
