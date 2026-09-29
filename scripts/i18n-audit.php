#!/usr/bin/env php
<?php
/**
 * Admin UI locale audit (P103).
 *
 *   php scripts/i18n-audit.php --selectable
 *   php scripts/i18n-audit.php --selectable --strict-quality
 *   php scripts/i18n-audit.php --locale=de --strict
 *
 * --selectable checks every lang file with _meta.selectable, plus official
 * plugin catalogs when the sibling repo is present. Missing keys, placeholder
 * mismatches, and translation sentinels fail the run. Identical-to-English and
 * copies from another locale warn, unless --strict-quality is set.
 */
declare(strict_types=1);

$root = dirname(__DIR__);
$options = getopt('', ['locale:', 'strict', 'strict-quality', 'selectable', 'warn-only', 'check-untranslated', 'help']);

if (isset($options['help'])) {
    fwrite(STDOUT, "Usage: php scripts/i18n-audit.php --selectable [--strict-quality]\n");
    fwrite(STDOUT, "       php scripts/i18n-audit.php --locale=de [--strict] [--warn-only]\n");
    exit(0);
}

/** @return array<string, string> */
function birkly_audit_flatten(array $data, string $prefix = ''): array
{
    $out = [];
    foreach ($data as $key => $value) {
        if ($key === '_meta') {
            continue;
        }
        $path = $prefix === '' ? (string) $key : $prefix . '.' . $key;
        if (is_array($value)) {
            $out += birkly_audit_flatten($value, $path);
        } elseif (is_string($value)) {
            $out[$path] = $value;
        }
    }

    return $out;
}

/** @return list<string> */
function birkly_audit_placeholders(string $value): array
{
    preg_match_all('/\{\{[a-zA-Z0-9_]+\}\}|\{[a-zA-Z0-9_]+\}/', $value, $matches);
    $tokens = $matches[0];
    sort($tokens);

    return $tokens;
}

function birkly_audit_has_sentinel(string $value): bool
{
    return preg_match('/BIRKLYPH|ビルクリフ|sartículo|Stäng|Okänt|Filtren|Collectionen|gecharger|Unter-Entrée|Continueren|Ouvrir Sie|Chargement Sie/ui', $value) === 1;
}

/**
 * German words left inside a non-German catalog. Skipped for German itself.
 */
function birkly_audit_has_locale_mash(string $label, string $value): bool
{
    if (preg_match('/ de$/', $label) === 1) {
        return false;
    }

    return preg_match(
        '/\b(konnten|werden|wurde|wurden|nicht|bitte|können|kann|muss|sowie|ihre|ihren|ihrem|ihrer|ihnen|ihr|sie|wirklich|rückgängig|wählen|beim|für|oder|und|einstellungen|gelöscht|gespeichert|fehlgeschlagen|aktualisieren|hinzufügen|erforderlich|abgebrochen|angeben|änderungen|durchsuchen|fortfahren|erneut|widerrufen|benötigen|deaktiviert|ausgeblendet|vorrendern|hochcharger|verwalten|ansehen|öffnen|auswählen|bearbeiten|gefunden|abgeschlossenen|abgebrochenen|bestellungen|klicken|prüfen|richten|steuern|standardmäßig|rückfragen|nachvollzieh|einfügen|geschlossenen|aufmerksamkeit|anbieter|besucher|festlegen|oberflächen|seitenleiste|zeitzone|karenz|zwischenablage|anmeldung|fenster|sitzplatz|bewertungen|kostenpflichtiges|licenceschlüssel|entdecken|kopfzeile|eingebauten|gecacht|komponente|gebunden|zahlung|schlüssel|animationen|fortschritt|konto|browser-infos|auslieferung|lern-vorlage|guide-hinweise|diese|dieser|dieses|diesen|einen|einer|einem|der|die|das|ist|sind|nach|über|unter|auch|dann|wenn|keine|noch|neue|alle|ohne|mit|auf|vor|zum|zur|dem|den|ein)\b|eine\b/ui',
        $value
    ) === 1;
}

/**
 * A long string that still contains several English function words.
 * Glossary-only values are exempt.
 *
 * @param list<string> $glossary
 * @param list<string> $phrases
 */
