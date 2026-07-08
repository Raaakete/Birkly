<?php
/**
 * Studio project + page storage (P22-M2).
 */

require_once __DIR__ . '/../init.php';

class StudioProjectRepository
{
    private string $root;

    public function __construct(?string $root = null)
    {
        $this->root = $root ?? studio_content_root();
        if (!is_dir($this->root)) {
            mkdir($this->root, 0755, true);
        }
    }

    public function listProjects(): array
    {
        $projects = [];
        foreach (scandir($this->root) ?: [] as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }
            $path = $this->root . '/' . $entry;
            if (!is_dir($path)) {
                continue;
            }
            $meta = $this->readProjectJson($entry);
            if ($meta) {
                $meta['id'] = $entry;
                $projects[] = $meta;
            }
        }
        usort($projects, static fn($a, $b) => strcmp($b['updated_at'] ?? '', $a['updated_at'] ?? ''));
        return $projects;
    }

    public function getProject(string $id): ?array
    {
        $meta = $this->readProjectJson($id);
        if (!$meta) {
            return null;
        }
        $meta['id'] = $id;
        $meta['pages'] = $this->listPages($id);
        return $meta;
    }

    public function findBySlug(string $slug): ?array
    {
        foreach ($this->listProjects() as $project) {
            if (($project['slug'] ?? '') === $slug) {
                return $this->getProject($project['id']);
            }
        }
        return null;
    }

    public function createProject(string $name, string $styleKit = 'minimal', ?string $slug = null): array
    {
        $kits = studio_style_kits();
        if (!isset($kits[$styleKit])) {
            $styleKit = 'minimal';
        }
        $id = studio_new_id();
        $slug = $slug !== null && $slug !== '' ? studio_slugify($slug) : studio_slugify($name);
        $slug = $this->ensureUniqueSlug($slug, null);

        $dir = $this->projectDir($id);
        mkdir($dir . '/styles', 0755, true);
        mkdir($dir . '/pages', 0755, true);
        mkdir($dir . '/website', 0755, true);
        mkdir($dir . '/components', 0755, true);

        $kitPath = studio_kit_css_path($styleKit);
        if ($kitPath) {
            copy($kitPath, $dir . '/styles/kit.css');
        } else {
            file_put_contents($dir . '/styles/kit.css', "/* studio kit */\n");
        }
        file_put_contents($dir . '/styles/custom.css', "/* custom rules */\n");

        file_put_contents($dir . '/tokens.json', json_encode([
            'colors' => ['primary' => '#0d6efd', 'text' => '#1a1a1a'],
            'spacing' => ['sm' => '0.5rem', 'md' => '1rem', 'lg' => '2rem'],
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n");

        $now = date('c');
        $meta = [
            'name' => $name,
            'slug' => $slug,
            'style_kit' => $styleKit,
            'published' => false,
            'revision' => 1,
            'home_page' => 'index',
            'created_at' => $now,
            'updated_at' => $now,
        ];
        $this->writeProjectJson($id, $meta);

        $canvas = $this->defaultCanvas('index', 'Home');
        $this->savePageCanvas($id, 'index', $canvas, false);

        return $this->getProject($id);
    }

    public function updateProject(string $id, array $patch): ?array
    {
        $meta = $this->readProjectJson($id);
        if (!$meta) {
            return null;
        }
        foreach (['name', 'style_kit', 'home_page'] as $key) {
            if (array_key_exists($key, $patch)) {
                $meta[$key] = $patch[$key];
            }
        }
        if (isset($patch['slug'])) {
            $meta['slug'] = $this->ensureUniqueSlug(studio_slugify((string) $patch['slug']), $id);
        }
        $meta['updated_at'] = date('c');
        $this->writeProjectJson($id, $meta);
        return $this->getProject($id);
    }

    public function deleteProject(string $id): bool
    {
        $dir = $this->projectDir($id);
        if (!is_dir($dir)) {
            return false;
        }
        $this->removeDir($dir);
        return true;
    }

    public function duplicateProject(string $id, string $newName): ?array
    {
        $src = $this->projectDir($id);
        if (!is_dir($src)) {
            return null;
        }
        $newId = studio_new_id();
        $dest = $this->projectDir($newId);
        $this->copyDir($src, $dest);
        $meta = $this->readProjectJson($newId);
        if (!$meta) {
            return null;
        }
        $meta['name'] = $newName;
        $meta['slug'] = $this->ensureUniqueSlug(studio_slugify($newName), $newId);
        $meta['published'] = false;
        $meta['revision'] = 1;
        $meta['created_at'] = date('c');
        $meta['updated_at'] = date('c');
        $this->writeProjectJson($newId, $meta);
        return $this->getProject($newId);
    }

    public function publishProject(string $id): ?array
    {
        $meta = $this->readProjectJson($id);
        if (!$meta) {
            return null;
        }
        require_once __DIR__ . '/StudioCompiler.php';
        $compiler = new StudioCompiler($this);
        foreach ($this->listPageSlugs($id) as $slug) {
            $compiler->compilePage($id, $slug);
        }
        require_once __DIR__ . '/StudioExporter.php';
        $exporter = new StudioExporter($this);
        $exporter->syncWebsiteMirror($id);

        $meta['published'] = true;
        $meta['revision'] = (int) ($meta['revision'] ?? 0) + 1;
        $meta['updated_at'] = date('c');
        $this->writeProjectJson($id, $meta);
        return $this->getProject($id);
    }

    public function listPages(string $projectId): array
    {
        $pagesDir = $this->projectDir($projectId) . '/pages';
        if (!is_dir($pagesDir)) {
            return [];
        }
        $pages = [];
        foreach (scandir($pagesDir) ?: [] as $file) {
            if (!str_ends_with($file, '.studio.json')) {
                continue;
            }
            $slug = substr($file, 0, -strlen('.studio.json'));
            $canvas = $this->readJson($pagesDir . '/' . $file);
            $pages[] = [
                'slug' => $slug,
                'title' => $canvas['page']['title'] ?? ucfirst($slug),
                'sort' => (int) ($canvas['page']['sort'] ?? 0),
            ];
        }
        usort($pages, static fn($a, $b) => ($a['sort'] <=> $b['sort']) ?: strcmp($a['slug'], $b['slug']));
        return $pages;
    }

    public function listPageSlugs(string $projectId): array
    {
        return array_map(static fn($p) => $p['slug'], $this->listPages($projectId));
    }

    public function getPageCanvas(string $projectId, string $pageSlug): ?array
    {
        $path = $this->pageCanvasPath($projectId, $pageSlug);
        if (!is_file($path)) {
            return null;
        }
        return $this->readJson($path);
    }

    public function getPageHtml(string $projectId, string $pageSlug): ?string
    {
        $path = $this->pageHtmlPath($projectId, $pageSlug);
        if (!is_file($path)) {
            return null;
        }
        $html = file_get_contents($path);
        return $html === false ? null : $html;
    }

    public function savePageCanvas(string $projectId, string $pageSlug, array $canvas, bool $compile = true): ?array
    {
        if (!$this->readProjectJson($projectId)) {
            return null;
        }
        $canvas['page']['slug'] = $pageSlug;
        if (!isset($canvas['version'])) {
            $canvas['version'] = 1;
        }
        require_once __DIR__ . '/StudioCanvasMigrator.php';
        $canvas = StudioCanvasMigrator::upgrade($canvas);
        $this->writeJson($this->pageCanvasPath($projectId, $pageSlug), $canvas);

        $meta = $this->readProjectJson($projectId);
        $meta['updated_at'] = date('c');
        $this->writeProjectJson($projectId, $meta);

        if ($compile) {
            require_once __DIR__ . '/StudioCompiler.php';
            (new StudioCompiler($this))->compilePage($projectId, $pageSlug);
        }

        return $canvas;
    }

    public function saveCustomCss(string $projectId, string $css): bool
    {
        $path = $this->projectDir($projectId) . '/styles/custom.css';
        if (!$this->readProjectJson($projectId)) {
            return false;
        }
        file_put_contents($path, $css);
        return true;
    }

    public function getCustomCss(string $projectId): string
    {
        $path = $this->projectDir($projectId) . '/styles/custom.css';
        if (!is_file($path)) {
            return '';
        }
        $css = file_get_contents($path);
        return $css === false ? '' : $css;
    }

    public function addPage(string $projectId, string $title, ?string $slug = null): ?array
    {
        $slug = $slug !== null && $slug !== '' ? studio_slugify($slug) : studio_slugify($title);
        if ($this->getPageCanvas($projectId, $slug)) {
            $slug = $slug . '-' . substr(bin2hex(random_bytes(2)), 0, 4);
        }
        $pages = $this->listPages($projectId);
        $maxSort = 0;
        foreach ($pages as $p) {
            $maxSort = max($maxSort, (int) ($p['sort'] ?? 0));
        }
        $canvas = $this->defaultCanvas($slug, $title);
        $canvas['page']['sort'] = $maxSort + 10;
        $this->savePageCanvas($projectId, $slug, $canvas);
        return ['slug' => $slug, 'title' => $title];
    }

    public function deletePage(string $projectId, string $pageSlug): bool
    {
        $canvasPath = $this->pageCanvasPath($projectId, $pageSlug);
        $htmlPath = $this->pageHtmlPath($projectId, $pageSlug);
        $ok = true;
        if (is_file($canvasPath)) {
            $ok = unlink($canvasPath) && $ok;
        }
        if (is_file($htmlPath)) {
            unlink($htmlPath);
        }
        return $ok;
    }

    public function reorderPages(string $projectId, array $orderedSlugs): bool
    {
        $sort = 10;
        foreach ($orderedSlugs as $slug) {
            $canvas = $this->getPageCanvas($projectId, $slug);
            if (!$canvas) {
                continue;
            }
            $canvas['page']['sort'] = $sort;
            $sort += 10;
            $this->savePageCanvas($projectId, $slug, $canvas, false);
        }
        return true;
    }

    public function setHomePage(string $projectId, string $pageSlug): ?array
    {
        return $this->updateProject($projectId, ['home_page' => $pageSlug]);
    }

    public function projectDir(string $id): string
    {
        return $this->root . '/' . $id;
    }

    public function pageCanvasPath(string $projectId, string $pageSlug): string
    {
        return $this->projectDir($projectId) . '/pages/' . $pageSlug . '.studio.json';
    }

    public function pageHtmlPath(string $projectId, string $pageSlug): string
    {
        return $this->projectDir($projectId) . '/pages/' . $pageSlug . '.html';
    }

    public function stylesCssPath(string $projectId): string
    {
        return $this->projectDir($projectId) . '/styles/kit.css';
    }

    private function defaultCanvas(string $slug, string $title): array
    {
        return [
            'version' => 1,
            'page' => ['slug' => $slug, 'title' => $title, 'sort' => 0],
            'sections' => [
                [
                    'id' => 'section-1',
                    'classes' => ['studio-section'],
                    'layout' => ['type' => 'grid', 'columns' => 1, 'gap' => '1.5rem'],
                    'rows' => [
                        [
                            'id' => 'row-1',
                            'columns' => [
                                [
                                    'id' => 'col-1',
                                    'width' => '100%',
                                    'elements' => [
                                        [
                                            'id' => 'el-hero',
                                            'type' => 'text',
                                            'tag' => 'h1',
                                            'content' => $title,
                                            'classes' => [],
                                            'styles' => ['textAlign' => 'center'],
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
            'customClasses' => [],
        ];
    }

    private function readProjectJson(string $id): ?array
    {
        $path = $this->projectDir($id) . '/project.json';
        if (!is_file($path)) {
            return null;
        }
        return $this->readJson($path);
    }

    private function writeProjectJson(string $id, array $meta): void
    {
        $this->writeJson($this->projectDir($id) . '/project.json', $meta);
    }

    private function readJson(string $path): ?array
    {
        if (!is_file($path)) {
            return null;
        }
        $decoded = json_decode((string) file_get_contents($path), true);
        return is_array($decoded) ? $decoded : null;
    }

    private function writeJson(string $path, array $data): void
    {
        $dir = dirname($path);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        file_put_contents($path, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    }

    private function ensureUniqueSlug(string $slug, ?string $exceptId): string
    {
        $base = $slug;
        $i = 2;
        while ($this->slugTaken($slug, $exceptId)) {
            $slug = $base . '-' . $i;
            $i++;
        }
        return $slug;
    }

    private function slugTaken(string $slug, ?string $exceptId): bool
    {
        foreach ($this->listProjects() as $project) {
            if ($exceptId !== null && ($project['id'] ?? '') === $exceptId) {
                continue;
            }
            if (($project['slug'] ?? '') === $slug) {
                return true;
            }
        }
        return false;
    }

    private function copyDir(string $src, string $dest): void
    {
        if (!is_dir($dest)) {
            mkdir($dest, 0755, true);
        }
        foreach (scandir($src) ?: [] as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            $from = $src . '/' . $item;
            $to = $dest . '/' . $item;
            if (is_dir($from)) {
                $this->copyDir($from, $to);
            } else {
                copy($from, $to);
            }
        }
    }

    private function removeDir(string $dir): void
    {
        foreach (scandir($dir) ?: [] as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            $path = $dir . '/' . $item;
            if (is_dir($path)) {
                $this->removeDir($path);
            } else {
                unlink($path);
            }
        }
        rmdir($dir);
    }
}
