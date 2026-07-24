#!/usr/bin/env php
<?php
/**
 * Create Demo Content Script
 * 
 * This script creates demo collections (blog, services, contact) with sample content
 * for testing and demonstration purposes.
 * 
 * Usage: php scripts/create-demo-content.php
 */

// Get the base directory
$baseDir = dirname(__DIR__);

// Collections directory
$collectionsDir = "$baseDir/content/collections";
if (!is_dir($collectionsDir)) {
    mkdir($collectionsDir, 0755, true);
}

// Helper function to create collection
function createCollection($slug, $name, $description, $fields, $entries = []) {
    global $baseDir;
    
    $collectionDir = "$baseDir/content/collections/$slug";
    if (!is_dir($collectionDir)) {
        mkdir($collectionDir, 0755, true);
    }
    
    $entriesDir = "$collectionDir/entries";
    if (!is_dir($entriesDir)) {
        mkdir($entriesDir, 0755, true);
    }
    
    // Create collection.json
    $collectionData = [
        'id' => 'col-' . $slug,
        'name' => $name,
        'slug' => $slug,
        'description' => $description,
        'created_at' => date('Y-m-d H:i:s'),
        'updated_at' => date('Y-m-d H:i:s')
    ];
    
    file_put_contents("$collectionDir/collection.json", json_encode($collectionData, JSON_PRETTY_PRINT));
    
    // Create schema.json
    $schemaData = [
        'fields' => $fields
    ];
    
    file_put_contents("$collectionDir/schema.json", json_encode($schemaData, JSON_PRETTY_PRINT));
    
    // Create entries
    foreach ($entries as $entry) {
        $entryId = $entry['id'] ?? uniqid('entry_');
        $entryData = array_merge([
            'id' => $entryId,
            'status' => 'published',
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s')
        ], $entry);
        
        file_put_contents("$entriesDir/$entryId.json", json_encode($entryData, JSON_PRETTY_PRINT));
    }
    
    return true;
}

