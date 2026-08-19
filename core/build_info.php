<?php
/**
 * Build / deploy diagnostics for verifying live installs match GitHub main.
 */

declare(strict_types=1);

/**
 * @return array<string, mixed>
 */
function birkly_build_info(): array
{
    $root = birkly_project_root();
    $contentTemplate = $root . '/admin/templates/content.html';
    $helpersFile = $root . '/api/admin_content_helpers.php';

    $gitCommit = null;
    $headFile = $root . '/.git/HEAD';
    if (is_readable($headFile)) {
        $head = trim((string) file_get_contents($headFile));
        if (str_starts_with($head, 'ref:')) {
            $ref = trim(substr($head, 4));
            $refFile = $root . '/.git/' . $ref;
            if (is_readable($refFile)) {
                $gitCommit = substr(trim((string) file_get_contents($refFile)), 0, 7);
            }
        } else {
            $gitCommit = substr($head, 0, 7);
        }
    }

    if ($gitCommit === null) {
        if (!function_exists('birkly_deploy_revision')) {
            require_once __DIR__ . '/birkly_data_paths.php';
        }
        $deployRev = birkly_deploy_revision();
        if (is_string($deployRev) && $deployRev !== '' && $deployRev !== 'unknown') {
            $gitCommit = substr($deployRev, 0, 40);
        }
    }

    $contentHead = is_readable($contentTemplate)
        ? substr(preg_replace('/\s+/', ' ', trim((string) file_get_contents($contentTemplate, false, null, 0, 120))), 0, 80)
        : null;
    $entriesTemplate = $root . '/admin/templates/entries.html';
    $entriesHead = is_readable($entriesTemplate)
        ? substr(preg_replace('/\s+/', ' ', trim((string) file_get_contents($entriesTemplate, false, null, 0, 120))), 0, 80)
        : null;

    $indexFile = $root . '/admin/index.php';
    $indexContent = is_readable($indexFile) ? (string) file_get_contents($indexFile) : '';
    $indexContentBypass = str_contains($indexContent, 'core/content_page.php')
        || str_contains($indexContent, 'birkly_load_content_page_data_safe')
        || str_contains($indexContent, 'never load admin_templates.php');

    $contentSmoke = ['status' => 'unknown'];
    $coreContent = $root . '/core/content_page.php';
    if (is_file($coreContent)) {
        try {
            require_once $coreContent;
            if (function_exists('birkly_load_content_page_data_safe')) {
                $smoke = birkly_load_content_page_data_safe();
                if (isset($smoke['error'])) {
                    $contentSmoke = [
                        'status' => 'error',
                        'message' => $smoke['error'],
                        'step' => $smoke['error_step'] ?? null,
                        'file' => $smoke['error_file'] ?? null,
                        'line' => $smoke['error_line'] ?? null,
                    ];
                } else {
                    $contentSmoke = [
                        'status' => 'ok',
                        'collections' => count($smoke['collections'] ?? []),
                        'loader' => $smoke['content_loader'] ?? null,
                        'fallback' => !empty($smoke['content_loader_fallback']),
                    ];
                }
            }
        } catch (Throwable $e) {
            $contentSmoke = [
                'status' => 'error',
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ];
        }
    } else {
        $contentSmoke = ['status' => 'error', 'message' => 'core/content_page.php missing'];
    }

    return [
        'cms_root' => $root,
        'version' => is_readable($root . '/VERSION') ? trim((string) file_get_contents($root . '/VERSION')) : null,
        'git_commit' => $gitCommit,
        'php_version' => PHP_VERSION,
        'base_path' => birkly_base_path(),
        'content_template_mode' => is_string($contentHead) && str_contains($contentHead, 'pure PHP template') ? 'php_direct' : 'legacy_or_unknown',
        'entries_template_mode' => is_string($entriesHead) && str_contains($entriesHead, 'pure PHP template') ? 'php_direct' : 'legacy_or_unknown',
        'index_content_bypass' => $indexContentBypass ? 'yes' : 'no',
        'content_smoke' => $contentSmoke,
        'content_template_head' => $contentHead,
        'entries_template_head' => $entriesHead,
        'content_template_mtime' => is_file($contentTemplate) ? date('c', (int) filemtime($contentTemplate)) : null,
        'admin_content_helpers' => is_file($helpersFile),
        'admin_topbar_partial' => is_file($root . '/admin/partials/admin-topbar.php'),
        'expected_pull_path' => 'Git repo root (contains admin/, api/, core/) — not admin/ folder alone',
    ];
}

