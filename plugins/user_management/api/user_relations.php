<?php
/**
 * User Relations API (site scope)
 *
 * Generic follow/connect relationships stored in a configured profile collection.
 * This stays plugin-owned (no core project-specific endpoints).
 */

header('Content-Type: application/json');

if (!function_exists('get_plugin_state') || !get_plugin_state('user_management')) {
    http_response_code(503);
    echo json_encode(['error' => 'User Management plugin is not active']);
    exit;
}

require_once __DIR__ . '/../includes/UserAuth.php';
require_once __DIR__ . '/../includes/UserService.php';
require_once __DIR__ . '/../includes/ProfileService.php';

function respond_json($data, $code = 200) {
    http_response_code($code);
    echo json_encode($data);
    exit;
}

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$action = $_GET['action'] ?? $_POST['action'] ?? '';

// Require logged in site user
$auth = new UserAuth('site');
$me = $auth->getCurrentUser();
if (!$me || empty($me['id'])) {
    respond_json(['success' => false, 'error' => 'Login required'], 401);
}
$myId = (string)$me['id'];

$profileCollection = function_exists('get_plugin_setting')
    ? (string)get_plugin_setting('user_management', 'profile_collection_slug', '')
    : '';
// Respect the admin toggle for collection profiles
$profileEnabled = function_exists('get_plugin_setting')
    ? (bool)get_plugin_setting('user_management', 'profile_collection_enabled', false)
    : false;

if (!$profileEnabled) {
    respond_json(['success' => false, 'error' => 'Profile collection profiles are disabled'], 400);
}
if ($profileCollection === '') {
    respond_json(['success' => false, 'error' => 'Profile collection is not configured'], 400);
}

$profiles = new ProfileService();
if (!$profiles->collectionExists($profileCollection)) {
    respond_json(['success' => false, 'error' => 'Profile collection does not exist'], 400);
}

$service = new UserService();

// Helpers
function uniq_ids($arr) {
    if (!is_array($arr)) $arr = empty($arr) ? [] : [$arr];
    $out = [];
    foreach ($arr as $v) {
        $s = (string)$v;
        if ($s === '') continue;
        if (!in_array($s, $out, true)) $out[] = $s;
    }
    return $out;
}

function remove_id($arr, $id) {
    $arr = uniq_ids($arr);
    return array_values(array_filter($arr, fn($x) => $x !== $id));
}

try {
    switch ($action) {
        case 'follow': {
            if ($method !== 'POST') respond_json(['success' => false, 'error' => 'Method not allowed'], 405);
            $targetId = (string)($_POST['target_user_id'] ?? '');
            if ($targetId === '' || $targetId === $myId) respond_json(['success' => false, 'error' => 'Invalid target_user_id'], 400);

            $target = $service->getUser($targetId);
            if (!$target) respond_json(['success' => false, 'error' => 'Target user not found'], 404);

            $myProfile = $profiles->ensureProfileForUser($profileCollection, $me);
            $targetProfile = $profiles->ensureProfileForUser($profileCollection, $target);
            if (!$myProfile || !$targetProfile) respond_json(['success' => false, 'error' => 'Profile unavailable'], 500);

            $myProfile['following'] = uniq_ids(array_merge($myProfile['following'] ?? [], [$targetId]));
            $targetProfile['followers'] = uniq_ids(array_merge($targetProfile['followers'] ?? [], [$myId]));

            $profiles->saveProfile($profileCollection, $myProfile);
            $profiles->saveProfile($profileCollection, $targetProfile);

            respond_json(['success' => true, 'message' => 'Followed']);
        }

        case 'unfollow': {
            if ($method !== 'POST') respond_json(['success' => false, 'error' => 'Method not allowed'], 405);
            $targetId = (string)($_POST['target_user_id'] ?? '');
            if ($targetId === '' || $targetId === $myId) respond_json(['success' => false, 'error' => 'Invalid target_user_id'], 400);

            $target = $service->getUser($targetId);
            if (!$target) respond_json(['success' => false, 'error' => 'Target user not found'], 404);

            $myProfile = $profiles->ensureProfileForUser($profileCollection, $me);
            $targetProfile = $profiles->ensureProfileForUser($profileCollection, $target);
            if (!$myProfile || !$targetProfile) respond_json(['success' => false, 'error' => 'Profile unavailable'], 500);

            $myProfile['following'] = remove_id($myProfile['following'] ?? [], $targetId);
            $targetProfile['followers'] = remove_id($targetProfile['followers'] ?? [], $myId);

            $profiles->saveProfile($profileCollection, $myProfile);
            $profiles->saveProfile($profileCollection, $targetProfile);

            respond_json(['success' => true, 'message' => 'Unfollowed']);
        }

        case 'connect': {
            if ($method !== 'POST') respond_json(['success' => false, 'error' => 'Method not allowed'], 405);
            $targetId = (string)($_POST['target_user_id'] ?? '');
            if ($targetId === '' || $targetId === $myId) respond_json(['success' => false, 'error' => 'Invalid target_user_id'], 400);

            $target = $service->getUser($targetId);
            if (!$target) respond_json(['success' => false, 'error' => 'Target user not found'], 404);

            $myProfile = $profiles->ensureProfileForUser($profileCollection, $me);
            $targetProfile = $profiles->ensureProfileForUser($profileCollection, $target);
            if (!$myProfile || !$targetProfile) respond_json(['success' => false, 'error' => 'Profile unavailable'], 500);

            $myProfile['connections'] = uniq_ids(array_merge($myProfile['connections'] ?? [], [$targetId]));
            $targetProfile['connections'] = uniq_ids(array_merge($targetProfile['connections'] ?? [], [$myId]));

            $profiles->saveProfile($profileCollection, $myProfile);
            $profiles->saveProfile($profileCollection, $targetProfile);

            respond_json(['success' => true, 'message' => 'Connected']);
        }

        case 'disconnect': {
            if ($method !== 'POST') respond_json(['success' => false, 'error' => 'Method not allowed'], 405);
            $targetId = (string)($_POST['target_user_id'] ?? '');
            if ($targetId === '' || $targetId === $myId) respond_json(['success' => false, 'error' => 'Invalid target_user_id'], 400);

            $target = $service->getUser($targetId);
            if (!$target) respond_json(['success' => false, 'error' => 'Target user not found'], 404);

            $myProfile = $profiles->ensureProfileForUser($profileCollection, $me);
            $targetProfile = $profiles->ensureProfileForUser($profileCollection, $target);
            if (!$myProfile || !$targetProfile) respond_json(['success' => false, 'error' => 'Profile unavailable'], 500);

            $myProfile['connections'] = remove_id($myProfile['connections'] ?? [], $targetId);
            $targetProfile['connections'] = remove_id($targetProfile['connections'] ?? [], $myId);

            $profiles->saveProfile($profileCollection, $myProfile);
            $profiles->saveProfile($profileCollection, $targetProfile);

            respond_json(['success' => true, 'message' => 'Disconnected']);
        }

        default:
            respond_json(['success' => false, 'error' => 'Invalid action'], 400);
    }
} catch (Exception $e) {
    respond_json(['success' => false, 'error' => $e->getMessage()], 500);
}

