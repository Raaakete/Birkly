<?php
/**
 * ContentEditor project CSS preview helpers (shared by fieldtype + API).
 */

declare(strict_types=1);

require_once __DIR__ . '/project_site_filesystem.php';

/**
 * Normalize css_url to a project-relative path (e.g. css/style.css), or null when not project-local.
 */
function birkly_content_editor_normalize_preview_css_path(string $cssUrl): ?string
{
    $trimmed = trim($cssUrl);
    if ($trimmed === '') {
        return null;
    }

    if (preg_match('#^https?://#i', $trimmed)) {
        return null;
    }

    if (str_starts_with($trimmed, '/fieldtypes/')) {
        return null;
    }

    if (str_starts_with($trimmed, '/project/')) {
        $relative = ltrim(substr($trimmed, strlen('/project/')), '/');
        return $relative !== '' ? $relative : null;
    }

    if ($trimmed === '/project') {
        return null;
    }

    if (str_starts_with($trimmed, '/')) {
        return ltrim($trimmed, '/');
    }

    if (str_starts_with($trimmed, 'project/')) {
        $relative = substr($trimmed, strlen('project/'));
        return $relative !== '' ? $relative : null;
    }

    return $trimmed;
}

/**
 * @return array{path:string,content:string,size:int}|null
 */
function birkly_content_editor_read_preview_css_file(ProjectSiteFileSystem $fs, string $path): ?array
{
    $candidates = [$path];
    if (!str_contains($path, '/')) {
        $candidates[] = 'css/' . $path;
    }

    foreach ($candidates as $candidate) {
        $file = $fs->readFile($candidate);
        if ($file !== null) {
            return $file;
        }
    }

    return null;
}

function birkly_content_editor_preview_css_proxy_path(string $projectPath): string
{
    return '/api/content_editor_preview_css.php?path=' . rawurlencode($projectPath);
}
