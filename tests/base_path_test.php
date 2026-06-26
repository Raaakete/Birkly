<?php
/**
 * Base path detection and URL helpers for subdirectory installs.
 */

require_once __DIR__ . '/../core/birkly_paths.php';

if (!function_exists('admin_url')) {
    function admin_url(string $page, array $params = []): string
    {
        return birkly_admin_url($page, $params);
    }
}

echo "Base path tests\n\n";

function test_assert(string $label, bool $ok, string $detail = ''): void
{
    echo $label . ': ' . ($ok ? 'PASS' : 'FAIL');
    if ($detail !== '') {
        echo " ($detail)";
    }
    echo "\n";
    if (!$ok) {
        exit(1);
    }
}

function with_script_name(string $script, callable $fn): void
{
    $prev = $_SERVER['SCRIPT_NAME'] ?? null;
    $_SERVER['SCRIPT_NAME'] = $script;
    birkly_paths_reset_cache();
    try {
        $fn();
    } finally {
        if ($prev === null) {
            unset($_SERVER['SCRIPT_NAME']);
        } else {
            $_SERVER['SCRIPT_NAME'] = $prev;
        }
        birkly_paths_reset_cache();
    }
}

with_script_name('/admin/index.php', function () {
    test_assert('root install base path empty', birkly_base_path() === '');
    test_assert('root admin_url', admin_url('content') === '/admin/index.php?page=content');
    test_assert('root birkly_url api', birkly_url('/api/foo.php') === '/api/foo.php');
});

with_script_name('/birkly-test/Birkly-0.1/admin/index.php', function () {
    test_assert(
        'subfolder base path',
        birkly_base_path() === '/birkly-test/Birkly-0.1',
        'got: ' . birkly_base_path()
    );
    test_assert(
        'subfolder admin_url content',
        admin_url('content') === '/birkly-test/Birkly-0.1/admin/index.php?page=content'
    );
    test_assert(
        'subfolder admin_url with params',
        admin_url('entries', ['collection' => 'blog']) === '/birkly-test/Birkly-0.1/admin/index.php?page=entries&collection=blog'
    );
    test_assert(
        'subfolder api url',
        birkly_url('/api/search.php') === '/birkly-test/Birkly-0.1/api/search.php'
    );
});

with_script_name('/birkly-test/Birkly-0.1/api/mcp_server.php', function () {
    test_assert(
        'api script detects same base',
        birkly_base_path() === '/birkly-test/Birkly-0.1'
    );
});

with_script_name('/Birkly-0.1/admin/index.php', function () {
    test_assert(
        'kekaba-style embed path',
        birkly_base_path() === '/Birkly-0.1'
    );
    test_assert(
        'embed admin_url',
        birkly_admin_url('dashboard') === '/Birkly-0.1/admin/index.php?page=dashboard'
    );
});

echo "\nbase_path_test: PASS\n";
