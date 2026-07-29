<?php
/**
 * MCP client origin allowlist — Cursor, Claude, ChatGPT.
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/core/mcp_protocol.php';

$allowed = birkly_mcp_allowed_client_origins();

$required = [
    'https://claude.ai',
    'https://chatgpt.com',
    'https://cursor.com',
    'https://www.cursor.com',
];

foreach ($required as $origin) {
    if (!in_array($origin, $allowed, true)) {
        echo "FAIL: missing allowed origin {$origin}\n";
        exit(1);
    }
}

$_SERVER['HTTP_ORIGIN'] = 'https://www.cursor.com';
$_SERVER['HTTP_HOST'] = 'cms.birkly.cloud';
$_SERVER['HTTPS'] = 'on';

if (!birkly_mcp_validate_origin()) {
    echo "FAIL: www.cursor.com origin should be allowed\n";
    exit(1);
}

$_SERVER['HTTP_ORIGIN'] = 'https://evil.example';
if (birkly_mcp_validate_origin()) {
    echo "FAIL: evil.example origin should be rejected\n";
    exit(1);
}

echo "PASS: mcp_client_origins_test\n";