function birkly_audit_english_leak(string $value, array $glossary, array $phrases): bool
{
    if (strlen($value) <= 40 || birkly_audit_glossary_only($value, $glossary, $phrases)) {
        return false;
    }
    $stripped = preg_replace('/\{\{?[a-zA-Z0-9_]+\}?\}/', ' ', $value) ?? $value;
    $words = preg_split("/[^a-zA-Z']+/", strtolower($stripped), -1, PREG_SPLIT_NO_EMPTY);
    if ($words === false) {
        return false;
    }
    $functionWords = [
        'the', 'and', 'with', 'your', 'this', 'that', 'from', 'have', 'been', 'will',
        'please', 'click', 'cannot', 'should', 'would', 'which', 'into', 'about',
        'after', 'before', 'because', 'without', 'between', 'these', 'those',
        'their', 'them', 'were', 'not', 'yet', 'only', 'when', 'then', 'than',
    ];
    $hits = 0;
    foreach ($words as $word) {
        if (in_array($word, $functionWords, true)) {
            $hits++;
            if ($hits >= 3) {
                return true;
            }
        }
    }

    return false;
}

/** @param list<string> $glossary */
function birkly_audit_glossary_only(string $value, array $glossary, array $phrases = []): bool
{
    $stripped = preg_replace('/\{\{?[a-zA-Z0-9_]+\}?\}/', ' ', $value) ?? $value;
    $stripped = trim(preg_replace('/<[^>]+>/', ' ', $stripped) ?? $stripped);
    $lower = strtolower($stripped);
    if ($lower === '' || in_array($lower, $glossary, true) || in_array($lower, $phrases, true)) {
        return true;
    }
    if (in_array(strtolower(trim($value)), $phrases, true)) {
        return true;
    }
    if (preg_match('/^[\w.+-]+@[\w.-]+\.[a-z]{2,}$/i', trim($value)) === 1) {
        return true;
    }
    if (preg_match('#^/[\w./-]*$#', trim($value)) === 1) {
        return true;
    }
    $words = preg_split('/[^a-z0-9]+/i', $lower, -1, PREG_SPLIT_NO_EMPTY);
    if ($words === false || $words === []) {
        return true;
    }
    foreach ($words as $word) {
        if (preg_match('/^[0-9]+$/', $word) === 1) {
            continue;
        }
        if (!in_array(strtolower($word), $glossary, true)) {
            return false;
        }
    }

    return true;
}

/**
 * @param array<string, string> $en
 * @param array<string, string> $locale
 * @param array<string, array<string, string>> $others
 * @param list<string> $glossary
 * @param list<string> $phrases
 * @param array<string, true> $shared exact values that are correct in more than one locale
 * @return array{missing: int, placeholder: int, sentinel: int, identical: int, cross: int, errors: list<string>, warnings: list<string>}
 */
function birkly_audit_compare(
    string $label,
    array $en,
    array $locale,
    array $others,
    array $glossary,
    bool $strictQuality,
    array $phrases = [],
    array $shared = []
): array {
    $missing = array_diff_key($en, $locale);
    $placeholder = 0;
    $sentinel = 0;
    $identical = 0;
    $cross = 0;
    $errors = [];
    $warnings = [];

    if ($missing !== []) {
        $errors[] = $label . ': ' . count($missing) . ' missing keys';
    }

    foreach ($en as $key => $enVal) {
        if (!isset($locale[$key]) || !is_string($locale[$key])) {
            continue;
        }
        $locVal = $locale[$key];
        if (birkly_audit_placeholders($enVal) !== birkly_audit_placeholders($locVal)) {
            $placeholder++;
            if ($placeholder <= 5) {
                $errors[] = $label . ': placeholder mismatch ' . $key;
            }
        }
        if (birkly_audit_has_sentinel($locVal) || birkly_audit_has_locale_mash($label, $locVal) || birkly_audit_english_leak($locVal, $glossary, $phrases)) {
            $sentinel++;
            if ($sentinel <= 5) {
                $errors[] = $label . ': sentinel in ' . $key;
            }
        }
        if ($locVal === $enVal && strlen($enVal) > 2 && preg_match('/[A-Za-z]{3,}/', $enVal) && !birkly_audit_glossary_only($enVal, $glossary, $phrases)) {
            $identical++;
        }
        if ($locVal !== $enVal && $locVal !== '' && !isset($shared[$locVal])) {
            foreach ($others as $otherCode => $otherMap) {
                if (($otherMap[$key] ?? null) === $locVal) {
                    $cross++;
                    break;
                }
            }
        }
    }

    if ($placeholder > 5) {
        $errors[] = $label . ': ' . $placeholder . ' placeholder mismatches';
    }
    if ($sentinel > 5) {
        $errors[] = $label . ': ' . $sentinel . ' sentinel leaks';
    }
    if ($identical > 0) {
        $msg = $label . ': ' . $identical . ' values identical to English';
        if ($strictQuality) {
            $errors[] = $msg;
        } else {
            $warnings[] = $msg;
        }
    }
    if ($cross > 0) {
        $msg = $label . ': ' . $cross . ' values copied from another locale';
        if ($strictQuality) {
            $errors[] = $msg;
        } else {
            $warnings[] = $msg;
        }
    }

    return [
        'missing' => count($missing),
        'placeholder' => $placeholder,
        'sentinel' => $sentinel,
        'identical' => $identical,
        'cross' => $cross,
        'errors' => $errors,
        'warnings' => $warnings,
    ];
}

