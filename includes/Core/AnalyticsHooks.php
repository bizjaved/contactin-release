<?php
/**
 * Analytics Hooks Integration
 *
 * Hooks into form lifecycle to track events:
 * - Form view (when shortcode is rendered)
 * - Form submission (successful insert)
 * - Form conversion (submission success response)
 *
 * @package ContactInbox\Core
 * @since   1.6.1
 */

namespace ContactInbox\Core;

use ContactInbox\Traits\Singleton;
use ContactInbox\Core\Analytics\AnalyticsCollector;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class AnalyticsHooks {
    use Singleton;

    protected function __construct() {
        // Hook: Track form view when shortcode is rendered
        add_action( 'contactin_form_rendered', [ $this, 'on_form_rendered' ] );

        // Hook: Track form submission when message is saved
        add_action( 'contactin_message_received', [ $this, 'on_message_received' ], 10, 2 );

        // Note: Form conversion is tracked via REST API response (frontend JS fires conversion event)
        // See: templates/frontend/contact-form.php for the JS call pattern
    }

    /**
     * Track form view event
     *
     * Called when form shortcode is rendered
     * Parameters: form_id (string)
     *
     * @param string $form_id Form identifier
     * @return void
     */
    public function on_form_rendered( string $form_id ): void {
        try {
            AnalyticsCollector::track_form_view( $form_id );
        } catch ( \Throwable $e ) {
            // Silently fail to avoid disrupting form rendering
            do_action( 'contactin_analytics_error', 'form_view_tracking', $e );
        }
    }

    /**
     * Track form submission event and trigger queue processing
     *
     * Called after message is saved to database.
     * Handles:
     * 1. Analytics tracking
     * 2. Webhook queueing
     * 3. **NEW:** Intelligent queue processing trigger
     *
     * Parameters: message_id (int), payload (array)
     *
     * @param int   $message_id ID of saved message
     * @param array $payload    Message data
     * @return void
     */
    public function on_message_received( int $message_id, array $payload ): void {
        try {
            // 1. Track analytics
            $form_id = (string) ( $payload['form_id'] ?? 'default' );
            AnalyticsCollector::track_form_submission( $form_id, $message_id );

            // 2. Queue webhooks
            $settings = \ContactInbox\Core\Settings::get_settings();
            if ( ! empty( $settings['webhooks_enable'] ) && ! empty( $settings['webhooks'] ) ) {
                foreach ( $settings['webhooks'] as $webhook ) {
                    if ( ! empty( $webhook['active'] ) && ! empty( $webhook['url'] ) ) {
                        // Prepare webhook queue payload
                        $webhook_data = [
                            'webhook_id' => $webhook['id'] ?? '',
                            'url'        => $webhook['url'],
                            'secret'     => $webhook['secret'] ?? '',
                            'events'     => $webhook['events'] ?? [],
                            'payload'    => $payload,
                        ];
                        \ContactInbox\Core\QueueManager::push(
                            'webhook',
                            $webhook_data,
                            (string) $message_id,
                            3 // normal priority
                        );
                    }
                }
            }

            // 3. **NEW:** Trigger queue processing (if enabled and not already running)
            QueueTrigger::maybe_trigger_email_processor();
            QueueTrigger::maybe_trigger_crm_processor();

        } catch ( \Throwable $e ) {
            // Silently fail to avoid disrupting form processing
            do_action( 'contactin_analytics_error', 'form_submission_tracking', $e );
        }
    }

    /**
     * Track form conversion (called via JavaScript/AJAX after successful submission)
     *
     * This is typically called from the frontend JS after receiving a success response
     * @see templates/frontend/contact-form.php for JS integration
     *
     * @param string $form_id      Form identifier
     * @param int    $submission_id Message ID
     * @return array Success response
     */
    public static function track_conversion( string $form_id, int $submission_id ): array {
        try {
            AnalyticsCollector::track_form_conversion( $form_id, $submission_id );

            return [
                'success' => true,
                'message' => __( 'Conversion tracked', Config::TEXTDOMAIN ),
            ];
        } catch ( \Throwable $e ) {
            return [
                'success' => false,
                'message' => __( 'Failed to track conversion', Config::TEXTDOMAIN ),
                'error'   => $e->getMessage(),
            ];
        }
    }
}
