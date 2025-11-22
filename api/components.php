<?php
/**
 * Components API
 * Handles CRUD operations for content editor components
 */

header('Content-Type: application/json');
error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING);
ini_set('display_errors', 0);

require_once __DIR__ . '/storage.php';

$action = $_GET['action'] ?? $_POST['action'] ?? '';

$componentsDir = __DIR__ . '/../content/components';
if (!is_dir($componentsDir)) {
    mkdir($componentsDir, 0755, true);
}

/**
 * List all components
 */
function listComponents() {
    global $componentsDir;
    
    $components = [];
    if (is_dir($componentsDir)) {
        $dirs = array_filter(glob($componentsDir . '/*'), 'is_dir');
        foreach ($dirs as $dir) {
            $slug = basename($dir);
            $metaFile = $dir . '/component.json';
            
            if (file_exists($metaFile)) {
                $meta = json_decode(file_get_contents($metaFile), true);
                $components[] = [
                    'slug' => $slug,
                    'name' => $meta['name'] ?? $slug,
                    'description' => $meta['description'] ?? '',
                    'category' => $meta['category'] ?? 'uncategorized',
                    'created_at' => $meta['created_at'] ?? '',
                    'updated_at' => $meta['updated_at'] ?? ''
                ];
            }
        }
    }
    
    // Sort by name
    usort($components, function($a, $b) {
        return strcmp($a['name'], $b['name']);
    });
    
    return $components;
}

/**
 * Get a single component
 */
function getComponent($slug) {
    global $componentsDir;
    
    $componentDir = $componentsDir . '/' . $slug;
    if (!is_dir($componentDir)) {
        return null;
    }
    
    $metaFile = $componentDir . '/component.json';
    $templateFile = $componentDir . '/template.html';
    $styleFile = $componentDir . '/style.css';
    $scriptFile = $componentDir . '/script.js';
    
    $component = [
        'slug' => $slug,
        'name' => '',
        'description' => '',
        'category' => 'uncategorized',
        'template' => '',
        'style' => '',
        'script' => '',
        'auto_scope_css' => true,
        'execute_js' => true,
        'created_at' => '',
        'updated_at' => ''
    ];
    
    if (file_exists($metaFile)) {
        $meta = json_decode(file_get_contents($metaFile), true);
        $component = array_merge($component, $meta);
    }
    
    if (file_exists($templateFile)) {
        $component['template'] = file_get_contents($templateFile);
    }
    
    if (file_exists($styleFile)) {
        $component['style'] = file_get_contents($styleFile);
    }
    
    if (file_exists($scriptFile)) {
        $component['script'] = file_get_contents($scriptFile);
    }
    
    return $component;
}

/**
 * Save a component
 */
function saveComponent($data) {
    global $componentsDir;
    
    $slug = $data['slug'] ?? '';
    if (empty($slug)) {
        return ['success' => false, 'error' => 'Slug is required'];
    }
    
    // Sanitize slug
    $slug = preg_replace('/[^a-z0-9-]/', '', strtolower($slug));
    if (empty($slug)) {
        return ['success' => false, 'error' => 'Invalid slug'];
    }
    
    $componentDir = $componentsDir . '/' . $slug;
    if (!is_dir($componentDir)) {
        mkdir($componentDir, 0755, true);
    }
    
    // Check if component exists
    $isNew = !file_exists($componentDir . '/component.json');
    
    // Save metadata
    $meta = [
        'slug' => $slug,
        'name' => $data['name'] ?? $slug,
        'description' => $data['description'] ?? '',
        'category' => $data['category'] ?? 'uncategorized',
        'auto_scope_css' => isset($data['auto_scope_css']) ? (bool)$data['auto_scope_css'] : true,
        'execute_js' => isset($data['execute_js']) ? (bool)$data['execute_js'] : true,
        'updated_at' => date('Y-m-d H:i:s')
    ];
    
    if ($isNew) {
        $meta['created_at'] = date('Y-m-d H:i:s');
    } else {
        $existingMeta = json_decode(file_get_contents($componentDir . '/component.json'), true);
        $meta['created_at'] = $existingMeta['created_at'] ?? date('Y-m-d H:i:s');
    }
    
    file_put_contents($componentDir . '/component.json', json_encode($meta, JSON_PRETTY_PRINT));
    
    // Save template
    $template = $data['template'] ?? '';
    file_put_contents($componentDir . '/template.html', $template);
    
    // Save style (optional)
    $style = $data['style'] ?? '';
    if (!empty($style)) {
        file_put_contents($componentDir . '/style.css', $style);
    } elseif (file_exists($componentDir . '/style.css')) {
        unlink($componentDir . '/style.css');
    }
    
    // Save script (optional)
    $script = $data['script'] ?? '';
    if (!empty($script)) {
        file_put_contents($componentDir . '/script.js', $script);
    } elseif (file_exists($componentDir . '/script.js')) {
        unlink($componentDir . '/script.js');
    }
    
    return ['success' => true, 'slug' => $slug];
}

