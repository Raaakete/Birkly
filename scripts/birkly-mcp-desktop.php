#!/usr/bin/env php
<?php
/**
 * Birkly Desktop MCP connector — stdio (Claude Desktop / Cursor) to local Streamable HTTP /mcp.
 *
 * Usage:
 *   php scripts/birkly-mcp-desktop.php --token-file=/path/to/credentials.json
 *
 * Pair credentials from Admin → Settings → AI → Connect desktop app.
 */

declare(strict_types=1);

$root = dirname(__DIR__);
require_once $root . '/core/mcp_desktop.php';

$tokenFile = null;
for ($i = 1; $i < count($argv); $i++) {
    $arg = $argv[$i];
    if ($arg === '--token-file' && isset($argv[$i + 1])) {
        $tokenFile = $argv[++$i];
        continue;
    }
    if (str_starts_with($arg, '--token-file=')) {
        $tokenFile = substr($arg, strlen('--token-file='));
    }
}

exit(birkly_mcp_desktop_stdio_loop($tokenFile));
