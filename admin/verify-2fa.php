<?php
// admin/verify-2fa.php - 2FA verification page

require_once __DIR__ . '/../core/birkly_paths.php';
require_once __DIR__ . '/../api/session.php';
haru_secure_session_start();

require_once __DIR__ . '/../core/csrf.php';
require_once __DIR__ . '/../core/i18n.php';
I18n::init();
// IMPORTANT: api/*.php files often auto-execute as JSON endpoints unless marked as internal.
// This page must be accessible before a full admin session exists.
$GLOBALS['__HARU_INTERNAL_CALL'] = true;
require_once __DIR__ . '/../api/2fa.php';
// NOTE: Do NOT include api/users.php here — it parses php://input as JSON on include
// and can break this form POST with "Invalid JSON input".

require_once __DIR__ . '/../api/validate.php';
require_once __DIR__ . '/../core/csp.php';
if (!headers_sent()) {
    header('Content-Type: text/html; charset=utf-8');
    birkly_set_admin_csp_headers();
}
$__birklyVerifyNonceAttr = birkly_csp_nonce_attr();

// Check if 2FA verification is pending
if (!isset($_SESSION['2fa_pending'])) {
    header('Location: login.php');
    exit;
}

$email = $_SESSION['2fa_pending'];
$error = '';
$success = '';

// Handle verification form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $code = trim($_POST['code'] ?? '');
    $csrf = $_POST['_csrf'] ?? '';
    // NOTE: use_backup_code is always present as a hidden input ("0"/"1")
    $useBackupCode = (($_POST['use_backup_code'] ?? '0') === '1');
    
    if (!validate_csrf_token($csrf)) {
        $error = __('admin.auth_2fa_error_token');
    } elseif (empty($code) && empty($_POST['backup_code'] ?? '')) {
        $error = __('admin.auth_2fa_error_empty');
    } else {
        // Verify 2FA code (use backup code field if available)
        $backupRaw = trim($_POST['backup_code'] ?? '');
        if ($useBackupCode && $backupRaw !== '') {
            // Backup codes are stored as 8 hex chars; accept with spaces/dashes and normalize
            $codeToVerify = strtoupper(preg_replace('/[^A-Fa-f0-9]/', '', $backupRaw));
        } else {
            // TOTP: normalize to digits only (users sometimes paste with spaces)
            $codeToVerify = preg_replace('/\D/', '', $code);
        }
        if (verify2FALogin($email, $codeToVerify, $useBackupCode)) {
            // 2FA verified - complete login
            $userFile = md5($email) . '.json';
            $usersDir = __DIR__ . '/../users';
            
            if (file_exists($usersDir . '/' . $userFile)) {
                $user = json_decode(file_get_contents($usersDir . '/' . $userFile), true);
                if ($user) {
                    unset($user['password']);
                    $_SESSION['user'] = $user;
                    $_SESSION['last_activity'] = time();
                    unset($_SESSION['2fa_pending']);
                    
                    // Redirect to dashboard
                    // Use absolute path to ensure correct routing
                    // Only a path on this site: no scheme, no protocol-relative //host, no backslash tricks.
                    $redirect = (string) ($_GET['redirect'] ?? '');
                    if (!preg_match('#^/(?![/\\\\])[^\\\\]*$#', $redirect)) {
                        $redirect = birkly_admin_url('dashboard');
                    }
                    header('Location: ' . $redirect);
                    exit;
                }
            }
            
            $error = __('admin.auth_2fa_error_login');
        } else {
            $error = __('admin.auth_2fa_error_invalid');
        }
    }
}
?>
<!DOCTYPE html>
<html lang="<?= htmlspecialchars(getCurrentLanguage()) ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="<?php echo get_csrf_token(); ?>">
    <title><?= htmlspecialchars(__('admin.auth_2fa_title')) ?> - Birkly</title>
    <link rel="stylesheet" href="<?= htmlspecialchars(birkly_url('/admin/css/style.css')) ?>">
    <link rel="shortcut icon" href="assets/birkly_favicon.svg">
