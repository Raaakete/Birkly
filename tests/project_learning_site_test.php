<?php
/**
 * Learning project site (blank starter).
 */

require_once __DIR__ . '/../core/project_learning_site.php';
require_once __DIR__ . '/../core/demo_starter_templates.php';

function learning_site_assert(string $label, bool $ok): void
{
    if (!$ok) {
        fwrite(STDERR, "FAIL: {$label}\n");
        exit(1);
    }
    echo "OK: {$label}\n";
}

$tmp = sys_get_temp_dir() . '/birkly-learning-site-' . getmypid();
@mkdir($tmp, 0755, true);
$projectDir = $tmp . '/site-root';
@mkdir($projectDir . '/project', 0755, true);

$html = birkly_learning_site_index_html();
learning_site_assert('html has marker', str_contains($html, birkly_learning_site_marker()));
learning_site_assert('html has posts loop', str_contains($html, "'posts'"));
learning_site_assert('html has client script', str_contains($html, 'birkly-client.js'));

$result = birkly_ensure_learning_project_site($projectDir, false);
learning_site_assert('ensure creates index', ($result['ok'] ?? false) && ($result['created'] ?? false));
learning_site_assert('index exists', is_file($projectDir . '/project/index.html'));

$result2 = birkly_ensure_learning_project_site($projectDir, false);
learning_site_assert('ensure idempotent', ($result2['ok'] ?? false) && empty($result2['created']));

@unlink($projectDir . '/project/index.html');
file_put_contents($projectDir . '/project/custom.html', '<html></html>');
$result3 = birkly_ensure_learning_project_site($projectDir, false);
learning_site_assert('does not overwrite custom site', !($result3['created'] ?? false));

birkly_demo_remove_tree($projectDir);
echo "project_learning_site_test passed.\n";
