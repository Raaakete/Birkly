<?php
/**
 * Collection groups persistence (content/collections/groups.json).
 * Supports nested tree (max depth 3); migrates legacy flat arrays on load.
 */

const BIRKLY_COLLECTION_GROUPS_MAX_DEPTH = 3;

function birkly_collection_groups_path(): string
{
    return dirname(__DIR__) . '/content/collections/groups.json';
}

/**
 * @param array<string, mixed> $node
 * @return array<string, mixed>
 */
function birkly_normalize_collection_group_node(array $node, int $depth = 1): array
{
    $name = trim((string) ($node['name'] ?? $node['label'] ?? ''));
    $id = trim((string) ($node['id'] ?? ''));
    if ($id === '') {
        $id = 'group-' . uniqid();
    }

    $normalized = [
        'id' => $id,
        'name' => $name !== '' ? $name : 'Untitled Group',
        'collections' => [],
    ];

    if (!empty($node['icon']) && is_string($node['icon'])) {
        $icon = trim($node['icon']);
        if ($icon !== '') {
            $normalized['icon'] = $icon;
        }
    }

    if (!empty($node['collections']) && is_array($node['collections'])) {
        $normalized['collections'] = array_values(array_filter(
            array_map('strval', $node['collections']),
            static fn(string $slug): bool => $slug !== ''
        ));
    }

    if ($depth < BIRKLY_COLLECTION_GROUPS_MAX_DEPTH && !empty($node['children']) && is_array($node['children'])) {
        $children = [];
        foreach ($node['children'] as $child) {
            if (!is_array($child)) {
                continue;
            }
            $children[] = birkly_normalize_collection_group_node($child, $depth + 1);
        }
        if ($children !== []) {
            $normalized['children'] = $children;
        }
    }

    return $normalized;
}

/**
 * @param array<int, mixed> $groups
 * @return array<int, array<string, mixed>>
 */
function birkly_normalize_collection_groups(array $groups): array
{
    $normalized = [];
    foreach ($groups as $group) {
        if (!is_array($group)) {
            continue;
        }

        $id = (string) ($group['id'] ?? '');
        if ($id === 'ungrouped') {
            $collections = $group['collections'] ?? [];
            $normalized[] = [
                'id' => 'ungrouped',
                'name' => trim((string) ($group['name'] ?? 'Ungrouped')),
                'collections' => is_array($collections)
                    ? array_values(array_filter(array_map('strval', $collections), static fn(string $s): bool => $s !== ''))
                    : [],
            ];
            continue;
        }

        $normalized[] = birkly_normalize_collection_group_node($group);
    }

    return $normalized;
}

/**
 * @param array<int, mixed> $groups
 */
function birkly_collection_groups_max_depth(array $groups, int $depth = 1): int
{
    $max = $depth;
    foreach ($groups as $group) {
        if (!is_array($group) || empty($group['children']) || !is_array($group['children'])) {
            continue;
        }
        $childDepth = birkly_collection_groups_max_depth($group['children'], $depth + 1);
        if ($childDepth > $max) {
            $max = $childDepth;
        }
    }

    return $max;
}

/**
 * @param array<int, mixed> $groups
 */
function birkly_validate_collection_groups(array $groups): ?string
{
    if (birkly_collection_groups_max_depth($groups) > BIRKLY_COLLECTION_GROUPS_MAX_DEPTH) {
        return 'Collection groups exceed maximum depth of ' . BIRKLY_COLLECTION_GROUPS_MAX_DEPTH;
    }

    return null;
}

/**
 * @return array<int, array<string, mixed>>
 */
function birkly_load_collection_groups(?string $path = null): array
{
    $path = $path ?? birkly_collection_groups_path();
    if (!is_file($path)) {
        return [];
    }

    $decoded = json_decode((string) file_get_contents($path), true);
    if (!is_array($decoded)) {
        return [];
    }

    return birkly_normalize_collection_groups($decoded);
}

/**
 * @param array<int, mixed> $groups
 */
function birkly_save_collection_groups(array $groups, ?string $path = null): bool
{
    $groups = birkly_normalize_collection_groups($groups);
    $error = birkly_validate_collection_groups($groups);
    if ($error !== null) {
        return false;
    }

    $path = $path ?? birkly_collection_groups_path();
    $dir = dirname($path);
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }

    return file_put_contents($path, json_encode($groups, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)) !== false;
}
