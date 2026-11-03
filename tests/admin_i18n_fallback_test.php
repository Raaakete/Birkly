<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/core/i18n.php';

$failures = 0;
$assert = static function (bool $ok, string $msg) use (&$failures): void {
    echo ($ok ? "ok" : "FAIL") . " - {$msg}\n";
    if (!$ok) {
        $failures++;
    }
};

$assert(
    birkly_translate_with_fallback('admin.totally_missing_test_key_xyz', 'Fallback label') === 'Fallback label',
    'missing key uses fallback'
);
$assert(
    birkly_translate_with_fallback('admin.totally_missing_test_key_xyz', 'Fallback label') !== 'admin.totally_missing_test_key_xyz',
    'fallback is not raw key'
);

exit($failures > 0 ? 1 : 0);
