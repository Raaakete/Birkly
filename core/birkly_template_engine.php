<?php
/**
 * Birkly Template Engine — PHP port (P52-M1).
 * -------------------------------------------
 * Pure-PHP implementation of the string `render()` pipeline from
 * `birkly-template-engine.js` (the canonical shared engine, see
 * docs/TEMPLATE_LANGUAGE_SPEC.md). No Node, no V8Js, no FFI.
 *
 * Parity contract (G52-01/G52-02/G52-03):
 *   - Output must be byte-identical to the JS engine for the shared fixture
 *     suite (tests/template-engine/fixtures/*.json, run by
 *     tests/template_engine_parity_test.php).
 *   - The pipeline order mirrors JS `render()` exactly:
 *       extractProtectedBlocks -> processEntryForBlocks ->
 *       processPublicFormBlocks -> processFromGetBlocks ->
 *       processInlineFromGet -> processForLoops -> processIfBlocks ->
 *       processCountVariables -> processVariables -> restoreProtectedBlocks
 *   - Nested renders (loops / from-get / if) pass `_skipProtect` so protected
 *     <style>/<script>/<noscript> tokens are not re-extracted.
 *
 * JS semantics that are intentionally reproduced (do not "fix" here without
 * changing the JS engine first and updating the fixtures):
 *   - JS truthiness ('' / 0 / null / false are falsy; arrays and objects are
 *     always truthy — even empty).
 *   - String() coercion: null -> 'null', true -> 'true', missing property ->
 *     'undefined' where the JS code calls String() on an absent field.
 *   - parseFloat() prefix parsing ('12px' -> 12, '' -> NaN).
 *   - Date parsing: date-only ISO strings ('2026-01-01') are UTC; date-time
 *     strings without timezone ('2025-01-05T16:45:00') are local time —
 *     matching `new Date(value)` in V8.
 *
 * Data is expected as a JSON-shaped associative array (the result of
 * json_decode($json, true)); PHP lists stand in for JS arrays and PHP
 * associative arrays for JS objects.
 *
 * Public API:
 *   birkly_template_engine_render(string $html, $data, array $options = []): string
 *   birkly_template_engine_escape_html(string $str): string
 *   birkly_template_engine_has_syntax(string $html): bool
 */

declare(strict_types=1);

/* -------------------------------------------------------------------------
 * JS-semantics helpers
 * ---------------------------------------------------------------------- */

/** Is this PHP array a JS array (sequential list) as opposed to a JS object? */
function birkly_template_engine_is_list(array $arr): bool
{
    $expected = 0;
    foreach ($arr as $key => $_) {
        if ($key !== $expected) {
            return false;
        }
        $expected++;
    }
    return true;
}

/** JS truthiness for JSON-shaped values (arrays/objects are always truthy). */
function birkly_template_engine_truthy($value): bool
{
    if ($value === null || $value === false || $value === '') {
        return false;
    }
    if (is_int($value)) {
        return $value !== 0;
    }
    if (is_float($value)) {
        return $value != 0.0 && !is_nan($value);
    }
    return true;
}

/** Mirror of JS String(value) for JSON-shaped values. */
function birkly_template_engine_js_string($value): string
{
    if ($value === null) {
        return 'null';
    }
    if (is_bool($value)) {
        return $value ? 'true' : 'false';
    }
    if (is_string($value)) {
        return $value;
    }
    if (is_int($value)) {
        return (string) $value;
    }
    if (is_float($value)) {
        if (is_nan($value)) {
            return 'NaN';
        }
        if (is_infinite($value)) {
            return $value > 0 ? 'Infinity' : '-Infinity';
        }
        if (floor($value) === $value && abs($value) < 1e21) {
            return sprintf('%.0F', $value);
        }
        return (string) $value;
    }
    if (is_array($value)) {
        if (birkly_template_engine_is_list($value)) {
            // JS Array.prototype.join: null/undefined elements become ''.
            return implode(',', array_map(
                static fn($item) => $item === null ? '' : birkly_template_engine_js_string($item),
                $value
            ));
        }
        return '[object Object]';
    }
    return (string) $value;
}

/** Mirror of JS parseFloat(): returns NAN when JS would return NaN. */
function birkly_template_engine_parse_float($value): float
{
    if (is_int($value) || is_float($value)) {
        return (float) $value;
    }
    if (!is_string($value)) {
        // JS coerces to string first; bool/null/objects never parse to a number.
        $value = birkly_template_engine_js_string($value);
    }
    $str = ltrim($value);
    if (preg_match('/^[+-]?(?:Infinity|\d+\.?\d*(?:[eE][+-]?\d+)?|\.\d+(?:[eE][+-]?\d+)?)/', $str, $m)) {
        if (stripos($m[0], 'Infinity') !== false) {
            return strncmp($m[0], '-', 1) === 0 ? -INF : INF;
        }
        return (float) $m[0];
    }
    return NAN;
}

function birkly_template_engine_normalize_operator(string $op): string
{
    $op = trim($op);
    if ($op === '=<') {
        return '<=';
    }
    if ($op === '=>') {
        return '>=';
    }
    if ($op === '==') {
        return '=';
    }
    return $op;
}

function birkly_template_engine_compare_numeric(float $left, string $op, float $right): bool
{
    switch ($op) {
        case '<':
            return $left < $right;
        case '>':
            return $left > $right;
        case '<=':
            return $left <= $right;
        case '>=':
            return $left >= $right;
        case '=':
        case '==':
            return abs($left - $right) < 0.00001;
        case '!=':
            return abs($left - $right) >= 0.00001;
        default:
            return false;
    }
}

function birkly_template_engine_entry_has_field($entry, string $field): bool
{
    if (!is_array($entry) || !array_key_exists($field, $entry)) {
        return false;
    }
    $val = $entry[$field];
    if ($val === null || $val === '') {
        return false;
    }
    if (is_array($val) && birkly_template_engine_is_list($val) && count($val) === 0) {
        return false;
    }
    return true;
}

/* -------------------------------------------------------------------------
 * Dates (mirror of JS `new Date(value)` / `Date.parse(value)`)
 * ---------------------------------------------------------------------- */

/**
 * Parse a value like JS `new Date(value)` and return a DateTime converted to
 * the local timezone (so format codes behave like JS local getters), or null
 * when JS would produce an Invalid Date.
 */
function birkly_template_engine_parse_date($value): ?DateTime
{
    if (is_int($value) || is_float($value)) {
        // Numeric input to Date() is a ms epoch; the engine only ever passes
        // strings, but mirror it for safety.
        $seconds = ((float) $value) / 1000.0;
        $dt = DateTime::createFromFormat('U.u', sprintf('%.6F', $seconds));
        if ($dt === false) {
            return null;
        }
        $dt->setTimezone(new DateTimeZone(date_default_timezone_get()));
        return $dt;
    }
    if (!is_string($value)) {
        return null;
    }
    $str = trim($value);
    if ($str === '') {
        return null;
    }
    $local = new DateTimeZone(date_default_timezone_get());
    try {
        // Date-only ISO forms (YYYY, YYYY-MM, YYYY-MM-DD) parse as UTC in JS.
        // Normalize to a full date so PHP does not misread "2026" as a time.
        if (preg_match('/^(\d{4})(?:-(\d{2}))?(?:-(\d{2}))?$/D', $str, $dm)) {
            $month = (isset($dm[2]) && $dm[2] !== '') ? $dm[2] : '01';
            $day = (isset($dm[3]) && $dm[3] !== '') ? $dm[3] : '01';
            $dt = new DateTime($dm[1] . '-' . $month . '-' . $day . ' 00:00:00', new DateTimeZone('UTC'));
            $dt->setTimezone($local);
            return $dt;
        }
        // "0" is what `Date.parse(0)` sees after string coercion; V8's legacy
        // parser resolves it to Jan 1 2000 (used as the missing-date fallback
        // in ordering comparators).
        if ($str === '0') {
            return new DateTime('2000-01-01 00:00:00', $local);
        }
        // Everything else (date-time without timezone, or with explicit
        // offset/Z) matches PHP's DateTime behavior: no-timezone strings are
        // local, embedded offsets win over the constructor timezone.
        return new DateTime($str, $local);
    } catch (Exception $e) {
        return null;
    }
}

/** Mirror of JS Date.parse(value): milliseconds since epoch, or NAN. */
function birkly_template_engine_date_ms($value): float
{
    $dt = birkly_template_engine_parse_date($value);
    if ($dt === null) {
        return NAN;
    }
    return ((float) $dt->format('U')) * 1000.0 + (float) $dt->format('v');
}

/**
 * Mirror of the JS ordering idiom
 * `Date.parse(a && a[field] ? a[field] : 0) || 0`.
 */
function birkly_template_engine_entry_date_ms($entry, string $field): float
{
    $raw = '0';
    if (is_array($entry) && array_key_exists($field, $entry) && birkly_template_engine_truthy($entry[$field])) {
        $raw = $entry[$field];
    }
    $ms = birkly_template_engine_date_ms($raw);
    return is_nan($ms) ? 0.0 : $ms;
}

