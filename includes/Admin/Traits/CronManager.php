<?php
declare(strict_types=1);

namespace ContactInbox\Admin\Traits;

use ContactInbox\Core\Config;
use ContactInbox\Core\Logger;
use ContactInbox\Lifecycle;

// phpcs:disable WordPress.Security.ValidatedSanitizedInput.MissingUnslash, Generic.PHP.ForbiddenFunctions.Found, PluginCheck.CodeAnalysis.DiscouragedFunctions.load_plugin_textdomainFound, PluginCheck.CodeAnalysis.Heredoc.NotAllowed, PluginCheck.Security.DirectDB.UnescapedDBParameter, Squiz.PHP.DiscouragedFunctions.Discouraged, WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.NamingConventions.PrefixAllGlobals.DynamicHooknameFound, WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound, WordPress.PHP.DevelopmentFunctions.error_log_debug_backtrace, WordPress.WP.AlternativeFunctions.file_system_operations_fsockopen, WordPress.WP.AlternativeFunctions.file_system_operations_readfile, WordPress.WP.AlternativeFunctions.file_system_operations_rmdir, WordPress.WP.EnqueuedResourceParameters.MissingVersion, WordPress.WP.EnqueuedResources.NonEnqueuedScript, WordPress.WP.I18n.MissingArgDomain, WordPress.WP.I18n.UnorderedPlaceholdersPlural, WordPress.WP.I18n.UnorderedPlaceholdersSingle

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

trait CronManager {

	/**
	 * Run a cron job manually via AJAX
	 */
	public function ajax_run_cron_now(): void {
		check_ajax_referer( 'ci_cron_action', 'nonce' );

		if ( ! current_user_can( Config::CAPABILITY ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'contactin' ) ) );
		}

		$event = sanitize_text_field( $_POST['event'] ?? '' );

		if ( empty( $event ) ) {
			wp_send_json_error(
				array(
					'message' => 'Event name is required',
				)
			);
		}

		if ( $event !== Config::CRON_PROCESS_EMAIL ) {
			wp_send_json_error(
				array(
					'message' => __( 'This processor is available in ContactIn Pro.', 'contactin' ),
				),
				403
			);
		}

		try {
			Logger::info( "Manually triggering cron event: {$event}" );

			// Execute the cron event immediately
			do_action( $event );

			Logger::info( "Cron event executed successfully: {$event}" );

			wp_send_json_success(
				array(
					'message' => 'Job executed successfully',
					'event'   => $event,
				)
			);
		} catch ( \Throwable $e ) {
			Logger::error( "Failed to run cron event {$event}: " . $e->getMessage() );
			wp_send_json_error(
				array(
					'message' => 'Failed to execute job: ' . $e->getMessage(),
				)
			);
		}
	}

	/**
	 * Update cron job interval via AJAX
	 */
	public function ajax_update_cron_interval(): void {
		check_ajax_referer( 'ci_cron_action', 'nonce' );

		if ( ! current_user_can( Config::CAPABILITY ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'contactin' ) ) );
		}

		$event        = sanitize_text_field( $_POST['event'] ?? '' );
		$new_interval = sanitize_text_field( $_POST['interval'] ?? '' );

		if ( empty( $event ) || empty( $new_interval ) ) {
			wp_send_json_error(
				array(
					'message' => 'Event name and interval are required',
				)
			);
		}

		if ( $event !== Config::CRON_PROCESS_EMAIL ) {
			wp_send_json_error(
				array(
					'message' => __( 'This processor is available in ContactIn Pro.', 'contactin' ),
				),
				403
			);
		}

		// Validate interval exists
		$schedules = wp_get_schedules();
		if ( ! isset( $schedules[ $new_interval ] ) ) {
			wp_send_json_error(
				array(
					'message' => 'Invalid interval specified',
				)
			);
		}

		$interval_seconds = (int) ( $schedules[ $new_interval ]['interval'] ?? 0 );
		$warning          = null;
		if ( $interval_seconds > 0 && $interval_seconds < 900 ) {
			$warning = 'Running more often than every 15 minutes can add load. Form submissions already trigger immediate one-off runs to keep notifications fresh.';
		}

		try {
			Logger::info( "Updating cron interval for {$event} to {$new_interval}" );

			update_option( 'contactin_queue_interval', $new_interval );

			Lifecycle::reschedule_cron_jobs( array( $event ) );
			$next_run = wp_next_scheduled( $event );
			if ( ! $next_run ) {
				throw new \Exception( 'Failed to reschedule event' );
			}

			Logger::info( "Cron interval updated successfully: {$event} -> {$new_interval}" );

			wp_send_json_success(
				array(
					'message'  => 'Schedule updated successfully',
					'event'    => $event,
					'interval' => $new_interval,
					'next_run' => $next_run,
					'warning'  => $warning,
				)
			);
		} catch ( \Throwable $e ) {
			Logger::error( "Failed to update cron interval for {$event}: " . $e->getMessage() );
			wp_send_json_error(
				array(
					'message' => 'Failed to update schedule: ' . $e->getMessage(),
				)
			);
		}
	}
}
