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
    'jpg', 'jpeg', 'png', 'gif', 'ico',
  ];

  /** @var string[] */
  private array $textExtensions = [
    'html', 'css', 'js', 'json', 'md', 'svg', 'txt',
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
  public function textExtensions(): array
  {
    return $this->textExtensions;
  }

  public function isTextFile(string $relativePath): bool
  {
    $ext = strtolower(pathinfo($this->normalizeRelative($relativePath), PATHINFO_EXTENSION));
    return in_array($ext, $this->textExtensions, true);
  }

  public function isImageFile(string $relativePath): bool
  {
    $ext = strtolower(pathinfo($this->normalizeRelative($relativePath), PATHINFO_EXTENSION));
    return in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg', 'ico'], true);
  }

  public function createDirectory(string $relativePath): bool
  {
    $rel = $this->normalizeRelative($relativePath);
    if ($rel === '' || str_contains($rel, '..') || $this->hasDotfileSegment($rel)) {
      return false;
    }

    $full = $this->resolvePath($rel, true);
    if ($full === null) {
      return false;
    }
    if (is_dir($full)) {
      return true;
    }
    if (is_file($full)) {
      return false;
    }

    return mkdir($full, 0755, true);
  }

  public function writeBinaryFile(string $relativePath, string $sourcePath): bool
  {
    $full = $this->resolvePath($relativePath, true);
    if ($full === null || !$this->isAllowedPath($relativePath) || !is_uploaded_file($sourcePath)) {
      return false;
    }
    $parent = dirname($full);
    if (!is_dir($parent)) {
      mkdir($parent, 0755, true);
    }
    return move_uploaded_file($sourcePath, $full);
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
      'detail_page_meta' => '<meta name="birkly-page" content="post"><meta name="birkly-collection" content="blog">',
      'list_loop' => "{for each entry in 'blog' with status = published ordered by newest}\n  <a href=\"/project/post.html?collection=blog&slug={slug}\">{title}</a>\n{endfor}",
      'detail_page' => "Use ?collection=blog&slug={slug} in links. Detail page: { title }, { content } — NO {for each} loop on detail pages.",
      'loop' => "{for each entry in 'blog'}\n  <h2>{title}</h2>\n  <div>{'content'}</div>\n{endfor}",
      'single_entry' => "{from 'blog' get entry 'my-post-slug'}\n  <h1>{title}</h1>\n{endget}",
      'conditional' => "{if entry 'blog'.'featured' exists}\n  ...\n{endif}",
      'from_block' => "{from 'site_elements' get entry 'navigation'}\n  ...\n{endget}",
      'region' => '<birkly-region collection="site_elements" entry="header"></birkly-region>',
      'rules' => [
        'ALL asset and page links MUST use site_url_prefix (e.g. /project/css/style.css not /css/style.css)',
        'create_entry auto-generates slug from title — use post.html?collection=blog&slug={slug} in list links',
        'Prefer slug URLs over ?id= for detail pages',
        'Collection slugs use single quotes (hyphens allowed: nav-links)',
        'System fields are bare: {title}, {slug}',
        'Custom fields use quoted names: {\'content\'}',
        'Detail pages: meta tags + top-level { title }/{ content } — never loop the whole collection',
        'Shared blocks: <birkly-region collection="site_elements" entry="header">',
        'Rich sites use multiple collections: site_elements (header/footer), blog, about, contact (public_api for forms)',
        'Filters: {created_at format "Y-m-d"} only — truncate/strip_tags are not implemented',
        'Public site only shows published entries — set status to published',
      ],
    ],
  ];
}
