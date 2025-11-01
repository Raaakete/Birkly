<?php
// admin/login.php - Admin login page for HaruCMS

// CRITICAL: Start output buffering FIRST to ensure headers can be sent
// This prevents any accidental output from interfering with headers
ob_start();

// Now include dependencies (before setting Content-Type to catch any output)
require_once __DIR__ . '/../api/session.php';
haru_secure_session_start();

// Include CSRF helper
require_once __DIR__ . '/../core/csrf.php';

// CRITICAL: Set Content-Type header AFTER includes (in case they output)
// But before any HTML output
if (!headers_sent()) {
    header('Content-Type: text/html; charset=utf-8');
}

// If user is already logged in, redirect to dashboard
// Use absolute path to ensure correct routing
if (isset($_SESSION['user'])) {
    header('Location: /admin/index.php?page=dashboard');
    exit;
}

$error = '';
$success = '';

// Handle login form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $csrf = $_POST['_csrf'] ?? '';
    
    // Validate CSRF token (simplified for now)
    if (!validate_csrf_token($csrf)) {
        $error = 'Invalid security token. Please try again.';
    } elseif (empty($email) || empty($password)) {
        $error = 'Please enter both email and password.';
    } else {
        // Include authentication functions
        require_once __DIR__ . '/../api/auth.php';
        require_once __DIR__ . '/../api/users.php';
        
        // Attempt login
        $loginResult = attemptLogin($email, $password);
        
        if ($loginResult['success']) {
            // Check if 2FA is enabled for this user
            require_once __DIR__ . '/../api/2fa.php';
            $usersDir = __DIR__ . '/../data/users';
            $userFile = $usersDir . '/' . sanitizeFilename($email) . '.json';
            
            if (file_exists($userFile)) {
                $userData = json_decode(file_get_contents($userFile), true);
                if (isset($userData['2fa']['enabled']) && $userData['2fa']['enabled']) {
                    // Store email in session for 2FA verification
                    $_SESSION['2fa_pending'] = $email;
                    // Don't set user session yet - require 2FA verification first
                    unset($_SESSION['user']);
                    // CRITICAL: Clear output buffer before redirect
                    ob_clean();
                    if (!headers_sent()) {
                        header('Location: /admin/verify-2fa.php');
                    }
                    exit;
                }
            }
            
            // No 2FA - proceed with normal login
            // Redirect to dashboard or original page
            // Use absolute path to ensure correct routing
            $redirect = $_GET['redirect'] ?? '/admin/index.php?page=dashboard';
            // If redirect is relative, make it absolute
            if (!preg_match('/^https?:\/\//', $redirect) && !str_starts_with($redirect, '/')) {
                $redirect = '/admin/' . $redirect;
            }
            // CRITICAL: Clear output buffer and ensure headers can be sent
            ob_clean();
            if (!headers_sent()) {
                header('Location: ' . $redirect);
                header('Content-Type: text/html; charset=utf-8'); // Some browsers need this
                http_response_code(302); // Explicitly set redirect status
            }
            exit;
        } else {
            $error = $loginResult['message'];
        }
    }
}

// Helper function to attempt login
function attemptLogin($email, $password) {
    $email = trim(strtolower($email));
    $userFile = md5($email) . '.json';
    $usersDir = __DIR__ . '/../users';
    
    if (!file_exists($usersDir . '/' . $userFile)) {
        return ['success' => false, 'message' => 'Invalid email or password.'];
    }
    
    $user = json_decode(file_get_contents($usersDir . '/' . $userFile), true);
    if (!$user) {
        return ['success' => false, 'message' => 'Invalid email or password.'];
    }
    
    if (!password_verify($password, $user['password'])) {
        return ['success' => false, 'message' => 'Invalid email or password.'];
    }
    
    // Login successful - set session
    unset($user['password']); // Don't store password in session
    $_SESSION['user'] = $user;
    $_SESSION['last_activity'] = time();
    
    return ['success' => true, 'message' => 'Login successful.'];
}

