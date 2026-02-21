<?php
/**
 * Intent Classifier – Lightweight Message Intent Classification
 *
 * Classifies messages into categories based on keyword patterns and scoring.
 * Provides confidence scores and matched keywords for transparency.
 *
 * ⚙️ PATTERN MANAGEMENT:
 * - Patterns stored in WordPress options (database) for persistence and upgrades
 * - Installed automatically during plugin activation
 * - Updated on each version upgrade via CoreBootstrap
 * - Can be customized via IntentClassifier::update_category_patterns()
 * - Can be reset to defaults via IntentClassifier::reset_to_defaults()
 *
 * 📊 CLASSIFICATION ALGORITHM:
 * - Each category has 3 weight levels: high (10 pts), medium (5 pts), low (2 pts)
 * - Scores calculated per category based on keyword matches
 * - Highest scoring category wins (with MIN_CONFIDENCE = 20% threshold)
 * - Subject line weighted 2x vs message body for importance
 * - Returns: category, confidence %, matched keywords
 *
 * Categories:
 * - sales: Pricing, purchase, quote, inquiries
 * - support: Help requests, issues, problems, repairs
 * - feedback: Suggestions, improvements, feature requests
 * - complaint: Dissatisfaction, refunds, cancellations
 * - question: General questions, how-to queries
 * - spam: Suspicious patterns, promotional content
 * - unclassified: No clear match (confidence < threshold)
 *
 * @package ContactInbox\Core
 */

declare(strict_types=1);

namespace ContactInbox\Core;

use ContactInbox\Traits\Singleton;

if (!defined('ABSPATH')) {
    exit;
}

final class IntentClassifier {
    use Singleton;

    // Intent categories
    public const CATEGORY_SALES = 'sales';
    public const CATEGORY_SUPPORT = 'support';
    public const CATEGORY_FEEDBACK = 'feedback';
    public const CATEGORY_COMPLAINT = 'complaint';
    public const CATEGORY_QUESTION = 'question';
    public const CATEGORY_SPAM = 'spam';
    public const CATEGORY_UNCLASSIFIED = 'unclassified';

    // Pattern versioning for robust updates
    private const PATTERN_VERSION = '1.2.0';

    // Minimum confidence threshold to classify (0-100)
    private const MIN_CONFIDENCE = 20.0;

    // Pattern weights (how much each keyword contributes to score)
    private const WEIGHT_HIGH = 10;
    private const WEIGHT_MEDIUM = 5;
    private const WEIGHT_LOW = 2;

    /**
     * Classification patterns with weighted keywords
     * Loads from database option, with fallback to defaults
     * 
     * @return array<string, array>
     */
    private function get_patterns(): array {
        // Try to load from database first
        $patterns = get_option('contactin_intent_patterns');
        
        if ($patterns && is_array($patterns)) {
            return apply_filters('contactin_intent_patterns', $patterns);
        }
        
        // Fallback to default patterns if not in database
        return apply_filters('contactin_intent_patterns', self::get_default_patterns());
    }

    /**
     * Get default classification patterns
     * Used during plugin activation and as fallback
     *
     * Delegates to BusinessPatterns / GenericPatterns so both the free and
     * Pro editions always share the same canonical keyword set.
     * Business-type selection (Pro feature) is forwarded when the option
     * 'contactin_business_type' is set; falls back to 'generic'.
     *
     * @return array<string, array>
     */
    public static function get_default_patterns(): array {
        $business_type = get_option('contactin_business_type', 'generic');
        return BusinessPatterns::get_patterns((string) $business_type);
    }

