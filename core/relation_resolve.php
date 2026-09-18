<?php

declare(strict_types=1);

require_once __DIR__ . '/content_resolve.php';

/**
 * @param mixed $value
 * @return list<string>
 */
function birkly_relation_resolve_titles($value, $storage = null): array
{
    if (is_string($value) && (str_starts_with($value, '[') || str_starts_with($value, '{'))) {
        $decoded = json_decode($value, true);
        if (is_array($decoded)) {
            $value = $decoded;
        }
    }
    if (!is_array($value)) {
        return [];
    }

    if ($storage === null) {
        require_once __DIR__ . '/storage/StorageFactory.php';
        $storage = get_storage();
    }

    $titles = [];
    foreach ($value as $item) {
        if (is_scalar($item)) {
            $title = birkly_relation_load_entry_title($storage, 'unknown', (string) $item);
            if ($title !== '') {
                $titles[] = $title;
            }
            continue;
        }
        if (!is_array($item)) {
            continue;
        }
        if (!empty($item['title']) && is_string($item['title'])) {
            $titles[] = $item['title'];
            continue;
        }
        $id = (string) ($item['id'] ?? '');
        $collection = (string) ($item['collection'] ?? $item['collectionSlug'] ?? '');
        if ($id === '') {
            continue;
        }
        $title = birkly_relation_load_entry_title($storage, $collection, $id);
        if ($title !== '') {
            $titles[] = $title;
        } elseif (!empty($item['label'])) {
            $titles[] = (string) $item['label'];
        } else {
            $titles[] = $id;
        }
    }

    return $titles;
}

function birkly_relation_load_entry_title($storage, string $collectionSlug, string $entryRef): string
{
    $entryRef = trim($entryRef);
    if ($entryRef === '') {
        return '';
    }

    $dir = $collectionSlug !== '' && $collectionSlug !== 'unknown'
        ? birkly_content_resolve_collection_dir($storage, $collectionSlug)
        : null;

    if ($dir !== null) {
        $direct = $storage->read('collections/' . $dir . '/entries/' . $entryRef . '.json');
        if (is_array($direct)) {
            return (string) ($direct['title'] ?? $direct['slug'] ?? '');
        }
        foreach ($storage->list('collections/' . $dir . '/entries') as $file) {
            $data = $storage->read('collections/' . $dir . '/entries/' . $file);
            if (!is_array($data)) {
                continue;
            }
            if (($data['id'] ?? '') === $entryRef || ($data['slug'] ?? '') === $entryRef) {
                return (string) ($data['title'] ?? $data['slug'] ?? '');
            }
        }
    }

    return '';
}

/**
 * @param mixed $value
 * @return list<array{title:string,url:string,collection:string}>
 */
function birkly_relation_resolve_links($value, $storage = null): array
{
    if (is_string($value) && (str_starts_with($value, '[') || str_starts_with($value, '{'))) {
        $decoded = json_decode($value, true);
        if (is_array($decoded)) {
            $value = $decoded;
        }
    }
    if (!is_array($value)) {
        return [];
    }

    if ($storage === null) {
        require_once __DIR__ . '/storage/StorageFactory.php';
        $storage = get_storage();
    }

    $links = [];
    foreach ($value as $item) {
        if (!is_array($item)) {
            continue;
        }
        $collection = (string) ($item['collection'] ?? $item['collectionSlug'] ?? '');
        $id = (string) ($item['id'] ?? '');
        $slug = (string) ($item['slug'] ?? '');
        $title = (string) ($item['title'] ?? $item['label'] ?? '');
        if ($title === '' && $id !== '') {
            $title = birkly_relation_load_entry_title($storage, $collection, $id);
        }
        if ($slug === '' && $id !== '' && $collection !== '') {
            $dir = birkly_content_resolve_collection_dir($storage, $collection);
            if ($dir !== null) {
                $data = $storage->read('collections/' . $dir . '/entries/' . $id . '.json');
                if (is_array($data) && !empty($data['slug'])) {
                    $slug = (string) $data['slug'];
                }
            }
        }
        if ($title === '') {
            $title = $slug !== '' ? $slug : $id;
        }
        if ($collection === '' || ($slug === '' && $id === '')) {
            continue;
        }
        $querySlug = $slug !== '' ? $slug : $id;
        $links[] = [
            'title' => $title,
            'collection' => $collection,
            'url' => '?collection=' . rawurlencode($collection) . '&slug=' . rawurlencode($querySlug),
        ];
    }

    return $links;
}
