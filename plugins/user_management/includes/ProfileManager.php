<?php
/**
 * ProfileManager - Manages hybrid profile system
 * 
 * Handles:
 * - Plugin-managed basic info (always present)
 * - Optional collection entry profiles
 * - Linking/unlinking users to profile entries
 * - Syncing basic info between plugin and entry
 */

require_once __DIR__ . '/UserStorage.php';
require_once __DIR__ . '/UserService.php';

class ProfileManager {
    private $storage;
    private $service;
    
    public function __construct() {
        $this->storage = new UserStorage();
        $this->service = new UserService();
    }
    
    /**
     * Check if collection profiles are enabled
     * 
     * @return bool
     */
    public function isCollectionProfilesEnabled() {
        $settings = $this->getProfileSettings();
        return !empty($settings['profile_collection_enabled']);
    }
    
    /**
     * Get profile collection slug
     * 
     * @return string|null
     */
    public function getProfileCollectionSlug() {
        $settings = $this->getProfileSettings();
        return $settings['profile_collection_slug'] ?? null;
    }
    
    /**
     * Get profile settings
     * 
     * @return array
     */
    private function getProfileSettings() {
        if (function_exists('get_plugin_setting')) {
            return [
                'profile_collection_enabled' => get_plugin_setting('user_management', 'profile_collection_enabled', false),
                'profile_collection_slug' => get_plugin_setting('user_management', 'profile_collection_slug', 'profiles'),
                'auto_create_profiles' => get_plugin_setting('user_management', 'auto_create_profiles', true)
            ];
        }
        
        // Fallback to metadata
        return $this->storage->getMetadata('profile_settings', [
            'profile_collection_enabled' => false,
            'profile_collection_slug' => 'profiles',
            'auto_create_profiles' => true
        ]);
    }
    
    /**
     * Create profile entry for user
     * 
     * @param string $user_id User ID
     * @return array|null Profile entry data or null on failure
     */
    public function createProfileEntry($user_id) {
        if (!$this->isCollectionProfilesEnabled()) {
            return null;
        }
        
        $collectionSlug = $this->getProfileCollectionSlug();
        if (!$collectionSlug) {
            return null;
        }
        
        // Get user data
        $user = $this->service->getUser($user_id);
        if (!$user) {
            return null;
        }
        
        // Check if profile entry already exists
        if (!empty($user['profile_entry_id'])) {
            return $this->getProfileEntry($user_id);
        }
        
        // Prepare entry data
        $entryData = [
            'user_id' => $user_id,
            'username' => $user['username'] ?? $user['email'],
            'email' => $user['email'],  // Redundant but ensures data if collection deleted
            'created_at' => date('c'),
            'updated_at' => date('c'),
            'status' => 'published',
            'author_id' => $user_id
        ];
        
        // Add name if available
        if (isset($user['name'])) {
            $entryData['name'] = $user['name'];
        }
        
        // Create entry in collection
        $entryId = $this->saveEntryToCollection($collectionSlug, $entryData);
        if (!$entryId) {
            return null;
        }
        
        // Link user to profile entry
        $user['profile_entry_id'] = $entryId;
        $this->service->updateUser($user_id, $user);
        
        return [
            'id' => $entryId,
            'collection' => $collectionSlug,
            'data' => $entryData
        ];
    }
    
    /**
     * Get profile entry for user
     * 
     * @param string $user_id User ID
     * @return array|null Profile entry or null
     */
    public function getProfileEntry($user_id) {
        $user = $this->service->getUser($user_id);
        if (!$user || empty($user['profile_entry_id'])) {
            return null;
        }
        
        $collectionSlug = $this->getProfileCollectionSlug();
        if (!$collectionSlug) {
            return null;
        }
        
        $entryId = $user['profile_entry_id'];
        return $this->readEntryFromCollection($collectionSlug, $entryId);
    }
    
    /**
     * Get user profile (plugin + entry merged)
     * 
     * @param string $user_id User ID
     * @return array Profile data
     */
    public function getUserProfile($user_id) {
        $user = $this->service->getUser($user_id);
        if (!$user) {
            return null;
        }
        
        // Start with plugin data (source of truth for basic info)
        $profile = [
            'id' => $user['id'],
            'user_id' => $user['id'],
            'username' => $user['username'] ?? $user['email'],
            'email' => $user['email'],
            'name' => $user['name'] ?? null,
            'role' => $user['role'] ?? 'member',
            'status' => $user['status'] ?? 'active',
            'created_at' => $user['created_at'] ?? null,
            'updated_at' => $user['updated_at'] ?? null
        ];
        
        // Merge with entry data if available
        $entry = $this->getProfileEntry($user_id);
        if ($entry) {
            // Overlay entry data (but don't override basic info from plugin)
            foreach ($entry as $key => $value) {
                // Skip basic fields that plugin owns
                if (!in_array($key, ['id', 'user_id', 'username', 'email', 'created_at', 'updated_at', 'status', 'author_id'])) {
                    $profile[$key] = $value;
                }
            }
            $profile['profile_entry_id'] = $user['profile_entry_id'];
        }
        
        return $profile;
    }
    