/**
 * Delete a component
 */
function deleteComponent($slug) {
    global $componentsDir;
    
    $componentDir = $componentsDir . '/' . $slug;
    if (!is_dir($componentDir)) {
        return ['success' => false, 'error' => 'Component not found'];
    }
    
    // Delete all files
    $files = glob($componentDir . '/*');
    foreach ($files as $file) {
        if (is_file($file)) {
            unlink($file);
        }
    }
    
    // Delete directory
    rmdir($componentDir);
    
    return ['success' => true];
}

/**
 * Find all entries using a component
 */
function findEntriesUsingComponent($componentSlug) {
    $entries = [];
    $collectionsDir = __DIR__ . '/../content/collections';
    
    if (!is_dir($collectionsDir)) {
        return $entries;
    }
    
    $collections = array_filter(glob($collectionsDir . '/*'), 'is_dir');
    
    foreach ($collections as $collectionDir) {
        $entriesDir = $collectionDir . '/entries';
        if (!is_dir($entriesDir)) {
            continue;
        }
        
        $entryFiles = glob($entriesDir . '/*.json');
        foreach ($entryFiles as $entryFile) {
            $entry = json_decode(file_get_contents($entryFile), true);
            if (!$entry) continue;
            
            // Check all fields for component usage
            foreach ($entry as $key => $value) {
                if (is_string($value) && strpos($value, 'data-component-slug="' . $componentSlug . '"') !== false) {
                    $entries[] = [
                        'collection' => basename($collectionDir),
                        'entry_id' => $entry['id'] ?? basename($entryFile, '.json'),
                        'slug' => $entry['slug'] ?? '',
                        'title' => $entry['title'] ?? $entry['name'] ?? 'Untitled'
                    ];
                    break; // Found in this entry, no need to check other fields
                }
            }
        }
    }
    
    return $entries;
}

/**
 * Update component in all entries
 */
function updateComponentInEntries($componentSlug, $newComponent) {
    $updated = 0;
    $errors = [];
    $collectionsDir = __DIR__ . '/../content/collections';
    
    if (!is_dir($collectionsDir)) {
        return ['success' => false, 'error' => 'Collections directory not found'];
    }
    
    $collections = array_filter(glob($collectionsDir . '/*'), 'is_dir');
    
    foreach ($collections as $collectionDir) {
        $entriesDir = $collectionDir . '/entries';
        if (!is_dir($entriesDir)) {
            continue;
        }
        
        $entryFiles = glob($entriesDir . '/*.json');
        foreach ($entryFiles as $entryFile) {
            $entry = json_decode(file_get_contents($entryFile), true);
            if (!$entry) continue;
            
            $modified = false;
            
            // Update component in all fields
            foreach ($entry as $key => $value) {
                if (is_string($value)) {
                    // Check for component slug with both double and single quotes, and escaped versions
                    $hasComponent = (
                        strpos($value, 'data-component-slug="' . $componentSlug . '"') !== false ||
                        strpos($value, "data-component-slug='" . $componentSlug . "'") !== false ||
                        strpos($value, 'data-component-slug=&quot;' . $componentSlug . '&quot;') !== false ||
                        strpos($value, 'data-component-slug=&#34;' . $componentSlug . '&#34;') !== false
                    );
                    
                    if ($hasComponent) {
                        // Update the component HTML
                        $updatedValue = updateComponentInHTML($value, $componentSlug, $newComponent);
                        if ($updatedValue !== $value) {
                            $entry[$key] = $updatedValue;
                            $modified = true;
                        }
                    }
                }
            }
            
            if ($modified) {
                if (file_put_contents($entryFile, json_encode($entry, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES))) {
                    $updated++;
                } else {
                    $errors[] = basename($entryFile);
                }
            }
        }
    }
    
    return [
        'success' => true,
        'updated' => $updated,
        'errors' => $errors
    ];
}

/**
 * Update component data attributes in HTML
 */
