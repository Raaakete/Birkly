<?php
/**
 * Internationalization (i18n) System for Birkly
 * Handles language files, translations, and content localization
 */

/**
 * Read lang/{code}.json catalogs. A language is in the admin picker only when
 * _meta.selectable is true. Files that are not language codes are ignored.
 *
 * @return array<string, array<string, mixed>>
 */
function birkly_admin_locale_catalog(): array
{
    static $catalog = null;
    if (is_array($catalog)) {
        return $catalog;
    }

    $catalog = [];
    $langDir = __DIR__ . '/../lang';
    if (!is_dir($langDir)) {
        return $catalog;
    }

    foreach (glob($langDir . '/*.json') ?: [] as $file) {
        $code = strtolower(basename($file, '.json'));
        if (!preg_match('/^[a-z]{2,3}(?:-[a-z0-9]+)?$/', $code)) {
            continue;
        }
        $data = json_decode((string) file_get_contents($file), true);
        if (!is_array($data) || !is_array($data['_meta'] ?? null) || !is_array($data['admin'] ?? null)) {
            continue;
        }
        $meta = $data['_meta'];
        $meta['code'] = $code;
        $meta['selectable'] = ($meta['selectable'] ?? false) === true;
        $catalog[$code] = $meta;
    }

    return $catalog;
}

/**
 * @return array<string, mixed>
 */
function birkly_admin_locale_meta(string $code): array
{
    $code = strtolower(trim($code));
    $catalog = birkly_admin_locale_catalog();

    return $catalog[$code] ?? [];
}

/**
 * Admin UI languages shown in onboarding, account, and sidebar pickers.
 * English stays first, then the historical order, then any newer codes.
 *
 * @return list<string>
 */
function birkly_admin_ui_selectable_language_codes(): array
{
    $found = [];
    foreach (birkly_admin_locale_catalog() as $code => $meta) {
        if (!empty($meta['selectable'])) {
            $found[] = $code;
        }
    }

    $preferred = ['en', 'de', 'fr', 'ja', 'es'];
    $ordered = [];
    foreach ($preferred as $code) {
        if (in_array($code, $found, true)) {
            $ordered[] = $code;
        }
    }
    $rest = array_values(array_diff($found, $ordered));
    sort($rest);

    return array_merge($ordered, $rest);
}

class I18n {
    private static $currentLanguage = 'en';
    private static $translations = [];
    private static $fallbackLanguage = 'en';
    private static $loadedLanguages = [];
    private static $pluginCatalogsMerged = [];
    
    /**
     * Initialize i18n system
     */
    public static function init($language = null) {
        // Get language from user profile, session, or settings
        if ($language === null) {
            if (session_status() === PHP_SESSION_NONE) {
                session_start();
            }
            
            // First check user profile (user-level language)
            if (isset($_SESSION['user'])) {
                require_once __DIR__ . '/storage/StorageFactory.php';
                $userStorage = StorageFactory::createStorage(__DIR__ . '/../users');
                $userId = $_SESSION['user']['id'] ?? '';
                if ($userId) {
                    $user = $userStorage->read($userId . '.json');
                    if ($user && isset($user['language'])) {
                        $language = $user['language'];
                        self::setLanguage($language);
                        return;
                    }
                }
            }
            
            // Then check session
            if (isset($_SESSION['admin_language'])) {
                $language = $_SESSION['admin_language'];
            } else {
                // Finally fall back to default
                $language = self::getDefaultLanguage();
            }
        }
        
        self::setLanguage($language);
    }

    /**
     * Map stored language to a selectable admin UI locale (hidden locales fall back to en).
     */
    public static function normalizeSelectableAdminLanguage(string $language): string
    {
        $language = strtolower(trim($language));
        if ($language === '') {
            return self::$fallbackLanguage;
        }
        if (in_array($language, birkly_admin_ui_selectable_language_codes(), true)) {
            return $language;
        }

        return self::$fallbackLanguage;
    }
    
    /**
     * Get default language from settings
     */
    private static function getDefaultLanguage() {
        $settingsFile = __DIR__ . '/../settings/admin.json';
        if (file_exists($settingsFile)) {
            $settings = json_decode(file_get_contents($settingsFile), true);
            return $settings['language'] ?? 'en';
        }
        return 'en';
    }
    
    /**
     * Set current language
     */
    public static function setLanguage($language) {
        $language = self::normalizeSelectableAdminLanguage((string) $language);
        self::$currentLanguage = $language;
        
        // Store in session
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        $_SESSION['admin_language'] = $language;
        
        // Load translations for this language
        self::loadLanguage($language);
    }
    
    /**
     * Get current language
     */
    public static function getLanguage() {
        return self::$currentLanguage;
    }
    
