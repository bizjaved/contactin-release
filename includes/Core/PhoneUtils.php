<?php
namespace ContactInbox\Core;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Phone Number Utilities
 * 
 * Provides phone number detection, normalization, and classification
 * for intelligent multi-field syncing to Salesforce.
 * 
 * @package ContactIn\Core
 * @since   2.1.0
 */
final class PhoneUtils
{
    /**
     * Phone type constants
     */
    const TYPE_MOBILE = 'mobile';
    const TYPE_HOME = 'home';
    const TYPE_BUSINESS = 'business';
    const TYPE_OTHER = 'other';
    const TYPE_UNKNOWN = 'unknown';

    /**
     * Mobile prefix patterns by country
     * 
     * Format: 'country_code' => ['pattern1', 'pattern2', ...]
     * Patterns are regex-compatible strings
     */
    private static $mobile_patterns = [
        // United States & Canada (+1)
        '1' => [
            // Mobile-heavy area codes
            '201', '202', '203', '206', '207', '213', '214', '215', '216', '217', '218',
            '219', '224', '225', '240', '248', '267', '269', '272', '281', '301', '302',
            '303', '304', '305', '310', '312', '313', '314', '315', '316', '317', '318',
            '319', '321', '323', '330', '331', '334', '336', '337', '339', '347', '351',
            '352', '360', '402', '404', '405', '407', '408', '410', '412', '413', '414',
            '415', '417', '419', '424', '425', '434', '443', '469', '470', '475', '478',
            '480', '484', '503', '504', '505', '507', '508', '509', '510', '512', '513',
            '515', '516', '517', '518', '520', '530', '540', '551', '559', '561', '562',
            '563', '564', '571', '573', '574', '585', '586', '601', '602', '603', '605',
            '606', '607', '608', '609', '610', '612', '614', '615', '616', '617', '618',
            '619', '620', '623', '626', '630', '631', '636', '646', '650', '651', '657',
            '660', '661', '662', '667', '678', '682', '701', '702', '703', '704', '706',
            '707', '708', '712', '713', '714', '715', '716', '717', '718', '719', '720',
            '724', '727', '731', '732', '734', '737', '740', '754', '757', '760', '763',
            '765', '770', '772', '773', '774', '775', '781', '785', '786', '801', '802',
            '803', '804', '805', '806', '808', '810', '812', '813', '814', '815', '816',
            '817', '818', '828', '830', '831', '832', '843', '845', '847', '848', '850',
            '856', '857', '858', '859', '860', '862', '863', '864', '865', '870', '872',
            '878', '901', '903', '904', '908', '909', '910', '912', '913', '914', '915',
            '916', '917', '918', '919', '920', '925', '928', '929', '931', '936', '937',
            '940', '941', '945', '947', '949', '951', '952', '954', '956', '959', '970',
            '971', '972', '973', '975', '978', '979', '980', '984', '985', '989'
        ],
        
        // United Kingdom (+44)
        '44' => [
            '^7[0-9]{9}$',  // UK mobile: 7xxx xxx xxx (starts with 7, 10 digits total)
        ],
        
        // India (+91)
        '91' => [
            '^[6-9][0-9]{9}$',  // Indian mobile: starts with 6/7/8/9, 10 digits total
        ],
        
        // Australia (+61)
        '61' => [
            '^4[0-9]{8}$',  // Australian mobile: 4xx xxx xxx (starts with 4, 9 digits total)
        ],
        
        // UAE (+971)
        '971' => [
            '^5[0-9]{8}$',  // UAE mobile: 5x xxx xxxx (starts with 5, 9 digits total)
        ],
        
        // Saudi Arabia (+966)
        '966' => [
            '^5[0-9]{8}$',  // Saudi mobile: 5x xxx xxxx
        ],
        
        // Pakistan (+92)
        '92' => [
            '^3[0-9]{9}$',  // Pakistan mobile: 3xx xxxxxxx (starts with 3, 10 digits total)
        ],

        // Sri Lanka (+94)
        '94' => [
            '^7[0-9]{8}$',  // Sri Lanka mobile: starts with 7, 9 digits total after country code
        ],
        
        // Kuwait (+965)
        '965' => [
            '^[569][0-9]{7}$',  // Kuwait mobile: starts with 5/6/9, 8 digits total
        ],
    ];

