<?php
// Simple query-based admin router (MPA)
// Usage: /admin/index.php?page=dashboard

// CRITICAL: Start output buffering to catch any accidental output
// This ensures headers can be set even if included files output something
ob_start();

// Set Content-Type header FIRST to ensure HTML is rendered correctly
// Must be before any output, including whitespace
if (!headers_sent()) {
    header('Content-Type: text/html; charset=utf-8');
}

// Include authentication middleware FIRST
require_once __DIR__ . '/auth_middleware.php';

// Require authentication for all admin pages
requireAdminAuth();

// Page access policy (UX + defense-in-depth).
// Backend endpoints remain the source of truth; this prevents opening pages a role shouldn't even view.
$requestedPage = isset($_GET['page']) && $_GET['page'] !== '' ? (string)$_GET['page'] : 'dashboard';
$pageViewPermissions = [
    // General
    'dashboard' => 'read',
    'help' => 'read',
    'activity' => 'read',
    'audit-log' => 'read',

    // Content
    'content' => 'read',
    'entries' => 'read',
    'entry-detail' => 'read',

    // Editors (write screens)
    'collection-editor' => 'manage_content',
    'entry-editor' => 'write',

    // Media
    'media' => 'read_media',

    // Settings & admin management
    'settings' => 'read_settings',
    'webhooks' => 'read_settings',
    'roles' => 'manage_settings',
    'users' => 'list_users',
    'plugins' => 'manage_plugins',

    // Account is always accessible to authenticated users
    'account' => null,

    // Onboarding is handled separately below (first-time setup)
    'onboarding' => null,
];

if (array_key_exists($requestedPage, $pageViewPermissions)) {
    $needed = $pageViewPermissions[$requestedPage];
    if (is_string($needed) && $needed !== '') {
        requireAdminPermission($needed);
    }
}

// Include CSRF helper
require_once __DIR__ . '/../core/csrf.php';

// Initialize i18n system
require_once __DIR__ . '/../core/i18n.php';
I18n::init();

// Security headers (basic)
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: SAMEORIGIN');
header('Referrer-Policy: no-referrer');

// Include template parser
require_once __DIR__ . '/template_parser.php';

// Check onboarding status - show welcome screen if needed
// Note: auth_middleware.php already starts the session, but we need onboarding functions
$GLOBALS['__HARU_INTERNAL_CALL'] = true;
require_once __DIR__ . '/../api/onboarding.php';

// Ensure session is started
if (session_status() === PHP_SESSION_NONE) {
    require_once __DIR__ . '/../api/session.php';
    haru_secure_session_start();
}

// Get onboarding status (only if user is logged in)
$onboardingStatus = [
    'is_first_time' => false,
    'completed' => false
];
if (isset($_SESSION['user'])) {
    $userId = $_SESSION['user']['email'] ?? $_SESSION['user']['id'] ?? 'unknown';
    $onboardingStatus = getOnboardingStatus($userId);
}

// If first-time setup and onboarding not completed, redirect to welcome
$page = isset($_GET['page']) && $_GET['page'] !== '' ? $_GET['page'] : 'dashboard';
if ($page !== 'onboarding' && $onboardingStatus['is_first_time'] && !$onboardingStatus['completed']) {
    $page = 'onboarding';
}

        // Whitelist of allowed pages → template file map
        $allowedPages = [
            'dashboard' => 'dashboard.html',
            'content' => 'content.html',
            'media' => 'media.html',
            'settings' => 'settings.html',
            'account' => 'account.html',
            'updates' => 'updates.html',
            'entries' => 'entries.html',
            'entry-editor' => 'entry-editor.html',
            'entry-detail' => 'entry-detail.html',
            'collection-editor' => 'collection-editor.html',
            'users' => 'settings.html', // Users UI currently lives in settings template section
            'roles' => 'roles.html',
            'plugins' => 'plugins.html',
            'audit-log' => 'audit-log.html',
            'activity' => 'activity.html',
            'webhooks' => 'webhooks.html',
            'onboarding' => 'onboarding.html',
            'help' => 'help.html',
            'community_users' => null, // Plugin page - handled dynamically
            'sample_plugin' => null // Plugin page - handled dynamically
        ];

