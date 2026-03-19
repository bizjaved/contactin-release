<?php
declare(strict_types=1);

namespace ContactInbox\Admin;

use WP_Error;
use ContactInbox\Core\Config;
use ContactInbox\Core\Settings as CoreSettings;
use ContactInbox\Admin\RestController;
use WP_REST_Request;

if (!defined('ABSPATH')) exit;

final class RestApiTest {

    private const OPTION_LAST_TEST_ID = 'contactin_last_test_id';

    private static function query_text(string $key, string $default = ''): string {
        $value = filter_input(INPUT_GET, $key, FILTER_UNSAFE_RAW);
        if (null === $value || false === $value) {
            return $default;
        }
        return sanitize_text_field(wp_unslash((string) $value));
    }

    private static function query_int(string $key, int $default = 0): int {
        $value = filter_input(INPUT_GET, $key, FILTER_UNSAFE_RAW);
        if (null === $value || false === $value || '' === $value) {
            return $default;
        }
        return (int) wp_unslash((string) $value);
    }

    public static function render(): void {
        $settings = CoreSettings::get_settings();

        if (empty($settings['restapi_enable'])) {
            echo '<div class="wrap"><div class="notice notice-error"><p><strong>REST API service is disabled.</strong> '
               . 'Please enable it in the plugin settings to run these tests.</p></div></div>';
            return;
        }

        $template = dirname(__DIR__, 2) . '/' . Config::TEMPLATE_ADMIN . 'restapi-test-page.php';
        if (file_exists($template)) {
            include $template;
        } else {
            echo '<div class="wrap"><div class="notice notice-error"><p>Template not found: '
               . esc_html($template) . '</p></div></div>';
        }
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------
    private static function display_payload(string $label, $payload): string {
        ob_start();
        echo '<h4>' . esc_html($label) . '</h4>';
        if (is_array($payload)) {
            echo '<pre>' . esc_html(wp_json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)) . '</pre>';
        } else {
            echo '<pre>' . esc_html((string)$payload) . '</pre>';
        }
        return ob_get_clean();
    }

