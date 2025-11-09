<?php
/**
 * Internationalization (i18n) System for HaruCMS
 * Handles language files, translations, and content localization
 */

class I18n {
    private static $currentLanguage = 'en';
    private static $translations = [];
    private static $fallbackLanguage = 'en';
    private static $loadedLanguages = [];
    
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
                require_once __DIR__ . '/../api/storage.php';
                $userStorage = new Storage(__DIR__ . '/../users');
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
     * Load language file
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
        
        self::$loadedLanguages[$language] = true;
    }
    
    /**
     * Translate a string
     */
    public static function translate($key, $params = []) {
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
                $translation = str_replace("{{$param}}", $value, $translation);
            }
        }
        
        return $translation;
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

