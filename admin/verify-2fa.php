<?php
// admin/verify-2fa.php - 2FA verification page

require_once __DIR__ . '/../core/birkly_paths.php';
require_once __DIR__ . '/../api/session.php';
haru_secure_session_start();

require_once __DIR__ . '/../core/csrf.php';
// IMPORTANT: api/*.php files often auto-execute as JSON endpoints unless marked as internal.
// This page must be accessible before a full admin session exists.
$GLOBALS['__HARU_INTERNAL_CALL'] = true;
require_once __DIR__ . '/../api/2fa.php';
// NOTE: Do NOT include api/users.php here — it parses php://input as JSON on include
// and can break this form POST with "Invalid JSON input".

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
    // NOTE: use_backup_code is always present as a hidden input ("0"/"1")
    $useBackupCode = (($_POST['use_backup_code'] ?? '0') === '1');
    
    if (!validate_csrf_token($csrf)) {
        $error = 'Invalid security token. Please try again.';
    } elseif (empty($code) && empty($_POST['backup_code'] ?? '')) {
        $error = 'Please enter your verification code.';
    } else {
        // Verify 2FA code (use backup code field if available)
        $backupRaw = trim($_POST['backup_code'] ?? '');
        if ($useBackupCode && $backupRaw !== '') {
            // Backup codes are stored as 8 hex chars; accept with spaces/dashes and normalize
            $codeToVerify = strtoupper(preg_replace('/[^A-Fa-f0-9]/', '', $backupRaw));
        } else {
            // TOTP: normalize to digits only (users sometimes paste with spaces)
            $codeToVerify = preg_replace('/\D/', '', $code);
        }
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
                    $redirect = $_GET['redirect'] ?? birkly_admin_url('dashboard');
                    if (!preg_match('/^https?:\/\//', $redirect) && !str_starts_with($redirect, '/')) {
                        $redirect = birkly_url('/admin/' . ltrim($redirect, '/'));
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
    <title>Verify Two-Factor Authentication - Birkly Admin</title>
    <link rel="stylesheet" href="<?= htmlspecialchars(birkly_url('/admin/css/style.css')) ?>">
    <link rel="shortcut icon" href="assets/birkly_favicon.svg">
    <style>
        body.auth-page {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: var(--background-global, var(--color-neutral-100, #f8f9f7));
        }

        .auth-card {
            background: var(--bg-elevated);
            border: 1px solid var(--border-light);
            border-radius: var(--border-radius-m, 8px);
            box-shadow: var(--shadow-md, 0 4px 6px -1px rgba(0, 0, 0, 0.1));
            padding: 3rem;
            width: 100%;
            max-width: 400px;
            margin: 2rem;
        }

        .auth-card__header {
            text-align: center;
            margin-bottom: 2rem;
        }

        .auth-card__header h1 {
            color: var(--text-primary);
            margin: 0 0 0.5rem 0;
            font-size: 1.875rem;
            font-weight: 700;
        }

        .auth-card__header p {
            color: var(--text-secondary);
            margin: 0;
            font-size: 0.875rem;
        }

        .auth-form {
            display: flex;
            flex-direction: column;
            gap: 1.5rem;
        }

        .auth-form .form-group {
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
            margin-bottom: 0;
        }

        .auth-form .form-group label {
            font-weight: 600;
            color: var(--text-primary);
            font-size: 0.875rem;
        }

        .auth-form .form-group input {
            padding: 0.75rem 1rem;
            border: 1px solid var(--border-color);
            border-radius: 8px;
            background: var(--bg-primary);
            color: var(--text-primary);
            font-size: 1.5rem;
            text-align: center;
            letter-spacing: 0.5rem;
            transition: border-color 0.2s, box-shadow 0.2s;
        }

        .auth-form .form-group input:focus {
            outline: none;
            border-color: var(--color-primary, var(--primary-500));
            box-shadow: 0 0 0 3px rgba(124, 227, 191, 0.25);
        }

        .auth-form .verify-button {
            background: var(--color-primary);
            color: var(--color-darkest, #4a4d47);
            border: none;
            padding: 0.875rem 1.5rem;
            border-radius: 8px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: background 0.2s;
        }

        .auth-form .verify-button:hover {
            background: var(--color-primary-hover);
        }

        .auth-form .verify-button:active {
            background: var(--color-primary-active);
        }

        .auth-card .message {
            margin-bottom: 1.5rem;
        }

        .backup-code-toggle {
            text-align: center;
            margin-top: -1rem;
        }

        .backup-code-toggle a {
            color: var(--color-primary, var(--primary-600));
            text-decoration: none;
            font-size: 0.875rem;
            cursor: pointer;
            font-weight: 500;
        }

        .backup-code-toggle a:hover {
            color: var(--primary-700);
            text-decoration: underline;
        }

        .backup-code-help {
            background: var(--primary-50);
            border: 1px solid var(--primary-200);
            padding: 1rem;
            border-radius: 8px;
            font-size: 0.875rem;
            color: var(--text-secondary);
        }

        .backup-code-help h4 {
            margin: 0 0 0.5rem 0;
            font-size: 0.875rem;
            color: var(--text-primary);
        }

        .backup-code-help input {
            width: 100%;
            padding: 0.5rem;
            border: 1px solid var(--border-color);
            border-radius: 4px;
            font-size: 0.875rem;
            font-family: monospace;
            text-align: center;
            letter-spacing: 0.25rem;
            background: var(--bg-primary);
            color: var(--text-primary);
        }

        .auth-card__footer {
            text-align: center;
            margin-top: 2rem;
            padding-top: 2rem;
            border-top: 1px solid var(--border-light);
        }

        .auth-card__footer a {
            color: var(--text-secondary);
            text-decoration: none;
            font-size: 0.875rem;
        }

        .auth-card__footer a:hover {
            color: var(--color-primary, var(--primary-600));
        }
    </style>
</head>
<body class="auth-page">
    <div class="auth-card verify-container">
        <div class="auth-card__header verify-header">
            <h1>Two-Factor Authentication</h1>
            <p>Enter the verification code from your authenticator app</p>
        </div>
        
        <?php if ($error): ?>
            <div class="message error" role="alert">
                <div class="message-content">
                    <p class="message-text"><?= htmlspecialchars($error) ?></p>
                </div>
            </div>
        <?php endif; ?>
        
        <?php if ($success): ?>
            <div class="message success" role="status">
                <div class="message-content">
                    <p class="message-text"><?= htmlspecialchars($success) ?></p>
                </div>
            </div>
        <?php endif; ?>
        
        <form class="auth-form verify-form" method="POST" id="verify-form">
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
        
        <div class="auth-card__footer">
            <a href="login.php">Back to login</a>
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

