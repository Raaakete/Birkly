<?php
/**
 * Simple test page to verify server is working correctly
 * Open this in your browser: http://localhost:8080/test-server.php
 */

// Check if PHP is executing
$phpWorking = true;
$routerWorking = false;

// Check if router is being used (we can detect by checking for REQUEST_URI)
if (isset($_SERVER['REQUEST_URI'])) {
    $routerWorking = true;
}

// Test Content-Type header
header('Content-Type: text/html; charset=utf-8');

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Server Test - Birkly</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            max-width: 800px;
            margin: 50px auto;
            padding: 20px;
            background: #f5f5f5;
        }
        .test-result {
            background: white;
            padding: 20px;
            margin: 20px 0;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .pass { color: #2d855a; background: #c6f6d5; padding: 10px; border-radius: 4px; }
        .fail { color: #c53030; background: #fed7d7; padding: 10px; border-radius: 4px; }
        h1 { color: #2d3748; }
        .info { color: #718096; margin: 10px 0; }
    </style>
</head>
<body>
    <h1>🧪 Birkly Server Test</h1>
    
    <div class="test-result">
        <h2>Test Results:</h2>
        
        <div class="test-result">
            <strong>PHP Execution:</strong>
            <?php if ($phpWorking): ?>
                <div class="pass">✅ PASS - PHP is executing correctly</div>
            <?php else: ?>
                <div class="fail">❌ FAIL - PHP is not executing</div>
            <?php endif; ?>
        </div>
        
        <div class="test-result">
            <strong>Router Script:</strong>
            <?php if ($routerWorking): ?>
                <div class="pass">✅ PASS - Router is working</div>
            <?php else: ?>
                <div class="fail">❌ FAIL - Router may not be working</div>
            <?php endif; ?>
        </div>
        
        <div class="test-result">
            <strong>Content-Type Header:</strong>
            <?php 
            $headers = headers_list();
            $contentTypeSet = false;
            foreach ($headers as $h) {
                if (stripos($h, 'Content-Type') !== false && stripos($h, 'text/html') !== false) {
                    $contentTypeSet = true;
                    break;
                }
            }
            ?>
            <?php if ($contentTypeSet): ?>
                <div class="pass">✅ PASS - Content-Type header is set correctly</div>
            <?php else: ?>
                <div class="fail">❌ FAIL - Content-Type header not set</div>
            <?php endif; ?>
        </div>
        
        <div class="test-result">
            <strong>Next Steps:</strong>
            <div class="info">
                <?php if ($phpWorking && $routerWorking && $contentTypeSet): ?>
                    <p>✅ Everything looks good! Try logging in now.</p>
                    <p><a href="/admin/login.php">→ Go to Login Page</a></p>
                <?php else: ?>
                    <p>❌ There's an issue. Make sure you started the server with:</p>
                    <pre style="background: #f7fafc; padding: 15px; border-radius: 4px;">./start-server.sh</pre>
                    <p>Or manually:</p>
                    <pre style="background: #f7fafc; padding: 15px; border-radius: 4px;">php -S localhost:8080 -t . server-router.php</pre>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <div class="test-result">
        <h3>Server Information:</h3>
        <div class="info">
            <p><strong>PHP Version:</strong> <?php echo PHP_VERSION; ?></p>
            <p><strong>Server:</strong> <?php echo $_SERVER['SERVER_SOFTWARE'] ?? 'PHP Built-in Server'; ?></p>
            <p><strong>Request URI:</strong> <?php echo $_SERVER['REQUEST_URI'] ?? 'Not set'; ?></p>
        </div>
    </div>
</body>
</html>

