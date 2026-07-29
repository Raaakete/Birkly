<?php
/**
 * JSON-RPC response shape — Cursor cloud MCP rejects both result and error keys.
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/core/mcp_protocol.php';

$ok = birkly_mcp_build_jsonrpc_response(['id' => 1], ['protocolVersion' => '2025-03-26'], null);
if (!isset($ok['result']) || isset($ok['error'])) {
    echo "FAIL: success response must have result only\n";
    exit(1);
}

$err = birkly_mcp_build_jsonrpc_response(['id' => 2], null, ['code' => -32000, 'message' => 'Authentication required']);
if (!isset($err['error']) || isset($err['result'])) {
    echo "FAIL: error response must have error only\n";
    exit(1);
}

echo "PASS: mcp_jsonrpc_response_test\n";
