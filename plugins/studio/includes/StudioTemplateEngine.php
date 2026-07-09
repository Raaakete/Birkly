<?php
/**
 * Studio SSR template engine (P24-M2, P25-M3/M4) — canonical Birkly syntax on the server.
 */

declare(strict_types=1);

class StudioTemplateEngine
{
  private const CONTROL_WORDS = [
    'if', 'else', 'elseif', 'endif', 'for', 'endfor', 'foreach', 'endforeach',
    'from', 'get', 'endget', 'end', 'set', 'count',
  ];

  /**
   * @return list<array<string, mixed>>
   */
  public static function collectBundleRequests(string $html): array
  {
    $requests = [];
    $seen = [];

    $add = static function (array $req) use (&$requests, &$seen): void {
      $type = $req['type'] ?? '';
      $into = $req['into'] ?? '';
      if ($type === '') {
        return;
      }
      if ($type !== 'template_functions_meta' && $into === '') {
        return;
      }
      $key = json_encode($req);
      if (isset($seen[$key])) {
        return;
      }
      $seen[$key] = true;
      $requests[] = $req;
    };

    if (preg_match_all(
      "/\{for\s+each\s+\w+\s+in\s+'([a-z0-9_-]+)'/i",
      $html,
      $matches
    )) {
      foreach ($matches[1] as $col) {
        $add(['type' => 'list_entries', 'into' => $col, 'collection' => $col]);
      }
    }

    if (preg_match_all(
      "/\{for\s+(?:each\s+)?\w+\s+in\s+'([a-z0-9_-]+)'\s+of\s+'([a-z0-9_-]+)'\.'([^']+)'/i",
      $html,
      $matches,
      PREG_SET_ORDER
    )) {
      foreach ($matches as $m) {
        $subCol = $m[1];
        $parentCol = $m[2];
        $parentId = $m[3];
        $into = '__sub__:' . $parentCol . ':' . $parentId . ':' . $subCol;
        $add([
          'type' => 'sub_entries',
          'into' => $into,
          'parent_collection' => $parentCol,
          'parent_entry_id' => $parentId,
          'sub_collection' => $subCol,
        ]);
      }
    }

    if (preg_match_all(
      "/\{for\s+(?:each\s+)?\w+\s+in\s+'([a-z0-9_-]+)'\s+of\s+'([a-z0-9_-]+)'\.(current|newest|last-updated)/i",
      $html,
      $matches,
      PREG_SET_ORDER
    )) {
      foreach ($matches as $m) {
        $subCol = $m[1];
        $parentCol = $m[2];
        $parentId = $m[3];
        $into = '__sub__:' . $parentCol . ':' . $parentId . ':' . $subCol;
        $add([
          'type' => 'sub_entries',
          'into' => $into,
          'parent_collection' => $parentCol,
          'parent_entry_id' => $parentId,
          'sub_collection' => $subCol,
        ]);
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
        $add([
          'type' => 'get_entry',
          'into' => $into,
          'collection' => $col,
          'slug' => $slug,
        ]);
      }
    }

    foreach (self::collectTemplateFunctionRequests($html) as $tfReq) {
      $add($tfReq);
    }

    if (str_contains($html, 'site_elements')) {
      $add(['type' => 'list_entries', 'into' => 'site_elements', 'collection' => 'site_elements']);
    }

    return $requests;
  }

