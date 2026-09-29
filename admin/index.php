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

require_once __DIR__ . '/../core/birkly_paths.php';
require_once __DIR__ . '/../core/icons.php';
require_once __DIR__ . '/../core/project_site.php';
require_once __DIR__ . '/../core/hosting_urls.php';
birkly_ensure_public_site_path();

// P43: canonical admin host — redirect /admin on public/marketing host to CMS URL
$requestedPage = isset($_GET['page']) && $_GET['page'] !== '' ? (string) $_GET['page'] : 'dashboard';
if (birkly_hosting_should_redirect_admin()) {
    $params = $_GET;
    unset($params['page']);
    header('Location: ' . birkly_hosting_admin_url($requestedPage, $params), true, 302);
    exit;
}

// Do not block onboarding HTML on outbound install registration.
// Other admin pages throttle to at most once per several hours (see platform_ops.php).
if ($requestedPage !== 'onboarding' && is_file(__DIR__ . '/../core/platform_ops.php')) {
    require_once __DIR__ . '/../core/platform_ops.php';
    birkly_ops_register_installation();
}

// Page access policy (UX + defense-in-depth).
// Backend endpoints remain the source of truth; this prevents opening pages a role shouldn't even view.
// $requestedPage set above for canonical host redirect
$pageViewPermissions = [
    // General
    'dashboard' => 'read',
    'help' => 'read',
    'feedback' => 'manage_settings',
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
    'ai-presence' => 'read',
    'agent-guide' => 'read',
    'discovery' => 'read',

    // Settings & admin management
    'settings' => 'read_settings',
    'redirects' => 'manage_settings',
    'webhooks' => 'read_settings',
    'updates' => 'manage_settings',
    'roles' => 'manage_settings',
    'users' => 'list_users',
    'site_users' => 'manage_users',
    'user_tiers' => 'manage_users',
    'user_groups' => 'manage_users',
    'plugins' => 'manage_plugins',
    'birch-kit' => 'manage_settings',

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
} elseif (function_exists('birkly_plugin_admin_page_permission')) {
    $pluginPerm = birkly_plugin_admin_page_permission($requestedPage);
    if (is_string($pluginPerm) && $pluginPerm !== '') {
        requireAdminPermission($pluginPerm);
    }
    if (function_exists('birkly_plugin_id_for_admin_page')) {
        require_once __DIR__ . '/../core/roles.php';
        $pluginPageId = birkly_plugin_id_for_admin_page($requestedPage);
        if ($pluginPageId !== null && isset($_SESSION['user'])
            && !birkly_user_plugin_allowed($_SESSION['user'], $pluginPageId)) {
            $redirect = birkly_admin_url('dashboard', [
                'permission_denied' => '1',
                'from' => $requestedPage,
                'reason' => 'plugin_access',
            ]);
            if (!headers_sent()) {
                header('Location: ' . $redirect);
                exit;
            }
        }
    }
}

// Include CSRF helper
require_once __DIR__ . '/../core/csrf.php';

// Initialize i18n system
require_once __DIR__ . '/../core/i18n.php';
I18n::init();

// Security headers set in layout_head (CSP nonce)
require_once __DIR__ . '/template_parser.php';

// Load plugin registry (admin pages registered via register_admin_page)
require_once __DIR__ . '/../core/plugins.php';
if (function_exists('load_plugins')) {
    load_plugins();
}
global $birkly_admin_pages;
if (!is_array($birkly_admin_pages)) {
    $birkly_admin_pages = [];
}

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
    // Never block the admin HTML shell on the remote starter catalog.
    // Onboarding JS loads availability via /api/onboarding.php?action=status.
    $onboardingStatus = getOnboardingStatus($userId, false);
}

