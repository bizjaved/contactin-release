<?php
namespace ContactInbox\Core;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Utility class to compose display names with optional salutations.
 */
final class NameFormatter {
    /**
     * Combine salutation and name into a single display string.
     */
    public static function display(?string $salutation, ?string $name): string {
        $parts = [];
        $sal = trim((string) ($salutation ?? ''));
        $nm = trim((string) ($name ?? ''));

        if ($sal !== '') {
            $parts[] = $sal;
        }
        if ($nm !== '') {
            $parts[] = $nm;
        }

        $combined = trim(implode(' ', $parts));
        return $combined !== '' ? $combined : $nm;
    }
}
