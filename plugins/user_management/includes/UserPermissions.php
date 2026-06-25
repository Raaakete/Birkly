<?php
/**
 * UserPermissions - Permission management system
 * 
 * Phase 4 implementation
 */

require_once __DIR__ . '/UserStorage.php';
require_once __DIR__ . '/UserService.php';
require_once __DIR__ . '/UserTiers.php';

class UserPermissions {
    private $storage;
    private $service;
    private $tiers;
    
    // Default role permissions (CMS-style roles kept for compatibility)
    private $rolePermissions = [
        'admin' => [
            'global' => ['*'],
            'collections' => ['*'],
            'entries' => ['*']
        ],
        'editor' => [
            'global' => ['read', 'create_entries', 'edit_entries', 'delete_own_entries'],
            'collections' => [],
            'entries' => []
        ],
        'author' => [
            'global' => ['read', 'create_entries', 'edit_own_entries', 'delete_own_entries'],
            'collections' => [],
            'entries' => []
        ],
        'user' => [
            'global' => ['read'],
            'collections' => [],
            'entries' => []
        ],
        // Site user roles (user_management plugin)
        'member' => [
            'global' => ['read'],
            'collections' => [],
            'entries' => []
        ],
        'subscriber' => [
            'global' => ['read'],
            'collections' => [],
            'entries' => []
        ],
        'premium' => [
            'global' => ['read', 'create_entries', 'edit_own_entries', 'delete_own_entries'],
            'collections' => [],
            'entries' => []
        ],
        'vip' => [
            'global' => ['read', 'create_entries', 'edit_own_entries', 'delete_own_entries'],
            'collections' => [],
            'entries' => []
        ],
    ];
    
    public function __construct() {
        $this->storage = new UserStorage();
        $this->service = new UserService();
        $this->tiers = new UserTiers();
    }
    
    /**
     * Get user permissions (merged from role, custom, and tier)
     */
    public function getUserPermissions($user_id) {
        $user = $this->service->getUser($user_id);
        if (!$user) {
            return null;
        }
        
        $role = $user['role'] ?? 'user';
        $permissions = $this->storage->getPermissions($user_id);
        $customPermissions = $permissions ? $permissions['permissions'] : null;
        
        // Start with role permissions
        $mergedPermissions = $this->rolePermissions[$role] ?? $this->rolePermissions['user'];
        
        // Merge custom permissions
        if ($customPermissions) {
            $mergedPermissions = $this->mergePermissions($mergedPermissions, $customPermissions);
        }
        
        // Add tier permissions
        $tier = $this->tiers->getUserTier($user_id);
        if ($tier && $tier !== 'free') {
            $tierData = $this->storage->getTier($tier);
            if ($tierData && isset($tierData['permissions'])) {
                $mergedPermissions = $this->mergePermissions($mergedPermissions, $tierData['permissions']);
            }
        }
        
        return $mergedPermissions;
    }
    
    /**
     * Merge two permission sets
     */
    private function mergePermissions($base, $additional) {
        $merged = $base;
        
        // Merge global permissions
        if (isset($additional['global'])) {
            if (in_array('*', $merged['global'])) {
                // Already has all permissions
            } elseif (in_array('*', $additional['global'])) {
                $merged['global'] = ['*'];
            } else {
                $merged['global'] = array_unique(array_merge($merged['global'], $additional['global']));
            }
        }
        
        // Merge collection permissions
        if (isset($additional['collections'])) {
            if (!isset($merged['collections'])) {
                $merged['collections'] = [];
            }
            
            foreach ($additional['collections'] as $collection => $perms) {
                if (isset($merged['collections'][$collection])) {
                    // Merge collection permissions
                    if (is_array($perms)) {
                        foreach ($perms as $perm => $value) {
                            $merged['collections'][$collection][$perm] = $value;
                        }
                    } else {
                        $merged['collections'][$collection] = $perms;
                    }
                } else {
                    $merged['collections'][$collection] = $perms;
                }
            }
        }
        
        // Merge entry permissions
        if (isset($additional['entries'])) {
            if (!isset($merged['entries'])) {
                $merged['entries'] = [];
            }
            
            foreach ($additional['entries'] as $entry => $perms) {
                $merged['entries'][$entry] = $perms;
            }
        }
        
        return $merged;
    }
    
