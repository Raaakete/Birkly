#!/usr/bin/env php
<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$manifestPath = $root . '/plugin.json';
$manifest = json_decode((string) file_get_contents($manifestPath), true);
if (!is_array($manifest)) {
    fwrite(STDERR, "Unable to read plugin.json\n");
    exit(1);
}

$slug = (string) ($manifest['marketplace_slug'] ?? 'birkly-automation');
$version = (string) ($manifest['version'] ?? '0.0.0');
$pluginDirName = 'automation';
$buildDir = $root . '/build';
$zipPath = $argv[1] ?? ($buildDir . '/' . $slug . '-' . $version . '.zip');

if (!extension_loaded('zip')) {
    fwrite(STDERR, "The PHP zip extension is required.\n");
    exit(1);
}

$requiredFiles = [
    'plugin.json',
    'plugin.php',
    'init.php',
    'README.md',
    'admin/automation.html',
    'admin/automation.css',
    'admin/automation.js',
    'admin/automation-editor.html',
    'admin/automation-editor.js',
    'admin/automation_node_editor.js',
    'admin/automation_node_serializer.js',
    'api/automation.php',
    'cli/process_queue.php',
    'includes/cron_auth.php',
];
foreach ($requiredFiles as $relative) {
    if (!is_file($root . '/' . $relative)) {
        fwrite(STDERR, "Required package file is missing: {$relative}\n");
        exit(1);
    }
}

if (!is_dir(dirname($zipPath))) {
    mkdir(dirname($zipPath), 0775, true);
}
if (is_file($zipPath)) {
    unlink($zipPath);
}

$excludeDirs = [
    '.git',
    'build',
    'cache',
    'logs',
    'settings',
    'tests',
    'users',
];
$excludeFiles = [
    'docs/RELEASE-READINESS-PLAN.md',
    'docs/RELEASE-READINESS-PROGRESS.md',
    'docs/WAVE3-RELEASE-EVIDENCE.md',
];

$zip = new ZipArchive();
if ($zip->open($zipPath, ZipArchive::CREATE) !== true) {
    fwrite(STDERR, "Unable to open zip for writing: {$zipPath}\n");
    exit(1);
}

$fileCount = 0;
$iterator = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS),
    RecursiveIteratorIterator::SELF_FIRST
);

foreach ($iterator as $item) {
    $relative = str_replace('\\', '/', $iterator->getSubPathName());
    if ($item->isLink()) {
        continue;
    }
    $segments = explode('/', $relative);
    if (array_intersect($segments, $excludeDirs)) {
        continue;
    }
    if (in_array($relative, $excludeFiles, true)) {
        continue;
    }
    if ($item->isDir()) {
        continue;
    }

    $zip->addFile($item->getPathname(), $pluginDirName . '/' . $relative);
    $fileCount++;
}

$zip->close();

$checksum = hash_file('sha256', $zipPath);
$metadata = [
    'status' => 'ok',
    'artifact' => $zipPath,
    'sha256' => $checksum,
    'plugin' => $pluginDirName,
    'marketplace_slug' => $slug,
    'version' => $version,
    'files' => $fileCount,
];

file_put_contents($zipPath . '.sha256', $checksum . '  ' . basename($zipPath) . PHP_EOL);
echo json_encode($metadata, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
