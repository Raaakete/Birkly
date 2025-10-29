<?php
// Simple query-based admin router (MPA)
// Usage: /admin/index.php?page=dashboard

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

$page = isset($_GET['page']) && $_GET['page'] !== '' ? $_GET['page'] : 'dashboard';

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
            if ($page === 'dashboard' || $page === 'media') {
                $data = fetch_admin_page_data($page);
                echo render_template_file($templatePath, $data);
            } elseif ($page === 'content' || $page === 'settings' || $page === 'updates' || $page === 'entries' || $page === 'entry-editor' || $page === 'collection-editor' || $page === 'users' || $page === 'roles' || $page === 'plugins') {
                // Content, Settings, Entries, Entry Editor, Collection Editor, Users, Roles, and Plugins pages use direct PHP instead of template parser
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