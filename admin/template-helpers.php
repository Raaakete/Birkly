<?php
/**
 * Template Helper Functions
 * These functions are called from templates to perform complex operations
 */

require_once __DIR__ . '/../core/birkly_paths.php';

/**
 * Resolve a sub-collection display label from collection config.
 */
function birkly_sub_collection_label(string $collectionSlug, ?string $subCollectionSlug, ?string $parentSubCollectionSlug = null): string
{
    if ($subCollectionSlug === null || $subCollectionSlug === '') {
        return 'Sub-Collection';
    }

    require_once __DIR__ . '/../core/sub_entries.php';
    $config = getSubCollectionConfig($collectionSlug, $subCollectionSlug, $parentSubCollectionSlug);

    return (string) ($config['name'] ?? $config['title'] ?? $subCollectionSlug);
}

/**
 * Human-readable entry title for breadcrumbs.
 */
function birkly_entry_breadcrumb_title(array $entry, bool $isNew = false): string
{
    if ($isNew) {
        return __('admin.new_entry');
    }

    require_once __DIR__ . '/../core/entry_multilang.php';
    $title = birkly_entry_display_title($entry);

    return $title !== '' ? $title : __('admin.untitled');
}

/**
 * Build breadcrumbs for entry editor/detail pages.
 *
 * @return array<int, array{label: string, url: string, is_current: bool}>
 */
function build_entry_breadcrumbs(array $data): array
{
    $breadcrumbs = [];
    $collectionSlug = (string) ($data['collection']['slug'] ?? '');
    $collectionName = (string) ($data['collection']['name'] ?? $data['collection']['title'] ?? $collectionSlug ?: 'Collection');
    $isSubEntry = !empty($data['is_sub_entry']);
    $isNested = !empty($data['is_nested_sub_entry'])
        || ($isSubEntry && !empty($data['parent_sub_collection']) && !empty($data['parent_sub_entry_id']));
    $parentEntryId = (string) ($data['parent_entry_id'] ?? $data['entry']['_parent_entry_id'] ?? '');
    $subCollectionSlug = (string) ($data['sub_collection'] ?? $data['entry']['_sub_collection'] ?? '');
    $subEntryId = (string) ($data['sub_entry_id'] ?? $data['entry']['id'] ?? '');
    $entry = is_array($data['entry'] ?? null) ? $data['entry'] : [];

    $breadcrumbs[] = [
        'label' => __('admin.content'),
        'url' => birkly_admin_url('content'),
        'is_current' => false,
    ];

    $breadcrumbs[] = [
        'label' => $collectionName,
        'url' => birkly_admin_url('entries', ['collection' => $collectionSlug]),
        'is_current' => false,
    ];

    if ($isSubEntry) {
        $parentEntryTitle = (string) ($entry['_parent_entry_title'] ?? __('admin.untitled'));
        if ($parentEntryId !== '') {
            $breadcrumbs[] = [
                'label' => $parentEntryTitle,
                'url' => birkly_admin_url('entry-detail', [
                    'collection' => $collectionSlug,
                    'entry' => $parentEntryId,
                ]),
                'is_current' => false,
            ];
        }

        if ($isNested) {
            $parentSubCollectionSlug = (string) ($data['parent_sub_collection'] ?? $entry['_parent_sub_collection'] ?? '');
            $parentSubEntryId = (string) ($data['parent_sub_entry_id'] ?? $entry['_parent_sub_entry_id'] ?? '');
            $parentSubEntryTitle = (string) ($entry['_parent_sub_entry_title'] ?? __('admin.untitled'));
            $parentSubLabel = birkly_sub_collection_label($collectionSlug, $parentSubCollectionSlug);

            if ($parentSubCollectionSlug !== '' && $parentSubEntryId !== '') {
                $breadcrumbs[] = [
                    'label' => $parentSubLabel . ': ' . $parentSubEntryTitle,
                    'url' => birkly_admin_url('entry-detail', [
                        'collection' => $collectionSlug,
                        'entry' => $parentEntryId,
                        'sub_collection' => $parentSubCollectionSlug,
                        'sub_entry' => $parentSubEntryId,
                    ]),
                    'is_current' => false,
                ];
            }
        }

        $subLabel = birkly_sub_collection_label(
            $collectionSlug,
            $subCollectionSlug,
            $isNested ? (string) ($data['parent_sub_collection'] ?? '') : null
        );
        $isNewSubEntry = $subEntryId === '';
        $currentTitle = birkly_entry_breadcrumb_title($entry, $isNewSubEntry);

        $breadcrumbs[] = [
            'label' => $subLabel . ': ' . $currentTitle,
            'url' => '',
            'is_current' => true,
        ];

        return $breadcrumbs;
    }

    $isNewEntry = empty($entry['id']);
    $breadcrumbs[] = [
        'label' => birkly_entry_breadcrumb_title($entry, $isNewEntry),
        'url' => '',
        'is_current' => true,
    ];

    return $breadcrumbs;
}

