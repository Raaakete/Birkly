<?php
error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../logs/php_errors.log');
// Input validation and sanitization helper for HaruCMS

function sanitize_string($value) {
    if (!is_string($value)) return '';
    return htmlspecialchars(trim($value), ENT_QUOTES | ENT_HTML5, 'UTF-8');
}

function sanitize_email($value) {
    if (!is_string($value)) return '';
    return filter_var(trim($value), FILTER_SANITIZE_EMAIL);
}

function sanitize_int($value) {
    return filter_var($value, FILTER_VALIDATE_INT);
}

function sanitize_url($value) {
    if (!is_string($value)) return '';
    return filter_var(trim($value), FILTER_SANITIZE_URL);
}

function sanitize_html($value, $allowed_tags = '') {
    if (!is_string($value)) return '';
    return strip_tags($value, $allowed_tags);
}

function sanitize_array($arr) {
    if (!is_array($arr)) return [];
    $out = [];
    foreach ($arr as $k => $v) {
        if (is_array($v)) {
            $out[$k] = sanitize_array($v);
        } else {
            $out[$k] = sanitize_string($v);
        }
    }
    return $out;
}

function sanitize_input($input) {
    if (is_array($input)) return sanitize_array($input);
    if (is_string($input)) return sanitize_string($input);
    return $input;
}

// Output escaping functions
function escape_html($value) {
    if (!is_string($value)) return '';
    return htmlspecialchars($value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
}

function escape_js($value) {
    if (!is_string($value)) return '';
    return json_encode($value);
}

function escape_url($value) {
    if (!is_string($value)) return '';
    return urlencode($value);
}

function escape_attr($value) {
    if (!is_string($value)) return '';
    return htmlspecialchars($value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
}

// Content security policy headers
function set_security_headers() {
    header("Content-Security-Policy: default-src 'self'; script-src 'self' 'unsafe-inline' 'unsafe-eval'; style-src 'self' 'unsafe-inline'; img-src 'self' data:; font-src 'self'; connect-src 'self'");
    header("X-Content-Type-Options: nosniff");
    header("X-Frame-Options: SAMEORIGIN");
    header("X-XSS-Protection: 1; mode=block");
    header("Referrer-Policy: strict-origin-when-cross-origin");
} 