// If first-time setup and onboarding not completed, redirect to welcome
$page = isset($_GET['page']) && $_GET['page'] !== '' ? $_GET['page'] : 'dashboard';
if ($page === 'redirects' || $page === 'roles' || $page === 'webhooks') {
    header('Location: ' . birkly_admin_url('settings') . '#' . $page, true, 302);
    exit;
}
if ($page === 'audit-log') {
    header('Location: ' . birkly_admin_url('activity'), true, 302);
    exit;
}
if ($page === 'agent-guide' || $page === 'ai-presence') {
    $params = $_GET;
    unset($params['page']);
    $params['tab'] = 'agent-guide';
    if (isset($params['zone'])) {
        $zone = (string) $params['zone'];
        if ($zone === 'tools') {
            $params['tab'] = 'webmcp';
        }
        unset($params['zone']);
    }
    header('Location: ' . birkly_admin_url('discovery', $params), true, 302);
    exit;
}
if ($page !== 'onboarding' && isset($_SESSION['user'])) {
    $userId = $_SESSION['user']['email'] ?? $_SESSION['user']['id'] ?? 'unknown';
    if (shouldRedirectToOnboarding($userId)) {
        $page = 'onboarding';
    }
}

        // Whitelist of allowed pages → template file map
        $allowedPages = [
            'dashboard' => 'dashboard.html',
            'content' => 'content.html',
            'media' => 'media.html',
            'ai-presence' => 'ai-presence.html',
            'agent-guide' => 'ai-presence.html',
            'discovery' => 'discovery.html',
            'settings' => 'settings.html',
            'redirects' => 'redirects.html',
            'account' => 'account.html',
            'updates' => 'updates.html',
            'entries' => 'entries.html',
            'entry-editor' => 'entry-editor.html',
            'entry-detail' => 'entry-detail.html',
            'collection-editor' => 'collection-editor.html',
            'users' => 'settings.html', // Users UI currently lives in settings template section
            'plugins' => 'plugins.html',
            'activity' => 'activity.html',
            'onboarding' => 'onboarding.html',
            'help' => 'help.html',
            'feedback' => 'feedback.html',
            'birch-kit' => 'birch-kit.php',
            'community_users' => null,
            'sample_plugin' => null,
        ];

// Plugin admin pages from register_admin_page() — null = include plugin HTML directly
foreach ($birkly_admin_pages as $pluginPageSlug => $pluginPageFile) {
    if (!array_key_exists($pluginPageSlug, $allowedPages)) {
        $allowedPages[$pluginPageSlug] = null;
    }
}

$isPluginAdminPage = array_key_exists($page, $birkly_admin_pages)
    && ($allowedPages[$page] ?? null) === null;

$templateFile = $allowedPages[$page] ?? 'dashboard.html';
$templatePath = $templateFile ? __DIR__ . '/templates/' . $templateFile : null;

// Fallback if template missing (never downgrade plugin admin pages)
if (!$isPluginAdminPage && (!$templatePath || !file_exists($templatePath))) {
    $page = 'dashboard';
    $templatePath = __DIR__ . '/templates/dashboard.html';
}

// Helper to build URL with query
function admin_url(string $page, array $params = []): string {
    return birkly_admin_url($page, $params);
}