/** Mirror of JS formatDate(value, format) — PHP-style codes, char-by-char. */
function birkly_template_engine_format_date($value, ?string $format): string
{
    if (!birkly_template_engine_truthy($value)) {
        return '';
    }
    $dt = birkly_template_engine_parse_date($value);
    if ($dt === null) {
        return birkly_template_engine_js_string($value);
    }
    $months = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
    $monthsFull = ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'];
    $monthIndex = ((int) $dt->format('n')) - 1;
    $codes = [
        'Y' => $dt->format('Y'),
        'y' => substr($dt->format('Y'), -2),
        'm' => $dt->format('m'),
        'n' => $dt->format('n'),
        'F' => $monthsFull[$monthIndex],
        'M' => $months[$monthIndex],
        'd' => $dt->format('d'),
        'j' => $dt->format('j'),
        'H' => $dt->format('H'),
        'G' => $dt->format('G'),
        'i' => $dt->format('i'),
        's' => $dt->format('s'),
    ];
    $fmt = ($format === null || $format === '') ? 'Y-m-d' : $format;
    $out = '';
    $len = strlen($fmt);
    for ($i = 0; $i < $len; $i++) {
        $ch = $fmt[$i];
        $out .= array_key_exists($ch, $codes) ? $codes[$ch] : $ch;
    }
    return $out;
}

/* -------------------------------------------------------------------------
 * Entry / collection resolution
 * ---------------------------------------------------------------------- */

/** @return mixed  entry array (or, for site_elements, any truthy value), or null */
function birkly_template_engine_resolve_entry(string $collection, string $slug, array $data)
{
    $key = '__entry__:' . $collection . ':' . $slug;
    if (isset($data[$key]) && is_array($data[$key])) {
        return $data[$key];
    }
    if ($collection === 'site_elements') {
        $se = (isset($data['site_elements']) && is_array($data['site_elements'])) ? $data['site_elements'] : [];
        $found = $se[$slug] ?? null;
        return birkly_template_engine_truthy($found) ? $found : null;
    }
    $entries = $data[$collection] ?? null;
    if (!is_array($entries) || !birkly_template_engine_is_list($entries)) {
        return null;
    }
    foreach ($entries as $e) {
        if (!birkly_template_engine_truthy($e) || !is_array($e)) {
            continue;
        }
        $eSlug = birkly_template_engine_truthy($e['slug'] ?? null) ? birkly_template_engine_js_string($e['slug']) : '';
        $eId = birkly_template_engine_truthy($e['id'] ?? null) ? birkly_template_engine_js_string($e['id']) : '';
        if ($eSlug === $slug || $eId === $slug) {
            return $e;
        }
    }
    return null;
}

/** @return mixed  entry array, or null */
function birkly_template_engine_resolve_parent_collection_entry(string $collection, string $identifier, array $data)
{
    if ($collection === 'site_elements') {
        $se = (isset($data['site_elements']) && is_array($data['site_elements'])) ? $data['site_elements'] : [];
        $found = $se[$identifier] ?? null;
        return birkly_template_engine_truthy($found) ? $found : null;
    }
    if ($identifier === 'current') {
        if (array_key_exists('entry', $data) && birkly_template_engine_truthy($data['entry'])) {
            return $data['entry'];
        }
        $prefix = '__entry__:' . $collection . ':';
        foreach ($data as $k => $v) {
            if (strncmp((string) $k, $prefix, strlen($prefix)) === 0 && birkly_template_engine_truthy($v)) {
                return $v;
            }
        }
        return null;
    }
    if ($identifier === 'newest' || $identifier === 'last-updated') {
        $entries = $data[$collection] ?? null;
        if (!is_array($entries) || !birkly_template_engine_is_list($entries) || count($entries) === 0) {
            return null;
        }
        $field = $identifier === 'last-updated' ? 'updated_at' : 'created_at';
        $sorted = $entries;
        usort($sorted, static function ($a, $b) use ($field): int {
            $da = birkly_template_engine_entry_date_ms($a, $field);
            $db = birkly_template_engine_entry_date_ms($b, $field);
            return $db <=> $da;
        });
        return $sorted[0];
    }
    return birkly_template_engine_resolve_entry($collection, $identifier, $data);
}

/**
 * Resolve a sub-collection loop parent identifier (slug, current, or {var.field}).
 */
function birkly_template_engine_resolve_loop_parent_identifier(string $raw, array $data): string
{
    $trimmed = trim($raw);
    if ($trimmed === '') {
        return '';
    }
    if (preg_match('/^\{([^}]+)\}$/D', $trimmed, $m)) {
        $resolved = birkly_template_engine_resolve_context_path(trim($m[1]), $data);
        if ($resolved !== null && $resolved !== '') {
            return birkly_template_engine_js_string($resolved);
        }
        return '';
    }
    if (preg_match("/^'([^']+)'$/D", $trimmed, $m)) {
        return $m[1];
    }
    return $trimmed;
}

/**
 * Infer top-level collection + entry id from render data (detail page context).
 *
 * @return array{0:string,1:string}
 */
function birkly_template_engine_top_entry_context(array $data): array
{
    $entry = $data['entry'] ?? null;
    if (!birkly_template_engine_truthy($entry) || !is_array($entry)) {
        return ['', ''];
    }
    $collection = trim((string) ($data['query']['collection'] ?? ''));
    if ($collection === '') {
        $collection = trim((string) ($entry['parent_collection'] ?? ''));
    }
    $entryId = trim((string) ($entry['id'] ?? $entry['slug'] ?? ''));
    return [$collection, $entryId];
}

/** @return mixed  entry array, or null */
function birkly_template_engine_resolve_collection_entry_ref(string $ref, array $data)
{
    $trimmed = trim($ref);
    if (preg_match("/^'([^']+)'\\.'([^']+)'$/D", $trimmed, $m)) {
        return birkly_template_engine_resolve_parent_collection_entry($m[1], $m[2], $data);
    }
    if (preg_match("/^'([^']+)'\\.'(current|newest|last-updated)'$/D", $trimmed, $m)) {
        return birkly_template_engine_resolve_parent_collection_entry($m[1], $m[2], $data);
    }
    return null;
}

/** @return list<mixed> */
function birkly_template_engine_resolve_sub_collection_items(string $subCollection, string $parentCollection, string $parentIdentifier, array $data): array
{
    $parentIdentifier = birkly_template_engine_resolve_loop_parent_identifier($parentIdentifier, $data);

    $topLevelList = $data[$parentCollection] ?? null;
    $isTopLevelCollection = is_array($topLevelList) && birkly_template_engine_is_list($topLevelList) && count($topLevelList) > 0;

    if (!$isTopLevelCollection && $parentIdentifier !== '') {
        [$topCollection, $topEntryId] = birkly_template_engine_top_entry_context($data);
        if ($topCollection !== '' && $topEntryId !== '') {
            $nestedKey = '__sub__:' . $topCollection . ':' . $topEntryId . ':'
                . $parentCollection . ':' . $parentIdentifier . ':' . $subCollection;
            if (isset($data[$nestedKey]) && is_array($data[$nestedKey]) && birkly_template_engine_is_list($data[$nestedKey])) {
                return $data[$nestedKey];
            }
        }
        foreach ($data as $varValue) {
            if (!is_array($varValue)) {
                continue;
            }
            $varId = birkly_template_engine_js_string($varValue['id'] ?? null);
            $varSlug = birkly_template_engine_js_string($varValue['slug'] ?? null);
            $needle = birkly_template_engine_js_string($parentIdentifier);
            if ($needle !== '' && ($varId === $needle || $varSlug === $needle)
                && isset($varValue[$subCollection]) && is_array($varValue[$subCollection])
                && birkly_template_engine_is_list($varValue[$subCollection])) {
                return $varValue[$subCollection];
            }
        }
        return [];
    }

    $parent = birkly_template_engine_resolve_parent_collection_entry($parentCollection, $parentIdentifier, $data);
    if (!birkly_template_engine_truthy($parent)) {
        return [];
    }
    $ids = [];
    foreach (['id', 'slug'] as $field) {
        if (is_array($parent) && array_key_exists($field, $parent)) {
            $v = $parent[$field];
            if ($v !== null && $v !== '') {
                $ids[] = $v;
            }
        }
    }
    if ($parentIdentifier !== '') {
        $ids[] = $parentIdentifier;
    }
    foreach ($ids as $id) {
        $key = '__sub__:' . $parentCollection . ':' . birkly_template_engine_js_string($id) . ':' . $subCollection;
        if (isset($data[$key]) && is_array($data[$key]) && birkly_template_engine_is_list($data[$key])) {
            return $data[$key];
        }
    }
    if (isset($data[$subCollection]) && is_array($data[$subCollection]) && birkly_template_engine_is_list($data[$subCollection])
        && array_key_exists('entry', $data) && birkly_template_engine_truthy($data['entry'])) {
        $entry = $data['entry'];
        $entryId = (is_array($entry) && array_key_exists('id', $entry))
            ? birkly_template_engine_js_string($entry['id'] ?? null)
            : 'undefined';
        $entrySlug = (is_array($entry) && array_key_exists('slug', $entry))
            ? birkly_template_engine_js_string($entry['slug'] ?? null)
            : 'undefined';
        foreach ($ids as $id) {
            $idStr = birkly_template_engine_js_string($id);
            if ($entryId === $idStr || $entrySlug === $idStr) {
                return $data[$subCollection];
            }
        }
    }
    if (is_array($parent) && isset($parent[$subCollection]) && is_array($parent[$subCollection]) && birkly_template_engine_is_list($parent[$subCollection])) {
        return $parent[$subCollection];
    }
    return [];
}

