<?php

namespace ContactInbox\Admin;

use ContactInbox\Core\Config;
use ContactInbox\Core\TemplateLoader;
use ContactInbox\Traits\Singleton;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
// phpcs:disable WordPress.WP.I18n.NonSingularStringLiteralDomain, WordPress.WP.I18n.MissingTranslatorsComment, WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound, WordPress.Security.EscapeOutput.OutputNotEscaped, WordPress.Security.ValidatedSanitizedInput.MissingUnslash, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized, WordPress.Security.NonceVerification.Recommended

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class PluginInfo {
	use Singleton;

	private const TEMPLATE_FILES = array(
		'description'  => 'info-description.php',
		'features'     => 'info-features.php',
		'integrations' => 'info-integrations.php',
		'installation' => 'info-installation.php',
		'faq'          => 'info-faq.php',
		'changelog'    => 'info-changelog.php',
	);

	private string $plugin_slug      = 'contactin';
	private string $free_plugin_slug = 'contactin';

	protected function __construct() {
		$this->init();
	}

	protected function init(): void {
		add_filter( 'wp_kses_allowed_html', array( $this, 'extend_allowed_html_tags' ), 10, 2 );
		add_action( 'admin_head', array( $this, 'print_modal_comparison_styles' ) );
	}

	public function print_modal_comparison_styles(): void {
		if ( ! $this->is_plugin_info_modal_request() ) {
			return;
		}

		echo '<style id="contactin-plugin-info-comparison-styles">';
		echo '.contactin-table-wrap{margin:12px 0 16px;}';
		echo '.contactin-table-wrap > p{margin:0 0 8px;}';
		echo '.contactin-plugin-info-table{list-style:none;margin:0;padding:0;border:1px solid ' . Config::COLOR_BORDER_SUBTLE . ';border-radius:4px;overflow:hidden;}';
		echo '.contactin-plugin-info-table li{margin:0;padding:10px 12px;border-top:1px solid #f0f0f1;line-height:1.45;}';
		echo '.contactin-plugin-info-table li:first-child{border-top:0;}';
		echo '.contactin-plugin-info-table li:nth-child(2n){background:' . Config::COLOR_BG_ALT . ';}';
		echo '.contactin-pro-matrix > p{margin:14px 0 8px;}';
		echo '.contactin-pro-matrix > p:first-child{margin-top:0;}';
		echo '.contactin-plugin-screenshots{margin:8px 0 0;}';
		echo '.contactin-plugin-screenshots ol{margin:0;padding-left:20px;}';
		echo '.contactin-plugin-screenshots li{margin:0 0 16px;}';
		echo '.contactin-plugin-screenshot{display:block;max-width:100%;height:auto;border:1px solid ' . Config::COLOR_BORDER_SUBTLE . ';border-radius:4px;background:' . Config::COLOR_BG_SURFACE . ';}';
		echo '</style>';
	}

	public function extend_allowed_html_tags( array $tags, string $context ): array {
		if ( ! $this->is_plugin_info_modal_request() ) {
			return $tags;
		}

		$tags['table']      = array( 'class' => true );
		$tags['thead']      = array( 'class' => true );
		$tags['tbody']      = array( 'class' => true );
		$tags['tr']         = array( 'class' => true );
		$tags['th']         = array(
			'class'   => true,
			'colspan' => true,
			'rowspan' => true,
			'scope'   => true,
		);
		$tags['td']         = array(
			'class'   => true,
			'colspan' => true,
			'rowspan' => true,
		);
		$tags['blockquote'] = array( 'class' => true );
		$tags['pre']        = array( 'class' => true );
		$tags['code']       = array( 'class' => true );
		$tags['div']        = array( 'class' => true );
		$tags['span']       = array( 'class' => true );
		$tags['hr']         = array();

		return $tags;
	}

	public function plugin_info( $result, $action, $args ) {
		if ( $action !== 'plugin_information' ) {
			return $result;
		}

		if ( ! $this->matches_plugin_request( $args ) ) {
			return $result;
		}

		if ( is_object( $result ) ) {
			$result = $this->merge_visual_assets_if_missing( $result );

			if ( ! $this->is_fallback_upgrade_modal( $result ) ) {
				return $result;
			}

			return $this->merge_fallback_result( $result );
		}

		return $this->get_plugin_data();
	}

	private function merge_visual_assets_if_missing( object $result ): object {
		$local = $this->get_plugin_data();

		if ( isset( $result->banners ) && is_object( $result->banners ) ) {
			$result->banners = (array) $result->banners;
		}

		if ( ! $this->has_valid_banners( $result ) && ! empty( $local->banners ) ) {
			$result->banners = $local->banners;
		}

		if ( empty( $result->icons ) && ! empty( $local->icons ) ) {
			$result->icons = $local->icons;
		}

		if ( empty( $result->screenshots ) && ! empty( $local->screenshots ) ) {
			$result->screenshots = $local->screenshots;
		}

		return $result;
	}

	private function has_valid_banners( object $result ): bool {
		if ( ! isset( $result->banners ) ) {
			return false;
		}

		$banners = $result->banners;
		if ( is_object( $banners ) ) {
			$banners = (array) $banners;
		}

		if ( ! is_array( $banners ) ) {
			return false;
		}

		$low  = isset( $banners['low'] ) ? trim( (string) $banners['low'] ) : '';
		$high = isset( $banners['high'] ) ? trim( (string) $banners['high'] ) : '';

		return $low !== '' || $high !== '';
	}

	private function is_fallback_upgrade_modal( object $result ): bool {
		if ( ! isset( $result->sections ) || ! is_array( $result->sections ) ) {
			return false;
		}

		if ( empty( $result->sections['description'] ) ) {
			return false;
		}

		$description = wp_strip_all_tags( (string) $result->sections['description'] );
		$plugin_name = isset( $result->name ) ? (string) $result->name : 'ContactIn';

		$normalized_description = strtolower( trim( $description ) );
		$expected               = strtolower( 'Upgrade ' . $plugin_name . ' to latest.' );
		$suffix                 = ' to latest.';
		$suffix_len             = strlen( $suffix );

		$has_upgrade_prefix = strpos( $normalized_description, 'upgrade ' ) === 0;
		$has_latest_suffix  = strlen( $normalized_description ) >= $suffix_len
			&& substr( $normalized_description, -$suffix_len ) === $suffix;

		return $normalized_description === $expected || ( $has_upgrade_prefix && $has_latest_suffix );
	}

	private function merge_fallback_result( object $result ): object {
		$local = $this->get_plugin_data();

		if ( ! isset( $result->sections ) || ! is_array( $result->sections ) ) {
			$result->sections = array();
		}

		foreach ( array( 'description', 'installation', 'faq', 'screenshots', 'documentation', 'changelog' ) as $key ) {
			if ( ! empty( $local->sections[ $key ] ) ) {
				$result->sections[ $key ] = $local->sections[ $key ];
			}
		}

		if ( empty( $result->banners ) && ! empty( $local->banners ) ) {
			$result->banners = $local->banners;
		}

		if ( empty( $result->icons ) && ! empty( $local->icons ) ) {
			$result->icons = $local->icons;
		}

		if ( empty( $result->screenshots ) && ! empty( $local->screenshots ) ) {
			$result->screenshots = $local->screenshots;
		}

		if ( empty( $result->name ) && ! empty( $local->name ) ) {
			$result->name = $local->name;
		}

		if ( empty( $result->slug ) && ! empty( $local->slug ) ) {
			$result->slug = $local->slug;
		}

		return $result;
	}

	public function get_full_readme_html(): string {
		$sections = $this->parse_readme_sections();
		if ( empty( $sections ) ) {
			return '';
		}

		$html_parts = array();
		foreach ( $sections as $section ) {
			if ( $section['title'] === '' || $section['content'] === '' ) {
				continue;
			}
			$html_parts[] = '<h2>' . esc_html( $section['title'] ) . '</h2>';
			$html_parts[] = $section['content'];
		}

		return implode( "\n", $html_parts );
	}

	public function get_readme_sections(): array {
		return $this->parse_readme_sections();
	}

	public function get_readme_section_html( string $key ): string {
		$needle = strtolower( trim( $key ) );
		if ( $needle === '' ) {
			return '';
		}

		foreach ( $this->parse_readme_sections() as $section ) {
			if ( $section['key'] === $needle ) {
				return $section['content'];
			}
		}

		return '';
	}

	private function is_plugin_info_modal_request(): bool {
		if ( ! is_admin() ) {
			return false;
		}

		$tab    = isset( $_REQUEST['tab'] ) ? sanitize_key( (string) $_REQUEST['tab'] ) : '';
		$action = isset( $_REQUEST['action'] ) ? sanitize_key( (string) $_REQUEST['action'] ) : '';

		if ( $tab !== 'plugin-information' && $action !== 'plugin_information' ) {
			return false;
		}

		$plugin = isset( $_REQUEST['plugin'] ) ? sanitize_text_field( (string) $_REQUEST['plugin'] ) : '';
		$slug   = isset( $_REQUEST['slug'] ) ? sanitize_text_field( (string) $_REQUEST['slug'] ) : '';

		$accepted = array(
			strtolower( $this->plugin_slug ),
			strtolower( $this->free_plugin_slug ),
			strtolower( dirname( CONTACTINBOX_BASENAME ) ),
			strtolower( CONTACTINBOX_BASENAME ),
			'contactin.php',
			'contactin-pro/contactin.php',
			'contactin/contactin.php',
		);

		foreach ( array( $plugin, $slug ) as $candidate ) {
			if ( $candidate === '' ) {
				continue;
			}

			$candidate = strtolower( $candidate );
			if ( in_array( $candidate, $accepted, true ) ) {
				return true;
			}

			if ( substr( $candidate, -strlen( '/contactin.php' ) ) === '/contactin.php' ) {
				return true;
			}
		}

		return false;
	}

	private function matches_plugin_request( $args ): bool {
		$candidates = array();

		if ( is_object( $args ) ) {
			if ( ! empty( $args->slug ) ) {
				$candidates[] = strtolower( (string) $args->slug );
			}
			if ( ! empty( $args->plugin ) ) {
				$candidates[] = strtolower( (string) $args->plugin );
			}
		} elseif ( is_array( $args ) ) {
			if ( ! empty( $args['slug'] ) ) {
				$candidates[] = strtolower( (string) $args['slug'] );
			}
			if ( ! empty( $args['plugin'] ) ) {
				$candidates[] = strtolower( (string) $args['plugin'] );
			}
		}

		if ( empty( $candidates ) && isset( $_REQUEST['plugin'] ) ) {
			$candidates[] = strtolower( sanitize_text_field( (string) $_REQUEST['plugin'] ) );
		}

		if ( empty( $candidates ) && isset( $_REQUEST['slug'] ) ) {
			$candidates[] = strtolower( sanitize_text_field( (string) $_REQUEST['slug'] ) );
		}

		$accepted = array(
			strtolower( $this->plugin_slug ),
			strtolower( $this->free_plugin_slug ),
			strtolower( dirname( CONTACTINBOX_BASENAME ) ),
			strtolower( CONTACTINBOX_BASENAME ),
			'contactin.php',
			'contactin-pro/contactin.php',
			'contactin/contactin.php',
		);

		foreach ( $candidates as $candidate ) {
			if ( in_array( $candidate, $accepted, true ) ) {
				return true;
			}

			if ( substr( $candidate, -strlen( '/contactin.php' ) ) === '/contactin.php' ) {
				return true;
			}
		}

		return false;
	}

	private function get_plugin_data(): object {
		$plugin_version = defined( 'CONTACTINBOX_VERSION' ) ? (string) CONTACTINBOX_VERSION : Config::VERSION;
		$sections       = $this->get_sections_from_readme();

		$data                 = new \stdClass();
		$data->name           = 'ContactIn';
		$data->slug           = $this->plugin_slug;
		$data->plugin         = 'contactin/contactin.php';
		$data->version        = $plugin_version;
		$data->author         = 'Javed Ahsan';
		$data->author_profile = 'https://linkedin.com/in/bizjaved';
		$data->homepage       = 'https://github.com/bizjaved/contactin';
		$data->download_link  = '';
		$data->donate_link    = '';
		$data->requires       = '6.4';
		$data->tested         = '6.9.1';
		$data->requires_php   = '7.4';
		$data->last_updated   = gmdate( 'Y-m-d' );
		$banner_low           = $this->get_asset_url_with_placeholder( 'assets/banner-772x250.jpg', 'assets/placeholders/banner-placeholder.svg' );
		$banner_high          = $this->get_asset_url_with_placeholder( 'assets/banner-1544x500.jpg', 'assets/placeholders/banner-placeholder.svg' );
		$icon_1x              = $this->get_asset_url_with_placeholder( 'assets/logo-128.png', 'assets/placeholders/logo-placeholder.svg' );
		$icon_2x              = $this->get_asset_url_with_placeholder( 'assets/logo-256.png', 'assets/placeholders/logo-placeholder.svg' );

		$data->banners = array(
			'low'  => $banner_low,
			'high' => $banner_high,
		);
		$data->icons   = array(
			'1x' => $icon_1x,
			'2x' => $icon_2x,
		);
		$screenshots   = array(
			array(
				'src'     => $this->get_asset_url_with_placeholder( 'assets/screenshot-1.png', 'assets/placeholders/screenshot-placeholder.svg' ),
				'caption' => 'Inbox',
			),
			array(
				'src'     => $this->get_asset_url_with_placeholder( 'assets/screenshot-2.png', 'assets/placeholders/screenshot-placeholder.svg' ),
				'caption' => 'Contacts',
			),
			array(
				'src'     => $this->get_asset_url_with_placeholder( 'assets/screenshot-3.png', 'assets/placeholders/screenshot-placeholder.svg' ),
				'caption' => 'Dashboard (submission tab)',
			),
			array(
				'src'     => $this->get_asset_url_with_placeholder( 'assets/screenshot-4.png', 'assets/placeholders/screenshot-placeholder.svg' ),
				'caption' => 'Dashboard (system performance tab)',
			),
			array(
				'src'     => $this->get_asset_url_with_placeholder( 'assets/screenshot-5.png', 'assets/placeholders/screenshot-placeholder.svg' ),
				'caption' => 'Maintenance & Operations',
			),
			array(
				'src'     => $this->get_asset_url_with_placeholder( 'assets/screenshot-6.png', 'assets/placeholders/screenshot-placeholder.svg' ),
				'caption' => 'Salesforce Integration',
			),
		);

		$data->screenshots = $screenshots;

		$description_section = $sections['description'] !== '' ? $sections['description'] : $this->get_description();

		$screenshots_section = $sections['screenshots'] ?? '';
		if ( ! $this->contains_image_markup( $screenshots_section ) ) {
			$gallery_html        = $this->get_screenshots_section_html( $screenshots );
			$screenshots_section = $screenshots_section !== ''
				? $screenshots_section . "\n" . $gallery_html
				: $gallery_html;
		}

		$data->sections = array(
			'description'   => $description_section,
			'installation'  => $sections['installation'] !== '' ? $sections['installation'] : $this->get_installation(),
			'faq'           => $sections['faq'] !== '' ? $sections['faq'] : $this->get_faq(),
			'screenshots'   => $screenshots_section,
			'documentation' => $sections['documentation'] !== ''
				? $sections['documentation']
				: '<p>Full documentation: <a href="https://contactinbox.app/contactin-pro-documentation/" target="_blank" rel="noopener noreferrer">contactinbox.app/contactin-pro-documentation</a></p>',
			'changelog'     => $sections['changelog'] !== '' ? $sections['changelog'] : $this->get_changelog(),
		);

		return $data;
	}

	private function get_sections_from_readme(): array {
		$map = array(
			'description'   => '',
			'installation'  => '',
			'faq'           => '',
			'screenshots'   => '',
			'documentation' => '',
			'changelog'     => '',
		);

		foreach ( $this->parse_readme_sections() as $section ) {
			$map[ $section['key'] ] = $section['content'];
		}

		return array(
			'description'   => $map['description'] ?? '',
			'installation'  => $map['installation'] ?? '',
			'faq'           => $map['frequently asked questions'] ?? ( $map['faq'] ?? '' ),
			'screenshots'   => $map['screenshots'] ?? '',
			'documentation' => $map['documentation']
				?? ( $map['documentation & support']
				?? ( $map['support']
				?? ( $map['other notes']
				?? ( $map['other_notes'] ?? '' ) ) ) ),
			'changelog'     => $map['changelog'] ?? '',
		);
	}

	private function get_screenshots_section_html( array $screenshots ): string {
		if ( empty( $screenshots ) ) {
			return '<p>Screenshots are not available at the moment.</p>';
		}

		$html = '<div class="contactin-plugin-screenshots"><ol>';

		foreach ( $screenshots as $index => $item ) {
			$src     = isset( $item['src'] ) ? (string) $item['src'] : '';
			$caption = isset( $item['caption'] ) ? (string) $item['caption'] : ( 'Screenshot ' . ( (int) $index + 1 ) );

			if ( $src === '' ) {
				continue;
			}

			$html .= '<li>';
			$html .= '<p><strong>' . esc_html( $caption ) . '</strong></p>';
			$html .= '<p><img src="' . esc_url( $src ) . '" class="contactin-plugin-screenshot" alt="' . esc_attr( $caption ) . '" /></p>';
			$html .= '</li>';
		}

		$html .= '</ol></div>';

		return $html;
	}

	private function contains_image_markup( string $html ): bool {
		if ( $html === '' ) {
			return false;
		}

		return preg_match( '/<img\b/i', $html ) === 1;
	}

	private function parse_readme_sections(): array {
		$readme_path = CONTACTINBOX_PATH . 'readme.txt';
		if ( ! file_exists( $readme_path ) ) {
			return array();
		}

		$raw = file_get_contents( $readme_path );
		if ( ! is_string( $raw ) || trim( $raw ) === '' ) {
			return array();
		}

		$raw = str_replace( array( "\r\n", "\r" ), "\n", $raw );

		$matches = array();
		preg_match_all( '/^==\s*(.+?)\s*==\s*$/m', $raw, $matches, PREG_OFFSET_CAPTURE );
		if ( empty( $matches[1] ) ) {
			return array();
		}

		$sections = array();
		$count    = count( $matches[1] );

		for ( $index = 0; $index < $count; $index++ ) {
			$title = trim( (string) $matches[1][ $index ][0] );
			if ( $title === '' ) {
				continue;
			}

			$title_offset  = (int) $matches[1][ $index ][1];
			$line_start    = strrpos( substr( $raw, 0, $title_offset ), "\n" );
			$heading_start = $line_start === false ? 0 : $line_start + 1;
			$heading_end   = strpos( $raw, "\n", $heading_start );
			if ( $heading_end === false ) {
				$heading_end = strlen( $raw );
			}

			$next_title_offset       = isset( $matches[1][ $index + 1 ] ) ? (int) $matches[1][ $index + 1 ][1] : strlen( $raw );
			$next_heading_line_start = strrpos( substr( $raw, 0, $next_title_offset ), "\n" );
			$content_end             = $next_heading_line_start === false ? strlen( $raw ) : $next_heading_line_start;

			$content = trim( substr( $raw, $heading_end + 1, max( 0, $content_end - ( $heading_end + 1 ) ) ) );
			if ( $content === '' ) {
				continue;
			}

			$sections[] = array(
				'key'     => strtolower( $title ),
				'title'   => $title,
				'content' => $this->format_readme_content( $content ),
			);
		}

		return $sections;
	}

	private function format_readme_content( string $content ): string {
		$content = str_replace( array( "\r\n", "\r" ), "\n", trim( $content ) );
		$content = $this->strip_non_pro_comparison_sections( $content );
		if ( $content === '' ) {
			return '';
		}

		$lines         = explode( "\n", $content );
		$output        = array();
		$paragraph     = array();
		$list_type     = null;
		$table_rows    = array();
		$table_seen    = false;
		$in_code_block = false;
		$code_lines    = array();

		$flush_paragraph = function () use ( &$paragraph, &$output ): void {
			if ( empty( $paragraph ) ) {
				return;
			}

			$text = trim( implode( ' ', array_map( 'trim', $paragraph ) ) );
			if ( $text !== '' ) {
				$output[] = '<p>' . $this->format_readme_inline( $text ) . '</p>';
			}

			$paragraph = array();
		};

		$close_list = function () use ( &$list_type, &$output ): void {
			if ( $list_type === null ) {
				return;
			}

			$output[]  = '</' . $list_type . '>';
			$list_type = null;
		};

		$flush_table = function () use ( &$table_rows, &$output, &$table_seen ): void {
			if ( count( $table_rows ) < 2 ) {
				$table_rows = array();
				return;
			}

			$output[]   = $this->render_readme_table( $table_rows );
			$table_rows = array();
			$table_seen = true;
		};

		$flush_code = function () use ( &$in_code_block, &$code_lines, &$output ): void {
			if ( ! $in_code_block && empty( $code_lines ) ) {
				return;
			}

			$code = trim( implode( "\n", $code_lines ) );
			if ( $code !== '' ) {
				$output[] = '<pre><code>' . esc_html( $code ) . '</code></pre>';
			}

			$in_code_block = false;
			$code_lines    = array();
		};

		foreach ( $lines as $line ) {
			$line    = $this->normalize_readme_table_line( (string) $line );
			$trimmed = trim( $line );

			if ( preg_match( '/^```/', $trimmed ) === 1 ) {
				if ( $in_code_block ) {
					$flush_code();
				} else {
					$flush_paragraph();
					$close_list();
					$flush_table();
					$in_code_block = true;
					$code_lines    = array();
				}
				continue;
			}

			if ( $in_code_block ) {
				$code_lines[] = rtrim( $line, "\r" );
				continue;
			}

			if ( $trimmed === '' ) {
				$flush_paragraph();
				$close_list();
				$flush_table();
				continue;
			}

			if ( $this->is_readme_table_row_line( $trimmed ) ) {
				$flush_paragraph();
				$close_list();
				$table_rows[] = $this->parse_readme_table_row( $trimmed );
				continue;
			}

			$flush_table();

			if ( preg_match( '/^>{1}\s?(.*)$/', $trimmed, $matches ) === 1 ) {
				$flush_paragraph();
				$close_list();
				$quoted = trim( (string) ( $matches[1] ?? '' ) );
				if ( $quoted !== '' ) {
					$output[] = '<blockquote><p>' . $this->format_readme_inline( $quoted ) . '</p></blockquote>';
				}
				continue;
			}

			if ( preg_match( '/^(?:---|\*\*\*)$/', $trimmed ) === 1 ) {
				$flush_paragraph();
				$close_list();
				$output[] = '<hr />';
				continue;
			}

			if ( preg_match( '/^#{3}\s+(.+)$/', $trimmed, $matches ) === 1 ) {
				$flush_paragraph();
				$close_list();
				$output[] = '<h4>' . esc_html( trim( (string) $matches[1] ) ) . '</h4>';
				continue;
			}

			if ( preg_match( '/^#{2}\s+(.+)$/', $trimmed, $matches ) === 1 ) {
				$flush_paragraph();
				$close_list();
				$output[] = '<h3>' . esc_html( trim( (string) $matches[1] ) ) . '</h3>';
				continue;
			}

			if ( preg_match( '/^#\s+(.+)$/', $trimmed, $matches ) === 1 ) {
				$flush_paragraph();
				$close_list();
				$output[] = '<h2>' . esc_html( trim( (string) $matches[1] ) ) . '</h2>';
				continue;
			}

			if ( preg_match( '/^=\s*(.+?)\s*=$/', $trimmed, $matches ) === 1 ) {
				$flush_paragraph();
				$close_list();
				$output[] = '<h4>' . esc_html( trim( (string) $matches[1] ) ) . '</h4>';
				continue;
			}

			if ( preg_match( '/^(\*|-|•)\s+(.+)$/u', $trimmed, $matches ) === 1 ) {
				$flush_paragraph();
				if ( $list_type !== 'ul' ) {
					$close_list();
					$output[]  = '<ul>';
					$list_type = 'ul';
				}

				$output[] = '<li>' . $this->format_readme_inline( (string) $matches[2] ) . '</li>';
				continue;
			}

			if ( preg_match( '/^\d+\.\s+(.+)$/', $trimmed, $matches ) === 1 ) {
				$flush_paragraph();
				if ( $list_type !== 'ol' ) {
					$close_list();
					$output[]  = '<ol>';
					$list_type = 'ol';
				}

				$output[] = '<li>' . $this->format_readme_inline( (string) $matches[1] ) . '</li>';
				continue;
			}

			$close_list();
			$paragraph[] = $trimmed;
		}

		$flush_paragraph();
		$close_list();
		$flush_table();
		$flush_code();

		if ( ! $table_seen ) {
			foreach ( $this->extract_readme_tables_from_lines( $lines ) as $rows ) {
				$output[] = $this->render_readme_table( $rows );
			}
		}

		$html = implode( "\n", $output );

		return wp_kses( $html, $this->get_allowed_readme_tags() );
	}

	private function strip_non_pro_comparison_sections( string $content ): string {
		if ( $content === '' ) {
			return '';
		}

		$content = preg_replace(
			'/^\s*=\s*Free\s+vs\.?\s+Pro\s*=\s*$.*?(?=^\s*=\s*.+?\s*=\s*$|\z)/ims',
			'',
			$content
		);

		if ( ! is_string( $content ) ) {
			return '';
		}

		return trim( preg_replace( "/\n{3,}/", "\n\n", $content ) ?? $content );
	}

	private function get_allowed_readme_tags(): array {
		return array(
			'p'          => array(),
			'h2'         => array(),
			'h3'         => array(),
			'h4'         => array(),
			'ul'         => array(),
			'ol'         => array(),
			'li'         => array(),
			'blockquote' => array( 'class' => true ),
			'hr'         => array(),
			'pre'        => array( 'class' => true ),
			'code'       => array( 'class' => true ),
			'div'        => array( 'class' => true ),
			'span'       => array( 'class' => true ),
			'table'      => array( 'class' => true ),
			'thead'      => array( 'class' => true ),
			'tbody'      => array( 'class' => true ),
			'tr'         => array( 'class' => true ),
			'th'         => array(
				'class'   => true,
				'colspan' => true,
				'rowspan' => true,
				'scope'   => true,
			),
			'td'         => array(
				'class'   => true,
				'colspan' => true,
				'rowspan' => true,
			),
			'strong'     => array(),
			'em'         => array(),
			'a'          => array(
				'href'   => true,
				'target' => true,
				'rel'    => true,
			),
		);
	}

	private function format_readme_inline( string $text ): string {
		$safe = esc_html( trim( $text ) );

		$safe = preg_replace_callback(
			'/`([^`]+)`/',
			static function ( array $matches ): string {
				return '<code>' . esc_html( html_entity_decode( (string) $matches[1], ENT_QUOTES, 'UTF-8' ) ) . '</code>';
			},
			$safe
		);

		$safe = preg_replace_callback(
			'/\[(.+?)\]\((https?:\/\/[^\s\)]+)\)/',
			static function ( array $matches ): string {
				$label = esc_html( html_entity_decode( (string) $matches[1], ENT_QUOTES, 'UTF-8' ) );
				$url   = esc_url( html_entity_decode( (string) $matches[2], ENT_QUOTES, 'UTF-8' ) );
				if ( $url === '' ) {
					return $label;
				}

				return '<a href="' . $url . '" target="_blank" rel="noopener noreferrer">' . $label . '</a>';
			},
			$safe
		);

		$safe = preg_replace( '/\*\*(.+?)\*\*/', '<strong>$1</strong>', $safe );
		$safe = preg_replace( '/\*(.+?)\*/', '<em>$1</em>', $safe );

		return $safe;
	}

	private function normalize_readme_table_line( string $line ): string {
		$normalized = str_replace( '｜', '|', $line );
		$normalized = preg_replace( '/[\x{200B}-\x{200D}\x{FEFF}]/u', '', $normalized );

		return is_string( $normalized ) ? $normalized : $line;
	}

	private function is_readme_table_row_line( string $line ): bool {
		$line = trim( $this->normalize_readme_table_line( $line ) );
		if ( $line === '' || strpos( $line, '|' ) === false ) {
			return false;
		}

		$cells = $this->parse_readme_table_row( $line );
		if ( count( $cells ) < 2 ) {
			return false;
		}

		$non_empty_cells = array_filter(
			$cells,
			static function ( string $cell ): bool {
				return trim( $cell ) !== '';
			}
		);

		return count( $non_empty_cells ) >= 2;
	}

	private function parse_readme_table_row( string $line ): array {
		$line = trim( $this->normalize_readme_table_line( $line ) );
		$line = trim( $line, '|' );

		$parts = explode( '|', $line );

		return array_map(
			static function ( string $cell ): string {
				return trim( $cell );
			},
			$parts
		);
	}

	private function is_readme_table_separator_row( array $cells ): bool {
		if ( empty( $cells ) ) {
			return false;
		}

		foreach ( $cells as $cell ) {
			$trimmed = str_replace( ' ', '', trim( (string) $cell ) );
			if ( $trimmed === '' || preg_match( '/^:?-{3,}:?$/', $trimmed ) !== 1 ) {
				return false;
			}
		}

		return true;
	}

	private function extract_readme_tables_from_lines( array $lines ): array {
		$tables = array();
		$rows   = array();

		$flush_rows = static function ( array &$current_rows, array &$all_tables ): void {
			if ( count( $current_rows ) >= 2 ) {
				$all_tables[] = $current_rows;
			}
			$current_rows = array();
		};

		foreach ( $lines as $line ) {
			$trimmed = trim( $this->normalize_readme_table_line( (string) $line ) );
			if ( $this->is_readme_table_row_line( $trimmed ) ) {
				$rows[] = $this->parse_readme_table_row( $trimmed );
				continue;
			}

			$flush_rows( $rows, $tables );
		}

		$flush_rows( $rows, $tables );

		return $tables;
	}

	private function render_readme_table( array $rows ): string {
		if ( empty( $rows ) ) {
			return '';
		}

		$header    = array();
		$body_rows = $rows;

		if ( count( $rows ) >= 2 && $this->is_readme_table_separator_row( $rows[1] ) ) {
			$header    = $rows[0];
			$body_rows = array_slice( $rows, 2 );
		}

		if ( empty( $header ) && ! empty( $body_rows ) ) {
			$header    = $body_rows[0];
			$body_rows = array_slice( $body_rows, 1 );
		}

		if ( $this->is_free_pro_comparison_header( $header ) ) {
			return $this->render_pro_feature_matrix( $body_rows, $header );
		}

		$html = '<div class="contactin-table-wrap">';

		if ( ! empty( $header ) ) {
			$header_labels = array_map(
				function ( $cell ): string {
					return wp_strip_all_tags( (string) $cell );
				},
				$header
			);
			$html         .= '<p><strong>' . esc_html( implode( ' | ', $header_labels ) ) . '</strong></p>';
		}

		$html .= '<ul class="contactin-plugin-info-table">';
		foreach ( $body_rows as $row ) {
			if ( $this->is_readme_table_separator_row( $row ) ) {
				continue;
			}

			$feature = isset( $row[0] ) ? $this->format_readme_inline( (string) $row[0] ) : '';
			$items   = array();

			$row_count = count( $row );
			for ( $index = 1; $index < $row_count; $index++ ) {
				$value = trim( (string) $row[ $index ] );
				if ( $value === '' ) {
					continue;
				}

				$label   = isset( $header[ $index ] ) ? wp_strip_all_tags( (string) $header[ $index ] ) : 'Value ' . $index;
				$items[] = '<strong>' . esc_html( $label ) . ':</strong> ' . $this->format_readme_inline( $value );
			}

			$line = $feature;
			if ( ! empty( $items ) ) {
				$line .= ' — ' . implode( ' • ', $items );
			}

			$html .= '<li>' . $line . '</li>';
		}
		$html .= '</ul>';
		$html .= '</div>';

		return $html;
	}

	private function is_free_pro_comparison_header( array $header ): bool {
		if ( count( $header ) < 3 ) {
			return false;
		}

		$first  = strtolower( trim( (string) $header[0] ) );
		$second = strtolower( trim( (string) $header[1] ) );
		$third  = strtolower( trim( (string) $header[2] ) );

		return $first === 'feature' && $second === 'free' && $third === 'pro';
	}

	private function render_pro_feature_matrix( array $rows, array $header ): string {
		$shared   = array();
		$pro_only = array();
		$enhanced = array();

		foreach ( $rows as $row ) {
			if ( $this->is_readme_table_separator_row( $row ) ) {
				continue;
			}

			$feature = trim( (string) ( $row[0] ?? '' ) );
			if ( $feature === '' ) {
				continue;
			}

			$free_value = trim( (string) ( $row[1] ?? '' ) );
			$pro_value  = trim( (string) ( $row[2] ?? '' ) );

			if ( $pro_value === '' && $free_value === '' ) {
				continue;
			}

			if ( $pro_value !== '' && $free_value === '' ) {
				$pro_only[] = $feature;
				continue;
			}

			if ( $pro_value !== '' && $free_value !== '' && strtolower( $pro_value ) !== strtolower( $free_value ) ) {
				$enhanced[] = array(
					'feature'    => $feature,
					'free'       => $free_value,
					'pro'        => $pro_value,
					'free_label' => trim( (string) ( $header[1] ?? 'Free' ) ),
					'pro_label'  => trim( (string) ( $header[2] ?? 'Pro' ) ),
				);
				continue;
			}

			if ( $pro_value !== '' ) {
				$shared[] = $feature;
			}
		}

		$html = '<div class="contactin-table-wrap contactin-pro-matrix">';

		if ( ! empty( $pro_only ) ) {
			$html .= '<p><strong>Pro-only capabilities</strong></p><ul class="contactin-plugin-info-table">';
			foreach ( $pro_only as $item ) {
				$html .= '<li>' . $this->format_readme_inline( $item ) . '</li>';
			}
			$html .= '</ul>';
		}

		if ( ! empty( $enhanced ) ) {
			$html .= '<p><strong>Expanded in Pro</strong></p><ul class="contactin-plugin-info-table">';
			foreach ( $enhanced as $item ) {
				$html .= '<li>' . $this->format_readme_inline( $item['feature'] )
					. ' — <strong>' . esc_html( $item['free_label'] ) . ':</strong> ' . $this->format_readme_inline( $item['free'] )
					. ' • <strong>' . esc_html( $item['pro_label'] ) . ':</strong> ' . $this->format_readme_inline( $item['pro'] )
					. '</li>';
			}
			$html .= '</ul>';
		}

		if ( ! empty( $shared ) ) {
			$html .= '<p><strong>Also included in Pro</strong></p><ul class="contactin-plugin-info-table">';
			foreach ( $shared as $item ) {
				$html .= '<li>' . $this->format_readme_inline( $item ) . '</li>';
			}
			$html .= '</ul>';
		}

		$html .= '</div>';

		return $html;
	}

	private function get_asset_url_with_placeholder( string $asset_relative_path, string $placeholder_relative_path ): string {
		$asset_relative_path       = ltrim( $asset_relative_path, '/' );
		$placeholder_relative_path = ltrim( $placeholder_relative_path, '/' );

		$asset_url = $this->resolve_asset_url_by_extensions( $asset_relative_path );
		if ( $asset_url !== '' ) {
			return $asset_url;
		}

		$placeholder_url = $this->resolve_asset_url_by_extensions( $placeholder_relative_path );
		if ( $placeholder_url !== '' ) {
			return $placeholder_url;
		}

		return '';
	}

	private function resolve_asset_url_by_extensions( string $relative_path ): string {
		$normalized = ltrim( $relative_path, '/' );
		if ( $normalized === '' ) {
			return '';
		}

		$pathinfo  = pathinfo( $normalized );
		$dirname   = isset( $pathinfo['dirname'] ) && $pathinfo['dirname'] !== '.' ? $pathinfo['dirname'] . '/' : '';
		$filename  = isset( $pathinfo['filename'] ) ? (string) $pathinfo['filename'] : '';
		$extension = isset( $pathinfo['extension'] ) ? strtolower( (string) $pathinfo['extension'] ) : '';

		if ( $filename === '' ) {
			return '';
		}

		$extensions = array();
		if ( $extension !== '' ) {
			$extensions[] = $extension;
		}

		foreach ( array( 'jpg', 'jpeg', 'png', 'svg' ) as $candidate_ext ) {
			if ( ! in_array( $candidate_ext, $extensions, true ) ) {
				$extensions[] = $candidate_ext;
			}
		}

		foreach ( $extensions as $ext ) {
			$candidate_relative = $dirname . $filename . '.' . $ext;
			$candidate_file     = CONTACTINBOX_PATH . $candidate_relative;

			if ( file_exists( $candidate_file ) ) {
				return CONTACTINBOX_URL . $candidate_relative;
			}
		}

		return '';
	}

	private function get_plugin_info_template_vars(): array {
		$plugin_version = defined( 'CONTACTINBOX_VERSION' ) ? (string) CONTACTINBOX_VERSION : Config::VERSION;

		return array(
			'plugin_name'   => 'ContactIn',
			'requires'      => '6.4',
			'tested'        => '6.9.1',
			'requires_php'  => '7.4',
			'version'       => $plugin_version,
			'release_date'  => 'February 13, 2026',
			'github_url'    => 'https://github.com/bizjaved/contactin',
			'github_label'  => 'github.com/bizjaved/contactin',
			'changelog_url' => 'https://github.com/bizjaved/contactin/blob/main/CHANGELOG.md',
		);
	}

	private function get_template_path( string $template_key ): string {
		if ( ! isset( self::TEMPLATE_FILES[ $template_key ] ) ) {
			return '';
		}

		return CONTACTINBOX_PATH . Config::TEMPLATE_ADMIN . 'plugin/' . self::TEMPLATE_FILES[ $template_key ];
	}

	private function render_plugin_info_template( string $template_name ): string {
		$template_path = $this->get_template_path( $template_name );
		if ( $template_path === '' || ! file_exists( $template_path ) ) {
			return '';
		}

		return TemplateLoader::render( $template_path, $this->get_plugin_info_template_vars() );
	}

	private function get_description(): string {
		return $this->render_plugin_info_template( 'description' );
	}

	private function get_features(): string {
		return $this->render_plugin_info_template( 'features' );
	}

	private function get_integrations(): string {
		return $this->render_plugin_info_template( 'integrations' );
	}

	private function get_installation(): string {
		return $this->render_plugin_info_template( 'installation' );
	}

	private function get_faq(): string {
		return $this->render_plugin_info_template( 'faq' );
	}

	private function get_changelog(): string {
		return $this->render_plugin_info_template( 'changelog' );
	}
}
