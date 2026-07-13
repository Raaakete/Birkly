<?php
/**
 * Transform one HTML file from baked to live client path (used by migrate-kekaba-live-path.sh).
 */
if ($argc < 2) {
    fwrite(STDERR, "usage: php migrate-kekaba-live-path-transform.php <html-file>\n");
    exit(1);
}

$path = $argv[1];
if (!is_file($path)) {
    exit(0);
}

$html = (string) file_get_contents($path);
$html = preg_replace('/<meta\s+name=["\']birkly-baked["\']\s+content=["\'][^"\']*["\']\s*\/?>\s*/i', '', $html);
$html = preg_replace('/<!--\s*birkly-baked-at:[^>]*-->\s*/i', '', $html);
$html = preg_replace(
    '/<script>if\(!document\.querySelector\([\'"]meta\[name=\\?[\'"]birkly-baked\\?[\'"]\][\'"]\)\)document\.documentElement\.classList\.add\([\'"]birkly-loading[\'"]\);<\/script>\s*/',
    '',
    $html
);
$html = str_replace('birkly-hydrate.js', 'Birkly-0.1/birkly-client.js', $html);
if (strpos($html, 'birkly-client.js') === false && strpos($html, '</body>') !== false) {
    $tag = "<script src=\"Birkly-0.1/birkly-client.js\"></script>\n";
    $html = str_replace('</body>', $tag . '</body>', $html);
}
file_put_contents($path, $html);
