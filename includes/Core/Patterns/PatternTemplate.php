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

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class PatternTemplate extends AbstractBusinessPattern {

	/**
	 * @return array<string, array<string, list<string>>>
	 */
	public static function get_patterns(): array {
		$base = self::base(); // Start from GenericPatterns

		// --- sales ---
		$base['sales']['high']   = array_merge( $base['sales']['high'], array( 'your-high-sales-keyword' ) );
		$base['sales']['medium'] = array_merge( $base['sales']['medium'] ?? array(), array( 'your-medium-sales-keyword' ) );

		// --- support ---
		$base['support']['high']   = array_merge( $base['support']['high'], array() );
		$base['support']['medium'] = array_merge( $base['support']['medium'] ?? array(), array() );

		// --- feedback ---
		$base['feedback']['high']   = array_merge( $base['feedback']['high'], array() );
		$base['feedback']['medium'] = array_merge( $base['feedback']['medium'] ?? array(), array() );

		// --- complaint ---
		$base['complaint']['high']   = array_merge( $base['complaint']['high'], array() );
		$base['complaint']['medium'] = array_merge( $base['complaint']['medium'] ?? array(), array() );

		// --- question ---
		$base['question']['high']   = array_merge( $base['question']['high'], array() );
		$base['question']['medium'] = array_merge( $base['question']['medium'] ?? array(), array() );

		// --- spam ---
		$base['spam']['high'] = array_merge( $base['spam']['high'], array() );

		return $base;
	}
}
