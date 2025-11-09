<?php
// admin/login.php - Admin login page for HaruCMS

// CRITICAL: Set Content-Type header FIRST before anything else
// This ensures it's sent even if session_start() or includes output something
if (!headers_sent()) {
    header('Content-Type: text/html; charset=utf-8');
}

// CRITICAL: Start output buffering AFTER setting Content-Type
// This prevents any accidental output from interfering with headers
ob_start();

// Now include dependencies (output buffering will catch any output)
require_once __DIR__ . '/../api/session.php';
haru_secure_session_start();

// Include CSRF helper
require_once __DIR__ . '/../core/csrf.php';

// Ensure Content-Type is still set (in case something cleared it)
if (!headers_sent()) {
    header('Content-Type: text/html; charset=utf-8');
}

// If user is already logged in, redirect to dashboard
// Use absolute path to ensure correct routing
if (isset($_SESSION['user'])) {
    // Clear output buffer if exists
    if (ob_get_level() > 0) {
        ob_clean();
    }
    if (!headers_sent()) {
        http_response_code(302);
        header('Location: /admin/index.php?page=dashboard');
    }
    exit;
}

$error = '';
$success = '';

// Check if no users exist - redirect to onboarding for first user creation
$GLOBALS['__HARU_INTERNAL_CALL'] = true;
require_once __DIR__ . '/../api/onboarding.php';
if (!hasUsers()) {
    // No users exist - redirect to onboarding to create first user
    if (ob_get_level() > 0) {
        ob_clean();
    }
    if (!headers_sent()) {
        http_response_code(302);
        header('Location: /admin/index.php?page=onboarding');
    }
    exit;
}

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
            // Set flag to prevent API from executing switch statement
            $GLOBALS['__HARU_INTERNAL_CALL'] = true;
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
                    if (ob_get_level() > 0) {
                        ob_clean();
                    }
                    if (!headers_sent()) {
                        http_response_code(302);
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
            // CRITICAL: Clear output buffer before redirect
            // Check if output buffer exists before trying to clean it
            if (ob_get_level() > 0) {
                ob_clean(); // Clear buffer content but keep buffer active
            }
            // Now set redirect header
            if (!headers_sent()) {
                http_response_code(302); // Explicitly set redirect status FIRST
                header('Location: ' . $redirect);
            }
            // Exit immediately - output buffer will be automatically discarded
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

// Helper function to sanitize filename
// Note: This function is also in api/2fa.php, so we check if it exists first
if (!function_exists('sanitizeFilename')) {
    function sanitizeFilename($filename) {
        return preg_replace('/[^a-z0-9._-]/i', '_', $filename);
    }
}

// Load branding settings for logo
require_once __DIR__ . '/../api/storage.php';
$storage = new Storage(__DIR__ . '/../settings');
$adminSettings = $storage->read('admin.json') ?? [];
$brandingSettings = $storage->read('branding.json') ?? [];

$projectName = $adminSettings['project_name'] ?? 'HaruCMS';
$logoPath = $brandingSettings['logoPath'] ?? '';
$defaultLogo = '/admin/assets/haru-icon.svg';
$logoUrl = !empty($logoPath) ? $logoPath : $defaultLogo;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="<?php echo get_csrf_token(); ?>">
    <title>Login - <?= htmlspecialchars($projectName) ?> Admin</title>
    <link rel="stylesheet" href="/admin/css/style.css">
    <link rel="shortcut icon" href="<?= htmlspecialchars($logoUrl) ?>">
    <style>
        :root {
            --color-primary: #FFFB00;
            --color-secondary: #A1B0A1;
            --color-darkest: #4a4d47;
            --color-dark: #8e928b;
            --color-mid: #000;
            --color-light: #dce2d7;
            --color-lightest: #f4f6f1;
            --color-positiv: #e4f1ec;
            --color-negativ: #f3eced;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            position: relative;
            overflow: hidden;
            background: var(--color-lightest);
        }

        /* Animated Background Canvas */
        .background {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            z-index: 0;
        }

        .background canvas {
            display: block;
            width: 100%;
            height: 100%;
        }

        .login-container {
            position: relative;
            z-index: 1;
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-radius: 16px;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
            padding: 3rem;
            width: 100%;
            max-width: 420px;
            margin: 2rem;
            border: 1px solid rgba(255, 255, 255, 0.2);
        }
        
        .login-header {
            text-align: center;
            margin-bottom: 2rem;
        }

        .login-logo {
            width: 64px;
            height: 64px;
            margin: 0 auto 1rem;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .login-logo img {
            width: 100%;
            height: 100%;
            object-fit: contain;
        }
        
        .login-header h1 {
            color: var(--color-darkest);
            margin: 0 0 0.5rem 0;
            font-size: 1.875rem;
            font-weight: 700;
        }
        
        .login-header p {
            color: var(--color-dark);
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
            color: var(--color-darkest);
            font-size: 0.875rem;
        }
        
        .form-group input {
            padding: 0.75rem 1rem;
            border: 2px solid var(--color-light);
            border-radius: 8px;
            font-size: 1rem;
            transition: all 0.2s ease;
            background: white;
            color: var(--color-darkest);
        }
        
        .form-group input:focus {
            outline: none;
            border-color: var(--color-primary);
            box-shadow: 0 0 0 3px rgba(255, 251, 0, 0.1);
        }
        
        .login-button {
            background: var(--color-primary);
            color: var(--color-darkest);
            border: none;
            padding: 0.875rem 1.5rem;
            border-radius: 8px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s ease;
            margin-top: 0.5rem;
        }
        
        .login-button:hover {
            background: #fff700;
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(255, 251, 0, 0.3);
        }
        
        .login-button:active {
            transform: translateY(0);
        }
        
        .error-message {
            background: var(--color-negativ);
            color: #c53030;
            padding: 0.75rem 1rem;
            border-radius: 8px;
            font-size: 0.875rem;
            border: 1px solid #feb2b2;
        }
        
        .success-message {
            background: var(--color-positiv);
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
            border-top: 1px solid var(--color-light);
        }
        
        .login-footer p {
            color: var(--color-dark);
            font-size: 0.875rem;
            margin: 0;
        }
        
        .login-footer a {
            color: var(--color-primary);
            text-decoration: none;
            font-weight: 600;
        }
        
        .login-footer a:hover {
            text-decoration: underline;
        }

        @media (max-width: 480px) {
            .login-container {
                padding: 2rem 1.5rem;
                margin: 1rem;
            }
        }
    </style>
</head>
<body>
    <div class="background">
        <canvas id="background-canvas"></canvas>
    </div>

    <div class="login-container">
        <div class="login-header">
            <div class="login-logo">
                <img src="<?= htmlspecialchars($logoUrl) ?>" alt="<?= htmlspecialchars($projectName) ?> Logo">
            </div>
            <h1>Welcome Back</h1>
            <p>Sign in to your <?= htmlspecialchars($projectName) ?> admin account</p>
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
        // Animated Background Canvas - CodePen style wave animation
        (function() {
            const canvas = document.getElementById('background-canvas');
            const ctx = canvas.getContext('2d');
            
            // Admin panel colors
            const colors = [
                '#FFFB00',  // primary - yellow
                '#A1B0A1',  // secondary - green-gray
                '#e4f1ec', // positiv - light green
                '#dce2d7', // light - light gray-green
                '#f4f6f1'  // lightest - very light green
            ];
            
            let time = 0;
            const waves = [];
            
            // Create multiple wave objects
            for (let i = 0; i < 4; i++) {
                waves.push({
                    amplitude: 50 + Math.random() * 50,
                    frequency: 0.01 + Math.random() * 0.02,
                    speed: 0.5 + Math.random() * 0.5,
                    color: colors[i % colors.length],
                    offset: Math.random() * Math.PI * 2,
                    y: (canvas.height / 5) * (i + 1)
                });
            }
            
            function resizeCanvas() {
                canvas.width = window.innerWidth;
                canvas.height = window.innerHeight;
                
                // Update wave positions
                waves.forEach((wave, i) => {
                    wave.y = (canvas.height / 5) * (i + 1);
                });
            }
            
            function drawWave(wave) {
                ctx.beginPath();
                ctx.moveTo(0, wave.y);
                
                for (let x = 0; x < canvas.width; x++) {
                    const y = wave.y + 
                        wave.amplitude * Math.sin(x * wave.frequency + time * wave.speed + wave.offset);
                    ctx.lineTo(x, y);
                }
                
                // Fill to bottom
                ctx.lineTo(canvas.width, canvas.height);
                ctx.lineTo(0, canvas.height);
                ctx.closePath();
                
                // Create gradient
                const gradient = ctx.createLinearGradient(0, wave.y, 0, canvas.height);
                gradient.addColorStop(0, wave.color);
                gradient.addColorStop(1, wave.color + '00'); // Fade to transparent
                
                ctx.fillStyle = wave.color;
                ctx.globalAlpha = 0.6;
                ctx.fill();
                ctx.globalAlpha = 1.0;
            }
            
            function animate() {
                ctx.clearRect(0, 0, canvas.width, canvas.height);
                
                // Draw background
                ctx.fillStyle = '#f4f6f1';
                ctx.fillRect(0, 0, canvas.width, canvas.height);
                
                // Draw all waves
                waves.forEach(wave => {
                    drawWave(wave);
                });
                
                time += 0.02;
                
                requestAnimationFrame(animate);
            }
            
            resizeCanvas();
            animate();
            
            window.addEventListener('resize', resizeCanvas);
        })();
        
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
