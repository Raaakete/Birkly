<?php
declare(strict_types=1);

/**
 * Commerce admin EN→ES exact value overrides (tier-1 Spanish finish).
 * Loaded by p85-es-commerce-from-en.php after phrase-based pass.
 */
function p85_es_commerce_value_map(): array
{
    static $map = null;
    if ($map !== null) {
        return $map;
    }
    $file = __DIR__ . '/data/p85-commerce-es-values.json';
    if (!is_file($file)) {
        return [];
    }
    $decoded = json_decode((string) file_get_contents($file), true);
    $map = is_array($decoded) ? $decoded : [];

    return $map;
}
