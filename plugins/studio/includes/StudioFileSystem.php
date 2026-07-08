<?php
/**
 * Sandboxed project filesystem (P23-M2).
 */

require_once __DIR__ . '/StudioProjectRepository.php';

class StudioFileSystem
{
    private StudioProjectRepository $repo;

    /** @var string[] */
    private array $allowedExtensions = [
        'json', 'html', 'css', 'md', 'txt', 'studio.json',
    ];

    public function __construct(?StudioProjectRepository $repo = null)
    {
        $this->repo = $repo ?? new StudioProjectRepository();
    }

    public function listTree(string $projectId): array
    {
        $base = $this->projectBase($projectId);
        if ($base === null) {
            return [];
        }
        return $this->scanDir($base, '');
    }

    /**
     * @return array{path:string,content:string,size:int}|null
     */
    public function readFile(string $projectId, string $relativePath): ?array
    {
        $full = $this->resolvePath($projectId, $relativePath, false);
        if ($full === null || !is_file($full)) {
            return null;
        }
        $content = file_get_contents($full);
        if ($content === false) {
            return null;
        }
        return [
            'path' => $this->normalizeRelative($relativePath),
            'content' => $content,
            'size' => filesize($full) ?: 0,
        ];
    }

    public function writeFile(string $projectId, string $relativePath, string $content, bool $recordRevision = true): bool
    {
        $full = $this->resolvePath($projectId, $relativePath, true);
        if ($full === null) {
            return false;
        }
        if (!$this->isAllowedPath($relativePath)) {
            return false;
        }
        $dir = dirname($full);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        $old = is_file($full) ? (string) file_get_contents($full) : null;
        $ok = file_put_contents($full, $content) !== false;
        if ($ok && $recordRevision && $old !== null && $old !== $content) {
            require_once __DIR__ . '/StudioRevisionStore.php';
            (new StudioRevisionStore($this->repo))->record($projectId, $relativePath, $old);
        }
        if ($ok) {
            $this->touchProject($projectId);
        }
        return $ok;
    }

    public function createFile(string $projectId, string $relativePath, string $content = ''): bool
    {
        $full = $this->resolvePath($projectId, $relativePath, true);
        if ($full === null || is_file($full)) {
            return false;
        }
        return $this->writeFile($projectId, $relativePath, $content, false);
    }

    public function createDirectory(string $projectId, string $relativePath): bool
    {
        $full = $this->resolvePath($projectId, $relativePath, true);
        if ($full === null || is_dir($full)) {
            return false;
        }
        return mkdir($full, 0755, true);
    }

    public function deletePath(string $projectId, string $relativePath): bool
    {
        $rel = $this->normalizeRelative($relativePath);
        if ($rel === 'project.json' || $rel === 'pages' || str_starts_with($rel, 'pages/index')) {
            return false;
        }
        $full = $this->resolvePath($projectId, $relativePath, false);
        if ($full === null || !file_exists($full)) {
            return false;
        }
        if (is_dir($full)) {
            $this->removeDir($full);
        } else {
            unlink($full);
        }
        $this->touchProject($projectId);
        return true;
    }

    public function renamePath(string $projectId, string $from, string $to): bool
    {
        $fromFull = $this->resolvePath($projectId, $from, false);
        $toFull = $this->resolvePath($projectId, $to, true);
        if ($fromFull === null || $toFull === null || !file_exists($fromFull)) {
            return false;
        }
        if (!is_dir(dirname($toFull))) {
            mkdir(dirname($toFull), 0755, true);
        }
        $ok = rename($fromFull, $toFull);
        if ($ok) {
            $this->touchProject($projectId);
        }
        return $ok;
    }

    public function resolvePath(string $projectId, string $relativePath, bool $allowCreate): ?string
    {
        $base = $this->projectBase($projectId);
        if ($base === null) {
            return null;
        }
        $rel = $this->normalizeRelative($relativePath);
        if ($rel === '' || str_contains($rel, '..') || str_starts_with($rel, '.')) {
            return null;
        }
        $parts = explode('/', $rel);
        foreach ($parts as $part) {
            if ($part === '' || $part === '.' || $part === '..') {
                return null;
            }
        }
        $candidate = $base . '/' . $rel;
        $parent = dirname($candidate);
        $parentReal = realpath($parent);
        if ($parentReal === false) {
            if (!$allowCreate) {
                return null;
            }
            if (!str_starts_with($parent, $base)) {
                return null;
            }
            return $candidate;
        }
        if (!str_starts_with($parentReal, $base)) {
            return null;
        }
        if (file_exists($candidate)) {
            $real = realpath($candidate);
            if ($real === false || !str_starts_with($real, $base)) {
                return null;
            }
            return $real;
        }
        return $allowCreate ? $candidate : null;
    }

    private function projectBase(string $projectId): ?string
    {
        $dir = $this->repo->projectDir($projectId);
        if (!is_dir($dir)) {
            return null;
        }
        $real = realpath($dir);
        return $real !== false ? $real : null;
    }

    private function normalizeRelative(string $path): string
    {
        return ltrim(str_replace('\\', '/', trim($path)), '/');
    }

    private function isAllowedPath(string $relativePath): bool
    {
        $rel = $this->normalizeRelative($relativePath);
        if (str_contains($rel, '..') || str_starts_with($rel, '.')) {
            return false;
        }
        if (str_ends_with($rel, '.studio.json')) {
            return true;
        }
        $ext = strtolower(pathinfo($rel, PATHINFO_EXTENSION));
        return in_array($ext, $this->allowedExtensions, true);
    }

    private function scanDir(string $base, string $prefix): array
    {
        $dir = $prefix === '' ? $base : $base . '/' . $prefix;
        if (!is_dir($dir)) {
            return [];
        }
        $nodes = [];
        foreach (scandir($dir) ?: [] as $entry) {
            if ($entry === '.' || $entry === '..' || $entry === '.revisions') {
                continue;
            }
            $rel = $prefix === '' ? $entry : $prefix . '/' . $entry;
            $path = $base . '/' . $rel;
            if (is_dir($path)) {
                $nodes[] = [
                    'name' => $entry,
                    'path' => $rel,
                    'type' => 'dir',
                    'children' => $this->scanDir($base, $rel),
                ];
            } else {
                $nodes[] = [
                    'name' => $entry,
                    'path' => $rel,
                    'type' => 'file',
                    'size' => filesize($path) ?: 0,
                ];
            }
        }
        usort($nodes, static function ($a, $b) {
            if ($a['type'] !== $b['type']) {
                return $a['type'] === 'dir' ? -1 : 1;
            }
            return strcasecmp($a['name'], $b['name']);
        });
        return $nodes;
    }

    private function removeDir(string $dir): void
    {
        foreach (scandir($dir) ?: [] as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            $path = $dir . '/' . $item;
            is_dir($path) ? $this->removeDir($path) : unlink($path);
        }
        rmdir($dir);
    }

    private function touchProject(string $projectId): void
    {
        $meta = $this->repo->getProject($projectId);
        if (!$meta) {
            return;
        }
        $path = $this->repo->projectDir($projectId) . '/project.json';
        $meta['updated_at'] = date('c');
        file_put_contents($path, json_encode($meta, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    }
}
