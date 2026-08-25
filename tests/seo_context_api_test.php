<?php
/**
 * SEO public context API syntax (P68-M7).
 */

declare(strict_types=1);

$root = dirname(__DIR__);
exec('php -l ' . escapeshellarg($root . '/api/public/seo-context.php') . ' 2>&1', $out, $code);
echo ($code === 0 ? "OK  seo-context.php syntax\n" : "FAIL seo-context.php syntax\n" . implode("\n", $out) . "\n");
exit($code === 0 ? 0 : 1);