    /**
     * Classify a message and return intent data
     * 
     * ENHANCED INTELLIGENCE:
     * - Sentiment analysis (positive/negative/neutral)
     * - Negation detection (not + keyword = opposite meaning)
     * - Contextual disambiguation (word relationships)
     * - Category exclusion rules (prevent conflicting classifications)
     * - Keyword priority weighting based on proximity
     * - Subject/body emphasis weighting
     *
     * @param string $subject Message subject
     * @param string $message Message body
     * @return array{category: string, confidence: float, keywords: array, classified_at: string}
     */
    public function classify(string $subject, string $message): array {
        // Clean and prepare text
        $subject_clean = strtolower(trim($subject));
        $message_clean = strtolower(trim($message));
        
        // Adaptive weighting: If subject is empty, give full weight to message
        // Otherwise subject gets 2x weight since it's typically more focused
        if (empty($subject_clean)) {
            // No subject: message gets all the weight (repeat 3x to compensate)
            $text = $message_clean . ' ' . $message_clean . ' ' . $message_clean;
        } else {
            // Has subject: subject gets 2x weight, message gets 1x
            $text = $subject_clean . ' ' . $subject_clean . ' ' . $message_clean;
        }

        // Get patterns
        $patterns = $this->get_patterns();

        // Detect sentiment (help determine between similar categories)
        $sentiment = $this->analyze_sentiment($subject_clean, $message_clean);

        // Calculate scores for each category
        $scores = [];
        $matched_keywords = [];

        foreach ($patterns as $category => $weights) {
            $category_score = 0;
            $category_matches = [];

            // High weight keywords
            if (!empty($weights['high'])) {
                foreach ($weights['high'] as $keyword) {
                    $count = $this->count_keyword($text, $keyword);
                    if ($count > 0) {
                        // Check for negation (reduces score significantly)
                        $negation_factor = $this->has_negation($text, $keyword) ? 0.3 : 1.0;
                        $category_score += $count * self::WEIGHT_HIGH * $negation_factor;
                        $category_matches[] = $keyword;
                    }
                }
            }

            // Medium weight keywords
            if (!empty($weights['medium'])) {
                foreach ($weights['medium'] as $keyword) {
                    $count = $this->count_keyword($text, $keyword);
                    if ($count > 0) {
                        // Check for negation
                        $negation_factor = $this->has_negation($text, $keyword) ? 0.3 : 1.0;
                        $category_score += $count * self::WEIGHT_MEDIUM * $negation_factor;
                        $category_matches[] = $keyword;
                    }
                }
            }

            // Low weight keywords
            if (!empty($weights['low'])) {
                foreach ($weights['low'] as $keyword) {
                    $count = $this->count_keyword($text, $keyword);
                    if ($count > 0) {
                        // Check for negation
                        $negation_factor = $this->has_negation($text, $keyword) ? 0.3 : 1.0;
                        $category_score += $count * self::WEIGHT_LOW * $negation_factor;
                        $category_matches[] = $keyword;
                    }
                }
            }

            if ($category_score > 0) {
                $scores[$category] = $category_score;
                $matched_keywords[$category] = array_unique($category_matches);
            }
        }

        // No matches found
        if (empty($scores)) {
            return $this->get_default_result();
        }

        // Apply contextual rules and sentiment adjustments
        $scores = $this->apply_contextual_rules($scores, $subject_clean, $message_clean, $sentiment);

        // Find highest scoring category
        arsort($scores);
        $top_category = array_key_first($scores);
        $top_score = $scores[$top_category];

        // Calculate confidence (normalize score to 0-100 range)
        $max_possible_score = 100; // Estimated max score
        $confidence = min(100, ($top_score / $max_possible_score) * 100);

        // If confidence is too low, mark as unclassified
        if ($confidence < self::MIN_CONFIDENCE) {
            return $this->get_default_result();
        }

        return [
            'category' => $top_category,
            'confidence' => round($confidence, 2),
            'keywords' => $matched_keywords[$top_category] ?? [],
            'classified_at' => current_time('mysql'),
        ];
    }

