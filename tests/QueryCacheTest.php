<?php
/**
 * Query Cache Trait Tests
 *
 * Tests caching functionality and invalidation strategies.
 */

declare(strict_types=1);

use ContactInbox\Core\Traits\QueryCache;

if (!defined('ABSPATH')) {
    exit;
}

// Mock class to test the trait
final class MockCacheRepository {
    use QueryCache;

    public function test_query(): string {
        return 'test_result_' . time();
    }

    public function cached_query(): string {
        return $this->cache_or_query(
            'test_query',
            fn() => $this->test_query(),
            3600
        );
    }

    public function invalidate_test(): void {
        $this->invalidate_cache('test_query');
    }

    public function clear_all_test(): void {
        $this->flush_cache();
    }
}

final class QueryCacheTest {

    public static function run_tests(): void {
        echo "\n=== QueryCache Trait Tests ===\n";
        
        self::test_cache_or_query();
        self::test_cache_invalidation();
        self::test_cache_stats();
        
        echo "\n✓ All QueryCache tests passed!\n";
    }

    private static function test_cache_or_query(): void {
        echo "Testing cache_or_query functionality...\n";
        
        $repo = new MockCacheRepository();
        
        // First call - should execute query
        $result1 = $repo->cached_query();
        assert(!empty($result1), 'Should return query result');
        echo "  ✓ First call executed query: {$result1}\n";
        
        // Second call immediately - should return cached result
        $result2 = $repo->cached_query();
        assert($result1 === $result2, 'Should return cached result on second call');
        echo "  ✓ Second call returned cached result\n";
    }

    private static function test_cache_invalidation(): void {
        echo "Testing cache invalidation...\n";
        
        $repo = new MockCacheRepository();
        
        // Cache a result
        $result1 = $repo->cached_query();
        
        // Invalidate
        $repo->invalidate_test();
        
        // Next query should execute fresh (different timestamp)
        sleep(1);
        $result2 = $repo->cached_query();
        
        assert($result1 !== $result2, 'Should execute fresh query after invalidation');
        echo "  ✓ Cache invalidation works correctly\n";
    }

    private static function test_cache_stats(): void {
        echo "Testing cache statistics...\n";
        
        $repo = new MockCacheRepository();
        $repo->cached_query();
        
        $stats = MockCacheRepository::get_cache_stats();
        
        assert(is_array($stats), 'Stats should return array');
        assert(isset($stats['memory_items']), 'Should have memory_items');
        assert(isset($stats['memory_usage_bytes']), 'Should have memory_usage_bytes');
        assert(isset($stats['memory_keys']), 'Should have memory_keys');
        
        assert($stats['memory_items'] > 0, 'Should have cached items');
        assert($stats['memory_usage_bytes'] > 0, 'Should have memory usage');
        
        echo "  ✓ Cache stats available\n";
        echo "  ✓ Memory items: {$stats['memory_items']}\n";
        echo "  ✓ Memory usage: {$stats['memory_usage_bytes']} bytes\n";
    }
}

// Run tests if executed directly
if (basename(__FILE__) === basename($_SERVER['PHP_SELF'] ?? '')) {
    QueryCacheTest::run_tests();
}
