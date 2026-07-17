<?php
//////////////////////////////////////////////////////////////////////////////80
// Minimap Controller
//////////////////////////////////////////////////////////////////////////////80

$action = POST("action");

switch ($action) {

	case 'generate':
		$script = __DIR__ . '/generate-presets.php';
		if (!file_exists($script)) {
			Common::send(400, "generate-presets.php not found");
		}
		exec('php ' . escapeshellarg($script) . ' 2>&1', $output, $returnVar);
		$text = implode("\n", $output);
		if ($returnVar === 0) {
			Common::send(200, $text);
		} else {
			Common::send(400, "Exit code $returnVar\n$text");
		}
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
