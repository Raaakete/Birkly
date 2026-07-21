<?php
/**
 * Phase 32 Pillar E API regression tests.
 */

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/http_test_helpers.php';

test_reset_failures();
echo "Pillar E API tests\n\n";

if (!function_exists('curl_init')) {
    fwrite(STDERR, "pillar_e_api_test: curl extension required\n");
    exit(1);
}

function pillar_e_extract_login_csrf(string $html): string
{
    if (preg_match('/name="_csrf"\s+value="([^"]+)"/', $html, $match)) {
        return $match[1];
    }
    if (preg_match('/name=\'_csrf\'\s+value=\'([^\']+)\'/', $html, $match)) {
        return $match[1];
    }
    return '';
}

function pillar_e_remove_tree(string $path): void
{
    if (!file_exists($path)) {
        return;
    }
    if (is_file($path) || is_link($path)) {
        @unlink($path);
        return;
    }
    foreach (scandir($path) ?: [] as $entry) {
        if ($entry === '.' || $entry === '..') {
            continue;
        }
        pillar_e_remove_tree($path . '/' . $entry);
    }
    @rmdir($path);
}

$root = dirname(__DIR__);
$port = 18200 + (getmypid() % 1000);
$base = 'http://127.0.0.1:' . $port;
$cookieFile = sys_get_temp_dir() . '/birkly-pillar-e-cookies-' . getmypid() . '.txt';
$usersDir = $root . '/users';
$backupUsers = sys_get_temp_dir() . '/birkly-pillar-e-users-' . getmypid();

$collectionSlug = 'pillar-e-' . getmypid();
$collectionDir = $root . '/content/collections/' . $collectionSlug;
$entriesDir = $collectionDir . '/entries';
$entryId = 'entry-one';
$collectionName = 'Phase 32 Articles ' . getmypid();
$originalTitle = 'Phase 32 Article ' . getmypid();
$updatedTitle = 'Phase 32 Updated ' . getmypid();

$redirectsFile = $root . '/settings/redirects.json';
$backupRedirects = sys_get_temp_dir() . '/birkly-pillar-e-redirects-' . getmypid() . '.json';
$redirectFromPath = '/old-phase-' . getmypid();
$redirectToPath = '/new-phase-' . getmypid();

$adminEmail = 'pillar-e-' . getmypid() . '@birkly.test';
$adminPassword = 'SecurePass123!';

@unlink($cookieFile);
touch($cookieFile);

$serverPid = birkly_test_http_server_start($root, $port);