/** @return list<array{root: string, id: string}> */
function birkly_audit_plugin_repos(string $root): array
{
    $parent = dirname($root);
    $repos = [
        'Birkly-Plugin-User' => 'user_management',
        'Birkly-Plugin-Commerce' => 'commerce',
        'Birkly-Plugin-Events' => 'events',
        'Birkly-Plugin-Automation' => 'automation',
        'Birkly-Plugin-Studio' => 'studio',
    ];
    $found = [];
    foreach ($repos as $dir => $id) {
        $path = $parent . '/' . $dir;
        if (is_file($path . '/lang/en.json')) {
            $found[] = ['root' => $path, 'id' => $id];
        }
    }

    return $found;
}

$glossary = [];
$glossaryPhrases = [];
$glossaryShared = [];
$glossaryFile = $root . '/lang/_glossary.json';
if (is_file($glossaryFile)) {
    $decoded = json_decode((string) file_get_contents($glossaryFile), true);
    if (is_array($decoded['tokens'] ?? null)) {
        foreach ($decoded['tokens'] as $token) {
            if (is_string($token) && $token !== '') {
                $glossary[] = strtolower($token);
            }
        }
    }
    if (is_array($decoded['phrases'] ?? null)) {
        foreach ($decoded['phrases'] as $phrase) {
            if (is_string($phrase) && $phrase !== '') {
                $glossaryPhrases[] = strtolower($phrase);
            }
        }
    }
    if (is_array($decoded['shared'] ?? null)) {
        foreach ($decoded['shared'] as $sharedValue) {
            if (is_string($sharedValue) && $sharedValue !== '') {
                $glossaryShared[$sharedValue] = true;
            }
        }
    }
}

$selectable = array_key_exists('selectable', $options);
$strictQuality = array_key_exists('strict-quality', $options);
$warnOnly = array_key_exists('warn-only', $options);

