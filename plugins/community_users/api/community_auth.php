<?php
// api/community_auth.php - Public registration and login for community users
header('Content-Type: application/json');
require_once 'storage.php';
require_once 'validate.php';
require_once 'rate_limit.php';
require_once 'log.php';
session_start();

function respond($data, $code = 200) {
    http_response_code($code);
    echo json_encode($data);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true) ?? [];
$action = $input['action'] ?? '';
$ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';

// Rate limit: 10 attempts per 10 minutes per IP
$rateLimiter = new RateLimiter('community_auth_' . $ip, 10, 600);
if (!$rateLimiter->check()) {
    log_security_event('rate_limited', ['action' => $action, 'ip' => $ip]);
    respond(['status' => 'error', 'message' => 'Too many attempts. Please try again later.'], 429);
}

$collection = 'community_users';
$storage = new Storage();
$dir = null;
$collections = $storage->list('collections');
foreach ($collections as $d) {
    $m = $storage->read("collections/$d/collection.json");
    if ($m && $m['slug'] === $collection) {
        $dir = $d;
        break;
    }
}
if (!$dir) respond(['status' => 'error', 'message' => 'User collection not found.'], 500);

if ($action === 'register') {
    $fields = $input['fields'] ?? [];
    $username = trim($fields['username'] ?? '');
    $email = trim($fields['email'] ?? '');
    $password = $fields['password'] ?? '';
    if ($username === '' || $email === '' || $password === '') {
        respond(['status' => 'error', 'message' => 'All fields are required.'], 400);
    }
    // Validate username/email uniqueness
    $entries = $storage->list("collections/$dir/entries");
    foreach ($entries as $entryFile) {
        $entry = $storage->read("collections/$dir/entries/$entryFile");
        if ($entry) {
            if (strcasecmp($entry['username'] ?? '', $username) === 0) {
                respond(['status' => 'error', 'message' => 'Username already exists.'], 400);
            }
            if (strcasecmp($entry['email'] ?? '', $email) === 0) {
                respond(['status' => 'error', 'message' => 'Email already registered.'], 400);
            }
        }
    }
    // Hash password
    $password_hash = password_hash($password, PASSWORD_DEFAULT);
    $id = uniqid();
    $now = date('c');
    $entry = [
        'id' => $id,
        'username' => $username,
        'email' => $email,
        'password_hash' => $password_hash,
        'avatar' => $fields['avatar'] ?? '',
        'bio' => $fields['bio'] ?? '',
        'created_at' => $now,
        'updated_at' => $now,
        'status' => 'active'
    ];
    $ok = $storage->write("collections/$dir/entries/$id.json", $entry);
    log_security_event('community_register', ['username' => $username, 'ip' => $ip, 'ok' => $ok]);
    if ($ok) {
        $_SESSION['community_user_id'] = $id;
        respond(['status' => 'success', 'message' => 'Registration successful.', 'user' => [ 'id' => $id, 'username' => $username, 'email' => $email ]]);
    } else {
        respond(['status' => 'error', 'message' => 'Registration failed.'], 500);
    }
}

if ($action === 'login') {
    $username = trim($input['username'] ?? '');
    $password = $input['password'] ?? '';
    if ($username === '' || $password === '') {
        respond(['status' => 'error', 'message' => 'Username and password required.'], 400);
    }
    $entries = $storage->list("collections/$dir/entries");
    foreach ($entries as $entryFile) {
        $entry = $storage->read("collections/$dir/entries/$entryFile");
        if ($entry && strcasecmp($entry['username'] ?? '', $username) === 0) {
            if (!empty($entry['status']) && $entry['status'] !== 'active') {
                respond(['status' => 'error', 'message' => 'Account is not active.'], 403);
            }
            if (password_verify($password, $entry['password_hash'] ?? '')) {
                $_SESSION['community_user_id'] = $entry['id'];
                respond(['status' => 'success', 'message' => 'Login successful.', 'user' => [ 'id' => $entry['id'], 'username' => $entry['username'], 'email' => $entry['email'] ]]);
            } else {
                respond(['status' => 'error', 'message' => 'Invalid password.'], 401);
            }
        }
    }
    respond(['status' => 'error', 'message' => 'User not found.'], 404);
}

if ($action === 'logout') {
    unset($_SESSION['community_user_id']);
    respond(['status' => 'success', 'message' => 'Logged out.']);
}

if ($action === 'count') {
    $entries = $storage->list("collections/$dir/entries");
    $count = count($entries);
    respond([
        'status' => 'ok',
        'data' => [
            'count' => $count
        ]
    ]);
}

respond(['status' => 'error', 'message' => 'Invalid action.'], 400); 