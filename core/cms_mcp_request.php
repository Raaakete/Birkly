<?php
/**
 * MCP request helpers — mock JSON body and CSRF bypass for internal admin dispatch.
 */

declare(strict_types=1);

/** @return array<string, mixed> */
function cms_mcp_read_json_body(): array
{
    if (array_key_exists('__CMS_MCP_JSON_INPUT', $GLOBALS)) {
        $raw = $GLOBALS['__CMS_MCP_JSON_INPUT'];
        if (is_array($raw)) {
            return $raw;
        }
        if (is_string($raw) && trim($raw) !== '') {
            $decoded = json_decode($raw, true);
            return is_array($decoded) ? $decoded : [];
        }
        return [];
    }

    $rawInput = file_get_contents('php://input');
    if (!is_string($rawInput) || trim($rawInput) === '') {
        return [];
    }
    $decoded = json_decode($rawInput, true);
    return is_array($decoded) ? $decoded : [];
}

function cms_mcp_csrf_valid(string $csrf, string $actionName): bool
{
    if (!empty($GLOBALS['__CMS_MCP_DISPATCH'])) {
        return true;
    }
    require_once dirname(__DIR__) . '/core/csrf.php';
    if (validate_csrf_token($csrf)) {
        return true;
    }
    require_once dirname(__DIR__) . '/api/log.php';
    log_security_event('permission_denied', ['action' => $actionName, 'reason' => 'invalid CSRF']);
    return false;
}

/**
 * @template T
 * @param callable(): T $fn
 * @return T
 */
function cms_mcp_content_collections_root(): string
{
    return dirname(__DIR__) . '/content/collections';
}

/**
 * True when $absolutePath's parent directory is inside content/collections/.
 */
function cms_mcp_path_within_collections(string $absolutePath): bool
{
    $root = realpath(cms_mcp_content_collections_root());
    if ($root === false) {
        return false;
    }

    $parent = realpath(dirname($absolutePath));
    if ($parent === false) {
        $normalized = str_replace('\\', '/', dirname($absolutePath));
        $rootNorm = rtrim(str_replace('\\', '/', $root), '/');

        return str_starts_with($normalized, $rootNorm . '/');
    }

    $rootNorm = rtrim(str_replace('\\', '/', $root), '/');
    $parentNorm = str_replace('\\', '/', $parent);

    return $parentNorm === $rootNorm || str_starts_with($parentNorm, $rootNorm . '/');
}

/** @return array<string, mixed>|null Error payload, or null when valid. */
function cms_mcp_validate_collection_slug(string $slug): ?array
{
    require_once __DIR__ . '/entry_slug.php';
    if (!birkly_validate_content_slug($slug)) {
        return cms_mcp_error('Invalid collection slug');
    }

    return null;
}

/** @return array<string, mixed>|null Error payload, or null when valid. */
function cms_mcp_validate_entry_id(string $entryId): ?array
{
    require_once __DIR__ . '/entry_slug.php';
    if (!birkly_validate_content_entry_id($entryId)) {
        return cms_mcp_error('Invalid entry id');
    }

    return null;
}

/**
 * Validate sub-entry MCP path inputs and optionally require on-disk entry / sub-collection dirs.
 *
 * @return array<string, mixed>|null Error payload, or null when valid
 */
function cms_mcp_validate_sub_entries_path_context(
    string $collection,
    string $entryId,
    string $subCollection,
    bool $requireSubCollectionDir = false
): ?array {
    if ($collection === '' || $entryId === '' || $subCollection === '') {
        return cms_mcp_error('collection, entry_id, and sub_collection are required');
    }

    $invalid = cms_mcp_validate_collection_slug($collection)
        ?? cms_mcp_validate_entry_id($entryId)
        ?? cms_mcp_validate_collection_slug($subCollection);
    if ($invalid !== null) {
        return $invalid;
    }

    require_once __DIR__ . '/content_resolve.php';
    require_once dirname(__DIR__) . '/api/storage.php';
    $contentStorage = birkly_ai_content_storage();
    $collectionDir = birkly_content_resolve_collection_dir_name($contentStorage, $collection);
    if ($collectionDir === null) {
        return cms_mcp_error('Collection not found');
    }

    $entryDir = cms_mcp_content_collections_root() . '/' . $collectionDir . '/entries/' . $entryId;
    if (!cms_mcp_path_within_collections($entryDir)) {
        return cms_mcp_error('Invalid entry path');
    }
    if (!is_dir($entryDir)) {
        return cms_mcp_error('Entry not found');
    }

    $subDir = $entryDir . '/sub_collections/' . $subCollection;
    if (!cms_mcp_path_within_collections($subDir)) {
        return cms_mcp_error('Invalid sub-entry path');
    }
    if ($requireSubCollectionDir && !is_dir($subDir)) {
        return cms_mcp_error('Sub-collection not found');
    }

    return null;
}

function cms_mcp_with_json_input(array $input, callable $fn)
{
    $previous = $GLOBALS['__CMS_MCP_JSON_INPUT'] ?? null;
    $previousDispatch = $GLOBALS['__CMS_MCP_DISPATCH'] ?? null;
    $GLOBALS['__CMS_MCP_JSON_INPUT'] = $input;
    $GLOBALS['__CMS_MCP_DISPATCH'] = true;
    try {
        return $fn();
    } finally {
        if ($previous === null) {
            unset($GLOBALS['__CMS_MCP_JSON_INPUT']);
        } else {
            $GLOBALS['__CMS_MCP_JSON_INPUT'] = $previous;
        }
        if ($previousDispatch === null) {
            unset($GLOBALS['__CMS_MCP_DISPATCH']);
        } else {
            $GLOBALS['__CMS_MCP_DISPATCH'] = $previousDispatch;
        }
    }
}
