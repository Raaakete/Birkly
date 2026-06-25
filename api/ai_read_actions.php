<?php
/**
 * Shared read-action executor for AI chat and MCP (P3-M4).
 */

require_once __DIR__ . '/../core/ai_permissions.php';
require_once __DIR__ . '/ai_helpers.php';

function executeReadAction($actionData, $aiUser) {
    require_once __DIR__ . '/../core/ai_permissions.php';

    $actionType = $actionData['type'] ?? '';
    $denied = birkly_ai_enforce_action_permission($aiUser, $actionType);
    if ($denied !== null) {
        return $denied;
    }

    require_once __DIR__ . '/storage.php';
    
    $actionPayload = $actionData['data'] ?? [];
    
    $contentStorage = new Storage('../content');
    
    if ($actionType === 'read_entry') {
        $collection = $actionPayload['collection'] ?? '';
        $slug = $actionPayload['slug'] ?? '';
        $entryId = $actionPayload['id'] ?? '';
        
        if (empty($collection)) {
            return ['success' => false, 'message' => 'Collection is required'];
        }
        
        // Find collection directory
        $collectionsDir = '../content/collections';
        $collectionDir = null;
        if (is_dir($collectionsDir)) {
            $collections = array_filter(glob($collectionsDir . '/*'), 'is_dir');
            foreach ($collections as $dir) {
                $collectionSlug = basename($dir);
                $collectionFile = $dir . '/collection.json';
                if (file_exists($collectionFile)) {
                    $collectionMeta = json_decode(file_get_contents($collectionFile), true);
                    if ($collectionMeta && ($collectionMeta['slug'] ?? $collectionSlug) === $collection) {
                        $collectionDir = $dir;
                        break;
                    }
                }
            }
        }
        
        if (!$collectionDir) {
            return ['success' => false, 'message' => 'Collection not found: ' . $collection];
        }
        
        // Find entry by slug or ID
        $entry = null;
        if ($slug) {
            $entries = $contentStorage->list("collections/" . basename($collectionDir) . "/entries");
            foreach ($entries as $entryFile) {
                $entryData = $contentStorage->read("collections/" . basename($collectionDir) . "/entries/" . $entryFile);
                if ($entryData && isset($entryData['slug']) && $entryData['slug'] === $slug) {
                    $entry = $entryData;
                    break;
                }
            }
        } elseif ($entryId) {
            $entry = $contentStorage->read("collections/" . basename($collectionDir) . "/entries/" . $entryId . ".json");
        }
        
        if (!$entry) {
            return ['success' => false, 'message' => 'Entry not found'];
        }
        
        return ['success' => true, 'data' => ['entry' => $entry]];
        
    } elseif ($actionType === 'list_entries') {
        $collection = $actionPayload['collection'] ?? '';
        $limit = $actionPayload['limit'] ?? 100;
        
        if (empty($collection)) {
            return ['success' => false, 'message' => 'Collection is required'];
        }
        
        // Find collection directory
        $collectionsDir = '../content/collections';
        $collectionDir = null;
        if (is_dir($collectionsDir)) {
            $collections = array_filter(glob($collectionsDir . '/*'), 'is_dir');
            foreach ($collections as $dir) {
                $collectionSlug = basename($dir);
                $collectionFile = $dir . '/collection.json';
                if (file_exists($collectionFile)) {
                    $collectionMeta = json_decode(file_get_contents($collectionFile), true);
                    if ($collectionMeta && ($collectionMeta['slug'] ?? $collectionSlug) === $collection) {
                        $collectionDir = $dir;
                        break;
                    }
                }
            }
        }
        
        if (!$collectionDir) {
            return ['success' => false, 'message' => 'Collection not found: ' . $collection];
        }
        
        // List entries
        $entries = $contentStorage->list("collections/" . basename($collectionDir) . "/entries");
        $entryData = [];
        $count = 0;
        foreach ($entries as $entryFile) {
            if ($count >= $limit) break;
            $data = $contentStorage->read("collections/" . basename($collectionDir) . "/entries/" . $entryFile);
            if ($data) {
                $entryData[] = $data;
                $count++;
            }
        }
        
        // Include collection slug in the result for easier matching
        $result = ['success' => true, 'data' => ['entries' => $entryData, 'count' => count($entryData), 'collection' => $collection]];
        error_log("executeReadAction list_entries: Returning result with collection '$collection', entry count: " . count($entryData));
        return $result;
        
    } elseif ($actionType === 'list_collections') {
        // List all collections
        $collectionsDir = '../content/collections';
        $collections = [];
        
        if (is_dir($collectionsDir)) {
            $collectionDirs = array_filter(glob($collectionsDir . '/*'), 'is_dir');
            foreach ($collectionDirs as $dir) {
                $collectionFile = $dir . '/collection.json';
                if (file_exists($collectionFile)) {
                    $collectionMeta = json_decode(file_get_contents($collectionFile), true);
                    if ($collectionMeta) {
                        $collections[] = [
                            'slug' => $collectionMeta['slug'] ?? basename($dir),
                            'name' => $collectionMeta['name'] ?? $collectionMeta['title'] ?? basename($dir),
                            'description' => $collectionMeta['description'] ?? '',
                            'fields' => $collectionMeta['fields'] ?? []
                        ];
                    }
                }
            }
        }
        
        return ['success' => true, 'data' => ['collections' => $collections, 'count' => count($collections)]];
        
    } elseif ($actionType === 'list_media') {
        // List media files
        require_once __DIR__ . '/storage.php';
        $mediaDir = __DIR__ . '/../media';
        $media = [];
        
        if (is_dir($mediaDir)) {
            $mainMetadataPath = $mediaDir . '/.metadata.json';
            if (file_exists($mainMetadataPath)) {
                $metadata = json_decode(file_get_contents($mainMetadataPath), true) ?: [];
                foreach ($metadata as $id => $item) {
                    $media[] = [
                        'id' => $id,
                        'name' => $item['name'] ?? '',
                        'type' => $item['type'] ?? '',
                        'size' => $item['size'] ?? 0,
                        'url' => '/media/' . ($item['folder'] ?? '') . ($item['folder'] ? '/' : '') . $id . '.' . ($item['ext'] ?? ''),
                        'folder' => $item['folder'] ?? ''
                    ];
                }
            }
        }
        
        return ['success' => true, 'data' => ['media' => $media, 'count' => count($media)]];
        
    } elseif ($actionType === 'read_settings') {
        // Read system settings
        require_once __DIR__ . '/storage.php';
        $settingsStorage = new Storage('../settings');
        $settings = [];
        
        $categories = ['site', 'admin', 'media', 'security'];
        foreach ($categories as $cat) {
            $settings[$cat] = $settingsStorage->read("$cat.json") ?? [];
        }
        
        return ['success' => true, 'data' => ['settings' => $settings]];
        
    } elseif ($actionType === 'list_users') {
        // List users (read-only, no passwords)
        require_once __DIR__ . '/storage.php';
        $usersStorage = new Storage('../users');
        $users = [];
        $userFiles = glob('../users/*.json');
        
        foreach ($userFiles as $file) {
            $user = json_decode(file_get_contents($file), true);
            if ($user) {
                $users[] = birkly_scrub_user_for_ai($user);
            }
        }
        
        return ['success' => true, 'data' => ['users' => $users, 'count' => count($users)]];
        
    } elseif ($actionType === 'read_user') {
        // Read a specific user
        require_once __DIR__ . '/storage.php';
        $usersStorage = new Storage('../users');
        $userId = $actionPayload['id'] ?? $actionPayload['email'] ?? '';
        
        if (empty($userId)) {
            return ['success' => false, 'message' => 'User ID or email is required'];
        }
        
        // Try to find user by ID or email
        $userFiles = glob('../users/*.json');
        $user = null;
        foreach ($userFiles as $file) {
            $userData = json_decode(file_get_contents($file), true);
            if ($userData && ($userData['id'] === $userId || $userData['email'] === $userId)) {
                $user = birkly_scrub_user_for_ai($userData);
                break;
            }
            }
        }
        
        if (!$user) {
            return ['success' => false, 'message' => 'User not found'];
        }
        
        return ['success' => true, 'data' => ['user' => $user]];
        
    } elseif ($actionType === 'read_collection') {
        // Read a specific collection
        $collectionSlug = $actionPayload['slug'] ?? '';
        if (empty($collectionSlug)) {
            return ['success' => false, 'message' => 'Collection slug is required'];
        }
        
        $collectionFile = '../content/collections/' . $collectionSlug . '/collection.json';
        if (!file_exists($collectionFile)) {
            return ['success' => false, 'message' => 'Collection not found'];
        }
        
        $collection = json_decode(file_get_contents($collectionFile), true);
        if (!$collection) {
            return ['success' => false, 'message' => 'Failed to read collection'];
        }
        
        // Also load schema if it exists
        $schemaFile = '../content/collections/' . $collectionSlug . '/schema.json';
        if (file_exists($schemaFile)) {
            $schema = json_decode(file_get_contents($schemaFile), true);
            if ($schema) {
                $collection['fields'] = $schema['fields'] ?? [];
            }
        }
        
        return ['success' => true, 'data' => ['collection' => $collection]];
        
    } elseif ($actionType === 'read_media') {
        // Read a specific media file
        $mediaId = $actionPayload['id'] ?? '';
        if (empty($mediaId)) {
            return ['success' => false, 'message' => 'Media ID is required'];
        }
        
        $mediaDir = __DIR__ . '/../media';
        $mainMetadataPath = $mediaDir . '/.metadata.json';
        if (!file_exists($mainMetadataPath)) {
            return ['success' => false, 'message' => 'Media not found'];
        }
        
        $metadata = json_decode(file_get_contents($mainMetadataPath), true) ?: [];
        if (!isset($metadata[$mediaId])) {
            return ['success' => false, 'message' => 'Media not found'];
        }
        
        $media = $metadata[$mediaId];
        $media['id'] = $mediaId;
        $media['url'] = '/media/' . ($media['folder'] ?? '') . ($media['folder'] ? '/' : '') . $mediaId . '.' . ($media['ext'] ?? '');
        
        return ['success' => true, 'data' => ['media' => $media]];
        
    } elseif ($actionType === 'list_pages') {
        // List pages
        require_once __DIR__ . '/storage.php';
        $contentStorage = new Storage('../content');
        $pagesDir = '../content/pages';
        $pages = [];
        
        if (is_dir($pagesDir)) {
            $pageFiles = glob($pagesDir . '/*.json');
            foreach ($pageFiles as $file) {
                $page = json_decode(file_get_contents($file), true);
                if ($page) {
                    $pages[] = $page;
                }
            }
        }
        
        return ['success' => true, 'data' => ['pages' => $pages, 'count' => count($pages)]];
        
    } elseif ($actionType === 'read_page') {
        // Read a specific page
        $pageSlug = $actionPayload['slug'] ?? $actionPayload['id'] ?? '';
        if (empty($pageSlug)) {
            return ['success' => false, 'message' => 'Page slug or ID is required'];
        }
        
        $pageFile = '../content/pages/' . $pageSlug . '.json';
        if (!file_exists($pageFile)) {
            // Try to find by slug in all pages
            $pagesDir = '../content/pages';
            if (is_dir($pagesDir)) {
                $pageFiles = glob($pagesDir . '/*.json');
                foreach ($pageFiles as $file) {
                    $page = json_decode(file_get_contents($file), true);
                    if ($page && ($page['slug'] === $pageSlug || $page['id'] === $pageSlug)) {
                        return ['success' => true, 'data' => ['page' => $page]];
                    }
                }
            }
            return ['success' => false, 'message' => 'Page not found'];
        }
        
        $page = json_decode(file_get_contents($pageFile), true);
        if (!$page) {
            return ['success' => false, 'message' => 'Failed to read page'];
        }
        
        return ['success' => true, 'data' => ['page' => $page]];
        
    } elseif ($actionType === 'list_activity') {
        // List activity logs
        require_once __DIR__ . '/activity.php';
        $limit = $actionPayload['limit'] ?? 50;
        $activityFile = '../data/activity.json';
        
        $activities = [];
        if (file_exists($activityFile)) {
            $allActivities = json_decode(file_get_contents($activityFile), true) ?: [];
            $activities = array_slice(array_reverse($allActivities), 0, $limit);
        }
        
        return ['success' => true, 'data' => ['activities' => $activities, 'count' => count($activities)]];
        
    } elseif ($actionType === 'list_roles') {
        // List roles
        require_once __DIR__ . '/storage.php';
        $settingsStorage = new Storage('../settings');
        $roles = $settingsStorage->read('roles.json') ?: [];
        
        return ['success' => true, 'data' => ['roles' => $roles, 'count' => count($roles)]];
        
    } elseif ($actionType === 'read_role') {
        // Read a specific role
        require_once __DIR__ . '/storage.php';
        $settingsStorage = new Storage('../settings');
        $roles = $settingsStorage->read('roles.json') ?: [];
        $roleId = $actionPayload['id'] ?? $actionPayload['name'] ?? '';
        
        if (empty($roleId)) {
            return ['success' => false, 'message' => 'Role ID or name is required'];
        }
        
        foreach ($roles as $role) {
            if (($role['id'] ?? '') === $roleId || ($role['name'] ?? '') === $roleId) {
                return ['success' => true, 'data' => ['role' => $role]];
            }
        }
        
        return ['success' => false, 'message' => 'Role not found'];
        
    } elseif ($actionType === 'list_plugins') {
        // List plugins
        require_once __DIR__ . '/../core/plugins.php';
        $pluginsDir = '../plugins';
        $plugins = [];
        
        if (is_dir($pluginsDir)) {
            $pluginDirs = array_filter(glob($pluginsDir . '/*'), 'is_dir');
            foreach ($pluginDirs as $dir) {
                $pluginFile = $dir . '/plugin.json';
                if (file_exists($pluginFile)) {
                    $plugin = json_decode(file_get_contents($pluginFile), true);
                    if ($plugin) {
                        $plugin['id'] = basename($dir);
                        $plugins[] = $plugin;
                    }
                }
            }
        }
        
        return ['success' => true, 'data' => ['plugins' => $plugins, 'count' => count($plugins)]];
    }
    
    return ['success' => false, 'message' => 'Unknown read action type: ' . $actionType];
}

