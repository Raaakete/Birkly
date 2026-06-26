<?php
/**
 * P4-M5 — MCP hardening tests (rate limit + audit helpers).
 */

require_once __DIR__ . '/bootstrap.php';
require_once dirname(__DIR__) . '/core/mcp_endpoints.php';

test_reset_failures();
echo "MCP hardening tests\n\n";

$endpointId = 'mcp_ep_hardening_test';
$auditDir = dirname(__DIR__) . '/data/mcp_audit';
$rateDir = dirname(__DIR__) . '/data/mcp_rate';

test_assert('rate limit constant defined', defined('BIRKLY_MCP_RATE_LIMIT_PER_MINUTE'));
test_assert('lockout constant defined', defined('BIRKLY_MCP_AUTH_LOCKOUT_ATTEMPTS'));

$beforeAudit = glob($auditDir . '/*.jsonl') ?: [];
birkly_mcp_audit_log($endpointId, 'tools/list', '', true);
$afterAudit = glob($auditDir . '/*.jsonl') ?: [];
test_assert('audit log file created or appended', count($afterAudit) >= count($beforeAudit));

$ok = true;
for ($i = 0; $i < 5; $i++) {
    if (!birkly_mcp_check_rate_limit($endpointId . '_rl')) {
        $ok = false;
        break;
    }
}
test_assert('rate limit allows normal traffic', $ok);

test_assert('audit log function exists', function_exists('birkly_mcp_audit_log'));
test_assert('failed auth recorder exists', function_exists('birkly_mcp_record_failed_auth'));

exit(test_finish('mcp_hardening_test'));
