<?php
use PHPUnit\Framework\TestCase;
use ContactInbox\Integrations\Webhooks;
use ContactInbox\Core\DB;

class WebhooksLogTest extends WP_UnitTestCase {

    public function test_valid_webhook_submission_logs_entry() {
        $request = new WP_REST_Request( 'POST', '/contactin/v1/webhook' );
        $request->set_body_params([
            'name'    => 'Tester',
            'email'   => 'tester@example.com',
            'message' => 'Hello world',
        ]);

        $response = rest_do_request( $request );
        $data     = $response->get_data();

        $this->assertTrue( $data['success'] );

        // Fetch last log
        $logs = DB::instance()->get_webhook_logs(1,0);
        $last = reset($logs);

        $this->assertEquals(200, (int)$last['http_code']);
        $this->assertEquals(1, (int)$last['validated']);
    }

    public function test_missing_fields_logs_error() {
        $request = new WP_REST_Request( 'POST', '/contactin/v1/webhook' );
        $request->set_body_params([
            'name'  => 'Tester',
            'email' => 'tester@example.com',
            // message missing
        ]);

        $response = rest_do_request( $request );
        $data     = $response->get_data();

        $this->assertFalse( $data['success'] );

        $logs = DB::instance()->get_webhook_logs(1,0);
        $last = reset($logs);

        $this->assertEquals(400, (int)$last['http_code']);
        $this->assertEquals('missing_fields', $last['error_code']);
    }

    public function test_status_update_logs_entry() {
        // Create a dummy message first
        $msg_id = DB::instance()->insert_message([
            'name'    => 'Tester',
            'email'   => 'tester@example.com',
            'message' => 'Status test',
        ]);

        $request = new WP_REST_Request( 'POST', '/contactin/v1/webhook/status/' . $msg_id );
        $request->set_body_params([ 'status' => \ContactInbox\Core\Config::STATUS_READ ]);

        $response = rest_do_request( $request );
        $data     = $response->get_data();

        $this->assertTrue( $data['success'] );

        $logs = DB::instance()->get_webhook_logs(1,0);
        $last = reset($logs);

        $this->assertEquals($msg_id, (int)$last['record_id']);
        $this->assertEquals(200, (int)$last['http_code']);
    }
}
