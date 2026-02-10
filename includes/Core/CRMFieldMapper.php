<?php
declare(strict_types=1);

namespace ContactInbox\Core;

/**
 * CRM Field Mapper - Stub for Free Version
 * 
 * CRM field mapping is a Pro-only feature.
 * This stub prevents fatal errors where the class is referenced.
 */
final class CRMFieldMapper {

    /**
     * Map contact fields (stub)
     */
    public static function map_contact_fields(array $form_data, array $crm_settings = []): array {
        return []; // Pro feature
    }

    /**
     * Map inquiry fields (stub)
     */
    public static function map_inquiry_fields(array $form_data, array $crm_settings = [], int $contact_id = 0): array {
        return []; // Pro feature
    }
}
