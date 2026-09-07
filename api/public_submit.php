<?php
// api/public_submit.php - Public submission endpoint for Birkly collections (with file upload support)
header('Content-Type: application/json');
require_once __DIR__ . '/domain_auth.php';
setDynamicCORS();
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'OPTIONS') {
    http_response_code(200);
    exit;
}
require_once __DIR__ . '/storage.php';
require_once __DIR__ . '/validate.php';
require_once __DIR__ . '/rate_limit.php';
require_once __DIR__ . '/log.php';
require_once __DIR__ . '/session.php';
require_once __DIR__ . '/../core/plugins.php';
require_once __DIR__ . '/../core/mail/notify.php';
require_once __DIR__ . '/../core/plugin_availability.php';
if (function_exists('load_plugins')) {
    load_plugins();
}

// Public submits should use the site session scope (never create/admin sessions)
if (function_exists('birkly_site_session_start')) {
    birkly_site_session_start();
}

/**
 * Resolve the current storefront user for public submits.
 * Uses UserAuth (plugin session storage fallback) — not raw $_SESSION alone.
 *
 * @return array<string, mixed>|null
 */
function birkly_public_submit_current_user(): ?array
{
    if (!empty($_SESSION['user']['id']) && is_array($_SESSION['user'])) {
        return $_SESSION['user'];
    }

    if (!birkly_require_plugin_file('user_management', 'includes/UserAuth.php')) {
        return null;
    }

    $auth = new UserAuth();
    $user = $auth->getCurrentUser();
    if (!is_array($user) || empty($user['id'])) {
        return null;
    }

    $_SESSION['user'] = $user;
    return $user;
}

// Support JSON, multipart/form-data, and application/x-www-form-urlencoded
$contentType = $_SERVER['CONTENT_TYPE'] ?? '';
if (strpos($contentType, 'multipart/form-data') === 0) {
    $input = $_POST;
    $fields = [];
    foreach ($input as $k => $v) {
        if ($k === 'collection' || $k === '_csrf') continue;
        $fields[$k] = $v;
    }
    $collectionSlug = $_POST['collection'] ?? '';
    $csrf = $_POST['_csrf'] ?? '';
} elseif (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' && !empty($_POST) && strpos($contentType, 'application/json') !== 0) {
    // Regular HTML form POST
    $input = $_POST;
    $fields = [];
    foreach ($input as $k => $v) {
        if ($k === 'collection' || $k === '_csrf') continue;
        $fields[$k] = $v;
    }
    $collectionSlug = $_POST['collection'] ?? '';
    $csrf = $_POST['_csrf'] ?? '';
} else {
    $raw = json_decode(file_get_contents('php://input'), true) ?? [];
    $collectionSlug = $raw['collection'] ?? '';
    $fields = $raw['fields'] ?? [];
    $csrf = $raw['_csrf'] ?? '';
}
$ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';

function respond($data, $code = 200) {
    http_response_code($code);
    echo json_encode($data);
    exit;
}

// Rate limiting: 5 submissions per minute per IP
$rateLimiter = new RateLimiter('public_submit_' . $ip . '_' . $collectionSlug, 5, 60);
if (!$rateLimiter->check()) {
    log_security_event('rate_limited', ['action' => 'public_submit', 'ip' => $ip, 'collection' => $collectionSlug]);
    $retryAfter = $rateLimiter->getRetryAfter();
    respond(['status' => 'error', 'message' => 'Too many submissions. Please try again later.', 'retry_after' => $retryAfter], 429);
}

// Load collection config
$storage = new Storage('../content');
$collections = $storage->list('collections');
$dir = null;
foreach ($collections as $d) {
    $meta = $storage->read("collections/$d/collection.json");
    if ($meta && ($meta['slug'] ?? '') === $collectionSlug) {
        $dir = $d;
        $collectionMeta = $meta;
        break;
    }
}
// SECURITY FIX: Use generic error message to prevent collection enumeration
if (!$dir) respond(['status' => 'error', 'message' => 'Invalid request'], 400);

