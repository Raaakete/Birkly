<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/core/cms_mcp_admin.php';

$failures = 0;
$mediaDir = dirname(__DIR__) . '/media';
$metadataPath = $mediaDir . '/.metadata.json';

function assert_true(string $name, bool $ok, string $detail = ''): void
{
    global $failures;
    echo $name . ': ' . ($ok ? "PASS\n" : "FAIL\n");
    if (!$ok) {
        $failures++;
        if ($detail !== '') {
            echo '  ' . $detail . "\n";
        }
    }
}

$meta = cms_mcp_admin_action_meta();
assert_true('media.list in catalog', isset($meta['media.list']));
assert_true('media.rename_folder in catalog', isset($meta['media.rename_folder']));
assert_true('media.update is write', ($meta['media.update']['write'] ?? false) === true);
assert_true('media.replace in catalog', isset($meta['media.replace']));
assert_true('media.create_variant in catalog', isset($meta['media.create_variant']));

$aiUser = ['id' => 'test', 'role' => 'admin', 'email' => 'admin@test.local', 'name' => 'Admin'];

if (!is_dir($mediaDir)) {
    mkdir($mediaDir, 0755, true);
}

$testId = 'mcpmediatest' . bin2hex(random_bytes(4));
$testFile = $mediaDir . '/' . $testId . '.txt';
file_put_contents($testFile, 'mcp media test');
$metadata = file_exists($metadataPath) ? json_decode((string) file_get_contents($metadataPath), true) : [];
if (!is_array($metadata)) {
    $metadata = [];
}
$metadata[$testId] = [
    'id' => $testId,
    'name' => 'Original',
    'title' => 'Original',
    'ext' => 'txt',
    'type' => 'text/plain',
    'size' => filesize($testFile),
    'url' => '/media/' . $testId . '.txt',
    'folder' => '',
    'alt_text' => '',
    'created_at' => date('c'),
];
file_put_contents($metadataPath, json_encode($metadata, JSON_PRETTY_PRINT));

$folders = cms_mcp_dispatch_admin('media.folders', [], $aiUser);
assert_true('media.folders ok', ($folders['status'] ?? '') === 'ok');

$update = cms_mcp_dispatch_admin('media.update', [
    'id' => $testId,
    'title' => 'Renamed via MCP',
    'alt_text' => 'Alt from MCP',
    'crop_rect' => ['x' => 10, 'y' => 10, 'w' => 80, 'h' => 80],
], $aiUser);
assert_true('media.update ok', ($update['status'] ?? '') === 'ok', json_encode($update));

$get = cms_mcp_dispatch_admin('media.get', ['id' => $testId], $aiUser);
$title = $get['data']['title'] ?? '';
assert_true('media.get title updated', $title === 'Renamed via MCP', 'got: ' . $title);
$crop = $get['data']['crop_rect'] ?? null;
assert_true(
    'media.update crop_rect',
    is_array($crop) && (int) ($crop['x'] ?? 0) === 10 && (int) ($crop['w'] ?? 0) === 80,
    json_encode($crop)
);

$pngBytes = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==');
$replace = cms_mcp_dispatch_admin('media.replace', [
    'id' => $testId,
    'base64' => base64_encode($pngBytes),
    'name' => 'replaced.png',
], $aiUser);
assert_true('media.replace ok', ($replace['status'] ?? '') === 'ok', json_encode($replace));
$getReplaced = cms_mcp_dispatch_admin('media.get', ['id' => $testId], $aiUser);
$url = (string) ($getReplaced['data']['url'] ?? '');
assert_true('media.replace updates url extension', str_ends_with($url, '.png'), 'url=' . $url);

$createFolder = cms_mcp_dispatch_admin('media.create_folder', ['name' => 'mcp_test_folder'], $aiUser);
assert_true('media.create_folder ok', ($createFolder['status'] ?? '') === 'ok');

$move = cms_mcp_dispatch_admin('media.move', ['id' => $testId, 'folder' => 'mcp_test_folder'], $aiUser);
assert_true('media.move ok', ($move['status'] ?? '') === 'ok');

$getMoved = cms_mcp_dispatch_admin('media.get', ['id' => $testId], $aiUser);
$url = (string) ($getMoved['data']['url'] ?? '');
assert_true('media.move updates url', str_contains($url, 'mcp_test_folder'), 'url=' . $url);

$renameFolder = cms_mcp_dispatch_admin('media.rename_folder', [
    'old_name' => 'mcp_test_folder',
    'new_name' => 'mcp_test_folder_renamed',
], $aiUser);
assert_true('media.rename_folder ok', ($renameFolder['status'] ?? '') === 'ok');

$deleteFolder = cms_mcp_dispatch_admin('media.delete_folder', [
    'name' => 'mcp_test_folder_renamed',
    'handle_files' => 'move',
], $aiUser);
assert_true('media.delete_folder ok', ($deleteFolder['status'] ?? '') === 'ok');

// cleanup file
$metadata = json_decode((string) file_get_contents($metadataPath), true) ?: [];
if (isset($metadata[$testId])) {
    $item = $metadata[$testId];
    $folder = (string) ($item['folder'] ?? '');
    $ext = (string) ($item['ext'] ?? 'txt');
    $path = $mediaDir . ($folder !== '' ? '/' . $folder : '') . '/' . $testId . '.' . $ext;
    if (is_file($path)) {
        unlink($path);
    }
    unset($metadata[$testId]);
    file_put_contents($metadataPath, json_encode($metadata, JSON_PRETTY_PRINT));
}

exit($failures > 0 ? 1 : 0);
