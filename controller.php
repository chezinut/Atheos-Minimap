<?php
//////////////////////////////////////////////////////////////////////////////80
// Minimap Controller
//////////////////////////////////////////////////////////////////////////////80

$action = POST("action");

switch ($action) {

	case 'generate':
		$themeDir = __DIR__ . '/../../components/editor/ace-editor';
		$patterns = [
			'rex' => '/\.ace_string\.ace_regexp[^{]*\{[^}]*color:\s*([^;}]+)/',
			'pct' => '/\.ace_keyword\.ace_operator[^{]*\{[^}]*color:\s*([^;}]+)/',
			'num' => '/\.ace_constant\.ace_numeric[^{]*\{[^}]*color:\s*([^;}]+)/',
			'bol' => '/\.ace_constant\.ace_language[^{]*\{[^}]*color:\s*([^;}]+)/',
			'con' => '/\.ace_support\.ace_function[^{]*\{[^}]*color:\s*([^;}]+)/',
			'brc' => '/\.ace_entity\.ace_name\.ace_tag[^{]*\{[^}]*color:\s*([^;}]+)/',
			'var' => '/\.ace_variable[^.][^{]*\{[^}]*color:\s*([^;}]+)/',
			'key' => '/\.ace_keyword[^{]*\{[^}]*color:\s*([^;}]+)/',
			'str' => '/\.ace_string(?!\.ace_regexp)[^{]*\{[^}]*color:\s*([^;}]+)/',
			'com' => '/\.ace_comment[^{]*\{[^}]*color:\s*([^;}]+)/',
		];

		$presets = [];
		$messages = [];

		$files = glob($themeDir . '/theme-*.js');
		foreach ($files as $file) {
			$basename = basename($file, '.js');
			$themeName = str_replace('theme-', '', $basename);
			$themeId = 'ace/theme/' . $themeName;

			$content = file_get_contents($file);

			$cssText = '';
			if (preg_match('/n\.exports\s*=\s*["\'](.+?)["\']\s*[,\)]/s', $content, $m)) {
				$cssText = $m[1];
			} elseif (preg_match('/exports\.cssText\s*=\s*["\x60](.+?)["\x60]/s', $content, $m)) {
				$cssText = $m[1];
			} elseif (preg_match('/n\.exports\s*=\s*["\x60](.+?)["\x60]/s', $content, $m)) {
				$cssText = $m[1];
			}

			if (empty($cssText)) {
				$messages[] = "SKIP: $themeName (no cssText found)";
				continue;
			}

			$cssText = str_replace('\\n', "\n", $cssText);
			$cssText = str_replace('\\t', "\t", $cssText);
			$cssText = str_replace('\\"', '"', $cssText);
			$cssText = str_replace("\\'", "'", $cssText);

			$colors = [];
			foreach ($patterns as $key => $regex) {
				if (preg_match($regex, $cssText, $m)) {
					$color = trim($m[1]);
					$colors[$key] = strpos($color, 'var(') !== false ? null : $color;
				} else {
					$colors[$key] = null;
				}
			}

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
			$messages[] = "OK: $themeName -> " . json_encode($colors);
		}

		$presets['ace/theme/atheos'] = [
			'key' => null,
			'str' => null,
			'com' => null,
			'num' => null,
			'bol' => null,
			'con' => null,
			'rex' => null,
			'pct' => null,
			'brc' => null,
			'nam' => null,
		];

		$presetFile = __DIR__ . '/presets.json';
		file_put_contents($presetFile, json_encode($presets, JSON_PRETTY_PRINT));
		$messages[] = "\nGenerated presets.json with " . count($presets) . " themes";

		Common::send(200, implode("\n", $messages));
		break;

	case 'check':
		$presetFile = __DIR__ . '/presets.json';
		Common::send(200, ['exists' => file_exists($presetFile)]);

		break;

	case 'load':
		$presetFile = __DIR__ . '/presets.json';
		if (!file_exists($presetFile)) {
			Common::send(404, "presets.json not found");
		}
		$data = json_decode(file_get_contents($presetFile), true);
		if ($data === null) {
			Common::send(500, "Invalid JSON in presets.json");
		}
		Common::send(200, $data);

		break;

	default:
		Common::send(400, "Invalid action");
		break;
}
