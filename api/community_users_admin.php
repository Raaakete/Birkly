<?php
// plugins/community_users/api/community_users_admin.php
// Admin API for community users management

header('Content-Type: application/json');
require_once __DIR__ . '/storage.php';
require_once __DIR__ . '/validate.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/log.php';
require_once __DIR__ . '/../core/community_user_files.php';

// Check admin authentication
requireAuth('manage_users');

$input = json_decode(file_get_contents('php://input'), true) ?? [];
$action = $input['action'] ?? $_GET['action'] ?? '';

$storage = new Storage('../content');
$collection = 'community_users';
$dir = null;

// Look for the community_users collection in the plugin directory
$pluginCollectionPath = __DIR__ . '/../plugins/community_users/collections/community_users';
if (is_dir($pluginCollectionPath)) {
    $dir = 'community_users'; // Use this as the directory name
} else {
    // Fallback: try to find it in main collections
    $collections = $storage->list('collections');
    foreach ($collections as $d) {
        $m = $storage->read("collections/$d/collection.json");
        if ($m && $m['slug'] === $collection) {
            $dir = $d;
            break;
        }
    }
}

if (!$dir) {
    echo json_encode(['status' => 'error', 'message' => 'Community users collection not found']);
    exit;
}

if ($action === 'count') {
    $pluginCollectionPath = __DIR__ . '/../plugins/community_users/collections/community_users/entries';
    $entries = [];
    if (is_dir($pluginCollectionPath)) {
        $entries = array_filter(scandir($pluginCollectionPath), function($file) {
            return $file !== '.' && $file !== '..' && pathinfo($file, PATHINFO_EXTENSION) === 'json';
        });
    }
    $count = count($entries);
    echo json_encode([
        'status' => 'ok',
        'data' => [
            'count' => $count
        ]
    ]);
    exit;
}

if ($action === 'stats') {
    $pluginCollectionPath = __DIR__ . '/../plugins/community_users/collections/community_users/entries';
    $entries = [];
    if (is_dir($pluginCollectionPath)) {
        $entries = array_filter(scandir($pluginCollectionPath), function($file) {
            return $file !== '.' && $file !== '..' && pathinfo($file, PATHINFO_EXTENSION) === 'json';
        });
    }
    
    $total_users = count($entries);
    $active_users = 0;
    $pending_verification = 0;
    $pending_approval = 0;
    
    foreach ($entries as $entryFile) {
        $entry = json_decode(file_get_contents($pluginCollectionPath . '/' . $entryFile), true);
        if ($entry) {
            $status = $entry['status'] ?? 'active';
            if ($status === 'active') {
                $active_users++;
            } elseif ($status === 'pending_verification') {
                $pending_verification++;
            } elseif ($status === 'pending_approval') {
                $pending_approval++;
            }
        }
    }
    
    echo json_encode([
        'status' => 'ok',
        'data' => [
            'stats' => [
                'total_users' => $total_users,
                'active_users' => $active_users,
                'pending_verification' => $pending_verification,
                'pending_approval' => $pending_approval
            ]
        ]
    ]);
    exit;
}

if ($action === 'list') {
    $pluginCollectionPath = __DIR__ . '/../plugins/community_users/collections/community_users/entries';
    $users = [];
    if (is_dir($pluginCollectionPath)) {
        $entries = array_filter(scandir($pluginCollectionPath), function($file) {
            return $file !== '.' && $file !== '..' && pathinfo($file, PATHINFO_EXTENSION) === 'json';
        });
        foreach ($entries as $entryFile) {
            $entry = json_decode(file_get_contents($pluginCollectionPath . '/' . $entryFile), true);
            if ($entry) {
                // Don't send password hashes
                unset($entry['password_hash']);
                $users[] = $entry;
            }
        }
    }
    echo json_encode([
        'status' => 'ok',
        'data' => [
            'users' => $users
        ]
    ]);
    exit;
}

if ($action === 'get') {
    $id = (string) ($_GET['id'] ?? '');
    if ($id === '') {
        echo json_encode(['status' => 'error', 'message' => 'User ID required']);
        exit;
    }
    $pluginCollectionPath = __DIR__ . '/../plugins/community_users/collections/community_users/entries';
    $entryFile = community_users_entry_file($pluginCollectionPath, $id);
    if ($entryFile === null) {
        echo json_encode(['status' => 'error', 'message' => 'Invalid user id']);
        exit;
    }
    if (!file_exists($entryFile)) {
        echo json_encode(['status' => 'error', 'message' => 'User not found']);
        exit;
    }
    $entry = json_decode(file_get_contents($entryFile), true);
    if (!$entry) {
        echo json_encode(['status' => 'error', 'message' => 'User not found']);
        exit;
    }
    // Don't send password hash
    unset($entry['password_hash']);
    echo json_encode([
        'status' => 'ok',
        'data' => [
            'user' => $entry
        ]
    ]);
    exit;
}

