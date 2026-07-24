<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/core/client_site_html.php';

function assert_true(string $label, bool $condition): void
{
    if (!$condition) {
        fwrite(STDERR, "FAIL: $label\n");
        exit(1);
    }
    echo "OK: $label\n";
}

$html = <<<'HTML'
<!DOCTYPE html>
<html><head><title>Test</title></head>
<body><main>{for each entry in 'blog'}{ title }{endfor}</main>
<script src="/birkly-client.js"></script></body></html>
HTML;

$booted = birkly_bootstrap_client_site_html($html);
assert_true('injects init script', str_contains($booted, 'birkly-client-init.js'));
assert_true('injects after head', str_contains($booted, '<head><script src='));

$again = birkly_bootstrap_client_site_html($booted);
assert_true('does not double inject', substr_count($again, 'birkly-client-init.js') === 1);

$static = '<html><head></head><body><p>Hello</p></body></html>';
assert_true('skips static html', $static === birkly_bootstrap_client_site_html($static));

echo "client_site_html_test: all passed\n";