/** @return list<mixed> */
function birkly_template_engine_resolve_loop_items(string $inClause, array $data): array
{
    $clause = trim($inClause);
    if (preg_match("/^'([^']+)'\\s+of\\s+'([^']+)'\\.(\\{[^}]+\\}|[^\\s{}]+)\\s*$/iD", $clause, $m)) {
        return birkly_template_engine_resolve_sub_collection_items($m[1], $m[2], $m[3], $data);
    }
    if (preg_match("/^'([^']+)'\\s+of\\s+'([^']+)'\\.'([^']+)'\\s*$/iD", $clause, $m)) {
        return birkly_template_engine_resolve_sub_collection_items($m[1], $m[2], $m[3], $data);
    }
    if (preg_match("/^'([^']+)'\\s+of\\s+'([^']+)'\\.\\s*(current|newest|last-updated)\\s*$/iD", $clause, $m)) {
        return birkly_template_engine_resolve_sub_collection_items($m[1], $m[2], $m[3], $data);
    }

    if (preg_match("/^'([^']+)'/", $clause, $m)) {
        $collection = $m[1];
        if (isset($data[$collection]) && is_array($data[$collection]) && birkly_template_engine_is_list($data[$collection])) {
            return $data[$collection];
        }
        $entry = $data['entry'] ?? null;
        if (birkly_template_engine_truthy($entry) && is_array($entry)
            && isset($entry[$collection]) && is_array($entry[$collection]) && birkly_template_engine_is_list($entry[$collection])) {
            return $entry[$collection];
        }
        return [];
    }

    $firstWord = preg_split('/\s+/', $clause)[0] ?? '';
    $field = preg_replace('/[^a-zA-Z0-9_]/', '', $firstWord);
    if ($field === '' || $field === null) {
        return [];
    }
    if (isset($data[$field]) && is_array($data[$field]) && birkly_template_engine_is_list($data[$field])) {
        return $data[$field];
    }
    $entry = $data['entry'] ?? null;
    if (!birkly_template_engine_truthy($entry) || !is_array($entry)) {
        return [];
    }
    $value = $entry[$field] ?? ($entry[$field . 's'] ?? null);
    return (is_array($value) && birkly_template_engine_is_list($value)) ? $value : [];
}

/** @return array{inClause:string,filter:?string,order:?string,limit:?int} */
function birkly_template_engine_parse_loop_modifiers(string $inClause): array
{
    $result = ['inClause' => $inClause, 'filter' => null, 'order' => null, 'limit' => null];
    $rest = trim($inClause);

    if (preg_match('/\blimit\s+(\d+)\b/i', $rest, $m)) {
        $result['limit'] = (int) $m[1];
        $rest = trim((string) preg_replace('/\blimit\s+\d+\b/i', '', $rest, 1));
    }
    if (preg_match('/\bordered\s+by\s+(\S+)/i', $rest, $m)) {
        $result['order'] = strtolower($m[1]);
        $rest = trim((string) preg_replace('/\bordered\s+by\s+\S+/i', '', $rest, 1));
    }
    if (preg_match('/\bwith\s+(.+)$/isD', $rest, $m)) {
        $result['filter'] = trim($m[1]);
        $rest = trim((string) preg_replace('/\bwith\s+.+$/isD', '', $rest, 1));
    }
    $result['inClause'] = trim($rest);
    return $result;
}

function birkly_template_engine_resolve_loop_filter_value(string $value, array $data): string
{
    $trimmed = trim($value);
    if ($trimmed === 'user_current') {
        $user = $data['user_current'] ?? null;
        if (is_array($user)) {
            return birkly_template_engine_js_string($user['id'] ?? null);
        }
        return '';
    }
    if (preg_match('/^user_current\\.(.+)$/D', $trimmed, $m)) {
        $user = $data['user_current'] ?? null;
        if (is_array($user)) {
            return birkly_template_engine_js_string($user[$m[1]] ?? null);
        }
        return '';
    }
    return $trimmed;
}

/**
 * @param list<mixed> $items
 * @return list<mixed>
 */
function birkly_template_engine_apply_loop_filters(array $items, ?string $filter, array $data = []): array
{
    if ($filter === null || $filter === '') {
        return $items;
    }
    $clauses = preg_split('/\s+with\s+/i', $filter);
    foreach ($clauses as $clause) {
        $clause = trim((string) $clause);
        if ($clause === '') {
            continue;
        }
        $items = birkly_template_engine_apply_single_loop_filter($items, $clause, $data);
    }
    return $items;
}

function birkly_template_engine_filter_literal_bool(string $value): ?bool
{
    $s = strtolower(trim($value));
    if (in_array($s, ['1', 'true', 'yes', 'on'], true)) {
        return true;
    }
    if (in_array($s, ['0', 'false', 'no', ''], true)) {
        return false;
    }
    return null;
}

function birkly_template_engine_field_matches_filter_value(mixed $fieldValue, string $filterValue): bool
{
    $boolFilter = birkly_template_engine_filter_literal_bool($filterValue);
    if ($boolFilter !== null) {
        if (is_bool($fieldValue)) {
            return $fieldValue === $boolFilter;
        }
        $fieldBool = birkly_template_engine_filter_literal_bool(birkly_template_engine_js_string($fieldValue));
        return ($fieldBool ?? false) === $boolFilter;
    }
    return birkly_template_engine_js_string($fieldValue) === $filterValue;
}

/**
 * @param list<mixed> $items
 * @return list<mixed>
 */
function birkly_template_engine_apply_single_loop_filter(array $items, string $filter, array $data = []): array
{
    if ($filter === '') {
        return $items;
    }
    if (preg_match('/^([a-zA-Z_][a-zA-Z0-9_]*)\s*=\s*([a-zA-Z_][a-zA-Z0-9_.]*)$/D', $filter, $m)) {
        $field = $m[1];
        $value = birkly_template_engine_resolve_loop_filter_value($m[2], $data);
        if (($m[2] === 'user_current' || str_starts_with($m[2], 'user_current.')) && $value === '') {
            return [];
        }
        $out = [];
        foreach ($items as $it) {
            if (!birkly_template_engine_truthy($it)) {
                continue;
            }
            $raw = (is_array($it) && array_key_exists($field, $it)) ? $it[$field] : null;
            if (birkly_template_engine_field_matches_filter_value($raw, $value)) {
                $out[] = $it;
            }
        }
        return $out;
    }
    if (preg_match('/^[\'"]([^\'"]+)[\'"]\s*=\s*([a-zA-Z_][a-zA-Z0-9_.]*)$/D', $filter, $m)) {
        $field = $m[1];
        $value = birkly_template_engine_resolve_loop_filter_value($m[2], $data);
        if (($m[2] === 'user_current' || str_starts_with($m[2], 'user_current.')) && $value === '') {
            return [];
        }
        $out = [];
        foreach ($items as $it) {
            if (!birkly_template_engine_truthy($it)) {
                continue;
            }
            $raw = (is_array($it) && array_key_exists($field, $it)) ? $it[$field] : null;
            if (birkly_template_engine_field_matches_filter_value($raw, $value)) {
                $out[] = $it;
            }
        }
        return $out;
    }
    if (preg_match('/^[\'"]([^\'"]+)[\'"]\s*=\s*[\'"]([^\'"]*)[\'"]$/D', $filter, $m)) {
        $field = $m[1];
        $value = $m[2];
        $out = [];
        foreach ($items as $it) {
            if (!birkly_template_engine_truthy($it)) {
                continue;
            }
            $raw = (is_array($it) && array_key_exists($field, $it)) ? $it[$field] : null;
            if (birkly_template_engine_field_matches_filter_value($raw, $value)) {
                $out[] = $it;
            }
        }
        return $out;
    }
    if (preg_match('/^[\'"]([^\'"]+)[\'"]\s*(=|<=|=<|>=|=>|<|>)\s*([0-9]+(?:\.[0-9]+)?)$/D', $filter, $m)) {
        $field = $m[1];
        $op = birkly_template_engine_normalize_operator($m[2]);
        $num = (float) $m[3];
        $out = [];
        foreach ($items as $it) {
            if (!birkly_template_engine_truthy($it)) {
                continue;
            }
            $raw = (is_array($it) && array_key_exists($field, $it)) ? $it[$field] : null;
            $v = birkly_template_engine_parse_float($raw);
            if (is_nan($v)) {
                continue;
            }
            if (birkly_template_engine_compare_numeric($v, $op, $num)) {
                $out[] = $it;
            }
        }
        return $out;
    }
    return $items;
}

