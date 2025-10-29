<?php
// admin/reset-password.php - Password reset for admin users
session_start();

// Include CSRF helper
require_once __DIR__ . '/../core/csrf.php';

// If user is not logged in, redirect to login
if (!isset($_SESSION['user'])) {
    header('Location: login.php');
    exit;
}

$error = '';
$success = '';

// Handle password reset form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $currentPassword = $_POST['current_password'] ?? '';
    $newPassword = $_POST['new_password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';
    $csrf = $_POST['_csrf'] ?? '';
    
    // Validate CSRF token
    if (!validate_csrf_token($csrf)) {
        $error = 'Invalid security token. Please try again.';
    } elseif (empty($currentPassword) || empty($newPassword) || empty($confirmPassword)) {
        $error = 'All fields are required.';
    } elseif ($newPassword !== $confirmPassword) {
        $error = 'New passwords do not match.';
    } elseif (strlen($newPassword) < 6) {
        $error = 'New password must be at least 6 characters long.';
    } else {
        // Verify current password
        $userFile = md5($_SESSION['user']['email']) . '.json';
        $usersDir = __DIR__ . '/../users';
        
        if (file_exists($usersDir . '/' . $userFile)) {
            $user = json_decode(file_get_contents($usersDir . '/' . $userFile), true);
            
            if (password_verify($currentPassword, $user['password'])) {
                // Update password
                $user['password'] = password_hash($newPassword, PASSWORD_DEFAULT);
                $user['updated_at'] = date('c');
                
                if (file_put_contents($usersDir . '/' . $userFile, json_encode($user, JSON_PRETTY_PRINT))) {
                    $success = 'Password updated successfully!';
                    
                    // Log the password change
                    require_once __DIR__ . '/../api/log.php';
                    log_security_event('password_changed', ['email' => $_SESSION['user']['email']]);
                } else {
                    $error = 'Failed to update password. Please try again.';
                }
            } else {
                $error = 'Current password is incorrect.';
            }
        } else {
            $error = 'User not found.';
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
    <title>Reset Password - HaruCMS Admin</title>
    <link rel="stylesheet" href="/admin/css/style.css">
    <link rel="shortcut icon" href="assets/haru_favicon.svg">
    <style>
        body {
            background: var(--bg-primary);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
        }
        
        .reset-container {
            background: white;
            border-radius: 12px;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.1);
            padding: 3rem;
            width: 100%;
            max-width: 400px;
            margin: 2rem;
        }
        
        .reset-header {
            text-align: center;
            margin-bottom: 2rem;
        }
        
        .reset-header h1 {
            color: #2d3748;
            margin: 0 0 0.5rem 0;
            font-size: 1.875rem;
            font-weight: 700;
        }
        
        .reset-header p {
            color: #718096;
            margin: 0;
            font-size: 0.875rem;
        }
        
        .reset-form {
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
        
        .reset-button {
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
        
        .reset-button:hover {
            background: #5a67d8;
        }
        
        .reset-button:active {
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
        
        .reset-footer {
            text-align: center;
            margin-top: 2rem;
            padding-top: 2rem;
            border-top: 1px solid #e2e8f0;
        }
        
        .reset-footer a {
            color: #667eea;
            text-decoration: none;
            font-weight: 600;
        }
        
        .reset-footer a:hover {
            text-decoration: underline;
        }
        
        .password-strength {
            font-size: 0.75rem;
            margin-top: 0.25rem;
        }
        
        .password-strength.weak {
            color: #e53e3e;
        }
        
        .password-strength.medium {
            color: #dd6b20;
        }
        
        .password-strength.strong {
            color: #38a169;
        }
    </style>
</head>
<body>
    <div class="reset-container">
        <div class="reset-header">
            <h1>Reset Password</h1>
            <p>Change your admin account password</p>
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
        
        <form class="reset-form" method="POST">
            <input type="hidden" name="_csrf" value="<?= get_csrf_token() ?>">
            
            <div class="form-group">
                <label for="current_password">Current Password</label>
                <input 
                    type="password" 
                    id="current_password" 
                    name="current_password" 
                    required 
                    autocomplete="current-password"
                    placeholder="Enter your current password"
                >
            </div>
            
            <div class="form-group">
                <label for="new_password">New Password</label>
                <input 
                    type="password" 
                    id="new_password" 
                    name="new_password" 
                    required 
                    minlength="6"
                    autocomplete="new-password"
                    placeholder="Enter your new password"
                >
                <div id="password-strength" class="password-strength"></div>
            </div>
            
            <div class="form-group">
                <label for="confirm_password">Confirm New Password</label>
                <input 
                    type="password" 
                    id="confirm_password" 
                    name="confirm_password" 
                    required 
                    minlength="6"
                    autocomplete="new-password"
                    placeholder="Confirm your new password"
                >
            </div>
            
            <button type="submit" class="reset-button">
                Update Password
            </button>
        </form>
        
        <div class="reset-footer">
            <a href="/admin/index.php?page=dashboard">← Back to Dashboard</a>
        </div>
    </div>
    
    <script>
        // Password strength indicator
        const newPasswordInput = document.getElementById('new_password');
        const strengthIndicator = document.getElementById('password-strength');
        
        newPasswordInput.addEventListener('input', function() {
            const password = this.value;
            const strength = calculatePasswordStrength(password);
            
            strengthIndicator.textContent = strength.text;
            strengthIndicator.className = 'password-strength ' + strength.class;
        });
        
        function calculatePasswordStrength(password) {
            if (password.length === 0) {
                return { text: '', class: '' };
            }
            
            let score = 0;
            if (password.length >= 6) score++;
            if (password.length >= 8) score++;
            if (/[a-z]/.test(password)) score++;
            if (/[A-Z]/.test(password)) score++;
            if (/[0-9]/.test(password)) score++;
            if (/[^A-Za-z0-9]/.test(password)) score++;
            
            if (score < 3) {
                return { text: 'Weak password', class: 'weak' };
            } else if (score < 5) {
                return { text: 'Medium strength', class: 'medium' };
            } else {
                return { text: 'Strong password', class: 'strong' };
            }
        }
        
        // Handle form submission
        document.querySelector('.reset-form').addEventListener('submit', function(e) {
            const button = document.querySelector('.reset-button');
            button.textContent = 'Updating...';
            button.disabled = true;
        });
    </script>
</body>
</html>
