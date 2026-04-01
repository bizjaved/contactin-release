<?php
/**
 * Admin Page – GDPR Deletion Log
 */

declare(strict_types=1);

namespace ContactInbox\Admin\Pages;

use ContactInbox\Traits\Singleton;
use ContactInbox\Admin\Traits\ExportHelper;
use ContactInbox\Core\Config;
use ContactInbox\Core\Repositories\GDPRRepository;

// phpcs:disable WordPress.WP.I18n.NonSingularStringLiteralDomain, WordPress.Security.ValidatedSanitizedInput, WordPress.Security.NonceVerification, WordPress.WP.I18n.MissingTranslatorsComment, WordPress.Security.EscapeOutput, WordPress.WP.I18n.UnorderedPlaceholdersText, WordPress.WP.I18n.TextDomainMismatch, WordPress.PHP.DevelopmentFunctions.error_log_error_log

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class GDPRLog {
	use Singleton;
	use ExportHelper;

	private GDPRRepository $repo;

	protected function __construct() {
		$this->repo = new GDPRRepository();
		add_action( 'admin_init', array( $this, 'hooks' ) );
	}

	public function hooks(): void {
		// Modern naming
		add_action( 'wp_ajax_contactinbox_gdpr_prune_logs', array( $this, 'ajax_prune_logs' ) );
		add_action( 'wp_ajax_contactinbox_gdpr_clear_logs', array( $this, 'ajax_clear_logs' ) );
		add_action( 'wp_ajax_contactinbox_download_gdpr_csv', array( $this, 'ajax_download_csv' ) );
		add_action( 'wp_ajax_contactinbox_gdpr_export_info', array( $this, 'ajax_export_info' ) );
		add_action( 'wp_ajax_contactinbox_gdpr_get_synced_count', array( $this, 'ajax_get_synced_count' ) );

		// Legacy support
		add_action( 'wp_ajax_ci_gdpr_prune_logs', array( $this, 'ajax_prune_logs' ) );
		add_action( 'wp_ajax_ci_gdpr_clear_logs', array( $this, 'ajax_clear_logs' ) );
	}

	public static function render(): void {
		if ( ! current_user_can( Config::CAPABILITY ) ) {
			wp_die( esc_html__( 'Permission denied.', 'contactin' ) );
		}
		self::instance()->display();
	}

	private function display(): void {
		// Filters
		$search        = isset( $_GET['s'] ) ? sanitize_text_field( $_GET['s'] ) : '';
		$crm_filter    = isset( $_GET['crm_status'] ) ? sanitize_text_field( $_GET['crm_status'] ) : 'all';
		$status_filter = isset( $_GET['deletion_status'] ) ? sanitize_text_field( $_GET['deletion_status'] ) : 'all';
		$paged         = max( 1, absint( $_GET['paged'] ?? 1 ) );
		$per_page      = max( 10, min( 100, absint( $_GET['per_page'] ?? 20 ) ) );

		// Get total count
		$total_items = $this->repo->count_logs( $search, $crm_filter, $status_filter );

		// Calculate pagination
		$pages  = max( 1, (int) ceil( $total_items / $per_page ) );
		$paged  = max( 1, min( $paged, $pages ) );
		$offset = ( $paged - 1 ) * $per_page;

		// Get logs
		$logs = $this->repo->get_logs( $search, $crm_filter, $status_filter, $per_page, $offset );

		// Count by CRM status
		$synced_count = $this->repo->count_synced_completed();

		$display_start = $total_items ? ( ( $paged - 1 ) * $per_page ) + 1 : 0;
		$display_end   = $total_items ? min( $display_start + $per_page - 1, $total_items ) : 0;

		$crm_filter_options = array(
			'all'              => __( 'All CRM statuses', 'contactin' ),
			'synced'           => __( 'Synced', 'contactin' ),
			'not_synced'       => __( 'Not synced', 'contactin' ),
			'unsynced'         => __( 'Unsynced', 'contactin' ),
			'partially_synced' => __( 'Partially synced', 'contactin' ),
			'manual_required'  => __( 'Manual deletion required', 'contactin' ),
			'unknown'          => __( 'Unknown', 'contactin' ),
		);

		$deletion_status_options = array(
			'all'         => __( 'All deletion statuses', 'contactin' ),
			'pending'     => __( 'Pending', 'contactin' ),
			'in_progress' => __( 'In progress', 'contactin' ),
			'completed'   => __( 'Completed', 'contactin' ),
			'failed'      => __( 'Failed', 'contactin' ),
		);

		$status_labels = array(
			'pending'     => __( 'Pending', 'contactin' ),
			'in_progress' => __( 'In progress', 'contactin' ),
			'completed'   => __( 'Completed', 'contactin' ),
			'failed'      => __( 'Failed', 'contactin' ),
		);

		$crm_labels = array(
			'synced'           => __( 'Synced', 'contactin' ),
			'not_synced'       => __( 'Not synced', 'contactin' ),
			'unsynced'         => __( 'Unsynced', 'contactin' ),
			'partially_synced' => __( 'Partially synced', 'contactin' ),
			'manual_required'  => __( 'Manual deletion required', 'contactin' ),
			'unknown'          => __( 'Unknown', 'contactin' ),
		);

		$range_summary_label = '';
		if ( $total_items ) {
			$range_summary_label = sprintf(
				__( 'Showing %1$s–%2$s of %3$s records', 'contactin' ),
				number_format_i18n( $display_start ),
				number_format_i18n( $display_end ),
				number_format_i18n( $total_items )
			);
		}

		// Load template
		$template = CONTACTINBOX_PATH . 'templates/admin/gdpr-log-page.php';
		if ( ! file_exists( $template ) ) {
			wp_die( esc_html__( 'GDPR log template missing.', 'contactin' ) );
		}

		// Pass variables to template
		include $template;
	}

	/**
	 * AJAX: Prune old logs using retention period from settings
	 */
	public function ajax_prune_logs(): void {
		check_ajax_referer( Config::NONCE_ACTION, 'nonce' );

		if ( ! current_user_can( Config::CAPABILITY ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'contactin' ) ) );
		}

		// Get retention days from settings
		$settings       = get_option( Config::OPTION_SETTINGS, array() );
		$retention_days = absint( $settings['gdpr_log_retention_days'] ?? 90 );

		$deleted = $this->repo->prune_old_logs( $retention_days );

		if ( $deleted === false ) {
			wp_send_json_error( array( 'message' => __( 'Failed to prune GDPR logs.', 'contactin' ) ) );
		}

		wp_send_json_success(
			array(
				'message' => sprintf(
					__( 'Pruned %d GDPR log entries older than %d days.', 'contactin' ),
					(int) $deleted,
					$retention_days
				),
				'deleted' => (int) $deleted,
				'days'    => $retention_days,
			)
		);
	}

	/**
	 * AJAX: Clear all non-synced logs
	 */
	public function ajax_clear_logs(): void {
		check_ajax_referer( Config::NONCE_ACTION, 'nonce' );

		if ( ! current_user_can( Config::CAPABILITY ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'contactin' ) ) );
		}

		$deleted = $this->repo->clear_non_synced_logs();

		if ( $deleted === false ) {
			wp_send_json_error( array( 'message' => __( 'Failed to clear GDPR logs.', 'contactin' ) ) );
		}

		wp_send_json_success(
			array(
				'message' => sprintf(
					__( 'Cleared %d log entries.', 'contactin' ),
					(int) $deleted
				),
				'deleted' => (int) $deleted,
			)
		);
	}

	/**
	 * AJAX: Download GDPR log as CSV
	 */
	public function ajax_download_csv(): void {
		// Feature gating: CSV export is a premium feature
		if ( ! \ContactInbox\Integration\FreemiusIntegration::can_use_premium_features() ) {
			wp_send_json_error( array( 'message' => __( 'This feature requires a Pro license.', 'contactin' ) ) );
		}

		// Security: nonce validation (passed as _wpnonce in query string)
		if ( ! isset( $_REQUEST['_wpnonce'] ) || ! wp_verify_nonce( $_REQUEST['_wpnonce'], Config::NONCE_ACTION ) ) {
			wp_send_json_error( array( 'message' => __( 'Security check failed.', 'contactin' ) ) );
		}

		if ( ! current_user_can( Config::CAPABILITY ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'contactin' ) ) );
		}

		// Batching/chunking support
		$limit  = isset( $_GET['limit'] ) ? max( 1, min( absint( $_GET['limit'] ), Config::EXPORT_LIMIT ) ) : Config::EXPORT_LIMIT;
		$batch  = isset( $_GET['batch'] ) ? max( 1, absint( $_GET['batch'] ) ) : 1;
		$offset = ( $batch - 1 ) * $limit;

		// Get filter parameters (can come from GET or POST)
		$search          = isset( $_REQUEST['s'] ) ? sanitize_text_field( $_REQUEST['s'] ) : '';
		$crm_status      = isset( $_REQUEST['crm_status'] ) ? sanitize_text_field( $_REQUEST['crm_status'] ) : 'all';
		$deletion_status = isset( $_REQUEST['deletion_status'] ) ? sanitize_text_field( $_REQUEST['deletion_status'] ) : 'all';

		$rows = $this->repo->get_export_rows( $search, $crm_status, $deletion_status, $limit, $offset );

		if ( empty( $rows ) ) {
			wp_send_json_error( array( 'message' => __( 'No logs to export.', 'contactin' ) ) );
		}

		// Build CSV with custom headers
		$headers = array( 'Email', 'Name', 'CRM Sync Status', 'Deletion Status', 'Deleted By', 'Deleted At' );
		$csv     = $this->build_csv_data( $rows, $headers );

		if ( ! $csv ) {
			wp_send_json_error( array( 'message' => __( 'Failed to generate CSV data.', 'contactin' ) ) );
		}

		$total_batches = absint( $_GET['total_batches'] ?? 0 );
		$filename      = $this->get_export_filename( 'gdpr-log', $batch, $total_batches );

		// Send CSV file download
		$this->send_csv_download( $csv, $filename );
	}

	/**
	 * AJAX: Export info (total, batches, limit) for client-side orchestration.
	 */
	public function ajax_export_info(): void {
		// Feature gating: CSV export is a premium feature
		if ( ! \ContactInbox\Integration\FreemiusIntegration::can_use_premium_features() ) {
			wp_send_json_error( array( 'message' => __( 'This feature requires a Pro license.', 'contactin' ) ) );
		}

		check_ajax_referer( Config::NONCE_ACTION, 'nonce' );

		if ( ! current_user_can( Config::CAPABILITY ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'contactin' ) ) );
		}

		// Get filter parameters (can come from GET or POST)
		$search          = isset( $_REQUEST['s'] ) ? sanitize_text_field( $_REQUEST['s'] ) : '';
		$crm_status      = isset( $_REQUEST['crm_status'] ) ? sanitize_text_field( $_REQUEST['crm_status'] ) : 'all';
		$deletion_status = isset( $_REQUEST['deletion_status'] ) ? sanitize_text_field( $_REQUEST['deletion_status'] ) : 'all';

		$total = $this->repo->count_logs( $search, $crm_status, $deletion_status );

		$limit   = Config::EXPORT_LIMIT;
		$batches = ceil( $total / $limit );

		wp_send_json_success(
			array(
				'total'   => $total,
				'limit'   => $limit,
				'batches' => $batches,
				'message' => sprintf( __( 'Found %d GDPR logs. Export limit: %d per file.', 'contactin' ), $total, $limit ),
			)
		);
	}

	/**
	 * AJAX: Get synced contact count for analytical warnings in prune/clear modals.
	 */
	public function ajax_get_synced_count(): void {
		check_ajax_referer( Config::NONCE_ACTION, 'nonce' );

		if ( ! current_user_can( Config::CAPABILITY ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'contactin' ) ) );
		}

		$synced_count = $this->repo->count_synced_completed();

		wp_send_json_success(
			array(
				'synced_count' => $synced_count,
			)
		);
	}
}
