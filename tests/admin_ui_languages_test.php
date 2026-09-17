<?php
/**
 * Admin UI selectable languages (picker whitelist).
 */
declare(strict_types=1);

$root = dirname(__DIR__);
require_once $root . '/core/i18n.php';

$failures = [];

function assert_true(string $label, bool $condition): void
{
    global $failures;
    if (!$condition) {
        $failures[] = $label;
        fwrite(STDERR, "FAIL: $label\n");
        return;
    }
    echo "OK: $label\n";
}

$codes = birkly_admin_ui_selectable_language_codes();
assert_true('includes en de fr ja es', $codes === ['en', 'de', 'fr', 'ja', 'es']);
assert_true('excludes da hi zh', !in_array('da', $codes, true) && !in_array('hi', $codes, true) && !in_array('zh', $codes, true));

$available = I18n::getAvailableLanguages();
assert_true('picker has es', isset($available['es']));
assert_true('picker omits da', !isset($available['da']));
assert_true('picker omits hi', !isset($available['hi']));
assert_true('picker omits zh', !isset($available['zh']));

assert_true('normalize da to en', I18n::normalizeSelectableAdminLanguage('da') === 'en');
assert_true('normalize es stays es', I18n::normalizeSelectableAdminLanguage('es') === 'es');

exit($failures === [] ? 0 : 1);
