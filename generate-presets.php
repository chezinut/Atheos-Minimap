<?php
/**
 * Generate minimap color presets from ACE theme files.
 * Run: php generate-presets.php
 * Output: presets.json
 */

$themeDir = __DIR__ . '/../../components/editor/ace-editor';
$patterns = [
    // More specific patterns first to avoid false matches
    'rex' => '/\.ace_string\.ace_regexp[^{]*\{[^}]*color:\s*([^;}]+)/',
    'pct' => '/\.ace_keyword\.ace_operator[^{]*\{[^}]*color:\s*([^;}]+)/',
    'num' => '/\.ace_constant\.ace_numeric[^{]*\{[^}]*color:\s*([^;}]+)/',
    'bol' => '/\.ace_constant\.ace_language[^{]*\{[^}]*color:\s*([^;}]+)/',
    'con' => '/\.ace_support\.ace_function[^{]*\{[^}]*color:\s*([^;}]+)/',
    'brc' => '/\.ace_entity\.ace_name\.ace_tag[^{]*\{[^}]*color:\s*([^;}]+)/',
    // Generic patterns after — exclude regex rules from string pattern
    'key' => '/\.ace_keyword[^{]*\{[^}]*color:\s*([^;}]+)/',
    'str' => '/\.ace_string(?!\.ace_regexp)[^{]*\{[^}]*color:\s*([^;}]+)/',
    'com' => '/\.ace_comment[^{]*\{[^}]*color:\s*([^;}]+)/',
];

$presets = [];

$files = glob($themeDir . '/theme-*.js');
foreach ($files as $file) {
    $basename = basename($file, '.js');
    $themeName = str_replace('theme-', '', $basename);
    $themeId = 'ace/theme/' . $themeName;

    $content = file_get_contents($file);

    // Extract cssText - handle double quotes, single quotes, and backticks
    $cssText = '';
    if (preg_match('/n\.exports\s*=\s*["\'](.+?)["\']\s*[,\)]/s', $content, $m)) {
        $cssText = $m[1];
    } elseif (preg_match('/exports\.cssText\s*=\s*["\x60](.+?)["\x60]/s', $content, $m)) {
        $cssText = $m[1];
    } elseif (preg_match('/n\.exports\s*=\s*["\x60](.+?)["\x60]/s', $content, $m)) {
        $cssText = $m[1];
    }

    if (empty($cssText)) {
        echo "SKIP: $themeName (no cssText found)\n";
        continue;
    }

    // Unescape the CSS string
    $cssText = str_replace('\\n', "\n", $cssText);
    $cssText = str_replace('\\t', "\t", $cssText);
    $cssText = str_replace('\\"', '"', $cssText);
    $cssText = str_replace("\\'", "'", $cssText);

    $colors = [];
    foreach ($patterns as $key => $regex) {
        if (preg_match($regex, $cssText, $m)) {
            $color = trim($m[1]);
            // Skip var() references - they won't resolve outside the browser
            if (strpos($color, 'var(') !== false) {
                $colors[$key] = null;
            } else {
                $colors[$key] = $color;
            }
        } else {
            $colors[$key] = null;
        }
    }

    // Extract base text color: .ace-theme { color: #xxx }
    $cssClass = '';
    if (preg_match('/exports\.cssClass\s*=\s*"([^"]+)"/', $content, $m)) {
        $cssClass = $m[1];
    }
    if ($cssClass) {
        $basePattern = '/\.' . preg_quote(str_replace('.', '\\.', $cssClass), '/') . '\s*\{[^}]*color:\s*([^;}]+)/';
        if (preg_match($basePattern, $cssText, $m)) {
            $baseColor = trim($m[1]);
            if (strpos($baseColor, 'var(') === false) {
                $colors['nam'] = $baseColor;
            }
        }
    }

    $presets[$themeId] = $colors;
    echo "OK: $themeName -> " . json_encode($colors) . "\n";
}

// Add the Atheos theme with CSS variable fallbacks
$presets['ace/theme/atheos'] = [
    'key' => null,  // var(--orange) - will use CSS fallback
    'str' => null,  // var(--cyan)
    'com' => null,  // var(--fontColorSmall)
    'num' => null,  // var(--green)
    'bol' => null,  // var(--green) [not defined, uses default]
    'con' => null,  // var(--yellow) [not defined, uses default]
    'rex' => null,
    'pct' => null,
    'brc' => null,
    'nam' => null,  // var(--shade3)
];

file_put_contents(__DIR__ . '/presets.json', json_encode($presets, JSON_PRETTY_PRINT));
echo "\nGenerated presets.json with " . count($presets) . " themes\n";
