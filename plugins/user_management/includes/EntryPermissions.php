<?php
/**
 * EntryPermissions - Backend permission enforcement for entries
 * 
 * CRITICAL: This enforces security on the backend.
 * Frontend checks are for UX only - backend must enforce permissions.
 */

require_once __DIR__ . '/UserAuth.php';
require_once __DIR__ . '/UserStorage.php';
require_once __DIR__ . '/UserPermissions.php';

class EntryPermissions {
    private $auth;
    private $storage;
    private $permissions;
    
    public function __construct() {
        $this->auth = new UserAuth();
        $this->storage = new UserStorage();
        $this->permissions = new UserPermissions();
    }
    
    /**
     * Check if user can view an entry
     * 
     * @param string $user_id Current user ID (null if not logged in)
     * @param array $entry Entry data
     * @return bool
     */
    public function canViewEntry($user_id, $entry) {
        // Public entries: everyone can view
        $visibility = $entry['visibility'] ?? 'public';
        if ($visibility === 'public') {
            return true;
        }
        
        // Private entries: only author can view
        if ($visibility === 'private') {
            if (!$user_id) {
                return false;
            }
            $author_id = $entry['author_id'] ?? ($entry['author_user_id'] ?? null);
            return $author_id === $user_id;
        }
        
        // Relationship-based: check if user is in author's relationship field
        if ($visibility === 'relationship') {
            if (!$user_id) {
                return false;
            }
            
            // Get relationship field name from entry (default: 'friends')
            $relationshipField = $entry['visibility_relationship_field'] ?? 'friends';
            $relationshipMode = $entry['visibility_relationship_mode'] ?? 'one_way'; // one_way | mutual
            
            // Get author's profile entry
            $author_id = $entry['author_id'] ?? ($entry['author_user_id'] ?? null);
            if (!$author_id) {
                return false;
            }
            
            // Check if profile collection exists
            $profileEntry = $this->getProfileEntry($author_id);
            if (!$profileEntry) {
                return false;
            }
            
            // Get relationship field value (user fieldtype)
            $relationships = $profileEntry[$relationshipField] ?? [];
            if (!is_array($relationships)) {
                $relationships = empty($relationships) ? [] : [$relationships];
            }
            
            // Check if current user is in relationships
            $oneWay = in_array($user_id, $relationships, true);
            if ($relationshipMode !== 'mutual') {
                return $oneWay;
            }

            // Mutual: also require author in viewer's relationship field (same collection/field)
            $viewerProfile = $this->getProfileEntry($user_id);
            if (!$viewerProfile) return false;
            $viewerRelationships = $viewerProfile[$relationshipField] ?? [];
            if (!is_array($viewerRelationships)) {
                $viewerRelationships = empty($viewerRelationships) ? [] : [$viewerRelationships];
            }
            $mutual = $oneWay && in_array($author_id, $viewerRelationships, true);
            return $mutual;
        }
        
        // Default: deny access
        return false;
    }
    
    /**
     * Check if user can edit an entry
     * 
     * @param string $user_id Current user ID
     * @param array $entry Entry data
     * @return bool
     */
    public function canEditEntry($user_id, $entry, $collection = null) {
        if (!$user_id) {
            return false;
        }

        $author_id = $entry['author_id'] ?? ($entry['author_user_id'] ?? null);
        $context = [
            'collection' => $collection,
            'entry' => $entry['id'] ?? null,
            'author_id' => $author_id,
        ];

        if ($this->permissions->userCan($user_id, 'edit', $context)) {
            return true;
        }

        // Author can edit own when tier matrix grants edit_own
        if ($author_id && (string) $author_id === (string) $user_id) {
            return $this->permissions->userCan($user_id, 'edit_own', $context);
        }

        return false;
    }
    
    /**
     * Check if user can delete an entry
     * 
     * @param string $user_id Current user ID
     * @param array $entry Entry data
     * @return bool
     */
    public function canDeleteEntry($user_id, $entry, $collection = null) {
        if (!$user_id) {
            return false;
        }

        $author_id = $entry['author_id'] ?? ($entry['author_user_id'] ?? null);
        $context = [
            'collection' => $collection,
            'entry' => $entry['id'] ?? null,
            'author_id' => $author_id,
        ];

        if ($this->permissions->userCan($user_id, 'delete', $context)) {
            return true;
        }

        if ($author_id && (string) $author_id === (string) $user_id) {
            return $this->permissions->userCan($user_id, 'delete_own', $context);
        }

        return false;
    }
    
    /**
     * Check if user can create sub-entries (comments) on an entry
     * 
     * @param string $user_id Current user ID
     * @param array $entry Entry data
     * @return bool
     */
    public function canCommentOnEntry($user_id, $entry) {
        if (!$user_id) {
            return false;
        }
        
        // If user can view, they can usually comment
        // But entry might have comment restrictions
        $allowComments = $entry['allow_comments'] ?? true;
        if (!$allowComments) {
            return false;
        }
        
        // Check if user can view the entry (can't comment if can't view)
        return $this->canViewEntry($user_id, $entry);
    }
    
    /**
     * Filter entries by view permission
     * 
     * @param string $user_id Current user ID (null if not logged in)
     * @param array $entries Array of entries
     * @return array Filtered entries (only those user can view)
     */
    public function filterEntriesByViewPermission($user_id, $entries) {
        $filtered = [];
        foreach ($entries as $entry) {
            if ($this->canViewEntry($user_id, $entry)) {
                $filtered[] = $entry;
            }
        }
        return $filtered;
    }
    
    /**
     * Get profile entry for a user
     * 
     * @param string $user_id User ID
     * @return array|null Profile entry or null
     */
    private function getProfileEntry($user_id) {
        $slug = 'profiles';
        if (function_exists('get_plugin_setting')) {
            $enabled = (bool)get_plugin_setting('user_management', 'profile_collection_enabled', false);
            if (!$enabled) return null;
            $s = (string)get_plugin_setting('user_management', 'profile_collection_slug', 'profiles');
            if ($s !== '') $slug = $s;
        }

        // Back-compat fallback for demo collection name
        $base = __DIR__ . '/../../../content/collections';
        if (!is_dir($base . '/' . $slug) && is_dir($base . '/social_profiles')) {
            $slug = 'social_profiles';
        }

        if (!is_dir($base . '/' . $slug)) return null;

        // Use Storage query for speed/consistency if available
        if (class_exists('Storage')) {
            try {
                $storage = new \Storage(__DIR__ . '/../../../content');
                $results = $storage->query($slug, ['user_id' => $user_id], ['limit' => 1]);
                if (is_array($results) && count($results) > 0) {
                    return $results[0];
                }
            } catch (\Throwable $e) {
                // fall through
            }
        }

        // Fallback: scan entries
        $entriesDir = $base . '/' . $slug . '/entries';
        if (!is_dir($entriesDir)) return null;
        foreach (glob($entriesDir . '/*.json') as $file) {
            $entry = json_decode(@file_get_contents($file), true);
            if (is_array($entry) && isset($entry['user_id']) && $entry['user_id'] === $user_id) {
                return $entry;
            }
        }
        return null;
    }
    
    /**
     * Get current user ID
     * 
     * @return string|null User ID or null if not logged in
     */
    public function getCurrentUserId() {
        try {
            $user = $this->auth->getCurrentUser();
            return $user['id'] ?? null;
        } catch (Exception $e) {
            return null;
        }
    }
}
