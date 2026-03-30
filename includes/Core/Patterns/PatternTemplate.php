<?php
/**
 * Pattern Template – Starter for new business types.
 *
 * To add a new business type:
 *  1. Copy this file and rename the class (e.g. NonprofitPatterns).
 *  2. Implement get_patterns() — call static::base() to start from
 *     the generic keyword set and merge your custom terms on top.
 *  3. Register the new class in BusinessPatterns::TYPE_CLASS_MAP and
 *     add a label to BusinessPatterns::get_business_types().
 *
 * @package ContactIn\Core\Patterns
 */

declare(strict_types=1);

namespace ContactInbox\Core\Patterns;

if (!defined('ABSPATH')) {
    exit;
}

final class PatternTemplate extends AbstractBusinessPattern
{
    /**
     * @return array<string, array<string, list<string>>>
     */
    public static function get_patterns(): array
    {
        $base = static::base(); // Start from GenericPatterns

        // --- sales ---
        $base['sales']['high']   = array_merge($base['sales']['high'],   ['your-high-sales-keyword']);
        $base['sales']['medium'] = array_merge($base['sales']['medium'] ?? [], ['your-medium-sales-keyword']);

        // --- support ---
        $base['support']['high']   = array_merge($base['support']['high'],   []);
        $base['support']['medium'] = array_merge($base['support']['medium'] ?? [], []);

        // --- feedback ---
        $base['feedback']['high']   = array_merge($base['feedback']['high'],   []);
        $base['feedback']['medium'] = array_merge($base['feedback']['medium'] ?? [], []);

        // --- complaint ---
        $base['complaint']['high']   = array_merge($base['complaint']['high'],   []);
        $base['complaint']['medium'] = array_merge($base['complaint']['medium'] ?? [], []);

        // --- question ---
        $base['question']['high']   = array_merge($base['question']['high'],   []);
        $base['question']['medium'] = array_merge($base['question']['medium'] ?? [], []);

        // --- spam ---
        $base['spam']['high'] = array_merge($base['spam']['high'], []);

        return $base;
    }
}
