<?php
/**
 * Execute page_bundle read requests (M4).
 */

require_once __DIR__ . '/public_read.php';

function birkly_page_bundle_collection_allowed(string $collectionSlug): bool {
    if ($collectionSlug === '' || $collectionSlug === 'site_elements') {
        return true;
    }
    if (function_exists('birkly_request_is_local_dev') && birkly_request_is_local_dev()) {
        return true;
    }
    $domain = $GLOBALS['current_domain'] ?? '';
    if (!$domain) {
        return false;
    }
    $domainAuth = new DomainAuth();
    $check = $domainAuth->hasPermission($domain, 'read', $collectionSlug);
    return !empty($check['allowed']);
}

function birkly_run_page_bundle_requests($storage, array $requests, string $language = 'en'): array {
    $out = [];
    $errors = [];

    foreach ($requests as $index => $req) {
        if (!is_array($req)) {
            continue;
        }
        $type = $req['type'] ?? '';
        $into = $req['into'] ?? '';
        if (!$type || !$into) {
            $errors[] = "Request $index: missing type or into";
            continue;
        }

        try {
            if ($type === 'list_entries') {
                $collection = $req['collection'] ?? $into;
                if (!birkly_page_bundle_collection_allowed($collection)) {
                    $errors[] = "permission denied: $collection";
                    $out[$into] = [];
                    continue;
                }
                $result = birkly_public_list_entries($storage, $collection, $language);
                if (($result['status'] ?? '') !== 'ok') {
                    $errors[] = "list_entries:$collection failed";
                    $out[$into] = [];
                    continue;
                }
                $entries = $result['data']['entries'] ?? [];
                $filterField = $req['filter_field'] ?? '';
                $filterValue = $req['filter_value'] ?? '';
                if ($filterField !== '' && $filterValue !== '') {
                    $entries = array_values(array_filter($entries, function ($e) use ($filterField, $filterValue) {
                        return (string)($e[$filterField] ?? '') === (string)$filterValue;
                    }));
                }
                $out[$into] = $entries;
            } elseif ($type === 'get_entry') {
                $collection = $req['collection'] ?? '';
                if (!birkly_page_bundle_collection_allowed($collection)) {
                    $errors[] = "permission denied: $collection";
                    $out[$into] = null;
                    continue;
                }
                $slug = $req['slug'] ?? '';
                $entryId = $req['entry_id'] ?? '';
                $result = birkly_public_get_entry($storage, $collection, $slug, $entryId, $language);
                if (($result['status'] ?? '') === 'ok') {
                    $out[$into] = $result['data']['entry'] ?? null;
                } else {
                    $out[$into] = null;
                    $errors[] = "get_entry:$collection:$slug failed";
                }
            } elseif ($type === 'sub_entries') {
                $parentCollection = $req['parent_collection'] ?? '';
                $parentEntryId = $req['parent_entry_id'] ?? '';
                $subCollection = $req['sub_collection'] ?? '';
                if (!$parentCollection || !$parentEntryId || !$subCollection) {
                    $errors[] = "sub_entries:$into missing params";
                    $out[$into] = [];
                    continue;
                }
                if (!birkly_page_bundle_collection_allowed($parentCollection)) {
                    $errors[] = "permission denied: $parentCollection";
                    $out[$into] = [];
                    continue;
                }
                $result = birkly_public_list_sub_entries($parentCollection, $parentEntryId, $subCollection);
                $out[$into] = $result['data']['entries'] ?? [];
            } else {
                $errors[] = "Unknown type: $type";
            }
        } catch (Throwable $e) {
            error_log('page_bundle error: ' . $e->getMessage());
            $errors[] = "Request $index failed";
            if (!isset($out[$into])) {
                $out[$into] = [];
            }
        }
    }

    return [
        'status' => 'ok',
        'data' => $out,
        'errors' => $errors,
    ];
}
