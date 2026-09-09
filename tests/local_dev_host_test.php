<?php
/**
 * Local dev host detection — LAN and Docker hosts used during local installs.
 */

declare(strict_types=1);

$root = dirname(__DIR__);
require_once $root . '/core/birkly_paths.php';

$errors = [];

if (!birkly_is_private_lan_host('192.168.1.42')) {
    $errors[] = '192.168.x.x should be treated as private LAN';
}
if (!birkly_is_private_lan_host('10.0.0.5')) {
    $errors[] = '10.x.x.x should be treated as private LAN';
}
if (!birkly_is_private_lan_host('172.16.0.2')) {
    $errors[] = '172.16.x.x should be treated as private LAN';
}
if (birkly_is_private_lan_host('8.8.8.8')) {
    $errors[] = 'Public IPv4 should not be treated as private LAN';
}
if (!birkly_is_private_lan_host('host.docker.internal')) {
    $errors[] = 'host.docker.internal should be treated as local dev host';
}

$_SERVER['HTTP_HOST'] = '192.168.0.12:8080';
if (!birkly_is_local_dev_request()) {
    $errors[] = 'LAN HTTP_HOST should count as local dev request';
}
unset($_SERVER['HTTP_HOST']);

if ($errors !== []) {
    fwrite(STDERR, implode(PHP_EOL, $errors) . PHP_EOL);
    exit(1);
}

echo "local_dev_host_test: OK\n";
