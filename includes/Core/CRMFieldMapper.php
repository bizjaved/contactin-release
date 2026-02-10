<?php
/**
 * CRM Field Mapper Stub
 * 
 * This is a stub class to prevent fatal errors.
 * Actual CRM field mapping is a PRO feature.
 *
 * @package ContactInbox
 */

namespace ContactInbox\Core;

/**
 * CRMFieldMapper stub class
 */
class CRMFieldMapper {
    
    /**
     * Split name into first and last name
     *
     * @param string $fullName Full name
     * @return array
     */
    public static function split_name($fullName) {
        return [
            'FirstName' => '',
            'LastName' => $fullName,
        ];
    }
}
