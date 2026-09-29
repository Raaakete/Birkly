<?php
/**
 * P102-M2 — components API requires an admin content session.
 * Mutations also require the existing CSRF token. MCP function-only loads stay open.
 */

declare(strict_types=1);

$root = dirname(__DIR__);
$errors = [];
$slug = 'p102-auth-probe';
$componentDir = $root . '/content/components/' . $slug;

function p102_components_fail(string $message, array &$errors): void
{
    $errors[] = $message;
}

function p102_components_run(string $root, string $mode, array $query, string $body = ''): string
{
    $runner = tempnam(sys_get_temp_dir(), 'p102comp');
    if ($runner === false) {
        throw new RuntimeException('temp runner failed');
    }
    $script = $runner . '.php';
    rename($runner, $script);
    $queryExport = var_export($query, true);
    $modeExport = var_export($mode, true);
    $rootExport = var_export($root, true);
    $bodyExport = var_export($body, true);
    file_put_contents($script, <<<PHP
<?php
\$root = {$rootExport};
\$mode = {$modeExport};
\$_GET = {$queryExport};
\$_POST = [];
\$_SERVER['REQUEST_METHOD'] = 'POST';
\$GLOBALS['__components_test_raw_body'] = {$bodyExport};
if (\$mode === 'helpers') {
    \$GLOBALS['__CMS_MCP_LOAD_FUNCTIONS_ONLY'] = true;
    require \$root . '/api/components.php';
    echo function_exists('findEntriesUsingComponent') ? 'helpers-ok' : 'helpers-missing';
    echo ' still-running';
    return;
}
require_once \$root . '/api/session.php';
haru_secure_session_start();
if (\$mode === 'editor') {
    \$_SESSION['user'] = [
        'id' => 'p102-editor',
        'role' => 'editor',
        'permissions' => ['manage_content'],
    ];
    \$_SESSION['csrf_token'] = 'p102-csrf';
    \$_SESSION['last_activity'] = time();
} elseif (\$mode === 'viewer') {
    \$_SESSION['user'] = [
        'id' => 'p102-viewer',
        'role' => 'viewer',
        'permissions' => ['read'],
    ];
    \$_SESSION['csrf_token'] = 'p102-csrf';
    \$_SESSION['last_activity'] = time();
}
require \$root . '/api/components.php';
PHP);
    $descriptors = [
        0 => ['pipe', 'r'],
        1 => ['pipe', 'w'],
        2 => ['pipe', 'w'],
    ];
    $process = proc_open([PHP_BINARY, $script], $descriptors, $pipes, $root);
    if (!is_resource($process)) {
        @unlink($script);
        throw new RuntimeException('proc_open failed');
    }
    fwrite($pipes[0], $body);
    fclose($pipes[0]);
    $stdout = stream_get_contents($pipes[1]);
    fclose($pipes[1]);
    $stderr = stream_get_contents($pipes[2]);
    fclose($pipes[2]);
    proc_close($process);
    @unlink($script);
    if (is_string($stderr) && trim($stderr) !== '') {
        $stdout .= "\n" . $stderr;
    }
    return is_string($stdout) ? $stdout : '';
}