    private static function display_result(string $label, $result): string {
        ob_start();
        echo '<h4>' . esc_html($label) . '</h4>';
        if ($result instanceof WP_Error) {
            echo '<div class="notice notice-error"><p><strong>Error:</strong> ' .
                 esc_html($result->get_error_message()) .
                 ' (code: ' . esc_html($result->get_error_code()) . ')</p></div>';
        } else {
            echo '<pre>' . esc_html(wp_json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)) . '</pre>';
        }
        return ob_get_clean();
    }

    private static function inject_modal(string $payloadHtml, string $resultHtml, string $paginationHtml = ''): void {
                $modal_inline_js = "document.addEventListener('DOMContentLoaded', function() {
                    const modal   = document.getElementById('contactin-restapi-modal');
                    const payload = document.querySelector('#contactin-restapi-payload .contactin-payload-content');
                    const output  = document.querySelector('#contactin-restapi-result .contactin-output-content');
                    const pages   = document.querySelector('#contactin-restapi-pagination');
                    const links   = document.querySelector('#contactin-restapi-pagination .contactin-pagination-links');
                    const closeBtn = document.getElementById('contactin-restapi-close');
                    const backdrop = modal ? modal.querySelector('.contactin-modal-backdrop') : null;

                    if (modal && payload && output) {
                        payload.innerHTML = " . wp_json_encode($payloadHtml) . ";
                        output.innerHTML  = " . wp_json_encode($resultHtml) . ";
                        if (pages && links) {
                            if (" . wp_json_encode($paginationHtml) . " !== '') {
                                links.innerHTML = " . wp_json_encode($paginationHtml) . ";
                                pages.style.display = 'block';
                            } else {
                                pages.style.display = 'none';
                            }
                        }
                        modal.classList.add('is-active');
                    }

                    if (closeBtn) {
                        closeBtn.addEventListener('click', function() {
                            modal.classList.remove('is-active');
                        });
                    }
                    if (backdrop) {
                        backdrop.addEventListener('click', function() {
                            modal.classList.remove('is-active');
                        });
                    }
                    document.addEventListener('keydown', function(e) {
                        if (e.key === 'Escape' && modal && modal.classList.contains('is-active')) {
                            modal.classList.remove('is-active');
                        }
                    });
                });";

                wp_add_inline_script('jquery-core', $modal_inline_js);
    }

    // -------------------------------------------------------------------------
    // Tests
    // -------------------------------------------------------------------------
    public static function ui_save_form(array $settings): void {
        $admin_emails_raw = (string) ($settings['admin_email'] ?? '');
        $candidates = preg_split('/[;,]/', $admin_emails_raw) ?: [];
        $candidates[] = (string) get_option('admin_email');

        $test_recipient = '';
        foreach ($candidates as $candidate) {
            $candidate = trim((string) $candidate);
            if ($candidate === '') {
                continue;
            }
            $sanitized = sanitize_email($candidate);
            if ($sanitized && is_email($sanitized)) {
                $test_recipient = $sanitized;
                break;
            }
        }
        if ($test_recipient === '') {
            $test_recipient = 'tester@example.com';
        }

        $params = [
            'name'    => 'Admin Tester',
            'email'   => $test_recipient,
            'phone'   => '+11234567890',
            'message' => 'Hello from Save Form Test!',
            'consent' => true,
            'form_id' => 'admin_save',
        ];
        if (!empty($settings['form_enable_subject'])) {
            $params['subject'] = 'Test Subject Line';
        }

        $request = new WP_REST_Request('POST', Config::REST_ENDPOINT_SUBMIT);
        $request->set_body_params($params);

        $submit = RestController::submit($request);

        $payloadHtml = self::display_payload('Payload Sent', $params);
        $resultHtml  = self::display_result('Confirmation (with GDPR link or error)', $submit);

        if (is_array($submit) && !empty($submit['id'])) {
            update_option(self::OPTION_LAST_TEST_ID, (int)$submit['id']);
            $resultHtml .= '<div class="notice notice-success"><p>Saved test record ID: ' .
                          esc_html((string)$submit['id']) . ' (persisted)</p></div>';
            $resultHtml .= self::display_result('Saved Record', $submit);
        }

        self::inject_modal($payloadHtml, $resultHtml);
    }

    public static function ui_change_status(): void {
        $savedId  = (int) get_option(self::OPTION_LAST_TEST_ID, 0);
        $recentId = self::query_int('id', $savedId);
        $status   = self::query_text('status', Config::STATUS_READ);

        $reqRead = new WP_REST_Request('GET', Config::REST_ENDPOINT_READ);
        $reqRead->set_param('id', $recentId);
        $prev = RestController::read($reqRead);

        $reqStatus = new WP_REST_Request('POST', Config::REST_ENDPOINT_STATUS);
        $reqStatus->set_param('id', $recentId);
        $reqStatus->set_param('status', $status);
        $updated = RestController::update_status($reqStatus);

        $payloadHtml = self::display_payload('Status Update Payload', ['id' => $recentId, 'status' => $status]);
        $resultHtml  = self::display_result('Previous Record', $prev) .
                       self::display_result('Updated Record', $updated);

        self::inject_modal($payloadHtml, $resultHtml);
    }

    public static function ui_gdpr_link(): void {
        $savedId  = (int) get_option(self::OPTION_LAST_TEST_ID, 0);
        $recentId = self::query_int('id', $savedId);

        $req = new WP_REST_Request('POST', Config::REST_ENDPOINT_GDPR);
        $req->set_body_params(['id' => $recentId]);
        $gdpr = RestController::gdpr_request($req);

        $payloadHtml = self::display_payload('GDPR Request Payload', ['id' => $recentId]);
        $resultHtml  = self::display_result('GDPR Link', $gdpr);

        self::inject_modal($payloadHtml, $resultHtml);
    }

    public static function ui_search_record(): void {
        $query    = self::query_text('q', 'Admin Tester');
        $page     = max(1, self::query_int('page', 1));
        $per_page = Config::INBOX_PER_PAGE;

        $req = new WP_REST_Request('GET', Config::REST_ENDPOINT_SEARCH);
        $req->set_param('q', $query);
        $req->set_param('page', $page);
        $req->set_param('per_page', $per_page);

        $result = RestController::search_messages($req);

        $payloadHtml = self::display_payload('Search Payload', ['q' => $query, 'page' => $page, 'per_page' => $per_page]);
        $resultHtml  = '';

        if (is_array($result) && !empty($result['items'])) {
            $resultHtml .= '<pre>';
            foreach ($result['items'] as $row) {
                $flat = is_array($row) ? $row : (array)$row;
                // Show only form fields in consistent order
                $fields = [
                    $flat['name'] ?? '',
                    $flat['email'] ?? '',
                    $flat['phone'] ?? '',
                    $flat['subject'] ?? '',
                    $flat['message'] ?? '',
                    $flat['status'] ?? '',
                    $flat['date'] ?? ''
                ];
                $resultHtml .= implode(' | ', $fields) . "\n";
            }
            $resultHtml .= '</pre>';
        } else {
            $resultHtml .= '<div class="notice notice-warning"><p>No records found for query: ' . esc_html($query) . '</p></div>';
        }

        // Pagination links using inbox rhythm
        $paginationHtml = '';
        if (is_array($result) && !empty($result['total_pages'])) {
            $paginationHtml .= '<div class="tablenav top">';
            for ($i = 1; $i <= (int)$result['total_pages']; $i++) {
                $url = esc_url(add_query_arg([
                    'page' => 'contactin-restapi-test',
                    'mode' => 'search',
                    'q'    => $query,
                    'page' => $i
                ], admin_url('admin.php')));
                $paginationHtml .= '<a class="button" href="' . $url . '">Page ' . $i . '</a> ';
            }
            $paginationHtml .= '</div>';
        }

        self::inject_modal($payloadHtml, $resultHtml, $paginationHtml);
    }

    public static function ui_delete_records(): void {
        $paramId  = self::query_int('id', 0);
        $latestId = (int) get_option(self::OPTION_LAST_TEST_ID, 0);
        $targetId = $paramId > 0 ? $paramId : $latestId;

        if ($targetId <= 0) {
            $payloadHtml = self::display_payload('Delete Payload', ['id' => null, 'ids' => []]);
            $resultHtml  = '<div class="notice notice-warning"><p>No valid record ID provided.</p></div>';
            self::inject_modal($payloadHtml, $resultHtml);
            return;
        }

        $reqBulk = new WP_REST_Request('DELETE', Config::REST_ENDPOINT_BULK_DELETE);
        // Send both forms
        $reqBulk->set_body_params([
            'id'  => $targetId,
            'ids' => [$targetId],
        ]);

        $deleted = RestController::bulk_delete($reqBulk);

        $payloadHtml = self::display_payload('Delete Payload', ['id' => $targetId, 'ids' => [$targetId]]);
        $resultHtml  = self::display_result('Delete Result', $deleted);

        self::inject_modal($payloadHtml, $resultHtml);
    }

}
