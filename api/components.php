<?php
/**
 * Components API — delegates storage to ContentEditor ComponentRegistry.
 */

$__cmsMcpComponentsFunctionsOnly = !empty($GLOBALS['__CMS_MCP_LOAD_FUNCTIONS_ONLY']);

if (!$__cmsMcpComponentsFunctionsOnly) {
    header('Content-Type: application/json');
}
error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING);
ini_set('display_errors', 0);

require_once __DIR__ . '/storage.php';
require_once __DIR__ . '/../fieldtypes/ContentEditor/components/ComponentRegistry.php';

use Birkly\FieldTypes\ContentEditor\ComponentRegistry;

$action = $_GET['action'] ?? $_POST['action'] ?? '';
$registry = new ComponentRegistry();

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

            foreach ($entry as $key => $value) {
                if (is_string($value) && strpos($value, 'data-component-slug="' . $componentSlug . '"') !== false) {
                    $entries[] = [
                        'collection' => basename($collectionDir),
                        'entry_id' => $entry['id'] ?? basename($entryFile, '.json'),
                        'slug' => $entry['slug'] ?? '',
                        'title' => $entry['title'] ?? $entry['name'] ?? 'Untitled'
                    ];
                    break;
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

            foreach ($entry as $key => $value) {
                if (is_string($value)) {
                    $hasComponent = (
                        strpos($value, 'data-component-slug="' . $componentSlug . '"') !== false ||
                        strpos($value, "data-component-slug='" . $componentSlug . "'") !== false ||
                        strpos($value, 'data-component-slug=&quot;' . $componentSlug . '&quot;') !== false ||
                        strpos($value, 'data-component-slug=&#34;' . $componentSlug . '&#34;') !== false
                    );

                    if ($hasComponent) {
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
    $hasComponent = (
        strpos($html, 'data-component-slug="' . $componentSlug . '"') !== false ||
        strpos($html, "data-component-slug='" . $componentSlug . "'") !== false ||
        strpos($html, 'data-component-slug=&quot;' . $componentSlug . '&quot;') !== false
    );

    if (!$hasComponent) {
        return $html;
    }

    $html = html_entity_decode($html, ENT_QUOTES | ENT_HTML5, 'UTF-8');

    $patterns = [
        '/(<div[^>]*data-component-slug="' . preg_quote($componentSlug, '/') . '"[^>]*>)/i',
        '/(<div[^>]*data-component-slug=\'' . preg_quote($componentSlug, '/') . '\'[^>]*>)/i'
    ];

    foreach ($patterns as $pattern) {
        $html = preg_replace_callback($pattern, function($matches) use ($componentSlug, $newComponent) {
            $oldElement = $matches[1];

            preg_match('/id=["\']([^"\']*)["\']/', $oldElement, $idMatch);
            $componentId = $idMatch[1] ?? 'component-' . $componentSlug . '-' . time();

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

if ($__cmsMcpComponentsFunctionsOnly) {
    return;
}

require_once __DIR__ . '/session.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/../core/csrf.php';

haru_secure_session_start();
requireAuth('manage_content');

// CLI tests have no HTTP body. Production requests never set this global.
$rawBody = (isset($GLOBALS['__components_test_raw_body']) && is_string($GLOBALS['__components_test_raw_body']))
    ? $GLOBALS['__components_test_raw_body']
    : file_get_contents('php://input');
$jsonBody = json_decode(is_string($rawBody) && $rawBody !== '' ? $rawBody : 'null', true);
if (!is_array($jsonBody)) {
    $jsonBody = [];
}

$mutatingActions = ['save', 'delete', 'update_entries'];
if (in_array($action, $mutatingActions, true)) {
    $csrf = (string) ($jsonBody['_csrf'] ?? $_POST['_csrf'] ?? '');
    if (!validate_csrf_token($csrf)) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Invalid CSRF token']);
        exit;
    }
}

try {
    switch ($action) {
        case 'list':
            echo json_encode(['success' => true, 'components' => $registry->listAll()]);
            break;

        case 'get':
            $slug = $_GET['slug'] ?? '';
            if (empty($slug)) {
                echo json_encode(['success' => false, 'error' => 'Slug is required']);
                break;
            }
            $component = $registry->get($slug);
            if ($component) {
                echo json_encode(['success' => true, 'component' => $component]);
            } else {
                echo json_encode(['success' => false, 'error' => 'Component not found']);
            }
            break;

        case 'save':
            $data = $jsonBody !== [] ? $jsonBody : $_POST;
            if (is_array($data)) {
                unset($data['_csrf']);
            }
            echo json_encode($registry->save(is_array($data) ? $data : []));
            break;

        case 'delete':
            $slug = $_GET['slug'] ?? $_POST['slug'] ?? ($jsonBody['slug'] ?? '');
            if (empty($slug)) {
                echo json_encode(['success' => false, 'error' => 'Slug is required']);
                break;
            }
            echo json_encode($registry->delete($slug));
            break;

        case 'render':
            $slug = $_GET['slug'] ?? $_POST['slug'] ?? '';
            $entryData = json_decode($_POST['entry_data'] ?? '{}', true);
            if (empty($slug)) {
                echo json_encode(['success' => false, 'error' => 'Slug is required']);
                break;
            }
            $component = $registry->get($slug);
            if (!$component) {
                echo json_encode(['success' => false, 'error' => 'Component not found']);
                break;
            }
            echo json_encode([
                'success' => true,
                'component' => $component,
                'entry_data' => $entryData
            ]);
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
            $data = $jsonBody !== [] ? $jsonBody : $_POST;
            $slug = is_array($data) ? ($data['slug'] ?? '') : '';
            if (empty($slug)) {
                echo json_encode(['success' => false, 'error' => 'Slug is required']);
                break;
            }
            $component = $registry->get($slug);
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
