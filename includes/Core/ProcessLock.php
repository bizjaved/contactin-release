<?php
/**
 * Process Lock – Distributed Mutual Exclusion for Queue Processing
 *
 * Provides transient-based locking to ensure only one queue processor
 * runs at a time for each process type (email, crm).
 *
 * Uses WordPress transients with auto-expiration for safety:
 * - Lock automatically expires after TTL (default 5 minutes)
 * - If process crashes, lock is released without manual intervention
 * - Prevents concurrent processing that could cause race conditions
 *
 * @package ContactInbox\Core
 * @since   2.0.0
 */

declare(strict_types=1);

namespace ContactInbox\Core;

use ContactInbox\Traits\Singleton;

if (!defined('ABSPATH')) exit;

final class ProcessLock {
    use Singleton;

    private const LOCK_PREFIX = 'contactin_lock_';
    private const DEFAULT_TTL = 300; // 5 minutes

    private static array $acquired_locks = [];

    /**
     * Attempt to acquire a process lock
     *
     * Returns early if another process already holds the lock.
     * Automatically releases when object is destroyed (via __destruct in caller).
     *
     * @param string $process Process identifier ('email', 'crm', 'webhook')
     * @param int $ttl Time-to-live in seconds (default 5 min)
     * @return bool True if lock was successfully acquired, false if already held
     *
     * @example
     * if (!ProcessLock::acquire('email', 300)) {
     *     Logger::debug('Email processor already running');
     *     return;
     * }
     * try {
     *     // Do processing...
     * } finally {
     *     ProcessLock::release('email');
     * }
     */
    public static function acquire(string $process, int $ttl = self::DEFAULT_TTL): bool {
        $lock_key = self::LOCK_PREFIX . sanitize_key($process);

        // Check if lock already exists and is still valid
        $existing = get_transient($lock_key);
        if ($existing !== false) {
            // Lock is held by another process
            return false;
        }

        // Attempt to set the lock
        $success = set_transient($lock_key, time(), $ttl);

        if ($success) {
            // Track acquired locks for cleanup
            self::$acquired_locks[$process] = $lock_key;
            
            Logger::debug("Lock acquired for process: {$process}", [
                'ttl' => $ttl,
                'lock_key' => $lock_key,
            ]);
        }

        return $success;
    }

    /**
     * Release a process lock
     *
     * Safe to call even if lock was never acquired (idempotent).
     *
     * @param string $process Process identifier
     * @return bool True if lock was released, false if it didn't exist
     */
    public static function release(string $process): bool {
        $lock_key = self::LOCK_PREFIX . sanitize_key($process);
        $result = delete_transient($lock_key);

        if ($result) {
            unset(self::$acquired_locks[$process]);
            
            Logger::debug("Lock released for process: {$process}", [
                'lock_key' => $lock_key,
            ]);
        }

        return $result;
    }

    /**
     * Check if a process lock is currently held
     *
     * @param string $process Process identifier
     * @return bool True if lock is held by another process
     */
    public static function is_locked(string $process): bool {
        $lock_key = self::LOCK_PREFIX . sanitize_key($process);
        return get_transient($lock_key) !== false;
    }

    /**
     * Get the time the lock was acquired (if held)
     *
     * Useful for determining how long a process has been running.
     *
     * @param string $process Process identifier
     * @return int|false Timestamp when lock was acquired, or false if not locked
     */
    public static function get_lock_timestamp(string $process): int|false {
        $lock_key = self::LOCK_PREFIX . sanitize_key($process);
        $timestamp = get_transient($lock_key);
        return $timestamp !== false ? intval($timestamp) : false;
    }

    /**
     * Calculate how long a process has been running (if locked)
     *
     * @param string $process Process identifier
     * @return int Seconds since lock was acquired, or 0 if not locked
     */
    public static function get_lock_duration(string $process): int {
        $lock_time = self::get_lock_timestamp($process);
        if ($lock_time === false) {
            return 0;
        }
        return max(0, time() - $lock_time);
    }

    /**
     * Force release a lock (admin recovery function)
     *
     * Use with caution: only force release if you're certain the process crashed.
     * Check lock duration first to avoid killing a legitimately running process.
     *
     * @param string $process Process identifier
     * @param int $min_age_seconds Only force release if lock is older than this (safety check)
     * @return bool True if lock was released
     *
     * @example
     * $duration = ProcessLock::get_lock_duration('email');
     * if ($duration > 600) { // Older than 10 minutes
     *     ProcessLock::force_release('email', 600);
     * }
     */
    public static function force_release(string $process, int $min_age_seconds = 0): bool {
        $duration = self::get_lock_duration($process);

        if ($duration < $min_age_seconds) {
            Logger::warning("Cannot force release: lock too recent", [
                'process' => $process,
                'duration_seconds' => $duration,
                'required_min_age' => $min_age_seconds,
            ]);
            return false;
        }

        $success = self::release($process);

        if ($success) {
            Logger::warning("Lock force-released (possible crash recovery)", [
                'process' => $process,
                'duration_seconds' => $duration,
            ]);
        }

        return $success;
    }

    /**
     * Clean up all acquired locks on shutdown
     *
     * Called automatically by WordPress shutdown hook to ensure locks
     * are released even if the process terminates unexpectedly.
     *
     * @internal Called via register_shutdown_function
     */
    public static function cleanup_on_shutdown(): void {
        foreach (self::$acquired_locks as $process => $lock_key) {
            self::release($process);
        }
    }

    /**
     * Register shutdown cleanup (called during plugin initialization)
     *
     * @internal
     */
    public static function register_cleanup(): void {
        register_shutdown_function([self::class, 'cleanup_on_shutdown']);
    }

    /**
     * Get all currently held locks (for debugging)
     *
     * @internal Admin-only function for debugging
     * @return array Array of [process => lock_timestamp]
     */
    public static function get_all_locks(): array {
        $locks = [];
        $processes = ['email', 'crm', 'webhook', 'analytics'];

        foreach ($processes as $process) {
            if (self::is_locked($process)) {
                $timestamp = self::get_lock_timestamp($process);
                if ($timestamp !== false) {
                    $locks[$process] = [
                        'timestamp' => $timestamp,
                        'duration_seconds' => self::get_lock_duration($process),
                    ];
                }
            }
        }

        return $locks;
    }
}