if ($action === 'add') {
    $username = trim($input['username'] ?? '');
    $email = trim($input['email'] ?? '');
    $password = $input['password'] ?? '';
    $status = $input['status'] ?? 'active';
    
    if (empty($username) || empty($email) || empty($password)) {
        echo json_encode(['status' => 'error', 'message' => 'Username, email, and password are required']);
        exit;
    }
    
    $pluginCollectionPath = __DIR__ . '/../plugins/community_users/collections/community_users/entries';
    if (!is_dir($pluginCollectionPath)) {
        mkdir($pluginCollectionPath, 0755, true);
    }
    
    // Check for duplicates
    $entries = array_filter(scandir($pluginCollectionPath), function($file) {
        return $file !== '.' && $file !== '..' && pathinfo($file, PATHINFO_EXTENSION) === 'json';
    });
    foreach ($entries as $entryFile) {
        $entry = json_decode(file_get_contents($pluginCollectionPath . '/' . $entryFile), true);
        if ($entry) {
            if (strcasecmp($entry['username'] ?? '', $username) === 0) {
                echo json_encode(['status' => 'error', 'message' => 'Username already exists']);
                exit;
            }
            if (strcasecmp($entry['email'] ?? '', $email) === 0) {
                echo json_encode(['status' => 'error', 'message' => 'Email already registered']);
                exit;
            }
        }
    }
    
    $id = uniqid();
    $now = date('c');
    $entry = [
        'id' => $id,
        'username' => $username,
        'email' => $email,
        'password_hash' => password_hash($password, PASSWORD_DEFAULT),
        'created_at' => $now,
        'updated_at' => $now,
        'status' => $status
    ];
    
    $entryFile = community_users_entry_file($pluginCollectionPath, $id);
    if ($entryFile === null) {
        echo json_encode(['status' => 'error', 'message' => 'Invalid user id']);
        exit;
    }
    $result = file_put_contents($entryFile, json_encode($entry, JSON_PRETTY_PRINT));
    echo json_encode([
        'status' => $result ? 'ok' : 'error',
        'message' => $result ? 'User added successfully' : 'Failed to add user'
    ]);
    exit;
}

if ($action === 'update') {
    $id = $input['id'] ?? '';
    $username = trim($input['username'] ?? '');
    $email = trim($input['email'] ?? '');
    $password = $input['password'] ?? '';
    $status = $input['status'] ?? 'active';
    
    if ($id === '' || $username === '' || $email === '') {
        echo json_encode(['status' => 'error', 'message' => 'ID, username, and email are required']);
        exit;
    }
    
    $pluginCollectionPath = __DIR__ . '/../plugins/community_users/collections/community_users/entries';
    $entryFile = community_users_entry_file($pluginCollectionPath, (string) $id);
    if ($entryFile === null) {
        echo json_encode(['status' => 'error', 'message' => 'Invalid user id']);
        exit;
    }
    if (!file_exists($entryFile)) {
        echo json_encode(['status' => 'error', 'message' => 'User not found']);
        exit;
    }
    
    $entry = json_decode(file_get_contents($entryFile), true);
    if (!$entry) {
        echo json_encode(['status' => 'error', 'message' => 'User not found']);
        exit;
    }
    
    // Check for duplicates (excluding current user)
    $entries = array_filter(scandir($pluginCollectionPath), function($file) {
        return $file !== '.' && $file !== '..' && pathinfo($file, PATHINFO_EXTENSION) === 'json';
    });
    foreach ($entries as $existingFile) {
        if ($existingFile === "$id.json") continue;
        $existingEntry = json_decode(file_get_contents($pluginCollectionPath . '/' . $existingFile), true);
        if ($existingEntry) {
            if (strcasecmp($existingEntry['username'] ?? '', $username) === 0) {
                echo json_encode(['status' => 'error', 'message' => 'Username already exists']);
                exit;
            }
            if (strcasecmp($existingEntry['email'] ?? '', $email) === 0) {
                echo json_encode(['status' => 'error', 'message' => 'Email already registered']);
                exit;
            }
        }
    }
    
    $entry['username'] = $username;
    $entry['email'] = $email;
    $entry['status'] = $status;
    $entry['updated_at'] = date('c');
    
    if (!empty($password)) {
        $entry['password_hash'] = password_hash($password, PASSWORD_DEFAULT);
    }
    
    $result = file_put_contents($entryFile, json_encode($entry, JSON_PRETTY_PRINT));
    echo json_encode([
        'status' => $result ? 'ok' : 'error',
        'message' => $result ? 'User updated successfully' : 'Failed to update user'
    ]);
    exit;
}

if ($action === 'delete' || $action === 'delete_user') {
    $id = (string) ($input['id'] ?? $input['user_id'] ?? '');
    if ($id === '') {
        echo json_encode(['status' => 'error', 'message' => 'User ID required']);
        exit;
    }
    
    $pluginCollectionPath = __DIR__ . '/../plugins/community_users/collections/community_users/entries';
    $entryFile = community_users_entry_file($pluginCollectionPath, $id);
    if ($entryFile === null) {
        echo json_encode(['status' => 'error', 'message' => 'Invalid user id']);
        exit;
    }
    $result = file_exists($entryFile) ? unlink($entryFile) : false;
    echo json_encode([
        'status' => $result ? 'ok' : 'error',
        'message' => $result ? 'User deleted successfully' : 'Failed to delete user'
    ]);
    exit;
}

// Default response
echo json_encode(['status' => 'error', 'message' => 'Invalid action']); 