    /**
     * Check if user can perform action
     */
    public function userCan($user_id, $action, $context = null) {
        $user = $this->service->getUser($user_id);
        if (!$user || ($user['status'] ?? 'active') !== 'active') {
            return false;
        }
        
        $permissions = $this->getUserPermissions($user_id);
        if (!$permissions) {
            return false;
        }
        
        // Check global permissions first
        if (isset($permissions['global'])) {
            if (in_array('*', $permissions['global'])) {
                return true; // Admin has all permissions
            }
            if (in_array($action, $permissions['global'])) {
                return true;
            }
        }
        
        // Check collection-level permissions
        $collection = null;
        if ($context && isset($permissions['collections'])) {
            // Context can be collection name or array with collection and entry
            if (is_string($context)) {
                $collection = $context;
            } elseif (is_array($context) && isset($context['collection'])) {
                $collection = $context['collection'];
            }
            
            if ($collection && isset($permissions['collections'][$collection])) {
                $collectionPerms = $permissions['collections'][$collection];
                
                if ($collectionPerms === '*') {
                    return true;
                }
                
                if (is_array($collectionPerms)) {
                    // Check specific permission
                    if (isset($collectionPerms[$action])) {
                        return $collectionPerms[$action] === true;
                    }
                    
                    // Check wildcard permissions
                    if (isset($collectionPerms['*'])) {
                        return $collectionPerms['*'] === true;
                    }
                }
            }
        }

        // Collection tier matrix (admin-configured metadata)
        if ($collection === null && $context) {
            if (is_string($context)) {
                $collection = $context;
            } elseif (is_array($context) && isset($context['collection'])) {
                $collection = $context['collection'];
            }
        }
        if ($collection && $this->checkCollectionTierPermission($user_id, $collection, $action, $context)) {
            return true;
        }
        
        // Check entry-level permissions
        if ($context && isset($permissions['entries'])) {
            $entryId = null;
            if (is_array($context) && isset($context['entry'])) {
                $entryId = $context['entry'];
            } elseif (is_array($context) && isset($context['collection']) && isset($context['id'])) {
                $entryId = $context['collection'] . ':' . $context['id'];
            }
            
            if ($entryId && isset($permissions['entries'][$entryId])) {
                $entryPerms = $permissions['entries'][$entryId];
                
                if ($entryPerms === '*') {
                    return true;
                }
                
                if (is_array($entryPerms)) {
                    if (isset($entryPerms[$action])) {
                        return $entryPerms[$action] === true;
                    }
                }
            }
        }
        
        // Check entry ownership
        if (is_array($context) && isset($context['author_id']) && $context['author_id'] === $user_id) {
            // User owns the entry - check if they can edit/delete their own entries
            if (in_array('edit_own_entries', $permissions['global'] ?? [])) {
                if (in_array($action, ['edit', 'update', 'delete'])) {
                    return true;
                }
            }
            if (in_array('delete_own_entries', $permissions['global'] ?? [])) {
                if ($action === 'delete') {
                    return true;
                }
            }
        }
        
        // Apply filters for extensibility
        if (function_exists('apply_filters')) {
            $result = apply_filters('user_can', false, $user_id, $action, $context, $permissions);
            if ($result !== false) {
                return $result;
            }
        }
        
        return false;
    }

    /**
     * Check collection permissions configured per tier in plugin metadata.
     */
    private function checkCollectionTierPermission($user_id, $collection, $action, $context = null) {
        $config = $this->storage->readMetadata('collection_permissions');
        if (!is_array($config) || !isset($config[$collection]) || !is_array($config[$collection])) {
            return false;
        }

        $tier = $this->tiers->getUserTier($user_id);
        $tierPerms = $config[$collection][$tier] ?? null;
        if (!is_array($tierPerms)) {
            return false;
        }

        $authorId = null;
        if (is_array($context) && isset($context['author_id'])) {
            $authorId = $context['author_id'];
        }
        $isOwner = $authorId && (string) $authorId === (string) $user_id;

        switch ($action) {
            case 'create':
            case 'create_entries':
                return !empty($tierPerms['create']);
            case 'edit':
            case 'update':
            case 'edit_entries':
            case 'edit_own':
            case 'edit_own_entries':
                if (!empty($tierPerms['edit_all'])) {
                    return true;
                }
                return $isOwner && !empty($tierPerms['edit_own']);
            case 'delete':
            case 'delete_entries':
            case 'delete_own':
            case 'delete_own_entries':
                if (!empty($tierPerms['delete_all'])) {
                    return true;
                }
                return $isOwner && !empty($tierPerms['delete_own']);
            case 'publish':
                return !empty($tierPerms['publish']);
            default:
                return !empty($tierPerms[$action]);
        }
    }
    
    /**
     * Set user permissions
     */
    public function setUserPermissions($user_id, $permissions) {
        return $this->storage->savePermissions($user_id, $permissions);
    }
    
    /**
     * Grant permission to user
     */
    public function grantPermission($user_id, $action, $context = null) {
        $permissions = $this->storage->getPermissions($user_id);
        $currentPerms = $permissions ? $permissions['permissions'] : [
            'global' => [],
            'collections' => [],
            'entries' => []
        ];
        
        if ($context === null) {
            // Global permission
            if (!in_array($action, $currentPerms['global'])) {
                $currentPerms['global'][] = $action;
            }
        } elseif (is_string($context)) {
            // Collection permission
            if (!isset($currentPerms['collections'][$context])) {
                $currentPerms['collections'][$context] = [];
            }
            $currentPerms['collections'][$context][$action] = true;
        } elseif (is_array($context) && isset($context['entry'])) {
            // Entry permission
            $entryId = $context['entry'];
            if (!isset($currentPerms['entries'][$entryId])) {
                $currentPerms['entries'][$entryId] = [];
            }
            $currentPerms['entries'][$entryId][$action] = true;
        }
        
        return $this->setUserPermissions($user_id, $currentPerms);
    }
    
    /**
     * Revoke permission from user
     */
    public function revokePermission($user_id, $action, $context = null) {
        $permissions = $this->storage->getPermissions($user_id);
        if (!$permissions) {
            return false;
        }
        
        $currentPerms = $permissions['permissions'];
        
        if ($context === null) {
            // Global permission
            $currentPerms['global'] = array_diff($currentPerms['global'], [$action]);
        } elseif (is_string($context)) {
            // Collection permission
            if (isset($currentPerms['collections'][$context][$action])) {
                unset($currentPerms['collections'][$context][$action]);
            }
        } elseif (is_array($context) && isset($context['entry'])) {
            // Entry permission
            $entryId = $context['entry'];
            if (isset($currentPerms['entries'][$entryId][$action])) {
                unset($currentPerms['entries'][$entryId][$action]);
            }
        }
        
        return $this->setUserPermissions($user_id, $currentPerms);
    }
}
