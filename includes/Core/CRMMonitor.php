<?php
declare(strict_types=1);

namespace ContactInbox\Core;

/**
 * CRM Monitor - Stub for Free Version
 * 
 * CRM monitoring and health checks are Pro-only features.
 * This stub prevents fatal errors where the class is referenced.
 */
final class CRMMonitor {

    /**
     * Get CRM statistics (stub)
     */
    public static function get_statistics(): array {
        return [
            'success_rate' => 0,
            'total_syncs' => 0,
            'failed_syncs' => 0,
            'pending_syncs' => 0,
        ];
    }

    /**
     * Get health status (stub)
     */
    public static function get_health_status(): array {
        return [
            'status' => 'disabled',
            'message' => 'CRM features are available in Contact Inbox Pro',
            'last_sync' => null,
        ];
    }
}
