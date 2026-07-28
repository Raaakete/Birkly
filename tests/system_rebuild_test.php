<?php
/**
 * Unit tests for system rebuild helper.
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/core/system_rebuild.php';

$failures = 0;

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

$tmpRoot = sys_get_temp_dir() . '/birkly-rebuild-test-' . getmypid();
@mkdir($tmpRoot, 0755, true);
@mkdir($tmpRoot . '/content/collections/blog/entries', 0755, true);
@mkdir($tmpRoot . '/content/collections/artworks/entries', 0755, true);
@mkdir($tmpRoot . '/users', 0755, true);
@mkdir($tmpRoot . '/settings', 0755, true);
@mkdir($tmpRoot . '/project', 0755, true);

file_put_contents($tmpRoot . '/content/collections/blog/entries/post-1.json', '{"id":"post-1"}');
file_put_contents($tmpRoot . '/content/collections/artworks/collection.json', '{"slug":"artworks","name":"Artworks"}');
file_put_contents($tmpRoot . '/content/collections/artworks/entries/piece-1.json', '{"id":"piece-1"}');
file_put_contents($tmpRoot . '/users/admin_onboarding.json', '{"setup_wizard_completed":true}');
file_put_contents($tmpRoot . '/settings/onboarding.json', '{"install_path":"test"}');
file_put_contents($tmpRoot . '/project/index.html', '<html></html>');

$result = birkly_rebuild_system($tmpRoot);

assert_true('rebuild returns success', !empty($result['success']), $result['message'] ?? '');
assert_true('entry file removed', !is_file($tmpRoot . '/content/collections/blog/entries/post-1.json'));
assert_true('user-created collection removed', !is_dir($tmpRoot . '/content/collections/artworks'));
assert_true('starter collection folder removed', !is_dir($tmpRoot . '/content/collections/blog'));
assert_true('onboarding file removed', !is_file($tmpRoot . '/users/admin_onboarding.json'));
assert_true('site onboarding settings removed', !is_file($tmpRoot . '/settings/onboarding.json'));
assert_true('project site cleared', !is_file($tmpRoot . '/project/index.html'));

// cleanup
function rrmdir(string $dir): void
{
    if (!is_dir($dir)) {
        return;
    }
    foreach (scandir($dir) ?: [] as $item) {
        if ($item === '.' || $item === '..') {
            continue;
        }
        $path = $dir . '/' . $item;
        if (is_dir($path)) {
            rrmdir($path);
        } else {
            @unlink($path);
        }
    }
    @rmdir($dir);
}

rrmdir($tmpRoot);

echo "\nsystem_rebuild_test: " . ($failures === 0 ? "PASS\n" : "FAIL ($failures)\n");
exit($failures === 0 ? 0 : 1);