    /**
     * Sync basic info from plugin to entry
     * 
     * @param string $user_id User ID
     * @return bool Success
     */
    public function syncBasicInfoToEntry($user_id) {
        $user = $this->service->getUser($user_id);
        if (!$user || empty($user['profile_entry_id'])) {
            return false;
        }
        
        $collectionSlug = $this->getProfileCollectionSlug();
        if (!$collectionSlug) {
            return false;
        }
        
        $entry = $this->readEntryFromCollection($collectionSlug, $user['profile_entry_id']);
        if (!$entry) {
            // Entry doesn't exist, try to recreate
            return $this->createProfileEntry($user_id) !== null;
        }
        
        // Update basic info in entry
        $entry['user_id'] = $user_id;
        $entry['username'] = $user['username'] ?? $user['email'];
        $entry['email'] = $user['email'];
        if (isset($user['name'])) {
            $entry['name'] = $user['name'];
        }
        $entry['updated_at'] = date('c');
        
        return $this->saveEntryToCollection($collectionSlug, $entry, $user['profile_entry_id']) !== null;
    }
    
    /**
     * Link user to existing entry
     * 
     * @param string $user_id User ID
     * @param string $entry_id Entry ID
     * @return bool Success
     */
    public function linkUserToEntry($user_id, $entry_id) {
        $user = $this->service->getUser($user_id);
        if (!$user) {
            return false;
        }
        
        $collectionSlug = $this->getProfileCollectionSlug();
        if (!$collectionSlug) {
            return false;
        }
        
        // Verify entry exists
        $entry = $this->readEntryFromCollection($collectionSlug, $entry_id);
        if (!$entry) {
            return false;
        }
        
        // Sync basic info to entry
        $entry['user_id'] = $user_id;
        $entry['username'] = $user['username'] ?? $user['email'];
        $entry['email'] = $user['email'];
        if (isset($user['name'])) {
            $entry['name'] = $user['name'];
        }
        $entry['updated_at'] = date('c');
        
        // Save entry
        if (!$this->saveEntryToCollection($collectionSlug, $entry, $entry_id)) {
            return false;
        }
        
        // Link user
        $user['profile_entry_id'] = $entry_id;
        return $this->service->updateUser($user_id, $user) !== null;
    }
    
    /**
     * Unlink user from entry
     * 
     * @param string $user_id User ID
     * @return bool Success
     */
    public function unlinkUserFromEntry($user_id) {
        $user = $this->service->getUser($user_id);
        if (!$user) {
            return false;
        }
        
        $user['profile_entry_id'] = null;
        unset($user['profile_entry_id']);
        
        return $this->service->updateUser($user_id, $user) !== null;
    }
    
    /**
     * Migrate existing users to collection profiles
     * 
     * @param array $user_ids Optional array of user IDs, or null for all users
     * @return array Results with success count and errors
     */
    public function migrateUsersToCollectionProfiles($user_ids = null) {
        if (!$this->isCollectionProfilesEnabled()) {
            return [
                'success' => false,
                'message' => 'Collection profiles are not enabled'
            ];
        }
        
        // Get users to migrate
        if ($user_ids === null) {
            $users = $this->service->listUsers();
        } else {
            $users = [];
            foreach ($user_ids as $user_id) {
                $user = $this->service->getUser($user_id);
                if ($user) {
                    $users[] = $user;
                }
            }
        }
        
        $success = 0;
        $errors = [];
        
        foreach ($users as $user) {
            // Skip if already linked
            if (!empty($user['profile_entry_id'])) {
                continue;
            }
            
            try {
                $result = $this->createProfileEntry($user['id']);
                if ($result) {
                    $success++;
                } else {
                    $errors[] = "Failed to create profile for user {$user['id']}";
                }
            } catch (Exception $e) {
                $errors[] = "Error creating profile for user {$user['id']}: " . $e->getMessage();
            }
        }
        
        return [
            'success' => true,
            'migrated' => $success,
            'total' => count($users),
            'errors' => $errors
        ];
    }
    
    /**
     * Save entry to collection
     * 
     * @param string $collectionSlug Collection slug
     * @param array $entryData Entry data
     * @param string|null $entryId Existing entry ID (for update)
     * @return string|null Entry ID or null on failure
     */
    private function saveEntryToCollection($collectionSlug, $entryData, $entryId = null) {
        // Use storage to save entry
        require_once __DIR__ . '/../../../core/storage/StorageFactory.php';
        $storage = StorageFactory::getStorage(__DIR__ . '/../../../content');
        
        if (!$storage) {
            return null;
        }
        
        // Generate entry ID if not provided
        if (!$entryId) {
            $entryId = 'profile_' . $entryData['user_id'];
        }
        
        // Ensure entry has required fields
        $entryData['id'] = $entryId;
        if (!isset($entryData['slug'])) {
            $entryData['slug'] = $entryId;
        }
        
        // Save entry
        $entryPath = "collections/{$collectionSlug}/entries/{$entryId}.json";
        if ($storage->write($entryPath, $entryData)) {
            return $entryId;
        }
        
        return null;
    }
    
    /**
     * Read entry from collection
     * 
     * @param string $collectionSlug Collection slug
     * @param string $entryId Entry ID
     * @return array|null Entry data or null
     */
    private function readEntryFromCollection($collectionSlug, $entryId) {
        require_once __DIR__ . '/../../../core/storage/StorageFactory.php';
        $storage = StorageFactory::getStorage(__DIR__ . '/../../../content');
        
        if (!$storage) {
            return null;
        }
        
        $entryPath = "collections/{$collectionSlug}/entries/{$entryId}.json";
        
        return $storage->read($entryPath);
    }
}
