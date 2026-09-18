<?php
/**
 * Regression: preg_replace must not interpret $ in field values as backreferences.
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/core/entry_multilang.php';

$html = '<input id="field_en_snippet_value" value="old" />';
$updated = birkly_entry_multilang_apply_field_value($html, 'snippet', 'en', 'price is $1 and ${name}');

if (!str_contains($updated, '$1') || !str_contains($updated, '${name}')) {
    fwrite(STDERR, "FAIL: dollar signs mangled: $updated\n");
    exit(1);
}

echo "entry_multilang_dollar_test: PASS\n";
