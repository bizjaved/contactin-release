<?php
/**
 * Business Pattern Interface
 *
 * All business-type pattern classes must implement this interface.
 *
 * @package ContactIn\Core\Patterns
 */

declare(strict_types=1);

namespace ContactInbox\Core\Patterns;

if (!defined('ABSPATH')) {
    exit;
}

interface BusinessPatternInterface
{
    /**
     * Return the intent classification patterns for this business type.
     *
     * @return array<string, array<string, list<string>>>
     */
    public static function get_patterns(): array;
}