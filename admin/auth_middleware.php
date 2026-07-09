<?php
// admin/auth_middleware.php - Authentication middleware for admin panel

require_once __DIR__ . '/../core/birkly_paths.php';
require_once __DIR__ . '/../api/auth.php';
require_once __DIR__ . '/../api/session.php';

// Start secure session
haru_secure_session_start();

require_once __DIR__ . '/../core/csrf.php';

// Check if user is authenticated
function requireAdminAuth() {
    // Check if user is logged in
    if (!isset($_SESSION['user'])) {
        // Check if we're on onboarding page and no users exist - allow access
        $page = $_GET['page'] ?? '';
        if ($page === 'onboarding') {
            $GLOBALS['__HARU_INTERNAL_CALL'] = true;
            require_once __DIR__ . '/../api/onboarding.php';
            if (!hasUsers()) {
                // No users exist and we're on onboarding - allow access for first user creation
                return true;
            }
        }
        
        // Redirect to login page
        $loginUrl = birkly_url('/admin/login.php');
        if (isset($_GET['page']) && isset($_SERVER['REQUEST_URI'])) {
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
        header('Location: ' . birkly_url('/admin/login.php?timeout=1'));
        exit;
    }
    
    // Update last activity
    $_SESSION['last_activity'] = $now;

    require_once __DIR__ . '/../core/security_policy.php';
    birkly_enforce_2fa_policy_for_admin_page();
    
    // Check if user has admin access
    $userRole = $_SESSION['user']['role'] ?? 'viewer';
    if (!in_array($userRole, ['admin', 'editor', 'viewer'])) {
        header('Location: ' . birkly_url('/admin/login.php?error=access_denied'));
        exit;
    }
    
    return true;
}

// Check specific permission
function requireAdminPermission($permission) {
    requireAdminAuth(); // First ensure user is authenticated
    
    if (!checkPermission($_SESSION['user'], $permission)) {
        // UX: For MPA navigation, redirect back to dashboard and show a unified modal there.
        // API endpoints still return JSON 403; this is only for page navigation.
        $fromPage = isset($_GET['page']) ? (string)$_GET['page'] : '';
        $redirectParams = ['permission_denied' => '1'];
        if ($permission) {
            $redirectParams['needed'] = (string) $permission;
        }
        if ($fromPage) {
            $redirectParams['from'] = $fromPage;
        }
        $redirect = birkly_admin_url('dashboard', $redirectParams);

        if (!headers_sent()) {
            header('Location: ' . $redirect);
            exit;
        }

        // Fallback if headers already sent: minimal HTML.
        http_response_code(403);
        echo '<div class="error-page">
                <h1>Access Denied</h1>
                <p>You do not have permission to access this page.</p>
                <a href="' . htmlspecialchars($redirect) . '" class="btn btn-primary">Back</a>
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