    /**
     * Normalize phone number to E.164 format
     * 
     * Strips all formatting and converts to +[country][number]
     * Example: (555) 123-4567 → +15551234567
     * 
     * @param string $phone Raw phone number
     * @param string $default_country Default country code if not detected (default: '1')
     * @return string Normalized phone in E.164 format
     */
    public static function normalize(string $phone, string $default_country = '1'): string
    {
        // Remove all non-digit characters except +
        $phone = preg_replace('/[^\d+]/', '', trim($phone));
        
        // Empty after cleaning
        if (empty($phone)) {
            return '';
        }
        
        // Already has + prefix
        if (substr($phone, 0, 1) === '+') {
            return $phone;
        }
        
        // Remove leading 0 for international numbers (common in national format)
        // Example: UK 07911123456 → 7911123456, UAE 0501234567 → 501234567
        if (substr($phone, 0, 1) === '0' && $default_country !== '1') {
            $phone = substr($phone, 1);
        }
        
        // Add default country code
        return '+' . $default_country . $phone;
    }

    /**
     * Detect phone number type (mobile, home, business, other)
     * 
     * Uses multiple detection strategies:
     * 1. Country-specific mobile prefix patterns
     * 2. Length heuristics
     * 3. Format patterns
     * 
     * @param string $phone Phone number (normalized or raw)
     * @param array $options Detection options
     *        - 'countries': Array of country codes to check (default: ['1', '44', '91'])
     *        - 'default_country': Default country if not detected (default: '1')
     * @return string Phone type constant (TYPE_MOBILE, TYPE_HOME, TYPE_BUSINESS, TYPE_UNKNOWN)
     */
    public static function detect_type(string $phone, array $options = []): string
    {
        // Normalize first
        $default_country = $options['default_country'] ?? '1';
        $normalized = self::normalize($phone, $default_country);
        
        if (empty($normalized)) {
            return self::TYPE_UNKNOWN;
        }
        
        // Extract country code and number
        $parsed = self::parse($normalized);
        if (!$parsed) {
            return self::TYPE_UNKNOWN;
        }
        
        $country_code = $parsed['country_code'];
        $number = $parsed['number'];
        
        // Check if country is in enabled list (default: all)
        $enabled_countries = $options['countries'] ?? null;
        if ($enabled_countries !== null && !in_array($country_code, $enabled_countries, true)) {
            return self::TYPE_UNKNOWN;
        }
        
        // Check mobile patterns for this country
        if (isset(self::$mobile_patterns[$country_code])) {
            $patterns = self::$mobile_patterns[$country_code];
            
            foreach ($patterns as $pattern) {
                // If pattern is regex (contains ^, $, etc.)
                if (strpos($pattern, '^') !== false || strpos($pattern, '$') !== false) {
                    if (preg_match('/' . $pattern . '/', $number)) {
                        return self::TYPE_MOBILE;
                    }
                } else {
                    // Simple prefix match (for US area codes)
                    // US numbers are 10 digits: 3-digit area code + 7 digits
                    if ($country_code === '1' && strlen($number) === 10) {
                        $area_code = substr($number, 0, 3);
                        if ($area_code === $pattern) {
                            return self::TYPE_MOBILE;
                        }
                    }
                }
            }
        }
        
        // No mobile pattern matched
        return self::TYPE_UNKNOWN;
    }

    /**
     * Parse normalized phone into components
     * 
     * @param string $normalized Normalized phone (+[country][number])
     * @return array|false Array with 'country_code' and 'number', or false if invalid
     */
    public static function parse(string $normalized)
    {
        if (empty($normalized) || substr($normalized, 0, 1) !== '+') {
            return false;
        }
        
        // Strip + prefix
        $digits = substr($normalized, 1);
        
        // Try to extract country code (1-3 digits)
        // Priority: Check known country codes first
        $known_codes = array_keys(self::$mobile_patterns);
        
        // Sort by length descending (longer codes first: '971' before '92' before '1')
        usort($known_codes, function($a, $b) {
            $len_diff = strlen($b) - strlen($a);
            if ($len_diff !== 0) {
                return $len_diff;
            }
            // If same length, sort alphabetically
            return strcmp($a, $b);
        });
        
        foreach ($known_codes as $code) {
            if (substr($digits, 0, strlen($code)) === (string)$code) {
                return [
                    'country_code' => (string)$code,
                    'number' => substr($digits, strlen($code))
                ];
            }
        }
        
        // Fallback: Assume 1-digit country code
        return [
            'country_code' => substr($digits, 0, 1),
            'number' => substr($digits, 1)
        ];
    }