// Create Blog collection
if (!is_dir("$collectionsDir/blog")) {
    echo "Creating Blog collection...\n";
    createCollection(
        'blog',
        'Blog',
        'Blog posts with categories and tags',
        [
            ['name' => 'title', 'type' => 'text', 'label' => 'Title', 'required' => true],
            ['name' => 'slug', 'type' => 'text', 'label' => 'Slug', 'required' => true],
            ['name' => 'excerpt', 'type' => 'textarea', 'label' => 'Excerpt', 'required' => false],
            ['name' => 'content', 'type' => 'content-editor', 'label' => 'Content', 'required' => true, 'css_url' => '/project/styles.css'],
            ['name' => 'featured_image', 'type' => 'file', 'label' => 'Featured Image', 'required' => false],
            ['name' => 'category', 'type' => 'text', 'label' => 'Category', 'required' => false],
            ['name' => 'tags', 'type' => 'tags', 'label' => 'Tags', 'required' => false],
            ['name' => 'author', 'type' => 'text', 'label' => 'Author', 'required' => false],
            ['name' => 'date', 'type' => 'date', 'label' => 'Date', 'required' => false],
            ['name' => 'status', 'type' => 'select', 'label' => 'Status', 'options' => [['label' => 'Draft', 'value' => 'draft'], ['label' => 'Published', 'value' => 'published']], 'default' => 'published']
        ],
        [
            [
                'id' => 'demo-post-1',
                'title' => 'Welcome to Birkly',
                'slug' => 'welcome-to-birkly',
                'excerpt' => 'Get started with Birkly and discover the power of flat-file content management. No database required, just simple JSON files and elegant simplicity.',
                'content' => '<p>Birkly is a modern, flat-file content management system designed for simplicity and flexibility. Unlike traditional database-driven CMS platforms, Birkly uses simple JSON files to store your content.</p><p>This makes it fast, portable, and perfect for version control. Every change is tracked in your version control system, making collaboration and deployment effortless.</p>',
                'date' => date('Y-m-d', strtotime('-5 days')),
                'status' => 'published',
                'tags' => ['getting-started', 'cms', 'flat-file']
            ],
            [
                'id' => 'demo-post-2',
                'title' => 'Creating Your First Content Entry',
                'slug' => 'creating-your-first-content-entry',
                'excerpt' => 'Learn how to create and manage content in Birkly with our step-by-step guide. We\'ll walk you through creating your first entry and understanding the editor.',
                'content' => '<p>Creating content in Birkly is straightforward and intuitive. The admin panel provides a clean interface for managing all your content.</p><p>Follow these simple steps to create your first entry:</p><ol><li>Navigate to the Content section</li><li>Select your collection</li><li>Click "New Entry"</li><li>Fill in the fields</li><li>Save and publish</li></ol>',
                'date' => date('Y-m-d', strtotime('-4 days')),
                'status' => 'published',
                'tags' => ['tutorial', 'guide', 'content']
            ],
            [
                'id' => 'demo-post-3',
                'title' => 'Understanding Fieldtypes',
                'slug' => 'understanding-fieldtypes',
                'excerpt' => 'Explore the various fieldtypes available in Birkly and how to use them effectively. From text fields to rich content editors, learn what each type offers.',
                'content' => '<p>Birkly comes with a variety of fieldtypes to suit different content needs. Each fieldtype is designed for a specific purpose.</p><p>Common fieldtypes include:</p><ul><li><strong>Text:</strong> Simple text input</li><li><strong>Textarea:</strong> Multi-line text</li><li><strong>Editor:</strong> Rich text editor with formatting</li><li><strong>Media:</strong> Image and file uploads</li><li><strong>Select:</strong> Dropdown selections</li><li><strong>Tags:</strong> Tag management</li></ul>',
                'date' => date('Y-m-d', strtotime('-3 days')),
                'status' => 'published',
                'tags' => ['fieldtypes', 'features']
            ],
            [
                'id' => 'demo-post-4',
                'title' => 'Working with Collections',
                'slug' => 'working-with-collections',
                'excerpt' => 'Collections are the foundation of content organization in Birkly. Learn how to create and manage collections effectively.',
                'content' => '<p>Collections are groups of related content entries. They help you organize your content by type or purpose.</p><p>Each collection can have its own set of fields, making it flexible for different content types. You can create collections for blog posts, products, testimonials, or any other content type you need.</p>',
                'date' => date('Y-m-d', strtotime('-2 days')),
                'status' => 'published',
                'tags' => ['collections', 'organization']
            ],
            [
                'id' => 'demo-post-5',
                'title' => 'Customizing Your Website',
                'slug' => 'customizing-your-website',
                'excerpt' => 'Learn how to customize your website\'s appearance and functionality using Birkly\'s templating system.',
                'content' => '<p>Birkly provides a simple yet powerful templating language that makes it easy to customize your website.</p><p>The templating system supports variables, loops, conditionals, and more. You can create dynamic pages that fetch and display your content automatically.</p>',
                'date' => date('Y-m-d', strtotime('-1 day')),
                'status' => 'published',
                'tags' => ['templates', 'customization']
            ]
        ]
    );
    echo "✅ Blog collection created\n";
}