    /**
     * Analyze sentiment of the message
     * Returns: 'positive', 'negative', or 'neutral'
     *
     * @param string $subject Subject line
     * @param string $message Message body
     * @return string
     */
    private function analyze_sentiment(string $subject, string $message): string {
        $text = $subject . ' ' . $message;

        // Positive sentiment indicators
        $positive_words = [
            'thank', 'appreciate', 'love', 'great', 'excellent', 'wonderful', 'amazing',
            'good', 'happy', 'pleased', 'satisfied', 'brilliant', 'fantastic', 'perfect',
            'professional', 'quick', 'fast', 'efficient', 'helpful', 'friendly', 'recommend'
        ];

        // Negative sentiment indicators
        $negative_words = [
            'bad', 'terrible', 'awful', 'horrible', 'worst', 'disappointed', 'unhappy',
            'frustrated', 'angry', 'upset', 'disgusted', 'poor', 'slow', 'late', 'delay',
            'damaged', 'broken', 'failed', 'problem', 'issue', 'complaint', 'refund'
        ];

        $positive_count = 0;
        $negative_count = 0;

        foreach ($positive_words as $word) {
            $positive_count += substr_count($text, $word);
        }

        foreach ($negative_words as $word) {
            $negative_count += substr_count($text, $word);
        }

        if ($negative_count > $positive_count) {
            return 'negative';
        } elseif ($positive_count > $negative_count) {
            return 'positive';
        }

        return 'neutral';
    }

    /**
     * Check if a keyword is negated (preceded by "not", "no", "don't", etc.)
     *
     * @param string $text Full text
     * @param string $keyword Keyword to check
     * @return bool
     */
    private function has_negation(string $text, string $keyword): bool {
        $negation_words = ['not', 'no', "don't", "doesn't", "didn't", "can't", 'unable', 'without'];
        
        // Find the position of the keyword
        $pos = strpos($text, $keyword);
        if ($pos === false) {
            return false;
        }

        // Check for negation within 10 characters before the keyword
        $before = substr($text, max(0, $pos - 30), 30);
        
        foreach ($negation_words as $negation) {
            if (strpos($before, $negation) !== false) {
                return true;
            }
        }

        return false;
    }

    /**
     * Apply contextual rules and sentiment-based adjustments
     * 
     * Rules:
     * - Complaint + positive sentiment → Support/Feedback
     * - Complaint + negative sentiment → Complaint
     * - Support + positive sentiment → Feedback
     * - Sales + negative sentiment → Complaint
     *
     * @param array $scores Category scores
     * @param string $subject Subject line
     * @param string $message Message body
     * @param string $sentiment Detected sentiment
     * @return array Modified scores
     */
    private function apply_contextual_rules(array $scores, string $subject, string $message, string $sentiment): array {
        // Rule 1: If support is top but positive sentiment, consider feedback instead
        if (isset($scores[self::CATEGORY_SUPPORT]) && 
            $sentiment === 'positive' && 
            isset($scores[self::CATEGORY_FEEDBACK])) {
            // Don't override if support is much stronger
            if ($scores[self::CATEGORY_SUPPORT] < $scores[self::CATEGORY_FEEDBACK] * 1.5) {
                $scores[self::CATEGORY_FEEDBACK] *= 1.2;
            }
        }

        // Rule 2: If multiple complaint indicators with negative sentiment, boost complaint score
        if ($sentiment === 'negative' && isset($scores[self::CATEGORY_COMPLAINT])) {
            $complaint_boost = $this->count_keyword($subject . ' ' . $message, 'delay') > 0 ? 1.5 : 1.2;
            $scores[self::CATEGORY_COMPLAINT] *= $complaint_boost;
        }

        // Rule 3: "After sales" or "warranty" should always be support
        if (strpos($subject, 'after sales') !== false || strpos($message, 'after sales') !== false ||
            strpos($subject, 'warranty') !== false || strpos($message, 'warranty') !== false) {
            if (isset($scores[self::CATEGORY_SUPPORT])) {
                $scores[self::CATEGORY_SUPPORT] *= 1.5;
            }
        }

        // Rule 4: High question count with "how/what/why" should favor question category
        $question_keywords = ['how', 'what', 'why', 'when', 'where', 'which'];
        $question_count = 0;
        foreach ($question_keywords as $kw) {
            $question_count += substr_count($subject . ' ' . $message, $kw);
        }
        
        if ($question_count >= 3 && isset($scores[self::CATEGORY_QUESTION])) {
            $scores[self::CATEGORY_QUESTION] *= 1.3;
        }

        // Rule 5: Prevent complaint from being classified as sales
        if (isset($scores[self::CATEGORY_COMPLAINT]) && isset($scores[self::CATEGORY_SALES])) {
            if ($sentiment === 'negative') {
                $scores[self::CATEGORY_SALES] *= 0.5; // Reduce sales score
            }
        }

        return $scores;
    }

