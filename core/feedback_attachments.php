<?php
/**
 * Alpha feedback file attachments (images + short MP4 screen recordings).
 */

declare(strict_types=1);

const FEEDBACK_ATTACHMENT_MAX_COUNT = 5;
const FEEDBACK_ATTACHMENT_MAX_BYTES = 5242880;
const FEEDBACK_RELAY_ATTACHMENT_MAX_BYTES = 1572864;

/**
 * @return array<string, string>
 */
function feedback_attachment_allowed_mimes(): array
{
    return [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/gif' => 'gif',
        'image/webp' => 'webp',
        'video/mp4' => 'mp4',
    ];
}

function feedback_attachments_dir(string $feedbackId): string
{
    $feedbackId = preg_replace('/[^a-f0-9\-]/', '', strtolower($feedbackId)) ?? '';
    return dirname(__DIR__) . '/data/feedback/' . $feedbackId . '/attachments';
}

/**
 * @return list<array{stored_name:string,original_name:string,mime:string,size:int}>
 */
function feedback_process_uploads(string $feedbackId): array
{
    if (!isset($_FILES['attachments'])) {
        return [];
    }

    $files = $_FILES['attachments'];
    if (!is_array($files['name'] ?? null)) {
        return feedback_process_single_upload($feedbackId, $files);
    }

    $saved = [];
    $count = count($files['name']);
    for ($i = 0; $i < $count && count($saved) < FEEDBACK_ATTACHMENT_MAX_COUNT; $i++) {
        $file = [
            'name' => $files['name'][$i] ?? '',
            'type' => $files['type'][$i] ?? '',
            'tmp_name' => $files['tmp_name'][$i] ?? '',
            'error' => $files['error'][$i] ?? UPLOAD_ERR_NO_FILE,
            'size' => $files['size'][$i] ?? 0,
        ];
        $one = feedback_save_uploaded_file($feedbackId, $file);
        if ($one !== null) {
            $saved[] = $one;
        }
    }

    return $saved;
}

/**
 * @param array<string, mixed> $file
 * @return list<array{stored_name:string,original_name:string,mime:string,size:int}>
 */
function feedback_process_single_upload(string $feedbackId, array $file): array
{
    $one = feedback_save_uploaded_file($feedbackId, $file);
    return $one !== null ? [$one] : [];
}

/**
 * @param array<string, mixed> $file
 * @return array{stored_name:string,original_name:string,mime:string,size:int}|null
 */
function feedback_save_uploaded_file(string $feedbackId, array $file): ?array
{
    $error = (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE);
    if ($error === UPLOAD_ERR_NO_FILE) {
        return null;
    }
    if ($error !== UPLOAD_ERR_OK) {
        return null;
    }

    $tmp = (string) ($file['tmp_name'] ?? '');
    if ($tmp === '' || !is_uploaded_file($tmp)) {
        return null;
    }

    $size = (int) ($file['size'] ?? 0);
    if ($size <= 0 || $size > FEEDBACK_ATTACHMENT_MAX_BYTES) {
        return null;
    }

    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = strtolower((string) $finfo->file($tmp));
    $allowed = feedback_attachment_allowed_mimes();
    if (!isset($allowed[$mime])) {
        return null;
    }

    $dir = feedback_attachments_dir($feedbackId);
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }

    $storedName = bin2hex(random_bytes(8)) . '.' . $allowed[$mime];
    $dest = $dir . '/' . $storedName;
    if (!move_uploaded_file($tmp, $dest)) {
        return null;
    }

    $original = basename((string) ($file['name'] ?? 'attachment'));
    $original = preg_replace('/[^a-zA-Z0-9._\- ]/', '_', $original) ?? 'attachment';

    return [
        'stored_name' => $storedName,
        'original_name' => $original,
        'mime' => $mime,
        'size' => $size,
    ];
}

/**
 * @param list<array<string, mixed>> $attachments
 * @return list<array<string, mixed>>
 */
function feedback_relay_attachments_payload(string $feedbackId, array $attachments): array
{
    $payload = [];
    $total = 0;
    $dir = feedback_attachments_dir($feedbackId);

    foreach ($attachments as $att) {
        if (!is_array($att)) {
            continue;
        }
        $stored = (string) ($att['stored_name'] ?? '');
        if ($stored === '' || preg_match('/[^a-zA-Z0-9._-]/', $stored)) {
            continue;
        }
        $path = $dir . '/' . $stored;
        if (!is_file($path)) {
            continue;
        }
        $size = (int) ($att['size'] ?? filesize($path));
        if ($size <= 0 || $size > FEEDBACK_ATTACHMENT_MAX_BYTES) {
            continue;
        }
        if ($total + $size > FEEDBACK_RELAY_ATTACHMENT_MAX_BYTES) {
            break;
        }
        $binary = file_get_contents($path);
        if ($binary === false) {
            continue;
        }
        $total += $size;
        $payload[] = [
            'filename' => (string) ($att['original_name'] ?? $stored),
            'mime' => (string) ($att['mime'] ?? 'application/octet-stream'),
            'content_base64' => base64_encode($binary),
        ];
    }

    return $payload;
}
