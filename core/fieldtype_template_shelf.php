<?php
/**
 * Core fieldtypes register template-shelf helpers (media, gallery, oEmbed, relations).
 */

declare(strict_types=1);

require_once __DIR__ . '/plugins/template_functions.php';
require_once __DIR__ . '/media_storage.php';
require_once __DIR__ . '/dynamicitems_presentation.php';
require_once __DIR__ . '/relation_resolve.php';

function birkly_media_resolve_item(string $mediaId): ?array
{
    $mediaId = trim($mediaId);
    if ($mediaId === '' || !birkly_validate_media_id($mediaId)) {
        return null;
    }

    $metadata = birkly_load_media_metadata();
    if (isset($metadata[$mediaId]) && is_array($metadata[$mediaId])) {
        $item = $metadata[$mediaId];
        $url = (string) ($item['url'] ?? '');
        if ($url !== '') {
            return $item;
        }
    }

    $mediaDir = birkly_media_dir();
    $extensions = ['webp', 'jpg', 'jpeg', 'png', 'gif', 'svg', 'pdf', 'mp4', 'webm'];
    foreach ($extensions as $ext) {
        $path = $mediaDir . '/' . $mediaId . '.' . $ext;
        if (is_file($path)) {
            return [
                'id' => $mediaId,
                'url' => birkly_media_public_url('', $mediaId, $ext),
                'ext' => $ext,
                'name' => $mediaId . '.' . $ext,
            ];
        }
    }

    return null;
}

function birkly_fieldtype_register_template_shelf(): void
{
    static $registered = false;
    if ($registered) {
        return;
    }
    $registered = true;

    register_template_function('media_url', static function ($mediaId) {
        $item = birkly_media_resolve_item((string) $mediaId);
        return $item ? (string) ($item['url'] ?? '') : '';
    }, [
        'description' => 'Public URL for a media library id (file or gallery item).',
        'arguments' => ['media_id' => 'string'],
    ]);

    register_template_function('media_img', static function ($mediaId, $alt = '') {
        $item = birkly_media_resolve_item((string) $mediaId);
        if (!$item) {
            return '';
        }
        $url = htmlspecialchars((string) ($item['url'] ?? ''), ENT_QUOTES, 'UTF-8');
        $altText = htmlspecialchars((string) $alt, ENT_QUOTES, 'UTF-8');
        if ($altText === '' && isset($item['alt_text'])) {
            $altText = htmlspecialchars((string) $item['alt_text'], ENT_QUOTES, 'UTF-8');
        }

        return '<img src="' . $url . '" alt="' . $altText . '" loading="lazy">';
    }, [
        'description' => 'HTML img tag for a media library id.',
        'arguments' => ['media_id' => 'string', 'alt' => 'string?'],
    ]);

    register_template_function('gallery_html', static function ($value) {
        $ids = [];
        if (is_array($value)) {
            $ids = $value;
        } elseif (is_string($value) && $value !== '') {
            $decoded = json_decode($value, true);
            $ids = is_array($decoded) ? $decoded : [$value];
        }
        $parts = [];
        foreach ($ids as $id) {
            if (!is_string($id) && !is_numeric($id)) {
                continue;
            }
            $item = birkly_media_resolve_item((string) $id);
            if (!$item) {
                continue;
            }
            $url = htmlspecialchars((string) ($item['url'] ?? ''), ENT_QUOTES, 'UTF-8');
            $alt = htmlspecialchars((string) ($item['alt_text'] ?? $item['name'] ?? $id), ENT_QUOTES, 'UTF-8');
            $parts[] = '<figure class="birkly-gallery-item"><img src="' . $url . '" alt="' . $alt . '" loading="lazy"></figure>';
        }
        if ($parts === []) {
            return '';
        }

        return '<div class="birkly-gallery">' . implode('', $parts) . '</div>';
    }, [
        'description' => 'Gallery grid HTML from a gallery field value (array or JSON string of media ids).',
        'arguments' => ['gallery_value' => 'array|string'],
    ]);

    register_template_function('oembed_html', static function ($urlOrEmbed) {
        $embed = trim((string) $urlOrEmbed);
        if ($embed === '') {
            return '';
        }
        if (!preg_match('#^https://(www\.)?(youtube\.com/embed/|player\.vimeo\.com/video/)#i', $embed)) {
            require_once dirname(__DIR__) . '/fieldtypes/OEmbed/OEmbed.php';
            $probe = new \Birkly\FieldTypes\OEmbed\OEmbed(['name' => '_', 'label' => '_', 'value' => $embed]);
            if (method_exists($probe, 'resolveEmbedUrlForTemplate')) {
                $resolved = $probe->resolveEmbedUrlForTemplate($embed);
                if ($resolved !== null) {
                    $embed = $resolved;
                }
            }
        }
        $src = htmlspecialchars($embed, ENT_QUOTES, 'UTF-8');

        return '<div class="birkly-oembed"><iframe src="' . $src . '" loading="lazy" allowfullscreen referrerpolicy="strict-origin-when-cross-origin"></iframe></div>';
    }, [
        'description' => 'Responsive oEmbed iframe HTML for a stored oEmbed URL or watch link.',
        'arguments' => ['url' => 'string'],
    ]);

    register_template_function('relation_titles', static function ($value) {
        $titles = birkly_relation_resolve_titles($value);
        return implode(', ', $titles);
    }, [
        'description' => 'Comma-separated relation entry titles (loads titles from storage when missing).',
        'arguments' => ['relation_value' => 'array|string'],
    ]);

    register_template_function('relation_links', static function ($value) {
        $links = birkly_relation_resolve_links($value);
        if ($links === []) {
            return '';
        }
        $parts = [];
        foreach ($links as $link) {
            $href = htmlspecialchars((string) ($link['url'] ?? ''), ENT_QUOTES, 'UTF-8');
            $title = htmlspecialchars((string) ($link['title'] ?? ''), ENT_QUOTES, 'UTF-8');
            $parts[] = '<a class="birkly-relation-link" href="' . $href . '">' . $title . '</a>';
        }

        return '<div class="birkly-relation-links">' . implode(' ', $parts) . '</div>';
    }, [
        'description' => 'HTML links to related entries (?collection=&slug=) from a relation field value.',
        'arguments' => ['relation_value' => 'array|string'],
    ]);

    register_template_function('dynamicitems_html', static function ($value) {
        return birkly_dynamicitems_render_html($value);
    }, [
        'description' => 'Render DynamicItems blocks (markdown/editor as HTML, other types escaped).',
        'arguments' => ['dynamicitems_value' => 'array|string'],
    ]);
}
