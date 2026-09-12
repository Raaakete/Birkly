#!/usr/bin/env php
<?php
/**
 * P85-M4 — Admin i18n locale audit.
 *
 * Usage:
 *   php scripts/i18n-audit.php [--locale=de] [--strict] [--check-untranslated] [--warn-only]
 *
 * Exit 0 when clean; 1 when strict violations found.
 */
declare(strict_types=1);

$root = dirname(__DIR__);
$options = getopt('', ['locale:', 'strict', 'warn-only', 'help']);

if (isset($options['help'])) {
    fwrite(STDOUT, "Usage: php scripts/i18n-audit.php [--locale=de] [--strict] [--warn-only]\n");
    exit(0);
}

$locale = isset($options['locale']) ? (string) $options['locale'] : 'de';
$strict = array_key_exists('strict', $options);
$checkUntranslated = array_key_exists('check-untranslated', $options);
$warnOnly = array_key_exists('warn-only', $options);

$enFile = $root . '/lang/en.json';
$localeFile = $root . '/lang/' . $locale . '.json';

if (!is_file($enFile)) {
    fwrite(STDERR, "Missing canonical file: lang/en.json\n");
    exit(1);
}

$enData = json_decode((string) file_get_contents($enFile), true);
if (!is_array($enData) || !is_array($enData['admin'] ?? null)) {
    fwrite(STDERR, "Invalid lang/en.json — expected admin object\n");
    exit(1);
}

$enAdmin = $enData['admin'];
$localeAdmin = [];

if (is_file($localeFile)) {
    $localeData = json_decode((string) file_get_contents($localeFile), true);
    if (is_array($localeData['admin'] ?? null)) {
        $localeAdmin = $localeData['admin'];
    }
}

$missing = array_diff_key($enAdmin, $localeAdmin);
$identical = [];
$identicalQuality = [];
$allowIdentical = ['birkly', 'hub', 'mcp', 'api', 'url', 'csv', 'json', 'html', 'css', 'js', 'stripe', 'github'];
$keepLatinValues = ['api', 'csv', 'json', 'html', 'css', 'js', 'url', 'stripe', 'github', 'mcp', 'birkly', 'hub'];

foreach ($localeAdmin as $key => $value) {
    if (!isset($enAdmin[$key]) || !is_string($value) || !is_string($enAdmin[$key])) {
        continue;
    }
    if ($value !== $enAdmin[$key] || strlen($value) <= 2) {
        continue;
    }
    $valLower = strtolower(trim($value));
    if (in_array($valLower, $keepLatinValues, true)) {
        continue;
    }
    if (strlen($value) <= 5 && $value === strtoupper($value)) {
        continue;
    }
    $identicalQuality[] = $key;

    $lower = strtolower($key . ' ' . $value);
    $skip = false;
    foreach ($allowIdentical as $token) {
        if (str_contains($lower, $token)) {
            $skip = true;
            break;
        }
    }
    if (!$skip) {
        $identical[] = $key;
    }
}

$errors = [];
$warnings = [];

if ($missing !== []) {
    $msg = count($missing) . ' missing admin.* keys in lang/' . $locale . '.json';
    if ($strict && !$warnOnly) {
        $errors[] = $msg;
    } else {
        $warnings[] = $msg;
    }
}

if ($identical !== [] && $checkUntranslated) {
    $msg = count($identical) . ' admin.* values identical to English (legacy substring filter)';
    if ($warnOnly) {
        $warnings[] = $msg;
    } else {
        $errors[] = $msg;
    }
}

if ($identicalQuality !== [] && $checkUntranslated) {
    $msg = count($identicalQuality) . ' admin.* values identical to English (translation quality)';
    if ($warnOnly) {
        $warnings[] = $msg;
    } else {
        $errors[] = $msg;
    }
}

fwrite(STDOUT, "i18n audit — locale={$locale} strict=" . ($strict ? 'yes' : 'no') . "\n");
fwrite(STDOUT, '  en admin keys: ' . count($enAdmin) . "\n");
fwrite(STDOUT, '  locale admin keys: ' . count($localeAdmin) . "\n");
fwrite(STDOUT, '  missing: ' . count($missing) . "\n");
fwrite(STDOUT, '  identical-to-en (de strict): ' . count($identical) . "\n");
fwrite(STDOUT, '  identical-to-en (quality): ' . count($identicalQuality) . "\n");

if ($warnings !== []) {
    foreach ($warnings as $warning) {
        fwrite(STDOUT, "WARN: {$warning}\n");
    }
}

if ($errors !== []) {
    foreach ($errors as $error) {
        fwrite(STDERR, "ERROR: {$error}\n");
    }
    if ($strict && count($missing) > 0 && count($missing) <= 15) {
        fwrite(STDERR, 'Missing keys sample: ' . implode(', ', array_slice(array_keys($missing), 0, 15)) . "\n");
    }
    exit(1);
}

fwrite(STDOUT, "OK: audit passed\n");
exit(0);
