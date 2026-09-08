<?php
/**
 * Nested {if condition} block parsing (SSR + client parity).
 *
 * Run: php tests/template_nested_if_test.php
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/core/birkly_template_engine.php';

$failures = 0;
$total = 0;

function assert_true(string $name, bool $ok, string $detail = ''): void
{
    global $failures, $total;
    $total++;
    echo '  ' . $name . ': ' . ($ok ? "PASS\n" : "FAIL\n");
    if (!$ok) {
        $failures++;
        if ($detail !== '') {
            echo '    ' . $detail . "\n";
        }
    }
}

echo "\n[nested-if] Nested conditional blocks\n";

$clubGate = '{if user_logged_in}<main class="member">MEMBER{if user_logged_in}<p class="inner">INNER</p>{endif}TAIL</main>{else}<p class="guest">GUEST</p>{endif}';

$member = birkly_template_engine_render($clubGate, ['user_logged_in' => true]);
assert_true(
    'member branch keeps nested true content without leaking guest block',
    str_contains($member, 'MEMBER') && str_contains($member, 'INNER') && str_contains($member, 'TAIL') && !str_contains($member, 'GUEST'),
    $member
);

$guest = birkly_template_engine_render($clubGate, ['user_logged_in' => false]);
assert_true(
    'guest branch when logged out',
    str_contains($guest, 'GUEST') && !str_contains($guest, 'MEMBER') && !str_contains($guest, 'INNER'),
    $guest
);

$triple = birkly_template_engine_render(
    '{if user_logged_in}L1{if user_logged_in}L2{if user_logged_in}L3{endif}L2E{endif}L1E{else}OUT{endif}',
    ['user_logged_in' => true]
);
assert_true('triple nested depth resolves correctly', $triple === 'L1L2L3L2EL1E', $triple);

echo "\n" . ($failures === 0 ? "All {$total} assertions passed.\n" : "{$failures} of {$total} assertions failed.\n");
exit($failures === 0 ? 0 : 1);