if ($selectable) {
    require_once $root . '/core/i18n.php';
    $codes = birkly_admin_ui_selectable_language_codes();
    $codes = array_values(array_filter($codes, static fn(string $code): bool => $code !== 'en'));
    $coreLocales = [];
    $enCore = birkly_audit_flatten(json_decode((string) file_get_contents($root . '/lang/en.json'), true)['admin'] ?? []);
    foreach ($codes as $code) {
        $data = json_decode((string) file_get_contents($root . '/lang/' . $code . '.json'), true);
        $coreLocales[$code] = birkly_audit_flatten(is_array($data['admin'] ?? null) ? $data['admin'] : []);
        $meta = birkly_admin_locale_meta($code);
        if (($meta['dir'] ?? 'ltr') === 'rtl') {
            fwrite(STDERR, "ERROR: {$code} is rtl and cannot be selectable in this phase\n");
            exit(1);
        }
    }

    $errors = [];
    $warnings = [];
    fwrite(STDOUT, "i18n audit — selectable strict-quality=" . ($strictQuality ? 'yes' : 'no') . "\n");
    foreach ($codes as $code) {
        $others = $coreLocales;
        unset($others[$code]);
        $result = birkly_audit_compare('core ' . $code, $enCore, $coreLocales[$code], $others, $glossary, $strictQuality, $glossaryPhrases, $glossaryShared);
        fwrite(STDOUT, sprintf(
            "  core %s missing=%d placeholder=%d sentinel=%d identical=%d cross=%d\n",
            $code,
            $result['missing'],
            $result['placeholder'],
            $result['sentinel'],
            $result['identical'],
            $result['cross']
        ));
        $errors = array_merge($errors, $result['errors']);
        $warnings = array_merge($warnings, $result['warnings']);
    }

    foreach (birkly_audit_plugin_repos($root) as $plugin) {
        $enData = json_decode((string) file_get_contents($plugin['root'] . '/lang/en.json'), true);
        $enPlugin = birkly_audit_flatten(is_array($enData['plugins'][$plugin['id']] ?? null) ? $enData['plugins'][$plugin['id']] : []);
        $pluginLocales = [];
        foreach ($codes as $code) {
            $path = $plugin['root'] . '/lang/' . $code . '.json';
            if (!is_file($path)) {
                fwrite(STDOUT, "  {$plugin['id']} {$code} file absent (English fallback)\n");
                continue;
            }
            $data = json_decode((string) file_get_contents($path), true);
            $pluginLocales[$code] = birkly_audit_flatten(is_array($data['plugins'][$plugin['id']] ?? null) ? $data['plugins'][$plugin['id']] : []);
        }
        foreach ($pluginLocales as $code => $flat) {
            $others = $pluginLocales;
            unset($others[$code]);
            $result = birkly_audit_compare($plugin['id'] . ' ' . $code, $enPlugin, $flat, $others, $glossary, $strictQuality, $glossaryPhrases, $glossaryShared);
            fwrite(STDOUT, sprintf(
                "  %s %s missing=%d placeholder=%d sentinel=%d identical=%d cross=%d\n",
                $plugin['id'],
                $code,
                $result['missing'],
                $result['placeholder'],
                $result['sentinel'],
                $result['identical'],
                $result['cross']
            ));
            $errors = array_merge($errors, $result['errors']);
            $warnings = array_merge($warnings, $result['warnings']);
        }
    }

    foreach ($warnings as $warning) {
        fwrite(STDOUT, "WARN: {$warning}\n");
    }
    if ($errors !== [] && !$warnOnly) {
        foreach ($errors as $error) {
            fwrite(STDERR, "ERROR: {$error}\n");
        }
        exit(1);
    }
    fwrite(STDOUT, "OK: audit passed\n");
    exit(0);
}

$locale = isset($options['locale']) ? (string) $options['locale'] : 'de';
$strict = array_key_exists('strict', $options);
$checkUntranslated = array_key_exists('check-untranslated', $options) || $strictQuality;

$enData = json_decode((string) file_get_contents($root . '/lang/en.json'), true);
$enAdmin = is_array($enData['admin'] ?? null) ? $enData['admin'] : [];
$localeData = is_file($root . '/lang/' . $locale . '.json')
    ? json_decode((string) file_get_contents($root . '/lang/' . $locale . '.json'), true)
    : [];
$localeAdmin = is_array($localeData['admin'] ?? null) ? $localeData['admin'] : [];
$enFlat = birkly_audit_flatten($enAdmin);
$localeFlat = birkly_audit_flatten($localeAdmin);
$result = birkly_audit_compare('core ' . $locale, $enFlat, $localeFlat, [], $glossary, $checkUntranslated, $glossaryPhrases, $glossaryShared);

fwrite(STDOUT, "i18n audit — locale={$locale} strict=" . ($strict ? 'yes' : 'no') . "\n");
fwrite(STDOUT, '  en admin keys: ' . count($enFlat) . "\n");
fwrite(STDOUT, '  locale admin keys: ' . count($localeFlat) . "\n");
fwrite(STDOUT, '  missing: ' . $result['missing'] . "\n");
fwrite(STDOUT, '  identical-to-en (quality): ' . $result['identical'] . "\n");

$failed = $strict && $result['missing'] > 0;
if ($checkUntranslated && $result['identical'] > 0 && !$warnOnly) {
    $failed = true;
}
if ($result['placeholder'] > 0 || $result['sentinel'] > 0) {
    $failed = $strict || $selectable;
}
foreach ($result['warnings'] as $warning) {
    fwrite(STDOUT, "WARN: {$warning}\n");
}
foreach ($result['errors'] as $error) {
    if ($warnOnly) {
        fwrite(STDOUT, "WARN: {$error}\n");
    } else {
        fwrite(STDERR, "ERROR: {$error}\n");
    }
}
if ($failed && !$warnOnly) {
    exit(1);
}
fwrite(STDOUT, "OK: audit passed\n");
exit(0);
