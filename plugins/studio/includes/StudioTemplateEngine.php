<?php
/**
 * Studio SSR template engine (P24-M2) — canonical Birkly syntax on the server.
 */

declare(strict_types=1);

class StudioTemplateEngine
{
    /**
     * @return list<array<string, mixed>>
     */
    public static function collectBundleRequests(string $html): array
    {
        $requests = [];
        $seen = [];

        if (preg_match_all(
            "/\{for\s+each\s+\w+\s+in\s+'([a-z0-9_-]+)'/i",
            $html,
            $matches
        )) {
            foreach ($matches[1] as $col) {
                if (isset($seen['list:' . $col])) {
                    continue;
                }
                $seen['list:' . $col] = true;
                $requests[] = ['type' => 'list_entries', 'into' => $col, 'collection' => $col];
            }
        }

        if (preg_match_all(
            "/\{from\s+'([a-z0-9_-]+)'\s+get\s+entry\s+'([a-z0-9_-]+)'\}/i",
            $html,
            $matches,
            PREG_SET_ORDER
        )) {
            foreach ($matches as $m) {
                $col = $m[1];
                $slug = $m[2];
                $into = '__entry__:' . $col . ':' . $slug;
                if (isset($seen['get:' . $into])) {
                    continue;
                }
                $seen['get:' . $into] = true;
                $requests[] = [
                    'type' => 'get_entry',
                    'into' => $into,
                    'collection' => $col,
                    'slug' => $slug,
                ];
            }
        }

        if (str_contains($html, 'site_elements') && !isset($seen['list:site_elements'])) {
            $requests[] = ['type' => 'list_entries', 'into' => 'site_elements', 'collection' => 'site_elements'];
        }

        return $requests;
    }

    public function render(string $html, array $data): string
    {
        $out = $this->processFromGetBlocks($html, $data);
        $out = $this->processForLoops($out, $data);
        $out = $this->processIfBlocks($out, $data);
        $out = $this->processVariables($out, $data);

        return $out;
    }

    private function processFromGetBlocks(string $html, array $data): string
    {
        $pattern = '/\{from\s+[\'"]([^\'"]+)[\'"]\s+get\s+entry\s+[\'"]([^\'"]+)[\'"]\}/i';

        while (preg_match($pattern, $html, $m, PREG_OFFSET_CAPTURE)) {
            $start = $m[0][1];
            $openLen = strlen($m[0][0]);
            $collection = $m[1][0];
            $slug = $m[2][0];
            $contentStart = $start + $openLen;

            [$closePos, $closeLen] = $this->findClosingTag($html, $contentStart, ['{endget}', '{end}']);
            if ($closePos === null) {
                $html = substr($html, 0, $start) . substr($html, $contentStart);
                continue;
            }

            $block = substr($html, $contentStart, $closePos - $contentStart);
            $entry = $this->resolveEntry($collection, $slug, $data);
            $replacement = '';
            if (is_array($entry)) {
                $blockData = array_merge($data, ['entry' => $entry], $entry);
                $replacement = $this->render($block, $blockData);
            }

            $html = substr($html, 0, $start) . $replacement . substr($html, $closePos + $closeLen);
        }

        return $html;
    }

    private function processForLoops(string $html, array $data): string
    {
        $pattern = '/\{for\s+(?:each\s+)?([a-zA-Z0-9_]+)\s+in\s+([^}]+)\}/i';

        while (preg_match($pattern, $html, $m, PREG_OFFSET_CAPTURE)) {
            $start = $m[0][1];
            $openLen = strlen($m[0][0]);
            $itemVar = $m[1][0];
            $inClause = trim($m[2][0]);
            $contentStart = $start + $openLen;

            $close = $this->findMatchingLoopEnd($html, $contentStart);
            if ($close === null) {
                break;
            }
            [$closePos, $closeLen] = $close;

            $loopBody = substr($html, $contentStart, $closePos - $contentStart);
            $items = $this->resolveLoopItems($inClause, $data);
            $header = $this->parseLoopModifiers($inClause);
            $items = $this->applyLoopFilters($items, $header);
            $items = $this->applyLoopOrder($items, $header);
            $items = $this->applyLoopLimit($items, $header);

            $rendered = '';
            foreach ($items as $item) {
                $loopData = array_merge($data, [$itemVar => $item]);
                if (is_array($item)) {
                    if ($itemVar === 'entry' || !array_key_exists('entry', $data)) {
                        $loopData['entry'] = $item;
                        $loopData = array_merge($loopData, $item);
                    }
                }
                $rendered .= $this->render($loopBody, $loopData);
            }

            $html = substr($html, 0, $start) . $rendered . substr($html, $closePos + $closeLen);
        }

        return $html;
    }

