<?php
// admin/login.php - Admin login page for Birkly

// CRITICAL: Set Content-Type header FIRST before anything else
// This ensures it's sent even if session_start() or includes output something
if (!headers_sent()) {
    header('Content-Type: text/html; charset=utf-8');
}

// CRITICAL: Start output buffering AFTER setting Content-Type
// This prevents any accidental output from interfering with headers
ob_start();

// Now include dependencies (output buffering will catch any output)
require_once __DIR__ . '/../core/birkly_paths.php';
require_once __DIR__ . '/../core/birkly_users.php';
require_once __DIR__ . '/../api/session.php';
haru_secure_session_start();

// Include CSRF helper
require_once __DIR__ . '/../core/csrf.php';
require_once __DIR__ . '/../core/i18n.php';
I18n::init();

function birkly_login_redirect_is_safe(string $redirect): bool
{
    if ($redirect === '') {
        return false;
    }

    if (preg_match('/^https?:\/\//i', $redirect)) {
        $host = parse_url($redirect, PHP_URL_HOST);
        $scheme = parse_url($redirect, PHP_URL_SCHEME);
        $expectedHost = $_SERVER['HTTP_HOST'] ?? '';
        $expectedScheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        if ($host !== $expectedHost || strtolower((string) $scheme) !== $expectedScheme) {
            return false;
        }
        $path = parse_url($redirect, PHP_URL_PATH) ?: '';
    } else {
        $path = $redirect;
    }

    return str_contains($path, '/oauth/authorize');
}

// SECURITY FIX: Set comprehensive security headers with CSP nonce (P16-M5)
require_once __DIR__ . '/../api/validate.php';
require_once __DIR__ . '/../core/csp.php';
if (!headers_sent()) {
    header('Content-Type: text/html; charset=utf-8');
    birkly_set_admin_csp_headers();
}
$__birklyLoginNonceAttr = birkly_csp_nonce_attr();

$error = '';
$success = '';
$notice = '';

// If user is already logged in AND has an allowed admin-panel role, redirect to dashboard.
// If they're logged in as a public/demo user (role like "member"), let them switch accounts here
// instead of causing a redirect loop between login and admin pages.
if (isset($_SESSION['user'])) {
    $currentRole = $_SESSION['user']['role'] ?? '';
    $allowedAdminRoles = ['admin', 'editor', 'viewer'];

    if (in_array($currentRole, $allowedAdminRoles, true)) {
        $redirect = trim((string) ($_GET['redirect'] ?? ''));
        if ($redirect !== '' && birkly_login_redirect_is_safe($redirect)) {
            if (ob_get_level() > 0) {
                ob_clean();
            }
            if (!headers_sent()) {
                http_response_code(302);
                header('Location: ' . $redirect);
            }
            exit;
        }

        // Clear output buffer if exists
        if (ob_get_level() > 0) {
            ob_clean();
        }
        if (!headers_sent()) {
            http_response_code(302);
            header('Location: ' . birkly_admin_url('dashboard'));
        }
        exit;
    }

    $currentEmail = $_SESSION['user']['email'] ?? ($_SESSION['user']['id'] ?? 'unknown');
    $notice = 'You are currently signed in as "' . $currentEmail . '" (role: ' . ($currentRole ?: 'unknown') . '). '
        . 'This account cannot access the admin panel. You can sign in below to switch accounts, or log out.';
}

// Check if no users exist - redirect to onboarding for first user creation
$GLOBALS['__HARU_INTERNAL_CALL'] = true;
require_once __DIR__ . '/../api/onboarding.php';
if (!hasUsers()) {
    // No users exist - redirect to onboarding to create first user
    if (ob_get_level() > 0) {
        ob_clean();
    }
    if (!headers_sent()) {
        http_response_code(302);
        header('Location: ' . birkly_admin_url('onboarding'));
    }
    exit;
}

// Ensure CSRF token exists for this session before handling POST
generate_csrf_token();

