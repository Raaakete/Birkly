<?php
/**
 * Email Verification Page
 * 
 * Public page for verifying email addresses
 */

require_once __DIR__ . '/includes/EmailVerification.php';

$token = $_GET['token'] ?? '';
$user_id = $_GET['user'] ?? '';

if (empty($token) || empty($user_id)) {
    http_response_code(400);
    die('Invalid verification link');
}

$verification = new EmailVerification();
$result = $verification->verifyToken($token, $user_id);

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Email Verification</title>
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
        .success {
            color: #27ae60;
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
        <?php if ($result['success']): ?>
            <h1 class="success">✓ Email Verified</h1>
            <p><?php echo htmlspecialchars($result['message']); ?></p>
            <a href="/" class="btn">Go to Homepage</a>
        <?php else: ?>
            <h1 class="error">✗ Verification Failed</h1>
            <p><?php echo htmlspecialchars($result['message']); ?></p>
            <a href="/" class="btn">Go to Homepage</a>
        <?php endif; ?>
    </div>
</body>
</html>