/**
 * @param list<mixed> $items
 * @return list<mixed>
 */
function birkly_template_engine_apply_loop_order(array $items, ?string $order): array
{
    if ($order === null || $order === '' || count($items) < 2) {
        return $items;
    }
    $list = $items;
    if ($order === 'newest' || $order === 'oldest' || $order === 'last-updated') {
        $field = $order === 'last-updated' ? 'updated_at' : 'created_at';
        usort($list, static function ($a, $b) use ($field, $order): int {
            $da = birkly_template_engine_entry_date_ms($a, $field);
            $db = birkly_template_engine_entry_date_ms($b, $field);
            return $order === 'oldest' ? ($da <=> $db) : ($db <=> $da);
        });
        return $list;
    }
    if ($order === 'order') {
        $pick = static function ($entry): float {
            $raw = 0;
            if (is_array($entry) && array_key_exists('order', $entry) && $entry['order'] !== null) {
                $raw = $entry['order'];
            } elseif (is_array($entry) && array_key_exists('sort_order', $entry) && $entry['sort_order'] !== null) {
                $raw = $entry['sort_order'];
            }
            $v = birkly_template_engine_parse_float($raw);
            return is_nan($v) ? 0.0 : $v;
        };
        usort($list, static function ($a, $b) use ($pick): int {
            return $pick($a) <=> $pick($b);
        });
    }
    return $list;
}

/**
 * @param list<mixed> $items
 * @return list<mixed>
 */
function birkly_template_engine_apply_loop_limit(array $items, ?int $limit): array
{
    if ($limit === null || $limit < 1) {
        return $items;
    }
    return array_slice($items, 0, $limit);
}

/* -------------------------------------------------------------------------
 * Block scanning
 * ---------------------------------------------------------------------- */

/**
 * @param list<string> $tags
 * @return array{0:?int,1:int}
 */
function birkly_template_engine_find_closing_tag(string $html, int $from, array $tags): array
{
    $bestPos = null;
    $bestLen = 0;
    foreach ($tags as $tag) {
        $pos = stripos($html, $tag, $from);
        if ($pos !== false && ($bestPos === null || $pos < $bestPos)) {
            $bestPos = $pos;
            $bestLen = strlen($tag);
        }
    }
    return $bestPos === null ? [null, 0] : [$bestPos, $bestLen];
}

/** @return array{0:int,1:int}|null */
function birkly_template_engine_find_matching_loop_end(string $html, int $from): ?array
{
    $pos = $from;
    $depth = 1;
    $closeTags = ['{endfor}', '{endforeach}', '{end}'];
    $length = strlen($html);

    while ($pos < $length && $depth > 0) {
        $nextOpen = PHP_INT_MAX;
        if (preg_match('/\{for\s+(?:each\s+)?/i', $html, $m, PREG_OFFSET_CAPTURE, $pos)) {
            $nextOpen = $m[0][1];
        }

        $nextClose = PHP_INT_MAX;
        $closeLen = 0;
        foreach ($closeTags as $tag) {
            $p = stripos($html, $tag, $pos);
            if ($p !== false && $p < $nextClose) {
                $nextClose = $p;
                $closeLen = strlen($tag);
            }
        }

        if ($nextClose === PHP_INT_MAX) {
            return null;
        }
        if ($nextOpen < $nextClose) {
            $depth++;
            $pos = $nextOpen + 1;
            continue;
        }
        $depth--;
        if ($depth === 0) {
            return [$nextClose, $closeLen];
        }
        $pos = $nextClose + $closeLen;
    }
    return null;
}

/** @return array{0:?int,1:int,2:?int,3:int} */
function birkly_template_engine_find_if_block_end(string $html, int $from): array
{
    $pos = $from;
    $depth = 1;
    $elsePos = null;
    $elseLen = 0;
    $length = strlen($html);

    while ($pos < $length && $depth > 0) {
        if (!preg_match('/\{(if(?:\s+[^}]+)?|elseif\s+[^}]+|else|endif)\}/i', $html, $m, PREG_OFFSET_CAPTURE, $pos)) {
            break;
        }
        $rawTag = $m[1][0];
        $tag = strtolower(preg_replace('/\s.*/', '', $rawTag));
        $tagPos = $m[0][1];
        $tagLen = strlen($m[0][0]);

        if ($tag === 'if' || $tag === 'elseif') {
            if ($depth === 1 && $tag === 'elseif' && $elsePos === null) {
                $elsePos = $tagPos;
                $elseLen = $tagLen;
            }
            if ($tag === 'if') {
                $depth++;
            }
            $pos = $tagPos + $tagLen;
            continue;
        }
        if ($tag === 'else' && $depth === 1 && $elsePos === null) {
            $elsePos = $tagPos;
            $elseLen = $tagLen;
            $pos = $tagPos + $tagLen;
            continue;
        }
        if ($tag === 'endif') {
            $depth--;
            if ($depth === 0) {
                return [$tagPos, $tagLen, $elsePos, $elseLen];
            }
        }
        $pos = $tagPos + $tagLen;
    }
    return [null, 0, null, 0];
}

/* -------------------------------------------------------------------------
 * Value resolution
 * ---------------------------------------------------------------------- */

/**
 * Resolve a bare-headed context/system path such as `current.language`,
 * `query.'lang'`, or `entry.title`. Returns a string, or null when the
 * expression is not such a path (mirror of JS `undefined`).
 */
function birkly_template_engine_resolve_context_path(string $expr, array $data): ?string
{
    $trimmed = trim($expr);
    if (!preg_match('/^([a-zA-Z_][a-zA-Z0-9_]*)((?:\.(?:\'[^\']+\'|"[^"]+"|\d+|[a-zA-Z_][a-zA-Z0-9_]*))+)$/D', $trimmed, $m)) {
        return null;
    }
    $head = $m[1];
    if (!array_key_exists($head, $data)) {
        return null;
    }
    $cur = $data[$head];
    if (!is_array($cur)) {
        return null;
    }
    if (preg_match_all('/\.(?:\'([^\']+)\'|"([^"]+)"|(\d+)|([a-zA-Z_][a-zA-Z0-9_]*))/', $m[2], $segs, PREG_SET_ORDER)) {
        foreach ($segs as $s) {
            $key = null;
            for ($g = 1; $g <= 4; $g++) {
                if (isset($s[$g]) && $s[$g] !== '') {
                    $key = $s[$g];
                    break;
                }
            }
            if ($key === null) {
                $key = '';
            }
            if (!is_array($cur)) {
                return null;
            }
            $cur = array_key_exists($key, $cur) ? $cur[$key] : null;
        }
    }
    if ($cur === null || is_array($cur)) {
        return null;
    }
    return birkly_template_engine_js_string($cur);
}

function birkly_template_engine_evaluate_existence(string $ref, array $data): bool
{
    $value = birkly_template_engine_resolve_collection_entry_ref($ref, $data);
    if ($value === null) {
        return false;
    }
    if (is_array($value) && birkly_template_engine_is_list($value)) {
        return count($value) > 0;
    }
    return $value !== '' && $value !== false;
}

/** Resolve the left-hand side of a comparison; null mirrors JS `undefined`. */
function birkly_template_engine_resolve_comparison_value(string $expr, array $data): ?string
{
    $trimmed = trim($expr);
    $ctx = birkly_template_engine_resolve_context_path($trimmed, $data);
    if ($ctx !== null) {
        return $ctx;
    }
    if (preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/D', $trimmed)) {
        return birkly_template_engine_resolve_variable($trimmed, $data);
    }
    if (preg_match("/^'([^']+)'$/D", $trimmed, $m)) {
        $entry = (array_key_exists('entry', $data) && birkly_template_engine_truthy($data['entry']))
            ? $data['entry']
            : $data;
        if (is_array($entry) && array_key_exists($m[1], $entry)) {
            $v = $entry[$m[1]];
            return ($v === null || is_array($v)) ? '' : birkly_template_engine_js_string($v);
        }
    }
    return null;
}

