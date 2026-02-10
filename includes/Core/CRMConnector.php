<?php
namespace ContactInbox\Core;

/**
 * CRMConnector stub - PRO feature removed in free version
 */
final class CRMConnector {
    public static function send($data, $message_id = null) {
        return new \WP_Error('pro_feature', 'CRM sync is a PRO feature');
    }
}