// Check if public submissions are allowed
// Use entry_method === 'public_api' (consistent with other public API endpoints)
if (($collectionMeta['entry_method'] ?? 'admin_only') !== 'public_api') {
    respond(['status' => 'error', 'message' => 'Public submissions not allowed for this collection'], 403);
}

require_once __DIR__ . '/../core/public_read.php';
require_once __DIR__ . '/../core/entry_ownership.php';
require_once __DIR__ . '/domain_auth.php';
$collectionMeta = birkly_public_maybe_migrate_collection_meta($storage, $dir, is_array($collectionMeta) ? $collectionMeta : []);
setDynamicCORS();
$submitAuth = birkly_public_api_auth_check($collectionMeta, $collectionSlug, 'write');
if (empty($submitAuth['allowed'])) {
    log_security_event('public_submit_denied', [
        'collection' => $collectionSlug,
        'reason' => $submitAuth['reason'],
        'ip' => $ip,
        'origin' => $_SERVER['HTTP_ORIGIN'] ?? '',
        'referer' => $_SERVER['HTTP_REFERER'] ?? '',
    ]);
    respond(['status' => 'error', 'message' => 'Authentication required', 'code' => 'AUTH_REQUIRED'], 403);
}

// Load schema
$schema = $storage->read("collections/$dir/schema.json");
if (!$schema || !isset($schema['fields'])) {
    respond(['status' => 'error', 'message' => 'Invalid collection schema'], 400);
}

$fields = birkly_filter_public_submit_fields($fields, $schema);

// Validate and sanitize input
$cleanData = [];
$errors = [];
$fileFields = [];
// Defer required validation for fields we can auto-populate generically
$deferredRequired = [];

// Resolve current site user (user_management login / cross-origin site session)
$currentUser = birkly_public_submit_current_user();
$currentUserId = $currentUser['id'] ?? null;
$currentUserName = $currentUser['name'] ?? ($currentUser['username'] ?? null);
$ownerFieldConfig = birkly_get_owner_field_config($schema);
$ownerFieldName = is_array($ownerFieldConfig) ? (string) ($ownerFieldConfig['name'] ?? '') : '';