try {
    $js = (string) file_get_contents($root . '/fieldtypes/ContentEditor/core/component-registry.js');
    if (!str_contains($js, 'meta[name="csrf-token"]') || !str_contains($js, '_csrf: this.csrfToken()')) {
        p102_components_fail('component registry client must send the admin CSRF meta token', $errors);
    }

    $helpers = p102_components_run($root, 'helpers', []);
    if (!str_contains($helpers, 'helpers-ok') || !str_contains($helpers, 'still-running')) {
        p102_components_fail('MCP function-only load must keep component helpers without a session: ' . $helpers, $errors);
    }
    if (str_contains($helpers, 'Unauthorized')) {
        p102_components_fail('MCP function-only load must not require a session', $errors);
    }

    $anon = p102_components_run($root, 'anon', ['action' => 'list']);
    if (!str_contains($anon, 'Unauthorized')) {
        p102_components_fail('anonymous list must be unauthorized: ' . $anon, $errors);
    }

    $anonRender = p102_components_run($root, 'anon', ['action' => 'render', 'slug' => 'callout']);
    if (!str_contains($anonRender, 'Unauthorized')) {
        p102_components_fail('anonymous render must be unauthorized: ' . $anonRender, $errors);
    }

    $viewer = p102_components_run($root, 'viewer', ['action' => 'list']);
    if (!str_contains($viewer, 'Forbidden')) {
        p102_components_fail('viewer without manage_content must be forbidden: ' . $viewer, $errors);
    }

    $list = p102_components_run($root, 'editor', ['action' => 'list']);
    $listJson = json_decode($list, true);
    if (!is_array($listJson) || ($listJson['success'] ?? false) !== true || !is_array($listJson['components'] ?? null)) {
        p102_components_fail('editor list should still return components: ' . $list, $errors);
    } else {
        $slugs = array_column($listJson['components'], 'slug');
        if (!in_array('callout', $slugs, true)) {
            p102_components_fail('editor list should include the bundled callout component', $errors);
        }
    }

    $render = p102_components_run($root, 'editor', ['action' => 'render', 'slug' => 'callout']);
    $renderJson = json_decode($render, true);
    if (!is_array($renderJson) || ($renderJson['success'] ?? false) !== true) {
        p102_components_fail('editor render should still return the component: ' . $render, $errors);
    }

    if (is_dir($componentDir)) {
        p102_components_fail('probe component directory already exists', $errors);
    }

    $saveDenied = p102_components_run($root, 'editor', ['action' => 'save'], json_encode([
        'slug' => $slug,
        'name' => 'P102 Probe',
        'template' => '<p>probe</p>',
    ], JSON_THROW_ON_ERROR));
    if (!str_contains($saveDenied, 'Invalid CSRF token') || is_dir($componentDir)) {
        p102_components_fail('save without CSRF must be rejected and must not write: ' . $saveDenied, $errors);
    }

    $save = p102_components_run($root, 'editor', ['action' => 'save'], json_encode([
        'slug' => $slug,
        'name' => 'P102 Probe',
        'template' => '<p>probe</p>',
        '_csrf' => 'p102-csrf',
    ], JSON_THROW_ON_ERROR));
    $saveJson = json_decode($save, true);
    if (!is_array($saveJson) || ($saveJson['success'] ?? false) !== true || !is_dir($componentDir)) {
        p102_components_fail('editor save with CSRF should create the component: ' . $save, $errors);
    }

    $get = p102_components_run($root, 'editor', ['action' => 'get', 'slug' => $slug]);
    $getJson = json_decode($get, true);
    if (!is_array($getJson) || ($getJson['component']['name'] ?? '') !== 'P102 Probe') {
        p102_components_fail('editor get should return the saved component: ' . $get, $errors);
    }

    $delete = p102_components_run($root, 'editor', ['action' => 'delete', 'slug' => $slug], json_encode([
        'slug' => $slug,
        '_csrf' => 'p102-csrf',
    ], JSON_THROW_ON_ERROR));
    $deleteJson = json_decode($delete, true);
    if (!is_array($deleteJson) || ($deleteJson['success'] ?? false) !== true || is_dir($componentDir)) {
        p102_components_fail('editor delete with CSRF should remove the component: ' . $delete, $errors);
    }
} catch (Throwable $e) {
    $errors[] = $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine();
} finally {
    if (is_dir($componentDir)) {
        foreach (glob($componentDir . '/*') ?: [] as $file) {
            if (is_file($file)) {
                unlink($file);
            }
        }
        rmdir($componentDir);
    }
}

if ($errors !== []) {
    fwrite(STDERR, implode("\n", $errors) . "\n");
    exit(1);
}

echo "p102_components_auth_test ok\n";
