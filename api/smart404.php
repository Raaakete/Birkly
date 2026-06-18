<?php
/**
 * Smart 404.html Generator API
 * Generates a smart 404.html file that can serve CMS content for direct URLs
 */

require_once '../core/csrf.php';
require_once '../api/auth.php';
require_once '../api/session.php';

header('Content-Type: application/json');

haru_secure_session_start();
requireAuth('manage_content');

// Verify CSRF token
if (!verifyCSRFToken()) {
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'CSRF token verification failed']);
    exit;
}

// Get request data
$input = json_decode(file_get_contents('php://input'), true);
$action = $input['action'] ?? '';

if ($action === 'generate_404') {
    generateSmart404($input);
} else {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Invalid action']);
}

function generateSmart404($data) {
    $siteBaseUrl = rtrim($data['site_base_url'], '/');
    $cmsApiUrl = rtrim($data['cms_api_url'], '/');
    $collections = $data['collections'] ?? [];
    
    // Validate inputs
    if (empty($siteBaseUrl) || empty($cmsApiUrl)) {
        echo json_encode(['status' => 'error', 'message' => 'Site base URL and CMS API URL are required']);
        return;
    }
    
    // Get collections data
    $collectionsData = [];
    if (in_array('all', $collections)) {
        // Get all collections
        $allCollections = getCollectionsList();
        foreach ($allCollections as $collection) {
            $collectionsData[$collection['name']] = getCollectionSlugs($collection['name']);
        }
    } else {
        // Get specific collections
        foreach ($collections as $collectionName) {
            $collectionsData[$collectionName] = getCollectionSlugs($collectionName);
        }
    }
    
    // Generate the smart 404.html content
    $htmlContent = generate404HTML($siteBaseUrl, $cmsApiUrl, $collectionsData);
    
    // Save the file
    $filename = 'smart404_' . date('Y-m-d_H-i-s') . '.html';
    $filepath = '../temp/' . $filename;
    
    // Create temp directory if it doesn't exist
    if (!is_dir('../temp')) {
        mkdir('../temp', 0755, true);
    }
    
    if (file_put_contents($filepath, $htmlContent)) {
        $downloadUrl = '/temp/index.php?file=' . $filename;
        echo json_encode([
            'status' => 'ok',
            'message' => 'Smart 404.html generated successfully',
            'data' => [
                'download_url' => $downloadUrl,
                'filename' => $filename,
                'collections_count' => count($collectionsData),
                'total_slugs' => array_sum(array_map('count', $collectionsData))
            ]
        ]);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Failed to save 404.html file']);
    }
}

function getCollectionsList() {
    $collectionsDir = '../content/collections/';
    $collections = [];
    
    if (is_dir($collectionsDir)) {
        $dirs = scandir($collectionsDir);
        foreach ($dirs as $dir) {
            if ($dir !== '.' && $dir !== '..' && is_dir($collectionsDir . $dir)) {
                $collectionFile = $collectionsDir . $dir . '/collection.json';
                if (file_exists($collectionFile)) {
                    $collectionData = json_decode(file_get_contents($collectionFile), true);
                    $entriesDir = $collectionsDir . $dir . '/entries/';
                    $entryCount = is_dir($entriesDir) ? count(scandir($entriesDir)) - 2 : 0; // -2 for . and ..
                    
                    $collections[] = [
                        'name' => $dir,
                        'title' => $collectionData['title'] ?? $dir,
                        'entry_count' => $entryCount
                    ];
                }
            }
        }
    }
    
    return $collections;
}

function getCollectionSlugs($collectionName) {
    $entriesDir = "../content/collections/{$collectionName}/entries/";
    $slugs = [];
    
    if (is_dir($entriesDir)) {
        $files = scandir($entriesDir);
        foreach ($files as $file) {
            if ($file !== '.' && $file !== '..' && pathinfo($file, PATHINFO_EXTENSION) === 'json') {
                $entryData = json_decode(file_get_contents($entriesDir . $file), true);
                if ($entryData) {
                    // Use explicit slug if available, otherwise use filename without extension
                    if (isset($entryData['slug'])) {
                        $slugs[] = $entryData['slug'];
                    } else {
                        $slug = pathinfo($file, PATHINFO_FILENAME);
                        $slugs[] = $slug;
                    }
                }
            }
        }
    }
    
    return $slugs;
}