  /**
   * @return list<array<string, mixed>>
   */
  private static function collectTemplateFunctionRequests(string $html): array
  {
    $requests = [];
    $seen = [];

    if (preg_match_all('/\{([a-zA-Z][a-zA-Z0-9_]*)\s+([^}]+)\}/', $html, $matches, PREG_SET_ORDER)) {
      foreach ($matches as $m) {
        $name = $m[1];
        if (in_array(strtolower($name), self::CONTROL_WORDS, true)) {
          continue;
        }
        if (!self::looksLikeTemplateFunction($name)) {
          continue;
        }
        $args = self::parseTemplateFunctionArgs($m[2]);
        $key = self::templateFunctionDataKey($name, $args);
        if (isset($seen[$key])) {
          continue;
        }
        $seen[$key] = true;
        $requests[] = [
          'type' => 'template_function',
          'into' => $key,
          'name' => $name,
          'args' => $args,
        ];
      }
    }

    if (preg_match_all('/\{([a-zA-Z][a-zA-Z0-9_]*)\}/', $html, $matches)) {
      foreach ($matches[1] as $name) {
        if (in_array(strtolower($name), self::CONTROL_WORDS, true)) {
          continue;
        }
        if (!self::looksLikeTemplateFunction($name)) {
          continue;
        }
        $key = self::templateFunctionDataKey($name, []);
        if (isset($seen[$key])) {
          continue;
        }
        $seen[$key] = true;
        $requests[] = [
          'type' => 'template_function',
          'into' => $key,
          'name' => $name,
          'args' => [],
        ];
      }
    }

    if ($requests !== []) {
      $requests[] = ['type' => 'template_functions_meta', 'into' => '__tf_meta__'];
    }

    return $requests;
  }

  public static function templateFunctionDataKey(string $name, array $args): string
  {
    return '__tf__:' . $name . ':' . substr(md5($name . '|' . json_encode($args)), 0, 8);
  }

  /**
   * @return list<mixed>
   */
  private static function parseTemplateFunctionArgs(string $raw): array
  {
    $raw = trim($raw);
    if ($raw === '') {
      return [];
    }
    if (preg_match_all("/'([^']*)'|\"([^\"]*)\"|(\d+(?:\.\d+)?)/", $raw, $parts, PREG_SET_ORDER)) {
      $args = [];
      foreach ($parts as $p) {
        if ($p[1] !== '') {
          $args[] = $p[1];
        } elseif ($p[2] !== '') {
          $args[] = $p[2];
        } elseif ($p[3] !== '') {
          $args[] = str_contains($p[3], '.') ? (float) $p[3] : (int) $p[3];
        }
      }
      return $args;
    }
    if (is_numeric($raw)) {
      return [(float) $raw];
    }

    return [$raw];
  }

  /**
   * @return list<string>
   */
  private static function knownTemplateFunctionNames(): array
  {
    static $names = null;
    if ($names !== null) {
      return $names;
    }
    $names = [];
    if (function_exists('get_all_template_functions')) {
      foreach (array_keys(get_all_template_functions()) as $name) {
        $names[] = (string) $name;
      }
    }
    return $names;
  }

  private static function looksLikeTemplateFunction(string $name): bool
  {
    if (in_array($name, self::knownTemplateFunctionNames(), true)) {
      return true;
    }
    return (bool) preg_match('/^[a-z][a-z0-9_]*_[a-z0-9_]+$/i', $name);
  }

  public function render(string $html, array $data): string
  {
    $out = $this->processFromGetBlocks($html, $data);
    $out = $this->processTemplateFunctions($out, $data);
    $out = $this->processForLoops($out, $data);
    $out = $this->processIfBlocks($out, $data);
    $out = $this->processCountVariables($out, $data);
    $out = $this->processVariables($out, $data);

    return $out;
  }

  private function processTemplateFunctions(string $html, array $data): string
  {
    $known = array_fill_keys(self::knownTemplateFunctionNames(), true);
    foreach (array_keys($data) as $key) {
      if (str_starts_with((string) $key, '__tf__:')) {
        $parts = explode(':', (string) $key, 3);
        if (isset($parts[1])) {
          $known[$parts[1]] = true;
        }
      }
    }

    if (preg_match_all('/\{([a-zA-Z][a-zA-Z0-9_]*)\s+([^}]+)\}/', $html, $matches, PREG_SET_ORDER)) {
      foreach ($matches as $m) {
        $name = $m[1];
        if (in_array(strtolower($name), self::CONTROL_WORDS, true)) {
          continue;
        }
        $args = self::parseTemplateFunctionArgs($m[2]);
        $key = self::templateFunctionDataKey($name, $args);
        if (!isset($known[$name]) && !array_key_exists($key, $data) && !self::looksLikeTemplateFunction($name)) {
          continue;
        }
        $replacement = $this->formatTemplateFunctionOutput($data[$key] ?? null);
        $html = str_replace($m[0], $replacement, $html);
      }
    }

    if (preg_match_all('/\{([a-zA-Z][a-zA-Z0-9_]*)\}/', $html, $matches, PREG_SET_ORDER)) {
      foreach ($matches as $m) {
        $name = $m[1];
        if (in_array(strtolower($name), self::CONTROL_WORDS, true)) {
          continue;
        }
        $key = self::templateFunctionDataKey($name, []);
        if (!isset($known[$name]) && !array_key_exists($key, $data) && !array_key_exists($name, $data) && !self::looksLikeTemplateFunction($name)) {
          continue;
        }
        if (!array_key_exists($key, $data) && !array_key_exists($name, $data)) {
          continue;
        }
        $value = $data[$key] ?? $data[$name] ?? null;
        $replacement = $this->formatTemplateFunctionOutput($value);
        $html = str_replace($m[0], $replacement, $html);
      }
    }

    return $html;
  }

