<?php
/**
 * PHP runtime requirements helper (extensions list for local / Windows portable PHP).
 */

declare(strict_types=1);

require_once __DIR__ . '/../core/php_runtime_requirements.php';

$failed = 0;

function assert_true(string $label, bool $condition): void
{
    global $failed;
    if (!$condition) {
        echo "FAIL: $label\n";
        $failed++;
        return;
    }
    echo "OK: $label\n";
}

$required = birkly_php_required_extensions();
assert_true('includes zip', in_array('zip', $required, true));
assert_true('includes gd', in_array('gd', $required, true));
assert_true('includes curl', in_array('curl', $required, true));

$status = birkly_php_extension_status();
assert_true('status keys match required', array_keys($status) === $required);

exit($failed > 0 ? 1 : 0);
