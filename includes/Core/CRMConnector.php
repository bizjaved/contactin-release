<?php
declare(strict_types=1);

namespace ContactInbox\Core;

/**
 * CRM Connector - Stub for Free Version
 * 
 * CRM connection and sync operations are Pro-only features.
 * This stub prevents fatal errors where the class is referenced.
 */
final class CRMConnector {

    /**
     * Sync message to CRM (stub)
     */
    public static function sync($message_id, $operation = 'create'): bool {
        return false; // Pro feature
    }

    /**
     * Test connection (stub)
     */
    public static function test_connection(): array {
        return [
            'success' => false,
            'message' => 'CRM features are available in ContactIn Pro',
        ];
    }
}
