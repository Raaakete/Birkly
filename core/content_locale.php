<?php
/**
 * Content locale helpers — display formats and translatable field options (i18n).
 */

declare(strict_types=1);

/**
 * Normalize a language code for lookups (lowercase, underscores → hyphens).
 */
function birkly_locale_normalize_code(string $code): string
{
    $code = strtolower(trim($code));
    $code = str_replace('_', '-', $code);

    return $code;
}

/**
 * Map Birkly language codes to BCP 47 locales for Intl / toLocaleString.
 */
function birkly_locale_to_bcp47(string $code): string
{
    $normalized = birkly_locale_normalize_code($code);
    $map = [
        'en' => 'en-US',
        'de' => 'de-DE',
        'fr' => 'fr-FR',
        'es' => 'es-ES',
        'it' => 'it-IT',
        'pt' => 'pt-BR',
        'nl' => 'nl-NL',
        'ja' => 'ja-JP',
        'zh' => 'zh-CN',
        'zh-tw' => 'zh-TW',
        'ko' => 'ko-KR',
        'ar' => 'ar-SA',
        'ru' => 'ru-RU',
        'pl' => 'pl-PL',
        'sv' => 'sv-SE',
        'da' => 'da-DK',
        'fi' => 'fi-FI',
        'no' => 'nb-NO',
    ];

    return $map[$normalized] ?? str_replace('-', '_', $normalized);
}

/**
 * Default PHP date display format for a language (admin list / field display).
 */
function birkly_locale_default_date_format(?string $langCode): string
{
    $lang = birkly_locale_normalize_code((string) $langCode);
    $map = [
        'en' => 'm/d/Y',
        'de' => 'd.m.Y',
        'fr' => 'd/m/Y',
        'es' => 'd/m/Y',
        'it' => 'd/m/Y',
        'pt' => 'd/m/Y',
        'nl' => 'd-m-Y',
        'ja' => 'Y/m/d',
        'zh' => 'Y/m/d',
        'zh-tw' => 'Y/m/d',
        'ko' => 'Y.m.d',
        'ar' => 'd/m/Y',
        'ru' => 'd.m.Y',
        'pl' => 'd.m.Y',
        'sv' => 'Y-m-d',
        'da' => 'd-m-Y',
        'fi' => 'd.m.Y',
    ];

    return $map[$lang] ?? 'Y-m-d';
}

/**
 * Default PHP datetime display format for a language.
 */
function birkly_locale_default_datetime_format(?string $langCode): string
{
    $lang = birkly_locale_normalize_code((string) $langCode);
    $map = [
        'en' => 'm/d/Y g:i A',
        'de' => 'd.m.Y H:i',
        'fr' => 'd/m/Y H:i',
        'es' => 'd/m/Y H:i',
        'it' => 'd/m/Y H:i',
        'pt' => 'd/m/Y H:i',
        'nl' => 'd-m-Y H:i',
        'ja' => 'Y/m/d H:i',
        'zh' => 'Y/m/d H:i',
        'zh-tw' => 'Y/m/d H:i',
        'ko' => 'Y.m.d H:i',
        'ar' => 'd/m/Y H:i',
        'ru' => 'd.m.Y H:i',
        'pl' => 'd.m.Y H:i',
        'sv' => 'Y-m-d H:i',
        'da' => 'd-m-Y H:i',
        'fi' => 'd.m.Y H:i',
    ];

    return $map[$lang] ?? 'Y-m-d H:i';
}

/**
 * Default time display format (24h for most locales).
 */
function birkly_locale_default_time_format(?string $langCode): string
{
    $lang = birkly_locale_normalize_code((string) $langCode);
    if ($lang === 'en') {
        return 'g:i A';
    }

    return 'H:i';
}

/**
 * Resolve display format for a date-like field (schema override or locale default).
 *
 * @param array<string, mixed> $field
 */
function birkly_locale_field_display_format(array $field, ?string $langCode, string $fieldType): string
{
    $explicit = trim((string) ($field['format'] ?? ''));
    $useLocale = !empty($field['locale_format']) || $explicit === '' || $explicit === 'locale';

    if (!$useLocale && $explicit !== '') {
        return $explicit;
    }

    switch ($fieldType) {
        case 'datetime':
            return birkly_locale_default_datetime_format($langCode);
        case 'time':
            return birkly_locale_default_time_format($langCode);
        case 'date':
        default:
            return birkly_locale_default_date_format($langCode);
    }
}

/**
 * Format a stored date/datetime value for display using a PHP date format string.
 */
function birkly_locale_format_stored_date(string $value, string $phpFormat): string
{
    if ($value === '' || $phpFormat === '') {
        return $value;
    }

    try {
        if (preg_match('/^(\d{4}-\d{2}-\d{2})T(\d{2}:\d{2})/', $value, $m)) {
            $dt = \DateTime::createFromFormat('Y-m-d H:i', $m[1] . ' ' . $m[2]);
        } elseif (preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            $dt = \DateTime::createFromFormat('Y-m-d', $value);
        } else {
            $dt = new \DateTime($value);
        }

        if ($dt instanceof \DateTime) {
            return $dt->format($phpFormat);
        }
    } catch (\Throwable $e) {
        // fall through
    }

    return $value;
}

