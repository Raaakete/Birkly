<?php
// admin/verify-2fa.php - 2FA verification page

require_once __DIR__ . '/../api/session.php';
haru_secure_session_start();

require_once __DIR__ . '/../core/csrf.php';
require_once __DIR__ . '/../api/2fa.php';
require_once __DIR__ . '/../api/users.php';

// Check if 2FA verification is pending
if (!isset($_SESSION['2fa_pending'])) {
    header('Location: login.php');
    exit;
}

$email = $_SESSION['2fa_pending'];
$error = '';
$success = '';

// Handle verification form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $code = trim($_POST['code'] ?? '');
    $csrf = $_POST['_csrf'] ?? '';
    $useBackupCode = isset($_POST['use_backup_code']);
    
    if (!validate_csrf_token($csrf)) {
        $error = 'Invalid security token. Please try again.';
    } elseif (empty($code)) {
        $error = 'Please enter your verification code.';
    } else {
        // Verify 2FA code (use backup code field if available)
        $codeToVerify = $useBackupCode && !empty($_POST['backup_code']) ? $_POST['backup_code'] : $code;
        if (verify2FALogin($email, $codeToVerify, $useBackupCode)) {
            // 2FA verified - complete login
            $userFile = md5($email) . '.json';
            $usersDir = __DIR__ . '/../users';
            
            if (file_exists($usersDir . '/' . $userFile)) {
                $user = json_decode(file_get_contents($usersDir . '/' . $userFile), true);
                if ($user) {
                    unset($user['password']);
                    $_SESSION['user'] = $user;
                    $_SESSION['last_activity'] = time();
                    unset($_SESSION['2fa_pending']);
                    
                    // Redirect to dashboard
                    // Use absolute path to ensure correct routing
                    $redirect = $_GET['redirect'] ?? '/admin/index.php?page=dashboard';
                    // If redirect is relative, make it absolute
                    if (!preg_match('/^https?:\/\//', $redirect) && !str_starts_with($redirect, '/')) {
                        $redirect = '/admin/' . $redirect;
                    }
                    header('Location: ' . $redirect);
                    exit;
                }
            }
            
            $error = 'Failed to complete login. Please try again.';
        } else {
            $error = 'Invalid verification code. Please try again.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="<?php echo get_csrf_token(); ?>">
    <title>Verify Two-Factor Authentication - HaruCMS Admin</title>
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
        
        .verify-container {
            background: white;
            border-radius: 12px;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.1);
            padding: 3rem;
            width: 100%;
            max-width: 400px;
            margin: 2rem;
        }
        
        .verify-header {
            text-align: center;
            margin-bottom: 2rem;
        }
        
        .verify-header h1 {
            color: #2d3748;
            margin: 0 0 0.5rem 0;
            font-size: 1.875rem;
            font-weight: 700;
        }
        
        .verify-header p {
            color: #718096;
            margin: 0;
            font-size: 0.875rem;
        }
        
        .verify-form {
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
            font-size: 1.5rem;
            text-align: center;
            letter-spacing: 0.5rem;
            transition: border-color 0.2s;
        }
        
        .form-group input:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }
        
        .verify-button {
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
        
        .verify-button:hover {
            background: #5a67d8;
        }
        
        .verify-button:active {
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
        
        .backup-code-toggle {
            text-align: center;
            margin-top: -1rem;
        }
        
        .backup-code-toggle a {
            color: #667eea;
            text-decoration: none;
            font-size: 0.875rem;
            cursor: pointer;
        }
        
        .backup-code-toggle a:hover {
            text-decoration: underline;
        }
        
        .backup-code-help {
            background: #edf2f7;
            padding: 1rem;
            border-radius: 8px;
            font-size: 0.875rem;
            color: #4a5568;
        }
        
        .backup-code-help h4 {
            margin: 0 0 0.5rem 0;
            font-size: 0.875rem;
            color: #2d3748;
        }
        
        .backup-code-help input {
            width: 100%;
            padding: 0.5rem;
            border: 1px solid #cbd5e0;
            border-radius: 4px;
            font-size: 0.875rem;
            font-family: monospace;
            text-align: center;
            letter-spacing: 0.25rem;
        }
    </style>
</head>
<body>
    <div class="verify-container">
        <div class="verify-header">
            <h1>Two-Factor Authentication</h1>
            <p>Enter the verification code from your authenticator app</p>
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
        
        <form class="verify-form" method="POST" id="verify-form">
            <input type="hidden" name="_csrf" value="<?= get_csrf_token() ?>">
            <input type="hidden" name="use_backup_code" id="use_backup_code" value="0">
            
            <div class="form-group">
                <label for="code">Verification Code</label>
                <input 
                    type="text" 
                    id="code" 
                    name="code" 
                    required 
                    autocomplete="one-time-code"
                    placeholder="000000"
                    maxlength="8"
                    autofocus
                >
            </div>
            
            <button type="submit" class="verify-button" id="verify-button">
                Verify
            </button>
            
            <div class="backup-code-toggle">
                <a href="#" id="toggle-backup" onclick="toggleBackupCode(); return false;">Use backup code instead</a>
            </div>
            
            <div id="backup-code-section" style="display: none;">
                <div class="backup-code-help">
                    <h4>Enter a backup code</h4>
                    <p style="margin: 0.5rem 0;">If you've lost access to your authenticator app, you can use one of your backup codes.</p>
                    <input 
                        type="text" 
                        id="backup_code" 
                        name="backup_code" 
                        placeholder="XXXXXXXX"
                        maxlength="8"
                        style="margin-top: 0.5rem;"
                    >
                </div>
            </div>
        </form>
        
        <div style="text-align: center; margin-top: 2rem; padding-top: 2rem; border-top: 1px solid #e2e8f0;">
            <a href="login.php" style="color: #718096; text-decoration: none; font-size: 0.875rem;">Back to login</a>
        </div>
    </div>
    
    <script>
        function toggleBackupCode() {
            const section = document.getElementById('backup-code-section');
            const codeInput = document.getElementById('code');
            const backupInput = document.getElementById('backup_code');
            const useBackupInput = document.getElementById('use_backup_code');
            const toggleLink = document.getElementById('toggle-backup');
            
            if (section.style.display === 'none') {
                section.style.display = 'block';
                codeInput.style.display = 'none';
                document.querySelector('.form-group label').textContent = 'Backup Code';
                useBackupInput.value = '1';
                toggleLink.textContent = 'Use authenticator app instead';
                backupInput.focus();
            } else {
                section.style.display = 'none';
                codeInput.style.display = 'block';
                document.querySelector('.form-group label').textContent = 'Verification Code';
                useBackupInput.value = '0';
                toggleLink.textContent = 'Use backup code instead';
                codeInput.focus();
            }
        }
        
        // Auto-format code input
        document.getElementById('code').addEventListener('input', function(e) {
            this.value = this.value.replace(/[^0-9]/g, '').substring(0, 6);
        });
        
        document.getElementById('backup_code').addEventListener('input', function(e) {
            this.value = this.value.toUpperCase().replace(/[^A-Z0-9]/g, '').substring(0, 8);
        });
        
        // Handle form submission
        document.getElementById('verify-form').addEventListener('submit', function(e) {
            const button = document.getElementById('verify-button');
            button.textContent = 'Verifying...';
            button.disabled = true;
        });
    </script>
</body>
</html>

