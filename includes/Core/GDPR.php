<?php
declare(strict_types=1);

namespace ContactInbox\Core;

/**
 * GDPR - Stub for Free Version
 * 
 * GDPR tools and deletion functionality are Pro-only features.
 * This stub prevents fatal errors where the class is referenced.
 */
final class GDPR {
    
    /**
     * GDPR deletion token expiration time in days
     */
    public const EXPIRATION_DAYS = 30;

    /**
     * Check if GDPR is enabled (always false for free version)
     */
    public static function is_enabled(): bool {
        return false;
    }
}
