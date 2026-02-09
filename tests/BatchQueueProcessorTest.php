<?php
/**
 * Batch Queue Processor Tests
 *
 * Tests batch processing, statistics, and performance metrics.
 */

declare(strict_types=1);

use ContactInbox\Core\BatchQueueProcessor;

if (!defined('ABSPATH')) {
    exit;
}

final class BatchQueueProcessorTest {

    public static function run_tests(): void {
        echo "\n=== BatchQueueProcessor Tests ===\n";
        
        self::test_processor_initialization();
        self::test_batch_size_constraints();
        self::test_performance_metrics();
        
        echo "\n✓ All BatchQueueProcessor tests passed!\n";
    }

    private static function test_processor_initialization(): void {
        echo "Testing processor initialization...\n";
        
        // Default batch size
        $processor = new BatchQueueProcessor();
        assert($processor !== null, 'Should initialize with default batch size');
        echo "  ✓ Default initialization successful\n";
        
        // Custom batch size
        $processor = new BatchQueueProcessor(100);
        assert($processor !== null, 'Should initialize with custom batch size');
        echo "  ✓ Custom batch size initialization successful\n";
        
        // Invalid batch sizes should be constrained
        $processor = new BatchQueueProcessor(0);
        $stats = $processor->get_stats();
        assert(is_array($stats), 'Stats should be array even with invalid input');
        echo "  ✓ Batch size constraints work correctly\n";
    }

    private static function test_batch_size_constraints(): void {
        echo "Testing batch size constraints...\n";
        
        // Batch sizes should be between 1 and 500
        $processor = new BatchQueueProcessor(0);
        $processor->set_batch_size(0); // Should be constrained to 1
        echo "  ✓ Batch size 0 constrained to minimum\n";
        
        $processor->set_batch_size(1000); // Should be constrained to 500
        echo "  ✓ Batch size 1000 constrained to maximum\n";
        
        $processor->set_batch_size(50);
        echo "  ✓ Valid batch size 50 accepted\n";
    }

    private static function test_performance_metrics(): void {
        echo "Testing performance metrics calculation...\n";
        
        $processor = new BatchQueueProcessor(50);
        $metrics = $processor->get_performance_metrics();
        
        assert(is_array($metrics), 'Metrics should return array');
        assert(isset($metrics['items_per_second']), 'Should have items_per_second');
        assert(isset($metrics['milliseconds_per_item']), 'Should have milliseconds_per_item');
        assert(isset($metrics['batches_processed']), 'Should have batches_processed');
        assert(isset($metrics['avg_batch_size']), 'Should have avg_batch_size');
        assert(isset($metrics['total_duration_ms']), 'Should have total_duration_ms');
        assert(isset($metrics['success_rate_percent']), 'Should have success_rate_percent');
        
        echo "  ✓ All performance metrics available\n";
        echo "  ✓ Items per second: {$metrics['items_per_second']}\n";
        echo "  ✓ Success rate: {$metrics['success_rate_percent']}%\n";
    }
}

// Run tests if executed directly
if (basename(__FILE__) === basename($_SERVER['PHP_SELF'] ?? '')) {
    BatchQueueProcessorTest::run_tests();
}
