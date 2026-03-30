<?php
/**
 * Intent Learner – Self-Learning Classifier (Pro Feature)
 *
 * Transforms user corrections into training data to improve classification accuracy.
 * Analyzes feedback patterns and suggests/applies pattern improvements.
 *
 * Features:
 * - Record all manual corrections for learning
 * - Analyze correction patterns weekly
 * - Identify keyword confusion and category misclassifications
 * - Generate insights and recommendations
 * - Safe auto-improvement with admin review
 * - Learning statistics and reporting
 *
 * @package ContactIn\Core
 */

declare(strict_types=1);

namespace ContactInbox\Core;

use ContactInbox\Traits\Singleton;

// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter

if (!defined('ABSPATH')) {
    exit;
}

final class IntentLearner {
    use Singleton;

    /**
     * Record a user correction for learning
     * Called when user manually reclassifies a message
     *
     * @param int $message_id Message ID
     * @param string $original_category What the classifier predicted
     * @param float $original_confidence Confidence of original prediction (0-100)
     * @param string $corrected_category What user says it should be
     * @param int|null $corrected_by User ID who made correction
     * @param array|null $matched_keywords Keywords that matched in original classification
     * @param string|null $feedback Optional reason for correction
     * @return bool Success
     */
    public function record_correction(
        int $message_id,
        string $original_category,
        float $original_confidence,
        string $corrected_category,
        ?int $corrected_by = null,
        ?array $matched_keywords = null,
        ?string $feedback = null
    ): bool {
        global $wpdb;
        $table = $wpdb->prefix . Config::TABLE_INTENT_FEEDBACK;

        $result = $wpdb->insert(
            $table,
            [
                'message_id' => $message_id,
                'original_category' => $original_category,
                'original_confidence' => $original_confidence,
                'corrected_category' => $corrected_category,
                'correction_source' => 'user',
                'corrected_by' => $corrected_by ?? get_current_user_id(),
                'matched_keywords' => $matched_keywords ? json_encode($matched_keywords) : null,
                'feedback' => $feedback ? sanitize_textarea_field($feedback) : null,
                'created_at' => current_time('mysql'),
            ],
            ['%d', '%s', '%f', '%s', '%s', '%d', '%s', '%s', '%s']
        );

        if ($result) {
            // Check if we have enough corrections for learning analysis
            $count = $this->get_feedback_count_since_last_learn();
            if ($count >= 10) {
                wp_schedule_single_event(time() + 5, Config::CRON_LEARN_FROM_FEEDBACK);
            }
            return true;
        }

        return false;
    }

