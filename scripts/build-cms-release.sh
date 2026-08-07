#!/usr/bin/env bash
# Build CMS release zip using scripts/cms-release-manifest.json (P10-M1).
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
MANIFEST="$ROOT/scripts/cms-release-manifest.json"
OUT="${OUT:-/tmp/birkly-release.zip}"

if [[ ! -f "$MANIFEST" ]]; then
  echo "[build-cms-release] FAIL: missing manifest $MANIFEST" >&2
  exit 1
fi

STAGING="$(mktemp -d)"
trap 'rm -rf "$STAGING"' EXIT

export ROOT STAGING MANIFEST OUT
php <<'PHP'
<?php
$root = getenv('ROOT');
$staging = getenv('STAGING');
$manifestPath = getenv('MANIFEST');
$out = getenv('OUT') ?: '/tmp/birkly-release.zip';

$manifest = json_decode(file_get_contents($manifestPath), true);
if (!is_array($manifest)) {
    fwrite(STDERR, "[build-cms-release] FAIL: invalid manifest JSON\n");
    exit(1);
}

$excludes = $manifest['excludes'] ?? [];
$shippedPluginFiles = $manifest['plugins_dir_shipped_files'] ?? ['plugins/README.md', 'plugins/.gitkeep'];
$runtimeSkeletonFiles = $manifest['runtime_skeleton_files'] ?? [];

$excludeRules = [];
$includeExceptions = [];
foreach ($excludes as $rule) {
    if (str_starts_with($rule, '!')) {
        $includeExceptions[] = substr($rule, 1);
    } else {
        $excludeRules[] = $rule;
    }
}

$relMatchesPattern = function (string $rel, string $pattern): bool {
    $rel = str_replace('\\', '/', $rel);
    $pattern = str_replace('\\', '/', $pattern);
    if (str_ends_with($pattern, '/**')) {
        $prefix = substr($pattern, 0, -3);
        return $rel === $prefix || str_starts_with($rel, $prefix . '/');
    }
    if (str_ends_with($pattern, '/*')) {
        $prefix = substr($pattern, 0, -2);
        if ($rel === $prefix) {
            return true;
        }
        if (!str_starts_with($rel, $prefix . '/')) {
            return false;
        }
        $rest = substr($rel, strlen($prefix) + 1);
        return strpos($rest, '/') === false;
    }
    if (str_ends_with($pattern, '/')) {
        return str_starts_with($rel, $pattern);
    }
    return fnmatch($pattern, $rel) || fnmatch($pattern, basename($rel));
};

$isExcluded = function (string $rel) use ($excludeRules, $includeExceptions, $relMatchesPattern): bool {
    foreach ($includeExceptions as $exception) {
        if ($relMatchesPattern($rel, $exception)) {
            return false;
        }
    }
    foreach ($excludeRules as $rule) {
        if ($relMatchesPattern($rel, $rule)) {
            return true;
        }
    }
    return false;
};

$iterator = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS),
    RecursiveIteratorIterator::SELF_FIRST
);

foreach ($iterator as $item) {
    /** @var SplFileInfo $item */
    $path = $item->getPathname();
    $rel = substr($path, strlen($root) + 1);
    $rel = str_replace('\\', '/', $rel);

    if (str_starts_with($rel, 'plugins/') || $rel === 'plugins') {
        if ($item->isFile()) {
            $allowed = in_array($rel, $shippedPluginFiles, true);
            if (!$allowed) {
                continue;
            }
        } else {
            if ($rel !== 'plugins' && !in_array($rel . '/README.md', $shippedPluginFiles, true)) {
                continue;
            }
        }
    } elseif ($isExcluded($rel)) {
        continue;
    }

    $dest = $staging . '/' . $rel;
    if ($item->isDir()) {
        if (!is_dir($dest)) {
            mkdir($dest, 0755, true);
        }
    } else {
        $destDir = dirname($dest);
        if (!is_dir($destDir)) {
            mkdir($destDir, 0755, true);
        }
        copy($path, $dest);
    }
}

foreach ($shippedPluginFiles as $rel) {
    $src = $root . '/' . $rel;
    if (!is_file($src)) {
        continue;
    }
    $dest = $staging . '/' . $rel;
    $destDir = dirname($dest);
    if (!is_dir($destDir)) {
        mkdir($destDir, 0755, true);
    }
    if (!is_file($dest)) {
        copy($src, $dest);
    }
}

foreach ($runtimeSkeletonFiles as $rel) {
    $dest = $staging . '/' . $rel;
    $destDir = dirname($dest);
    if (!is_dir($destDir)) {
        mkdir($destDir, 0755, true);
    }
    $src = $root . '/' . $rel;
    if (is_file($src)) {
        if (!is_file($dest)) {
            copy($src, $dest);
        }
    } elseif (!is_file($dest)) {
        touch($dest);
    }
}

$zip = new ZipArchive();
if ($zip->open($out, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
    fwrite(STDERR, "[build-cms-release] FAIL: cannot create $out\n");
    exit(1);
}

$stageIterator = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($staging, FilesystemIterator::SKIP_DOTS),
    RecursiveIteratorIterator::SELF_FIRST
);

foreach ($stageIterator as $file) {
    /** @var SplFileInfo $file */
    $path = $file->getPathname();
    $rel = substr($path, strlen($staging) + 1);
    $rel = str_replace('\\', '/', $rel);
    if ($file->isDir()) {
        $zip->addEmptyDir($rel);
    } else {
        $zip->addFile($path, $rel);
    }
}

$zip->close();
echo "[build-cms-release] wrote $out\n";
PHP

echo "[build-cms-release] OK"
