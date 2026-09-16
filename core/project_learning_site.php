<?php
/**
 * Minimal learning / test site under project/ when no demo template is installed.
 */

declare(strict_types=1);

require_once __DIR__ . '/project_site.php';

function birkly_learning_site_collection_slug(): string
{
    return 'posts';
}

function birkly_learning_site_marker(): string
{
    return 'birkly-learning-starter-site';
}

function birkly_learning_site_is_learning_index(string $path): bool
{
    if (!is_readable($path)) {
        return false;
    }
    $content = (string) file_get_contents($path);
    return str_contains($content, birkly_learning_site_marker());
}

function birkly_learning_site_index_html(): string
{
    $slug = birkly_learning_site_collection_slug();
    return <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Birkly learning site</title>
</head>
<body>
  <!-- birkly-learning-starter-site -->
  <main>
    <h1>Birkly learning site</h1>
    <p>
      This page is a minimal starter template for testing and learning.
      It is not a production design — use it to practice how Birkly connects
      collections in the admin to HTML on your public site.
    </p>
    <p>
      In the admin Guide, create a collection with slug <code>{$slug}</code>,
      turn on <strong>public read</strong>, publish an entry, then reload this page
      to see it listed below.
    </p>
    <section aria-labelledby="posts-heading">
      <h2 id="posts-heading">Published entries</h2>
      <ul>
        {for each entry in '{$slug}' with status = published ordered by newest}
        <li><strong>{title}</strong></li>
        {endfor}
      </ul>
    </section>
  </main>
  <script src="/birkly-client.js"></script>
</body>
</html>

HTML;
}

/**
 * True when project/ has no real site yet (missing index or only placeholder files).
 */
function birkly_learning_site_project_is_empty(string $websiteRoot): bool
{
    if (!is_dir($websiteRoot)) {
        return true;
    }
    $index = $websiteRoot . '/index.html';
    if (is_file($index)) {
        return false;
    }
    $hasSiteFile = false;
    foreach (scandir($websiteRoot) ?: [] as $item) {
        if ($item === '.' || $item === '..' || $item === '.gitkeep') {
            continue;
        }
        $path = $websiteRoot . '/' . $item;
        if (is_file($path)) {
            $hasSiteFile = true;
            break;
        }
        if (is_dir($path)) {
            $hasSiteFile = true;
            break;
        }
    }
    return !$hasSiteFile;
}

/**
 * Write (or replace) the learning index.html under project/.
 */
function birkly_write_learning_project_site_index(string $projectRoot): bool
{
    $websiteRoot = rtrim($projectRoot, '/\\') . '/' . birkly_public_site_dir();
    if (!is_dir($websiteRoot) && !@mkdir($websiteRoot, 0755, true) && !is_dir($websiteRoot)) {
        return false;
    }
    $indexPath = $websiteRoot . '/index.html';
    return @file_put_contents($indexPath, birkly_learning_site_index_html()) !== false;
}

/**
 * Write learning index.html when the project folder has no site yet.
 *
 * @return array{ok:bool,created:bool,path?:string,message?:string}
 */
function birkly_ensure_learning_project_site(string $projectRoot, bool $enableVisitHosting = true): array
{
    $websiteRoot = rtrim($projectRoot, '/\\') . '/' . birkly_public_site_dir();
    if (!is_dir($websiteRoot)) {
        if (!@mkdir($websiteRoot, 0755, true) && !is_dir($websiteRoot)) {
            return ['ok' => false, 'created' => false, 'message' => 'Could not create project directory'];
        }
    }

    $indexPath = $websiteRoot . '/index.html';
    if (!birkly_learning_site_project_is_empty($websiteRoot)) {
        if (is_file($indexPath)) {
            return ['ok' => true, 'created' => false, 'path' => $indexPath];
        }
        return [
            'ok' => true,
            'created' => false,
            'message' => 'Project site files already exist',
        ];
    }

    if (is_file($indexPath) && !birkly_learning_site_is_learning_index($indexPath)) {
        return ['ok' => true, 'created' => false, 'path' => $indexPath];
    }

    $html = birkly_learning_site_index_html();
    if (@file_put_contents($indexPath, $html) === false) {
        return ['ok' => false, 'created' => false, 'message' => 'Could not write learning site index.html'];
    }

    if ($enableVisitHosting && function_exists('birkly_demo_enable_visit_project_after_install')) {
        require_once __DIR__ . '/demo_starter_templates.php';
        birkly_demo_enable_visit_project_after_install($projectRoot);
    }

    return ['ok' => true, 'created' => true, 'path' => $indexPath];
}
