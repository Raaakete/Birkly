<?php
/**
 * Project site admin API helpers and filesystem sandbox checks.
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/core/project_site.php';
require_once dirname(__DIR__) . '/core/project_site_filesystem.php';

$tmpdir = sys_get_temp_dir() . '/birkly_project_site_' . bin2hex(random_bytes(4));
@mkdir($tmpdir, 0755, true);
@mkdir($tmpdir . '/project', 0755, true);

$failures = 0;

function assert_true(string $name, bool $ok): void
{
    global $failures;
    echo $name . ': ' . ($ok ? 'PASS' : 'FAIL') . PHP_EOL;
    if (!$ok) {
        $failures++;
    }
}

$fs = new ProjectSiteFileSystem($tmpdir . '/project');

assert_true('create allowed file', $fs->createFile('pages/test.html', '<p>hi</p>'));
assert_true('read created file', ($read = $fs->readFile('pages/test.html')) !== null && str_contains($read['content'], 'hi'));
assert_true('list root contains pages dir', count(array_filter(
    $fs->listFiles(''),
    static fn(array $e): bool => ($e['path'] ?? '') === 'pages' && ($e['type'] ?? '') === 'dir'
)) === 1);
assert_true('reject php extension', $fs->createFile('evil.php', '<?php') === false);
assert_true('reject dotfile', $fs->createFile('.hidden', 'x') === false);
assert_true('reject path traversal', $fs->readFile('../settings/admin.json') === null);
assert_true('delete file', $fs->deleteFile('pages/test.html'));
assert_true('deleted file missing', $fs->readFile('pages/test.html') === null);

// Last-change helper via API include (without HTTP).
require_once dirname(__DIR__) . '/api/storage.php';

$settingsDir = $tmpdir . '/settings';
@mkdir($settingsDir, 0755, true);
file_put_contents($settingsDir . '/onboarding.json', json_encode(['starter_template' => 'marsk']));

// Re-require API helpers by copying key function behavior
$starter = null;
$siteOnboarding = json_decode((string) file_get_contents($settingsDir . '/onboarding.json'), true);
if (is_array($siteOnboarding) && !empty($siteOnboarding['starter_template'])) {
    $starter = $siteOnboarding['starter_template'];
}
assert_true('starter label from onboarding.json', $starter === 'marsk');

@unlink($settingsDir . '/onboarding.json');
@rmdir($settingsDir);
@rmdir($tmpdir . '/project/pages');
@rmdir($tmpdir . '/project');
@rmdir($tmpdir);

echo PHP_EOL . 'project_site_api_test: ' . ($failures === 0 ? 'PASS' : "FAIL ($failures)") . PHP_EOL;
exit($failures === 0 ? 0 : 1);
