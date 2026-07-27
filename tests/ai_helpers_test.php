<?php

declare(strict_types=1);

require_once __DIR__ . '/../api/ai_helpers.php';

function assert_true(bool $condition, string $message): void
{
    if (!$condition) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
}

assert_true(
    birkly_ai_resolve_model('google', 'gemini-1.5-flash') === 'gemini-2.5-flash',
    'legacy gemini-1.5-flash maps to gemini-2.5-flash'
);

assert_true(
    birkly_ai_default_model('google') === 'gemini-2.5-flash',
    'google default model is gemini-2.5-flash'
);

$providers = birkly_ai_providers_for_client();
assert_true(
    !empty($providers['openrouter']['dynamic_models']),
    'openrouter exposes dynamic_models to clients'
);

assert_true(
    !isset($providers['openrouter']['models']),
    'openrouter client metadata omits static model list'
);

$providersFull = getSupportedAiProviders();
assert_true(
    in_array('gemini-2.5-flash', $providersFull['google']['models'] ?? []) === false,
    'google provider config has no static models array'
);

echo "ai_helpers_test: OK\n";
