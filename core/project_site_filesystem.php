<?php
/**
 * Sandboxed public site filesystem under project/ (P38-M0).
 * Adapted from plugins/studio/includes/StudioFileSystem.php.
 */

declare(strict_types=1);

require_once __DIR__ . '/project_site.php';

class ProjectSiteFileSystem
{
  private string $base;

  /** @var string[] */
  private array $allowedExtensions = [
    'html', 'css', 'js', 'json', 'md', 'svg', 'webp', 'txt',
  ];

  public function __construct(?string $basePath = null)
  {
    birkly_ensure_public_site_path();
    $base = $basePath ?? birkly_public_site_path();
    if (!is_dir($base) && !mkdir($base, 0755, true)) {
      throw new RuntimeException('Cannot create public site directory');
    }
    $real = realpath($base);
    $this->base = $real !== false ? $real : $base;
  }

  public function basePath(): string
  {
    return $this->base;
  }

  /** @return string[] */
  public function allowedExtensions(): array
  {
    return $this->allowedExtensions;
  }

  /**
   * @return list<array{name:string,path:string,type:string,size?:int}>
   */
  public function listFiles(?string $relativePath = ''): array
  {
    $rel = $this->normalizeRelative((string) $relativePath);
    $dir = $rel === '' ? $this->base : $this->resolvePath($rel, false);
    if ($dir === null || !is_dir($dir)) {
      return [];
    }

    $entries = [];
    foreach (scandir($dir) ?: [] as $entry) {
      if ($entry === '.' || $entry === '..' || str_starts_with($entry, '.')) {
        continue;
      }
      $childRel = $rel === '' ? $entry : $rel . '/' . $entry;
      $full = $dir . '/' . $entry;
      if (is_dir($full)) {
        $entries[] = ['name' => $entry, 'path' => $childRel, 'type' => 'dir'];
        continue;
      }
      if (!$this->isAllowedPath($childRel)) {
        continue;
      }
      $entries[] = [
        'name' => $entry,
        'path' => $childRel,
        'type' => 'file',
        'size' => filesize($full) ?: 0,
      ];
    }

    usort($entries, static function (array $a, array $b): int {
      if ($a['type'] !== $b['type']) {
        return $a['type'] === 'dir' ? -1 : 1;
      }
      return strcasecmp($a['name'], $b['name']);
    });

    return $entries;
  }

  /**
   * @return array{path:string,content:string,size:int}|null
   */
  public function readFile(string $relativePath): ?array
  {
    $full = $this->resolvePath($relativePath, false);
    if ($full === null || !is_file($full) || !$this->isAllowedPath($relativePath)) {
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

  public function writeFile(string $relativePath, string $content): bool
  {
    $full = $this->resolvePath($relativePath, true);
    if ($full === null || !$this->isAllowedPath($relativePath)) {
      return false;
    }
    $parent = dirname($full);
    if (!is_dir($parent)) {
      mkdir($parent, 0755, true);
    }
    return file_put_contents($full, $content) !== false;
  }

  public function createFile(string $relativePath, string $content = ''): bool
  {
    $full = $this->resolvePath($relativePath, true);
    if ($full === null || is_file($full) || !$this->isAllowedPath($relativePath)) {
      return false;
    }
    return $this->writeFile($relativePath, $content);
  }

  public function deleteFile(string $relativePath): bool
  {
    $full = $this->resolvePath($relativePath, false);
    if ($full === null || !is_file($full) || !$this->isAllowedPath($relativePath)) {
      return false;
    }
    return unlink($full);
  }

  public function resolvePath(string $relativePath, bool $allowCreate): ?string
  {
    $rel = $this->normalizeRelative($relativePath);
    if ($rel === '' || str_contains($rel, '..') || $this->hasDotfileSegment($rel)) {
      return null;
    }
    $parts = explode('/', $rel);
    foreach ($parts as $part) {
      if ($part === '' || $part === '.' || $part === '..') {
        return null;
      }
    }

    $candidate = $this->base . '/' . $rel;
    $parent = dirname($candidate);
    $parentReal = realpath($parent);
    if ($parentReal === false) {
      if (!$allowCreate) {
        return null;
      }
      if (!str_starts_with($parent, $this->base)) {
        return null;
      }
      return $candidate;
    }
    if (!str_starts_with($parentReal, $this->base)) {
      return null;
    }
    if (file_exists($candidate)) {
      $real = realpath($candidate);
      if ($real === false || !str_starts_with($real, $this->base)) {
        return null;
      }
      return $real;
    }
    return $allowCreate ? $candidate : null;
  }

  private function normalizeRelative(string $path): string
  {
    return ltrim(str_replace('\\', '/', trim($path)), '/');
  }

  private function hasDotfileSegment(string $relativePath): bool
  {
    foreach (explode('/', $relativePath) as $segment) {
      if ($segment !== '' && str_starts_with($segment, '.')) {
        return true;
      }
    }
    return false;
  }

  private function isAllowedPath(string $relativePath): bool
  {
    $rel = $this->normalizeRelative($relativePath);
    if ($rel === '' || str_contains($rel, '..') || $this->hasDotfileSegment($rel)) {
      return false;
    }
    $ext = strtolower(pathinfo($rel, PATHINFO_EXTENSION));
    if ($ext === 'php' || $ext === 'phtml' || $ext === 'phar') {
      return false;
    }
    if ($ext === '') {
      return false;
    }
    return in_array($ext, $this->allowedExtensions, true);
  }
}

/**
 * @return array<string, mixed>
 */
function birkly_project_site_info(): array
{
  require_once __DIR__ . '/../api/storage.php';

  $fs = new ProjectSiteFileSystem();
  $contentStorage = new Storage(birkly_project_root() . '/content');
  $collections = [];

  $collectionsDir = birkly_project_root() . '/content/collections';
  if (is_dir($collectionsDir)) {
    foreach (array_filter(glob($collectionsDir . '/*'), 'is_dir') ?: [] as $dir) {
      $metaFile = $dir . '/collection.json';
      if (!is_file($metaFile)) {
        continue;
      }
      $meta = json_decode((string) file_get_contents($metaFile), true);
      if (!is_array($meta)) {
        continue;
      }
      $collections[] = [
        'slug' => $meta['slug'] ?? basename($dir),
        'name' => $meta['name'] ?? $meta['title'] ?? basename($dir),
      ];
    }
  }

  return [
    'site_dir' => birkly_public_site_dir(),
    'site_path' => birkly_public_site_path(),
    'site_url_prefix' => '/' . birkly_public_site_dir(),
    'public_url_example' => birkly_public_site_url('/index.html'),
    'allowed_extensions' => $fs->allowedExtensions(),
    'collections' => $collections,
    'templating' => [
      'script' => '<script src="/birkly-client.js"></script>',
      'loop' => "{for each entry in 'blog'}\n  <h2>{title}</h2>\n  <div>{'content'}</div>\n{endfor}",
      'single_entry' => "{entry 'blog'.'my-post-slug'}\n  <h1>{title}</h1>\n{endentry}",
      'conditional' => "{if entry 'blog'.'featured' exists}\n  ...\n{endif}",
      'from_block' => "{from 'site_elements' get entry 'navigation'}\n  ...\n{endget}",
      'rules' => [
        'Collection slugs use single quotes (hyphens allowed: nav-links)',
        'System fields are bare: {title}, {slug}',
        'Custom fields use quoted names: {\'content\'}',
        'Static assets live under /project/ (css/, js/, images/)',
        'Public site only shows published entries — set status to published for nav/menu items',
        'Template tags work in any outermost <body> element with Birkly syntax',
      ],
    ],
  ];
}