    /**
     * Load core language file(s) for a locale.
     */
    private static function loadLanguage($language) {
        if (isset(self::$loadedLanguages[$language])) {
            return; // Already loaded
        }
        
        $langFile = __DIR__ . "/../lang/{$language}.json";
        
        if (file_exists($langFile)) {
            $translations = json_decode(file_get_contents($langFile), true);
            if ($translations) {
                self::$translations[$language] = $translations;
            }
        }
        
        if (!isset(self::$translations[$language])) {
            self::$translations[$language] = [];
        }
        
        // Always load fallback language
        if ($language !== self::$fallbackLanguage) {
            $fallbackFile = __DIR__ . "/../lang/" . self::$fallbackLanguage . ".json";
            if (file_exists($fallbackFile) && !isset(self::$loadedLanguages[self::$fallbackLanguage])) {
                $translations = json_decode(file_get_contents($fallbackFile), true);
                if ($translations) {
                    self::$translations[self::$fallbackLanguage] = $translations;
                }
            }
        }
        
        if (!isset(self::$translations[self::$fallbackLanguage])) {
            self::$translations[self::$fallbackLanguage] = [];
        }
        
        self::$loadedLanguages[$language] = true;
        
        self::applyTranslationFilters($language);
        if ($language !== self::$fallbackLanguage) {
            self::applyTranslationFilters(self::$fallbackLanguage);
        }
    }
    
    /**
     * Merge active plugin lang catalogs into loaded translations (P85-M1).
     */
    public static function mergePluginCatalogs(): void {
        if (!function_exists('birkly_get_registered_plugin_i18n_catalogs')
            || !function_exists('birkly_load_plugin_i18n_file')
            || !function_exists('birkly_i18n_deep_merge')) {
            return;
        }

        $languages = array_unique([self::$currentLanguage, self::$fallbackLanguage]);
        $catalogs = birkly_get_registered_plugin_i18n_catalogs();

        foreach ($languages as $language) {
            if ($language === '' || isset(self::$pluginCatalogsMerged[$language])) {
                continue;
            }

            if (!isset(self::$translations[$language])) {
                self::$translations[$language] = [];
            }

            foreach ($catalogs as $catalog) {
                $langDir = (string) ($catalog['lang_dir'] ?? '');
                if ($langDir === '') {
                    continue;
                }
                $pluginData = birkly_load_plugin_i18n_file($langDir, $language);
                if (is_array($pluginData) && $pluginData !== []) {
                    self::$translations[$language] = birkly_i18n_deep_merge(
                        self::$translations[$language],
                        $pluginData
                    );
                }
            }

            self::applyTranslationFilters($language);
            self::$pluginCatalogsMerged[$language] = true;
        }
    }

    /**
     * Reset plugin merge state (tests).
     */
    public static function resetPluginCatalogMergeState(): void {
        self::$pluginCatalogsMerged = [];
    }

    /**
     * Full reset for unit tests.
     */
    public static function resetForTests(): void {
        self::$pluginCatalogsMerged = [];
        self::$loadedLanguages = [];
        self::$translations = [];
        self::$currentLanguage = 'en';
    }
    
    /**
     * Apply birkly_i18n_translations filter to a loaded locale bucket.
     */
    private static function applyTranslationFilters(string $language): void {
        if (!function_exists('apply_filters')) {
            return;
        }

        $merged = apply_filters(
            'birkly_i18n_translations',
            self::$translations[$language] ?? [],
            $language
        );

        if (is_array($merged)) {
            self::$translations[$language] = $merged;
        }
    }
    
    /**
     * Translate a string
     */
    public static function translate($key, $params = []) {
        self::ensurePluginCatalogsMerged();
        
        // Try current language first
        $translation = self::getTranslation($key, self::$currentLanguage);
        
        // Fallback to default language if not found
        if ($translation === null && self::$currentLanguage !== self::$fallbackLanguage) {
            $translation = self::getTranslation($key, self::$fallbackLanguage);
        }
        
        // If still not found, return the key
        if ($translation === null) {
            return $key;
        }
        
        // Replace parameters
        if (!empty($params)) {
            foreach ($params as $param => $value) {
                $replacement = (string) $value;
                $translation = str_replace('{{' . $param . '}}', $replacement, $translation);
                $translation = str_replace('{' . $param . '}', $replacement, $translation);
            }
        }
        
        return $translation;
    }

    private static function ensurePluginCatalogsMerged(): void {
        if (function_exists('birkly_merge_active_plugin_i18n_into_i18n')) {
            self::mergePluginCatalogs();
        }
    }
    
    /**
     * Get translation from loaded translations
     */
    private static function getTranslation($key, $language) {
        if (!isset(self::$translations[$language])) {
            return null;
        }
        
        $keys = explode('.', $key);
        $value = self::$translations[$language];
        
        foreach ($keys as $k) {
            if (isset($value[$k])) {
                $value = $value[$k];
            } else {
                return null;
            }
        }
        
        return is_string($value) ? $value : null;
    }

