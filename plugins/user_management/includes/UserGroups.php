<?php
/**
 * UserGroups - User group/team management
 * 
 * Phase 7 implementation
 */

require_once __DIR__ . '/UserStorage.php';
require_once __DIR__ . '/UserService.php';
require_once __DIR__ . '/UserPermissions.php';

class UserGroups {
    private $storage;
    private $service;
    private $permissions;
    
    public function __construct() {
        $this->storage = new UserStorage();
        $this->service = new UserService();
        $this->permissions = new UserPermissions();
    }
    
    /**
     * Create a new group
     */
    public function createGroup($data) {
        $group_id = $this->generateGroupId();
        
        $groupData = [
            'id' => $group_id,
            'name' => $data['name'] ?? '',
            'description' => $data['description'] ?? '',
            'type' => $data['type'] ?? 'private', // public, private, closed
            'owner_id' => $data['owner_id'] ?? null,
            'members' => $data['members'] ?? [],
            'permissions' => $data['permissions'] ?? [],
            'settings' => $data['settings'] ?? [],
            'created_at' => date('c'),
            'updated_at' => date('c')
        ];
        
        // Validate
        if (empty($groupData['name'])) {
            throw new Exception('Group name is required');
        }
        
        // Save group
        if (!$this->storage->saveGroup($group_id, $groupData)) {
            throw new Exception('Failed to create group');
        }
        
        // Fire hook
        if (function_exists('do_action')) {
            do_action('group_created', $groupData);
        }
        
        return $groupData;
    }
    
    /**
     * Get group by ID
     */
    public function getGroup($group_id) {
        return $this->storage->getGroup($group_id);
    }
    
    /**
     * Update group
     */
    public function updateGroup($group_id, $data) {
        $group = $this->storage->getGroup($group_id);
        if (!$group) {
            throw new Exception('Group not found');
        }
        
        // Update fields
        if (isset($data['name'])) {
            $group['name'] = $data['name'];
        }
        if (isset($data['description'])) {
            $group['description'] = $data['description'];
        }
        if (isset($data['type'])) {
            $group['type'] = $data['type'];
        }
        if (isset($data['permissions'])) {
            $group['permissions'] = $data['permissions'];
        }
        if (isset($data['settings'])) {
            $group['settings'] = $data['settings'];
        }
        
        $group['updated_at'] = date('c');
        
        // Save
        if (!$this->storage->saveGroup($group_id, $group)) {
            throw new Exception('Failed to update group');
        }
        
        // Fire hook
        if (function_exists('do_action')) {
            do_action('group_updated', $group);
        }
        
        return $group;
    }
    
    /**
     * Delete group
     */
    public function deleteGroup($group_id) {
        $group = $this->storage->getGroup($group_id);
        if (!$group) {
            throw new Exception('Group not found');
        }
        
        // Fire hook
        if (function_exists('do_action')) {
            do_action('group_deleted', $group);
        }
        
        return $this->storage->deleteGroup($group_id);
    }
    
    /**
     * List all groups
     */
    public function listGroups($filters = []) {
        return $this->storage->listGroups($filters);
    }
    
    /**
     * Add member to group
     */
    public function addMember($group_id, $user_id) {
        $group = $this->storage->getGroup($group_id);
        if (!$group) {
            throw new Exception('Group not found');
        }
        
        if (!in_array($user_id, $group['members'])) {
            $group['members'][] = $user_id;
            $group['updated_at'] = date('c');
            
            if (!$this->storage->saveGroup($group_id, $group)) {
                throw new Exception('Failed to add member');
            }
        }
        
        // Fire hook
        if (function_exists('do_action')) {
            do_action('group_member_added', $group_id, $user_id);
        }
        
        return true;
    }
    
    /**
     * Remove member from group
     */
    public function removeMember($group_id, $user_id) {
        $group = $this->storage->getGroup($group_id);
        if (!$group) {
            throw new Exception('Group not found');
        }
        
        $key = array_search($user_id, $group['members']);
        if ($key !== false) {
            unset($group['members'][$key]);
            $group['members'] = array_values($group['members']); // Reindex
            $group['updated_at'] = date('c');
            
            if (!$this->storage->saveGroup($group_id, $group)) {
                throw new Exception('Failed to remove member');
            }
        }
        
        // Fire hook
        if (function_exists('do_action')) {
            do_action('group_member_removed', $group_id, $user_id);
        }
        
        return true;
    }
    
    /**
     * Get user's groups
     */
    public function getUserGroups($user_id) {
        $groups = $this->listGroups();
        $userGroups = [];
        
        foreach ($groups as $group) {
            if (in_array($user_id, $group['members']) || $group['owner_id'] === $user_id) {
                $userGroups[] = $group;
            }
        }
        
        return $userGroups;
    }
    
    /**
     * Check if user is member of group
     */
    public function isMember($group_id, $user_id) {
        $group = $this->storage->getGroup($group_id);
        if (!$group) {
            return false;
        }
        
        return in_array($user_id, $group['members']) || $group['owner_id'] === $user_id;
    }
    
    /**
     * Generate unique group ID
     */
    private function generateGroupId() {
        return 'group_' . bin2hex(random_bytes(8));
    }
}
