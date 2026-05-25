<?php
/**
 * Learning Handler – AJAX Endpoints for Classifier Self-Learning
 *
 * Handles:
 * - View detailed learning reports
 * - Apply recommendations
 * - Export learning data
 * - Get learning statistics
 *
 * @package ContactIn\Admin\AJAX
 */

declare(strict_types=1);

namespace ContactInbox\Admin\AJAX;

use ContactInbox\Core\Config;
use ContactInbox\Core\IntentLearner;
use ContactInbox\Core\Logger;

// phpcs:disable WordPress.WP.I18n.NonSingularStringLiteralDomain, WordPress.WP.I18n.MissingTranslatorsComment, WordPress.Security.ValidatedSanitizedInput.MissingUnslash, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized, WordPress.Security.NonceVerification.Recommended, WordPress.Security.NonceVerification.Missing, WordPress.WP.I18n.UnorderedPlaceholdersText

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class LearningHandler extends BaseAJAXHandler {

	/**
	 * Handle learning report view request
	 */
	public function handle_learning_report(): void {
		$this->verify();

		try {
			$learner  = IntentLearner::instance();
			$stats    = $learner->get_learning_stats();
			$analysis = $learner->analyze_feedback_and_improve();
			$patterns = $learner->get_top_correction_patterns( 10 );

			wp_send_json_success(
				array(
					'stats'    => $stats,
					'analysis' => $analysis,
					'patterns' => $patterns,
				)
			);
		} catch ( \Exception $e ) {
			$this->handle_error( $e );
		}
	}

	/**
	 * Apply a recommendation
	 */
	public function handle_apply_recommendation(): void {
		$this->verify();

		try {
			$recommendation = isset( $_POST['recommendation'] ) ?
				json_decode( stripslashes( (string) $_POST['recommendation'] ), true ) : null;

			if ( ! is_array( $recommendation ) ) {
				throw new \Exception( __( 'Invalid recommendation data.', 'contactin' ) );
			}

			$learner = IntentLearner::instance();
			$result  = $learner->apply_recommendation( $recommendation );

			if ( $result['success'] ?? false ) {
				Logger::notice(
					'Admin applied learning recommendation',
					array(
						'action'  => $recommendation['action'] ?? 'unknown',
						'keyword' => $recommendation['keyword'] ?? null,
					)
				);

				wp_send_json_success(
					array(
						'message' => $result['message'],
						'keyword' => $result['keyword'] ?? null,
					)
				);
			} else {
				throw new \Exception( $result['message'] ?? __( 'Failed to apply recommendation.', 'contactin' ) );
			}
		} catch ( \Exception $e ) {
			$this->handle_error( $e );
		}
	}

	/**
	 * Export learning data as CSV
	 */
	public function handle_export_learning_data(): void {
		$this->verify();

		try {
			global $wpdb;
			$table = $wpdb->prefix . Config::TABLE_INTENT_FEEDBACK;
			$days  = isset( $_POST['days'] ) ? (int) $_POST['days'] : 30;

			// Get period statistics
			$learner = IntentLearner::instance();
			$data    = $learner->get_period_statistics( $days );

			// Generate CSV
			$csv = "Category,Count,Avg Original Confidence\n";
			foreach ( $data['by_category'] as $category => $stats ) {
				$csv .= sprintf(
					"%s,%d,%.1f%%\n",
					$category,
					$stats['count'],
					$stats['avg_original_confidence']
				);
			}

			wp_send_json_success(
				array(
					'csv'               => $csv,
					'filename'          => 'learning-data-' . gmdate( 'Y-m-d-H-i-s' ) . '.csv',
					'total_corrections' => $data['total_corrections'],
				)
			);
		} catch ( \Exception $e ) {
			$this->handle_error( $e );
		}
	}

	/**
	 * Get corrections for a specific message
	 */
	public function handle_get_message_corrections(): void {
		$this->verify();

		try {
			$message_id = isset( $_POST['message_id'] ) ? (int) $_POST['message_id'] : 0;

			if ( ! $message_id ) {
				throw new \Exception( __( 'Invalid message ID.', 'contactin' ) );
			}

			$learner     = IntentLearner::instance();
			$corrections = $learner->get_corrections_for_message( $message_id );

			wp_send_json_success(
				array(
					'message_id'  => $message_id,
					'corrections' => $corrections,
					'count'       => count( $corrections ),
				)
			);
		} catch ( \Exception $e ) {
			$this->handle_error( $e );
		}
	}

	/**
	 * Get learning statistics
	 */
	public function handle_learning_stats(): void {
		$this->verify();

		try {
			$learner = IntentLearner::instance();
			$stats   = $learner->get_learning_stats();

			wp_send_json_success( $stats );
		} catch ( \Exception $e ) {
			$this->handle_error( $e );
		}
	}

	/**
	 * Manually trigger learning analysis
	 * (Normally runs on weekly cron)
	 */
	public function handle_trigger_learning(): void {
		check_ajax_referer( Config::INBOX_NONCE_ACTION, 'nonce' );

		if ( ! current_user_can( Config::CAPABILITY ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'contactin' ) ) );
		}

		try {
			$learner  = IntentLearner::instance();
			$analysis = $learner->analyze_feedback_and_improve();

			if ( empty( $analysis['analyzed'] ) ) {
				wp_send_json_success(
					array(
						'message'  => __( 'No new corrections to analyze.', 'contactin' ),
						'analyzed' => 0,
					)
				);
				return;
			}

			wp_send_json_success(
				array(
					'message'  => sprintf(
						__( 'Analyzed %d corrections, found %d insights, %d recommendations pending review.', 'contactin' ),
						$analysis['analyzed'],
						count( $analysis['insights'] ?? array() ),
						count( $analysis['recommended_changes'] ?? array() )
					),
					'analysis' => $analysis,
				)
			);

			Logger::notice(
				'Admin manually triggered learning analysis',
				array(
					'analyzed' => $analysis['analyzed'],
					'insights' => count( $analysis['insights'] ?? array() ),
				)
			);
		} catch ( \Exception $e ) {
			wp_send_json_error( array( 'message' => $e->getMessage() ) );
		}
	}
}
