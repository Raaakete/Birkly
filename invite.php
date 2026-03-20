<?php
// invite.php - Public invitation acceptance page

require_once __DIR__ . '/api/storage.php';
require_once __DIR__ . '/api/log.php';

$token = $_GET['token'] ?? '';
$error = '';
$success = '';
$invitation = null;

// Validate token
if (empty($token)) {
    $error = 'Invalid invitation link';
} else {
    $invitationsStorage = new Storage(__DIR__ . '/data/invitations');
    $invitation = $invitationsStorage->read($token . '.json');
    
    if (!$invitation) {
        $error = 'Invitation not found or has expired';
    } elseif ($invitation['status'] !== 'pending') {
        $error = 'This invitation is no longer valid';
    } elseif (strtotime($invitation['expires_at']) < time()) {
        $error = 'This invitation has expired';
    }
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $invitation && !$error) {
    $password = $_POST['password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';
    
    if (empty($password) || empty($confirmPassword)) {
        $error = 'Please fill in all fields';
    } elseif ($password !== $confirmPassword) {
        $error = 'Passwords do not match';
    } elseif (strlen($password) < 6) {
        $error = 'Password must be at least 6 characters long';
    } else {
        // Create user account
        $usersStorage = new Storage(__DIR__ . '/users');
        $userFile = md5($invitation['email']) . '.json';
        
        // Check if user already exists (double-check)
        if ($usersStorage->read($userFile)) {
            $error = 'User account already exists';
        } else {
            // Create user data
            $userData = [
                'id' => md5($invitation['email']),
                'email' => $invitation['email'],
                'role' => $invitation['role'],
                'password' => password_hash($password, PASSWORD_DEFAULT),
                'created_at' => date('c'),
                'created_by' => $invitation['created_by'],
                'invitation_accepted_at' => date('c'),
                'invitation_token' => $token
            ];
            
            $result = $usersStorage->write($userFile, $userData);
            
            if ($result) {
                // Mark invitation as accepted
                $invitation['status'] = 'accepted';
                $invitation['accepted_at'] = date('c');
                $invitationsStorage->write($token . '.json', $invitation);
                
                // Log the event
                log_security_event('invitation_accepted', [
                    'email' => $invitation['email'],
                    'role' => $invitation['role']
                ]);
                
                $success = 'Account created successfully! You can now login to the admin panel.';
                
                // Clear invitation data for security
                $invitation = null;
            } else {
                $error = 'Failed to create account. Please try again.';
            }
        }
    }
}

// Increment attempt counter
if ($invitation && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $invitation['attempts'] = ($invitation['attempts'] ?? 0) + 1;
    $invitationsStorage->write($token . '.json', $invitation);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Accept Invitation - Birkly</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 2rem;
        }
        
        .invitation-container {
            background: white;
            border-radius: 12px;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.1);
            padding: 3rem;
            width: 100%;
            max-width: 500px;
        }
        
        .invitation-header {
            text-align: center;
            margin-bottom: 2rem;
        }
        
        .invitation-header h1 {
            color: #2d3748;
            margin-bottom: 0.5rem;
            font-size: 1.875rem;
            font-weight: 700;
        }
        
        .invitation-header p {
            color: #718096;
            font-size: 0.875rem;
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
        
        .invitation-info {
            background: #f7fafc;
            border-radius: 8px;
            padding: 1.5rem;
            margin-bottom: 2rem;
            border-left: 4px solid #667eea;
        }
        
        .invitation-info h3 {
            color: #2d3748;
            margin-bottom: 0.5rem;
            font-size: 1.125rem;
        }
        
        .invitation-info p {
            color: #4a5568;
            margin: 0.25rem 0;
        }
        
        .form-group {
            margin-bottom: 1.5rem;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 600;
            color: #2d3748;
            font-size: 0.875rem;
        }
        
        .form-group input {
            width: 100%;
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
        
        .btn-primary {
            width: 100%;
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
        
        .btn-primary:hover {
            background: #5a67d8;
        }
        
        .btn-primary:disabled {
            background: #a0aec0;
            cursor: not-allowed;
        }
        
        .message {
            padding: 0.75rem 1rem;
            border-radius: 8px;
            margin-bottom: 1rem;
            font-size: 0.875rem;
        }
        
        .message.error {
            background: #fed7d7;
            color: #c53030;
            border: 1px solid #feb2b2;
        }
        
        .message.success {
            background: #c6f6d5;
            color: #2f855a;
            border: 1px solid #9ae6b4;
        }
        
        .login-link {
            text-align: center;
            margin-top: 2rem;
            padding-top: 2rem;
            border-top: 1px solid #e2e8f0;
        }
        
        .login-link a {
            color: #667eea;
            text-decoration: none;
            font-weight: 600;
        }
        
        .login-link a:hover {
            text-decoration: underline;
        }
        
        .expired-message {
            text-align: center;
            color: #718096;
        }
        
        .expired-message h2 {
            color: #2d3748;
            margin-bottom: 1rem;
        }
    </style>
</head>
<body>
    <div class="invitation-container">
        <div class="invitation-header">
            <div class="logo">H</div>
            <h1>Accept Invitation</h1>
            <p>Set up your account to join Birkly</p>
        </div>
        
        <?php if ($error): ?>
            <div class="message error">
                <?= htmlspecialchars($error) ?>
            </div>
            
            <?php if (strpos($error, 'expired') !== false || strpos($error, 'not found') !== false): ?>
                <div class="expired-message">
                    <h2>Invitation Not Available</h2>
                    <p>This invitation link is no longer valid. Please contact your administrator for a new invitation.</p>
                </div>
            <?php endif; ?>
            
        <?php elseif ($success): ?>
            <div class="message success">
                <?= htmlspecialchars($success) ?>
            </div>
            
            <div class="login-link">
                <p><a href="/admin/login.php">Go to Login Page</a></p>
            </div>
            
        <?php elseif ($invitation): ?>
            <div class="invitation-info">
                <h3>You're invited to join Birkly</h3>
                <p><strong>Email:</strong> <?= htmlspecialchars($invitation['email']) ?></p>
                <p><strong>Role:</strong> <?= htmlspecialchars(ucfirst($invitation['role'])) ?></p>
                <p><strong>Expires:</strong> <?= date('M j, Y g:i A', strtotime($invitation['expires_at'])) ?></p>
            </div>
            
            <form method="POST">
                <div class="form-group">
                    <label for="password">Create Password</label>
                    <input 
                        type="password" 
                        id="password" 
                        name="password" 
                        required 
                        minlength="6"
                        placeholder="Enter a secure password"
                    >
                </div>
                
                <div class="form-group">
                    <label for="confirm_password">Confirm Password</label>
                    <input 
                        type="password" 
                        id="confirm_password" 
                        name="confirm_password" 
                        required 
                        minlength="6"
                        placeholder="Confirm your password"
                    >
                </div>
                
                <button type="submit" class="btn-primary">
                    Create Account
                </button>
            </form>
            
        <?php endif; ?>
    </div>
    
    <script>
        // Password confirmation validation
        document.addEventListener('DOMContentLoaded', function() {
            const password = document.getElementById('password');
            const confirmPassword = document.getElementById('confirm_password');
            
            if (password && confirmPassword) {
                function validatePasswords() {
                    if (password.value !== confirmPassword.value) {
                        confirmPassword.setCustomValidity('Passwords do not match');
                    } else {
                        confirmPassword.setCustomValidity('');
                    }
                }
                
                password.addEventListener('input', validatePasswords);
                confirmPassword.addEventListener('input', validatePasswords);
            }
        });
    </script>
</body>
</html>
