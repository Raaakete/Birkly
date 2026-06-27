<?php
/**
 * Test router — simulates LiteSpeed stripping /Birkly-main from SCRIPT_NAME and REQUEST_URI.
 */

$docRoot = __DIR__ . '/docroot';
$uri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
if (!is_string($uri)) {
    return false;
}

if (preg_match('#^/Birkly-main/((?:admin|api)/.+)$#', $uri, $m)) {
    $_SERVER['REDIRECT_URL'] = $uri;
    $internal = '/' . $m[1];
    $_SERVER['SCRIPT_NAME'] = $internal;
    $query = $_SERVER['QUERY_STRING'] ?? '';
    $_SERVER['REQUEST_URI'] = $internal . ($query !== '' ? '?' . $query : '');

    $file = $docRoot . '/Birkly-main/' . $m[1];
    if (is_file($file)) {
        chdir(dirname($file));
        require $file;
        return true;
    }
}

$file = $docRoot . $uri;
if ($uri !== '/' && is_file($file)) {
    return false;
}

http_response_code(404);
echo 'Not found';
return true;
