<?php
/**
 * Core – Email Log Item model
 *
 * @package ContactInbox\Admin\Core
 * @since   1.0.0
 */

namespace ContactInbox\Core;

final class EmailLogItem {
    public int    $id;
    public string $recipient;
    public string $subject;
    public string $status;
    public string $error_message;
    public string $created_at;
    public string $type; 

    public function __construct( array $data ) {
        $this->id            = (int) ( $data['id'] ?? 0 );
        $this->recipient     = (string) ( $data['recipient'] ?? '' );
        $this->subject       = (string) ( $data['subject'] ?? '' );
        $this->status        = (string) ( $data['status'] ?? 'pending' );
        $this->error_message = (string) ( $data['error_message'] ?? '' );
        $this->created_at    = (string) ( $data['created_at'] ?? '' );
        $this->type          = (string) ( $data['type'] ?? EmailLog::TYPE_CONTACT_FORM );
    }

    public function get_subject_label(): string {
        $labels = EmailLog::get_type_labels();

        // Prefer type mapping first
        if (isset($labels[$this->type])) {
            return $labels[$this->type];
        }

        // Fallback: match by subject
        foreach ($labels as $constant => $label) {
            if ($this->subject === $constant || $this->subject === $label) {
                return $label;
            }
        }

        return $this->subject;
    }
}
