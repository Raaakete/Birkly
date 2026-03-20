<?php
/**
 * UserTiers - Tier-based access management
 * 
 * Phase 6 implementation
 */

require_once __DIR__ . '/UserStorage.php';
require_once __DIR__ . '/UserService.php';

class UserTiers {
    private $storage;
    private $service;
    
    public function __construct() {
        $this->storage = new UserStorage();
        $this->service = new UserService();
    }
    
    /**
     * Get user tier
     */
    public function getUserTier($user_id) {
        $user = $this->service->getUser($user_id);
        if (!$user) {
            return 'all';
        }
        
        return $user['tier'] ?? 'all';
    }
    
    /**
     * Set user tier
     */
    public function setUserTier($user_id, $tier_id) {
        $user = $this->service->getUser($user_id);
        if (!$user) {
            throw new Exception('User not found');
        }
        
        // Validate tier exists
        $tier = $this->storage->getTier($tier_id);
        if (!$tier) {
            throw new Exception('Tier not found');
        }
        
        $oldTier = $user['tier'] ?? 'all';
        
        // Update user tier
        $this->service->updateUser($user_id, ['tier' => $tier_id]);
        
        // Fire hook
        if (function_exists('do_action')) {
            do_action('user_tier_changed', $user_id, $oldTier, $tier_id);
        }
        
        return true;
    }
    
    /**
     * Check if user has tier
     */
    public function userHasTier($user_id, $tier_id) {
        $userTier = $this->getUserTier($user_id);
        return $userTier === $tier_id;
    }
    
    /**
     * Get tier limits
     */
    public function getTierLimits($tier_id) {
        $tier = $this->storage->getTier($tier_id);
        if (!$tier) {
            return null;
        }
        
        return $tier['limits'] ?? [];
    }
    
    /**
     * Initialize default tier - just "All"
     */
    public function initializeDefaultTiers() {
        // Only create "All" tier if it doesn't exist
        $existing = $this->storage->getTier('all');
        if (!$existing) {
            $this->storage->saveTier('all', [
                'id' => 'all',
                'name' => 'All',
                'description' => 'Default tier for all users',
                'permissions' => [
                    'collections' => [],
                    'features' => []
                ],
                'features' => [],
                'custom' => [],
                'created_at' => date('c')
            ]);
        }
    }
}