// Create Services collection
if (!is_dir("$collectionsDir/services")) {
    echo "Creating Services collection...\n";
    createCollection(
        'services',
        'Services',
        'Services offered',
        [
            ['name' => 'title', 'type' => 'text', 'label' => 'Title', 'required' => true],
            ['name' => 'slug', 'type' => 'text', 'label' => 'Slug', 'required' => true],
            ['name' => 'description', 'type' => 'textarea', 'label' => 'Description', 'required' => true],
            ['name' => 'content', 'type' => 'content-editor', 'label' => 'Content', 'required' => false, 'css_url' => '/project/styles.css'],
            ['name' => 'icon', 'type' => 'text', 'label' => 'Icon', 'required' => false],
            ['name' => 'price', 'type' => 'text', 'label' => 'Price', 'required' => false],
            ['name' => 'featured', 'type' => 'boolean', 'label' => 'Featured', 'required' => false],
            ['name' => 'status', 'type' => 'select', 'label' => 'Status', 'options' => [['label' => 'Draft', 'value' => 'draft'], ['label' => 'Published', 'value' => 'published']], 'default' => 'published']
        ],
        [
            [
                'id' => 'service-1',
                'title' => 'Web Development',
                'slug' => 'web-development',
                'description' => 'Custom web applications built with modern technologies',
                'content' => '<p>We create responsive, fast, and secure web applications tailored to your business needs. Our team uses the latest technologies and best practices to deliver high-quality solutions.</p><p>From simple websites to complex web applications, we\'ve got you covered.</p>',
                'icon' => '💻',
                'price' => 'Starting at $2,500',
                'featured' => true,
                'status' => 'published'
            ],
            [
                'id' => 'service-2',
                'title' => 'Content Management',
                'slug' => 'content-management',
                'description' => 'Easy-to-use CMS solutions for managing your content',
                'content' => '<p>Take control of your content with our intuitive content management systems. Update your website anytime, anywhere without technical knowledge.</p><p>Perfect for blogs, portfolios, and business websites.</p>',
                'icon' => '📝',
                'price' => 'Starting at $1,500',
                'featured' => true,
                'status' => 'published'
            ],
            [
                'id' => 'service-3',
                'title' => 'E-Commerce Solutions',
                'slug' => 'e-commerce-solutions',
                'description' => 'Complete online store setup and management',
                'content' => '<p>Launch your online store with our comprehensive e-commerce solutions. We handle everything from setup to payment integration.</p><p>Accept payments, manage inventory, and track orders all in one place.</p>',
                'icon' => '🛒',
                'price' => 'Starting at $3,500',
                'featured' => false,
                'status' => 'published'
            ],
            [
                'id' => 'service-4',
                'title' => 'SEO Optimization',
                'slug' => 'seo-optimization',
                'description' => 'Improve your search engine rankings and visibility',
                'content' => '<p>Boost your online presence with our SEO optimization services. We help you rank higher in search results and attract more visitors.</p><p>From keyword research to technical SEO, we cover it all.</p>',
                'icon' => '🔍',
                'price' => 'Starting at $1,200',
                'featured' => false,
                'status' => 'published'
            ],
            [
                'id' => 'service-5',
                'title' => 'Mobile App Development',
                'slug' => 'mobile-app-development',
                'description' => 'Native and cross-platform mobile applications',
                'content' => '<p>Reach your audience on the go with our mobile app development services. We create apps for iOS and Android using modern frameworks.</p><p>From concept to launch, we guide you through the entire process.</p>',
                'icon' => '📱',
                'price' => 'Starting at $4,000',
                'featured' => true,
                'status' => 'published'
            ]
        ]
    );
    echo "✅ Services collection created\n";
}

// Create Contact collection with Public API enabled
if (!is_dir("$collectionsDir/contact")) {
    echo "Creating Contact collection...\n";
    $contactDir = "$collectionsDir/contact";
    mkdir($contactDir, 0755, true);
    mkdir("$contactDir/entries", 0755, true);
    
    // Create collection.json with Public API enabled
    $collectionData = [
        'id' => 'col-contact',
        'name' => 'Contact',
        'slug' => 'contact',
        'description' => 'Contact form submissions',
        'entry_method' => 'public_api',
        'api_key' => 'haru_contact_1234567890abcdef1234567890abcdef',
        'api_settings' => [
            'auto_publish' => false,
            'rate_limit' => 10,
            'notification_email' => '',
            'captcha_provider' => '',
            'captcha_site_key' => '',
            'captcha_secret_key' => ''
        ],
        'created_at' => date('Y-m-d H:i:s'),
        'updated_at' => date('Y-m-d H:i:s')
    ];
    
    file_put_contents("$contactDir/collection.json", json_encode($collectionData, JSON_PRETTY_PRINT));
    
    // Create schema.json
    $schemaData = [
        'fields' => [
            ['name' => 'title', 'type' => 'text', 'label' => 'Name', 'required' => true],
            ['name' => 'email', 'type' => 'text', 'label' => 'Email', 'required' => true],
            ['name' => 'subject', 'type' => 'text', 'label' => 'Subject', 'required' => false],
            ['name' => 'message', 'type' => 'textarea', 'label' => 'Message', 'required' => true],
            ['name' => 'status', 'type' => 'select', 'label' => 'Status', 'options' => [['label' => 'Draft', 'value' => 'draft'], ['label' => 'Published', 'value' => 'published']], 'default' => 'draft']
        ]
    ];
    
    file_put_contents("$contactDir/schema.json", json_encode($schemaData, JSON_PRETTY_PRINT));
    
    echo "✅ Contact collection created with Public API enabled\n";
}

