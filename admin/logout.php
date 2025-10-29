<?php
// admin/logout.php - Logout handler for admin panel

session_start();

// Include CSRF helper
require_once __DIR__ . '/../core/csrf.php';

// Handle logout
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrf = $_POST['_csrf'] ?? '';
    
    // Validate CSRF token
    if (!validate_csrf_token($csrf)) {
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => 'Invalid security token']);
        exit;
    }
    
    // Log the logout event
    if (isset($_SESSION['user'])) {
        require_once __DIR__ . '/../api/log.php';
        log_security_event('logout', ['email' => $_SESSION['user']['email'] ?? 'unknown']);
    }
    
    // Destroy session
    session_unset();
    session_destroy();
    
    // Return success
    echo json_encode(['status' => 'ok', 'message' => 'Logged out successfully']);
    exit;
}

// If not POST request, redirect to login
header('Location: login.php');
exit;