function generate404HTML($siteBaseUrl, $cmsApiUrl, $collectionsData) {
    $collectionsJson = json_encode($collectionsData, JSON_PRETTY_PRINT);
    
    return <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title id="page-title">Page Not Found</title>
    <meta name="description" id="page-description" content="Page not found">
    <meta name="robots" content="noindex, nofollow">
    
    <!-- Open Graph Meta Tags -->
    <meta property="og:title" id="og-title" content="Page Not Found">
    <meta property="og:description" id="og-description" content="Page not found">
    <meta property="og:type" content="website">
    <meta property="og:url" id="og-url" content="">
    
    <!-- Twitter Card Meta Tags -->
    <meta name="twitter:card" content="summary">
    <meta name="twitter:title" id="twitter-title" content="Page Not Found">
    <meta name="twitter:description" id="twitter-description" content="Page not found">
    
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            line-height: 1.6;
            margin: 0;
            padding: 20px;
            background-color: #f8f9fa;
        }
        .container {
            max-width: 800px;
            margin: 0 auto;
            background: white;
            padding: 40px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .loading {
            text-align: center;
            padding: 40px;
            color: #666;
        }
        .error {
            text-align: center;
            padding: 40px;
            color: #dc3545;
        }
        .content {
            margin-top: 20px;
        }
        .back-link {
            margin-top: 20px;
            padding-top: 20px;
            border-top: 1px solid #eee;
        }
        .back-link a {
            color: #007bff;
            text-decoration: none;
        }
        .back-link a:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>
    <div class="container">
        <div id="content">
            <div class="loading">
                <h1>Loading...</h1>
                <p>Please wait while we find the content you're looking for.</p>
            </div>
        </div>
    </div>

    <script>
        // CMS data embedded in the file
        const CMS_DATA = {$collectionsJson};
        const SITE_BASE_URL = '{$siteBaseUrl}';
        const CMS_API_URL = '{$cmsApiUrl}';
        
        // URL parsing and content serving logic
        function parseURL(path) {
            const parts = path.split('/').filter(p => p);
            if (parts.length >= 2) {
                return {
                    collection: parts[0],
                    slug: parts[1]
                };
            }
            return null;
        }
        
        function isValidSlug(collection, slug) {
            return CMS_DATA[collection] && CMS_DATA[collection].includes(slug);
        }
        
        async function fetchContent(collection, slug) {
            try {
                // Try to fetch by slug first
                let response = await fetch(\`\${CMS_API_URL}/public.php?action=get_entry&collection=\${collection}&slug=\${slug}\`);
                let data = await response.json();
                
                if (data.status === 'ok' && data.data.entry) {
                    return data.data.entry;
                }
                
                // If not found by slug, try to fetch all entries and find by filename
                response = await fetch(\`\${CMS_API_URL}/public.php?action=list_entries&collection=\${collection}\`);
                data = await response.json();
                
                if (data.status === 'ok' && data.data.entries) {
                    // Find entry by slug (which might be the filename)
                    const entry = data.data.entries.find(entry => {
                        return entry.slug === slug || entry.id === slug || entry.title?.toLowerCase().replace(/[^a-z0-9]+/g, '-') === slug;
                    });
                    return entry || null;
                }
                
                return null;
            } catch (error) {
                console.error('Error fetching content:', error);
                return null;
            }
        }
        
        function updateSEO(content) {
            const title = content.title || content.name || 'Content';
            const description = content.description || content.content || content.bio || 'Content from Birkly';
            
            // Update page title
            document.title = title;
            document.getElementById('page-title').textContent = title;
            
            // Update meta description
            document.getElementById('page-description').content = description;
            
            // Update Open Graph tags
            document.getElementById('og-title').content = title;
            document.getElementById('og-description').content = description;
            document.getElementById('og-url').content = window.location.href;
            
            // Update Twitter Card tags
            document.getElementById('twitter-title').content = title;
            document.getElementById('twitter-description').content = description;
            
            // Update robots meta tag for valid content
            document.querySelector('meta[name="robots"]').content = 'index, follow';
        }
        
        function renderContent(content, collection) {
            const contentDiv = document.getElementById('content');
            
            // Create content based on collection type
            let html = '';
            
            switch(collection) {
                case 'blog':
                    html = \`
                        <h1>\${content.title || 'Blog Post'}</h1>
                        \${content.date ? \`<p class="date"><small>\${content.date}</small></p>\` : ''}
                        \${content.author ? \`<p class="author"><small>By \${content.author}</small></p>\` : ''}
                        <div class="content">\${content.content || ''}</div>
                    \`;
                    break;
                    
                case 'users':
                    html = \`
                        <h1>\${content.name || content.title || 'User Profile'}</h1>
                        \${content.role ? \`<p class="role"><strong>Role:</strong> \${content.role}</p>\` : ''}
                        \${content.bio ? \`<div class="bio">\${content.bio}</div>\` : ''}
                        \${content.email ? \`<p class="email"><strong>Email:</strong> \${content.email}</p>\` : ''}
                    \`;
                    break;
                    
                case 'portfolio':
                    html = \`
                        <h1>\${content.title || 'Portfolio Project'}</h1>
                        \${content.description ? \`<div class="description">\${content.description}</div>\` : ''}
                        \${content.category ? \`<p class="category"><strong>Category:</strong> \${content.category}</p>\` : ''}
                        \${content.date ? \`<p class="date"><small>\${content.date}</small></p>\` : ''}
                    \`;
                    break;
                    
                default:
                    // Generic template for any collection
                    html = \`
                        <h1>\${content.title || content.name || 'Content'}</h1>
                        \${content.content ? \`<div class="content">\${content.content}</div>\` : ''}
                        \${content.description ? \`<div class="description">\${content.description}</div>\` : ''}
                    \`;
            }
            
            html += \`
                <div class="back-link">
                    <a href="\${SITE_BASE_URL}">← Back to Home</a>
                </div>
            \`;
            
            contentDiv.innerHTML = html;
        }
        
        function showError(message) {
            const contentDiv = document.getElementById('content');
            contentDiv.innerHTML = \`
                <div class="error">
                    <h1>Page Not Found</h1>
                    <p>\${message}</p>
                    <div class="back-link">
                        <a href="\${SITE_BASE_URL}">← Back to Home</a>
                    </div>
                </div>
            \`;
        }
        
        async function serveContent() {
            const path = window.location.pathname;
            const urlData = parseURL(path);
            
            if (!urlData) {
                showError('Invalid URL format. Expected format: /collection/slug');
                return;
            }
            
            const { collection, slug } = urlData;
            
            if (!isValidSlug(collection, slug)) {
                showError(\`Content not found: \${collection}/\${slug}\`);
                return;
            }
            
            const content = await fetchContent(collection, slug);
            
            if (content) {
                updateSEO(content);
                renderContent(content, collection);
            } else {
                showError(\`Failed to load content: \${collection}/\${slug}\`);
            }
        }
        
        // Initialize on page load
        document.addEventListener('DOMContentLoaded', serveContent);
    </script>
</body>
</html>
HTML;
}
?> 