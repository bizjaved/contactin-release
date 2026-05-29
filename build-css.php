<?php
/**
 * ContactIn CSS build script.
 *
 * Generates minified CSS files in dist/css from assets/src/css.
 *
 * Usage:
 *   php build-css.php
 */

declare(strict_types=1);

$rootDir = __DIR__;
$srcDir  = $rootDir . '/assets/src/css';
$distDir = $rootDir . '/dist/css';

if (!is_dir($srcDir)) {
    fwrite(STDERR, "Error: Source directory not found: {$srcDir}\n");
    exit(1);
}

if (!is_dir($distDir) && !mkdir($distDir, 0775, true) && !is_dir($distDir)) {
    fwrite(STDERR, "Error: Could not create dist directory: {$distDir}\n");
    exit(1);
}

/**
 * Lightweight CSS minifier suitable for distributable assets.
 */
function contactin_minify_css(string $css): string
{
    // Remove block comments (keep /*! ... */ comments).
    $css = preg_replace('#/\*(?!\!)(.*?)\*/#s', '', $css) ?? $css;
    // Normalize line endings and collapse whitespace.
    $css = str_replace(["\r\n", "\r"], "\n", $css);
    $css = preg_replace('/\s+/', ' ', $css) ?? $css;
    // Remove unnecessary spaces around tokens.
    $css = preg_replace('/\s*([{};:,>+~])\s*/', '$1', $css) ?? $css;
    // Remove final semicolon before }.
    $css = str_replace(';}', '}', $css);

    return trim($css) . "\n";
}

$files = glob($srcDir . '/*.css') ?: [];
$built = 0;
$failed = 0;

foreach ($files as $srcFile) {
    $baseName = basename($srcFile, '.css');
    $distFile = $distDir . '/' . $baseName . '.min.css';

    $raw = @file_get_contents($srcFile);
    if ($raw === false) {
        fwrite(STDERR, "Failed to read: {$srcFile}\n");
        $failed++;
        continue;
    }

    $minified = contactin_minify_css($raw);
    $ok = @file_put_contents($distFile, $minified);
    if ($ok === false) {
        fwrite(STDERR, "Failed to write: {$distFile}\n");
        $failed++;
        continue;
    }

    echo "Built: " . basename($distFile) . "\n";
    $built++;
}

echo "\nCSS build complete. Built {$built} file(s).";
if ($failed > 0) {
    echo " Failed {$failed} file(s).\n";
    exit(2);
}

echo "\n";
exit(0);
