<?php
/**
 * Internationalization (i18n) System for Birkly
 * Handles language files, translations, and content localization
 */

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
                $translation = str_replace('{{' . $param . '}}', (string) $value, $translation);
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
     * Build flat JS dictionary: admin.* plus optional plugins.{id}.* subtree.
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

        $plugin_id = trim((string) $plugin_id);
        if ($plugin_id !== ''
            && isset($merged['plugins'][$plugin_id])
            && is_array($merged['plugins'][$plugin_id])) {
            $flat = array_merge(
                $flat,
                self::flattenForJs(['plugins' => [$plugin_id => $merged['plugins'][$plugin_id]]])
            );
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
            $files = glob($langDir . '/*.json');
            foreach ($files as $file) {
                $langCode = basename($file, '.json');
                $data = json_decode(file_get_contents($file), true);
                if ($data && isset($data['_meta'])) {
                    $languages[$langCode] = [
                        'code' => $langCode,
                        'name' => $data['_meta']['name'] ?? $langCode,
                        'native_name' => $data['_meta']['native_name'] ?? $data['_meta']['name'] ?? $langCode,
                        'flag' => $data['_meta']['flag'] ?? ''
                    ];
                }
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