try {
    birkly_test_backup_directory($usersDir, $backupUsers);
    birkly_test_clear_json_files($usersDir);

    if (is_file($redirectsFile)) {
        copy($redirectsFile, $backupRedirects);
    } else {
        @unlink($backupRedirects);
    }
    @unlink($redirectsFile);

    @mkdir($entriesDir, 0755, true);
    file_put_contents($collectionDir . '/collection.json', json_encode([
        'name' => $collectionName,
        'slug' => $collectionSlug,
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    file_put_contents($collectionDir . '/schema.json', json_encode([
        'fields' => [
            ['name' => 'summary', 'label' => 'Summary', 'type' => 'text'],
            ['name' => 'featured', 'label' => 'Featured', 'type' => 'checkbox'],
        ],
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    file_put_contents($entriesDir . '/' . $entryId . '.json', json_encode([
        'id' => $entryId,
        'title' => $originalTitle,
        'slug' => 'phase-32-article-' . getmypid(),
        'summary' => 'Initial summary',
        'status' => 'unpublished',
        'version' => 1,
        'created_at' => '2026-07-21 20:00:00',
        'updated_at' => '2026-07-21 20:00:00',
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

    $createUser = birkly_test_http_request($base . '/api/onboarding.php?action=create_first_user', [
        'method' => 'POST',
        'headers' => ['Content-Type: application/json'],
        'body' => json_encode([
            'name' => 'Pillar E Admin',
            'email' => $adminEmail,
            'password' => $adminPassword,
        ]),
        'cookie_file' => $cookieFile,
    ]);
    test_assert('seed admin user', ($createUser['code'] ?? 0) === 200, $createUser['body'] ?? '');

    $loginGet = birkly_test_http_request($base . '/admin/login.php', ['cookie_file' => $cookieFile]);
    $loginCsrf = pillar_e_extract_login_csrf($loginGet['body'] ?? '');
    test_assert(
        'login form exposes csrf token or redirects authenticated session',
        $loginCsrf !== '' || in_array($loginGet['code'] ?? 0, [302, 303], true)
    );

    $loginPost = birkly_test_http_request($base . '/admin/login.php', [
        'method' => 'POST',
        'headers' => ['Content-Type: application/x-www-form-urlencoded'],
        'body' => http_build_query([
            'email' => $adminEmail,
            'password' => $adminPassword,
            '_csrf' => $loginCsrf,
        ]),
        'cookie_file' => $cookieFile,
    ]);
    test_assert('admin login succeeds', in_array($loginPost['code'] ?? 0, [302, 303], true), $loginPost['headers'] ?? '');

    $adminGet = birkly_test_http_request($base . '/admin/index.php?page=dashboard', ['cookie_file' => $cookieFile]);
    preg_match('/meta name="csrf-token" content="([^"]+)"/', $adminGet['body'] ?? '', $adminCsrfMatch);
    $apiCsrf = $adminCsrfMatch[1] ?? '';
    test_assert('dashboard request returns API csrf token', ($adminGet['code'] ?? 0) === 200 && $apiCsrf !== '');

    $duplicate = birkly_test_http_request($base . '/api/admin_entries.php?action=duplicate', [
        'method' => 'POST',
        'headers' => ['Content-Type: application/json'],
        'body' => json_encode([
            'collection' => $collectionSlug,
            'entry_id' => $entryId,
            '_csrf' => $apiCsrf,
        ]),
        'cookie_file' => $cookieFile,
    ]);
    $duplicateData = birkly_test_json_decode($duplicate['body'] ?? '');
    $duplicatedId = $duplicateData['data']['id'] ?? '';
    test_assert(
        'duplicate entry creates a copy',
        ($duplicate['code'] ?? 0) === 200
            && ($duplicateData['status'] ?? '') === 'ok'
            && $duplicatedId !== ''
            && ($duplicateData['data']['title'] ?? '') === $originalTitle . ' (copy)'
    );
    $duplicatedEntry = $duplicatedId !== '' && is_file($entriesDir . '/' . $duplicatedId . '.json')
        ? json_decode((string) file_get_contents($entriesDir . '/' . $duplicatedId . '.json'), true)
        : [];
    test_assert(
        'duplicate entry resets status and slug',
        is_array($duplicatedEntry)
            && ($duplicatedEntry['status'] ?? '') === 'unpublished'
            && str_contains((string) ($duplicatedEntry['slug'] ?? ''), 'copy')
    );

    $search = birkly_test_http_request($base . '/api/search.php?action=admin_search&q=' . rawurlencode('Phase 32') . '&limit=10', [
        'cookie_file' => $cookieFile,
    ]);
    $searchData = birkly_test_json_decode($search['body'] ?? '');
    $searchTypes = array_map(static function (array $item): string {
        return (string) ($item['type'] ?? '');
    }, $searchData['results'] ?? []);
    test_assert(
        'admin search returns collections and entries',
        ($search['code'] ?? 0) === 200
            && in_array('collection', $searchTypes, true)
            && in_array('entry', $searchTypes, true)
    );

    $trash = birkly_test_http_request($base . '/api/admin_entries.php?action=delete', [
        'method' => 'POST',
        'headers' => ['Content-Type: application/json'],
        'body' => json_encode([
            'collection' => $collectionSlug,
            'entry_id' => $entryId,
            '_csrf' => $apiCsrf,
        ]),
        'cookie_file' => $cookieFile,
    ]);
    $trashData = birkly_test_json_decode($trash['body'] ?? '');
    test_assert('delete action soft deletes entry', ($trashData['data']['status'] ?? '') === 'trashed');

    $trashedList = birkly_test_http_request($base . '/api/admin_entries.php?action=list&collection=' . rawurlencode($collectionSlug) . '&status=trashed', [
        'cookie_file' => $cookieFile,
    ]);
    $trashedListData = birkly_test_json_decode($trashedList['body'] ?? '');
    $trashedEntries = $trashedListData['entries'] ?? [];
    test_assert(
        'trashed list filters entries by trashed status',
        ($trashedList['code'] ?? 0) === 200
            && is_array($trashedEntries)
            && count($trashedEntries) === 1
            && (($trashedEntries[0]['id'] ?? '') === $entryId)
    );

    $restore = birkly_test_http_request($base . '/api/admin_entries.php?action=restore', [
        'method' => 'POST',
        'headers' => ['Content-Type: application/json'],
        'body' => json_encode([
            'collection' => $collectionSlug,
            'entry_id' => $entryId,
            '_csrf' => $apiCsrf,
        ]),
        'cookie_file' => $cookieFile,
    ]);
    $restoreData = birkly_test_json_decode($restore['body'] ?? '');
    test_assert('restore action untrashes entry', ($restoreData['data']['status'] ?? '') === 'unpublished');

    $bulkFieldEdit = birkly_test_http_request($base . '/api/admin_entries.php?action=bulk_field_edit', [
        'method' => 'POST',
        'headers' => ['Content-Type: application/json'],
        'body' => json_encode([
            'collection' => $collectionSlug,
            'entry_ids' => [$entryId],
            'field_name' => 'title',
            'value' => $updatedTitle,
            '_csrf' => $apiCsrf,
        ]),
        'cookie_file' => $cookieFile,
    ]);
    $bulkFieldEditData = birkly_test_json_decode($bulkFieldEdit['body'] ?? '');
    $editedEntry = json_decode((string) file_get_contents($entriesDir . '/' . $entryId . '.json'), true) ?: [];
    test_assert(
        'bulk field edit updates selected field',
        ($bulkFieldEdit['code'] ?? 0) === 200
            && ($bulkFieldEditData['status'] ?? '') === 'ok'
            && ($editedEntry['title'] ?? '') === $updatedTitle
    );

    $versions = birkly_test_http_request($base . '/api/admin_entries.php?action=list_versions&collection=' . rawurlencode($collectionSlug) . '&entry_id=' . rawurlencode($entryId), [
        'cookie_file' => $cookieFile,
    ]);
    $versionsData = birkly_test_json_decode($versions['body'] ?? '');
    $versionNumbers = array_map(static function (array $item): int {
        return (int) ($item['version'] ?? 0);
    }, $versionsData['versions'] ?? []);
    test_assert(
        'version history lists previous versions',
        ($versions['code'] ?? 0) === 200
            && in_array(1, $versionNumbers, true)
            && count($versionNumbers) >= 2
    );

    $restoreVersion = birkly_test_http_request($base . '/api/admin_entries.php?action=restore_version', [
        'method' => 'POST',
        'headers' => ['Content-Type: application/json'],
        'body' => json_encode([
            'collection' => $collectionSlug,
            'entry_id' => $entryId,
            'version' => 1,
            '_csrf' => $apiCsrf,
        ]),
        'cookie_file' => $cookieFile,
    ]);
    $restoreVersionData = birkly_test_json_decode($restoreVersion['body'] ?? '');
    $restoredEntry = json_decode((string) file_get_contents($entriesDir . '/' . $entryId . '.json'), true) ?: [];
    test_assert(
        'restore version resets entry data',
        ($restoreVersion['code'] ?? 0) === 200
            && ($restoreVersionData['status'] ?? '') === 'ok'
            && ($restoredEntry['title'] ?? '') === $originalTitle
    );

    $saveRedirect = birkly_test_http_request($base . '/api/redirects.php?action=save', [
        'method' => 'POST',
        'headers' => ['Content-Type: application/json'],
        'body' => json_encode([
            'from_path' => $redirectFromPath,
            'to_path' => $redirectToPath,
            'type' => 302,
            'enabled' => false,
            '_csrf' => $apiCsrf,
        ]),
        'cookie_file' => $cookieFile,
    ]);
    $saveRedirectData = birkly_test_json_decode($saveRedirect['body'] ?? '');
    $redirectId = $saveRedirectData['data']['redirect']['id'] ?? '';
    test_assert(
        'redirect save stores redirect record',
        ($saveRedirect['code'] ?? 0) === 200
            && $redirectId !== ''
            && is_file($redirectsFile)
    );

    $listRedirects = birkly_test_http_request($base . '/api/redirects.php?action=list', [
        'cookie_file' => $cookieFile,
    ]);
    $listRedirectsData = birkly_test_json_decode($listRedirects['body'] ?? '');
    $storedRedirects = $listRedirectsData['data']['redirects'] ?? [];
    test_assert(
        'redirect list exposes saved redirect',
        ($listRedirects['code'] ?? 0) === 200
            && is_array($storedRedirects)
            && count($storedRedirects) === 1
            && (($storedRedirects[0]['from_path'] ?? '') === $redirectFromPath)
    );

    $deleteRedirect = birkly_test_http_request($base . '/api/redirects.php?action=delete', [
        'method' => 'POST',
        'headers' => ['Content-Type: application/json'],
        'body' => json_encode([
            'id' => $redirectId,
            '_csrf' => $apiCsrf,
        ]),
        'cookie_file' => $cookieFile,
    ]);
    $deleteRedirectData = birkly_test_json_decode($deleteRedirect['body'] ?? '');
    $redirectsAfterDelete = json_decode((string) file_get_contents($redirectsFile), true) ?: [];
    test_assert(
        'redirect delete removes redirect record',
        ($deleteRedirect['code'] ?? 0) === 200
            && ($deleteRedirectData['status'] ?? '') === 'ok'
            && count($redirectsAfterDelete) === 0
    );
} finally {
    birkly_test_http_server_stop($serverPid);
    birkly_test_restore_directory($usersDir, $backupUsers);
    pillar_e_remove_tree($collectionDir);

    if (is_file($backupRedirects)) {
        copy($backupRedirects, $redirectsFile);
        @unlink($backupRedirects);
    } else {
        @unlink($redirectsFile);
    }

    @unlink($cookieFile);
}

exit(test_finish('pillar_e_api_test'));