foreach ($schema['fields'] as $field) {
    $name = $field['name'];
    $required = $field['required'] ?? false;
    $type = $field['type'] ?? 'text';
    $value = $fields[$name] ?? '';
    if ($required && trim($value) === '' && !isset($_FILES[$name])) {
        // Allow slug to be auto-generated later
        if ($name === 'slug') {
            $deferredRequired[] = $name;
        } else if ($ownerFieldName !== '' && $name === $ownerFieldName && $currentUserId) {
            // Allow schema owner field to be auto-populated from session
            $deferredRequired[] = $name;
        } else if (in_array($name, ['author_user_id', 'author_id', 'author_name'], true) && $currentUserId) {
            // Allow ownership fields to be auto-populated from session
            $deferredRequired[] = $name;
        } else {
            $errors[] = "$name is required";
        }
    }
    // Basic type validation (extend as needed)
    if ($type === 'email' && $value && !filter_var($value, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "$name must be a valid email address";
    }
    // File/media/image field
    if (($type === 'media' || $type === 'file' || $type === 'image') && isset($_FILES[$name])) {
        $fileFields[$name] = $field;
    } else if ($type === 'wysiwyg' || $type === 'media') {
        $cleanData[$name] = $value;
    } else if ($type === 'checkbox') {
        $cleanData[$name] = !empty($value) && $value !== '0' && $value !== 'false' && $value !== 'no';
    } else {
        $cleanData[$name] = sanitize_string($value);
    }
}

// Optional system title (not always in schema) — used for list/detail display.
if (isset($fields['title']) && is_string($fields['title']) && trim($fields['title']) !== '') {
    $cleanData['title'] = sanitize_string($fields['title']);
}

// -------------------------------------------------------------------------
// Generic ownership + visibility defaults (system-wide, not project-specific)
// -------------------------------------------------------------------------

// Schema-driven owner field (type=user, ownership=true)
try {
    $cleanData = birkly_stamp_entry_owner(
        $cleanData,
        $schema,
        $currentUserId ? (string) $currentUserId : null,
        false
    );
} catch (RuntimeException $e) {
    respond(['status' => 'error', 'message' => $e->getMessage()], 401);
}

// Legacy author fields — keep for backward compatibility until migration completes
foreach (['author_user_id', 'author_id'] as $authorField) {
    foreach ($schema['fields'] as $f) {
        if (($f['name'] ?? '') !== $authorField) continue;
        $required = !empty($f['required']);
        if ($required && !$currentUserId) {
            respond(['status' => 'error', 'message' => 'Authentication required'], 401);
        }
        if ($currentUserId) {
            $cleanData[$authorField] = (string)$currentUserId;
        }
        break;
    }
}

// If schema contains author_name and we have it, set it from session
foreach ($schema['fields'] as $f) {
    if (($f['name'] ?? '') === 'author_name' && $currentUserName) {
        $cleanData['author_name'] = (string)$currentUserName;
        break;
    }
}

// Default visibility settings from collection meta (if schema contains those fields)
// This allows site admins to configure visibility per-collection without hardcoding.
$defaultVisibility = $collectionMeta['default_visibility'] ?? null;
$defaultRelField = $collectionMeta['default_visibility_relationship_field'] ?? null;
$defaultRelMode = $collectionMeta['default_visibility_relationship_mode'] ?? null;

foreach ($schema['fields'] as $f) {
    $n = $f['name'] ?? '';
    if ($n === 'visibility' && (trim((string)($cleanData['visibility'] ?? '')) === '') && $defaultVisibility) {
        $cleanData['visibility'] = (string)$defaultVisibility;
    }
    if ($n === 'visibility_relationship_field' && (trim((string)($cleanData['visibility_relationship_field'] ?? '')) === '') && $defaultRelField) {
        $cleanData['visibility_relationship_field'] = (string)$defaultRelField;
    }
    if ($n === 'visibility_relationship_mode' && (trim((string)($cleanData['visibility_relationship_mode'] ?? '')) === '') && $defaultRelMode) {
        $cleanData['visibility_relationship_mode'] = (string)$defaultRelMode;
    }
}

// Generic slug auto-generation (system-wide, not project-specific):
// If schema contains a `slug` field and it's empty, generate it from the first meaningful text field.
if (array_key_exists('slug', $cleanData) && trim((string)($cleanData['slug'] ?? '')) === '') {
    $source = '';
    foreach (['title', 'name', 'content'] as $candidate) {
        $v = $cleanData[$candidate] ?? '';
        if (is_string($v) && trim($v) !== '') { $source = $v; break; }
    }
    $source = trim($source);
    if ($source !== '') {
        $s = strtolower($source);
        $s = preg_replace('/[^a-z0-9]+/', '-', $s);
        $s = trim($s, '-');
        if ($s === '') $s = 'entry';
        $cleanData['slug'] = substr($s, 0, 80);
    }
}

// Apply deferred required checks (e.g., slug after auto-generation)
foreach ($deferredRequired as $name) {
    $v = $cleanData[$name] ?? '';
    if (is_string($v) && trim($v) === '') {
        $errors[] = "$name is required";
    }
}
if ($errors) {
    respond(['status' => 'error', 'message' => 'Validation failed', 'errors' => $errors], 422);
}
$antiSpam = $collectionMeta['anti_spam'] ?? [];

// Honeypot (already implemented)
if (!empty($antiSpam['honeypot']) && !empty($fields['website'])) {
    log_security_event('public_submission_spam', ['type' => 'honeypot', 'ip' => $ip, 'collection' => $collectionSlug]);
    respond(['status' => 'error', 'message' => 'Bot detected'], 400);
}

// Simple math CAPTCHA
if (!empty($antiSpam['captcha'])) {
    $expected = $_SESSION['public_captcha'] ?? null;
    $provided = $fields['captcha'] ?? null;
    if ($expected === null || $provided === null || (string)$expected !== trim((string)$provided)) {
        log_security_event('public_submission_spam', ['type' => 'captcha', 'ip' => $ip, 'collection' => $collectionSlug]);
        respond(['status' => 'error', 'message' => 'Invalid CAPTCHA'], 400);
    }
    unset($_SESSION['public_captcha']); // one-time use
}

// Akismet (scaffold)
if (!empty($antiSpam['akismet']) && !empty($antiSpam['akismet_api_key'])) {
    $akismetKey = $antiSpam['akismet_api_key'];
    $akismetBlog = $antiSpam['akismet_blog'] ?? ($_SERVER['HTTP_HOST'] ?? 'http://localhost');
    $comment = [
        'blog' => $akismetBlog,
        'user_ip' => $ip,
        'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
        'referrer' => $_SERVER['HTTP_REFERER'] ?? '',
        'comment_type' => 'public_submission',
        'comment_author' => $fields['name'] ?? '',
        'comment_author_email' => $fields['email'] ?? '',
        'comment_content' => json_encode($fields)
    ];
    $akismetUrl = "https://$akismetKey.rest.akismet.com/1.1/comment-check";
    $opts = [
        'http' => [
            'method' => 'POST',
            'header' => "Content-type: application/x-www-form-urlencoded",
            'content' => http_build_query($comment)
        ]
    ];
    $context = stream_context_create($opts);
    $result = @file_get_contents($akismetUrl, false, $context);
    if ($result === 'true') {
        log_security_event('public_submission_spam', ['type' => 'akismet', 'ip' => $ip, 'collection' => $collectionSlug]);
        respond(['status' => 'error', 'message' => 'Submission flagged as spam'], 400);
    }
}

// Handle file uploads
$mediaDir = __DIR__ . '/../media/public_submissions';
if (!file_exists($mediaDir)) mkdir($mediaDir, 0755, true);
foreach ($fileFields as $name => $field) {
    $file = $_FILES[$name];
    if ($file['error'] !== UPLOAD_ERR_OK) {
        $errors[] = "$name upload failed";
        continue;
    }
    // SECURITY FIX: Validate file type/size using detected MIME type from file content
    $allowedTypes = $field['allowed_types'] ?? ['image/jpeg','image/png','application/pdf'];
    $maxSize = $field['max_size'] ?? 5*1024*1024;
    
    // Always use finfo_file() to detect MIME type from file content, not client-provided type
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $detectedMimeType = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);
    
    if (!in_array($detectedMimeType, $allowedTypes)) {
        $errors[] = "$name: Invalid file type";
        continue;
    }
    
    // Additional validation for images
    if (strpos($detectedMimeType, 'image/') === 0) {
        $imageInfo = @getimagesize($file['tmp_name']);
        if ($imageInfo === false) {
            $errors[] = "$name: Invalid image file";
            continue;
        }
    }
    if ($file['size'] > $maxSize) {
        $errors[] = "$name: File too large (max " . ($maxSize/1024/1024) . "MB)";
        continue;
    }
    // Generate unique filename
    $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $filename = uniqid() . '.' . $extension;
    $filepath = $mediaDir . '/' . $filename;
    if (!move_uploaded_file($file['tmp_name'], $filepath)) {
        $errors[] = "$name: Failed to save file";
        continue;
    }
    $cleanData[$name] = [
        'name' => $file['name'],
        'type' => $detectedMimeType,
        'size' => $file['size'],
        'url' => '/media/public_submissions/' . $filename
    ];
}

