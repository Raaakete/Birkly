<?php
/**
 * P18-M6 — Spaces v1 config stub and denylist integration.
 */

declare(strict_types=1);

require_once __DIR__ . '/storage/StorageFactory.php';

/**
 * @return array{default_space_id:string,spaces:array<int,array{id:string,label:string,default_collections:array}>}
 */
function birkly_spaces_config(): array
{
    if (array_key_exists('__birkly_spaces_config', $GLOBALS)) {
        return $GLOBALS['__birkly_spaces_config'];
    }

    $settingsDir = isset($GLOBALS['__birkly_spaces_settings_dir'])
        ? (string) $GLOBALS['__birkly_spaces_settings_dir']
        : __DIR__ . '/../settings';

    $storage = StorageFactory::createStorage($settingsDir);
    $stored = $storage->read('spaces.json');
    if (!is_array($stored)) {
        $stored = [
            'default_space_id' => 'default',
            'spaces' => [
                [
                    'id' => 'default',
                    'label' => 'Default',
                    'default_collections' => [],
                ],
            ],
        ];
    }

    $GLOBALS['__birkly_spaces_config'] = $stored;
    return $stored;
}

function birkly_spaces_reset_cache(): void
{
    unset($GLOBALS['__birkly_spaces_config']);
}

/**
 * Resolve space_id for a collection slug from spaces.json stub.
 */
function birkly_space_id_for_collection(string $collectionSlug): ?string
{
    $config = birkly_spaces_config();
    foreach ($config['spaces'] ?? [] as $space) {
        if (!is_array($space)) {
            continue;
        }
        $collections = $space['default_collections'] ?? [];
        if (is_array($collections) && in_array($collectionSlug, $collections, true)) {
            return (string) ($space['id'] ?? '');
        }
    }

    return (string) ($config['default_space_id'] ?? 'default');
}

function birkly_space_is_denylisted(string $spaceId): bool
{
    if ($spaceId === '') {
        return false;
    }

    require_once __DIR__ . '/plugins/capabilities.php';
    return is_resource_protected($spaceId) || is_resource_protected('space:' . $spaceId);
}

function birkly_collection_in_denylisted_space(string $collectionSlug): bool
{
    $spaceId = birkly_space_id_for_collection($collectionSlug);
    return $spaceId !== '' && birkly_space_is_denylisted($spaceId);
}
