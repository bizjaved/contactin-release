<?php
declare(strict_types=1);

namespace ContactInbox\Core\Repositories;

/**
 * Salesforce Attachment Repository - Stub for Free Version
 * 
 * Salesforce attachment sync is a Pro-only feature.
 * This stub prevents fatal errors where the class is referenced.
 */
final class SalesforceAttachmentRepository {

    /**
     * Get status counts (stub)
     */
    public function get_status_counts(): array {
        return [
            'pending' => 0,
            'completed' => 0,
            'failed' => 0,
        ];
    }
}