function birkly_template_engine_evaluate_condition(string $condition, array $data): bool
{
    $cond = trim($condition);
    if ($cond === '') {
        return false;
    }

    if (preg_match('/^(.+?)\s+does\s+not\s+exist\s*$/iD', $cond, $m)) {
        return !birkly_template_engine_evaluate_existence(trim($m[1]), $data);
    }

    if (preg_match('/^(.+?)\s+exist\s*$/iD', $cond, $m)) {
        return birkly_template_engine_evaluate_existence(trim($m[1]), $data);
    }

    if (preg_match('/^entry\s+has\s+[\'"]([^\'"]+)[\'"]\s*(>=|<=|=<|=>|>|<|=|==|!=)\s*([0-9]+(?:\.[0-9]+)?)\s*$/iD', $cond, $m)) {
        $entry = (array_key_exists('entry', $data) && birkly_template_engine_truthy($data['entry'])) ? $data['entry'] : $data;
        if (!is_array($entry)) {
            return false;
        }
        $val = birkly_template_engine_parse_float(array_key_exists($m[1], $entry) ? $entry[$m[1]] : null);
        if (is_nan($val)) {
            return false;
        }
        return birkly_template_engine_compare_numeric($val, birkly_template_engine_normalize_operator($m[2]), (float) $m[3]);
    }

    if (preg_match('/^entry\s+has\s+[\'"]([^\'"]+)[\'"]\s*$/iD', $cond, $m)) {
        $entry = (array_key_exists('entry', $data) && birkly_template_engine_truthy($data['entry'])) ? $data['entry'] : $data;
        return is_array($entry) && birkly_template_engine_entry_has_field($entry, $m[1]);
    }

    if (preg_match('/^entry\s+has\s+([a-zA-Z_][a-zA-Z0-9_]*)\s*$/iD', $cond, $m)) {
        $entry = (array_key_exists('entry', $data) && birkly_template_engine_truthy($data['entry'])) ? $data['entry'] : $data;
        return is_array($entry) && birkly_template_engine_entry_has_field($entry, $m[1]);
    }

    if (preg_match('/^has\s+[\'"]([^\'"]+)[\'"]\s*$/iD', $cond, $m)) {
        $entry = (array_key_exists('entry', $data) && birkly_template_engine_truthy($data['entry'])) ? $data['entry'] : $data;
        return is_array($entry) && birkly_template_engine_entry_has_field($entry, $m[1]);
    }

    // entry-field numeric comparison without `has`: {if entry 'price' <= 15}
    if (preg_match('/^entry\s+[\'"]([^\'"]+)[\'"]\s*(>=|<=|=<|=>|>|<|=|==|!=)\s*([0-9]+(?:\.[0-9]+)?)\s*$/iD', $cond, $m)) {
        $entry = (array_key_exists('entry', $data) && birkly_template_engine_truthy($data['entry'])) ? $data['entry'] : $data;
        if (!is_array($entry)) {
            return false;
        }
        $val = birkly_template_engine_parse_float(array_key_exists($m[1], $entry) ? $entry[$m[1]] : null);
        if (is_nan($val)) {
            return false;
        }
        return birkly_template_engine_compare_numeric($val, birkly_template_engine_normalize_operator($m[2]), (float) $m[3]);
    }

    // Generic value comparison against a literal, e.g.
    // {if current.language = 'de'} or {if query.'mode' = 'grid'}
    if (preg_match('/^(.+?)\s*(=|==|!=|<=|>=|=<|=>|<|>)\s*(\'([^\']*)\'|"([^"]*)"|[0-9]+(?:\.[0-9]+)?|[a-zA-Z0-9_-]+)\s*$/D', $cond, $m, PREG_UNMATCHED_AS_NULL)) {
        $op = birkly_template_engine_normalize_operator((string) $m[2]);
        $rawRight = $m[3];
        $left = birkly_template_engine_resolve_comparison_value(trim((string) $m[1]), $data);
        if ($left !== null) {
            // Mirror of JS: m[4] (single-quoted) / m[5] (double-quoted) may be
            // an empty string but still "matched"; unmatched groups are null.
            if (($m[4] ?? null) !== null) {
                $right = $m[4];
            } elseif (($m[5] ?? null) !== null) {
                $right = $m[5];
            } else {
                $right = $rawRight;
            }
            $leftNum = birkly_template_engine_parse_float($left);
            $rightNum = birkly_template_engine_parse_float($right);
            if (!is_nan($leftNum) && !is_nan($rightNum) && preg_match('/^[0-9.]+$/D', birkly_template_engine_js_string($right))) {
                return birkly_template_engine_compare_numeric($leftNum, $op, $rightNum);
            }
            $leftStr = birkly_template_engine_js_string($left);
            $rightStr = birkly_template_engine_js_string($right);
            switch ($op) {
                case '=':
                    return $leftStr === $rightStr;
                case '!=':
                    return $leftStr !== $rightStr;
                case '<':
                    return strcmp($leftStr, $rightStr) < 0;
                case '>':
                    return strcmp($leftStr, $rightStr) > 0;
                case '<=':
                    return strcmp($leftStr, $rightStr) <= 0;
                case '>=':
                    return strcmp($leftStr, $rightStr) >= 0;
                default:
                    return false;
            }
        }
    }

    // Bare identifier: context flags or zero-arg template functions (user_logged_in).
    if (preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/D', $cond)) {
        if (array_key_exists($cond, $data)) {
            return birkly_template_engine_truthy($data[$cond]);
        }
        $tfValue = birkly_template_engine_call_registered_function($cond, []);
        if ($tfValue !== null) {
            return birkly_template_engine_truthy($tfValue);
        }
    }

    return false;
}

/** @param array<string,mixed> $options */
function birkly_template_engine_escape_output(string $pathOrName, $value, array $options): string
{
    $str = $value === null ? '' : birkly_template_engine_js_string($value);
    if ($str === '') {
        return '';
    }

    if (!function_exists('birkly_template_engine_should_output_raw_html')) {
        require_once __DIR__ . '/fieldtype_html.php';
    }

    if (birkly_template_engine_should_output_raw_html($pathOrName, $options)) {
        return $str;
    }

    return birkly_template_engine_escape_html($str);
}

function birkly_template_engine_resolve_variable(string $name, array $data): string
{
    if (array_key_exists($name, $data) && !is_array($data[$name]) && $data[$name] !== null) {
        return birkly_template_engine_js_string($data[$name]);
    }
    $entry = $data['entry'] ?? null;
    if (birkly_template_engine_truthy($entry) && is_array($entry)
        && array_key_exists($name, $entry) && !is_array($entry[$name]) && $entry[$name] !== null) {
        return birkly_template_engine_js_string($entry[$name]);
    }
    return '';
}

/**
 * @param list<array<int,string>> $parts  regex match sets from /'([^']+)'|(\d+)/
 */
function birkly_template_engine_format_resolved_field_leaf(string $fieldName, $current, array $data): string
{
    if ($current === null) {
        return '';
    }

    require_once __DIR__ . '/fieldtype_html.php';
    $fieldTypes = $data['_field_types'] ?? [];
    $type = is_array($fieldTypes) ? (string) ($fieldTypes[$fieldName] ?? '') : '';
    if ($fieldName !== '' && $type !== '') {
        require_once __DIR__ . '/fieldtype_presentation.php';
        return birkly_fieldtype_format_leaf_for_template(
            $fieldName,
            $current,
            $type,
            ['entry' => $data['entry'] ?? $data]
        );
    }

    if (is_array($current) || is_object($current)) {
        return '';
    }

    return birkly_template_engine_js_string($current);
}

function birkly_template_engine_resolve_field_path_from_parts(array $parts, array $data): string
{
    if (count($parts) === 1) {
        $key = (isset($parts[0][1]) && $parts[0][1] !== '') ? $parts[0][1] : ($parts[0][2] ?? '');
        if ($key !== '') {
            $current = null;
            if (array_key_exists($key, $data) && $data[$key] !== null) {
                $current = $data[$key];
            } else {
                $entry = (array_key_exists('entry', $data) && birkly_template_engine_truthy($data['entry']))
                    ? $data['entry']
                    : null;
                if (is_array($entry) && array_key_exists($key, $entry) && $entry[$key] !== null) {
                    $current = $entry[$key];
                }
            }
            if ($current !== null) {
                return birkly_template_engine_format_resolved_field_leaf($key, $current, $data);
            }
        }
    }

    $current = (array_key_exists('entry', $data) && birkly_template_engine_truthy($data['entry']))
        ? $data['entry']
        : $data;
    foreach ($parts as $part) {
        $key = (isset($part[1]) && $part[1] !== '') ? $part[1] : ($part[2] ?? '');
        if (!is_array($current) || !array_key_exists($key, $current)) {
            return '';
        }
        $current = $current[$key];
    }
    if ($current === null) {
        return '';
    }

    require_once __DIR__ . '/fieldtype_html.php';
    $path = '';
    foreach ($parts as $part) {
        $segment = (isset($part[1]) && $part[1] !== '') ? $part[1] : ($part[2] ?? '');
        if ($segment === '') {
            continue;
        }
        $path .= ($path === '' ? '' : '.') . (preg_match('/^\d+$/', (string) $segment) ? $segment : "'" . $segment . "'");
    }
    $fieldName = birkly_template_engine_field_name_for_escape($path);

    return birkly_template_engine_format_resolved_field_leaf($fieldName, $current, $data);
}

function birkly_template_engine_encode_entry_block_content(string $html): string
{
    return str_replace(
        ['&', '"', '<', '>'],
        ['&amp;', '&quot;', '&lt;', '&gt;'],
        $html
    );
}

/* -------------------------------------------------------------------------
 * Pipeline steps
 * ---------------------------------------------------------------------- */

/**
 * `{entry for 'collection'}…{endentry}` — public form scaffold placeholder.
 */
