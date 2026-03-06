<?php
/**
 * Singleton Trait – Enterprise-Grade
 *
 * Provides a strict, reusable singleton pattern without overriding class constructors.
 *
 * @package ContactInbox\Traits
 */
namespace ContactInbox\Traits;

trait Singleton
{
    /**
     * Holds the single instance of the class using this trait.
     */
    protected static $instance = null;

    /**
     * Retrieve the singleton instance.
     */
    public static function instance(): static
    {
        if (null === static::$instance) {
            static::$instance = new static();
        }
        return static::$instance;
    }

    /**
     * Prevent cloning of the singleton.
     */
    private function __clone() {}

    /**
     * Prevent unserialization of the singleton.
     *
     * Must be public to satisfy PHP’s requirement for magic methods,
     * but throws an exception to enforce singleton.
     */
    public function __wakeup(): void
    {
        throw new \Exception('Cannot unserialize singleton');
    }
}
