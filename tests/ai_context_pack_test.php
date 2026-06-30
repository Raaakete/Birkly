<?php
/**
 * P3-M3: Curated AI context pack loader tests.
 */

require_once __DIR__ . '/bootstrap.php';
require_once dirname(__DIR__) . '/api/ai_helpers.php';

test_reset_failures();
echo "AI context pack tests\n\n";

$pack = loadBirklyContextPack();
test_assert('context pack file loads', $pack !== '');
test_assert('context pack mentions conditionals', stripos($pack, '{if') !== false || stripos($pack, 'Conditionals') !== false);
test_assert('context pack mentions single entry', stripos($pack, '{entry') !== false || stripos($pack, 'Single entry') !== false);

$scrubbed = birkly_scrub_user_for_ai([
    'id' => 'ai_1',
    'password' => 'secret',
    'api_key_encrypted' => 'enc',
    'mcp_api_key' => 'mcp-key',
]);
test_assert('scrub removes secrets', !isset($scrubbed['password'], $scrubbed['api_key_encrypted'], $scrubbed['mcp_api_key']));

$context = generateSystemContext(['role' => 'viewer']);
test_assert('context has pack not doc dump', !empty($context['context_pack']) && !isset($context['documentation']));

exit(test_finish('ai_context_pack_test'));
