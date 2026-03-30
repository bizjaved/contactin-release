<?php
/**
 * Abstract Business Pattern
 *
 * Provides the generic base patterns and a helper to retrieve them.
 * All business-type pattern classes should extend this class.
 *
 * @package ContactIn\Core\Patterns
 */

declare(strict_types=1);

namespace ContactInbox\Core\Patterns;

if (!defined('ABSPATH')) {
    exit;
}

abstract class AbstractBusinessPattern implements BusinessPatternInterface
{
    /**
     * Returns the generic base patterns.
     * Subclasses call this to build on top of the generic set.
     *
     * @return array<string, array<string, list<string>>>
     */
    protected static function base(): array
    {
        return GenericPatterns::get_patterns();
    }
}