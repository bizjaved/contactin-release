<?php
/**
 * CRM Queue Service Stub
 * 
 * This is a stub class to prevent fatal errors.
 * Actual CRM queue processing is a PRO feature.
 *
 * @package ContactInbox
 */

namespace ContactInbox\Core;

/**
 * CRMQueueService stub class
 */
class CRMQueueService {
    
    /**
     * Queue message for CRM sync
     *
     * @param int $message_id Message ID
     * @param int $priority Priority
     * @param bool $force Force queue
     * @return bool
     */
    public static function queue_message_sync($message_id, $priority = 3, $force = false) {
        return false;
    }
}
