<?php
declare(strict_types=1);

namespace ContactInbox\Core;

use ContactInbox\Traits\Singleton;

/**
 * CRM Settings - Stub for Free Version
 * 
 * CRM settings management is a Pro-only feature.
 * This stub prevents fatal errors where the class is referenced.
 */
final class CRMSettings {
    use Singleton;

    /**
     * Get CRM settings (stub)
     */
    public static function get_settings(): array {
        return [
            'enabled' => false,
            'crm_system' => 'none',
        ];
    }

    /**
     * Save CRM settings (stub)
     */
    public static function save_settings(array $settings): bool {
        return false; // Pro feature
    }

    /**
     * Is CRM enabled (stub)
     */
    public static function is_enabled(): bool {
        return false;
    }
}
