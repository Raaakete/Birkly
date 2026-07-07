<?php
/**
 * P19-M3 — Install path router: persist local|cloud in settings/onboarding.json
 */

$GLOBALS['__HARU_INTERNAL_CALL'] = true;
require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/../api/storage.php';
require_once __DIR__ . '/../api/onboarding.php';

test_reset_failures();
echo "Onboarding install path tests\n\n";

$root = dirname(__DIR__);
$settingsDir = $root . '/settings';
$onboardingFile = $settingsDir . '/onboarding.json';
$backup = is_file($onboardingFile) ? file_get_contents($onboardingFile) : null;

try {
    if (is_file($onboardingFile)) {
        unlink($onboardingFile);
    }

    // Storage paths in onboarding.php are relative to api/
    $prevCwd = getcwd();
    chdir($root . '/api');

    test_assert('getSiteOnboardingSettings returns empty by default', getSiteOnboardingSettings() === []);

    saveSiteOnboardingSettings(['install_path' => 'local']);
    $saved = getSiteOnboardingSettings();
    test_assert('install_path local persisted', ($saved['install_path'] ?? '') === 'local');

    saveSiteOnboardingSettings(['install_path' => 'cloud']);
    $saved = getSiteOnboardingSettings();
    test_assert('install_path cloud persisted', ($saved['install_path'] ?? '') === 'cloud');

    $status = getOnboardingStatus('test@example.com');
    test_assert('getOnboardingStatus includes install_path', ($status['install_path'] ?? '') === 'cloud');

    chdir($prevCwd);

    test_assert('START_HERE.html exists', is_file($root . '/START_HERE.html'));
    $startHere = file_get_contents($root . '/START_HERE.html');
    test_assert('START_HERE builds install param in JS', strpos($startHere, "install=' + install") !== false || strpos($startHere, 'install=local') !== false);
    test_assert('START_HERE cloud install param', strpos($startHere, 'install=cloud') !== false || strpos($startHere, "install=' + install") !== false);
} finally {
    if ($backup === null && is_file($onboardingFile)) {
        unlink($onboardingFile);
    } elseif ($backup !== null) {
        file_put_contents($onboardingFile, $backup);
    }
}

exit(test_finish('onboarding_install_path_test'));