    /**
     * @return array{0:int,1:int}|null
     */
    private function findMatchingLoopEnd(string $html, int $from): ?array
    {
        $pos = $from;
        $depth = 1;
        $openPattern = '/\{for\s+(?:each\s+)?/i';
        $closeTags = ['{endfor}', '{endforeach}', '{end}'];

        while ($pos < strlen($html) && $depth > 0) {
            $nextOpen = preg_match($openPattern, $html, $om, PREG_OFFSET_CAPTURE, $pos)
                ? $om[0][1]
                : PHP_INT_MAX;
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

    /**
     * @return list<array<string, mixed>>
     */
    private function resolveLoopItems(string $inClause, array $data): array
    {
        if (preg_match("/^'([^']+)'/", $inClause, $m)) {
            $collection = $m[1];
            if (isset($data[$collection]) && is_array($data[$collection])) {
                return array_values($data[$collection]);
            }
            $entry = $data['entry'] ?? null;
            if (is_array($entry) && isset($entry[$collection]) && is_array($entry[$collection])) {
                return array_values($entry[$collection]);
            }

            return [];
        }

        $field = preg_replace('/[^a-zA-Z0-9_]/', '', explode(' ', $inClause)[0] ?? '');
        if ($field === '') {
            return [];
        }

        $entry = $data['entry'] ?? null;
        if (!is_array($entry)) {
            return [];
        }

        $value = $entry[$field] ?? $entry[$field . 's'] ?? [];
        if (is_array($value)) {
            return array_values($value);
        }

        return [];
    }

    /**
     * @return array{collection:string,filter:?string,order:?string,limit:?int}
     */
    private function parseLoopModifiers(string $inClause): array
    {
        $result = ['collection' => '', 'filter' => null, 'order' => null, 'limit' => null];
        if (preg_match("/^'([^']+)'(.*)$/s", $inClause, $m)) {
            $result['collection'] = $m[1];
            $tail = $m[2];
            if (preg_match('/\bwith\s+(.+?)(?:\s+ordered\s+by|\s+limit\s+|\s*$)/is', $tail, $fm)) {
                $result['filter'] = trim($fm[1]);
            }
            if (preg_match('/\bordered\s+by\s+(newest|oldest|last-updated)\b/i', $tail, $om)) {
                $result['order'] = strtolower($om[1]);
            }
            if (preg_match('/\blimit\s+(\d+)\b/i', $tail, $lm)) {
                $result['limit'] = (int) $lm[1];
            }
        }

        return $result;
    }

    /**
     * @param list<array<string, mixed>> $items
     * @return list<array<string, mixed>>
     */
    private function applyLoopFilters(array $items, array $header): array
    {
        $filter = $header['filter'] ?? null;
        if ($filter === null || $filter === '') {
            return $items;
        }

        if (preg_match('/^([a-zA-Z_][a-zA-Z0-9_]*)\s*=\s*([a-zA-Z_][a-zA-Z0-9_]*)$/', $filter, $m)) {
            $field = $m[1];
            $value = $m[2];
            return array_values(array_filter($items, static fn($it) => is_array($it) && (string) ($it[$field] ?? '') === $value));
        }

        return $items;
    }

    /**
     * @param list<array<string, mixed>> $items
     * @return list<array<string, mixed>>
     */
    private function applyLoopOrder(array $items, array $header): array
    {
        $order = $header['order'] ?? null;
        if ($order === null || count($items) < 2) {
            return $items;
        }

        $field = $order === 'last-updated' ? 'updated_at' : 'created_at';
        usort($items, static function ($a, $b) use ($field, $order) {
            $da = is_array($a) ? strtotime((string) ($a[$field] ?? '')) : 0;
            $db = is_array($b) ? strtotime((string) ($b[$field] ?? '')) : 0;
            if ($order === 'oldest') {
                return $da <=> $db;
            }
            return $db <=> $da;
        });

        return $items;
    }

    /**
     * @param list<array<string, mixed>> $items
     * @return list<array<string, mixed>>
     */
    private function applyLoopLimit(array $items, array $header): array
    {
        $limit = $header['limit'] ?? null;
        if ($limit === null || $limit < 1) {
            return $items;
        }

        return array_slice($items, 0, $limit);
    }

    private function processIfBlocks(string $html, array $data): string
    {
        $pattern = '/\{if\s+entry\s+has\s+[\'"]([^\'"]+)[\'"]\s*\}/i';
        while (preg_match($pattern, $html, $m, PREG_OFFSET_CAPTURE)) {
            $start = $m[0][1];
            $openLen = strlen($m[0][0]);
            $field = $m[1][0];
            $contentStart = $start + $openLen;

            [$closePos, $closeLen] = $this->findClosingTag($html, $contentStart, ['{endif}', '{/if}']);
            if ($closePos === null) {
                break;
            }

            $block = substr($html, $contentStart, $closePos - $contentStart);
            $entry = $data['entry'] ?? $data;
            $has = is_array($entry) && $this->entryHasField($entry, $field);
            $replacement = $has ? $this->render($block, $data) : '';

            $html = substr($html, 0, $start) . $replacement . substr($html, $closePos + $closeLen);
        }

        return $html;
    }

    private function entryHasField(array $entry, string $field): bool
    {
        if (!array_key_exists($field, $entry)) {
            return false;
        }
        $val = $entry[$field];
        if ($val === null || $val === '' || $val === []) {
            return false;
        }

        return true;
    }

    private function processVariables(string $html, array $data): string
    {
        return preg_replace_callback(
            '/\{([^}]+)\}/',
            function (array $m) use ($data) {
                $inner = trim($m[1]);
                if ($inner === '' || preg_match('/^(if|else|endif|for|endfor|from|get|endget|end)\b/i', $inner)) {
                    return '';
                }

                if (str_contains($inner, "'")) {
                    preg_match_all("/'([^']+)'|(\d+)/", $inner, $parts, PREG_SET_ORDER);
                    if ($parts !== []) {
                        $value = $this->resolveFieldPathFromParts($parts, $data);
                        return $this->escapeOutput($inner, $value);
                    }
                }

                if (preg_match('/^([a-zA-Z_][a-zA-Z0-9_]*)(?:\s+format\s+[\'"]([^\'"]+)[\'"])?$/', $inner, $vm)) {
                    $name = $vm[1];
                    $format = $vm[2] ?? null;
                    $value = $this->resolveVariable($name, $data);
                    if ($format !== null && $value !== '') {
                        $ts = strtotime((string) $value);
                        if ($ts !== false) {
                            return date($format, $ts);
                        }
                    }
                    return $this->escapeOutput($name, $value);
                }

                return '';
            },
            $html
        ) ?? $html;
    }

    private function resolveVariable(string $name, array $data): string
    {
        if (array_key_exists($name, $data) && !is_array($data[$name])) {
            return (string) $data[$name];
        }
        $entry = $data['entry'] ?? null;
        if (is_array($entry) && array_key_exists($name, $entry) && !is_array($entry[$name])) {
            return (string) $entry[$name];
        }

        return '';
    }

    private function resolveFieldPathFromParts(array $parts, array $data): string
    {
        $current = $data['entry'] ?? $data;
        foreach ($parts as $part) {
            $key = $part[1] !== '' ? $part[1] : $part[2];
            if (!is_array($current) || !array_key_exists($key, $current)) {
                return '';
            }
            $current = $current[$key];
        }

        if (is_scalar($current) || $current === null) {
            return (string) ($current ?? '');
        }

        return '';
    }

    private function escapeOutput(string $pathOrName, mixed $value): string
    {
        $str = (string) ($value ?? '');
        if ($str === '') {
            return '';
        }
        if (str_contains($pathOrName, 'content') || str_contains($pathOrName, 'html')) {
            return $str;
        }

        return htmlspecialchars($str, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    private function resolveEntry(string $collection, string $slug, array $data): ?array
    {
        $key = '__entry__:' . $collection . ':' . $slug;
        if (isset($data[$key]) && is_array($data[$key])) {
            return $data[$key];
        }

        $entries = $data[$collection] ?? [];
        if (!is_array($entries)) {
            return null;
        }

        foreach ($entries as $entry) {
            if (!is_array($entry)) {
                continue;
            }
            if (($entry['slug'] ?? '') === $slug || ($entry['id'] ?? '') === $slug) {
                return $entry;
            }
        }

        return null;
    }

    /**
     * @param list<string> $tags
     * @return array{0:?int,1:int}
     */
    private function findClosingTag(string $html, int $from, array $tags): array
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

        return [$bestPos, $bestLen];
    }
}
