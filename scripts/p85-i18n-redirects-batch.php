<?php
/**
 * P85 — Settings redirects panel (EN/DE).
 * Usage: php scripts/p85-i18n-redirects-batch.php
 */
declare(strict_types=1);

$root = dirname(__DIR__);
$dataDir = __DIR__;

$newEn = json_decode((string) file_get_contents($dataDir . '/p85-i18n-redirects-en.data.json'), true);
$newDe = json_decode((string) file_get_contents($dataDir . '/p85-i18n-redirects-de.data.json'), true);

if (!is_array($newEn) || !is_array($newDe)) {
    fwrite(STDERR, "Missing or invalid EN/DE data JSON in scripts/\n");
    exit(1);
}

foreach (['en.json', 'de.json'] as $file) {
    $path = $root . '/lang/' . $file;
    $data = json_decode((string) file_get_contents($path), true);
    if (!is_array($data['admin'] ?? null)) {
        fwrite(STDERR, "Invalid {$file}\n");
        exit(1);
    }
    $batch = $file === 'en.json' ? $newEn : $newDe;
    foreach ($batch as $key => $value) {
        $data['admin'][$key] = $value;
    }
    ksort($data['admin']);
    $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n";
    file_put_contents($path, $json);
    fwrite(STDOUT, "Updated lang/{$file} (" . count($batch) . " keys)\n");
}