$templateFile = $allowedPages[$page] ?? 'dashboard.html';
$templatePath = $templateFile ? __DIR__ . '/templates/' . $templateFile : null;

// Fallback if template missing or page not implemented
if (!$templatePath || !file_exists($templatePath)) {
    $page = 'dashboard';
    $templatePath = __DIR__ . '/templates/dashboard.html';
}

// Helper to build URL with query
function admin_url(string $page, array $params = []): string {
    $query = http_build_query(array_merge(['page' => $page], $params));
    return '/admin/index.php' . ($query ? ('?' . $query) : '');
}

function fetch_admin_page_data(string $page, array $query = []): array {
    // Include the admin templates API directly to get the functions
    $apiPath = $_SERVER['DOCUMENT_ROOT'] . '/api/admin_templates.php';
    if (file_exists($apiPath)) {
        // Set flag to prevent switch statement from executing AND prevent JSON headers
        $GLOBALS['__HARU_INTERNAL_CALL'] = true;
        
        // Include the file to get the functions
        // Any output will be caught by the main output buffer started at the top of this file
        try {
            require_once $apiPath;
        } catch (Throwable $e) {
            error_log("Error including admin_templates.php: " . $e->getMessage());
            return ['error' => $e->getMessage()];
        }
        
        // Call getPageData directly instead of going through the switch
        if (function_exists('getPageData')) {
            try {
                $data = getPageData($page);
                return $data;
            } catch (Exception $e) {
                error_log("Error in fetch_admin_page_data for page '$page': " . $e->getMessage());
                return ['error' => $e->getMessage()];
            }
        }
    }
    return [];
}
?>
<?php 
// Check if we're on onboarding page - if so, use full-page layout without sidebar
$isOnboarding = ($page === 'onboarding');
?>
<?php if ($isOnboarding): ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Setup - HaruCMS</title>
    <link rel="stylesheet" href="/admin/css/style.css">
    <link rel="stylesheet" href="/admin/css/onboarding.css">
    <?php
    // Load settings to get logo for favicon
    require_once __DIR__ . '/../api/storage.php';
    $storage = new Storage(__DIR__ . '/../settings');
    $brandingSettings = $storage->read('branding.json') ?? [];
    $logoPath = $brandingSettings['logoPath'] ?? '';
    $faviconUrl = !empty($logoPath) ? $logoPath : '/admin/assets/haru-icon.svg';
    ?>
    <link rel="shortcut icon" href="<?= htmlspecialchars($faviconUrl) ?>">
</head>
<body class="onboarding-body">
    <div class="onboarding-wrapper">
        <?php
        // Render onboarding page without sidebar
        $onboardingData = fetch_admin_page_data('onboarding', $_GET);
        // Calculate project root path (admin/ is one level down from project root)
        // __DIR__ in admin/index.php is /path/to/project/admin, so dirname(__DIR__) is project root
        $projectRoot = dirname(__DIR__);
        // Verify the path is correct
        if (!file_exists($projectRoot . '/api/storage.php')) {
            // Fallback: try to find the project root by looking for api/storage.php
            $currentDir = __DIR__;
            $projectRoot = null;
            for ($i = 0; $i < 3; $i++) {
                $testPath = $currentDir . str_repeat('/..', $i);
                $realPath = realpath($testPath);
                if ($realPath && file_exists($realPath . '/api/storage.php')) {
                    $projectRoot = $realPath;
                    break;
                }
            }
            if (!$projectRoot) {
                $projectRoot = dirname(__DIR__); // Fallback to default
            }
        }
        try {
            $output = render_template_file($templatePath, array_merge($data ?? [], [
                'onboarding' => $onboardingStatus,
                'onboarding_data' => $onboardingData,
                'project_root' => $projectRoot // Pass project root path to template
            ]));
            echo $output ?: '<div class="error">Failed to render onboarding page</div>';
        } catch (Exception $e) {
            echo '<div class="error">Error rendering onboarding: ' . htmlspecialchars($e->getMessage()) . '</div>';
        }
        ?>
    </div>
    <script src="/admin/js/utils.js"></script>
    <script src="/admin/js/onboarding.js"></script>
