<?php
// phpcs:disable WordPress.Security.EscapeOutput.OutputNotEscaped
/**
 * Admin Trait – Export Helper (Reusable across all pages)
 *
 * Handles CSV export with batching/chunking logic.
 * Responsibility: Provide common export methods for all log pages (inbox, email log, CRM log, REST log).
 *
 * @package ContactInbox\Admin\Traits
 * @since   1.0.0
 */

declare(strict_types=1);

namespace ContactInbox\Admin\Traits;

use ContactInbox\Core\Config;

if (!defined('ABSPATH')) {
    exit;
}

trait ExportHelper {

    /**
     * Build CSV data from an array of rows/objects.
     * Handles both associative arrays and objects.
     * Safely encodes JSON fields to prevent CSV delimiter conflicts.
     *
     * @param array $rows Array of rows (associative arrays or objects).
     * @param array $headers Optional custom headers; if not provided, derived from first row keys.
     * @return string CSV-formatted data, or empty string on error.
     */
    protected function build_csv_data(array $rows, array $headers = []): string {
        if (empty($rows)) {
            return '';
        }

        // If no headers provided, derive from first row
        if (empty($headers)) {
            $first_row = reset($rows);
            if (is_object($first_row)) {
                $headers = array_keys((array) $first_row);
            } elseif (is_array($first_row)) {
                $headers = array_keys($first_row);
            } else {
                return '';
            }
        }

        // Known JSON fields in log tables that need special handling
        $json_fields = [
            'response',
            'request_headers',
            'request_payload',
            'response_body',
            'error_context',
            'metadata',
            'data',
        ];

        // Build CSV manually with proper escaping for JSON fields
        $csv_lines = [];
        
        // Add header row
        $csv_lines[] = $this->escape_csv_line($headers);

        // Add data rows
        foreach ($rows as $row) {
            $row_data = is_object($row) ? (array) $row : $row;
            
            // Process each field
            $processed_row = [];
            foreach ($row_data as $key => $value) {
                if (in_array($key, $json_fields, true) && is_string($value) && !empty($value)) {
                    // For JSON fields, validate and ensure proper formatting
                    $trimmed = trim($value);
                    if (($trimmed[0] === '{' || $trimmed[0] === '[') && $this->is_valid_json($trimmed)) {
                        // Keep JSON as-is, CSV escaping will handle it
                        $processed_row[] = $value;
                    } else {
                        $processed_row[] = $value;
                    }
                } else {
                    $processed_row[] = $value;
                }
            }
            
            $csv_lines[] = $this->escape_csv_line($processed_row);
        }

        return implode("\n", $csv_lines) . "\n";
    }

    /**
     * Escape a CSV line with proper quote handling.
     * Ensures all fields are safely quoted and quotes within fields are doubled.
     *
     * @param array $fields Array of field values.
     * @return string Properly escaped CSV line.
     */
    private function escape_csv_line(array $fields): string {
        $escaped_fields = [];
        
        foreach ($fields as $field) {
            $field_str = (string) $field;
            
            // Always quote fields and double any internal quotes
            // This ensures that fields with commas, quotes, or newlines are handled safely
            $escaped_field = '"' . str_replace('"', '""', $field_str) . '"';
            $escaped_fields[] = $escaped_field;
        }
        
        return implode(',', $escaped_fields);
    }

    /**
     * Check if string is valid JSON.
     *
     * @param string $string String to validate.
     * @return bool True if valid JSON.
     */
    private function is_valid_json(string $string): bool {
        json_decode($string, true);
        return json_last_error() === JSON_ERROR_NONE;
    }

    /**
     * Generate a filename for CSV export with optional batch info.
     *
     * @param string $prefix Prefix for the filename (e.g., 'email-log', 'crm-log').
     * @param int $batch Optional batch number.
     * @param int $total_batches Optional total batch count.
     * @return string CSV filename.
     */
    protected function get_export_filename(string $prefix, int $batch = 0, int $total_batches = 0): string {
        $filename = $prefix . '-export-' . gmdate('Y-m-d-His');
        if ($batch > 0 && $total_batches > 0) {
            $filename .= '-b' . $batch . '-of' . $total_batches;
        }
        $filename .= '.csv';
        return $filename;
    }

    /**
     * Send CSV download headers and output CSV data.
     *
     * @param string $csv CSV data to output.
     * @param string $filename Filename for download.
     */
    protected function send_csv_download(string $csv, string $filename): void {
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Cache-Control: no-cache, no-store, must-revalidate');
        header('Pragma: no-cache');
        header('Expires: 0');
        echo $csv;
        exit;
    }
}
