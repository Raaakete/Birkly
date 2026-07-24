<?php
/**
 * ProfileService - optional "profile collection" helper for sites.
 *
 * This stays generic: the site/admin chooses which collection slug is the profile collection.
 * The plugin can then auto-create and manage profile entries.
 */
require_once __DIR__ . '/../../../api/storage.php'; // Storage compatibility layer

class ProfileService {
    private $storage;

    public function __construct() {
        // Use absolute base path
        $this->storage = new Storage(__DIR__ . '/../../../content');
    }

    public function collectionExists(string $collectionSlug): bool {
        return is_dir(__DIR__ . "/../../../content/collections/{$collectionSlug}");
    }

    public function getProfileByUserId(string $collectionSlug, string $userId): ?array {
        if ($collectionSlug === '' || $userId === '') return null;
        if (!$this->collectionExists($collectionSlug)) return null;

        $results = $this->storage->query($collectionSlug, ['user_id' => $userId], ['limit' => 1]);
        if (is_array($results) && count($results) > 0) {
            return $results[0];
        }
        return null;
    }

    public function ensureProfileForUser(string $collectionSlug, array $user): ?array {
        $userId = (string)($user['id'] ?? '');
        if ($collectionSlug === '' || $userId === '') return null;
        if (!$this->collectionExists($collectionSlug)) return null;

        $existing = $this->getProfileByUserId($collectionSlug, $userId);
        if ($existing) return $existing;

        $name = (string)($user['name'] ?? $user['username'] ?? '');
        $email = (string)($user['email'] ?? '');

        $entryId = 'profile_' . substr(sha1($userId), 0, 12);
        $slug = $this->slugify($name !== '' ? $name : $userId);

        // Determine entry status based on user status/approved and auto-approve setting
        $userStatus = $user['status'] ?? 'pending';
        $userApproved = $user['approved'] ?? false;
        $autoApprove = function_exists('get_plugin_setting')
            ? get_plugin_setting('user_management', 'auto_approve_users', false)
            : false;
        
        // Map user status to entry status
        $entryStatus = 'pending';
        if ($userApproved && $userStatus === 'active') {
            $entryStatus = 'published';
        } elseif ($userStatus === 'inactive') {
            $entryStatus = 'unpublished';
        } else {
            $entryStatus = 'pending';
        }
        
        // If auto-approve is enabled, always publish
        if ($autoApprove) {
            $entryStatus = 'published';
        }

        $entry = [
            'id' => $entryId,
            'slug' => $slug,
            'user_id' => $userId,
            'name' => $name !== '' ? $name : $email,
            'bio' => '',
            'avatar' => null,
            'connections' => [],
            'following' => [],
            'followers' => [],
            'status' => $entryStatus,
            'created_at' => date('c'),
            'updated_at' => date('c')
        ];

        $ok = $this->storage->write("collections/{$collectionSlug}/entries/{$entryId}.json", $entry);
        return $ok ? $entry : null;
    }

    public function saveProfile(string $collectionSlug, array $profile): bool {
        $id = (string)($profile['id'] ?? '');
        if ($collectionSlug === '' || $id === '') return false;
        if (!$this->collectionExists($collectionSlug)) return false;

        $profile['updated_at'] = date('c');
        return (bool)$this->storage->write("collections/{$collectionSlug}/entries/{$id}.json", $profile);
    }

    private function slugify(string $s): string {
        $s = trim(mb_strtolower($s));
        $s = preg_replace('/[^a-z0-9]+/u', '-', $s);
        $s = trim($s, '-');
        if ($s === '') $s = 'profile';
        return substr($s, 0, 80);
    }
}