// If title still empty, derive from common name fields.
if (trim((string) ($cleanData['title'] ?? '')) === '') {
    foreach (['display_name', 'name', 'organization'] as $candidate) {
        $v = $cleanData[$candidate] ?? '';
        if (is_string($v) && trim($v) !== '') {
            $cleanData['title'] = trim($v);
            break;
        }
    }
}
if ($errors) {
    respond(['status' => 'error', 'message' => 'File upload failed', 'errors' => $errors], 422);
}

// Create entry
$id = uniqid();
$cleanData['id'] = $id;
$cleanData['created_at'] = date('c');
$cleanData['updated_at'] = date('c');
$apiSettings = is_array($collectionMeta['api_settings'] ?? null) ? $collectionMeta['api_settings'] : [];
$autoPublish = !empty($apiSettings['auto_publish']);
$defaultStatus = $collectionMeta['default_public_status'] ?? null;
if ($autoPublish) {
    $cleanData['status'] = 'published';
} elseif (is_string($defaultStatus) && $defaultStatus !== '') {
    $cleanData['status'] = $defaultStatus;
} else {
    $cleanData['status'] = 'pending';
}

try {
    if (function_exists('apply_filters_strict')) {
        $cleanData = apply_filters_strict('before_entry_create', $cleanData, $collectionSlug);
    }
    if (function_exists('do_action_strict')) {
        do_action_strict('before_entry_create', $collectionSlug, $cleanData);
    }
} catch (Exception $e) {
    respond(['status' => 'error', 'message' => $e->getMessage()], 403);
}

