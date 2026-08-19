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