/**
 * Build identity for MCP get_system_context — always returns both keys.
 *
 * @return array{git_commit: string, deployed_at: string}
 */
function birkly_mcp_system_build_info(): array
{
    if (!function_exists('birkly_project_root')) {
        require_once __DIR__ . '/birkly_paths.php';
    }
    if (!function_exists('birkly_deploy_revision')) {
        require_once __DIR__ . '/birkly_data_paths.php';
    }

    $build = birkly_build_info();
    $gitCommit = trim((string) ($build['git_commit'] ?? ''));
    if ($gitCommit === '') {
        $deployRev = trim((string) (birkly_deploy_revision() ?? ''));
        if ($deployRev !== '' && $deployRev !== 'unknown') {
            $gitCommit = substr($deployRev, 0, 40);
        }
    }
    if ($gitCommit === '') {
        $headFile = birkly_project_root() . '/.git/HEAD';
        if (is_readable($headFile)) {
            $head = trim((string) file_get_contents($headFile));
            if (str_starts_with($head, 'ref:')) {
                $refFile = birkly_project_root() . '/.git/' . trim(substr($head, 4));
                if (is_readable($refFile)) {
                    $gitCommit = substr(trim((string) file_get_contents($refFile)), 0, 40);
                }
            } elseif ($head !== '') {
                $gitCommit = substr($head, 0, 40);
            }
        }
    }

    $deployedAt = trim((string) (getenv('BIRKLY_DEPLOY_TIME') ?: ''));
    $revisionFile = birkly_project_root() . '/BUILD_REVISION';
    if ($deployedAt === '' && is_file($revisionFile)) {
        $deployedAt = date('c', (int) filemtime($revisionFile));
    }

    return [
        'git_commit' => $gitCommit !== '' ? $gitCommit : 'unknown',
        'deployed_at' => $deployedAt !== '' ? $deployedAt : 'unknown',
    ];
}

/**
 * Release id for cache busting: git commit, Docker build revision, or null.
 */
function birkly_release_identifier(): ?string
{
    $info = birkly_build_info();
    $commit = $info['git_commit'] ?? null;
    if (is_string($commit) && $commit !== '') {
        return $commit;
    }

    if (!function_exists('birkly_deploy_revision')) {
        require_once __DIR__ . '/birkly_data_paths.php';
    }
    $revision = birkly_deploy_revision();
    if (is_string($revision) && $revision !== '' && $revision !== 'unknown') {
        return $revision;
    }

    return null;
}

/**
 * Cache-bust token for admin JS/CSS. Docker images without .git use file mtime.
 */
function birkly_admin_asset_version(string $relativePath): string
{
    $release = birkly_release_identifier();
    if ($release !== null) {
        return $release;
    }

    $normalized = ltrim(str_replace('\\', '/', $relativePath), '/');
    $full = birkly_project_root() . '/' . $normalized;
    if (is_file($full)) {
        return (string) filemtime($full);
    }

    return '1';
}

/**
 * Admin static asset URL with a cache-busting query parameter.
 */
function birkly_admin_asset_url(string $relativePath): string
{
    if (!function_exists('birkly_url')) {
        require_once __DIR__ . '/birkly_paths.php';
    }

    $path = '/' . ltrim(str_replace('\\', '/', $relativePath), '/');
    return birkly_url($path) . '?v=' . rawurlencode(birkly_admin_asset_version(ltrim($path, '/')));
}

/**
 * Cache-bust token for public runtime assets (birkly-client.js, etc.).
 */
function birkly_public_runtime_asset_version(string $basename): string
{
    return birkly_admin_asset_version($basename);
}

/**
 * Public runtime asset URL with immutable cache-busting ?v= query.
 */
function birkly_public_runtime_asset_url(string $basename): string
{
    if (!function_exists('birkly_url')) {
        require_once __DIR__ . '/birkly_paths.php';
    }

    $basename = ltrim(str_replace('\\', '/', $basename), '/');
    return birkly_url('/' . $basename) . '?v=' . rawurlencode(birkly_public_runtime_asset_version($basename));
}