$entryPath = "collections/$dir/entries/$id.json";
$ok = $storage->write($entryPath, $cleanData);

log_security_event('public_submission', ['collection' => $collectionSlug, 'ip' => $ip, 'id' => $id, 'ok' => $ok]);

// Email notification (if enabled)
$apiSettings = is_array($collectionMeta['api_settings'] ?? null) ? $collectionMeta['api_settings'] : [];
$notifyTo = trim((string) ($apiSettings['notification_email'] ?? ''));
if ($ok && $notifyTo !== '') {
    require_once __DIR__ . '/../core/connections/resolve.php';
    $to = $notifyTo;
    $subject = 'New Submission: ' . ($collectionMeta['title'] ?? $collectionSlug);
    $body = "A new submission was received for collection: " . ($collectionMeta['title'] ?? $collectionSlug) . "\n\n";
    foreach ($cleanData as $k => $v) {
        if (is_array($v) && isset($v['url'])) {
            $body .= "$k: [file] " . $v['url'] . "\n";
        } else if ($k !== 'id' && $k !== 'created_at' && $k !== 'updated_at' && $k !== 'status') {
            $body .= "$k: $v\n";
        }
    }
    $body .= "\nSubmitted at: " . $cleanData['created_at'] . "\nIP: $ip\n";
    $sendResult = birkly_send_api_notification_mail($apiSettings, $to, $subject, $body, [
        'source' => 'public_submit',
        'collection' => $collectionSlug,
        'id' => $id,
    ]);
    $sent = in_array($sendResult['status'] ?? '', ['queued', 'accepted', 'logged'], true);
    log_security_event('public_submission_email', ['collection' => $collectionSlug, 'id' => $id, 'to' => $to, 'sent' => $sent, 'send_status' => $sent ? 'success' : 'failed']);
}

if ($ok) {
    if (function_exists('do_action')) {
        do_action('after_entry_save', $cleanData, $collectionSlug, null);
    }
    respond([
        'status' => 'ok',
        'message' => 'Submission received. Thank you!',
        'id' => $id,
        'entry_id' => $id,
        'slug' => (string) ($cleanData['slug'] ?? ''),
        'data' => [
            'id' => $id,
            'slug' => (string) ($cleanData['slug'] ?? ''),
            'status' => (string) ($cleanData['status'] ?? ''),
        ],
    ]);
} else {
    respond(['status' => 'error', 'message' => 'Failed to save submission'], 500);
} 