</body>
</html>
<?php else: ?>
<?php include __DIR__ . '/partials/layout_head.php'; ?>
        <?php include __DIR__ . '/partials/sidebar.php'; ?>

        <!-- Main Content -->
        <main class="main-content">
            <?php
            // Render the page using template system
            if ($page === 'dashboard' || $page === 'media' || $page === 'account') {
                // Pass query params so entry-editor receives collection/entry ids
                $data = fetch_admin_page_data($page, $_GET);
                
                // For dashboard, ensure onboarding data is included
                if ($page === 'dashboard') {
                    $data['onboarding'] = $onboardingStatus;
                }
                
                // Check if there's an error in the data
                if (isset($data['error'])) {
                    echo '<div class="error-message" style="padding: 2rem; margin: 2rem; background: #fee; border: 1px solid #fcc; border-radius: 8px;">';
                    echo '<h2>Error Loading Page</h2>';
                    echo '<p>' . htmlspecialchars($data['error']) . '</p>';
                    echo '<a href="/admin/index.php?page=dashboard" class="btn btn-primary">Back to Dashboard</a>';
                    echo '</div>';
                } else {
                    try {
                        $output = render_template_file($templatePath, $data);
                        if (empty($output)) {
                            echo '<div class="error-message" style="padding: 2rem; margin: 2rem; background: #fee; border: 1px solid #fcc; border-radius: 8px;">';
                            echo '<h2>Error Rendering Template</h2>';
                            echo '<p>The template rendered empty output. This might be a template parsing error.</p>';
                            echo '<p><strong>Debug Info:</strong></p>';
                            echo '<pre style="background: #f8f8f8; padding: 1rem; border-radius: 4px; overflow: auto;">';
                            echo 'Page: ' . htmlspecialchars($page) . "\n";
                            echo 'Template: ' . htmlspecialchars($templatePath) . "\n";
                            echo 'Data keys: ' . implode(', ', array_keys($data)) . "\n";
                            if (isset($data['collection'])) {
                                echo 'Collection: ' . htmlspecialchars($data['collection']['slug'] ?? 'N/A') . "\n";
                            }
                            if (isset($data['entry'])) {
                                echo 'Entry ID: ' . htmlspecialchars($data['entry']['id'] ?? 'N/A') . "\n";
                            }
                            echo '</pre>';
                            echo '<a href="/admin/index.php?page=dashboard" class="btn btn-primary">Back to Dashboard</a>';
                            echo '</div>';
                        } else {
                            echo $output;
                        }
                    } catch (Exception $e) {
                        echo '<div class="error-message" style="padding: 2rem; margin: 2rem; background: #fee; border: 1px solid #fcc; border-radius: 8px;">';
                        echo '<h2>Template Rendering Error</h2>';
                        echo '<p>' . htmlspecialchars($e->getMessage()) . '</p>';
                        echo '<pre style="background: #f8f8f8; padding: 1rem; border-radius: 4px; overflow: auto;">' . htmlspecialchars($e->getTraceAsString()) . '</pre>';
                        echo '<a href="/admin/index.php?page=dashboard" class="btn btn-primary">Back to Dashboard</a>';
                        echo '</div>';
                    }
                }
            } elseif ($page === 'content' || $page === 'settings' || $page === 'account' || $page === 'entries' || $page === 'entry-editor' || $page === 'entry-detail' || $page === 'collection-editor' || $page === 'users' || $page === 'roles' || $page === 'plugins' || $page === 'webhooks' || $page === 'help') {
                // Content, Settings, Account, Entries, Entry Editor, Collection Editor, Users, Roles, Plugins, Audit Log, Activity, and Webhooks pages
                try {
                    $data = fetch_admin_page_data($page, $_GET);
                    if (isset($data['error'])) {
                        echo '<div class="error-message" style="padding: 2rem; margin: 2rem; background: #fee; border: 1px solid #fcc; border-radius: 8px;">';
                        echo '<h2>Error Loading Page</h2>';
                        echo '<p>' . htmlspecialchars($data['error']) . '</p>';
                        echo '<a href="/admin/index.php?page=dashboard" class="btn btn-primary">Back to Dashboard</a>';
                        echo '</div>';
                    } else {
                        // Check if template uses PHP tags or template directives
                        $templateContent = file_get_contents($templatePath);
                        
                        // IMPROVED LOGIC: If template starts with <?php, it's a pure PHP template
                        // This avoids false positives from directive-like strings in PHP code
                        $startsWithPhp = (strpos(ltrim($templateContent), '<?php') === 0);
                        
                        if ($startsWithPhp) {
                            // Pure PHP template - use direct include
                            extract($data);
                            include $templatePath;
                        } else {
                            // Check for template directives
                            $usesTemplateDirectives = (strpos($templateContent, '{translate') !== false || 
                                                       strpos($templateContent, '{admin_url') !== false ||
                                                       strpos($templateContent, '{for each') !== false ||
                                                       strpos($templateContent, '{if ') !== false ||
                                                       strpos($templateContent, '{include_script') !== false ||
                                                       strpos($templateContent, '{include_style') !== false);
                            
                            if ($usesTemplateDirectives) {
                                // Template with directives - use parser
                                $output = render_template_file($templatePath, $data);
                                if (empty($output)) {
                                    echo '<div class="error-message" style="padding: 2rem; margin: 2rem; background: #fee; border: 1px solid #fcc; border-radius: 8px;">';
                                    echo '<h2>Error Rendering Template</h2>';
                                    echo '<p>The template rendered empty output.</p>';
                                    echo '<a href="/admin/index.php?page=dashboard" class="btn btn-primary">Back to Dashboard</a>';
                                    echo '</div>';
                                } else {
                                    echo $output;
                                }
                            } else {
                                // Fallback to direct include
                                extract($data);
                                include $templatePath;
                            }
                        }
                    }
                } catch (Exception $e) {
                    echo '<div class="error-message" style="padding: 2rem; margin: 2rem; background: #fee; border: 1px solid #fcc; border-radius: 8px;">';
                    echo '<h2>Error Loading Page</h2>';
                    echo '<p>' . htmlspecialchars($e->getMessage()) . '</p>';
                    echo '<pre style="background: #f8f8f8; padding: 1rem; border-radius: 4px; overflow: auto;">' . htmlspecialchars($e->getTraceAsString()) . '</pre>';
                    echo '<a href="/admin/index.php?page=dashboard" class="btn btn-primary">Back to Dashboard</a>';
                    echo '</div>';
                }
            } elseif ($page === 'community_users') {
                // Plugin page - load from plugin directory
                $pluginPath = __DIR__ . '/../plugins/community_users/admin/community_users.html';
                if (file_exists($pluginPath)) {
                    include $pluginPath;
                } else {
                    echo '<div class="coming-soon">';
                    echo '<h1>Plugin Not Found</h1>';
                    echo '<p>The Community Users plugin page could not be loaded.</p>';
                    echo '<a href="' . admin_url('dashboard') . '" class="btn btn-primary">Back to Dashboard</a>';
                    echo '</div>';
                }
            } elseif ($page === 'sample_plugin') {
                // Plugin page - load from plugin directory
                $pluginPath = __DIR__ . '/../plugins/sample-plugin/admin/sample_plugin.html';
                if (file_exists($pluginPath)) {
                    include $pluginPath;
                } else {
                    echo '<div class="coming-soon">';
                    echo '<h1>Plugin Not Found</h1>';
                    echo '<p>The Sample Plugin page could not be loaded.</p>';
                    echo '<a href="' . admin_url('dashboard') . '" class="btn btn-primary">Back to Dashboard</a>';
                    echo '</div>';
                }
            } else {
                // Show "coming soon" for unimplemented pages
                echo '<div class="coming-soon">';
                echo '<h1>Coming Soon</h1>';
                echo '<p>The ' . htmlspecialchars(ucfirst($page)) . ' page is not implemented yet.</p>';
                echo '<a href="' . admin_url('dashboard') . '" class="btn btn-primary">Back to Dashboard</a>';
                echo '</div>';
            }
            ?>
        </main>

        <?php include __DIR__ . '/partials/layout_footer.php'; ?>
<?php endif; ?>
<?php
// Ensure Content-Type is still set before flushing
if (!headers_sent()) {
    header('Content-Type: text/html; charset=utf-8');
}

// Flush output buffer to send content to browser
while (ob_get_level() > 0) {
    ob_end_flush();
}
?>