/**
 * Resolve one option label for a language (supports labels map + legacy label).
 *
 * @param array<string, mixed> $option
 */
function birkly_selection_option_label(array $option, ?string $langCode = null): string
{
    $labels = $option['labels'] ?? null;
    if (is_array($labels) && $langCode !== null && $langCode !== '') {
        $normalized = birkly_locale_normalize_code($langCode);
        if (isset($labels[$normalized]) && trim((string) $labels[$normalized]) !== '') {
            return (string) $labels[$normalized];
        }
        $base = explode('-', $normalized)[0];
        if ($base !== $normalized && isset($labels[$base]) && trim((string) $labels[$base]) !== '') {
            return (string) $labels[$base];
        }
    }

    $label = trim((string) ($option['label'] ?? ''));
    if ($label !== '') {
        return $label;
    }

    return (string) ($option['value'] ?? '');
}

/**
 * Return options with resolved display labels for a language.
 *
 * @param array<int, mixed> $options
 * @return array<int, array{label: string, value: string, labels?: array<string, string>}>
 */
function birkly_selection_resolve_options(array $options, ?string $langCode = null): array
{
    $resolved = [];
    foreach ($options as $opt) {
        if (is_string($opt)) {
            $opt = trim($opt);
            if ($opt === '') {
                continue;
            }
            $resolved[] = [
                'label' => $opt,
                'value' => strtolower(preg_replace('/[^a-z0-9]+/', '_', $opt)),
            ];
            continue;
        }
        if (!is_array($opt)) {
            continue;
        }
        $value = (string) ($opt['value'] ?? $opt['label'] ?? '');
        $label = birkly_selection_option_label($opt, $langCode);
        if ($value === '' && $label === '') {
            continue;
        }
        $row = [
            'label' => $label,
            'value' => $value !== '' ? $value : $label,
        ];
        if (isset($opt['labels']) && is_array($opt['labels'])) {
            $row['labels'] = $opt['labels'];
        }
        $resolved[] = $row;
    }

    return $resolved;
}

/**
 * Build option rows for collection editor fieldtype config (with optional per-language labels).
 *
 * @param array<int, mixed> $options
 * @param list<string> $languages
 */
function birkly_selection_options_config_rows(int|string $fieldIndex, array $options, array $languages = []): string
{
    $fieldIndex = (string) $fieldIndex;
    $html = '';
    $languages = array_values(array_filter(array_map(
        static fn($code) => is_string($code) ? birkly_locale_normalize_code($code) : '',
        $languages
    )));

    foreach ($options as $idx => $opt) {
        if (!is_array($opt)) {
            continue;
        }
        $label = htmlspecialchars((string) ($opt['label'] ?? $opt['value'] ?? ''), ENT_QUOTES, 'UTF-8');
        $value = htmlspecialchars((string) ($opt['value'] ?? $opt['label'] ?? ''), ENT_QUOTES, 'UTF-8');
        $labels = is_array($opt['labels'] ?? null) ? $opt['labels'] : [];

        $translationsHtml = '';
        if ($languages !== []) {
            $translationsHtml = '<div class="option-label-translations u-stack-1 u-mt-2">';
            foreach ($languages as $langCode) {
                $transLabel = htmlspecialchars((string) ($labels[$langCode] ?? ''), ENT_QUOTES, 'UTF-8');
                $langDisplay = htmlspecialchars($langCode, ENT_QUOTES, 'UTF-8');
                $translationsHtml .= <<<HTML
                    <div class="option-label-lang-row">
                        <label class="u-text-xs u-fg-secondary">{$langDisplay}</label>
                        <input type="text" class="input input-sm option-label-lang" placeholder="Label ({$langDisplay})" value="{$transLabel}" data-field="{$fieldIndex}" data-option="{$idx}" data-lang="{$langDisplay}">
                    </div>
                HTML;
            }
            $translationsHtml .= '</div>';
        }

        $html .= <<<HTML
            <div class="option-item" data-option-index="{$idx}">
                <input type="text" class="input option-label" placeholder="Label" value="{$label}" data-field="{$fieldIndex}" data-option="{$idx}">
                <input type="text" class="input option-value" placeholder="Value" value="{$value}" data-field="{$fieldIndex}" data-option="{$idx}">
                <button type="button" class="btn-icon remove-option-btn" data-field="{$fieldIndex}" data-option="{$idx}" title="Remove option" aria-label="Remove option">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                        <line x1="18" y1="6" x2="6" y2="18"></line>
                        <line x1="6" y1="6" x2="18" y2="18"></line>
                    </svg>
                </button>
                {$translationsHtml}
            </div>
        HTML;
    }

    return $html;
}
