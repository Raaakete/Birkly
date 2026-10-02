<?php
/**
 * Windows start-server.ps1 and portable php.ini tuning (stderr / OPcache regressions).
 */
declare(strict_types=1);

$root = dirname(__DIR__);
$failures = 0;
$assert = static function (bool $cond, string $msg) use (&$failures): void {
    if ($cond) {
        echo "ok - {$msg}\n";
        return;
    }
    echo "FAIL - {$msg}\n";
    $failures++;
};

$startPs1 = (string) file_get_contents($root . '/start-server.ps1');
$commonPs1 = (string) file_get_contents($root . '/scripts/windows-php-common.ps1');

$assert(
    str_contains($startPs1, "ErrorActionPreference = 'Continue'"),
    'start-server.ps1 relaxes ErrorActionPreference while PHP runs (stderr startup line)'
);
$assert(
    str_contains($startPs1, '>> $log 2>&1'),
    'start-server.ps1 merges PHP stdout/stderr into server.log like start-server.sh'
);
$assert(
    str_contains($startPs1, 'opcache.enable_cli=0'),
    'start-server.ps1 disables OPcache for CLI built-in server'
);
$assert(
    str_contains($startPs1, 'max_input_time=600'),
    'start-server.ps1 raises max_input_time for large uploads'
);
$assert(
    str_contains($startPs1, 'Repair-BirklyPhpIni'),
    'start-server.ps1 repairs portable php.ini before launch'
);
$assert(
    !str_contains($startPs1, '*>> $log'),
    'start-server.ps1 does not use *>> (triggers NativeCommandError on PHP stderr)'
);

$assert(
    !preg_match('/opcache\.revalidate_freq\s*=\s*0/', $commonPs1),
    'windows-php-common.ps1 does not force opcache revalidate_freq 0'
);
$assert(
    !preg_match('/zend_extension\s*=\s*php_opcache\.dll/', $commonPs1),
    'windows-php-common.ps1 does not auto-enable OPcache zend_extension'
);
$assert(
    str_contains($commonPs1, 'opcache.enable_cli = 0'),
    'windows-php-common.ps1 keeps OPcache off for local CLI server'
);

exit($failures === 0 ? 0 : 1);
