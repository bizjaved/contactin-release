<?php
declare(strict_types=1);

namespace ContactInbox\Core\Traits;

use ContactInbox\Core\Config;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Query Result Caching Trait
 *
 * Provides in-memory and transient caching for expensive query results.
 * Reduces database queries for frequently accessed data like CRM settings.
 *
 * Usage:
 * ```php
 * class MyRepository {
 *     use QueryCache;
 *
 *     public function get_settings() {
 *         return $this->cache_or_query(
 *             'crm_settings',
 *             fn() => $this->query_settings(),
 *             HOUR_IN_SECONDS * 6 // Cache for 6 hours
 *         );
 *     }
 * }
 * ```
 *
 * @package ContactIn\Core\Traits
 */
trait QueryCache {

	private static array $memory_cache = array();

	/**
	 * Get cached result or execute query
	 *
	 * Uses two-tier caching:
	 * 1. In-memory cache (request scope) - checked first
	 * 2. Transient cache (6-hour TTL by default) - used across requests
	 *
	 * @param string   $cache_key Unique cache key
	 * @param callable $query_fn Function that executes the query
	 * @param int      $ttl Time to live in seconds (default 6 hours)
	 * @return mixed Cached result or query result
	 */
	protected function cache_or_query(
		string $cache_key,
		callable $query_fn,
		int $ttl = 21600
	): mixed {
		// Check in-memory cache first (request scope)
		if ( isset( self::$memory_cache[ $cache_key ] ) ) {
			return self::$memory_cache[ $cache_key ];
		}

		// Check transient cache (6-hour TTL)
		$cached = get_transient( $this->get_transient_key( $cache_key ) );
		if ( $cached !== false ) {
			self::$memory_cache[ $cache_key ] = $cached;
			return $cached;
		}

		// Execute query
		$result = $query_fn();

		// Store in both caches
		self::$memory_cache[ $cache_key ] = $result;
		set_transient( $this->get_transient_key( $cache_key ), $result, $ttl );

		return $result;
	}

	/**
	 * Invalidate specific cache entry
	 *
	 * Removes from both in-memory and transient caches
	 *
	 * @param string $cache_key Cache key to invalidate
	 * @return void
	 */
	protected function invalidate_cache( string $cache_key ): void {
		unset( self::$memory_cache[ $cache_key ] );
		delete_transient( $this->get_transient_key( $cache_key ) );
	}

	/**
	 * Invalidate all caches matching pattern
	 *
	 * @param string $pattern Pattern to match (supports wildcards)
	 * @return void
	 */
	protected function invalidate_cache_pattern( string $pattern ): void {
		// Invalidate in-memory caches matching pattern
		foreach ( self::$memory_cache as $key => $value ) {
			if ( fnmatch( $pattern, $key ) ) {
				unset( self::$memory_cache[ $key ] );
			}
		}

		// Note: Transient deletion doesn't support patterns in WordPress
		// Would require direct DB query for pattern matching, skip for now
	}

	/**
	 * Clear all caches
	 *
	 * @return void
	 */
	protected function flush_cache(): void {
		self::$memory_cache = array();
		// Note: Complete transient flush requires iterating all options, skip for performance
	}

	/**
	 * Get transient key with plugin prefix
	 *
	 * @param string $cache_key Base cache key
	 * @return string Full transient key
	 */
	private function get_transient_key( string $cache_key ): string {
		return Config::PLUGIN_SLUG . '_cache_' . $cache_key;
	}

	/**
	 * Get cache memory usage statistics
	 *
	 * @return array Cache stats
	 */
	public static function get_cache_stats(): array {
		$memory_usage = 0;
		foreach ( self::$memory_cache as $value ) {
			$memory_usage += strlen( serialize( $value ) );
		}

		return array(
			'memory_items'       => count( self::$memory_cache ),
			'memory_usage_bytes' => $memory_usage,
			'memory_keys'        => array_keys( self::$memory_cache ),
		);
	}
}