</head>
<body class="auth-page" data-birch>
    <main class="auth-shell">
        <section class="card auth-card">
            <div class="card-body u-stack-6">
                <div class="u-center-col u-stack-2">
                    <h1 class="auth-title"><?= htmlspecialchars(__('admin.auth_2fa_title')) ?></h1>
                    <p class="auth-subtitle"><?= htmlspecialchars(__('admin.auth_2fa_subtitle')) ?></p>
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

                <form class="auth-form u-stack-4" method="POST" id="verify-form">
                    <input type="hidden" name="_csrf" value="<?= get_csrf_token() ?>">
                    <input type="hidden" name="use_backup_code" id="use_backup_code" value="0">

                    <div class="field" id="code-field">
                        <label class="field-label" for="code" id="code-label"><?= htmlspecialchars(__('admin.auth_2fa_code_label')) ?></label>
                        <input
                            class="input auth-code-input"
                            type="text"
                            id="code"
                            name="code"
                            required
                            autocomplete="one-time-code"
                            inputmode="numeric"
                            placeholder="000000"
                            maxlength="8"
                            autofocus
                        >
                    </div>

                    <div class="field" id="backup-code-section" hidden>
                        <label class="field-label" for="backup_code"><?= htmlspecialchars(__('admin.auth_2fa_backup_label')) ?></label>
                        <input
                            class="input auth-code-input u-mono"
                            type="text"
                            id="backup_code"
                            name="backup_code"
                            placeholder="XXXXXXXX"
                            maxlength="8"
                        >
                        <p class="field-help"><?= htmlspecialchars(__('admin.auth_2fa_backup_help')) ?></p>
                    </div>

                    <button type="submit" class="button is-main btn-full" id="verify-button" data-busy-label="<?= htmlspecialchars(__('admin.auth_2fa_verifying')) ?>"><?= htmlspecialchars(__('admin.auth_2fa_verify')) ?></button>

                    <p class="u-align-text-center u-m-0">
                        <button type="button" class="button is-quiet" id="toggle-backup" data-label-backup="<?= htmlspecialchars(__('admin.auth_2fa_use_backup')) ?>" data-label-app="<?= htmlspecialchars(__('admin.auth_2fa_use_app')) ?>"><?= htmlspecialchars(__('admin.auth_2fa_use_backup')) ?></button>
                    </p>
                </form>

                <div class="auth-footer">
                    <a class="u-text-sm" href="login.php"><?= htmlspecialchars(__('admin.auth_2fa_back_to_login')) ?></a>
                </div>
            </div>
        </section>
    </main>

    <script<?= $__birklyVerifyNonceAttr ?>>
        function toggleBackupCode() {
            const section = document.getElementById('backup-code-section');
            const codeField = document.getElementById('code-field');
            const codeInput = document.getElementById('code');
            const backupInput = document.getElementById('backup_code');
            const useBackupInput = document.getElementById('use_backup_code');
            const toggleLink = document.getElementById('toggle-backup');

            if (section.hidden) {
                section.hidden = false;
                codeField.hidden = true;
                codeInput.required = false;
                useBackupInput.value = '1';
                toggleLink.textContent = toggleLink.dataset.labelApp;
                backupInput.focus();
            } else {
                section.hidden = true;
                codeField.hidden = false;
                codeInput.required = true;
                useBackupInput.value = '0';
                toggleLink.textContent = toggleLink.dataset.labelBackup;
                codeInput.focus();
            }
        }

        document.getElementById('toggle-backup').addEventListener('click', toggleBackupCode);
        
        // Auto-format code input
        document.getElementById('code').addEventListener('input', function(e) {
            this.value = this.value.replace(/[^0-9]/g, '').substring(0, 6);
        });
        
        document.getElementById('backup_code').addEventListener('input', function(e) {
            this.value = this.value.toUpperCase().replace(/[^A-Z0-9]/g, '').substring(0, 8);
        });
        
        // Handle form submission
        document.getElementById('verify-form').addEventListener('submit', function(e) {
            const button = document.getElementById('verify-button');
            button.textContent = button.dataset.busyLabel;
            button.disabled = true;
        });
    </script>
</body>
</html>

