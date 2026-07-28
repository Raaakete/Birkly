<?php
/**
 * Entry slug helpers for public API and AI create_entry.
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/core/entry_slug.php';

$failures = 0;

function assert_true(string $name, bool $ok, string $detail = ''): void
{
    global $failures;
    echo $name . ': ' . ($ok ? "PASS\n" : "FAIL\n");
    if (!$ok) {
        $failures++;
        if ($detail !== '') {
            echo '  ' . $detail . "\n";
        }
    }
}

assert_true(
    'effective slug from title',
    birkly_entry_effective_slug(['title' => 'Welcome to the blog']) === 'welcome-to-the-blog'
);

assert_true(
    'matches stored slug',
    birkly_entry_matches_slug(['slug' => 'hello-world', 'title' => 'Hello'], 'hello-world')
);

assert_true(
    'matches generated slug when stored empty',
    birkly_entry_matches_slug(['title' => 'Designing for clarity'], 'designing-for-clarity')
);

$withSlug = birkly_public_entry_with_effective_slug(['title' => 'Shipping small, shipping often']);
assert_true(
    'public entry gets slug field',
    ($withSlug['slug'] ?? '') === 'shipping-small-shipping-often'
);

assert_true(
    'resolve create slug from title',
    birkly_resolve_entry_slug('create', ['title' => 'My Post'], null, 'My Post', 'abc123') === 'my-post'
);

exit($failures > 0 ? 1 : 0);
