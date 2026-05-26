<?php
// phpcs:disable WordPress.WP.I18n.TextDomainMismatch, WordPress.PHP.DevelopmentFunctions.error_log_error_log, WordPress.WP.AlternativeFunctions.unlink_unlink, WordPress.WP.AlternativeFunctions.file_system_operations_fwrite, WordPress.WP.AlternativeFunctions.file_system_operations_is_writable, WordPress.WP.AlternativeFunctions.file_system_operations_fclose, WordPress.WP.AlternativeFunctions.rename_rename, WordPress.WP.AlternativeFunctions.file_system_operations_fopen, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber
/**
 * Enhanced Logger
 *
 * Structured logging with severity levels and contextual information.
 * Follows PSR-3 interface for compatibility.
 *
 * @package ContactIn\Core
 */

declare(strict_types=1);

namespace ContactInbox\Core;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Logger {

	private const LOG_DIR_NAME = 'contactin';
	private const LOG_FILE_NAME = 'contactin.log';
	private const MAX_LOG_SIZE = 10 * 1024 * 1024; // 10MB

	// PSR-3 Log Levels
	public const EMERGENCY = 'emergency';
	public const ALERT     = 'alert';
	public const CRITICAL  = 'critical';
	public const ERROR     = 'error';
	public const WARNING   = 'warning';
	public const NOTICE    = 'notice';
	public const INFO      = 'info';
	public const DEBUG     = 'debug';

	/**
	 * Log levels in order of severity
	 */
	private const LEVELS = array(
		self::EMERGENCY => 0,
		self::ALERT     => 1,
		self::CRITICAL  => 2,
		self::ERROR     => 3,
		self::WARNING   => 4,
		self::NOTICE    => 5,
		self::INFO      => 6,
		self::DEBUG     => 7,
	);

	/**
	 * Log a message with severity level
	 *
	 * @param string $level Log level (debug, info, notice, warning, error, critical, alert, emergency)
	 * @param string $message Message to log
	 * @param array  $context Additional context data
	 * @return void
	 */
	public static function log( string $level = self::INFO, string $message = '', array $context = array() ): void {
		if ( empty( $message ) ) {
			return;
		}

		// Get configured minimum log level (default: INFO)
		$min_level = apply_filters( 'contactin_log_level', self::INFO );

		// Skip if this level is below minimum
		if ( ( self::LEVELS[ $level ] ?? 7 ) > ( self::LEVELS[ $min_level ] ?? 6 ) ) {
			return;
		}

		// Build log entry
		$timestamp   = current_time( 'Y-m-d H:i:s' );
		$level_upper = strtoupper( $level );

		// Format context data
		$context_str = ! empty( $context ) ? ' ' . wp_json_encode( $context ) : '';

		// Build complete log message
		$log_message = sprintf(
			'[%s] [ContactIN] [%s] %s%s',
			$timestamp,
			$level_upper,
			$message,
			$context_str
		);

		// Write to log
		self::write_log( $log_message );
	}

	/**
	 * Log emergency message
	 *
	 * @param string $message
	 * @param array  $context
	 */
	public static function emergency( string $message, array $context = array() ): void {
		self::log( self::EMERGENCY, $message, $context );
	}

	/**
	 * Log critical message
	 *
	 * @param string $message
	 * @param array  $context
	 */
	public static function critical( string $message, array $context = array() ): void {
		self::log( self::CRITICAL, $message, $context );
	}

	/**
	 * Log error message
	 *
	 * @param string $message
	 * @param array  $context
	 */
	public static function error( string $message, array $context = array() ): void {
		self::log( self::ERROR, $message, $context );
	}

	/**
	 * Log warning message
	 *
	 * @param string $message
	 * @param array  $context
	 */
	public static function warning( string $message, array $context = array() ): void {
		self::log( self::WARNING, $message, $context );
	}

	/**
	 * Log notice message
	 *
	 * @param string $message
	 * @param array  $context
	 */
	public static function notice( string $message, array $context = array() ): void {
		self::log( self::NOTICE, $message, $context );
	}

	/**
	 * Log info message
	 *
	 * @param string $message
	 * @param array  $context
	 */
	public static function info( string $message, array $context = array() ): void {
		self::log( self::INFO, $message, $context );
	}

	/**
	 * Log debug message
	 *
	 * @param string $message
	 * @param array  $context
	 */
	public static function debug( string $message, array $context = array() ): void {
		self::log( self::DEBUG, $message, $context );
	}

	/**
	 * Write log entry to file with rotation
	 *
	 * @param string $message
	 * @return void
	 */
	private static function write_log( string $message ): void {
		// Create logs directory if needed
		$log_dir = self::get_log_dir();
		if ( ! is_dir( $log_dir ) ) {
			wp_mkdir_p( $log_dir );
		}

		$log_file = self::get_log_file();

		// Check file size and rotate if needed
		if ( file_exists( $log_file ) && filesize( $log_file ) > self::MAX_LOG_SIZE ) {
			self::rotate_log();
		}

			// Write to plugin log file under uploads; fallback to PHP error_log if file write fails.
			$written = @error_log( $message . "\n", 3, $log_file ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- Intentional: fallback to PHP error_log() on line below if file write fails.
		if ( $written === false ) {
			error_log( $message ); // send to PHP error_log
		}
	}

	/**
	 * Rotate log file when it exceeds max size
	 *
	 * @return void
	 */
	private static function rotate_log(): void {
		$max_backups = 5;
		$base_file   = self::get_log_file();

		// Shift existing backups
		for ( $i = $max_backups - 1; $i >= 1; $i-- ) {
			$old_file = "{$base_file}.{$i}";
			$new_file = "{$base_file}." . ( $i + 1 );

			if ( file_exists( $old_file ) ) {
				rename( $old_file, $new_file );
			}
		}

		// Rename current log
		if ( file_exists( $base_file ) ) {
			rename( $base_file, "{$base_file}.1" );
		}
	}

	/**
	 * Get recent log entries
	 *
	 * @param int         $lines Number of lines to retrieve
	 * @param string|null $level Filter by level (optional)
	 * @return array Log entries
	 */
	public static function get_recent( int $lines = 100, ?string $level = null ): array {
		$log_file = self::get_log_file();

		if ( ! file_exists( $log_file ) ) {
			return array();
		}

		$entries = array();
		$handle  = fopen( $log_file, 'r' );

		if ( ! $handle ) {
			return array();
		}

		// Get last N lines
		$all_lines    = file( $log_file );
		$recent_lines = array_slice( $all_lines, -$lines );

		// Filter by level if specified
		foreach ( $recent_lines as $line ) {
			if ( $level && strpos( $line, "[$level]" ) === false ) {
				continue;
			}
			$entries[] = trim( $line );
		}

		fclose( $handle );
		return $entries;
	}

	/**
	 * Clear log file
	 *
	 * @return bool Success
	 */
	public static function clear(): bool {
		$log_file = self::get_log_file();

		if ( file_exists( $log_file ) ) {
			return unlink( $log_file );
		}
		return true;
	}

	/**
	 * Get the plugin log directory under WordPress uploads.
	 *
	 * @return string
	 */
	private static function get_log_dir(): string {
		$upload_dir = wp_upload_dir();

		return trailingslashit( $upload_dir['basedir'] ) . self::LOG_DIR_NAME;
	}

	/**
	 * Get the plugin log file path under WordPress uploads.
	 *
	 * @return string
	 */
	private static function get_log_file(): string {
		return trailingslashit( self::get_log_dir() ) . self::LOG_FILE_NAME;
	}
}
