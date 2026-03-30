<?php
/**
 * Plugin Details Page
 * 
 * Displays plugin information in a modal-friendly format
 * 
 * @package ContactIn\Admin\Pages
 */

declare(strict_types=1);

namespace ContactInbox\Admin\Pages;

use ContactInbox\Core\Config;
use ContactInbox\Core\TemplateLoader;
use ContactInbox\Admin\PluginInfo;
use ContactInbox\Traits\Singleton;

// phpcs:disable WordPress.WP.I18n.NonSingularStringLiteralDomain, WordPress.Security.EscapeOutput.OutputNotEscaped, Generic.PHP.ForbiddenFunctions.Found, PluginCheck.CodeAnalysis.DiscouragedFunctions.load_plugin_textdomainFound, PluginCheck.CodeAnalysis.Heredoc.NotAllowed, PluginCheck.Security.DirectDB.UnescapedDBParameter, Squiz.PHP.DiscouragedFunctions.Discouraged, WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.NamingConventions.PrefixAllGlobals.DynamicHooknameFound, WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound, WordPress.PHP.DevelopmentFunctions.error_log_debug_backtrace, WordPress.WP.AlternativeFunctions.file_system_operations_fsockopen, WordPress.WP.AlternativeFunctions.file_system_operations_readfile, WordPress.WP.AlternativeFunctions.file_system_operations_rmdir, WordPress.WP.EnqueuedResourceParameters.MissingVersion, WordPress.WP.EnqueuedResources.NonEnqueuedScript, WordPress.WP.I18n.MissingArgDomain, WordPress.WP.I18n.UnorderedPlaceholdersPlural, WordPress.WP.I18n.UnorderedPlaceholdersSingle

if (!defined('ABSPATH')) {
    exit;
}

final class PluginDetails {
    use Singleton;

    private const TEMPLATE_FILES = [
        'modal' => 'details-modal.php',
        'tab_description' => 'details-tab-description.php',
        'tab_readme' => 'details-tab-readme.php',
        'tab_screenshots' => 'details-tab-screenshots.php',
        'shared_installation' => 'shared-installation.php',
        'shared_faq' => 'shared-faq.php',
        'shared_changelog' => 'shared-changelog.php',
    ];

    protected function __construct() {
        // Constructor is now empty
        // AJAX handler is registered in contactin.php (main plugin file)
        // so it works even when plugin is deactivated
    }

    /**
     * Static method to render plugin details - works when called directly
     */
    public static function render_details_static(): void {
        // Call the instance method
        self::instance()->render_details();
    }

    private function get_template_path(string $template_key): string {
        if (!isset(self::TEMPLATE_FILES[$template_key])) {
            return '';
        }

        return CONTACTINBOX_PATH . Config::TEMPLATE_ADMIN . 'plugin/' . self::TEMPLATE_FILES[$template_key];
    }

    private function get_plugin_version(): string {
        if (defined('CONTACTINBOX_VERSION')) {
            return (string) CONTACTINBOX_VERSION;
        }

        return Config::VERSION;
    }

    /**
     * Render plugin details page
     */
    public function render_details(): void {
        // Set proper headers for iframe content
        header('Content-Type: text/html; charset=utf-8');
        header('X-Frame-Options: SAMEORIGIN');
        
        // No nonce check needed - this is public plugin information
        // Capability check - any logged-in user who can access admin can see plugin details
        if (!is_admin()) {
            status_header(403);
            wp_die(esc_html__('Invalid request.',  'contactin'));
        }
        
        // Disable WordPress error display in AJAX
        if (defined('WP_DEBUG') && WP_DEBUG) {
            @ini_set('display_errors', 0);
        }
        $template = $this->get_template_path('modal');

        echo TemplateLoader::render($template, [
            'tab_documentation' => $this->get_readme_tab(),
            'tab_description' => $this->get_description_tab(),
            'tab_installation' => $this->get_installation_tab(),
            'tab_faq' => $this->get_faq_tab(),
            'tab_changelog' => $this->get_changelog_tab(),
            'tab_screenshots' => $this->get_screenshots_tab(),
            'plugin_version' => $this->get_plugin_version(),
        ]);

        exit;
    }

    private function get_description_tab(): string {
        $readme_description = PluginInfo::instance()->get_readme_section_html('description');

        return TemplateLoader::render(
            $this->get_template_path('tab_description'),
            [
                'readme_description' => $readme_description,
            ]
        );
    }

    private function get_installation_tab(): string {
        return TemplateLoader::render(
            $this->get_template_path('shared_installation'),
            [
                'requires' => '6.4',
                'tested' => '6.9.1',
                'requires_php' => '7.4',
            ]
        );
    }

    private function get_faq_tab(): string {
        return TemplateLoader::render(
            $this->get_template_path('shared_faq'),
            [
                'github_url' => 'https://github.com/bizjaved/contactin-pro',
                'github_label' => 'github.com/bizjaved/contactin-pro',
            ]
        );
    }

    private function get_changelog_tab(): string {
        return TemplateLoader::render(
            $this->get_template_path('shared_changelog'),
            [
                'version' => $this->get_plugin_version(),
                'release_date' => 'February 13, 2026',
                'changelog_url' => 'https://github.com/bizjaved/contactin-pro/blob/main/CHANGELOG.md',
            ]
        );
    }

    private function get_screenshots_tab(): string {
        return TemplateLoader::render(
            $this->get_template_path('tab_screenshots')
        );
    }

    private function get_readme_tab(): string {
        $sections = PluginInfo::instance()->get_readme_sections();

        $normalize_key = static function (string $key): string {
            $key = strtolower(trim($key));
            $key = str_replace(["’", "'"], '', $key);
            return $key;
        };

        $find_section = static function (array $sections, string $needle) use ($normalize_key): array {
            $needle = $normalize_key($needle);
            foreach ($sections as $section) {
                if ($normalize_key($section['key']) === $needle) {
                    return $section;
                }
            }
            return [];
        };

        $section_description = $find_section($sections, 'description');
        $section_who = $find_section($sections, 'who it’s for');
        if (empty($section_who)) {
            $section_who = $find_section($sections, 'who its for');
        }
        $section_features = $find_section($sections, 'features');
        $section_free_pro = $find_section($sections, 'free vs pro');
        $section_quick_start = $find_section($sections, 'quick start');
        $section_installation = $find_section($sections, 'installation');
        $section_faq = $find_section($sections, 'frequently asked questions');
        $section_privacy = $find_section($sections, 'privacy & data collection');
        $section_docs = $find_section($sections, 'documentation & support');
        $section_changelog = $find_section($sections, 'changelog');

        $summary_text = '';
        if (!empty($section_description['content'])) {
            $plain = trim(wp_strip_all_tags($section_description['content']));
            if ($plain !== '') {
                $summary_text = wp_trim_words($plain, 38, '...');
            }
        }


        return TemplateLoader::render(
            $this->get_template_path('tab_readme'),
            [
                'sections' => $sections,
                'summary_text' => $summary_text,
                'main_sections' => [
                    $section_features,
                    $section_free_pro,
                    $section_quick_start,
                    $section_installation,
                    $section_faq,
                    $section_privacy,
                    $section_changelog,
                ],
                'side_sections' => [
                    $section_description,
                    $section_who,
                    $section_docs,
                ],
            ]
        );
    }
}