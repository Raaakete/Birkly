<?php
// CSRF Helper

function generate_csrf_token() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function get_csrf_token() {
    return $_SESSION['csrf_token'] ?? generate_csrf_token();
}

function is_csrf_enabled() {
    static $enabled = null;
    if ($enabled !== null) {
        return $enabled;
    }

    $securityFile = __DIR__ . '/../settings/security.json';
    if (file_exists($securityFile)) {
        $security = json_decode(file_get_contents($securityFile), true);
        if (is_array($security) && array_key_exists('enable_csrf', $security)) {
            $enabled = (bool) $security['enable_csrf'];
            return $enabled;
        }
    }

    $enabled = true;
    return $enabled;
}

function validate_csrf_token($token) {
    if (!is_csrf_enabled()) {
        return true;
    }
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], (string) $token);
} 