  private function formatTemplateFunctionOutput(mixed $value): string
  {
    if ($value === null) {
      return '';
    }
    if (is_scalar($value)) {
      return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
    if (is_array($value)) {
      return '';
    }

    return '';
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
      $header = $this->parseLoopModifiers($inClause);
      $items = $this->resolveLoopItems($header['inClause'] ?? $inClause, $data);
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
    $inClause = trim($inClause);

    if (preg_match("/^'([^']+)'\s+of\s+'([^']+)'\.'([^']+)'\s*$/i", $inClause, $m)) {
      return $this->resolveSubCollectionItems($m[1], $m[2], $m[3], $data);
    }
    if (preg_match("/^'([^']+)'\s+of\s+'([^']+)'\.\s*(current|newest|last-updated)\s*$/i", $inClause, $m)) {
      return $this->resolveSubCollectionItems($m[1], $m[2], $m[3], $data);
    }

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

    if (isset($data[$field]) && is_array($data[$field])) {
      return array_values($data[$field]);
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
   * @return list<array<string, mixed>>
   */
  private function resolveSubCollectionItems(
    string $subCollection,
    string $parentCollection,
    string $parentIdentifier,
    array $data
  ): array {
    $parent = $this->resolveParentCollectionEntry($parentCollection, $parentIdentifier, $data);
    if (!is_array($parent)) {
      return [];
    }

    $ids = array_filter([
      $parent['id'] ?? null,
      $parent['slug'] ?? null,
      $parentIdentifier,
    ], static fn($v) => $v !== null && $v !== '');

    foreach ($ids as $id) {
      $key = '__sub__:' . $parentCollection . ':' . $id . ':' . $subCollection;
      if (isset($data[$key]) && is_array($data[$key])) {
        return array_values($data[$key]);
      }
    }

    if (isset($data[$subCollection]) && is_array($data[$subCollection])) {
      $entry = $data['entry'] ?? null;
      if (is_array($entry)) {
        foreach ($ids as $id) {
          if ((string) ($entry['id'] ?? '') === (string) $id || (string) ($entry['slug'] ?? '') === (string) $id) {
            return array_values($data[$subCollection]);
          }
        }
      }
    }

    if (isset($parent[$subCollection]) && is_array($parent[$subCollection])) {
      return array_values($parent[$subCollection]);
    }

    return [];
  }

  private function resolveParentCollectionEntry(string $collection, string $identifier, array $data): ?array
  {
    if ($identifier === 'current') {
      if (isset($data['entry']) && is_array($data['entry'])) {
        return $data['entry'];
      }
      $prefix = '__entry__:' . $collection . ':';
      foreach ($data as $key => $val) {
        if (is_string($key) && str_starts_with($key, $prefix) && is_array($val)) {
          return $val;
        }
      }
      return null;
    }

    if ($identifier === 'newest' || $identifier === 'last-updated') {
      $entries = $data[$collection] ?? [];
      if (!is_array($entries) || $entries === []) {
        return null;
      }
      $field = $identifier === 'last-updated' ? 'updated_at' : 'created_at';
      usort($entries, static function ($a, $b) use ($field) {
        $da = is_array($a) ? strtotime((string) ($a[$field] ?? '')) : 0;
        $db = is_array($b) ? strtotime((string) ($b[$field] ?? '')) : 0;
        return $db <=> $da;
      });
      $first = $entries[0] ?? null;
      return is_array($first) ? $first : null;
    }

    return $this->resolveEntry($collection, $identifier, $data);
  }

  /**
   * @return array{collection:string,inClause:string,filter:?string,order:?string,limit:?int}
   */
  private function parseLoopModifiers(string $inClause): array
  {
    $result = ['collection' => '', 'inClause' => $inClause, 'filter' => null, 'order' => null, 'limit' => null];
    $rest = $inClause;

    if (preg_match('/\blimit\s+(\d+)\b/i', $rest, $lm)) {
      $result['limit'] = (int) $lm[1];
      $rest = preg_replace('/\blimit\s+\d+\b/i', '', $rest) ?? $rest;
    }
    if (preg_match('/\bordered\s+by\s+(newest|oldest|last-updated)\b/i', $rest, $om)) {
      $result['order'] = strtolower($om[1]);
      $rest = preg_replace('/\bordered\s+by\s+(newest|oldest|last-updated)\b/i', '', $rest) ?? $rest;
    }
    if (preg_match('/\bwith\s+(.+)$/is', trim($rest), $fm)) {
      $result['filter'] = trim($fm[1]);
      $rest = preg_replace('/\bwith\s+.+$/is', '', $rest) ?? $rest;
    }

    $rest = trim($rest);
    $result['inClause'] = $rest;
    if (preg_match("/^'([^']+)'/", $rest, $m)) {
      $result['collection'] = $m[1];
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

    if (preg_match("/^['\"]([^'\"]+)['\"]\s*(=|<=|=<|>=|=>|<|>)\s*([0-9]+(?:\.[0-9]+)?)$/", $filter, $m)) {
      $field = $m[1];
      $op = $this->normalizeOperator($m[2]);
      $num = (float) $m[3];
      return array_values(array_filter($items, function ($it) use ($field, $op, $num) {
        if (!is_array($it)) {
          return false;
        }
        $v = (float) ($it[$field] ?? NAN);
        if (is_nan($v)) {
          return false;
        }
        return $this->compareNumeric($v, $op, $num);
      }));
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
    $pattern = '/\{(if|elseif)\s+([^}]+)\}/i';
    while (preg_match($pattern, $html, $m, PREG_OFFSET_CAPTURE)) {
      $start = $m[0][1];
      $openLen = strlen($m[0][0]);
      $directive = strtolower($m[1][0]);
      $condition = trim($m[2][0]);
      $contentStart = $start + $openLen;

      [$closePos, $closeLen, $elsePos, $elseLen] = $this->findIfBlockEnd($html, $contentStart);
      if ($closePos === null) {
        break;
      }

      $trueBlock = $elsePos !== null
        ? substr($html, $contentStart, $elsePos - $contentStart)
        : substr($html, $contentStart, $closePos - $contentStart);
      $falseBlock = $elsePos !== null
        ? substr($html, $elsePos + $elseLen, $closePos - ($elsePos + $elseLen))
        : '';

      $condResult = $this->evaluateCondition($condition, $data);
      $replacement = $condResult ? $this->render($trueBlock, $data) : $this->render($falseBlock, $data);

      $html = substr($html, 0, $start) . $replacement . substr($html, $closePos + $closeLen);
    }

    return $html;
  }

  /**
   * @return array{0:?int,1:int,2:?int,3:int}
   */
  private function findIfBlockEnd(string $html, int $from): array
  {
    $pos = $from;
    $depth = 1;
    $elsePos = null;
    $elseLen = 0;

    while ($pos < strlen($html) && $depth > 0) {
      if (preg_match('/\{(if|elseif|else|endif)\}/i', $html, $m, PREG_OFFSET_CAPTURE, $pos)) {
        $tag = strtolower($m[1][0]);
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
        continue;
      }
      break;
    }

    return [null, 0, null, 0];
  }

  private function evaluateCondition(string $condition, array $data): bool
  {
    $cond = trim($condition);
    if ($cond === '') {
      return false;
    }

    if (preg_match('/^(.+?)\s+does\s+not\s+exist\s*$/i', $cond, $m)) {
      return !$this->evaluateExistence(trim($m[1]), $data);
    }

    if (preg_match('/^(.+?)\s+exist\s*$/i', $cond, $m)) {
      return $this->evaluateExistence(trim($m[1]), $data);
    }

    if (preg_match("/^entry\s+has\s+['\"]([^'\"]+)['\"]\s*(>=|<=|=<|=>|>|<|=|==|!=)\s*([0-9]+(?:\.[0-9]+)?)\s*$/i", $cond, $m)) {
      $entry = $data['entry'] ?? $data;
      if (!is_array($entry)) {
        return false;
      }
      $val = (float) ($entry[$m[1]] ?? NAN);
      if (is_nan($val)) {
        return false;
      }
      return $this->compareNumeric($val, $this->normalizeOperator($m[2]), (float) $m[3]);
    }

    if (preg_match("/^entry\s+has\s+['\"]([^'\"]+)['\"]\s*$/i", $cond, $m)) {
      $entry = $data['entry'] ?? $data;
      return is_array($entry) && $this->entryHasField($entry, $m[1]);
    }

    if (preg_match('/^entry\s+has\s+([a-zA-Z_][a-zA-Z0-9_]*)\s*$/i', $cond, $m)) {
      $entry = $data['entry'] ?? $data;
      return is_array($entry) && $this->entryHasField($entry, $m[1]);
    }

    return false;
  }

  private function evaluateExistence(string $ref, array $data): bool
  {
    $value = $this->resolveCollectionEntryRef($ref, $data);
    if ($value === null) {
      return false;
    }
    if (is_array($value)) {
      return $value !== [];
    }

    return $value !== '' && $value !== false;
  }

  private function resolveCollectionEntryRef(string $ref, array $data): mixed
  {
    $ref = trim($ref);
    if (preg_match("/^'([^']+)'\.'([^']+)'$/", $ref, $m)) {
      return $this->resolveParentCollectionEntry($m[1], $m[2], $data);
    }
    if (preg_match("/^'([^']+)'\.'(current|newest|last-updated)'$/", $ref, $m)) {
      return $this->resolveParentCollectionEntry($m[1], $m[2], $data);
    }

    return null;
  }

  private function normalizeOperator(string $op): string
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

  private function compareNumeric(float $left, string $op, float $right): bool
  {
    return match ($op) {
      '<' => $left < $right,
      '>' => $left > $right,
      '<=' => $left <= $right,
      '>=' => $left >= $right,
      '=', '==' => abs($left - $right) < 0.00001,
      '!=' => abs($left - $right) >= 0.00001,
      default => false,
    };
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

  private function processCountVariables(string $html, array $data): string
  {
    return preg_replace_callback(
      '/\{\s*count\s+of\s+([^}]+)\}/i',
      function (array $m) use ($data) {
        $clause = trim($m[1]);
        if (preg_match("/^'([^']+)'\s+of\s+'([^']+)'\.'([^']+)'\s*$/i", $clause, $parts)) {
          $items = $this->resolveSubCollectionItems($parts[1], $parts[2], $parts[3], $data);
        } elseif (preg_match("/^'([^']+)'\s*$/", $clause, $parts)) {
          $items = $this->resolveLoopItems("'" . $parts[1] . "'", $data);
        } else {
          $items = $this->resolveLoopItems($clause, $data);
        }

        return (string) count($items);
      },
      $html
    ) ?? $html;
  }

  private function processVariables(string $html, array $data): string
  {
    return preg_replace_callback(
      '/\{([^}]+)\}/',
      function (array $m) use ($data) {
        $inner = trim($m[1]);
        if ($inner === '' || preg_match('/^(if|else|elseif|endif|for|endfor|from|get|endget|end|set|count)\b/i', $inner)) {
          return '';
        }

        if (preg_match('/^count\s+of\b/i', $inner)) {
          return $m[0];
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