function updateComponentInHTML($html, $componentSlug, $newComponent) {
    // Check if component exists in HTML (handle both regular and escaped versions)
    $hasComponent = (
        strpos($html, 'data-component-slug="' . $componentSlug . '"') !== false ||
        strpos($html, "data-component-slug='" . $componentSlug . "'") !== false ||
        strpos($html, 'data-component-slug=&quot;' . $componentSlug . '&quot;') !== false
    );
    
    if (!$hasComponent) {
        return $html; // Component not found, return unchanged
    }
    
    // Decode HTML entities first to handle escaped HTML
    $html = html_entity_decode($html, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    
    // Find all component elements with this slug (handle multiple occurrences)
    // Match with both double and single quotes
    $patterns = [
        '/(<div[^>]*data-component-slug="' . preg_quote($componentSlug, '/') . '"[^>]*>)/i',
        '/(<div[^>]*data-component-slug=\'' . preg_quote($componentSlug, '/') . '\'[^>]*>)/i'
    ];
    
    foreach ($patterns as $pattern) {
        $html = preg_replace_callback($pattern, function($matches) use ($componentSlug, $newComponent) {
            $oldElement = $matches[1];
            
            // Extract existing ID if present (handle both quote styles)
            preg_match('/id=["\']([^"\']*)["\']/', $oldElement, $idMatch);
            $componentId = $idMatch[1] ?? 'component-' . $componentSlug . '-' . time();
            
            // Build new element with updated data attributes
            $newElement = '<div class="content-editor-component" data-component-slug="' . htmlspecialchars($componentSlug, ENT_QUOTES, 'UTF-8') . '"';
            $newElement .= ' data-component-name="' . htmlspecialchars($newComponent['name'] ?? '', ENT_QUOTES, 'UTF-8') . '"';
            $newElement .= ' data-component-template="' . htmlspecialchars($newComponent['template'] ?? '', ENT_QUOTES, 'UTF-8') . '"';
            $newElement .= ' data-component-style="' . htmlspecialchars($newComponent['style'] ?? '', ENT_QUOTES, 'UTF-8') . '"';
            $newElement .= ' data-component-script="' . htmlspecialchars($newComponent['script'] ?? '', ENT_QUOTES, 'UTF-8') . '"';
            $newElement .= ' data-component-auto-scope="' . ($newComponent['auto_scope_css'] !== false ? 'true' : 'false') . '"';
            $newElement .= ' data-component-execute-js="' . ($newComponent['execute_js'] !== false ? 'true' : 'false') . '"';
            $newElement .= ' data-component-updated-at="' . htmlspecialchars($newComponent['updated_at'] ?? '', ENT_QUOTES, 'UTF-8') . '"';
            $newElement .= ' id="' . htmlspecialchars($componentId, ENT_QUOTES, 'UTF-8') . '"';
            $newElement .= '>';
            
            return $newElement;
        }, $html);
    }
    
    return $html;
}

/**
 * Render a component with entry data
 */
function renderComponent($slug, $entryData = []) {
    $component = getComponent($slug);
    if (!$component) {
        return ['success' => false, 'error' => 'Component not found'];
    }
    
    // This will be handled by the template engine on the frontend
    // For now, just return the component data
    return [
        'success' => true,
        'component' => $component,
        'entry_data' => $entryData
    ];
}

// Handle requests
try {
    switch ($action) {
        case 'list':
            echo json_encode(['success' => true, 'components' => listComponents()]);
            break;
            
        case 'get':
            $slug = $_GET['slug'] ?? '';
            if (empty($slug)) {
                echo json_encode(['success' => false, 'error' => 'Slug is required']);
                break;
            }
            $component = getComponent($slug);
            if ($component) {
                echo json_encode(['success' => true, 'component' => $component]);
            } else {
                echo json_encode(['success' => false, 'error' => 'Component not found']);
            }
            break;
            
        case 'save':
            $data = json_decode(file_get_contents('php://input'), true);
            if (!$data) {
                $data = $_POST;
            }
            echo json_encode(saveComponent($data));
            break;
            
        case 'delete':
            $slug = $_GET['slug'] ?? $_POST['slug'] ?? '';
            if (empty($slug)) {
                echo json_encode(['success' => false, 'error' => 'Slug is required']);
                break;
            }
            echo json_encode(deleteComponent($slug));
            break;
            
        case 'render':
            $slug = $_GET['slug'] ?? $_POST['slug'] ?? '';
            $entryData = json_decode($_POST['entry_data'] ?? '{}', true);
            if (empty($slug)) {
                echo json_encode(['success' => false, 'error' => 'Slug is required']);
                break;
            }
            echo json_encode(renderComponent($slug, $entryData));
            break;
            
        case 'find_entries':
            $slug = $_GET['slug'] ?? $_POST['slug'] ?? '';
            if (empty($slug)) {
                echo json_encode(['success' => false, 'error' => 'Slug is required']);
                break;
            }
            $entries = findEntriesUsingComponent($slug);
            echo json_encode(['success' => true, 'entries' => $entries]);
            break;
            
        case 'update_entries':
            $data = json_decode(file_get_contents('php://input'), true);
            if (!$data) {
                $data = $_POST;
            }
            $slug = $data['slug'] ?? '';
            if (empty($slug)) {
                echo json_encode(['success' => false, 'error' => 'Slug is required']);
                break;
            }
            $component = getComponent($slug);
            if (!$component) {
                echo json_encode(['success' => false, 'error' => 'Component not found']);
                break;
            }
            echo json_encode(updateComponentInEntries($slug, $component));
            break;
            
        default:
            echo json_encode(['success' => false, 'error' => 'Invalid action']);
            break;
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}

