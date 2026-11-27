<?php

declare(strict_types=1);

/**
 * Resolve a storage-relative path under basePath; refuse ".." and paths outside the base.
 *
 * @return string|null Absolute filesystem path, or null when the relative path is unsafe
 */
function birkly_storage_resolve_relative_path(string $basePath, string $relativePath): ?string
{
    $relativePath = str_replace('\\', '/', trim($relativePath));
    $relativePath = ltrim($relativePath, '/');
    if ($relativePath === '') {
        return null;
    }
    if (str_contains($relativePath, '..')) {
        return null;
    }

    $basePath = rtrim(str_replace('\\', '/', $basePath), '/');
    $baseReal = realpath($basePath);
    if ($baseReal === false) {
        if (!is_dir($basePath) && !@mkdir($basePath, 0755, true)) {
            return null;
        }
        $baseReal = realpath($basePath);
        if ($baseReal === false) {
            return null;
        }
    }

    $parts = array_values(array_filter(explode('/', $relativePath), static fn (string $p): bool => $p !== ''));
    $resolved = $baseReal;
    foreach ($parts as $part) {
        if ($part === '.' || $part === '..') {
            return null;
        }
        $resolved .= '/' . $part;
    }

    $prefix = $baseReal . DIRECTORY_SEPARATOR;
    $normalizedResolved = str_replace('\\', '/', $resolved);
    $normalizedBase = str_replace('\\', '/', $baseReal);
    if ($normalizedResolved !== $normalizedBase && !str_starts_with($normalizedResolved . '/', $normalizedBase . '/')) {
        return null;
    }

    return $resolved;
}