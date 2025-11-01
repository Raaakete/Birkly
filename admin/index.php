<?php
// Simple query-based admin router (MPA)
// Usage: /admin/index.php?page=dashboard

// Set Content-Type header FIRST to ensure HTML is rendered correctly
// Must be before any output, including whitespace
if (!headers_sent()) {
    header('Content-Type: text/html; charset=utf-8');
}

// Include authentication middleware FIRST
require_once __DIR__ . '/auth_middleware.php';

// Require authentication for all admin pages
requireAdminAuth();

// Include CSRF helper
require_once __DIR__ . '/../core/csrf.php';

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
            'updates' => 'updates.html',
            'entries' => 'entries.html',
            'entry-editor' => 'entry-editor.html',
            'collection-editor' => 'collection-editor.html',
            'roles' => 'roles.html',
            'plugins' => 'plugins.html',
            'audit-log' => 'audit-log.html',
            'activity' => 'activity.html',
            'webhooks' => 'webhooks.html',
            'onboarding' => 'onboarding.html',
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
    // Include the admin templates API directly to avoid HTTP requests
    $apiPath = $_SERVER['DOCUMENT_ROOT'] . '/api/admin_templates.php';
    if (file_exists($apiPath)) {
        // Set up the environment for the API
        $originalGet = $_GET;
        $_GET = array_merge(['action' => 'get_page_data', 'page' => $page], $query);
        
        // Capture output
        ob_start();
        include $apiPath;
        $output = ob_get_clean();
        
        // Restore original $_GET
        $_GET = $originalGet;
        
        $json = json_decode($output, true);
        if (is_array($json) && ($json['status'] ?? '') === 'ok' && isset($json['data'])) {
            return $json['data'];
        }
    }
    return [];
}
?>
<?php include __DIR__ . '/partials/layout_head.php'; ?>
        <?php include __DIR__ . '/partials/sidebar.php'; ?>

        <!-- Main Content -->
        <main class="main-content">
            <?php
            // Render the page using template system
            if ($page === 'onboarding') {
                // Onboarding page - load onboarding data and render template
                $onboardingData = fetch_admin_page_data('onboarding', $_GET);
                try {
                    $output = render_template_file($templatePath, array_merge($data ?? [], [
                        'onboarding' => $onboardingStatus,
                        'onboarding_data' => $onboardingData
                    ]));
                    echo $output ?: '<div class="error">Failed to render onboarding page</div>';
                } catch (Exception $e) {
                    echo '<div class="error">Error rendering onboarding: ' . htmlspecialchars($e->getMessage()) . '</div>';
                }
            } elseif ($page === 'dashboard' || $page === 'media' || $page === 'entry-editor') {
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
            } elseif ($page === 'content' || $page === 'settings' || $page === 'updates' || $page === 'entries' || $page === 'collection-editor' || $page === 'users' || $page === 'roles' || $page === 'plugins' || $page === 'audit-log' || $page === 'activity' || $page === 'webhooks') {
                // Content, Settings, Entries, Entry Editor, Collection Editor, Users, Roles, Plugins, Audit Log, Activity, and Webhooks pages use direct PHP instead of template parser
                $data = fetch_admin_page_data($page, $_GET);
                extract($data);
                include $templatePath;
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