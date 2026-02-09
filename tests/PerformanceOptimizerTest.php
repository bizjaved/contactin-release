<?php
/**
 * Performance Optimizer Tests
 *
 * Tests index creation, table optimization, and cache diagnostics.
 */

declare(strict_types=1);

use ContactInbox\Core\PerformanceOptimizer;

if (!defined('ABSPATH')) {
    exit;
}

final class PerformanceOptimizerTest {

    public static function run_tests(): void {
        echo "\n=== PerformanceOptimizer Tests ===\n";
        
        self::test_index_creation();
        self::test_recommendations();
        self::test_cache_impact_estimates();
        
        echo "\n✓ All PerformanceOptimizer tests passed!\n";
    }

    private static function test_index_creation(): void {
        echo "Testing index creation logic...\n";
        
        $report = PerformanceOptimizer::create_critical_indexes();
        
        assert(is_array($report), 'Index creation should return array');
        assert(isset($report['created']), 'Report should have created key');
        assert(isset($report['existing']), 'Report should have existing key');
        assert(isset($report['failed']), 'Report should have failed key');
        assert(isset($report['total']), 'Report should have total count');
        assert($report['total'] > 0, 'Should have indexes to create');
        
        $total_processed = count($report['created']) + count($report['existing']) + count($report['failed']);
        assert($total_processed === $report['total'], 'All indexes should be accounted for');
        
        echo "  ✓ Index creation report generated successfully\n";
        echo "  ✓ Created: " . count($report['created']) . ", Existing: " . count($report['existing']) . ", Failed: " . count($report['failed']) . "\n";
    }

    private static function test_recommendations(): void {
        echo "Testing optimization recommendations...\n";
        
        $recommendations = PerformanceOptimizer::get_recommendations();
        
        assert(is_array($recommendations), 'Recommendations should return array');
        
        foreach ($recommendations as $rec) {
            assert(isset($rec['type']), 'Recommendation should have type');
            assert(isset($rec['severity']), 'Recommendation should have severity');
            assert(isset($rec['message']), 'Recommendation should have message');
            assert(isset($rec['action']), 'Recommendation should have action');
            
            assert(in_array($rec['severity'], ['low', 'medium', 'high']), 'Severity should be valid');
            echo "  ✓ Recommendation: [{$rec['severity']}] {$rec['type']}\n";
        }
    }

    private static function test_cache_impact_estimates(): void {
        echo "Testing cache impact estimation...\n";
        
        $estimates = PerformanceOptimizer::estimate_cache_impact();
        
        assert(is_array($estimates), 'Estimates should return array');
        assert(isset($estimates['queue_rows']), 'Should have queue_rows');
        assert(isset($estimates['queue_memory_estimate_mb']), 'Should have queue_memory_estimate_mb');
        assert(isset($estimates['settings_cache_memory_estimate_mb']), 'Should have settings_cache_memory_estimate_mb');
        assert(isset($estimates['total_estimate_mb']), 'Should have total_estimate_mb');
        
        assert($estimates['queue_rows'] >= 0, 'Queue rows should be non-negative');
        assert($estimates['total_estimate_mb'] >= 0, 'Memory estimate should be non-negative');
        
        echo "  ✓ Queue rows: " . $estimates['queue_rows'] . "\n";
        echo "  ✓ Estimated memory: " . $estimates['total_estimate_mb'] . " MB\n";
    }
}

// Run tests if executed directly
if (basename(__FILE__) === basename($_SERVER['PHP_SELF'] ?? '')) {
    PerformanceOptimizerTest::run_tests();
}
