<?php
/**
 * CRM Duplicate Resolver Trait
 * 
 * Handles resolution of duplicate contacts in Salesforce when HTTP 300 is returned.
 * Uses intelligent name matching and fallback strategies.
 *
 * @package ContactIn\Core\Traits
 */

declare(strict_types=1);

namespace ContactInbox\Core\Traits;

use ContactInbox\Core\Logger;

if (!defined('ABSPATH')) {
    exit;
}

trait CRMDuplicateResolver
{
    /**
     * Resolve duplicate contacts by matching name when HTTP 300 is returned
     * 
     * @param string $email Email address
     * @param string $submitted_name Name from form submission (could be FirstName LastName or just name)
     * @param string $response_body JSON array of contact URLs from HTTP 300 response
     * @param string $base_url Salesforce instance URL
     * @param string $api_version API version
     * @param array $headers Request headers with auth
     * @return string|\WP_Error Contact ID or error
     */
    protected function resolve_duplicate_contact(
        string $email,
        string $submitted_name,
        string $response_body,
        string $base_url,
        string $api_version,
        array $headers
    ) {
        $contact_urls = json_decode($response_body, true);
        
        if (!is_array($contact_urls) || empty($contact_urls)) {
            return new \WP_Error('invalid_duplicate_response', 'Invalid HTTP 300 response format');
        }

        // Extract Contact IDs from URLs
        $contact_ids = [];
        foreach ($contact_urls as $url) {
            // URL format: /services/data/v58.0/sobjects/Contact/003fj00000XL3HdAAL
            if (preg_match('/\/Contact\/([a-zA-Z0-9]+)$/', $url, $matches)) {
                $contact_ids[] = $matches[1];
            }
        }

        if (empty($contact_ids)) {
            return new \WP_Error('no_contact_ids', 'Could not extract contact IDs from HTTP 300 response');
        }

        Logger::info('Extracted contact IDs from duplicate response', [
            'email' => $email,
            'contact_ids' => $contact_ids,
            'count' => count($contact_ids),
        ]);

        // Normalize submitted name for comparison
        $submitted_name_normalized = $this->normalize_name($submitted_name);

        // Fetch all duplicate contacts to compare names
        $contacts = $this->fetch_contacts_by_ids($contact_ids, $base_url, $api_version, $headers);
        
        if (is_wp_error($contacts)) {
            // Fallback: Use first ID if we can't fetch contact details
            Logger::warning('Could not fetch duplicate contacts for name matching, using first ID', [
                'email' => $email,
                'contact_id' => $contact_ids[0],
                'error' => $contacts->get_error_message(),
            ]);
            return $contact_ids[0];
        }

        // Try to match by name
        $best_match = null;
        $best_match_score = 0;

        foreach ($contacts as $contact) {
            $full_name = trim(($contact['FirstName'] ?? '') . ' ' . ($contact['LastName'] ?? ''));
            $normalized_full_name = $this->normalize_name($full_name);

            // Calculate match score
            $score = $this->calculate_name_match_score($submitted_name_normalized, $normalized_full_name);

            Logger::debug('Name match comparison', [
                'email' => $email,
                'contact_id' => $contact['Id'],
                'contact_name' => $full_name,
                'submitted_name' => $submitted_name,
                'score' => $score,
            ]);

            if ($score > $best_match_score) {
                $best_match_score = $score;
                $best_match = $contact;
            }
        }

        // If we found a good match (score > 0.7), use it
        if ($best_match && $best_match_score >= 0.7) {
            Logger::info('Found name match for duplicate contact', [
                'email' => $email,
                'contact_id' => $best_match['Id'],
                'contact_name' => trim(($best_match['FirstName'] ?? '') . ' ' . ($best_match['LastName'] ?? '')),
                'match_score' => $best_match_score,
            ]);
            return $best_match['Id'];
        }

        // No good name match found - use most recently modified contact as fallback
        usort($contacts, function($a, $b) {
            $a_time = strtotime($a['LastModifiedDate'] ?? '1970-01-01');
            $b_time = strtotime($b['LastModifiedDate'] ?? '1970-01-01');
            return $b_time - $a_time; // Descending order
        });

        $selected_contact = $contacts[0];
        Logger::info('No name match found, using most recently modified contact', [
            'email' => $email,
            'contact_id' => $selected_contact['Id'],
            'last_modified' => $selected_contact['LastModifiedDate'] ?? 'unknown',
            'submitted_name' => $submitted_name,
        ]);

        return $selected_contact['Id'];
    }

    /**
     * Fetch multiple contacts by IDs from Salesforce
     * 
     * @param array $contact_ids Array of contact IDs
     * @param string $base_url Salesforce instance URL
     * @param string $api_version API version
     * @param array $headers Request headers
     * @return array|\WP_Error Array of contact records or error
     */
    protected function fetch_contacts_by_ids(array $contact_ids, string $base_url, string $api_version, array $headers) {
        // Build SOQL query to fetch all contacts
        $ids_quoted = array_map(function($id) {
            return "'" . str_replace("'", "\\'", $id) . "'";
        }, $contact_ids);
        
        $ids_list = implode(',', $ids_quoted);
        $query = "SELECT Id, FirstName, LastName, Email, LastModifiedDate FROM Contact WHERE Id IN ({$ids_list})";
        $query_encoded = rawurlencode($query);
        $query_url = "{$base_url}/services/data/{$api_version}/query?q={$query_encoded}";

        $response = wp_remote_get($query_url, [
            'headers' => $headers,
            'timeout' => 15,
        ]);

        if (is_wp_error($response)) {
            return new \WP_Error('contact_fetch_failed', $response->get_error_message());
        }

        $code = wp_remote_retrieve_response_code($response);
        $body = json_decode(wp_remote_retrieve_body($response), true);

        if ($code !== 200 || empty($body['records'])) {
            $detail = $body['message'] ?? 'Contact fetch returned no results';
            return new \WP_Error('contact_fetch_failed', $detail);
        }

        return $body['records'];
    }

    /**
     * Normalize name for comparison (lowercase, trim, remove extra spaces)
     */
    protected function normalize_name(string $name): string {
        $name = strtolower(trim($name));
        $name = preg_replace('/\s+/', ' ', $name); // Multiple spaces to single
        return $name;
    }

    /**
     * Calculate name match score using Levenshtein distance
     * Returns score between 0 (no match) and 1 (perfect match)
     */
    protected function calculate_name_match_score(string $name1, string $name2): float {
        if (empty($name1) || empty($name2)) {
            return 0.0;
        }

        // Exact match
        if ($name1 === $name2) {
            return 1.0;
        }

        // Check if one contains the other (partial match)
        if (strpos($name1, $name2) !== false || strpos($name2, $name1) !== false) {
            return 0.85;
        }

        // Use Levenshtein distance for fuzzy matching
        $max_len = max(strlen($name1), strlen($name2));
        if ($max_len === 0) {
            return 0.0;
        }

        $distance = levenshtein($name1, $name2);
        $similarity = 1.0 - ($distance / $max_len);

        return max(0.0, $similarity);
    }
}
