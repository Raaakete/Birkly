<?php
/**
 * P58-M2 — onboarding Storage paths must stay inside the install tree when cwd drifts.
 */

declare(strict_types=1);

$root = dirname(__DIR__);
$GLOBALS['__HARU_INTERNAL_CALL'] = true;

$failures = 0;

function assert_true(bool $cond, string $label): void
{
    global $failures;
    if ($cond) {
        echo "OK  $label\n";
        return;
    }
    echo "FAIL $label\n";
    $failures++;
}

require_once $root . '/core/birkly_paths.php';
require_once $root . '/api/storage.php';
require_once $root . '/api/onboarding.php';

$marker = 'p58_m2_' . bin2hex(random_bytes(4));
$parentSettings = dirname($root) . '/settings';
$parentLeakFile = $parentSettings . '/onboarding.json';
$projectOnboarding = $root . '/settings/onboarding.json';

$hadParentLeak = is_file($parentLeakFile);
$parentLeakBackup = $hadParentLeak ? file_get_contents($parentLeakFile) : null;
$hadProjectFile = is_file($projectOnboarding);
$projectBackup = $hadProjectFile ? file_get_contents($projectOnboarding) : null;

$prevCwd = getcwd();
chdir($root);

try {
    saveSiteOnboardingSettings(['install_path' => 'local', 'p58_marker' => $marker]);

    assert_true(is_file($projectOnboarding), 'onboarding.json created under project settings/');

    $projectData = json_decode((string) file_get_contents($projectOnboarding), true);
    assert_true(is_array($projectData) && ($projectData['p58_marker'] ?? '') === $marker, 'marker persisted in project settings');

    if (is_file($parentLeakFile)) {
        $parentData = json_decode((string) file_get_contents($parentLeakFile), true);
        $parentHasMarker = is_array($parentData) && ($parentData['p58_marker'] ?? '') === $marker;
        assert_true(!$parentHasMarker, 'no write leak to parent ../settings when cwd is project root');
    } else {
        echo "OK  parent ../settings/onboarding.json not created (no leak)\n";
    }

    $runtime = birkly_ensure_runtime_dirs($root);
    foreach (birkly_runtime_dir_names() as $dir) {
        assert_true(($runtime[$dir]['exists'] ?? false) && ($runtime[$dir]['writable'] ?? false), "runtime dir writable: $dir");
    }
} finally {
    if ($prevCwd !== false) {
        chdir($prevCwd);
    }
    if ($hadProjectFile) {
        file_put_contents($projectOnboarding, (string) $projectBackup);
    } elseif (is_file($projectOnboarding)) {
        @unlink($projectOnboarding);
    }
    if ($hadParentLeak) {
        file_put_contents($parentLeakFile, (string) $parentLeakBackup);
    } elseif (is_file($parentLeakFile)) {
        @unlink($parentLeakFile);
    }
}

if ($failures > 0) {
    fwrite(STDERR, "$failures test(s) failed\n");
    exit(1);
}

echo "onboarding_storage_paths_test: PASS\n";
exit(0);
