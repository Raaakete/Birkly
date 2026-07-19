<?php
/**
 * Ensure a stable admin account exists for beta review video recording.
 * Usage: php scripts/prepare-beta-video-admin.php
 */
declare(strict_types=1);

$root = dirname(__DIR__);
require_once $root . '/tests/journeys/_helpers.php';

$base = getenv('BIRKLY_BASE_URL') ?: 'http://127.0.0.1:8080';
$email = getenv('BETA_VIDEO_ADMIN_EMAIL') ?: 'beta-review@birkly.local';
$password = getenv('BETA_VIDEO_ADMIN_PASSWORD') ?: 'BetaReview123!';
$name = 'Beta Review Admin';

$cookieFile = sys_get_temp_dir() . '/birkly-beta-video-admin.txt';
@unlink($cookieFile);

$login = journey_http($base, '/admin/login.php');
if ($login['code'] === 200 && stripos($login['body'], 'create') !== false && stripos($login['body'], 'first') !== false) {
    journey_create_first_admin($base, $cookieFile, $email, $password, $name);
    journey_onboarding_skip($base, $cookieFile);
    echo "Created first admin: $email\n";
    exit(0);
}

// Try logging in with known credentials
$loginAttempt = journey_http($base, '/admin/login.php', [
    'method' => 'POST',
    'cookie_file' => $cookieFile,
    'headers' => ['Content-Type: application/x-www-form-urlencoded'],
    'body' => http_build_query(['email' => $email, 'password' => $password]),
]);
$dash = journey_http($base, '/admin/index.php?page=dashboard', ['cookie_file' => $cookieFile]);
if ($dash['code'] === 200 && stripos($dash['body'], 'dashboard') !== false) {
    echo "Admin ready: $email\n";
    exit(0);
}

// Reset users and create fresh admin for video
$usersDir = $root . '/users';
$settingsDir = $root . '/settings';
foreach (glob($usersDir . '/*.json') ?: [] as $f) {
    @unlink($f);
}
$adminSettings = $settingsDir . '/admin.json';
if (is_file($adminSettings)) {
    @unlink($adminSettings);
}

journey_create_first_admin($base, $cookieFile, $email, $password, $name);
journey_onboarding_skip($base, $cookieFile);
echo "Reset and created admin: $email\n";