// Handle login form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $csrf = $_POST['_csrf'] ?? '';
    
    // Validate CSRF token
    if (!validate_csrf_token($csrf)) {
        generate_csrf_token();
        $error = 'Invalid security token. Please try again.';
    } elseif (empty($email) || empty($password)) {
        $error = 'Please enter both email and password.';
    } else {
        // Include authentication functions
        require_once __DIR__ . '/../api/auth.php';
        // Note: We don't need api/users.php here - login.php has its own attemptLogin() function
        
        // Attempt login
        $loginResult = attemptLogin($email, $password);
        
        if ($loginResult['success']) {
            // Check if 2FA is enabled for this user (per-user setting)
            // Use the same user storage + ID scheme as api/2fa.php / api/users.php
            $GLOBALS['__HARU_INTERNAL_CALL'] = true;
            require_once __DIR__ . '/../api/2fa.php';
            require_once __DIR__ . '/../core/security_policy.php';

            $normalizedEmail = trim(strtolower($email));

            $userFile = birkly_user_account_path($email);
            
            if ($userFile !== null && file_exists($userFile)) {
                $userData = json_decode(file_get_contents($userFile), true);
                if (is_array($userData)) {
                    $userData = birkly_save_user_with_2fa_policy($userData);
                    if (isset($userData['2fa']['enabled']) && $userData['2fa']['enabled']) {
                        // Store email in session for 2FA verification
                        $_SESSION['2fa_pending'] = $normalizedEmail;
                        // Don't keep full user session yet - require 2FA verification first
                        unset($_SESSION['user']);
                        // CRITICAL: Clear output buffer before redirect
                        if (ob_get_level() > 0) {
                            ob_clean();
                        }
                        if (!headers_sent()) {
                            http_response_code(302);
                            header('Location: ' . birkly_url('/admin/verify-2fa.php'));
                        }
                        exit;
                    }

                    if (birkly_user_2fa_blocked($userData)) {
                        $_SESSION['flash_2fa_policy'] = 'Two-factor authentication is required for all admin users. Enable 2FA in your account settings to continue.';
                        if (ob_get_level() > 0) {
                            ob_clean();
                        }
                        if (!headers_sent()) {
                            http_response_code(302);
                            header('Location: ' . birkly_admin_url('account', ['2fa_required' => '1']) . '#2fa');
                        }
                        exit;
                    }

                    $_SESSION['user'] = array_merge($_SESSION['user'] ?? [], $userData);
                    unset($_SESSION['user']['password']);
                }
            }
            
            // No per-user 2FA verification required — proceed with normal login
            // Redirect to dashboard or original page
            // Use absolute path to ensure correct routing
            $redirect = $_GET['redirect'] ?? birkly_admin_url('dashboard');
            if ($redirect !== '' && !birkly_login_redirect_is_safe($redirect)) {
                $redirect = birkly_admin_url('dashboard');
            }
            // If redirect is relative, make it absolute under the install base
            if (!preg_match('/^https?:\/\//', $redirect) && !str_starts_with($redirect, '/')) {
                $redirect = birkly_url('/admin/' . ltrim($redirect, '/'));
            }
            // CRITICAL: Clear ALL output buffers before redirect
            // We need to end all buffers to prevent any output from being sent
            while (ob_get_level() > 0) {
                ob_end_clean();
            }
            // Now set redirect header
            if (!headers_sent()) {
                http_response_code(302); // Explicitly set redirect status FIRST
                header('Location: ' . $redirect);
            }
            // Exit immediately - no output should be sent
            exit;
        } else {
            $error = $loginResult['message'];
        }
    }
}

// Helper function to attempt login
function attemptLogin($email, $password) {
    $user = birkly_load_user_account($email);
    if (!$user) {
        return ['success' => false, 'message' => 'Invalid email or password.'];
    }
    
    if (!password_verify($password, $user['password'] ?? '')) {
        return ['success' => false, 'message' => 'Invalid email or password.'];
    }
    
    // Login successful - set session
    unset($user['password']); // Don't store password in session
    session_regenerate_id(true);
    $_SESSION['user'] = $user;
    $_SESSION['last_activity'] = time();
    
    return ['success' => true, 'message' => 'Login successful.'];
}

// Helper function to sanitize filename
// Note: This function is also in api/2fa.php, so we check if it exists first
if (!function_exists('sanitizeFilename')) {
    function sanitizeFilename($filename) {
        return preg_replace('/[^a-z0-9._-]/i', '_', $filename);
    }
}

// Load branding settings for logo
require_once __DIR__ . '/../api/storage.php';
$storage = new Storage(__DIR__ . '/../settings');
$adminSettings = $storage->read('admin.json') ?? [];
$brandingSettings = $storage->read('branding.json') ?? [];

$projectName = $adminSettings['project_name'] ?? 'Birkly';
$logoPath = $brandingSettings['logoPath'] ?? '';
$defaultLogo = birkly_url('/admin/assets/birkly-icon.svg');
$logoUrl = !empty($logoPath) ? $logoPath : $defaultLogo;
?>
<!DOCTYPE html>
<html lang="<?= htmlspecialchars(getCurrentLanguage()) ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="<?php echo get_csrf_token(); ?>">
    <title><?= htmlspecialchars(__('admin.auth_login_title', ['project' => $projectName])) ?></title>
    <link rel="stylesheet" href="<?= htmlspecialchars(birkly_url('/admin/css/style.css')) ?>">
    <link rel="shortcut icon" href="<?= htmlspecialchars($logoUrl) ?>">
