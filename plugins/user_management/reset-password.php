<?php
/**
 * Password Reset Page
 * 
 * Public page for resetting passwords
 */

require_once __DIR__ . '/includes/PasswordReset.php';

$token = $_GET['token'] ?? '';
$user_id = $_GET['user'] ?? '';

// If no token, show request form
if (empty($token) || empty($user_id)) {
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Reset Password</title>
        <style>
            body {
                font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
                display: flex;
                justify-content: center;
                align-items: center;
                min-height: 100vh;
                margin: 0;
                background: #f5f5f5;
            }
            .container {
                background: white;
                padding: 2rem;
                border-radius: 8px;
                box-shadow: 0 2px 10px rgba(0,0,0,0.1);
                max-width: 400px;
                width: 100%;
            }
            h1 {
                margin-top: 0;
                color: #2c3e50;
            }
            .form-group {
                margin-bottom: 1rem;
            }
            label {
                display: block;
                margin-bottom: 0.5rem;
                color: #333;
                font-weight: 500;
            }
            input {
                width: 100%;
                padding: 0.75rem;
                border: 1px solid #ddd;
                border-radius: 4px;
                font-size: 1rem;
                box-sizing: border-box;
            }
            button {
                width: 100%;
                padding: 0.75rem;
                background: #3498db;
                color: white;
                border: none;
                border-radius: 4px;
                font-size: 1rem;
                cursor: pointer;
                margin-top: 1rem;
            }
            button:hover {
                background: #2980b9;
            }
            button:disabled {
                background: #95a5a6;
                cursor: not-allowed;
            }
            .message {
                padding: 0.75rem;
                border-radius: 4px;
                margin-bottom: 1rem;
            }
            .message.success {
                background: #d4edda;
                color: #155724;
                border: 1px solid #c3e6cb;
            }
            .message.error {
                background: #f8d7da;
                color: #721c24;
                border: 1px solid #f5c6cb;
            }
            .hidden {
                display: none;
            }
        </style>
    </head>
    <body>
        <div class="container">
            <h1>Reset Password</h1>
            <div id="message" class="message hidden"></div>
            <form id="resetForm">
                <div class="form-group">
                    <label for="email">Email Address</label>
                    <input type="email" id="email" name="email" required>
                </div>
                <button type="submit" id="submitBtn">Send Reset Link</button>
            </form>
        </div>
        <script>
            document.getElementById('resetForm').addEventListener('submit', async function(e) {
                e.preventDefault();
                
                const email = document.getElementById('email').value;
                const submitBtn = document.getElementById('submitBtn');
                const messageDiv = document.getElementById('message');
                
                submitBtn.disabled = true;
                submitBtn.textContent = 'Sending...';
                messageDiv.classList.add('hidden');
                
                try {
                    const response = await fetch('/api/index.php?route=password_reset&action=request', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json'
                        },
                        body: JSON.stringify({ email: email })
                    });
                    
                    const data = await response.json();
                    
                    if (data.status === 'ok') {
                        messageDiv.textContent = data.message;
                        messageDiv.classList.remove('hidden');
                        messageDiv.classList.add('success');
                        messageDiv.classList.remove('error');
                        document.getElementById('resetForm').reset();
                    } else {
                        messageDiv.textContent = data.message || 'An error occurred';
                        messageDiv.classList.remove('hidden');
                        messageDiv.classList.add('error');
                        messageDiv.classList.remove('success');
                    }
                } catch (error) {
                    messageDiv.textContent = 'An error occurred. Please try again.';
                    messageDiv.classList.remove('hidden');
                    messageDiv.classList.add('error');
                    messageDiv.classList.remove('success');
                } finally {
                    submitBtn.disabled = false;
                    submitBtn.textContent = 'Send Reset Link';
                }
            });
        </script>
    </body>
    </html>
    <?php
    exit;
}

// Verify token
$passwordReset = new PasswordReset();
$verifyResult = $passwordReset->verifyToken($token, $user_id);

