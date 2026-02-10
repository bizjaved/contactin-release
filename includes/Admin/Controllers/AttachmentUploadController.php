<?php
/**
 * Attachment Upload Controller Stub
 * 
 * This is a stub class to prevent fatal errors.
 * Actual attachment upload is a PRO feature.
 *
 * @package ContactInbox
 */

namespace ContactInbox\Admin\Controllers;

/**
 * AttachmentUploadController stub class
 */
class AttachmentUploadController {
    
    /**
     * Upload attachment
     *
     * @param \WP_REST_Request $request REST request
     * @return \WP_REST_Response|\WP_Error
     */
    public static function upload($request) {
        return new \WP_Error(
            'pro_feature',
            __('Attachment upload via REST API is a PRO feature', 'contact-inbox'),
            ['status' => 403]
        );
    }
}