    /**
     * Count keyword occurrences in text (handles phrases)
     *
     * @param string $text Haystack
     * @param string $keyword Needle
     * @return int Count
     */
    private function count_keyword(string $text, string $keyword): int {
        $keyword = strtolower($keyword);
        return substr_count($text, $keyword);
    }

    /**
     * Get default unclassified result
     *
     * @return array
     */
    private function get_default_result(): array {
        return [
            'category' => self::CATEGORY_UNCLASSIFIED,
            'confidence' => 0.0,
            'keywords' => [],
            'classified_at' => null,
        ];
    }

    /**
     * Install or update intent classification patterns
     * Called during plugin activation and version updates
     * 
     * ROBUST INSTALLATION PROCESS:
     * 1. Validates patterns structure
     * 2. Backs up existing patterns
     * 3. Installs new patterns with verification
     * 4. Validates installation success
     * 5. Logs installation status
     * 6. Handles errors gracefully with rollback
     *
     * @return array{success: bool, message: string, errors: array, version: string}
     */
    public static function install_patterns(): array {
        $result = [
            'success' => false,
            'message' => '',
            'errors' => [],
            'version' => self::PATTERN_VERSION,
        ];

        try {
            // Step 1: Get default patterns
            $patterns = self::get_default_patterns();
            
            if (empty($patterns) || !is_array($patterns)) {
                $result['errors'][] = 'Failed to load default patterns';
                return $result;
            }

            // Step 2: Validate pattern structure
            $validation = self::validate_patterns($patterns);
            if (!$validation['valid']) {
                $result['errors'] = $validation['errors'];
                return $result;
            }

            // Step 3: Backup existing patterns before update
            $existing = get_option('contactin_intent_patterns');
            if ($existing && is_array($existing)) {
                update_option('contactin_intent_patterns_backup', $existing);
            }

            // Step 4: Install patterns to database
            $install_success = update_option('contactin_intent_patterns', $patterns);
            if (!$install_success && get_option('contactin_intent_patterns') !== $patterns) {
                $result['errors'][] = 'Failed to write patterns to database';
                // Try to restore from backup
                if ($existing) {
                    update_option('contactin_intent_patterns', $existing);
                }
                return $result;
            }

            // Step 5: Store pattern version
            update_option('contactin_intent_patterns_version', self::PATTERN_VERSION);

            // Step 6: Mark installation as complete
            update_option('contactin_patterns_installed', true);
            update_option('contactin_patterns_installed_at', current_time('mysql'));

            // Step 7: Verify installation
            $verification = self::verify_patterns_installation();
            if (!$verification['valid']) {
                $result['errors'] = array_merge($result['errors'], $verification['errors']);
                return $result;
            }

            // Step 8: Log successful installation
            error_log('[ContactInbox] Intent patterns installed successfully. Version: ' . self::PATTERN_VERSION . ', Categories: ' . count($patterns));

            $result['success'] = true;
            $result['message'] = sprintf(
                'Intent patterns installed successfully (%d categories, %s)',
                count($patterns),
                self::PATTERN_VERSION
            );

            return $result;

        } catch (\Exception $e) {
            $result['errors'][] = 'Installation exception: ' . $e->getMessage();
            error_log('[ContactInbox] Intent patterns installation error: ' . $e->getMessage());
            return $result;
        }
    }