    /**
     * Flatten nested translation tree for JS injection (P85-M2).
     *
     * @param array<string, mixed> $tree
     * @param string $prefix
     * @return array<string, string>
     */
    public static function flattenForJs(array $tree, string $prefix = ''): array {
        $flat = [];
        foreach ($tree as $key => $value) {
            if ($key === '_meta') {
                continue;
            }
            $path = $prefix === '' ? (string) $key : $prefix . '.' . $key;
            if (is_array($value)) {
                $flat = array_merge($flat, self::flattenForJs($value, $path));
            } elseif (is_string($value)) {
                $flat[$path] = $value;
            }
        }
        return $flat;
    }

    /**
     * Subtree for a plugin namespace (e.g. plugins.commerce).
     *
     * @return array<string, mixed>
     */
    public static function getPluginSubtree(string $plugin_id, ?string $language = null): array {
        self::ensurePluginCatalogsMerged();
        $language = $language ?? self::$currentLanguage;
        $plugins = self::$translations[$language]['plugins'][$plugin_id] ?? null;
        if (is_array($plugins)) {
            return $plugins;
        }
        if ($language !== self::$fallbackLanguage) {
            $fallback = self::$translations[self::$fallbackLanguage]['plugins'][$plugin_id] ?? null;
            return is_array($fallback) ? $fallback : [];
        }
        return [];
    }
    
    /**
     * Build flat JS dictionary: admin.* plus every active plugin subtree.
     *
     * Dashboard widgets and other cross-page scripts need plugin catalogs even
     * when the current admin page is not owned by that plugin. $plugin_id is
     * accepted for older callers and does not limit the dictionary.
     *
     * @return array<string, string>
     */
    public static function getJsDictionary(?string $plugin_id = null): array {
        self::ensurePluginCatalogsMerged();

        $language = self::$currentLanguage;
        $fallback = self::$fallbackLanguage;

        $merged = is_array(self::$translations[$language] ?? null) ? self::$translations[$language] : [];
        if ($language !== $fallback && is_array(self::$translations[$fallback] ?? null)) {
            if (function_exists('birkly_i18n_deep_merge')) {
                $merged = birkly_i18n_deep_merge(self::$translations[$fallback], $merged);
            } else {
                $merged = array_replace_recursive(self::$translations[$fallback], $merged);
            }
        }

        $flat = [];
        if (isset($merged['admin']) && is_array($merged['admin'])) {
            $flat = array_merge($flat, self::flattenForJs(['admin' => $merged['admin']]));
        }

        $plugins = isset($merged['plugins']) && is_array($merged['plugins']) ? $merged['plugins'] : [];
        foreach ($plugins as $id => $tree) {
            if (!is_string($id) || $id === '' || !is_array($tree)) {
                continue;
            }
            $flat = array_merge($flat, self::flattenForJs(['plugins' => [$id => $tree]]));
        }

        return $flat;
    }
    
    /**
     * Get available languages
     */
    public static function getAvailableLanguages() {
        $langDir = __DIR__ . '/../lang';
        $languages = [];

        if (is_dir($langDir)) {
            foreach (birkly_admin_ui_selectable_language_codes() as $langCode) {
                $file = $langDir . '/' . $langCode . '.json';
                if (!is_file($file)) {
                    continue;
                }
                $data = json_decode((string) file_get_contents($file), true);
                if (!is_array($data) || !isset($data['_meta'])) {
                    continue;
                }
                $languages[$langCode] = [
                    'code' => $langCode,
                    'name' => $data['_meta']['name'] ?? $langCode,
                    'native_name' => $data['_meta']['native_name'] ?? $data['_meta']['name'] ?? $langCode,
                    'flag' => $data['_meta']['flag'] ?? '',
                ];
            }
        }

        return $languages;
    }
}

/**
 * Translation helper function
 */
function __($key, $params = []) {
    return I18n::translate($key, $params);
}

/**
 * Get current language
 */
function getCurrentLanguage() {
    return I18n::getLanguage();
}

/**
 * Set language
 */
function setLanguage($language) {
    I18n::setLanguage($language);
}

/**
 * Display label for built-in CMS admin roles (user menu, sidebar).
 */
function birkly_admin_user_role_label(string $role): string
{
    $slug = strtolower(trim($role));
    if ($slug === '') {
        $slug = 'viewer';
    }
    $key = 'admin.user_role_' . preg_replace('/[^a-z0-9_]/', '', str_replace('-', '_', $slug));
    $label = I18n::translate($key);
    if ($label !== $key) {
        return $label;
    }

    return ucfirst($slug);
}
