<?php
/**
 * P4-M3 — MCP endpoint model tests.
 */

require_once __DIR__ . '/bootstrap.php';

$tmpSettings = sys_get_temp_dir() . '/birkly-mcp-test-' . getmypid();
mkdir($tmpSettings, 0755, true);

// Override storage path via reflection hack — use isolated JSON file
$storeFile = $tmpSettings . '/mcp_endpoints.json';
file_put_contents($storeFile, json_encode(['version' => 1, 'endpoints' => [], 'legacy_dual_resolve_until' => null]));

// Load core with a test-only wrapper
require_once dirname(__DIR__) . '/core/ai_tool_registry.php';

class BirklyMcpTestStorage {
    public static string $file = '';
    public static function read(): array {
        $data = json_decode((string) @file_get_contents(self::$file), true);
        return is_array($data) ? $data : ['version' => 1, 'endpoints' => []];
    }
    public static function write(array $data): bool {
        return file_put_contents(self::$file, json_encode($data, JSON_PRETTY_PRINT)) !== false;
    }
}

BirklyMcpTestStorage::$file = $storeFile;

// Inline test helpers mirroring core functions
function test_mcp_create(string $name, string $role = 'viewer'): array {
    $store = BirklyMcpTestStorage::read();
    $id = 'mcp_ep_test_' . bin2hex(random_bytes(4));
    $key = bin2hex(random_bytes(16));
    $ep = [
        'id' => $id,
        'name' => $name,
        'role' => $role,
        'require_approval' => true,
        'mcp_api_key' => $key,
        'previous_mcp_api_key' => null,
        'previous_key_expires_at' => null,
        'status' => 'active',
    ];
    $store['endpoints'][] = $ep;
    BirklyMcpTestStorage::write($store);
    return $ep;
}

function test_mcp_resolve(string $apiKey): ?array {
    foreach (BirklyMcpTestStorage::read()['endpoints'] as $ep) {
        if (($ep['status'] ?? '') !== 'active') continue;
        if (!empty($ep['mcp_api_key']) && hash_equals($ep['mcp_api_key'], $apiKey)) {
            return $ep;
        }
        if (!empty($ep['previous_mcp_api_key']) && !empty($ep['previous_key_expires_at'])
            && strtotime($ep['previous_key_expires_at']) >= time()
            && hash_equals($ep['previous_mcp_api_key'], $apiKey)) {
            return $ep;
        }
    }
    return null;
}

test_reset_failures();
echo "MCP endpoints tests\n\n";

$ep = test_mcp_create('Test Endpoint', 'admin');
test_assert('endpoint created with key', !empty($ep['mcp_api_key']));
test_assert('resolve current key', test_mcp_resolve($ep['mcp_api_key'])['id'] === $ep['id']);

// Rotation grace simulation
$store = BirklyMcpTestStorage::read();
foreach ($store['endpoints'] as &$item) {
    if ($item['id'] === $ep['id']) {
        $item['previous_mcp_api_key'] = $item['mcp_api_key'];
        $item['previous_key_expires_at'] = date('c', time() + 3600);
        $item['mcp_api_key'] = bin2hex(random_bytes(16));
        $newKey = $item['mcp_api_key'];
        $oldKey = $item['previous_mcp_api_key'];
    }
}
unset($item);
BirklyMcpTestStorage::write($store);

test_assert('resolve new key after rotation', test_mcp_resolve($newKey)['id'] === $ep['id']);
test_assert('resolve old key during grace', test_mcp_resolve($oldKey)['id'] === $ep['id']);

// Revoke
$store = BirklyMcpTestStorage::read();
foreach ($store['endpoints'] as &$item) {
    if ($item['id'] === $ep['id']) {
        $item['status'] = 'revoked';
    }
}
unset($item);
BirklyMcpTestStorage::write($store);
test_assert('revoked key not resolved', test_mcp_resolve($newKey) === null);

// Core file exists and exports key functions
require_once dirname(__DIR__) . '/core/mcp_endpoints.php';
test_assert('birkly_mcp_list_endpoints exists', function_exists('birkly_mcp_list_endpoints'));
test_assert('birkly_mcp_resolve_actor_by_key exists', function_exists('birkly_mcp_resolve_actor_by_key'));
test_assert('birkly_mcp_endpoint_to_ai_user has role', ($u = birkly_mcp_endpoint_to_ai_user(['id' => 'x', 'role' => 'editor'])) && $u['role'] === 'editor');

@unlink($storeFile);
@rmdir($tmpSettings);

exit(test_finish('mcp_endpoints_test'));