</head>
<body class="auth-page" data-birch>
    <main class="auth-shell">
        <section class="card auth-card">
            <div class="card-body u-stack-6">
                <div class="u-center-col u-stack-2">
                    <img class="auth-logo" src="<?= htmlspecialchars($logoUrl) ?>" alt="<?= htmlspecialchars($projectName) ?> logo">
                    <h1 class="auth-title"><?= htmlspecialchars(__('admin.auth_welcome_back')) ?></h1>
                    <p class="auth-subtitle"><?= htmlspecialchars(__('admin.auth_sign_in_subtitle', ['project' => $projectName])) ?></p>
                </div>

                <?php if ($error): ?>
                    <div class="callout callout-danger" role="alert">
                        <div class="callout-body"><p><?= htmlspecialchars($error) ?></p></div>
                    </div>
                <?php endif; ?>

                <?php if ($success): ?>
                    <div class="callout callout-success" role="status">
                        <div class="callout-body"><p><?= htmlspecialchars($success) ?></p></div>
                    </div>
                <?php endif; ?>

                <?php if ($notice): ?>
                    <div class="callout callout-warning" role="status">
                        <div class="callout-body u-stack-3">
                            <p><?= htmlspecialchars($notice) ?></p>
                            <form method="POST" action="<?= htmlspecialchars(birkly_url('/admin/logout.php')) ?>">
                                <input type="hidden" name="_csrf" value="<?= htmlspecialchars(get_csrf_token()) ?>">
                                <button type="submit" class="button is-small"><?= htmlspecialchars(__('admin.auth_log_out')) ?></button>
                            </form>
                        </div>
                    </div>
                <?php endif; ?>

                <form class="auth-form u-stack-4" id="login-form" method="POST" action="<?= htmlspecialchars(birkly_url('/admin/login.php')) ?>">
                    <input type="hidden" name="_csrf" value="<?= get_csrf_token() ?>">

                    <div class="field">
                        <label class="field-label" for="email"><?= htmlspecialchars(__('admin.auth_email_address')) ?></label>
                        <input
                            class="input"
                            type="email"
                            id="email"
                            name="email"
                            value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"
                            required
                            autocomplete="email"
                            placeholder="admin@example.com"
                        >
                    </div>

                    <div class="field">
                        <label class="field-label" for="password"><?= htmlspecialchars(__('admin.auth_password')) ?></label>
                        <input
                            class="input"
                            type="password"
                            id="password"
                            name="password"
                            required
                            autocomplete="current-password"
                            placeholder="<?= htmlspecialchars(__('admin.auth_password_placeholder')) ?>"
                        >
                    </div>

                    <button type="submit" class="button is-main btn-full u-mt-2" id="login-submit">
                        <?= htmlspecialchars(__('admin.auth_sign_in')) ?>
                    </button>
                </form>

                <div class="auth-footer u-stack-3">
                    <?php
                    require_once __DIR__ . '/../core/platform.php';
                    if (birkly_alpha_program_enabled()):
                        $termsUrl = birkly_legal_terms_url();
                        $privacyUrl = birkly_legal_privacy_url();
                    ?>
                    <p class="u-text-xs u-fg-secondary u-m-0">
                        <?= htmlspecialchars(birkly_program_status_label()) ?> ·
                        <a href="<?= htmlspecialchars($termsUrl) ?>" target="_blank" rel="noopener noreferrer"><?= htmlspecialchars(__('admin.auth_terms_link')) ?></a>
                        ·
                        <a href="<?= htmlspecialchars($privacyUrl) ?>" target="_blank" rel="noopener noreferrer"><?= htmlspecialchars(__('admin.auth_privacy_link')) ?></a>
                        ·
                        <a href="https://birkly.com/#waitlist" target="_blank" rel="noopener noreferrer"><?= htmlspecialchars(__('admin.auth_request_invite')) ?></a>
                    </p>
                    <?php endif; ?>
                    <p class="u-text-sm u-fg-secondary u-m-0"><?= htmlspecialchars(__('admin.auth_need_help')) ?></p>
                </div>
            </div>
        </section>
    </main>

    <script<?= $__birklyLoginNonceAttr ?>>
        // Auto-focus email field
        document.getElementById('email').focus();

        // Handle form submission
        document.getElementById('login-form').addEventListener('submit', function(e) {
            const button = document.getElementById('login-submit');
            button.textContent = 'Signing in…';
            button.disabled = true;
        });
    </script>
</body>
</html>
<?php
// End output buffering and send all output
ob_end_flush();
?>
