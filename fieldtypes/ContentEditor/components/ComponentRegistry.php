<?php
/**
 * ContentEditor component registry — bundled fieldtype components + user content/components.
 * P2-M3-T6: self-contained under fieldtypes/ContentEditor/
 */

namespace Birkly\FieldTypes\ContentEditor;

class ComponentRegistry
{
    private string $bundledDir;
    private string $userDir;

    public function __construct(?string $bundledDir = null, ?string $userDir = null)
    {
        $this->bundledDir = $bundledDir ?? __DIR__ . '/bundled';
        $this->userDir = $userDir ?? dirname(__DIR__, 3) . '/content/components';
    }

    public function getBundledDir(): string
    {
        return $this->bundledDir;
    }

    public function getUserDir(): string
    {
        return $this->userDir;
    }

    /**
     * @return list<array{slug:string,name:string,description:string,category:string,source:string,bundled:bool,created_at:string,updated_at:string}>
     */
    public function listAll(): array
    {
        $bySlug = [];

        foreach ($this->scanDir($this->bundledDir, 'bundled') as $component) {
            $bySlug[$component['slug']] = $component;
        }

        foreach ($this->scanDir($this->userDir, 'user') as $component) {
            $bySlug[$component['slug']] = $component;
        }

        $components = array_values($bySlug);
        usort($components, static fn(array $a, array $b): int => strcmp($a['name'], $b['name']));

        return $components;
    }

    public function get(string $slug): ?array
    {
        $slug = $this->sanitizeSlug($slug);
        if ($slug === '') {
            return null;
        }

        $userPath = $this->userDir . '/' . $slug;
        if (is_dir($userPath)) {
            return $this->loadFromDir($userPath, $slug, 'user', false);
        }

        $bundledPath = $this->bundledDir . '/' . $slug;
        if (is_dir($bundledPath)) {
            return $this->loadFromDir($bundledPath, $slug, 'bundled', true);
        }

        return null;
    }

    public function isBundled(string $slug): bool
    {
        $slug = $this->sanitizeSlug($slug);
        return $slug !== '' && is_dir($this->bundledDir . '/' . $slug);
    }

    /**
     * @param array<string,mixed> $data
     * @return array{success:bool,slug?:string,error?:string}
     */
    public function save(array $data): array
    {
        $slug = $this->sanitizeSlug($data['slug'] ?? '');
        if ($slug === '') {
            return ['success' => false, 'error' => 'Invalid slug'];
        }

        if ($this->isBundled($slug)) {
            return ['success' => false, 'error' => 'Bundled components cannot be overwritten'];
        }

        if (!is_dir($this->userDir)) {
            mkdir($this->userDir, 0755, true);
        }

        $componentDir = $this->userDir . '/' . $slug;
        if (!is_dir($componentDir)) {
            mkdir($componentDir, 0755, true);
        }

        $metaFile = $componentDir . '/component.json';
        $isNew = !is_file($metaFile);
        $existingMeta = $isNew ? [] : (json_decode((string) file_get_contents($metaFile), true) ?: []);

        $meta = [
            'slug' => $slug,
            'name' => $data['name'] ?? $slug,
            'description' => $data['description'] ?? '',
            'category' => $data['category'] ?? 'uncategorized',
            'auto_scope_css' => isset($data['auto_scope_css']) ? (bool) $data['auto_scope_css'] : true,
            'execute_js' => isset($data['execute_js']) ? (bool) $data['execute_js'] : true,
            'updated_at' => date('Y-m-d H:i:s'),
            'created_at' => $existingMeta['created_at'] ?? date('Y-m-d H:i:s'),
        ];

        file_put_contents($metaFile, json_encode($meta, JSON_PRETTY_PRINT));
        file_put_contents($componentDir . '/template.html', $data['template'] ?? '');

        $this->writeOptionalFile($componentDir . '/style.css', $data['style'] ?? '');
        $this->writeOptionalFile($componentDir . '/script.js', $data['script'] ?? '');

        return ['success' => true, 'slug' => $slug];
    }

    /**
     * @return array{success:bool,error?:string}
     */
    public function delete(string $slug): array
    {
        $slug = $this->sanitizeSlug($slug);
        if ($slug === '') {
            return ['success' => false, 'error' => 'Invalid slug'];
        }

        if ($this->isBundled($slug)) {
            return ['success' => false, 'error' => 'Bundled components cannot be deleted'];
        }

        $componentDir = $this->userDir . '/' . $slug;
        if (!is_dir($componentDir)) {
            return ['success' => false, 'error' => 'Component not found'];
        }

        foreach (glob($componentDir . '/*') ?: [] as $file) {
            if (is_file($file)) {
                unlink($file);
            }
        }
        rmdir($componentDir);

        return ['success' => true];
    }

    /**
     * @return list<array{slug:string,name:string,description:string,category:string,source:string,bundled:bool,created_at:string,updated_at:string}>
     */
    private function scanDir(string $dir, string $source): array
    {
        if (!is_dir($dir)) {
            return [];
        }

        $components = [];
        foreach (array_filter(glob($dir . '/*'), 'is_dir') as $componentDir) {
            $slug = basename($componentDir);
            $metaFile = $componentDir . '/component.json';
            if (!is_file($metaFile)) {
                continue;
            }

            $meta = json_decode((string) file_get_contents($metaFile), true) ?: [];
            $components[] = [
                'slug' => $slug,
                'name' => $meta['name'] ?? $slug,
                'description' => $meta['description'] ?? '',
                'category' => $meta['category'] ?? 'uncategorized',
                'source' => $source,
                'bundled' => $source === 'bundled',
                'created_at' => $meta['created_at'] ?? '',
                'updated_at' => $meta['updated_at'] ?? '',
            ];
        }

        return $components;
    }

    private function loadFromDir(string $dir, string $slug, string $source, bool $bundled): array
    {
        $metaFile = $dir . '/component.json';
        $meta = is_file($metaFile) ? (json_decode((string) file_get_contents($metaFile), true) ?: []) : [];

        $component = [
            'slug' => $slug,
            'name' => $meta['name'] ?? $slug,
            'description' => $meta['description'] ?? '',
            'category' => $meta['category'] ?? 'uncategorized',
            'template' => '',
            'style' => '',
            'script' => '',
            'auto_scope_css' => $meta['auto_scope_css'] ?? true,
            'execute_js' => $meta['execute_js'] ?? true,
            'source' => $source,
            'bundled' => $bundled,
            'created_at' => $meta['created_at'] ?? '',
            'updated_at' => $meta['updated_at'] ?? '',
        ];

        $templateFile = $dir . '/template.html';
        if (is_file($templateFile)) {
            $component['template'] = file_get_contents($templateFile);
        }

        $styleFile = $dir . '/style.css';
        if (is_file($styleFile)) {
            $component['style'] = file_get_contents($styleFile);
        }

        $scriptFile = $dir . '/script.js';
        if (is_file($scriptFile)) {
            $component['script'] = file_get_contents($scriptFile);
        }

        return $component;
    }

    private function sanitizeSlug(string $slug): string
    {
        return preg_replace('/[^a-z0-9-]/', '', strtolower($slug)) ?? '';
    }

    private function writeOptionalFile(string $path, string $contents): void
    {
        if ($contents !== '') {
            file_put_contents($path, $contents);
        } elseif (is_file($path)) {
            unlink($path);
        }
    }
}
