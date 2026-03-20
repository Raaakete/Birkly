<?php
/**
 * Test Template Function System
 * 
 * This file tests the template function registration and invocation system.
 * Run this file directly to verify template functions work correctly.
 */

// Load core plugin system
require_once __DIR__ . '/core/plugins.php';

// Register a test template function
register_template_function('test_hello', function($name = 'World') {
    return "Hello, " . $name . "!";
}, [
    'description' => 'Test function that greets a user',
    'arguments' => [
        ['name' => 'name', 'type' => 'string', 'required' => false]
    ]
]);

// Register another test function
register_template_function('test_add', function($a, $b) {
    return (int)$a + (int)$b;
}, [
    'description' => 'Test function that adds two numbers',
    'arguments' => [
        ['name' => 'a', 'type' => 'number', 'required' => true],
        ['name' => 'b', 'type' => 'number', 'required' => true]
    ]
]);

// Register a function that returns an array (for testing complex data)
register_template_function('test_user', function() {
    return [
        'name' => 'Test User',
        'email' => 'test@example.com',
        'role' => 'admin'
    ];
}, [
    'description' => 'Test function that returns user data'
]);

// Load template parser
require_once __DIR__ . '/admin/template_parser.php';

// Test template with function calls
$template = <<<TEMPLATE
<!DOCTYPE html>
<html>
<head>
    <title>Template Function Test</title>
</head>
<body>
    <h1>Template Function Test Results</h1>
    
    <h2>Test 1: Simple function call</h2>
    <p>{test_hello}</p>
    
    <h2>Test 2: Function with string argument</h2>
    <p>{test_hello 'Birkly'}</p>
    
    <h2>Test 3: Function with multiple arguments</h2>
    <p>5 + 3 = {test_add 5 3}</p>
    
    <h2>Test 4: Function with quoted arguments</h2>
    <p>{test_hello('Template Functions')}</p>
    
    <h2>Test 5: Function returning object (should be JSON encoded)</h2>
    <p>User: {test_user}</p>
    
    <h2>Test 6: Function with variable argument</h2>
    <p>{test_hello name}</p>
    
    <h2>Test 7: Non-existent function (should fallback to variable)</h2>
    <p>Non-existent: {nonexistent_function}</p>
</body>
</html>
TEMPLATE;

// Test data
$data = [
    'name' => 'Test Name',
    'site_title' => 'Birkly Test'
];

// Render template
$parser = new TemplateParser($data);
try {
    $output = $parser->render($template);
    echo $output;
    
    // Also output test results summary
    echo "\n\n<!-- Test Summary:\n";
    echo "✓ Template function registration: " . (function_exists('register_template_function') ? 'PASS' : 'FAIL') . "\n";
    echo "✓ Template function retrieval: " . (function_exists('get_template_function') ? 'PASS' : 'FAIL') . "\n";
    echo "✓ Function 'test_hello' exists: " . (template_function_exists('test_hello') ? 'PASS' : 'FAIL') . "\n";
    echo "✓ Function 'test_add' exists: " . (template_function_exists('test_add') ? 'PASS' : 'FAIL') . "\n";
    echo "✓ Function 'test_user' exists: " . (template_function_exists('test_user') ? 'PASS' : 'FAIL') . "\n";
    
    $all_functions = get_all_template_functions();
    echo "✓ Total registered functions: " . count($all_functions) . "\n";
    echo "-->\n";
    
} catch (Exception $e) {
    echo "ERROR: " . htmlspecialchars($e->getMessage()) . "\n";
    echo "Stack trace:\n" . htmlspecialchars($e->getTraceAsString()) . "\n";
}
