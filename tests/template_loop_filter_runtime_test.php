<?php
/**
 * P81-M4: runtime loop filters with user_current in template engine + SSR data.
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/core/birkly_template_engine.php';
require_once dirname(__DIR__) . '/core/storage/StorageFactory.php';
require_once dirname(__DIR__) . '/core/public_read.php';
require_once dirname(__DIR__) . '/core/project_ssr.php';
require_once dirname(__DIR__) . '/core/plugins/template_functions.php';

$failures = 0;
$tmpRoot = sys_get_temp_dir() . '/birkly-loop-filter-' . bin2hex(random_bytes(4));
$content = $tmpRoot . '/content';

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

function delete_dir(string $dir): void
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
            delete_dir($path);
        } else {
            @unlink($path);
        }
    }
    @rmdir($dir);
}

mkdir($content . '/collections/posts/entries', 0755, true);
file_put_contents($content . '/collections/posts/collection.json', json_encode([
    'slug' => 'posts',
    'title' => 'Posts',
    'allow_public_read' => true,
], JSON_PRETTY_PRINT));
file_put_contents($content . '/collections/posts/schema.json', json_encode([
    'fields' => [
        ['name' => 'title', 'type' => 'text'],
        ['name' => 'owner', 'type' => 'user', 'ownership' => true],
    ],
], JSON_PRETTY_PRINT));
file_put_contents($content . '/collections/posts/entries/a.json', json_encode([
    'id' => 'a', 'slug' => 'a', 'status' => 'published', 'title' => 'Mine', 'owner' => 'user-1',
], JSON_PRETTY_PRINT));
file_put_contents($content . '/collections/posts/entries/b.json', json_encode([
    'id' => 'b', 'slug' => 'b', 'status' => 'published', 'title' => 'Theirs', 'owner' => 'user-2',
], JSON_PRETTY_PRINT));

StorageFactory::reset();
$storage = StorageFactory::createStorage($content);

$posts = [
    ['id' => 'a', 'slug' => 'a', 'title' => 'Mine', 'owner' => 'user-1'],
    ['id' => 'b', 'slug' => 'b', 'title' => 'Theirs', 'owner' => 'user-2'],
];

$html = "{for each entry in 'posts' with owner = user_current}<li>{title}</li>{endfor}";
$guestRendered = birkly_template_engine_render($html, ['posts' => $posts]);
assert_true('guest owner filter yields empty loop output', $guestRendered === '', $guestRendered);

$userRendered = birkly_template_engine_render($html, [
    'posts' => $posts,
    'user_current' => ['id' => 'user-1', 'name' => 'Alice'],
]);
assert_true('logged-in owner filter keeps only current user posts', str_contains($userRendered, 'Mine') && !str_contains($userRendered, 'Theirs'), $userRendered);

$idFilterHtml = "{for each entry in 'posts' with 'owner' = user_current.id}<li>{title}</li>{endfor}";
$idRendered = birkly_template_engine_render($idFilterHtml, [
    'posts' => $posts,
    'user_current' => ['id' => 'user-1'],
]);
assert_true('user_current.id resolves in quoted field filter', str_contains($idRendered, 'Mine') && !str_contains($idRendered, 'Theirs'), $idRendered);

$_GET['scope'] = 'mine';
$GLOBALS['__p81_test_viewer'] = '';
// Inject user_current via global hook for scope=mine without plugin
$originalResolve = null;
// Use direct render data injection test for SSR instead
unset($_GET['scope']);

$ssrHtml = "{for each entry in 'posts' with owner = user_current}<span>{title}</span>{endfor}";
$ssrData = birkly_project_ssr_build_data($ssrHtml, [], 'en', $storage, [
    ['type' => 'list_entries', 'into' => 'posts', 'collection' => 'posts'],
]);
$ssrData['user_current'] = ['id' => 'user-1', 'name' => 'Alice'];
$ssrRendered = birkly_template_engine_render($ssrHtml, $ssrData);
assert_true('SSR fixture page filters posts by injected user_current', str_contains($ssrRendered, 'Mine') && !str_contains($ssrRendered, 'Theirs'), $ssrRendered);

register_template_function('user_current', static function () {
    return ['id' => 'user-1', 'name' => 'Alice'];
});
$listMine = birkly_public_list_entries($storage, 'posts', 'en', ['scope' => 'mine']);
$mineSlugs = array_map(static fn($e) => $e['slug'] ?? '', $listMine['data']['entries'] ?? []);
assert_true('scope=mine returns only owned entries when user_current resolves', $mineSlugs === ['a'], json_encode($mineSlugs));

delete_dir($tmpRoot);
StorageFactory::reset();

echo "\ntemplate_loop_filter_runtime_test: " . ($failures === 0 ? "PASS\n" : "FAIL ($failures)\n");
exit($failures === 0 ? 0 : 1);
