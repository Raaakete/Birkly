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
