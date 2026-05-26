<?php
namespace ContactInbox\Admin\Pages;

use ContactInbox\Traits\Singleton;
use ContactInbox\Core\Config;
use ContactInbox\Core\Repositories\EmailLogRepository;
use ContactInbox\Core\Settings;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
// phpcs:disable WordPress.WP.I18n.NonSingularStringLiteralDomain, WordPress.Security.ValidatedSanitizedInput, WordPress.Security.NonceVerification, WordPress.WP.I18n.UnorderedPlaceholdersText, WordPress.WP.I18n.MissingTranslatorsComment

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class EmailLog {
	use Singleton;

	private EmailLogRepository $repo;

	protected function __construct() {
		$this->repo = new EmailLogRepository();
		add_action( 'admin_init', array( $this, 'hooks' ) );

		// AJAX handlers - support both naming conventions
		add_action( 'wp_ajax_contactinbox_get_email_log', array( $this, 'ajax_get_log' ) );
		add_action( 'wp_ajax_contactinbox_get_adjacent_email_log', array( $this, 'ajax_get_adjacent' ) );
		add_action( 'wp_ajax_contactinbox_prune_email_logs', array( $this, 'ajax_prune' ) );
		add_action( 'wp_ajax_contactin_prune_email_logs', array( $this, 'ajax_prune' ) ); // Legacy support
		add_action( 'wp_ajax_contactinbox_email_clear_all_logs', array( $this, 'ajax_clear_all_logs' ) );
		add_action( 'wp_ajax_contactin_email_clear_all_logs', array( $this, 'ajax_clear_all_logs' ) ); // Legacy support
		add_action( 'wp_ajax_contactinbox_get_email_logs', array( $this, 'ajax_get_logs' ) );
	}

	public static function render(): void {
		if ( ! current_user_can( Config::CAPABILITY ) ) {
			wp_die(
				esc_html__( 'You do not have sufficient permissions to access this page.', 'contactin' )
			);
		}

		// Instantiate and prepare table in controller, not template
		$table = new \ContactInbox\Core\EmailLogTable();
		$table->prepare_items();

		$total_items  = (int) $table->get_pagination_arg( 'total_items' );
		$per_page     = (int) $table->get_pagination_arg( 'per_page' );
		$current_page = (int) $table->get_pagination_arg( 'page' );
		$total_pages  = (int) $table->get_pagination_arg( 'total_pages' );

		$start = ( $current_page - 1 ) * $per_page + 1;
		$end   = min( $start + $per_page - 1, $total_items );

		// Build absolute path to template
		$template = CONTACTINBOX_PATH . Config::TEMPLATE_ADMIN . 'email-log-page.php';

		if ( file_exists( $template ) ) {
			include $template;
		} else {
			echo '<div class="notice notice-error"><p>'
				. esc_html__( 'Email log template not found.', 'contactin' )
				. '</p></div>';
		}
	}

	public function hooks(): void {
		// Placeholder for notices/assets
	}

	public function ajax_get_logs(): void {
		check_ajax_referer( Config::NONCE_ACTION, 'nonce' );
		if ( ! current_user_can( Config::CAPABILITY ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'contactin' ) ) );
		}

		$status  = isset( $_POST['status'] ) && $_POST['status'] !== 'all'
			? sanitize_text_field( wp_unslash( $_POST['status'] ) )
			: '';
		$orderby = sanitize_key( wp_unslash( $_POST['orderby'] ?? 'created_at' ) );
		$order   = strtoupper( sanitize_text_field( wp_unslash( $_POST['order'] ?? 'DESC' ) ) );
		$limit   = absint( wp_unslash( $_POST['limit'] ?? 20 ) );
		$offset  = absint( wp_unslash( $_POST['offset'] ?? 0 ) );

		$rows = $this->repo->get_with_limit_offset( $limit, $offset, $status, $orderby, $order );

		if ( empty( $rows ) ) {
			wp_send_json_success(
				array(
					'rows'    => array(),
					'message' => __( 'No email logs found.', 'contactin' ),
				)
			);
		}

		wp_send_json_success(
			array(
				'rows'    => $rows,
				'count'   => count( $rows ),
				'message' => sprintf(
					__( 'Loaded %d email logs.', 'contactin' ),
					count( $rows )
				),
			)
		);
	}

	/**
	 * AJAX: Get single email log
	 */
	public function ajax_get_log(): void {
		check_ajax_referer( Config::NONCE_ACTION, 'nonce' );
		if ( ! current_user_can( Config::CAPABILITY ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'contactin' ) ) );
		}

		$id     = absint( wp_unslash( $_POST['id'] ?? 0 ) );
		$status = sanitize_text_field( wp_unslash( $_POST['status'] ?? 'all' ) );

		if ( ! $id ) {
			wp_send_json_error( array( 'message' => __( 'Invalid log ID.', 'contactin' ) ) );
		}

		$row = $this->repo->get_by_id( $id );
		if ( ! $row ) {
			wp_send_json_error( array( 'message' => __( 'Log not found.', 'contactin' ) ) );
		}

		// Always compute navigation flags with current filters
		$hasPrev = (bool) $this->repo->get_adjacent( $row['id'], 'prev', $status );
		$hasNext = (bool) $this->repo->get_adjacent( $row['id'], 'next', $status );

		wp_send_json_success(
			array(
				'id'        => (int) $row['id'],
				'timestamp' => $row['created_at'],
				'recipient' => $row['recipient'],
				'subject'   => $row['subject'],
				'status'    => $row['status'],
				'error'     => $row['error_message'] ?? '',
				'headers'   => $row['headers'] ?? '',
				'body'      => $row['body'] ?? '',
				'hasPrev'   => $hasPrev,
				'hasNext'   => $hasNext,
			)
		);
	}

	/**
	 * AJAX: Get adjacent email log (Prev/Next)
	 */
	public function ajax_get_adjacent(): void {
		check_ajax_referer( Config::NONCE_ACTION, 'nonce' );
		if ( ! current_user_can( Config::CAPABILITY ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'contactin' ) ) );
		}

		$direction  = sanitize_text_field( wp_unslash( $_POST['direction'] ?? '' ) );
		$current_id = absint( wp_unslash( $_POST['current_id'] ?? 0 ) );
		$status     = sanitize_text_field( wp_unslash( $_POST['status'] ?? 'all' ) );

		if ( ! $current_id || ! in_array( $direction, array( 'prev', 'next' ), true ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid request.', 'contactin' ) ) );
		}

		$row = $this->repo->get_adjacent( $current_id, $direction, $status );
		if ( ! $row ) {
			// Return error response with navigation flags for button state management
			$hasPrev = (bool) $this->repo->get_adjacent( $current_id, 'prev', $status );
			$hasNext = (bool) $this->repo->get_adjacent( $current_id, 'next', $status );

			wp_send_json_error(
				array(
					'message' => __( 'No more logs in this direction.', 'contactin' ),
					'hasPrev' => $hasPrev,
					'hasNext' => $hasNext,
				)
			);
		}

		$hasPrev = (bool) $this->repo->get_adjacent( $row['id'], 'prev', $status );
		$hasNext = (bool) $this->repo->get_adjacent( $row['id'], 'next', $status );

		wp_send_json_success(
			array(
				'id'        => (int) $row['id'],
				'timestamp' => $row['created_at'],
				'recipient' => $row['recipient'],
				'subject'   => $row['subject'],
				'status'    => $row['status'],
				'error'     => $row['error_message'] ?? '',
				'headers'   => $row['headers'] ?? '',
				'body'      => $row['body'] ?? '',
				'hasPrev'   => $hasPrev,
				'hasNext'   => $hasNext,
			)
		);
	}

	/**
	 * AJAX: Prune old email logs
	 */
	public function ajax_prune(): void {
		check_ajax_referer( Config::NONCE_ACTION, '_ajax_nonce' );
		if ( ! current_user_can( Config::CAPABILITY ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'contactin' ) ) );
		}

		$settings = Settings::get_settings();
		$days     = absint( $settings['email_log_retention_days'] ?? 90 );
		$deleted  = $this->repo->prune( $days );

		wp_send_json_success(
			array(
				'message' => sprintf(
					__( 'Pruned %d email logs older than %d days.', 'contactin' ),
					(int) $deleted,
					$days
				),
				'deleted' => (int) $deleted,
				'days'    => $days,
			)
		);
	}

	/**
	 * AJAX: Clear all email logs
	 */
	public function ajax_clear_all_logs(): void {
		check_ajax_referer( 'contactinbox_email_clear_all_logs', '_ajax_nonce' );
		if ( ! current_user_can( Config::CAPABILITY ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'contactin' ) ) );
		}

		$deleted = $this->repo->clear_all();

		if ( $deleted > 0 ) {
			wp_send_json_success(
				array(
					'message' => sprintf(
						__( 'Successfully cleared %d email logs.', 'contactin' ),
						$deleted
					),
					'deleted' => $deleted,
				)
			);
		} else {
			wp_send_json_success(
				array(
					'message' => __( 'No email logs to clear.', 'contactin' ),
					'deleted' => 0,
				)
			);
		}
	}
}