    /**
     * Validate patterns structure and content
     *
     * @param array $patterns Patterns to validate
     * @return array{valid: bool, errors: array}
     */
    private static function validate_patterns(array $patterns): array {
        $result = [
            'valid' => true,
            'errors' => [],
        ];

        // Check all required categories exist
        $required_categories = [
            self::CATEGORY_SALES,
            self::CATEGORY_SUPPORT,
            self::CATEGORY_FEEDBACK,
            self::CATEGORY_COMPLAINT,
            self::CATEGORY_QUESTION,
            self::CATEGORY_SPAM,
        ];

        foreach ($required_categories as $category) {
            if (!isset($patterns[$category])) {
                $result['valid'] = false;
                $result['errors'][] = "Missing required category: $category";
                continue;
            }

            $category_data = $patterns[$category];

            // Check weight levels
            foreach (['high', 'medium', 'low'] as $weight) {
                if (!isset($category_data[$weight])) {
                    $result['valid'] = false;
                    $result['errors'][] = "Missing $weight weight for category $category";
                    continue;
                }

                if (!is_array($category_data[$weight])) {
                    $result['valid'] = false;
                    $result['errors'][] = "Invalid $weight weight format for category $category";
                    continue;
                }

                // Minimum keywords check
                if (empty($category_data[$weight])) {
                    $result['valid'] = false;
                    $result['errors'][] = "Category $category has no $weight weight keywords";
                }
            }
        }

        return $result;
    }

    /**
     * Verify patterns were installed correctly
     *
     * @return array{valid: bool, errors: array}
     */
    private static function verify_patterns_installation(): array {
        $result = [
            'valid' => true,
            'errors' => [],
        ];

        // Check patterns exist in database
        $stored = get_option('contactin_intent_patterns');
        if (!$stored || !is_array($stored)) {
            $result['valid'] = false;
            $result['errors'][] = 'Patterns not found in database after installation';
            return $result;
        }

        // Verify all categories present
        $expected = array_keys(self::get_default_patterns());
        foreach ($expected as $category) {
            if (!isset($stored[$category])) {
                $result['valid'] = false;
                $result['errors'][] = "Category $category missing from stored patterns";
            }
        }

        // Verify version marker
        $version = get_option('contactin_intent_patterns_version');
        if ($version !== self::PATTERN_VERSION) {
            $result['valid'] = false;
            $result['errors'][] = "Pattern version mismatch. Expected: " . self::PATTERN_VERSION . ", Got: $version";
        }

        // Calculate and store checksum for integrity
        $checksum = md5(json_encode($stored));
        update_option('contactin_intent_patterns_checksum', $checksum);

        return $result;
    }

    /**
     * Get installation status
     *
     * @return array{installed: bool, version: string, category_count: int, verified: bool, last_installed: string}
     */
    public static function get_installation_status(): array {
        $patterns = get_option('contactin_intent_patterns');
        $version = get_option('contactin_intent_patterns_version');
        $installed_at = get_option('contactin_patterns_installed_at');
        $is_installed = (bool)get_option('contactin_patterns_installed');

        $verification = self::verify_patterns_installation();

        return [
            'installed' => $is_installed && !empty($patterns),
            'version' => $version ?: 'unknown',
            'category_count' => is_array($patterns) ? count($patterns) : 0,
            'verified' => $verification['valid'],
            'last_installed' => $installed_at ?: 'never',
        ];
    }

