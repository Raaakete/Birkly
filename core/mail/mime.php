<?php
/**
 * MIME message builder shared by SMTP and raw-email transports.
 */

declare(strict_types=1);

require_once __DIR__ . '/attachments.php';

/**
 * Build a complete RFC 5322 MIME message (headers + body), without SMTP dot-stuffing.
 *
 * @param list<array{filename: string, content: string, mime_type: string}> $attachments
 */
function birkly_mail_build_mime_message(
    string $fromName,
    string $fromEmail,
    array $to,
    string $subject,
    string $bodyHtml,
    string $bodyText,
    array $attachments = []
): string {
    $headers = [
        'Date: ' . date('r'),
        'From: ' . birkly_mail_encode_address($fromName, $fromEmail),
        'To: <' . implode('>, <', $to) . '>',
        'Subject: ' . birkly_mail_encode_header($subject),
        'MIME-Version: 1.0',
    ];

    $bodyPart = birkly_mail_build_body_part($bodyHtml, $bodyText);
    if ($attachments === []) {
        $headers[] = 'Content-Type: ' . $bodyPart['content_type'];
        if (!empty($bodyPart['transfer_encoding'])) {
            $headers[] = 'Content-Transfer-Encoding: ' . $bodyPart['transfer_encoding'];
        }
        return implode("\r\n", $headers) . "\r\n\r\n" . $bodyPart['body'];
    }

    $mixedBoundary = 'birkly_mixed_' . bin2hex(random_bytes(12));
    $headers[] = 'Content-Type: multipart/mixed; boundary="' . $mixedBoundary . '"';

    $message = '';
    $message .= '--' . $mixedBoundary . "\r\n";
    $message .= 'Content-Type: ' . $bodyPart['content_type'] . "\r\n";
    if (!empty($bodyPart['transfer_encoding'])) {
        $message .= 'Content-Transfer-Encoding: ' . $bodyPart['transfer_encoding'] . "\r\n";
    }
    $message .= "\r\n" . $bodyPart['body'] . "\r\n";

    foreach ($attachments as $att) {
        $message .= '--' . $mixedBoundary . "\r\n";
        $message .= 'Content-Type: ' . $att['mime_type'] . '; name="' . birkly_mail_mime_encode_filename($att['filename']) . '"' . "\r\n";
        $message .= 'Content-Transfer-Encoding: base64' . "\r\n";
        $message .= 'Content-Disposition: attachment; filename="' . birkly_mail_mime_encode_filename($att['filename']) . '"' . "\r\n\r\n";
        $message .= chunk_split(base64_encode($att['content']), 76, "\r\n");
        $message .= "\r\n";
    }

    $message .= '--' . $mixedBoundary . '--';

    return implode("\r\n", $headers) . "\r\n\r\n" . $message;
}

/**
 * @return array{content_type: string, transfer_encoding: string, body: string}
 */
function birkly_mail_build_body_part(string $bodyHtml, string $bodyText): array
{
    $hasHtml = $bodyHtml !== '';
    $hasText = $bodyText !== '';

    if ($hasHtml && $hasText) {
        $boundary = 'birkly_alt_' . bin2hex(random_bytes(12));
        $parts = "--" . $boundary . "\r\n";
        $parts .= "Content-Type: text/plain; charset=UTF-8\r\n";
        $parts .= "Content-Transfer-Encoding: 8bit\r\n\r\n";
        $parts .= $bodyText . "\r\n\r\n";
        $parts .= "--" . $boundary . "\r\n";
        $parts .= "Content-Type: text/html; charset=UTF-8\r\n";
        $parts .= "Content-Transfer-Encoding: 8bit\r\n\r\n";
        $parts .= $bodyHtml . "\r\n\r\n";
        $parts .= "--" . $boundary . "--";

        return [
            'content_type' => 'multipart/alternative; boundary="' . $boundary . '"',
            'transfer_encoding' => '',
            'body' => $parts,
        ];
    }

    if ($hasHtml) {
        return [
            'content_type' => 'text/html; charset=UTF-8',
            'transfer_encoding' => '8bit',
            'body' => $bodyHtml,
        ];
    }

    return [
        'content_type' => 'text/plain; charset=UTF-8',
        'transfer_encoding' => '8bit',
        'body' => $bodyText,
    ];
}

function birkly_mail_mime_encode_filename(string $filename): string
{
    $filename = str_replace(["\r", "\n", '"'], '', $filename);
    if (preg_match('/^[\x20-\x7E]*$/', $filename)) {
        return $filename;
    }
    return '=?UTF-8?B?' . base64_encode($filename) . '?=';
}
