<?php
/**
 * Default model resolution for outbound BYOAI chat.
 */

require_once __DIR__ . '/bootstrap.php';
require_once dirname(__DIR__) . '/api/ai_helpers.php';

test_reset_failures();
echo "AI model resolve tests\n\n";

test_assert(
    'openai default model is current',
    birkly_ai_default_model('openai') === 'gpt-4o-mini'
);

test_assert(
    'empty openai model resolves to default',
    birkly_ai_resolve_model('openai', '') === 'gpt-4o-mini'
);

test_assert(
    'legacy gpt-4 maps to gpt-4o',
    birkly_ai_resolve_model('openai', 'gpt-4') === 'gpt-4o'
);

test_assert(
    'explicit model is preserved',
    birkly_ai_resolve_model('openai', 'gpt-4o-mini') === 'gpt-4o-mini'
);

test_assert(
    'custom provider keeps empty model',
    birkly_ai_resolve_model('custom', '') === ''
);

test_assert(
    'provider error body is parsed',
    birkly_ai_parse_provider_error(json_encode(['error' => ['message' => 'model not found']])) === 'model not found'
);

exit(test_finish('ai_model_resolve_test'));
