<?php
// api/public_submit.php - Public submission endpoint for HaruCMS collections (with file upload support)
header('Content-Type: application/json');
require_once 'storage.php';
require_once 'validate.php';
require_once 'rate_limit.php';
require_once 'log.php';

// Support both JSON and multipart/form-data
if (strpos($_SERVER['CONTENT_TYPE'] ?? '', 'multipart/form-data') === 0) {
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
if (!$dir) respond(['status' => 'error', 'message' => 'Collection not found'], 400);

// Check if public submissions are allowed
if (empty($collectionMeta['public_submission'])) {
    respond(['status' => 'error', 'message' => 'Public submissions not allowed for this collection'], 403);
}

// Load schema
$schema = $storage->read("collections/$dir/schema.json");
if (!$schema || !isset($schema['fields'])) {
    respond(['status' => 'error', 'message' => 'Invalid collection schema'], 400);
}

// Validate and sanitize input
$cleanData = [];
$errors = [];
$fileFields = [];
foreach ($schema['fields'] as $field) {
    $name = $field['name'];
    $required = $field['required'] ?? false;
    $type = $field['type'] ?? 'text';
    $value = $fields[$name] ?? '';
    if ($required && trim($value) === '' && !isset($_FILES[$name])) {
        $errors[] = "$name is required";
    }
    // Basic type validation (extend as needed)
    if ($type === 'email' && $value && !filter_var($value, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "$name must be a valid email address";
    }
    // File/media field
    if (($type === 'media' || $type === 'file') && isset($_FILES[$name])) {
        $fileFields[$name] = $field;
    } else if ($type === 'wysiwyg' || $type === 'media') {
        $cleanData[$name] = $value;
    } else {
        $cleanData[$name] = sanitize_string($value);
    }
}
if ($errors) {
    respond(['status' => 'error', 'message' => 'Validation failed', 'errors' => $errors], 422);
}

// --- Anti-spam checks ---
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
    // Validate file type/size
    $allowedTypes = $field['allowed_types'] ?? ['image/jpeg','image/png','application/pdf'];
    $maxSize = $field['max_size'] ?? 5*1024*1024;
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mimeType = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);
    if (!in_array($mimeType, $allowedTypes)) {
        $errors[] = "$name: Invalid file type";
        continue;
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
        'type' => $mimeType,
        'size' => $file['size'],
        'url' => '/media/public_submissions/' . $filename
    ];
}
if ($errors) {
    respond(['status' => 'error', 'message' => 'File upload failed', 'errors' => $errors], 422);
}

// Create entry
$id = uniqid();
$cleanData['id'] = $id;
$cleanData['created_at'] = date('c');
$cleanData['updated_at'] = date('c');
$cleanData['status'] = $collectionMeta['default_public_status'] ?? 'pending';
$entryPath = "collections/$dir/entries/$id.json";
$ok = $storage->write($entryPath, $cleanData);

log_security_event('public_submission', ['collection' => $collectionSlug, 'ip' => $ip, 'id' => $id, 'ok' => $ok]);

// Email notification (if enabled)
if ($ok && !empty($collectionMeta['notify_email'])) {
    $to = $collectionMeta['notify_email'];
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
    $headers = 'From: noreply@' . ($_SERVER['SERVER_NAME'] ?? 'harucms.local');
    $sent = mail($to, $subject, $body, $headers);
    log_security_event('public_submission_email', ['collection' => $collectionSlug, 'id' => $id, 'to' => $to, 'sent' => $sent]);
}

if ($ok) {
    respond(['status' => 'ok', 'message' => 'Submission received. Thank you!']);
} else {
    respond(['status' => 'error', 'message' => 'Failed to save submission'], 500);
} 