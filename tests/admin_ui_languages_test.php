<?php
/**
 * Admin UI selectable languages come from lang/{code}.json _meta.selectable.
 */
declare(strict_types=1);

$root = dirname(__DIR__);
require_once $root . '/core/i18n.php';
require_once $root . '/core/content_locale.php';
I18n::setLanguage('en');

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

$fromFiles = [];
foreach (glob($root . '/lang/*.json') ?: [] as $file) {
    $code = strtolower(basename($file, '.json'));
    if (!preg_match('/^[a-z]{2,3}(?:-[a-z0-9]+)?$/', $code)) {
        continue;
    }
    $data = json_decode((string) file_get_contents($file), true);
    if (is_array($data['_meta'] ?? null) && ($data['_meta']['selectable'] ?? false) === true) {
        $fromFiles[] = $code;
    }
}
$preferred = ['en', 'de', 'fr', 'ja', 'es'];
$ordered = [];
foreach ($preferred as $code) {
    if (in_array($code, $fromFiles, true)) {
        $ordered[] = $code;
    }
}
$rest = array_values(array_diff($fromFiles, $ordered));
sort($rest);
$expected = array_merge($ordered, $rest);

$codes = birkly_admin_ui_selectable_language_codes();
assert_true('picker matches selectable catalogs', $codes === $expected);
assert_true('includes en de fr ja es', $codes === ['en', 'de', 'fr', 'ja', 'es']);
assert_true('excludes da hi zh', !in_array('da', $codes, true) && !in_array('hi', $codes, true) && !in_array('zh', $codes, true));

$available = I18n::getAvailableLanguages();
assert_true('picker has es', isset($available['es']));
assert_true('picker omits da', !isset($available['da']));
assert_true('picker omits hi', !isset($available['hi']));
assert_true('picker omits zh', !isset($available['zh']));

assert_true('normalize da to en', I18n::normalizeSelectableAdminLanguage('da') === 'en');
assert_true('normalize es stays es', I18n::normalizeSelectableAdminLanguage('es') === 'es');
assert_true('german date format from catalog', birkly_locale_default_date_format('de') === 'd.m.Y');
assert_true('german bcp47 from catalog', birkly_locale_to_bcp47('de') === 'de-DE');

I18n::setLanguage('en');
assert_true(
    'php substitutes {project}',
    __('admin.auth_login_title', ['project' => 'Ada']) === 'Login - Ada Admin'
);

exit($failures === [] ? 0 : 1);