/**
 * Build cancel URL for entry editor.
 */
function build_cancel_url(array $data): string
{
    $collectionSlug = $data['collection']['slug'] ?? '';

    if (!empty($_GET['return_to'])) {
        return urldecode((string) $_GET['return_to']);
    }

    if (!empty($data['is_sub_entry'])) {
        $mainEntryId = $data['parent_entry_id'] ?? $data['entry']['_parent_entry_id'] ?? '';

        if (!empty($_GET['cancel_to_sub_collection']) && !empty($_GET['cancel_to_sub_entry'])) {
            return birkly_admin_url('entry-detail', [
                'collection' => $collectionSlug,
                'entry' => $mainEntryId,
                'sub_collection' => $_GET['cancel_to_sub_collection'],
                'sub_entry' => $_GET['cancel_to_sub_entry'],
            ]);
        }

        if (!empty($data['sub_entry_id']) && !empty($data['collection']['sub_collections'])) {
            $currentSubCollectionSlug = $data['sub_collection'] ?? '';
            $hasNestedSubCollections = false;

            foreach ($data['collection']['sub_collections'] as $subCol) {
                if (($subCol['slug'] ?? '') === $currentSubCollectionSlug && !empty($subCol['sub_collections'])) {
                    $hasNestedSubCollections = true;
                    break;
                }
            }

            if ($hasNestedSubCollections) {
                $params = [
                    'collection' => $collectionSlug,
                    'entry' => $mainEntryId,
                    'sub_collection' => $currentSubCollectionSlug,
                    'sub_entry' => $data['sub_entry_id'],
                ];
                if (!empty($data['parent_sub_collection']) && !empty($data['parent_sub_entry_id'])) {
                    $params['parent_sub_collection'] = $data['parent_sub_collection'];
                    $params['parent_sub_entry'] = $data['parent_sub_entry_id'];
                }
                return birkly_admin_url('entry-detail', $params);
            }
        }

        if (!empty($data['parent_sub_collection']) && !empty($data['parent_sub_entry_id'])) {
            return birkly_admin_url('entry-detail', [
                'collection' => $collectionSlug,
                'entry' => $mainEntryId,
                'sub_collection' => $data['parent_sub_collection'],
                'sub_entry' => $data['parent_sub_entry_id'],
            ]);
        }

        if (!empty($mainEntryId)) {
            return birkly_admin_url('entry-detail', ['collection' => $collectionSlug, 'entry' => $mainEntryId]);
        }

        return birkly_admin_url('entries', ['collection' => $collectionSlug]);
    }

    $entryId = $data['entry']['id'] ?? $_GET['entry'] ?? null;
    $referer = $_SERVER['HTTP_REFERER'] ?? '';
    if (!empty($referer)) {
        $refererParts = parse_url($referer);
        if (!empty($refererParts['query'])) {
            parse_str($refererParts['query'], $refererParams);

            if (!empty($refererParams['page']) && $refererParams['page'] === 'entry-detail'
                && !empty($refererParams['entry']) && !empty($refererParams['collection'])) {
                return birkly_admin_url('entry-detail', [
                    'collection' => $refererParams['collection'],
                    'entry' => $refererParams['entry'],
                ]);
            }
        }
    }

    return birkly_admin_url('entries', ['collection' => $collectionSlug]);
}

/**
 * Build save button text based on context.
 */
function get_save_button_text(array $data): string
{
    if (!empty($data['entry']['id'])) {
        return __('admin.update_entry');
    }

    return __('admin.create_entry');
}

/**
 * Check if cancel button should be shown.
 */
function should_show_cancel_button(array $data): bool
{
    return true;
}
