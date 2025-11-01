#!/usr/bin/env php
<?php
/**
 * Create Default Admin User Script
 * 
 * This script creates the default admin user for HaruCMS.
 * Run this after a fresh installation if you don't have a user account.
 * 
 * Usage: php scripts/create-default-user.php
 */

// Get the base directory
$baseDir = dirname(__DIR__);

// Ensure users directory exists
$usersDir = "$baseDir/users";
if (!is_dir($usersDir)) {
    mkdir($usersDir, 0755, true);
}

// Default admin credentials
$email = 'admin@localhost';
$password = 'password';
$role = 'admin';

// Check if user already exists
$userFile = md5(strtolower(trim($email))) . '.json';
$userPath = "$usersDir/$userFile";

if (file_exists($userPath)) {
    echo "⚠️  User already exists: $email\n";
    echo "File: $userPath\n";
    exit(1);
}

// Create user data
$user = [
    'id' => bin2hex(random_bytes(16)),
    'email' => $email,
    'password' => password_hash($password, PASSWORD_DEFAULT),
    'role' => $role,
    'created_at' => date('c'),
    'note' => 'Default admin user'
];

// Save user file
if (file_put_contents($userPath, json_encode($user, JSON_PRETTY_PRINT))) {
    echo "✅ Default admin user created successfully!\n\n";
    echo "Email: $email\n";
    echo "Password: $password\n";
    echo "File: $userPath\n\n";
    echo "You can now log in to the admin panel.\n";
    exit(0);
} else {
    echo "❌ Failed to create user file: $userPath\n";
    exit(1);
}