function birkly_template_engine_process_entry_for_blocks(string $html): string
{
    $pattern = '/\{entry\s+for\s+[\'"]([^\'"]+)[\'"]\}/i';
    $guard = 0;
    while ($guard++ < 100) {
        if (!preg_match($pattern, $html, $m, PREG_OFFSET_CAPTURE)) {
            break;
        }
        $start = $m[0][1];
        $openLen = strlen($m[0][0]);
        $collectionSlug = $m[1][0];
        $contentStart = $start + $openLen;
        [$closePos, $closeLen] = birkly_template_engine_find_closing_tag($html, $contentStart, ['{endentry}']);
        if ($closePos === null) {
            $html = substr($html, 0, $start) . substr($html, $contentStart);
            continue;
        }
        $inner = substr($html, $contentStart, $closePos - $contentStart);
        $placeholder = '<div class="birkly-entry-block" data-birkly-collection="'
            . birkly_template_engine_escape_html($collectionSlug)
            . '" data-birkly-content="'
            . birkly_template_engine_encode_entry_block_content($inner)
            . '"></div>';
        $html = substr($html, 0, $start) . $placeholder . substr($html, $closePos + $closeLen);
    }
    return $html;
}

/** `{public_form 'collection'}` — inline public form placeholder. */
function birkly_template_engine_process_public_form_blocks(string $html): string
{
    $out = preg_replace_callback('/\{public_form\s+[\'"]([^\'"]+)[\'"]\}/i', static function (array $m): string {
        return '<div class="birkly-form-placeholder" data-birkly-collection="'
            . birkly_template_engine_escape_html(trim($m[1]))
            . '"></div>';
    }, $html);
    return $out === null ? $html : $out;
}

function birkly_template_engine_stringify_inline_item($item): string
{
    if ($item === null) {
        return '';
    }
    if (!is_array($item)) {
        return birkly_template_engine_js_string($item);
    }
    foreach (['value', 'name', 'label', 'title', 'id'] as $k) {
        if (array_key_exists($k, $item) && $item[$k] !== null && !is_array($item[$k])) {
            return birkly_template_engine_js_string($item[$k]);
        }
    }
    return '';
}

/** @return mixed  item value, or null (mirror of JS undefined) */
function birkly_template_engine_resolve_array_inline_item(array $arr, string $accessor)
{
    if (!birkly_template_engine_is_list($arr)) {
        return null;
    }
    if (preg_match('/^item\.(\d+)$/iD', $accessor, $m)) {
        $idx = ((int) $m[1]) - 1;
        return ($idx >= 0 && $idx < count($arr)) ? $arr[$idx] : null;
    }
    if (preg_match('/^item\.newest$/iD', $accessor)) {
        return count($arr) > 0 ? $arr[count($arr) - 1] : null;
    }
    if (preg_match('/^item\.oldest$/iD', $accessor)) {
        return count($arr) > 0 ? $arr[0] : null;
    }
    if (preg_match("/^item\\.'([^']+)'$/iD", $accessor, $m)) {
        $key = $m[1];
        foreach ($arr as $it) {
            if ($it === null) {
                continue;
            }
            if (is_array($it)) {
                // Mirror of JS `['value','name','id','label'].some((k) => String(it[k]) === key)`
                // (String() of a missing property is 'undefined').
                foreach (['value', 'name', 'id', 'label'] as $k) {
                    $vStr = array_key_exists($k, $it)
                        ? birkly_template_engine_js_string($it[$k])
                        : 'undefined';
                    if ($vStr === $key) {
                        return $it;
                    }
                }
                continue;
            }
            if (birkly_template_engine_js_string($it) === $key) {
                return $it;
            }
        }
        return null;
    }
    return null;
}

/**
 * Inline single-value `{from … get …}` (no `{endget}`).
 * @param array<string,mixed> $options
 */
function birkly_template_engine_process_inline_from_get(string $html, array $data, array $options): string
{
    $out = preg_replace_callback('/\{from\s+([^{}]+?)\s+get\s+([^{}]+?)\}/i', static function (array $match) use ($data, $options): string {
        $src = trim($match[1]);
        $tgt = trim($match[2]);
        if (preg_match('/^entry\b/i', $tgt)) {
            return '';
        }

        // Case A: {from 'collection'.'identifier' get 'field'.0.'value'}
        $m = [];
        if (preg_match("/^'([^']+)'\\.'([^']+)'$/D", $src, $m)
            || preg_match("/^'([^']+)'\\.(current|newest|last-updated)$/D", $src, $m)) {
            $entry = birkly_template_engine_resolve_parent_collection_entry($m[1], $m[2], $data);
            if (!birkly_template_engine_truthy($entry) || !is_array($entry)) {
                return '';
            }
            if (!preg_match_all("/'([^']+)'|(\\d+)/", $tgt, $parts, PREG_SET_ORDER) || count($parts) === 0) {
                return '';
            }
            $value = birkly_template_engine_resolve_field_path_from_parts($parts, ['entry' => $entry]);
            return birkly_template_engine_escape_output($tgt, $value, $options);
        }

        // Case B: {from 'arrayField' get item.N | item.newest | item.'value'}
        if (preg_match("/^'([^']+)'$/D", $src, $m)) {
            $field = $m[1];
            $entry = $data['entry'] ?? null;
            $arr = null;
            if (birkly_template_engine_truthy($entry) && is_array($entry)
                && isset($entry[$field]) && is_array($entry[$field]) && birkly_template_engine_is_list($entry[$field])) {
                $arr = $entry[$field];
            } elseif (isset($data[$field]) && is_array($data[$field]) && birkly_template_engine_is_list($data[$field])) {
                $arr = $data[$field];
            }
            if ($arr === null) {
                return '';
            }
            $item = birkly_template_engine_resolve_array_inline_item($arr, $tgt);
            return birkly_template_engine_escape_output($tgt, birkly_template_engine_stringify_inline_item($item), $options);
        }

        return '';
    }, $html);
    return $out === null ? $html : $out;
}

/** @param callable(string,array):string $render */
function birkly_template_engine_process_from_get_summary_blocks(string $html, array $data, callable $render): string
{
    $pattern = '/\{from\s+[\'"]agent-guide[\'"]\s+get\s+summary\}/i';
    $guard = 0;
    while ($guard++ < 500) {
        if (!preg_match($pattern, $html, $m, PREG_OFFSET_CAPTURE)) {
            break;
        }
        $start = $m[0][1];
        $openLen = strlen($m[0][0]);
        $contentStart = $start + $openLen;
        [$closePos, $closeLen] = birkly_template_engine_find_closing_tag($html, $contentStart, ['{endget}', '{end}']);
        if ($closePos === null) {
            $html = substr($html, 0, $start) . substr($html, $contentStart);
            continue;
        }
        $block = substr($html, $contentStart, $closePos - $contentStart);
        $summary = $data['agent_guide'] ?? null;
        $replacement = '';
        if (is_array($summary) && $summary !== []) {
            $blockData = $data;
            $blockData['entry'] = $summary;
            foreach ($summary as $k => $v) {
                $blockData[$k] = $v;
            }
            $replacement = $render($block, $blockData);
        }
        $html = substr($html, 0, $start) . $replacement . substr($html, $closePos + $closeLen);
    }

    return $html;
}

/** @param callable(string,array):string $render */
function birkly_template_engine_process_from_get_blocks(string $html, array $data, callable $render): string
{
    $pattern = '/\{from\s+[\'"]([^\'"]+)[\'"]\s+get\s+entry\s+[\'"]([^\'"]+)[\'"]\}/i';
    $guard = 0;
    while ($guard++ < 500) {
        if (!preg_match($pattern, $html, $m, PREG_OFFSET_CAPTURE)) {
            break;
        }
        $start = $m[0][1];
        $openLen = strlen($m[0][0]);
        $collection = $m[1][0];
        $slug = $m[2][0];
        $contentStart = $start + $openLen;
        [$closePos, $closeLen] = birkly_template_engine_find_closing_tag($html, $contentStart, ['{endget}', '{end}']);
        if ($closePos === null) {
            $html = substr($html, 0, $start) . substr($html, $contentStart);
            continue;
        }
        $block = substr($html, $contentStart, $closePos - $contentStart);
        $entry = birkly_template_engine_resolve_entry($collection, $slug, $data);
        $replacement = '';
        if (birkly_template_engine_truthy($entry) && is_array($entry)) {
            $blockData = $data;
            $blockData['entry'] = $entry;
            foreach ($entry as $k => $v) {
                $blockData[$k] = $v;
            }
            $replacement = $render($block, $blockData);
        }
        $html = substr($html, 0, $start) . $replacement . substr($html, $closePos + $closeLen);
    }
    return $html;
}

