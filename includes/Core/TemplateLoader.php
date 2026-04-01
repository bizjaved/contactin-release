<?php
/**
 * Template Loader Utility for ContactIn
 *
 * @package ContactIn\Core
 */

namespace ContactInbox\Core;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class TemplateLoader {
	/**
	 * Render a template and return its HTML as string.
	 *
	 * @param string $template_path Absolute path to template file.
	 * @param array  $vars Variables to extract for template scope.
	 * @return string Rendered HTML
	 */
	public static function render( $template_path, $vars = array() ) {
		if ( ! file_exists( $template_path ) ) {
			return '';
		}
		ob_start();
		extract( $vars, EXTR_SKIP );
		include $template_path;
		return ob_get_clean();
	}
}
