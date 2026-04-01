<?php
/**
 * Users Data AJAX Handler
 *
 * @package ContactIn\Admin\AJAX
 */

declare(strict_types=1);

namespace ContactInbox\Admin\AJAX;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class UsersDataHandler extends BaseAJAXHandler {

	public function handle(): void {
		$this->verify();

		try {
			$date_range = $this->parse_date_range();
			$days       = $date_range['days'];
			$start_date = $date_range['start_date'];
			$end_date   = $date_range['end_date'];

			// Get analytics from events table with date filters
			$device_distribution = $this->analytics->get_device_distribution( $days, $start_date, $end_date );

			$geographic_distribution = $this->analytics->get_geographic_distribution( $days, $start_date, $end_date );

			$traffic_sources = $this->analytics->get_traffic_sources( $days, $start_date, $end_date );

			$browser_distribution = $this->analytics->get_browser_distribution( $days, $start_date, $end_date );

			$response_data = array(
				'device_distribution'     => $device_distribution,
				'geographic_distribution' => $geographic_distribution,
				'traffic_sources'         => $traffic_sources,
				'browser_distribution'    => $browser_distribution,
			);

			wp_send_json_success( $response_data );
		} catch ( \Exception $e ) {
			$this->handle_error( $e );
		}
	}
}