if (!$verifyResult['success']) {
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Reset Password</title>
        <style>
            body {
                font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
                display: flex;
                justify-content: center;
                align-items: center;
                min-height: 100vh;
                margin: 0;
                background: #f5f5f5;
            }
            .container {
                background: white;
                padding: 2rem;
                border-radius: 8px;
                box-shadow: 0 2px 10px rgba(0,0,0,0.1);
                max-width: 500px;
                text-align: center;
            }
            .error {
                color: #e74c3c;
            }
            .btn {
                display: inline-block;
                padding: 10px 20px;
                background: #3498db;
                color: white;
                text-decoration: none;
                border-radius: 5px;
                margin-top: 1rem;
            }
            .btn:hover {
                background: #2980b9;
            }
        </style>
    </head>
    <body>
        <div class="container">
            <h1 class="error">✗ Invalid Reset Link</h1>
            <p><?php echo htmlspecialchars($verifyResult['message']); ?></p>
            <a href="/reset-password" class="btn">Request New Reset Link</a>
        </div>
    </body>
    </html>
    <?php
    exit;
}

// Show reset form
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Password</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            margin: 0;
            background: #f5f5f5;
        }
        .container {
            background: white;
            padding: 2rem;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            max-width: 400px;
            width: 100%;
        }
        h1 {
            margin-top: 0;
            color: #2c3e50;
        }
        .form-group {
            margin-bottom: 1rem;
        }
        label {
            display: block;
            margin-bottom: 0.5rem;
            color: #333;
            font-weight: 500;
        }
        input {
            width: 100%;
            padding: 0.75rem;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 1rem;
            box-sizing: border-box;
        }
        button {
            width: 100%;
            padding: 0.75rem;
            background: #3498db;
            color: white;
            border: none;
            border-radius: 4px;
            font-size: 1rem;
            cursor: pointer;
            margin-top: 1rem;
        }
        button:hover {
            background: #2980b9;
        }
        button:disabled {
            background: #95a5a6;
            cursor: not-allowed;
        }
        .message {
            padding: 0.75rem;
            border-radius: 4px;
            margin-bottom: 1rem;
        }
        .message.success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        .message.error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        .hidden {
            display: none;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>Reset Password</h1>
        <div id="message" class="message hidden"></div>
        <form id="resetForm">
            <input type="hidden" id="token" value="<?php echo htmlspecialchars($token); ?>">
            <input type="hidden" id="user_id" value="<?php echo htmlspecialchars($user_id); ?>">
            <div class="form-group">
                <label for="password">New Password</label>
                <input type="password" id="password" name="password" required minlength="8">
            </div>
            <div class="form-group">
                <label for="password_confirm">Confirm Password</label>
                <input type="password" id="password_confirm" name="password_confirm" required minlength="8">
            </div>
            <button type="submit" id="submitBtn">Reset Password</button>
        </form>
    </div>
    <script>
        document.getElementById('resetForm').addEventListener('submit', async function(e) {
            e.preventDefault();
            
            const password = document.getElementById('password').value;
            const passwordConfirm = document.getElementById('password_confirm').value;
            const token = document.getElementById('token').value;
            const user_id = document.getElementById('user_id').value;
            const submitBtn = document.getElementById('submitBtn');
            const messageDiv = document.getElementById('message');
            
            if (password !== passwordConfirm) {
                messageDiv.textContent = 'Passwords do not match';
                messageDiv.classList.remove('hidden');
                messageDiv.classList.add('error');
                messageDiv.classList.remove('success');
                return;
            }
            
            if (password.length < 8) {
                messageDiv.textContent = 'Password must be at least 8 characters long';
                messageDiv.classList.remove('hidden');
                messageDiv.classList.add('error');
                messageDiv.classList.remove('success');
                return;
            }
            
            submitBtn.disabled = true;
            submitBtn.textContent = 'Resetting...';
            messageDiv.classList.add('hidden');
            
            try {
                const response = await fetch('/api/index.php?route=password_reset&action=reset', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        token: token,
                        user_id: user_id,
                        password: password
                    })
                });
                
                const data = await response.json();
                
                if (data.status === 'ok') {
                    messageDiv.textContent = data.message + ' You can now login with your new password.';
                    messageDiv.classList.remove('hidden');
                    messageDiv.classList.add('success');
                    messageDiv.classList.remove('error');
                    document.getElementById('resetForm').reset();
                    setTimeout(() => {
                        window.location.href = '/';
                    }, 3000);
                } else {
                    messageDiv.textContent = data.message || 'An error occurred';
                    messageDiv.classList.remove('hidden');
                    messageDiv.classList.add('error');
                    messageDiv.classList.remove('success');
                }
            } catch (error) {
                messageDiv.textContent = 'An error occurred. Please try again.';
                messageDiv.classList.remove('hidden');
                messageDiv.classList.add('error');
                messageDiv.classList.remove('success');
            } finally {
                submitBtn.disabled = false;
                submitBtn.textContent = 'Reset Password';
            }
        });
    </script>
</body>
</html>
