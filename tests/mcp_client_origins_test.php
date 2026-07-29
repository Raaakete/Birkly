<?php
/**
 * MCP request origin policy — any HTTPS MCP/OAuth client, not a vendor allowlist.
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/core/mcp_protocol.php';

$cases = [
    ['origin' => '', 'expect' => true, 'label' => 'empty (native clients)'],
    ['origin' => 'https://www.cursor.com', 'expect' => true, 'label' => 'cursor'],
    ['origin' => 'https://claude.ai', 'expect' => true, 'label' => 'claude'],
    ['origin' => 'https://future-ai-vendor.example', 'expect' => true, 'label' => 'unknown https vendor'],
    ['origin' => 'http://localhost:8787', 'expect' => true, 'label' => 'localhost dev'],
    ['origin' => 'http://evil.example', 'expect' => false, 'label' => 'remote http'],
    ['origin' => 'not-a-url', 'expect' => false, 'label' => 'malformed'],
];

$_SERVER['HTTP_HOST'] = 'cms.birkly.cloud';
$_SERVER['HTTPS'] = 'on';

foreach ($cases as $case) {
    $_SERVER['HTTP_ORIGIN'] = $case['origin'];
    $ok = birkly_mcp_validate_origin();
    if ($ok !== $case['expect']) {
        echo "FAIL: {$case['label']} expected " . ($case['expect'] ? 'allow' : 'deny') . " got " . ($ok ? 'allow' : 'deny') . "\n";
        exit(1);
    }
}

$_SERVER['HTTP_ORIGIN'] = 'https://cms.birkly.cloud';
if (!birkly_mcp_validate_origin()) {
    echo "FAIL: same-host origin should be allowed\n";
    exit(1);
}

echo "PASS: mcp_client_origins_test\n";