    /**
     * Restore patterns from backup
     *
     * @return array{success: bool, message: string}
     */
    public static function restore_from_backup(): array {
        $backup = get_option('contactin_intent_patterns_backup');
        
        if (!$backup || !is_array($backup)) {
            return [
                'success' => false,
                'message' => 'No backup patterns found',
            ];
        }

        update_option('contactin_intent_patterns', $backup);
        error_log('[ContactInbox] Intent patterns restored from backup');

        return [
            'success' => true,
            'message' => 'Patterns restored from backup successfully',
        ];
    }

    /**
     * Health check for patterns
     * Returns diagnostics about pattern installation and integrity
     *
     * @return array Diagnostic information
     */
    public static function health_check(): array {
        $status = self::get_installation_status();
        $verification = self::verify_patterns_installation();
        $stored = get_option('contactin_intent_patterns');
        $checksum = get_option('contactin_intent_patterns_checksum');
        $current_checksum = md5(json_encode($stored));

        return [
            'status' => $status,
            'verification' => $verification,
            'checksum_match' => $checksum === $current_checksum,
            'stored_checksum' => $checksum,
            'current_checksum' => $current_checksum,
            'total_keywords' => $stored ? array_sum(array_map(function($cat) {
                return count($cat['high'] ?? []) + count($cat['medium'] ?? []) + count($cat['low'] ?? []);
            }, $stored)) : 0,
            'backup_exists' => !empty(get_option('contactin_intent_patterns_backup')),
            'timestamp' => current_time('mysql'),
        ];
    }

    /**
     * Get stored patterns (with option to include defaults)
     *
     * @param bool $include_defaults Include unmodified defaults
     * @return array
     */
    public static function get_stored_patterns(bool $include_defaults = false): array {
        $stored = get_option('contactin_intent_patterns', []);
        
        if (empty($stored) || !is_array($stored)) {
            return self::get_default_patterns();
        }
        
        if (!$include_defaults) {
            return $stored;
        }
        
        // Merge stored with defaults to fill any missing categories
        $defaults = self::get_default_patterns();
        foreach ($defaults as $category => $weights) {
            if (!isset($stored[$category])) {
                $stored[$category] = $weights;
            }
        }
        
        return $stored;
    }

    /**
     * Update patterns for a specific category
     * 
     * @param string $category Category name
     * @param array $weights New weights {high: [...], medium: [...], low: [...]}
     * @return bool Success
     */
    public static function update_category_patterns(string $category, array $weights): bool {
        $patterns = self::get_stored_patterns(true);
        
        if (!isset($patterns[$category])) {
            return false;
        }
        
        $patterns[$category] = [
            'high' => array_filter($weights['high'] ?? []),
            'medium' => array_filter($weights['medium'] ?? []),
            'low' => array_filter($weights['low'] ?? []),
        ];
        
        return (bool)update_option('contactin_intent_patterns', $patterns);
    }

    /**
     * Reset patterns to defaults
     *
     * @return bool Success
     */
    public static function reset_to_defaults(): bool {
        self::install_patterns();
        return true;
    }

    /**
     * Get all available categories
     *
     * @return array<string, string> Category key => Label
     */
    public static function get_categories(): array {
        return [
            self::CATEGORY_SALES => __('Sales', Config::TEXTDOMAIN),
            self::CATEGORY_SUPPORT => __('Support', Config::TEXTDOMAIN),
            self::CATEGORY_FEEDBACK => __('Feedback', Config::TEXTDOMAIN),
            self::CATEGORY_COMPLAINT => __('Complaint', Config::TEXTDOMAIN),
            self::CATEGORY_QUESTION => __('Question', Config::TEXTDOMAIN),
            self::CATEGORY_SPAM => __('Spam', Config::TEXTDOMAIN),
            self::CATEGORY_UNCLASSIFIED => __('Unclassified', Config::TEXTDOMAIN),
        ];
    }

    /**
     * Get category label
     *
     * @param string $category Category key
     * @return string Label
     */
    public static function get_category_label(string $category): string {
        $categories = self::get_categories();
        return $categories[$category] ?? $categories[self::CATEGORY_UNCLASSIFIED];
    }