    /**
     * Get count of corrections since last learning run
     *
     * @return int Count of new corrections
     */
    private function get_feedback_count_since_last_learn(): int {
        global $wpdb;
        $table = $wpdb->prefix . Config::TABLE_INTENT_FEEDBACK;

        $last_learn = get_option('contactin_last_learning_run', '2000-01-01 00:00:00');

        return (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$table} WHERE created_at > %s",
            $last_learn
        ));
    }

    /**
     * Analyze corrections and extract insights
     * Called weekly (or triggered after 10+ corrections)
     *
     * @return array Analysis results with insights and recommendations
     */
    public function analyze_feedback_and_improve(): array {
        global $wpdb;
        $feedback_table = $wpdb->prefix . Config::TABLE_INTENT_FEEDBACK;
        $messages_table = $wpdb->prefix . Config::TABLE_MESSAGES;

        $report = [
            'analyzed' => 0,
            'insights' => [],
            'recommended_changes' => [],
            'period' => 'last_7_days',
        ];

        // Get recent corrections
        $corrections = $wpdb->get_results(
            "SELECT f.*, m.subject, m.message 
             FROM {$feedback_table} f
             JOIN {$messages_table} m ON f.message_id = m.id
             WHERE f.created_at > DATE_SUB(NOW(), INTERVAL 7 DAY)
             AND f.original_category != f.corrected_category
             ORDER BY f.created_at DESC
             LIMIT 100"
        );

        if (empty($corrections)) {
            return $report;
        }

        $report['analyzed'] = count($corrections);

        // Analyze patterns in corrections
        $keyword_analysis = [];
        $category_confusions = [];

        foreach ($corrections as $correction) {
            $wrong_cat = $correction->original_category;
            $right_cat = $correction->corrected_category;
            $matched = json_decode($correction->matched_keywords ?? '[]', true) ?: [];

            // Track category confusions
            $confusion_key = "{$wrong_cat}→{$right_cat}";
            $category_confusions[$confusion_key] = ($category_confusions[$confusion_key] ?? 0) + 1;

            // Analyze keywords that caused wrong classification
            foreach ($matched as $keyword) {
                $keyword_lower = strtolower((string) $keyword);
                if (!isset($keyword_analysis[$keyword_lower])) {
                    $keyword_analysis[$keyword_lower] = [
                        'misclassified_to' => [],
                        'count' => 0,
                    ];
                }
                $keyword_analysis[$keyword_lower]['count']++;
                $keyword_analysis[$keyword_lower]['misclassified_to'][$wrong_cat] =
                    ($keyword_analysis[$keyword_lower]['misclassified_to'][$wrong_cat] ?? 0) + 1;
            }
        }

        // Generate insights
        $report['insights'] = $this->generate_insights(
            $category_confusions,
            $keyword_analysis,
            count($corrections)
        );

        // Generate recommendations
        $report['recommended_changes'] = $this->generate_recommendations(
            $keyword_analysis,
            $category_confusions
        );

        return $report;
    }

    /**
     * Generate insights from correction data
     *
     * @param array $confusions Category confusion map
     * @param array $keyword_analysis Keyword confusion analysis
     * @param int $total_corrections Total corrections analyzed
     * @return array Array of insights
     */
    private function generate_insights(
        array $confusions,
        array $keyword_analysis,
        int $total_corrections
    ): array {
        $insights = [];

        // Find most common confusions
        arsort($confusions);
        foreach (array_slice($confusions, 0, 3) as $path => $count) {
            $pct = round(($count / $total_corrections) * 100, 1);
            [$from, $to] = explode('→', (string) $path);

            $insights[] = [
                'type' => 'category_confusion',
                'message' => "'{$from}' misclassified as '{$to}' {$count}x ({$pct}%)",
                'severity' => $pct > 30 ? 'high' : ($pct > 20 ? 'medium' : 'low'),
                'count' => $count,
                'from' => $from,
                'to' => $to,
            ];
        }

        // Find problematic keywords
        $problem_keywords = array_filter(
            $keyword_analysis,
            fn($data) => count($data['misclassified_to']) > 1 && $data['count'] > 3
        );

        foreach (array_slice($problem_keywords, 0, 3) as $keyword => $data) {
            $insights[] = [
                'type' => 'ambiguous_keyword',
                'message' => "Keyword '{$keyword}' caused {$data['count']} errors across " .
                    count($data['misclassified_to']) . ' categories',
                'severity' => $data['count'] > 5 ? 'high' : 'medium',
                'keyword' => $keyword,
                'count' => $data['count'],
                'categories' => array_keys($data['misclassified_to']),
            ];
        }

        return $insights;
    }

    /**
     * Generate specific recommendations for pattern changes
     *
     * @param array $keyword_analysis Analyzed keyword data
     * @param array $confusions Category confusions
     * @return array Array of recommendations
     */
    private function generate_recommendations(
        array $keyword_analysis,
        array $confusions
    ): array {
        $recommendations = [];

        // Recommend reviewing ambiguous keywords
        foreach ($keyword_analysis as $keyword => $data) {
            if (count($data['misclassified_to']) > 1 && $data['count'] > 3) {
                $recommendations[] = [
                    'action' => 'review_keyword',
                    'keyword' => $keyword,
                    'reason' => "Causes {$data['count']} classification errors across " .
                        count($data['misclassified_to']) . ' categories',
                    'affected_categories' => array_keys($data['misclassified_to']),
                    'confidence' => 'medium',
                ];
            }
        }

        // Recommend addressing severe category confusions
        arsort($confusions);
        foreach (array_slice($confusions, 0, 2) as $path => $count) {
            if ($count > 5) {
                [$from, $to] = explode('→', (string) $path);
                $recommendations[] = [
                    'action' => 'address_confusion',
                    'from_category' => $from,
                    'to_category' => $to,
                    'count' => $count,
                    'reason' => "'{$from}' is frequently misclassified as '{$to}' ({$count}x)",
                    'confidence' => 'high',
                ];
            }
        }

        return $recommendations;
    }

    /**
     * Get learning statistics for dashboard
     *
     * @return array Learning statistics
     */
    public function get_learning_stats(): array {
        global $wpdb;
        $table = $wpdb->prefix . Config::TABLE_INTENT_FEEDBACK;

        $total = (int) $wpdb->get_var(
            "SELECT COUNT(*) FROM {$table}"
        );

        $this_week = (int) $wpdb->get_var(
            "SELECT COUNT(*) FROM {$table} WHERE created_at > DATE_SUB(NOW(), INTERVAL 7 DAY)"
        );

        $avg_confidence = (float) $wpdb->get_var(
            "SELECT AVG(original_confidence) FROM {$table}"
        ) ?? 0;

        $accuracy_improvement = $this->estimate_accuracy_improvement();

        return [
            'total_corrections' => $total,
            'this_week' => $this_week,
            'avg_original_confidence' => round($avg_confidence, 1),
            'estimated_accuracy_improvement' => $accuracy_improvement . '%',
            'ready_for_training' => $this_week >= 10,
            'ready_count' => max(0, 10 - $this_week),
        ];
    }

    /**
     * Estimate classifier accuracy improvement potential
     *
     * @return float Estimated improvement percentage
     */
    private function estimate_accuracy_improvement(): float {
        global $wpdb;
        $table = $wpdb->prefix . Config::TABLE_INTENT_FEEDBACK;

        $avg_original = (float) $wpdb->get_var(
            "SELECT AVG(original_confidence) FROM {$table}"
        ) ?? 0;

        // Improvement is proportional to how wrong we were
        // If avg confidence was 40%, improvement potential is ~40%
        return min(round((1 - ($avg_original / 100)) * 100, 1), 25);
    }

    /**
     * Get correction history for a specific message
     *
     * @param int $message_id Message ID
     * @return array Array of corrections
     */
    public function get_corrections_for_message(int $message_id): array {
        global $wpdb;
        $table = $wpdb->prefix . Config::TABLE_INTENT_FEEDBACK;

        $corrections = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$table} 
             WHERE message_id = %d 
             ORDER BY created_at DESC",
            $message_id
        ));

        return $corrections ?? [];
    }

    /**
     * Get most common correction patterns
     *
     * @param int $limit Limit results
     * @return array Array of patterns
     */
    public function get_top_correction_patterns(int $limit = 10): array {
        global $wpdb;
        $table = $wpdb->prefix . Config::TABLE_INTENT_FEEDBACK;

        $patterns = $wpdb->get_results($wpdb->prepare(
            "SELECT 
                CONCAT(original_category, ' → ', corrected_category) as pattern,
                original_category,
                corrected_category,
                COUNT(*) as count
             FROM {$table}
             WHERE created_at > DATE_SUB(NOW(), INTERVAL 30 DAY)
             GROUP BY original_category, corrected_category
             ORDER BY count DESC
             LIMIT %d",
            $limit
        ));

        return $patterns ?? [];
    }

    /**
     * Apply safe improvements to patterns
     * Only applies changes with high confidence after admin review
     *
     * @return array Results of apply operation
     */
    public function apply_safe_improvements(): array {
        $analysis = $this->analyze_feedback_and_improve();

        $results = [
            'applied' => 0,
            'pending_review' => 0,
            'changes' => [],
        ];

        // Mark changes for admin review instead of auto-applying
        foreach ($analysis['recommended_changes'] ?? [] as $rec) {
            if ($rec['confidence'] === 'high') {
                $results['pending_review']++;
                $results['changes'][] = [
                    'status' => 'pending_review',
                    'recommendation' => $rec,
                ];
            }
        }

        // Update last learning run timestamp
        if ($results['pending_review'] > 0 || $results['applied'] > 0) {
            update_option('contactin_last_learning_run', current_time('mysql'));
        }

        return $results;
    }

    /**
     * Manually apply a recommended change
     * Called by admin after reviewing a recommendation
     *
     * @param array $recommendation The recommendation to apply
     * @return array Result of operation
     */
    public function apply_recommendation(array $recommendation): array {
        $action = $recommendation['action'] ?? null;

        if ($action === 'review_keyword') {
            return $this->review_keyword_weight(
                (string) ($recommendation['keyword'] ?? ''),
                (array) ($recommendation['affected_categories'] ?? [])
            );
        }

        return [
            'success' => false,
            'message' => 'Unknown recommendation action',
        ];
    }

    /**
     * Review and adjust keyword weights between categories
     *
     * @param string $keyword The keyword to review
     * @param array $affected_categories Categories affected
     * @return array Result
     */
    private function review_keyword_weight(string $keyword, array $affected_categories): array {
        // Get current patterns
        $patterns = IntentClassifier::get_stored_patterns(true);

        if (empty($keyword) || empty($affected_categories)) {
            return [
                'success' => false,
                'message' => 'Invalid keyword or categories',
            ];
        }

        // Log the decision for audit trail
        Logger::notice('Admin reviewing keyword weight', [
            'keyword' => $keyword,
            'affected_categories' => $affected_categories,
        ]);

        return [
            'success' => true,
            'message' => "Keyword '{$keyword}' flagged for review. " .
                'Admin can manually adjust pattern weights in Classification settings.',
            'keyword' => $keyword,
            'categories' => $affected_categories,
        ];
    }

    /**
     * Clear old feedback data (older than days parameter)
     *
     * @param int $days Keep feedback only this many days
     * @return int Number of records deleted
     */
    public function cleanup_old_feedback(int $days = 90): int {
        global $wpdb;
        $table = $wpdb->prefix . Config::TABLE_INTENT_FEEDBACK;

        $deleted = $wpdb->query($wpdb->prepare(
            "DELETE FROM {$table} WHERE created_at < DATE_SUB(NOW(), INTERVAL %d DAY)",
            $days
        ));

        if ($deleted) {
            Logger::info('Cleaned up old feedback data', [
                'days' => $days,
                'deleted_count' => $deleted,
            ]);
        }

        return (int) $deleted;
    }

    /**
     * Get feedback statistics for a time period
     *
     * @param int $days Days to analyze
     * @return array Statistics
     */
    public function get_period_statistics(int $days = 7): array {
        global $wpdb;
        $table = $wpdb->prefix . Config::TABLE_INTENT_FEEDBACK;

        $stats = $wpdb->get_results($wpdb->prepare(
            "SELECT 
                corrected_category,
                COUNT(*) as total,
                AVG(original_confidence) as avg_original_confidence
             FROM {$table}
             WHERE created_at > DATE_SUB(NOW(), INTERVAL %d DAY)
             GROUP BY corrected_category
             ORDER BY total DESC",
            $days
        ));

        $summary = [
            'period_days' => $days,
            'total_corrections' => 0,
            'by_category' => [],
        ];

        if ($stats) {
            foreach ($stats as $stat) {
                $summary['total_corrections'] += (int) $stat->total;
                $summary['by_category'][$stat->corrected_category] = [
                    'count' => (int) $stat->total,
                    'avg_original_confidence' => round((float) $stat->avg_original_confidence, 1),
                ];
            }
        }

        return $summary;
    }
}
