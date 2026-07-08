<?php
/**
 * Export Studio project as ZIP (P22-M10).
 */

require_once __DIR__ . '/StudioProjectRepository.php';
require_once __DIR__ . '/StudioCompiler.php';

class StudioExporter
{
    private StudioProjectRepository $repo;

    public function __construct(?StudioProjectRepository $repo = null)
    {
        $this->repo = $repo ?? new StudioProjectRepository();
    }

    public function syncWebsiteMirror(string $projectId): void
    {
        $project = $this->repo->getProject($projectId);
        if (!$project) {
            return;
        }
        $websiteDir = $this->repo->projectDir($projectId) . '/website';
        if (!is_dir($websiteDir)) {
            mkdir($websiteDir, 0755, true);
        }

        $compiler = new StudioCompiler($this->repo);
        foreach ($this->repo->listPageSlugs($projectId) as $slug) {
            $compiler->compilePage($projectId, $slug);
        }

        $stylesDir = $this->repo->projectDir($projectId) . '/styles';
        $kit = is_file($stylesDir . '/kit.css') ? file_get_contents($stylesDir . '/kit.css') : '';
        $custom = is_file($stylesDir . '/custom.css') ? file_get_contents($stylesDir . '/custom.css') : '';
        file_put_contents($websiteDir . '/styles.css', $kit . "\n" . $custom);

        $home = $project['home_page'] ?? 'index';
        foreach ($this->repo->listPageSlugs($projectId) as $slug) {
            $html = $this->repo->getPageHtml($projectId, $slug);
            if ($html === null) {
                continue;
            }
            $html = $this->rewriteForExport($html, $project['slug'] ?? $projectId);
            $filename = ($slug === $home) ? 'index.html' : $slug . '.html';
            file_put_contents($websiteDir . '/' . $filename, $html);
        }

        $readme = $this->buildReadme($project);
        file_put_contents($websiteDir . '/README.md', $readme);
    }

    public function exportZip(string $projectId): array
    {
        if (!class_exists('ZipArchive')) {
            return ['ok' => false, 'error' => 'ZipArchive extension not available'];
        }

        $project = $this->repo->getProject($projectId);
        if (!$project) {
            return ['ok' => false, 'error' => 'Project not found'];
        }

        $this->syncWebsiteMirror($projectId);
        $websiteDir = $this->repo->projectDir($projectId) . '/website';
        $slug = $project['slug'] ?? $projectId;
        $zipPath = sys_get_temp_dir() . '/studio-' . $slug . '-' . time() . '.zip';

        $zip = new ZipArchive();
        if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            return ['ok' => false, 'error' => 'Failed to create ZIP'];
        }

        $this->addDirToZip($zip, $websiteDir, 'website');
        $zip->close();

        return ['ok' => true, 'path' => $zipPath, 'filename' => $slug . '-studio-export.zip'];
    }

    private function addDirToZip(ZipArchive $zip, string $dir, string $prefix): void
    {
        foreach (scandir($dir) ?: [] as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            $path = $dir . '/' . $item;
            $name = $prefix . '/' . $item;
            if (is_dir($path)) {
                $this->addDirToZip($zip, $path, $name);
            } else {
                $zip->addFile($path, $name);
            }
        }
    }

    private function rewriteForExport(string $html, string $projectSlug): string
    {
        $html = preg_replace(
            '#href="[^"]*/site/' . preg_quote($projectSlug, '#') . '/styles\.css[^"]*"#',
            'href="styles.css"',
            $html
        ) ?? $html;

        $clientTag = '<script src="/birkly-client.js" data-cms-url="' . (function_exists('birkly_url') ? birkly_url('') : '') . '"></script>';
        if (!str_contains($html, 'birkly-client.js')) {
            $html = str_replace('</body>', $clientTag . "\n</body>", $html);
        }
        return $html;
    }

    private function buildReadme(array $project): string
    {
        $name = $project['name'] ?? 'Studio project';
        return <<<MD
# {$name} — Studio export

Exported from Birkly Studio. The in-CMS copy at `content/studio-projects/` remains the source of truth.

## Files

- `website/index.html` — home page (compiled HTML + Birkly template tags)
- `website/styles.css` — kit + custom CSS
- Optional: `birkly-client.js` script tag for live client rendering

## SSR

Published sites are served at `/site/{project-slug}/` when the Studio plugin is active.

MD;
    }
}
