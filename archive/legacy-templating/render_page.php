<?php
// Page renderer for Birkly
require_once __DIR__ . '/template_engine.php';

// Get the page data (passed from router)
$pageData = $pageData ?? null;
if (!$pageData) {
    http_response_code(404);
    echo 'Page not found';
    exit;
}

// Load navigation menu
function getNavigationMenu() {
    $pagesDir = __DIR__ . '/content/collections/pages/entries';
    $menu = [];
    
    if (is_dir($pagesDir)) {
        $entries = scandir($pagesDir);
        foreach ($entries as $entry) {
            if (pathinfo($entry, PATHINFO_EXTENSION) === 'json') {
                $pageData = json_decode(file_get_contents($pagesDir . '/' . $entry), true);
                if ($pageData && $pageData['status'] === 'published' && !empty($pageData['show_in_menu'])) {
                    $menu[] = [
                        'title' => $pageData['title'],
                        'slug' => $pageData['slug'],
                        'order' => $pageData['menu_order'] ?? 999
                    ];
                }
            }
        }
    }
    
    // Sort by menu order
    usort($menu, function($a, $b) {
        return ($a['order'] ?? 999) - ($b['order'] ?? 999);
    });
    
    return $menu;
}

// Get blog entries for homepage
function getBlogEntries($limit = 6) {
    $blogDir = __DIR__ . '/content/collections/blog/entries';
    $entries = [];
    
    if (is_dir($blogDir)) {
        $files = scandir($blogDir);
        $count = 0;
        foreach ($files as $file) {
            if (pathinfo($file, PATHINFO_EXTENSION) === 'json' && $count < $limit) {
                $entryData = json_decode(file_get_contents($blogDir . '/' . $file), true);
                if ($entryData) {
                    $entries[] = $entryData;
                    $count++;
                }
            }
        }
    }
    
    // Sort by date (newest first)
    usort($entries, function($a, $b) {
        return strtotime($b['created_at'] ?? '') - strtotime($a['created_at'] ?? '');
    });
    
    return $entries;
}

// Render based on template
$template = $pageData['template'] ?? 'default';
$menu = getNavigationMenu();

switch ($template) {
    case 'home':
        $blogEntries = getBlogEntries(6);
        $templateHtml = <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{$pageData['title']}</title>
    <meta name="description" content="{$pageData['meta_description']}">
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; line-height: 1.6; margin: 0; padding: 0; }
        .container { max-width: 1200px; margin: 0 auto; padding: 0 20px; }
        .header { background: #f8f9fa; padding: 1rem 0; border-bottom: 1px solid #e9ecef; }
        .nav { display: flex; gap: 2rem; }
        .nav a { text-decoration: none; color: #333; }
        .nav a:hover { color: #007bff; }
        .hero { padding: 4rem 0; text-align: center; }
        .blog-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 2rem; margin: 3rem 0; }
        .blog-card { border: 1px solid #e9ecef; border-radius: 8px; padding: 1.5rem; }
        .blog-card h3 { margin-top: 0; }
        .blog-card .date { color: #6c757d; font-size: 0.9rem; }
        .footer { background: #f8f9fa; padding: 2rem 0; margin-top: 3rem; text-align: center; }
    </style>
</head>
<body>
    <header class="header">
        <div class="container">
            <nav class="nav">
                {for each item in menu}
                    <a href="/{item.slug}">{item.title}</a>
                {/for}
            </nav>
        </div>
    </header>
    
    <main>
        <div class="hero">
            <div class="container">
                <h1>{$pageData['title']}</h1>
                <div>{$pageData['content']}</div>
            </div>
        </div>
        
        <div class="container">
            <h2>Latest Blog Posts</h2>
            <div class="blog-grid">
                {for each entry in blogEntries}
                    <article class="blog-card">
                        <h3><a href="/blog/{entry.slug}">{entry.title}</a></h3>
                        <div class="date">{entry.date}</div>
                        <p>{entry.content}</p>
                        {if entry.featured}<span style="color: #007bff;">★ Featured</span>{/if}
                    </article>
                {/for}
            </div>
        </div>
    </main>
    
    <footer class="footer">
        <div class="container">
            <p>&copy; 2025 My Blog. Built with Birkly.</p>
        </div>
    </footer>
</body>
</html>
HTML;
        break;
        
    case 'blog-list':
        $blogEntries = getBlogEntries(20);
        $templateHtml = <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{$pageData['title']}</title>
    <meta name="description" content="{$pageData['meta_description']}">
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; line-height: 1.6; margin: 0; padding: 0; }
        .container { max-width: 800px; margin: 0 auto; padding: 0 20px; }
        .header { background: #f8f9fa; padding: 1rem 0; border-bottom: 1px solid #e9ecef; }
        .nav { display: flex; gap: 2rem; }
        .nav a { text-decoration: none; color: #333; }
        .nav a:hover { color: #007bff; }
        .blog-list { margin: 3rem 0; }
        .blog-item { border-bottom: 1px solid #e9ecef; padding: 2rem 0; }
        .blog-item h2 { margin-top: 0; }
        .blog-item .date { color: #6c757d; font-size: 0.9rem; }
        .footer { background: #f8f9fa; padding: 2rem 0; margin-top: 3rem; text-align: center; }
    </style>
</head>
<body>
    <header class="header">
        <div class="container">
            <nav class="nav">
                {for each item in menu}
                    <a href="/{item.slug}">{item.title}</a>
                {/for}
            </nav>
        </div>
    </header>
    
    <main>
        <div class="container">
            <h1>{$pageData['title']}</h1>
            <div class="blog-list">
                {for each entry in blogEntries}
                    <article class="blog-item">
                        <h2><a href="/blog/{entry.slug}">{entry.title}</a></h2>
                        <div class="date">{entry.date}</div>
                        <p>{entry.content}</p>
                        {if entry.featured}<span style="color: #007bff;">★ Featured</span>{/if}
                    </article>
                {/for}
            </div>
        </div>
    </main>
    
    <footer class="footer">
        <div class="container">
            <p>&copy; 2025 My Blog. Built with Birkly.</p>
        </div>
    </footer>
</body>
</html>
HTML;
        break;
        
    default:
        $templateHtml = <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{$pageData['title']}</title>
    <meta name="description" content="{$pageData['meta_description']}">
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; line-height: 1.6; margin: 0; padding: 0; }
        .container { max-width: 800px; margin: 0 auto; padding: 0 20px; }
        .header { background: #f8f9fa; padding: 1rem 0; border-bottom: 1px solid #e9ecef; }
        .nav { display: flex; gap: 2rem; }
        .nav a { text-decoration: none; color: #333; }
        .nav a:hover { color: #007bff; }
        .content { padding: 3rem 0; }
        .footer { background: #f8f9fa; padding: 2rem 0; margin-top: 3rem; text-align: center; }
    </style>
</head>
<body>
    <header class="header">
        <div class="container">
            <nav class="nav">
                {for each item in menu}
                    <a href="/{item.slug}">{item.title}</a>
                {/for}
            </nav>
        </div>
    </header>
    
    <main class="content">
        <div class="container">
            <h1>{$pageData['title']}</h1>
            <div>{$pageData['content']}</div>
        </div>
    </main>
    
    <footer class="footer">
        <div class="container">
            <p>&copy; 2025 My Blog. Built with Birkly.</p>
        </div>
    </footer>
</body>
</html>
HTML;
        break;
}

// Render the template
echo TemplateEngine::render($templateHtml, [
    'page' => $pageData,
    'menu' => $menu,
    'blogEntries' => $blogEntries ?? []
]); 