// Helper function to sanitize filename (duplicate from 2fa.php - should be in common file)
function sanitizeFilename($filename) {
    return preg_replace('/[^a-z0-9._-]/i', '_', $filename);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="<?php echo get_csrf_token(); ?>">
    <title>Login - HaruCMS Admin</title>
    <link rel="stylesheet" href="/admin/css/style.css">
    <link rel="shortcut icon" href="assets/haru_favicon.svg">
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
        }
        
        .login-container {
            background: white;
            border-radius: 12px;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.1);
            padding: 3rem;
            width: 100%;
            max-width: 400px;
            margin: 2rem;
        }
        
        .login-header {
            text-align: center;
            margin-bottom: 2rem;
        }
        
        .login-header h1 {
            color: #2d3748;
            margin: 0 0 0.5rem 0;
            font-size: 1.875rem;
            font-weight: 700;
        }
        
        .login-header p {
            color: #718096;
            margin: 0;
            font-size: 0.875rem;
        }
        
        .login-form {
            display: flex;
            flex-direction: column;
            gap: 1.5rem;
        }
        
        .form-group {
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
        }
        
        .form-group label {
            font-weight: 600;
            color: #2d3748;
            font-size: 0.875rem;
        }
        
        .form-group input {
            padding: 0.75rem 1rem;
            border: 2px solid #e2e8f0;
            border-radius: 8px;
            font-size: 1rem;
            transition: border-color 0.2s;
        }
        
        .form-group input:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }
        
        .login-button {
            background: #667eea;
            color: white;
            border: none;
            padding: 0.875rem 1.5rem;
            border-radius: 8px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: background-color 0.2s;
        }
        
        .login-button:hover {
            background: #5a67d8;
        }
        
        .login-button:active {
            background: #4c51bf;
        }
        
        .error-message {
            background: #fed7d7;
            color: #c53030;
            padding: 0.75rem 1rem;
            border-radius: 8px;
            font-size: 0.875rem;
            border: 1px solid #feb2b2;
        }
        
        .success-message {
            background: #c6f6d5;
            color: #2f855a;
            padding: 0.75rem 1rem;
            border-radius: 8px;
            font-size: 0.875rem;
            border: 1px solid #9ae6b4;
        }
        
        .login-footer {
            text-align: center;
            margin-top: 2rem;
            padding-top: 2rem;
            border-top: 1px solid #e2e8f0;
        }
        
        .login-footer p {
            color: #718096;
            font-size: 0.875rem;
            margin: 0;
        }
        
        .login-footer a {
            color: #667eea;
            text-decoration: none;
            font-weight: 600;
        }
        
        .login-footer a:hover {
            text-decoration: underline;
        }
        
        .logo {
            width: 60px;
            height: 60px;
            margin: 0 auto 1rem;
            background: #667eea;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 1.5rem;
            font-weight: bold;
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="login-header">
            <div class="logo">H</div>
            <h1>Welcome Back</h1>
            <p>Sign in to your HaruCMS admin account</p>
        </div>
        
        <?php if ($error): ?>
            <div class="error-message">
                <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>
        
        <?php if ($success): ?>
            <div class="success-message">
                <?= htmlspecialchars($success) ?>
            </div>
        <?php endif; ?>
        
        <form class="login-form" method="POST">
            <input type="hidden" name="_csrf" value="<?= get_csrf_token() ?>">
            
            <div class="form-group">
                <label for="email">Email Address</label>
                <input 
                    type="email" 
                    id="email" 
                    name="email" 
                    value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"
                    required 
                    autocomplete="email"
                    placeholder="admin@example.com"
                >
            </div>
            
            <div class="form-group">
                <label for="password">Password</label>
                <input 
                    type="password" 
                    id="password" 
                    name="password" 
                    required 
                    autocomplete="current-password"
                    placeholder="Enter your password"
                >
            </div>
            
            <button type="submit" class="login-button">
                Sign In
            </button>
        </form>
        
        <div class="login-footer">
            <p>Need help? <a href="#" onclick="alert('Contact your system administrator')">Contact Support</a></p>
        </div>
    </div>
    
    <script>
        // Auto-focus email field
        document.getElementById('email').focus();
        
        // Handle form submission
        document.querySelector('.login-form').addEventListener('submit', function(e) {
            const button = document.querySelector('.login-button');
            button.textContent = 'Signing In...';
            button.disabled = true;
        });
    </script>
</body>
</html>
<?php
// End output buffering and send all output
ob_end_flush();
?>
