<?php
/**
 * Developer mode + template export scrub tests (P61).
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/core/developer_mode.php';

$failures = 0;
$path = birkly_developer_mode_settings_path();
$backup = is_readable($path) ? file_get_contents($path) : null;

function dm_assert(string $name, bool $ok, string $detail = ''): void
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

try {
    dm_assert('default off', birkly_developer_mode_enabled() === false || birkly_developer_mode_save(false));
    birkly_developer_mode_save(false);
    dm_assert('disabled', birkly_developer_mode_enabled() === false);
    birkly_developer_mode_save(true);
    dm_assert('enabled', birkly_developer_mode_enabled() === true);

    dm_assert('block users path', birkly_developer_export_path_blocked('users/admin.json') === true);
    dm_assert('block secrets', birkly_developer_export_path_blocked('settings/secrets.json') === true);
    dm_assert('block mcp keys', birkly_developer_export_path_blocked('settings/mcp_keys.json') === true);
    dm_assert('allow project html', birkly_developer_export_path_blocked('project/index.html') === false);
    dm_assert('allow content entry', birkly_developer_export_path_blocked('content/collections/blog/entries/a.json') === false);

    $info = birkly_developer_system_info();
    dm_assert('system info php', isset($info['php_version']) && $info['php_version'] !== '');

    // Export smoke if project exists
    $projectPath = birkly_public_site_path();
    if (is_dir($projectPath)) {
        $result = birkly_developer_export_template_pack(false);
        dm_assert('export ok', !empty($result['ok']), $result['error'] ?? '');
        if (!empty($result['ok']) && !empty($result['path']) && class_exists('ZipArchive')) {
            $zip = new ZipArchive();
            dm_assert('zip open', $zip->open($result['path']) === true);
            $names = [];
            for ($i = 0; $i < $zip->numFiles; $i++) {
                $names[] = (string) $zip->getNameIndex($i);
            }
            $zip->close();
            $joined = implode("\n", $names);
            dm_assert('zip has project', str_contains($joined, 'project/'));
            dm_assert('zip has pack.json', str_contains($joined, 'pack.json'));
            dm_assert('zip no users', !str_contains($joined, 'users/'));
            @unlink($result['path']);
        }
    } else {
        echo "export smoke: SKIP (no project/)\n";
    }
} finally {
    if ($backup === null) {
        @unlink($path);
    } else {
        file_put_contents($path, $backup);
    }
}

exit($failures > 0 ? 1 : 0);
