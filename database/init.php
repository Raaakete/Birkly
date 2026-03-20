<?php
// Database initialization script for Birkly

$dbPath = __DIR__ . '/birkly.db';

try {
    $pdo = new PDO("sqlite:$dbPath");
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Create collections table
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS collections (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            title TEXT NOT NULL,
            slug TEXT UNIQUE NOT NULL,
            description TEXT,
            fields TEXT,
            schema TEXT,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )
    ");
    
    // Create entries table
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS entries (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            collection TEXT NOT NULL,
            title TEXT NOT NULL,
            slug TEXT,
            content TEXT,
            status TEXT DEFAULT 'draft',
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )
    ");
    
    // Create settings table
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS settings (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            key TEXT UNIQUE NOT NULL,
            value TEXT,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )
    ");
    
    // Insert some sample data
    $pdo->exec("
        INSERT OR IGNORE INTO collections (title, slug, description) VALUES 
        ('Blog Posts', 'blog', 'Blog posts and articles'),
        ('Pages', 'pages', 'Static pages'),
        ('Portfolio', 'portfolio', 'Portfolio projects')
    ");
    
    $pdo->exec("
        INSERT OR IGNORE INTO entries (collection, title, slug, content, status) VALUES 
        ('blog', 'Welcome to Birkly', 'welcome', 'This is your first blog post.', 'published'),
        ('pages', 'About Us', 'about', 'About our company.', 'published'),
        ('portfolio', 'Sample Project', 'sample-project', 'A sample portfolio project.', 'published')
    ");
    
    $pdo->exec("
        INSERT OR IGNORE INTO settings (key, value) VALUES 
        ('site_title', 'Birkly'),
        ('site_description', 'A headless CMS'),
        ('admin_email', 'admin@example.com')
    ");
    
    echo "Database initialized successfully!\n";
    
} catch (Exception $e) {
    echo "Error initializing database: " . $e->getMessage() . "\n";
}
?> 