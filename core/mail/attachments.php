<?php
/**
 * Mail attachment normalization for the central pipeline.
 */

declare(strict_types=1);

/**
 * Normalize attachment payloads for transports.
 *
 * Each item supports:
 * - filename (required)
 * - content (raw string) OR path (readable file)
 * - mime_type (optional, inferred from filename when missing)
 *
 * @param mixed $raw
 * @return list<array{filename: string, content: string, mime_type: string}>
 */
function birkly_mail_normalize_attachments(mixed $raw): array
{
    if (!is_array($raw)) {
        return [];
    }

    $out = [];
    foreach ($raw as $item) {
        if (!is_array($item)) {
            continue;
        }
        $filename = trim((string) ($item['filename'] ?? $item['name'] ?? ''));
        if ($filename === '') {
            continue;
        }
        $filename = basename(str_replace(["\0", "\r", "\n"], '', $filename));

        $content = '';
        if (array_key_exists('content', $item)) {
            $content = (string) $item['content'];
        } elseif (!empty($item['path']) && is_string($item['path'])) {
            $path = (string) $item['path'];
            if (is_file($path) && is_readable($path)) {
                $read = file_get_contents($path);
                $content = is_string($read) ? $read : '';
            }
        }
        if ($content === '') {
            continue;
        }

        $mime = trim((string) ($item['mime_type'] ?? $item['content_type'] ?? ''));
        if ($mime === '') {
            $mime = birkly_mail_guess_mime_type($filename);
        }

        $out[] = [
            'filename' => $filename,
            'content' => $content,
            'mime_type' => $mime,
        ];
    }

    return $out;
}

function birkly_mail_guess_mime_type(string $filename): string
{
    $ext = strtolower((string) pathinfo($filename, PATHINFO_EXTENSION));
    return match ($ext) {
        'html', 'htm' => 'text/html; charset=UTF-8',
        'txt' => 'text/plain; charset=UTF-8',
        'pdf' => 'application/pdf',
        'png' => 'image/png',
        'jpg', 'jpeg' => 'image/jpeg',
        'gif' => 'image/gif',
        'csv' => 'text/csv; charset=UTF-8',
        'json' => 'application/json; charset=UTF-8',
        'zip' => 'application/zip',
        default => 'application/octet-stream',
    };
}

/**
 * Encode attachments for JSON ESP APIs (Resend, Postmark).
 *
 * @param list<array{filename: string, content: string, mime_type: string}> $attachments
 * @return list<array<string, string>>
 */
function birkly_mail_attachments_for_json_api(array $attachments, string $nameKey = 'filename', string $contentKey = 'content'): array
{
    $out = [];
    foreach ($attachments as $att) {
        $out[] = [
            $nameKey => $att['filename'],
            $contentKey => base64_encode($att['content']),
            'content_type' => $att['mime_type'],
        ];
    }
    return $out;
}
