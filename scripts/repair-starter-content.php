#!/usr/bin/env php
<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/core/starter_content_repair.php';

$result = birkly_run_starter_content_repairs();
if (!empty($result['repaired'])) {
    echo 'Repaired: ' . implode(', ', $result['repaired']) . PHP_EOL;
}
if (!empty($result['errors'])) {
    fwrite(STDERR, 'Errors: ' . implode(', ', $result['errors']) . PHP_EOL);
    exit(1);
}
