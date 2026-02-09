<?php
/**
 * Contact Inbox – PSR-4 Autoloader
 *
 * Enterprise-Grade: Fast, reliable, strict, debug-friendly.
 *
 * Automatically loads all classes under:
 *   includes/
 *   ├─ Admin/
 *   ├─ Core/
 *   ├─ Frontend/
 *   └─ Integrations/
 *
 * @package ContactInbox
 */

namespace ContactInbox;

use ContactInbox\Core\Logger;

if ( ! class_exists( __NAMESPACE__ . '\\Autoloader' ) ) {

    /**
     * PSR-4 compliant autoloader for the ContactInbox namespace.
     */
    final class Autoloader {

        /** @var string Namespace prefix */
        private const PREFIX = 'ContactInbox\\';

        /**
         * Get the length of the namespace prefix.
         *
         * @return int
         */
        private static function prefixLen(): int {
            return strlen(self::PREFIX);
        }

        /** @var string Base directory for class files */
        private static string $base_dir;

        /**
         * Register the autoloader.
         *
         * @return void
         */
        public static function register(): void {
            // Define base directory: /wp-content/plugins/contact-inbox/includes/
            self::$base_dir = dirname(__DIR__) . DIRECTORY_SEPARATOR;
            spl_autoload_register( [ __CLASS__, 'load' ] );
        }

        /**
         * Load the class file.
         *
         * @param string $class The fully-qualified class name.
         * @return void
         */
        public static function load( string $class ): void {
	    // Only proceed if class uses our namespace
            if ( strncmp( $class, self::PREFIX, self::prefixLen() ) !== 0 ) {
                return;
            }

	    // Remove namespace prefix
            $relative_class = substr( $class, self::prefixLen() );

            // Convert namespace separators to directory separators
            $file = self::$base_dir . str_replace( '\\', DIRECTORY_SEPARATOR, $relative_class ) . '.php';

            if ( is_readable( $file ) ) {
                /** @noinspection PhpIncludeInspection */
                require $file;
                return;
            }
            // Optionally, log missing class with your own logger if needed (no error_log)
        }
    }

    // Self-register immediately
    Autoloader::register();
}