/** @param callable(string,array):string $render */
function birkly_template_engine_process_for_loops(string $html, array $data, callable $render): string
{
    $pattern = '/\{for\s+(?:each\s+)?([a-zA-Z0-9_]+)\s+in\s+((?:[^{}]|\{[^}]*\})*)\}/i';
    $guard = 0;
    while ($guard++ < 500) {
        if (!preg_match($pattern, $html, $m, PREG_OFFSET_CAPTURE)) {
            break;
        }
        $start = $m[0][1];
        $openLen = strlen($m[0][0]);
        $itemVar = $m[1][0];
        $inClause = trim($m[2][0]);
        $contentStart = $start + $openLen;
        $close = birkly_template_engine_find_matching_loop_end($html, $contentStart);
        if ($close === null) {
            break;
        }
        [$closePos, $closeLen] = $close;
        $loopBody = substr($html, $contentStart, $closePos - $contentStart);
        $header = birkly_template_engine_parse_loop_modifiers($inClause);
        $items = birkly_template_engine_resolve_loop_items($header['inClause'], $data);
        $items = birkly_template_engine_apply_loop_filters($items, $header['filter'], $data);
        $items = birkly_template_engine_apply_loop_order($items, $header['order']);
        $items = birkly_template_engine_apply_loop_limit($items, $header['limit']);

        $rendered = '';
        foreach ($items as $item) {
            $loopData = $data;
            $loopData[$itemVar] = $item;
            if (!is_array($item)) {
                $loopData['__loop_scalar'] = $item;
            } else {
                unset($loopData['__loop_scalar']);
            }
            if (birkly_template_engine_truthy($item) && is_array($item)) {
                foreach ($item as $k => $v) {
                    $loopData[$k] = $v;
                }
                if ($itemVar === 'entry' || !array_key_exists('entry', $data)) {
                    $loopData['entry'] = $item;
                }
            }
            $rendered .= $render($loopBody, $loopData);
        }
        $html = substr($html, 0, $start) . $rendered . substr($html, $closePos + $closeLen);
    }
    return $html;
}

/** @param callable(string,array):string $render */
function birkly_template_engine_process_if_blocks(string $html, array $data, callable $render): string
{
    $pattern = '/\{(if|elseif)\s+([^}]+)\}/i';
    $guard = 0;
    while ($guard++ < 500) {
        if (!preg_match($pattern, $html, $m, PREG_OFFSET_CAPTURE)) {
            break;
        }
        $start = $m[0][1];
        $openLen = strlen($m[0][0]);
        $condition = trim($m[2][0]);
        $contentStart = $start + $openLen;
        [$closePos, $closeLen, $elsePos, $elseLen] = birkly_template_engine_find_if_block_end($html, $contentStart);
        if ($closePos === null) {
            break;
        }

        $trueBlock = $elsePos !== null
            ? substr($html, $contentStart, $elsePos - $contentStart)
            : substr($html, $contentStart, $closePos - $contentStart);
        $falseBlock = $elsePos !== null
            ? substr($html, $elsePos + $elseLen, $closePos - ($elsePos + $elseLen))
            : '';

        $replacement = birkly_template_engine_evaluate_condition($condition, $data)
            ? $render($trueBlock, $data)
            : $render($falseBlock, $data);
        $html = substr($html, 0, $start) . $replacement . substr($html, $closePos + $closeLen);
    }
    return $html;
}

function birkly_template_engine_process_count_variables(string $html, array $data): string
{
    $out = preg_replace_callback('/\{\s*count\s+of\s+([^}]+)\}/i', static function (array $match) use ($data): string {
        $trimmed = trim($match[1]);
        if (preg_match("/^'([^']+)'\\s+of\\s+'([^']+)'\\.'([^']+)'\\s*$/iD", $trimmed, $m)) {
            $items = birkly_template_engine_resolve_sub_collection_items($m[1], $m[2], $m[3], $data);
        } elseif (preg_match("/^'([^']+)'\\s*$/D", $trimmed, $m)) {
            $items = birkly_template_engine_resolve_loop_items("'" . $m[1] . "'", $data);
        } else {
            $items = birkly_template_engine_resolve_loop_items($trimmed, $data);
        }
        return (string) (is_array($items) ? count($items) : 0);
    }, $html);
    return $out === null ? $html : $out;
}

/**
 * Invoke a plugin-registered template function (SSR / page_bundle path).
 *
 * @param list<mixed> $args
 */
function birkly_template_engine_call_registered_function(string $name, array $args = []): mixed
{
    if ($name === '' || !preg_match('/^[a-zA-Z0-9_]+$/', $name)) {
        return null;
    }
    if (!function_exists('birkly_page_bundle_call_template_function')) {
        $path = __DIR__ . '/page_bundle_template_functions.php';
        if (!is_file($path)) {
            return null;
        }
        require_once $path;
    }
    if (!function_exists('birkly_page_bundle_call_template_function')) {
        return null;
    }
    return birkly_page_bundle_call_template_function($name, $args);
}

/**
 * Resolve a template-function argument: field ref {'body'}, literal 'text', or bare identifier.
 *
 * @param array<string,mixed> $data
 */
function birkly_template_engine_resolve_template_function_arg(string $raw, array $data): mixed
{
    $trimmed = trim($raw);
    if ($trimmed === '') {
        return '';
    }
    if (preg_match("/^'([^']*)'$/D", $trimmed, $m)) {
        $key = $m[1];
        if (array_key_exists($key, $data)) {
            return $data[$key];
        }
        $entry = (array_key_exists('entry', $data) && birkly_template_engine_truthy($data['entry']))
            ? $data['entry']
            : null;
        if (is_array($entry) && array_key_exists($key, $entry)) {
            return $entry[$key];
        }
        return $key;
    }
    if (preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/D', $trimmed)) {
        if (array_key_exists($trimmed, $data)) {
            $val = $data[$trimmed];
            if (!is_array($val)) {
                return $val;
            }
        }
        $entry = (array_key_exists('entry', $data) && birkly_template_engine_truthy($data['entry']))
            ? $data['entry']
            : null;
        if (is_array($entry) && array_key_exists($trimmed, $entry)) {
            $val = $entry[$trimmed];
            if (!is_array($val)) {
                return $val;
            }
        }
    }
    return $trimmed;
}

/**
 * Split comma-separated template-function args (paren call syntax), respecting single quotes.
 *
 * @return list<string>
 */
function birkly_template_engine_split_template_function_call_args(string $raw): array
{
    $raw = trim($raw);
    if ($raw === '') {
        return [];
    }
    $args = [];
    $buf = '';
    $len = strlen($raw);
    $inQuote = false;
    for ($i = 0; $i < $len; $i++) {
        $ch = $raw[$i];
        if ($ch === "'") {
            $inQuote = !$inQuote;
            $buf .= $ch;
            continue;
        }
        if ($ch === ',' && !$inQuote) {
            $piece = trim($buf);
            if ($piece !== '') {
                $args[] = $piece;
            }
            $buf = '';
            continue;
        }
        $buf .= $ch;
    }
    $piece = trim($buf);
    if ($piece !== '') {
        $args[] = $piece;
    }

    return $args;
}

/**
 * @param array<string,mixed> $data
 * @return list<mixed>
 */
function birkly_template_engine_parse_template_function_args(string $rawArgs, array $data): array
{
    $rawArgs = trim($rawArgs);
    if ($rawArgs === '') {
        return [];
    }
    if (preg_match("/^\{'([^']+)'\}$/", $rawArgs, $fm)) {
        return [birkly_template_engine_resolve_template_function_arg("'" . $fm[1] . "'", $data)];
    }
    if (str_starts_with($rawArgs, '(') && str_ends_with($rawArgs, ')')) {
        $rawArgs = substr($rawArgs, 1, -1);
    }
    $pieces = str_contains($rawArgs, ',')
        ? birkly_template_engine_split_template_function_call_args($rawArgs)
        : [$rawArgs];
    $args = [];
    foreach ($pieces as $piece) {
        $piece = trim($piece);
        if ($piece === '') {
            continue;
        }
        if (preg_match("/^'([^']*)'$/D", $piece, $qm)) {
            $args[] = birkly_template_engine_resolve_template_function_arg("'" . $qm[1] . "'", $data);
            continue;
        }
        $args[] = birkly_template_engine_resolve_template_function_arg($piece, $data);
    }

    return $args;
}

/**
 * @param array<string,mixed> $data
 */
function birkly_template_engine_evaluate_template_function(string $funcName, string $rawArgs, array $data): string
{
    $args = birkly_template_engine_parse_template_function_args($rawArgs, $data);
    $result = birkly_template_engine_call_registered_function($funcName, $args);
    if ($result === null) {
        return '';
    }
    if (is_bool($result)) {
        return $result ? '1' : '';
    }
    if (is_scalar($result)) {
        return (string) $result;
    }

    return '';
}

/**
 * Replace {template_function arg …} calls with SSR-evaluated results.
 *
 * @param array<string,mixed> $data
 */
