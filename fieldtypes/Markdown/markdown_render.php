<?php
/**
 * PHP subset of the Markdown fieldtype preview renderer (parity with fieldtype.js).
 */

declare(strict_types=1);

function birkly_markdown_escape_html(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function birkly_markdown_safe_link(string $url): string
{
    $url = trim($url);
    if (preg_match('/^(https?:\/\/|mailto:)/i', $url)) {
        return birkly_markdown_escape_html($url);
    }

    return '#';
}

function birkly_markdown_render_inline(string $text): string
{
    $html = birkly_markdown_escape_html($text);
    $html = preg_replace_callback(
        '/\[([^\]]+)\]\(([^)]+)\)/',
        static function (array $m): string {
            $label = birkly_markdown_escape_html($m[1]);
            $href = birkly_markdown_safe_link($m[2]);
            return '<a href="' . $href . '" target="_blank" rel="noopener noreferrer">' . $label . '</a>';
        },
        $html
    ) ?? $html;
    $html = preg_replace('/`([^`]+)`/', '<code>$1</code>', $html) ?? $html;
    $html = preg_replace('/\*\*([^*]+)\*\*/', '<strong>$1</strong>', $html) ?? $html;
    $html = preg_replace('/\*([^*]+)\*/', '<em>$1</em>', $html) ?? $html;

    return $html;
}

function birkly_markdown_render(string $source): string
{
    $lines = preg_split('/\r\n|\r|\n/', (string) $source) ?: [];
    $html = [];
    $inUl = false;
    $inOl = false;

    $closeLists = static function () use (&$html, &$inUl, &$inOl): void {
        if ($inUl) {
            $html[] = '</ul>';
            $inUl = false;
        }
        if ($inOl) {
            $html[] = '</ol>';
            $inOl = false;
        }
    };

    foreach ($lines as $line) {
        $trimmed = trim((string) $line);
        if ($trimmed === '') {
            $closeLists();
            continue;
        }

        if (preg_match('/^(#{1,6})\s+(.+)$/', $trimmed, $m)) {
            $closeLists();
            $level = strlen($m[1]);
            $html[] = '<h' . $level . '>' . birkly_markdown_render_inline($m[2]) . '</h' . $level . '>';
            continue;
        }

        if (preg_match('/^>\s?(.*)$/', $trimmed, $m)) {
            $closeLists();
            $html[] = '<blockquote>' . birkly_markdown_render_inline($m[1]) . '</blockquote>';
            continue;
        }

        if (str_starts_with($trimmed, '- ')) {
            if (!$inUl) {
                $closeLists();
                $html[] = '<ul>';
                $inUl = true;
            }
            $html[] = '<li>' . birkly_markdown_render_inline(substr($trimmed, 2)) . '</li>';
            continue;
        }

        if (preg_match('/^\d+\.\s+(.+)$/', $trimmed, $m)) {
            if (!$inOl) {
                $closeLists();
                $html[] = '<ol>';
                $inOl = true;
            }
            $html[] = '<li>' . birkly_markdown_render_inline($m[1]) . '</li>';
            continue;
        }

        $closeLists();
        $html[] = '<p>' . birkly_markdown_render_inline($trimmed) . '</p>';
    }

    $closeLists();

    return implode('', $html);
}