    /**
     * Check if two phone numbers are the same (after normalization)
     * 
     * @param string $phone1 First phone number
     * @param string $phone2 Second phone number
     * @param string $default_country Default country code (tries to auto-detect if not provided)
     * @return bool True if phones are the same after normalization
     */
    public static function are_equal(string $phone1, string $phone2, string $default_country = '1'): bool
    {
        // Try to detect country from phone1 if it has + prefix
        $detected_country = $default_country;
        if (substr(trim($phone1), 0, 1) === '+') {
            $parsed = self::parse(preg_replace('/[^\d+]/', '', $phone1));
            if ($parsed) {
                $detected_country = $parsed['country_code'];
            }
        }
        
        $normalized1 = self::normalize($phone1, $detected_country);
        $normalized2 = self::normalize($phone2, $detected_country);
        
        if (empty($normalized1) || empty($normalized2)) {
            return false;
        }
        
        return $normalized1 === $normalized2;
    }

    /**
     * Format phone number for display
     * 
     * @param string $phone Normalized phone
     * @param string $format Format type: 'international', 'national', 'raw'
     * @return string Formatted phone
     */
    public static function format(string $phone, string $format = 'international'): string
    {
        $normalized = self::normalize($phone);
        
        if ($format === 'raw') {
            return $normalized;
        }
        
        $parsed = self::parse($normalized);
        if (!$parsed) {
            return $phone; // Return as-is if can't parse
        }
        
        $country_code = $parsed['country_code'];
        $number = $parsed['number'];
        
        // Format based on country
        if ($country_code === '1' && strlen($number) === 10) {
            // US/Canada: (555) 123-4567
            return $format === 'international'
                ? '+1 (' . substr($number, 0, 3) . ') ' . substr($number, 3, 3) . '-' . substr($number, 6)
                : '(' . substr($number, 0, 3) . ') ' . substr($number, 3, 3) . '-' . substr($number, 6);
        }
        
        // Default: Just add spaces every 3-4 digits
        return '+' . $country_code . ' ' . chunk_split($number, 3, ' ');
    }

    /**
     * Normalize, classify, and deduplicate phone fields from a payload.
     * Returns normalized phones keyed by their target field.
     */
    public static function bucket(array $payload, string $default_country = '1'): array
    {
        $targets = [
            'mobile_phone' => null,
            'phone'        => null,
            'home_phone'   => null,
            'other_phone'  => null,
        ];

        $fields_order = ['mobile_phone', 'phone', 'home_phone', 'other_phone'];
        $seen = [];

        foreach ($fields_order as $field) {
            $raw = isset($payload[$field]) ? (string) $payload[$field] : '';
            $normalized = self::normalize($raw, $default_country);
            if ($normalized === '') {
                continue;
            }

            if (in_array($normalized, $seen, true)) {
                continue;
            }

            $target = $field;
            if ($field === 'phone') {
                $type = self::detect_type($raw, ['default_country' => $default_country]);
                if ($type === self::TYPE_MOBILE) {
                    $target = 'mobile_phone';
                } elseif ($type === self::TYPE_HOME) {
                    $target = 'home_phone';
                } elseif ($type === self::TYPE_OTHER) {
                    $target = 'other_phone';
                } else {
                    $target = 'phone';
                }
            }

            if ($targets[$target] === null) {
                $targets[$target] = $normalized;
                $seen[] = $normalized;
            }
        }

        return array_filter($targets, static fn($v) => $v !== null && $v !== '');
    }

    /**
     * Convert bucketed phones to CRM-style field map (Salesforce-compatible names).
     * Input expects normalized values keyed by mobile_phone/phone/home_phone/other_phone.
     * Output keys: Phone (primary), MobilePhone, HomePhone, OtherPhone.
     */
    public static function to_crm_fields(array $bucketed): array
    {
        return array_filter([
            'Phone'       => $bucketed['phone'] ?? ($bucketed['mobile_phone'] ?? null),
            'MobilePhone' => $bucketed['mobile_phone'] ?? null,
            'HomePhone'   => $bucketed['home_phone'] ?? null,
            'OtherPhone'  => $bucketed['other_phone'] ?? null,
        ], static fn($v) => $v !== null && $v !== '');
    }
}