function birkly_template_engine_process_template_functions(string $html, array $data): string
{
    if ($html === '' || !function_exists('birkly_page_bundle_template_functions_meta_list')) {
        $path = __DIR__ . '/page_bundle_template_functions.php';
        if (!is_file($path)) {
            return $html;
        }
        require_once $path;
    }

    $meta = birkly_page_bundle_template_functions_meta_list();
    $names = [];
    foreach ($meta as $row) {
        if (is_array($row) && !empty($row['name'])) {
            $names[(string) $row['name']] = true;
        }
    }
    if ($names === []) {
        return $html;
    }

    $controlStarts = [
        'if' => true, 'else' => true, 'elseif' => true, 'endif' => true,
        'for' => true, 'endfor' => true, 'foreach' => true, 'endforeach' => true,
        'from' => true, 'get' => true, 'endget' => true, 'endentry' => true,
        'end' => true, 'set' => true, 'count' => true, 'entry' => true, 'public_form' => true,
    ];

    $replaceFn = static function (array $match) use ($data, $names, $controlStarts): string {
        $funcName = $match[1];
        if (!isset($names[$funcName]) || isset($controlStarts[$funcName])) {
            return $match[0];
        }

        return birkly_template_engine_evaluate_template_function($funcName, trim($match[2]), $data);
    };

    // Docs / shelf syntax: {media_img('hero', title)}
    $out = preg_replace_callback(
        '/\{([a-zA-Z0-9_]+)\(([^)]*)\)\}/',
        $replaceFn,
        $html
    );
    $out = $out === null ? $html : $out;

    // Space-separated args: {render_mentions {'body'}}
    $out = preg_replace_callback(
        '/\{([a-zA-Z0-9_]+)\s+((?:[^{}]|\{[^{}]*\})*)\}/',
        $replaceFn,
        $out
    );

    return $out === null ? $html : $out;
}

/** @param array<string,mixed> $options */
function birkly_template_engine_process_variables(string $html, array $data, array $options): string
{
    $out = preg_replace_callback('/\{([^{}]*)\}/', static function (array $match) use ($data, $options): string {
        $full = $match[0];
        $trimmed = trim($match[1]);
        if ($trimmed === '') {
            return '';
        }
        // Bare `entry` is a valid loop variable for scalar arrays; only strip real control tags.
        if (preg_match('/^(if|else|elseif|endif|for|endfor|from|get|endget|endentry|end|set|count)\b/i', $trimmed)) {
            return '';
        }
        if (preg_match('/^entry\s+for\s+[\'"][^\'"]+[\'"]$/iD', $trimmed)) {
            return '';
        }
        if (preg_match('/^public_form\s+[\'"][^\'"]+[\'"]$/iD', $trimmed)) {
            return '';
        }
        if (preg_match('/^count\s+of\b/i', $trimmed)) {
            return $full;
        }

        if ($trimmed === '.' && array_key_exists('__loop_scalar', $data)) {
            return birkly_template_engine_escape_output('.', (string) $data['__loop_scalar'], $options);
        }

        // Context paths with optional date format: comment.created_at format "M j, Y"
        if (preg_match('/^([a-zA-Z_][a-zA-Z0-9_]*(?:\.[a-zA-Z_][a-zA-Z0-9_]*)+)(?:\s+format\s+["\']([^"\']+)["\'])?\s*$/D', $trimmed, $dm)) {
            $ctx = birkly_template_engine_resolve_context_path($dm[1], $data);
            if ($ctx !== null) {
                if (isset($dm[2]) && $dm[2] !== '' && $ctx !== '') {
                    return birkly_template_engine_format_date($ctx, $dm[2]);
                }
                return birkly_template_engine_escape_output($dm[1], $ctx, $options);
            }
        }

        // System / context paths: current.language, query.'lang', query.slug …
        $ctx = birkly_template_engine_resolve_context_path($trimmed, $data);
        if ($ctx !== null) {
            return birkly_template_engine_escape_output($trimmed, $ctx, $options);
        }

        if (strpos($trimmed, "'") !== false) {
            if (preg_match_all("/'([^']+)'|(\\d+)/", $trimmed, $parts, PREG_SET_ORDER) && count($parts) > 0) {
                $value = birkly_template_engine_resolve_field_path_from_parts($parts, $data);
                return birkly_template_engine_escape_output($trimmed, $value, $options);
            }
        }

        if (preg_match('/^([a-zA-Z_][a-zA-Z0-9_]*)(?:\s+format\s+["\']([^"\']+)["\'])?$/D', $trimmed, $vm)) {
            $name = $vm[1];
            $format = (isset($vm[2]) && $vm[2] !== '') ? $vm[2] : null;
            $value = birkly_template_engine_resolve_variable($name, $data);
            if ($format !== null && $value !== '') {
                return birkly_template_engine_format_date($value, $format);
            }
            return birkly_template_engine_escape_output($name, $value, $options);
        }

        return '';
    }, $html);
    return $out === null ? $html : $out;
}

/* -------------------------------------------------------------------------
 * Protected blocks (<style>/<script>/<noscript>)
 * ---------------------------------------------------------------------- */

/**
 * Pull `<style>`, `<script>`, and `<noscript>` bodies out of the template so
 * CSS/JS `{ … }` blocks are not eaten by process_variables. Tokens are
 * restored after templating. (V-PHP-11 / V-ENG-11.)
 *
 * @return array{html:string,blocks:list<string>}
 */
function birkly_template_engine_extract_protected_blocks(string $html): array
{
    $blocks = [];
    $next = preg_replace_callback(
        '~<(script|style|noscript)\b([^>]*)>([\s\S]*?)</\1>~i',
        static function (array $m) use (&$blocks): string {
            $token = '@@BIRKLY_PROTECTED_' . count($blocks) . '@@';
            $blocks[] = $m[3];
            return '<' . $m[1] . $m[2] . '>' . $token . '</' . $m[1] . '>';
        },
        $html
    );
    return ['html' => $next === null ? $html : $next, 'blocks' => $blocks];
}

/** @param list<string> $blocks */
function birkly_template_engine_restore_protected_blocks(string $html, array $blocks): string
{
    $out = $html;
    $count = count($blocks);
    for ($i = 0; $i < $count; $i++) {
        $out = str_replace('@@BIRKLY_PROTECTED_' . $i . '@@', $blocks[$i], $out);
    }
    return $out;
}

/* -------------------------------------------------------------------------
 * Public API
 * ---------------------------------------------------------------------- */

/** HTML-escape a value (mirror of JS engine escapeHtml). */
function birkly_template_engine_escape_html(string $str): string
{
    return str_replace(
        ['&', '<', '>', '"', "'"],
        ['&amp;', '&lt;', '&gt;', '&quot;', '&#39;'],
        $str
    );
}

/** Does the string contain Birkly template syntax? */
function birkly_template_engine_has_syntax(string $html): bool
{
    return (bool) preg_match('/\{[a-zA-Z0-9_\'"]/', $html);
}

/**
 * Render a template string against data (mirror of JS engine render()).
 *
 * @param mixed $data    render context — JSON-shaped associative array
 *                       (collections, entry, site_elements, query, current, …)
 * @param array<string,mixed> $options  escapeHtml defaults to true (safe);
 *                       `_skipProtect` is internal (nested renders only)
 */
function birkly_template_engine_render(string $html, $data, array $options = []): string
{
    if ($html === '') {
        return '';
    }
    if (!is_array($data)) {
        return $html;
    }

    $ctxOptions = ['escapeHtml' => (($options['escapeHtml'] ?? null) === false) ? false : true];
    if (isset($data['_html_fields']) && is_array($data['_html_fields'])) {
        $ctxOptions['htmlFields'] = $data['_html_fields'];
    } elseif (isset($options['htmlFields']) && is_array($options['htmlFields'])) {
        $ctxOptions['htmlFields'] = $options['htmlFields'];
    }
    // Nested render() calls (loops/from/if) must not re-extract already-tokenized
    // protected blocks from the parent pass — only the outermost call protects.
    $protect = ($options['_skipProtect'] ?? null) !== true;
    $renderFn = static function (string $tpl, array $ctx) use ($ctxOptions): string {
        return birkly_template_engine_render($tpl, $ctx, array_merge($ctxOptions, ['_skipProtect' => true]));
    };

    $source = $html;
    $protectedBlocks = [];
    if ($protect) {
        $extracted = birkly_template_engine_extract_protected_blocks($html);
        $source = $extracted['html'];
        $protectedBlocks = $extracted['blocks'];
    }

    $out = birkly_template_engine_process_entry_for_blocks($source);
    $out = birkly_template_engine_process_public_form_blocks($out);
    $out = birkly_template_engine_process_from_get_summary_blocks($out, $data, $renderFn);
    $out = birkly_template_engine_process_from_get_blocks($out, $data, $renderFn);
    $out = birkly_template_engine_process_inline_from_get($out, $data, $ctxOptions);
    $out = birkly_template_engine_process_for_loops($out, $data, $renderFn);
    $out = birkly_template_engine_process_if_blocks($out, $data, $renderFn);
    $out = birkly_template_engine_process_count_variables($out, $data);
    $out = birkly_template_engine_process_template_functions($out, $data);
    $out = birkly_template_engine_process_variables($out, $data, $ctxOptions);

    if ($protect && count($protectedBlocks) > 0) {
        $out = birkly_template_engine_restore_protected_blocks($out, $protectedBlocks);
    }
    return $out;
}