function fetch_admin_page_data(string $page, array $query = []): array {
    if (function_exists('get_plugin_admin_meta_for_page')) {
        $pluginMeta = get_plugin_admin_meta_for_page($page);
        if ($pluginMeta !== null) {
            // P61: clear sidebar attention when viewing the plugin
            $pluginId = (string) ($pluginMeta['plugin_id'] ?? '');
            if ($pluginId !== '') {
                $activityFile = birkly_project_root() . '/core/plugin_activity.php';
                if (is_file($activityFile)) {
                    require_once $activityFile;
                    if (function_exists('birkly_plugin_activity_clear_nav')) {
                        birkly_plugin_activity_clear_nav($pluginId);
                    }
                }
            }
            return [
                'page' => $page,
                'plugin_admin' => $pluginMeta,
            ];
        }
    }

    // Content page: core loader only — never admin_templates.php
    if ($page === 'content') {
        $corePath = birkly_project_root() . '/core/content_page.php';
        if (!is_file($corePath)) {
            return [
                'error' => 'Content page core module missing (core/content_page.php).',
                'error_step' => 'core_missing',
            ];
        }
        try {
            require_once $corePath;
            return birkly_load_content_page_data_safe();
        } catch (Throwable $e) {
            error_log('Error in fetch_admin_page_data(content): ' . $e->getMessage());
            return birkly_content_page_error_from_exception($e, 'content_fetch');
        }
    }

    // Entries pages: load admin_templates in isolation with full error context
    if (in_array($page, ['entries', 'entry-detail', 'entry-editor'], true)) {
        $apiPath = birkly_project_root() . '/api/admin_templates.php';
        if (!is_file($apiPath)) {
            return ['error' => 'Admin templates API missing.', 'error_step' => 'admin_templates_missing'];
        }
        $GLOBALS['__HARU_INTERNAL_CALL'] = true;
        try {
            require_once $apiPath;
        } catch (Throwable $e) {
            error_log('Error including admin_templates.php for ' . $page . ': ' . $e->getMessage());
            return [
                'error' => $e->getMessage(),
                'error_step' => 'require_admin_templates',
                'error_class' => get_class($e),
                'error_file' => $e->getFile(),
                'error_line' => $e->getLine(),
            ];
        }
        if (!function_exists('getPageData')) {
            return ['error' => 'getPageData() unavailable.', 'error_step' => 'getPageData_missing'];
        }
        try {
            return getPageData($page);
        } catch (Throwable $e) {
            error_log("Error in getPageData('$page'): " . $e->getMessage());
            return [
                'error' => $e->getMessage(),
                'error_step' => 'getPageData',
                'error_class' => get_class($e),
                'error_file' => $e->getFile(),
                'error_line' => $e->getLine(),
            ];
        }
    }

    // Include the admin templates API directly to get the functions
    $apiPath = birkly_project_root() . '/api/admin_templates.php';
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
            } catch (Throwable $e) {
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
<?php
require_once __DIR__ . '/../core/csp.php';
birkly_set_admin_csp_headers();
$__birklyOnboardingNonceAttr = birkly_csp_nonce_attr();
?>
<!DOCTYPE html>
<html lang="<?= htmlspecialchars(getCurrentLanguage()) ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="<?php echo htmlspecialchars(get_csrf_token()); ?>">
    <title><?= htmlspecialchars(__('admin.onboarding_setup_title'), ENT_QUOTES, 'UTF-8') ?> - Birkly</title>
    <script<?= $__birklyOnboardingNonceAttr ?>>
      window.Birkly = window.Birkly || {};
      window.Birkly.basePath = <?= birkly_base_path_json() ?>;
      <?php
      if (class_exists('I18n')) {
          I18n::mergePluginCatalogs();
          $adminI18nData = I18n::getJsDictionary(null);
      } else {
          $adminI18nData = [];
      }
      ?>
      window.BirklyAdminI18n = <?= json_encode($adminI18nData, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
    </script>
    <link rel="stylesheet" href="<?= htmlspecialchars(birkly_url('/admin/css/style.css')) ?>">
    <link rel="stylesheet" href="<?= htmlspecialchars(birkly_url('/admin/css/onboarding.css')) ?>">
    <?php
    // Load settings to get logo for favicon
    require_once __DIR__ . '/../api/storage.php';
    $storage = new Storage(__DIR__ . '/../settings');
    $brandingSettings = $storage->read('branding.json') ?? [];
    $logoPath = $brandingSettings['logoPath'] ?? '';
    $faviconUrl = !empty($logoPath) ? $logoPath : birkly_url('/admin/assets/birkly-icon.svg');
    ?>
    <link rel="shortcut icon" href="<?= htmlspecialchars($faviconUrl) ?>">
</head>
<body class="onboarding-body" data-birch>
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
    <script src="<?= htmlspecialchars(birkly_url('/admin/js/birkly-paths.js')) ?>"<?= $__birklyOnboardingNonceAttr ?>></script>
    <script src="<?= htmlspecialchars(birkly_url('/admin/js/utils.js')) ?>"<?= $__birklyOnboardingNonceAttr ?>></script>
    <script src="<?= htmlspecialchars(birkly_url('/admin/js/i18n.js')) ?>"<?= $__birklyOnboardingNonceAttr ?>></script>
    <script src="<?= htmlspecialchars(birkly_url('/admin/js/ui-feedback.js')) ?>"<?= $__birklyOnboardingNonceAttr ?>></script>
    <script src="<?= htmlspecialchars(birkly_url('/admin/js/onboarding.js')) ?>"<?= $__birklyOnboardingNonceAttr ?>></script>
</body>
</html>
<?php else: ?>
<?php include __DIR__ . '/partials/layout_head.php'; ?>
        <?php include __DIR__ . '/partials/sidebar.php'; ?>

        <!-- Main Content -->
        <main class="main-content">
            <?php include __DIR__ . '/partials/admin-topbar.php'; ?>
            <?php include __DIR__ . '/partials/2fa-grace-banner.php'; ?>
            <?php
            $renderAdminError = static function (string $title, string $message, ?Throwable $e = null, array $context = []): void {
                echo '<div class="page-body u-stack-6"><div class="callout callout-danger callout--block">';
                echo '<div class="callout-body u-stack-3">';
                echo '<h2 class="callout-title">' . htmlspecialchars($title) . '</h2>';
                echo '<p>' . htmlspecialchars($message) . '</p>';
                if (!empty($context['error_step'])) {
                    echo '<p class="u-text-sm"><strong>Step:</strong> <code>' . htmlspecialchars((string) $context['error_step']) . '</code></p>';
                }
                if (!empty($context['error_file'])) {
                    echo '<p class="u-text-sm"><strong>File:</strong> <code>' . htmlspecialchars((string) $context['error_file']) . '</code>'
                        . (!empty($context['error_line']) ? ' <strong>line</strong> ' . (int) $context['error_line'] : '')
                        . '</p>';
                }
                if ($e !== null) {
                    echo '<pre>' . htmlspecialchars($e->getTraceAsString()) . '</pre>';
                }
                require_once __DIR__ . '/../core/build_info.php';
                $build = birkly_build_info();
                echo '<p class="u-text-sm"><strong>Deploy check:</strong> '
                    . 'git <code>' . htmlspecialchars((string) ($build['git_commit'] ?? 'unknown')) . '</code>, '
                    . 'content mode <code>' . htmlspecialchars((string) ($build['content_template_mode'] ?? '?')) . '</code>, '
                    . 'index bypass <code>' . htmlspecialchars((string) ($build['index_content_bypass'] ?? '?')) . '</code>. '
                    . '<a href="' . htmlspecialchars(birkly_url('/admin/deploy-check.php')) . '">Full diagnostics</a></p>';
                echo '<p class="u-text-sm u-fg-secondary">Pull the full Birkly repo root on the server (folders <code>admin/</code>, <code>api/</code>, <code>core/</code> as siblings), not only <code>admin/</code>.</p>';
                echo '<div><a href="' . htmlspecialchars(admin_url('dashboard')) . '" class="button is-main">Back to dashboard</a></div>';
                echo '</div></div></div>';
            };

            // Direct PHP templates — never use the directive parser (avoids syntax errors on live)
            $directPhpPages = [
                'entries' => 'entries.html',
                'entry-detail' => 'entry-detail.html',
                'entry-editor' => 'entry-editor.html',
                'collection-editor' => 'collection-editor.html',
            ];
            if ($page === 'content') {
                try {
                    $data = fetch_admin_page_data('content', $_GET);
                    if (isset($data['error'])) {
                        $renderAdminError('Error Loading Page', (string) $data['error'], null, $data);
                    } else {
                        if (!defined('__TEMPLATE_DIR__')) {
                            define('__TEMPLATE_DIR__', __DIR__ . '/templates');
                        }
                        extract($data, EXTR_SKIP);
                        include __DIR__ . '/templates/content.html';
                    }
                } catch (Throwable $e) {
                    $renderAdminError('Error Loading Page', $e->getMessage(), $e, [
                        'error_step' => 'include_content_template',
                        'error_file' => $e->getFile(),
                        'error_line' => $e->getLine(),
                    ]);
                }
            } elseif (isset($directPhpPages[$page])) {
                $templateFile = $directPhpPages[$page];
                try {
                    $data = fetch_admin_page_data($page, $_GET);
                    if (isset($data['error'])) {
                        $renderAdminError('Error Loading Page', (string) $data['error'], null, $data);
                    } else {
                        if (!defined('__TEMPLATE_DIR__')) {
                            define('__TEMPLATE_DIR__', __DIR__ . '/templates');
                        }
                        extract($data, EXTR_SKIP);
                        include __DIR__ . '/templates/' . $templateFile;
                    }
                } catch (Throwable $e) {
                    $renderAdminError('Error Loading Page', $e->getMessage(), $e, [
                        'error_step' => 'include_' . $page . '_template',
                        'error_file' => $e->getFile(),
                        'error_line' => $e->getLine(),
                    ]);
                }
            } elseif ($page === 'birch-kit') {
                require_once __DIR__ . '/../core/developer_mode.php';
                if (birkly_developer_mode_enabled()) {
                    include $templatePath;
                } else {
                    echo '<div class="empty-state"><p class="empty-state-title">Developer mode is off</p>';
                    echo '<p class="empty-state-text">Turn on Developer mode in Settings › System to open the Birch component kit.</p></div>';
                }
            } elseif ($page === 'dashboard' || $page === 'media' || $page === 'account') {
                // Pass query params so entry-editor receives collection/entry ids
                $data = fetch_admin_page_data($page, $_GET);
                
                // Merge session onboarding status into dashboard payload (keep checklist from API)
                if ($page === 'dashboard' && isset($data['onboarding']) && is_array($data['onboarding'])) {
                    $data['onboarding'] = array_merge($data['onboarding'], $onboardingStatus);
                } elseif ($page === 'dashboard') {
                    $data['onboarding'] = $onboardingStatus;
                }

                if ($page === 'dashboard') {
                    require_once __DIR__ . '/../core/birkly_paths.php';
                    $data['is_local_dev'] = birkly_is_local_dev_request();
                    $data['project_root'] = dirname(__DIR__);
                }
                
                // Check if there's an error in the data
                if (isset($data['error'])) {
                    echo '<div class="page-body u-stack-6"><div class="callout callout-danger callout--block"><div class="callout-body u-stack-3">';
                    echo '<h2 class="callout-title">Error Loading Page</h2>';
                    echo '<p>' . htmlspecialchars($data['error']) . '</p>';
                    echo '<div><a href="' . htmlspecialchars(admin_url('dashboard')) . '" class="button is-main">Back to dashboard</a></div>';
                    echo '</div></div></div>';
                } else {
                    try {
                        $output = render_template_file($templatePath, $data);
                        if (empty($output)) {
                            echo '<div class="page-body u-stack-6"><div class="callout callout-danger callout--block"><div class="callout-body u-stack-3">';
                            echo '<h2 class="callout-title">Error Rendering Template</h2>';
                            echo '<p>The template rendered empty output. This might be a template parsing error.</p>';
                            echo '<p><strong>Debug Info:</strong></p>';
                            echo '<pre>';
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
                            echo '<div><a href="' . htmlspecialchars(admin_url('dashboard')) . '" class="button is-main">Back to dashboard</a></div>';
                            echo '</div></div></div>';
                        } else {
                            echo $output;
                        }
                    } catch (Throwable $e) {
                        echo '<div class="page-body u-stack-6"><div class="callout callout-danger callout--block"><div class="callout-body u-stack-3">';
                        echo '<h2 class="callout-title">Template Rendering Error</h2>';
                        echo '<p>' . htmlspecialchars($e->getMessage()) . '</p>';
                        echo '<pre>' . htmlspecialchars($e->getTraceAsString()) . '</pre>';
                        echo '<div><a href="' . htmlspecialchars(admin_url('dashboard')) . '" class="button is-main">Back to dashboard</a></div>';
                        echo '</div></div></div>';
                    }
                }
            } elseif (in_array($page, [
                'settings', 'redirects',
                'users', 'roles', 'plugins', 'webhooks', 'help', 'feedback', 'audit-log', 'activity', 'updates',
                'ai-presence', 'agent-guide', 'discovery',
            ], true)) {
                // Settings, Entries, Entry Editor, Collection Editor, Users, Roles, Plugins, etc.
                try {
                    $data = fetch_admin_page_data($page, $_GET);
                    if (isset($data['error'])) {
                        echo '<div class="page-body u-stack-6"><div class="callout callout-danger callout--block"><div class="callout-body u-stack-3">';
                        echo '<h2 class="callout-title">Error Loading Page</h2>';
                        echo '<p>' . htmlspecialchars($data['error']) . '</p>';
                        echo '<div><a href="' . htmlspecialchars(admin_url('dashboard')) . '" class="button is-main">Back to dashboard</a></div>';
                        echo '</div></div></div>';
                    } else {
                        // Check if template uses PHP tags or template directives
                        $templateContent = file_get_contents($templatePath);
                        if (str_starts_with($templateContent, "\xEF\xBB\xBF")) {
                            $templateContent = substr($templateContent, 3);
                        }
                        
                        // PHP-first templates use direct include; directive-based templates use parser
                        $startsWithPhp = (strpos(ltrim($templateContent), '<?php') === 0);
                        $usesTemplateDirectives = (strpos($templateContent, '{translate') !== false
                            || strpos($templateContent, '{admin_url') !== false
                            || strpos($templateContent, '{for each') !== false
                            || strpos($templateContent, '{if ') !== false
                            || strpos($templateContent, '{json') !== false
                            || strpos($templateContent, '{include_script') !== false
                            || strpos($templateContent, '{include_style') !== false);

                        if ($usesTemplateDirectives) {
                            $output = render_template_file($templatePath, $data);
                            if (empty($output)) {
                                echo '<div class="page-body u-stack-6"><div class="callout callout-danger callout--block"><div class="callout-body u-stack-3">';
                                echo '<h2 class="callout-title">Error Rendering Template</h2>';
                                echo '<p>The template rendered empty output.</p>';
                                echo '<div><a href="' . htmlspecialchars(admin_url('dashboard')) . '" class="button is-main">Back to dashboard</a></div>';
                                echo '</div></div></div>';
                            } else {
                                echo $output;
                            }
                        } else {
                            extract($data, EXTR_SKIP);
                            include $templatePath;
                        }
                    }
                } catch (Throwable $e) {
                    echo '<div class="page-body u-stack-6"><div class="callout callout-danger callout--block"><div class="callout-body u-stack-3">';
                    echo '<h2 class="callout-title">Error Loading Page</h2>';
                    echo '<p>' . htmlspecialchars($e->getMessage()) . '</p>';
                    echo '<pre>' . htmlspecialchars($e->getTraceAsString()) . '</pre>';
                    echo '<div><a href="' . htmlspecialchars(admin_url('dashboard')) . '" class="button is-main">Back to dashboard</a></div>';
                    echo '</div></div></div>';
                }
            } elseif ($isPluginAdminPage) {
                $pluginPath = $birkly_admin_pages[$page] ?? '';
                if ($pluginPath !== '' && file_exists($pluginPath)) {
                    $pluginContent = file_get_contents($pluginPath);
                    $usesTemplateDirectives = (strpos($pluginContent, '{translate') !== false
                        || strpos($pluginContent, '{include_script') !== false
                        || strpos($pluginContent, '{admin_url') !== false
                        || strpos($pluginContent, '{birkly_url') !== false);
                    if ($usesTemplateDirectives && function_exists('render_template_file')) {
                        $data = fetch_admin_page_data($page, $_GET);
                        $output = render_template_file($pluginPath, $data ?? []);
                        echo $output ?: '<div class="callout callout-danger">Failed to render plugin page</div>';
                    } else {
                        include $pluginPath;
                    }
                } else {
                    echo '<div class="empty-state">';
                    echo '<p class="empty-state-title">Plugin Not Found</p>';
                    echo '<p class="empty-state-text">The plugin page could not be loaded.</p>';
                    echo '<div class="empty-state-actions"><a href="' . admin_url('dashboard') . '" class="button is-main">Back to dashboard</a></div>';
                    echo '</div>';
                }
            } else {
                // Show "coming soon" for unimplemented pages
                echo '<div class="empty-state">';
                echo '<p class="empty-state-title">Coming Soon</p>';
                echo '<p class="empty-state-text">The ' . htmlspecialchars(ucfirst($page)) . ' page is not implemented yet.</p>';
                echo '<div class="empty-state-actions"><a href="' . admin_url('dashboard') . '" class="button is-main">Back to dashboard</a></div>';
                echo '</div>';
            }
            ?>
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