    /**
     * Get category color for UI
     *
     * @param string $category Category key
     * @return string Color class suffix
     */
    public static function get_category_color(string $category): string {
        $colors = [
            self::CATEGORY_SALES => 'primary',
            self::CATEGORY_SUPPORT => 'warning',
            self::CATEGORY_FEEDBACK => 'info',
            self::CATEGORY_COMPLAINT => 'danger',
            self::CATEGORY_QUESTION => 'secondary',
            self::CATEGORY_SPAM => 'dark',
            self::CATEGORY_UNCLASSIFIED => 'muted',
        ];

        return $colors[$category] ?? 'muted';
    }

    /**
     * Manually reclassify a message
     *
     * @param int $message_id Message ID
     * @param string $category New category
     * @return bool Success
     */
    public function reclassify(int $message_id, string $category): bool {
        // Validate category
        $valid_categories = array_keys(self::get_categories());
        if (!in_array($category, $valid_categories, true)) {
            return false;
        }

        // Update database
        return DB::instance()->update_message_intent($message_id, [
            'category' => $category,
            'confidence' => 100.0, // Manual classification = 100% confidence
            'keywords' => json_encode(['manual']),
            'classified_at' => current_time('mysql'),
        ]);
    }

    /**
     * Count unclassified messages in database
     *
     * @return int Total count of unclassified messages
     */
    public function count_unclassified_messages(): int {
        return DB::instance()->count_unclassified_messages();
    }

    /**
     * Bulk reclassify unclassified messages
     *
     * @param int $limit Maximum messages to process in this batch
     * @param int $offset Starting position for this batch
     * @return array Results array with 'processed', 'success', 'failed' keys
     */
    public function bulk_classify_unclassified(int $limit = 100, int $offset = 0): array {
        // Get unclassified messages from repository (excluding spam and archived)
        $messages = DB::instance()->get_unclassified_batch($limit, $offset);

        if (empty($messages)) {
            return [
                'processed' => 0,
                'success' => 0,
                'failed' => 0,
                'breakdown' => [],
            ];
        }

        Logger::info('Bulk reclassify unclassified', [
            'total_queried' => count($messages),
            'limit' => $limit,
            'offset' => $offset,
        ]);

        $processed = 0;
        $success = 0;
        $failed = 0;
        $breakdown = [
            self::CATEGORY_SALES => 0,
            self::CATEGORY_SUPPORT => 0,
            self::CATEGORY_FEEDBACK => 0,
            self::CATEGORY_COMPLAINT => 0,
            self::CATEGORY_QUESTION => 0,
            self::CATEGORY_SPAM => 0,
            self::CATEGORY_UNCLASSIFIED => 0,
        ];

        foreach ($messages as $msg) {
            $processed++;
            
            try {
                $intent = $this->classify($msg->subject, $msg->message);
                
                if ($intent['category'] !== self::CATEGORY_UNCLASSIFIED) {
                    // Only count as success if DB update actually succeeds
                    $update_result = DB::instance()->update_message_intent((int) $msg->id, $intent);
                    if ($update_result) {
                        $success++;
                        if (isset($breakdown[$intent['category']])) {
                            $breakdown[$intent['category']]++;
                        }
                    } else {
                        $failed++;
                        Logger::warning('Failed to update intent for message', [
                            'message_id' => $msg->id,
                            'category' => $intent['category'],
                        ]);
                    }
                } else {
                    $failed++;
                    $breakdown[self::CATEGORY_UNCLASSIFIED]++;
                }
            } catch (\Throwable $e) {
                $failed++;
                $breakdown[self::CATEGORY_UNCLASSIFIED]++;
                Logger::warning('Failed to reclassify message', [
                    'message_id' => $msg->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return [
            'processed' => $processed,
            'success' => $success,
            'failed' => $failed,
            'breakdown' => $breakdown,
        ];
    }
}