// Create About collection (singleton page)
if (!is_dir("$collectionsDir/about")) {
    echo "Creating About collection...\n";
    createCollection(
        'about',
        'About',
        'About page content',
        [
            ['name' => 'title', 'type' => 'text', 'label' => 'Title', 'required' => true],
            ['name' => 'slug', 'type' => 'text', 'label' => 'Slug', 'required' => true],
            ['name' => 'subtitle', 'type' => 'text', 'label' => 'Subtitle', 'required' => false],
            ['name' => 'content', 'type' => 'content-editor', 'label' => 'Content', 'required' => true, 'css_url' => '/project/styles.css'],
            ['name' => 'status', 'type' => 'select', 'label' => 'Status', 'options' => [['label' => 'Draft', 'value' => 'draft'], ['label' => 'Published', 'value' => 'published']], 'default' => 'published']
        ],
        [
            [
                'id' => 'about-page',
                'title' => 'About Us',
                'slug' => 'about',
                'subtitle' => 'Learn more about our company and what we do',
                'status' => 'published',
                'content' => '<p>This is a demo website built with Birkly, a modern flat-file content management system.</p><h2>About Birkly</h2><p>Birkly is designed for simplicity and flexibility. Unlike traditional database-driven CMS platforms, Birkly uses simple JSON files to store your content, making it:</p><ul><li><strong>Fast:</strong> No database queries, just file reads</li><li><strong>Portable:</strong> Move your entire site by copying a folder</li><li><strong>Version-controlled:</strong> Every change is tracked in your version control system</li><li><strong>Secure:</strong> No SQL injection vulnerabilities</li><li><strong>Flexible:</strong> Edit content directly in your code editor if needed</li></ul><h2>This Demo Website</h2><p>This demo website showcases how easy it is to create a professional website with Birkly. The entire website uses minimal JavaScript—just one script tag to connect to the CMS. All content is fetched dynamically from the CMS and rendered using Birkly\'s simple templating language.</p><h2>Features</h2><p>This demo website includes:</p><ul><li>Dynamic blog posts from the CMS</li><li>Services showcase with featured items</li><li>Contact form with public API integration</li><li>Responsive design that works on all devices</li><li>Modern, clean interface</li></ul><h2>Get Started</h2><p>Ready to build your own website with Birkly? Visit our <a href="/project/services.html">services page</a> to see what we offer, or <a href="/project/contact.html">contact us</a> to learn more.</p>'
            ]
        ]
    );
    echo "✅ About collection created\n";
}

echo "\n✅ Demo content creation complete!\n";
echo "Collections created: Blog, Services, Contact, About\n";
echo "You can now use the demo website at /project/\n";

// Generate page bundle snapshots for dev/hydrate smoke (P2-M6-T2)
$snapshotScript = "$baseDir/scripts/generate-page-snapshots.php";
if (is_file($snapshotScript)) {
    echo "\nGenerating page snapshots...\n";
    passthru('php ' . escapeshellarg($snapshotScript), $code);
    if ($code === 0) {
        echo "✅ Page snapshots generated in cache/page-snapshots/\n";
    } else {
        echo "⚠️  Page snapshot generation failed (exit $code)\n";
    }
}

