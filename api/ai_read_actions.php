<?php
/**
 * Shared read-action executor for AI chat and MCP (P3-M4).
 */

require_once __DIR__ . '/../core/ai_permissions.php';
require_once __DIR__ . '/ai_helpers.php';
require_once __DIR__ . '/../core/content_resolve.php';

function executeReadAction($actionData, $aiUser) {
    require_once __DIR__ . '/../core/ai_permissions.php';

    $actionType = $actionData['type'] ?? '';
    $denied = birkly_ai_enforce_action_permission($aiUser, $actionType);
    if ($denied !== null) {
        return $denied;
    }

    require_once __DIR__ . '/storage.php';
    
    $actionPayload = $actionData['data'] ?? [];
    $birklyRoot = dirname(__DIR__);
    
    $contentStorage = new Storage($birklyRoot . '/content');
    
    if ($actionType === 'read_entry') {
        $collection = $actionPayload['collection'] ?? '';
        $slug = $actionPayload['slug'] ?? '';
        $entryId = $actionPayload['id'] ?? '';
        
        if (empty($collection)) {
            return ['success' => false, 'message' => 'Collection is required'];
        }

        $collectionDir = birkly_content_resolve_collection_dir($contentStorage, $collection);
        if (!$collectionDir) {
            return ['success' => false, 'message' => 'Collection not found: ' . $collection];
        }

        // Find entry by slug or ID
        $entry = null;
        if ($slug) {
            $entries = $contentStorage->list('collections/' . $collectionDir . '/entries');
            foreach ($entries as $entryFile) {
                $entryData = $contentStorage->read('collections/' . $collectionDir . '/entries/' . $entryFile);
                if ($entryData && isset($entryData['slug']) && $entryData['slug'] === $slug) {
                    $entry = $entryData;
                    break;
                }
            }
        } elseif ($entryId) {
            $entry = $contentStorage->read('collections/' . $collectionDir . '/entries/' . $entryId . '.json');
        }

        if (!$entry) {
            return ['success' => false, 'message' => 'Entry not found'];
        }

        return ['success' => true, 'data' => ['entry' => $entry]];

    } elseif ($actionType === 'list_entries') {
        $collection = $actionPayload['collection'] ?? '';
        $limit = (int) ($actionPayload['limit'] ?? 100);
        $summaryOnly = !empty($actionPayload['summary']);

        if (empty($collection)) {
            return ['success' => false, 'message' => 'Collection is required'];
        }

        $collectionDir = birkly_content_resolve_collection_dir($contentStorage, $collection);
        if (!$collectionDir) {
            return ['success' => false, 'message' => 'Collection not found: ' . $collection];
        }

        $meta = $contentStorage->read('collections/' . $collectionDir . '/collection.json');
        $canonicalSlug = is_array($meta) ? (string) ($meta['slug'] ?? $collectionDir) : $collectionDir;

        $entries = $contentStorage->list('collections/' . $collectionDir . '/entries');
        $entryData = [];
        $count = 0;
        foreach ($entries as $entryFile) {
            if ($count >= $limit) {
                break;
            }
            $data = $contentStorage->read('collections/' . $collectionDir . '/entries/' . $entryFile);
            if ($data) {
                $entryData[] = $summaryOnly ? birkly_content_entry_list_summary($data) : $data;
                $count++;
            }
        }

        return [
            'success' => true,
            'data' => [
                'entries' => $entryData,
                'count' => count($entryData),
                'collection' => $canonicalSlug,
            ],
        ];

    } elseif ($actionType === 'list_collections') {
        require_once __DIR__ . '/../core/ai_collection_config.php';
        $collectionsDir = $birklyRoot . '/content/collections';
        $collections = [];

        if (is_dir($collectionsDir)) {
            foreach (array_filter(glob($collectionsDir . '/*'), 'is_dir') ?: [] as $dir) {
                $summary = birkly_ai_summarize_collection_from_dir($dir);
                if ($summary !== null) {
                    $collections[] = $summary;
                }
            }
        }

        return ['success' => true, 'data' => ['collections' => $collections, 'count' => count($collections)]];

    } elseif ($actionType === 'list_collection_groups') {
        require_once __DIR__ . '/../core/collection_groups.php';
        $groups = birkly_load_collection_groups();

        return ['success' => true, 'data' => ['collection_groups' => $groups, 'count' => count($groups)]];
        
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
        $settingsStorage = new Storage($birklyRoot . '/settings');
        $settings = [];
        
        $categories = ['site', 'admin', 'media', 'security'];
        foreach ($categories as $cat) {
            $settings[$cat] = $settingsStorage->read("$cat.json") ?? [];
        }
        
        return ['success' => true, 'data' => ['settings' => $settings]];
        
    } elseif ($actionType === 'list_users') {
        require_once __DIR__ . '/../core/birkly_users.php';
        require_once __DIR__ . '/ai_helpers.php';
        $users = array_map(
            'birkly_scrub_user_for_ai',
            birkly_list_human_user_records(true)
        );

        return ['success' => true, 'data' => ['users' => $users, 'count' => count($users)]];
        
    } elseif ($actionType === 'read_user') {
        // Read a specific user
        require_once __DIR__ . '/storage.php';
        $usersStorage = new Storage($birklyRoot . '/users');
        $userId = $actionPayload['id'] ?? $actionPayload['email'] ?? '';
        
        if (empty($userId)) {
            return ['success' => false, 'message' => 'User ID or email is required'];
        }
        
        // Try to find user by ID or email
        $userFiles = glob($birklyRoot . '/users/*.json');
        $user = null;
        foreach ($userFiles as $file) {
            $userData = json_decode(file_get_contents($file), true);
            if ($userData && ($userData['id'] === $userId || $userData['email'] === $userId)) {
                $user = birkly_scrub_user_for_ai($userData);
                break;
            }
        }
        
        if (!$user) {
            return ['success' => false, 'message' => 'User not found'];
        }
        
        return ['success' => true, 'data' => ['user' => $user]];
        
    } elseif ($actionType === 'read_collection') {
        $actionPayload = birkly_ai_normalize_collection_action_data($actionPayload);
        $collectionSlug = birkly_ai_resolve_collection_slug_from_payload($actionPayload, $contentStorage);
        if ($collectionSlug === '') {
            return ['success' => false, 'message' => 'Collection slug is required. Action data: ' . json_encode($actionPayload)];
        }

        $collectionDir = birkly_content_resolve_collection_dir($contentStorage, $collectionSlug);
        if (!$collectionDir) {
            return ['success' => false, 'message' => 'Collection not found'];
        }

        $collection = $contentStorage->read('collections/' . $collectionDir . '/collection.json');
        if (!$collection) {
            return ['success' => false, 'message' => 'Failed to read collection'];
        }

        $schema = $contentStorage->read('collections/' . $collectionDir . '/schema.json');
        if (is_array($schema)) {
            $collection['fields'] = $schema['fields'] ?? $collection['fields'] ?? [];
        }

        // Surface sub_collections summary (slug + name + entry_method only)
        if (!empty($collection['sub_collections']) && is_array($collection['sub_collections'])) {
            $collection['sub_collections'] = array_map(function (array $sc): array {
                return [
                    'slug' => $sc['slug'] ?? '',
                    'name' => $sc['name'] ?? '',
                    'entry_method' => $sc['entry_method'] ?? 'admin_only',
                    'field_count' => count($sc['fields'] ?? []),
                ];
            }, $collection['sub_collections']);
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
        $contentStorage = new Storage($birklyRoot . '/content');
        $pagesDir = $birklyRoot . '/content/pages';
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
        
        $pageFile = $birklyRoot . '/content/pages/' . $pageSlug . '.json';
        if (!file_exists($pageFile)) {
            // Try to find by slug in all pages
            $pagesDir = $birklyRoot . '/content/pages';
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
        // List activity logs (legacy activity.json + P61 plugin activity bus)
        require_once __DIR__ . '/activity.php';
        $limit = max(1, min(200, (int) ($actionPayload['limit'] ?? 50)));
        $pluginFilter = isset($actionPayload['plugin']) ? (string) $actionPayload['plugin'] : null;
        $activityFile = $birklyRoot . '/data/activity.json';

        $activities = [];
        if (file_exists($activityFile)) {
            $allActivities = json_decode(file_get_contents($activityFile), true) ?: [];
            $activities = array_slice(array_reverse($allActivities), 0, $limit);
        }

        $pluginActivity = [];
        $navAttention = [];
        $unreadNotify = 0;
        $pluginActivityPhp = $birklyRoot . '/core/plugin_activity.php';
        if (is_file($pluginActivityPhp)) {
            require_once $pluginActivityPhp;
            if (function_exists('birkly_plugin_activity_list')) {
                $pluginActivity = birkly_plugin_activity_list($limit, $pluginFilter);
            }
            if (function_exists('birkly_plugin_activity_nav_attention_counts')) {
                $navAttention = birkly_plugin_activity_nav_attention_counts();
            }
            if (function_exists('birkly_plugin_activity_unread_notify_count')) {
                $unreadNotify = birkly_plugin_activity_unread_notify_count();
            }
        }

        return [
            'success' => true,
            'data' => [
                'activities' => $activities,
                'count' => count($activities),
                'plugin_activity' => $pluginActivity,
                'plugin_activity_count' => count($pluginActivity),
                'nav_attention' => $navAttention,
                'unread_notify_count' => $unreadNotify,
            ],
        ];

    } elseif ($actionType === 'list_roles') {
        // List roles
        require_once __DIR__ . '/storage.php';
        $settingsStorage = new Storage($birklyRoot . '/settings');
        $roles = $settingsStorage->read('roles.json') ?: [];
        
        return ['success' => true, 'data' => ['roles' => $roles, 'count' => count($roles)]];
        
    } elseif ($actionType === 'read_role') {
        // Read a specific role
        require_once __DIR__ . '/storage.php';
        $settingsStorage = new Storage($birklyRoot . '/settings');
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
        $pluginsDir = $birklyRoot . '/plugins';
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

    } elseif ($actionType === 'list_sub_entries') {
        $parentCollection = $actionPayload['collection'] ?? '';
        $parentEntryId = $actionPayload['entry_id'] ?? '';
        $subCollection = $actionPayload['sub_collection'] ?? '';
        $limit = (int) ($actionPayload['limit'] ?? 100);

        if (empty($parentCollection) || empty($parentEntryId) || empty($subCollection)) {
            return ['success' => false, 'message' => 'collection, entry_id, and sub_collection are required'];
        }

        require_once $birklyRoot . '/core/sub_entries.php';
        $entries = getSubEntries($parentCollection, $parentEntryId, $subCollection, [], ['limit' => $limit]);
        if (!is_array($entries)) {
            $entries = [];
        }

        return [
            'success' => true,
            'data' => [
                'sub_entries' => $entries,
                'count' => count($entries),
                'collection' => $parentCollection,
                'entry_id' => $parentEntryId,
                'sub_collection' => $subCollection,
            ],
        ];

    } elseif ($actionType === 'read_sub_entry') {
        $parentCollection = $actionPayload['collection'] ?? '';
        $parentEntryId = $actionPayload['entry_id'] ?? '';
        $subCollection = $actionPayload['sub_collection'] ?? '';
        $subEntryId = $actionPayload['sub_entry_id'] ?? '';

        if (empty($parentCollection) || empty($parentEntryId) || empty($subCollection) || empty($subEntryId)) {
            return ['success' => false, 'message' => 'collection, entry_id, sub_collection, and sub_entry_id are required'];
        }

        require_once $birklyRoot . '/core/sub_entries.php';
        $subEntry = getSubEntry($parentCollection, $parentEntryId, $subCollection, $subEntryId);

        if (!$subEntry) {
            return ['success' => false, 'message' => 'Sub-entry not found'];
        }

        return ['success' => true, 'data' => ['sub_entry' => $subEntry]];
    } elseif (in_array($actionType, ['list_project_files', 'read_project_file', 'get_project_info'], true)) {
        require_once __DIR__ . '/../core/project_site_filesystem.php';
        $fs = new ProjectSiteFileSystem();

        if ($actionType === 'get_project_info') {
            return ['success' => true, 'data' => birkly_project_site_info()];
        }

        if ($actionType === 'list_project_files') {
            $path = (string) ($actionPayload['path'] ?? '');
            $files = $fs->listFiles($path);
            return [
                'success' => true,
                'data' => [
                    'path' => $path,
                    'files' => $files,
                    'count' => count($files),
                ],
            ];
        }

        $filePath = (string) ($actionPayload['path'] ?? '');
        if ($filePath === '') {
            return ['success' => false, 'message' => 'path is required'];
        }
        $file = $fs->readFile($filePath);
        if ($file === null) {
            return ['success' => false, 'message' => 'File not found or not allowed: ' . $filePath];
        }
        return ['success' => true, 'data' => ['file' => $file]];
    } elseif ($actionType === 'get_system_context') {
        return ['success' => true, 'data' => generateSystemContext($aiUser)];
    } elseif ($actionType === 'read_birkly_docs') {
        $topic = readBirklyDocsTopic((string) ($actionPayload['topic'] ?? ''));
        if ($topic === null) {
            return ['success' => false, 'message' => 'Unknown docs topic'];
        }
        return ['success' => true, 'data' => $topic];
    } elseif (in_array($actionType, ['get_agent_guide_context', 'get_brand_context', 'list_agent_guide_products', 'list_brand_products', 'get_agent_guide_claim', 'get_brand_claim', 'get_agent_guide_product', 'get_brand_product'], true)) {
        require_once __DIR__ . '/../core/ai_presence_pro.php';
        require_once __DIR__ . '/../core/ai_presence_links.php';
        if (in_array($actionType, ['get_agent_guide_context', 'get_brand_context'], true)) {
            $view = (string) ($actionPayload['view'] ?? 'summary');
            $data = birkly_ai_presence_public_agent_guide_for_tools($view);
            if ($data === null) {
                return ['success' => false, 'message' => 'Agent Guide not published'];
            }
            return ['success' => true, 'data' => $data];
        }
        if (in_array($actionType, ['list_agent_guide_products', 'list_brand_products'], true)) {
            $data = birkly_ai_presence_public_agent_guide_for_tools('products');
            if ($data === null) {
                return ['success' => false, 'message' => 'Agent Guide not published'];
            }
            return ['success' => true, 'data' => ['products_services' => $data['products_services'] ?? []]];
        }
        $id = (string) ($actionPayload['id'] ?? '');
        $full = birkly_ai_presence_public_agent_guide_for_tools('full');
        if ($full === null || $id === '') {
            return ['success' => false, 'message' => 'Not found'];
        }
        if (in_array($actionType, ['get_agent_guide_product', 'get_brand_product'], true)) {
            $graph = birkly_ai_presence_public_product_graph($full, $id);
            if ($graph === null) {
                return ['success' => false, 'message' => 'Product not found'];
            }
            return ['success' => true, 'data' => $graph];
        }
        foreach ($full['claims'] ?? [] as $claim) {
            if (is_array($claim) && ($claim['id'] ?? '') === $id && ($claim['status'] ?? '') === 'supported') {
                return ['success' => true, 'data' => ['claim' => $claim]];
            }
        }
        return ['success' => false, 'message' => 'Claim not found'];
    }

    return ['success' => false, 'message' => 'Unknown read action type: ' . $actionType];
}

