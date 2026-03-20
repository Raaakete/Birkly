<?php
error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../logs/php_errors.log');
// Secure session initialization for HaruCMS

function haru_secure_session_start() {
    $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || ($_SERVER['SERVER_PORT'] ?? null) == 443;
    $params = session_get_cookie_params();
    session_set_cookie_params([
        'lifetime' => $params['lifetime'],
        'path' => $params['path'],
        'domain' => $params['domain'],
        'secure' => $secure,
        'httponly' => true,
        'samesite' => 'Strict'
    ]);
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
}

/**
 * Start the site (frontend) session so user_current / user_logged_in see the website user.
 * Used by public API and demo site so template functions see the logged-in demo user (e.g. Alice),
 * not the admin session (e.g. CMS admin).
 */
function birkly_site_session_start() {
    if (session_status() !== PHP_SESSION_NONE) {
        return;
    }
    $params = session_get_cookie_params();
    $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || ($_SERVER['SERVER_PORT'] ?? null) == 443;
    session_name('birkly_site_sid');
    session_set_cookie_params([
        'lifetime' => $params['lifetime'],
        'path' => '/',
        'domain' => $params['domain'],
        'secure' => $secure,
        'httponly' => true,
        'samesite' => 'Strict'
    ]);
    session_start();
}

/**
 * Start the admin session (CMS panel). Used by admin panel only.
 */
function birkly_admin_session_start() {
    if (session_status() !== PHP_SESSION_NONE) {
        return;
    }
    $params = session_get_cookie_params();
    $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || ($_SERVER['SERVER_PORT'] ?? null) == 443;
    session_name('birkly_admin_sid');
    session_set_cookie_params([
        'lifetime' => $params['lifetime'],
        'path' => '/',
        'domain' => $params['domain'],
        'secure' => $secure,
        'httponly' => true,
        'samesite' => 'Strict'
    ]);
    session_start();
} 