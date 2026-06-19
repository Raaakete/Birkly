<?php
/**
 * Normalize create_collection schema payloads (P2-R12).
 *
 * Accepts schema.fields, top-level fields, or a bare field list under schema.
 */

function birkly_normalize_collection_schema(array $input): array
{
    if (isset($input['schema']) && is_array($input['schema'])) {
        $schema = $input['schema'];
        if (isset($schema['fields']) && is_array($schema['fields'])) {
            return ['fields' => $schema['fields']];
        }
        if ($schema !== [] && array_is_list($schema)) {
            return ['fields' => $schema];
        }
        if ($schema !== []) {
            return $schema;
        }
    }

    if (isset($input['fields']) && is_array($input['fields'])) {
        return ['fields' => $input['fields']];
    }

    return ['fields' => []];
}
