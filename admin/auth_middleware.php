<?php
// admin/auth_middleware.php - Authentication middleware for admin panel

require_once __DIR__ . '/../api/auth.php';
require_once __DIR__ . '/../api/session.php';

// Start secure session
haru_secure_session_start();

// Check if user is authenticated
function requireAdminAuth() {
    // Check if user is logged in
    if (!isset($_SESSION['user'])) {
        // Redirect to login page
        $loginUrl = '/admin/login.php';
        if (isset($_GET['page'])) {
            $loginUrl .= '?redirect=' . urlencode($_SERVER['REQUEST_URI']);
        }
        header('Location: ' . $loginUrl);
        exit;
    }
    
    // Check session timeout
    $timeout = getSessionTimeout();
    $now = time();
    if (isset($_SESSION['last_activity']) && ($now - $_SESSION['last_activity']) > $timeout) {
        session_unset();
        session_destroy();
        header('Location: /admin/login.php?timeout=1');
        exit;
    }
    
    // Update last activity
    $_SESSION['last_activity'] = $now;
    
    // Check if user has admin access
    $userRole = $_SESSION['user']['role'] ?? 'viewer';
    if (!in_array($userRole, ['admin', 'editor', 'viewer'])) {
        header('Location: /admin/login.php?error=access_denied');
        exit;
    }
    
    return true;
}

// Check specific permission
function requireAdminPermission($permission) {
    requireAdminAuth(); // First ensure user is authenticated
    
    if (!checkPermission($_SESSION['user'], $permission)) {
        http_response_code(403);
        echo '<div class="error-page">
                <h1>Access Denied</h1>
                <p>You do not have permission to access this page.</p>
                <a href="/admin/index.php?page=dashboard" class="btn btn-primary">Back to Dashboard</a>
              </div>';
        exit;
    }
    
    return true;
}

// Helper function to get current user info
function getCurrentUser() {
    return $_SESSION['user'] ?? null;
}

// Helper function to check if user has specific role
function hasRole($role) {
    $user = getCurrentUser();
    return $user && $user['role'] === $role;
}

// Helper function to check if user has admin privileges
function isAdmin() {
    return hasRole('admin');
}

// Helper function to check if user has editor privileges
function isEditor() {
    $user = getCurrentUser();
    return $user && in_array($user['role'], ['admin', 'editor']);
}
