<?php
/**
 * J-ADMIN — Content editor (create + publish entry via API).
 */

require_once __DIR__ . '/_helpers.php';

return [
    'id' => 'J-ADMIN',
    'name' => 'Content editor',
    'server' => 'dev',
    'run' => function (array $ctx): void {
        $base = $ctx['base_url'];
        $root = $ctx['repo_root'];
        journey_install_reference_project('blog');

        $usersDir = $root . '/users';
        $settingsDir = $root . '/settings';
        $backupUsers = sys_get_temp_dir() . '/birkly-journey-users-' . getmypid();
        $backupSettings = sys_get_temp_dir() . '/birkly-journey-settings-' . getmypid();
        $cookieFile = journey_temp_cookie_file('admin');
        $email = 'journey-admin-' . getmypid() . '@birkly.test';
        $password = 'SecurePass123!';
        $title = 'Journey Admin Post ' . getmypid();

        try {
            birkly_test_backup_directory($usersDir, $backupUsers);
            birkly_test_backup_directory($settingsDir, $backupSettings);
            birkly_test_clear_json_files($usersDir);
            $adminSettingsPath = $settingsDir . '/admin.json';
            if (is_file($adminSettingsPath)) {
                unlink($adminSettingsPath);
            }

            journey_create_first_admin($base, $cookieFile, $email, $password);
            journey_onboarding_skip($base, $cookieFile);
            $csrf = journey_admin_csrf($base, $cookieFile);

            $collections = journey_http($base, '/api/content.php?action=list_collections', [
                'cookie_file' => $cookieFile,
            ]);
            $collectionsData = birkly_test_json_decode($collections['body']);
            $blogCollectionId = null;
            foreach ($collectionsData['data']['collections'] ?? [] as $col) {
                if (($col['slug'] ?? '') === 'blog') {
                    $blogCollectionId = $col['id'] ?? null;
                    break;
                }
            }
            journey_assert('blog collection found', $blogCollectionId !== null);

            $create = journey_http($base, '/api/content.php?action=create_entry', [
                'method' => 'POST',
                'headers' => ['Content-Type: application/json'],
                'body' => json_encode([
                    'action' => 'create_entry',
                    'collection' => $blogCollectionId,
                    'data' => [
                        'title' => $title,
                        'content' => '<p>Journey admin draft content.</p>',
                    ],
                    '_csrf' => $csrf,
                ]),
                'cookie_file' => $cookieFile,
            ]);
            $createData = birkly_test_json_decode($create['body']);
            journey_assert('create entry HTTP 200', $create['code'] === 200, $create['body']);
            journey_assert('create entry ok', ($createData['status'] ?? '') === 'ok', $create['body']);
            $entryId = (string) ($createData['data']['id'] ?? '');
            $slug = (string) ($createData['data']['slug'] ?? '');
            journey_assert('entry id returned', $entryId !== '');
            journey_assert('entry slug returned', $slug !== '');

            $publish = journey_http($base, '/api/content.php?action=update_entry', [
                'method' => 'POST',
                'headers' => ['Content-Type: application/json'],
                'body' => json_encode([
                    'action' => 'update_entry',
                    'collection' => $blogCollectionId,
                    'id' => $entryId,
                    'data' => [
                        'title' => $title,
                        'content' => '<p>Journey admin published content.</p>',
                        'status' => 'published',
                    ],
                    '_csrf' => $csrf,
                ]),
                'cookie_file' => $cookieFile,
            ]);
            $publishData = birkly_test_json_decode($publish['body']);
            journey_assert('publish entry ok', ($publishData['status'] ?? '') === 'ok', $publish['body']);

            $public = journey_http($base, '/api/public.php?action=get_entry&collection=blog&slug=' . rawurlencode($slug));
            $publicData = birkly_test_json_decode($public['body']);
            journey_assert('public get_entry HTTP 200', $public['code'] === 200);
            journey_assert('public entry slug matches', ($publicData['data']['entry']['slug'] ?? '') === $slug);
        } finally {
            birkly_test_restore_directory($usersDir, $backupUsers);
            birkly_test_restore_directory($settingsDir, $backupSettings);
            birkly_test_remove_directory($backupUsers);
            birkly_test_remove_directory($backupSettings);
            @unlink($cookieFile);
        }
    },
];
